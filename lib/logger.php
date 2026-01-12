<?php
function app_log(string $message, string $level = 'INFO'): void
{
    $logFile = __DIR__ . '/../logs/app.log';
    $date = date('Y-m-d H:i:s');
    
    // Προσθήκη επιπέδου και ημερομηνίας
    $line = "[$date] [$level] $message" . PHP_EOL;

    // Εγγραφή (το FILE_APPEND προσθέτει στο τέλος)
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}