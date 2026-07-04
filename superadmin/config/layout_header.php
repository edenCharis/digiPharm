<?php
// ── Layout header — inclure en début de chaque page ──────────
$current_page = basename($_SERVER['PHP_SELF'], '.php');

$db = sa_db();
$stats = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'active')    AS active,
        SUM(status = 'trial')     AS trial,
        SUM(status = 'suspended') AS suspended
    FROM pharmacies
")->fetch();

// Pending registrations count for badge
$pending_count = 0;
try {
    $pending_count = (int) $db->query("SELECT COUNT(*) FROM pharmacy_registrations WHERE status = 'pending'")->fetchColumn();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>digiPharm — SuperAdmin</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: #F3F4F6;
    color: #1A1A2E;
}

/* ── Sidebar ── */
.sidebar {
    position: fixed; top:0; left:0; bottom:0; width:240px;
    background: #1A1A2E;
    display: flex; flex-direction: column;
    z-index: 100;
}
.sidebar-brand {
    padding: 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    display: flex; align-items: center; gap: 10px;
}
.sb-mark {
    width: 32px; height: 32px; background: #16A34A; border-radius: 8px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.brand-name { font-size: 1rem; font-weight: 700; color: white; }
.brand-sub  { font-size: 0.65rem; color: #6B7280; font-weight: 500;
              text-transform: uppercase; letter-spacing: 0.08em; margin-top: 1px; }
.sidebar-nav { flex: 1; padding: 0.75rem 0; overflow-y: auto; scrollbar-width: none; }
.sidebar-nav::-webkit-scrollbar { display: none; }
.nav-section { padding: 0.5rem 1.25rem; font-size: 0.65rem; color: #4B5563;
               text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600;
               margin-top: 0.5rem; }
.nav-item {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.6rem 1.25rem;
    color: #9CA3AF; font-size: 0.875rem;
    text-decoration: none; transition: all 0.15s;
    border-left: 3px solid transparent;
}
.nav-item:hover { color: white; background: rgba(255,255,255,0.05); }
.nav-item.active { color: white; background: rgba(22,163,74,0.15);
                   border-left-color: #16A34A; }
.nav-item svg { width: 16px; height: 16px; flex-shrink: 0; }
.nav-badge {
    margin-left: auto; background: #16A34A; color: white;
    font-size: 0.65rem; font-weight: 700;
    padding: 0.15rem 0.5rem; border-radius: 999px; min-width: 20px; text-align: center;
}
.nav-badge.orange { background: #F59E0B; }
.sidebar-footer {
    padding: 1rem 1.25rem;
    border-top: 1px solid rgba(255,255,255,0.08);
}
.sa-user { font-size: 0.75rem; color: #6B7280; margin-bottom: 0.5rem; }
.sa-user strong { color: #E5E7EB; display: block; font-size: 0.85rem; }
.btn-logout {
    display: block; width: 100%; padding: 0.5rem;
    background: rgba(239,68,68,0.12); color: #F87171;
    border: 1px solid rgba(239,68,68,0.25); border-radius: 6px;
    font-size: 0.8rem; text-align: center; text-decoration: none;
    transition: all 0.15s;
}
.btn-logout:hover { background: rgba(239,68,68,0.22); color: white; }

/* ── Main ── */
.main { margin-left: 240px; min-height: 100vh; }
.topbar {
    background: white; padding: 1rem 1.5rem;
    border-bottom: 1px solid #E5E7EB;
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 10;
}
.topbar-title { font-size: 1rem; font-weight: 700; color: #111827; }
.topbar-meta  { font-size: 0.75rem; color: #9CA3AF; margin-top: 1px; }
.topbar-stats { display: flex; gap: 1rem; }
.topbar-stat {
    display: flex; align-items: center; gap: 0.4rem;
    font-size: 0.8rem; color: #6B7280;
}
.dot { width: 7px; height: 7px; border-radius: 50%; }
.dot-green  { background: #10B981; }
.dot-yellow { background: #F59E0B; }
.dot-red    { background: #EF4444; }
.content { padding: 1.5rem; }

/* ── KPI cards ── */
.kpi-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 1rem; margin-bottom: 1.5rem; }
.kpi-card {
    background: white; border-radius: 10px; padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    border-left: 4px solid;
}
.kpi-card.teal   { border-color: #16A34A; }
.kpi-card.green  { border-color: #10B981; }
.kpi-card.yellow { border-color: #F59E0B; }
.kpi-card.red    { border-color: #EF4444; }
.kpi-card.blue   { border-color: #3B82F6; }
.kpi-value { font-size: 2rem; font-weight: 700; color: #111827; line-height: 1; }
.kpi-label { font-size: 0.8rem; color: #6B7280; margin-top: 0.4rem; }

/* ── Table card ── */
.table-card { background: white; border-radius: 10px;
              box-shadow: 0 1px 3px rgba(0,0,0,0.06); overflow: hidden; margin-bottom: 1.5rem; }
.table-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #F3F4F6;
    display: flex; align-items: center; justify-content: space-between;
}
.table-title { font-size: 0.95rem; font-weight: 700; color: #111827; }
table { width: 100%; border-collapse: collapse; }
th {
    text-align: left; padding: 0.75rem 1.25rem;
    font-size: 0.72rem; font-weight: 600; color: #6B7280;
    text-transform: uppercase; letter-spacing: 0.05em;
    background: #F9FAFB; border-bottom: 1px solid #E5E7EB;
    white-space: nowrap;
}
td {
    padding: 0.85rem 1.25rem; font-size: 0.875rem;
    border-bottom: 1px solid #F3F4F6; vertical-align: middle;
}
tr:last-child td { border-bottom: none; }
tr:hover td { background: #FAFAFA; }

/* ── Badges ── */
.badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 0.2rem 0.65rem;
    border-radius: 999px; font-size: 0.72rem; font-weight: 600;
}
.badge::before { content: ''; width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }
.badge-active    { background: #D1FAE5; color: #065F46; }
.badge-active::before { background: #10B981; }
.badge-trial     { background: #FEF3C7; color: #92400E; }
.badge-trial::before  { background: #F59E0B; }
.badge-suspended { background: #FEE2E2; color: #991B1B; }
.badge-suspended::before { background: #EF4444; }
.badge-basic     { background: #F3F4F6; color: #374151; }
.badge-pro       { background: #D1FAE5; color: #065F46; }
.badge-enterprise{ background: #EDE9FE; color: #5B21B6; }
.badge-pending   { background: #FEF3C7; color: #92400E; }
.badge-pending::before { background: #F59E0B; }
.badge-approved  { background: #D1FAE5; color: #065F46; }
.badge-approved::before { background: #10B981; }
.badge-rejected  { background: #FEE2E2; color: #991B1B; }
.badge-rejected::before { background: #EF4444; }

/* ── Buttons ── */
.btn-sm {
    padding: 0.35rem 0.75rem; border-radius: 6px;
    font-size: 0.8rem; font-weight: 500; cursor: pointer;
    text-decoration: none; display: inline-flex;
    align-items: center; gap: 0.35rem; transition: all 0.15s;
    border: 1px solid transparent;
}
.btn-primary { background: #16A34A; color: white; border-color: #16A34A; }
.btn-primary:hover { background: #15803D; }
.btn-outline { background: white; color: #374151; border-color: #D1D5DB; }
.btn-outline:hover { background: #F9FAFB; }
.btn-danger  { background: #FEE2E2; color: #991B1B; border-color: #FECACA; }
.btn-danger:hover  { background: #FCA5A5; }
.btn-warning { background: #FEF3C7; color: #92400E; border-color: #FDE68A; }
.btn-warning:hover { background: #FDE68A; }

/* ── Form ── */
.form-card { background: white; border-radius: 10px; padding: 1.5rem;
             box-shadow: 0 1px 3px rgba(0,0,0,0.06); margin-bottom: 1.5rem; }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-group { margin-bottom: 1rem; }
.form-group.full { grid-column: 1 / -1; }
.form-group label { display: block; font-size: 0.82rem; font-weight: 600;
                    color: #374151; margin-bottom: 0.4rem; }
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%; padding: 0.55rem 0.85rem;
    border: 1.5px solid #E5E7EB; border-radius: 8px;
    font-size: 0.875rem; font-family: inherit;
    transition: border-color 0.15s; background: #FAFAFA;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none; border-color: #16A34A; background: white;
    box-shadow: 0 0 0 3px rgba(22,163,74,0.1);
}

/* ── Alert ── */
.alert { padding: 0.85rem 1rem; border-radius: 8px;
         font-size: 0.875rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 8px; }
.alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
.alert-error   { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
.alert-info    { background: #DBEAFE; color: #1E40AF; border: 1px solid #BFDBFE; }
.alert-warning { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }

/* ── Log ── */
.log-entry { display: flex; align-items: flex-start; gap: 0.75rem;
             padding: 0.75rem 1.25rem; border-bottom: 1px solid #F3F4F6; }
.log-entry:last-child { border-bottom: none; }
.log-dot { width: 7px; height: 7px; border-radius: 50%;
           background: #16A34A; margin-top: 5px; flex-shrink: 0; }
.log-time { font-size: 0.72rem; color: #9CA3AF; white-space: nowrap; }
.log-msg  { font-size: 0.85rem; color: #374151; flex: 1; }
.log-pharmacy { font-size: 0.72rem; color: #16A34A; font-weight: 500; }

/* ── Filter tabs ── */
.filter-tabs { display: flex; gap: 0; border-bottom: 1px solid #E5E7EB; margin-bottom: 1.5rem; }
.filter-tab {
    padding: 0.65rem 1.1rem; font-size: 0.85rem; font-weight: 500;
    color: #6B7280; text-decoration: none;
    border-bottom: 2px solid transparent; display: flex; align-items: center; gap: 6px;
    transition: color .15s;
}
.filter-tab:hover { color: #111827; }
.filter-tab.active { color: #15803D; border-bottom-color: #16A34A; }
.filter-tab .count {
    font-size: 0.7rem; font-weight: 600; padding: 1px 7px;
    border-radius: 999px; background: #F3F4F6; color: #6B7280;
}
.filter-tab.active .count { background: #DCFCE7; color: #15803D; }

/* ── Empty state ── */
.empty-state { padding: 60px 20px; text-align: center; color: #9CA3AF; }
.empty-state svg { width: 40px; height: 40px; margin: 0 auto 12px; display: block; }
.empty-state h3 { font-size: 0.95rem; font-weight: 600; color: #6B7280; margin-bottom: 4px; }
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">
        <div class="sb-mark">
            <svg width="14" height="14" viewBox="0 0 20 20" fill="#fff"><path d="M11 2H9v7H2v2h7v7h2v-7h7V9h-7V2z"/></svg>
        </div>
        <div>
            <div class="brand-name">digiPharm</div>
            <div class="brand-sub">SuperAdmin · Digitech</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Principal</div>
        <a href="/superadmin/dashboard.php" class="nav-item <?= $current_page === 'dashboard' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Tableau de bord
        </a>

        <div class="nav-section">Inscriptions</div>
        <a href="/superadmin/registrations.php" class="nav-item <?= $current_page === 'registrations' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Demandes d'accès
            <?php if ($pending_count > 0): ?>
            <span class="nav-badge orange"><?= $pending_count ?></span>
            <?php endif; ?>
        </a>

        <div class="nav-section">Pharmacies</div>
        <a href="/superadmin/pharmacies/list.php" class="nav-item <?= $current_page === 'list' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            Toutes les pharmacies
            <span class="nav-badge"><?= $stats['total'] ?></span>
        </a>
        <a href="/superadmin/pharmacies/create.php" class="nav-item <?= $current_page === 'create' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
            </svg>
            Créer une pharmacie
        </a>

        <div class="nav-section">Comptes</div>
        <a href="/superadmin/pharmacies/users.php" class="nav-item <?= $current_page === 'users' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Comptes admin
        </a>

        <div class="nav-section">Monitoring</div>
        <a href="/superadmin/pharmacies/logs.php" class="nav-item <?= $current_page === 'logs' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            Logs système
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sa-user">
            Connecté en tant que
            <strong><?= htmlspecialchars($_SESSION['sa_user'] ?? 'admin') ?></strong>
        </div>
        <a href="/superadmin/logout.php" class="btn-logout">Déconnexion</a>
    </div>
</div>

<div class="main">
    <div class="topbar">
        <div>
            <div class="topbar-title">
                <?php
                $titles = [
                    'dashboard'     => 'Tableau de bord',
                    'registrations' => 'Demandes d\'accès',
                    'list'          => 'Pharmacies',
                    'create'        => 'Nouvelle pharmacie',
                    'users'         => 'Comptes admin',
                    'logs'          => 'Logs système',
                    'view'          => 'Détail pharmacie',
                    'edit'          => 'Modifier pharmacie',
                ];
                echo $titles[$current_page] ?? 'digiPharm SuperAdmin';
                ?>
            </div>
            <div class="topbar-meta">digiPharm Platform · Digitech</div>
        </div>
        <div class="topbar-stats">
            <div class="topbar-stat"><div class="dot dot-green"></div><span><?= $stats['active'] ?> actives</span></div>
            <div class="topbar-stat"><div class="dot dot-yellow"></div><span><?= $stats['trial'] ?> en essai</span></div>
            <div class="topbar-stat"><div class="dot dot-red"></div><span><?= $stats['suspended'] ?> suspendues</span></div>
        </div>
    </div>
    <div class="content">
