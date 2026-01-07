<?php
require_once "lib/dbconnect.php"; 
session_start();

// Αν υπάρχει ενεργό session και χρώμα, ενημερώνουμε τη βάση ότι αποχωρήσαμε
if (isset($_SESSION['my_color'])) {
    
    $my_color = $_SESSION['my_color']; // 'white' ή 'black'
    $db_color = ($my_color === 'white') ? 'W' : 'B'; // 'W' ή 'B' για τη βάση
    
    // 1. Ενημερώνουμε το status σε 'aborted'
    // Αν θέλεις μπορούμε να βάλουμε στο result τον νικητή (τον αντίπαλο)
    // Π.χ. Αν έφυγε ο W, νικητής είναι ο B.
    $winner = ($db_color === 'W') ? 'B' : 'W';
    
    // Προσοχή: Ελέγχουμε αν η στήλη result δέχεται 'W'/'B'. Αν ναι, το βάζουμε.
    // Αλλιώς αφήνουμε μόνο το status='aborted'.
    $sql = "UPDATE game_status SET status='aborted', result='$winner', p_turn=NULL";
    $mysqli->query($sql);

    // 2. Καθαρίζουμε τον παίκτη που φεύγει από τον πίνακα players
    // (Τον κάνουμε NULL για να ελευθερωθεί η θέση, αλλά ΟΧΙ τον αντίπαλο ακόμα)
    $sql_player = "UPDATE players SET username=NULL, token=NULL WHERE piece_color='$db_color'";
    $mysqli->query($sql_player);
}

// 3. Καταστροφή Session και Ανακατεύθυνση
session_unset();
session_destroy();
header("Location: index.php");
exit();
?>