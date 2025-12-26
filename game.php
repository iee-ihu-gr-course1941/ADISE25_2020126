<?php
// game.php
require_once "lib/dbconnect.php";
session_start();

// Έλεγχος Ασφαλείας: Αν δεν υπάρχουν παίκτες στο session και δεν γίνεται Login, διώξε τον χρήστη
if (!isset($_SESSION['player_white']) || !isset($_SESSION['player_black'])) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_SESSION['error'] = "Πρέπει να κάνετε Login για να παίξετε!";
        header("Location: login.php");
        exit();
    }
}

// Λογική Login (Τρέχει ΜΟΝΟ όταν έρχεσαι από τη φόρμα εισόδου)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['player1']) && !empty($_POST['player2'])) {
        $p1_name = htmlspecialchars($_POST['player1']);
        $p2_name = htmlspecialchars($_POST['player2']);
        $choice  = $_POST['p1_color']; 

        // 1. Ρύθμιση Session (Ποιος είναι ποιος)
        if ($choice === 'white') {
            $_SESSION['player_white'] = $p1_name; 
            $_SESSION['player_black'] = $p2_name; 
            $_SESSION['my_color'] = 'white'; 
        } else {
            $_SESSION['player_black'] = $p1_name; 
            $_SESSION['player_white'] = $p2_name; 
            $_SESSION['my_color'] = 'black';
        }

        // 2. >>> RESET ΒΑΣΗΣ ΔΕΔΟΜΕΝΩΝ <<< 
        // Αυτό λύνει και τα δύο προβλήματά σου!
        
        // α) Μηδενισμός Σκορ και Κατάστασης (ώστε να εμφανιστεί το κουμπί 'Έναρξη')
        $sql_reset_status = "UPDATE game_status SET status='not active', p_turn='W', result=NULL, last_change=NOW(), score_w=0, score_b=0, dice1=NULL, dice2=NULL";
        $mysqli->query($sql_reset_status);

        // β) Καθαρισμός του Board (αδειάζουμε τα πούλια για να ξεκινήσουμε καθαρά)
        // Σημείωση: Αν χρησιμοποιείς stored procedure 'clean_board', μπορείς να καλέσεις αυτήν.
        // Εδώ κάνουμε manual καθαρισμό για σιγουριά.
        $sql_clear_board = "DELETE FROM board"; 
        $mysqli->query($sql_clear_board);
        
        // γ) Αρχική τοποθέτηση (προαιρετικό, συνήθως γίνεται στο 'startGame', 
        // αλλά αν το κάνουμε clear, καλό είναι να είναι άδειο).
        
    } else {
        $_SESSION['error'] = "Παρακαλώ συμπληρώστε ονόματα!";
        header("Location: login.php");
        exit();
    }
}
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