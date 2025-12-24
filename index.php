<?php

session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];

    if (!empty($username)) {
        $_SESSION['username'] = $username;
        header("Location: game.php");
        exit();
    } else {
        $error = "Παρακαλώ δώσε ένα όνομα!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Είσοδος στο παιχνίδι</title>
    <link rel="stylesheet" type="text/css" href="style.css">
    <style>
        body { font-family: sans-serif; text-align: center; background-color: #f0f0f0; }
        .login-box { 
            margin: 100px auto; 
            padding: 20px; 
            background: white; 
            width: 300px; 
            border-radius: 8px; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
        }
        input { padding: 10px; margin: 10px 0; width: 80%; }
        button { padding: 10px 20px; cursor: pointer; background-color: #28a745; color: white; border: none; }
    </style>
</head>
<body>

<div class="login-box">
    <h2>Καλωσήρθες στο Τάβλι</h2>
    <?php if(isset($error)) { echo "<p style='color:red'>$error</p>"; } ?>
    
    <form method="post" action="index.php">
        <label>Όνομα Παίκτη:</label><br>
        <input type="text" name="username" placeholder="Γράψε το όνομά σου"><br>
        <button type="submit">Login / Είσοδος</button>
    </form>
</div>

</body>
</html>