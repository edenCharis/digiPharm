<?php
// generate-sales-report-print.php
session_start();
if($_SESSION["role"] !== "SELLER" || $_SESSION["id"] !== session_id()){
    header("location: ../logout.php");
    exit;
}

try {
    include '../config/database.php';
    
    if (!isset($db)) {
        throw new Exception('Database connection not found');
    }

    // Get parameters
    $startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : date('Y-m-01');
    $endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : date('Y-m-d');
    $sellerId = $_SESSION['user_id'] ?? null;

    // Get sales data for the period
    $sql = "SELECT 
                p.name as product_name,
                p.code as product_code,
                c.name as category_name,
                SUM(ci.quantity) as total_sold,
                p.stock as current_stock,
                ct.seller_id as seller,
                ci.unit_price,
                SUM(ci.quantity * ci.unit_price) as total_revenue
            FROM cart_items ci
            INNER JOIN carts ct ON ci.cart_id = ct.id
            INNER JOIN product p ON ci.product_id = p.id
            LEFT JOIN category c ON p.categoryId = c.id
            WHERE ct.status = 'completed'
            AND DATE(ct.created_at) BETWEEN ? AND ?
            GROUP BY p.id, p.name, p.code, c.name, p.stock, ci.unit_price
            ORDER BY total_sold DESC";
    
    $salesData = $db->fetchAll($sql, [$startDate, $endDate]);

    // Get seller info
    $sellerSql = "SELECT username FROM user WHERE id = ?";
    $sellerResult = $db->fetchAll($sellerSql, [$sellerId]);
    $sellerName = $sellerResult[0]['username'] ?? 'Vendeur inconnu';

    // Calculate totals
    $totalRevenue = 0;
    $totalSold = 0;
    foreach ($salesData as $item) {
        $totalRevenue += $item['total_revenue'];
        $totalSold += $item['total_sold'];
    }

} catch (Exception $e) {
    error_log('Report generation error: ' . $e->getMessage());
    die('Erreur lors de la génération du rapport: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport de Ventes - <?php echo date('d/m/Y', strtotime($startDate)); ?> - <?php echo date('d/m/Y', strtotime($endDate)); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
            background: var(--ds-surface-alt);
        }

        .print-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        .report-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid var(--ds-green);
        }

        .logo {
            color: var(--ds-green);
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .report-title {
            color: var(--ds-text-900);
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .report-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            padding: 20px;
            background: var(--ds-surface-alt);
            border-radius: 8px;
        }

        .info-item {
            text-align: center;
        }

        .info-label {
            font-size: 0.875rem;
            color: var(--ds-text-400);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 1rem;
            color: var(--ds-text-900);
            font-weight: 600;
        }

        .summary-boxes {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-box {
            background: var(--ds-green);
            color: white;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
        }

        .summary-box h3 {
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .summary-box .value {
            font-size: 2rem;
            font-weight: 700;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .data-table thead {
            background: var(--ds-surface-alt);
        }

        .data-table th {
            padding: 12px;
            text-align: left;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--ds-text-900);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--ds-border);
        }

        .data-table td {
            padding: 12px;
            border-bottom: 1px solid var(--ds-surface-alt);
            color: var(--ds-text-900);
            font-size: 0.875rem;
        }

        .data-table tbody tr:hover {
            background: var(--ds-surface-alt);
        }

        .data-table tbody tr:last-child td {
            border-bottom: 2px solid var(--ds-border);
        }

        .product-code {
            font-family: 'Courier New', monospace;
            background: var(--ds-border);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75rem;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-in-stock {
            background: var(--ds-green-bg);
            color: #065f46;
        }

        .status-out-of-stock {
            background: #fee2e2;
            color: #991b1b;
        }

        .row-out-of-stock {
            background: #fef2f2 !important;
        }

        .row-out-of-stock:hover {
            background: #fee2e2 !important;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid var(--ds-border);
            color: var(--ds-text-400);
            font-size: 0.875rem;
        }

        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }

        .btn-print {
            background: var(--ds-green);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-print:hover {
            background: #047857;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
        }

        .btn-close {
            background: var(--ds-text-400);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            margin-left: 10px;
            transition: all 0.2s;
        }

        .btn-close:hover {
            background: var(--ds-text-600);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--ds-text-400);
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .print-container {
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }

            .no-print {
                display: none !important;
            }

            .data-table {
                page-break-inside: auto;
            }

            .data-table tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            .data-table thead {
                display: table-header-group;
            }

            .report-header {
                margin-bottom: 20px;
                padding-bottom: 10px;
            }

            .summary-boxes {
                page-break-inside: avoid;
            }
        }

        @page {
            margin: 1cm;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">
            🖨️ Imprimer / Enregistrer en PDF
        </button>
        <button class="btn-close" onclick="window.close()">
            ✕ Fermer
        </button>
    </div>

    <div class="print-container">
        <div class="report-header">
            <div class="logo">PharmaSys</div>
            <h1 class="report-title">Rapport de Ventes</h1>
        </div>

        <div class="report-info">
            <div class="info-item">
                <div class="info-label">Période</div>
                <div class="info-value">
                    <?php echo date('d/m/Y', strtotime($startDate)); ?> - <?php echo date('d/m/Y', strtotime($endDate)); ?>
                </div>
            </div>
            <div class="info-item">
                <div class="info-label">Vendeur</div>
                <div class="info-value"><?php echo htmlspecialchars($sellerName); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Date de génération</div>
                <div class="info-value"><?php echo date('d/m/Y à H:i'); ?></div>
            </div>
        </div>

        <div class="summary-boxes">
            <div class="summary-box">
                <h3>Revenus Total</h3>
                <div class="value"><?php echo number_format($totalRevenue, 0); ?> XAF</div>
            </div>
            <div class="summary-box">
                <h3>Articles Vendus</h3>
                <div class="value"><?php echo number_format($totalSold, 0); ?></div>
            </div>
        </div>

        <?php if (empty($salesData)): ?>
            <div class="empty-state">
                <h3>Aucune vente trouvée</h3>
                <p>Il n'y a aucune vente enregistrée pour cette période.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Code</th>
                        <th>Catégorie</th>
                        <th class="text-center">Quantité Vendue</th>
                        <th class="text-center">Stock Restant</th>
                        <th class="text-right">Prix Unitaire</th>
                        <th class="text-center">Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salesData as $item): ?>
                        <tr class="<?php echo $item['current_stock'] == 0 ? 'row-out-of-stock' : ''; ?>">
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td>
                                <span class="product-code"><?php echo htmlspecialchars($item['product_code']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($item['category_name'] ?: 'Non classé'); ?></td>
                            <td class="text-center"><?php echo number_format($item['total_sold'], 0); ?></td>
                            <td class="text-center"><?php echo number_format($item['current_stock'], 0); ?></td>
                            <td class="text-right"><?php echo number_format($item['unit_price'], 0); ?> XAF</td>
                            <td class="text-center">
                                <?php if ($item['current_stock'] == 0): ?>
                                    <span class="status-badge status-out-of-stock">À commander</span>
                                <?php else: ?>
                                    <span class="status-badge status-in-stock">En stock</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

       
    </div>

    <script>
        // Auto-print dialog on load (optional)
        // window.onload = function() {
        //     setTimeout(function() {
        //         window.print();
        //     }, 500);
        // };
    </script>
</body>
</html>