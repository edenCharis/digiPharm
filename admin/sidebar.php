<?php
require_once '../config/app_settings.php';
AppSettings::init($db);

$currentPage = basename($_SERVER['PHP_SELF']);

$_navAlerts = 0;
try {
    $__thresh = AppSettings::getLowStockThreshold();
    $__r = $db->fetch("SELECT COUNT(*) as c FROM product WHERE stock <= ? AND pharmacy_id = ?", [$__thresh, $pharmacyId]);
    $_navAlerts = $__r ? (int)$__r['c'] : 0;
} catch (Exception $_e) {}
?>
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<aside id="sidebar" class="sidebar">

    <!-- Brand -->
    <a href="index.php" class="sidebar-brand">
        <div class="brand-icon"><?php echo getAppIcon('icon-class'); ?></div>
        <div class="sidebar-label">
            <div class="brand-name"><?php echo htmlspecialchars(appName()); ?></div>
            <div class="brand-role">Administration</div>
        </div>
    </a>

    <!-- Mobile close -->
    <button id="sidebarClose" style="display:none;position:absolute;top:14px;right:14px;background:transparent;border:none;color:rgba(255,255,255,0.5);cursor:pointer;padding:4px;">
        <i data-lucide="x" style="width:18px;height:18px;"></i>
    </button>

    <!-- Nav -->
    <nav class="nav">

        <!-- OVERVIEW -->
        <span class="nav-label sidebar-label">Vue d'ensemble</span>

        <a href="index.php" class="nav-item <?php echo $currentPage === 'index.php' ? 'active' : ''; ?>" data-label="Tableau de bord">
            <i data-lucide="layout-dashboard"></i>
            <span class="nav-text sidebar-label">Tableau de bord</span>
        </a>

        <!-- INVENTAIRE -->
        <span class="nav-label sidebar-label">Inventaire</span>

        <a href="products.php" class="nav-item <?php echo $currentPage === 'products.php' ? 'active' : ''; ?>" data-label="Produits">
            <i data-lucide="package"></i>
            <span class="nav-text sidebar-label">Produits</span>
            <?php if ($_navAlerts > 0): ?>
            <span class="nav-badge sidebar-label"><?php echo $_navAlerts > 9 ? '9+' : $_navAlerts; ?></span>
            <?php endif; ?>
        </a>

        <a href="category.php" class="nav-item <?php echo $currentPage === 'category.php' ? 'active' : ''; ?>" data-label="Catégories">
            <i data-lucide="grid-2x2"></i>
            <span class="nav-text sidebar-label">Catégories</span>
        </a>

        <a href="suppliers.php" class="nav-item <?php echo $currentPage === 'suppliers.php' ? 'active' : ''; ?>" data-label="Fournisseurs">
            <i data-lucide="truck"></i>
            <span class="nav-text sidebar-label">Fournisseurs</span>
        </a>

        <a href="stock-deliveries.php" class="nav-item <?php echo $currentPage === 'stock-deliveries.php' ? 'active' : ''; ?>" data-label="Livraisons">
            <i data-lucide="package-open"></i>
            <span class="nav-text sidebar-label">Livraisons</span>
        </a>

        <!-- VENTES & CAISSE -->
        <span class="nav-label sidebar-label">Ventes & Caisse</span>

        <a href="cash-register.php" class="nav-item <?php echo $currentPage === 'cash-register.php' ? 'active' : ''; ?>" data-label="Gestion Caisses">
            <i data-lucide="calculator"></i>
            <span class="nav-text sidebar-label">Gestion Caisses</span>
        </a>

        <!-- RAPPORTS -->
        <span class="nav-label sidebar-label">Rapports</span>

        <a href="reports.php" class="nav-item <?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>" data-label="Rapports">
            <i data-lucide="bar-chart-2"></i>
            <span class="nav-text sidebar-label">Rapports</span>
        </a>

        <a href="logs.php" class="nav-item <?php echo $currentPage === 'logs.php' ? 'active' : ''; ?>" data-label="Logs système">
            <i data-lucide="file-text"></i>
            <span class="nav-text sidebar-label">Logs système</span>
        </a>

        <!-- ÉQUIPE -->
        <span class="nav-label sidebar-label">Équipe</span>

        <a href="users.php" class="nav-item <?php echo $currentPage === 'users.php' ? 'active' : ''; ?>" data-label="Utilisateurs">
            <i data-lucide="users"></i>
            <span class="nav-text sidebar-label">Utilisateurs</span>
        </a>

        <!-- SYSTÈME -->
        <span class="nav-label sidebar-label">Système</span>

        <a href="settings.php" class="nav-item <?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>" data-label="Paramètres">
            <i data-lucide="settings"></i>
            <span class="nav-text sidebar-label">Paramètres</span>
        </a>

    </nav>

    <!-- User strip -->
    <a href="../logout.php" class="sidebar-user" title="Déconnexion">
        <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?></div>
        <div class="user-info sidebar-label">
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>
            <div class="user-role-lbl">Administrateur</div>
        </div>
        <div class="user-chevron sidebar-label"><i data-lucide="log-out"></i></div>
    </a>

</aside>
