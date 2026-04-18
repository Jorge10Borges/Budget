<?php
// Database configuration - reads from environment variables with sensible defaults
// Defaults set from the connection string you provided.
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT = getenv('DB_PORT') ?: 3306;
$DB_SOCKET = getenv('DB_SOCKET') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'budget';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';

// Return array for mysqli connection
return [
    'host' => $DB_HOST,
    'port' => (int)$DB_PORT,
    'socket' => $DB_SOCKET,
    'db' => $DB_NAME,
    'user' => $DB_USER,
    'pass' => $DB_PASS,
];
