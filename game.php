<?php
// game.php
require_once "lib/dbconnect.php";
session_start();

// Έλεγχος Ασφαλείας
if (!isset($_SESSION['player_white']) || !isset($_SESSION['player_black'])) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_SESSION['error'] = "Πρέπει να κάνετε Login για να παίξετε!";
        header("Location: index.php");
        exit();
    }
}

// Λογική Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['player1']) && !empty($_POST['player2'])) {
        $p1_name = htmlspecialchars($_POST['player1']);
        $p2_name = htmlspecialchars($_POST['player2']);
        $choice  = $_POST['p1_color']; 

        // Εδώ ορίζουμε ποιος είμαι ΕΓΩ σε αυτόν τον browser
        if ($choice === 'white') {
            $_SESSION['player_white'] = $p1_name; 
            $_SESSION['player_black'] = $p2_name; 
            $_SESSION['my_color'] = 'white'; 
        } else {
            // Αν επέλεξα "Μαύρα", τότε εγώ (ο παίκτης 1 της φόρμας) είμαι ο Μαύρος
            $_SESSION['player_black'] = $p1_name; 
            $_SESSION['player_white'] = $p2_name; 
            $_SESSION['my_color'] = 'black';
        }

        if (!isset($_SESSION['turn'])) $_SESSION['turn'] = 'white';

        // --- ΠΡΟΣΟΧΗ: ΑΦΑΙΡΕΣΑΜΕ ΤΟΝ ΜΗΔΕΝΙΣΜΟ ΑΠΟ ΕΔΩ ---
        // Ο μηδενισμός θα γίνεται μόνο με το κουμπί "Έναρξη Παιχνιδιού"
        // έτσι ώστε αν μπει ο 2ος παίκτης να μην χαλάσει το ματς.

    } else {
        $_SESSION['error'] = "Παρακαλώ συμπληρώστε ονόματα!";
        header("Location: index.php");
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
        const myColor = "<?php echo isset($_SESSION['my_color']) ? $_SESSION['my_color'] : ''; ?>";
        console.log("My Color is: " + myColor); 
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
        <button id="btn-roll" onclick="rollDice()">Ρίξε τα Ζάρια!</button>
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