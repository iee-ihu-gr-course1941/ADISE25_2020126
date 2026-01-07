<?php
require_once "lib/dbconnect.php"; 
session_start();

// 1. Έλεγχος τρέχουσας κατάστασης
// Πρέπει να δούμε αν το παιχνίδι είναι ΗΔΗ aborted (π.χ. από τον άλλο παίκτη)
// για να μην ξαναγράψουμε το status και χαλάσουμε το result.
$sql_status = "SELECT status FROM game_status";
$status_res = $mysqli->query($sql_status);
$current_status = $status_res->fetch_assoc()['status'];

if (isset($_SESSION['my_color'])) {
    
    // Αν το παιχνίδι ΔΕΝ έχει λήξει ακόμα, τότε εγώ είμαι αυτός που το διακόπτει.
    // Άρα πρέπει να ορίσω τον νικητή.
    if ($current_status !== 'aborted' && $current_status !== 'ended') {
        $my_color = $_SESSION['my_color']; 
        $db_color = ($my_color === 'white') ? 'W' : 'B'; 
        
        // Νικητής είναι ο αντίπαλος
        $winner = ($db_color === 'W') ? 'B' : 'W';
        
        $sql = "UPDATE game_status SET status='aborted', result='$winner', p_turn=NULL";
        $mysqli->query($sql);
    }

    // 2. ΟΛΙΚΗ ΔΙΑΓΡΑΦΗ (NUCLEAR OPTION)
    // Σβήνουμε ΚΑΙ τους δύο παίκτες από τη βάση, ώστε να είναι πεντακάθαρη
    // για το επόμενο παιχνίδι. Δεν βάζουμε WHERE clause.
    $sql_clean_all = "UPDATE players SET username=NULL, token=NULL";
    $mysqli->query($sql_clean_all);
}

// 3. Καθαρισμός Session και Επιστροφή
session_unset();
session_destroy();
header("Location: index.php");
exit();
?>