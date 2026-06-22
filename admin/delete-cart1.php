<?php
session_start();
header('Content-Type: application/json');

if($_SESSION["role"] !== "ADMIN" || $_SESSION["id"] != session_id()){
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

try {
    include '../config/database.php';
    
     $input = json_decode(file_get_contents('php://input'), true);
    $cartId = isset($input['cartId']) ? intval($input['cartId']) : 0;
    
    if ($cartId <= 0) {
        throw new Exception('ID du panier invalide');
    }
    
    // Delete cart items first
    $deleteItemsQuery = "DELETE FROM cart_items WHERE cart_id = ?";
    $db->execute($deleteItemsQuery, [$cartId]);
    
    // Delete the cart
    $deleteCartQuery = "DELETE FROM carts WHERE id = ?";
    $result = $db->execute($deleteCartQuery, [$cartId]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Panier supprimé avec succès']);
    } else {
        throw new Exception('Erreur lors de la suppression du panier');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>