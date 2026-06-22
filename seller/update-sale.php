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
    $cartName = isset($_POST['cart_name']) ? trim($_POST['cart_name']) : '';
    $clientId = isset($_POST['client_id']) && $_POST['client_id'] !== '' ? (int)$_POST['client_id'] : null;
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'pending';
    $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];

    if ($cartId <= 0) {
        throw new Exception('Invalid cart ID');
    }

    if (empty($cartName)) {
        throw new Exception('Cart name is required');
    }

    if (empty($items)) {
        throw new Exception('At least one item is required');
    }

    // Begin transaction
    $db->beginTransaction();

    try {
        // Update cart
        $updateCartSql = "UPDATE carts SET name = ?, client_id = ?, status = ?, updated_at = NOW() WHERE id = ?";
        $db->query($updateCartSql, [$cartName, $clientId, $status, $cartId]);

        // Get existing items
        $existingItemsSql = "SELECT * FROM cart_items WHERE cart_id = ?";
        $existingItems = $db->fetchAll($existingItemsSql, [$cartId]);
        $existingItemIds = array_column($existingItems, 'id');

        $processedItemIds = [];

        foreach ($items as $item) {
            $itemId = (int)$item['id'];
            $productId = (int)$item['product_id'];
            $quantity = (int)$item['quantity'];
            $unitPrice = (float)$item['unit_price'];

            if ($quantity <= 0) {
                throw new Exception('Quantity must be greater than 0');
            }

            // Get product stock
            $productSql = "SELECT stock FROM product WHERE id = ?";
            $productResult = $db->fetchAll($productSql, [$productId]);
            
            if (!$productResult || empty($productResult)) {
                throw new Exception('Product not found');
            }

            $currentStock = (int)$productResult[0]['stock'];

            if ($itemId > 0) {
                // Update existing item
                // Get old quantity to adjust stock
                $oldItemSql = "SELECT quantity FROM cart_items WHERE id = ? AND cart_id = ?";
                $oldItemResult = $db->fetchAll($oldItemSql, [$itemId, $cartId]);
                
                if ($oldItemResult && !empty($oldItemResult)) {
                    $oldQuantity = (int)$oldItemResult[0]['quantity'];
                    $quantityDiff = $quantity - $oldQuantity;

                    // Check if we have enough stock
                    if ($quantityDiff > $currentStock) {
                        throw new Exception('Insufficient stock for product');
                    }

                    // Update item
                    $updateItemSql = "UPDATE cart_items SET quantity = ?, unit_price = ? WHERE id = ? AND cart_id = ?";
                    $db->query($updateItemSql, [$quantity, $unitPrice, $itemId, $cartId]);

                    // Update product stock
                    $newStock = $currentStock - $quantityDiff;
                    $updateStockSql = "UPDATE product SET stock = ? WHERE id = ?";
                    $db->query($updateStockSql, [$newStock, $productId]);

                    $processedItemIds[] = $itemId;
                }
            } else {
                // New item
                if ($quantity > $currentStock) {
                    throw new Exception('Insufficient stock for product');
                }

                // Insert new item
                $insertItemSql = "INSERT INTO cart_items (cart_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)";
                $db->query($insertItemSql, [$cartId, $productId, $quantity, $unitPrice]);

                // Update product stock
                $newStock = $currentStock - $quantity;
                $updateStockSql = "UPDATE product SET stock = ? WHERE id = ?";
                $db->query($updateStockSql, [$newStock, $productId]);
            }
        }

        // Delete items that were removed
        foreach ($existingItemIds as $existingId) {
            if (!in_array($existingId, $processedItemIds)) {
                // Get item details to restore stock
                $itemSql = "SELECT product_id, quantity FROM cart_items WHERE id = ?";
                $itemResult = $db->fetchAll($itemSql, [$existingId]);
                
                if ($itemResult && !empty($itemResult)) {
                    $productId = $itemResult[0]['product_id'];
                    $quantity = $itemResult[0]['quantity'];

                    // Restore stock
                    $restoreStockSql = "UPDATE product SET stock = stock + ? WHERE id = ?";
                    $db->query($restoreStockSql, [$quantity, $productId]);

                    // Delete item
                    $deleteItemSql = "DELETE FROM cart_items WHERE id = ?";
                    $db->query($deleteItemSql, [$existingId]);
                }
            }
        }

        // Commit transaction
        $db->commit();

        echo json_encode(['success' => true, 'message' => 'Sale updated successfully']);

    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log('Update sale error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>