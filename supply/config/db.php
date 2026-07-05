<?php
/**
 * digiSupply portal — DB connection (analytics DB, read via token auth only).
 * Uses same env.php as digiMind.
 */
$envPath = dirname(__DIR__, 2) . '/env.php'; // → /opt/lampp/htdocs/digiPharm/env.php
if (!file_exists($envPath)) {
    http_response_code(503);
    die('Configuration manquante');
}
require_once $envPath;

function supply_db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dbName = defined('ANALYTICS_DB_NAME') ? ANALYTICS_DB_NAME : 'digipharmai_db';
    $host   = defined('DB_HOST') ? DB_HOST : 'localhost';
    $user   = defined('DB_USER') ? DB_USER : 'root';
    $pass   = defined('DB_PASS') ? DB_PASS : '';
    $port   = defined('DB_PORT') ? DB_PORT : '3306';

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbName;charset=utf8mb4",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    return $pdo;
}

/**
 * Validate a supplier token. Returns [order, items, pharmacy] or null on failure.
 */
function supply_resolve_token(string $token): ?array
{
    if (strlen($token) !== 64 || !ctype_xdigit($token)) return null;
    $db = supply_db();

    $stmt = $db->prepare("
        SELECT t.order_id, t.expires_at,
               o.*, p.name AS pharmacy_name
        FROM ai_supplier_tokens t
        JOIN ai_purchase_orders o ON o.id = t.order_id
        JOIN ai_pharmacies p ON p.id = o.pharmacy_id
        WHERE t.token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row) return null;

    // Check expiry
    if ($row['expires_at'] && strtotime($row['expires_at']) < time()) return null;

    $items = $db->prepare("SELECT * FROM ai_purchase_order_items WHERE order_id = ? ORDER BY id");
    $items->execute([$row['order_id']]);
    $row['items'] = $items->fetchAll();

    return $row;
}
