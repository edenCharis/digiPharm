<?php
// ── Layout header — inclure en début de chaque page ──────────
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Stats rapides pour le header
$db = sa_db();
$stats = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'active')    AS active,
        SUM(status = 'trial')     AS trial,
        SUM(status = 'suspended') AS suspended
    FROM pharmacies
")->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DigiPharma AI — Digitech</title>
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
    padding: 1.5rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
.brand-name { font-size: 1.1rem; font-weight: 700; color: white; }
.brand-sub  { font-size: 0.7rem; color: #0D7C66; font-weight: 600;
              text-transform: uppercase; letter-spacing: 0.08em; margin-top: 2px; }
.sidebar-nav { flex: 1; padding: 1rem 0; overflow-y: auto; }
.nav-section { padding: 0.5rem 1.25rem; font-size: 0.65rem; color: #6B7280;
               text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600;
               margin-top: 0.5rem; }
.nav-item {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.65rem 1.25rem;
    color: #9CA3AF; font-size: 0.875rem;
    text-decoration: none; transition: all 0.15s;
    border-left: 3px solid transparent;
}
.nav-item:hover { color: white; background: rgba(255,255,255,0.05); }
.nav-item.active { color: white; background: rgba(13,124,102,0.15);
                   border-left-color: #0D7C66; }
.nav-item svg { width: 18px; height: 18px; flex-shrink: 0; }
.nav-badge {
    margin-left: auto; background: #0D7C66; color: white;
    font-size: 0.65rem; font-weight: 700;
    padding: 0.15rem 0.5rem; border-radius: 999px;
}
.sidebar-footer {
    padding: 1rem 1.25rem;
    border-top: 1px solid rgba(255,255,255,0.08);
}
.sa-user { font-size: 0.8rem; color: #9CA3AF; margin-bottom: 0.5rem; }
.sa-user strong { color: white; display: block; }
.btn-logout {
    display: block; width: 100%; padding: 0.5rem;
    background: rgba(239,68,68,0.15); color: #F87171;
    border: 1px solid rgba(239,68,68,0.3); border-radius: 6px;
    font-size: 0.8rem; text-align: center; text-decoration: none;
    transition: all 0.15s;
}
.btn-logout:hover { background: rgba(239,68,68,0.25); color: white; }

/* ── Main ── */
.main { margin-left: 240px; min-height: 100vh; }
.topbar {
    background: white; padding: 1rem 1.5rem;
    border-bottom: 1px solid #E5E7EB;
    display: flex; align-items: center; justify-content: space-between;
}
.topbar-title { font-size: 1.1rem; font-weight: 700; color: #1A1A2E; }
.topbar-meta  { font-size: 0.8rem; color: #6B7280; }
.topbar-stats { display: flex; gap: 1rem; }
.topbar-stat {
    display: flex; align-items: center; gap: 0.4rem;
    font-size: 0.8rem;
}
.dot { width: 8px; height: 8px; border-radius: 50%; }
.dot-green  { background: #10B981; }
.dot-yellow { background: #F59E0B; }
.dot-red    { background: #EF4444; }
.content { padding: 1.5rem; }

/* ── Cards ── */
.kpi-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 1rem; margin-bottom: 1.5rem; }
.kpi-card {
    background: white; border-radius: 10px; padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    border-left: 4px solid;
}
.kpi-card.teal   { border-color: #0D7C66; }
.kpi-card.green  { border-color: #10B981; }
.kpi-card.yellow { border-color: #F59E0B; }
.kpi-card.red    { border-color: #EF4444; }
.kpi-value { font-size: 2rem; font-weight: 700; color: #1A1A2E; line-height: 1; }
.kpi-label { font-size: 0.8rem; color: #6B7280; margin-top: 0.4rem; }

/* ── Table ── */
.table-card { background: white; border-radius: 10px;
              box-shadow: 0 1px 3px rgba(0,0,0,0.06); overflow: hidden; }
.table-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #F3F4F6;
    display: flex; align-items: center; justify-content: space-between;
}
.table-title { font-size: 0.95rem; font-weight: 700; color: #1A1A2E; }
table { width: 100%; border-collapse: collapse; }
th {
    text-align: left; padding: 0.75rem 1.25rem;
    font-size: 0.75rem; font-weight: 600; color: #6B7280;
    text-transform: uppercase; letter-spacing: 0.05em;
    background: #F9FAFB; border-bottom: 1px solid #E5E7EB;
}
td {
    padding: 0.85rem 1.25rem; font-size: 0.875rem;
    border-bottom: 1px solid #F3F4F6; vertical-align: middle;
}
tr:last-child td { border-bottom: none; }
tr:hover td { background: #F9FAFB; }

/* ── Badges ── */
.badge {
    display: inline-block; padding: 0.2rem 0.6rem;
    border-radius: 999px; font-size: 0.72rem; font-weight: 600;
}
.badge-active   { background: #D1FAE5; color: #065F46; }
.badge-trial    { background: #FEF3C7; color: #92400E; }
.badge-suspended{ background: #FEE2E2; color: #991B1B; }
.badge-starter  { background: #E0E7FF; color: #3730A3; }
.badge-pro      { background: #D1FAE5; color: #065F46; }
.badge-enterprise{ background: #FDF4FF; color: #6B21A8; }

/* ── Buttons ── */
.btn-sm {
    padding: 0.35rem 0.75rem; border-radius: 6px;
    font-size: 0.8rem; font-weight: 500; cursor: pointer;
    text-decoration: none; display: inline-flex;
    align-items: center; gap: 0.35rem; transition: all 0.15s;
    border: 1px solid transparent;
}
.btn-primary { background: #0D7C66; color: white; }
.btn-primary:hover { background: #0a6354; }
.btn-outline { background: white; color: #374151; border-color: #D1D5DB; }
.btn-outline:hover { background: #F9FAFB; }
.btn-danger  { background: #FEE2E2; color: #991B1B; }
.btn-danger:hover  { background: #FCA5A5; }

/* ── Form ── */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 500;
                    color: #374151; margin-bottom: 0.4rem; }
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%; padding: 0.6rem 0.9rem;
    border: 1.5px solid #E5E7EB; border-radius: 8px;
    font-size: 0.875rem; transition: border-color 0.15s;
}
.form-group input:focus,
.form-group select:focus { outline: none; border-color: #0D7C66;
                            box-shadow: 0 0 0 3px rgba(13,124,102,0.1); }
.form-group.full { grid-column: 1 / -1; }

/* ── Alert ── */
.alert { padding: 0.85rem 1rem; border-radius: 8px;
         font-size: 0.875rem; margin-bottom: 1rem; }
.alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
.alert-error   { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }

/* ── Log ── */
.log-entry { display: flex; align-items: flex-start; gap: 0.75rem;
             padding: 0.75rem 1.25rem; border-bottom: 1px solid #F3F4F6; }
.log-entry:last-child { border-bottom: none; }
.log-dot { width: 8px; height: 8px; border-radius: 50%;
           background: #0D7C66; margin-top: 5px; flex-shrink: 0; }
.log-time { font-size: 0.75rem; color: #9CA3AF; white-space: nowrap; }
.log-msg  { font-size: 0.85rem; color: #374151; flex: 1; }
.log-pharmacy { font-size: 0.75rem; color: #0D7C66; font-weight: 500; }
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-name">DigiPharma AI</div>
        <div class="brand-sub">Digitech · SuperAdmin</div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Principal</div>
        <a href="dashboard.php" class="nav-item <?= $current_page === 'dashboard' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Tableau de bord
        </a>

        <div class="nav-section">Pharmacies</div>
        <a href="pharmacies/list.php" class="nav-item <?= $current_page === 'list' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            Toutes les pharmacies
            <span class="nav-badge"><?= $stats['total'] ?></span>
        </a>
        <a href="pharmacies/create.php" class="nav-item <?= $current_page === 'create' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 4v16m8-8H4"/>
            </svg>
            Créer une pharmacie
        </a>

        <div class="nav-section">Comptes</div>
        <a href="pharmacies/users.php" class="nav-item <?= $current_page === 'users' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Comptes admin
        </a>

        <div class="nav-section">Monitoring</div>
        <a href="pharmacies/logs.php" class="nav-item <?= $current_page === 'logs' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            Logs système
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sa-user">
            Connecté en tant que
            <strong><?= htmlspecialchars($_SESSION['sa_user'] ?? 'admin') ?></strong>
        </div>
        <a href="logout.php" class="btn-logout">⏻ Déconnexion</a>
    </div>
</div>

<div class="main">
    <div class="topbar">
        <div>
            <div class="topbar-title">
                <?php
                $titles = [
                    'dashboard' => 'Tableau de bord',
                    'list'      => 'Pharmacies',
                    'create'    => 'Nouvelle pharmacie',
                    'users'     => 'Comptes admin',
                    'logs'      => 'Logs système',
                    'view'      => 'Détail pharmacie',
                    'edit'      => 'Modifier pharmacie',
                ];
                echo $titles[$current_page] ?? 'DigiPharma AI';
                ?>
            </div>
            <div class="topbar-meta">DigiPharma AI Platform · Digitech</div>
        </div>
        <div class="topbar-stats">
            <div class="topbar-stat">
                <div class="dot dot-green"></div>
                <span><?= $stats['active'] ?> actives</span>
            </div>
            <div class="topbar-stat">
                <div class="dot dot-yellow"></div>
                <span><?= $stats['trial'] ?> en trial</span>
            </div>
            <div class="topbar-stat">
                <div class="dot dot-red"></div>
                <span><?= $stats['suspended'] ?> suspendues</span>
            </div>
        </div>
    </div>
    <div class="content">
