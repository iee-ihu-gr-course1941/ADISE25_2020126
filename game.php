<?php
// game.php
require_once "lib/dbconnect.php";
session_start();

//Ελέγχει αν υπάρχουν ονόματα στην μνήμη. Αν δεν υπάρχουν σημαίνει ότι μπήκε κάποιος απευθείας χωρίς να τα συμπληρώσει
//και τον στέλνει πίσω στο login.php 
if (!isset($_SESSION['player1_name']) || !isset($_SESSION['player2_name'])) {
    $_SESSION['error'] = "Πρέπει να κάνετε Login για να παίξετε!";
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['player_white'])) {
    $p1_name = $_SESSION['player1_name'];
    $p2_name = $_SESSION['player2_name'];
    $choice  = isset($_SESSION['player1_color']) ? $_SESSION['player1_color'] : 'white';

    if ($choice === 'white') {
        $_SESSION['player_white'] = $p1_name; 
        $_SESSION['player_black'] = $p2_name; 
        $_SESSION['my_color'] = 'white'; 
    } else {
        $_SESSION['player_black'] = $p1_name; 
        $_SESSION['player_white'] = $p2_name; 
        $_SESSION['my_color'] = 'black';
    }

    //Μηδενίζει τα πάντα. 
    // Το παιχνίδι δεν έχει αρχίσει ακόμα. Μηδενίζει τα σκορ. Σβήνει τα ζάρια.
    
    //$mysqli->query("UPDATE game_status SET status='not active', p_turn=NULL, result=NULL, score_w=0, score_b=0, dice1=NULL, dice2=NULL");

    $mysqli->query("call clean_board()");
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Το Φεύγα μου</title>
    <link rel="stylesheet" href="style.css?v=4"> 
    <script>
        var myColor = "<?php echo $_SESSION['my_color']; ?>";
        var isHotseat = <?php echo ($_SESSION['game_mode'] === 'hotseat') ? 'true' : 'false'; ?>;
        var pWhite = "<?php echo $_SESSION['player_white']; ?>";
        var pBlack = "<?php echo $_SESSION['player_black']; ?>";
    </script>
</head>
<body>

    <div id="scoreboard">
        <table>
            <tr>
                <th><?php echo $_SESSION['player_white']; ?> (W)</th>
                <th><?php echo $_SESSION['player_black']; ?> (B)</th>
            </tr>
            <tr>
                <td id="score-w">0</td>
                <td id="score-b">0</td>
            </tr>
        </table>
    </div>

    <a href="logout.php" class="btn-exit-top">Έξοδος</a>

    <div class="game-wrapper">
        
        <div class="player-label top-right">
            <span id="turn-label-b" class="turn-active" style="display:none; margin-right:10px;">Παίζει τώρα</span>
            <?php echo $_SESSION['player_black']; ?> (Μαύρα)
        </div>

        <div class="board">
            <div class="half-board">
                <div class="row top">
                    <div id="p13" class="point"></div><div id="p14" class="point"></div><div id="p15" class="point"></div>
                    <div id="p16" class="point"></div><div id="p17" class="point"></div><div id="p18" class="point"></div>
                </div>
                <div class="row bottom">
                    <div id="p12" class="point"></div><div id="p11" class="point"></div><div id="p10" class="point"></div>
                    <div id="p9" class="point"></div><div id="p8" class="point"></div><div id="p7" class="point"></div>
                </div>
            </div>

            <div class="bar"></div>

            <div class="half-board">
                <div class="row top">
                    <div id="p19" class="point"></div><div id="p20" class="point"></div><div id="p21" class="point"></div>
                    <div id="p22" class="point"></div><div id="p23" class="point"></div><div id="p24" class="point"></div>
                </div>
                <div class="row bottom">
                    <div id="p6" class="point"></div><div id="p5" class="point"></div><div id="p4" class="point"></div>
                    <div id="p3" class="point"></div><div id="p2" class="point"></div><div id="p1" class="point"></div>
                </div>
            </div>
        </div>

        <div class="player-label bottom-left">
            <?php echo $_SESSION['player_white']; ?> (Άσπρα)
            <span id="turn-label-w" class="turn-active" style="display:none;">Παίζει τώρα</span>
        </div>

        <div id="controls">
            <div id="dice-display" style="display:none; margin-bottom: 15px;">
                <div class="dice-box" id="d1">-</div>
                <div class="dice-box" id="d2">-</div>
            </div>

            <div id="start-message" style="display:none; font-size: 1.5rem; font-weight:bold; color: #f1c40f; margin: 10px 0;">
                </div>

            <button id="btn-start-game" onclick="startGame()">Έναρξη Παιχνιδιού</button>
            <button id="btn-roll-first" onclick="rollFirst()" style="display:none;">🎲 Ποιος παίζει πρώτος;</button>
            <button id="btn-roll" onclick="rollDice()" style="display:none;">Ρίξε τα Ζάρια!</button>

            <div id="game-controls" style="display:none; margin-top:15px;">
                <button onclick="resetGame()" class="btn-secondary">Reset</button> 
                <button onclick="updateAll()" class="btn-secondary">Refresh</button>
            </div>
        </div>

    </div>

    <script src="fevga.js?v=13"></script> 
</body>
</html>