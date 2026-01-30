<?php
// game.php
require_once "lib/dbconnect.php";
session_start();

//Έλεγχος αν έχει επιλεγεί Mode. Αν όχι, πίσω στην αρχή.
if (!isset($_SESSION['game_mode'])) {
    header("Location: index.php");
    exit();
}

//Έλεγχος αν ο τρέχων παίκτης έχει κάνει login.
if (!isset($_SESSION['player1_name'])) {
    header("Location: login.php?mode=" . $_SESSION['game_mode']);
    exit();
}

$is_hotseat = ($_SESSION['game_mode'] === 'hotseat');

//Αν είναι Hotseat, απαιτούμε να υπάρχουν και τα δύο ονόματα στο Session.
// Αν είναι Online, επιτρέπουμε την είσοδο μόνο με το player1_name (ο 2ος θα συνδεθεί μετά).
if ($is_hotseat && (!isset($_SESSION['player2_name']) || empty($_SESSION['player2_name']))) {
    $_SESSION['error'] = "Πρέπει να δώσετε ονόματα και για τους δύο παίκτες!";
    header("Location: login.php?mode=hotseat");
    exit();
}

$p1 = $_SESSION['player1_name'];
$p1_color = $_SESSION['player1_color'];
$p2 = (isset($_SESSION['player2_name']) && $_SESSION['player2_name'] !== "") ? $_SESSION['player2_name'] : "Αναμονή...";

if ($p1_color == 'white') {
    $name_white = $p1; $name_black = $p2;
} else {
    $name_white = $p2; $name_black = $p1;
}

//Αρχικοποίηση Session μεταβλητών (αν δεν έχει γίνει ήδη)
if (!isset($_SESSION['my_color'])) {
    $_SESSION['player_white'] = $name_white; 
    $_SESSION['player_black'] = $name_black; 
    $_SESSION['my_color'] = ($p1_color == 'white') ? 'white' : 'black';
    
    // Καθαρισμός 
    $status_res = $mysqli->query("SELECT status, result FROM game_status LIMIT 1");
    $status_data = $status_res->fetch_assoc();
    $status_check = $status_data['status'];
    $result_check = $status_data['result'];

    // Μην καθαρίζεις αν το παιχνίδι τελείωσε (ended) ή περιμένει απάντηση (_READY)
    if ($status_check !== 'ended' && !str_contains((string)$result_check, 'READY')) {
        $players_count = $mysqli->query("SELECT count(*) as c FROM players WHERE username IS NOT NULL")->fetch_assoc()['c'];
        if ($is_hotseat || $players_count == 0) {
            $mysqli->query("call clean_board()");
            $mysqli->query("UPDATE game_status SET status='not active', result=NULL, p_turn=NULL, score_w=0, score_b=0");
        }
    }
    
}

$is_hotseat_js = $is_hotseat ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Φεύγα</title>
    
    <link href="bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet"> 

    <script src="bootstrap/jquery-3.2.1.min.js"></script>
    <script src="bootstrap/popper.min.js"></script> 
    <script src="bootstrap/bootstrap.min.js"></script>
    
    </head>
<body>
    
    <div id="waiting-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(44, 62, 80, 0.95); z-index:9999; flex-direction:column; justify-content:center; align-items:center; color:white; text-align:center;">
        <h1 style="font-size: 3rem; margin-bottom: 20px;">⏳ Αναμονή Αντιπάλου...</h1>
        <p style="font-size: 1.5rem;">Περιμένετε τον 2ο παίκτη να συνδεθεί για να ξεκινήσετε.</p>
        <div class="spinner-border text-light" role="status" style="width: 3rem; height: 3rem; margin-top: 20px;">
            <span class="sr-only">Loading...</span>
        </div>
    </div>

    <div id="scoreboard">
        <table>
            <tr>
                <th><span id="score-name-w"><?php echo $_SESSION['player_white']; ?></span> (W)</th>
                <th><span id="score-name-b"><?php echo $_SESSION['player_black']; ?></span> (B)</th>
            </tr>
            <tr>
                <td id="score-w">0</td>
                <td id="score-b">0</td>
            </tr>
        </table>
    </div>

    <a href="#" onclick="confirmExit()" class="btn-exit-top btn btn-danger">Έξοδος</a>

    <div class="game-wrapper">
        
        <div class="player-label top-right">
            <span id="turn-label-b" class="turn-active" style="display:none; margin-right:10px;">Παίζει τώρα</span>
            <span id="p-name-b"><?php echo $_SESSION['player_black']; ?></span> (Μαύρα)
        </div>

        <div id="exit-b" class="off-zone off-left"></div>

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

        <div id="exit-w" class="off-zone off-right"></div>

        <div class="player-label bottom-left">
            <span id="p-name-w"><?php echo $_SESSION['player_white']; ?></span> (Άσπρα)
            <span id="turn-label-w" class="turn-active" style="display:none;">Παίζει τώρα</span>
        </div>

        <div id="controls">
            <!-- Το Ρολόι -->
            <div id="timer-container" style="position: fixed; bottom: 20px; left: 20px; background: rgba(0,0,0,0.7); padding: 15px; border-radius: 10px; border: 2px solid #f1c40f;">
                <div style="font-size: 0.8rem; color: #bdc3c7;">ΧΡΟΝΟΣ ΣΕΙΡΑΣ</div>
                <div id="timer-display" style="font-size: 2rem; font-weight: bold; color: #f1c40f; font-family: monospace;">02:00</div>
            </div>

            <div id="dice-display" style="display:none; margin-bottom: 15px;">
                <div class="dice-box" id="d1">-</div>
                <div class="dice-box" id="d2">-</div>
            </div>

            <button id="btn-start-game" onclick="startGame()" class="btn btn-success btn-lg">Έναρξη Παιχνιδιού</button>
            <button id="btn-roll" onclick="rollDice()" class="btn btn-warning btn-lg" style="display:none;">Ρίξε τα Ζάρια!</button>

            <div id="game-controls" style="display:none; margin-top:15px;">
                <button id="btn-pass" onclick="passTurn()" class="btn btn-danger">Πάσο</button>
                <button onclick="resetGame()" class="btn btn-primary">Επανεκκίνηση</button> 
                <!-- Το UpdateAll είναι κρυφό -->
                <button onclick="updateAll()" id="btn-refresh" style="display:none;">Ανανέωση</button>
            </div>
        </div>

    </div>

    <script>
        var myColor = "<?php echo $_SESSION['my_color']; ?>";
        var isHotseat = <?php echo $is_hotseat_js; ?>;
        var pWhite = "<?php echo $name_white; ?>";
        var pBlack = "<?php echo $name_black; ?>";
    </script>

    <script>
        function confirmExit() {
            if (confirm("Θέλετε σίγουρα να αποχωρήσετε από το παιχνίδι;")) {
                window.location.href = "logout.php";
            }
        }
    </script>
    
    <script src="js/fevga.js?v=4"></script> 

</body>
</html>