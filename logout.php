<?php
require_once "lib/dbconnect.php"; 
session_start();

if (isset($_SESSION['game_mode']) && $_SESSION['game_mode'] == 'hotseat') {
    $mysqli->query("call clean_board()");
    $mysqli->query("UPDATE players SET username=NULL, token=NULL, last_action=NULL");
    $mysqli->query("UPDATE game_status SET status='not active', p_turn=NULL, result=NULL, score_w=0, score_b=0, last_change=NOW()");
} else {
    if(isset($_SESSION['token'])) {
        $token = $_SESSION['token'];
        $sql = "SELECT piece_color FROM players WHERE token = '$token'";
        $res = $mysqli->query($sql);
        
        if ($row = $res->fetch_assoc()) {
            $my_color = $row['piece_color'];
            $winner = ($my_color == 'W') ? 'B' : 'W';
            
            // 1. Aborted status για να διώξει τον άλλον
            $mysqli->query("UPDATE game_status SET status='aborted', result='$winner'");
            
            // 2. Σβήνουμε παίκτες και ΜΗΔΕΝΙΖΟΥΜΕ το σκορ για τους επόμενους
            $mysqli->query("UPDATE players SET username=NULL, token=NULL, last_action=NULL");
            $mysqli->query("UPDATE game_status SET score_w=0, score_b=0");
        }
    }
}

session_unset();
session_destroy();
header("Location: index.php");
exit;
?>