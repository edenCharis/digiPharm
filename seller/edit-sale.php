<?php
session_start();
if($_SESSION["role"] === "SELLER" && $_SESSION["id"] == session_id()){

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    include '../config/database.php';
    
    if (!isset($db)) {
        throw new Exception('Database connection not found');
    }

    // Get cart ID from URL
    $cartId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$cartId) {
        throw new Exception('ID de commande invalide');
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update') {
            // Update cart info
            $cartName = trim($_POST['cart_name'] ?? '');
            $clientId = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;
            $status = $_POST['status'] ?? 'pending';
            
            $updateCartSql = "UPDATE carts SET name = ?, client_id = ?, status = ? WHERE id = ?";
            $db->query($updateCartSql, [$cartName, $clientId, $status, $cartId]);
            // Delete existing items
            $db->query("DELETE FROM cart_items WHERE cart_id = ?", [$cartId]);
            
            // Insert updated items
            if (isset($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $item) {
                    if (empty($item['product_id']) || empty($item['quantity'])) continue;
                    
                    $productId = (int)$item['product_id'];
                    $quantity = (int)$item['quantity'];
                    $unitPrice = (float)$item['unit_price'];
                    
                    $insertItemSql = "INSERT INTO cart_items (cart_id, product_id, quantity, unit_price) 
                                     VALUES (?, ?, ?, ?)";
                    $db->query($insertItemSql, [$cartId, $productId, $quantity, $unitPrice]);
                }
            }
            
            $_SESSION['success_message'] = 'Vente modifiée avec succès';
            header('Location: historique.php');
            exit;
            
        } elseif ($action === 'delete') {
            // Delete cart items first
            $db->query("DELETE FROM cart_items WHERE cart_id = ?", [$cartId]);
            
            // Delete cart
            $db->query("DELETE FROM carts WHERE id = ?", [$cartId]);
            
            $_SESSION['success_message'] = 'Vente supprimée avec succès (remboursement)';
            header('Location: historique.php');
            exit;
        }
    }

    // Fetch cart details
    $cartSql = "SELECT carts.*, cl.name as client_name
                FROM carts 
                LEFT JOIN client cl ON carts.client_id = cl.id
                WHERE carts.id = ?";
    $cartResult = $db->fetchAll($cartSql, [$cartId]);
    
    if (!$cartResult || empty($cartResult)) {
        throw new Exception('Commande introuvable');
    }
    
    $cart = $cartResult[0];

    // Fetch cart items
    $itemsSql = "SELECT ci.*, p.name as product_name, p.code as product_code, 
                        p.description as product_description, p.price as current_price,
                        c.name as category_name
                 FROM cart_items ci
                 LEFT JOIN category c ON p.categoryId = c.id
                 WHERE ci.cart_id = ?
                 ORDER BY ci.id";
    $items = $db->fetchAll($itemsSql, [$cartId]);
    
    if ($items === false) {
        $items = [];
    }

    // Fetch all clients for dropdown
    $clientsSql = "SELECT id, name FROM client ORDER BY name";
    $clients = $db->fetchAll($clientsSql, []);
    
    if ($clients === false) {
        $clients = [];
    }

    // Fetch all products for search
    $productsSql = "SELECT p.id, p.name, p.code, p.description, p.price, p.stock, c.name as category_name
                    FROM product p
                    LEFT JOIN category c ON p.categoryId = c.id
                    WHERE p.stock > 0
                    ORDER BY p.name";
    $products = $db->fetchAll($productsSql, []);
    
    if ($products === false) {
        $products = [];
    }

} catch (Exception $e) {
    error_log('Edit sale page error: ' . $e->getMessage());
    die('Error: ' . $e->getMessage() . '<br><br><a href="historique.php">Retour à l\'historique</a>');
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys - Modifier la vente #<?php echo $cartId; ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/umd/lucide.js"></script>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .edit-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            color: #059669;
            font-size: 1.875rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-back {
            background: #6b7280;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: #4b5563;
            color: white;
        }

        .form-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
        }

        .form-group input,
        .form-group select {
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        .product-search {
            position: relative;
            margin-bottom: 1rem;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            width: 1.25rem;
            height: 1.25rem;
        }

        .product-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            margin-top: 0.25rem;
            max-height: 300px;
            overflow-y: auto;
            z-index: 10;
            display: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .product-results.active {
            display: block;
        }

        .product-result-item {
            padding: 0.75rem;
            cursor: pointer;
            border-bottom: 1px solid #f3f4f6;
            transition: background 0.2s;
        }

        .product-result-item:hover {
            background: #f9fafb;
        }

        .product-result-item:last-child {
            border-bottom: none;
        }

        .product-name {
            font-weight: 500;
            color: #111827;
        }

        .product-code {
            font-family: 'Courier New', monospace;
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        .product-price {
            color: #059669;
            font-weight: 600;
            margin-top: 0.25rem;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .items-table th {
            background: #f8fafc;
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
            font-size: 0.875rem;
        }

        .items-table td {
            padding: 0.75rem;
            border-bottom: 1px solid #f3f4f6;
        }

        .items-table input {
            padding: 0.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            width: 100%;
        }

        .items-table input:focus {
            outline: none;
            border-color: #059669;
        }

        .btn-remove {
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .btn-remove:hover {
            background: #dc2626;
        }

        .no-items {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
        }

        .total-section {
            background: #f9fafb;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-top: 1.5rem;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
        }

        .total-amount {
            color: #059669;
            font-size: 1.5rem;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            border: none;
            font-size: 0.875rem;
        }

        .btn-save {
            background: #059669;
            color: white;
        }

        .btn-save:hover {
            background: #047857;
        }

        .btn-delete {
            background: #ef4444;
            color: white;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .btn-cancel {
            background: #6b7280;
            color: white;
        }

        .btn-cancel:hover {
            background: #4b5563;
        }

        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fbbf24;
        }

        @media (max-width: 768px) {
            .edit-container {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .items-table-container {
                overflow-x: auto;
            }

            .items-table {
                min-width: 600px;
            }

            .form-actions {
                flex-direction: column;
            }
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-container {
            background: white;
            border-radius: 0.75rem;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
        }

        .modal-message {
            color: #6b7280;
            margin-bottom: 1.5rem;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
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
                    <div class="page-header">
                        <h1 class="page-title">
                            <i data-lucide="edit"></i>
                            Modifier la vente #<?php echo $cartId; ?>
                        </h1>
                        <a href="historique.php" class="btn-back">
                            <i data-lucide="arrow-left"></i>
                            Retour à l'historique
                        </a>
                    </div>

                    <div class="alert alert-warning">
                        <i data-lucide="alert-triangle"></i>
                        <div>
                            <strong>Attention :</strong> La modification d'une vente affectera les statistiques et le stock. 
                            Assurez-vous que les modifications sont correctes avant de sauvegarder.
                        </div>
                    </div>

                    <form id="editSaleForm" method="POST">
                        <input type="hidden" name="action" value="update">
                        
                        <!-- Cart Information -->
                        <div class="form-card">
                            <h2 class="section-title">
                                <i data-lucide="info"></i>
                                Informations de la commande
                            </h2>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="cart_name">Nom de la commande</label>
                                    <input 
                                        type="text" 
                                        id="cart_name" 
                                        name="cart_name" 
                                        value="<?php echo htmlspecialchars($cart['name'] ?? ''); ?>"
                                        placeholder="Ex: Commande du matin"
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="client_id">Client</label>
                                    <select id="client_id" name="client_id">
                                        <option value="">Client anonyme</option>
                                        <?php foreach ($clients as $client): ?>
                                            <option 
                                                value="<?php echo $client['id']; ?>"
                                                <?php echo ($cart['client_id'] == $client['id']) ? 'selected' : ''; ?>
                                            >
                                                <?php echo htmlspecialchars($client['name']); ?>

                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="status">Statut</label>
                                    <select id="status" name="status">
                                        <option value="pending" <?php echo ($cart['status'] === 'pending') ? 'selected' : ''; ?>>
                                            En attente
                                        </option>
                                        <option value="completed" <?php echo ($cart['status'] === 'completed') ? 'selected' : ''; ?>>
                                            Complété
                                        </option>
                                        <option value="cancelled" <?php echo ($cart['status'] === 'cancelled') ? 'selected' : ''; ?>>
                                            Annulé
                                        </option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Date de création</label>
                                    <input 
                                        type="text" 
                                        value="<?php echo date('d/m/Y H:i', strtotime($cart['created_at'])); ?>"
                                        disabled
                                        style="background: #f3f4f6; color: #6b7280;"
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Cart Items -->
                        <div class="form-card">
                            <h2 class="section-title">
                                <i data-lucide="package"></i>
                                Articles de la commande
                            </h2>

                            <div class="product-search">
                                <i data-lucide="search" class="search-icon"></i>
                                <input 
                                    type="text" 
                                    id="productSearch" 
                                    class="search-input" 
                                    placeholder="Rechercher un produit à ajouter..."
                                    autocomplete="off"
                                >
                                <div id="productResults" class="product-results"></div>
                            </div>

                            <div class="items-table-container">
                                <table class="items-table">
                                    <thead>
                                        <tr>
                                            <th>Produit</th>
                                            <th>Code</th>
                                            <th>Prix unitaire</th>
                                            <th>Quantité</th>
                                            <th>Total</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemsTableBody">
                                        <?php if (empty($items)): ?>
                                            <tr id="noItemsRow">
                                                <td colspan="6" class="no-items">
                                                    Aucun article. Utilisez la recherche pour ajouter des produits.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($items as $index => $item): ?>
                                                <tr class="item-row" data-product-id="<?php echo $item['product_id']; ?>">
                                                    <td>
                                                        <div class="product-name">
                                                            <?php echo htmlspecialchars($item['product_name'] ?? 'Produit supprimé'); ?>
                                                        </div>
                                                        <input type="hidden" name="items[<?php echo $index; ?>][product_id]" value="<?php echo $item['product_id']; ?>">
                                                    </td>
                                                    <td><?php echo htmlspecialchars($item['product_code'] ?? 'N/A'); ?></td>
                                                    <td>
                                                        <input 
                                                            type="number" 
                                                            name="items[<?php echo $index; ?>][unit_price]" 
                                                            value="<?php echo $item['unit_price']; ?>"
                                                            min="0"
                                                            step="0.01"
                                                            class="item-price-input"
                                                            required
                                                        >
                                                    </td>
                                                    <td>
                                                        <input 
                                                            type="number" 
                                                            name="items[<?php echo $index; ?>][quantity]" 
                                                            value="<?php echo $item['quantity']; ?>"
                                                            min="1"
                                                            class="item-quantity-input"
                                                            required
                                                        >
                                                    </td>
                                                    <td class="item-total-cell">
                                                        <?php echo number_format($item['quantity'] * $item['unit_price'], 0); ?> XAF
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn-remove" onclick="removeItem(this)">
                                                            <i data-lucide="trash-2"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="total-section">
                                <div class="total-row">
                                    <span>Total de la commande :</span>
                                    <span id="totalAmount" class="total-amount">0 XAF</span>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="form-actions">
                            <a href="historique.php" class="btn btn-cancel">
                                <i data-lucide="x"></i>
                                Annuler
                            </a>
                            <button type="button" class="btn btn-delete" onclick="confirmDelete()">
                                <i data-lucide="trash-2"></i>
                                Supprimer (Remboursement)
                            </button>
                            <button type="submit" class="btn btn-save">
                                <i data-lucide="save"></i>
                                Sauvegarder les modifications
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-overlay">
        <div class="modal-container">
            <h3 class="modal-title">
                <i data-lucide="alert-triangle" style="color: #ef4444;"></i>
                Confirmer la suppression
            </h3>
            <p class="modal-message">
                Êtes-vous sûr de vouloir supprimer cette vente ? Cette action est irréversible et 
                sera considérée comme un remboursement. Tous les articles de cette commande seront supprimés.
            </p>
            <div class="modal-actions">
                <button type="button" class="btn btn-cancel" onclick="closeDeleteModal()">
                    Annuler
                </button>
                <button type="button" class="btn btn-delete" onclick="deleteSale()">
                    <i data-lucide="trash-2"></i>
                    Confirmer la suppression
                </button>
            </div>
        </div>
    </div>

    <script>
        // Products data from PHP
        const products = <?php echo json_encode($products); ?>;
        let itemCounter = <?php echo count($items); ?>;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            calculateTotal();
            setupProductSearch();
            setupItemListeners();
        });

        // Product search
        function setupProductSearch() {
            const searchInput = document.getElementById('productSearch');
            const resultsContainer = document.getElementById('productResults');

            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                
                if (searchTerm.length < 2) {
                    resultsContainer.classList.remove('active');
                    return;
                }

                const filtered = products.filter(p => 
                    p.name.toLowerCase().includes(searchTerm) || 
                    p.code.toLowerCase().includes(searchTerm)
                );

                if (filtered.length > 0) {
                    displaySearchResults(filtered);
                    resultsContainer.classList.add('active');
                } else {
                    resultsContainer.classList.remove('active');
                }
            });

            // Close results when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
                    resultsContainer.classList.remove('active');
                }
            });
        }

        function displaySearchResults(results) {
            const container = document.getElementById('productResults');
            container.innerHTML = results.map(product => `
                <div class="product-result-item" onclick="addProduct(${product.id}, '${escapeHtml(product.name)}', '${escapeHtml(product.code)}', ${product.price})">
                    <div class="product-name">${escapeHtml(product.name)}</div>
                    <div class="product-code">${escapeHtml(product.code)}</div>
                    <div class="product-price">${formatPrice(product.price)} XAF - Stock: ${product.stock_quantity}</div>
                </div>
            `).join('');
        }

        function addProduct(productId, productName, productCode, price) {
            // Check if product already exists
            const existingRow = document.querySelector(`tr[data-product-id="${productId}"]`);
            if (existingRow) {
                const qtyInput = existingRow.querySelector('.item-quantity-input');
                qtyInput.value = parseInt(qtyInput.value) + 1;
                updateItemTotal(qtyInput);
                calculateTotal();
                
                document.getElementById('productSearch').value = '';
                document.getElementById('productResults').classList.remove('active');
                return;
            }

            const tbody = document.getElementById('itemsTableBody');
            const noItemsRow = document.getElementById('noItemsRow');
            
            if (noItemsRow) {
                noItemsRow.remove();
            }

            const row = document.createElement('tr');
            row.className = 'item-row';
            row.setAttribute('data-product-id', productId);
            row.innerHTML = `
                <td>
                    <div class="product-name">${escapeHtml(productName)}</div>
                    <input type="hidden" name="items[${itemCounter}][product_id]" value="${productId}">
                </td>
                <td>${escapeHtml(productCode)}</td>
                <td>
                    <input 
                        type="number" 
                        name="items[${itemCounter}][unit_price]" 
                        value="${price}"
                        min="0"
                        step="0.01"
                        class="item-price-input"
                        required
                    >
                </td>
                <td>
                    <input 
                        type="number" 
                        name="items[${itemCounter}][quantity]" 
                        value="1"
                        min="1"
                        class="item-quantity-input"
                        required
                    >
                </td>
                <td class="item-total-cell">${formatPrice(price)} XAF</td>
                <td>
                    <button type="button" class="btn-remove" onclick="removeItem(this)">
                        <i data-lucide="trash-2"></i>
                    </button>
                </td>
            `;