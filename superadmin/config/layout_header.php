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
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

:root {
    --green:      #16A34A;
    --green-dark: #15803D;
    --green-dim:  rgba(22,163,74,0.12);
    --green-glow: rgba(22,163,74,0.18);
    --sidebar-bg: #0F1117;
    --sidebar-w:  248px;
    --border:     #E5E7EB;
    --border-lt:  #F3F4F6;
    --text:       #111827;
    --text-2:     #374151;
    --muted:      #6B7280;
    --muted-lt:   #9CA3AF;
    --surface:    #ffffff;
    --bg:         #F4F6F9;
    --radius:     12px;
    --shadow:     0 1px 3px rgba(0,0,0,0.07), 0 1px 2px rgba(0,0,0,0.04);
    --shadow-md:  0 4px 16px rgba(0,0,0,0.08), 0 1px 4px rgba(0,0,0,0.04);
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: var(--bg);
    color: var(--text);
    font-size: 14px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
}

/* ══════════════════════════════════════════
   SIDEBAR
══════════════════════════════════════════ */
.sidebar {
    position: fixed; top:0; left:0; bottom:0; width: var(--sidebar-w);
    background: var(--sidebar-bg);
    display: flex; flex-direction: column;
    z-index: 100;
    transition: transform 0.25s cubic-bezier(.4,0,.2,1);
    border-right: 1px solid rgba(255,255,255,0.04);
}
.sidebar.collapsed { transform: translateX(calc(-1 * var(--sidebar-w))); }

.sidebar-brand {
    padding: 1.1rem 1.25rem 1rem;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    display: flex; align-items: center; gap: 10px;
}
.sb-mark {
    width: 34px; height: 34px;
    background: linear-gradient(135deg, #16A34A 0%, #0d7a32 100%);
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(22,163,74,0.35);
}
.brand-name { font-size: 0.95rem; font-weight: 700; color: #fff; letter-spacing: -0.02em; }
.brand-sub  { font-size: 0.6rem; color: rgba(255,255,255,0.3); font-weight: 500;
              text-transform: uppercase; letter-spacing: 0.1em; margin-top: 1px; }

.sidebar-nav { flex: 1; padding: 0.6rem 0; overflow-y: auto; scrollbar-width: none; }
.sidebar-nav::-webkit-scrollbar { display: none; }

.nav-section {
    padding: 1rem 1.25rem 0.3rem;
    font-size: 0.6rem; color: rgba(255,255,255,0.22);
    text-transform: uppercase; letter-spacing: 0.12em; font-weight: 600;
}
.nav-item {
    display: flex; align-items: center; gap: 0.7rem;
    padding: 0.52rem 1rem 0.52rem 1.15rem;
    margin: 1px 0.6rem;
    color: rgba(255,255,255,0.45); font-size: 0.85rem; font-weight: 500;
    text-decoration: none;
    border-radius: 8px;
    transition: background 0.15s, color 0.15s;
}
.nav-item:hover { color: rgba(255,255,255,0.85); background: rgba(255,255,255,0.06); }
.nav-item.active {
    color: #fff;
    background: linear-gradient(90deg, rgba(22,163,74,0.22) 0%, rgba(22,163,74,0.08) 100%);
    border-left: 2px solid var(--green);
    padding-left: calc(1.15rem - 2px);
}
.nav-item svg { width: 15px; height: 15px; flex-shrink: 0; opacity: 0.7; }
.nav-item.active svg { opacity: 1; color: #4ade80; }

.nav-badge {
    margin-left: auto;
    background: var(--green); color: white;
    font-size: 0.62rem; font-weight: 700;
    padding: 0.12rem 0.45rem; border-radius: 99px; min-width: 18px; text-align: center;
    line-height: 1.4;
}
.nav-badge.orange { background: #F59E0B; color: #fff; }

.sidebar-footer {
    padding: 0.875rem 1.25rem;
    border-top: 1px solid rgba(255,255,255,0.06);
    display: flex; align-items: center; gap: 10px;
}
.sa-avatar {
    width: 32px; height: 32px; border-radius: 8px;
    background: linear-gradient(135deg, #16A34A, #0d7a32);
    display: flex; align-items: center; justify-content: center;
    font-size: 0.75rem; font-weight: 700; color: #fff;
    flex-shrink: 0; letter-spacing: 0.02em;
}
.sa-user-info { flex: 1; min-width: 0; }
.sa-user-name { font-size: 0.8rem; font-weight: 600; color: #E5E7EB;
                white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sa-user-role { font-size: 0.65rem; color: rgba(255,255,255,0.3); font-weight: 400; margin-top: 1px; }
.btn-logout {
    width: 28px; height: 28px; border-radius: 6px;
    background: rgba(239,68,68,0.1); color: #F87171;
    border: 1px solid rgba(239,68,68,0.2);
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; flex-shrink: 0; transition: all 0.15s;
}
.btn-logout:hover { background: rgba(239,68,68,0.22); color: #fff; }
.btn-logout svg { width: 13px; height: 13px; }

/* ══════════════════════════════════════════
   SIDEBAR TOGGLE + TOPBAR
══════════════════════════════════════════ */
.main {
    margin-left: var(--sidebar-w);
    min-height: 100vh;
    transition: margin-left 0.25s cubic-bezier(.4,0,.2,1);
    display: flex; flex-direction: column;
}
.main.expanded { margin-left: 0; }

.topbar {
    background: var(--surface);
    padding: 0 1.5rem;
    height: 56px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 10;
    gap: 1rem;
}
.topbar-left { display: flex; align-items: center; gap: 0.75rem; min-width: 0; }
.topbar-right { display: flex; align-items: center; gap: 1rem; flex-shrink: 0; }

.btn-sidebar-toggle {
    width: 34px; height: 34px; border-radius: 8px;
    background: transparent; border: 1px solid var(--border);
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    color: var(--muted); flex-shrink: 0; transition: all 0.15s;
}
.btn-sidebar-toggle:hover { background: var(--border-lt); color: var(--text); }
.btn-sidebar-toggle svg { width: 16px; height: 16px; }

.topbar-title { font-size: 0.95rem; font-weight: 700; color: var(--text); white-space: nowrap; }
.topbar-meta  { font-size: 0.7rem; color: var(--muted-lt); margin-top: 1px; }

.topbar-stats { display: flex; gap: 0.75rem; }
.topbar-stat {
    display: flex; align-items: center; gap: 5px;
    font-size: 0.77rem; font-weight: 500; color: var(--muted);
    background: var(--border-lt); border-radius: 99px;
    padding: 3px 10px;
}
.dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.dot-green  { background: #10B981; box-shadow: 0 0 0 2px rgba(16,185,129,.2); }
.dot-yellow { background: #F59E0B; box-shadow: 0 0 0 2px rgba(245,158,11,.2); }
.dot-red    { background: #EF4444; box-shadow: 0 0 0 2px rgba(239,68,68,.2); }

.content { padding: 1.5rem; flex: 1; }

/* ══════════════════════════════════════════
   KPI CARDS
══════════════════════════════════════════ */
.kpi-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 1rem; margin-bottom: 1.5rem; }
.kpi-card {
    background: var(--surface); border-radius: var(--radius); padding: 1.25rem 1.25rem 1.1rem;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
    position: relative; overflow: hidden;
}
.kpi-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    border-radius: var(--radius) var(--radius) 0 0;
}
.kpi-card.teal::before   { background: #16A34A; }
.kpi-card.green::before  { background: #10B981; }
.kpi-card.yellow::before { background: #F59E0B; }
.kpi-card.red::before    { background: #EF4444; }
.kpi-card.blue::before   { background: #3B82F6; }
.kpi-value { font-size: 2rem; font-weight: 700; color: var(--text); line-height: 1; letter-spacing: -0.03em; font-variant-numeric: tabular-nums; }
.kpi-label { font-size: 0.78rem; color: var(--muted); margin-top: 0.5rem; font-weight: 500; }

/* ══════════════════════════════════════════
   TABLE CARD
══════════════════════════════════════════ */
.table-card {
    background: var(--surface); border-radius: var(--radius);
    box-shadow: var(--shadow); border: 1px solid var(--border);
    margin-bottom: 1.5rem;
    /* No overflow:hidden — it clips the horizontal scroll inside table-scroll */
}
.table-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border-lt);
    display: flex; align-items: center; justify-content: space-between; gap: 1rem;
    border-radius: var(--radius) var(--radius) 0 0;
}
.table-title { font-size: 0.9rem; font-weight: 700; color: var(--text); }

.table-scroll { overflow-x: auto; border-radius: 0 0 var(--radius) var(--radius); }
.table-scroll::-webkit-scrollbar { height: 4px; }
.table-scroll::-webkit-scrollbar-track { background: var(--border-lt); }
.table-scroll::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

table { width: 100%; border-collapse: collapse; min-width: 600px; }
th {
    text-align: left; padding: 0.7rem 1.25rem;
    font-size: 0.68rem; font-weight: 600; color: var(--muted);
    text-transform: uppercase; letter-spacing: 0.06em;
    background: #FAFBFC; border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
td {
    padding: 0.8rem 1.25rem; font-size: 0.85rem;
    border-bottom: 1px solid var(--border-lt); vertical-align: middle;
    color: var(--text-2);
}
tr:last-child td { border-bottom: none; }
tbody tr { transition: background 0.1s; }
tbody tr:hover td { background: #FAFBFC; }

/* ══════════════════════════════════════════
   BADGES
══════════════════════════════════════════ */
.badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 0.18rem 0.6rem;
    border-radius: 99px; font-size: 0.7rem; font-weight: 600;
    white-space: nowrap;
}
.badge::before { content: ''; width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }
.badge-active    { background: #DCFCE7; color: #166534; }
.badge-active::before { background: #16A34A; }
.badge-trial     { background: #FEF9C3; color: #854D0E; }
.badge-trial::before  { background: #CA8A04; }
.badge-suspended { background: #FEE2E2; color: #991B1B; }
.badge-suspended::before { background: #DC2626; }
.badge-basic     { background: #F3F4F6; color: #374151; }
.badge-basic::before { background: #9CA3AF; }
.badge-pro       { background: #DCFCE7; color: #166534; }
.badge-pro::before { background: #16A34A; }
.badge-starter   { background: #F3F4F6; color: #374151; }
.badge-starter::before { background: #9CA3AF; }
.badge-enterprise{ background: #EDE9FE; color: #4C1D95; }
.badge-enterprise::before { background: #7C3AED; }
.badge-pending   { background: #FEF9C3; color: #854D0E; }
.badge-pending::before { background: #CA8A04; }
.badge-approved  { background: #DCFCE7; color: #166534; }
.badge-approved::before { background: #16A34A; }
.badge-rejected  { background: #FEE2E2; color: #991B1B; }
.badge-rejected::before { background: #DC2626; }

/* ══════════════════════════════════════════
   BUTTONS
══════════════════════════════════════════ */
.btn-sm {
    padding: 0.38rem 0.8rem; border-radius: 7px;
    font-size: 0.8rem; font-weight: 500; cursor: pointer;
    text-decoration: none; display: inline-flex;
    align-items: center; gap: 0.35rem; transition: all 0.15s;
    border: 1px solid transparent; font-family: inherit;
    white-space: nowrap;
}
.btn-primary { background: var(--green); color: #fff; border-color: var(--green); }
.btn-primary:hover { background: var(--green-dark); box-shadow: 0 2px 8px rgba(22,163,74,.3); }
.btn-outline { background: #fff; color: var(--text-2); border-color: var(--border); }
.btn-outline:hover { background: var(--border-lt); border-color: #D1D5DB; }
.btn-danger  { background: #FEF2F2; color: #B91C1C; border-color: #FECACA; }
.btn-danger:hover  { background: #FEE2E2; }
.btn-warning { background: #FFFBEB; color: #92400E; border-color: #FDE68A; }
.btn-warning:hover { background: #FEF3C7; }

/* ══════════════════════════════════════════
   FORM
══════════════════════════════════════════ */
.form-card {
    background: var(--surface); border-radius: var(--radius); padding: 1.5rem;
    box-shadow: var(--shadow); border: 1px solid var(--border); margin-bottom: 1.5rem;
}
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-group { margin-bottom: 0.9rem; }
.form-group.full { grid-column: 1 / -1; }
.form-group label {
    display: block; font-size: 0.78rem; font-weight: 600;
    color: var(--text-2); margin-bottom: 0.35rem; letter-spacing: 0.01em;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%; padding: 0.52rem 0.85rem;
    border: 1.5px solid var(--border); border-radius: 8px;
    font-size: 0.875rem; font-family: inherit; color: var(--text);
    transition: border-color 0.15s, box-shadow 0.15s; background: #FAFAFA;
}
.form-group input:hover,
.form-group select:hover { border-color: #D1D5DB; }
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none; border-color: var(--green); background: #fff;
    box-shadow: 0 0 0 3px rgba(22,163,74,0.1);
}
.form-group input::placeholder { color: #C4C9D4; }

/* ══════════════════════════════════════════
   ALERTS
══════════════════════════════════════════ */
.alert {
    padding: 0.8rem 1rem; border-radius: 9px;
    font-size: 0.85rem; margin-bottom: 1.25rem;
    display: flex; align-items: center; gap: 9px;
    border: 1px solid;
}
.alert-success { background: #F0FDF4; color: #166534; border-color: #BBF7D0; }
.alert-error   { background: #FFF1F2; color: #9F1239; border-color: #FECDD3; }
.alert-info    { background: #EFF6FF; color: #1E40AF; border-color: #BFDBFE; }
.alert-warning { background: #FFFBEB; color: #92400E; border-color: #FDE68A; }

/* ══════════════════════════════════════════
   LOG ENTRIES
══════════════════════════════════════════ */
.log-entry { display: flex; align-items: flex-start; gap: 0.75rem;
             padding: 0.75rem 1.25rem; border-bottom: 1px solid var(--border-lt); }
.log-entry:last-child { border-bottom: none; }
.log-dot { width: 7px; height: 7px; border-radius: 50%;
           background: var(--green); margin-top: 5px; flex-shrink: 0; }
.log-time { font-size: 0.7rem; color: var(--muted-lt); white-space: nowrap; }
.log-msg  { font-size: 0.84rem; color: var(--text-2); flex: 1; }
.log-pharmacy { font-size: 0.7rem; color: var(--green); font-weight: 500; }

/* ══════════════════════════════════════════
   FILTER TABS
══════════════════════════════════════════ */
.filter-tabs { display: flex; border-bottom: 1px solid var(--border); }
.filter-tab {
    padding: 0.65rem 1.1rem; font-size: 0.83rem; font-weight: 500;
    color: var(--muted); text-decoration: none;
    border-bottom: 2px solid transparent; margin-bottom: -1px;
    display: flex; align-items: center; gap: 6px;
    transition: color .15s;
}
.filter-tab:hover { color: var(--text); }
.filter-tab.active { color: var(--green-dark); border-bottom-color: var(--green); }
.filter-tab .count {
    font-size: 0.68rem; font-weight: 600; padding: 1px 7px;
    border-radius: 99px; background: var(--border-lt); color: var(--muted);
}
.filter-tab.active .count { background: #DCFCE7; color: #15803D; }

/* ══════════════════════════════════════════
   EMPTY STATE
══════════════════════════════════════════ */
.empty-state { padding: 60px 20px; text-align: center; color: var(--muted-lt); }
.empty-state svg { width: 40px; height: 40px; margin: 0 auto 12px; display: block; opacity: .5; }
.empty-state h3 { font-size: 0.92rem; font-weight: 600; color: var(--muted); }
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
        <a href="/superadmin/admins.php" class="nav-item <?= $current_page === 'admins' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            SuperAdmins
        </a>

        <div class="nav-section">digiMind AI</div>
        <a href="/superadmin/digimind/users.php" class="nav-item <?= $current_page === 'dm_users' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
            Utilisateurs
        </a>

        <div class="nav-section">Configuration</div>
        <a href="/superadmin/plans.php" class="nav-item <?= $current_page === 'plans' ? 'active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg>
            Forfaits & Prix
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
        <div class="sa-avatar"><?= strtoupper(substr($_SESSION['sa_display_name'] ?? $_SESSION['sa_user'] ?? 'A', 0, 2)) ?></div>
        <div class="sa-user-info">
            <div class="sa-user-name"><?= htmlspecialchars($_SESSION['sa_display_name'] ?? $_SESSION['sa_user'] ?? 'Admin') ?></div>
            <div class="sa-user-role">SuperAdmin</div>
        </div>
        <a href="/superadmin/logout.php" class="btn-logout" title="Déconnexion">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
        </a>
    </div>
</div>

<div class="main">
    <div class="topbar">
        <div style="display:flex;align-items:center">
        <button class="btn-sidebar-toggle" id="sidebarToggle" title="Réduire/afficher le menu" onclick="toggleSidebar()">
            <svg id="toggleIconOpen" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
            <svg id="toggleIconClose" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>
        <div>
            <div class="topbar-title">
                <?php
                $titles = [
                    'dashboard'     => 'Tableau de bord',
                    'registrations' => 'Demandes d\'accès',
                    'list'          => 'Pharmacies',
                    'create'        => 'Nouvelle pharmacie',
                    'users'         => 'Comptes admin',
                    'admins'        => 'SuperAdmins',
                    'plans'         => 'Forfaits & Prix',
                    'logs'          => 'Logs système',
                    'view'          => 'Détail pharmacie',
                    'edit'          => 'Modifier pharmacie',
                ];
                echo $titles[$current_page] ?? 'digiPharm SuperAdmin';
                ?>
            </div>
            <div class="topbar-meta">digiPharm Platform · Digitech</div>
        </div>
        </div>
        <div class="topbar-stats">
            <div class="topbar-stat"><div class="dot dot-green"></div><span><?= $stats['active'] ?> actives</span></div>
            <div class="topbar-stat"><div class="dot dot-yellow"></div><span><?= $stats['trial'] ?> en essai</span></div>
            <div class="topbar-stat"><div class="dot dot-red"></div><span><?= $stats['suspended'] ?> suspendues</span></div>
        </div>
    </div>
    <div class="content">
<script>
(function(){
    var SB_KEY = 'sa_sidebar_collapsed';
    var sidebar = document.querySelector('.sidebar');
    var main    = document.querySelector('.main');
    if (localStorage.getItem(SB_KEY) === '1') {
        sidebar.classList.add('collapsed');
        main.classList.add('expanded');
    }
    window.toggleSidebar = function() {
        var isCollapsed = sidebar.classList.toggle('collapsed');
        main.classList.toggle('expanded', isCollapsed);
        localStorage.setItem(SB_KEY, isCollapsed ? '1' : '0');
    };
})();
</script>
