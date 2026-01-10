<?php

function app_log(string $message, string $level = 'INFO'): void
{
    $logFile = __DIR__ . '/../logs/app.log';

    $date = date('Y-m-d H:i:s');
    $line = "[$date] [$level] $message" . PHP_EOL;

    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}