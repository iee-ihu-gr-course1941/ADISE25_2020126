<?php
// lib/dbconnect.php
$host = 'localhost';
$db = 'tavli';
require_once "db_upass.php";

$user = $DB_USER;
$pass = $DB_PASS;

// Έλεγχος αν είμαστε στον Server της σχολής (users.iee.ihu.gr)
if(gethostname() == 'users.iee.ihu.gr') {
    // Σύνδεση με Socket (για τον Server)
    $mysqli = new mysqli($host, $user, $pass, $db, null, '/home/student/iee/2020/iee2020126/mysql/run/mysql.sock');
} else {
    // Σύνδεση Τοπικά (XAMPP)
    $mysqli = new mysqli($host, $user, $pass, $db, port:3307);
}

// Αν υπάρχει λάθος, το εμφανίζουμε (για να ξέρουμε τι φταίει)
if ($mysqli->connect_errno) {
    echo "Απέτυχε η σύνδεση στη MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
}

$mysqli->set_charset("utf8");

?>