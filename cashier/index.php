<?php
session_start();
if($_SESSION["role"] === "CASHIER" && $_SESSION["id"] == session_id()){

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Include database connection
    include '../config/database.php';
    
    // Check if database connection exists
    if (!isset($db)) {
        throw new Exception('Database connection not found');
    }

    // Get cashier statistics
    $cashierId = $_SESSION["user_id"];
    $today = date('Y-m-d ');

    // Pending carts count
    $pendingCartsQuery = "SELECT COUNT(*) as count
                      FROM carts c
                      JOIN cash_register cr ON c.cash_register_id = cr.id
                      WHERE c.status = 'pending' AND cr.cashier_id = ? AND c.pharmacy_id = ?";

$pendingCartsResult = $db->fetch($pendingCartsQuery, [$cashierId, $pharmacyId]);
$pendingCarts = $pendingCartsResult ? $pendingCartsResult['count'] : 0;

    // Today's completed sales
    $completedSalesQuery = "SELECT COUNT(*) as count, COALESCE(SUM(totalAmount), 0) as total
                           FROM sale join cash_register cr ON sale.cash_register_id = cr.id
                           WHERE DATE(createdAt) = ? AND cashier_id = ? AND sale.pharmacy_id = ?";
    $completedSalesResult = $db->fetch($completedSalesQuery, [$today, $cashierId, $pharmacyId]);
    $completedSales = $completedSalesResult ? $completedSalesResult['count'] : 0;
    $todayTotal = $completedSalesResult ? $completedSalesResult['total'] : 0;

    // This week's sales
    $weekStart = date('Y-m-d', strtotime('monday this week'));
    $weekSalesQuery = "SELECT COUNT(*) as count, COALESCE(SUM(totalAmount), 0) as total
                      FROM sale Join cash_register cr ON sale.cash_register_id = cr.id
                      WHERE DATE(createdAt) >= ? AND cashier_id = ? AND sale.pharmacy_id = ?";
    $weekSalesResult = $db->fetch($weekSalesQuery, [$weekStart, $cashierId, $pharmacyId]);
    $weekSales = $weekSalesResult ? $weekSalesResult['count'] : 0;
    $weekTotal = $weekSalesResult ? $weekSalesResult['total'] : 0;

    // Average sale amount
    $avgSaleAmount = $completedSales > 0 ? $todayTotal / $completedSales : 0;

    // Recent completed sales (last 10)
    $recentSalesQuery = "SELECT s.*, c.name as clientName, sel.username as sellerName
                        FROM sale s
                        LEFT JOIN client c ON s.clientId = c.id
                        LEFT JOIN user sel ON s.sellerId = sel.id
                        JOIN cash_register cr ON s.cash_register_id = cr.id
                        WHERE cr.cashier_id = ? AND s.pharmacy_id = ?
                        ORDER BY s.createdAt DESC
                        LIMIT 10";
    $recentSales = $db->fetchAll($recentSalesQuery, [$cashierId, $pharmacyId]);
    if (!$recentSales) $recentSales = [];

    // Current pending carts with details
    $pendingCartsDetailQuery = "SELECT c.*, sel.username as sellerName, cl.name as clientName,
                               COUNT(ci.id) as itemCount
                               FROM carts c
                               LEFT JOIN user sel ON c.seller_id = sel.id
                               LEFT JOIN client cl ON c.client_id = cl.id
                               LEFT JOIN cart_items ci ON c.id = ci.cart_id
                               WHERE c.status = 'pending' AND c.pharmacy_id = ?
                               GROUP BY c.id
                               ORDER BY c.created_at ASC
                               LIMIT 8";
    $pendingCartsDetail = $db->fetchAll($pendingCartsDetailQuery, [$pharmacyId]);
    if (!$pendingCartsDetail) $pendingCartsDetail = [];

    // Low stock alerts
    $lowStockQuery = "SELECT code, name, stock FROM product WHERE stock <= 5 AND stock > 0 AND pharmacy_id = ? ORDER BY stock ASC LIMIT 5";
    $lowStockProducts = $db->fetchAll($lowStockQuery, [$pharmacyId]);
    if (!$lowStockProducts) $lowStockProducts = [];

} catch (Exception $e) {
    // Log error and show user-friendly message
    error_log('Cashier dashboard error: ' . $e->getMessage());
    die('Error loading dashboard: ' . $e->getMessage() . '<br><br><a href="../logout.php">Logout</a>');
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys - Caisse</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/umd/lucide.js"></script>
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/seller-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    <style>
        .cashier-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .urgent-card {
            border-left: 4px solid #f59e0b;
            background: linear-gradient(135deg, #fef3c7 0%, #fbbf24 100%);
            color: #92400e;
        }

        .urgent-card .stat-icon {
            background: rgba(146, 64, 14, 0.1);
            color: #92400e;
        }

        .pending-carts-section {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .section-header {
            background: #f8fafc;
            padding: 1.5rem;
            border-bottom: 1px solid var(--ds-border);
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .section-title {
            color: var(--ds-green);
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-action {
            color: var(--ds-green);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .section-action:hover {
            color: #047857;
        }

        .cart-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .cart-item {
            display: flex;
            align-items: center;
            justify-content: between;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--ds-surface-alt);
            transition: all 0.2s;
        }

        .cart-item:hover {
            background: var(--ds-surface-alt);
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
        }

        .cart-avatar {
            width: 3rem;
            height: 3rem;
            background: var(--ds-green);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.125rem;
        }

        .cart-details {
            flex: 1;
        }

        .cart-seller {
            font-weight: 600;
            color: var(--ds-text-900);
            margin-bottom: 0.25rem;
        }

        .cart-client {
            color: var(--ds-text-400);
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        .cart-time {
            color: var(--ds-text-400);
            font-size: 0.75rem;
        }

        .cart-meta {
            text-align: right;
        }

        .cart-items-count {
            background: #e0f2fe;
            color: #0277bd;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .cart-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-process {
            background: var(--ds-green);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .btn-process:hover {
            background: var(--ds-green);
            color: white;
            transform: translateY(-1px);
        }

        .recent-sales-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .recent-sales-table {
            width: 100%;
            border-collapse: collapse;
        }

        .recent-sales-table th {
            background: #f8fafc;
            color: var(--ds-text-900);
            font-weight: 600;
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--ds-border);
            font-size: 0.875rem;
        }

        .recent-sales-table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--ds-surface-alt);
            color: var(--ds-text-900);
            font-size: 0.875rem;
        }

        .recent-sales-table tbody tr:hover {
            background: var(--ds-surface-alt);
        }

        .invoice-number {
            font-family: 'Courier New', monospace;
            background: var(--ds-surface-alt);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 500;
        }

        .amount {
            font-weight: 600;
            color: var(--ds-green);
        }

        .low-stock-list {
            padding: 1.5rem;
        }

        .low-stock-item {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--ds-surface-alt);
        }

        .low-stock-item:last-child {
            border-bottom: none;
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-weight: 500;
            color: var(--ds-text-900);
            margin-bottom: 0.25rem;
        }

        .product-code {
            font-family: 'Courier New', monospace;
            font-size: 0.75rem;
            color: var(--ds-text-400);
        }

        .stock-level {
            background: #fef2f2;
            color: #dc2626;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .stock-level.critical {
            background: #dc2626;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--ds-text-400);
        }

        .empty-state i {
            width: 3rem;
            height: 3rem;
            margin-bottom: 1rem;
            color: var(--ds-border);
        }

        @media (max-width: 1024px) {
            .recent-sales-grid {
                grid-template-columns: 1fr;
            }

            .cashier-stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .cart-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .cart-meta {
                text-align: left;
                margin-top: 0.5rem;
            }

            .cart-actions {
                justify-content: flex-start;
                margin-top: 0.5rem;
            }
        }
        
        
        .btn-delete {
    background: #ef4444;
    color: white;
    border: none;
    padding: 0.5rem;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-delete:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

.btn-delete i {
    width: 1rem;
    height: 1rem;
}

/* Modal styles */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.modal-overlay.show {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 0.75rem;
    padding: 2rem;
    max-width: 400px;
    width: 90%;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

.modal-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.modal-icon {
    width: 3rem;
    height: 3rem;
    background: #fef2f2;
    color: #dc2626;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--ds-text-900);
    margin: 0;
}

.modal-body {
    color: var(--ds-text-400);
    margin-bottom: 1.5rem;
}

.modal-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}

.btn-cancel {
    background: var(--ds-surface-alt);
    color: var(--ds-text-900);
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-cancel:hover {
    background: var(--ds-border);
}

.btn-confirm {
    background: #dc2626;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-confirm:hover {
    background: #b91c1c;
}

.btn-confirm:disabled {
    opacity: 0.5;
    cursor: not-allowed;
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
                <!-- Stats Cards -->
               

                <!-- Pending Carts Section -->
                <div class="pending-carts-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i data-lucide="shopping-cart"></i>
                            Paniers en attente
                        </h2>
                        <a href="pending-carts.php" class="section-action">
                            Voir tous
                            <i data-lucide="arrow-right"></i>
                        </a>
                    </div>
                    
                    <div class="cart-list">
                        <?php if (empty($pendingCartsDetail)): ?>
                            <div class="empty-state">
                                <i data-lucide="shopping-cart"></i>
                                <h3>Aucun panier en attente</h3>
                                <p>Tous les paniers ont été traités!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pendingCartsDetail as $cart): ?>
                                <div class="cart-item">
                                    <div class="cart-info">
                                        <div class="cart-avatar">
                                            <?php echo strtoupper(substr($cart['sellerName'] ?? 'V', 0, 1)); ?>
                                        </div>
                                        <div class="cart-details">
                                            <div class="cart-seller"><?php echo htmlspecialchars($cart['sellerName'] ?? 'Vendeur inconnu'); ?></div>
                                            <div class="cart-client">Client: <?php echo htmlspecialchars($cart['clientName'] ?? 'Client anonyme'); ?></div>
                                          <div class="cart-time">
    <?php 
    $createdTime = new DateTime($cart['created_at']);
    $now = new DateTime();
    $interval = $now->diff($createdTime);
    
    // Afficher selon l'ancienneté
    if ($interval->days == 0) {
        // Aujourd'hui - afficher l'heure
        echo 'Créé à ' . $createdTime->format('H:i');
    } elseif ($interval->days == 1) {
        // Hier
        echo 'Hier à ' . $createdTime->format('H:i');
    } else {
        // Plus ancien - afficher date et heure
        echo 'Le ' . $createdTime->format('d/m/Y à H:i');
    }
    ?>
</div>
                                        </div>
                                    </div>
                                    <div class="cart-meta">
                                        <div class="cart-items-count">
                                            <?php echo $cart['itemCount']; ?> article<?php echo $cart['itemCount'] > 1 ? 's' : ''; ?>
                                        </div>
                                     <div class="cart-actions">
    <a href="process-payment.php?cartId=<?php echo $cart['id']; ?>" class="btn-process">
        <i data-lucide="credit-card"></i>
        Traiter
    </a>
  
</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Sales & Alerts Grid -->
                <div class="recent-sales-grid">
                    <!-- Recent Sales -->
                    <div class="card col-sm-12">
                        <div class="section-header">
                            <h2 class="section-title">
                                <i data-lucide="history"></i>
                                Dernières ventes
                            </h2>
                            <a href="completed-sales.php" class="section-action">
                                Voir toutes
                                <i data-lucide="arrow-right"></i>
                            </a>
                        </div>
                        
                        <?php if (empty($recentSales)): ?>
                            <div class="empty-state">
                                <i data-lucide="receipt"></i>
                                <h3>Aucune vente aujourd'hui</h3>
                                <p>Les ventes apparaîtront ici</p>
                            </div>
                        <?php else: ?>
                            <table class="recent-sales-table">
                                <thead>
                                    <tr>
                                        <th>Facture</th>
                                        <th>Client</th>
                                        <th>Vendeur</th>
                                        <th>Montant</th>
                                        <th>Heure</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentSales as $sale): ?>
                                        <tr>
                                            <td>
                                                <span class="invoice-number"><?php echo htmlspecialchars($sale['invoiceNumber']); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($sale['clientName'] ?? 'Client anonyme'); ?></td>
                                            <td><?php echo htmlspecialchars($sale['sellerName'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="amount"><?php echo number_format($sale['totalAmount'], 0); ?> XAF</span>
                                            </td>
                                            <td>
                                                <?php 
                                                $saleTime = new DateTime($sale['createdAt']);
                                                echo $saleTime->format('H:i'); 
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                  
             
                </div>
                
                
                <!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-icon">
                <i data-lucide="alert-triangle"></i>
            </div>
            <h3 class="modal-title">Confirmer la suppression</h3>
        </div>
        <div class="modal-body">
            <p>Êtes-vous sûr de vouloir supprimer ce panier ? Cette action est irréversible.</p>
        </div>
        <div class="modal-actions">
            <button onclick="closeDeleteModal()" class="btn-cancel">Annuler</button>
            <button onclick="confirmDelete()" class="btn-confirm" id="confirmDeleteBtn">Supprimer</button>
        </div>
    </div>
</div>
            </main>
        </div>
    </div>

    <script>
        // Set favicon
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

        // Sidebar functionality
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

        // Auto refresh dashboard every 60 seconds
        function autoRefresh() {
            setInterval(() => {
                location.reload();
            }, 60000); // 60 seconds
        }

        // Initialize app
        function initApp() {
            setFavicon();
            setupSidebar();
            autoRefresh();
            
            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        // Start the app when DOM is loaded
        document.addEventListener('DOMContentLoaded', initApp);

        // Update page title dynamically
        document.addEventListener('DOMContentLoaded', function() {
            const pageTitle = document.getElementById('pageTitle');
            const pageDescription = document.getElementById('pageDescription');
            
            if (pageTitle) pageTitle.textContent = 'Caisse';
            if (pageDescription) pageDescription.textContent = 'Traitement des ventes et paiements';
        });
    </script>
    
    <script>
        let cartToDelete = null;

function deleteCart(cartId) {
    cartToDelete = cartId;
    document.getElementById('deleteModal').classList.add('show');
    lucide.createIcons();
}

function closeDeleteModal() {
    cartToDelete = null;
    document.getElementById('deleteModal').classList.remove('show');
}

function confirmDelete() {
    if (!cartToDelete) return;
    
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Suppression...';
    
    fetch('delete-cart1.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ cartId: cartToDelete })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeDeleteModal();
            location.reload();
        } else {
            alert('Erreur: ' + (data.message || 'Impossible de supprimer le panier'));
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Supprimer';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Une erreur est survenue lors de la suppression');
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Supprimer';
    });
}

// Fermer le modal avec la touche Échap
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
    }
});
    </script>
</body>
</html>

<?php }else{
    header("location: ../logout.php");
}?>