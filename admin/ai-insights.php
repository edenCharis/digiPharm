<?php
/**
 * AI Insights endpoint — called via AJAX from admin dashboard.
 * Returns JSON from the FastAPI AI service (or graceful fallback).
 */
session_start();
header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include '../config/database.php';
include '../includes/AIService.php';

$pharmacyId = (int) ($_SESSION['pharmacy_id'] ?? 0);
if ($pharmacyId === 0) {
    echo json_encode(['available' => false, 'error' => 'pharmacy_id manquant']);
    exit;
}

$type = $_GET['type'] ?? 'dashboard';

$result = match ($type) {
    'dashboard'  => AIService::dashboardSummary($pharmacyId),
    'alerts'     => AIService::alerts($pharmacyId),
    'trends'     => AIService::trends($pharmacyId, (int)($_GET['days'] ?? 30)),
    'inventory'  => AIService::inventoryRecommendations($pharmacyId),
    'top'        => AIService::topProductsForecast($pharmacyId),
    default      => ['available' => false, 'error' => 'Type inconnu'],
};

echo json_encode($result);
