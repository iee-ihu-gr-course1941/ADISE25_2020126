<?php
// tavli.php 
require_once "lib/dbconnect.php"; 
require_once "lib/board.php";
require_once "lib/game_logic.php"; 
require_once "lib/players.php";    

$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['PATH_INFO']??'', '/'));
$input = json_decode(file_get_contents('php://input'),true);

// Δημιουργία κενού πίνακα αν δεν υπάρχει input
if($input==null) {
    $input=[];
}

// Έλεγχος Token 
if (isset($_SERVER['HTTP_APP_TOKEN'])) {
    $input['token'] = $_SERVER['HTTP_APP_TOKEN'];
} elseif (!isset($input['token'])) {
    $input['token'] = '';
}

header('Content-Type: application/json');

// Ο ΚΥΡΙΟΣ ROUTER
switch ($r=array_shift($request)) {
    case 'board':
        handle_board($method, $input); 

        if ($method == 'POST') {
        //reset_players(); 
        }
        break;
        
    case 'status': 
        handle_status($method, $input); 
        break;
        
    case 'player': 
        handle_player($method, $request, $input); 
        break;
        
    default: 
        header("HTTP/1.1 404 Not Found");
        echo json_encode(['error' => 'No route specified']);
        break;
}


?>