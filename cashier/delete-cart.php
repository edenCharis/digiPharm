<?php
session_start();
header('Content-Type: application/json');

if($_SESSION["role"] !== "CASHIER" || $_SESSION["id"] != session_id()){
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

try {
    include '../config/database.php';
    
    $cartId = isset($_POST['cartId']) ? $_POST['cartId'] : 0;
    
    if (!$cartId) {
        throw new Exception('ID panier invalide');
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