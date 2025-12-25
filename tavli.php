<?php
// tavli.php
require_once "lib/dbconnect.php"; 
header('Content-Type: application/json');

// Ανάγνωση Input
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$request = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
$r = array_shift($request);

// Router
switch ($r) {
    case 'board': 
        handle_board($method); 
        break;
    case 'status': 
        handle_status($method, $input); 
        break;
    default: 
        http_response_code(404); 
        echo json_encode(['error' => 'Not Found']);
        exit;
}

function handle_board($method) {
    if($method=='GET') show_board();
    elseif($method=='POST') clear_table();
}

function handle_status($method, $input) {
    if($method=='GET') show_status();
    elseif($method=='POST') {
        if(isset($input['action'])) {
            if($input['action'] == 'surrender') surrender($input['color']);
            elseif($input['action'] == 'start') start_new_game();
            elseif($input['action'] == 'move') {
                // Μετατροπή σε ακέραιους για σιγουριά
                $from = intval($input['from']);
                $to = intval($input['to']);
                move_piece($from, $to, $input['color']);
            }
        } else {
            roll_dice();
        }
    }
}

// --- LOGIC FUNCTIONS ---

function move_piece($from, $to, $playerColor) {
    global $mysqli;
    
    // 1. Έλεγχος Σειράς
    $status = $mysqli->query("SELECT * FROM game_status")->fetch_assoc();
    $pCode = ($playerColor == 'white') ? 'W' : 'B';
    
    if($status['p_turn'] != $pCode) {
        echo json_encode(['error' => 'Δεν είναι η σειρά σου!']); return;
    }

    // 2. Υπολογισμός Απόστασης
    $distance = 0;
    if ($pCode == 'W') {
        if ($to >= $from) { echo json_encode(['error' => 'Τα Άσπρα πάνε προς το 1']); return; }
        $distance = $from - $to;
    } else {
        // Κυκλική λογική Μαύρων
        if ($from >= 12 && $to > $from) $distance = $to - $from;
        elseif ($from < 12 && $to > $from) $distance = $to - $from;
        elseif ($from > $to) $distance = (24 - $from) + $to;
        else { echo json_encode(['error' => 'Λάθος κατεύθυνση']); return; }
    }
    
    // 3. Έλεγχος Ζαριών (Απλό ή Διπλό)
    $diceToUse = []; // Ποια ζάρια θα κάψουμε
    $d1 = $status['dice1'];
    $d2 = $status['dice2'];

    // Περίπτωση Α: Απλή κίνηση (ίση με το ένα ζάρι)
    if ($d1 == $distance) $diceToUse = ['dice1'];
    elseif ($d2 == $distance) $diceToUse = ['dice2'];
    
    // Περίπτωση Β: Σύνθετη κίνηση (Άθροισμα) - Π.χ. 2+4=6
    elseif ($d1 && $d2 && ($d1 + $d2 == $distance)) {
        // Εδώ κανονικά πρέπει να ελέγξουμε αν το ενδιάμεσο πάτημα είναι ανοιχτό.
        // Για ευκολία τώρα το επιτρέπουμε, αλλά καίμε ΚΑΙ ΤΑ ΔΥΟ ζάρια.
        $diceToUse = ['dice1', 'dice2'];
    } 
    else {
        echo json_encode(['error' => "Λάθος ζαριά! Απόσταση: $distance. Ζάρια: $d1, $d2"]); 
        return;
    }

    // 4. Έλεγχος Προορισμού (Πόρτα)
    $stmt = $mysqli->prepare("SELECT piece_color, piece_count FROM board WHERE x=?");
    $stmt->bind_param("i", $to);
    $stmt->execute();
    $dest = $stmt->get_result()->fetch_assoc();

    if($dest && $dest['piece_count'] > 0 && $dest['piece_color'] != $pCode) {
        echo json_encode(['error' => 'Η θέση είναι πιασμένη (Πόρτα)!']); return;
    }

    // 5. Εκτέλεση Κίνησης
    $mysqli->query("UPDATE board SET piece_count = piece_count - 1 WHERE x=$from");
    $mysqli->query("UPDATE board SET piece_color = NULL WHERE x=$from AND piece_count=0");
    
    if (!$dest || $dest['piece_count'] == 0) {
        $mysqli->query("UPDATE board SET piece_count = 1, piece_color='$pCode' WHERE x=$to");
    } else {
        $mysqli->query("UPDATE board SET piece_count = piece_count + 1 WHERE x=$to");
    }

    // 6. Κάψιμο Ζαριών
    foreach($diceToUse as $dieCol) {
        $mysqli->query("UPDATE game_status SET $dieCol = NULL");
    }

    // 7. Αλλαγή Σειράς (αν τελείωσαν τα ζάρια)
    $s = $mysqli->query("SELECT * FROM game_status")->fetch_assoc();
    if(empty($s['dice1']) && empty($s['dice2'])) {
        $next = ($pCode == 'W') ? 'B' : 'W';
        $mysqli->query("UPDATE game_status SET p_turn='$next'");
    }

    show_status();
}

function show_board() {
    global $mysqli;
    echo json_encode($mysqli->query("SELECT * FROM board")->fetch_all(MYSQLI_ASSOC));
}

function show_status() {
    global $mysqli;
    echo json_encode($mysqli->query("SELECT * FROM game_status")->fetch_assoc());
}

function clear_table() {
    global $mysqli;
    $mysqli->query("call clear_game()");
    show_board();
}

function start_new_game() {
    global $mysqli;
    $mysqli->query("call clean_board()");
    show_status();
}

function roll_dice() {
    global $mysqli;
    $d1 = rand(1,6); $d2 = rand(1,6);
    $st = $mysqli->prepare("UPDATE game_status SET dice1=?, dice2=? WHERE status='started'");
    $st->bind_param('ii', $d1, $d2);
    $st->execute();
    show_status();
}

function surrender($loser_color) {
    global $mysqli;
    $col = ($loser_color === 'white') ? 'score_b' : 'score_w';
    $mysqli->query("UPDATE game_status SET $col = $col + 1");
    clear_table();
}
?>