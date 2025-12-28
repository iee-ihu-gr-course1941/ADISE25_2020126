<?php
// game.php
require_once "lib/dbconnect.php";
session_start();

// 1. ΕΛΕΓΧΟΣ ΑΣΦΑΛΕΙΑΣ:
// Αν δεν υπάρχουν τα ονόματα που έστειλε το login.php, τότε ο χρήστης μπήκε παράνομα.
if (!isset($_SESSION['player1_name']) || !isset($_SESSION['player2_name'])) {
    $_SESSION['error'] = "Πρέπει να κάνετε Login για να παίξετε!";
    header("Location: login.php");
    exit();
}

// 2. ΑΡΧΙΚΟΠΟΙΗΣΗ ΠΑΙΧΝΙΔΙΟΥ (Τρέχει ΜΟΝΟ την πρώτη φορά που μπαίνεις μετά το Login)
// Ελέγχουμε αν έχει οριστεί το 'player_white'. Αν όχι, σημαίνει ότι είναι νέο παιχνίδι.
if (!isset($_SESSION['player_white'])) {

    // Παίρνουμε τα δεδομένα που αποθήκευσε ο "Αστυνομικός" (login.php)
    $p1_name = $_SESSION['player1_name'];
    $p2_name = $_SESSION['player2_name'];
    $choice  = isset($_SESSION['player1_color']) ? $_SESSION['player1_color'] : 'white';

    // Ρύθμιση Session (Ποιος είναι ποιος)
    if ($choice === 'white') {
        $_SESSION['player_white'] = $p1_name; 
        $_SESSION['player_black'] = $p2_name; 
        $_SESSION['my_color'] = 'white'; // Ξεκινάει αυτός που διάλεξε άσπρα
    } else {
        $_SESSION['player_black'] = $p1_name; 
        $_SESSION['player_white'] = $p2_name; 
        $_SESSION['my_color'] = 'black';
    }

    // >>> RESET ΒΑΣΗΣ ΔΕΔΟΜΕΝΩΝ <<< 
    // Αυτό τρέχει ΜΙΑ φορά στην αρχή, οπότε δεν θα μηδενίζει το σκορ στο refresh.
    
    // α) Μηδενισμός Σκορ και Κατάστασης
    $sql_reset_status = "UPDATE game_status SET status='not active', p_turn='W', result=NULL, last_change=NOW(), score_w=0, score_b=0, dice1=NULL, dice2=NULL";
    $mysqli->query($sql_reset_status);

    // β) Καθαρισμός του Board
    $sql_clear_board = "DELETE FROM board"; 
    $mysqli->query($sql_clear_board);
}

// Από εδώ και κάτω συνεχίζει το HTML της σελίδας...
?>
<!DOCTYPE html>
<html>
<head>
    <title>Παιχνίδι Φεύγα</title>
    <link rel="stylesheet" type="text/css" href="style.css?v=6">
    <script>
        let myColor = "<?php echo isset($_SESSION['my_color']) ? $_SESSION['my_color'] : ''; ?>";
        
        // ΝΕΟ: Ελέγχουμε αν έχει οριστεί το 'game_mode' στο session (θα το κάνουμε στο login.php)
        const isHotseat = <?php echo (isset($_SESSION['game_mode']) && $_SESSION['game_mode'] === 'hotseat') ? 'true' : 'false'; ?>;
        
        console.log("My Color is: " + myColor); 
        console.log("Mode Hotseat: " + isHotseat);
    </script>
    <script src="fevga.js" defer></script> 
</head>
<body>

    <div id="scoreboard">
        <table>
            <tr><th><?php echo $_SESSION['player_white']; ?> (A)</th><th><?php echo $_SESSION['player_black']; ?> (M)</th></tr>
            <tr><td id="score-w">0</td><td id="score-b">0</td></tr>
        </table>
    </div>

    <div class="game-wrapper">
        <div class="player-label top-right">
            <button class="btn-surrender" onclick="surrender('black')">Τα παρατάω</button>
            <span class="p-name"><?php echo $_SESSION['player_black']; ?></span>
            <div id="turn-label-b" class="turn-box top">Είναι η σειρά σου</div>
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
            <div id="turn-label-w" class="turn-box bottom">Είναι η σειρά σου</div>
            <span class="p-name"><?php echo $_SESSION['player_white']; ?></span>
            <button class="btn-surrender" onclick="surrender('white')">Τα παρατάω</button>
        </div>
    </div>

    <div id="dice-container">
        <button id="btn-roll" onclick="rollDice()" display="none">Ρίξε τα Ζάρια!</button>
        <div id="dice-display" style="display:none;">
            <div class="dice-box" id="d1">?</div><div class="dice-box" id="d2">?</div>
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