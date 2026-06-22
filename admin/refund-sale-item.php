<?php
/**
 * refund-sale-item.php
 * Retire un article d'une vente (remboursement partiel) et réajuste le stock.
 * 
 * Requête : POST JSON  { "itemId": <int>, "saleId": <int> }
 * Réponse : JSON       { "success": true|false, "message": "...", "newTotal": <float> }
 */

session_start();

header('Content-Type: application/json');

// Only ADMIN can do refunds
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN' || $_SESSION['id'] !== session_id()) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}

try {
    include '../config/database.php';

    if (!isset($db)) {
        throw new Exception('Connexion à la base de données introuvable');
    }
    
    
    
    $raw_debug = file_get_contents('php://input');
    error_log('RAW INPUT: ' . $raw_debug);
    error_log('POST: ' . print_r($_POST, true));
    error_log('CONTENT-TYPE: ' . ($_SERVER['CONTENT_TYPE'] ?? 'none'));
    file_put_contents('/tmp/refund_debug.txt', "RAW: $raw_debug\nPOST: " . print_r($_POST, true));
   

    // Read JSON body
  $raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

// Fallback to $_POST in case Content-Type was mangled
if (empty($input)) {
    $input = $_POST;
}

$itemId = isset($input['itemId']) ? trim((string)$input['itemId']) : '';
$saleId = isset($input['saleId']) ? trim((string)$input['saleId']) : '';
if ($itemId === '' || $itemId === '0' || $saleId === '' || $saleId === '0') {
    throw new Exception('Paramètres manquants ou invalides (itemId=' . $itemId . ', saleId=' . $saleId . ')');
}

    // Fetch the item to get quantity + productId + amounts
    $itemSQL = "SELECT si.*, p.id as pid
                FROM saleitem si
                LEFT JOIN product p ON si.productId = p.id
                WHERE si.id = ? AND si.saleId = ?";
    $item = $db->fetch($itemSQL, [$itemId, $saleId]);

    if (!$item) {
        throw new Exception('Article introuvable dans cette vente');
    }

    // Calculate amount to subtract from sale total
    $lineTotal   = ($item['quantity'] * $item['unitPrice']) - $item['discount'] + $item['vatAmount'];
    $vatToRemove = $item['vatAmount'];
    $discToRemove = $item['discount'];

    // ── BEGIN TRANSACTION ──
    // Note: wrap in try/catch because some DB wrappers differ.
    // Adjust the transaction calls to match your $db wrapper API.

    // 1. Restore product stock
    $restoreStockSQL = "UPDATE product SET stock = stock + ? WHERE id = ?";
    $db->query($restoreStockSQL, [$item['quantity'], $item['productId']]);

    // 2. Delete the sale item
    $deleteItemSQL = "DELETE FROM saleitem WHERE id = ?";
    $db->query($deleteItemSQL, [$itemId]);

    // 3. Recalculate sale totals from remaining items
    $recalcSQL = "SELECT 
                      COALESCE(SUM(quantity * unitPrice), 0) AS newTotal,
                      COALESCE(SUM(vatAmount), 0)  AS newVAT,
                      COALESCE(SUM(discount), 0)   AS newDiscount
                  FROM saleitem
                  WHERE saleId = ?";
    $recalc = $db->fetch($recalcSQL, [$saleId]);

    $newTotal    = $recalc ? $recalc['newTotal']    : 0;
    $newVAT      = $recalc ? $recalc['newVAT']      : 0;
    $newDiscount = $recalc ? $recalc['newDiscount']  : 0;

    // 4. Update sale record
    $updateSaleSQL = "UPDATE sale 
                      SET totalAmount = ?, totalVAT = ?, discountAmount = ?
                      WHERE id = ?";
    $db->query($updateSaleSQL, [$newTotal, $newVAT, $newDiscount, $saleId]);
    
    
    
    $remainingSQL = "SELECT COUNT(*) as cnt FROM saleitem WHERE saleId = ?";
$remaining = $db->fetch($remainingSQL, [$saleId]);

if ($remaining && intval($remaining['cnt']) === 0) {
    $db->query("DELETE FROM sale WHERE id = ?", [$saleId]);
    echo json_encode([
        'success'        => true,
        'message'        => 'Article remboursé et facture supprimée',
        'invoiceDeleted' => true,
        'newTotal'       => 0,
        'removed'        => $lineTotal
    ]);
    exit;
}

    echo json_encode([
        'success'  => true,
        'message'  => 'Article remboursé avec succès',
        'newTotal' => $newTotal,
        'removed'  => $lineTotal
    ]);

} catch (Exception $e) {
    error_log('refund-sale-item error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}