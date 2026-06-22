<?php
session_start();
header('Content-Type: application/json');

if($_SESSION["role"] !== "CASHIER" || $_SESSION["id"] != session_id()){
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

try {
    include '../config/database.php';
    
    $itemId = isset($_POST['itemId']) ? (int)$_POST['itemId'] : 0;
    
    if (!$itemId) {
        throw new Exception('ID article invalide');
    }
    
    // Delete the item
    $deleteQuery = "DELETE FROM cart_items WHERE id = ?";
    $result = $db->execute($deleteQuery, [$itemId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Article retiré avec succès']);
    } else {
        throw new Exception('Erreur lors de la suppression');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>