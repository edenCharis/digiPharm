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

    $sale_id = isset($_GET['sale_id']) ? ($_GET['sale_id']) : 0;
    if (!$sale_id) throw new Exception('ID de vente manquant');

    // Infos de la vente
    $saleSQL = "SELECT s.id , s.saleDate, s.totalAmount, s.totalVAT, s.discountAmount,
                    s.invoiceNumber, s.cashReceived, s.changeAmount,
                    seller.username as seller_name,
                    client.name as client_name,
                    client.contact as client_phone
                FROM sale s
                LEFT JOIN user seller ON s.sellerId = seller.id
                LEFT JOIN client ON s.clientId = client.id
                WHERE s.id = ?";
    $sale = $db->fetch($saleSQL, [$sale_id]);
    if (!$sale) throw new Exception('Vente introuvable');

    // Articles de la vente
    $itemsSQL = "SELECT si.id, si.quantity, si.unitPrice, si.discount, si.vatAmount,
                    p.name as product_name, p.description as product_description,
                    (si.quantity * si.unitPrice) as line_total
                 FROM saleitem si
                 LEFT JOIN product p ON si.productId = p.id
                 WHERE si.saleId = ?
                 ORDER BY si.id";
    $items = $db->fetchAll($itemsSQL, [$sale_id]);
    if (!$items) $items = [];

    // Prépare la réponse
    $response = [
        'id'                 => $sale['id'],
        'invoiceNumber'      => $sale['invoiceNumber'],
        'saleDate'           => $sale['saleDate'],
        'saleDateFormatted'  => date('d/m/Y H:i', strtotime($sale['saleDate'])),
        'totalAmount'        => floatval($sale['totalAmount']),
        'totalVAT'           => floatval($sale['totalVAT']),
        'discountAmount'     => floatval($sale['discountAmount']),
        'cashReceived'       => floatval($sale['cashReceived']),
        'changeAmount'       => floatval($sale['changeAmount']),
        'seller_name'        => $sale['seller_name'] ?? '',
        'client_name'        => $sale['client_name'] ?? '',
        'client_phone'       => $sale['client_phone'] ?? '',
        'items'              => array_map(function($it) {
            return [
                'id'                  => $it['id'],
                'product_name'        => $it['product_name'] ?? '',
                'product_description' => $it['product_description'] ?? '',
                'quantity'            => intval($it['quantity']),
                'unitPrice'           => floatval($it['unitPrice']),
                'discount'            => floatval($it['discount']),
                'vatAmount'           => floatval($it['vatAmount']),
                'line_total'          => floatval($it['line_total']),
            ];
        }, $items),
    ];

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>