<?php
// Database configuration - reads from environment variables with sensible defaults.
// Loads .env from project root if present (works in shared hosting/local Apache setups).
$envPath = dirname(__DIR__) . '/.env';
if (is_file($envPath) && is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");

        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

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
