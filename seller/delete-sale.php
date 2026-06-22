<?php
session_start();
header('Content-Type: application/json');

if($_SESSION["role"] !== "SELLER" || $_SESSION["id"] != session_id()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
try {
    include '../config/database.php';
    
    if (!isset($db)) {
        throw new Exception('Database connection not found');
    }

    $cartId = isset($_POST['cart_id']) ? (int)$_POST['cart_id'] : 0;
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

    if ($cartId <= 0) {
        throw new Exception('Invalid cart ID');
    }

    // Begin transaction
    $db->beginTransaction();

    try {
        // Get cart items to restore stock
        $itemsSql = "SELECT product_id, quantity FROM cart_items WHERE cart_id = ?";
        $items = $db->fetchAll($itemsSql, [$cartId]);

        if ($items) {
            foreach ($items as $item) {
                // Restore product stock
                $updateStockSql = "UPDATE product SET stock = stock + ? WHERE id = ?";
                $db->query($updateStockSql, [$item['quantity'], $item['product_id']]);
            }
        }

        // Delete cart items
        $deleteItemsSql = "DELETE FROM cart_items WHERE cart_id = ?";
        $db->query($deleteItemsSql, [$cartId]);

        // Instead of deleting the cart, mark it as deleted/cancelled with reason
        $updateCartSql = "UPDATE carts SET status = 'cancelled', updated_at = NOW() WHERE id = ?";
        $db->query($updateCartSql, [$cartId]);

        // Optional: Log the deletion reason
        if (!empty($reason)) {
            $logSql = "INSERT INTO cart_deletion_log (cart_id, reason, deleted_at, deleted_by) 
                       VALUES (?, ?, NOW(), ?)";
            // Note: You may need to create this table if it doesn't exist
            // $db->query($logSql, [$cartId, $reason, $_SESSION['user_id']]);
        }

        // Commit transaction
        $db->commit();

        echo json_encode([
            'success' => true, 
            'message' => 'Sale deleted successfully and stock restored'
        ]);

    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log('Delete sale error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>