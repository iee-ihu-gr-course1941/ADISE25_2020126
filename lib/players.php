<?php
// lib/players.php
//require_once "lib/game_logic.php"; 

function handle_player($method, $p, $input) {
    // Διαβάζουμε το χρώμα (W ή B) από το URL (π.χ. tavli.php/player/W)
    switch ($b=array_shift($p)) {
        case '':
        case null: 
            if($method=='GET') { show_users(); }
            else { 
                header("HTTP/1.1 400 Bad Request"); 
                print json_encode(['errormesg'=>"Method $method not allowed here."]);
            }
            break;
        case 'B': 
        case 'W': 
            handle_user($method, $b, $input);
            break;
        default: 
            header("HTTP/1.1 404 Not Found");
            print json_encode(['errormesg'=>"Player $b not found."]);
            break;
    }
}

function reset_players() {
    global $mysqli;
    $sql = "UPDATE players SET username = NULL, token = NULL";
    $mysqli->query($sql);

    reset_status();
}

function handle_user($method, $b, $input) {
    if($method=='GET') {
        show_user($b);
    } else if($method=='PUT') { 
        set_user($b, $input);
    }
}

function show_user($b) {
    global $mysqli;
    // ΤΩΡΑ ΔΙΑΒΑΖΟΥΜΕ ΚΑΝΟΝΙΚΑ ΑΠΟ ΤΗ ΒΑΣΗ
    $sql = 'SELECT username, piece_color FROM players WHERE piece_color=?';
    $st = $mysqli->prepare($sql);
    $st->bind_param('s', $b);
    $st->execute();
    $res = $st->get_result();
    
    header('Content-type: application/json');
    print json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
}

function show_users() {
    global $mysqli;
    // Ζητάμε από τη βάση ΟΛΟΥΣ τους παίκτες
    $sql = 'SELECT username, piece_color FROM players';
    $st = $mysqli->prepare($sql);
    $st->execute();
    $res = $st->get_result();
    
    header('Content-type: application/json');
    // Τυπώνουμε τα αποτελέσματα σε JSON
    print json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
}


function set_user($b, $input) {
    if(!isset($input['username']) || $input['username']=='') {
        header("HTTP/1.1 400 Bad Request");
        print json_encode(['errormesg'=>"No username given."]);
        exit;
    }

    $username = $input['username'];
    global $mysqli;

    // 1. Έλεγχος: Υπάρχει παίκτης σε αυτή τη θέση ΠΟΥ ΝΑ ΕΙΝΑΙ ΕΝΕΡΓΟΣ;
    // Ενεργός = Έχει κάνει κίνηση τα τελευταία 5 λεπτά
    $sql = 'SELECT count(*) as c FROM players 
            WHERE piece_color=? 
            AND username IS NOT NULL
            AND last_action > (NOW() - INTERVAL 30 SECOND)'; 
            
    $st = $mysqli->prepare($sql);
    $st->bind_param('s', $b);
    $st->execute();
    $res = $st->get_result();
    $r = $res->fetch_all(MYSQLI_ASSOC);
    
    if($r[0]['c'] > 0) {
        header("HTTP/1.1 400 Bad Request");
        print json_encode(['errormesg'=>"Player $b is already set and active."]);
        exit;
    }

    // 2. Αν περάσαμε τον έλεγχο (η θέση είναι κενή Η' ο προηγούμενος ήταν ανενεργός),
    // τότε παίρνουμε εμείς τη θέση.
    // ΠΡΟΣΟΧΗ: Ενημερώνουμε και το last_action=NOW()
    $sql = 'UPDATE players SET username=?, token=md5(CONCAT(?, NOW())), last_action=NOW() WHERE piece_color=?';
    $st2 = $mysqli->prepare($sql);
    $st2->bind_param('sss', $username, $username, $b);
    $st2->execute();

    // 3. Ενημέρωση game_status (θα το δούμε μετά όπως λέει η διαφάνεια)
    update_game_status(); 

    // 4. Επιστροφή στοιχείων
    $sql = 'SELECT * FROM players WHERE piece_color=?';
    $st = $mysqli->prepare($sql);
    $st->bind_param('s', $b);
    $st->execute();
    $res = $st->get_result();
    
    header('Content-type: application/json');
    print json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
}

?>