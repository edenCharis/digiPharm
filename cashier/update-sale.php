<?php
session_start();
header('Content-Type: application/json');

if($_SESSION["role"] !== "CASHIER" || $_SESSION["id"] != session_id()){
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

try {
    include '../config/database.php';
    
    if (!isset($db)) {
        throw new Exception('Connexion base de données non trouvée');
    }

    // Get POST data
    $saleId = isset($_POST['saleId']) ? $_POST['saleId'] : 0;
    $clientId = isset($_POST['clientId']) && $_POST['clientId'] !== '' ? $_POST['clientId'] : null;
    $discountAmount = isset($_POST['discountAmount']) ? (float)$_POST['discountAmount'] : 0;
    
    $itemIds = isset($_POST['itemId']) ? $_POST['itemId'] : [];
    $productIds = isset($_POST['productId']) ? $_POST['productId'] : [];
    $quantities = isset($_POST['quantity']) ? $_POST['quantity'] : [];
    $prices = isset($_POST['price']) ? $_POST['price'] : [];
    
    $newProductIds = isset($_POST['newProductId']) ? $_POST['newProductId'] : [];
    $newQuantities = isset($_POST['newQuantity']) ? $_POST['newQuantity'] : [];
    $newPrices = isset($_POST['newPrice']) ? $_POST['newPrice'] : [];
    
    if (!$saleId) {
        throw new Exception('ID de vente invalide');
    }

    // Validate that we have at least one item
    if (empty($productIds) && empty($newProductIds)) {
        throw new Exception('Une vente doit contenir au moins un article');
    }

    // Get original sale data
    $originalSaleQuery = "SELECT * FROM sale WHERE id = ?";
    $originalSale = $db->fetch($originalSaleQuery, [$saleId]);
    
    if (!$originalSale) {
        throw new Exception('Vente introuvable');
    }

    // Get original sale items
    $originalItemsQuery = "SELECT * FROM saleitem WHERE saleId = ?";
    $originalItems = $db->fetchAll($originalItemsQuery, [$saleId]);

    // Start transaction
    $db->query("START TRANSACTION");

    try {
        // Step 1: Restore stock for all original items
        foreach ($originalItems as $originalItem) {
            $restoreStockQuery = "UPDATE product SET stock = stock + ? WHERE id = ?";
            $db->query($restoreStockQuery, [
                $originalItem['quantity'],
                $originalItem['productId']
            ]);
        }

        // Step 2: Delete all existing sale items
        $deleteItemsQuery = "DELETE FROM saleitem WHERE saleId = ?";
        $db->query($deleteItemsQuery, [$saleId]);

        // Step 3: Calculate new totals and insert updated items
        $newSubtotal = 0;
        $itemsProcessed = [];

        // Process existing items (that were kept)
        for ($i = 0; $i < count($productIds); $i++) {
            $productId = (int)$productIds[$i];
            $quantity = (int)$quantities[$i];
            $unitPrice = (float)$prices[$i];
            
            if ($quantity <= 0) continue;

            // Check stock availability
            $stockQuery = "SELECT stock, name FROM product WHERE id = ?";
            $product = $db->fetch($stockQuery, [$productId]);
            
            if (!$product) {
                throw new Exception("Produit ID $productId introuvable");
            }
            
            if ($product['stock'] < $quantity) {
                throw new Exception("Stock insuffisant pour {$product['name']}. Disponible: {$product['stock']}, Demandé: $quantity");
            }

            // Insert sale item
            $insertItemQuery = "INSERT INTO saleitem (saleId, productId, quantity, unitPrice) 
                               VALUES (?, ?, ?, ?)";
            $totalPrice = $quantity * $unitPrice;
            $db->query($insertItemQuery, [
                $saleId,
                $productId,
                $quantity,
                $unitPrice
            ]);

            // Update stock
            $updateStockQuery = "UPDATE product SET stock = stock - ? WHERE id = ?";
            $db->query($updateStockQuery, [$quantity, $productId]);

            $newSubtotal += $totalPrice;
            $itemsProcessed[] = $productId;
        }

        // Process new items
        for ($i = 0; $i < count($newProductIds); $i++) {
            $productId = (int)$newProductIds[$i];
            $quantity = (int)$newQuantities[$i];
            $unitPrice = (float)$newPrices[$i];
            
            if ($quantity <= 0) continue;

            // Check stock availability
            $stockQuery = "SELECT stock, name FROM product WHERE id = ?";
            $product = $db->fetch($stockQuery, [$productId]);
            
            if (!$product) {
                throw new Exception("Produit ID $productId introuvable");
            }
            
            if ($product['stock'] < $quantity) {
                throw new Exception("Stock insuffisant pour {$product['name']}. Disponible: {$product['stock']}, Demandé: $quantity");
            }

            // Insert sale item
            $insertItemQuery = "INSERT INTO saleitem (saleId, productId, quantity, unitPrice, totalPrice) 
                               VALUES (?, ?, ?, ?, ?)";
            $totalPrice = $quantity * $unitPrice;
            $db->query($insertItemQuery, [
                $saleId,
                $productId,
                $quantity,
                $unitPrice,
                $totalPrice
            ]);

            // Update stock
            $updateStockQuery = "UPDATE product SET stock = stock - ? WHERE id = ?";
            $db->query($updateStockQuery, [$quantity, $productId]);

            $newSubtotal += $totalPrice;
            $itemsProcessed[] = $productId;
        }

        // Calculate final totals
        $newTotalAmount = $newSubtotal - $discountAmount;

        // Step 4: Update sale record
        $updateSaleQuery = "UPDATE sale 
                           SET clientId = ?,
                              
                               discountAmount = ?,
                               totalAmount = ?
                           WHERE id = ?";
        
        $db->query($updateSaleQuery, [
            $clientId,
            $discountAmount,
            $newTotalAmount,
            $saleId
        ]);

        // Step 5: Update cash register (adjust the difference)
        $cashDifference = $newTotalAmount - $originalSale['totalAmount'];
        
        if ($cashDifference != 0) {
            $updateRegisterQuery = "UPDATE cash_register 
                                   SET final_amount = final_amount + ?
                                   WHERE id = ?";
            $db->query($updateRegisterQuery, [
                $cashDifference,
                $originalSale['cash_register_id']
            ]);
        }

        // Step 6: Log the modification
        $logQuery = "INSERT INTO sale_modifications (sale_id, user_id, original_total, new_total, modification_date, notes)
                     VALUES (?, ?, ?, ?, NOW(), ?)";
        
        $notes = "Vente modifiée. Articles: " . count($itemsProcessed) . 
                 ". Différence: " . number_format($cashDifference, 0) . " XAF";
        
        // Check if table exists, if not skip logging
        try {
            $db->query($logQuery, [
                $saleId,
                $_SESSION['user_id'] ?? $_SESSION['id'],
                $originalSale['totalAmount'],
                $newTotalAmount,
                $notes
            ]);
        } catch (Exception $e) {
            // Table might not exist, continue anyway
        }

        // Commit transaction
        $db->query("COMMIT");

        echo json_encode([
            'success' => true,
            'message' => 'Vente mise à jour avec succès',
            'saleId' => $saleId,
            'newTotal' => $newTotalAmount,
            'itemsCount' => count($itemsProcessed)
        ]);

    } catch (Exception $e) {
        // Rollback on error
        $db->query("ROLLBACK");
        throw $e;
    }

} catch (Exception $e) {
    error_log('Update sale error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>