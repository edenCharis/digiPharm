<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ADMIN') {
    header('Location: ../logout.php');
    exit();
}

require_once '../config/database.php';
require_once '../config/app_settings.php';
AppSettings::init($db);

// Pagination
$recordsPerPage = 20;
$currentPage    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset         = ($currentPage - 1) * $recordsPerPage;

// Filters
$searchTerm   = isset($_GET['search'])        ? trim($_GET['search'])              : '';
$userFilter   = isset($_GET['user_filter'])   ? (int)$_GET['user_filter']          : '';
$actionFilter = isset($_GET['action_filter']) ? trim($_GET['action_filter'])        : '';
$dateFilter   = isset($_GET['date_filter'])   ? trim($_GET['date_filter'])          : '';

// WHERE clause
$whereClause = "WHERE l.pharmacy_id = ?";
$params      = [$pharmacyId];

if (!empty($searchTerm)) {
    $whereClause .= " AND (l.description LIKE ? OR l.tableName LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}
if (!empty($userFilter)) {
    $whereClause .= " AND l.userId = ?";
    $params[] = $userFilter;
}
if (!empty($actionFilter)) {
    $whereClause .= " AND l.action = ?";
    $params[] = $actionFilter;
}
if (!empty($dateFilter)) {
    $whereClause .= " AND DATE(l.createdAt) = ?";
    $params[] = $dateFilter;
}

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM log l LEFT JOIN user u ON l.userId = u.id $whereClause");
$countStmt->execute($params);
$totalRecords = $countStmt->fetch()['total'];
$totalPages   = max(1, ceil($totalRecords / $recordsPerPage));

// Rows
$rowParams   = array_merge($params, [$recordsPerPage, $offset]);
$stmt        = $pdo->prepare("
    SELECT l.id, l.userId, u.username, u.email,
           l.action, l.tableName, l.recordId, l.description, l.createdAt
    FROM log l
    LEFT JOIN user u ON l.userId = u.id
    $whereClause
    ORDER BY l.createdAt DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($rowParams);
$logs = $stmt->fetchAll();

// Filter dropdowns
$usersStmt = $pdo->prepare("SELECT DISTINCT u.id, u.username FROM log l JOIN user u ON l.userId = u.id WHERE l.pharmacy_id = ? ORDER BY u.username");
$usersStmt->execute([$pharmacyId]);
$users = $usersStmt->fetchAll();

$actionsStmt = $pdo->prepare("SELECT DISTINCT action FROM log WHERE pharmacy_id = ? ORDER BY action");
$actionsStmt->execute([$pharmacyId]);
$actions = $actionsStmt->fetchAll();

// Helpers
function translateAction($action) {
    $map = ['CREATE'=>'Création','UPDATE'=>'Modification','DELETE'=>'Suppression',
            'LOGIN'=>'Connexion','LOGOUT'=>'Déconnexion','VIEW'=>'Consultation',
            'EXPORT'=>'Export','IMPORT'=>'Import'];
    return $map[$action] ?? $action;
}
function translateTableName($t) {
    $map = ['users'=>'Utilisateurs','products'=>'Produits','sales'=>'Ventes',
            'inventory'=>'Inventaire','suppliers'=>'Fournisseurs','categories'=>'Catégories',
            'cash_registers'=>'Caisses','transactions'=>'Transactions'];
    return $map[$t] ?? $t;
}
function getActionBadgeClass($action) {
    switch(strtoupper($action)) {
        case 'CREATE': case 'LOGIN':   return 'success';
        case 'UPDATE': case 'VIEW':    return 'info';
        case 'DELETE': case 'LOGOUT':  return 'danger';
        case 'EXPORT': case 'IMPORT':  return 'warning';
        default:                       return 'secondary';
    }
}

// Query string for pagination links
$filterQS = http_build_query(array_filter([
    'search'        => $searchTerm,
    'user_filter'   => $userFilter ?: '',
    'action_filter' => $actionFilter,
    'date_filter'   => $dateFilter,
]));
if ($filterQS) $filterQS = '&' . $filterQS;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs d'Activité — <?php echo htmlspecialchars(appName()); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">    <link rel="stylesheet" href="../assets/css/admin-dark-theme.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/header.css">
</head>
<body>
<div class="app-layout">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <?php include 'header.php'; ?>

        <div class="content-area">

            <!-- KPI -->
            <div class="kpi-grid" style="margin-bottom:20px">
                <div class="kpi-card" data-color="green">
                    <div class="kpi-icon"><i data-lucide="file-text"></i></div>
                    <div class="kpi-label">Total entrées</div>
                    <div class="kpi-val"><?php echo number_format($totalRecords); ?></div>
                </div>
                <div class="kpi-card" data-color="blue">
                    <div class="kpi-icon"><i data-lucide="users"></i></div>
                    <div class="kpi-label">Utilisateurs</div>
                    <div class="kpi-val"><?php echo count($users); ?></div>
                </div>
                <div class="kpi-card" data-color="amber">
                    <div class="kpi-icon"><i data-lucide="zap"></i></div>
                    <div class="kpi-label">Types d'actions</div>
                    <div class="kpi-val"><?php echo count($actions); ?></div>
                </div>
                <div class="kpi-card" data-color="purple">
                    <div class="kpi-icon"><i data-lucide="layers"></i></div>
                    <div class="kpi-label">Page</div>
                    <div class="kpi-val"><?php echo $currentPage; ?><span style="font-size:14px;font-weight:500;color:var(--muted)">/<?php echo $totalPages; ?></span></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card" style="margin-bottom:20px">
                <div class="card-head">
                    <span class="card-title"><i data-lucide="filter" style="width:15px;height:15px;"></i> Filtres</span>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="log-filters">
                            <input type="text" name="search" class="log-input"
                                   placeholder="Rechercher description, table…"
                                   value="<?php echo htmlspecialchars($searchTerm); ?>">

                            <select name="user_filter" class="log-input">
                                <option value="">Tous les utilisateurs</option>
                                <?php foreach($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo $userFilter == $u['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u['username']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>

                            <select name="action_filter" class="log-input">
                                <option value="">Toutes les actions</option>
                                <?php foreach($actions as $a): ?>
                                <option value="<?php echo $a['action']; ?>" <?php echo $actionFilter === $a['action'] ? 'selected' : ''; ?>>
                                    <?php echo translateAction($a['action']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>

                            <input type="date" name="date_filter" class="log-input"
                                   value="<?php echo htmlspecialchars($dateFilter); ?>">

                            <div style="display:flex;gap:6px;">
                                <button type="submit" style="height:36px;padding:0 16px;background:var(--green);color:#fff;border:none;border-radius:var(--radius-sm);font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;">
                                    <i data-lucide="search" style="width:14px;height:14px;"></i> Filtrer
                                </button>
                                <a href="logs.php" style="height:36px;padding:0 12px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:13px;color:var(--muted);background:#fff;display:flex;align-items:center;text-decoration:none;">
                                    <i data-lucide="x" style="width:14px;height:14px;"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table card -->
            <div class="card">
                <div class="card-head">
                    <span class="card-title"><i data-lucide="list" style="width:15px;height:15px;"></i> Logs d'activité</span>
                    <span style="font-size:12px;color:var(--muted);"><?php echo number_format($totalRecords); ?> entrée(s)</span>
                </div>
                <div class="card-body" style="padding:0">
                    <div class="log-table-wrap">
                        <table class="log-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Utilisateur</th>
                                    <th>Action</th>
                                    <th>Table</th>
                                    <th>Description</th>
                                    <th>Date / Heure</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center;padding:48px 24px;color:var(--muted);">
                                        <i data-lucide="inbox" style="width:32px;height:32px;display:block;margin:0 auto 10px;"></i>
                                        Aucun log trouvé avec ces critères
                                    </td>
                                </tr>
                            <?php else: foreach ($logs as $log): ?>
                                <tr class="log-row"
                                    data-id="<?php echo $log['id']; ?>"
                                    data-user="<?php echo htmlspecialchars($log['username'] ?? 'Supprimé'); ?>"
                                    data-action="<?php echo htmlspecialchars(translateAction($log['action'])); ?>"
                                    data-table="<?php echo htmlspecialchars(translateTableName($log['tableName'] ?? '')); ?>"
                                    data-record="<?php echo htmlspecialchars($log['recordId'] ?? '-'); ?>"
                                    data-desc="<?php echo htmlspecialchars($log['description'] ?? ''); ?>"
                                    data-date="<?php echo date('d/m/Y H:i:s', strtotime($log['createdAt'])); ?>">
                                    <td style="color:var(--muted-lite);font-size:12px;"><?php echo $log['id']; ?></td>
                                    <td>
                                        <div class="log-user">
                                            <div class="user-avatar-sm"><?php echo strtoupper(substr($log['username'] ?? 'U', 0, 1)); ?></div>
                                            <div>
                                                <div style="font-weight:600;font-size:13px;color:var(--text);"><?php echo htmlspecialchars($log['username'] ?? 'Supprimé'); ?></div>
                                                <?php if (!empty($log['email'])): ?>
                                                <div style="font-size:11px;color:var(--muted);"><?php echo htmlspecialchars($log['email']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="log-badge log-badge-<?php echo getActionBadgeClass($log['action']); ?>">
                                            <?php echo translateAction($log['action']); ?>
                                        </span>
                                    </td>
                                    <td style="font-size:12px;color:var(--muted);"><?php echo htmlspecialchars(translateTableName($log['tableName'] ?? '')); ?></td>
                                    <td><div class="log-desc"><?php echo htmlspecialchars($log['description'] ?? ''); ?></div></td>
                                    <td style="font-size:12px;color:var(--muted);white-space:nowrap;"><?php echo date('d/m/Y H:i', strtotime($log['createdAt'])); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPages > 1): ?>
                    <div class="log-pagination">
                        <?php if ($currentPage > 1): ?>
                        <a class="pg-btn" href="?page=<?php echo $currentPage - 1; ?><?php echo $filterQS; ?>">
                            <i data-lucide="chevron-left"></i>
                        </a>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $currentPage - 2);
                        $endPage   = min($totalPages, $currentPage + 2);
                        if ($startPage > 1): ?><span class="pg-dots">…</span><?php endif;
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a class="pg-btn <?php echo $i === $currentPage ? 'active' : ''; ?>"
                           href="?page=<?php echo $i; ?><?php echo $filterQS; ?>"><?php echo $i; ?></a>
                        <?php endfor;
                        if ($endPage < $totalPages): ?><span class="pg-dots">…</span><?php endif; ?>

                        <?php if ($currentPage < $totalPages): ?>
                        <a class="pg-btn" href="?page=<?php echo $currentPage + 1; ?><?php echo $filterQS; ?>">
                            <i data-lucide="chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /.content-area -->
    </div><!-- /.main-content -->
</div><!-- /.app-layout -->

<!-- Log detail modal -->
<div class="modal-overlay" id="logModal">
    <div class="modal-box">
        <div class="modal-head">
            <div class="modal-title">
                <i data-lucide="file-text"></i>
                Détail du log
            </div>
            <button class="modal-close" id="modalClose"><i data-lucide="x"></i></button>
        </div>
        <div class="modal-body">
            <div class="modal-row">
                <span class="modal-key">ID</span>
                <span class="modal-val" id="mId">—</span>
            </div>
            <div class="modal-row">
                <span class="modal-key">Utilisateur</span>
                <span class="modal-val" id="mUser">—</span>
            </div>
            <div class="modal-row">
                <span class="modal-key">Action</span>
                <span class="modal-val" id="mAction">—</span>
            </div>
            <div class="modal-row">
                <span class="modal-key">Table</span>
                <span class="modal-val" id="mTable">—</span>
            </div>
            <div class="modal-row">
                <span class="modal-key">ID Enregistrement</span>
                <span class="modal-val" id="mRecord">—</span>
            </div>
            <div class="modal-row modal-row-full">
                <span class="modal-key">Description</span>
                <span class="modal-val" id="mDesc" style="white-space:pre-wrap;color:var(--muted);font-weight:400;">—</span>
            </div>
            <div class="modal-row">
                <span class="modal-key">Date / Heure</span>
                <span class="modal-val" id="mDate">—</span>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script>
lucide.createIcons();

(function () {
    var modal  = document.getElementById('logModal');
    var mClose = document.getElementById('modalClose');

    document.querySelectorAll('.log-row').forEach(function (row) {
        row.addEventListener('click', function () {
            document.getElementById('mId').textContent     = this.dataset.id     || '—';
            document.getElementById('mUser').textContent   = this.dataset.user   || '—';
            document.getElementById('mAction').textContent = this.dataset.action || '—';
            document.getElementById('mTable').textContent  = this.dataset.table  || '—';
            document.getElementById('mRecord').textContent = this.dataset.record || '—';
            document.getElementById('mDesc').textContent   = this.dataset.desc   || '—';
            document.getElementById('mDate').textContent   = this.dataset.date   || '—';
            modal.classList.add('open');
        });
    });

    mClose.addEventListener('click', function () { modal.classList.remove('open'); });
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.classList.remove('open'); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') modal.classList.remove('open'); });
}());
</script>
</body>
</html>
