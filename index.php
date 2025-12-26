<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="UTF-8">
    <title>Το Τάβλι - Επιλογή Παιχνιδιού</title>
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
        .btn-mode small {
            font-size: 0.7em;
            margin-top: 10px;
            font-weight: normal;
            opacity: 0.9;
        }
        
        /* Κουμπί Hotseat (Πράσινο) */
        .btn-local {
            background-color: #27ae60; 
        }
        .btn-local:hover {
            background-color: #2ecc71;
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.4);
        }

        /* Κουμπί Online (Μπλε - Ανενεργό προς το παρόν) */
        .btn-online {
            background-color: #34495e; /* Σκούρο μπλε/γκρι */
            cursor: not-allowed;
            opacity: 0.6;
        }
        /* Όταν το ενεργοποιήσουμε, θα βγάλουμε τα σχόλια από εδώ:
        .btn-online:hover {
            background-color: #3498db;
            transform: translateY(-5px);
        }
        */
    </style>
</head>
<body>

    <h1>Καλωσήρθατε στο Τάβλι</h1>

    <div class="container">
        <a href="login.php?mode=hotseat" class="btn-mode btn-local">
            <span>🏠 Single PC</span>
            <small>2 Παίκτες στην ίδια οθόνη</small>
        </a>

        <a href="#" class="btn-mode btn-online" onclick="alert('Η Online λειτουργία θα ενεργοποιηθεί σύντομα!'); return false;">
            <span>🌐 Online</span>
            <small>Multiplayer μέσω δικτύου</small>
        </a>
    </div>

</body>
</html>