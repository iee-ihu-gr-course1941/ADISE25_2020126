<?php
// login.php
require_once "./lib/dbconnect.php"; 
session_start();

//Λήψη Mode
if (isset($_GET['mode'])) { $_SESSION['game_mode'] = $_GET['mode']; }
$requested_mode = isset($_SESSION['game_mode']) ? $_SESSION['game_mode'] : null;
if (!$requested_mode) { header("Location: index.php"); exit(); }

$is_hotseat = ($requested_mode === 'hotseat');

//Καθαρισμός ανενεργών για να ξέρουμε πόσοι είναι μέσα
$mysqli->query("UPDATE players SET username=NULL, token=NULL, last_action=NULL WHERE last_action < (NOW() - INTERVAL 5 MINUTE)");

$res_count = $mysqli->query("SELECT count(*) as c FROM players WHERE username IS NOT NULL");
$active_players = $res_count->fetch_assoc()['c'];

if ($active_players == 0) {
    $mysqli->query("call clean_board()");
}

$game_full = false;
$taken_color = null;
$opponent_name = null;

// Έλεγχοι πληρότητας και ονόματος αντιπάλου
if ($active_players >= 2) {
    $game_full = true;
} elseif ($active_players == 1) {
    if ($is_hotseat) {
        $game_full = true; 
    } else {
        $res_opp = $mysqli->query("SELECT username, piece_color FROM players WHERE username IS NOT NULL LIMIT 1");
        $row_opp = $res_opp->fetch_assoc();
        $taken_color = $row_opp['piece_color'];
        $opponent_name = $row_opp['username'];
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $p1 = isset($_POST['player1']) ? trim($_POST['player1']) : '';
    $p2 = isset($_POST['player2']) ? trim($_POST['player2']) : '';
    $p1_color = isset($_POST['p1_color']) ? $_POST['p1_color'] : 'white';

    //Κενά ονόματα
    if (empty($p1) || ($is_hotseat && empty($p2))) {
        $_SESSION['error'] = "Παρακαλώ συμπληρώστε τα ονόματα!";
        header("Location: login.php"); exit();
    }

    //Ίδια ονόματα (Hotseat)
    if ($is_hotseat && strtolower($p1) === strtolower($p2)) {
        $_SESSION['error'] = "Οι παίκτες πρέπει να έχουν διαφορετικά ονόματα!";
        header("Location: login.php"); exit();
    }

    //Ίδιο όνομα με αντίπαλο (Online)
    if (!$is_hotseat && $opponent_name && strtolower($p1) === strtolower($opponent_name)) {
        $_SESSION['error'] = "Αυτό το όνομα το έχει ήδη ο αντίπαλος!";
        header("Location: login.php"); exit();
    }

    $_SESSION['player1_name'] = $p1;
    $_SESSION['player2_name'] = $p2;
    $_SESSION['player1_color'] = $p1_color;

    // Δημιουργία μοναδικών Tokens
    $token1 = md5(uniqid($p1 . '1', true));
    $token2 = md5(uniqid($p2 . '2', true));
    $_SESSION['token'] = $token1;

    if ($is_hotseat) {
        $c1_db = ($p1_color == 'white') ? 'W' : 'B';
        $c2_db = ($c1_db == 'W') ? 'B' : 'W';
        $mysqli->query("UPDATE players SET username='$p1', token='$token1', last_action=NOW() WHERE piece_color='$c1_db'");
        $mysqli->query("UPDATE players SET username='$p2', token='$token2', last_action=NOW() WHERE piece_color='$c2_db'");
    } else {
        $color_db = ($p1_color == 'white') ? 'W' : 'B';
        $mysqli->query("UPDATE players SET username='$p1', token='$token1', last_action=NOW() WHERE piece_color='$color_db'");
    }
    
    header("Location: game.php");
    exit();
}

// Καθαρισμός Session στο GET 
if ($_SERVER["REQUEST_METHOD"] == "GET" && !isset($_SESSION['error'])) {
    $m = $_SESSION['game_mode']; session_unset(); $_SESSION['game_mode'] = $m;
}

$error = isset($_SESSION['error']) ? $_SESSION['error'] : "";
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Ρυθμίσεις Παιχνιδιού</title>
    <link href="bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <script src="bootstrap/jquery-3.2.1.min.js"></script>
    <script src="bootstrap/popper.min.js"></script> 
    <script src="bootstrap/bootstrap.min.js"></script>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #2c3e50; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 2rem; border-radius: 10px; width: 350px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        input[type="text"], select { width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #ddd; box-sizing: border-box; font-size: 1rem;}
        .btn-login { width: 100%; padding: 12px; background-color: #f39c12; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 1.1rem; transition: 0.3s;}
        .btn-login:hover { background-color: #e67e22; }
        .error-msg { color: #e74c3c; font-weight: bold; margin-bottom: 15px; }
        .full-msg { color: #e74c3c; font-size: 1.2rem; font-weight: bold; margin: 20px 0; }
        label { display: block; text-align: left; font-weight: bold; margin-bottom: 5px; color: #2c3e50; }
    </style>
</head>
<body>

<div class="login-card">
    <h2>Ρυθμίσεις Παιχνιδιού</h2>
    <p style="color:#7f8c8d; font-size:0.9rem; margin-top: 0;">Λειτουργία: <?php echo $is_hotseat ? 'Hotseat' : 'Online Multiplayer'; ?></p>

    <?php if($error): ?><div class="error-msg"><?php echo $error; ?></div><?php endif; ?>

    <?php if ($game_full): ?>
        <div class="full-msg">⚠️ Το παιχνίδι είναι πλήρες!</div>
        <p>Υπάρχουν ήδη ενεργοί παίκτες στο παιχνίδι.</p>
        <a href="index.php" class="btn-login" style="display:block; text-decoration:none; background:#34495e;">Επιστροφή</a>
    <?php else: ?>
        <form action="login.php" method="POST"> 
            <label for="player1">Όνομα Παίκτη 1:</label>
            <input type="text" id="player1" name="player1" placeholder="Όνομα..." required>
            
            <label for="p1_color">Επιλέξτε χρώμα:</label>
            <select id="p1_color" name="p1_color">
                <option value="white" 
                    <?php if($taken_color == 'W') echo 'disabled'; ?> 
                    <?php if($taken_color == 'B') echo 'selected'; ?>>
                    Άσπρα <?php if($taken_color == 'W') echo '(Πιασμένο)'; ?>
                </option>

                <option value="black" 
                    <?php if($taken_color == 'B') echo 'disabled'; ?> 
                    <?php if($taken_color == 'W') echo 'selected'; ?>>
                    Μαύρα <?php if($taken_color == 'B') echo '(Πιασμένο)'; ?>
                </option>
            </select>

            <hr> 

            <label for="player2">Όνομα Παίκτη 2:</label>
            <input type="text" id="player2" name="player2" placeholder="<?php echo $is_hotseat ? 'Όνομα...' : 'Αναμονή...'; ?>" <?php echo $is_hotseat ? 'required' : 'disabled'; ?>>

            <?php if ($is_hotseat): ?>
            <label for="p2_color_display">Ο Παίκτης 2 παίζει με:</label>
            <select id="p2_color_display">
                <option value="black" selected>Μαύρα</option>
                <option value="white">Άσπρα</option> 
            </select>
            <?php endif; ?>

            <button type="submit" class="btn-login" style="margin-top: 20px;">Έναρξη Παιχνιδιού</button>
        </form>
    <?php endif; ?>
    
    <br>
    <?php if (!(!$is_hotseat && $game_full)): ?>
        <a href="index.php" style="color:#bdc3c7; text-decoration:none;">← Επιστροφή στην επιλογή</a>
    <?php endif; ?>
</div>

<?php if ($is_hotseat): ?>
<script>
    const p1Select = document.getElementById('p1_color');
    const p2Select = document.getElementById('p2_color_display');
    function syncColors() {
        p2Select.value = (p1Select.value === 'white') ? 'black' : 'white';
    }
    p1Select.addEventListener('change', syncColors);
</script>
<?php endif; ?>

</body>
</html>