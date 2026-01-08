<?php
// game.php
require_once "lib/dbconnect.php";
session_start();

if (!isset($_SESSION['player1_name']) || !isset($_SESSION['player2_name'])) {
    $_SESSION['error'] = "Πρέπει να κάνετε Login για να παίξετε!";
    header("Location: login.php");
    exit();
}

// --- LOGIC FIX: Υπολογισμός ονομάτων για JavaScript και Session ---
$name_white = "";
$name_black = "";

if (isset($_SESSION['player1_color']) && $_SESSION['player1_color'] == 'white') {
    $name_white = $_SESSION['player1_name'];
    $name_black = $_SESSION['player2_name'];
} else {
    $name_white = $_SESSION['player2_name']; 
    $name_black = $_SESSION['player1_name'];
}

// Αρχικοποίηση Session (αν δεν έχει γίνει ήδη)
if (!isset($_SESSION['player_white'])) {
    $_SESSION['player_white'] = $name_white; 
    $_SESSION['player_black'] = $name_black; 
    
    // Ποιο χρώμα είμαι εγώ;
    if($_SESSION['player1_name'] == $name_white) {
        $_SESSION['my_color'] = 'white';
    } else {
        $_SESSION['my_color'] = 'black';
    }
    
    $is_hotseat = (isset($_SESSION['game_mode']) && $_SESSION['game_mode'] === 'hotseat');
    
    // ==================================================================
    // ΝΕΟΣ ΚΩΔΙΚΑΣ: ΚΑΘΑΡΙΣΜΟΣ ZOMBIE GAMES
    // ==================================================================
    
    // 1. Τραβάμε status ΚΑΙ χρόνο τελευταίας αλλαγής
    $status_data = $mysqli->query("SELECT status, last_change FROM game_status")->fetch_assoc();
    $status_check = $status_data['status'];
    
    // 2. Υπολογίζουμε πόση ώρα έχει περάσει (σε δευτερόλεπτα)
    $last_active_time = strtotime($status_data['last_change']);
    $time_diff = time() - $last_active_time; // Τωρινή ώρα μείον ώρα βάσης

    // 3. Μετράμε παίκτες
    $players_count = $mysqli->query("SELECT count(*) as c FROM players WHERE username IS NOT NULL")->fetch_assoc()['c'];

    // Η ΣΥΝΘΗΚΗ: Καθαρίζουμε αν είναι Hotseat, ή Aborted, ή κενό, 
    // Ή αν είναι 'started' ΑΛΛΑ έχουν περάσει πάνω από 15 λεπτά (900 δευτ.) αδράνειας
    if ($is_hotseat || $status_check === 'aborted' || $players_count == 0 || ($status_check === 'started' && $time_diff > 900)) {
        
        $mysqli->query("call clean_board()");
        $mysqli->query("UPDATE game_status SET status='not active', result=NULL, p_turn=NULL");
    }
    // ==================================================================
}

// Υπολογισμός μεταβλητής για JS
$is_hotseat_js = (isset($_SESSION['game_mode']) && $_SESSION['game_mode'] === 'hotseat') ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Το Φεύγα μου</title>
    
    <link href="bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet"> 

    <script src="bootstrap/jquery-3.2.1.min.js"></script>
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
            <span id="p-name-w"><?php echo $_SESSION['player_white']; ?></span> (Άσπρα)
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
                <button onclick="resetGame()" class="btn btn-primary">Επανεκκίνηση</button> 
                <button onclick="updateAll()" class="btn btn-warning">Ανανέωση</button>
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