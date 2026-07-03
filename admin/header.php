<?php
require_once '../config/app_settings.php';
AppSettings::init($db);

// ── Notifications ────────────────────────────────────────
$notifications = [];
$notifCount    = 0;
try {
    $lowStockThreshold = AppSettings::get('low_stock_threshold', '10');

    $lowStock = $db->fetchAll(
        "SELECT name, stock FROM product WHERE stock <= ? AND stock > 0 AND pharmacy_id = ? ORDER BY stock ASC LIMIT 8",
        [$lowStockThreshold, $pharmacyId]
    );
    foreach ($lowStock as $p) {
        $notifications[] = ['type'=>'warning','icon'=>'package','title'=>$p['name'],'sub'=>'Stock faible : '.$p['stock'].' restant(s)','link'=>'products.php'];
        $notifCount++;
    }
    $outOfStock = $db->fetchAll("SELECT name FROM product WHERE stock = 0 AND pharmacy_id = ? LIMIT 5", [$pharmacyId]);
    foreach ($outOfStock as $p) {
        $notifications[] = ['type'=>'danger','icon'=>'package-x','title'=>$p['name'],'sub'=>'Rupture de stock','link'=>'products.php'];
        $notifCount++;
    }
    $pendingCarts = $db->fetch("SELECT COUNT(*) as c FROM carts WHERE status='pending' AND pharmacy_id = ?", [$pharmacyId]);
    if ($pendingCarts && $pendingCarts['c'] > 0) {
        $notifications[] = ['type'=>'info','icon'=>'shopping-cart','title'=>$pendingCarts['c'].' panier(s) en attente','sub'=>'À traiter en caisse','link'=>'../cashier/index.php'];
        $notifCount++;
    }
} catch (Exception $e) {}

// ── Trial banner ─────────────────────────────────────────
$_banner = null;
if (!empty($_SESSION['pharmacy_id'])) {
    try {
        $__ph = $db->fetch("SELECT status, trial_ends_at FROM pharmacies WHERE id = ?", [$_SESSION['pharmacy_id']]);
        if ($__ph) {
            if ($__ph['status'] === 'suspended') {
                $_banner = ['type'=>'danger','msg'=>'<strong>Compte suspendu.</strong> Contactez <a href="mailto:support@digitech.cg">support@digitech.cg</a>.'];
            } elseif ($__ph['status'] === 'trial' && $__ph['trial_ends_at']) {
                $__days = (int)ceil((strtotime($__ph['trial_ends_at']) - time()) / 86400);
                if ($__days < 0) {
                    $_banner = ['type'=>'danger','msg'=>'<strong>Période d\'essai expirée.</strong> Contactez <a href="mailto:support@digitech.cg">support@digitech.cg</a>.'];
                } elseif ($__days <= 5) {
                    $_banner = ['type'=>'warn','msg'=>"<strong>Essai gratuit :</strong> il reste <strong>$__days jour".($__days>1?'s':'')."</strong>. <a href=\"mailto:support@digitech.cg\">Souscrire</a>."];
                }
            }
        }
    } catch (Exception $_e) {}
}

// Day name in French
$dayNames = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
$headerDate = $dayNames[(int)date('w')] . ' ' . date('j M Y');
?>
<?php if ($_banner): ?>
<div class="trial-banner <?php echo $_banner['type']; ?>">
    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <span><?php echo $_banner['msg']; ?></span>
</div>
<?php endif; ?>

<header class="header">
    <div class="header-content">
        <!-- Left: toggle + page title -->
        <div class="header-left">
            <button id="menuToggle" class="menu-toggle" title="Réduire/étendre le menu">
                <i data-lucide="menu"></i>
            </button>
        </div>

        <!-- Center: global search -->
        <div class="header-search">
            <span class="header-search-icon"><i data-lucide="search"></i></span>
            <input type="text" placeholder="Rechercher produits, commandes, rapports…" id="globalSearch" autocomplete="off">
        </div>

        <!-- Right: date · notifications · add · user -->
        <div class="header-right">

            <!-- Date chip -->
            <div class="header-date">
                <i data-lucide="calendar"></i>
                <?php echo $headerDate; ?>
            </div>

            <!-- Notifications -->
            <div class="notif-wrapper" id="notifWrapper">
                <button class="header-btn" id="notifBtn" title="Notifications">
                    <i data-lucide="bell"></i>
                    <?php if ($notifCount > 0): ?>
                    <span class="notif-badge"><?php echo $notifCount > 9 ? '9+' : $notifCount; ?></span>
                    <?php endif; ?>
                </button>
                <div class="notif-panel" id="notifPanel">
                    <div class="notif-panel-head">
                        <span>Notifications</span>
                        <?php if ($notifCount > 0): ?>
                        <span class="notif-count-pill"><?php echo $notifCount; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="notif-list">
                        <?php if (empty($notifications)): ?>
                        <div class="notif-empty">
                            <i data-lucide="check-circle"></i>
                            <span>Tout est en ordre</span>
                        </div>
                        <?php else: foreach ($notifications as $n): ?>
                        <a href="<?php echo htmlspecialchars($n['link']); ?>" class="notif-item notif-<?php echo $n['type']; ?>">
                            <div class="notif-icon"><i data-lucide="<?php echo $n['icon']; ?>"></i></div>
                            <div class="notif-body">
                                <div class="notif-title"><?php echo htmlspecialchars($n['title']); ?></div>
                                <div class="notif-sub"><?php echo htmlspecialchars($n['sub']); ?></div>
                            </div>
                        </a>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>

            <!-- CTA -->
            <a href="products.php?action=add" class="btn-cta">
                <i data-lucide="plus"></i>
                Ajouter
            </a>

            <!-- User menu -->
            <div class="user-wrapper" id="userWrapper">
                <button class="user-btn" id="userMenuToggle">
                    <div class="avatar"><?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?></div>
                    <i data-lucide="chevron-down" style="width:13px;height:13px;color:var(--ps-muted)"></i>
                </button>
                <div class="user-panel" id="userDropdown">
                    <div class="user-panel-head">
                        <div class="avatar lg"><?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?></div>
                        <div>
                            <div class="up-name"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>
                            <div class="up-role">Administrateur</div>
                        </div>
                    </div>
                    <div class="user-panel-menu">
                        <a href="profile.php"    class="up-item"><i data-lucide="user"></i> Mon profil</a>
                        <a href="cash-count.php" class="up-item"><i data-lucide="calculator"></i> Comptage caisse</a>
                        <a href="settings.php"   class="up-item"><i data-lucide="settings"></i> Paramètres</a>
                        <div class="up-sep"></div>
                        <a href="../logout.php"  class="up-item danger"><i data-lucide="log-out"></i> Déconnexion</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<?php include_once '../assets/header-shared.php'; ?>
