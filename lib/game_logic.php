<?php
// lib/game_logic.php

function handle_status($method, $input=null) {
    update_game_status();
    if($method=='GET') { show_status(); } 
    elseif($method=='POST') {
        try {
            if(isset($input['action']) && $input['action'] == 'start') { 
                global $mysqli; $mysqli->query("CALL clean_board()"); 
                show_status(); 
            }
            elseif(isset($input['action']) && $input['action'] == 'roll_first') { 
                handle_roll_first(); 
            }
            elseif(isset($input['action']) && $input['action'] == 'move') { 
                move_piece(intval($input['from']), intval($input['to']), $input['color']); 
            }
            elseif(isset($input['action']) && $input['action'] == 'surrender') { 
                surrender($input['color']); 
            }
            elseif(isset($input['action']) && $input['action'] == 'pass') { 
                pass_turn(); 
            }
            elseif(isset($input['action']) && $input['action'] == 'reset_online') {
                global $mysqli; $my_col = $input['color']; $winner_code = ($my_col === 'white') ? 'B' : 'W'; $winner_score_col = ($winner_code === 'W') ? 'score_w' : 'score_b';
                $mysqli->query("UPDATE game_status SET $winner_score_col = $winner_score_col + 1");
                $mysqli->query("CALL clean_board()");
                $mysqli->query("UPDATE game_status SET status='started', result='RESTART_$winner_code', p_turn='W'");
                show_status();
            }
            elseif(isset($input['action']) && $input['action'] == 'collect') {
                collect_piece(intval($input['from']), $input['color']);
            }
            elseif(isset($input['action']) && $input['action'] == 'clear_result') { 
                global $mysqli; $mysqli->query("UPDATE game_status SET result=NULL"); 
                show_status(); 
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

function show_status() { global $mysqli; $res = $mysqli->query("SELECT * FROM game_status LIMIT 1"); if($res) { echo json_encode($res->fetch_assoc(), JSON_PRETTY_PRINT); } }

function update_game_status() {
    global $mysqli; 
    $res = $mysqli->query("SELECT status FROM game_status LIMIT 1"); 
    $status = $res->fetch_assoc()['status'];
    $sql_players = "SELECT count(*) as c FROM players WHERE username IS NOT NULL AND last_action > (NOW() - INTERVAL 15 MINUTE)";
    $active_players = $mysqli->query($sql_players)->fetch_assoc()['c'];

    if ($status == 'not active' && $active_players == 2) { 
        $mysqli->query("UPDATE game_status SET status='started', p_turn='W', moves_left=0, last_change=NOW()"); 
    }
    elseif ($status == 'started') {
        $sql_timeout = "SELECT piece_color FROM players WHERE last_action < (NOW() - INTERVAL 15 MINUTE) AND username IS NOT NULL"; 
        $res_timeout = $mysqli->query($sql_timeout);
        if ($row = $res_timeout->fetch_assoc()) { 
            $sleeping_color = $row['piece_color']; 
            $winner = ($sleeping_color == 'W') ? 'B' : 'W'; 
            $mysqli->query("UPDATE game_status SET status='aborted', result='$winner', p_turn=NULL"); 
        }
    }
}

function roll_dice() {
    global $mysqli; 
    $st = $mysqli->query("SELECT * FROM game_status LIMIT 1")->fetch_assoc(); 
    $pTurn = $st['p_turn'];
    $res_white = $mysqli->query("SELECT piece_count FROM board WHERE x=24 AND piece_color='W'")->fetch_assoc();
    $res_black = $mysqli->query("SELECT piece_count FROM board WHERE x=12 AND piece_color='B'")->fetch_assoc();
    $is_first_move = ($pTurn == 'W' && $res_white['piece_count'] == 15) || ($pTurn == 'B' && $res_black['piece_count'] == 15);
    $d1 = rand(1,6); $d2 = rand(1,6); $moves = ($is_first_move) ? 1 : (($d1 == $d2) ? 4 : 2);
    $mysqli->query("UPDATE game_status SET dice1=$d1, dice2=$d2, moves_left=$moves, last_change=NOW() WHERE status='started'");
    show_status();
}

function pass_turn() { 
    global $mysqli; 
    $status = $mysqli->query("SELECT p_turn FROM game_status LIMIT 1")->fetch_assoc(); 
    $next = ($status['p_turn'] == 'W') ? 'B' : 'W';
    $mysqli->query("UPDATE game_status SET p_turn='$next', dice1=NULL, dice2=NULL, moves_left=0, last_change=NOW()"); 
    show_status(); 
}

// Βοηθητική συνάρτηση για έλεγχο αν μια θέση είναι πιασμένη από τον αντίπαλο
function is_pos_blocked($pos, $myCode) {
    global $mysqli;
    $res = $mysqli->query("SELECT piece_color, piece_count FROM board WHERE x=$pos")->fetch_assoc();
    return ($res && $res['piece_count'] > 0 && $res['piece_color'] != $myCode);
}

// Βοηθητική συνάρτηση για υπολογισμό θέσης (handling wrap-around για τα μαύρα)
function calculate_target($start, $steps, $color) {
    $t = $start - $steps;
    if ($color == 'B') {
        // Αν είναι στο πρώτο μισό (12-1) και βγαίνει κάτω από το 1, κάνει wrap στο 24
        if ($start <= 12 && $t < 1) { 
            $t += 24; 
        } 
        // Αν είναι στο δεύτερο μισό (24-13) και πάει να πέσει κάτω από το 13, 
        // αυτό ΔΕΝ είναι wrap, είναι έξοδος ή άκυρο για την move_piece
    }
    return $t;
}

function move_piece($from, $to, $playerColor) {
    global $mysqli; 
    $status = $mysqli->query("SELECT * FROM game_status LIMIT 1")->fetch_assoc(); 
    $pCode = ($playerColor == 'white') ? 'W' : 'B';
    if($status['p_turn'] != $pCode) { header("HTTP/1.1 400 Bad Request"); echo json_encode(['error' => "Δεν είναι η σειρά σου!"]); return; }

    $dist = $from - $to; if($dist < 0) $dist += 24;
    $d1 = $status['dice1']; $d2 = $status['dice2'];
    $dieUsed = null; $moves_to_subtract = 0;

    // 1. Lap Control (ΑΥΣΤΗΡΟ ΦΡΑΓΜΑ)
    $invalid = false;
    if ($pCode == 'W') { 
        if ($to >= $from || $to < 1) $invalid = true; 
    } else { 
        if ($from <= 12) { 
            if ($to > 12 && $to <= $from) $invalid = true; 
        } else { 
            // Αν είναι στο 24-13, δεν επιτρέπεται να πέσει στο 1-12
            if ($to <= 12 || $to >= $from) $invalid = true; 
        }
    }
    if ($invalid) { header("HTTP/1.1 400 Bad Request"); echo json_encode(['error' => "Μη έγκυρη διαδρομή!"]); return; }

    // Helper για blocking
    $is_blocked = function($pos) use ($mysqli, $pCode) {
        $res = $mysqli->query("SELECT piece_color, piece_count FROM board WHERE x=$pos")->fetch_assoc();
        return ($res && $res['piece_count'] > 0 && $res['piece_color'] != $pCode);
    };

    // 2. Μάνα check
    $startPos = ($pCode == 'W') ? 24 : 12;
    $resStart = $mysqli->query("SELECT piece_count FROM board WHERE x=$startPos AND piece_color='$pCode'")->fetch_assoc();
    if ($resStart && $resStart['piece_count'] == 15 && $from != $startPos) { header("HTTP/1.1 400 Bad Request"); echo json_encode(['error' => 'Πρέπει να κουνήσετε πρώτα τη Μάνα!']); return; }

    // 3. Λογική Ζαριών
    if ($resStart && $resStart['piece_count'] == 15 && $status['moves_left'] == 1) {
        $val = ($d1 == 6 && $d2 == 6) ? 6 : ($d1 + $d2);
        if ($dist == $val) {
            if ($val > 6) {
                $s1 = calculate_target($from, $d1, $pCode); $s2 = calculate_target($from, $d2, $pCode);
                if ($is_blocked($s1) && $is_blocked($s2)) { header("HTTP/1.1 400 Bad Request"); echo json_encode(['error' => "Ενδιάμεσες στάσεις πιασμένες!"]); return; }
            }
            $dieUsed = 'both'; $moves_to_subtract = 1;
        }
    } 
    else if ($d1 == $d2 && $d1 !== NULL) { // Διπλές
        if ($dist % $d1 == 0) {
            $steps = $dist / $d1;
            if ($steps <= $status['moves_left']) {
                for ($i = 1; $i <= $steps; $i++) {
                    if ($is_blocked(calculate_target($from, $i * $d1, $pCode))) { header("HTTP/1.1 400 Bad Request"); echo json_encode(['error' => "Εμπόδιο στη διαδρομή!"]); return; }
                }
                $dieUsed = 'double'; $moves_to_subtract = $steps;
            }
        }
    } 
    else { // Απλές
        if ($d1 && $d2 && $dist == ($d1 + $d2)) {
            $s1 = calculate_target($from, $d1, $pCode); $s2 = calculate_target($from, $d2, $pCode);
            if ($is_blocked($s1) && $is_blocked($s2)) { header("HTTP/1.1 400 Bad Request"); echo json_encode(['error' => "Ενδιάμεσες στάσεις πιασμένες!"]); return; }
            $dieUsed = 'both'; $moves_to_subtract = 2;
        }
        elseif ($d1 == $dist) { $dieUsed = 'dice1'; $moves_to_subtract = 1; }
        elseif ($d2 == $dist) { $dieUsed = 'dice2'; $moves_to_subtract = 1; }
    }

    if (!$dieUsed || $is_blocked($to)) { header("HTTP/1.1 400 Bad Request"); echo json_encode(['error' => "Μη έγκυρη κίνηση!"]); return; }

    // Εκτέλεση κίνησης
    $mysqli->query("UPDATE board SET piece_count = piece_count - 1 WHERE x=$from"); 
    $mysqli->query("UPDATE board SET piece_color = NULL WHERE x=$from AND piece_count=0");
    $mysqli->query("INSERT INTO board (x, piece_color, piece_count) VALUES ($to, '$pCode', 1) ON DUPLICATE KEY UPDATE piece_count = piece_count + 1, piece_color='$pCode'");

    // Ενημέρωση κινήσεων
    $new_moves = $status['moves_left'] - $moves_to_subtract;
    if ($dieUsed == 'double') { $mysqli->query("UPDATE game_status SET moves_left = $new_moves"); if ($new_moves <= 0) $mysqli->query("UPDATE game_status SET dice1=NULL, dice2=NULL"); }
    else { if ($dieUsed == 'both' || $new_moves <= 0) $mysqli->query("UPDATE game_status SET dice1=NULL, dice2=NULL, moves_left=0"); elseif ($dieUsed == 'dice1') $mysqli->query("UPDATE game_status SET dice1=NULL, moves_left=$new_moves"); elseif ($dieUsed == 'dice2') $mysqli->query("UPDATE game_status SET dice2=NULL, moves_left=$new_moves"); }
    if ($new_moves <= 0) { $next = ($pCode == 'W') ? 'B' : 'W'; $mysqli->query("UPDATE game_status SET p_turn='$next', moves_left=0, dice1=NULL, dice2=NULL"); }
    $mysqli->query("UPDATE players SET last_action=NOW() WHERE piece_color='$pCode'");
    show_status();
}

function handle_roll_first() {
    global $mysqli; 
    $res = $mysqli->query("SELECT dice1, dice2 FROM game_status LIMIT 1")->fetch_assoc(); 
    $d1 = $res['dice1']; $d2 = $res['dice2'];
    if ($d1 === NULL) { $d1 = rand(1, 6); $mysqli->query("UPDATE game_status SET dice1=$d1, status='first_roll'"); }
    elseif ($d2 === NULL) { 
        $d2 = rand(1, 6); 
        if($d1 != $d2) { $start = ($d1 > $d2) ? 'W' : 'B'; $mysqli->query("UPDATE game_status SET dice2=$d2, status='started', p_turn='$start', moves_left=0"); } 
        else { $mysqli->query("UPDATE game_status SET dice1=NULL, dice2=NULL"); }
    } 
    show_status();
}

function can_collect($color) {
    global $mysqli;
    if ($color == 'W') $res = $mysqli->query("SELECT count(*) as c FROM board WHERE piece_color='W' AND x > 6");
    else $res = $mysqli->query("SELECT count(*) as c FROM board WHERE piece_color='B' AND (x < 13 OR x > 18)");
    return ($res->fetch_assoc()['c'] == 0);
}

function collect_piece($from, $playerColor) {
    global $mysqli;
    $status = $mysqli->query("SELECT * FROM game_status LIMIT 1")->fetch_assoc();
    $pCode = ($playerColor == 'white') ? 'W' : 'B';
    if($status['p_turn'] != $pCode) return;

    $dist = ($pCode == 'W') ? $from : ($from - 12);
    $d1 = $status['dice1']; $d2 = $status['dice2'];
    $dieUsed = null;
    $dice = ($d1 == $d2 && $d1 !== null) ? [$d1, $d1] : [$d1, $d2];

    foreach ($dice as $idx => $val) {
        if ($val >= $dist) { // ΚΑΝΟΝΑΣ >=
            $dieUsed = ($d1 == $d2) ? 'double' : ($idx == 0 ? 'dice1' : 'dice2');
            break;
        }
    }

    if (!$dieUsed) return;

    $mysqli->query("UPDATE board SET piece_count = piece_count - 1 WHERE x=$from");
    $mysqli->query("UPDATE board SET piece_color = NULL WHERE x=$from AND piece_count=0");
    $col_off = ($pCode == 'W') ? 'w_off' : 'b_off';
    $mysqli->query("UPDATE game_status SET $col_off = $col_off + 1");

    $new_moves = $status['moves_left'] - 1;
    if ($dieUsed == 'double') {
        $mysqli->query("UPDATE game_status SET moves_left = $new_moves");
        if ($new_moves <= 0) $mysqli->query("UPDATE game_status SET dice1=NULL, dice2=NULL");
    } else {
        $mysqli->query("UPDATE game_status SET $dieUsed = NULL, moves_left = $new_moves");
    }

    if ($new_moves <= 0) {
        $next = ($pCode == 'W') ? 'B' : 'W';
        $mysqli->query("UPDATE game_status SET p_turn='$next', moves_left=0, dice1=NULL, dice2=NULL");
    }

    // --- ΕΛΕΓΧΟΣ ΝΙΚΗΣ (Μέσα στη collect_piece) ---
    $resWin = $mysqli->query("SELECT w_off, b_off, score_w, score_b FROM game_status LIMIT 1")->fetch_assoc();
    
    if ($resWin['w_off'] == 15 || $resWin['b_off'] == 15) {
        $winnerCode = ($resWin['w_off'] == 15) ? 'W' : 'B';
        // Αυξάνουμε το σκορ του νικητή στη βάση
        $scoreCol = ($winnerCode == 'W') ? 'score_w' : 'score_b';
        $mysqli->query("UPDATE game_status SET status='ended', result='$winnerCode', $scoreCol = $scoreCol + 1");
    }
    show_status();
}

function can_collect_backwards($color, $currentX) {
    global $mysqli;
    // Ψάχνουμε πούλια ΠΙΟ ΠΙΣΩ από το τρέχον (πιο μακριά από την έξοδο)
    $limit = ($color == 'W') ? 6 : 18;
    $res = $mysqli->query("SELECT count(*) as c FROM board WHERE piece_color='$color' AND x > $currentX AND x <= $limit");
    $row = $res->fetch_assoc();
    return ($row['c'] > 0);
}

function surrender($loser) { 
    global $mysqli; 
    $winner_col = ($loser === 'white') ? 'score_b' : 'score_w'; 
    $mysqli->query("UPDATE game_status SET $winner_col = $winner_col + 1, result='aborted'"); 
    $mysqli->query("CALL clear_game()"); 
    show_status(); 
}
?>