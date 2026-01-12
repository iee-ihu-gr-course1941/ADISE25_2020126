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
            elseif(isset($input['action']) && $input['action'] == 'pass') {
                pass_turn();
            }
            elseif(isset($input['action']) && $input['action'] == 'reset_online') {
                global $mysqli;
                $my_col = $input['color'];
                $winner_code = ($my_col === 'white') ? 'B' : 'W';
                $winner_score_col = ($winner_code === 'W') ? 'score_w' : 'score_b';
                
                $mysqli->query("UPDATE game_status SET $winner_score_col = $winner_score_col + 1");
                $mysqli->query("CALL clean_board()");
                $mysqli->query("UPDATE game_status SET status='started', result='$winner_code', p_turn='W'");
                show_status();
            }
            elseif(isset($input['action']) && $input['action'] == 'clear_result') {
                global $mysqli;
                $mysqli->query("UPDATE game_status SET result=NULL");
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

function show_status() {
    global $mysqli;
    $res = $mysqli->query("SELECT * FROM game_status LIMIT 1");
    if($res) {
        echo json_encode($res->fetch_assoc(), JSON_PRETTY_PRINT);
    }
}

function update_game_status() {
    global $mysqli;
    $res = $mysqli->query("SELECT status FROM game_status LIMIT 1");
    $status = $res->fetch_assoc()['status'];
    
    // Όριο 15 λεπτών για αδράνεια
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

    // Έλεγχος αν ο παίκτης έχει ακόμα 15 πούλια στη βάση του
    $res_white = $mysqli->query("SELECT piece_count FROM board WHERE x=24 AND piece_color='W'")->fetch_assoc();
    $res_black = $mysqli->query("SELECT piece_count FROM board WHERE x=12 AND piece_color='B'")->fetch_assoc();
    
    $is_first_move = ($pTurn == 'W' && $res_white['piece_count'] == 15) || 
                     ($pTurn == 'B' && $res_black['piece_count'] == 15);

    $d1 = rand(1,6); 
    $d2 = rand(1,6);
    
    // Πρώτη κίνηση = 1 κίνηση, αλλιώς 2 ή 4 (διπλές)
    $moves = ($is_first_move) ? 1 : (($d1 == $d2) ? 4 : 2);

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

function move_piece($from, $to, $playerColor) {
    global $mysqli;
    $status = $mysqli->query("SELECT * FROM game_status LIMIT 1")->fetch_assoc();
    $pCode = ($playerColor == 'white') ? 'W' : 'B';

    if($status['p_turn'] != $pCode) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => "Δεν είναι η σειρά σου!"]); return;
    }

    $diff = $from - $to;
    if($diff < 0) $diff += 24;

    // Lap Control (ΑΥΣΤΗΡΟ)
    $is_invalid_lap = false;
    if ($pCode == 'W') {
        if ($to > $from || $to < 1) $is_invalid_lap = true;
    } else {
        // Μαύρα: Δεν μπορούν να περάσουν από το 13-24 πίσω στο 1-12
        if ($from >= 13 && $from <= 24 && $to >= 1 && $to <= 12) $is_invalid_lap = true;
        if ($to > 24 || $to < 1) $is_invalid_lap = true;
    }

    if ($is_invalid_lap) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => "Δεν μπορείτε να ξαναπεράσετε την αφετηρία!"]); return;
    }

    // Μάνα
    $startPos = ($pCode == 'W') ? 24 : 12;
    $resStart = $mysqli->query("SELECT piece_count FROM board WHERE x=$startPos AND piece_color='$pCode'")->fetch_assoc();
    if ($resStart && $resStart['piece_count'] == 15 && $from != $startPos) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => 'Πρέπει να κουνήσετε πρώτα τη Μάνα!']); return;
    }

    $d1 = $status['dice1']; $d2 = $status['dice2'];
    $dieUsed = null; $moves_to_subtract = 0;

    // Πρώτη κίνηση Μάνας (moves_left == 1)
    if ($resStart && $resStart['piece_count'] == 15 && $status['moves_left'] == 1) {
        if ($d1 == 6 && $d2 == 6) {
            if ($diff == 6) { $dieUsed = 'double'; $moves_to_subtract = 1; }
        } else {
            if ($diff == ($d1 + $d2)) { $dieUsed = 'both'; $moves_to_subtract = 1; }
        }
    } 
    else if ($d1 == $d2 && $d1 !== NULL) {
        if ($diff % $d1 == 0) {
            $needed = $diff / $d1;
            if ($needed <= $status['moves_left']) { $dieUsed = 'double'; $moves_to_subtract = $needed; }
        }
    } else {
        if ($d1 && $d2 && $diff == ($d1 + $d2)) { $dieUsed = 'both'; $moves_to_subtract = 2; }
        elseif ($d1 == $diff) { $dieUsed = 'dice1'; $moves_to_subtract = 1; }
        elseif ($d2 == $diff) { $dieUsed = 'dice2'; $moves_to_subtract = 1; }
    }

    if (!$dieUsed) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => "Μη έγκυρη κίνηση!"]); return;
    }

    // Blocking
    $stmt = $mysqli->prepare("SELECT piece_color, piece_count FROM board WHERE x=?");
    $stmt->bind_param("i", $to); $stmt->execute();
    $dest = $stmt->get_result()->fetch_assoc();
    if($dest && $dest['piece_count'] > 0 && $dest['piece_color'] != $pCode) {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['error' => 'Η θέση είναι πιασμένη!']); return;
    }

    // Εκτέλεση κίνησης
    $mysqli->query("UPDATE board SET piece_count = piece_count - 1 WHERE x=$from");
    $mysqli->query("UPDATE board SET piece_color = NULL WHERE x=$from AND piece_count=0");
    $mysqli->query("INSERT INTO board (x, piece_color, piece_count) VALUES ($to, '$pCode', 1) ON DUPLICATE KEY UPDATE piece_count = piece_count + 1, piece_color='$pCode'");

    $new_moves_left = $status['moves_left'] - $moves_to_subtract;
    if ($dieUsed == 'double') {
        $mysqli->query("UPDATE game_status SET moves_left = $new_moves_left");
        if ($new_moves_left <= 0) $mysqli->query("UPDATE game_status SET dice1=NULL, dice2=NULL");
    } else {
        if ($dieUsed == 'both' || $new_moves_left <= 0) $mysqli->query("UPDATE game_status SET dice1=NULL, dice2=NULL, moves_left=0");
        elseif ($dieUsed == 'dice1') $mysqli->query("UPDATE game_status SET dice1=NULL, moves_left=$new_moves_left");
        elseif ($dieUsed == 'dice2') $mysqli->query("UPDATE game_status SET dice2=NULL, moves_left=$new_moves_left");
    }

    if ($new_moves_left <= 0) {
        $next_player = ($pCode == 'W') ? 'B' : 'W';
        $mysqli->query("UPDATE game_status SET p_turn='$next_player', moves_left=0, dice1=NULL, dice2=NULL");
    }
    
    $mysqli->query("UPDATE players SET last_action=NOW() WHERE piece_color='$pCode'");
    show_status();
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

function surrender($loser_color) {
    global $mysqli;
    $winner_score_col = ($loser_color === 'white') ? 'score_b' : 'score_w';
    $mysqli->query("UPDATE game_status SET $winner_score_col = $winner_score_col + 1, result='aborted'");
    $mysqli->query("CALL clear_game()");
    show_status();
}
?>