<?php
/**
 * Database connection (PDO) — single shared instance.
 */

function config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $path = __DIR__ . '/config.php';
        if (!is_file($path)) {
            http_response_code(500);
            exit('Configuration missing. Copy app/config.sample.php to app/config.php and set your DB credentials.');
        }
        $cfg = require $path;
    }
    return $cfg;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config()['db'];
        $dsn = "mysql:host={$c['host']};dbname={$c['name']};charset={$c['charset']}";
        try {
            $pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // Pin the connection to West Africa Time so CURDATE()/NOW() and
            // registered_at always mean Nigerian calendar time, regardless of
            // the (often UTC) server timezone on shared hosting.
            $tz = preg_replace('/[^0-9:+\-]/', '', config()['db_timezone'] ?? '+01:00');
            $pdo->exec("SET time_zone = '" . ($tz ?: '+01:00') . "'");
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Database connection failed. Check app/config.php credentials.');
        }
    }
    return $pdo;
}
