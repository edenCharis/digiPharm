<?php
error_reporting(0);
ini_set('display_errors', 0);
if (ob_get_level()) ob_end_clean();

session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    include_once '../config/database.php';
    
    if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "SELLER") {
        throw new Exception("Unauthorized");
    }
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON");
    }
    
    if (!isset($data['query']) || empty(trim($data['query']))) {
        throw new Exception("Query required");
    }
    
    $query = trim($data['query']);
    $exactMatch = isset($data['exactMatch']) && $data['exactMatch'] === true;
    
    if (strlen($query) < 2) {
        throw new Exception("Query too short");
    }
    
    if ($exactMatch) {
        $sql = "SELECT code, name, sellingPrice, stock FROM product WHERE code = ? AND stock > 0 LIMIT 1";
        $params = [$query];
    } else {
        $searchTerm = "%{$query}%";
        $sql = "SELECT code, name, sellingPrice, stock FROM product WHERE (name LIKE ? OR code LIKE ?) AND stock > 0 ORDER BY CASE WHEN code = ? THEN 1 WHEN name = ? THEN 2 WHEN code LIKE ? THEN 3 WHEN name LIKE ? THEN 4 ELSE 5 END, name ASC LIMIT 50";
        $params = [$searchTerm, $searchTerm, $query, $query, $query . '%', $query . '%'];
    }
    
    if (!isset($db) || !method_exists($db, 'fetchAll')) {
        throw new Exception("Database unavailable");
    }
    
    $products = $db->fetchAll($sql, $params);
    
    if (!$products) {
        $products = [];
    } else {
        if (!is_array($products)) {
            $products = [$products];
        } elseif (isset($products['code'])) {
            $products = [$products];
        }
    }
    
    $response = [
        'success' => true,
        'products' => $products,
        'count' => count($products),
        'query' => $query,
        'exactMatch' => $exactMatch
    ];
    
    if (ob_get_level()) ob_end_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
    
} catch (Exception $e) {
    if (ob_get_level()) ob_end_clean();
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'products' => [],
        'count' => 0
    ], JSON_UNESCAPED_UNICODE);
    exit;
}