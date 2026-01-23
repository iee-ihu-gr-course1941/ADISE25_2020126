<?php
require_once "lib/dbconnect.php"; 
session_start();

// 1. ΛΟΓΙΚΗ ΓΙΑ ONLINE ΕΙΔΟΠΟΙΗΣΗ (Αν κάποιος φύγει στη μέση)
if (isset($_SESSION['game_mode']) && $_SESSION['game_mode'] == 'online') {
    if(isset($_SESSION['token'])) {
        $token = $_SESSION['token'];
        $sql = "SELECT piece_color FROM players WHERE token = '$token'";
        $res = $mysqli->query($sql);
        
        if ($row = $res->fetch_assoc()) {
            $my_color = $row['piece_color'];
            $winner = ($my_color == 'W') ? 'B' : 'W';
            
            // Ενημερώνουμε τον αντίπαλο μόνο αν το παιχνίδι ΠΑΙΖΟΤΑΝ ακόμα
            // Αν είχε ήδη τελειώσει (status='ended'), δεν χρειάζεται το 'aborted'
            $mysqli->query("UPDATE game_status SET status='aborted', result='$winner' WHERE status='started' OR status='first_roll'");
        }
    }
}

// 2. ΚΑΘΟΛΙΚΟΣ ΚΑΘΑΡΙΣΜΟΣ (Για Hotseat και Online)
// Καθαρίζουμε το ταμπλό και μηδενίζουμε τα w_off, b_off (μέσω της procedure)
$mysqli->query("call clean_board()");

// Μηδενίζουμε τους παίκτες
$mysqli->query("UPDATE players SET username=NULL, token=NULL, last_action=NULL");

// Μηδενίζουμε το σκορ και το status για την επόμενη παρτίδα/χρήστες
$mysqli->query("UPDATE game_status SET status='not active', p_turn=NULL, result=NULL, score_w=0, score_b=0, last_change=NOW()");

// 3. ΚΑΤΑΣΤΡΟΦΗ SESSION
session_unset();
session_destroy();

// Επιστροφή στην αρχική
header("Location: index.php");
exit;
?>