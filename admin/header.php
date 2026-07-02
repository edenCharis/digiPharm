<?php
require_once '../config/app_settings.php';
AppSettings::init($db);

// Notifications data
$notifications = [];
$notifCount = 0;
try {
    $lowStockThreshold = AppSettings::get('low_stock_threshold', '10');
    $lowStock = $db->fetchAll("SELECT name, stock FROM product WHERE stock <= ? AND stock > 0 ORDER BY stock ASC LIMIT 8", [$lowStockThreshold]);
    foreach ($lowStock as $p) {
        $notifications[] = ['type'=>'warning','icon'=>'package','title'=>$p['name'],'sub'=>'Stock faible : '.$p['stock'].' restant(s)','link'=>'products.php'];
        $notifCount++;
    }
    $outOfStock = $db->fetchAll("SELECT name FROM product WHERE stock = 0 LIMIT 5");
    foreach ($outOfStock as $p) {
        $notifications[] = ['type'=>'danger','icon'=>'package-x','title'=>$p['name'],'sub'=>'Rupture de stock','link'=>'products.php'];
        $notifCount++;
    }
    $pendingCarts = $db->fetch("SELECT COUNT(*) as c FROM carts WHERE status='pending'");
    if ($pendingCarts && $pendingCarts['c'] > 0) {
        $notifications[] = ['type'=>'info','icon'=>'shopping-cart','title'=>$pendingCarts['c'].' panier(s) en attente','sub'=>'À traiter en caisse','link'=>'../cashier/index.php'];
        $notifCount++;
    }
} catch (Exception $e) {}
?>
<header class="header">
    <div class="header-content">
        <div class="header-left">
            <button id="menuToggle" class="menu-toggle" title="Menu">
                <i data-lucide="menu"></i>
            </button>
            <div class="header-page-title">
                <span id="pageTitle">Tableau de bord</span>
            </div>
        </div>

        <div class="header-right">
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

            <!-- User Menu -->
            <div class="user-wrapper" id="userWrapper">
                <button class="user-btn" id="userMenuToggle">
                    <div class="avatar"><?php echo strtoupper(substr($_SESSION["username"], 0, 1)); ?></div>
                    <i data-lucide="chevron-down" style="width:14px;height:14px;color:var(--ds-text-400)"></i>
                </button>
                <div class="user-panel" id="userDropdown">
                    <div class="user-panel-head">
                        <div class="avatar lg"><?php echo strtoupper(substr($_SESSION["username"], 0, 1)); ?></div>
                        <div>
                            <div class="up-name"><?php echo htmlspecialchars($_SESSION["username"]); ?></div>
                            <div class="up-role">Administrateur</div>
                        </div>
                    </div>
                    <div class="user-panel-menu">
                        <a href="profile.php" class="up-item"><i data-lucide="user"></i> Mon profil</a>
                        <a href="cash-count.php" class="up-item"><i data-lucide="calculator"></i> Comptage caisse</a>
                        <a href="settings.php" class="up-item"><i data-lucide="settings"></i> Paramètres</a>
                        <div class="up-sep"></div>
                        <a href="../logout.php" class="up-item danger"><i data-lucide="log-out"></i> Déconnexion</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<?php
// Trial / subscription status banner
if (!empty($_SESSION['pharmacy_id']) && $_SESSION['pharmacy_id'] > 0) {
    try {
        $__trial = $db->fetch(
            "SELECT status, trial_ends_at FROM pharmacies WHERE id = ?",
            [$_SESSION['pharmacy_id']]
        );
        if ($__trial) {
            if ($__trial['status'] === 'suspended') {
                echo '<div style="background:#fef2f2;border-bottom:1px solid #fecaca;padding:10px 24px;font-size:13px;color:#991b1b;display:flex;align-items:center;gap:8px;">'
                   . '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
                   . '<strong>Compte suspendu.</strong> Contactez <a href="mailto:support@digitech.cg" style="color:#991b1b;">support@digitech.cg</a> pour réactiver votre abonnement.'
                   . '</div>';
            } elseif ($__trial['status'] === 'trial' && $__trial['trial_ends_at']) {
                $__days = (int) ceil((strtotime($__trial['trial_ends_at']) - time()) / 86400);
                if ($__days < 0) {
                    echo '<div style="background:#fef2f2;border-bottom:1px solid #fecaca;padding:10px 24px;font-size:13px;color:#991b1b;display:flex;align-items:center;gap:8px;">'
                       . '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
                       . '<strong>Période d\'essai expirée.</strong> Contactez <a href="mailto:support@digitech.cg" style="color:#991b1b;">support@digitech.cg</a> pour souscrire.'
                       . '</div>';
                } elseif ($__days <= 5) {
                    echo '<div style="background:#fffbeb;border-bottom:1px solid #fde68a;padding:10px 24px;font-size:13px;color:#92400e;display:flex;align-items:center;gap:8px;">'
                       . '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'
                       . "<strong>Essai gratuit :</strong> il vous reste <strong>$__days jour" . ($__days > 1 ? 's' : '') . "</strong>. Contactez <a href=\"mailto:support@digitech.cg\" style=\"color:#92400e;\">support@digitech.cg</a> pour continuer."
                       . '</div>';
                }
            }
        }
    } catch (Exception $e) { /* pharmacies table may not exist on first run */ }
}
?>

<?php include_once '../assets/header-shared.php'; ?>
