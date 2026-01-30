<?php

function handle_board($method, $input) {
    if($method=='GET'){
        show_board();
    }
    elseif($method=='POST') {
        reset_board();
    } 
}

function show_board() {
    global $mysqli;
    $sql = 'SELECT * FROM board';

    $st = $mysqli->prepare($sql);
    $st->execute();
    $res = $st->get_result();
    
    echo json_encode($res->fetch_all(MYSQLI_ASSOC), JSON_PRETTY_PRINT);
}

function reset_board() {
    global $mysqli;
    $mysqli->query("CALL clean_board()");
    show_board();
}

?>