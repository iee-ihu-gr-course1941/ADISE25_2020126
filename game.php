<?php
// game.php
require_once "lib/dbconnect.php"; // Συνδέουμε τη βάση για να μπορούμε να μηδενίσουμε το σκορ
session_start();

// --- ΕΛΕΓΧΟΣ ΑΣΦΑΛΕΙΑΣ ---
if (!isset($_SESSION['player_white']) || !isset($_SESSION['player_black'])) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_SESSION['error'] = "Πρέπει να κάνετε Login για να παίξετε!";
        header("Location: index.php");
        exit();
    }
}

// --- ΛΟΓΙΚΗ LOGIN (ΝΕΟ ΠΑΙΧΝΙΔΙ) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['player1']) && !empty($_POST['player2'])) {
        
        // 1. Αποθήκευση Ονομάτων στο Session
        $p1_name = htmlspecialchars($_POST['player1']);
        $p2_name = htmlspecialchars($_POST['player2']);
        $choice  = $_POST['p1_color']; 

        if ($choice === 'white') {
            $_SESSION['player_white'] = $p1_name; 
            $_SESSION['player_black'] = $p2_name; 
        } else {
            $_SESSION['player_black'] = $p1_name; 
            $_SESSION['player_white'] = $p2_name; 
        }

        if (!isset($_SESSION['turn'])) {
            $_SESSION['turn'] = 'white';
        }

        // 2. ΜΗΔΕΝΙΣΜΟΣ ΣΚΟΡ & ΚΑΘΑΡΙΣΜΟΣ ΤΡΑΠΕΖΙΟΥ (SQL)
        // Αυτό τρέχει ΜΟΝΟ όταν πατάς "Έναρξη" από την αρχική σελίδα
        
        // Μηδενίζουμε το σκορ
        $sql_score = "UPDATE game_status SET score_w=0, score_b=0";
        $mysqli->query($sql_score);

        // Καλούμε την clear_game() για να αδειάσει το ταμπλό και να βγει το κουμπί 'Έναρξη'
        $sql_clear = "call clear_game()";
        $mysqli->query($sql_clear);

    } else {
        $_SESSION['error'] = "Παρακαλώ συμπληρώστε και τα δύο ονόματα!";
        header("Location: index.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Παιχνίδι Φεύγα</title>
    <link rel="stylesheet" type="text/css" href="style.css?v=3">
    <script src="fevga.js" defer></script> 
</head>
<body>

    <div id="scoreboard">
        <table>
            <tr>
                <th><?php echo $_SESSION['player_white']; ?> (A)</th>
                <th><?php echo $_SESSION['player_black']; ?> (M)</th>
            </tr>
            <tr>
                <td id="score-w">0</td>
                <td id="score-b">0</td>
            </tr>
        </table>
    </div>

    <div class="game-wrapper">
        <div class="player-label top-right">
            <button class="btn-surrender" onclick="surrender('black')">Τα παρατάω</button>
            <span class="p-name"><?php echo $_SESSION['player_black']; ?></span>
        </div>
        
        <div class="board">
            <div class="half-board left">
                <div class="row top">
                    <div class="point" id="p13"></div><div class="point" id="p14"></div>
                    <div class="point" id="p15"></div><div class="point" id="p16"></div>
                    <div class="point" id="p17"></div><div class="point" id="p18"></div>
                </div>
                <div class="row bottom">
                    <div class="point" id="p12"></div><div class="point" id="p11"></div>
                    <div class="point" id="p10"></div><div class="point" id="p9"></div>
                    <div class="point" id="p8"></div><div class="point" id="p7"></div>
                </div>
            </div>
            <div class="bar"></div>
            <div class="half-board right">
                <div class="row top">
                    <div class="point" id="p19"></div><div class="point" id="p20"></div>
                    <div class="point" id="p21"></div><div class="point" id="p22"></div>
                    <div class="point" id="p23"></div><div class="point" id="p24"></div>
                </div>
                <div class="row bottom">
                    <div class="point" id="p6"></div><div class="point" id="p5"></div>
                    <div class="point" id="p4"></div><div class="point" id="p3"></div>
                    <div class="point" id="p2"></div><div class="point" id="p1"></div>
                </div>
            </div>
        </div>

        <div class="player-label bottom-left">
            <span class="p-name"><?php echo $_SESSION['player_white']; ?></span>
            <button class="btn-surrender" onclick="surrender('white')">Τα παρατάω</button>
        </div>
    </div>

    <div id="dice-container">
        <button id="btn-roll" onclick="rollDice()">Ρίξε τα Ζάρια!</button>
        <div id="dice-display" style="display:none;">
            <div class="dice-box" id="d1">?</div>
            <div class="dice-box" id="d2">?</div>
        </div>
    </div>

    <div id="controls">
        <button id="btn-start-game" onclick="startGame()">Έναρξη Παιχνιδιού</button>

        <div id="game-controls" style="display:none;">
            <button onclick="resetGame()">Νέο Παιχνίδι (Reset)</button>
            <button onclick="updateAll()">Ανανέωση</button>
        </div>

        <br>
        <a href="logout.php" style="color: white; display:inline-block; margin-top:15px;">Έξοδος</a>
    </div>

</body>
</html>