<?php
// game.php
session_start();

// Αν κάποιος προσπαθήσει να μπει εδώ χωρίς να κάνει login, τον διώχνουμε
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Παιχνίδι Φεύγα</title>
    <link rel="stylesheet" type="text/css" href="style.css">
</head>
<body>

    <h1>Γεια σου, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
    
    <div class="board">
        
        <div class="half-board left">
            <div class="row top">
                <div class="point" id="p13"></div>
                <div class="point" id="p14"></div>
                <div class="point" id="p15"></div>
                <div class="point" id="p16"></div>
                <div class="point" id="p17"></div>
                <div class="point" id="p18"></div>
            </div>
            <div class="row bottom">
                <div class="point" id="p12"></div>
                <div class="point" id="p11"></div>
                <div class="point" id="p10"></div>
                <div class="point" id="p9"></div>
                <div class="point" id="p8"></div>
                <div class="point" id="p7"></div>
            </div>
        </div>

        <div class="bar"></div>

        <div class="half-board right">
            <div class="row top">
                 <div class="point" id="p19"></div>
                <div class="point" id="p20"></div>
                <div class="point" id="p21"></div>
                <div class="point" id="p22"></div>
                <div class="point" id="p23"></div>
                <div class="point" id="p24"></div>
            </div>
            <div class="row bottom">
                <div class="point" id="p6"></div>
                <div class="point" id="p5"></div>
                <div class="point" id="p4"></div>
                <div class="point" id="p3"></div>
                <div class="point" id="p2"></div>
                <div class="point" id="p1"></div>
            </div>
        </div>
        
    </div>

    <br>
    <a href="index.php" style="color: white;">Έξοδος</a>

</body>
</html>