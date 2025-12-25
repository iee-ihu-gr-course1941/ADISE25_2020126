<?php
// lib/dbconnect.php

$host = 'localhost';
$db = 'tavli'; 
require_once "db_upass.php"; 

$user = $DB_USER;
$pass = $DB_PASS;

// Ελέγχουμε αν τρέχουμε στον server του πανεπιστημίου ή τοπικά
if(gethostname()=='users.iee.ihu.gr') {
    // Στον server του πανεπιστημίου η mysql δεν είναι στο localhost
    $mysqli = new mysqli('mysql.iee.ihu.gr', $user, $pass, $db);
} else {
    // Τοπικά στον υπολογιστή μας (XAMPP)
    $mysqli = new mysqli($host, $user, $pass, $db);
}

// Έλεγχος αν πέτυχε η σύνδεση
if ($mysqli->connect_errno) {
    echo "Αποτυχία σύνδεσης στη MySQL: (" .
    $mysqli->connect_errno . ") " . $mysqli->connect_error;
}
?>