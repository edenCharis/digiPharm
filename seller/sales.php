<?php

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Include database config with error handling
try {
    include '../config/database.php';
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Check session and role
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "SELLER" || 
    !isset($_SESSION["id"]) || $_SESSION["id"] != session_id()) {
    header("location: ../logout.php");
    exit();
}

// Initialize variables with defaults
$sellerId = $_SESSION["user_id"] ?? 1;
$today = date('Y-m-d');
$cartCount = 0;
$totalValue = 0;

try {
    // Get seller's daily cart statistics
    $dailyStatsQuery = "
        SELECT 
            COUNT(c.id) as cart_count,
            COALESCE(SUM(
                (SELECT SUM(ci.quantity * ci.unit_price) FROM cart_items ci WHERE ci.cart_id = c.id)
            ), 0) as total_value
        FROM carts c 
        WHERE c.seller_id = ? 
        AND DATE(c.created_at) = ?
    ";
    
    // Check if $db object exists and has fetch method
    if (isset($db) && method_exists($db, 'fetch')) {
        $dailyStats = $db->fetch($dailyStatsQuery, [$sellerId, $today]);
        
        if ($dailyStats) {
            $cartCount = $dailyStats['cart_count'] ?? 0;
            $totalValue = $dailyStats['total_value'] ?? 0;
        }
    }
} catch (Exception $e) {
    // Log error but don't break the page
    error_log("Daily stats query error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys - Nouvelle Vente</title>
    
    <!-- Use same icon approach as dashboard -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/umd/lucide.js"></script>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/seller-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <!-- Select2 CSS and JS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0/js/select2.min.js"></script>

    <style>
        /* All your existing CSS styles remain the same */
        .app-layout {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        
        #sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }
        
        .main-content .header {
            position: fixed;
            top: 0;
            right: 0;
            left: 280px;
            z-index: 999;
            background: white;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .content-area {
            flex: 1;
            overflow-y: auto;
            padding-top: 80px;
        }
        
        /* Daily Stats Card */
        .daily-stats {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);;
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            position: sticky;
            top: 20px;
            z-index: 10;
        }
        
        .daily-stats h4 {
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .stat-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .sale-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .sale-header {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            padding: 20px;
            border-radius: 12px;
            color: white;
            margin-bottom: 30px;
        }
        
        .sale-header h1 {
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sale-header p {
            margin: 0;
            opacity: 0.9;
        }
        
        .sale-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .product-search {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .product-search h3 {
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1f2937;
        }
        
        .search-input {
            position: relative;
        }
        
        .search-input input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .search-input input:focus {
            outline: none;
            border-color: #059669;
        }
        
        .search-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: #059669;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .search-btn:hover {
            background: #047857;
        }
        
        .product-results {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #f3f4f6;
            transition: background-color 0.2s;
        }
        
        .product-item:hover {
            background-color: #f9fafb;
        }
        
        .product-info {
            flex: 1;
        }
        
        .product-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .product-details {
            font-size: 14px;
            color: #6b7280;
        }
        
        .product-price {
            font-weight: 700;
            color: #059669;
            margin-right: 15px;
        }
        
        .add-btn {
            background: #059669;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .add-btn:hover {
            background: #047857;
        }
        
        .cart-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            height: fit-content;
        }
        
        .cart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .cart-title {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .cart-items {
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 20px;
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .cart-item-info {
            flex: 1;
        }
        
        .cart-item-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .cart-item-price {
            font-size: 14px;
            color: #6b7280;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }
        
        .quantity-btn {
            width: 30px;
            height: 30px;
            border: 1px solid #d1d5db;
            background: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .quantity-btn:hover {
            background: #f3f4f6;
        }
        
        .quantity {
            min-width: 30px;
            text-align: center;
            font-weight: 600;
        }
        
        .item-total {
            font-weight: 700;
            color: #059669;
            min-width: 80px;
            text-align: right;
        }
        
        .remove-btn {
            color: #dc2626;
            background: none;
            border: none;
            padding: 5px;
            cursor: pointer;
            margin-left: 10px;
        }
        
        .cart-summary {
            border-top: 2px solid #f3f4f6;
            padding-top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .summary-row.total {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
            margin-top: 15px;
        }
        
        .client-selection {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .client-selection h3 {
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1f2937;
        }
        
        .client-search {
            display: flex;
            gap: 15px;
            align-items: end;
        }

        .cashier-selection {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .cashier-selection h3 {
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1f2937;
        }

        .cashier-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }

        .cashier-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
        }

        .cashier-status.open {
            background: #dcfce7;
            color: #166534;
        }

        .cashier-status.busy {
            background: #fef3c7;
            color: #92400e;
        }

        .cashier-status.closed {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #059669;
        }
        
        .btn-primary {
            background: #059669;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary:hover {
            background: #047857;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.2s;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .empty-cart {
            text-align: center;
            color: #6b7280;
            padding: 40px 20px;
        }
        
        .empty-cart i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        
        .alert-error {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }
        
        .alert-info {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            color: #1e40af;
        }

        .alert-warning {
            background: #fef3c7;
            border: 1px solid #fde68a;
            color: #92400e;
        }
        
        /* Select2 customization */
        .select2-container .select2-selection--single {
            height: 50px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 46px;
            padding-left: 12px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 46px;
        }
        
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #059669;
            outline: none;
        }
        
        .select2-dropdown {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
        }
        
        .select2-results__option {
            padding: 10px 15px;
        }
        
        .select2-results__option--highlighted {
            background-color: #059669 !important;
        }
        
        /* Mobile responsive */
        @media (max-width: 1200px) {
            #sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            #sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .main-content .header {
                left: 0;
            }
            
            .sale-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .client-search {
                flex-direction: column;
                align-items: stretch;
            }
            
            .sale-container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
   <div class="app-layout">
        <!-- Sidebar -->
        <div id="sidebarOverlay" class="sidebar-overlay"></div>
      <?php
       include 'sidebar.php';
      ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
          <?php 
              include 'header.php';
          ?>
            
            <!-- Content Area -->
            <main class="content-area">
                <div class="sale-container">
                    <!-- Sale Header -->
                    <div class="sale-header">
                        <h1><i data-lucide="plus-circle"></i> Nouvelle Vente</h1>
                        <p>Créez un panier pour votre client et assignez-le à un caissier</p>
                    </div>

                    <!-- Client Selection -->
                    <div class="client-selection">
                        <h3><i data-lucide="user"></i> Informations Client (Optionnel)</h3>
                        <div class="alert alert-info">
                            <i data-lucide="info"></i>
                            <span>La sélection d'un client est optionnelle. Vous pouvez procéder sans client.</span>
                        </div>
                        <div class="client-search">
                            <div class="form-group">
                                <label for="clientSelect">Rechercher/Sélectionner un client</label>
                                <select id="clientSelect" style="width: 100%;" onchange="displaySelectedClient(this.options[this.selectedIndex])">
                                    <option value="">-- Aucun client sélectionné --</option>
                                    <?php
                                    try {
                                        // Fetch clients from database with error handling
                                        if (isset($db) && method_exists($db, 'fetch')) {
                                            $clientQuery = "SELECT id, name, contact as tel FROM client ORDER BY name ASC";
                                            $clients = $db->fetchAll($clientQuery);
                                            
                                            if ($clients && is_array($clients) && count($clients) > 0) {
                                                // Handle both single row and multiple rows
                                                $clientsArray = isset($clients[0]) ? $clients : [$clients];
                                                
                                                foreach ($clientsArray as $client) {
                                                    $clientId = htmlspecialchars($client['id']);
                                                    $clientName = htmlspecialchars($client['name']); 
                                                    $clientTel = htmlspecialchars($client['tel'] ?? 'N/A');
                                                    echo "<option value='$clientId' data-name='$clientName' data-tel='$clientTel'>$clientName - $clientTel</option>";
                                                }
                                            }
                                        }
                                    } catch (Exception $e) {
                                        error_log("Client query error: " . $e->getMessage());
                                    }
                                    ?>
                                </select>
                            </div>
                            <button type="button" class="btn btn-secondary" onclick="openNewClientModal()">
                                <i data-lucide="user-plus"></i>
                                Nouveau Client
                            </button>
                        </div>
                        
                        <div id="selectedClient" class="alert alert-success" style="display: none; margin-top: 15px;">
                            <i data-lucide="check-circle"></i>
                            <span>Client sélectionné: <strong id="clientName"></strong></span>
                        </div>
                    </div>

                    <!-- Cashier Selection -->
                    <div class="cashier-selection">
                        <h3><i data-lucide="calculator"></i> Sélectionner un Caissier (Obligatoire)</h3>
                        <div class="alert alert-warning">
                            <i data-lucide="alert-triangle"></i>
                            <span>Vous devez sélectionner un caissier pour traiter cette vente.</span>
                        </div>
                        <div class="form-group">
                            <label for="cashierSelect">Choisir un caissier disponible</label>
                            <select id="cashierSelect" style="width: 100%;" onchange="displaySelectedCashier(this.options[this.selectedIndex])">
                                <option value="">-- Sélectionner un caissier --</option>
                                <?php
                                try {
                                    // Fetch available cashiers (those with open cash registers)
                                    $cashierQuery = "
                                        SELECT 
                                            cr.id as register_id,
                                           u.username as cashier_name,
                                            cr.opening_time,
                                            cr.status,
                                            cr.initial_amount,
                                            (SELECT COUNT(*) FROM carts c WHERE c.cash_register_id = cr.id AND c.status = 'PENDING') as pending_carts
                                        FROM cash_register cr
                                        JOIN user u ON u.id = cr.cashier_id
                                        WHERE cr.status = 'OPEN' AND u.role = 'CASHIER'
                                        ORDER BY pending_carts ASC, u.username ASC
                                    ";
                                    
                                    $cashiers = $db->fetchAll($cashierQuery);
                                    
                                    if ($cashiers && is_array($cashiers) && count($cashiers) > 0) {
                                        $cashiersArray = isset($cashiers[0]) ? $cashiers : [$cashiers];
                                        
                                        $int = 0;
                                        
                                        foreach ($cashiersArray as $cashier) {
                                            $registerId = htmlspecialchars($cashier['register_id']);
                                            $cashierId = htmlspecialchars($cashier['cashier_id']);
                                            $cashierName = htmlspecialchars($cashier['cashier_name']);
                                            $openingTime = htmlspecialchars($cashier['opening_time']);
                                            $pendingCarts = (int)$cashier['pending_carts'];
                                            $initialAmount = number_format($cashier['initial_amount'], 0, ',', ' ');
                                            
                                            $statusText = $pendingCarts == 0 ? 'Libre' : "$pendingCarts panier(s) en attente";
                                            
                                            
                                              $selected = ($int === 0) ? 'selected' : '';
              
                                            
                                            echo "<option     value='$registerId' 
                                                    data-cashier-id='$cashierId' 
                                                    data-cashier-name='$cashierName' 
                                                    data-opening-time='$openingTime'
                                                    data-pending-carts='$pendingCarts'
                                                    data-initial-amount='$initialAmount'>
                                                    $cashierName - $statusText
                                                  </option>";
                                                  
                                                  $int++;
                                        }
                                    } else {
                                        echo "<option disabled>Aucun caissier disponible</option>";
                                    }
                                } catch (Exception $e) {
                                    error_log("Cashier query error: " . $e->getMessage());
                                    echo "<option disabled>Erreur de chargement ".$e->getMessage()."</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div id="selectedCashier" class="cashier-info" style="display: none;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <strong id="selectedCashierName">Caissier sélectionné</strong>
                                <span id="cashierStatus" class="cashier-status open">Libre</span>
                            </div>
                            <div style="font-size: 14px; color: #6b7280;">
                                <div>Paniers en attente: <span id="cashierPendingCarts"></span></div>
                            </div>
                        </div>
                    </div>

                    <!-- New Client Modal -->
                    <div id="newClientModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
                        <div class="modal-content" style="background-color: white; margin: 15% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 500px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                <h3 style="margin: 0;"><i data-lucide="user-plus"></i> Nouveau Client</h3>
                                <button onclick="closeNewClientModal()" style="background: none; border: none; cursor: pointer;">
                                    <i data-lucide="x"></i>
                                </button>
                            </div>
                            <form method="POST" action="traitement.php">
                                  <div class="form-group" style="margin-bottom: 15px;">
                                    <label for="clientNameInput">Nom</label>
                                    <input type="text" id="clientNameInput" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" >
                                </div>
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label for="clientPhoneInput">Téléphone</label>
                                    <input type="tel" id="clientPhoneInput" name="tel" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" minlength="8" maxlength="20" pattern="[0-9+\-\s]+">
                                </div>
                                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                                    <button type="button" class="btn-secondary" onclick="closeNewClientModal()">Annuler</button>
                                    <button type="submit" class="btn-primary">Créer</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Main Sale Grid -->
                    <div class="sale-grid">
                        <!-- Product Search Section -->
                        <div>
                            <!-- Product Search -->
                            <div class="product-search">
                                <h3><i data-lucide="search"></i> Rechercher des produits</h3>
                                <div class="search-input">
                                    <input type="text" id="productSearch" placeholder="Rechercher par nom, code-barres ou référence...">
                                    <button class="search-btn" onclick="searchProducts()">
                                        <i data-lucide="search"></i>
                                    </button>
                                </div>
                                
                                <div id="productResults" class="product-results">
                                    <?php
                                    try {
                                        // Fetch products from database with error handling
                                            $query = "SELECT code, name, sellingPrice, stock FROM product WHERE stock > 0 ORDER BY name ASC";
                                            $products = $db->fetchAll($query);
                                            
                                            if ($products && is_array($products) && count($products) > 0) {
                                                // Handle both single row and multiple rows
                                                $productsArray = isset($products[0]) ? $products : [$products];
                                                
                                               foreach ($productsArray as $product) {

    $code = htmlspecialchars($product['code'], ENT_QUOTES);
    $name = htmlspecialchars($product['name'], ENT_QUOTES);
    $price = number_format($product['sellingPrice'], 2, '.', '');
    $stock = (int)$product['stock'];
    $priceXaf = number_format($product['sellingPrice'], 0, ',', ' ');

    echo <<<HTML
<div class="product-item">
    <div class="product-info">
        <div class="product-name">{$name}</div>
        <div class="product-details">Code: {$code} • Stock: {$stock} • PRIX: {$price} XAF</div>
    </div>
    <div class="product-price">{$priceXaf} XAF</div>
    <button class="add-btn" onclick="addToCart('{$code}', '{$name}', {$price}, {$stock})">
        <i data-lucide="plus"></i>
    </button>
</div>
HTML;
}

                                            } else {
                                                echo '<div class="empty-cart"><i data-lucide="package"></i> Aucun produit disponible.</div>';
                                            }
                                        } catch (Exception $e) {
                                            error_log("Product query error: " . $e->getMessage());
                                            echo '<div class="empty-cart"><i data-lucide="alert-triangle"></i> Erreur lors du chargement des produits.</div>';
                                        }
                                 
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- Cart Section -->
                        <div class="cart-section">
                            <div class="cart-header">
                                <div class="cart-title">
                                    <i data-lucide="shopping-cart"></i>
                                    Panier (<span id="cartCount">0</span>)
                                </div>
                                <button class="btn btn-danger" onclick="clearCart()" style="padding: 8px 12px; font-size: 14px;">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </div>

                            <div id="cartItems" class="cart-items">
                                <div class="empty-cart">
                                    <i data-lucide="shopping-cart"></i>
                                    <p>Votre panier est vide</p>
                                    <small>Recherchez et ajoutez des produits</small>
                                </div>
                            </div>

                            <div class="cart-summary">
                                <div class="summary-row">
                                    <span>Sous-total:</span>
                                    <span id="subtotal">0.00 XAF</span>
                                </div>
                             
                                <div class="summary-row">
                                    <span>Remise:</span>
                                    <span id="discount">0.00 XAF</span>
                                </div>
                                <div class="summary-row total">
                                    <span>Total:</span>
                                    <span id="total">0.00 XAF</span>
                                </div>
                                
                                <button class="btn-success" onclick="sendToCashier()" style="margin-top: 20px;">
                                    <i data-lucide="send"></i>
                                    Envoyer au Caissier
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
<script>
    
    
    
</script>

    <script>
        // Set favicon - same as dashboard
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

        // Sidebar functionality - same as dashboard
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

        let cart = [];
        let selectedClient = null;
        let selectedCashRegister = null;

        // Initialize app
    // REMPLACEZ votre fonction initApp() existante par celle-ci :
// ============================================
// SOLUTION COMPLÈTE POUR AUTO-SELECT CASHIER
// ============================================

function initApp() {
    setFavicon();
    setupSidebar();
    addCustomCSS();
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Initialize Select2
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#clientSelect').select2({
            placeholder: 'Rechercher un client...',
            allowClear: true,
            language: {
                noResults: function() {
                    return "Aucun client trouvé";
                },
                searching: function() {
                    return "Recherche...";
                }
            }
        });
        
        $('#cashierSelect').select2({
            placeholder: 'Sélectionner un caissier...',
            language: {
                noResults: function() {
                    return "Aucun caissier disponible";
                }
            }
        });
        
        // Handle client selection
        $('#clientSelect').on('select2:select', function(e) {
            const data = e.params.data;
            if (data.id) {
                const option = $(this).find('option:selected');
                selectedClient = {
                    id: data.id,
                    name: option.data('name'),
                    tel: option.data('tel')
                };
                showSelectedClient();
            } else {
                selectedClient = null;
                hideSelectedClient();
            }
        });
        
        $('#clientSelect').on('select2:clear', function(e) {
            selectedClient = null;
            hideSelectedClient();
        });

        // Handle cashier selection - CHANGEMENT ICI
        $('#cashierSelect').on('select2:select change', function(e) {
            const selectedValue = $(this).val();
            
            if (selectedValue) {
                const option = $(this).find('option:selected');
                selectedCashRegister = {
                    id: selectedValue,
                    cashierId: option.data('cashier-id'),
                    cashierName: option.data('cashier-name'),
                    openingTime: option.data('opening-time'),
                    pendingCarts: parseInt(option.data('pending-carts')) || 0,
                    initialAmount: option.data('initial-amount')
                };
                
                console.log('Caissier sélectionné:', selectedCashRegister);
                showSelectedCashier();
                updateSendButton(); // IMPORTANT: Mettre à jour le bouton
            } else {
                selectedCashRegister = null;
                hideSelectedCashier();
                updateSendButton(); // IMPORTANT: Mettre à jour le bouton
            }
        });

        $('#cashierSelect').on('select2:clear', function(e) {
            selectedCashRegister = null;
            hideSelectedCashier();
            updateSendButton();
        });

        // ========================================
        // AUTO-SELECT FIRST CASHIER
        // ========================================
        setTimeout(function() {
            const cashierSelect = $('#cashierSelect');
            const firstOption = cashierSelect.find('option[value!=""][value]').first();
            
            if (firstOption.length > 0) {
                const firstValue = firstOption.val();
                
                console.log('Auto-sélection du caissier:', firstValue);
                
                // Méthode 1: Via Select2
                cashierSelect.val(firstValue).trigger('change');
                
                // Méthode 2: Forcer la mise à jour manuelle aussi
                selectedCashRegister = {
                    id: firstValue,
                    cashierId: firstOption.data('cashier-id'),
                    cashierName: firstOption.data('cashier-name'),
                    openingTime: firstOption.data('opening-time'),
                    pendingCarts: parseInt(firstOption.data('pending-carts')) || 0,
                    initialAmount: firstOption.data('initial-amount')
                };
                
                console.log('selectedCashRegister défini:', selectedCashRegister);
                
                showSelectedCashier();
                updateSendButton();
                
                console.log('✓ Premier caissier auto-sélectionné et configuré');
            } else {
                console.warn('⚠ Aucun caissier disponible pour auto-sélection');
            }
        }, 200);
    }
    
    // Set initial button state
    updateSendButton();
    
    // Add event listeners
    const productSearchInput = document.getElementById('productSearch');
    if (productSearchInput) {
        productSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchProducts();
            }
        });
    }
}

// Alternative: Si vous n'utilisez pas Select2, utilisez cette version
function initAppWithoutSelect2() {
    setFavicon();
    setupSidebar();
    addCustomCSS();
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // ========================================
    // AUTO-SELECT FIRST CASHIER (sans Select2)
    // ========================================
    const cashierSelect = document.getElementById('cashierSelect');
    
    if (cashierSelect) {
        // Trouver la première option valide
        const firstOption = cashierSelect.querySelector('option[value!=""]');
        
        if (firstOption) {
            // Sélectionner l'option
            cashierSelect.value = firstOption.value;
            
            // Trigger manuellement l'événement change
            displaySelectedCashier(firstOption);
            
            console.log('✓ Premier caissier auto-sélectionné');
        } else {
            console.warn('⚠ Aucun caissier disponible pour auto-sélection');
        }
        
        // Ajouter l'event listener pour les changements futurs
        cashierSelect.addEventListener('change', function() {
            displaySelectedCashier(this.options[this.selectedIndex]);
        });
    }
    
    // Set initial button state
    updateSendButton();
    
    // Add event listeners
    const productSearchInput = document.getElementById('productSearch');
    if (productSearchInput) {
        productSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchProducts();
            }
        });
    }
}

// ========================================
// MISE À JOUR DU BOUTON
// ========================================
function updateSendButton() {
    const sendButton = document.querySelector('.btn-success');
    if (sendButton) {
        if (canSendToCart()) {
            sendButton.disabled = false;
            sendButton.innerHTML = '<i data-lucide="send"></i> Envoyer au Caissier';
        } else {
            sendButton.disabled = true;
            if (cart.length === 0 && !selectedCashRegister) {
                // Caissier sélectionné mais panier vide
                sendButton.innerHTML = '<i data-lucide="shopping-cart"></i> Ajoutez des produits au panier';
            } else if (cart.length === 0) {
                // Panier vide (caissier déjà sélectionné)
                sendButton.innerHTML = '<i data-lucide="shopping-cart"></i> Ajoutez des produits au panier';
            } else if (!selectedCashRegister) {
                sendButton.innerHTML = '<i data-lucide="calculator"></i> Sélectionnez un caissier';
            }
        }
        // Reinitialize icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
}

// ========================================
// VERSION SIMPLIFIÉE POUR TEST
// ========================================
// Si les fonctions ci-dessus ne marchent pas, utilisez cette version simple
function autoSelectFirstCashier() {
    // Attendre que le DOM soit chargé
    setTimeout(function() {
        const cashierSelect = document.getElementById('cashierSelect');
        
        if (cashierSelect) {
            // Méthode 1: Via Select2
            if (typeof $ !== 'undefined' && $.fn.select2) {
                const firstValue = $('#cashierSelect option[value!=""]').first().val();
                if (firstValue) {
                    $('#cashierSelect').val(firstValue).trigger('change');
                    
                    // Récupérer l'option pour les données
                    const firstOption = document.querySelector('#cashierSelect option[value="' + firstValue + '"]');
                    if (firstOption) {
                        displaySelectedCashier(firstOption);
                    }
                }
            } 
            // Méthode 2: Sans Select2
            else {
                const firstOption = cashierSelect.querySelector('option[value!=""]');
                if (firstOption) {
                    cashierSelect.value = firstOption.value;
                    displaySelectedCashier(firstOption);
                }
            }
            
            console.log('✓ Auto-sélection du caissier effectuée');
        }
    }, 200);
}

// Appeler cette fonction au chargement
document.addEventListener('DOMContentLoaded', function() {
    autoSelectFirstCashier();
});

        // Display selected cashier
        function displaySelectedCashier(option) {
            if (option && option.value) {
                const cashierName = option.getAttribute('data-cashier-name');
                const openingTime = option.getAttribute('data-opening-time');
                const pendingCarts = option.getAttribute('data-pending-carts');
                const initialAmount = option.getAttribute('data-initial-amount');
                
                selectedCashRegister = {
                    id: option.value,
                    cashierId: option.getAttribute('data-cashier-id'),
                    cashierName: cashierName,
                    openingTime: openingTime,
                    pendingCarts: parseInt(pendingCarts),
                    initialAmount: initialAmount
                };
                
                showSelectedCashier();
            } else {
                selectedCashRegister = null;
                hideSelectedCashier();
            }
        }

        // Show selected cashier
        function showSelectedCashier() {
            if (selectedCashRegister) {
                document.getElementById('selectedCashier').style.display = 'block';
                document.getElementById('selectedCashierName').textContent = selectedCashRegister.cashierName;
                document.getElementById('cashierOpeningTime').textContent = new Date(selectedCashRegister.openingTime).toLocaleString('fr-FR');
                document.getElementById('cashierInitialAmount').textContent = selectedCashRegister.initialAmount;
                document.getElementById('cashierPendingCarts').textContent = selectedCashRegister.pendingCarts;
                
                const statusElement = document.getElementById('cashierStatus');
                if (selectedCashRegister.pendingCarts === 0) {
                    statusElement.textContent = 'Libre';
                    statusElement.className = 'cashier-status open';
                } else {
                    statusElement.textContent = `${selectedCashRegister.pendingCarts} panier(s)`;
                    statusElement.className = 'cashier-status busy';
                }
            }
        }

        // Hide selected cashier
        function hideSelectedCashier() {
            document.getElementById('selectedCashier').style.display = 'none';
        }
        
        
        // Add event listeners
const productSearchInput = document.getElementById('productSearch');
if (productSearchInput) {
    productSearchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchProducts();
        }
    });
}

        // Product search
     // Improved product search function with better error handling
function searchProducts() {
    const searchInput = document.getElementById('productSearch');
    const query = searchInput.value.trim();
    
    // Validate minimum length
    if (query.length < 2) {
        showErrorModal('Veuillez saisir au moins 2 caractères pour la recherche');
        return;
    }
    
    // Show loading indicator
    const resultsContainer = document.getElementById('productResults');
    resultsContainer.innerHTML = `
        <div class="empty-cart">
            <i data-lucide="loader" style="animation: spin 1s linear infinite;"></i>
            <p>Recherche en cours...</p>
        </div>
    `;
    
    // Add spinner animation
    const style = document.createElement('style');
    style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
    document.head.appendChild(style);
    
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Debug: Log the request
    console.log('Searching for:', query);
    
    // Make AJAX request
    fetch('search_products.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ query: query })
    })
    .then(response => {
        // Debug: Log response status
        console.log('Response status:', response.status);
        
        // Check if response is OK
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Parse JSON
        return response.json();
    })
    .then(data => {
        // Debug: Log received data
        console.log('Received data:', data);
        
        if (data.success) {
            // Display results
            displaySearchResults(data.products);
            
            // Show count
            if (data.count !== undefined) {
                console.log(`Found ${data.count} product(s)`);
            }
        } else {
            // Show error message
            console.error('Search error:', data.message);
            resultsContainer.innerHTML = `
                <div class="empty-cart">
                    <i data-lucide="alert-triangle"></i>
                    <p>Erreur de recherche</p>
                    <small>${data.message || 'Une erreur est survenue'}</small>
                </div>
            `;
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }
    })
    .catch(error => {
        // Debug: Log error
        console.error('Fetch error:', error);
        
        // Show error in UI
        resultsContainer.innerHTML = `
            <div class="empty-cart">
                <i data-lucide="alert-triangle"></i>
                <p>Erreur de connexion</p>
                <small>${error.message}</small>
            </div>
        `;
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
        
        // Show error modal
        showErrorModal('Erreur lors de la recherche: ' + error.message);
    });
}

// Enhanced display search results function
function displaySearchResults(products) {
    const resultsContainer = document.getElementById('productResults');
    
    // Debug: Log products
    console.log('Displaying products:', products);
    
    if (!products || products.length === 0) {
        resultsContainer.innerHTML = `
            <div class="empty-cart">
                <i data-lucide="search"></i>
                <p>Aucun produit trouvé</p>
                <small>Essayez avec d'autres mots-clés</small>
            </div>
        `;
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
        return;
    }

    // Build HTML for products
    const productsHTML = products.map(product => {
        const code = escapeHtml(product.code || '');
        const name = escapeHtml(product.name || '');
        const price = parseFloat(product.sellingPrice || 0);
        const stock = parseInt(product.stock || 0);
        const priceFormatted = price.toFixed(2);
        const priceXaf = price.toLocaleString('fr-FR', {
            minimumFractionDigits: 0, 
            maximumFractionDigits: 0
        });
        
        return `
            <div class="product-item">
                <div class="product-info">
                    <div class="product-name">${name}</div>
                    <div class="product-details">Code: ${code} • Stock: ${stock} • PRIX: ${priceFormatted} XAF</div>
                </div>
                <div class="product-price">${priceXaf} XAF</div>
                <button class="add-btn" onclick="addToCart('${code}', '${name}', ${price}, ${stock})">
                    <i data-lucide="plus"></i>
                </button>
            </div>
        `;
    }).join('');
    
    resultsContainer.innerHTML = productsHTML;
    
    // Reinitialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Helper function to escape HTML
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

// Add real-time search (optional - searches as user types)
function setupRealtimeSearch() {
    const searchInput = document.getElementById('productSearch');
    let searchTimeout;
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            // Clear previous timeout
            clearTimeout(searchTimeout);
            
            const query = this.value.trim();
            
            // Only search if query is at least 2 characters
            if (query.length >= 2) {
                // Wait 500ms after user stops typing
                searchTimeout = setTimeout(() => {
                    searchProducts();
                }, 500);
            } else if (query.length === 0) {
                // If search is cleared, reload all products
                loadAllProducts();
            }
        });
    }
}

// Function to load all products (initial state)
function loadAllProducts() {
    const resultsContainer = document.getElementById('productResults');
    
    resultsContainer.innerHTML = `
        <div class="empty-cart">
            <i data-lucide="loader" style="animation: spin 1s linear infinite;"></i>
            <p>Chargement des produits...</p>
        </div>
    `;
    
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // This will load the initial products from PHP
    // You might want to create a separate endpoint or just refresh the page
    location.reload();
}

        // Add product to cart
        function addToCart(code, name, price, stock) {
            const existingItem = cart.find(item => item.code === code);
            
            if (existingItem) {
                if (existingItem.quantity < stock) {
                    existingItem.quantity++;
                    updateCart();
                } else {
                    showErrorModal('Stock insuffisant!');
                }
            } else {
                cart.push({
                    code: code,
                    name: name,
                    price: parseFloat(price),
                    quantity: 1,
                    stock: stock
                });
                updateCart();
            }
        }

        // Update cart display
        function updateCart() {
            const cartItemsContainer = document.getElementById('cartItems');
            const cartCount = document.getElementById('cartCount');
            
            if (cart.length === 0) {
                cartItemsContainer.innerHTML = `
                    <div class="empty-cart">
                        <i data-lucide="shopping-cart"></i>
                        <p>Votre panier est vide</p>
                        <small>Recherchez et ajoutez des produits</small>
                    </div>
                `;
                cartCount.textContent = '0';
            } else {
                cartItemsContainer.innerHTML = cart.map(item => `
                    <div class="cart-item">
                        <div class="cart-item-info">
                            <div class="cart-item-name">${item.name}</div>
                            <div class="cart-item-price">${item.price.toFixed(2)} XAF / unité</div>
                            <div class="quantity-controls">
                                <button class="quantity-btn" onclick="updateQuantity('${item.code}', -1)">
                                    <i data-lucide="minus"></i>
                                </button>
                                <span class="quantity">${item.quantity}</span>
                                <button class="quantity-btn" onclick="updateQuantity('${item.code}', 1)">
                                    <i data-lucide="plus"></i>
                                </button>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div class="item-total">${(item.price * item.quantity).toFixed(2)} XAF</div>
                            <button class="remove-btn" onclick="removeFromCart('${item.code}')">
                                <i data-lucide="trash-2"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
                
                cartCount.textContent = cart.reduce((sum, item) => sum + item.quantity, 0);
            }
            
            updateSummary();
            
            // Reinitialize icons after DOM update
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        // Update quantity
        function updateQuantity(code, change) {
            const item = cart.find(item => item.code === code);
            if (item) {
                const newQuantity = item.quantity + change;
                if (newQuantity <= 0) {
                    removeFromCart(code);
                } else if (newQuantity <= item.stock) {
                    item.quantity = newQuantity;
                    updateCart();
                } else {
                    alert('Stock insuffisant!');
                }
            }
        }

        // Remove from cart
        function removeFromCart(code) {
            cart = cart.filter(item => item.code !== code);
            updateCart();
        }

        // Clear cart
        function clearCart() {
            if (cart.length > 0 && confirm('Êtes-vous sûr de vouloir vider le panier?')) {
                cart = [];
                updateCart();
            }
        }

        // Update summary
    function updateSummary() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    const discount = 0; // Can be modified based on client/insurance
    const total = subtotal - discount; // NO TVA ADDED

    document.getElementById('subtotal').textContent = subtotal.toFixed(2) + ' XAF';
    document.getElementById('discount').textContent = discount.toFixed(2) + ' XAF';
    document.getElementById('total').textContent = total.toFixed(2) + ' XAF';
}
        // New client modal functions
        function openNewClientModal() {
            document.getElementById('newClientModal').style.display = 'block';
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        function closeNewClientModal() {
            document.getElementById('newClientModal').style.display = 'none';
            document.getElementById('clientNameInput').value = '';
            document.getElementById('clientPhoneInput').value = '';
        }

        function displaySelectedClient(option) {
            if (option && option.value) {
                const name = option.getAttribute('data-name');
                const tel = option.getAttribute('data-tel');
                document.getElementById('selectedClient').style.display = 'flex';
                document.getElementById('clientName').textContent = `${name} (${tel})`;
                selectedClient = {
                    id: option.value,
                    name: name,
                    tel: tel
                };
            } else {
                document.getElementById('selectedClient').style.display = 'none';
                selectedClient = null;
            }
        }

        // Create new client
      
        // Error modal helper function
        function showErrorModal(message) {
            const modalHTML = `
                <div class="modal" style="display:block; position:fixed; z-index:1100; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
                    <div class="modal-content" style="background-color:white; margin:15% auto; padding:20px; border-radius:8px; width:80%; max-width:400px;">
                        <div style="color:#dc2626; margin-bottom:15px;">
                            <i data-lucide="alert-circle"></i>
                            <strong>Erreur</strong>
                        </div>
                        <p>${message}</p>
                        <button onclick="this.parentElement.parentElement.remove()" class="btn btn-primary" style="width:100%;">OK</button>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        // Success modal helper function 
        function showSuccessModal(message) {
            const modalHTML = `
                <div class="modal" style="display:block; position:fixed; z-index:1100; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
                    <div class="modal-content" style="background-color:white; margin:15% auto; padding:20px; border-radius:8px; width:80%; max-width:400px;">
                        <div style="color:#059669; margin-bottom:15px;">
                            <i data-lucide="check-circle"></i>
                            <strong>Succès</strong>
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

        // Show selected client
        function showSelectedClient() {
            if (selectedClient) {
                document.getElementById('selectedClient').style.display = 'flex';
                document.getElementById('clientName').textContent = `${selectedClient.name} (${selectedClient.tel})`;
            }
        }

        // Hide selected client
        function hideSelectedClient() {
            document.getElementById('selectedClient').style.display = 'none';
        }

        // Update daily stats after successful cart creation
        function updateDailyStats() {
            fetch('get_daily_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const cartCountElement = document.querySelector('.daily-stats .stat-item:first-child .stat-value');
                        const totalValueElement = document.querySelector('.daily-stats .stat-item:last-child .stat-value');
                        
                        if (cartCountElement) {
                            cartCountElement.textContent = data.cart_count;
                        }
                        if (totalValueElement) {
                            totalValueElement.textContent = new Intl.NumberFormat('fr-FR').format(data.total_value) + ' XAF';
                        }
                    }
                })
                .catch(error => console.error('Error updating daily stats:', error));
        }

        // Send to cashier - Modified to require cashier selection
       function sendToCashier() {
    if (!canSendToCart()) {
        if (cart.length === 0) {
            showErrorModal('Le panier est vide! Veuillez ajouter au moins un produit.');
        } else if (!selectedCashRegister) {
            showErrorModal('Veuillez sélectionner un caissier avant d\'envoyer le panier.');
        }
        return;
    }

    const clientMessage = selectedClient ? 
        `Client: ${selectedClient.name}<br>` : 
        'Aucun client sélectionné<br>';
    
    // NO VAT multiplication - price already includes VAT
    const totalAmount = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    
    const modalHTML = `
        <div class="modal" style="display:block; position:fixed; z-index:1100; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
            <div class="modal-content" style="background-color:white; margin:15% auto; padding:20px; border-radius:8px; width:80%; max-width:500px;">
                <h3 style="margin-bottom:20px;">Confirmer l'envoi</h3>
                <div style="margin-bottom:20px;">
                    ${clientMessage}
                    <p><strong>Caissier:</strong> ${selectedCashRegister.cashierName}</p>
                    <p><strong>Total:</strong> ${totalAmount.toFixed(2)} XAF</p>
                    <p>Envoyer ce panier au caissier sélectionné?</p>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button onclick="this.closest('.modal').remove()" class="btn-secondary">Annuler</button>
                    <button onclick="proceedWithSale(); this.closest('.modal').remove();" class="btn-primary">Confirmer</button>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}
        // Proceed with sale
    function proceedWithSale() {
    const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
    
    const saleData = {
        items: cart,
        clientId: selectedClient ? selectedClient.id : null, 
        cashRegisterId: selectedCashRegister.id,
        sellerId: <?php echo json_encode($sellerId); ?>,
        subtotal: subtotal,
        totalVAT: 0, // Set to 0 since VAT is already in price
        discountAmount: 0,
        totalAmount: subtotal // NO VAT added
    };

    fetch('create_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(saleData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccessModal('Panier envoyé au caissier avec succès!');

            // Reset form
            cart = [];
            selectedClient = null;
            selectedCashRegister = null;
            
            if (typeof $ !== 'undefined' && $.fn.select2) {
                $('#clientSelect').val(null).trigger('change');
                $('#cashierSelect').val(null).trigger('change');
            }
            
            hideSelectedClient();
            hideSelectedCashier();
            updateCart();
            updateDailyStats();

            setTimeout(() => {
                window.location.href = 'index.php';
            }, 2000);
        } else {
            showErrorModal(data.message || 'Une erreur est survenue');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorModal('Une erreur est survenue lors de l\'envoi du panier');
    });
}
        // Start the app when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initApp();
        });

        // Also support jQuery ready if available
        if (typeof $ !== 'undefined') {
            $(document).ready(function() {
                initApp();
            });
        }
    </script>

    <script>
        // Add these CSS classes to hide/show elements
const additionalCSS = `
    .hidden {
        display: none !important;
    }
    
    .btn-success:disabled {
        background: #9ca3af !important;
        cursor: not-allowed !important;
        opacity: 0.6 !important;
    }
`;

// Add the CSS to the page
function addCustomCSS() {
    const style = document.createElement('style');
    style.textContent = additionalCSS;
    document.head.appendChild(style);
}

// Modified function to check if cart can be sent
function canSendToCart() {
    return cart.length > 0 && selectedCashRegister !== null;
}

// Function to update button state
function updateSendButton() {
    const sendButton = document.querySelector('.btn-success');
    if (sendButton) {
        if (canSendToCart()) {
            sendButton.disabled = false;
            sendButton.innerHTML = '<i data-lucide="send"></i> Envoyer au Caissier';
        } else {
            sendButton.disabled = true;
            if (cart.length === 0 && !selectedCashRegister) {
                sendButton.innerHTML = '<i data-lucide="send"></i> Ajoutez des produits et sélectionnez un caissier';
            } else if (cart.length === 0) {
                sendButton.innerHTML = '<i data-lucide="send"></i> Ajoutez des produits au panier';
            } else if (!selectedCashRegister) {
                sendButton.innerHTML = '<i data-lucide="send"></i> Sélectionnez un caissier';
            }
        }
        // Reinitialize icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
}

// Modified showSelectedClient function
function showSelectedClient() {
    if (selectedClient) {
        document.getElementById('selectedClient').style.display = 'flex';
        document.getElementById('clientName').textContent = `${selectedClient.name} (${selectedClient.tel})`;
        // Hide the client info alert
        const clientAlert = document.querySelector('.client-selection .alert-info');
        if (clientAlert) {
            clientAlert.classList.add('hidden');
        }
    }
}

// Modified hideSelectedClient function
function hideSelectedClient() {
    document.getElementById('selectedClient').style.display = 'none';
    // Show the client info alert
    const clientAlert = document.querySelector('.client-selection .alert-info');
    if (clientAlert) {
        clientAlert.classList.remove('hidden');
    }
}

// Modified showSelectedCashier function
function showSelectedCashier() {
    if (selectedCashRegister) {
        document.getElementById('selectedCashier').style.display = 'block';
        document.getElementById('selectedCashierName').textContent = selectedCashRegister.cashierName;
        document.getElementById('cashierPendingCarts').textContent = selectedCashRegister.pendingCarts;
        
        const statusElement = document.getElementById('cashierStatus');
        if (selectedCashRegister.pendingCarts === 0) {
            statusElement.textContent = 'Libre';
            statusElement.className = 'cashier-status open';
        } else {
            statusElement.textContent = `${selectedCashRegister.pendingCarts} panier(s)`;
            statusElement.className = 'cashier-status busy';
        }
        
        // Hide the cashier warning alert
        const cashierAlert = document.querySelector('.cashier-selection .alert-warning');
        if (cashierAlert) {
            cashierAlert.classList.add('hidden');
        }
        
        // Update button state
        updateSendButton();
    }
}

// Modified hideSelectedCashier function
function hideSelectedCashier() {
    document.getElementById('selectedCashier').style.display = 'none';
    // Show the cashier warning alert
    const cashierAlert = document.querySelector('.cashier-selection .alert-warning');
    if (cashierAlert) {
        cashierAlert.classList.remove('hidden');
    }
    
    // Update button state
    updateSendButton();
}

// Modified updateCart function to include button state update
function updateCart() {
    const cartItemsContainer = document.getElementById('cartItems');
    const cartCount = document.getElementById('cartCount');
    
    if (cart.length === 0) {
        cartItemsContainer.innerHTML = `
            <div class="empty-cart">
                <i data-lucide="shopping-cart"></i>
                <p>Votre panier est vide</p>
                <small>Recherchez et ajoutez des produits</small>
            </div>
        `;
        cartCount.textContent = '0';
    } else {
        cartItemsContainer.innerHTML = cart.map(item => `
            <div class="cart-item">
                <div class="cart-item-info">
                    <div class="cart-item-name">${item.name}</div>
                    <div class="cart-item-price">${item.price.toFixed(2)} XAF / unité</div>
                    <div class="quantity-controls">
                        <button class="quantity-btn" onclick="updateQuantity('${item.code}', -1)">
                            <i data-lucide="minus"></i>
                        </button>
                        <span class="quantity">${item.quantity}</span>
                        <button class="quantity-btn" onclick="updateQuantity('${item.code}', 1)">
                            <i data-lucide="plus"></i>
                        </button>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div class="item-total">${(item.price * item.quantity).toFixed(2)} XAF</div>
                    <button class="remove-btn" onclick="removeFromCart('${item.code}')">
                        <i data-lucide="trash-2"></i>
                    </button>
                </div>
            </div>
        `).join('');
        
        cartCount.textContent = cart.reduce((sum, item) => sum + item.quantity, 0);
    }
    
    updateSummary();
    updateSendButton(); // Add this line
    
    // Reinitialize icons after DOM update
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Modified sendToCashier function with improved validation
function sendToCashier() {
    if (!canSendToCart()) {
        if (cart.length === 0) {
            showErrorModal('Le panier est vide! Veuillez ajouter au moins un produit.');
        } else if (!selectedCashRegister) {
            showErrorModal('Veuillez sélectionner un caissier avant d\'envoyer le panier.');
        }
        return;
    }

    const clientMessage = selectedClient ? 
        `Client: ${selectedClient.name}<br>` : 
        'Aucun client sélectionné<br>';
    
    const totalAmount = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0) * 1.18;
    
    const modalHTML = `
        <div class="modal" style="display:block; position:fixed; z-index:1100; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
            <div class="modal-content" style="background-color:white; margin:15% auto; padding:20px; border-radius:8px; width:80%; max-width:500px;">
                <h3 style="margin-bottom:20px;">Confirmer l'envoi</h3>
                <div style="margin-bottom:20px;">
                    ${clientMessage}
                    <p><strong>Caissier:</strong> ${selectedCashRegister.cashierName}</p>
                    <p><strong>Total:</strong> ${totalAmount.toFixed(2)} XAF</p>
                    <p>Envoyer ce panier au caissier sélectionné?</p>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button onclick="this.closest('.modal').remove()" class="btn-secondary">Annuler</button>
                    <button onclick="proceedWithSale(); this.closest('.modal').remove();" class="btn-primary">Confirmer</button>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Modified displaySelectedCashier function
function displaySelectedCashier(option) {
    if (option && option.value) {
        const cashierName = option.getAttribute('data-cashier-name');
        const openingTime = option.getAttribute('data-opening-time');
        const pendingCarts = option.getAttribute('data-pending-carts');
        const initialAmount = option.getAttribute('data-initial-amount');
        
        selectedCashRegister = {
            id: option.value,
            cashierId: option.getAttribute('data-cashier-id'),
            cashierName: cashierName,
            openingTime: openingTime,
            pendingCarts: parseInt(pendingCarts),
            initialAmount: initialAmount
        };
        
        showSelectedCashier();
    } else {
        selectedCashRegister = null;
        hideSelectedCashier();
    }
}

// Modified initApp function to include the new CSS and initial button state
function initApp() {
    setFavicon();
    setupSidebar();
    addCustomCSS(); // Add this line
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Initialize Select2
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#clientSelect').select2({
            placeholder: 'Rechercher un client...',
            allowClear: true,
            language: {
                noResults: function() {
                    return "Aucun client trouvé";
                },
                searching: function() {
                    return "Recherche...";
                }
            }
        });
        
        $('#cashierSelect').select2({
            placeholder: 'Sélectionner un caissier...',
            language: {
                noResults: function() {
                    return "Aucun caissier disponible";
                }
            }
        });
        
        // Handle client selection
        $('#clientSelect').on('select2:select', function(e) {
            const data = e.params.data;
            if (data.id) {
                const option = $(this).find('option:selected');
                selectedClient = {
                    id: data.id,
                    name: option.data('name'),
                    tel: option.data('tel')
                };
                showSelectedClient();
            } else {
                selectedClient = null;
                hideSelectedClient();
            }
        });
        
        $('#clientSelect').on('select2:clear', function(e) {
            selectedClient = null;
            hideSelectedClient();
        });

        // Handle cashier selection
        $('#cashierSelect').on('select2:select', function(e) {
            const data = e.params.data;
            if (data.id) {
                const option = $(this).find('option:selected');
                selectedCashRegister = {
                    id: data.id,
                    cashierId: option.data('cashier-id'),
                    cashierName: option.data('cashier-name'),
                    openingTime: option.data('opening-time'),
                    pendingCarts: option.data('pending-carts'),
                    initialAmount: option.data('initial-amount')
                };
                showSelectedCashier();
            } else {
                selectedCashRegister = null;
                hideSelectedCashier();
            }
        });

        $('#cashierSelect').on('select2:clear', function(e) {
            selectedCashRegister = null;
            hideSelectedCashier();
        });
    }
    
    // Set initial button state
    updateSendButton();
    
    // Add event listeners
    const productSearchInput = document.getElementById('productSearch');
    if (productSearchInput) {
        productSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchProducts();
            }
        });
    }
}


    </script>


<script>
    // ============================================
// FIXED: BARCODE SCANNER + MANUAL SEARCH
// ============================================

// Scanner configuration
const SCANNER_CONFIG = {
    minBarcodeLength: 3,
    maxBarcodeLength: 50,
    scanTimeout: 100, // milliseconds between keystrokes
    preventDefaultKeys: false // Changed to false to allow typing in search
};

// Scanner state
let scannerBuffer = '';
let scannerTimeout = null;
let isScanning = false;
let isTypingInSearchBox = false;

/**
 * Initialize barcode scanner listener
 */
function initBarcodeScanner() {
    console.log('✓ Barcode scanner initialized');
    
    document.addEventListener('keypress', handleScannerInput);
    document.addEventListener('keydown', handleScannerKeyDown);
    
    // Track when user is typing in search box
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.addEventListener('focus', () => {
            isTypingInSearchBox = true;
            console.log('Search box focused - scanner paused');
        });
        
        searchInput.addEventListener('blur', () => {
            // Delay to allow Enter key to process
            setTimeout(() => {
                isTypingInSearchBox = false;
                console.log('Search box blurred - scanner active');
            }, 200);
        });
    }
    
    // Show scanner status indicator
    showScannerStatus('ready');
}

/**
 * Handle scanner keypress events
 */
function handleScannerInput(event) {
    // IMPORTANT: Don't intercept if user is typing in ANY input/textarea/select
    if (event.target.tagName === 'INPUT' || 
        event.target.tagName === 'TEXTAREA' || 
        event.target.tagName === 'SELECT') {
        return; // Let the user type normally
    }
    
    // Don't scan if explicitly typing in search box
    if (isTypingInSearchBox) {
        return;
    }
    
    // Start scanning mode
    if (!isScanning) {
        isScanning = true;
        scannerBuffer = '';
        showScannerStatus('scanning');
        console.log('Scanner: Started scanning');
    }
    
    // Add character to buffer
    const char = event.key;
    if (char && char.length === 1) {
        scannerBuffer += char;
        console.log('Scanner buffer:', scannerBuffer);
        
        // Only prevent default if NOT in an input field
        if (SCANNER_CONFIG.preventDefaultKeys && 
            event.target.tagName !== 'INPUT' && 
            event.target.tagName !== 'TEXTAREA') {
            event.preventDefault();
        }
    }
    
    // Reset timeout
    clearTimeout(scannerTimeout);
    scannerTimeout = setTimeout(processScan, SCANNER_CONFIG.scanTimeout);
}

/**
 * Handle scanner keydown events (for Enter key)
 */
function handleScannerKeyDown(event) {
    // Don't process if in search box
    if (isTypingInSearchBox || 
        event.target.tagName === 'INPUT' || 
        event.target.tagName === 'TEXTAREA') {
        return;
    }
    
    // Check for Enter key (scanner typically sends Enter after barcode)
    if (event.key === 'Enter' && isScanning && scannerBuffer.length > 0) {
        event.preventDefault();
        clearTimeout(scannerTimeout);
        console.log('Scanner: Enter detected, processing scan');
        processScan();
    }
}

/**
 * Process the scanned barcode
 */
function processScan() {
    const barcode = scannerBuffer.trim();
    
    console.log('Scanner: Processing barcode:', barcode);
    
    // Reset scanner state
    isScanning = false;
    scannerBuffer = '';
    
    // Validate barcode length
    if (barcode.length < SCANNER_CONFIG.minBarcodeLength || 
        barcode.length > SCANNER_CONFIG.maxBarcodeLength) {
        console.log('Scanner: Invalid barcode length:', barcode.length);
        showScannerStatus('ready');
        return;
    }
    
    console.log('Scanner: Valid barcode, searching:', barcode);
    showScannerStatus('processing');
    
    // Search for product by barcode
    searchProductByBarcode(barcode);
}

/**
 * Search for product by barcode and add to cart
 */
function searchProductByBarcode(barcode) {
    console.log('Scanner: Searching for barcode:', barcode);
    showScannerFeedback('searching', `Recherche: ${barcode}`);
    
    // Make AJAX request to search for product
    fetch('search_products.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ 
            query: barcode,
            exactMatch: true
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        console.log('Scanner: Raw response:', text.substring(0, 100));
        
        try {
            const data = JSON.parse(text);
            return data;
        } catch (e) {
            console.error('Scanner: JSON parse error:', e);
            throw new Error('Réponse invalide du serveur');
        }
    })
    .then(data => {
        console.log('Scanner: Parsed data:', data);
        
        if (data.success && data.products && data.products.length > 0) {
            const product = data.products[0];
            
            if (!product.code || !product.name || !product.sellingPrice) {
                throw new Error('Données produit invalides');
            }
            
            const price = parseFloat(product.sellingPrice);
            const stock = parseInt(product.stock || 0);
            
            if (stock <= 0) {
                showScannerFeedback('error', `Stock épuisé: ${product.name}`);
                playErrorSound();
                showScannerStatus('ready');
                return;
            }
            
            const existingItem = cart.find(item => item.code === product.code);
            
            if (existingItem) {
                if (existingItem.quantity >= stock) {
                    showScannerFeedback('warning', `Stock max atteint: ${product.name} (${stock})`);
                    playWarningSound();
                    showScannerStatus('ready');
                    return;
                }
                
                existingItem.quantity++;
                showScannerFeedback('success', `${product.name} (×${existingItem.quantity})`);
            } else {
                cart.push({
                    code: product.code,
                    name: product.name,
                    price: price,
                    quantity: 1,
                    stock: stock
                });
                showScannerFeedback('success', `Ajouté: ${product.name}`);
            }
            
            updateCart();
            playSuccessSound();
            showScannerStatus('ready');
            
        } else {
            showScannerFeedback('error', `Produit introuvable: ${barcode}`);
            playErrorSound();
            showScannerStatus('ready');
        }
    })
    .catch(error => {
        console.error('Scanner: Search error:', error);
        showScannerFeedback('error', `Erreur: ${error.message}`);
        playErrorSound();
        showScannerStatus('ready');
    });
}

/**
 * MANUAL SEARCH FUNCTION (separate from scanner)
 */
function searchProducts() {
    const searchInput = document.getElementById('productSearch');
    const query = searchInput ? searchInput.value.trim() : '';
    
    console.log('Manual search: Query:', query);
    
    if (query.length < 2) {
        showSearchError('Au moins 2 caractères requis');
        return;
    }
    
    const resultsContainer = document.getElementById('productResults');
    if (!resultsContainer) {
        console.error('Results container not found');
        return;
    }
    
    resultsContainer.innerHTML = `
        <div class="empty-cart">
            <i data-lucide="loader" style="animation: spin 1s linear infinite;"></i>
            <p>Recherche en cours...</p>
        </div>
    `;
    
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    fetch('search_products.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ 
            query: query,
            exactMatch: false // Manual search uses fuzzy matching
        })
    })
    .then(response => {
        console.log('Manual search: Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        console.log('Manual search: Raw response:', text.substring(0, 100));
        
        try {
            const data = JSON.parse(text);
            return data;
        } catch (e) {
            console.error('Manual search: JSON parse error:', e);
            throw new Error('Réponse invalide');
        }
    })
    .then(data => {
        console.log('Manual search: Data:', data);
        
        if (data.success) {
            displaySearchResults(data.products);
        } else {
            showSearchError(data.message || 'Erreur de recherche');
        }
    })
    .catch(error => {
        console.error('Manual search: Error:', error);
        showSearchError('Erreur: ' + error.message);
    });
}

/**
 * Display search results
 */
function displaySearchResults(products) {
    const resultsContainer = document.getElementById('productResults');
    
    if (!resultsContainer) return;
    
    console.log('Displaying', products.length, 'products');
    
    if (!products || products.length === 0) {
        resultsContainer.innerHTML = `
            <div class="empty-cart">
                <i data-lucide="search"></i>
                <p>Aucun produit trouvé</p>
                <small>Essayez d'autres mots-clés</small>
            </div>
        `;
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
        return;
    }

    const productsHTML = products.map(product => {
        const code = escapeHtml(product.code || '');
        const name = escapeHtml(product.name || '');
        const price = parseFloat(product.sellingPrice || 0);
        const stock = parseInt(product.stock || 0);
        const priceFormatted = price.toFixed(2);
        const priceXaf = price.toLocaleString('fr-FR', {
            minimumFractionDigits: 0, 
            maximumFractionDigits: 0
        });
        
        let stockClass = '';
        let stockText = `Stock: ${stock}`;
        if (stock < 10) {
            stockClass = 'text-warning';
            stockText = `Stock faible: ${stock}`;
        }
        if (stock === 0) {
            stockClass = 'text-danger';
            stockText = 'Rupture';
        }
        
        return `
            <div class="product-item">
                <div class="product-info">
                    <div class="product-name">${name}</div>
                    <div class="product-details ${stockClass}">
                        Code: ${code} • ${stockText}
                    </div>
                </div>
                <div class="product-price">${priceXaf} XAF</div>
                <button class="add-btn" onclick="addToCart('${code}', '${name}', ${price}, ${stock})" ${stock === 0 ? 'disabled' : ''}>
                    <i data-lucide="plus"></i>
                </button>
            </div>
        `;
    }).join('');
    
    resultsContainer.innerHTML = productsHTML;
    
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

/**
 * Show search error
 */
function showSearchError(message) {
    const resultsContainer = document.getElementById('productResults');
    if (!resultsContainer) return;
    
    resultsContainer.innerHTML = `
        <div class="empty-cart">
            <i data-lucide="alert-triangle" style="color: #dc2626;"></i>
            <p style="color: #dc2626;">Erreur</p>
            <small>${escapeHtml(message)}</small>
        </div>
    `;
    
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

/**
 * Helper: Escape HTML
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
}

/**
 * Show scanner status indicator
 */
function showScannerStatus(status) {
    let indicator = document.getElementById('scannerStatusIndicator');
    
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = 'scannerStatusIndicator';
        indicator.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            z-index: 2000;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        `;
        document.body.appendChild(indicator);
    }
    
    const statusConfig = {
        ready: {
            bg: '#10b981',
            color: 'white',
            icon: 'scan',
            text: 'Scanner prêt'
        },
        scanning: {
            bg: '#3b82f6',
            color: 'white',
            icon: 'loader',
            text: 'Lecture...'
        },
        processing: {
            bg: '#f59e0b',
            color: 'white',
            icon: 'loader',
            text: 'Traitement...'
        }
    };
    
    const config = statusConfig[status] || statusConfig.ready;
    indicator.style.backgroundColor = config.bg;
    indicator.style.color = config.color;
    indicator.innerHTML = `
        <i data-lucide="${config.icon}" style="width: 16px; height: 16px;"></i>
        <span>${config.text}</span>
    `;
    
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

/**
 * Show scanner feedback
 */
function showScannerFeedback(type, message) {
    if (type === 'error' || type === 'warning') {
        showScannerAlert(type, message);
        return;
    }
    
    const existingFeedback = document.getElementById('scannerFeedback');
    if (existingFeedback) {
        existingFeedback.remove();
    }
    
    const typeConfig = {
        success: {
            bg: '#d1fae5',
            border: '#a7f3d0',
            color: '#065f46',
            icon: 'check-circle'
        },
        searching: {
            bg: '#dbeafe',
            border: '#93c5fd',
            color: '#1e40af',
            icon: 'search'
        }
    };
    
    const config = typeConfig[type] || typeConfig.success;
    
    const feedback = document.createElement('div');
    feedback.id = 'scannerFeedback';
    feedback.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        background: ${config.bg};
        border: 1px solid ${config.border};
        color: ${config.color};
        font-size: 14px;
        font-weight: 600;
        z-index: 2000;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        animation: slideIn 0.3s ease;
        max-width: 400px;
    `;
    
    feedback.innerHTML = `
        <i data-lucide="${config.icon}" style="width: 20px; height: 20px;"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(feedback);
    
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    setTimeout(() => {
        feedback.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => feedback.remove(), 300);
    }, 2000);
}

/**
 * Show scanner alert
 */
function showScannerAlert(type, message) {
    const existingAlert = document.getElementById('scannerAlert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    const typeConfig = {
        error: {
            bg: '#fee2e2',
            headerBg: '#dc2626',
            color: '#991b1b',
            icon: 'x-circle',
            title: 'Erreur de Scan'
        },
        warning: {
            bg: '#fef3c7',
            headerBg: '#f59e0b',
            color: '#92400e',
            icon: 'alert-triangle',
            title: 'Attention'
        }
    };
    
    const config = typeConfig[type] || typeConfig.error;
    
    const modal = document.createElement('div');
    modal.id = 'scannerAlert';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.2s ease;
    `;
    
    modal.innerHTML = `
        <div style="
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
            overflow: hidden;
        ">
            <div style="
                background: ${config.headerBg};
                color: white;
                padding: 20px;
                display: flex;
                align-items: center;
                gap: 12px;
            ">
                <i data-lucide="${config.icon}" style="width: 28px; height: 28px;"></i>
                <h3 style="margin: 0; font-size: 20px; font-weight: 700;">${config.title}</h3>
            </div>
            
            <div style="padding: 25px; background: ${config.bg};">
                <p style="margin: 0; color: ${config.color}; font-size: 16px; line-height: 1.6; font-weight: 600;">
                    ${message}
                </p>
            </div>
            
            <div style="padding: 20px; display: flex; justify-content: flex-end; background: white; border-top: 1px solid #e5e7eb;">
                <button onclick="document.getElementById('scannerAlert').remove()" style="
                    background: ${config.headerBg};
                    color: white;
                    border: none;
                    padding: 12px 30px;
                    border-radius: 8px;
                    font-weight: 600;
                    font-size: 15px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                ">
                    <i data-lucide="check" style="width: 18px; height: 18px;"></i>
                    OK
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

/**
 * Sound functions
 */
function playSuccessSound() {
    try {
        const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZiTYIG2m98OScTgwOUKfj8LdjHAU7k9n0y3krBSh+zPLaizsKGGS57OihUBELTKXh8bllHgU2jdXzzn0vBSZ8yvLbjTwLF2O56+mjUhENTqvj8rhlHgU4kNn1zHksBSV6yPLajjsKGGS56+mjUhEMTqvj8bhlHgU4kNn1zHksBSZ8yvLbjTwLGGS56+mjUhENTqvj8bhlHgU4kNn1zHksBSZ8yvLbjTwLGGS56+mjUhENTqvj8bhlHgU4kNn1zHksBSZ8yvLbjTwLGGS56+mjUhENTqvj8bhlHgU4kNn1zHksBSZ8yvLbjTwLGGS56+mjUhENTqvj8bhlHgU4kNn1zHks');
        audio.volume = 0.3;
        audio.play().catch(() => {});
    } catch (e) {}
}

function playErrorSound() {
    try {
        const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAAB/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/f39/');
        audio.volume = 0.3;
        audio.play().catch(() => {});
    } catch (e) {}
}

function playWarningSound() {
    try {
        const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACAgoSGiImLjI2Oj5CRkpOUlZaXmJmam5ydnp+goaKjpKWmp6ipqqusra6vsLGys7S1tre4ubq7vL2+v8DBwsPExcbHyMnKy8zNzs/Q0dLT1NXW19jZ2tvc3d7f4OHi4+Tl5ufo6err7O3u7/Dx8vP09fb3+Pn6+/z9/v+AgYKDhIWGh4iJiouMjY6PkJGSk5SVlpeYmZqbnJ2en6ChoqOkpaanqKmqq6ytrq+wsbKztLW2t7i5uru8vb6/wMHCw8TFxsfIycrLzM3Oz9DR0tPU1dbX2Nna29zd3t/g4eLj5OXm5+jp6uvs7e7v8PHy8/T19vf4+fr7/P3+/4CBgoOEhYaHiImKi4yNjo+QkZKTlJWWl5iZmpucnZ6foKGio6SlpqeoqaqrrK2ur7CxsrO0tba3uLm6u7y9vr/AwcLDxMXGx8jJysvMzc7P0NHS09TV1tfY2drb3N3e3+Dh4uPk5ebn6Onq6+zt7u/w8fLz9PX29/j5+vv8/f7/gIGCg4SFhoeIiYqLjI2Oj5CRkpOUlZaXmJmam5ydnp+goaKjpKWmp6ipqqusra6vsLGys7S1tre4ubq7vL2+v8DBwsPExcbHyMnKy8zNzs/Q0dLT1NXW19jZ2tvc3d7f4OHi4+Tl5ufo6err7O3u7/Dx8vP09fb3+Pn6+/z9/v8=');
        audio.volume = 0.3;
        audio.play().catch(() => {});
    } catch (e) {}
}

/**
 * Add animation styles
 */
function addScannerStyles() {
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(400px); opacity: 0; }
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .text-warning { color: #f59e0b; }
        .text-danger { color: #dc2626; }
    `;
    document.head.appendChild(style);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    addScannerStyles();
    initBarcodeScanner();
    
    // Setup manual search
    const searchInput = document.getElementById('productSearch');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                searchProducts();
            }
        });
    }
    
    const searchBtn = document.querySelector('.search-btn');
    if (searchBtn) {
        searchBtn.addEventListener('click', function(e) {
            e.preventDefault();
            searchProducts();
        });
    }
    
    console.log('✓ Scanner and manual search ready');
});

// Export
window.initBarcodeScanner = initBarcodeScanner;
window.searchProductByBarcode = searchProductByBarcode;
window.searchProducts = searchProducts;
</script>


</body>
</html>

<?php 
// Remove in production - this is for debugging only
if (error_get_last()) {
    error_log("PHP Error in sale page: " . print_r(error_get_last(), true));
} else {
    // echo "No errors"; --- IGNORE ---
}