<?php
require_once "lib/dbconnect.php"; 
session_start();

if (isset($_SESSION['game_mode']) && $_SESSION['game_mode'] == 'online') {
    if(isset($_SESSION['token'])) {
        $token = $_SESSION['token'];
        $sql = "SELECT piece_color FROM players WHERE token = '$token'";
        $res = $mysqli->query($sql);
        
        if ($row = $res->fetch_assoc()) {
            $my_color = $row['piece_color'];
            $winner = ($my_color == 'W') ? 'B' : 'W';
            
            // 1. Στέλνουμε σήμα aborted. 
            // ΠΡΟΣΟΧΗ: Δεν καλούμε clean_board ακόμα για να προλάβει να το δει ο άλλος!
            $mysqli->query("UPDATE game_status SET status='aborted', result='$winner' WHERE status='started' OR status='first_roll'");
        }
    }
}

// 2. Καθαρίζουμε ΤΟΥΣ ΠΑΝΤΕΣ από τον πίνακα players
$mysqli->query("UPDATE players SET username=NULL, token=NULL, last_action=NULL");

// 3. Μηδενίζουμε τα σκορ
$mysqli->query("UPDATE game_status SET score_w=0, score_b=0");

// 4. Καταστροφή Session
session_unset();
session_destroy();

header("Location: index.php");
exit;
?>