<?php
/**
 * Internal AJAX bridge — analytics dashboard → FastAPI.
 * Attaches the pharmacy's API key automatically (not exposed to browser JS).
 */
require_once __DIR__ . '/config/auth.php';
ai_check_auth();

header('Content-Type: application/json');

$user = ai_user();
$apiKey = $user['api_key'];

if (!$apiKey) {
    echo json_encode(['available' => false, 'error' => 'No API key']);
    exit;
}

$type = $_GET['type'] ?? 'dashboard';
$days = (int) ($_GET['days'] ?? 30);

$map = [
    'dashboard' => '/analytics/dashboard',
    'trends'    => "/analytics/trends?days=$days",
    'alerts'    => '/analytics/alerts',
    'inventory' => '/analytics/inventory',
];

if (!isset($map[$type])) {
    echo json_encode(['available' => false, 'error' => 'Type inconnu']);
    exit;
}

$url = 'http://127.0.0.1:8000' . $map[$type];

$ctx = stream_context_create([
    'http' => [
        'method'  => 'GET',
        'header'  => "X-API-Key: $apiKey\r\nAccept: application/json\r\n",
        'timeout' => 5,
        'ignore_errors' => true,
    ],
]);

$body = @file_get_contents($url, false, $ctx);

if ($body === false) {
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
    echo json_encode(['available' => false, 'error' => "FastAPI $code"]);
    exit;
}

echo $body;
