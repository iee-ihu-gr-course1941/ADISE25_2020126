<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"> 
    <title>Το Τάβλι - Επιλογή Παιχνιδιού</title>
    
    <link href="bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <script src="bootstrap/jquery-3.2.1.min.js"></script>
    <script src="bootstrap/popper.min.js"></script> 
    <script src="bootstrap/bootstrap.min.js"></script>

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #2c3e50;
            color: #ecf0f1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        h1 { 
            margin-bottom: 40px; 
            text-shadow: 2px 2px 4px #000; 
            font-size: 2.5em;
        }
        .container {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
        }
        /* Κάνουμε τα κουμπιά να φαίνονται σωστά ακόμα και με Bootstrap */
        .btn-mode {
            padding: 30px 50px;
            font-size: 20px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: transform 0.2s, background 0.3s, box-shadow 0.3s;
            text-decoration: none;
            color: white;
            font-weight: bold;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 200px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .btn-mode:hover { color: white; text-decoration: none; } /* Fix για Bootstrap link hover */
        
        .btn-mode small {
            font-size: 0.7em;
            margin-top: 10px;
            font-weight: normal;
            opacity: 0.9;
        }
        
        .btn-local { background-color: #27ae60; }
        .btn-local:hover { background-color: #2ecc71; transform: translateY(-5px); }

        .btn-online { background-color: #34495e; }
        
        .btn-online:hover { background-color: #2c3e50; 
            transform: translateY(-5px); 
        }
    </style>
</head>
<body>

    <h1>Καλωσήρθατε στο Τάβλι</h1>

    <div class="container">
        <a href="login.php?mode=hotseat" class="btn-mode btn-local text-decoration-none">
            <span>🏠 Single PC</span>
            <small>2 Παίκτες στην ίδια οθόνη</small>
        </a>

        <a href="login.php?mode=online" class="btn-mode btn-online text-decoration-none">
            <span>🌐 Online</span>
            <small>Multiplayer μέσω δικτύου</small>
        </a>
    </div>

</body>
</html>