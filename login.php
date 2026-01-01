<?php
// login.php
session_start();

// --- ΚΑΘΑΡΙΣΜΟΣ SESSION --- Όταν κάποιος μπαίνει στην σελίδα χωρίς να έχει πατήσει το κουμπί.
// Κρατάει προσωρινά backup error και mode. Κάνουμε νέο session με ναέα δεδομένα και βάζουμε στο νέο session τα προηγούμενα 
// error και mode.
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    $temp_error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
    $temp_mode = isset($_SESSION['game_mode']) ? $_SESSION['game_mode'] : '';
    session_unset(); 
    $_SESSION['game_mode'] = $temp_mode;
    if($temp_error) $_SESSION['error'] = $temp_error;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    //Ternary operatior -> Σε μία γραμμή
    $p1 = isset($_POST['player1']) ? trim($_POST['player1']) : '';
    $p2 = isset($_POST['player2']) ? trim($_POST['player2']) : '';
    $p1_color = isset($_POST['p1_color']) ? $_POST['p1_color'] : 'white';
    $p2_color = ($p1_color === 'white') ? 'black' : 'white';

    if (empty($p1) || empty($p2)) {
        $_SESSION['error'] = "Παρακαλώ συμπληρώστε έγκυρα ονόματα!";
        header("Location: login.php"); 
        exit();
    }
    if ($p1 === $p2) {
        $_SESSION['error'] = "Οι παίκτες δεν μπορούν να έχουν το ίδιο όνομα!";
        header("Location: login.php");
        exit();
    }

    //Αν δεν βρει λάθος...
    $_SESSION['player1_name'] = $p1;
    $_SESSION['player2_name'] = $p2;
    $_SESSION['player1_color'] = $p1_color;
    
    header("Location: game.php");
    exit();
}

// Λήψη Mode
if (isset($_GET['mode'])) { 
    $_SESSION['game_mode'] = $_GET['mode']; 
}

//Αν κάποιος προσπαθήσει να μπει χωρίς να έχει επιλέξει mode, επιστρέφει στην αρχική σελίδα.
if (!isset($_SESSION['game_mode'])) { 
    header("Location: index.php"); 
    exit(); 
}

$is_hotseat = ($_SESSION['game_mode'] === 'hotseat');

$error = isset($_SESSION['error']) ? $_SESSION['error'] : "";
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Ρυθμίσεις Παιχνιδιού</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #2c3e50; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-card { background: white; padding: 2rem; border-radius: 10px; width: 350px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        input[type="text"], select { width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #ddd; box-sizing: border-box; font-size: 1rem;}
        .btn-login { width: 100%; padding: 12px; background-color: #f39c12; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 1.1rem; transition: 0.3s;}
        .btn-login:hover { background-color: #e67e22; }
        .error-msg { color: #e74c3c; font-weight: bold; margin-bottom: 15px; }
        label { display: block; text-align: left; font-weight: bold; margin-bottom: 5px; color: #2c3e50; }
        h2 { color: #34495e; margin-bottom: 5px; }
        hr { border: 0; border-top: 1px solid #eee; margin: 20px 0; }
    </style>
</head>
<body>

<div class="login-card">
    <h2>Ρυθμίσεις Παιχνιδιού</h2>
    <p style="color:#7f8c8d; font-size:0.9rem; margin-top: 0;">Λειτουργία: <?php echo $is_hotseat ? 'Ένας Υπολογιστής (Hotseat)' : 'Online Multiplayer'; ?></p>

    <?php if($error): ?><div class="error-msg"><?php echo $error; ?></div><?php endif; ?>

    <form action="login.php" method="POST"> 
        <label for="player1">Όνομα Παίκτη 1:</label>
        <input type="text" id="player1" name="player1" placeholder="Όνομα..." value="<?php echo isset($_SESSION['player1_name']) ? htmlspecialchars($_SESSION['player1_name']) : ''; ?>" required>
        
        <label for="p1_color">Ο Παίκτης 1 παίζει με:</label>
        <select id="p1_color" name="p1_color">
            <option value="white" selected>Άσπρα</option>
            <option value="black">Μαύρα</option>
        </select>

        <hr> 

        <label for="player2">Όνομα Παίκτη 2:</label>
        <input type="text" id="player2" name="player2" placeholder="<?php echo $is_hotseat ? 'Όνομα...' : 'Αναμονή...'; ?>" value="<?php echo isset($_SESSION['player2_name']) ? htmlspecialchars($_SESSION['player2_name']) : ''; ?>" <?php echo $is_hotseat ? 'required' : 'disabled'; ?>>

        <label for="p2_color_display">Ο Παίκτης 2 παίζει με:</label>
        <select id="p2_color_display">
            <option value="black" selected>Μαύρα</option>
            <option value="white">Άσπρα</option>
        </select>

        <button type="submit" class="btn-login" style="margin-top: 20px;">Έναρξη Παιχνιδιού</button>
    </form>
    <br>
    <a href="index.php" style="color:#bdc3c7; text-decoration:none;">← Επιστροφή στην επιλογή</a>
</div>

<script>
    const p1Select = document.getElementById('p1_color');
    const p2Select = document.getElementById('p2_color_display');

    function syncColors(changedElement) {
        if (changedElement === p1Select) {
            p2Select.value = (p1Select.value === 'white') ? 'black' : 'white';
        } else {
            p1Select.value = (p2Select.value === 'white') ? 'black' : 'white';
        }
    }

    p1Select.addEventListener('change', function() { syncColors(this); });
    p2Select.addEventListener('change', function() { syncColors(this); });

    syncColors(p1Select);
</script>

</body>
</html>