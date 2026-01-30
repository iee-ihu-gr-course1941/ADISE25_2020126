<?php
require_once "lib/dbconnect.php"; 
session_start();

if (isset($_SESSION['game_mode']) && $_SESSION['game_mode'] == 'online') {
    if(isset($_SESSION['token'])) {
        $token = $_SESSION['token'];
        $st = $mysqli->prepare("SELECT piece_color FROM players WHERE token = ?");
        $st->bind_param("s", $token);
        $st->execute();
        $res = $st->get_result();
        if ($row = $res->fetch_assoc()) {
            $my_color = $row['piece_color'];
            $winner = ($my_color == 'W') ? 'B' : 'W';
            $mysqli->query("UPDATE game_status SET status='aborted', result='$winner' WHERE status IN ('started', 'first_roll', 'ended')");
        }
    }
}

$mysqli->query("call clean_board()"); 
$mysqli->query("UPDATE players SET username=NULL, token=NULL, last_action=NULL");

$mysqli->query("UPDATE game_status SET status='not active', p_turn=NULL, result=NULL, score_w=0, score_b=0, last_change=NOW()");

session_unset();
session_destroy();

header("Location: index.php");
exit;
?>