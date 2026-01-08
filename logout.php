<?php
require_once "lib/dbconnect.php"; 
session_start();

// Έλεγχος: Είμαι συνδεδεμένος;
if (isset($_SESSION['my_color'])) {
    
    $my_color = $_SESSION['my_color']; 
    $db_color = ($my_color === 'white') ? 'W' : 'B'; 

    // 1. Ενημερώνουμε το status (Αν το παιχνίδι έτρεχε)
    // Ελέγχουμε αν είναι ήδη aborted/ended για να μην χαλάσουμε το αποτέλεσμα του άλλου
    $sql_status = "SELECT status FROM game_status";
    $status_res = $mysqli->query($sql_status);
    $current_status = $status_res->fetch_assoc()['status'];

    if ($current_status !== 'aborted' && $current_status !== 'ended') {
        // Ορίζουμε νικητή τον ΑΝΤΙΠΑΛΟ
        $winner = ($db_color === 'W') ? 'B' : 'W';
        $sql = "UPDATE game_status SET status='aborted', result='$winner', p_turn=NULL";
        $mysqli->query($sql);
    }

    // 2. ΔΙΑΓΡΑΦΟΥΜΕ ΜΟΝΟ ΤΟΝ ΕΑΥΤΟ ΜΑΣ (ΟΧΙ ΤΟΝ ΑΝΤΙΠΑΛΟ)
    // Αφήνουμε τον αντίπαλο μέσα στη βάση, για να προλάβει να δει το μήνυμα νίκης!
    // Όταν ο αντίπαλος πατήσει ΟΚ στο μήνυμα, θα έρθει κι αυτός εδώ και θα σβηστεί τότε.
    $sql_clean_me = "UPDATE players SET username=NULL, token=NULL WHERE piece_color='$db_color'";
    $mysqli->query($sql_clean_me);
}

// 3. Καθαρισμός Session και Επιστροφή στην Αρχική
session_unset();
session_destroy();
header("Location: index.php");
exit();
?>