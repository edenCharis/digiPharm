<?php
/**
 * Internal AJAX bridge — analytics dashboard → FastAPI.
 * The API key is attached server-side — never exposed to browser JS.
 */
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/crypto.php';
ai_check_auth();

header('Content-Type: application/json');

$user   = ai_user();
$apiKey = $user['api_key'];

if (!$apiKey) {
    echo json_encode(['available' => false, 'error' => 'No API key']);
    exit;
}

$type = $_GET['type'] ?? 'dashboard';
$days = (int) ($_GET['days'] ?? 30);
$full = isset($_GET['full']) ? '&full=true' : '';

// ── Routing table ─────────────────────────────────────────────────────────
// GET routes → standard analytics data
$getRoutes = [
    'dashboard' => '/analytics/dashboard',
    'brief'     => '/analytics/brief',
    'trends'    => "/analytics/trends?days=$days",
    'alerts'    => '/analytics/alerts',
    'inventory' => '/analytics/inventory',
    'suppliers' => '/analytics/suppliers',
    'etl_sync'  => "/analytics/etl/sync$full",
];

// POST routes → ETL control (forward the form body to FastAPI)
// Chat now lives in chat-api.php (adds conversation persistence + history).
$postRoutes = [
    'etl_test' => '/analytics/etl/test',
];

$baseUrl = 'http://127.0.0.1:8000';

if (isset($postRoutes[$type])) {
    $url  = $baseUrl . $postRoutes[$type];
    $body = json_encode($_POST);

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "X-API-Key: $apiKey\r\nContent-Type: application/json\r\nAccept: application/json\r\n",
            'content'       => $body,
            'timeout'       => 20,
            'ignore_errors' => true,
        ],
    ]);

} elseif (isset($getRoutes[$type])) {
    $url = $baseUrl . $getRoutes[$type];
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'header'        => "X-API-Key: $apiKey\r\nAccept: application/json\r\n",
            'timeout'       => $type === 'etl_sync' ? 30 : 6,
            'ignore_errors' => true,
        ],
    ]);

} else {
    echo json_encode(['available' => false, 'error' => 'Type inconnu']);
    exit;
}

$responseBody = @file_get_contents($url, false, $ctx);

if ($responseBody === false) {
    echo json_encode(['available' => false, 'error' => 'Service AI indisponible']);
    exit;
}

$code = 200;
if (isset($http_response_header)) {
    foreach ($http_response_header as $h) {
        if (preg_match('/HTTP\/\d\.\d (\d+)/', $h, $m)) {
            $code = (int) $m[1];
        }
    }
}

if ($code >= 400) {
    $decoded = json_decode($responseBody, true);
    $detail  = $decoded['detail'] ?? "Erreur $code";
    echo json_encode(['available' => false, 'error' => $detail]);
    exit;
}

echo $responseBody;
