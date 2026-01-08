<?php
require_once "lib/dbconnect.php"; 
session_start();

// Ελέγχουμε αν υπάρχει session, αλλιώς δεν μπορούμε να ξέρουμε το mode
if (isset($_SESSION['game_mode']) && $_SESSION['game_mode'] == 'hotseat') {
    // ===========================
    // ΛΟΓΙΚΗ ΓΙΑ HOTSEAT (ΤΟΠΙΚΟ)
    // ===========================
    
    // 1. ΠΡΩΤΑ καθαρίζουμε το ταμπλό (που ίσως βάζει status='started')
    $sql = "call clean_board()";
    $mysqli->query($sql);

    // 2. ΜΕΤΑ σβήνουμε τους παίκτες
    $sql = "UPDATE players SET username=NULL, token=NULL, last_action=NULL";
    $mysqli->query($sql);

    // 3. ΚΑΙ ΤΕΛΟΣ βάζουμε το status σε 'not active' (για να ισχύσει αυτό σίγουρα)
    $sql = "UPDATE game_status SET status='not active', p_turn=NULL, result=NULL, last_change=NOW()";
    $mysqli->query($sql);

} else {
    // ===========================
    // ΛΟΓΙΚΗ ΓΙΑ ONLINE
    // ===========================
    
    // Εδώ κρατάμε την παλιά λογική: Αν φύγω εγώ, το παιχνίδι γίνεται aborted 
    // και ίσως θέλουμε να νικήσει ο άλλος.
    
    if(isset($_SESSION['token'])) {
        $token = $_SESSION['token'];
        
        // Βρίσκουμε ποιο χρώμα είμαι
        $sql = "SELECT piece_color FROM players WHERE token = '$token'";
        $res = $mysqli->query($sql);
        
        if ($row = $res->fetch_assoc()) {
            $my_color = $row['piece_color'];
            
            // Κάνω εμένα NULL
            $sql = "UPDATE players SET username=NULL, token=NULL WHERE token = '$token'";
            $mysqli->query($sql);
            
            // Ενημερώνουμε το status σε aborted (αν ήταν active)
            $sql = "UPDATE game_status SET status='aborted', result=IF('$my_color'='W','B','W') WHERE status='active'";
            $mysqli->query($sql);
        }
    }
}

// Τέλος, καταστρέφουμε το Session του browser
session_unset();
session_destroy();

// Επιστροφή στην αρχική
header("Location: index.php");
exit;
?>