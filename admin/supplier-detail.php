<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'ADMIN' || ($_SESSION['id'] ?? '') !== session_id()) {
    header('Location: ../index.php');
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    include '../config/database.php';
    if (!isset($db)) throw new Exception('Database connection not found');

    $supplierId = trim($_GET['id'] ?? '');
    if (!$supplierId) { header('Location: suppliers.php'); exit; }

    $supplier = $db->fetch(
        "SELECT * FROM supplier WHERE id = ? AND pharmacy_id = ?",
        [$supplierId, $pharmacyId]
    );
    if (!$supplier) { header('Location: suppliers.php'); exit; }

    $products = $db->fetchAll(
        "SELECT p.name, p.code, p.stock, p.purchasePrice, p.sellingPrice, c.name as category
         FROM product p
         LEFT JOIN category c ON p.categoryId = c.id
         WHERE p.supplierId = ? AND p.pharmacy_id = ?
         ORDER BY p.name",
        [$supplierId, $pharmacyId]
    ) ?: [];

    $deliveries = $db->fetchAll(
        "SELECT d.id, d.deliveryDate, d.ASD, d.createdAt,
                COUNT(di.productId) as item_count,
                COALESCE(SUM(di.quantity), 0) as total_qty,
                COALESCE(SUM(di.quantity * di.priceCession), 0) as total_value
         FROM delivery d
         LEFT JOIN delivery_items di ON di.deliveryId = d.id
         WHERE d.supplierId = ? AND d.pharmacy_id = ?
         GROUP BY d.id, d.deliveryDate, d.ASD, d.createdAt
         ORDER BY d.createdAt DESC
         LIMIT 50",
        [$supplierId, $pharmacyId]
    ) ?: [];

    $stats = $db->fetch(
        "SELECT COUNT(DISTINCT d.id) as total_deliveries,
                COALESCE(SUM(di.quantity * di.priceCession), 0) as total_purchased,
                COALESCE(SUM(di.quantity), 0) as total_units
         FROM delivery d
         LEFT JOIN delivery_items di ON di.deliveryId = d.id
         WHERE d.supplierId = ? AND d.pharmacy_id = ?",
        [$supplierId, $pharmacyId]
    );

} catch (Exception $e) {
    die('Erreur: ' . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiche fournisseur — <?php echo htmlspecialchars($supplier['name']); ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/umd/lucide.js"></script>
    <link rel="stylesheet" href="../assets/css/admin-dark-theme.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .stat-row { display:flex; gap:1rem; flex-wrap:wrap; margin-bottom:1.5rem; }
        .stat-box { flex:1; min-width:130px; background:var(--ds-bg-2,#1e293b); border:1px solid var(--ds-border,rgba(255,255,255,.08)); border-radius:10px; padding:1rem 1.25rem; }
        .stat-val { font-size:1.4rem; font-weight:700; color:var(--ds-text-100,#f1f5f9); }
        .stat-lbl { font-size:.72rem; color:var(--ds-text-400,#94a3b8); margin-top:.2rem; text-transform:uppercase; letter-spacing:.05em; }
        .section-card { background:var(--ds-bg-2,#1e293b); border:1px solid var(--ds-border,rgba(255,255,255,.08)); border-radius:12px; padding:1.5rem; margin-bottom:1.5rem; }
        .section-title { font-size:.72rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:var(--ds-text-400,#94a3b8); margin-bottom:1rem; display:flex; align-items:center; gap:.5rem; }
        .data-table { width:100%; border-collapse:collapse; font-size:.875rem; }
        .data-table th { padding:.6rem 1rem; text-align:left; font-size:.7rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--ds-text-400,#94a3b8); border-bottom:1px solid var(--ds-border,rgba(255,255,255,.08)); }
        .data-table td { padding:.75rem 1rem; border-bottom:1px solid rgba(255,255,255,.04); color:var(--ds-text-100,#f1f5f9); }
        .data-table tbody tr:last-child td { border-bottom:none; }
        .data-table tbody tr:hover td { background:rgba(255,255,255,.03); }
        .badge-green { display:inline-block; padding:.2rem .65rem; border-radius:9999px; background:rgba(16,185,129,.15); color:#10b981; font-size:.72rem; font-weight:600; }
        .back-btn { color:var(--ds-text-300,#cbd5e1); opacity:.85; text-decoration:none; font-size:.875rem; display:inline-flex; align-items:center; gap:.4rem; border:1px solid rgba(255,255,255,.2); padding:.4rem .9rem; border-radius:8px; transition:opacity .2s; margin-bottom:1.25rem; }
        .back-btn:hover { opacity:1; color:var(--ds-text-100,#f1f5f9); }
        .supplier-header { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; flex-wrap:wrap; }
        .empty { text-align:center; padding:2rem; color:var(--ds-text-400,#94a3b8); font-size:.875rem; }
        @media print {
            .sidebar, nav, .main-header, .back-btn { display:none !important; }
            body { background:#fff !important; color:#000 !important; }
        }
    </style>
</head>
<body>
<div class="app-layout">
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <?php include 'header.php'; ?>

        <main class="content-area" style="padding:1.5rem;">

            <a href="suppliers.php" class="back-btn">
                <i data-lucide="arrow-left" style="width:15px;height:15px;"></i>
                Retour aux fournisseurs
            </a>

            <!-- Supplier info card -->
            <div class="section-card">
                <div class="supplier-header">
                    <div>
                        <div style="font-size:1.5rem;font-weight:700;color:var(--ds-text-100,#f1f5f9);margin-bottom:.25rem;">
                            <?php echo htmlspecialchars($supplier['name']); ?>
                        </div>
                        <div style="color:var(--ds-text-300,#cbd5e1);font-size:.9rem;display:flex;align-items:center;gap:.4rem;">
                            <i data-lucide="phone" style="width:14px;height:14px;"></i>
                            <?php echo htmlspecialchars($supplier['contact']); ?>
                        </div>
                        <div style="margin-top:.5rem;font-size:.78rem;color:var(--ds-text-400,#94a3b8);">
                            Enregistré le <?php echo date('d/m/Y', strtotime($supplier['createdAt'])); ?>
                        </div>
                    </div>
                    <span class="badge-green">Actif</span>
                </div>
            </div>

            <!-- Stats -->
            <div class="stat-row">
                <div class="stat-box">
                    <div class="stat-val"><?php echo (int)($stats['total_deliveries'] ?? 0); ?></div>
                    <div class="stat-lbl">Livraisons</div>
                </div>
                <div class="stat-box">
                    <div class="stat-val"><?php echo number_format((float)($stats['total_units'] ?? 0), 0, ',', ' '); ?></div>
                    <div class="stat-lbl">Unités reçues</div>
                </div>
                <div class="stat-box">
                    <div class="stat-val"><?php echo number_format((float)($stats['total_purchased'] ?? 0), 0, ',', ' '); ?></div>
                    <div class="stat-lbl">Total achats (FCFA)</div>
                </div>
                <div class="stat-box">
                    <div class="stat-val"><?php echo count($products); ?></div>
                    <div class="stat-lbl">Produits référencés</div>
                </div>
            </div>

            <!-- Delivery history -->
            <div class="section-card">
                <div class="section-title">
                    <i data-lucide="truck" style="width:14px;height:14px;"></i>
                    Historique des livraisons (<?php echo count($deliveries); ?>)
                </div>
                <?php if (empty($deliveries)): ?>
                    <div class="empty">Aucune livraison enregistrée pour ce fournisseur.</div>
                <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date livraison</th>
                                <th>Produits distincts</th>
                                <th style="text-align:right;">Quantité</th>
                                <th style="text-align:right;">Valeur (FCFA)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deliveries as $i => $d): ?>
                            <tr>
                                <td style="color:var(--ds-text-400,#94a3b8);font-size:.8rem;"><?php echo $i + 1; ?></td>
                                <td><?php echo $d['deliveryDate'] ? date('d/m/Y', strtotime($d['deliveryDate'])) : date('d/m/Y', strtotime($d['createdAt'])); ?></td>
                                <td><?php echo (int)$d['item_count']; ?></td>
                                <td style="text-align:right;"><?php echo number_format((int)$d['total_qty'], 0, ',', ' '); ?></td>
                                <td style="text-align:right;font-weight:600;"><?php echo number_format((float)$d['total_value'], 0, ',', ' '); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Products -->
            <div class="section-card">
                <div class="section-title">
                    <i data-lucide="package" style="width:14px;height:14px;"></i>
                    Produits de ce fournisseur (<?php echo count($products); ?>)
                </div>
                <?php if (empty($products)): ?>
                    <div class="empty">Aucun produit lié à ce fournisseur.</div>
                <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Catégorie</th>
                                <th style="text-align:right;">Stock</th>
                                <th style="text-align:right;">Prix achat</th>
                                <th style="text-align:right;">Prix vente</th>
                                <th style="text-align:right;">Valeur stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:500;"><?php echo htmlspecialchars($p['name']); ?></div>
                                    <div style="font-size:.72rem;color:var(--ds-text-400,#94a3b8);"><?php echo htmlspecialchars($p['code'] ?? ''); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($p['category'] ?? '—'); ?></td>
                                <td style="text-align:right;"><?php echo number_format((int)$p['stock'], 0, ',', ' '); ?></td>
                                <td style="text-align:right;"><?php echo number_format((float)$p['purchasePrice'], 0, ',', ' '); ?></td>
                                <td style="text-align:right;"><?php echo number_format((float)$p['sellingPrice'], 0, ',', ' '); ?></td>
                                <td style="text-align:right;font-weight:600;"><?php echo number_format((float)($p['stock'] * $p['purchasePrice']), 0, ',', ' '); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    if (typeof lucide !== 'undefined') lucide.createIcons();

    const pageTitle = document.getElementById('pageTitle');
    const pageDesc  = document.getElementById('pageDescription');
    if (pageTitle) pageTitle.textContent = 'Fiche fournisseur';
    if (pageDesc)  pageDesc.textContent  = '<?php echo htmlspecialchars($supplier['name'], ENT_QUOTES); ?>';

    const sidebar  = document.querySelector('.sidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    const toggle   = document.querySelector('.sidebar-toggle');
    const closeBtn = document.querySelector('.sidebar-close');
    const show = () => { sidebar?.classList.add('show'); overlay?.classList.add('show'); };
    const hide = () => { sidebar?.classList.remove('show'); overlay?.classList.remove('show'); };
    toggle?.addEventListener('click', show);
    closeBtn?.addEventListener('click', hide);
    overlay?.addEventListener('click', hide);
});
</script>
</body>
</html>
