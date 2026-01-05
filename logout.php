<?php
require_once "lib/dbconnect.php"; 
require_once "lib/game_logic.php";

session_start();
session_destroy();

global $mysqli;

$mysqli->query("CALL clean_board()");
$sql = "UPDATE players SET username = NULL, token = NULL";
$mysqli->query($sql);
reset_status(); 

header("Location: index.php"); 
exit;
?>