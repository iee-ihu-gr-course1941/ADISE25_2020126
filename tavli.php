<?php
// tavli.php 
require_once "lib/dbconnect.php"; 
require_once "lib/board.php";

$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['PATH_INFO']??'', '/'));
$input = json_decode(file_get_contents('php://input'),true);

//print_r($request);
header('Content-Type: application/json');

$r=array_shift($request);

switch ($r) {
    case 'board': 
        handle_board($method); 
        break;
    case 'status': 
        handle_status($method, $input); 
        break;
    default: 
        echo json_encode(['error' => 'No route specified']);
        break;
}

function handle_board($method) {
    if($method=='GET'){ 
        show_board();
    }
    elseif($method=='POST') {
        reset_board();
    }
}

function handle_status($method, $input) {
    global $mysqli; 
    if($method=='GET') {
        show_status();
    } 
    elseif($method=='POST') {
        if(isset($input['action']) && $input['action'] == 'start') {
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

function handle_roll_first() {
    global $mysqli;
    $res = $mysqli->query("SELECT dice1, dice2 FROM game_status LIMIT 1");
    if($res) {
        $row = $res->fetch_assoc();
        $d1 = $row['dice1'];
        $d2 = $row['dice2'];

        if ($d1 === NULL) {
            $d1 = rand(1, 6);
            $mysqli->query("UPDATE game_status SET dice1=$d1, status='first_roll'");
        } elseif ($d2 === NULL) {
            $d2 = rand(1, 6);
            $mysqli->query("UPDATE game_status SET dice2=$d2");
        }
    }
    show_status();
}

function move_piece($from, $to, $playerColor) {
    global $mysqli;
    $status = $mysqli->query("SELECT * FROM game_status")->fetch_assoc();
    $pCode = ($playerColor == 'white') ? 'W' : 'B';
    
    if($status['p_turn'] != $pCode) {
        echo json_encode(['error' => 'Δεν είναι η σειρά σου!']); return;
    }

    $diff = $from - $to;
    if ($diff < 0) $diff = abs($diff); 

    $diceToUse = []; 
    $d1 = $status['dice1'];
    $d2 = $status['dice2'];
    
    if ($d1 == $diff) $diceToUse = ['dice1'];
    elseif ($d2 == $diff) $diceToUse = ['dice2'];
    elseif ($d1 && $d2 && ($d1 + $d2 == $diff)) $diceToUse = ['dice1', 'dice2'];
    else {
        echo json_encode(['error' => "Λάθος ζαριά!"]); return;
    }

    // Έλεγχος αν η θέση είναι πιασμένη
    $stmt = $mysqli->prepare("SELECT piece_color, piece_count FROM board WHERE x=?");
    $stmt->bind_param("i", $to);
    $stmt->execute();
    $dest = $stmt->get_result()->fetch_assoc();

    if($dest && $dest['piece_count'] > 0 && $dest['piece_color'] != $pCode) {
        echo json_encode(['error' => 'Η θέση είναι πιασμένη!']); return;
    }
    
    // Εκτέλεση κίνησης
    $mysqli->query("UPDATE board SET piece_count = piece_count - 1 WHERE x=$from");
    $mysqli->query("UPDATE board SET piece_color = NULL WHERE x=$from AND piece_count=0");
    
    if (!$dest || $dest['piece_count'] == 0) {
         $sql = "INSERT INTO board (x, piece_color, piece_count) VALUES ($to, '$pCode', 1) 
                 ON DUPLICATE KEY UPDATE piece_count=1, piece_color='$pCode'";
         $mysqli->query($sql);
    } else {
        $mysqli->query("UPDATE board SET piece_count = piece_count + 1 WHERE x=$to");
    }

    // Κάψιμο ζαριών
    foreach($diceToUse as $dieCol) {
        $mysqli->query("UPDATE game_status SET $dieCol = NULL");
    }

    // Αλλαγή σειράς αν τελείωσαν τα ζάρια
    $s = $mysqli->query("SELECT dice1, dice2 FROM game_status")->fetch_assoc();
    if(empty($s['dice1']) && empty($s['dice2'])) {
        $next = ($pCode == 'W') ? 'B' : 'W';
        $mysqli->query("UPDATE game_status SET p_turn='$next'");
    }
    show_status();
}


function show_status() {
    global $mysqli;
    $res = $mysqli->query("SELECT * FROM game_status LIMIT 1");
    if($res) {
        echo json_encode($res->fetch_assoc());
    } else {
        echo json_encode(['status' => 'error', 'msg' => 'Database error']);
    }
}



function roll_dice() {
    global $mysqli;
    $st = $mysqli->query("SELECT dice1 FROM game_status")->fetch_assoc();
    if($st['dice1'] != NULL) { show_status(); return; }
    
    $d1 = rand(1,6); $d2 = rand(1,6);
    $mysqli->query("UPDATE game_status SET dice1=$d1, dice2=$d2 WHERE status='started'");
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