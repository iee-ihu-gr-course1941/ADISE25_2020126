<?php
// lib/dbconnect.php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = 'localhost';
$db = 'tavli'; 
require_once "db_upass.php"; 

$user = $DB_USER;
$pass = $DB_PASS;

// Το path του Socket που βρήκαμε ότι δουλεύει
$socket_path = '/home/student/iee/2020/iee2020126/mysql/run/mysql.sock';

try {
    $hostname = gethostname();
    // Έλεγχος αν είμαστε στον Server
    if (strpos($hostname, 'users') !== false || strpos($hostname, 'teithe') !== false || strpos($hostname, 'ihu') !== false) {
        // Σύνδεση με Socket
        $mysqli = new mysqli('localhost', $user, $pass, $db, 0, $socket_path);
    } else {
        // Σύνδεση Τοπικά (Localhost)
        $mysqli = new mysqli('127.0.0.1', $user, $pass, $db, 3308);
    }
    $mysqli->set_charset("utf8");

} catch (Exception $e) {
    // Εδώ ΔΕΝ κάνουμε echo για να μην χαλάσει το JSON του παιχνιδιού
    // Αν αποτύχει, το παιχνίδι θα βγάλει error 500, αλλά δεν θα στείλει σκουπίδια
    error_log($e->getMessage()); 
    exit('DB Error');
}
?>