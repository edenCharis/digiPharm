<?php
require_once __DIR__ . '/config/auth.php';
ai_check_auth();
$user     = ai_user();
$initials = strtoupper(substr($user['display_name'], 0, 1));
$jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$mois  = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
$today = $jours[(int)date('w')] . ' ' . date('j') . ' ' . $mois[(int)date('n')] . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>digiMind — Briefing</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --green:#1a7f4b;--green-dk:#155e38;--green-lt:#e8f5ee;
  --amber:#d97706;--amber-dk:#92400e;--amber-lt:#fef3c7;
  --red:#dc2626;--red-dk:#7f1d1d;--red-lt:#fee2e2;
  --blue:#2563eb;--blue-dk:#1e3a8a;--blue-lt:#dbeafe;
  --purple:#7c3aed;--purple-lt:#ede9fe;
  --border:#dadce0;--border-lt:#f0f0f0;
  --text:#111827;--text-2:#4b5563;--text-3:#9ca3af;
  --surface:#fff;--surface-alt:#f8f9fa;--bg:#f3f4f6;
  --sidebar-w:240px;--header-h:56px;--r:10px;
}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;}

/* Sidebar */
.sidebar{width:var(--sidebar-w);min-height:100vh;background:var(--surface);border-right:1px solid var(--border);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100;}
.sidebar-logo{padding:18px 20px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--border-lt);}
.logo-icon{width:32px;height:32px;background:var(--green);border-radius:7px;display:grid;place-items:center;}
.logo-icon svg{width:18px;height:18px;stroke:#fff;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;}
.logo-text{font-size:15px;font-weight:700;letter-spacing:-.3px;}
.logo-text span{color:var(--green);}
.sidebar-pharmacy{padding:12px 20px;font-size:11px;font-weight:600;color:var(--text-3);text-transform:uppercase;letter-spacing:.6px;border-bottom:1px solid var(--border-lt);}
nav{flex:1;padding:8px 0;}
.nav-s{padding:16px 20px 4px;font-size:10px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.6px;}
.nav-link{display:flex;align-items:center;gap:10px;padding:8px 14px;margin:1px 8px;border-radius:8px;font-size:13.5px;color:var(--text-2);text-decoration:none;transition:background .12s,color .12s;}
.nav-link svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;}
.nav-link:hover{background:var(--surface-alt);color:var(--text);}
.nav-link.active{background:var(--green-lt);color:var(--green-dk);font-weight:600;}
.sidebar-footer{padding:14px 16px;border-top:1px solid var(--border-lt);display:flex;align-items:center;gap:10px;}
.avatar{width:32px;height:32px;background:var(--green);border-radius:50%;display:grid;place-items:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;}
.av-name{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.av-role{font-size:11px;color:var(--text-3);}
.logout-btn{color:var(--text-3);text-decoration:none;font-size:11px;margin-left:auto;}
.logout-btn:hover{color:var(--red);}

/* Main */
.main{margin-left:var(--sidebar-w);flex:1;min-height:100vh;display:flex;flex-direction:column;}
.topbar{height:var(--header-h);background:var(--surface);border-bottom:1px solid var(--border);padding:0 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50;}
.topbar-left{display:flex;flex-direction:column;gap:2px;}
.topbar-title{font-size:16px;font-weight:700;}
.topbar-meta{font-size:12px;color:var(--text-3);display:flex;align-items:center;gap:5px;}
.status-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--text-3);animation:pulse 2s infinite;}
.status-dot.online{background:var(--green);}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
.refresh-btn{padding:6px 12px;border:1px solid var(--border);border-radius:8px;background:var(--surface);cursor:pointer;color:var(--text-2);font-size:13px;display:flex;align-items:center;gap:5px;transition:background .12s;}
.refresh-btn:hover{background:var(--surface-alt);}
.refresh-btn svg{width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}

.content{padding:20px 28px 32px;flex:1;display:flex;flex-direction:column;gap:16px;}

/* ── Greeting ── */
.greeting-wrap{display:grid;grid-template-columns:1fr 260px;gap:16px;align-items:start;}
.greeting{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:20px 22px;}
.g-head{display:flex;align-items:center;gap:12px;margin-bottom:12px;}
.g-brain{font-size:28px;line-height:1;}
.g-hello{font-size:17px;font-weight:700;}
.g-hello span{color:var(--green);}
.g-body{font-size:13.5px;color:var(--text-2);line-height:1.65;margin-bottom:16px;}
.g-body strong{color:var(--text);}
.g-stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.g-stat{background:var(--surface-alt);border:1px solid var(--border-lt);border-radius:8px;padding:10px 14px;}
.g-stat-val{font-size:18px;font-weight:700;font-variant-numeric:tabular-nums;line-height:1;}
.g-stat-val.red{color:var(--red);}
.g-stat-val.green{color:var(--green);}
.g-stat-val.amber{color:var(--amber);}
.g-stat-lbl{font-size:11px;color:var(--text-3);margin-top:3px;}

/* ── Health Score ── */
.health-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:18px 20px;display:flex;flex-direction:column;gap:14px;}
.hs-top{display:flex;align-items:center;justify-content:space-between;}
.hs-label{font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.6px;}
.hs-score-wrap{display:flex;align-items:baseline;gap:5px;}
.hs-score{font-size:36px;font-weight:800;font-variant-numeric:tabular-nums;line-height:1;}
.hs-denom{font-size:14px;color:var(--text-3);}
.hs-tag{display:inline-block;padding:2px 9px;border-radius:99px;font-size:11px;font-weight:700;}
.hs-tag.excellent{background:var(--green-lt);color:var(--green-dk);}
.hs-tag.bon{background:var(--blue-lt);color:var(--blue-dk);}
.hs-tag.moyen{background:var(--amber-lt);color:var(--amber-dk);}
.hs-tag.faible{background:var(--red-lt);color:var(--red-dk);}
.hs-dims{display:flex;flex-direction:column;gap:7px;}
.hs-dim{display:flex;flex-direction:column;gap:3px;}
.hs-dim-head{display:flex;justify-content:space-between;align-items:center;}
.hs-dim-name{font-size:11.5px;color:var(--text-2);}
.hs-dim-val{font-size:11px;font-weight:600;color:var(--text-3);font-variant-numeric:tabular-nums;}
.hs-bar-wrap{height:4px;background:var(--border-lt);border-radius:2px;overflow:hidden;}
.hs-bar{height:100%;border-radius:2px;background:var(--green);transition:width .6s ease;}

/* ── Tabs ── */
.tab-wrap{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;display:flex;flex-direction:column;}
.tab-bar{display:flex;border-bottom:1px solid var(--border);overflow-x:auto;scrollbar-width:none;}
.tab-bar::-webkit-scrollbar{display:none;}
.tab-btn{display:flex;align-items:center;gap:7px;padding:13px 16px;font-size:12.5px;font-weight:500;color:var(--text-3);border:none;background:none;cursor:pointer;border-bottom:2px solid transparent;white-space:nowrap;transition:color .12s,border-color .12s;flex-shrink:0;}
.tab-btn:hover{color:var(--text-2);background:var(--surface-alt);}
.tab-btn.active{font-weight:600;color:var(--text);}
.tab-btn.active.t-risks{border-bottom-color:var(--red);color:var(--red-dk);}
.tab-btn.active.t-opp{border-bottom-color:var(--green);color:var(--green-dk);}
.tab-btn.active.t-actions{border-bottom-color:var(--amber);color:var(--amber-dk);}
.tab-btn.active.t-fcast{border-bottom-color:var(--blue);color:var(--blue-dk);}
.tab-btn.active.t-insight{border-bottom-color:var(--purple);color:var(--purple);}
.tab-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;}
.tab-badge{min-width:18px;height:18px;padding:0 5px;border-radius:99px;font-size:10px;font-weight:700;background:var(--border-lt);color:var(--text-3);display:inline-flex;align-items:center;justify-content:center;}
.tab-btn.active .tab-badge{background:currentColor;color:#fff;filter:brightness(1.15);}
.tab-dot{width:6px;height:6px;border-radius:50%;}
.tab-dot.critical{background:var(--red);}
.tab-dot.warning{background:var(--amber);}

/* Tab panels */
.tab-panels{flex:1;}
.tab-panel{display:none;padding:20px 22px;}
.tab-panel.active{display:block;}
.section-intro{display:flex;align-items:flex-start;gap:10px;padding:11px 14px;background:var(--surface-alt);border:1px solid var(--border-lt);border-radius:8px;margin-bottom:16px;font-size:13px;color:var(--text-2);line-height:1.55;}
.section-intro svg{width:14px;height:14px;stroke:var(--text-3);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;margin-top:2px;}

/* ── Decision cards ── */
.brief-cards{display:flex;flex-direction:column;gap:14px;}
.brief-card{background:var(--surface);border:1px solid var(--border);border-left:4px solid var(--border);border-radius:var(--r);overflow:hidden;}
.brief-card.sev-critical{border-left-color:var(--red);}
.brief-card.sev-warning{border-left-color:var(--amber);}
.brief-card.sev-info{border-left-color:var(--blue);}
.brief-card.sev-ok{border-left-color:var(--green);}

.card-head{display:flex;align-items:center;gap:10px;padding:14px 18px 0;}
.sev-badge{padding:2px 9px;border-radius:99px;font-size:10px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;flex-shrink:0;}
.sev-badge.sev-critical{background:var(--red-lt);color:var(--red-dk);}
.sev-badge.sev-warning{background:var(--amber-lt);color:var(--amber-dk);}
.sev-badge.sev-info{background:var(--blue-lt);color:var(--blue-dk);}
.sev-badge.sev-ok{background:var(--green-lt);color:var(--green-dk);}
.card-conf{font-size:11px;color:var(--text-3);margin-left:auto;}

.card-headline{padding:10px 18px 0;font-size:15px;font-weight:700;line-height:1.3;}

.card-body{padding:14px 18px;display:flex;flex-direction:column;gap:12px;}
.field-label{font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;color:var(--text-3);margin-bottom:4px;}
.field-text{font-size:13px;color:var(--text-2);line-height:1.6;}

.impact-box{background:var(--surface-alt);border:1px solid var(--border-lt);border-radius:7px;padding:10px 14px;display:flex;align-items:flex-start;gap:8px;}
.impact-box svg{width:14px;height:14px;stroke:var(--amber);fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;margin-top:2px;}

.result-box{background:var(--green-lt);border:1px solid #c3e6d4;border-radius:7px;padding:10px 14px;display:flex;align-items:flex-start;gap:8px;}
.result-box svg{width:14px;height:14px;stroke:var(--green-dk);fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;margin-top:2px;}
.result-box .field-text{color:var(--green-dk);}

.rec-text{font-size:13px;color:var(--text-2);line-height:1.6;padding-left:16px;position:relative;}
.rec-text::before{content:'→';position:absolute;left:0;color:var(--text-3);}

.card-foot{padding:0 18px 14px;display:flex;align-items:center;gap:12px;}
.cbar-wrap{flex:1;height:4px;background:var(--border-lt);border-radius:2px;overflow:hidden;}
.cbar{height:100%;border-radius:2px;}
.cbar.sev-critical{background:var(--red);}
.cbar.sev-warning{background:var(--amber);}
.cbar.sev-info{background:var(--blue);}
.cbar.sev-ok{background:var(--green);}
.cbar-pct{font-size:11px;color:var(--text-3);white-space:nowrap;}
.card-action-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 13px;border:1px solid var(--border);border-radius:7px;font-size:12px;font-weight:600;color:var(--text-2);text-decoration:none;background:var(--surface);white-space:nowrap;transition:all .12s;flex-shrink:0;}
.card-action-btn:hover{background:var(--surface-alt);border-color:var(--text-3);color:var(--text);}
.card-action-btn svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;}

/* ── Timeline ── */
.timeline-wrap{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:16px 20px;}
.tl-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
.tl-title{font-size:13.5px;font-weight:700;}
.tl-sub{font-size:12px;color:var(--text-3);}
.tl-list{display:flex;flex-direction:column;gap:0;}
.tl-row{display:flex;align-items:center;gap:14px;padding:9px 0;border-bottom:1px solid var(--border-lt);}
.tl-row:last-child{border-bottom:none;}
.tl-date{font-size:11.5px;font-weight:600;color:var(--text-3);white-space:nowrap;min-width:80px;font-variant-numeric:tabular-nums;}
.tl-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.tl-dot.critical{background:var(--red);}
.tl-dot.warning{background:var(--amber);}
.tl-dot.ok{background:var(--green);}
.tl-dot.milestone{background:var(--blue);}
.tl-label{font-size:12.5px;color:var(--text-2);}
.tl-type{font-size:10.5px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;margin-left:auto;white-space:nowrap;padding:1px 7px;border-radius:99px;}
.tl-type.stockout{background:var(--red-lt);color:var(--red-dk);}
.tl-type.expiry{background:var(--amber-lt);color:var(--amber-dk);}
.tl-type.milestone{background:var(--blue-lt);color:var(--blue-dk);}
.tl-type.ok{background:var(--green-lt);color:var(--green-dk);}

/* Skeleton */
.sk{background:linear-gradient(90deg,#f0f0f0 25%,#e8e8e8 50%,#f0f0f0 75%);background-size:200% 100%;animation:shimmer 1.4s infinite;border-radius:6px;display:block;}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
.err{background:var(--red-lt);border:1px solid #fca5a5;border-radius:var(--r);padding:14px 18px;color:var(--red-dk);font-size:13px;}

@media(max-width:1100px){.greeting-wrap{grid-template-columns:1fr;}}

/* Mobile */
.sidebar{transition:transform .25s ease;}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99;}
.sidebar-overlay.open{display:block;}
.hamburger{display:none;background:none;border:none;cursor:pointer;padding:4px;color:var(--text-2);}
.hamburger svg{width:22px;height:22px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
@media(max-width:768px){
  .sidebar{transform:translateX(-100%);}
  .sidebar.open{transform:translateX(0);}
  .main{margin-left:0!important;}
  .hamburger{display:flex;align-items:center;}
  .content{padding:14px 14px 28px!important;}
  .topbar{padding:0 14px!important;}
  .g-stats{grid-template-columns:1fr 1fr;}
  .greeting-wrap{grid-template-columns:1fr;}
  .tab-btn{padding:10px 10px;font-size:11.5px;}
  .refresh-btn span{display:none;}
}
@media(max-width:420px){
  .g-stats{grid-template-columns:1fr;}
  .tab-btn svg{display:none;}
  .card-foot{flex-wrap:wrap;}
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
    <div class="logo-text">digiMind</div>
  </div>
  <div class="sidebar-pharmacy"><?= htmlspecialchars($user['pharmacy_name']) ?></div>
  <nav>
    <div class="nav-s">Analyse</div>
    <a href="/analytics/" class="nav-link active">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Vue d'ensemble
    </a>
    <a href="/analytics/trends.php" class="nav-link">
      <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>Tendances
    </a>
    <a href="/analytics/inventory.php" class="nav-link">
      <svg viewBox="0 0 24 24"><path d="M21 16V8l-9-5-9 5v8l9 5 9-5z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>Inventaire
    </a>
    <a href="/analytics/alerts.php" class="nav-link">
      <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>Alertes
    </a>
    <div class="nav-s" style="margin-top:8px">Données</div>
    <a href="/analytics/sync.php" class="nav-link">
      <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>Synchronisation
    </a>
    <a href="/analytics/settings.php" class="nav-link">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Paramètres
    </a>
    <div class="nav-s" style="margin-top:8px">Compte</div>
    <a href="/analytics/account.php" class="nav-link">
      <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Mon compte
    </a>
  </nav>
  <div class="sidebar-footer">
    <div class="avatar"><?= $initials ?></div>
    <div>
      <div class="av-name"><?= htmlspecialchars($user['display_name']) ?></div>
      <div class="av-role"><?= $user['role']==='admin'?'Administrateur':'Lecteur' ?></div>
    </div>
    <a href="/analytics/logout.php" class="logout-btn" title="Déconnexion">✕</a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div class="topbar-left" style="flex-direction:row;align-items:center;gap:10px;">
      <button class="hamburger" onclick="openSidebar()" aria-label="Menu">
        <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div>
        <div class="topbar-title">Briefing quotidien</div>
        <div class="topbar-meta">
          <span class="status-dot" id="aiDot"></span>
          <span id="aiStatus">Analyse en cours…</span>
        </div>
      </div>
    </div>
    <button class="refresh-btn" onclick="loadBrief()">
      <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3"/></svg>
      Actualiser
    </button>
  </div>

  <div class="content">

    <!-- Greeting + Health Score -->
    <div class="greeting-wrap">
      <div class="greeting">
        <div class="g-head">
          <div class="g-brain">🧠</div>
          <div>
            <div class="g-hello">Bonjour, <span><?= htmlspecialchars($user['pharmacy_name']) ?></span></div>
            <div style="font-size:12px;color:var(--text-3)"><?= $today ?> · <span id="genTime">—</span></div>
          </div>
        </div>
        <div class="g-body" id="gBody">
          <span class="sk" style="width:90%;height:14px;display:block;margin-bottom:6px"></span>
          <span class="sk" style="width:70%;height:13px;display:block;margin-bottom:6px"></span>
          <span class="sk" style="width:80%;height:13px;display:block"></span>
        </div>
        <div class="g-stats" id="gStats" style="display:none">
          <div class="g-stat">
            <div class="g-stat-val red" id="gsRisk">—</div>
            <div class="g-stat-lbl">⚠️ CA à risque aujourd'hui</div>
          </div>
          <div class="g-stat">
            <div class="g-stat-val green" id="gsOpp">—</div>
            <div class="g-stat-lbl">💰 CA récupérable</div>
          </div>
          <div class="g-stat">
            <div class="g-stat-val amber" id="gsProd">—</div>
            <div class="g-stat-lbl">📦 Produits à traiter</div>
          </div>
          <div class="g-stat">
            <div class="g-stat-val" id="gsProb" style="color:var(--blue)">—</div>
            <div class="g-stat-lbl">📈 Probabilité objectif mensuel</div>
          </div>
        </div>
      </div>

      <div class="health-card">
        <div class="hs-top">
          <div class="hs-label">Score de santé IA</div>
          <span class="hs-tag" id="hsTag" style="display:none"></span>
        </div>
        <div style="display:flex;align-items:baseline;gap:8px">
          <div class="hs-score" id="hsScore"><span class="sk" style="width:60px;height:36px;display:inline-block"></span></div>
          <div class="hs-denom">/100</div>
        </div>
        <div class="hs-dims" id="hsDims">
          <?php for($i=0;$i<5;$i++): ?>
          <div class="hs-dim">
            <div class="hs-dim-head">
              <span class="sk" style="width:120px;height:11px"></span>
              <span class="sk" style="width:28px;height:11px"></span>
            </div>
            <div class="hs-bar-wrap"><div class="hs-bar" style="width:0%"></div></div>
          </div>
          <?php endfor; ?>
        </div>
      </div>
    </div>

    <!-- Decision tabs -->
    <div class="tab-wrap">
      <div class="tab-bar" id="tabBar">
        <div style="padding:13px 18px;display:flex;gap:14px">
          <?php for($i=0;$i<5;$i++): ?><span class="sk" style="width:90px;height:16px;border-radius:4px"></span><?php endfor; ?>
        </div>
      </div>
      <div class="tab-panels" id="tabPanels">
        <div class="tab-panel active" style="padding:20px 22px">
          <?php for($i=0;$i<2;$i++): ?>
          <div class="brief-card sev-ok" style="margin-bottom:14px">
            <div class="card-head"><span class="sk" style="width:70px;height:18px;border-radius:99px"></span></div>
            <div style="padding:10px 18px 0"><span class="sk" style="width:75%;height:20px"></span></div>
            <div class="card-body" style="gap:10px">
              <span class="sk" style="width:40px;height:10px"></span>
              <span class="sk" style="width:100%;height:36px"></span>
              <span class="sk" style="width:100%;height:46px;border-radius:7px"></span>
              <span class="sk" style="width:100%;height:46px;border-radius:7px"></span>
            </div>
          </div>
          <?php endfor; ?>
        </div>
      </div>
    </div>

    <!-- Timeline -->
    <div class="timeline-wrap" id="timelineWrap" style="display:none">
      <div class="tl-header">
        <div class="tl-title">Agenda prévisionnel</div>
        <div class="tl-sub">Événements calculés par l'IA</div>
      </div>
      <div class="tl-list" id="tlList"></div>
    </div>

  </div>
</div>

<script>
const TABS = [
  {id:'risks',         label:'Ce qui menace',  cls:'t-risks',   icon:'<svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'},
  {id:'opportunities', label:'Argent sur la table',cls:'t-opp',  icon:'<svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>'},
  {id:'actions',       label:'Actions du jour', cls:'t-actions', icon:'<svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>'},
  {id:'forecasts',     label:'Ce qui va se passer',cls:'t-fcast',icon:'<svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>'},
  {id:'insights',      label:'Ce que j\'ai trouvé',cls:'t-insight',icon:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'},
];
const SEV={critical:'CRITIQUE',warning:'ATTENTION',info:'INFO',ok:'INFO'};
const ARROW='<svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
const ZAP='<svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';
const CHECK='<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>';
const INFO_I='<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12.01" y2="8"/><line x1="12" y1="12" x2="12" y2="16"/></svg>';

let briefData=null, activeTab='risks';

function fmt(n){
  if(!n&&n!==0)return'—';
  n=parseFloat(n);
  if(n>=1e6)return(n/1e6).toFixed(1)+'M XAF';
  if(n>=1e3)return Math.round(n/1e3)+'k XAF';
  return Math.round(n).toLocaleString('fr')+' XAF';
}
function fmtN(n){
  if(!n&&n!==0)return'—';
  return parseFloat(n).toLocaleString('fr');
}

function sectionIntro(sec){
  const c=sec.cards||[];
  const crit=c.filter(x=>x.severity==='critical').length;
  const warn=c.filter(x=>x.severity==='warning').length;
  if(sec.id==='risks'){
    if(crit>0)return `${crit} situation${crit>1?'s':''} critique${crit>1?'s':''} identifiée${crit>1?'s':''} ce matin — action immédiate recommandée avant d'ouvrir.`;
    if(warn>0)return `${warn} point${warn>1?'s':''} de vigilance. Pas d'urgence immédiate, mais à traiter dans la journée.`;
    return 'Situation opérationnelle normale. Aucun risque critique détecté. Concentrez-vous sur la croissance.';
  }
  if(sec.id==='opportunities')return `${c.length} opportunité${c.length>1?'s':''} de croissance identifiée${c.length>1?'s':''} dans vos données des 30 derniers jours.`;
  if(sec.id==='actions'){
    const u=c.filter(x=>['critical','warning'].includes(x.severity)).length;
    return u>0?`${u} action${u>1?'s':''} urgente${u>1?'s':''} à mener aujourd'hui. Les ${c.length-u} autre${c.length-u>1?'s':''} peuvent attendre cette semaine.`:`${c.length} recommandation${c.length>1?'s':''} pour optimiser vos opérations aujourd'hui.`;
  }
  if(sec.id==='forecasts')return 'Prévisions calculées sur votre historique réel. Niveau de confiance indicatif — mis à jour à chaque synchronisation.';
  if(sec.id==='insights')return `${c.length} découverte${c.length>1?'s':''} que vous n'auriez probablement pas trouvée${c.length>1?'s':''} seul${c.length>1?'s':''}.`;
  return '';
}

function renderCard(card){
  const sev=card.severity==='ok'?'ok':card.severity;
  const action=card.action_label&&card.action_target
    ?`<a href="${card.action_target}" class="card-action-btn">${card.action_label} ${ARROW}</a>`:'';
  const result=card.expected_result
    ?`<div class="result-box">${CHECK}<div><div class="field-label" style="margin-bottom:3px;color:var(--green-dk)">Résultat attendu</div><div class="field-text">${card.expected_result}</div></div></div>`:'';
  return `
  <div class="brief-card sev-${sev}">
    <div class="card-head">
      <span class="sev-badge sev-${sev}">${SEV[card.severity]||'INFO'}</span>
      <span class="card-conf">Confiance : ${card.confidence}%</span>
    </div>
    <div class="card-headline">${card.headline}</div>
    <div class="card-body">
      <div>
        <div class="field-label">Pourquoi l'IA a détecté ça</div>
        <div class="field-text">${card.explanation}</div>
      </div>
      <div class="impact-box">
        ${ZAP}
        <div>
          <div class="field-label" style="margin-bottom:3px">Impact business</div>
          <div class="field-text">${card.impact}</div>
        </div>
      </div>
      <div>
        <div class="field-label">Recommandation</div>
        <div class="rec-text">${card.recommendation}</div>
      </div>
      ${result}
    </div>
    <div class="card-foot">
      <div class="cbar-wrap"><div class="cbar sev-${sev}" style="width:${card.confidence}%"></div></div>
      <span class="cbar-pct">${card.confidence}%</span>
      ${action}
    </div>
  </div>`;
}

function renderPanel(sec){
  const meta=TABS.find(t=>t.id===sec.id)||{};
  return `
  <div class="tab-panel${sec.id===activeTab?' active':''}" id="panel-${sec.id}">
    <div class="section-intro">${INFO_I} ${sectionIntro(sec)}</div>
    <div class="brief-cards">${(sec.cards||[]).map(renderCard).join('')}</div>
  </div>`;
}

function buildTabBar(sections){
  document.getElementById('tabBar').innerHTML=sections.map(sec=>{
    const t=TABS.find(x=>x.id===sec.id)||{cls:'',label:sec.title,icon:''};
    const cards=sec.cards||[];
    const crit=cards.some(c=>c.severity==='critical');
    const warn=!crit&&cards.some(c=>c.severity==='warning');
    const dot=crit?'<span class="tab-dot critical"></span>':warn?'<span class="tab-dot warning"></span>':'';
    return `<button class="tab-btn ${t.cls}${sec.id===activeTab?' active':''}" onclick="switchTab('${sec.id}')">
      ${t.icon}${t.label}<span class="tab-badge">${cards.length}</span>${dot}
    </button>`;
  }).join('');
}

function switchTab(id){
  if(!briefData)return;
  activeTab=id;
  document.querySelectorAll('.tab-btn').forEach(b=>{
    const match=b.getAttribute('onclick')===`switchTab('${id}')`;
    b.classList.toggle('active',match);
    const tabMeta=TABS.find(t=>t.id===id)||{};
    if(match){b.className='tab-btn '+tabMeta.cls+' active';}
  });
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
  const panel=document.getElementById(`panel-${id}`);
  if(panel)panel.classList.add('active');
}

function renderTimeline(tl){
  if(!tl||!tl.length){document.getElementById('timelineWrap').style.display='none';return;}
  document.getElementById('timelineWrap').style.display='';
  const TYPE_LABEL={stockout:'Rupture',expiry:'Péremption',milestone:'Objectif',ok:'Prévision'};
  const dow_fr=['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
  document.getElementById('tlList').innerHTML=tl.map(e=>{
    const d=new Date(e.date+'T00:00:00');
    const dStr=`${dow_fr[d.getDay()]} ${d.getDate()}/${d.getMonth()+1}`;
    const type=e.type||'ok';
    const sev=e.severity||'ok';
    return `<div class="tl-row">
      <span class="tl-date">${dStr}</span>
      <span class="tl-dot ${sev==='milestone'?'milestone':sev}"></span>
      <span class="tl-label">${e.label}</span>
      <span class="tl-type ${type}">${TYPE_LABEL[type]||type}</span>
    </div>`;
  }).join('');
}

function renderHealthScore(hs){
  if(!hs)return;
  const score=hs.score||0;
  const label=(hs.label||'').toLowerCase();
  document.getElementById('hsScore').textContent=score;
  const tag=document.getElementById('hsTag');
  tag.textContent=hs.label;
  tag.className='hs-tag '+label;
  tag.style.display='';

  // Colour score by level
  const scoreEl=document.getElementById('hsScore');
  scoreEl.style.color=score>=85?'var(--green)':score>=70?'var(--blue)':score>=55?'var(--amber)':'var(--red)';

  // Breakdown bars
  const dims=hs.breakdown||{};
  document.getElementById('hsDims').innerHTML=Object.entries(dims).map(([name,val])=>`
    <div class="hs-dim">
      <div class="hs-dim-head">
        <span class="hs-dim-name">${name}</span>
        <span class="hs-dim-val">${val}%</span>
      </div>
      <div class="hs-bar-wrap"><div class="hs-bar" style="width:${val}%;background:${val>=80?'var(--green)':val>=60?'var(--blue)':val>=40?'var(--amber)':'var(--red)'}"></div></div>
    </div>`).join('');
}

async function loadBrief(){
  document.getElementById('aiDot').classList.remove('online');
  document.getElementById('aiStatus').textContent='Analyse en cours…';

  let data;
  try{
    const r=await fetch('/analytics/api.php?type=brief');
    data=await r.json();
  }catch(e){
    document.getElementById('tabPanels').innerHTML='<div class="tab-panel active"><div class="err">Service IA indisponible. Vérifiez que le serveur Python est en ligne.</div></div>';
    document.getElementById('aiStatus').textContent='Service indisponible';
    return;
  }
  if(!data||data.available===false){
    document.getElementById('tabPanels').innerHTML=`<div class="tab-panel active"><div class="err">${data?.error||'Erreur inconnue'}</div></div>`;
    document.getElementById('aiStatus').textContent='Erreur';
    return;
  }

  briefData=data;
  document.getElementById('aiDot').classList.add('online');
  document.getElementById('aiStatus').textContent='digiMind · en ligne';

  // Generated time
  const genAt=data.generated_at?new Date(data.generated_at).toLocaleTimeString('fr',{hour:'2-digit',minute:'2-digit'}):'';
  document.getElementById('genTime').textContent=genAt?`Généré à ${genAt}`:'';

  // Greeting body
  const rows=(data.data_rows||0).toLocaleString('fr');
  const inv=(data.inventory_count||0).toLocaleString('fr');
  const g=data.greeting||{};
  const hasRisk=g.revenue_at_risk>0||g.products_requiring_action>0;
  document.getElementById('gBody').innerHTML=
    `Pendant votre absence, j'ai analysé <strong>${rows} transactions</strong> et <strong>${inv} articles</strong> en stock. `+
    (hasRisk?`Voici les cinq décisions les plus importantes que vous devrez prendre aujourd'hui.`:`La situation est stable. Voici votre briefing du jour.`);

  // Stats
  if(g&&Object.keys(g).length){
    document.getElementById('gsRisk').textContent=fmt(g.revenue_at_risk);
    document.getElementById('gsOpp').textContent=fmt(g.revenue_recoverable);
    document.getElementById('gsProd').textContent=fmtN(g.products_requiring_action)+' produits';
    document.getElementById('gsProb').textContent=g.monthly_probability+'%';
    document.getElementById('gStats').style.display='grid';
  }

  // Health score
  renderHealthScore(data.health_score);

  // Tabs + panels
  const sections=data.sections||[];
  if(sections.length){
    const critSec=sections.find(s=>(s.cards||[]).some(c=>c.severity==='critical'));
    activeTab=critSec?critSec.id:(sections[0]?.id||'risks');
    buildTabBar(sections);
    document.getElementById('tabPanels').innerHTML=sections.map(renderPanel).join('');
  }else{
    document.getElementById('tabPanels').innerHTML='<div class="tab-panel active"><div class="err">Aucune donnée. Lancez une synchronisation.</div></div>';
  }

  // Timeline
  renderTimeline(data.timeline||[]);
}

loadBrief();

function openSidebar()  { document.querySelector('.sidebar').classList.add('open'); document.getElementById('sidebarOverlay').classList.add('open'); }
function closeSidebar() { document.querySelector('.sidebar').classList.remove('open'); document.getElementById('sidebarOverlay').classList.remove('open'); }
</script>
</body>
</html>
