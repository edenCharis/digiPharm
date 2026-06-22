<?php
session_start();
if($_SESSION["role"] === "ADMIN" && $_SESSION["id"] == session_id()){

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    include '../config/database.php';
    
    if (!isset($pdo)) {
        throw new Exception('Database connection not found');
    }

    // Get category filter from URL
    $categoryFilter = isset($_GET['category']) && !empty($_GET['category']) ? intval($_GET['category']) : null;
    $categoryName = "Tous les produits";

    // Build WHERE clause based on filter
    $whereClause = '';
    $params = [];
    
    if ($categoryFilter) {
        $whereClause = "WHERE p.categoryId = ?";
        $params[] = $categoryFilter;
        
        // Get category name
        $catSQL = "SELECT name FROM category WHERE id = ?";
        $stmt = $pdo->prepare($catSQL);
        $stmt->execute([$categoryFilter]);
        $catResult = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($catResult) {
            $categoryName = $catResult['name'];
        }
    }

    // Get all products (or filtered by category) with their relationships
    $productsSQL = "SELECT 
                        p.id,
                        p.code,
                        p.name,
                        p.description,
                        p.price,
                        p.purchasePrice,
                        p.sellingPrice,
                        p.stock,
                        p.vatRate,
                        p.statut_TVA,
                        p.expiryDate,
                        p.createdAt,
                        p.updatedAt,
                        c.name as categoryName,
                        s.name as supplierName,
                        s.contact as supplierContact
                    FROM product p 
                    LEFT JOIN category c ON p.categoryId = c.id 
                    LEFT JOIN supplier s ON p.supplierId = s.id 
                    $whereClause
                    ORDER BY p.name ASC";
    
    $stmt = $pdo->prepare($productsSQL);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate statistics
    $totalProducts = count($products);
    $totalValue = 0;
    $totalStock = 0;
    $lowStockCount = 0;
    $outOfStockCount = 0;
    $expiredCount = 0;
    $currentDate = date('Y-m-d');

    foreach ($products as $product) {
        $totalValue += $product['stock'] * $product['sellingPrice'];
        $totalStock += $product['stock'];
        if ($product['stock'] <= 10) $lowStockCount++;
        if ($product['stock'] <= 0) $outOfStockCount++;
        if (!empty($product['expiryDate']) && $product['expiryDate'] <= $currentDate) $expiredCount++;
    }

    // Set headers for Excel download
    $filename = 'Produits_' . ($categoryFilter ? preg_replace('/[^a-zA-Z0-9]/', '_', $categoryName) : 'Tous') . '_' . date('Y-m-d_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Add BOM for UTF-8 encoding (important for special characters)
    echo "\xEF\xBB\xBF";

    // Start HTML table for Excel
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; }
        .header { background-color: #10b981; color: white; font-weight: bold; text-align: center; padding: 15px; font-size: 18pt; }
        .category-badge { background-color: #3b82f6; color: white; padding: 5px 15px; border-radius: 5px; display: inline-block; margin: 10px 0; font-size: 14pt; }
        .info-section { margin: 20px 0; }
        .info-table { border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 8px; border: 1px solid #ddd; }
        .info-label { background-color: #E7E6E6; font-weight: bold; width: 250px; }
        .data-table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        .data-table th { background-color: #10b981; color: white; font-weight: bold; padding: 10px; border: 1px solid #000; text-align: left; }
        .data-table td { padding: 8px; border: 1px solid #ddd; }
        .data-table tr:nth-child(even) { background-color: #F2F2F2; }
        .stock-low { background-color: #FFF2CC; }
        .stock-out { background-color: #FFC7CE; color: #9C0006; }
        .expired { background-color: #FFC7CE; color: #9C0006; font-weight: bold; }
        .number { text-align: right; }
        .footer { margin-top: 30px; font-size: 9pt; color: #666; text-align: center; }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    📦 EXPORT DES PRODUITS - PHARMASYS
</div>

<!-- Category Badge -->
<?php if ($categoryFilter): ?>
<div style="text-align: center; margin: 15px 0;">
    <span class="category-badge">📁 Catégorie: <?php echo htmlspecialchars($categoryName); ?></span>
</div>
<?php endif; ?>

<!-- Information Section -->
<div class="info-section">
    <table class="info-table">
        <tr>
            <td class="info-label">📅 Date d'export</td>
            <td><?php echo date('d/m/Y à H:i:s'); ?></td>
        </tr>
        <tr>
            <td class="info-label">👤 Exporté par</td>
            <td><?php echo htmlspecialchars($_SESSION['username'] ?? 'Administrateur'); ?></td>
        </tr>
        <tr>
            <td class="info-label">📂 Catégorie sélectionnée</td>
            <td><strong><?php echo htmlspecialchars($categoryName); ?></strong></td>
        </tr>
        <tr>
            <td class="info-label">📊 Nombre de produits</td>
            <td><strong><?php echo $totalProducts; ?></strong></td>
        </tr>
        <tr>
            <td class="info-label">📦 Quantité totale en stock</td>
            <td><strong><?php echo number_format($totalStock, 0, ',', ' '); ?> unités</strong></td>
        </tr>
        <tr>
            <td class="info-label">💰 Valeur totale du stock</td>
            <td><strong><?php echo number_format($totalValue, 0, ',', ' '); ?> FCFA</strong></td>
        </tr>
        <tr>
            <td class="info-label">⚠️ Produits en stock bas (≤10)</td>
            <td><?php echo $lowStockCount; ?></td>
        </tr>
        <tr>
            <td class="info-label">❌ Produits en rupture de stock</td>
            <td><?php echo $outOfStockCount; ?></td>
        </tr>
        <tr>
            <td class="info-label">⏰ Produits expirés</td>
            <td><?php echo $expiredCount; ?></td>
        </tr>
    </table>
</div>

<!-- Products Table -->
<?php if ($totalProducts > 0): ?>
<table class="data-table">
    <thead>
        <tr>
            <th>ID</th>
            <th>Code</th>
            <th>Nom du Produit</th>
            <th>Description</th>
            <th>Catégorie</th>
            <th>Fournisseur</th>
            <th>Contact Fournisseur</th>
            <th>Prix Unitaire (FCFA)</th>
            <th>Prix d'Achat (FCFA)</th>
            <th>Prix de Vente (FCFA)</th>
            <th>Stock</th>
            <th>Valeur Stock (FCFA)</th>
            <th>Taux TVA (%)</th>
            <th>Statut TVA</th>
            <th>Date d'Expiration</th>
            <th>Statut Stock</th>
            <th>Date de Création</th>
            <th>Dernière Mise à Jour</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($products as $product): 
            $stockClass = '';
            $statusText = 'En stock';
            
            if ($product['stock'] <= 0) {
                $stockClass = 'stock-out';
                $statusText = 'RUPTURE';
            } elseif ($product['stock'] <= 10) {
                $stockClass = 'stock-low';
                $statusText = 'Stock bas';
            }
            
            $isExpired = !empty($product['expiryDate']) && $product['expiryDate'] <= $currentDate;
            $stockValue = $product['stock'] * $product['sellingPrice'];
        ?>
        <tr class="<?php echo $stockClass; ?>">
            <td><?php echo htmlspecialchars($product['id']); ?></td>
            <td><?php echo htmlspecialchars($product['code']); ?></td>
            <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
            <td><?php echo htmlspecialchars($product['description'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($product['categoryName'] ?? 'Non définie'); ?></td>
            <td><?php echo htmlspecialchars($product['supplierName'] ?? 'Non défini'); ?></td>
            <td><?php echo htmlspecialchars($product['supplierContact'] ?? '-'); ?></td>
            <td class="number"><?php echo number_format($product['price'], 0, ',', ' '); ?></td>
            <td class="number"><?php echo number_format($product['purchasePrice'] ?? 0, 0, ',', ' '); ?></td>
            <td class="number"><?php echo number_format($product['sellingPrice'], 0, ',', ' '); ?></td>
            <td class="number"><strong><?php echo $product['stock']; ?></strong></td>
            <td class="number"><?php echo number_format($stockValue, 0, ',', ' '); ?></td>
            <td class="number"><?php echo number_format($product['vatRate'], 2, ',', ' '); ?></td>
            <td><?php echo htmlspecialchars($product['statut_TVA'] ?? 'taxable'); ?></td>
            <td <?php echo $isExpired ? 'class="expired"' : ''; ?>>
                <?php 
                if (!empty($product['expiryDate'])) {
                    echo date('d/m/Y', strtotime($product['expiryDate']));
                    if ($isExpired) echo ' ⚠️ EXPIRÉ';
                } else {
                    echo '-';
                }
                ?>
            </td>
            <td><strong><?php echo $statusText; ?></strong></td>
            <td><?php echo date('d/m/Y H:i', strtotime($product['createdAt'])); ?></td>
            <td><?php echo date('d/m/Y H:i', strtotime($product['updatedAt'])); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<div style="text-align: center; padding: 40px; background: #FFF2CC; border: 2px solid #F59E0B; border-radius: 10px; margin: 20px 0;">
    <h2 style="color: #92400E;">⚠️ Aucun produit trouvé</h2>
    <p style="color: #78350F;">La catégorie sélectionnée ne contient aucun produit.</p>
</div>
<?php endif; ?>

<!-- Footer -->
<div class="footer">
    <p><strong>PharmaSys - Système de Gestion Pharmaceutique</strong></p>
    <p>Document confidentiel - Usage interne uniquement</p>
    <p>Généré le <?php echo date('d/m/Y à H:i:s'); ?></p>
    <?php if ($categoryFilter): ?>
    <p><em>Export filtré par catégorie: <?php echo htmlspecialchars($categoryName); ?></em></p>
    <?php endif; ?>
</div>

</body>
</html>
<?php

} catch (Exception $e) {
    // In case of error, output a simple error message
    echo "<h2>Erreur lors de l'export</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}

} else {
    header("Location: ../login.php");
    exit();
}
?>