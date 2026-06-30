<?php
require_once '../config/app_settings.php';
AppSettings::init($db);

$notifications = [];
$notifCount = 0;
try {
    $lowStock = $db->fetchAll("SELECT name, stock FROM product WHERE stock <= 5 AND stock > 0 ORDER BY stock ASC LIMIT 5");
    foreach ($lowStock as $p) {
        $notifications[] = ['type'=>'warning','icon'=>'package','title'=>$p['name'],'sub'=>'Stock faible : '.$p['stock'].' restant(s)','link'=>'products.php'];
        $notifCount++;
    }
    $outOfStock = $db->fetchAll("SELECT name FROM product WHERE stock = 0 LIMIT 3");
    foreach ($outOfStock as $p) {
        $notifications[] = ['type'=>'danger','icon'=>'package-x','title'=>$p['name'],'sub'=>'Rupture de stock','link'=>'products.php'];
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
                <span id="pageTitle">Ventes</span>
            </div>
        </div>

        <div class="header-right">
            <div class="notif-wrapper">
                <button class="header-btn" id="notifBtn" title="Notifications">
                    <i data-lucide="bell"></i>
                    <?php if ($notifCount > 0): ?>
                    <span class="notif-badge"><?php echo $notifCount > 9 ? '9+' : $notifCount; ?></span>
                    <?php endif; ?>
                </button>
                <div class="notif-panel" id="notifPanel">
                    <div class="notif-panel-head">
                        <span>Notifications</span>
                        <?php if ($notifCount > 0): ?><span class="notif-count-pill"><?php echo $notifCount; ?></span><?php endif; ?>
                    </div>
                    <div class="notif-list">
                        <?php if (empty($notifications)): ?>
                        <div class="notif-empty"><i data-lucide="check-circle"></i><span>Tout est en ordre</span></div>
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

            <div class="user-wrapper">
                <button class="user-btn" id="userMenuToggle">
                    <div class="avatar"><?php echo strtoupper(substr($_SESSION["username"], 0, 1)); ?></div>
                    <i data-lucide="chevron-down" style="width:14px;height:14px;color:var(--ds-text-400)"></i>
                </button>
                <div class="user-panel" id="userDropdown">
                    <div class="user-panel-head">
                        <div class="avatar lg"><?php echo strtoupper(substr($_SESSION["username"], 0, 1)); ?></div>
                        <div>
                            <div class="up-name"><?php echo htmlspecialchars($_SESSION["username"]); ?></div>
                            <div class="up-role">Vendeur</div>
                        </div>
                    </div>
                    <div class="user-panel-menu">
                        <a href="profile.php" class="up-item"><i data-lucide="user"></i> Mon profil</a>
                        <a href="historique.php" class="up-item"><i data-lucide="history"></i> Historique</a>
                        <div class="up-sep"></div>
                        <a href="../logout.php" class="up-item danger"><i data-lucide="log-out"></i> Déconnexion</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
<?php include_once '../assets/header-shared.php'; ?>
