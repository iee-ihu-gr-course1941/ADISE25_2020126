<?php
// lib/dbconnect.php

$host = 'localhost';
$db = 'tavli'; 
require_once "db_upass.php"; 

$user = $DB_USER;
$pass = $DB_PASS;

$use_remote_tunnel = true;

if(gethostname()=='users.iee.ihu.gr') {
    $mysqli = new mysqli('mysql.iee.ihu.gr', $user, $pass, $db);
} else {
    if ($use_remote_tunnel) {
        $mysqli = new mysqli('127.0.0.1', $user, $pass, $db, 3308);
    } else {
        $local_user = 'root';
        $local_pass = '';
        $mysqli = new mysqli('localhost', $local_user, $local_pass, $db, 3307);
    }
}

// Έλεγχος αν πέτυχε η σύνδεση
if ($mysqli->connect_errno) {
    echo "Αποτυχία σύνδεσης στη MySQL: (" .
    $mysqli->connect_errno . ") " . $mysqli->connect_error;
}

// Αυτό βοηθάει με τα Ελληνικά στη βάση
$mysqli->set_charset("utf8");
?>