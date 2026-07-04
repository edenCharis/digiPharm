<?php
session_start();
if (($_SESSION['role'] ?? '') !== 'ADMIN' || ($_SESSION['id'] ?? '') !== session_id()) {
    header('Location: ../index.php');
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    include '../config/database.php';
    define('DB_CONNECTION_INCLUDED', true);
    require_once '../config/app_settings.php';
    if (!isset($db)) throw new Exception('Database connection not found');
    AppSettings::init($db);
} catch (Exception $e) {
    die('Erreur de connexion: ' . htmlspecialchars($e->getMessage()));
}

$today     = date('Y-m-d');
$thisMonth = date('Y-m-01');
$yesterday = date('Y-m-d', strtotime('-1 day'));

/* ── Helper ─────────────────────────────────────────────── */
function timeAgo($dt) {
    $s = time() - strtotime($dt);
    if ($s < 60)    return 'À l\'instant';
    if ($s < 3600)  return 'Il y a ' . floor($s/60) . ' min';
    if ($s < 86400) return 'Il y a ' . floor($s/3600) . 'h';
    return 'Il y a ' . floor($s/86400) . 'j';
}
function pct($a, $b) {
    if ($b <= 0) return $a > 0 ? 100 : 0;
    return round(($a - $b) / $b * 100, 1);
}

/* ── KPI 1 — Today's revenue ────────────────────────────── */
$todayRev = $db->fetch(
    "SELECT COALESCE(SUM(ci.quantity*ci.unit_price),0) as rev, COUNT(DISTINCT c.id) as orders
     FROM carts c LEFT JOIN cart_items ci ON c.id=ci.cart_id
     WHERE DATE(c.created_at)=? AND c.status='completed' AND c.pharmacy_id=?",
    [$today, $pharmacyId]
);
$todayRevenue = $todayRev ? (float)$todayRev['rev']   : 0;
$todayOrders  = $todayRev ? (int)$todayRev['orders'] : 0;

$yestRev = $db->fetch(
    "SELECT COALESCE(SUM(ci.quantity*ci.unit_price),0) as rev
     FROM carts c LEFT JOIN cart_items ci ON c.id=ci.cart_id
     WHERE DATE(c.created_at)=? AND c.status='completed' AND c.pharmacy_id=?",
    [$yesterday, $pharmacyId]
);
$yesterdayRevenue = $yestRev ? (float)$yestRev['rev'] : 0;
$revenueGrowth    = pct($todayRevenue, $yesterdayRevenue);

/* ── KPI 2 — Monthly revenue ────────────────────────────── */
$monthlyRev = $db->fetch(
    "SELECT COALESCE(SUM(ci.quantity*ci.unit_price),0) as rev, COUNT(DISTINCT c.id) as orders
     FROM carts c LEFT JOIN cart_items ci ON c.id=ci.cart_id
     WHERE c.created_at>=? AND c.status='completed' AND c.pharmacy_id=?",
    [$thisMonth, $pharmacyId]
);
$monthlyRevenue = $monthlyRev ? (float)$monthlyRev['rev']   : 0;
$monthlyOrders  = $monthlyRev ? (int)$monthlyRev['orders'] : 0;

/* ── KPI 3 — Inventory ──────────────────────────────────── */
$lowStockThreshold = AppSettings::getLowStockThreshold();
$invResult = $db->fetch(
    "SELECT COUNT(*) as total,
            SUM(CASE WHEN stock > ? THEN 1 ELSE 0 END) as in_stock,
            SUM(CASE WHEN stock > 0 AND stock <= ? THEN 1 ELSE 0 END) as low_stock,
            SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock
     FROM product WHERE pharmacy_id=?",
    [$lowStockThreshold, $lowStockThreshold, $pharmacyId]
);
$inv = $invResult ?: ['total'=>0,'in_stock'=>0,'low_stock'=>0,'out_of_stock'=>0];

/* ── KPI 4 — Staff ──────────────────────────────────────── */
$staffResult = $db->fetch(
    "SELECT SUM(role='SELLER') as sellers, SUM(role='CASHIER') as cashiers
     FROM user WHERE pharmacy_id=?",
    [$pharmacyId]
);
$totalSellers  = $staffResult ? (int)$staffResult['sellers']  : 0;
$totalCashiers = $staffResult ? (int)$staffResult['cashiers'] : 0;

/* ── 7-day chart ────────────────────────────────────────── */
$weekRows = $db->fetchAll(
    "SELECT DATE(c.created_at) as d, COALESCE(SUM(ci.quantity*ci.unit_price),0) as rev
     FROM carts c LEFT JOIN cart_items ci ON c.id=ci.cart_id
     WHERE DATE(c.created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
       AND c.status='completed' AND c.pharmacy_id=?
     GROUP BY DATE(c.created_at)",
    [$pharmacyId]
) ?: [];

$weekMap = [];
foreach ($weekRows as $r) $weekMap[$r['d']] = (float)$r['rev'];
$chartData = []; $chartLabels = []; $chartDays = [];
$dayNames = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chartData[]   = $weekMap[$d] ?? 0;
    $chartLabels[] = $dayNames[(int)date('w', strtotime($d))];
    $chartDays[]   = $d;
}
$maxChartRev = max(array_merge([1], $chartData));

/* ── Expiring products (next 30 days) ───────────────────── */
$expiryRows = $db->fetchAll(
    "SELECT name, stock, expiryDate,
            DATEDIFF(expiryDate, CURDATE()) as days_left
     FROM product
     WHERE pharmacy_id=? AND expiryDate IS NOT NULL
       AND expiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
       AND stock > 0
     ORDER BY expiryDate ASC LIMIT 6",
    [$pharmacyId]
) ?: [];

/* ── Top products this month ────────────────────────────── */
$topProducts = $db->fetchAll(
    "SELECT p.name,
            COALESCE(SUM(ci.quantity),0) as qty,
            COALESCE(SUM(ci.quantity*ci.unit_price),0) as rev
     FROM product p
     JOIN cart_items ci ON p.id=ci.product_id
     JOIN carts c ON ci.cart_id=c.id
     WHERE c.status='completed' AND c.pharmacy_id=?
       AND DATE(c.created_at) >= ?
     GROUP BY p.id, p.name
     ORDER BY rev DESC LIMIT 5",
    [$pharmacyId, $thisMonth]
) ?: [];

/* ── Top sellers today ───────────────────────────────────── */
$topSellers = $db->fetchAll(
    "SELECT u.username,
            COALESCE(SUM(ci.quantity*ci.unit_price),0) as rev,
            COUNT(DISTINCT c.id) as orders
     FROM user u
     JOIN carts c ON u.id=c.seller_id AND DATE(c.created_at)=? AND c.status='completed'
     LEFT JOIN cart_items ci ON c.id=ci.cart_id
     WHERE u.role='SELLER' AND u.pharmacy_id=?
     GROUP BY u.id, u.username
     HAVING rev > 0
     ORDER BY rev DESC LIMIT 5",
    [$today, $pharmacyId]
) ?: [];

/* ── Critical stock alerts ───────────────────────────────── */
$criticalAlerts = $db->fetchAll(
    "SELECT p.name, p.stock
     FROM product p
     WHERE p.stock <= ? AND p.pharmacy_id=?
     ORDER BY p.stock ASC LIMIT 5",
    [max(1, intdiv($lowStockThreshold, 2)), $pharmacyId]
) ?: [];

/* ── Recent transactions ─────────────────────────────────── */
$recentActivity = $db->fetchAll(
    "SELECT c.id, c.created_at, u.username as seller,
            cl.name as client,
            COALESCE(SUM(ci.quantity*ci.unit_price),0) as amount
     FROM carts c
     LEFT JOIN user u ON c.seller_id=u.id
     LEFT JOIN client cl ON c.client_id=cl.id
     LEFT JOIN cart_items ci ON c.id=ci.cart_id
     WHERE c.status='completed' AND c.pharmacy_id=?
     GROUP BY c.id, c.created_at, u.username, cl.name
     ORDER BY c.created_at DESC LIMIT 8",
    [$pharmacyId]
) ?: [];

/* ── Subscription / forfait ──────────────────────────────── */
$forfait = null;
try {
    $forfait = $db->fetch(
        "SELECT plan, status, trial_ends_at FROM pharmacies WHERE id=?",
        [$pharmacyId]
    );
} catch (Exception $_e) {}

$planLabels = ['starter'=>'Starter','pro'=>'Pro','enterprise'=>'Enterprise'];
$planLabel  = $forfait ? ($planLabels[$forfait['plan']] ?? ucfirst($forfait['plan'])) : 'Starter';
$planStatus = $forfait ? $forfait['status'] : 'active';

$trialDaysLeft = 0;
$trialTotal    = 14; // default trial period
$trialPct      = 100;
if ($forfait && $forfait['status'] === 'trial' && $forfait['trial_ends_at']) {
    $trialDaysLeft = max(0, (int)ceil((strtotime($forfait['trial_ends_at']) - time()) / 86400));
    $trialPct      = min(100, max(0, round(($trialTotal - $trialDaysLeft) / $trialTotal * 100)));
}

/* ── Pharmacy info ───────────────────────────────────────── */
$pharmacyInfo = AppSettings::getPharmacyInfo();
?>
<!DOCTYPE html>
<html lang="<?php echo appSetting('language', 'fr'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getPageTitle('Administration'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/admin-dark-theme.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <?php echo AppSettings::getCSSVariables(); ?>
</head>
<body>
<div class="app-layout">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <?php include 'header.php'; ?>

        <div class="content-area">

            <!-- ── Greeting ──────────────────────────────── -->
            <?php
            $hour = (int)date('H');
            $greeting = $hour < 12 ? 'Bonjour' : ($hour < 18 ? 'Bonne journée' : 'Bonsoir');
            $firstName = explode(' ', $_SESSION['username'] ?? 'Admin')[0];
            ?>
            <div class="dash-greeting"><?php echo $greeting; ?>, <?php echo htmlspecialchars($firstName); ?> 👋</div>
            <div class="dash-sub">Voici ce qui se passe dans votre pharmacie aujourd'hui.</div>

            <!-- ── KPI Grid ───────────────────────────────── -->
            <div class="kpi-grid">

                <!-- Revenue today (featured) -->
                <div class="kpi-card kpi-featured" data-color="green">
                    <div class="kpi-header">
                        <div class="kpi-label">CA aujourd'hui</div>
                        <div class="kpi-badge"><i data-lucide="calendar"></i> Aujourd'hui</div>
                    </div>
                    <div class="kpi-val"><?php echo formatAppCurrency($todayRevenue); ?></div>
                    <div class="kpi-footer">
                        <?php $g = $revenueGrowth; ?>
                        <div class="kpi-delta <?php echo $g >= 0 ? 'up' : 'down'; ?>">
                            <i data-lucide="<?php echo $g >= 0 ? 'trending-up' : 'trending-down'; ?>"></i>
                            <?php echo ($g >= 0 ? '+' : '') . $g; ?>% vs hier
                        </div>
                        <canvas class="kpi-sparkline" id="spark0" width="72" height="28"></canvas>
                    </div>
                </div>

                <!-- Monthly revenue -->
                <div class="kpi-card" data-color="blue">
                    <div class="kpi-header">
                        <div class="kpi-label">CA ce mois</div>
                        <div class="kpi-icon"><i data-lucide="bar-chart-2"></i></div>
                    </div>
                    <div class="kpi-val"><?php echo formatAppCurrency($monthlyRevenue); ?></div>
                    <div class="kpi-footer">
                        <div class="kpi-delta" style="color:var(--ps-muted)">
                            <i data-lucide="shopping-bag"></i>
                            <?php echo $monthlyOrders; ?> commande<?php echo $monthlyOrders !== 1 ? 's' : ''; ?>
                        </div>
                        <canvas class="kpi-sparkline" id="spark1" width="72" height="28"></canvas>
                    </div>
                </div>

                <!-- Inventory -->
                <div class="kpi-card" data-color="amber">
                    <div class="kpi-header">
                        <div class="kpi-label">Produits en stock</div>
                        <div class="kpi-icon"><i data-lucide="package"></i></div>
                    </div>
                    <div class="kpi-val"><?php echo number_format((int)$inv['total']); ?></div>
                    <div class="kpi-footer">
                        <?php $low = (int)$inv['low_stock']; $out = (int)$inv['out_of_stock']; ?>
                        <?php if ($out > 0): ?>
                        <div class="kpi-delta down"><i data-lucide="alert-circle"></i><?php echo $out; ?> rupture<?php echo $out>1?'s':''; ?></div>
                        <?php elseif ($low > 0): ?>
                        <div class="kpi-delta" style="color:var(--ps-amber)"><i data-lucide="alert-triangle"></i><?php echo $low; ?> faible<?php echo $low>1?'s':''; ?></div>
                        <?php else: ?>
                        <div class="kpi-delta up"><i data-lucide="check-circle"></i>Tout en ordre</div>
                        <?php endif; ?>
                        <canvas class="kpi-sparkline" id="spark2" width="72" height="28"></canvas>
                    </div>
                </div>

                <!-- Expiring soon -->
                <?php $expiringCount = count($expiryRows); ?>
                <div class="kpi-card" data-color="<?php echo $expiringCount > 0 ? 'red' : 'purple'; ?>">
                    <div class="kpi-header">
                        <div class="kpi-label">Expirent bientôt</div>
                        <div class="kpi-icon"><i data-lucide="clock"></i></div>
                    </div>
                    <div class="kpi-val"><?php echo $expiringCount; ?></div>
                    <div class="kpi-footer">
                        <div class="kpi-delta <?php echo $expiringCount > 0 ? 'down' : ''; ?>" style="<?php echo $expiringCount === 0 ? 'color:var(--ps-muted)' : ''; ?>">
                            <?php if ($expiringCount > 0): ?>
                            <i data-lucide="alert-triangle"></i>Dans les 30 jours
                            <?php else: ?>
                            <i data-lucide="check-circle"></i>Aucun à surveiller
                            <?php endif; ?>
                        </div>
                        <canvas class="kpi-sparkline" id="spark3" width="72" height="28"></canvas>
                    </div>
                </div>

            </div><!-- /.kpi-grid -->

            <!-- ── Row 2: Revenue chart + Inventory donut ── -->
            <div class="dash-row">
                <div class="dash-grid-2">

                    <!-- Revenue overview -->
                    <div class="card">
                        <div class="card-head" style="align-items:flex-start;flex-wrap:wrap;gap:8px;">
                            <div>
                                <div style="font-size:11px;font-weight:600;color:var(--ps-muted);margin-bottom:2px;">Revenus — 7 derniers jours</div>
                                <div style="font-size:22px;font-weight:800;letter-spacing:-0.3px;color:var(--ps-text);"><?php echo formatAppCurrency($monthlyRevenue); ?></div>
                                <div style="display:flex;align-items:center;gap:5px;margin-top:3px;">
                                    <span style="width:8px;height:8px;border-radius:50%;background:var(--ps-green);display:inline-block;"></span>
                                    <span style="font-size:11.5px;color:var(--ps-muted);">Revenus (<?php echo appSetting('currency', 'FCFA'); ?>)</span>
                                </div>
                            </div>
                            <a href="reports.php" class="card-link">Voir rapports <i data-lucide="arrow-right"></i></a>
                        </div>
                        <div class="card-body" style="padding:16px 18px 12px;">
                            <div class="chart-wrap">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Inventory health donut -->
                    <div class="card">
                        <div class="card-head">
                            <span class="card-title"><i data-lucide="activity"></i> Santé du stock</span>
                            <a href="products.php" class="card-link">Voir tout <i data-lucide="arrow-right"></i></a>
                        </div>
                        <div class="donut-wrap">
                            <div class="donut-center">
                                <canvas id="donutChart" width="140" height="140"></canvas>
                                <div class="donut-center-label">
                                    <div class="donut-total-val"><?php echo (int)$inv['total']; ?></div>
                                    <div class="donut-total-sub">Total</div>
                                </div>
                            </div>
                            <div class="donut-legend">
                                <?php
                                $inStock  = (int)$inv['in_stock'];
                                $lowStock = (int)$inv['low_stock'];
                                $outStock = (int)$inv['out_of_stock'];
                                $total    = max(1, (int)$inv['total']);
                                ?>
                                <div class="donut-row">
                                    <div class="donut-left"><span class="donut-dot" style="background:var(--ps-green)"></span>En stock</div>
                                    <div class="donut-right"><?php echo $inStock; ?> <span style="font-weight:400;color:var(--ps-muted)">(<?php echo round($inStock/$total*100); ?>%)</span></div>
                                </div>
                                <div class="donut-row">
                                    <div class="donut-left"><span class="donut-dot" style="background:var(--ps-amber)"></span>Stock faible</div>
                                    <div class="donut-right"><?php echo $lowStock; ?> <span style="font-weight:400;color:var(--ps-muted)">(<?php echo round($lowStock/$total*100); ?>%)</span></div>
                                </div>
                                <div class="donut-row">
                                    <div class="donut-left"><span class="donut-dot" style="background:var(--ps-red)"></span>Rupture</div>
                                    <div class="donut-right"><?php echo $outStock; ?> <span style="font-weight:400;color:var(--ps-muted)">(<?php echo round($outStock/$total*100); ?>%)</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ── Row 3: Transactions + Forfait ────────── -->
            <div class="dash-row">
                <div class="dash-grid-2">

                    <!-- Recent transactions -->
                    <div class="card">
                        <div class="card-head">
                            <span class="card-title"><i data-lucide="receipt"></i> Transactions récentes</span>
                            <a href="reports.php" class="card-link">Voir tout <i data-lucide="arrow-right"></i></a>
                        </div>
                        <div class="card-body" style="padding:0 18px;">
                            <?php if (empty($recentActivity)): ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i data-lucide="inbox"></i></div>
                                <div class="empty-title">Aucune transaction</div>
                                <div class="empty-sub">Les ventes apparaîtront ici</div>
                            </div>
                            <?php else: foreach ($recentActivity as $txn): ?>
                            <div class="list-row">
                                <div style="width:34px;height:34px;border-radius:10px;background:var(--ps-green-bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--ps-green);">
                                    <i data-lucide="shopping-cart" style="width:15px;height:15px;"></i>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div class="list-name" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        <?php echo htmlspecialchars($txn['client'] ?: 'Client anonyme'); ?>
                                    </div>
                                    <div class="list-sub"><?php echo htmlspecialchars($txn['seller'] ?? ''); ?> · <?php echo timeAgo($txn['created_at']); ?></div>
                                </div>
                                <div class="list-val"><?php echo formatAppCurrency($txn['amount']); ?></div>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                    <!-- Forfait card -->
                    <div class="card">
                        <div class="card-head">
                            <span class="card-title"><i data-lucide="credit-card"></i> Abonnement</span>
                        </div>
                        <div class="forfait-card">
                            <div class="forfait-plan"><?php echo htmlspecialchars($planLabel); ?></div>
                            <span class="forfait-badge <?php echo htmlspecialchars($planStatus); ?>">
                                <?php echo $planStatus === 'active' ? 'Actif' : ($planStatus === 'trial' ? 'Essai' : 'Suspendu'); ?>
                            </span>
                            <?php if ($planStatus === 'trial' && $trialDaysLeft > 0): ?>
                            <div class="forfait-sep"></div>
                            <div class="forfait-days"><strong><?php echo $trialDaysLeft; ?> jours</strong> restants</div>
                            <div class="forfait-progress"><div class="forfait-fill" style="width:<?php echo $trialPct; ?>%"></div></div>
                            <div class="forfait-pct"><?php echo $trialPct; ?>% utilisé</div>
                            <?php elseif ($planStatus === 'active'): ?>
                            <div class="forfait-sep"></div>
                            <div style="font-size:12px;color:var(--ps-muted);">Votre abonnement est actif et à jour.</div>
                            <?php endif; ?>
                            <a href="mailto:support@digitech.cg" class="forfait-cta">Gérer l'abonnement →</a>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ── AI Banner ───────────────────────────────── -->
            <div class="dash-row">
                <div class="ai-banner">
                    <div class="ai-banner-left">
                        <div class="ai-icon"><i data-lucide="sparkles"></i></div>
                        <div>
                            <div class="ai-title">Optimisez votre pharmacie</div>
                            <div class="ai-sub">Suggestions intelligentes basées sur vos données</div>
                            <div class="ai-chips">
                                <?php if ($expiringCount > 0): ?>
                                <span class="ai-chip"><i data-lucide="alert-triangle"></i><?php echo $expiringCount; ?> produit(s) expirent bientôt</span>
                                <?php endif; ?>
                                <?php if ((int)$inv['low_stock'] > 0): ?>
                                <span class="ai-chip"><i data-lucide="package"></i><?php echo (int)$inv['low_stock']; ?> produit(s) à stock faible</span>
                                <?php endif; ?>
                                <?php if ($revenueGrowth < 0): ?>
                                <span class="ai-chip"><i data-lucide="trending-down"></i>CA en baisse de <?php echo abs($revenueGrowth); ?>%</span>
                                <?php else: ?>
                                <span class="ai-chip"><i data-lucide="trending-up"></i>CA en hausse de <?php echo $revenueGrowth; ?>%</span>
                                <?php endif; ?>
                                <?php if ($todayOrders === 0): ?>
                                <span class="ai-chip"><i data-lucide="shopping-cart"></i>Aucune vente aujourd'hui</span>
                                <?php else: ?>
                                <span class="ai-chip"><i data-lucide="check-circle"></i><?php echo $todayOrders; ?> vente(s) aujourd'hui</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <a href="reports.php" class="ai-cta">
                        <i data-lucide="bar-chart-2"></i> Voir Analytics
                    </a>
                </div>
            </div>

        </div><!-- /.content-area -->
    </div><!-- /.main-content -->
</div><!-- /.app-layout -->

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
lucide.createIcons();

// ── Chart.js defaults ──────────────────────────────────
Chart.defaults.font.family = "'Inter',system-ui,sans-serif";
Chart.defaults.font.size   = 12;
Chart.defaults.color       = '#6B7280';

// ── Shared data ────────────────────────────────────────
var sparkData = <?php echo json_encode(array_values($chartData)); ?>;
var sparkLabels = <?php echo json_encode(array_values($chartLabels)); ?>;

// ── Sparkline helper ────────────────────────────────────
function makeSparkline(id, color, data) {
    var el = document.getElementById(id);
    if (!el) return;
    new Chart(el, {
        type: 'line',
        data: {
            labels: sparkLabels,
            datasets: [{ data: data, borderColor: color, borderWidth: 2, fill: false, tension: 0.4, pointRadius: 0 }]
        },
        options: { responsive: false, animation: false, plugins: { legend: { display: false }, tooltip: { enabled: false } }, scales: { x: { display: false }, y: { display: false } } }
    });
}
makeSparkline('spark0', '#ffffff', sparkData);
makeSparkline('spark1', '#2563EB', sparkData);
makeSparkline('spark2', '#F59E0B', sparkData.map(function(v,i){ return Math.max(0, <?php echo (int)$inv['total']; ?> - i); }));
makeSparkline('spark3', <?php echo $expiringCount > 0 ? "'#DC2626'" : "'#7C3AED'"; ?>, [<?php echo implode(',', array_fill(0, 7, $expiringCount)); ?>]);

// ── Revenue line chart ──────────────────────────────────
(function() {
    var el = document.getElementById('revenueChart');
    if (!el) return;
    new Chart(el, {
        type: 'line',
        data: {
            labels: sparkLabels,
            datasets: [{
                label: 'Revenus',
                data: sparkData,
                borderColor: '#16A34A',
                backgroundColor: function(ctx) {
                    var g = ctx.chart.ctx.createLinearGradient(0,0,0,240);
                    g.addColorStop(0, 'rgba(22,163,74,0.15)');
                    g.addColorStop(1, 'rgba(22,163,74,0)');
                    return g;
                },
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#16A34A',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0F172A',
                    titleColor: '#fff',
                    bodyColor: 'rgba(255,255,255,0.7)',
                    padding: 10,
                    cornerRadius: 10,
                    callbacks: {
                        label: function(ctx) {
                            return ' ' + new Intl.NumberFormat('fr-FR').format(ctx.raw) + ' <?php echo addslashes(appSetting('currency','FCFA')); ?>';
                        }
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 11 } } },
                y: { grid: { color: '#F3F4F6' }, border: { display: false }, ticks: { font: { size: 11 }, callback: function(v){ return v >= 1000 ? (v/1000).toFixed(0)+'k' : v; } } }
            }
        }
    });
}());

// ── Donut chart ─────────────────────────────────────────
(function() {
    var el = document.getElementById('donutChart');
    if (!el) return;
    var inS = <?php echo $inStock; ?>, low = <?php echo $lowStock; ?>, out = <?php echo $outStock; ?>;
    if (inS + low + out === 0) { inS = 1; }
    new Chart(el, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [inS, low, out],
                backgroundColor: ['#16A34A', '#F59E0B', '#DC2626'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: false,
            cutout: '72%',
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            animation: { duration: 800 }
        }
    });
}());
</script>
</body>
</html>
