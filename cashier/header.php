<?php
require_once '../config/app_settings.php';
AppSettings::init($db);

$notifications = [];
$notifCount    = 0;
try {
    $pending = $db->fetch("SELECT COUNT(*) as c FROM carts WHERE status='pending' AND pharmacy_id=?", [$pharmacyId]);
    if ($pending && $pending['c'] > 0) {
        $notifications[] = ['type'=>'info','icon'=>'shopping-cart','title'=>$pending['c'].' panier(s) en attente','sub'=>'À traiter en caisse','link'=>'pending-carts.php'];
        $notifCount++;
    }
    $lowStock = $db->fetchAll("SELECT name, stock FROM product WHERE stock <= 5 AND stock > 0 AND pharmacy_id=? ORDER BY stock ASC LIMIT 5", [$pharmacyId]);
    foreach ($lowStock as $p) {
        $notifications[] = ['type'=>'warning','icon'=>'package','title'=>$p['name'],'sub'=>'Stock faible : '.$p['stock'].' restant(s)','link'=>'../admin/products.php'];
        $notifCount++;
    }
} catch (Exception $e) {}

$dayNames   = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
$headerDate = $dayNames[(int)date('w')] . ' ' . date('j M Y');
?>
<header class="header">
    <div class="header-content">
        <div class="header-left">
            <button id="menuToggle" class="menu-toggle" title="Menu">
                <i data-lucide="menu"></i>
            </button>
        </div>

        <div class="header-search">
            <span class="header-search-icon"><i data-lucide="search"></i></span>
            <input type="text" placeholder="Rechercher paniers, ventes…" autocomplete="off">
        </div>

        <div class="header-right">
            <div class="header-date">
                <i data-lucide="calendar"></i>
                <?php echo $headerDate; ?>
            </div>

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

            <a href="process-payment.php" class="btn-cta">
                <i data-lucide="credit-card"></i>
                Encaisser
            </a>

            <div class="user-wrapper" id="userWrapper">
                <button class="user-btn" id="userMenuToggle">
                    <div class="avatar"><?php echo strtoupper(substr($_SESSION['username'] ?? 'C', 0, 1)); ?></div>
                    <i data-lucide="chevron-down" style="width:13px;height:13px;color:var(--ps-muted)"></i>
                </button>
                <div class="user-panel" id="userDropdown">
                    <div class="user-panel-head">
                        <div class="avatar lg"><?php echo strtoupper(substr($_SESSION['username'] ?? 'C', 0, 1)); ?></div>
                        <div>
                            <div class="up-name"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>
                            <div class="up-role">Caissier</div>
                        </div>
                    </div>
                    <div class="user-panel-menu">
                        <a href="profile.php"         class="up-item"><i data-lucide="user"></i> Mon profil</a>
                        <a href="completed-sales.php" class="up-item"><i data-lucide="receipt"></i> Ventes du jour</a>
                        <div class="up-sep"></div>
                        <a href="../logout.php" class="up-item danger"><i data-lucide="log-out"></i> Déconnexion</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
<?php include_once '../assets/header-shared.php'; ?>
