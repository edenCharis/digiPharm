<?php
session_start();
if($_SESSION["role"] === "ADMIN" && $_SESSION["id"] == session_id()){

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    include '../config/database.php';
    if (!isset($db)) throw new Exception('Database connection not found');

    $register_id = isset($_GET['id']) ? trim($_GET['id']) : '';
    if (!$register_id) throw new Exception('ID de caisse manquant');

    $registerSQL = "SELECT cr.id, cr.cashier_id, cr.opening_time, cr.closing_time, cr.status,
                        cr.initial_amount, cr.final_amount,
                        u.username as cashier_name, u.role as cashier_role,
                        COALESCE(SUM(s.totalAmount), 0) as total_sales,
                        COUNT(s.id) as total_transactions,
                        COALESCE(SUM(s.cashReceived), 0) as total_cash_received,
                        COALESCE(SUM(s.changeAmount), 0) as total_change_given,
                        COALESCE(SUM(s.totalVAT), 0) as total_vat,
                        COALESCE(SUM(s.discountAmount), 0) as total_discount
                    FROM cash_register cr
                    LEFT JOIN user u ON cr.cashier_id = u.id
                    LEFT JOIN sale s ON cr.id = s.cash_register_id
                    WHERE cr.id = ?
                    GROUP BY cr.id, cr.cashier_id, cr.opening_time, cr.closing_time,
                             cr.status, cr.initial_amount, cr.final_amount, u.username, u.role";
    $register = $db->fetch($registerSQL, [$register_id]);
    if (!$register) throw new Exception('Caisse introuvable');

    $salesSQL = "SELECT s.id, s.saleDate, s.totalAmount, s.totalVAT, s.discountAmount,
                    s.invoiceNumber, s.cashReceived, s.changeAmount,
                    seller.username as seller_name,
                    client.name as client_name
                 FROM sale s
                 LEFT JOIN user seller ON s.sellerId = seller.id
                 LEFT JOIN client ON s.clientId = client.id
                 WHERE s.cash_register_id = ?
                 ORDER BY s.saleDate DESC";
    $sales = $db->fetchAll($salesSQL, [$register_id]);
    if (!$sales) $sales = [];

    $itemCounts = [];
    foreach ($sales as $s) {
        $r = $db->fetch("SELECT COUNT(*) as cnt FROM saleitem WHERE saleId = ?", [$s['id']]);
        $itemCounts[$s['id']] = $r ? intval($r['cnt']) : 0;
    }

    $pendingCartsSQL = "SELECT c.id, c.created_at, c.status, u.username as seller_name,
                            cl.name as client_name, COUNT(ci.id) as item_count,
                            COALESCE(SUM(ci.quantity * ci.unit_price), 0) as cart_total
                        FROM carts c
                        LEFT JOIN user u ON c.seller_id = u.id
                        LEFT JOIN client cl ON c.client_id = cl.id
                        LEFT JOIN cart_items ci ON c.id = ci.cart_id
                        WHERE c.cash_register_id = ? AND c.status = 'pending'
                        GROUP BY c.id, c.created_at, c.status, c.seller_id, c.client_id, u.username, cl.name
                        ORDER BY c.created_at ASC";
    $pendingCarts = $db->fetchAll($pendingCartsSQL, [$register_id]);
    if (!$pendingCarts) $pendingCarts = [];

    $statistics = [
        'difference'   => $register['status'] === 'closed'
                            ? ($register['final_amount'] - ($register['initial_amount'] + $register['total_sales']))
                            : 0,
        'average_sale' => $register['total_transactions'] > 0
                            ? ($register['total_sales'] / $register['total_transactions'])
                            : 0,
    ];

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

function formatCurrency($amount) { return number_format($amount, 0) . ' XAF'; }
function formatDateTime($dt)     { return date('d/m/Y H:i', strtotime($dt)); }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>digiPharm - Détails Caisse #<?php echo htmlspecialchars($register_id); ?></title>
    <link rel="stylesheet" href="../assets/css/admin-dark-theme.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .register-details-header { background:var(--ds-green); color:white; padding:2rem; border-radius:12px; margin-bottom:2rem; }
        .register-status { display:inline-block; padding:.5rem 1rem; border-radius:20px; font-weight:600; font-size:.875rem; margin-left:1rem; }
        .register-status.open   { background:rgba(16,185,129,.2); color:var(--ds-green); border:2px solid var(--ds-green); }
        .register-status.closed { background:rgba(107,114,128,.2); color:var(--ds-text-400); border:2px solid var(--ds-text-400); }

        .stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1.5rem; margin-bottom:2rem; }
        .stat-card  { background:white; padding:1.5rem; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.1); text-align:center; border:1px solid var(--ds-border); }
        .stat-icon  { width:3rem; height:3rem; margin:0 auto 1rem; border-radius:50%; display:flex; align-items:center; justify-content:center; }
        .stat-icon.primary { background:#dbeafe; color:#3b82f6; }
        .stat-icon.success { background:var(--ds-green-bg); color:var(--ds-green); }
        .stat-icon.warning { background:#fef3c7; color:#f59e0b; }
        .stat-icon.danger  { background:#fee2e2; color:#ef4444; }
        .stat-value { font-size:1.4rem; font-weight:700; color:var(--ds-text-900); margin-bottom:.25rem; }
        .stat-label { color:var(--ds-text-400); font-weight:500; font-size:.875rem; }

        .pending-section { background:white; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,.1); overflow:hidden; margin-bottom:2rem; border:1px solid #fde68a; }
        .pending-section-header { background:linear-gradient(135deg,#fef3c7,#fde68a); padding:1.25rem 1.5rem; border-bottom:1px solid #fde68a; display:flex; align-items:center; justify-content:space-between; }
        .pending-section-title  { font-size:1.1rem; font-weight:700; color:#92400e; display:flex; align-items:center; gap:.5rem; margin:0; }
        .pending-badge { background:#f59e0b; color:white; border-radius:9999px; padding:.2rem .65rem; font-size:.8rem; font-weight:700; }
        .pending-cart-row { display:flex; align-items:center; padding:1rem 1.5rem; border-bottom:1px solid var(--ds-surface-alt); gap:1rem; transition:background .15s; }
        .pending-cart-row:last-child { border-bottom:none; }
        .pending-cart-row:hover { background:#fffbeb; }
        .pending-avatar { width:2.5rem; height:2.5rem; background:#f59e0b; color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; flex-shrink:0; }
        .pending-info { flex:1; }
        .pending-seller { font-weight:600; color:var(--ds-text-900); }
        .pending-client { font-size:.85rem; color:var(--ds-text-400); }
        .pending-time   { font-size:.75rem; color:var(--ds-text-400); }
        .pending-meta   { text-align:right; min-width:110px; }
        .pending-total  { font-weight:700; color:#92400e; }
        .pending-items  { font-size:.8rem; color:var(--ds-text-400); }
        .btn-delete-cart { background:#ef4444; color:white; border:none; padding:.45rem .8rem; border-radius:8px; font-size:.8rem; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:.3rem; transition:all .2s; white-space:nowrap; }
        .btn-delete-cart:hover { background:#dc2626; }

        .sales-table { background:white; border-radius:12px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.1); }
        .table-header { background:#f8fafc; padding:1.5rem; border-bottom:1px solid var(--ds-border); display:flex; align-items:center; justify-content:space-between; }
        .table-title  { font-size:1.25rem; font-weight:600; color:var(--ds-text-900); display:flex; align-items:center; gap:.5rem; margin:0; }

        .sale-card { display:grid; grid-template-columns:2fr 1fr 1fr 1fr auto; align-items:center; padding:1rem 1.5rem; border-bottom:1px solid var(--ds-surface-alt); cursor:pointer; transition:background .15s, transform .1s; gap:1rem; }
        .sale-card:last-child { border-bottom:none; }
        .sale-card:hover { background:#f0f9ff; transform:translateX(3px); }
        .sale-card:hover .sale-arrow { color:#3b82f6; }
        .sale-invoice-num { font-weight:700; color:var(--ds-text-900); font-size:.95rem; }
        .sale-date-time   { font-size:.8rem; color:var(--ds-text-400); margin-top:2px; }
        .sale-client-tag  { display:inline-flex; align-items:center; gap:.3rem; font-size:.78rem; color:#6366f1; font-weight:600; background:#eef2ff; padding:.15rem .5rem; border-radius:20px; margin-top:4px; }
        .sale-amount-val  { font-weight:700; color:var(--ds-text-900); font-size:1rem; }
        .sale-sub-label   { font-size:.75rem; color:var(--ds-text-400); }
        .sale-arrow       { color:var(--ds-border); transition:color .15s; display:flex; align-items:center; }
        .badge-items      { background:#e0e7ff; color:#4338ca; font-size:.72rem; font-weight:700; padding:.2rem .55rem; border-radius:20px; white-space:nowrap; }

        .drawer-overlay { position:fixed; inset:0; background:rgba(15,23,42,.45); z-index:1000; opacity:0; pointer-events:none; transition:opacity .3s; backdrop-filter:blur(2px); }
        .drawer-overlay.show { opacity:1; pointer-events:all; }
        .drawer { position:fixed; top:0; right:0; width:520px; max-width:95vw; height:100vh; background:#fff; z-index:1001; transform:translateX(100%); transition:transform .35s cubic-bezier(.4,0,.2,1); display:flex; flex-direction:column; box-shadow:-8px 0 40px rgba(0,0,0,.18); }
        .drawer.show { transform:translateX(0); }

        .drawer-header { padding:1.5rem; background:var(--ds-green); color:white; flex-shrink:0; }
        .drawer-header-top { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:.75rem; }
        .drawer-invoice { font-size:1.3rem; font-weight:800; }
        .drawer-date    { font-size:.85rem; opacity:.85; margin-top:2px; }
        .drawer-close   { background:rgba(255,255,255,.2); border:none; color:white; width:2rem; height:2rem; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:background .2s; flex-shrink:0; }
        .drawer-close:hover { background:rgba(255,255,255,.35); }
        .drawer-header-badges { display:flex; gap:.5rem; flex-wrap:wrap; }
        .drawer-badge { background:rgba(255,255,255,.2); border:1px solid rgba(255,255,255,.3); color:white; font-size:.78rem; font-weight:600; padding:.25rem .75rem; border-radius:20px; }

        .drawer-body { flex:1; overflow-y:auto; padding:1.5rem; display:flex; flex-direction:column; gap:1.25rem; }
        .drawer-body::-webkit-scrollbar { width:5px; }
        .drawer-body::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:3px; }

        .drawer-loading { display:flex; flex-direction:column; align-items:center; justify-content:center; flex:1; gap:1rem; color:#64748b; padding:3rem; }
        .spinner { width:2.5rem; height:2.5rem; border:3px solid #e2e8f0; border-top-color:#6366f1; border-radius:50%; animation:spin .7s linear infinite; }
        @keyframes spin { to { transform:rotate(360deg); } }

        .drawer-summary { display:grid; grid-template-columns:repeat(3,1fr); gap:.75rem; }
        .drawer-summary-card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:.85rem; text-align:center; }
        .drawer-summary-val   { font-size:1rem; font-weight:700; color:#1e293b; }
        .drawer-summary-label { font-size:.72rem; color:#64748b; margin-top:2px; }

        .drawer-section-title { font-size:.8rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.05em; padding-bottom:.5rem; border-bottom:2px solid #f1f5f9; margin-bottom:.75rem; }

        .drawer-item { display:flex; align-items:center; gap:1rem; padding:.85rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; transition:border-color .15s; }
        .drawer-item:hover { border-color:#a5b4fc; }
        .drawer-item-num  { width:1.8rem; height:1.8rem; background:#e0e7ff; color:#4338ca; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.75rem; font-weight:800; flex-shrink:0; }
        .drawer-item-info { flex:1; min-width:0; }
        .drawer-item-name { font-weight:600; color:#1e293b; font-size:.9rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .drawer-item-desc { font-size:.75rem; color:#94a3b8; }
        .drawer-item-detail { font-size:.75rem; color:#64748b; margin-top:2px; }
        .drawer-item-price { text-align:right; flex-shrink:0; }
        .drawer-item-total { font-weight:700; color:#1e293b; font-size:.9rem; }
        .drawer-item-qty   { font-size:.75rem; color:#64748b; }

        .btn-refund-drawer { background:#fff1f2; color:#e11d48; border:1px solid #fecdd3; padding:.3rem .6rem; border-radius:6px; font-size:.75rem; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:.25rem; transition:all .2s; white-space:nowrap; flex-shrink:0; }
        .btn-refund-drawer:hover { background:#e11d48; color:white; border-color:#e11d48; }

        .drawer-client-block { background:#f0fdf4; border:1px solid var(--ds-green-bg); border-radius:10px; padding:1rem; display:flex; align-items:center; gap:.75rem; }
        .drawer-client-avatar { width:2.5rem; height:2.5rem; background:var(--ds-green); color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:1rem; flex-shrink:0; }
        .drawer-client-name   { font-weight:700; color:#065f46; }
        .drawer-client-phone  { font-size:.8rem; color:var(--ds-green); }

        .back-button  { background:var(--ds-text-400); color:white; border:none; padding:.75rem 1.5rem; border-radius:8px; font-weight:500; cursor:pointer; display:inline-flex; align-items:center; gap:.5rem; text-decoration:none; transition:background .2s; }
        .back-button:hover { background:var(--ds-text-600); color:white; text-decoration:none; }
        .print-button { background:#3b82f6; color:white; border:none; padding:.75rem 1.5rem; border-radius:8px; font-weight:500; cursor:pointer; display:inline-flex; align-items:center; gap:.5rem; transition:background .2s; }
        .print-button:hover { background:#2563eb; }
        .actions-bar  { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; }
        .text-success { color:var(--ds-green) !important; }
        .text-danger  { color:#ef4444 !important; }

        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:2000; align-items:center; justify-content:center; }
        .modal-overlay.show { display:flex; }
        .modal-box { background:white; border-radius:12px; padding:2rem; max-width:420px; width:90%; box-shadow:0 20px 40px rgba(0,0,0,.25); }
        .modal-icon-wrap { width:3.5rem; height:3.5rem; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-bottom:1rem; }
        .modal-icon-wrap.danger  { background:#fee2e2; color:#dc2626; }
        .modal-icon-wrap.warning { background:#fef3c7; color:#d97706; }
        .modal-title { font-size:1.2rem; font-weight:700; color:var(--ds-text-900); margin-bottom:.5rem; }
        .modal-desc  { color:var(--ds-text-400); margin-bottom:1.5rem; font-size:.9rem; }
        .modal-actions { display:flex; gap:.75rem; justify-content:flex-end; }
        .btn-cancel { background:var(--ds-surface-alt); color:var(--ds-text-900); border:none; padding:.5rem 1rem; border-radius:8px; font-weight:500; cursor:pointer; }
        .btn-cancel:hover { background:var(--ds-border); }
        .btn-confirm-danger { background:#dc2626; color:white; border:none; padding:.5rem 1rem; border-radius:8px; font-weight:600; cursor:pointer; }
        .btn-confirm-danger:hover { background:#b91c1c; }
        .btn-confirm-danger:disabled { opacity:.5; cursor:not-allowed; }

        .empty-state { text-align:center; padding:2.5rem 1.5rem; color:var(--ds-text-400); }
        .no-sales-message { text-align:center; padding:3rem; color:var(--ds-text-400); }

        .print-content { display:none; }
        @media print {
            body * { visibility:hidden; }
            .print-content, .print-content * { visibility:visible; }
            .print-content { position:absolute; left:0; top:0; width:100%; display:block !important; }
            .no-print { display:none !important; }
            .print-header { text-align:center; margin-bottom:30px; border-bottom:2px solid #333; padding-bottom:20px; }
            .print-header h1 { font-size:24px; margin:0; }
            .print-header p { margin:5px 0; font-size:14px; color:#666; }
            .print-stats { display:grid; grid-template-columns:repeat(2,1fr); gap:20px; margin-bottom:30px; }
            .print-stat { border:1px solid #ddd; padding:15px; text-align:center; }
            .print-stat-label { font-weight:bold; font-size:12px; color:#666; margin-bottom:5px; }
            .print-stat-value { font-size:16px; font-weight:bold; }
            .print-sales-table { width:100%; border-collapse:collapse; font-size:12px; }
            .print-sales-table th, .print-sales-table td { border:1px solid #ddd; padding:8px; text-align:left; }
            .print-sales-table th { background:#f5f5f5; font-weight:bold; }
        }
        @media (max-width:768px) {
            .sale-card { grid-template-columns:1fr auto; }
            .sale-card > *:nth-child(2), .sale-card > *:nth-child(3), .sale-card > *:nth-child(4) { display:none; }
            .drawer { width:100vw; }
            .drawer-summary { grid-template-columns:repeat(2,1fr); }
        }
    </style>
</head>
<body>
<div class="app-layout">
    <div id="sidebarOverlay" class="sidebar-overlay no-print"></div>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="no-print"><?php include 'header.php'; ?></div>

        <main class="content-area">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><i data-lucide="alert-circle"></i> <?php echo htmlspecialchars($error_message); ?></div>
            <div class="text-center mt-4">
                <a href="cash-register.php" class="back-button"><i data-lucide="arrow-left"></i> Retour</a>
            </div>
        <?php else: ?>

            <div class="actions-bar no-print">
                <a href="cash-register.php" class="back-button"><i data-lucide="arrow-left"></i> Retour aux caisses</a>
                <button onclick="window.print()" class="print-button"><i data-lucide="printer"></i> Imprimer</button>
            </div>

            <div class="register-details-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-2">
                            <i data-lucide="wallet"></i> Caisse #<?php echo htmlspecialchars($register['id']); ?>
                            <span class="register-status <?php echo $register['status']; ?>">
                                <?php echo $register['status'] === 'open' ? 'Ouverte' : 'Fermée'; ?>
                            </span>
                        </h1>
                        <p class="mb-0 opacity-75">
                            Caissier : <strong><?php echo htmlspecialchars($register['cashier_name']); ?></strong>
                            (<?php echo $register['cashier_role']; ?>)
                        </p>
                    </div>
                    <div class="text-end">
                        <div class="mb-2"><strong>Ouvert le :</strong><br><?php echo formatDateTime($register['opening_time']); ?></div>
                        <?php if ($register['closing_time']): ?>
                            <div><strong>Fermé le :</strong><br><?php echo formatDateTime($register['closing_time']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon primary"><i data-lucide="banknote"></i></div><div class="stat-value"><?php echo formatCurrency($register['initial_amount']); ?></div><div class="stat-label">Montant initial</div></div>
                <div class="stat-card"><div class="stat-icon success"><i data-lucide="trending-up"></i></div><div class="stat-value"><?php echo formatCurrency($register['total_sales']); ?></div><div class="stat-label">Total des ventes</div></div>
                <div class="stat-card"><div class="stat-icon warning"><i data-lucide="shopping-cart"></i></div><div class="stat-value"><?php echo $register['total_transactions']; ?></div><div class="stat-label">Transactions</div></div>
                <div class="stat-card"><div class="stat-icon primary"><i data-lucide="calculator"></i></div><div class="stat-value"><?php echo formatCurrency($statistics['average_sale']); ?></div><div class="stat-label">Vente moyenne</div></div>
                <div class="stat-card"><div class="stat-icon success"><i data-lucide="dollar-sign"></i></div><div class="stat-value"><?php echo formatCurrency($register['total_cash_received']); ?></div><div class="stat-label">Espèces reçues</div></div>
                <div class="stat-card"><div class="stat-icon warning"><i data-lucide="coins"></i></div><div class="stat-value"><?php echo formatCurrency($register['total_change_given']); ?></div><div class="stat-label">Monnaie rendue</div></div>
                <?php if ($register['status'] === 'closed'): ?>
                <div class="stat-card"><div class="stat-icon primary"><i data-lucide="wallet"></i></div><div class="stat-value"><?php echo formatCurrency($register['final_amount']); ?></div><div class="stat-label">Montant final</div></div>
                <div class="stat-card">
                    <div class="stat-icon <?php echo $statistics['difference'] >= 0 ? 'success' : 'danger'; ?>">
                        <i data-lucide="<?php echo $statistics['difference'] >= 0 ? 'trending-up' : 'trending-down'; ?>"></i>
                    </div>
                    <div class="stat-value <?php echo $statistics['difference'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo ($statistics['difference'] >= 0 ? '+' : '') . formatCurrency($statistics['difference']); ?>
                    </div>
                    <div class="stat-label">Écart</div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Pending Carts -->
            <div class="pending-section no-print">
                <div class="pending-section-header">
                    <h3 class="pending-section-title">
                        <i data-lucide="clock" style="width:18px;height:18px;"></i>
                        Paniers en attente
                        <span class="pending-badge"><?php echo count($pendingCarts); ?></span>
                    </h3>
                    <small style="color:#92400e;font-weight:500;">Non encore encaissés</small>
                </div>
                <?php if (empty($pendingCarts)): ?>
                    <div class="empty-state">
                        <i data-lucide="check-circle" style="width:2.5rem;height:2.5rem;color:var(--ds-green);display:block;margin:0 auto .75rem;"></i>
                        <p style="font-weight:600;color:var(--ds-text-900);">Aucun panier en attente</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pendingCarts as $cart): ?>
                        <div class="pending-cart-row" id="cart-row-<?php echo htmlspecialchars($cart['id']); ?>">
                            <div class="pending-avatar"><?php echo strtoupper(substr($cart['seller_name'] ?? 'V', 0, 1)); ?></div>
                            <div class="pending-info">
                                <div class="pending-seller"><?php echo htmlspecialchars($cart['seller_name'] ?? 'Vendeur inconnu'); ?></div>
                                <div class="pending-client">Client : <?php echo htmlspecialchars($cart['client_name'] ?? 'Client anonyme'); ?></div>
                                <div class="pending-time"><?php
                                    $ct = new DateTime($cart['created_at']); $now = new DateTime(); $diff = $now->diff($ct);
                                    if ($diff->days == 0) echo 'Créé à '.$ct->format('H:i');
                                    elseif ($diff->days == 1) echo 'Hier à '.$ct->format('H:i');
                                    else echo 'Le '.$ct->format('d/m/Y à H:i');
                                ?></div>
                            </div>
                            <div class="pending-meta">
                                <div class="pending-total"><?php echo formatCurrency($cart['cart_total']); ?></div>
                                <div class="pending-items"><?php echo $cart['item_count']; ?> article<?php echo $cart['item_count'] > 1 ? 's' : ''; ?></div>
                            </div>
                            <button class="btn-delete-cart"
                                    data-cart-id="<?php echo htmlspecialchars($cart['id']); ?>"
                                    data-seller="<?php echo htmlspecialchars($cart['seller_name'] ?? ''); ?>">
                                <i data-lucide="trash-2" style="width:13px;height:13px;"></i> Supprimer
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Sales List -->
            <div class="sales-table">
                <div class="table-header">
                    <h3 class="table-title"><i data-lucide="receipt"></i> Détail des ventes (<?php echo count($sales); ?>)</h3>
                    <small class="text-muted no-print">Cliquez pour voir le détail</small>
                </div>
                <?php if (empty($sales)): ?>
                    <div class="no-sales-message">
                        <div style="font-size:4rem;color:var(--ds-border);margin-bottom:1rem;"><i data-lucide="shopping-cart"></i></div>
                        <h4>Aucune vente enregistrée</h4>
                    </div>
                <?php else: ?>
                    <?php foreach ($sales as $sale): ?>
                        <!-- ✅ ID en data-attribute, pas dans onclick -->
                        <div class="sale-card no-print" data-sale-id="<?php echo htmlspecialchars($sale['id']); ?>">
                            <div>
                                <div class="sale-invoice-num">
                                    <i data-lucide="file-text" style="width:13px;height:13px;vertical-align:middle;margin-right:4px;color:#6366f1;"></i>
                                    Facture #<?php echo htmlspecialchars($sale['invoiceNumber']); ?>
                                </div>
                                <div class="sale-date-time"><?php echo formatDateTime($sale['saleDate']); ?></div>
                                <?php if ($sale['client_name']): ?>
                                    <div><span class="sale-client-tag"><?php echo htmlspecialchars($sale['client_name']); ?></span></div>
                                <?php endif; ?>
                            </div>
                            <div><div class="sale-amount-val"><?php echo formatCurrency($sale['totalAmount']); ?></div><div class="sale-sub-label">Total</div></div>
                            <div><div class="sale-amount-val" style="color:var(--ds-green)"><?php echo formatCurrency($sale['cashReceived']); ?></div><div class="sale-sub-label">Reçu</div></div>
                            <div><span class="badge-items"><?php echo $itemCounts[$sale['id']]; ?> article(s)</span></div>
                            <div class="sale-arrow"><i data-lucide="chevron-right" style="width:20px;height:20px;"></i></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php endif; ?>
        </main>
    </div>
</div>

<!-- DRAWER -->
<div class="drawer-overlay no-print" id="drawerOverlay"></div>
<div class="drawer no-print" id="saleDrawer">
    <div class="drawer-header">
        <div class="drawer-header-top">
            <div>
                <div class="drawer-invoice" id="drawerInvoice">—</div>
                <div class="drawer-date"    id="drawerDate">—</div>
            </div>
            <button class="drawer-close" id="btnCloseDrawer">
                <i data-lucide="x" style="width:16px;height:16px;"></i>
            </button>
        </div>
        <div class="drawer-header-badges" id="drawerBadges"></div>
    </div>
    <div class="drawer-body" id="drawerBody"></div>
</div>

<!-- MODAL – Delete Cart -->
<div id="modalDeleteCart" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-icon-wrap warning"><i data-lucide="alert-triangle" style="width:1.5rem;height:1.5rem;"></i></div>
        <div class="modal-title">Supprimer ce panier ?</div>
        <div class="modal-desc" id="modalDeleteCartDesc">Cette action est irréversible.</div>
        <div class="modal-actions">
            <button class="btn-cancel" id="btnCancelDelete">Annuler</button>
            <button class="btn-confirm-danger" id="btnConfirmDeleteCart">Supprimer</button>
        </div>
    </div>
</div>

<!-- MODAL – Refund -->
<div id="modalRefundItem" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-icon-wrap danger"><i data-lucide="rotate-ccw" style="width:1.5rem;height:1.5rem;"></i></div>
        <div class="modal-title">Rembourser cet article ?</div>
        <div class="modal-desc" id="modalRefundItemDesc"></div>
        <div class="modal-actions">
            <button class="btn-cancel" id="btnCancelRefund">Annuler</button>
            <button class="btn-confirm-danger" id="btnConfirmRefund">Confirmer</button>
        </div>
    </div>
</div>

<!-- PRINT -->
<div class="print-content">
    <div class="print-header">
        <h1>Rapport de Caisse #<?php echo htmlspecialchars($register['id']); ?></h1>
        <p>digiPharm — Caissier : <?php echo htmlspecialchars($register['cashier_name']); ?></p>
        <p>Du <?php echo formatDateTime($register['opening_time']); ?><?php if($register['closing_time']): ?> au <?php echo formatDateTime($register['closing_time']); ?><?php endif; ?></p>
    </div>
    <div class="print-stats">
        <div class="print-stat"><div class="print-stat-label">Montant Initial</div><div class="print-stat-value"><?php echo formatCurrency($register['initial_amount']); ?></div></div>
        <div class="print-stat"><div class="print-stat-label">Total Ventes</div><div class="print-stat-value"><?php echo formatCurrency($register['total_sales']); ?></div></div>
        <div class="print-stat"><div class="print-stat-label">Transactions</div><div class="print-stat-value"><?php echo $register['total_transactions']; ?></div></div>
        <div class="print-stat"><div class="print-stat-label">Vente Moyenne</div><div class="print-stat-value"><?php echo formatCurrency($statistics['average_sale']); ?></div></div>
        <div class="print-stat"><div class="print-stat-label">Espèces Reçues</div><div class="print-stat-value"><?php echo formatCurrency($register['total_cash_received']); ?></div></div>
        <div class="print-stat"><div class="print-stat-label">Monnaie Rendue</div><div class="print-stat-value"><?php echo formatCurrency($register['total_change_given']); ?></div></div>
    </div>
    <?php if (!empty($sales)): ?>
    <table class="print-sales-table">
        <thead><tr><th>Facture</th><th>Date</th><th>Client</th><th>Articles</th><th>Total</th><th>Reçu</th><th>Rendu</th></tr></thead>
        <tbody>
        <?php foreach ($sales as $sale): ?>
        <tr>
            <td>#<?php echo htmlspecialchars($sale['invoiceNumber']); ?></td>
            <td><?php echo formatDateTime($sale['saleDate']); ?></td>
            <td><?php echo $sale['client_name'] ? htmlspecialchars($sale['client_name']) : '—'; ?></td>
            <td><?php echo $itemCounts[$sale['id']]; ?> article(s)</td>
            <td><?php echo formatCurrency($sale['totalAmount']); ?></td>
            <td><?php echo formatCurrency($sale['cashReceived']); ?></td>
            <td><?php echo formatCurrency($sale['changeAmount']); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') lucide.createIcons();
    setupSidebar();

    // ✅ Listeners sur les sale-cards via data-sale-id (pas de onclick inline)
    document.querySelectorAll('.sale-card[data-sale-id]').forEach(function(card) {
        card.addEventListener('click', function() {
            openDrawer(card.getAttribute('data-sale-id'));
        });
    });

    // ✅ Listeners sur les boutons supprimer panier via data-attributes
    document.querySelectorAll('.btn-delete-cart').forEach(function(btn) {
        btn.addEventListener('click', function() {
            confirmDeleteCart(btn.getAttribute('data-cart-id'), btn.getAttribute('data-seller'));
        });
    });

    // Drawer close
    document.getElementById('btnCloseDrawer').addEventListener('click', closeDrawer);
    document.getElementById('drawerOverlay').addEventListener('click', closeDrawer);

    // Modal buttons
    document.getElementById('btnCancelDelete').addEventListener('click', function() { closeModal('modalDeleteCart'); });
    document.getElementById('btnConfirmDeleteCart').addEventListener('click', executeDeleteCart);
    document.getElementById('btnCancelRefund').addEventListener('click', function() { closeModal('modalRefundItem'); });
    document.getElementById('btnConfirmRefund').addEventListener('click', executeRefundItem);

    // Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { closeDrawer(); closeModal('modalDeleteCart'); closeModal('modalRefundItem'); }
    });
});

function setupSidebar() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var mt = document.getElementById('menuToggle');
    var sc = document.getElementById('sidebarClose');
    if (mt) mt.addEventListener('click', function(){ sidebar.classList.add('show'); overlay.classList.add('show'); });
    if (sc) sc.addEventListener('click', function(){ sidebar.classList.remove('show'); overlay.classList.remove('show'); });
    if (overlay) overlay.addEventListener('click', function(){ sidebar.classList.remove('show'); overlay.classList.remove('show'); });
}

function fmt(n) { return new Intl.NumberFormat('fr-FR').format(Math.round(n)) + ' XAF'; }

// ── DRAWER ────────────────────────────────────────────────
function openDrawer(saleId) {
    document.getElementById('drawerInvoice').textContent = 'Chargement…';
    document.getElementById('drawerDate').textContent    = '';
    document.getElementById('drawerBadges').innerHTML    = '';
    document.getElementById('drawerBody').innerHTML      =
        '<div class="drawer-loading"><div class="spinner"></div><span>Chargement des détails…</span></div>';
    document.getElementById('drawerOverlay').classList.add('show');
    document.getElementById('saleDrawer').classList.add('show');

    // ✅ encodeURIComponent pour les IDs qui peuvent être des strings quelconques
    fetch('get-sale-details.php?sale_id=' + encodeURIComponent(saleId))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { showToast('Erreur : ' + data.error, 'danger'); closeDrawer(); return; }
            renderDrawer(data);
        })
        .catch(function(err) {
            console.error('Fetch error:', err);
            showToast('Erreur de chargement', 'danger');
            closeDrawer();
        });
}

function renderDrawer(d) {
    document.getElementById('drawerInvoice').textContent = 'Facture #' + d.invoiceNumber;
    document.getElementById('drawerDate').textContent    = d.saleDateFormatted;
    document.getElementById('drawerBadges').innerHTML    =
        '<span class="drawer-badge">' + d.items.length + ' article(s)</span>' +
        '<span class="drawer-badge">' + (d.seller_name || 'Vendeur') + '</span>';

    var html = '';

    // Client
    if (d.client_name) {
        html += '<div class="drawer-client-block">' +
            '<div class="drawer-client-avatar">' + d.client_name.charAt(0).toUpperCase() + '</div>' +
            '<div><div class="drawer-client-name">' + d.client_name + '</div>' +
            (d.client_phone ? '<div class="drawer-client-phone">📞 ' + d.client_phone + '</div>' : '') +
            '</div></div>';
    }

    // Résumé financier — données brutes de la base
    html += '<div><div class="drawer-section-title">Résumé financier</div><div class="drawer-summary">' +
        '<div class="drawer-summary-card"><div class="drawer-summary-val">' + fmt(d.totalAmount) + '</div><div class="drawer-summary-label">Total</div></div>' +
        '<div class="drawer-summary-card"><div class="drawer-summary-val" style="color:var(--ds-green)">' + fmt(d.cashReceived) + '</div><div class="drawer-summary-label">Reçu</div></div>' +
        '<div class="drawer-summary-card"><div class="drawer-summary-val" style="color:#f59e0b">' + fmt(d.changeAmount) + '</div><div class="drawer-summary-label">Rendu</div></div>';
    if (d.totalVAT > 0)
        html += '<div class="drawer-summary-card"><div class="drawer-summary-val" style="color:#6366f1">' + fmt(d.totalVAT) + '</div><div class="drawer-summary-label">TVA incluse</div></div>';
    if (d.discountAmount > 0)
        html += '<div class="drawer-summary-card"><div class="drawer-summary-val" style="color:#ef4444">-' + fmt(d.discountAmount) + '</div><div class="drawer-summary-label">Remise</div></div>';
    html += '</div></div>';

    // Articles — affichage des données brutes, sans recalcul
    html += '<div><div class="drawer-section-title">Articles (' + d.items.length + ')</div><div style="display:flex;flex-direction:column;gap:.6rem;">';
    d.items.forEach(function(item, idx) {
        html +=
            '<div class="drawer-item" id="drawer-item-' + item.id + '">' +
                '<div class="drawer-item-num">' + (idx + 1) + '</div>' +
                '<div class="drawer-item-info">' +
                    '<div class="drawer-item-name">' + item.product_name + '</div>' +
                    (item.product_description ? '<div class="drawer-item-desc">' + item.product_description + '</div>' : '') +
                    '<div class="drawer-item-detail">Qté : ' + item.quantity + ' &nbsp;|&nbsp; P.U. : ' + fmt(item.unitPrice) + '</div>' +
                '</div>' +
                '<div class="drawer-item-price">' +
                    '<div class="drawer-item-total">' + fmt(item.line_total) + '</div>' +
                '</div>' +
                // ✅ IDs stockés en data-attributes (strings préservées, pas de inline JS)
                '<button class="btn-refund-drawer"' +
                    ' data-item-id="' + item.id + '"' +
                    ' data-sale-id="' + d.id + '"' +
                    ' data-name="' + item.product_name.replace(/"/g, '&quot;') + '"' +
                    ' data-total="' + item.unitPrice + '">' +
                    '<i data-lucide="rotate-ccw" style="width:12px;height:12px;"></i> Rembourser' +
                '</button>' +
            '</div>';
    });
    html += '</div></div>';

    document.getElementById('drawerBody').innerHTML = html;

    // ✅ Listeners sur les boutons remboursement — lecture des data-attributes en string
    document.querySelectorAll('#drawerBody .btn-refund-drawer').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            // On passe les IDs tels quels (strings) — pas de parseInt
            confirmRefundItem(
                btn.getAttribute('data-item-id'),
                btn.getAttribute('data-sale-id'),
                btn.getAttribute('data-name'),
                parseFloat(btn.getAttribute('data-total'))
            );
        });
    });

    if (typeof lucide !== 'undefined') lucide.createIcons();
}

function closeDrawer() {
    document.getElementById('drawerOverlay').classList.remove('show');
    document.getElementById('saleDrawer').classList.remove('show');
}

// ── Modals ────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('show'); if (typeof lucide !== 'undefined') lucide.createIcons(); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

// ── Delete cart ───────────────────────────────────────────
var _cartIdToDelete = null;
function confirmDeleteCart(cartId, sellerName) {
    _cartIdToDelete = cartId;
    document.getElementById('modalDeleteCartDesc').textContent =
        'Supprimer le panier de "' + sellerName + '" ? Cette action est irréversible.';
    openModal('modalDeleteCart');
}
function executeDeleteCart() {
    if (!_cartIdToDelete) return;
    var btn = document.getElementById('btnConfirmDeleteCart');
    btn.disabled = true; btn.textContent = 'Suppression…';
    fetch('delete-cart1.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ cartId: _cartIdToDelete })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var row = document.getElementById('cart-row-' + _cartIdToDelete);
            if (row) row.remove();
            var badge = document.querySelector('.pending-badge');
            if (badge) badge.textContent = Math.max(0, parseInt(badge.textContent) - 1);
            closeModal('modalDeleteCart');
            showToast('Panier supprimé', 'success');
        } else { showToast('Erreur : ' + (data.message || ''), 'danger'); }
    })
    .catch(function() { showToast('Erreur réseau', 'danger'); })
    .finally(function() { btn.disabled = false; btn.textContent = 'Supprimer'; _cartIdToDelete = null; });
}

// ── Refund item ───────────────────────────────────────────
var _refundItem = null;
function confirmRefundItem(itemId, saleId, productName, lineTotal) {
    // ✅ On stocke les IDs comme strings — refund-sale-item.php les reçoit en POST JSON
    _refundItem = { itemId: itemId, saleId: saleId, productName: productName, lineTotal: lineTotal };
    document.getElementById('modalRefundItemDesc').innerHTML =
        'Rembourser <strong>' + productName + '</strong> (' + fmt(lineTotal) + ') ?<br>' +
        '<small style="color:var(--ds-text-400);">L\'article sera retiré et le stock réajusté.</small>';
    openModal('modalRefundItem');
}
function executeRefundItem() {
    if (!_refundItem) return;
    var btn = document.getElementById('btnConfirmRefund');
    btn.disabled = true; btn.textContent = 'Traitement…';
    fetch('refund-sale-item.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ itemId: _refundItem.itemId, saleId: _refundItem.saleId })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var row = document.getElementById('drawer-item-' + _refundItem.itemId);
            if (row) row.remove();
            closeModal('modalRefundItem');
            
            
            
            showToast('Remboursement effectué', 'success');
            setTimeout(function() { location.reload(); }, 1800);
        } else { showToast('Erreur : ' + (data.message || ''), 'danger'); }
    })
    .catch(function() { showToast('Erreur réseau', 'danger'); })
    .finally(function() { btn.disabled = false; btn.textContent = 'Confirmer'; _refundItem = null; });
}

// ── Toast ─────────────────────────────────────────────────
function showToast(msg, type) {
    var t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:99999;padding:.75rem 1.25rem;border-radius:10px;font-weight:600;font-size:.9rem;box-shadow:0 4px 20px rgba(0,0,0,.2);max-width:360px;background:' +
        (type === 'success' ? 'var(--ds-green)' : '#ef4444') + ';color:white;animation:slideIn .25s ease;';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function() { t.remove(); }, 3500);
}

// ── Print ─────────────────────────────────────────────────
window.addEventListener('beforeprint', function() { document.querySelector('.print-content').style.display = 'block'; });
window.addEventListener('afterprint',  function() { document.querySelector('.print-content').style.display = 'none'; });
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') { e.preventDefault(); window.print(); }
});
</script>
<style>@keyframes slideIn { from{transform:translateX(120%);opacity:0} to{transform:translateX(0);opacity:1} }</style>
</body>
</html>
<?php } else { header("Location: ../login.php"); exit(); } ?>