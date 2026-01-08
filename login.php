<?php
// login.php
require_once "lib/dbconnect.php"; 
session_start();

// --- ΕΛΕΓΧΟΣ ΚΑΤΑΣΤΑΣΗΣ ΠΑΙΧΝΙΔΙΟΥ (ONLINE MODE) ---
$game_full = false;
$taken_color = null;

if (isset($_GET['mode']) && $_GET['mode'] == 'online') {
    $sql = "SELECT count(*) as c FROM players WHERE username IS NOT NULL AND last_action > (NOW() - INTERVAL 5 MINUTE)";
    $res = $mysqli->query($sql);
    $active_players = $res->fetch_assoc()['c'];

    if ($active_players >= 2) {
        $game_full = true;
    }

    if (!$game_full) {
        $sql = "SELECT piece_color FROM players WHERE username IS NOT NULL AND last_action > (NOW() - INTERVAL 5 MINUTE)";
        $res = $mysqli->query($sql);
        if ($row = $res->fetch_assoc()) {
            $taken_color = $row['piece_color']; 
        }
    }
}

// --- ΚΑΘΑΡΙΣΜΟΣ SESSION ---
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $temp_error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
    $temp_mode = isset($_SESSION['game_mode']) ? $_SESSION['game_mode'] : '';
    session_unset(); 
    $_SESSION['game_mode'] = $temp_mode;
    if($temp_error) $_SESSION['error'] = $temp_error;
}

// --- LOGIC ΓΙΑ ΤΟ POST (LOGIN) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $p1 = isset($_POST['player1']) ? trim($_POST['player1']) : '';
    $p2 = isset($_POST['player2']) ? trim($_POST['player2']) : '';
    $p1_color = isset($_POST['p1_color']) ? $_POST['p1_color'] : 'white';

    if (empty($p1)) {
        $_SESSION['error'] = "Παρακαλώ συμπληρώστε το όνομά σας!";
        header("Location: login.php"); 
        exit();
    }
    
    $is_hotseat = ($_SESSION['game_mode'] === 'hotseat');
    if ($is_hotseat && empty($p2)) {
        $_SESSION['error'] = "Παρακαλώ συμπληρώστε και το όνομα του 2ου παίκτη!";
        header("Location: login.php"); 
        exit();
    }

    // Αποθήκευση στο Session
    $_SESSION['player1_name'] = $p1;
    $_SESSION['player2_name'] = $p2;
    $_SESSION['player1_color'] = $p1_color;

    // ---------------------------------------------------------
    // --- ΝΕΟΣ ΚΩΔΙΚΑΣ: ΕΓΓΡΑΦΗ ΣΤΗ ΒΑΣΗ ΔΕΔΟΜΕΝΩΝ (SQL) ---
    // ---------------------------------------------------------
    
    // Δημιουργούμε ένα μοναδικό Token για τον παίκτη
    $token = md5(uniqid(rand(), true));
    $_SESSION['token'] = $token; // Το αποθηκεύουμε για να ξέρουμε ποιοι είμαστε

    if ($is_hotseat) {
        // ΑΝ ΕΙΝΑΙ HOTSEAT: Ενημερώνουμε και τους δύο παίκτες
        // 1. Παίκτης 1
        $c1_db = ($p1_color == 'white') ? 'W' : 'B';
        $sql = "UPDATE players SET username=?, token=?, last_action=NOW() WHERE piece_color=?";
        $st = $mysqli->prepare($sql);
        $st->bind_param('sss', $p1, $token, $c1_db);
        $st->execute();

        // 2. Παίκτης 2
        $c2_db = ($c1_db == 'W') ? 'B' : 'W';
        $sql = "UPDATE players SET username=?, token=?, last_action=NOW() WHERE piece_color=?";
        $st = $mysqli->prepare($sql);
        $st->bind_param('sss', $p2, $token, $c2_db); // Χρησιμοποιούμε το ίδιο token στο Hotseat για ευκολία
        $st->execute();

    } else {
        // ΑΝ ΕΙΝΑΙ ONLINE: Ενημερώνουμε μόνο τον εαυτό μας
        $color_db = ($p1_color == 'white') ? 'W' : 'B';
        
        $sql = "UPDATE players SET username=?, token=?, last_action=NOW() WHERE piece_color=?";
        $st = $mysqli->prepare($sql);
        $st->bind_param('sss', $p1, $token, $color_db);
        $st->execute();
    }
    // ---------------------------------------------------------
    // --- ΤΕΛΟΣ ΝΕΟΥ ΚΩΔΙΚΑ ---
    // ---------------------------------------------------------
    
    header("Location: game.php");
    exit();
}

// Λήψη Mode
if (isset($_GET['mode'])) { $_SESSION['game_mode'] = $_GET['mode']; }
if (!isset($_SESSION['game_mode'])) { header("Location: index.php"); exit(); }

$is_hotseat = ($_SESSION['game_mode'] === 'hotseat');
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

    <?php if (!$is_hotseat && $game_full): ?>
        <div class="full-msg">⚠️ Το παιχνίδι είναι πλήρες!</div>
        <p>Υπάρχουν ήδη 2 παίκτες συνδεδεμένοι.</p>
        <a href="index.php" class="btn-login" style="display:block; text-decoration:none; background:#34495e;">Επιστροφή</a>
    <?php else: ?>
        <form action="login.php" method="POST"> 
            <label for="player1">Όνομα Παίκτη 1:</label>
            <input type="text" id="player1" name="player1" placeholder="Όνομα..." required>
            
            <label for="p1_color">Ο Παίκτης 1 παίζει με:</label>
            <select id="p1_color" name="p1_color">
                <?php if ($taken_color == 'W'): ?>
                    <option value="white" disabled>Άσπρα (Πιασμένο)</option>
                    <option value="black" selected>Μαύρα</option>
                <?php elseif ($taken_color == 'B'): ?>
                    <option value="white" selected>Άσπρα</option>
                    <option value="black" disabled>Μαύρα (Πιασμένο)</option>
                <?php else: ?>
                    <option value="white" selected>Άσπρα</option>
                    <option value="black">Μαύρα</option>
                <?php endif; ?>
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