<?php
// lib/game_logic.php

function handle_status($method, $input=null) {
    update_game_status();
    if($method=='GET') {
        show_status();
    } elseif($method=='POST') {
        try {
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
                $col = isset($input['color']) ? $input['color'] : 'white'; 
                move_piece($from, $to, $col);
            }
            elseif(isset($input['action']) && $input['action'] == 'surrender') {
                surrender($input['color']);
            }
            else {
                roll_dice();
            }
        } catch (Exception $e) {
            require_once('logger.php');
            app_log('handle status: ' . $e);
        }
    }
}

function show_status() {
    global $mysqli;
    $res = $mysqli->query("SELECT * FROM game_status LIMIT 1");
    if($res) {
        echo json_encode($res->fetch_assoc(), JSON_PRETTY_PRINT);
    }
}


function reset_status() {
    global $mysqli;
    $mysqli->query("UPDATE game_status SET status='not active', p_turn='W', dice1=NULL, dice2=NULL, result=NULL, moves_left=0");
}

function update_game_status() {
    global $mysqli;
    $res = $mysqli->query("SELECT status FROM game_status LIMIT 1");
    $status = $res->fetch_assoc()['status'];
    
    $sql_players = "SELECT count(*) as c FROM players WHERE username IS NOT NULL AND last_action > (NOW() - INTERVAL 5 MINUTE)";
    $active_players = $mysqli->query($sql_players)->fetch_assoc()['c'];

    // ΑΠΛΗ ΛΟΓΙΚΗ: 2 παίκτες = Ξεκινάμε με Άσπρα
    if ($status == 'not active' && $active_players == 2) {
        $mysqli->query("UPDATE game_status SET status='started', p_turn='W', moves_left=0, last_change=NOW()");
    }
    elseif ($status == 'started') {
        $sql_timeout = "SELECT piece_color FROM players WHERE last_action < (NOW() - INTERVAL 5 MINUTE) AND username IS NOT NULL";
        $res_timeout = $mysqli->query($sql_timeout);
        if ($row = $res_timeout->fetch_assoc()) {
            $sleeping_color = $row['piece_color'];
            $winner = ($sleeping_color == 'W') ? 'B' : 'W';
            $mysqli->query("UPDATE game_status SET status='aborted', result='$winner', p_turn=NULL");
            $mysqli->query("UPDATE players SET username=NULL, token=NULL WHERE piece_color='$sleeping_color'");
        }
    }
}

function handle_roll_first() {
    global $mysqli;
    $res = $mysqli->query("SELECT dice1, dice2 FROM game_status LIMIT 1")->fetch_assoc();
    $d1 = $res['dice1']; $d2 = $res['dice2'];

    if ($d1 === NULL) {
        $d1 = rand(1, 6);
        $mysqli->query("UPDATE game_status SET dice1=$d1, status='first_roll'");
    } elseif ($d2 === NULL) {
        $d2 = rand(1, 6);
        if($d1 != $d2) {
            $start_turn = ($d1 > $d2) ? 'W' : 'B';
            $mysqli->query("UPDATE game_status SET dice2=$d2, status='started', p_turn='$start_turn', moves_left=0");
        } else {
            $mysqli->query("UPDATE game_status SET dice1=NULL, dice2=NULL"); 
        }
    }
    show_status();
}

function roll_dice() {
    global $mysqli;
    $st = $mysqli->query("SELECT * FROM game_status LIMIT 1")->fetch_assoc();
    
    // Έλεγχος αν είναι η ΠΡΩΤΗ ζαριά του παιχνιδιού (όλα τα πούλια στις θέσεις τους)
    $res_white = $mysqli->query("SELECT piece_count FROM board WHERE x=24 AND piece_color='W'")->fetch_assoc();
    $res_black = $mysqli->query("SELECT piece_count FROM board WHERE x=12 AND piece_color='B'")->fetch_assoc();
    
    $is_first_roll = ($res_white['piece_count'] == 15 && $res_black['piece_count'] == 15);

    $d1 = rand(1,6); 
    $d2 = rand(1,6);
    
    if ($is_first_roll) {
        $moves = 1; // Μόνο μία κίνηση στην πρώτη ζαριά
    } else {
        $moves = ($d1 == $d2) ? 4 : 2;
    }

    $mysqli->query("UPDATE game_status SET dice1=$d1, dice2=$d2, moves_left=$moves WHERE status='started'");
    
    // Deadlock check
    if (!can_player_move($st['p_turn'])) {
        $next = ($st['p_turn'] == 'W') ? 'B' : 'W';
        $mysqli->query("UPDATE game_status SET p_turn='$next', dice1=NULL, dice2=NULL, moves_left=0");
    }
    show_status();
}

function move_piece($from, $to, $playerColor) {
    global $mysqli;
    $status = $mysqli->query("SELECT * FROM game_status LIMIT 1")->fetch_assoc();
    $pCode = ($playerColor == 'white') ? 'W' : 'B';

    if($status['p_turn'] != $pCode) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => "Δεν είναι η σειρά σου! Σειρά έχει ο " . $status['p_turn']]); return;
    }

    $diff = $from - $to;
    if($diff < 0) $diff += 24;

    // Lap Control
    $is_invalid_lap = false;
    if ($pCode == 'W') {
        if ($to > $from) $is_invalid_lap = true;
    } else {
        if ($from >= 13 && $from <= 24 && $to >= 1 && $to <= 12) $is_invalid_lap = true;
    }

    if ($is_invalid_lap) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => "Δεν μπορείτε να ξαναπεράσετε την αφετηρία!"]); return;
    }

    // Μάνα
    $startPos = ($pCode == 'W') ? 24 : 12;
    $resStart = $mysqli->query("SELECT piece_count FROM board WHERE x=$startPos AND piece_color='$pCode'")->fetch_assoc();
    $isMana = ($resStart && $resStart['piece_count'] == 15);
    if ($isMana && $from != $startPos) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => 'Πρέπει να κουνήσετε πρώτα τη Μάνα!']); return;
    }

    // --- ΛΟΓΙΚΗ ΕΠΙΛΟΓΗΣ ΖΑΡΙΟΥ (ΕΙΔΙΚΗ ΓΙΑ ΠΡΩΤΗ ΚΙΝΗΣΗ) ---
    $d1 = $status['dice1']; 
    $d2 = $status['dice2'];
    $dieUsed = null;
    $moves_to_subtract = 0;

    // ΕΛΕΓΧΟΣ: Είναι η πρώτη κίνηση του παίκτη; (15 πούλια στη Μάνα και moves_left=1)
    if ($isMana && $status['moves_left'] == 1) {
        if ($d1 == 6 && $d2 == 6) {
            // Αν είναι 6άρες, πρέπει η απόσταση να είναι ακριβώς 6
            if ($diff == 6) {
                $dieUsed = 'double';
                $moves_to_subtract = 1;
            }
        } else {
            // Σε κάθε άλλη περίπτωση, πρέπει η απόσταση να είναι το άθροισμα
            if ($diff == ($d1 + $d2)) {
                $dieUsed = 'both';
                $moves_to_subtract = 1;
            }
        }
    } 
    // ΚΑΝΟΝΙΚΟ ΠΑΙΧΝΙΔΙ (μετά την πρώτη κίνηση)
    else if ($d1 == $d2 && $d1 !== NULL) {
        if ($diff % $d1 == 0) {
            $needed = $diff / $d1;
            if ($needed <= $status['moves_left']) {
                $dieUsed = 'double';
                $moves_to_subtract = $needed;
            }
        }
    } else {
        if ($d1 && $d2 && $diff == ($d1 + $d2)) { 
            $dieUsed = 'both'; 
            $moves_to_subtract = 2; 
        } elseif ($d1 == $diff) { 
            $dieUsed = 'dice1'; 
            $moves_to_subtract = 1; 
        } elseif ($d2 == $diff) { 
            $dieUsed = 'dice2'; 
            $moves_to_subtract = 1; 
        }
    }

    if (!$dieUsed) {
        header("HTTP/1.1 400 Bad Request");
        // Πιο αναλυτικό μήνυμα για να ξέρεις τι φταίει
        $targetMsg = ($isMana && $status['moves_left'] == 1) ? (($d1==6 && $d2==6) ? "6" : ($d1+$d2)) : "έγκυρη ζαριά";
        echo json_encode(['error' => "Στην πρώτη κίνηση πρέπει να πάτε στη θέση $targetMsg!"]); return;
    }

    // Blocking
    $stmt = $mysqli->prepare("SELECT piece_color, piece_count FROM board WHERE x=?");
    $stmt->bind_param("i", $to);
    $stmt->execute();
    $dest = $stmt->get_result()->fetch_assoc();
    if($dest && $dest['piece_count'] > 0 && $dest['piece_color'] != $pCode) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => 'Η θέση είναι πιασμένη!']); return;
    }

    // Κίνηση
    $mysqli->query("UPDATE board SET piece_count = piece_count - 1 WHERE x=$from");
    $mysqli->query("UPDATE board SET piece_color = NULL WHERE x=$from AND piece_count=0");
    $mysqli->query("INSERT INTO board (x, piece_color, piece_count) VALUES ($to, '$pCode', 1) 
                    ON DUPLICATE KEY UPDATE piece_count = piece_count + 1, piece_color='$pCode'");

    $new_moves_left = $status['moves_left'] - $moves_to_subtract;
    $next_player = ($pCode == 'W') ? 'B' : 'W';

    if ($dieUsed == 'double') {
        $mysqli->query("UPDATE game_status SET moves_left = $new_moves_left");
        if ($new_moves_left <= 2) $mysqli->query("UPDATE game_status SET dice1=NULL");
        if ($new_moves_left <= 0) $mysqli->query("UPDATE game_status SET dice2=NULL");
    } else {
        if ($dieUsed == 'both') $mysqli->query("UPDATE game_status SET dice1=NULL, dice2=NULL, moves_left=0");
        elseif ($dieUsed == 'dice1') $mysqli->query("UPDATE game_status SET dice1=NULL, moves_left=$new_moves_left");
        elseif ($dieUsed == 'dice2') $mysqli->query("UPDATE game_status SET dice2=NULL, moves_left=$new_moves_left");
    }

    if ($new_moves_left <= 0) {
        $mysqli->query("UPDATE game_status SET p_turn='$next_player', dice1=NULL, dice2=NULL, moves_left=0");
    }
    $mysqli->query("UPDATE players SET last_action=NOW() WHERE piece_color='$pCode'");

    // Αυτόματο Deadlock check
    $status_after = $mysqli->query("SELECT * FROM game_status LIMIT 1")->fetch_assoc();
    if ($status_after['moves_left'] > 0 && !can_player_move($pCode)) {
        $mysqli->query("UPDATE game_status SET p_turn='$next_player', dice1=NULL, dice2=NULL, moves_left=0");
    }
    show_status();
}

function can_player_move($pCode) {
    global $mysqli;
    $status = $mysqli->query("SELECT * FROM game_status LIMIT 1")->fetch_assoc();
    $dice = array_filter([$status['dice1'], $status['dice2']]);
    if (empty($dice) && $status['moves_left'] == 0) return false;
    
    // Αν είναι διπλές και d1 υπάρχει, d2 είναι null αλλά έχουμε moves, d2 = d1
    if($status['dice1'] == $status['dice2'] && count($dice) == 1 && $status['moves_left'] > 0) {
        $dice[] = $status['dice1'];
    }

    $res = $mysqli->query("SELECT x, piece_count FROM board WHERE piece_color='$pCode'");
    $pieces = $res->fetch_all(MYSQLI_ASSOC);

    $startPos = ($pCode == 'W') ? 24 : 12;
    $resMana = $mysqli->query("SELECT piece_count FROM board WHERE x=$startPos AND piece_color='$pCode'")->fetch_assoc();
    $hasMana = ($resMana && $resMana['piece_count'] == 15);

    foreach ($pieces as $p) {
        if ($hasMana && $p['x'] != $startPos) continue;
        
        foreach ($dice as $val) {
            $from = $p['x'];
            $to = $from - $val;
            
            // Μαύρα Wrap-around
            if ($pCode == 'B' && $from <= 12 && $to < 1) $to += 24;

            // Lap Control
            $invalid = false;
            if ($pCode == 'W') { if ($to < 1 || $to > $from) $invalid = true; }
            else { if ($from >= 13 && $from <= 24 && $to < 13) $invalid = true; }
            
            if ($invalid) continue;

            $dest = $mysqli->query("SELECT piece_color, piece_count FROM board WHERE x=$to")->fetch_assoc();
            if (!$dest || $dest['piece_count'] == 0 || $dest['piece_color'] == $pCode) return true;
        }
    }
    return false;
}

function surrender($loser_color) {
    global $mysqli;
    $winner_score_col = ($loser_color === 'white') ? 'score_b' : 'score_w';
    $mysqli->query("UPDATE game_status SET $winner_score_col = $winner_score_col + 1, result='aborted'");
    $mysqli->query("CALL clear_game()");
    show_status();
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