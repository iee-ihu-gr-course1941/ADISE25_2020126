<?php
// lib/game_logic.php

function handle_status($method, $input=null) {
    update_game_status();
    if($method=='GET') {
        show_status();
    } elseif($method=='POST') {
        if(isset($input['action']) && $input['action'] == 'start') {
            global $mysqli;
            $mysqli->query("CALL clean_board()");
            show_status();
        }
        elseif(isset($input['action']) && $input['action'] == 'roll_first') {
            handle_roll_first();
        }
        elseif(isset($input['action']) && $input['action'] == 'move') {
            $from = intval($input['from']);
            $to = intval($input['to']);
            // Προστασία αν δεν ορίστηκε color
            $col = isset($input['color']) ? $input['color'] : 'white'; 
            move_piece($from, $to, $col);
        }
        elseif(isset($input['action']) && $input['action'] == 'surrender') {
            surrender($input['color']);
        }
        else {
            roll_dice();
        }
    }
}

function show_status() {
    global $mysqli;
    $res = $mysqli->query("SELECT * FROM game_status LIMIT 1");
    if($res) {
        echo json_encode($res->fetch_assoc(), JSON_PRETTY_PRINT);
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Database error']);
    }
}

function reset_status() {
    global $mysqli;
    // Επαναφορά του game_status στην αρχική κατάσταση
    $sql = "UPDATE game_status SET status='not active', p_turn='W', dice1=NULL, dice2=NULL, result=NULL";
    $mysqli->query($sql);
}

function update_game_status() {
    global $mysqli;
    
    // 1. Διαβάζουμε την τρέχουσα κατάσταση
    $status = $mysqli->query("SELECT status FROM game_status")->fetch_assoc()['status'];
    
    // 2. Αν το παιχνίδι είναι 'started', ελέγχουμε για Timeout
    if($status == 'started') {
        // ΔΙΟΡΘΩΣΗ: Ζητάμε SELECT * (όλα τα πεδία) και όχι count, 
        // για να μπορούμε να διαβάσουμε το piece_color του παίκτη που κοιμήθηκε.
        $sql = "SELECT * FROM players WHERE last_action < (NOW() - INTERVAL 5 MINUTE) AND username IS NOT NULL";
        $st = $mysqli->prepare($sql);
        $st->execute();
        $res = $st->get_result();

        // Αν βρέθηκε παίκτης (δηλαδή η fetch_assoc επιστρέψει δεδομένα)
        if ($row = $res->fetch_assoc()) {
            // Βρήκαμε παίκτη που "κοιμήθηκε"!
            $sleeping_color = $row['piece_color']; // 'W' ή 'B'
            
            // Ο νικητής είναι ο αντίπαλος
            $winner = ($sleeping_color == 'W') ? 'B' : 'W';

            // α. Ενημερώνουμε το status σε aborted και ορίζουμε τον νικητή
            $mysqli->query("UPDATE game_status SET status='aborted', result='$winner', p_turn=NULL");

            // β. Διαγράφουμε ΜΟΝΟ τον παίκτη που άργησε
            $mysqli->query("UPDATE players SET username=NULL, token=NULL WHERE piece_color='$sleeping_color'");
        }
        
        // ΣΗΜΕΙΩΣΗ: Αφαίρεσα το if($res['c'] > 0)... ήταν λάθος και περιττό.
    }
    // 3. Αν το παιχνίδι ΔΕΝ είναι started, ελέγχουμε μήπως πρέπει να ξεκινήσει!
    else {
        $sql = "SELECT count(*) as c FROM players WHERE username IS NOT NULL";
        $result = $mysqli->query($sql)->fetch_assoc();
        
        if($result['c'] == 2) {
            // Θέτουμε status='started' και p_turn='W' ΜΟΝΟ την πρώτη φορά
            $mysqli->query("UPDATE game_status SET status='started', p_turn='W'");
        }
    }
}

function handle_roll_first() {
    global $mysqli;
    $res = $mysqli->query("SELECT dice1, dice2 FROM game_status LIMIT 1")->fetch_assoc();
    $d1 = $res['dice1'];
    $d2 = $res['dice2'];

    if ($d1 === NULL) {
        $d1 = rand(1, 6);
        $mysqli->query("UPDATE game_status SET dice1=$d1, status='first_roll'");
    } elseif ($d2 === NULL) {
        $d2 = rand(1, 6);
        if($d1 != $d2) {
            $start_turn = ($d1 > $d2) ? 'W' : 'B';
            // Ορίζουμε 2 κινήσεις αφού τα ζάρια είναι διαφορετικά
            $mysqli->query("UPDATE game_status SET dice2=$d2, status='started', p_turn='$start_turn', moves_left=2");
        } else {
            // Ισοπαλία - ξαναρίχνουν
            $mysqli->query("UPDATE game_status SET dice1=NULL, dice2=NULL"); 
        }
    }
    show_status();
}


function move_piece($from, $to, $playerColor) {
    global $mysqli;
    $status = $mysqli->query("SELECT * FROM game_status")->fetch_assoc();
    $pCode = ($playerColor == 'white') ? 'W' : 'B';
    
    if($status['p_turn'] != $pCode) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => 'Δεν είναι η σειρά σου!']); return;
    }

    // Ανίχνευση Μάνας (Πρώτο πούλι)
    $startPos = ($pCode == 'W') ? 24 : 12;
    $resStart = $mysqli->query("SELECT piece_count FROM board WHERE x=$startPos AND piece_color='$pCode'")->fetch_assoc();
    $isMana = ($resStart && $resStart['piece_count'] == 15);

    $diff = ($from - $to + 24) % 24;
    $d1 = $status['dice1']; $d2 = $status['dice2'];
    $dieUsed = null;

    // --- ΠΡΟΣΘΗΚΗ ΕΛΕΓΧΟΥ ΤΕΡΜΑΤΙΣΜΟΥ (LAP CONTROL) ---
    $is_invalid_lap = false;
    if ($pCode == 'W') {
        // Αν ο Άσπρος είναι στο 1-6 και πάει οπουδήποτε αλλού (wrap around)
        if ($from >= 1 && $from <= 6 && ($to > 6 || $to < 1)) $is_invalid_lap = true;
    } else {
        // Αν ο Μαύρος είναι στο 13-18 και βγει εκτός αυτών των ορίων
        if ($from >= 13 && $from <= 18 && ($to < 13 || $to > 18)) $is_invalid_lap = true;
    }

    if ($is_invalid_lap) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => "Δεν μπορείτε να ξαναπεράσετε την αφετηρία!"]); return;
    }

    // --- ΛΟΓΙΚΗ ΕΠΙΛΟΓΗΣ ΖΑΡΙΟΥ ---
    if ($isMana) {
        // Στην πρώτη κίνηση επιτρέπεται ΜΟΝΟ το άθροισμα (ή 6 αν είναι εξάρες)
        if ($d1 == 6 && $d2 == 6 && $diff == 6) $dieUsed = 'double';
        elseif ($d1 && $d2 && $diff == ($d1 + $d2)) $dieUsed = 'both';
    } else {
        // Κανονικό παιχνίδι: Άθροισμα, Ζάρι 1, Ζάρι 2 ή Διπλή
        if ($d1 && $d2 && $diff == ($d1 + $d2)) $dieUsed = 'both';
        elseif ($d1 == $diff) $dieUsed = 'dice1';
        elseif ($d2 == $diff) $dieUsed = 'dice2';
        elseif ($d1 == $d2 && $d1 == $diff) $dieUsed = 'double';
    }

    if (!$dieUsed) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => "Λάθος ζαριά! (Θες $diff, έχεις $d1,$d2)"]); return;
    }

    // Έλεγχος πιασμένης θέσης (Blocking)
    $stmt = $mysqli->prepare("SELECT piece_color, piece_count FROM board WHERE x=?");
    $stmt->bind_param("i", $to);
    $stmt->execute();
    $dest = $stmt->get_result()->fetch_assoc();
    if($dest && $dest['piece_count'] > 0 && $dest['piece_color'] != $pCode) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => 'Η θέση είναι πιασμένη!']); return;
    }

    // --- ΕΚΤΕΛΕΣΗ ΚΙΝΗΣΗΣ ---
    $mysqli->query("UPDATE board SET piece_count = piece_count - 1 WHERE x=$from");
    $mysqli->query("UPDATE board SET piece_color = NULL WHERE x=$from AND piece_count=0");
    $mysqli->query("INSERT INTO board (x, piece_color, piece_count) VALUES ($to, '$pCode', 1) 
                    ON DUPLICATE KEY UPDATE piece_count = piece_count + 1, piece_color='$pCode'");

    // --- ΔΙΑΧΕΙΡΙΣΗ ΣΕΙΡΑΣ ΚΑΙ ΖΑΡΙΩΝ ---
    $next = ($pCode == 'W') ? 'B' : 'W';

    if ($isMana) {
        // Η πρώτη κίνηση τελειώνει ΠΑΝΤΑ τη σειρά
        $mysqli->query("UPDATE game_status SET p_turn='$next', dice1=NULL, dice2=NULL, moves_left=0");
    } else {
        if ($dieUsed == 'both') {
            $new_moves = $status['moves_left'] - 2;
            $mysqli->query("UPDATE game_status SET dice1=NULL, dice2=NULL, moves_left=$new_moves");
        } elseif ($dieUsed == 'dice1') {
            $new_moves = $status['moves_left'] - 1;
            $mysqli->query("UPDATE game_status SET dice1=NULL, moves_left=$new_moves");
        } elseif ($dieUsed == 'dice2') {
            $new_moves = $status['moves_left'] - 1;
            $mysqli->query("UPDATE game_status SET dice2=NULL, moves_left=$new_moves");
        } else { // double
            $new_moves = $status['moves_left'] - 1;
            $mysqli->query("UPDATE game_status SET moves_left=$new_moves");
        }

        // Έλεγχος αν πρέπει να αλλάξει η σειρά
        $check = $mysqli->query("SELECT moves_left, dice1, dice2 FROM game_status")->fetch_assoc();
        if ($check['moves_left'] <= 0 || ($check['dice1'] == NULL && $check['dice2'] == NULL && $status['dice1'] != $status['dice2'])) {
            $mysqli->query("UPDATE game_status SET p_turn='$next', dice1=NULL, dice2=NULL, moves_left=0");
        }
    }

    $mysqli->query("UPDATE players SET last_action=NOW() WHERE piece_color='$pCode'");

    // Έλεγχος αν πρέπει να αλλάξει η σειρά (Είτε επειδή τελείωσαν οι κινήσεις είτε λόγω Deadlock)
    $check = $mysqli->query("SELECT moves_left, dice1, dice2 FROM game_status")->fetch_assoc();
    $still_has_moves = can_player_move($pCode);

    if ($check['moves_left'] <= 0 || !$still_has_moves) {
        $next = ($pCode == 'W') ? 'B' : 'W';
        $mysqli->query("UPDATE game_status SET p_turn='$next', dice1=NULL, dice2=NULL, moves_left=0");
        // Αν θέλεις alert στο frontend, μπορείς να προσθέσεις ένα flag στο JSON
    }

    show_status();
}

function roll_dice() {
    global $mysqli;
    // Έλεγχος αν υπάρχουν ήδη ζάρια που δεν παίχτηκαν
    $st = $mysqli->query("SELECT dice1 FROM game_status")->fetch_assoc();
    if($st['dice1'] != NULL) { show_status(); return; }
    
    $d1 = rand(1,6); 
    $d2 = rand(1,6);
    
    // Αν είναι διπλές -> 4 κινήσεις, αλλιώς 2
    $moves = ($d1 == $d2) ? 4 : 2;

    $mysqli->query("UPDATE game_status SET dice1=$d1, dice2=$d2, moves_left=$moves WHERE status='started'");

    // Έλεγχος αν ο παίκτης μπορεί να κουνήσει έστω και μία φορά με τη νέα ζαριά
    $status = $mysqli->query("SELECT p_turn FROM game_status")->fetch_assoc();
    if (!can_player_move($status['p_turn'])) {
        $next = ($status['p_turn'] == 'W') ? 'B' : 'W';
        $mysqli->query("UPDATE game_status SET p_turn='$next', dice1=NULL, dice2=NULL, moves_left=0");
    }
    
    show_status();
}

function surrender($loser_color) {
    global $mysqli;
    $winner_score_col = ($loser_color === 'white') ? 'score_b' : 'score_w';
    $mysqli->query("UPDATE game_status SET $winner_score_col = $winner_score_col + 1, result='aborted'");
    $mysqli->query("CALL clear_game()");
    show_status();
}


function can_player_move($pCode) {
    global $mysqli;
    $status = $mysqli->query("SELECT * FROM game_status")->fetch_assoc();
    $d1 = $status['dice1'];
    $d2 = $status['dice2'];
    
    if ($d1 === NULL && $d2 === NULL) return false;

    // Φέρνουμε όλα τα πούλια του παίκτη
    $res = $mysqli->query("SELECT x, piece_count FROM board WHERE piece_color='$pCode'");
    $pieces = $res->fetch_all(MYSQLI_ASSOC);

    foreach ($pieces as $p) {
        $from = $p['x'];
        $isMana = ($p['piece_count'] == 15 && (($pCode == 'W' && $from == 24) || ($pCode == 'B' && $from == 12)));

        // Δοκιμάζουμε τα διαθέσιμα ζάρια
        $dice_to_test = [];
        if ($isMana) {
            if ($d1 && $d2) $dice_to_test[] = $d1 + $d2; // Στη μάνα μόνο το άθροισμα
            if ($d1 == 6 && $d2 == 6) $dice_to_test[] = 6;
        } else {
            if ($d1) $dice_to_test[] = $d1;
            if ($d2) $dice_to_test[] = $d2;
            if ($d1 && $d2) $dice_to_test[] = $d1 + $d2;
        }

        foreach ($dice_to_test as $steps) {
            // Υπολογισμός στόχου
            $to = $from;
            for ($i = 0; $i < $steps; $i++) {
                $to--; if ($to < 1) $to = 24;
            }

            // Έλεγχος Lap Control (για να μην χαλάσουμε τον κανόνα τερματισμού)
            $invalid = false;
            if ($pCode == 'W' && $from >= 1 && $from <= 6 && ($to > 6 || $to < 1)) $invalid = true;
            if ($pCode == 'B' && $from >= 13 && $from <= 18 && ($to < 13 || $to > 18)) $invalid = true;
            
            if ($invalid) continue;

            // Έλεγχος αν η θέση είναι πιασμένη
            $dest = $mysqli->query("SELECT piece_color, piece_count FROM board WHERE x=$to")->fetch_assoc();
            if (!$dest || $dest['piece_count'] == 0 || $dest['piece_color'] == $pCode) {
                return true; // Βρέθηκε έστω και ΜΙΑ νόμιμη κίνηση!
            }
        }
    }
    return false; // Καμία νόμιμη κίνηση
}

function can_collect($color) {
    global $mysqli;
    if ($color == 'W') {
        // Υπάρχει έστω και ένα άσπρο πούλι ΕΞΩ από τις θέσεις 1-6;
        $res = $mysqli->query("SELECT count(*) as c FROM board WHERE piece_color='W' AND x > 6");
    } else {
        // Υπάρχει έστω και ένα μαύρο πούλι ΕΞΩ από τις θέσεις 13-18;
        // Στο Φεύγα ο Μαύρος κινείται 12->1 και μετά 24->13. Άρα το σπίτι του είναι το 13-18.
        $res = $mysqli->query("SELECT count(*) as c FROM board WHERE piece_color='B' AND (x < 13 OR x > 18)");
    }
    $row = $res->fetch_assoc();
    return ($row['c'] == 0); // True αν όλα είναι στο σπίτι
}


?>