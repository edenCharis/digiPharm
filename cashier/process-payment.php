<?php
session_start();
if($_SESSION["role"] === "CASHIER" && $_SESSION["id"] == session_id()){

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    include '../config/database.php';
    
    if (!isset($db)) {
        throw new Exception('Database connection not found');
    }

    $cartId = isset($_GET['cartId']) ? (int)$_GET['cartId'] : null;
    $cart = null;
    $cartItems = [];
    $client = null;
    $seller = null;

    // If cartId is provided, load cart details
    if ($cartId) {
        // Get cart details
        $cartQuery = "SELECT c.*, cl.name as clientName, cl.contact as clientPhone, 
                             sel.username as sellerName
                      FROM carts c
                      LEFT JOIN client cl ON c.client_id = cl.id
                      LEFT JOIN user sel ON c.seller_id = sel.id
                      WHERE c.id = ? AND c.status = 'pending'";
        $cart = $db->fetch($cartQuery, [$cartId]);

        if ($cart) {
            // Get cart items with product details
            $itemsQuery = "SELECT ci.*, p.name as productName, p.code, p.sellingPrice,
                                  c.name as category_name, p.stock
                           FROM cart_items ci
                           JOIN product p ON ci.product_id = p.id
                           JOIN category c ON p.categoryId = c.id
                           WHERE ci.cart_id = ?
                           ORDER BY p.name";
            $cartItems = $db->fetchAll($itemsQuery, [$cartId]);
            
            if (!$cartItems) $cartItems = [];
        }
    }

    // Get all pending carts for dropdown
    $pendingCartsQuery = "SELECT c.id, c.created_at, sel.username as sellerName, 
                                 cl.name as clientName, COUNT(ci.id) as itemCount
                          FROM carts c
                          LEFT JOIN user sel ON c.seller_id = sel.id
                          LEFT JOIN client cl ON c.client_id = cl.id
                          LEFT JOIN cart_items ci ON c.id = ci.cart_id
                          WHERE c.status = 'pending'
                          GROUP BY c.id
                          ORDER BY c.created_at ASC";
    $pendingCarts = $db->fetchAll($pendingCartsQuery);
    if (!$pendingCarts) $pendingCarts = [];

    // Calculate totals (prices already include VAT)
    $totalAmount = 0;
    $totalItems = 0;

    foreach ($cartItems as $item) {
        $itemTotal = $item['quantity'] * $item['sellingPrice'];
        $totalAmount += $itemTotal;
        $totalItems += $item['quantity'];
    }

    // Generate invoice number
    function generateInvoiceNumber() {
        return 'FAC-' . date('Y') . '-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    // Load pharmacy information from settings table
    $settingsQuery = "SELECT setting_key, setting_value FROM app_settings";
    $settingsResult = $db->fetchAll($settingsQuery);
    
    $pharmacyInfo = [];
    foreach ($settingsResult as $setting) {
        $pharmacyInfo[$setting['setting_key']] = $setting['setting_value'];
    }
    
    // Get cashier information
    $cashierId = $_SESSION['user_id'] ?? $_SESSION['id'];
    $cashierQuery = "SELECT username FROM user WHERE id = ?";
    $cashierData = $db->fetch($cashierQuery, [$cashierId]);
    $cashierName = $cashierData['username'];

} catch (Exception $e) {
    error_log('Process payment error: ' . $e->getMessage());
    die('Error loading page: ' . $e->getMessage() . '<br><br><a href="index.php">Return to Dashboard</a>');
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys - Traitement Paiement</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/umd/lucide.js"></script>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/seller-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    <style>
        .payment-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .cart-selection {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .cart-dropdown {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 1rem;
            background: white;
        }

        .cart-dropdown:focus {
            outline: none;
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        .cart-details {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .cart-header {
            background: #f8fafc;
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .cart-title {
            color: #059669;
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .cart-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .info-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }

        .info-value {
            color: #111827;
            font-weight: 600;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            background: #f8fafc;
            color: #374151;
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.875rem;
        }

        .items-table td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
        }

        .items-table tbody tr:hover {
            background: #f9fafb;
        }

        .product-code {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .product-name {
            font-weight: 500;
            color: #111827;
            margin-bottom: 0.25rem;
        }

        .product-category {
            background: #e0f2fe;
            color: #0277bd;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .quantity-input {
            width: 80px;
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            text-align: center;
            font-weight: 600;
        }

        .price {
            font-weight: 600;
            color: #059669;
        }

        .payment-panel {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            height: fit-content;
            position: sticky;
            top: 2rem;
        }

        .panel-header {
            background: #059669;
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

        .totals-section {
            border: 2px solid #f3f4f6;
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
            border-top: 1px solid #e5e7eb;
            font-size: 1.125rem;
            font-weight: 700;
            color: #059669;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        .process-btn {
            width: 100%;
            background: #10b981;
            color: white;
            border: none;
            padding: 1rem;
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

        .process-btn:hover {
            background: #059669;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .process-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .discount-section {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .empty-cart {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }

        .empty-cart i {
            width: 4rem;
            height: 4rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }

        /* Invoice Modal Styles */
        .invoice-modal {
            display: none;
            position: fixed;
            z-index: 1100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            align-items: center;
            justify-content: center;
        }

        .invoice-modal.show {
            display: flex;
        }

        .invoice-content {
            background: white;
            max-width: 400px;
            width: 90%;
            max-height: 90%;
            overflow-y: auto;
            border-radius: 8px;
            position: relative;
        }

        .invoice-header {
            background: #059669;
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 8px 8px 0 0;
        }

        .invoice-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .receipt-container {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.3;
            color: #000;
            padding: 15px;
        }

        .pharmacy-header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #000;
        }

        .pharmacy-name {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 3px;
        }

        .pharmacy-info {
            font-size: 11px;
            margin-bottom: 2px;
        }

        .sale-info {
            margin: 10px 0;
            font-size: 12px;
            display: flex;
            justify-content: space-between;
        }

        .items-header {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            border-bottom: 1px solid #000;
            padding: 3px 0;
            margin-bottom: 8px;
            font-size: 11px;
        }

        .item {
            margin-bottom: 8px;
            font-size: 11px;
        }

        .item-name {
            margin-bottom: 2px;
            font-weight: normal;
        }

        .item-calc {
            display: flex;
            justify-content: space-between;
            margin-left: 20px;
            font-family: 'Courier New', monospace;
        }

        .invoice-totals {
            border-top: 1px solid #000;
            padding-top: 8px;
            margin-top: 15px;
        }

        .invoice-total-line {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .payment-info {
            margin: 10px 0;
            font-size: 12px;
        }

        .payment-line {
            margin-bottom: 2px;
        }

        .invoice-footer {
            text-align: center;
            margin-top: 15px;
            border-top: 1px solid #000;
            padding-top: 10px;
            font-size: 10px;
        }

        .invoice-actions {
            padding: 15px;
            background: #f8f9fa;
            border-top: 1px solid #e5e7eb;
            display: flex;
            gap: 10px;
        }

        .invoice-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-print {
            background: #059669;
            color: white;
        }

        .btn-close {
            background: #6b7280;
            color: white;
        }

        @media (max-width: 1024px) {
            .payment-container {
                grid-template-columns: 1fr;
            }

            .payment-panel {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .cart-info {
                grid-template-columns: 1fr;
            }

            .items-table {
                font-size: 0.75rem;
            }

            .items-table th, .items-table td {
                padding: 0.5rem;
            }
        }
        
        
        .btn-remove-item {
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
    width: 36px;
    height: 36px;
}

.btn-remove-item:hover {
    background: #dc2626;
    transform: scale(1.1);
}

.btn-delete-cart {
    background: #dc2626;
    color: white;
    border: none;
    padding: 0.625rem 1.25rem;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
}

.btn-delete-cart:hover {
    background: #b91c1c;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}

.cart-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

.confirm-modal {
    display: none;
    position: fixed;
    z-index: 1200;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    align-items: center;
    justify-content: center;
}

.confirm-modal.show {
    display: flex;
}

.confirm-content {
    background: white;
    max-width: 400px;
    width: 90%;
    border-radius: 8px;
    overflow: hidden;
}

.confirm-header {
    background: #dc2626;
    color: white;
    padding: 1rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.confirm-body {
    padding: 1.5rem;
}

.confirm-actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
}

.confirm-btn {
    flex: 1;
    padding: 0.75rem;
    border: none;
    border-radius: 0.375rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-confirm-delete {
    background: #dc2626;
    color: white;
}

.btn-confirm-delete:hover {
    background: #b91c1c;
}

.btn-cancel {
    background: #6b7280;
    color: white;
}

.btn-cancel:hover {
    background: #4b5563;
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
            
            <!-- Content Area -->
            <main class="content-area">
                <!-- Cart Selection -->
                <div class="cart-selection">
                    <h2 style="color: #059669; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i data-lucide="shopping-cart"></i>
                        Sélectionner un panier
                    </h2>
                    <select class="cart-dropdown" onchange="loadCart(this.value)">
                        <option value="">-- Choisir un panier en attente --</option>
                        <?php foreach ($pendingCarts as $pendingCart): ?>
                            <option value="<?php echo $pendingCart['id']; ?>" 
                                    <?php echo ($cartId == $pendingCart['id']) ? 'selected' : ''; ?>>
                                Panier #<?php echo $pendingCart['id']; ?> - 
                                <?php echo htmlspecialchars($pendingCart['sellerName']); ?> - 
                                <?php echo htmlspecialchars($pendingCart['clientName'] ?: 'Client anonyme'); ?> - 
                                <?php echo $pendingCart['itemCount']; ?> article(s) - 
                                <?php echo date('H:i', strtotime($pendingCart['created_at'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($cart && !empty($cartItems)): ?>
                    <!-- Payment Processing Container -->
                    <div class="payment-container">
                        <!-- Cart Details -->
                        <div class="cart-details">
                            <div class="cart-header">
                                <h2 class="cart-title">
                                    <i data-lucide="receipt"></i>
                                    Panier #<?php echo $cart['id']; ?>
                                </h2>
                                

                                
                                <div class="cart-info">
                                    <div class="info-item">
                                        <span class="info-label">Vendeur</span>
                                        <span class="info-value"><?php echo htmlspecialchars($cart['sellerName']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Client</span>
                                        <span class="info-value"><?php echo htmlspecialchars($cart['clientName'] ?: 'Client anonyme'); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Créé le</span>
                                        <span class="info-value"><?php echo date('d/m/Y à H:i', strtotime($cart['created_at'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Articles</span>
                                        <span class="info-value"><?php echo $totalItems; ?> unité(s)</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Items Table -->
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Produit</th>
                                        <th>Prix unitaire TTC</th>
                                        <th>Quantité</th>
                                        <th>Total TTC</th>
                                           
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cartItems as $item): ?>
                                        <?php 
                                        $itemTotal = $item['quantity'] * $item['sellingPrice'];
                                        ?>
                                        <tr>
                                            <td>
                                                <span class="product-code"><?php echo htmlspecialchars($item['code']); ?></span>
                                            </td>
                                            <td>
                                                <div class="product-name"><?php echo htmlspecialchars($item['productName']); ?></div>
                                                <span class="product-category"><?php echo htmlspecialchars($item['category_name'] ?: 'Général'); ?></span>
                                            </td>
                                            <td>
                                                <span class="price"><?php echo number_format($item['sellingPrice'], 0); ?> XAF</span>
                                            </td>
                                            <td>
                                                <input type="number" 
                                                       class="quantity-input" 
                                                       value="<?php echo $item['quantity']; ?>" 
                                                       min="1" 
                                                       max="<?php echo $item['stock']; ?>"
                                                       data-item-id="<?php echo $item['id']; ?>"
                                                       data-price="<?php echo $item['sellingPrice']; ?>"
                                                       onchange="updateQuantity(this)">
                                            </td>
                                            <td>
                                                <span class="price item-total"><?php echo number_format($itemTotal, 0); ?> XAF</span>
                                            </td>
        
                                            
                                            
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Payment Panel -->
                        <div class="payment-panel">
                            <div class="panel-header">
                                <h3 class="panel-title">Finalisation de la vente</h3>
                            </div>
                            
                            <div class="panel-content">
                                <form id="paymentForm" onsubmit="processPayment(event)">
                                    <input type="hidden" name="cartId" value="<?php echo $cartId; ?>">
                                    <input type="hidden" name="invoiceNumber" value="<?php echo generateInvoiceNumber(); ?>">
                                    <input type="hidden" name="paymentMethod" value="cash">

                                    <!-- Totals Section -->
                                    <div class="totals-section">
                                        <div class="total-row">
                                            <span>Sous-total:</span>
                                            <span class="subtotal-display"><?php echo number_format($totalAmount, 0); ?> XAF</span>
                                        </div>
                                        <div class="total-row discount-row" style="display: none;">
                                            <span>Remise:</span>
                                            <span class="discount-display">0 XAF</span>
                                        </div>
                                        <div class="total-row">
                                            <span>Total à payer:</span>
                                            <span class="total-display" id="finalTotal"><?php echo number_format($totalAmount, 0); ?> XAF</span>
                                        </div>
                                    </div>

                                    <!-- Discount Section -->
                                    <div class="discount-section">
                                        <div class="form-group">
                                            <label class="form-label">Remise (optionnel)</label>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <input type="number" class="form-input" name="discountAmount" 
                                                       placeholder="0.00" step="0.01" min="0" 
                                                       onchange="updateDiscount(this.value)" style="flex: 1;">
                                                <select class="form-select" style="width: 100px;" onchange="updateDiscountType(this.value)">
                                                    <option value="amount">XAF</option>
                                                    <option value="percent">%</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Cash Payment Details -->
                                    <div class="form-group">
                                        <label class="form-label">Montant reçu</label>
                                        <input type="number" class="form-input" id="cashReceived" name="cashReceived" 
                                               step="0.01" min="0" onchange="calculateChange(this.value)">
                                        <div id="changeAmount" style="margin-top: 0.5rem; font-weight: 600; color: #059669;"></div>
                                    </div>

                                    <!-- Process Button -->
                                    <button type="submit" class="process-btn">
                                        <i data-lucide="check-circle"></i>
                                        Finaliser la vente
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                <?php elseif (empty($pendingCarts)): ?>
                    <div class="empty-cart">
                        <i data-lucide="shopping-cart"></i>
                        <h3>Aucun panier en attente</h3>
                        <p>Tous les paniers ont été traités ou il n'y en a aucun.</p>
                        <a href="index.php" style="color: #059669; text-decoration: none; font-weight: 500;">
                            ← Retour au tableau de bord
                        </a>
                    </div>

                <?php else: ?>
                    <div class="empty-cart">
                        <i data-lucide="package"></i>
                        <h3>Sélectionnez un panier</h3>
                        <p>Choisissez un panier dans la liste ci-dessus pour commencer le traitement.</p>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Invoice Modal -->
    <div id="invoiceModal" class="invoice-modal">
        <div class="invoice-content">
            <div class="invoice-header">
                <h3>Ticket de vente</h3>
                <button class="invoice-close" onclick="closeInvoiceModal()">×</button>
            </div>
            <div id="invoiceBody" class="receipt-container">
                <!-- Invoice content will be inserted here -->
            </div>
            <div class="invoice-actions">
                <button class="invoice-btn btn-print" onclick="printInvoice()">
                    <i data-lucide="printer"></i> Imprimer
                </button>
                <button class="invoice-btn btn-close" onclick="closeInvoiceModal()">Fermer</button>
            </div>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
<div id="confirmModal" class="confirm-modal">
    <div class="confirm-content">
        <div class="confirm-header">
            <i data-lucide="alert-triangle" style="width: 24px; height: 24px;"></i>
            <h3 style="margin: 0;">Confirmation</h3>
        </div>
        <div class="confirm-body">
            <p id="confirmMessage" style="margin: 0 0 1rem 0;"></p>
            <div class="confirm-actions">
                <button class="confirm-btn btn-cancel" onclick="closeConfirmModal()">Annuler</button>
                <button class="confirm-btn btn-confirm-delete" id="confirmButton">Confirmer</button>
            </div>
        </div>
    </div>
</div>

    <script>
        let originalSubtotal = <?php echo $totalAmount; ?>;
        let discountType = 'amount';
        let currentSaleData = {};

        function loadCart(cartId) {
            if (cartId) {
                window.location.href = 'process-payment.php?cartId=' + cartId;
            }
        }

        function updateQuantity(input) {
            const quantity = parseInt(input.value);
            const price = parseFloat(input.dataset.price);
            const row = input.closest('tr');
            const totalCell = row.querySelector('.item-total');
            
            const newTotal = quantity * price;
            totalCell.textContent = newTotal.toLocaleString() + ' XAF';
            
            updateTotals();
        }

        function updateTotals() {
            let subtotal = 0;
            
            document.querySelectorAll('.items-table tbody tr').forEach(row => {
                const quantity = parseInt(row.querySelector('.quantity-input').value);
                const price = parseFloat(row.querySelector('.quantity-input').dataset.price);
                
                const itemTotal = quantity * price;
                subtotal += itemTotal;
            });
            
            originalSubtotal = subtotal;
            
            // Apply discount
            const discountAmount = calculateDiscountAmount(subtotal);
            const finalTotal = subtotal - discountAmount;
            
            // Update display
            document.querySelector('.subtotal-display').textContent = subtotal.toLocaleString() + ' XAF';
            document.querySelector('.total-display').textContent = finalTotal.toLocaleString() + ' XAF';
            
            if (discountAmount > 0) {
                document.querySelector('.discount-row').style.display = 'flex';
                document.querySelector('.discount-display').textContent = discountAmount.toLocaleString() + ' XAF';
            } else {
                document.querySelector('.discount-row').style.display = 'none';
            }
            
            // Recalculate change if cash received is entered
            const cashReceived = parseFloat(document.getElementById('cashReceived').value) || 0;
            if (cashReceived > 0) {
                calculateChange(cashReceived);
            }
        }

        function calculateDiscountAmount(subtotal) {
            const discountInput = document.querySelector('input[name="discountAmount"]');
            const discountValue = parseFloat(discountInput.value) || 0;
            
            if (discountType === 'percent') {
                return (subtotal * discountValue) / 100;
            } else {
                return discountValue;
            }
        }

        function updateDiscount(value) {
            updateTotals();
        }

        function updateDiscountType(type) {
            discountType = type;
            updateTotals();
        }

        function calculateChange(received) {
            const finalTotalText = document.querySelector('.total-display').textContent;
            const totalAmount = parseFloat(finalTotalText.replace(/[^0-9.-]+/g,""));
            const change = received - totalAmount;
            const changeDiv = document.getElementById('changeAmount');
            
            if (change > 0) {
                changeDiv.textContent = `Rendu: ${change.toLocaleString()} XAF`;
                changeDiv.style.color = '#059669';
            } else if (change < 0) {
                changeDiv.textContent = `Manque: ${Math.abs(change).toLocaleString()} XAF`;
                changeDiv.style.color = '#dc2626';
            } else {
                changeDiv.textContent = 'Montant exact';
                changeDiv.style.color = '#059669';
            }
        }

        function processPayment(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const button = event.target.querySelector('button[type="submit"]');
            
            // Validate cash received
            const cashReceived = parseFloat(document.getElementById('cashReceived').value) || 0;
            const finalTotalText = document.querySelector('.total-display').textContent;
            const totalAmount = parseFloat(finalTotalText.replace(/[^0-9.-]+/g,""));
            
            if (cashReceived < totalAmount) {
                showErrorModal('Le montant reçu doit être supérieur ou égal au total à payer.');
                return;
            }
            
            // Disable button and show loading
            button.disabled = true;
            button.innerHTML = '<i data-lucide="loader-2"></i> Traitement...';
            
            // Collect updated quantities
            const updatedItems = [];
            document.querySelectorAll('.quantity-input').forEach(input => {
                updatedItems.push({
                    itemId: input.dataset.itemId,
                    quantity: input.value
                });
            });
            formData.append('updatedItems', JSON.stringify(updatedItems));
            
            // Add calculated totals
            const subtotal = originalSubtotal;
            const discountAmount = calculateDiscountAmount(subtotal);
            const finalTotal = subtotal - discountAmount;
            
            formData.append('subtotal', subtotal);
            formData.append('totalVAT', 0);
            formData.append('discountAmount', discountAmount);
            formData.append('totalAmount', finalTotal);
            formData.append('cashReceived', cashReceived);
            
            // Store sale data for invoice
            currentSaleData = {
                subtotal: subtotal,
                discountAmount: discountAmount,
                totalAmount: finalTotal,
                cashReceived: cashReceived,
                change: cashReceived - finalTotal,
                items: []
            };
            
            // Collect items data
            document.querySelectorAll('.items-table tbody tr').forEach(row => {
                const code = row.querySelector('.product-code').textContent;
                const name = row.querySelector('.product-name').textContent;
                const quantity = parseInt(row.querySelector('.quantity-input').value);
                const price = parseFloat(row.querySelector('.quantity-input').dataset.price);
                const total = quantity * price;
                
                currentSaleData.items.push({
                    code: code,
                    name: name,
                    quantity: quantity,
                    unitPrice: price,
                    total: total
                });
            });
            
            // Send to server
            fetch('process-payment-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Add invoice number to sale data
                    currentSaleData.invoiceNumber = data.invoiceNumber;
                    currentSaleData.saleDate = new Date().toLocaleDateString('fr-FR');
                    currentSaleData.saleTime = new Date().toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'});
                    
                    // Show invoice modal
                    showInvoiceModal();
                } else {
                    showErrorModal('Erreur: ' + data.message);
                    button.disabled = false;
                    button.innerHTML = '<i data-lucide="check-circle"></i> Finaliser la vente';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorModal('Erreur de traitement. Veuillez réessayer.');
                button.disabled = false;
                button.innerHTML = '<i data-lucide="check-circle"></i> Finaliser la vente';
            });
        }

        function showInvoiceModal() {
            const modal = document.getElementById('invoiceModal');
            const invoiceBody = document.getElementById('invoiceBody');
            
            // Generate invoice HTML
            const invoiceHTML = generateInvoiceHTML();
            invoiceBody.innerHTML = invoiceHTML;
            
            modal.classList.add('show');
            
            // Initialize Lucide icons in modal
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        function closeInvoiceModal() {
            const modal = document.getElementById('invoiceModal');
            modal.classList.remove('show');
            
            // Redirect to dashboard after closing
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 300);
        }

        function printInvoice() {
            const invoiceContent = document.getElementById('invoiceBody').innerHTML;
            const printWindow = window.open('', '_blank');
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Ticket N° ${currentSaleData.invoiceNumber}</title>
                    <meta charset="UTF-8">
                    <style>
                        @page { 
                            size: A4;
                            margin: 0;
                        }
                        
                        body { 
                            font-family: 'Courier New', monospace; 
                            font-size: 12px; 
                            line-height: 1.2; 
                            margin: 0; 
                            padding: 20mm 10mm;
                            color: #000;
                            background: white;
                            display: flex;
                            justify-content: center;
                            align-items: flex-start;
                        }
                        
                        .receipt-wrapper {
                            width: 80mm;
                            background: white;
                            border: 1px solid #ccc;
                            padding: 5mm;
                            box-shadow: 0 0 10px rgba(0,0,0,0.1);
                        }
                        
                        .pharmacy-header { 
                            text-align: center; 
                            margin-bottom: 8px; 
                            padding-bottom: 5px; 
                            border-bottom: 1px dashed #000; 
                        }
                        
                        .pharmacy-name { 
                            font-weight: bold; 
                            font-size: 13px; 
                            margin-bottom: 2px; 
                        }
                        
                        .pharmacy-info { 
                            font-size: 10px; 
                            margin-bottom: 1px; 
                        }
                        
                        .sale-info { 
                            margin: 5px 0; 
                            font-size: 11px; 
                            display: flex; 
                            justify-content: space-between; 
                            border-bottom: 1px dashed #000;
                            padding-bottom: 3px;
                        }
                        
                        .client-info {
                            font-size: 10px;
                            margin: 3px 0;
                            text-align: left;
                        }
                        
                        .items-header { 
                            display: flex; 
                            justify-content: space-between; 
                            font-weight: bold; 
                            border-bottom: 1px solid #000; 
                            padding: 2px 0; 
                            margin: 5px 0 3px 0; 
                            font-size: 10px; 
                        }
                        
                        .item { 
                            margin-bottom: 4px; 
                            font-size: 10px; 
                            border-bottom: 1px dotted #ccc;
                            padding-bottom: 2px;
                        }
                        
                        .item:last-child {
                            border-bottom: none;
                        }
                        
                        .item-name { 
                            margin-bottom: 1px; 
                            font-weight: normal; 
                            text-transform: uppercase;
                        }
                        
                        .item-calc { 
                            display: flex; 
                            justify-content: space-between; 
                            margin-left: 15px; 
                            font-size: 9px;
                        }
                        
                        .invoice-totals { 
                            border-top: 1px solid #000; 
                            padding-top: 5px; 
                            margin-top: 8px; 
                        }
                        
                        .invoice-total-line { 
                            display: flex; 
                            justify-content: space-between; 
                            font-weight: bold; 
                            margin-bottom: 2px; 
                            font-size: 11px;
                        }
                        
                        .payment-info { 
                            margin: 8px 0; 
                            font-size: 11px; 
                            border-top: 1px dashed #000;
                            padding-top: 5px;
                        }
                        
                        .payment-line { 
                            margin-bottom: 1px; 
                            display: flex;
                            justify-content: space-between;
                        }
                        
                        .invoice-footer { 
                            text-align: center; 
                            margin-top: 10px; 
                            border-top: 1px dashed #000; 
                            padding-top: 5px; 
                            font-size: 9px; 
                        }
                        
                        @media print {
                            body { 
                                padding: 0;
                                margin: 0;
                                display: block;
                            }
                            
                            .receipt-wrapper {
                                width: 100%;
                                max-width: 80mm;
                                margin: 0 auto;
                                border: none;
                                box-shadow: none;
                                padding: 2mm;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="receipt-wrapper">
                        ${invoiceContent}
                    </div>
                </body>
                </html>
            `);
            
            printWindow.document.close();
            
            printWindow.onload = function() {
                setTimeout(function() {
                    printWindow.focus();
                    printWindow.print();
                    
                    printWindow.onafterprint = function() {
                        printWindow.close();
                    };
                    
                    setTimeout(function() {
                        if (!printWindow.closed) {
                            printWindow.close();
                        }
                    }, 3000);
                }, 500);
            };
        }

        function generateInvoiceHTML() {
            const pharmacyInfo = <?php echo json_encode($pharmacyInfo); ?>;
            const cashierName = '<?php echo htmlspecialchars($cashierName ?? 'Caissier'); ?>';
            const cartInfo = {
                sellerName: '<?php echo htmlspecialchars($cart['sellerName'] ?? ''); ?>',
                clientName: '<?php echo htmlspecialchars($cart['clientName'] ?? 'Client anonyme'); ?>',
                clientPhone: '<?php echo htmlspecialchars($cart['clientPhone'] ?? ''); ?>'
            };

            let html = `
                <div class="pharmacy-header">
                    <div class="pharmacy-name">${pharmacyInfo.app_name ? pharmacyInfo.app_name.toUpperCase() : 'PHARMACIE'}</div>
                    <div class="pharmacy-info">${pharmacyInfo.pharmacy_address || ''}</div>
                    <div class="pharmacy-info">Tel: ${pharmacyInfo.pharmacy_phone || ''}</div>
                    ${pharmacyInfo.pharmacy_email ? `<div class="pharmacy-info">${pharmacyInfo.pharmacy_email}</div>` : ''}
                    ${pharmacyInfo.pharmacy_license ? `<div class="pharmacy-info">${pharmacyInfo.pharmacy_license}</div>` : ''}
                </div>

                <div class="sale-info">
                    <span>N°: ${currentSaleData.invoiceNumber}</span>
                    <span>${currentSaleData.saleDate}</span>
                </div>
                
                <div class="client-info">
                    <strong>Caissier:</strong> ${cashierName.toUpperCase()}
                </div>
                
                <div class="client-info">
                    <strong>Vendeur:</strong> ${cartInfo.sellerName.toUpperCase()}
                </div>

                ${cartInfo.clientName !== 'Client anonyme' ? `
                <div class="client-info">
                    <strong>Client:</strong> ${cartInfo.clientName}
                    ${cartInfo.clientPhone ? `<br><strong>Tel:</strong> ${cartInfo.clientPhone}` : ''}
                </div>
                ` : ''}

                <div class="items-header">
                    <span>Article</span>
                    <span>PU x Qte = Total</span>
                </div>
            `;

            currentSaleData.items.forEach(item => {
                html += `
                    <div class="item">
                        <div class="item-name">${item.name}</div>
                        <div class="item-calc">
                            <span>${item.unitPrice.toLocaleString()}F x ${item.quantity}</span>
                            <span><strong>${item.total.toLocaleString()}F</strong></span>
                        </div>
                    </div>
                `;
            });

            html += `
                <div class="invoice-totals">
                    ${currentSaleData.discountAmount > 0 ? `
                    <div class="invoice-total-line">
                        <span>Sous-total:</span>
                        <span>${currentSaleData.subtotal.toLocaleString()}F</span>
                    </div>
                    <div class="invoice-total-line">
                        <span>Remise:</span>
                        <span>-${currentSaleData.discountAmount.toLocaleString()}F</span>
                    </div>
                    ` : ''}
                    <div class="invoice-total-line" style="font-size: 13px; border-top: 1px solid #000; padding-top: 3px; margin-top: 3px;">
                        <span>TOTAL:</span>
                        <span>${currentSaleData.totalAmount.toLocaleString()}F</span>
                    </div>
                </div>

                <div class="payment-info">
                    <div class="payment-line">
                        <span><strong>ESPECES:</strong></span>
                        <span><strong>${currentSaleData.cashReceived.toLocaleString()}F</strong></span>
                    </div>
                    <div class="payment-line">
                        <span>Rendu:</span>
                        <span>${Math.max(0, currentSaleData.change).toLocaleString()}F</span>
                    </div>
                </div>

                <div class="invoice-footer">
                    <div style="margin-bottom: 3px; font-weight: bold;">MERCI DE VOTRE VISITE!</div>
                    <div>${currentSaleData.saleTime}</div>
                    <div style="margin-top: 2px;">${pharmacyInfo.company_description || '© PharmaSys - Système de gestion'}</div>
                </div>
            `;

            return html;
        }

        function showErrorModal(message) {
            const modalHTML = `
                <div class="modal" style="display:block; position:fixed; z-index:1100; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
                    <div class="modal-content" style="background-color:white; margin:15% auto; padding:20px; border-radius:8px; width:80%; max-width:400px;">
                        <div style="color:#dc2626; margin-bottom:15px;">
                            <i data-lucide="x-circle"></i>
                            <strong>Erreur</strong>
                        </div>
                        <p>${message}</p>
                        <button onclick="this.parentElement.parentElement.remove()" class="btn-primary" style="width:100%;">OK</button>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });

        function setupSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const menuToggle = document.getElementById('menuToggle');
            const sidebarClose = document.getElementById('sidebarClose');

            function showSidebar() {
                sidebar.classList.add('show');
                overlay.classList.add('show');
            }

            function hideSidebar() {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }

            if (menuToggle) menuToggle.addEventListener('click', showSidebar);
            if (sidebarClose) sidebarClose.addEventListener('click', hideSidebar);
            if (overlay) overlay.addEventListener('click', hideSidebar);
        }

        function setFavicon() {
            const svgData = `
                <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                    <path d="M60 20 L140 20 L140 60 L180 60 L180 140 L140 140 L140 180 L60 180 L60 140 L20 140 L20 60 L60 60 Z" fill="#059669"/>
                    <path d="M75 35 L125 35 L125 75 L165 75 L165 125 L125 125 L125 165 L75 165 L75 125 L35 125 L35 75 L75 75 Z" fill="white"/>
                    <g fill="#059669">
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

        document.addEventListener('DOMContentLoaded', function() {
            setFavicon();
            setupSidebar();
        });
        
        
        function removeItem(itemId) {
    if (!confirm('Voulez-vous vraiment retirer cet article du panier ?')) {
        return;
    }
    
    fetch('delete-cart-item.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'itemId=' + itemId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Retirer la ligne du tableau
            const row = document.querySelector(`tr[data-item-id="${itemId}"]`);
            if (row) {
                row.remove();
            }
            
            // Recalculer les totaux
            updateTotals();
            
            // Vérifier s'il reste des articles
            const remainingItems = document.querySelectorAll('.items-table tbody tr').length;
            if (remainingItems === 0) {
                window.location.reload();
            }
        } else {
            alert('Erreur: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Erreur lors de la suppression de l\'article');
    });
}

// Confirmer la suppression du panier
function confirmDeleteCart(cartId) {
    const modal = document.getElementById('confirmModal');
    const message = document.getElementById('confirmMessage');
    const confirmBtn = document.getElementById('confirmButton');
    
    message.textContent = 'Êtes-vous sûr de vouloir supprimer ce panier ? Cette action est irréversible.';
    
    confirmBtn.onclick = function() {
        deleteCart(cartId);
    };
    
    modal.classList.add('show');
    
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Fermer le modal de confirmation
function closeConfirmModal() {
    const modal = document.getElementById('confirmModal');
    modal.classList.remove('show');
}

// Supprimer le panier
function deleteCart(cartId) {
    fetch('delete-cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'cartId=' + cartId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeConfirmModal();
            window.location.href = 'index.php';
        } else {
            alert('Erreur: ' + data.message);
            closeConfirmModal();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Erreur lors de la suppression du panier');
        closeConfirmModal();
    });
}

// MODIFIER la fonction updateTotals() existante pour gérer les lignes supprimées
function updateTotals() {
    let subtotal = 0;
    
    document.querySelectorAll('.items-table tbody tr').forEach(row => {
        const quantityInput = row.querySelector('.quantity-input');
        if (quantityInput) {
            const quantity = parseInt(quantityInput.value);
            const price = parseFloat(quantityInput.dataset.price);
            
            const itemTotal = quantity * price;
            subtotal += itemTotal;
        }
    });
    
    originalSubtotal = subtotal;
    
    // Apply discount
    const discountAmount = calculateDiscountAmount(subtotal);
    const finalTotal = subtotal - discountAmount;
    
    // Update display
    document.querySelector('.subtotal-display').textContent = subtotal.toLocaleString() + ' XAF';
    document.querySelector('.total-display').textContent = finalTotal.toLocaleString() + ' XAF';
    
    if (discountAmount > 0) {
        document.querySelector('.discount-row').style.display = 'flex';
        document.querySelector('.discount-display').textContent = discountAmount.toLocaleString() + ' XAF';
    } else {
        document.querySelector('.discount-row').style.display = 'none';
    }
    
    // Recalculate change if cash received is entered
    const cashReceived = parseFloat(document.getElementById('cashReceived').value) || 0;
    if (cashReceived > 0) {
        calculateChange(cashReceived);
    }
}


    </script>
</body>
</html>

<?php }else{
    header("location: ../logout.php");
}?>