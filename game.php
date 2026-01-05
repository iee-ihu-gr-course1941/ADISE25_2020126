<?php
// game.php
require_once "lib/dbconnect.php";
session_start();

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
    $mysqli->query("call clean_board()");
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Το Φεύγα μου</title>
    
    <script>
        var myColor = "<?php echo $_SESSION['my_color']; ?>";
        var isHotseat = <?php echo ($_SESSION['game_mode'] === 'hotseat') ? 'true' : 'false'; ?>;
        var pWhite = "<?php echo $_SESSION['player_white']; ?>";
        var pBlack = "<?php echo $_SESSION['player_black']; ?>";
    </script>

    <link href="bootstrap/bootstrap.min.css" rel="stylesheet">
    
    <link href="css/style.css" rel="stylesheet"> 

    <script src="bootstrap/jquery-3.2.1.min.js"></script>
    <script src="bootstrap/bootstrap.min.js"></script>
    
    <script src="js/fevga.js"></script>
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

    <a href="logout.php" class="btn-exit-top btn btn-danger">Έξοδος</a>

    <div class="game-wrapper">
        
        <div class="player-label top-right">
            <span id="turn-label-b" class="turn-active" style="display:none; margin-right:10px;">Παίζει τώρα</span>
            <?php echo $_SESSION['player_black']; ?> (Μαύρα)
        </div>

        <div class="board">
            <div class="half-board">
                <div class="board-row top">
                    <div id="p13" class="point"></div><div id="p14" class="point"></div><div id="p15" class="point"></div>
                    <div id="p16" class="point"></div><div id="p17" class="point"></div><div id="p18" class="point"></div>
                </div>
                <div class="board-row bottom">
                    <div id="p12" class="point"></div><div id="p11" class="point"></div><div id="p10" class="point"></div>
                    <div id="p9" class="point"></div><div id="p8" class="point"></div><div id="p7" class="point"></div>
                </div>
            </div>

            <div class="bar"></div>

            <div class="half-board">
                <div class="board-row top">
                    <div id="p19" class="point"></div><div id="p20" class="point"></div><div id="p21" class="point"></div>
                    <div id="p22" class="point"></div><div id="p23" class="point"></div><div id="p24" class="point"></div>
                </div>
                <div class="board-row bottom">
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

            <button id="btn-start-game" onclick="startGame()" class="btn btn-success btn-lg">Έναρξη Παιχνιδιού</button>
            <button id="btn-roll-first" onclick="rollFirst()" class="btn btn-primary btn-lg" style="display:none;">🎲 Ποιος παίζει πρώτος;</button>
            <button id="btn-roll" onclick="rollDice()" class="btn btn-warning btn-lg" style="display:none;">Ρίξε τα Ζάρια!</button>

            <div id="game-controls" style="display:none; margin-top:15px;">
                <button onclick="resetGame()" class="btn btn-secondary">Reset</button> 
                <button onclick="updateAll()" class="btn btn-info">Refresh</button>
            </div>
        </div>

    </div>

</body>
</html>