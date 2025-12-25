<?php
// index.php
session_start();

// Προαιρετικό: Αν θέλουμε να εμφανίσουμε κάποιο μήνυμα λάθους που μας έστειλε το game.php
$error = "";
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']); // Το σβήνουμε για να μην εμφανίζεται συνέχεια
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Είσοδος στο παιχνίδι</title>
    <link rel="stylesheet" type="text/css" href="style.css"> 
    <style>
        /* CSS ειδικά για τη σελίδα εισόδου */
        body { font-family: sans-serif; text-align: center; background-color: #2c3e50; color: #333; }
        .login-box { 
            margin: 100px auto; 
            padding: 30px; 
            background: white; 
            width: 320px; 
            border-radius: 8px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.3); 
        }
        h2 { color: #2c3e50; }
        .form-group { text-align: left; margin-bottom: 15px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
        input, select { 
            padding: 10px; 
            width: 100%; 
            box-sizing: border-box; /* Για να μην βγαίνουν έξω από το κουτί */
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
    </style>
</head>
<body>

<div class="login-box">
    <h2>Καλωσήρθες στο Τάβλι</h2>
    
    <?php if(!empty($error)) { echo "<p style='color:red; font-weight:bold;'>$error</p>"; } ?>
    
    <form action="game.php" method="POST">
    
        <div class="form-group">
            <label for="player1">Όνομα Παίκτη 1:</label>
            <input type="text" id="player1" name="player1" placeholder="Όνομα..." required>
            
            <label for="p1_color" style="margin-top: 10px; font-size: 0.9em; color:#666;">Επέλεξε πούλια:</label>
            <select id="p1_color" name="p1_color">
                <option value="white">Άσπρα</option>
                <option value="black">Μαύρα</option>
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

</body>
</html>