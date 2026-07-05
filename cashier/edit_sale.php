<?php
session_start();
if($_SESSION["role"] === "CASHIER" && $_SESSION["id"] == session_id()){

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    include '../config/database.php';
    
    if (!isset($db)) {
        throw new Exception('Database connection not found');
    }

    $saleId = isset($_GET['id']) ? $_GET['id'] : 0;
    
    

    $cashierId = $_SESSION['user_id'] ?? $_SESSION['id'];

    // Get sale details with all related information
    $saleQuery = "SELECT s.*, 
                         c.name as clientName
                  FROM sale s 
                  LEFT JOIN client c ON s.clientId = c.id
                  LEFT JOIN user u ON s.sellerId = u.id
                  LEFT JOIN cash_register cr ON s.cash_register_id = cr.id
                  WHERE s.id = ?";
    
    $sale = $db->fetch($saleQuery, [$saleId]);
    
    if (!$sale) {
        throw new Exception('Vente introuvable');
    }

    // Get sale items with product details
    $itemsQuery = "SELECT si.*, 
                          p.name as productName,
                          p.code as productCode,
                          p.stock as currentStock,
                          p.sellingPrice as currentPrice,
                          c.name as categoryName
                   FROM saleitem si
                   JOIN product p ON si.productId = p.id
                   LEFT JOIN category c ON p.categoryId = c.id
                   WHERE si.saleId = ?
                   ORDER BY p.name";
    
    $items = $db->fetchAll($itemsQuery, [$saleId]);
    
    if (!$items) {
        $items = [];
    }

    // Get all products for adding new items
    $productsQuery = "SELECT p.id, p.name, p.code, p.sellingPrice, p.stock, c.name as categoryName
                      FROM product p
                      LEFT JOIN category c ON p.categoryId = c.id
                      WHERE p.stock > 0
                      ORDER BY p.name";
    $allProducts = $db->fetchAll($productsQuery);

    // Get all clients for dropdown
    $clientsQuery = "SELECT id, name, contact FROM client ORDER BY name";
    $clients = $db->fetchAll($clientsQuery);

    // Load pharmacy information
    $settingsQuery = "SELECT setting_key, setting_value FROM app_settings";
    $settingsResult = $db->fetchAll($settingsQuery);
    
    $pharmacyInfo = [];
    foreach ($settingsResult as $setting) {
        $pharmacyInfo[$setting['setting_key']] = $setting['setting_value'];
    }

} catch (Exception $e) {
    error_log('Edit sale error: ' . $e->getMessage());
    die('Erreur: ' . $e->getMessage() . '<br><br><a href="completed-sales.php">Retour</a>');
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Vente - digiPharm</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="../assets/css/admin-dark-theme.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .edit-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .page-title {
            color: var(--ds-green);
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sale-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .info-card {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 0.5rem;
            border: 1px solid var(--ds-border);
        }

        .info-label {
            font-size: 0.875rem;
            color: var(--ds-text-400);
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .info-value {
            color: var(--ds-text-900);
            font-weight: 600;
        }

        .edit-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
        }

        .items-section {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .section-header {
            background: #f8fafc;
            padding: 1.5rem;
            border-bottom: 1px solid var(--ds-border);
        }

        .section-title {
            color: var(--ds-green);
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            background: #f8fafc;
            color: var(--ds-text-900);
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--ds-border);
            font-size: 0.875rem;
        }

        .items-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--ds-surface-alt);
            color: var(--ds-text-900);
        }

        .items-table tbody tr:hover {
            background: var(--ds-surface-alt);
        }

        .product-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .product-name {
            font-weight: 500;
            color: var(--ds-text-900);
        }

        .product-code {
            font-family: 'Courier New', monospace;
            background: var(--ds-surface-alt);
            padding: 0.125rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            display: inline-block;
        }

        .product-category {
            background: #e0f2fe;
            color: #0277bd;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            display: inline-block;
        }

        .quantity-input, .price-input {
            width: 80px;
            padding: 0.5rem;
            border: 1px solid var(--ds-border);
            border-radius: 0.375rem;
            text-align: center;
            font-weight: 600;
        }

        .price-input {
            width: 100px;
        }

        .btn-remove {
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: 0.375rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .btn-remove:hover {
            background: #dc2626;
        }

        .add-product-section {
            padding: 1.5rem;
            background: var(--ds-surface-alt);
            border-top: 1px solid var(--ds-border);
        }

        .product-search {
            position: relative;
            margin-bottom: 1rem;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 2.5rem 0.75rem 1rem;
            border: 2px solid var(--ds-border);
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--ds-green);
        }

        .search-icon {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--ds-text-400);
        }

        .product-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--ds-border);
            border-radius: 0.5rem;
            background: white;
        }

        .product-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--ds-surface-alt);
            cursor: pointer;
            transition: background 0.2s;
        }

        .product-item:hover {
            background: var(--ds-surface-alt);
        }

        .product-item:last-child {
            border-bottom: none;
        }

        .summary-panel {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            height: fit-content;
            position: sticky;
            top: 2rem;
        }

        .panel-header {
            background: var(--ds-green);
            color: white;
            padding: 1.5rem;
            border-radius: 0.75rem 0.75rem 0 0;
        }

        .panel-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0;
        }

        .panel-content {
            padding: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--ds-text-900);
            margin-bottom: 0.5rem;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--ds-border);
            border-radius: 0.375rem;
            font-size: 0.875rem;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--ds-green);
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        .totals-section {
            border: 2px solid var(--ds-surface-alt);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            background: #fafbfc;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
        }

        .total-row:last-child {
            margin-bottom: 0;
            padding-top: 0.75rem;
            border-top: 1px solid var(--ds-border);
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--ds-green);
        }

        .action-buttons {
            display: flex;
            gap: 0.75rem;
        }

        .btn {
            flex: 1;
            padding: 1rem;
            border: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--ds-green);
            color: white;
        }

        .btn-primary:hover {
            background: var(--ds-green);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--ds-text-400);
            color: white;
        }

        .btn-secondary:hover {
            background: var(--ds-text-600);
        }

        .warning-box {
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            gap: 0.75rem;
        }

        .warning-icon {
            color: #f59e0b;
            flex-shrink: 0;
        }

        .warning-text {
            color: #92400e;
            font-size: 0.875rem;
        }

        @media (max-width: 1024px) {
            .edit-grid {
                grid-template-columns: 1fr;
            }

            .summary-panel {
                position: static;
            }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <div id="sidebarOverlay" class="sidebar-overlay"></div>
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <?php include 'header.php'; ?>
            
            <main class="content-area">
                <div class="edit-container">
                    <!-- Page Header -->
                    <div class="page-header">
                        <h1 class="page-title">
                            <i data-lucide="edit"></i>
                            Modifier la vente #<?php echo htmlspecialchars($sale['invoiceNumber']); ?>
                        </h1>
                        
                        <div class="sale-info-grid">
                            <div class="info-card">
                                <div class="info-label">Date de vente</div>
                                <div class="info-value"><?php echo date('d/m/Y à H:i', strtotime($sale['saleDate'])); ?></div>
                            </div>
                          
                        </div>
                    </div>

                    <form id="editSaleForm" onsubmit="submitEditedSale(event)">
                        <input type="hidden" name="saleId" value="<?php echo $saleId; ?>">
                        
                        <div class="edit-grid">
                            <!-- Items Section -->
                            <div class="items-section">
                                <div class="section-header">
                                    <h2 class="section-title">
                                        <i data-lucide="package"></i>
                                        Articles de la vente
                                    </h2>
                                </div>

                                <table class="items-table" id="itemsTable">
                                    <thead>
                                        <tr>
                                            <th>Produit</th>
                                            <th>Prix unitaire</th>
                                            <th>Quantité</th>
                                            <th>Total</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <tr data-item-id="<?php echo $item['id']; ?>" data-product-id="<?php echo $item['productId']; ?>">
                                                <td>
                                                    <div class="product-info">
                                                        <span class="product-name"><?php echo htmlspecialchars($item['productName']); ?></span>
                                                        <div>
                                                            <span class="product-code"><?php echo htmlspecialchars($item['productCode']); ?></span>
                                                            <span class="product-category"><?php echo htmlspecialchars($item['categoryName'] ?: 'Général'); ?></span>
                                                        </div>
                                                        <small style="color: var(--ds-text-400);">Stock disponible: <?php echo $item['currentStock'] + $item['quantity']; ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="number" 
                                                           class="price-input" 
                                                           name="price[]"
                                                           value="<?php echo $item['unitPrice']; ?>"
                                                           min="0"
                                                           step="0.01"
                                                           data-original-price="<?php echo $item['unitPrice']; ?>"
                                                           onchange="updateItemTotal(this)">
                                                    <input type="hidden" name="itemId[]" value="<?php echo $item['id']; ?>">
                                                    <input type="hidden" name="productId[]" value="<?php echo $item['productId']; ?>">
                                                </td>
                                                <td>
                                                    <input type="number" 
                                                           class="quantity-input" 
                                                           name="quantity[]"
                                                           value="<?php echo $item['quantity']; ?>"
                                                           min="1"
                                                           max="<?php echo $item['currentStock'] + $item['quantity']; ?>"
                                                           data-original-qty="<?php echo $item['quantity']; ?>"
                                                           data-max-stock="<?php echo $item['currentStock'] + $item['quantity']; ?>"
                                                           onchange="updateItemTotal(this)">
                                                </td>
                                                <td>
                                                    <span class="price item-total" style="font-weight: 600; color: var(--ds-green);">
                                                        <?php echo number_format($item['quantity'] * $item['unitPrice'], 0); ?> XAF
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn-remove" onclick="removeExistingItem(this)">
                                                        <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <!-- Add New Product Section -->
                                <div class="add-product-section">
                                    <h3 style="color: var(--ds-green); margin-bottom: 1rem; font-size: 1rem;">
                                        <i data-lucide="plus-circle" style="width: 16px; height: 16px;"></i>
                                        Ajouter un produit
                                    </h3>
                                    
                                    <div class="product-search">
                                        <input type="text" 
                                               class="search-input" 
                                               id="productSearch" 
                                               placeholder="Rechercher un produit..."
                                               onkeyup="filterProducts()">
                                        <i data-lucide="search" class="search-icon" style="width: 18px; height: 18px;"></i>
                                    </div>

                                    <div class="product-list" id="productList">
                                        <?php foreach ($allProducts as $product): ?>
                                            <div class="product-item" 
                                                 data-product-id="<?php echo $product['id']; ?>"
                                                 data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                 data-product-code="<?php echo htmlspecialchars($product['code']); ?>"
                                                 data-product-price="<?php echo $product['sellingPrice']; ?>"
                                                 data-product-stock="<?php echo $product['stock']; ?>"
                                                 data-product-category="<?php echo htmlspecialchars($product['categoryName'] ?: 'Général'); ?>"
                                                 onclick="addNewProduct(this)">
                                                <div class="product-info">
                                                    <span class="product-name"><?php echo htmlspecialchars($product['name']); ?></span>
                                                    <div>
                                                        <span class="product-code"><?php echo htmlspecialchars($product['code']); ?></span>
                                                        <span class="product-category"><?php echo htmlspecialchars($product['categoryName'] ?: 'Général'); ?></span>
                                                    </div>
                                                    <small style="color: var(--ds-text-400);">
                                                        Prix: <?php echo number_format($product['sellingPrice'], 0); ?> XAF | 
                                                        Stock: <?php echo $product['stock']; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Summary Panel -->
                            <div class="summary-panel">
                                <div class="panel-header">
                                    <h3 class="panel-title">Résumé de la vente</h3>
                                </div>
                                
                                <div class="panel-content">
                                    <div class="warning-box">
                                        <i data-lucide="alert-triangle" class="warning-icon" style="width: 20px; height: 20px;"></i>
                                        <div class="warning-text">
                                            <strong>Attention:</strong> La modification d'une vente affectera le stock et les statistiques.
                                        </div>
                                    </div>

                                    <!-- Client Selection -->
                                    <div class="form-group">
                                        <label class="form-label">Client</label>
                                        <select class="form-select" name="clientId">
                                            <option value="">Client anonyme</option>
                                            <?php foreach ($clients as $client): ?>
                                                <option value="<?php echo $client['id']; ?>"
                                                        <?php echo ($sale['clientId'] == $client['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($client['name']); ?>
                                                    <?php if ($client['contact']): ?>
                                                        - <?php echo htmlspecialchars($client['contact']); ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Discount -->
                                    <div class="form-group">
                                        <label class="form-label">Remise</label>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <input type="number" 
                                                   class="form-input" 
                                                   name="discountAmount" 
                                                   value="<?php echo $sale['discountAmount']; ?>"
                                                   min="0"
                                                   step="0.01"
                                                   onchange="updateTotals()"
                                                   style="flex: 1;">
                                            <span style="padding: 0.75rem; background: var(--ds-surface-alt); border-radius: 0.375rem;">XAF</span>
                                        </div>
                                    </div>

                                    <!-- Totals -->
                                    <div class="totals-section">
                                       
                                        <div class="total-row" id="discountRow" style="<?php echo $sale['discountAmount'] > 0 ? '' : 'display: none;'; ?>">
                                            <span>Remise:</span>
                                            <span id="discount">-<?php echo number_format($sale['discountAmount'], 0); ?> XAF</span>
                                        </div>
                                        <div class="total-row">
                                            <span>Total:</span>
                                            <span id="total"><?php echo number_format($sale['totalAmount'], 0); ?> XAF</span>
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="action-buttons">
                                        <button type="button" class="btn btn-secondary" onclick="window.location.href='completed-sales.php'">
                                            <i data-lucide="x" style="width: 18px; height: 18px;"></i>
                                            Annuler
                                        </button>
                                        <button type="submit" class="btn btn-primary">
                                            <i data-lucide="save" style="width: 18px; height: 18px;"></i>
                                            Enregistrer
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
<script>
let newItemCounter = 0;

function updateItemTotal(input) {
    const row = input.closest('tr');
    const priceInput = row.querySelector('.price-input');
    const qtyInput = row.querySelector('.quantity-input');
    const totalSpan = row.querySelector('.item-total');
    
    const price = parseFloat(priceInput.value) || 0;
    const qty = parseInt(qtyInput.value) || 0;
    const total = price * qty;
    
    totalSpan.textContent = total.toLocaleString() + ' XAF';
    updateTotals();
}

function updateTotals() {
    let subtotal = 0;
    
    document.querySelectorAll('#itemsTable tbody tr').forEach(row => {
        const priceInput = row.querySelector('.price-input');
        const qtyInput = row.querySelector('.quantity-input');
        
        if (priceInput && qtyInput) {
            const price = parseFloat(priceInput.value) || 0;
            const qty = parseInt(qtyInput.value) || 0;
            subtotal += (price * qty);
        }
    });
    
    const discountInput = document.querySelector('input[name="discountAmount"]');
    const discount = parseFloat(discountInput.value) || 0;
    const total = subtotal - discount;
    
    // Update subtotal if element exists
    const subtotalElement = document.getElementById('subtotal');
    if (subtotalElement) {
        subtotalElement.textContent = subtotal.toLocaleString() + ' XAF';
    }
    
    document.getElementById('discount').textContent = '-' + discount.toLocaleString() + ' XAF';
    document.getElementById('total').textContent = total.toLocaleString() + ' XAF';
    
    const discountRow = document.getElementById('discountRow');
    discountRow.style.display = discount > 0 ? 'flex' : 'none';
}

function removeExistingItem(button) {
    if (confirm('Êtes-vous sûr de vouloir retirer cet article?')) {
        const row = button.closest('tr');
        row.remove();
        updateTotals();
        
        // Check if table is empty
        const remainingRows = document.querySelectorAll('#itemsTable tbody tr').length;
        if (remainingRows === 0) {
            alert('Une vente doit contenir au moins un article!');
            window.location.reload();
        }
    }
}

function filterProducts() {
    const searchValue = document.getElementById('productSearch').value.toLowerCase();
    const products = document.querySelectorAll('.product-item');
    
    products.forEach(product => {
        const name = product.dataset.productName.toLowerCase();
        const code = product.dataset.productCode.toLowerCase();
        
        if (name.includes(searchValue) || code.includes(searchValue)) {
            product.style.display = 'block';
        } else {
            product.style.display = 'none';
        }
    });
}

function addNewProduct(element) {
    const productId = element.dataset.productId;
    
    // Check if product already exists in table
    const existingRow = document.querySelector(`tr[data-product-id="${productId}"]`);
    if (existingRow) {
        alert('Ce produit est déjà dans la liste!');
        const qtyInput = existingRow.querySelector('.quantity-input');
        qtyInput.focus();
        return;
    }
    
    const name = element.dataset.productName;
    const code = element.dataset.productCode;
    const price = element.dataset.productPrice;
    const stock = element.dataset.productStock;
    const category = element.dataset.productCategory;
    
    const tbody = document.querySelector('#itemsTable tbody');
    const newRow = document.createElement('tr');
    newRow.dataset.productId = productId;
    newRow.dataset.newItem = 'true';
    
    newRow.innerHTML = `
        <td>
            <div class="product-info">
                <span class="product-name">${name}</span>
                <div>
                    <span class="product-code">${code}</span>
                    <span class="product-category">${category}</span>
                </div>
                <small style="color: var(--ds-text-400);">Stock disponible: ${stock}</small>
            </div>
        </td>
        <td>
            <input type="number" 
                   class="price-input" 
                   name="newPrice[]"
                   value="${price}"
                   min="0"
                   step="0.01"
                   onchange="updateItemTotal(this)">
            <input type="hidden" name="newProductId[]" value="${productId}">
        </td>
        <td>
            <input type="number" 
                   class="quantity-input" 
                   name="newQuantity[]"
                   value="1"
                   min="1"
                   max="${stock}"
                   onchange="updateItemTotal(this)">
        </td>
        <td>
            <span class="price item-total" style="font-weight: 600; color: var(--ds-green);">
                ${parseInt(price).toLocaleString()} XAF
            </span>
        </td>
        <td>
            <button type="button" class="btn-remove" onclick="removeExistingItem(this)">
                <i data-lucide="trash-2" style="width: 16px; height: 16px;"></i>
            </button>
        </td>
    `;
    
    tbody.appendChild(newRow);
    
    // Reinitialize Lucide icons for new elements
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    updateTotals();
    
    // Clear search
    document.getElementById('productSearch').value = '';
    filterProducts();
    
    // Focus on quantity input
    const qtyInput = newRow.querySelector('.quantity-input');
    qtyInput.focus();
    qtyInput.select();
}

function submitEditedSale(event) {
    event.preventDefault();
    
    // Validate that we have at least one item
    const itemCount = document.querySelectorAll('#itemsTable tbody tr').length;
    if (itemCount === 0) {
        alert('La vente doit contenir au moins un article!');
        return;
    }
    
    // Confirm submission
    if (!confirm('Êtes-vous sûr de vouloir enregistrer ces modifications? Cette action modifiera le stock et les statistiques.')) {
        return;
    }
    
    const formData = new FormData(event.target);
    const submitBtn = event.target.querySelector('button[type="submit"]');
    
    // Disable button and show loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span style="display: inline-block; animation: spin 1s linear infinite;">⟳</span> Enregistrement...';
    
    fetch('update-sale.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Vente mise à jour avec succès!');
            window.location.href = 'completed-sales.php';
        } else {
            alert('Erreur: ' + data.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i data-lucide="save" style="width: 18px; height: 18px;"></i> Enregistrer';
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Erreur de connexion. Veuillez réessayer.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i data-lucide="save" style="width: 18px; height: 18px;"></i> Enregistrer';
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
}

function setupSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const menuToggle = document.getElementById('menuToggle');
    const sidebarClose = document.getElementById('sidebarClose');

    function showSidebar() {
        if (sidebar) sidebar.classList.add('show');
        if (overlay) overlay.classList.add('show');
    }

    function hideSidebar() {
        if (sidebar) sidebar.classList.remove('show');
        if (overlay) overlay.classList.remove('show');
    }

    if (menuToggle) menuToggle.addEventListener('click', showSidebar);
    if (sidebarClose) sidebarClose.addEventListener('click', hideSidebar);
    if (overlay) overlay.addEventListener('click', hideSidebar);
}

function setFavicon() {
    const svgData = `
        <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
            <path d="M60 20 L140 20 L140 60 L180 60 L180 140 L140 140 L140 180 L60 180 L60 140 L20 140 L20 60 L60 60 Z" fill="var(--ds-green)"/>
            <path d="M75 35 L125 35 L125 75 L165 75 L165 125 L125 125 L125 165 L75 165 L75 125 L35 125 L35 75 L75 75 Z" fill="white"/>
            <g fill="var(--ds-green)">
                <rect x="97" y="50" width="6" height="100"/>
                <rect x="50" y="97" width="100" height="6"/>
            </g>
        </svg>
    `;
    
    const favicon = `data:image/svg+xml;base64,${btoa(svgData)}`;
    
    const existingFavicon = document.querySelector('link[rel="icon"]') || document.querySelector('link[rel="shortcut icon"]');
    if (existingFavicon) {
        existingFavicon.remove();
    }
    
    const link = document.createElement('link');
    link.rel = 'icon';
    link.type = 'image/svg+xml';
    link.href = favicon;
    document.head.appendChild(link);
}

// Initialize everything when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded');
    
    setFavicon();
    setupSidebar();
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
        console.log('Lucide icons initialized successfully');
    } else {
        console.error('Lucide library not loaded! Check if the CDN link is working.');
    }
});

// Add spinning animation for loader
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);
</script>
</body>
</html>

<?php }else{
    header("location: ../logout.php");
}?>