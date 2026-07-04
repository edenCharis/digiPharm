<?php
require_once '../config/app_settings.php';
AppSettings::init($db);

$currentPage = basename($_SERVER['PHP_SELF']);
$cashierId   = (int)($_SESSION['user_id'] ?? 0);

$_pendingCount = 0;
$_salesCount   = 0;
try {
    $r = $db->fetch("SELECT COUNT(c.id) as n FROM carts c JOIN cash_register cr ON c.cash_register_id=cr.id WHERE c.status='PENDING' AND cr.cashier_id=? AND cr.status='OPEN'", [$cashierId]);
    $_pendingCount = $r ? (int)$r['n'] : 0;
    $r2 = $db->fetch("SELECT COUNT(s.id) as n FROM sale s JOIN cash_register cr ON s.cash_register_id=cr.id WHERE DATE(s.createdAt)=CURDATE() AND cr.cashier_id=?", [$cashierId]);
    $_salesCount = $r2 ? (int)$r2['n'] : 0;
} catch (Exception $_e) {}
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<aside id="sidebar" class="sidebar">

    <a href="index.php" class="sidebar-brand">
        <div class="brand-icon"><?php echo getAppIcon('icon-class'); ?></div>
        <div class="sidebar-label">
            <div class="brand-name"><?php echo htmlspecialchars(appName()); ?></div>
            <div class="brand-role">Caisse</div>
        </div>
    </a>

    <button id="sidebarClose" style="display:none;position:absolute;top:14px;right:14px;background:transparent;border:none;color:rgba(255,255,255,0.5);cursor:pointer;padding:4px;">
        <i data-lucide="x" style="width:18px;height:18px;"></i>
    </button>

    <nav class="nav">

        <span class="nav-label sidebar-label">Vue d'ensemble</span>

        <a href="index.php" class="nav-item <?php echo $currentPage==='index.php'?'active':''; ?>" data-label="Tableau de bord">
            <i data-lucide="layout-dashboard"></i>
            <span class="nav-text sidebar-label">Tableau de bord</span>
        </a>

        <span class="nav-label sidebar-label">Ventes</span>

        <a href="pending-carts.php" class="nav-item <?php echo $currentPage==='pending-carts.php'?'active':''; ?>" data-label="Paniers en attente">
            <i data-lucide="shopping-cart"></i>
            <span class="nav-text sidebar-label">Paniers en attente</span>
            <?php if ($_pendingCount > 0): ?>
            <span class="nav-badge sidebar-label"><?php echo $_pendingCount > 9 ? '9+' : $_pendingCount; ?></span>
            <?php endif; ?>
        </a>

        <a href="process-payment.php" class="nav-item <?php echo $currentPage==='process-payment.php'?'active':''; ?>" data-label="Traitement paiement">
            <i data-lucide="credit-card"></i>
            <span class="nav-text sidebar-label">Traitement paiement</span>
        </a>

        <a href="completed-sales.php" class="nav-item <?php echo $currentPage==='completed-sales.php'?'active':''; ?>" data-label="Ventes terminées">
            <i data-lucide="check-circle"></i>
            <span class="nav-text sidebar-label">Ventes terminées</span>
            <?php if ($_salesCount > 0): ?>
            <span class="nav-badge sidebar-label" style="background:var(--ps-green)"><?php echo $_salesCount; ?></span>
            <?php endif; ?>
        </a>

        <span class="nav-label sidebar-label">Compte</span>

        <a href="profile.php" class="nav-item <?php echo $currentPage==='profile.php'?'active':''; ?>" data-label="Mon profil">
            <i data-lucide="user"></i>
            <span class="nav-text sidebar-label">Mon profil</span>
        </a>

    </nav>

    <a href="../logout.php" class="sidebar-user" title="Déconnexion">
        <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['username'] ?? 'C', 0, 1)); ?></div>
        <div class="user-info sidebar-label">
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>
            <div class="user-role-lbl">Caissier</div>
        </div>
        <div class="user-chevron sidebar-label"><i data-lucide="log-out"></i></div>
    </a>

</aside>
