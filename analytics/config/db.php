<?php
/**
 * Analytics DB connection — digipharmai_db (separate from the ERP DB).
 * Reads credentials from the same env.php used by the ERP.
 */
$envPath = dirname(__DIR__, 2) . '/env.php';
if (!file_exists($envPath)) {
    http_response_code(503);
    die(json_encode(['error' => 'env.php missing']));
}
require_once $envPath;

$_analytics_db_name = defined('ANALYTICS_DB_NAME') ? ANALYTICS_DB_NAME : 'digipharmai_db';

function analytics_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    global $_analytics_db_name;
    $host = defined('DB_HOST') ? DB_HOST : 'localhost';
    $user = defined('DB_USER') ? DB_USER : 'root';
    $pass = defined('DB_PASS') ? DB_PASS : '';
    $port = defined('DB_PORT') ? DB_PORT : '3306';

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$_analytics_db_name;charset=utf8mb4",
        $user, $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    return $pdo;
}
