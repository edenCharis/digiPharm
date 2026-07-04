<?php
require_once '../config/app_settings.php';
AppSettings::init($db);

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<aside id="sidebar" class="sidebar">

    <a href="index.php" class="sidebar-brand">
        <div class="brand-icon"><?php echo getAppIcon('icon-class'); ?></div>
        <div class="sidebar-label">
            <div class="brand-name"><?php echo htmlspecialchars(appName()); ?></div>
            <div class="brand-role">Stock</div>
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

        <span class="nav-label sidebar-label">Inventaire</span>

        <a href="products.php" class="nav-item <?php echo $currentPage==='products.php'?'active':''; ?>" data-label="Produits">
            <i data-lucide="package"></i>
            <span class="nav-text sidebar-label">Produits</span>
        </a>

        <a href="suppliers.php" class="nav-item <?php echo $currentPage==='suppliers.php'?'active':''; ?>" data-label="Fournisseurs">
            <i data-lucide="truck"></i>
            <span class="nav-text sidebar-label">Fournisseurs</span>
        </a>

        <span class="nav-label sidebar-label">Livraisons</span>

        <a href="index.php" class="nav-item <?php echo false?'active':''; ?>" data-label="Livraisons">
            <i data-lucide="package-open"></i>
            <span class="nav-text sidebar-label">Livraisons</span>
        </a>

        <span class="nav-label sidebar-label">Compte</span>

        <a href="profile.php" class="nav-item <?php echo $currentPage==='profile.php'?'active':''; ?>" data-label="Mon profil">
            <i data-lucide="user"></i>
            <span class="nav-text sidebar-label">Mon profil</span>
        </a>

    </nav>

    <a href="../logout.php" class="sidebar-user" title="Déconnexion">
        <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['username'] ?? 'S', 0, 1)); ?></div>
        <div class="user-info sidebar-label">
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>
            <div class="user-role-lbl">Gestionnaire Stock</div>
        </div>
        <div class="user-chevron sidebar-label"><i data-lucide="log-out"></i></div>
    </a>

</aside>
