<?php
session_start();
if($_SESSION["role"] !== "ADMIN" || $_SESSION["id"] !== session_id()){
    header("Location: ../logout.php");
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Include database connection
    include '../config/database.php';
    
    if (!isset($pdo)) {
        throw new Exception('Database connection not found');
    }

    // Get delivery ID from URL
    $deliveryId = $_GET['id'] ?? '';
    if (empty($deliveryId)) {
        header("Location: stock-deliveries.php");
        exit();
    }

    $success_message = '';
    $error_message = '';

   $sql = "SELECT * FROM delivery WHERE id = ? AND pharmacy_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$deliveryId, $pharmacyId]);

$delivery = $stmt->fetch(PDO::FETCH_ASSOC);

if (empty($delivery)) {
    header("Location: stock-deliveries.php");
    exit();     
}


    // Handle AJAX requests for product search
    if (isset($_GET['action']) && $_GET['action'] === 'search_products') {
        $searchTerm = $_GET['term'] ?? '';
        $products = [];
        
        if (strlen($searchTerm) >= 2) {
            $searchSQL = "SELECT id, name, code, description, stock, purchasePrice, sellingPrice, statut_TVA,expiryDate
                         FROM product
                         WHERE (name LIKE ? OR code LIKE ? OR description LIKE ?) AND pharmacy_id = ?
                         ORDER BY name ASC
                         LIMIT 20";
            $searchTerm = "%{$searchTerm}%";
            $stmt = $pdo->prepare($searchSQL);
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $pharmacyId]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
           
        header('Content-Type: application/json');
        echo json_encode($products);
        exit();
    }



    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_delivery_item':
                    $productId = trim($_POST['productId'] ?? '');
                    $quantity = intval($_POST['quantity'] ?? 0);
                    $priceCession = floatval($_POST['priceCession'] ?? 0);
                    $publicPrice = floatval($_POST['publicPrice'] ?? 0);
                    $date=$_POST['date'];
                    $ASD = floatval($_POST['ASD'] ?? 0);
                   
                    
                    // Validation
               
                             if (
    empty($productId) ||
    $quantity <= 0 ||
    $priceCession <= 0 ||
    $publicPrice <= 0
) {
    $error_message = "Veuillez sélectionner un produit et saisir une quantité et des prix valides.";
    break;
}




if (empty($date) || !strtotime($date)) {
    $error_message = "Veuillez sélectionner une date d'expiration .";
    break;
}


                    

                    $pdo->beginTransaction();
                    try {
                        // Get product info
                        $productSQL = "SELECT * FROM product WHERE id = ? AND pharmacy_id = ?";
                        $stmt = $pdo->prepare($productSQL);
                        $stmt->execute([$productId, $pharmacyId]);
                        $product = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$product) {
                            throw new Exception('Produit non trouvé');
                        }

                        // Check if item already exists in delivery
                        $checkSQL = "SELECT quantity FROM delivery_items WHERE deliveryId = ? AND productId = ?";
                        $stmt = $pdo->prepare($checkSQL);
                        $stmt->execute([$deliveryId, $productId]);
                        $existingItem = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($existingItem) {
                            // Update existing item
                            $newQuantity = $existingItem['quantity'] + $quantity;
                            $updateSQL = "UPDATE delivery_items SET quantity = ?,priceCession = ?, publicPrice= ?, ASD = ?, updatedAt = NOW() 
                                         WHERE deliveryId = ? AND productId = ?";
                            $stmt = $pdo->prepare($updateSQL);
                            $stmt->execute([$newQuantity, $priceCession,$publicPrice, $ASD, $deliveryId, $productId]);
                            
                              $updateSQLA = "UPDATE product SET stock = ?,purchasePrice = ?, sellingPrice= ?, ASD = ?,expiryDate=?, updatedAt = NOW() 
                                         WHERE id = ?";
                            $stmt = $pdo->prepare($updateSQLA);
                            $stmt->execute([$newQuantity, $priceCession,$publicPrice, $ASD,$date,  $productId]);
                            
                            
                        } else {
                            // Add new item
                            $insertSQL = "INSERT INTO delivery_items (deliveryId, productId, quantity, publicPrice, priceCession, ASD, statutTVA, validated, createdAt, updatedAt, pharmacy_id)
                                         VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW(), ?)";
                            $stmt = $pdo->prepare($insertSQL);
                            $stmt->execute([$deliveryId, $productId, $quantity, $publicPrice, $priceCession, $ASD, $product['statut_TVA'], $pharmacyId]);
                        }

                        // Update product stock and prices
                        $newStock = $product['stock'] + $quantity;
                        $updateProductSQL = "UPDATE product SET stock = ?, purchasePrice = ?,sellingPrice=?, updatedAt = NOW(), expiryDate= ? WHERE id = ?";
                        $stmt = $pdo->prepare($updateProductSQL);
                        $stmt->execute([$newStock, $priceCession,$publicPrice,$date, $productId]);

                        $pdo->commit();
                        $success_message = "Article ajouté à la livraison avec succès.";
                        
                    } catch (Exception $e) {
                        $pdo->rollback();
                        $error_message = "Erreur lors de l'ajout: " . $e->getMessage();
                    }
                    break;

                case 'create_new_product':
                    $productCode = trim($_POST['productCode'] ?? '');
                    $productName = trim($_POST['productName'] ?? '');
                    $description = trim($_POST['description'] ?? '');
                    $publicPrice = floatval($_POST['publicPrice'] ?? 0);
                    $quantity = intval($_POST['quantity'] ?? 0);
                    $date=$_POST['date'];
                    $priceCession = floatval($_POST['priceCession'] ?? 0);
                    $ASD = floatval($_POST['ASD'] ?? 0);
                    $statutTVA = $_POST['statutTVA'] ?? 'Oui';
                    $categoryId = intval($_POST['categoryId'] ?? 0) ?: null;
                    
                    // Validation
                    if (empty($productCode) || empty($productName) || $quantity <= 0 || $priceCession <= 0 || $publicPrice <= 0) {
                        $error_message = "Veuillez remplir tous les champs obligatoires (Code, Nom, Quantité, Prix de cession, Prix publique).";
                        break;
                    }

                    $pdo->beginTransaction();
                    try {
                        // Check if product code already exists
                        $checkSQL = "SELECT id, stock FROM product WHERE code = ? AND pharmacy_id = ?";
                        $stmt = $pdo->prepare($checkSQL);
                        $stmt->execute([$productCode, $pharmacyId]);
                        $existingProduct = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($existingProduct) {
                            // Product exists - update stock and add to delivery
                            $productId = $existingProduct['id'];
                            $newStock = $existingProduct['stock'] + $quantity;
                            
                            $updateProductSQL = "UPDATE product SET stock = ?, purchasePrice = ?, expiryDate=?,updatedAt = NOW() WHERE id = ?";
                            $stmt = $pdo->prepare($updateProductSQL);
                            $stmt->execute([$newStock, $priceCession, $date, $productId]);
                        } else {
                            // Create new product
                            $stmt = $pdo->query("SELECT id FROM product ORDER BY CAST(id AS UNSIGNED) DESC LIMIT 1");
                            $lastId = $stmt->fetch(PDO::FETCH_COLUMN);
                            $productId = $lastId ? (string)((int)$lastId + 1) : "1";
                            
                            // Calculate selling price
                            $vatRate = ($statutTVA === 'Oui') ? 18 : 0;
                           // $sellingPrice = $priceCession * ($statutTVA === 'Oui' ? 1.75 : 1.41);
                          // $publicPrice = $publicPrice;
                            
                            $insertProductSQL = "INSERT INTO product (id, name, description, price, stock, purchasePrice, sellingPrice, vatRate, createdAt, updatedAt, categoryId, code, statut_TVA, expiryDate, pharmacy_id)
                                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?)";
                            $stmt = $pdo->prepare($insertProductSQL);
                            $stmt->execute([$productId, $productName, $description, $priceCession, $quantity, $priceCession, $publicPrice, $vatRate, $categoryId, $productCode, $statutTVA, $date, $pharmacyId]);
                        }
                        
                        // Add to delivery
                        $insertDeliveryItemSQL = "INSERT INTO delivery_items (deliveryId, productId, quantity, priceCession, publicPrice, ASD, statutTVA, validated, createdAt, updatedAt, pharmacy_id)
                                                VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW(), ?)";
                        $stmt = $pdo->prepare($insertDeliveryItemSQL);
                        $stmt->execute([$deliveryId, $productId, $quantity, $priceCession, $publicPrice, $ASD, $statutTVA, $pharmacyId]);
                        $pdo->commit();
                        $success_message = "Nouveau produit créé et ajouté à la livraison.";
                        
                    } catch (Exception $e) {
                        $pdo->rollback();
                        $error_message = "Erreur lors de la création du produit: " . $e->getMessage();
                    }
                    break;

                case 'validate_item':
                    $itemId = $_POST['itemId'] ?? '';
                    if (!empty($itemId)) {
                        $updateSQL = "UPDATE delivery_items SET validated = 1, updatedAt = NOW() WHERE deliveryId = ? AND productId = ?";
                        $stmt = $pdo->prepare($updateSQL);
                        $result = $stmt->execute([$deliveryId, $itemId]);
                        
                        if ($result) {
                            $success_message = "Article validé avec succès.";
                        } else {
                            $error_message = "Erreur lors de la validation.";
                        }
                    }
                    break;

                case 'validate_all':
                    $updateSQL = "UPDATE delivery_items SET validated = 1, updatedAt = NOW() WHERE deliveryId = ? AND validated = 0";
                    $stmt = $pdo->prepare($updateSQL);
                    $result = $stmt->execute([$deliveryId]);
                    
                    if ($result) {
                        $success_message = "Tous les articles ont été validés.";
                    } else {
                        $error_message = "Erreur lors de la validation globale.";
                    }
                    break;

                case 'delete_item':
                    $productId = $_POST['productId'] ?? '';
                    $quantity = intval($_POST['quantity'] ?? 0);
                    
                    if (!empty($productId)) {
                        $pdo->beginTransaction();
                        try {
                            // Remove from delivery_items
                            $deleteSQL = "DELETE FROM delivery_items WHERE deliveryId = ? AND productId = ?";
                            $stmt = $pdo->prepare($deleteSQL);
                            $stmt->execute([$deliveryId, $productId]);
                            
                            // Reduce product stock
                            $updateStockSQL = "UPDATE product SET stock = GREATEST(0, stock - ?) WHERE id = ?";
                            $stmt = $pdo->prepare($updateStockSQL);
                            $stmt->execute([$quantity, $productId]);
                            
                            $pdo->commit();
                            $success_message = "Article supprimé de la livraison.";
                            
                        } catch (Exception $e) {
                            $pdo->rollback();
                            $error_message = "Erreur lors de la suppression: " . $e->getMessage();
                        }
                    }
                    break;
                    
case 'edit_item':
    $productId = trim($_POST['productId'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 0);
    $priceCession = floatval($_POST['priceCession'] ?? 0);
    $ASD = floatval($_POST['ASD'] ?? 0);
    $statutTVA = $_POST['statutTVA'] ?? 'Oui';
    $expiryDate = $_POST['date'] ?? null;
    $publicPrice= floatval($_POST['publicPrice'] ?? 0);
    
    // Validation
    if (empty($productId) || $quantity <= 0 || $priceCession <= 0  ||  $publicPrice <= 0 ) {
        $error_message = "Données invalides. Veuillez vérifier les champs.";
        break;
    }

    $pdo->beginTransaction();
    try {
        // Get current quantity in delivery
        $currentSQL = "SELECT quantity FROM delivery_items WHERE deliveryId = ? AND productId = ?";
        $stmt = $pdo->prepare($currentSQL);
        $stmt->execute([$deliveryId, $productId]);
        $currentItem = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentItem) {
            throw new Exception('Article non trouvé dans la livraison');
        }
        
        $oldQuantity = $currentItem['quantity'];
        $quantityDifference = $quantity - $oldQuantity;
        
        // Update delivery item
        $updateItemSQL = "UPDATE delivery_items 
                         SET quantity = ?, 
                             priceCession = ?, 
                             ASD = ?, 
                             statutTVA = ?,
                             publicPrice=?,
                             updatedAt = NOW() 
                         WHERE deliveryId = ? AND productId = ?";
        $stmt = $pdo->prepare($updateItemSQL);
        $stmt->execute([$quantity, $priceCession, $ASD, $statutTVA,$publicPrice, $deliveryId, $productId]);
        
        // Update product stock (adjust by the difference)
        $updateStockSQL = "UPDATE product 
                          SET stock = stock + ?, 
                              purchasePrice = ?,
                              sellingPrice=?,
                              expiryDate = COALESCE(NULLIF(?, ''), expiryDate),
                              updatedAt = NOW() 
                          WHERE id = ?";
        $stmt = $pdo->prepare($updateStockSQL);
        $stmt->execute([$quantityDifference, $priceCession,$publicPrice, $expiryDate, $productId]);
        
        $pdo->commit();
        $success_message = "Article modifié avec succès.";
        
    } catch (Exception $e) {
        $pdo->rollback();
        $error_message = "Erreur lors de la modification: " . $e->getMessage();
    }
    break;

            }
        }
        
        // Redirect to prevent form resubmission
        $redirect_url = "delivery_items.php?id=" . urlencode($deliveryId);
        if (!empty($success_message)) {
            $redirect_url .= "&success=" . urlencode($success_message);
        }
        if (!empty($error_message)) {
            $redirect_url .= "&error=" . urlencode($error_message);
        }
        header("Location: " . $redirect_url);
        exit();
    }

    // Handle messages from redirect
    if (isset($_GET['success'])) {
        $success_message = $_GET['success'];
    }
    if (isset($_GET['error'])) {
        $error_message = $_GET['error'];
    }

    // Get delivery information
    $deliverySQL = "SELECT d.*, s.name as supplierName FROM delivery d LEFT JOIN supplier s ON d.supplierId = s.id WHERE d.id = ? AND d.pharmacy_id = ?";
    $stmt = $pdo->prepare($deliverySQL);
    $stmt->execute([$deliveryId, $pharmacyId]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$delivery) {
        header("Location: stock-deliveries.php");
        exit();
    }

    // Get delivery items
    $itemsSQL = "SELECT di.*, p.name as productName,p.expiryDate as expiryDate, p.code as productCode, p.description as productDescription,
                 (di.quantity * di.publicPrice) as totalValue,
                 di.publicPrice as publicPrice
                 FROM delivery_items di 
                 LEFT JOIN product p ON di.productId = p.id 
                 WHERE di.deliveryId = ?
                 ORDER BY di.createdAt DESC";
    $stmt = $pdo->prepare($itemsSQL);
    $stmt->execute([$deliveryId]);
    $deliveryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get categories for dropdown
    $categoriesSQL = "SELECT id, name FROM category WHERE pharmacy_id = ? ORDER BY name";
    $stmt = $pdo->prepare($categoriesSQL);
    $stmt->execute([$pharmacyId]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $totalQuantity = array_sum(array_column($deliveryItems, 'quantity'));
    $totalValue = array_sum(array_column($deliveryItems, 'totalValue'));
    $validatedCount = count(array_filter($deliveryItems, function($item) { return $item['validated'] == 1; }));

    // Determine delivery status
    $status = 'empty';
    if (count($deliveryItems) > 0) {
        if ($validatedCount === count($deliveryItems)) {
            $status = 'validated';
        } elseif ($validatedCount > 0) {
            $status = 'partial';
        } else {
            $status = 'pending';
        }
    }

} catch (Exception $e) {
    $error_message = "Erreur système: " . $e->getMessage();
}

// Helper functions
function formatPrice($price) {
    return number_format($price, 0, ',', ' ') . ' FCFA';
}

function getStatusBadge($status) {
    switch ($status) {
        case 'empty':
            return '<span class="badge badge-secondary">Vide</span>';
        case 'pending':
            return '<span class="badge badge-warning">En attente</span>';
        case 'partial':
            return '<span class="badge badge-info">Partiellement validée</span>';
        case 'validated':
            return '<span class="badge badge-success">Validée</span>';
        default:
            return '<span class="badge badge-secondary">Inconnu</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys - Livraison #<?php echo htmlspecialchars($deliveryId); ?></title>
    
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <style>
        .delivery-header {
            background: var(--ds-green);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .delivery-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .info-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        
        .info-label {
            font-size: 0.875rem;
            opacity: 0.8;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .main-grid {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: var(--ds-surface-alt);
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #495057;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.25);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
            color: white;
        }
        
        .btn-success {
            background: #48bb78;
            color: white;
        }
        
        .btn-success:hover {
            background: #38a169;
        }
        
        .btn-danger {
            background: #f56565;
            color: white;
        }
        
        .btn-danger:hover {
            background: #e53e3e;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }
        
        .btn-block {
            width: 100%;
            justify-content: center;
        }
        
        .search-container {
            position: relative;
        }
        
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ced4da;
            border-top: none;
            border-radius: 0 0 6px 6px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .search-results.show {
            display: block;
        }
        
        .search-result {
            padding: 0.75rem;
            border-bottom: 1px solid var(--ds-surface-alt);
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        
        .search-result:hover {
            background: var(--ds-surface-alt);
        }
        
        .search-result:last-child {
            border-bottom: none;
        }
        
        .result-name {
            font-weight: 500;
            color: #495057;
        }
        
        .result-info {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .selected-product {
            background: #e6f3ff;
            border: 1px solid #667eea;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .selected-product h6 {
            margin: 0 0 0.5rem 0;
            color: #495057;
        }
        
        .product-details {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .items-table th,
        .items-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .items-table th {
            background: var(--ds-surface-alt);
            font-weight: 600;
            color: #495057;
        }
        
        .items-table tr:hover {
            background: var(--ds-surface-alt);
        }
        
        .badge {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 4px;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-info {
            background: #cce7ff;
            color: #004085;
        }
        
        .badge-secondary {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .summary-item {
            text-align: center;
            padding: 1rem;
            background: var(--ds-surface-alt);
            border-radius: 8px;
        }
        
        .summary-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.25rem;
        }
        
        .summary-label {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            
            .delivery-info {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <div id="sidebarOverlay" class="sidebar-overlay"></div>
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <?php include 'header.php'; ?>
            
            <main class="content-area">
                <div style="max-width: 1600px; margin: 0 auto; padding: 2rem;">
                    <!-- Breadcrumb -->
                    <div style="margin-bottom: 1rem;">
                        <a href="stock-deliveries.php" style="color: #667eea; text-decoration: none;">← Retour aux livraisons</a>
                    </div>

                    <!-- Delivery Header -->
                    <div class="delivery-header">
                        <h1 style="margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
                            <i data-lucide="package-open"></i>
                            Livraison #<?php echo htmlspecialchars($deliveryId); ?>
                        </h1>
                        <div class="delivery-info">
                            <div class="info-card">
                                <div class="info-label">Fournisseur</div>
                                <div class="info-value"><?php echo htmlspecialchars($delivery['supplierName'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-card">
                                <div class="info-label">Date de livraison</div>
                                <div class="info-value"><?php echo date('d/m/Y', strtotime($delivery['deliveryDate'])); ?></div>
                            </div>
                            <div class="info-card">
                                <div class="info-label">Statut</div>
                                <div class="info-value"><?php echo getStatusBadge($status); ?></div>
                            </div>
                            <div class="info-card">
                                <div class="info-label">Articles</div>
                                <div class="info-value"><?php echo count($deliveryItems); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Alerts -->
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <i data-lucide="check-circle"></i>
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <i data-lucide="alert-circle"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Main Grid -->
                    <div class="main-grid">
                        <!-- Add Items Form -->
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">
                                    <i data-lucide="plus-circle"></i>
                                    Ajouter des Articles
                                </h2>
                            </div>
                            <div class="card-body">
                                <!-- Existing Product Section -->
                             <div class="form-section">
    <h3 class="section-title">Produit Existant</h3>
    <form method="POST" id="existingProductForm">
        <input type="hidden" name="action" value="add_delivery_item">
        
        <div class="form-group">
            <label class="form-label">Rechercher un produit</label>
            <div class="search-container">
                <input type="text" id="productSearch" class="form-control" 
                       placeholder="Nom, code ou description du produit..." autocomplete="off">
                <div class="search-results" id="searchResults"></div>
            </div>
        </div>

        <div id="selectedProductInfo" class="selected-product" style="display: none;">
            <h6><i data-lucide="check"></i> Produit sélectionné</h6>
            <div class="product-details">
                <div><strong>Nom:</strong> <span id="selectedName"></span></div>
                <div><strong>Code:</strong> <span id="selectedCode"></span></div>
                <div><strong>Stock actuel:</strong> <span id="selectedStock"></span></div>
                <div><strong>Prix d'achat:</strong> <span id="selectedPrice"></span></div>
                  <div><strong>Date d'expiration :</strong> <span id="expiryDate"></span></div>
            </div>
        </div>

        <input type="hidden" name="productId" id="selectedProductId">
        <input type="hidden" name="statutTVA" id="selectedStatutTVA">
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Quantité *</label>
                <input type="number" name="quantity" class="form-control" min="1" value="1" required>
            </div>
            <div class="form-group">
                <label class="form-label">Prix de cession *</label>
                <input type="number" name="priceCession" id="existingPriceCession" class="form-control" step="0.01" min="0" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Prix Public Calculé *</label>
                <input type="number" name="publicPrice" id="existingPublicPrice" class="form-control" step="0.01" min="0" required>
                <small style="color: #6c757d; font-size: 0.75rem;">
                    <i data-lucide="info" style="width: 12px; height: 12px;"></i>
                    Auto-calculé, modifiable
                </small>
            </div>
            <div class="form-group">
                <label class="form-label">ASD</label>
                <input type="number" name="ASD" class="form-control" step="0.01" min="0" value="<?php echo $delivery['ASD'] ?? '0'; ?>">
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Date d'expiration</label>
            <input type="date" name="date" class="form-control" id="expiryDate">
        </div>
        
        <button type="submit" class="btn btn-primary btn-block">
            <i data-lucide="plus"></i>
            Ajouter à la livraison
        </button>
    </form>
</div>

<!-- NEW PRODUCT FORM - Replace the existing new product section -->
<div class="form-section">
    <h3 class="section-title">Nouveau Produit</h3>
    <form method="POST" id="newProductForm">
        <input type="hidden" name="action" value="create_new_product">
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Code produit *</label>
                <input type="text" name="productCode" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Nom du produit *</label>
                <input type="text" name="productName" class="form-control" required>
            </div>
        </div>
        
        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="2"></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Date d'expiration</label>
            <input type="date" name="date" class="form-control">
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Quantité *</label>
                <input type="number" name="quantity" class="form-control" min="1" value="1" required>
            </div>
            <div class="form-group">
                <label class="form-label">Prix de cession *</label>
                <input type="number" name="priceCession" id="newPriceCession" class="form-control" step="0.01" min="0" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Statut TVA *</label>
                <select name="statutTVA" id="newStatutTVA" class="form-control" required>
                    <option value="Oui">Avec TVA (18%)</option>
                    <option value="Non">Sans TVA</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Prix Public Calculé *</label>
                <input type="number" name="publicPrice" id="newPublicPrice" class="form-control" step="0.01" min="0" required>
                <small style="color: #6c757d; font-size: 0.75rem;">
                    <i data-lucide="info" style="width: 12px; height: 12px;"></i>
                    Auto-calculé, modifiable
                </small>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">ASD</label>
                <input type="number" name="ASD" class="form-control" step="0.01" min="0" value="<?php echo $delivery['ASD'] ?? '0'; ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Catégorie</label>
                <select name="categoryId" class="form-control">
                    <option value="">Aucune catégorie</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <button type="submit" class="btn btn-success btn-block">
            <i data-lucide="package-plus"></i>
            Créer et ajouter
        </button>
    </form>
</div>
            </div>
        </div>

        <!-- Items List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i data-lucide="list"></i>
                    Articles de la livraison (<?php echo count($deliveryItems); ?>)
                </h2>
                <?php if (count($deliveryItems) > 0 && $validatedCount < count($deliveryItems)): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="validate_all">
                        <button type="submit" class="btn btn-success btn-sm" 
                                onclick="return confirm('Valider tous les articles non validés ?');">
                            <i data-lucide="check-circle"></i>
                            Valider tout
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($deliveryItems)): ?>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Quantité</th>
                                <th>Prix cession</th>
                                <th>ASD</th>
                                <th>Prix public</th>
                                <th>Total</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deliveryItems as $item): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($item['productName']); ?></strong>
                                            <br>
                                            <small style="color: #6c757d;">
                                                Code: <?php echo htmlspecialchars($item['productCode']); ?>
                                                <?php if ($item['productDescription']): ?>
                                                    <br><?php echo htmlspecialchars($item['productDescription']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><?php echo formatPrice($item['priceCession']); ?></td>
                                    <td><?php echo formatPrice($item['ASD']); ?></td>
                                    <td><?php echo ($item['publicPrice'] !== null) ? formatPrice($item['publicPrice']) : 'non defini'; ?></td>
                                    <td><strong><?php  echo ($item['publicPrice'] !== null) ? formatPrice($item['totalValue']) : 'non defini'; ?></strong></td>
                                    <td>
                                        <?php if ($item['validated']): ?>
                                            <span class="badge badge-success">Validé</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">En attente</span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Remplacer la cellule "Actions" dans le tableau des articles -->
<td>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
        <!-- Bouton Modifier -->
        <button type="button" 
                class="btn btn-sm" 
                style="background: #3b82f6; color: white;"
                onclick="openEditModal(
                    '<?php echo htmlspecialchars($item['productId'], ENT_QUOTES); ?>',
                    '<?php echo htmlspecialchars($item['productName'], ENT_QUOTES); ?>',
                    '<?php echo htmlspecialchars($item['productCode'], ENT_QUOTES); ?>',
                    <?php echo $item['quantity']; ?>,
                    <?php echo $item['priceCession']; ?>,
                 <?php echo  ($item['publicPrice'] !== null) ? $item['publicPrice']: 0 ; ?>,
                
                    <?php echo $item['ASD']; ?>,
                    '<?php echo htmlspecialchars($item['statutTVA'], ENT_QUOTES); ?>',
                    '<?php echo htmlspecialchars($item['expiryDate'] ?? '', ENT_QUOTES); ?>'
                )"
                title="Modifier l'article">
            <i data-lucide="edit" style="width: 16px; height: 16px;"></i>
        </button>
        
        <!-- Bouton Valider (si pas encore validé) -->
        <?php if (!$item['validated']): ?>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="validate_item">
                <input type="hidden" name="itemId" value="<?php echo $item['productId']; ?>">
                <button type="submit" class="btn btn-success btn-sm" title="Valider l'article">
                    <i data-lucide="check" style="width: 16px; height: 16px;"></i>
                </button>
            </form>
        <?php endif; ?>
        
        <!-- Bouton Supprimer -->
        <form method="POST" style="display: inline;">
            <input type="hidden" name="action" value="delete_item">
            <input type="hidden" name="productId" value="<?php echo $item['productId']; ?>">
            <input type="hidden" name="quantity" value="<?php echo $item['quantity']; ?>">
            <button type="submit" 
                    class="btn btn-danger btn-sm"
                    onclick="return confirm('Supprimer cet article de la livraison ?');"
                    title="Supprimer l'article">
                <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
            </button>
        </form>
    </div>
</td>

<!-- Style supplémentaire pour les boutons -->
<style>
    .btn-sm {
        padding: 0.5rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
    }
    
    .btn-sm:hover {
        opacity: 0.9;
        transform: translateY(-1px);
    }
    
    .btn-sm:active {
        transform: translateY(0);
    }
</style>
                                
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Summary -->
                    <div class="summary-grid">
                        <div class="summary-item">
                            <div class="summary-value"><?php echo count($deliveryItems); ?></div>
                            <div class="summary-label">Produits</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value"><?php echo $totalQuantity; ?></div>
                            <div class="summary-label">Quantité totale</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value"><?php echo $validatedCount; ?></div>
                            <div class="summary-label">Validés</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value"><?php echo formatPrice($totalValue); ?></div>
                            <div class="summary-label">Valeur totale</div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i data-lucide="package" style="width: 64px; height: 64px; opacity: 0.3;"></i>
                        <h3 style="margin: 1rem 0 0.5rem 0;">Aucun article dans cette livraison</h3>
                        <p>Utilisez le formulaire ci-contre pour ajouter des articles.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
            </main>
        </div>
    </div>
    
    
    
    <!-- Modal de Modification (à ajouter dans le HTML après le tableau) -->
<div id="editModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); overflow: auto;">
    <div class="modal-content" style="background-color: white; margin: 5% auto; padding: 0; border-radius: 12px; width: 90%; max-width: 600px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <!-- Modal Header -->
        <div style="background: var(--ds-green); color: white; padding: 1.5rem; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                <i data-lucide="edit" style="width: 24px; height: 24px;"></i>
                Modifier l'article
            </h3>
            <button onclick="closeEditModal()" style="background: none; border: none; color: white; cursor: pointer; padding: 0.5rem;">
                <i data-lucide="x" style="width: 24px; height: 24px;"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <form method="POST" id="editForm" style="padding: 1.5rem;">
            <input type="hidden" name="action" value="edit_item">
            <input type="hidden" name="productId" id="editProductId">
            
            <!-- Product Info Display -->
            <div style="background: var(--ds-surface-alt); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid #667eea;">
                <div style="display: flex; align-items: start; gap: 0.5rem;">
                    <i data-lucide="info" style="width: 20px; height: 20px; color: #667eea; flex-shrink: 0; margin-top: 2px;"></i>
                    <div style="flex: 1;">
                        <strong style="display: block; margin-bottom: 0.5rem; color: #495057;">Produit</strong>
                        <div style="font-size: 0.875rem; color: #6c757d;">
                            <div><strong>Nom:</strong> <span id="editProductName"></span></div>
                            <div><strong>Code:</strong> <span id="editProductCode"></span></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Form Fields -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Quantité *</label>
                    <input type="number" name="quantity" id="editQuantity" class="form-control" min="1" required>
                    <small style="color: #6c757d; font-size: 0.75rem; display: block; margin-top: 0.25rem;">
                        <i data-lucide="info" style="width: 12px; height: 12px;"></i>
                        Quantité livrée
                    </small>
                </div>
                <div class="form-group">
                    <label class="form-label">Prix de cession *</label>
                    <input type="number" name="priceCession" id="editPriceCession" class="form-control" step="0.01" min="0" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">ASD</label>
                    <input type="number" name="ASD" id="editASD" class="form-control" step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Statut TVA *</label>
                    <select name="statutTVA" id="editStatutTVA" class="form-control" required>
                        <option value="Oui">Avec TVA (18%)</option>
                        <option value="Non">Sans TVA</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Prix Public Calculé *</label>
                <input type="number" name="publicPrice" id="editPublicPrice" class="form-control" step="0.01" min="0">
                <small style="color: #6c757d; font-size: 0.75rem; display: block; margin-top: 0.25rem;">
                    <i data-lucide="calculator" style="width: 12px; height: 12px;"></i>
                    Calculé automatiquement selon le statut TVA
                </small>
            </div>
            
            <div class="form-group">
                <label class="form-label">Date d'expiration</label>
                <input type="date" name="date" id="editExpiryDate" class="form-control">
            </div>
            
            <!-- Modal Footer -->
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e9ecef;">
                <button type="button" onclick="closeEditModal()" class="btn" style="background: #6c757d; color: white;">
                    <i data-lucide="x"></i>
                    Annuler
                </button>
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="save"></i>
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</div>

<style>
    .modal {
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }
    
    .modal-content {
        animation: slideIn 0.3s ease;
    }
    
    @keyframes slideIn {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    @media (max-width: 768px) {
        .modal-content {
            width: 95% !important;
            margin: 2% auto !important;
        }
    }
</style>

<script>
// Variables globales pour le modal
let currentEditItem = null;

// Fonction pour ouvrir le modal d'édition
function openEditModal(productId, productName, productCode, quantity, priceCession,publicPrice, ASD, statutTVA, expiryDate) {
    currentEditItem = {
        productId,
        productName,
        productCode,
        quantity,
        priceCession,
        publicPrice,
        ASD,
        statutTVA,
        expiryDate
    };
    
    // Remplir les champs du formulaire
    document.getElementById('editProductId').value = productId;
    document.getElementById('editProductName').textContent = productName;
    document.getElementById('editProductCode').textContent = productCode;
    document.getElementById('editQuantity').value = quantity;
    document.getElementById('editPriceCession').value = priceCession;
    document.getElementById('editASD').value = ASD || 0;
    document.getElementById('editStatutTVA').value = statutTVA;
    document.getElementById('editExpiryDate').value = expiryDate || '';
     document.getElementById('editPublicPrice').value = publicPrice || '';
     
    // Calculer le prix public
  //  calculateEditPublicPrice();
    
    // Afficher le modal
    document.getElementById('editModal').style.display = 'block';
    document.body.style.overflow = 'hidden'; // Empêcher le scroll
    
    // Réinitialiser les icônes Lucide
    lucide.createIcons();
}

// Fonction pour fermer le modal
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
    document.body.style.overflow = 'auto'; // Réactiver le scroll
    currentEditItem = null;
}

// Calculer le prix public dans le modal d'édition
function calculateEditPublicPrice() {
    const priceCession = parseFloat(document.getElementById('editPriceCession').value) || 0;
    const statutTVA = document.getElementById('editStatutTVA').value;
    
    if (priceCession > 0) {
        const multiplier = (statutTVA === 'Oui') ? 1.75 : 1.41;
        const publicPrice = (priceCession * multiplier).toFixed(2);
        document.getElementById('editPublicPrice').value = publicPrice;
    } else {
        document.getElementById('editPublicPrice').value = '';
    }
}

// Event listeners pour le calcul automatique
document.getElementById('editPriceCession')?.addEventListener('input', calculateEditPublicPrice);
document.getElementById('editStatutTVA')?.addEventListener('change', calculateEditPublicPrice);

// Fermer le modal en cliquant à l'extérieur
window.addEventListener('click', function(event) {
    const modal = document.getElementById('editModal');
    if (event.target === modal) {
        closeEditModal();
    }
});

// Fermer le modal avec la touche Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('editModal');
        if (modal && modal.style.display === 'block') {
            closeEditModal();
        }
    }
});

// Validation du formulaire d'édition
document.getElementById('editForm')?.addEventListener('submit', function(e) {
    const quantity = parseInt(document.getElementById('editQuantity').value) || 0;
    const priceCession = parseFloat(document.getElementById('editPriceCession').value) || 0;
    const publicPrice = parseFloat(document.getElementById('editPublicPrice').value) || 0;
    
    if (quantity <= 0) {
        e.preventDefault();
        alert('La quantité doit être supérieure à 0.');
        return false;
    }
    
    if (priceCession <= 0) {
        e.preventDefault();
        alert('Le prix de cession doit être supérieur à 0.');
        return false;
    }
    
    if (publicPrice <= 0) {
        e.preventDefault();
        alert('Le prix public doit être supérieur à 0.');
        return false;
    }
    
    // Confirmation avant soumission
    if (!confirm('Êtes-vous sûr de vouloir modifier cet article ?')) {
        e.preventDefault();
        return false;
    }
});
</script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
      // Initialize Lucide icons
lucide.createIcons();

let searchTimeout;
let selectedProduct = null;

// Product search functionality
document.getElementById('productSearch').addEventListener('input', function(e) {
    const query = e.target.value;
    
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    if (query.length < 2) {
        hideSearchResults();
        return;
    }

    searchTimeout = setTimeout(() => {
        searchProducts(query);
    }, 300);
});

function searchProducts(query) {
    const searchResults = document.getElementById('searchResults');
    searchResults.innerHTML = '<div class="search-result">Recherche...</div>';
    searchResults.classList.add('show');

    fetch(`?id=<?php echo $deliveryId; ?>&action=search_products&term=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(products => {
            displaySearchResults(products);
        })
        .catch(error => {
            console.error('Erreur de recherche:', error);
            searchResults.innerHTML = '<div class="search-result">Erreur de recherche</div>';
        });
}

function displaySearchResults(products) {
    const searchResults = document.getElementById('searchResults');
    
    if (products.length === 0) {
        searchResults.innerHTML = '<div class="search-result">Aucun produit trouvé</div>';
        return;
    }

    let html = '';
    products.forEach(product => {
        html += `
            <div class="search-result" onclick='selectProduct(${JSON.stringify(product)})'>
                <div class="result-name">${escapeHtml(product.name)}</div>
                <div class="result-info">
                    Code: ${escapeHtml(product.code)} | 
                    Stock: ${product.stock} | 
                    Prix: ${formatPrice(product.purchasePrice)}
                </div>
            </div>
        `;
    });
    
    searchResults.innerHTML = html;
}

function selectProduct(product) {
    selectedProduct = product;
    
    // Update hidden fields
    document.getElementById('selectedProductId').value = product.id;
    document.getElementById('selectedStatutTVA').value = product.statut_TVA || 'Oui';
    
    // Update display
    document.getElementById('selectedName').textContent = product.name;
    document.getElementById('selectedCode').textContent = product.code;
    document.getElementById('selectedStock').textContent = product.stock;
    document.getElementById('selectedPrice').textContent = formatPrice(product.purchasePrice);
    
    // Show selected product info
    document.getElementById('selectedProductInfo').style.display = 'block';
    
    // Set suggested price
    document.getElementById('existingPriceCession').value = product.purchasePrice;
    
    // Calculate and set public price
    calculateExistingPublicPrice();
    
    // Hide search results
    hideSearchResults();
    
    // Update search input
    document.getElementById('productSearch').value = `${product.name} (${product.code})`;
    
    // Re-initialize lucide icons
    lucide.createIcons();
}

function hideSearchResults() {
    document.getElementById('searchResults').classList.remove('show');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatPrice(price) {
    return new Intl.NumberFormat('fr-FR').format(price) + ' FCFA';
}

// Auto-calculate public price for EXISTING product
document.getElementById('existingPriceCession').addEventListener('input', function() {
    calculateExistingPublicPrice();
});

function calculateExistingPublicPrice() {
    const priceCession = parseFloat(document.getElementById('existingPriceCession').value) || 0;
    const statutTVA = document.getElementById('selectedStatutTVA').value || 'Oui';
    
    if (priceCession > 0) {
        const multiplier = (statutTVA === 'Oui') ? 1.75 : 1.41;
        const publicPrice = (priceCession * multiplier).toFixed(2);
        document.getElementById('existingPublicPrice').value = publicPrice;
    } else {
        document.getElementById('existingPublicPrice').value = '';
    }
}

// Auto-calculate public price for NEW product
document.getElementById('newPriceCession').addEventListener('input', function() {
    calculateNewPublicPrice();
});

document.getElementById('newStatutTVA').addEventListener('change', function() {
    calculateNewPublicPrice();
});

function calculateNewPublicPrice() {
    const priceCession = parseFloat(document.getElementById('newPriceCession').value) || 0;
    const statutTVA = document.getElementById('newStatutTVA').value;
    
    if (priceCession > 0) {
        const multiplier = (statutTVA === 'Oui') ? 1.75 : 1.41;
        const publicPrice = (priceCession * multiplier).toFixed(2);
        document.getElementById('newPublicPrice').value = publicPrice;
    } else {
        document.getElementById('newPublicPrice').value = '';
    }
}

// Hide search results when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-container')) {
        hideSearchResults();
    }
});

// Form validation
document.getElementById('existingProductForm').addEventListener('submit', function(e) {
    if (!selectedProduct) {
        e.preventDefault();
        alert('Veuillez sélectionner un produit existant.');
        return false;
    }
    
    const publicPrice = parseFloat(document.getElementById('existingPublicPrice').value) || 0;
    if (publicPrice <= 0) {
        e.preventDefault();
        alert('Veuillez saisir un prix public valide.');
        return false;
    }
});

document.getElementById('newProductForm').addEventListener('submit', function(e) {
    const publicPrice = parseFloat(document.getElementById('newPublicPrice').value) || 0;
    if (publicPrice <= 0) {
        e.preventDefault();
        alert('Veuillez saisir un prix public valide.');
        return false;
    }
});

// Auto-hide success messages
setTimeout(() => {
    const successAlert = document.querySelector('.alert-success');
    if (successAlert) {
        successAlert.style.opacity = '0';
        setTimeout(() => successAlert.remove(), 300);
    }
}, 5000);

// Sidebar functionality
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }
}

// Close sidebar on overlay click
document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.remove('active');
        this.classList.remove('active');
    }
});
  </script>
</body>
</html>