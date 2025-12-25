<?php
// tavli.php
require_once "lib/dbconnect.php"; 
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['PATH_INFO'],'/'));
$input = json_decode(file_get_contents('php://input'),true);

switch ($r=array_shift($request)) {
    case 'board': 
        handle_board($method);
        break;
    case 'status':
        handle_status($method, $input); 
        break;
    default:
        header("HTTP/1.1 404 Not Found");
        exit;
}

function handle_board($method) {
    if($method=='GET') {
        show_board(); 
    } else if ($method=='POST') {
        // Το Reset (POST στο board) τώρα ΑΔΕΙΑΖΕΙ το τραπέζι
        clear_table(); 
    }
}

function handle_status($method, $input) {
    if($method=='GET') {
        show_status();
    } else if ($method=='POST') {
        if (isset($input['action'])) {
            if ($input['action'] == 'surrender') {
                surrender($input['color']);
            } elseif ($input['action'] == 'start') {
                start_new_game(); // ΝΕΑ ΕΝΤΟΛΗ
            }
        } else {
            roll_dice();
        }
    }
}

// --- FUNCTIONS ---

function show_board() {
    global $mysqli;
    $sql = 'select * from board';
    $st = $mysqli->prepare($sql);
    $st->execute();
    $res = $st->get_result();
    echo json_encode($res->fetch_all(MYSQLI_ASSOC));
}

// Αυτό καλείται όταν πατάς "Νέο Παιχνίδι" (Reset) ή τελειώνει το ματς
function clear_table() {
    global $mysqli;
    $sql = 'call clear_game()'; // Καλεί τη νέα διαδικασία που αδειάζει τα πάντα
    $mysqli->query($sql);
    show_board();
}

// Αυτό καλείται όταν πατάς "Έναρξη Παιχνιδιού"
function start_new_game() {
    global $mysqli;
    $sql = 'call clean_board()'; // Καλεί την παλιά διαδικασία που ΣΤΗΝΕΙ τα πούλια
    $mysqli->query($sql);
    show_status();
}

function show_status() {
    global $mysqli;
    $sql = 'select * from game_status';
    $st = $mysqli->prepare($sql);
    $st->execute();
    $res = $st->get_result();
    echo json_encode($res->fetch_assoc());
}

function roll_dice() {
    global $mysqli;
    // Ρίχνουμε ζάρια μόνο αν το παιχνίδι είναι started
    $d1 = rand(1,6);
    $d2 = rand(1,6);
    $sql = 'update game_status set dice1=?, dice2=? where status="started"';
    $st = $mysqli->prepare($sql);
    $st->bind_param('ii', $d1, $d2);
    $st->execute();
    show_status();
}

function surrender($loser_color) {
    global $mysqli;
    if($loser_color === 'white') {
        $sql = "UPDATE game_status SET score_b = score_b + 1";
    } else {
        $sql = "UPDATE game_status SET score_w = score_w + 1";
    }
    $mysqli->query($sql);

    // Μετά την παραίτηση, ΑΔΕΙΑΖΟΥΜΕ το τραπέζι (δεν στήνουμε κατευθείαν)
    clear_table();
}
?>