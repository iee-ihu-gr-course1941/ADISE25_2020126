<?php
// tavli.php 
require_once "lib/dbconnect.php"; 
require_once "lib/board.php";
require_once "lib/game_logic.php"; 
require_once "lib/players.php";    
require_once "lib/logger.php"; 

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $request = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input == null) $input = [];

    if (isset($_SERVER['HTTP_APP_TOKEN'])) {
        $input['token'] = $_SERVER['HTTP_APP_TOKEN'];
    } elseif (!isset($input['token'])) {
        $input['token'] = '';
    }

    header('Content-Type: application/json');

    switch ($r = array_shift($request)) {
        case 'board':
            handle_board($method, $input); 
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

} catch (Throwable $e) {
    // 1. Καταγραφή του σφάλματος στο κρυφό log αρχείο μας
    app_log("FATAL ERROR: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine(), "FATAL");

    // 2. Επιστροφή "ευγενούς" σφάλματος στον χρήστη
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Παρουσιάστηκε ένα εσωτερικό πρόβλημα στην εφαρμογή. Η τεχνική ομάδα ενημερώθηκε.',
        'ref_id' => date('Ymd-His') // Ένα ID για να μπορεί ο χρήστης να σου αναφέρει πότε έγινε
    ]);
}
?>