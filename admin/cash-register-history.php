<?php
session_start();
if($_SESSION["role"] === "ADMIN" && $_SESSION["id"] == session_id()){

error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    include '../config/database.php';
    if (!isset($db)) throw new Exception('Database connection not found');

    // ── Filters from GET ──────────────────────────────────────────
    $filterStatus    = $_GET['status']    ?? 'all';           // all | open | closed
    $filterCashier   = $_GET['cashier']   ?? '';
    $filterDateFrom  = $_GET['date_from'] ?? date('Y-m-01');  // first day of current month
    $filterDateTo    = $_GET['date_to']   ?? date('Y-m-d');
    $currentPage     = max(1, (int)($_GET['page'] ?? 1));
    $perPage         = 15;
    $offset          = ($currentPage - 1) * $perPage;

    // ── Build WHERE clause ─────────────────────────────────────────
    $whereParts = ["DATE(cr.opening_time) BETWEEN ? AND ?", "cr.pharmacy_id = ?"];
    $params     = [$filterDateFrom, $filterDateTo, $pharmacyId];

    if ($filterStatus !== 'all') {
        $whereParts[] = "cr.status = ?";
        $params[]     = $filterStatus;
    }
    if ($filterCashier !== '') {
        $whereParts[] = "cr.cashier_id = ?";
        $params[]     = $filterCashier;
    }

    $whereSQL = "WHERE " . implode(" AND ", $whereParts);

    // ── Count total for pagination ──────────────────────────────────
    $countSQL = "SELECT COUNT(*) as total FROM cash_register cr $whereSQL";
    $countRow = $db->fetch($countSQL, $params);
    $totalRows = $countRow ? (int)$countRow['total'] : 0;
    $totalPages = max(1, ceil($totalRows / $perPage));

    // ── Main query ─────────────────────────────────────────────────
    $historySQL = "SELECT 
                        cr.id,
                        cr.cashier_id,
                        cr.opening_time,
                        cr.closing_time,
                        cr.status,
                        cr.initial_amount,
                        cr.final_amount,
                        u.username  AS cashier_name,
                        u.role      AS cashier_role,
                        COALESCE(SUM(s.totalAmount), 0)     AS total_sales,
                        COUNT(s.id)                         AS sales_count,
                        COALESCE(SUM(s.cashReceived), 0)    AS total_cash_received,
                        COALESCE(SUM(s.changeAmount), 0)    AS total_change,
                        TIMESTAMPDIFF(MINUTE, cr.opening_time,
                            IFNULL(cr.closing_time, NOW()))  AS duration_minutes
                   FROM cash_register cr
                   LEFT JOIN user  u ON cr.cashier_id = u.id
                   LEFT JOIN sale  s ON cr.id = s.cash_register_id
                   $whereSQL
                   GROUP BY cr.id, cr.cashier_id, cr.opening_time, cr.closing_time,
                            cr.status, cr.initial_amount, cr.final_amount, u.username, u.role
                   ORDER BY cr.opening_time DESC
                   LIMIT $perPage OFFSET $offset";

    $history = $db->fetchAll($historySQL, $params);
    if (!$history) $history = [];

    // ── Summary stats for filtered range ──────────────────────────
    $summarySQL = "SELECT
                        COUNT(cr.id)                            AS total_registers,
                        COUNT(CASE WHEN cr.status='open'   THEN 1 END) AS open_count,
                        COUNT(CASE WHEN cr.status='closed' THEN 1 END) AS closed_count,
                        COALESCE(SUM(s.totalAmount), 0)         AS total_revenue,
                        COUNT(s.id)                             AS total_transactions,
                        COALESCE(AVG(CASE WHEN cr.status='closed'
                            THEN cr.final_amount - (cr.initial_amount + COALESCE(ss.total,0))
                        END), 0) AS avg_difference
                   FROM cash_register cr
                   LEFT JOIN user u ON cr.cashier_id = u.id
                   LEFT JOIN sale s ON cr.id = s.cash_register_id
                   LEFT JOIN (
                        SELECT cash_register_id, SUM(totalAmount) AS total FROM sale GROUP BY cash_register_id
                   ) ss ON cr.id = ss.cash_register_id
                   $whereSQL";

    $summary = $db->fetch($summarySQL, $params);

    // ── Cashier list for filter dropdown ──────────────────────────
    $cashiersSQL = "SELECT id, username, role FROM user WHERE role IN ('CASHIER','SELLER') AND statut=1 AND pharmacy_id = ? ORDER BY username";
    $cashiers = $db->fetchAll($cashiersSQL, [$pharmacyId]) ?: [];

} catch (Exception $e) {
    die('Erreur: ' . $e->getMessage());
}

// ── Helpers ────────────────────────────────────────────────────────
function fc($n)  { return number_format($n, 0, ',', ' ') . ' XAF'; }
function fdt($d) { return $d ? date('d/m/Y H:i', strtotime($d)) : '–'; }
function fdur($minutes) {
    if ($minutes < 60)  return $minutes . ' min';
    $h = floor($minutes / 60);
    $m = $minutes % 60;
    return $h . 'h' . ($m ? ' ' . $m . 'min' : '');
}
function diffClass($v) { return $v > 0 ? 'positive' : ($v < 0 ? 'negative' : 'neutral'); }
function diffLabel($v) {
    if ($v == 0) return '<span class="badge-neutral">Exact ✓</span>';
    $s = fc(abs($v));
    return $v > 0
        ? '<span class="badge-pos">+' . $s . ' Surplus</span>'
        : '<span class="badge-neg">−' . $s . ' Manquant</span>';
}

// Build query string without 'page' for pagination links
function buildQuery($overrides = []) {
    $params = array_merge([
        'status'    => $_GET['status']    ?? 'all',
        'cashier'   => $_GET['cashier']   ?? '',
        'date_from' => $_GET['date_from'] ?? date('Y-m-01'),
        'date_to'   => $_GET['date_to']   ?? date('Y-m-d'),
    ], $overrides);
    return http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== 'all'));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys – Historique des caisses</title>
    <link rel="stylesheet" href="../assets/css/design-system.css">
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <style>
        /* ── Page layout ─────────────────────────────── */
        .page-hero {
            background: var(--ds-green);
            color: white;
            padding: 1.75rem 2rem;
            border-radius: 14px;
            margin-bottom: 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .page-hero h1 { margin: 0; font-size: 1.5rem; font-weight: 700; display:flex;align-items:center;gap:.6rem; }
        .page-hero p  { margin: .3rem 0 0; opacity: .8; font-size: .9rem; }

        /* ── Summary cards ───────────────────────────── */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.1rem;
            margin-bottom: 1.75rem;
        }
        .s-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 1px 6px rgba(0,0,0,.08);
            border: 1px solid var(--ds-border);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .s-icon {
            width: 2.75rem; height: 2.75rem; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .s-icon.blue   { background:#dbeafe; color:#2563eb; }
        .s-icon.green  { background:var(--ds-green-bg); color:var(--ds-green); }
        .s-icon.orange { background:#fef3c7; color:#d97706; }
        .s-icon.purple { background:#ede9fe; color:#7c3aed; }
        .s-icon.red    { background:#fee2e2; color:#dc2626; }

        .s-label { font-size:.8rem; color:var(--ds-text-400); font-weight:500; margin-bottom:.2rem; }
        .s-value { font-size:1.3rem; font-weight:700; color:var(--ds-text-900); line-height:1.2; }

        /* ── Filter bar ──────────────────────────────── */
        .filter-bar {
            background: white;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 1px 6px rgba(0,0,0,.08);
            border: 1px solid var(--ds-border);
            margin-bottom: 1.5rem;
        }
        .filter-bar form { display:flex; flex-wrap:wrap; gap:.75rem; align-items:flex-end; }
        .filter-group { display:flex; flex-direction:column; gap:.3rem; min-width:140px; flex:1; }
        .filter-group label { font-size:.8rem; font-weight:600; color:var(--ds-text-900); }
        .filter-group select,
        .filter-group input  { border:1px solid var(--ds-border); border-radius:8px; padding:.5rem .75rem; font-size:.875rem; background:white; }
        .filter-group select:focus,
        .filter-group input:focus  { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.1); }
        .btn-filter { background:#2563eb; color:white; border:none; padding:.55rem 1.25rem; border-radius:8px; font-weight:600; font-size:.875rem; cursor:pointer; display:flex; align-items:center; gap:.4rem; transition:background .2s; }
        .btn-filter:hover { background:#1d4ed8; }
        .btn-reset  { background:var(--ds-surface-alt); color:var(--ds-text-900); border:1px solid var(--ds-border); padding:.55rem 1rem; border-radius:8px; font-weight:500; font-size:.875rem; cursor:pointer; text-decoration:none; display:flex; align-items:center; gap:.4rem; transition:background .2s; }
        .btn-reset:hover { background:var(--ds-border); color:var(--ds-text-900); }

        /* ── Table ───────────────────────────────────── */
        .hist-table-wrap {
            background: white;
            border-radius: 14px;
            box-shadow: 0 1px 6px rgba(0,0,0,.08);
            border: 1px solid var(--ds-border);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .hist-table-head {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1.2rem 1.5rem;
            background: #f8fafc;
            border-bottom: 1px solid var(--ds-border);
        }
        .hist-table-head h2 { margin:0; font-size:1rem; font-weight:700; color:var(--ds-text-900); display:flex;align-items:center;gap:.5rem; }
        .result-count { font-size:.85rem; color:var(--ds-text-400); }

        table.hist { width:100%; border-collapse:collapse; }
        table.hist thead th {
            background:#f1f5f9; color:#475569; font-weight:700; font-size:.8rem;
            padding:.75rem 1rem; text-align:left; white-space:nowrap;
            border-bottom: 1px solid #e2e8f0;
        }
        table.hist tbody tr { border-bottom:1px solid var(--ds-surface-alt); transition:background .15s; }
        table.hist tbody tr:last-child { border-bottom:none; }
        table.hist tbody tr:hover { background:#f8fafc; }
        table.hist td { padding:.85rem 1rem; font-size:.875rem; vertical-align:middle; }

        /* status pill */
        .pill { display:inline-flex; align-items:center; gap:.35rem; padding:.25rem .7rem; border-radius:9999px; font-size:.78rem; font-weight:700; white-space:nowrap; }
        .pill-open   { background:var(--ds-green-bg); color:#065f46; }
        .pill-closed { background:var(--ds-surface-alt); color:var(--ds-text-600); }

        /* difference badges */
        .badge-pos     { background:var(--ds-green-bg); color:#065f46; padding:.2rem .55rem; border-radius:6px; font-size:.78rem; font-weight:700; }
        .badge-neg     { background:#fee2e2; color:#991b1b; padding:.2rem .55rem; border-radius:6px; font-size:.78rem; font-weight:700; }
        .badge-neutral { background:var(--ds-surface-alt); color:var(--ds-text-900); padding:.2rem .55rem; border-radius:6px; font-size:.78rem; font-weight:700; }

        .cashier-cell { display:flex; align-items:center; gap:.6rem; }
        .cashier-avatar {
            width:2rem; height:2rem; border-radius:50%; background:#2563eb;
            color:white; font-weight:700; font-size:.8rem;
            display:flex; align-items:center; justify-content:center; flex-shrink:0;
        }
        .cashier-name  { font-weight:600; color:var(--ds-text-900); font-size:.875rem; }
        .cashier-role  { font-size:.75rem; color:var(--ds-text-400); }

        .amount-cell   { font-weight:700; color:var(--ds-text-900); }
        .sub-info      { font-size:.75rem; color:var(--ds-text-400); margin-top:.1rem; }

        /* action buttons */
        .btn-details {
            background:#2563eb; color:white; border:none; padding:.4rem .9rem;
            border-radius:7px; font-size:.8rem; font-weight:600; cursor:pointer;
            display:inline-flex; align-items:center; gap:.35rem;
            text-decoration:none; transition:background .2s;
        }
        .btn-details:hover { background:#1d4ed8; color:white; }

        /* ── Pagination ──────────────────────────────── */
        .pagination-wrap {
            display: flex; justify-content: space-between; align-items: center;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--ds-border);
            flex-wrap: wrap; gap: .75rem;
        }
        .pag-info { font-size:.85rem; color:var(--ds-text-400); }
        .pag-links { display:flex; gap:.4rem; flex-wrap:wrap; }
        .pag-btn {
            padding:.4rem .75rem; border-radius:7px; font-size:.85rem; font-weight:600;
            border:1px solid var(--ds-border); background:white; color:var(--ds-text-900);
            text-decoration:none; display:inline-flex; align-items:center; gap:.3rem;
            transition:all .15s; cursor:pointer;
        }
        .pag-btn:hover  { background:#2563eb; color:white; border-color:#2563eb; }
        .pag-btn.active { background:#2563eb; color:white; border-color:#2563eb; }
        .pag-btn.disabled { opacity:.4; pointer-events:none; }

        /* ── Empty state ─────────────────────────────── */
        .empty-state { text-align:center; padding:4rem 2rem; color:var(--ds-text-400); }
        .empty-state svg { width:4rem; height:4rem; margin-bottom:1rem; opacity:.4; }
        .empty-state h3 { font-size:1.1rem; color:var(--ds-text-400); margin-bottom:.5rem; }

        /* ── Back link ───────────────────────────────── */
        .back-link {
            color:white; opacity:.85; text-decoration:none; font-size:.875rem;
            display:inline-flex; align-items:center; gap:.4rem;
            border:1px solid rgba(255,255,255,.3); padding:.4rem .9rem;
            border-radius:8px; transition:opacity .2s;
        }
        .back-link:hover { opacity:1; color:white; }

        @media (max-width: 900px) {
            table.hist thead th:nth-child(5),
            table.hist td:nth-child(5),
            table.hist thead th:nth-child(6),
            table.hist td:nth-child(6) { display:none; }
        }
        @media (max-width: 640px) {
            .summary-grid { grid-template-columns: 1fr 1fr; }
            table.hist thead th:nth-child(4),
            table.hist td:nth-child(4)  { display:none; }
        }
    </style>
</head>
<body>
<div class="app-layout">
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <?php include 'header.php'; ?>

        <main class="content-area">

            <!-- ── Hero ─────────────────────────────────────── -->
            <div class="page-hero">
                <div>
                    <h1><i data-lucide="history" style="width:1.3rem;height:1.3rem;"></i> Historique des caisses</h1>
                    <p>Consultez et analysez toutes les sessions de caisse</p>
                </div>
                <a href="cash-register.php" class="back-link">
                    <i data-lucide="arrow-left" style="width:14px;height:14px;"></i>
                    Retour aux caisses
                </a>
            </div>

            <!-- ── Summary cards ────────────────────────────── -->
            <div class="summary-grid">
                <div class="s-card">
                    <div class="s-icon blue"><i data-lucide="layers" style="width:1.2rem;height:1.2rem;"></i></div>
                    <div>
                        <div class="s-label">Total caisses</div>
                        <div class="s-value"><?= $summary['total_registers'] ?? 0 ?></div>
                    </div>
                </div>
                <div class="s-card">
                    <div class="s-icon green"><i data-lucide="activity" style="width:1.2rem;height:1.2rem;"></i></div>
                    <div>
                        <div class="s-label">Ouvertes</div>
                        <div class="s-value"><?= $summary['open_count'] ?? 0 ?></div>
                    </div>
                </div>
                <div class="s-card">
                    <div class="s-icon orange"><i data-lucide="check-circle" style="width:1.2rem;height:1.2rem;"></i></div>
                    <div>
                        <div class="s-label">Fermées</div>
                        <div class="s-value"><?= $summary['closed_count'] ?? 0 ?></div>
                    </div>
                </div>
                <div class="s-card">
                    <div class="s-icon purple"><i data-lucide="trending-up" style="width:1.2rem;height:1.2rem;"></i></div>
                    <div>
                        <div class="s-label">CA total</div>
                        <div class="s-value" style="font-size:1rem;"><?= fc($summary['total_revenue'] ?? 0) ?></div>
                    </div>
                </div>
                <div class="s-card">
                    <div class="s-icon blue"><i data-lucide="receipt" style="width:1.2rem;height:1.2rem;"></i></div>
                    <div>
                        <div class="s-label">Transactions</div>
                        <div class="s-value"><?= $summary['total_transactions'] ?? 0 ?></div>
                    </div>
                </div>
            </div>

            <!-- ── Filters ───────────────────────────────────── -->
            <div class="filter-bar">
                <form method="GET" action="">
                    <div class="filter-group">
                        <label>Statut</label>
                        <select name="status">
                            <option value="all"    <?= $filterStatus==='all'    ? 'selected':'' ?>>Tous</option>
                            <option value="open"   <?= $filterStatus==='open'   ? 'selected':'' ?>>Ouvertes</option>
                            <option value="closed" <?= $filterStatus==='closed' ? 'selected':'' ?>>Fermées</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Caissier</label>
                        <select name="cashier">
                            <option value="">Tous les caissiers</option>
                            <?php foreach ($cashiers as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $filterCashier==$c['id'] ? 'selected':'' ?>>
                                    <?= htmlspecialchars($c['username']) ?> (<?= $c['role'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Du</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>">
                    </div>

                    <div class="filter-group">
                        <label>Au</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>">
                    </div>

                    <button type="submit" class="btn-filter">
                        <i data-lucide="search" style="width:14px;height:14px;"></i>
                        Filtrer
                    </button>
                    <a href="?" class="btn-reset">
                        <i data-lucide="x" style="width:14px;height:14px;"></i>
                        Réinitialiser
                    </a>
                </form>
            </div>

            <!-- ── Table ────────────────────────────────────── -->
            <div class="hist-table-wrap">
                <div class="hist-table-head">
                    <h2><i data-lucide="table" style="width:16px;height:16px;"></i> Liste des sessions</h2>
                    <span class="result-count"><?= $totalRows ?> résultat<?= $totalRows > 1 ? 's' : '' ?> · Page <?= $currentPage ?>/<?= $totalPages ?></span>
                </div>

                <?php if (empty($history)): ?>
                    <div class="empty-state">
                        <i data-lucide="inbox" style="display:block;width:3.5rem;height:3.5rem;margin:0 auto 1rem;opacity:.3;"></i>
                        <h3>Aucune caisse trouvée</h3>
                        <p>Modifiez vos filtres pour élargir la recherche.</p>
                    </div>
                <?php else: ?>
                    <table class="hist">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Caissier</th>
                                <th>Ouverture</th>
                                <th>Fermeture</th>
                                <th>Durée</th>
                                <th>Montant init.</th>
                                <th>Ventes</th>
                                <th>Transactions</th>
                                <th>Écart</th>
                                <th>Statut</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($history as $r):
                            $expected = $r['initial_amount'] + $r['total_sales'];
                            $diff     = ($r['status'] === 'closed') ? ($r['final_amount'] - $expected) : null;
                        ?>
                            <tr>
                                <!-- ID -->
                                <td style="font-weight:700;color:var(--ds-text-400);font-size:.8rem;">#<?= $r['id'] ?></td>

                                <!-- Caissier -->
                                <td>
                                    <div class="cashier-cell">
                                        <div class="cashier-avatar">
                                            <?= strtoupper(substr($r['cashier_name'] ?? 'U', 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="cashier-name"><?= htmlspecialchars($r['cashier_name'] ?? '–') ?></div>
                                            <div class="cashier-role"><?= $r['cashier_role'] ?></div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Ouverture -->
                                <td>
                                    <div><?= date('d/m/Y', strtotime($r['opening_time'])) ?></div>
                                    <div class="sub-info"><?= date('H:i', strtotime($r['opening_time'])) ?></div>
                                </td>

                                <!-- Fermeture -->
                                <td>
                                    <?php if ($r['closing_time']): ?>
                                        <div><?= date('d/m/Y', strtotime($r['closing_time'])) ?></div>
                                        <div class="sub-info"><?= date('H:i', strtotime($r['closing_time'])) ?></div>
                                    <?php else: ?>
                                        <span style="color:var(--ds-text-400);font-size:.8rem;">En cours…</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Durée -->
                                <td style="color:var(--ds-text-400);font-size:.85rem;">
                                    <?= fdur((int)$r['duration_minutes']) ?>
                                </td>

                                <!-- Montant initial -->
                                <td>
                                    <div class="amount-cell"><?= fc($r['initial_amount']) ?></div>
                                </td>

                                <!-- Ventes -->
                                <td>
                                    <div class="amount-cell"><?= fc($r['total_sales']) ?></div>
                                    <?php if ($r['status'] === 'closed'): ?>
                                        <div class="sub-info">Final : <?= fc($r['final_amount']) ?></div>
                                    <?php endif; ?>
                                </td>

                                <!-- Transactions -->
                                <td style="text-align:center;font-weight:700;color:var(--ds-text-900);">
                                    <?= $r['sales_count'] ?>
                                </td>

                                <!-- Écart -->
                                <td>
                                    <?php if ($r['status'] === 'closed' && $diff !== null): ?>
                                        <?= diffLabel($diff) ?>
                                    <?php else: ?>
                                        <span style="color:var(--ds-text-400);font-size:.8rem;">–</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Statut -->
                                <td>
                                    <?php if ($r['status'] === 'open'): ?>
                                        <span class="pill pill-open">
                                            <i data-lucide="zap" style="width:11px;height:11px;"></i> Ouverte
                                        </span>
                                    <?php else: ?>
                                        <span class="pill pill-closed">
                                            <i data-lucide="lock" style="width:11px;height:11px;"></i> Fermée
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Action -->
                                <td>
                                    <a href="register_details.php?id=<?= $r['id'] ?>" class="btn-details">
                                        <i data-lucide="eye" style="width:13px;height:13px;"></i>
                                        Détails
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div class="pagination-wrap">
                        <div class="pag-info">
                            Affichage de <?= min($offset + 1, $totalRows) ?>–<?= min($offset + $perPage, $totalRows) ?>
                            sur <?= $totalRows ?> entrée<?= $totalRows > 1 ? 's' : '' ?>
                        </div>
                        <div class="pag-links">
                            <!-- Prev -->
                            <?php if ($currentPage > 1): ?>
                                <a class="pag-btn" href="?<?= buildQuery(['page' => $currentPage - 1]) ?>">
                                    <i data-lucide="chevron-left" style="width:13px;height:13px;"></i> Préc.
                                </a>
                            <?php else: ?>
                                <span class="pag-btn disabled"><i data-lucide="chevron-left" style="width:13px;height:13px;"></i> Préc.</span>
                            <?php endif; ?>

                            <!-- Page numbers -->
                            <?php
                            $startPage = max(1, $currentPage - 2);
                            $endPage   = min($totalPages, $currentPage + 2);
                            if ($startPage > 1) echo '<span class="pag-btn disabled">…</span>';
                            for ($p = $startPage; $p <= $endPage; $p++):
                            ?>
                                <a class="pag-btn <?= $p == $currentPage ? 'active' : '' ?>"
                                   href="?<?= buildQuery(['page' => $p]) ?>"><?= $p ?></a>
                            <?php endfor;
                            if ($endPage < $totalPages) echo '<span class="pag-btn disabled">…</span>';
                            ?>

                            <!-- Next -->
                            <?php if ($currentPage < $totalPages): ?>
                                <a class="pag-btn" href="?<?= buildQuery(['page' => $currentPage + 1]) ?>">
                                    Suiv. <i data-lucide="chevron-right" style="width:13px;height:13px;"></i>
                                </a>
                            <?php else: ?>
                                <span class="pag-btn disabled">Suiv. <i data-lucide="chevron-right" style="width:13px;height:13px;"></i></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== 'undefined') lucide.createIcons();

    // Sidebar
    const sidebar   = document.getElementById('sidebar');
    const overlay   = document.getElementById('sidebarOverlay');
    const toggle    = document.getElementById('menuToggle');
    const closeBtn  = document.getElementById('sidebarClose');
    const show = () => { sidebar?.classList.add('show'); overlay?.classList.add('show'); };
    const hide = () => { sidebar?.classList.remove('show'); overlay?.classList.remove('show'); };
    toggle?.addEventListener('click', show);
    closeBtn?.addEventListener('click', hide);
    overlay?.addEventListener('click', hide);

    // Update page title in header
    const pageTitle = document.getElementById('pageTitle');
    const pageDesc  = document.getElementById('pageDescription');
    if (pageTitle) pageTitle.textContent = 'Historique des caisses';
    if (pageDesc)  pageDesc.textContent  = 'Toutes les sessions d\'ouverture/fermeture';
});
</script>
</body>
</html>
<?php
} else {
    header("Location: ../logout.php");
    exit();
}
?>