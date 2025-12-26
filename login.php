<?php
// login.php (Πρώην index.php)
session_start();

// 1. Λήψη του Mode από το URL (αν υπάρχει) και αποθήκευση στο Session
if (isset($_GET['mode'])) {
    $_SESSION['game_mode'] = $_GET['mode'];
}

// Ασφάλεια: Αν κάποιος ανοίξει το login.php απευθείας χωρίς να έχει επιλέξει mode,
// τον στέλνουμε πίσω στο index.php για να διαλέξει.
if (!isset($_SESSION['game_mode'])) {
    header("Location: index.php");
    exit();
}

$is_hotseat = ($_SESSION['game_mode'] === 'hotseat');

// Διαχείριση Μηνυμάτων Λάθους
$error = "";
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Είσοδος - <?php echo $is_hotseat ? 'Τοπικό Παιχνίδι' : 'Online'; ?></title>
    <link rel="stylesheet" type="text/css" href="style.css"> 
    <style>
        /* CSS ειδικά για τη σελίδα εισόδου */
        body { font-family: sans-serif; text-align: center; background-color: #2c3e50; color: #333; }
        .login-box { 
            margin: 60px auto; 
            padding: 30px; 
            background: white; 
            width: 320px; 
            border-radius: 8px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.3); 
            position: relative;
        }
        h2 { color: #2c3e50; margin-top: 0; }
        .subtitle { color: #7f8c8d; font-size: 0.9em; margin-bottom: 20px; display: block; }
        .form-group { text-align: left; margin-bottom: 15px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
        input, select { 
            padding: 10px; 
            width: 100%; 
            box-sizing: border-box; 
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button { 
            padding: 12px 20px; 
            cursor: pointer; 
            background-color: #ff9800; 
            color: white; 
            border: none; 
            width: 100%;
            font-size: 16px;
            font-weight: bold;
            border-radius: 4px;
            margin-top: 10px;
        }
        button:hover { background-color: #e68900; }
        hr { border: 0; border-top: 1px solid #eee; margin: 20px 0; }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #bdc3c7;
            text-decoration: none;
            font-size: 0.9em;
        }
        .back-link:hover { color: white; }
    </style>
</head>
<body>

<div class="login-box">
    <h2>Ρυθμίσεις Παιχνιδιού</h2>
    <span class="subtitle">
        <?php echo $is_hotseat ? 'Λειτουργία: Ένας Υπολογιστής (Hotseat)' : 'Λειτουργία: Online Multiplayer'; ?>
    </span>
    
    <?php if(!empty($error)) { echo "<p style='color:red; font-weight:bold;'>$error</p>"; } ?>
    
    <form action="game.php" method="POST">
    
        <div class="form-group">
            <label for="player1">Όνομα Παίκτη 1:</label>
            <input type="text" id="player1" name="player1" placeholder="Όνομα..." required>
            
            <label for="p1_color" style="margin-top: 10px; font-size: 0.9em; color:#666;">Ο Παίκτης 1 παίζει με:</label>
            <select id="p1_color" name="p1_color">
                <option value="white">Άσπρα (Παίζει πρώτος)</option>
                <option value="black">Μαύρα (Παίζει δεύτερος)</option>
            </select>
        </div>

        <hr> 

        <div class="form-group">
            <label for="player2">Όνομα Παίκτη 2:</label>
            <input type="text" id="player2" name="player2" placeholder="Όνομα..." required>
        </div>

        <button type="submit" class="btn-login">Έναρξη Παιχνιδιού</button>
    </form>
</div>

<a href="index.php" class="back-link">← Επιστροφή στην επιλογή</a>

</body>
</html>