<?php
// lib/players.php
require_once "lib/game_logic.php"; 

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
    try {
        global $mysqli;
        // ΤΩΡΑ ΔΙΑΒΑΖΟΥΜΕ ΚΑΝΟΝΙΚΑ ΑΠΟ ΤΗ ΒΑΣΗ
        $sql = 'SELECT username, piece_color, token, last_action FROM players WHERE piece_color=?';
        $st = $mysqli->prepare($sql);
        $st->bind_param('s', $b);
        $st->execute();
        $res = $st->get_result();
        
        header('Content-type: application/json');
        print json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } catch(Exception $e) {
        require_once('logger.php');
        app_log('handle status: ' . $e);
    }
}

function show_users() {
    global $mysqli;
    // Ζητάμε από τη βάση ΟΛΟΥΣ τους παίκτες
    $sql = 'SELECT username, piece_color, token, last_action FROM players';
    $st = $mysqli->prepare($sql);
    $st->execute();
    $res = $st->get_result();
    
    header('Content-type: application/json');
    // Τυπώνουμε τα αποτελέσματα σε JSON
    print json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT| JSON_UNESCAPED_UNICODE);
}


function set_user($b, $input) {
    try {
        if(!isset($input['username']) || $input['username']=='') {
            header("HTTP/1.1 400 Bad Request");
            print json_encode(['errormesg'=>"No username given."]);
            exit;
        }
    
        $username = $input['username'];
        global $mysqli;
    
        // --- NEW: ΕΛΕΓΧΟΣ ΑΝ ΤΟ ΠΑΙΧΝΙΔΙ ΕΙΝΑΙ ΓΕΜΑΤΟ (3ος παίκτης) ---
        // Μετράμε πόσοι ΕΝΕΡΓΟΙ παίκτες υπάρχουν ΣΥΝΟΛΙΚΑ (όχι μόνο στο χρώμα που ζητάμε)
        // Εξαιρούμε τον εαυτό μας αν κάνουμε update (αν και εδώ είναι για insert συνήθως)
        $sql_full = 'SELECT count(*) as c FROM players 
                     WHERE username IS NOT NULL 
                     AND last_action > (NOW() - INTERVAL 5 MINUTE)';
        $res_full = $mysqli->query($sql_full);
        $active_total = $res_full->fetch_assoc()['c'];
    
        // Αν υπάρχουν ήδη 2 παίκτες, και εγώ δεν είμαι ένας από αυτούς (π.χ. προσπαθώ να μπω τώρα)
        // Τότε απαγορεύεται η είσοδος.
        // ΣΗΜΕΙΩΣΗ: Επειδή εδώ είναι το set_user, ελέγχουμε αν η θέση που ζητάω ($b) είναι κενή.
        // Αν η θέση $b είναι κενή, ΑΛΛΑ υπάρχουν ήδη 2 παίκτες (πώς γίνεται; δεν γίνεται σε τάβλι, αλλά για ασφάλεια)
        // Το σωστότερο είναι: Αν η θέση που ζητάω είναι πιασμένη -> Error (Το έχουμε ήδη παρακάτω).
        
        // ΑΛΛΑ: Αν κάποιος προσπαθεί να μπει σε 'W' και είναι πιασμένο, θα τον κόψει το παρακάτω check.
        // Το πρόβλημα είναι αν κάποιος προσπαθήσει να μπει σε 'W' και ο 'W' είναι μέσα.
        
        // 1. Έλεγχος: Υπάρχει παίκτης σε ΑΥΤΗ τη θέση ($b) ΠΟΥ ΝΑ ΕΙΝΑΙ ΕΝΕΡΓΟΣ;
        $sql = 'SELECT count(*) as c FROM players 
                WHERE piece_color=? 
                AND username IS NOT NULL
                AND last_action > (NOW() - INTERVAL 5 MINUTE)'; 
                
        $st = $mysqli->prepare($sql);
        $st->bind_param('s', $b);
        $st->execute();
        $res = $st->get_result();
        $r = $res->fetch_all(MYSQLI_ASSOC);
        
        if($r[0]['c'] > 0) {
            header("HTTP/1.1 400 Bad Request");
            // Εδώ αλλάζουμε το μήνυμα για να είναι πιο σαφές
            print json_encode(['errormesg'=>"Player $b is already active. Game might be full or color taken."]);
            exit;
        }
    
        // 2. Αν περάσαμε τον έλεγχο (η θέση είναι κενή Η' ο προηγούμενος ήταν ανενεργός),
        // τότε παίρνουμε εμείς τη θέση.
        // ΠΡΟΣΟΧΗ: Ενημερώνουμε και το last_action=NOW()
        $sql = 'UPDATE players 
                SET username=?, 
                token=md5(CONCAT( ?, NOW())), 
                last_action=NOW() 
                WHERE piece_color=?';
    
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
    } catch(Exception $e) {
        require_once('logger.php');
        app_log('handle status error: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode(['error' => 'Internal Server Error']);
        exit;
    }
}


function current_color($token) {
    global $mysqli;
    if($token == null) { return null; }
    
    $sql = 'SELECT * FROM players WHERE token = ?';
    $st = $mysqli->prepare($sql);
    $st->bind_param('s', $token);
    $st->execute();
    $res = $st->get_result();
    
    if($row = $res->fetch_assoc()) {
        return $row['piece_color'];
    }
    return null;
}

?>