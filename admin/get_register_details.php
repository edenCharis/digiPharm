<?php
session_start();
header('Content-Type: application/json');

if (!($_SESSION["role"] === "ADMIN" && $_SESSION["id"] == session_id())) {
    echo json_encode(['error' => 'Non autorisé']);
    exit();
}

try {
    include '../config/database.php';
    if (!isset($db)) throw new Exception('Database connection not found');

    // ✅ Pas de intval — l'ID peut être une string
    $sale_id = isset($_GET['sale_id']) ? trim($_GET['sale_id']) : '';
    if (empty($sale_id)) throw new Exception('ID de vente manquant');

    // Infos de la vente
    $saleSQL = "SELECT s.id, s.saleDate, s.totalAmount, s.totalVAT, s.discountAmount,
                    s.invoiceNumber, s.cashReceived, s.changeAmount,
                    seller.username as seller_name,
                    client.name as client_name,
                    client.contact as client_phone
                FROM sale s
                LEFT JOIN user seller ON s.sellerId = seller.id
                LEFT JOIN client ON s.clientId = client.id
                WHERE s.id = ?";
    $sale = $db->fetch($saleSQL, [$sale_id]);
    if (!$sale) throw new Exception('Vente introuvable (id=' . $sale_id . ')');

    // Articles — on retourne exactement ce qui est en base, sans recalcul
    $itemsSQL = "SELECT si.id, si.quantity, si.unitPrice, si.discount, si.vatAmount,
                    si.totalPrice,
                    p.name as product_name, p.description as product_description
                 FROM saleitem si
                 LEFT JOIN product p ON si.productId = p.id
                 WHERE si.saleId = ?
                 ORDER BY si.id";
    $items = $db->fetchAll($itemsSQL, [$sale_id]);
    if (!$items) $items = [];

    $response = [
        'id'                => $sale['id'],          // string telle quelle
        'invoiceNumber'     => $sale['invoiceNumber'],
        'saleDate'          => $sale['saleDate'],
        'saleDateFormatted' => date('d/m/Y H:i', strtotime($sale['saleDate'])),
        'totalAmount'       => floatval($sale['totalAmount']),
        'totalVAT'          => floatval($sale['totalVAT']),
        'discountAmount'    => floatval($sale['discountAmount']),
        'cashReceived'      => floatval($sale['cashReceived']),
        'changeAmount'      => floatval($sale['changeAmount']),
        'seller_name'       => $sale['seller_name']  ?? '',
        'client_name'       => $sale['client_name']  ?? '',
        'client_phone'      => $sale['client_phone'] ?? '',
        'items'             => array_map(function($it) {
            return [
                'id'                  => $it['id'],   // string telle quelle
                'product_name'        => $it['product_name']        ?? '',
                'product_description' => $it['product_description'] ?? '',
                'quantity'            => $it['quantity'],
                'unitPrice'           => floatval($it['unitPrice']),
                'discount'            => floatval($it['discount']),
                'vatAmount'           => floatval($it['vatAmount']),
                // ✅ On utilise totalPrice stocké en base — pas de recalcul
                'line_total'          => floatval($it['unitPrice']) * intval($it['quantity']),
            ];
        }, $items),
    ];

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>