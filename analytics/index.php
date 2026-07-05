<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
ai_check_auth();
$user       = ai_user();
$activePage = 'dashboard';
$jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$mois  = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
$today = $jours[(int)date('w')] . ' ' . date('j') . ' ' . $mois[(int)date('n')] . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>digiMind — Briefing</title>
<style>
<?php require_once __DIR__ . '/includes/common.css.php'; ?>

:root { --purple:#7c3aed; --teal:#0d9488; --orange:#f97316; --orange-lt:#ffedd5; }
.content { padding:20px 24px 32px; flex:1; display:flex; flex-direction:column; gap:16px; }

/* Topbar */
.topbar-meta { display:flex; align-items:center; gap:5px; font-size:12px; color:var(--text-3); }
.topbar-actions { display:flex; align-items:center; gap:8px; }
.tb-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 13px; border:1px solid var(--border); border-radius:8px; background:var(--surface); color:var(--text-2); font-size:13px; font-weight:500; cursor:pointer; transition:background .12s; white-space:nowrap; }
.tb-btn:hover { background:var(--surface-alt); }
.tb-btn svg { width:14px; height:14px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
.bell-wrap { position:relative; }
.bell-btn { width:36px; height:36px; padding:0; border:1px solid var(--border); border-radius:8px; background:var(--surface); cursor:pointer; display:flex; align-items:center; justify-content:center; color:var(--text-2); }
.bell-btn svg { width:18px; height:18px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
.bell-badge { position:absolute; top:-5px; right:-5px; min-width:18px; height:18px; padding:0 4px; background:#ef4444; color:#fff; border-radius:99px; font-size:10px; font-weight:700; display:none; align-items:center; justify-content:center; border:2px solid var(--surface); }

/* Period picker */
.period-picker { position:relative; }
.period-dropdown { position:absolute; top:calc(100% + 6px); right:0; background:var(--surface); border:1px solid var(--border); border-radius:10px; box-shadow:0 4px 16px rgba(0,0,0,.1); z-index:300; min-width:180px; padding:4px; display:none; }
.period-dropdown.open { display:block; }
.period-opt { display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:7px; font-size:13px; color:var(--text-2); cursor:pointer; transition:background .12s; white-space:nowrap; }
.period-opt:hover { background:var(--surface-alt); color:var(--text); }
.period-opt.active { color:var(--green-dk); font-weight:600; background:var(--green-lt); }
.period-sep { height:1px; background:var(--border-lt); margin:4px 0; }

/* Layout */
.dash-top    { display:grid; grid-template-columns:1fr 300px; gap:16px; }
.dash-bottom { display:grid; grid-template-columns:1fr 360px; gap:16px; }

/* Greeting */
.greeting-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:20px 22px; }
.g-head { display:flex; align-items:flex-start; gap:14px; margin-bottom:16px; }
.g-icon { width:44px; height:44px; background:var(--green); border-radius:10px; display:grid; place-items:center; flex-shrink:0; }
.g-icon svg { width:22px; height:22px; stroke:#fff; fill:none; stroke-width:2.2; stroke-linecap:round; stroke-linejoin:round; }
.g-title { font-size:17px; font-weight:700; }
.g-title span { color:var(--green); }
.g-sub { font-size:13px; color:var(--text-2); margin-top:5px; line-height:1.55; }
.g-sub strong { color:var(--text); font-weight:600; }
.kpi-strip { display:grid; grid-template-columns:repeat(4,1fr); border:1px solid var(--border); border-radius:8px; overflow:hidden; }
.kpi-item { padding:12px 14px 0; border-right:1px solid var(--border); display:flex; flex-direction:column; }
.kpi-item:last-child { border-right:none; }
.kpi-val { font-size:20px; font-weight:800; line-height:1; font-variant-numeric:tabular-nums; }
.kpi-val.red   { color:#ef4444; }
.kpi-val.teal  { color:#0d9488; }
.kpi-val.amber { color:#f97316; }
.kpi-val.blue  { color:#3b82f6; }
.kpi-lbl { font-size:11px; color:var(--text-3); margin-top:3px; line-height:1.3; }
.kpi-spark { display:block; margin-top:auto; padding-top:8px; width:100%; height:36px; }

/* Health card */
.health-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:18px 20px; display:flex; flex-direction:column; gap:14px; }
.hc-top  { display:flex; align-items:center; justify-content:space-between; }
.hc-label { font-size:10px; font-weight:700; color:var(--text-3); text-transform:uppercase; letter-spacing:.8px; }
.hc-badge { padding:3px 10px; border-radius:99px; font-size:11px; font-weight:700; }
.hc-badge.excellent,.hc-badge.bon { background:var(--green-lt); color:var(--green-dk); }
.hc-badge.moyen  { background:var(--amber-lt); color:#92400e; }
.hc-badge.faible { background:var(--red-lt); color:var(--red); }
.hc-score-row { display:flex; align-items:baseline; gap:6px; }
.hc-score { font-size:42px; font-weight:800; line-height:1; color:var(--text); font-variant-numeric:tabular-nums; }
.hc-denom { font-size:15px; color:var(--text-3); }
.hc-dims { display:flex; flex-direction:column; gap:9px; }
.hc-dim { display:flex; flex-direction:column; gap:4px; }
.hc-dim-meta { display:flex; justify-content:space-between; }
.hc-dim-name { font-size:12px; color:var(--text-2); }
.hc-dim-pct  { font-size:12px; font-weight:600; color:var(--text); font-variant-numeric:tabular-nums; }
.hc-track { height:4px; background:var(--border-lt); border-radius:2px; overflow:hidden; }
.hc-fill  { height:100%; border-radius:2px; transition:width .6s ease; }
.hc-link  { font-size:12px; color:var(--green); font-weight:600; text-decoration:none; display:flex; align-items:center; gap:4px; }
.hc-link:hover { text-decoration:underline; }
.hc-link svg { width:13px; height:13px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; }

/* Tab section */
.tab-section { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
.tab-bar  { display:flex; border-bottom:1px solid var(--border); overflow-x:auto; scrollbar-width:none; }
.tab-bar::-webkit-scrollbar { display:none; }
.tab-btn  { display:inline-flex; align-items:center; gap:6px; padding:14px 16px; font-size:12.5px; font-weight:500; color:var(--text-3); border:none; background:none; cursor:pointer; border-bottom:2px solid transparent; white-space:nowrap; transition:color .12s,border-color .12s; flex-shrink:0; }
.tab-btn:hover { color:var(--text-2); background:var(--surface-alt); }
.tab-btn.active { font-weight:600; }
.tab-btn.t-risks.active   { color:#ef4444; border-bottom-color:#ef4444; }
.tab-btn.t-opp.active     { color:var(--green-dk); border-bottom-color:var(--green); }
.tab-btn.t-actions.active { color:#f97316; border-bottom-color:#f97316; }
.tab-btn.t-fcast.active   { color:var(--blue); border-bottom-color:var(--blue); }
.tab-btn.t-insight.active { color:#7c3aed; border-bottom-color:#7c3aed; }
.tab-btn svg { width:13px; height:13px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; }
.tab-count { display:inline-flex; align-items:center; justify-content:center; min-width:20px; height:20px; padding:0 5px; border-radius:99px; font-size:10px; font-weight:700; background:var(--surface-alt); color:var(--text-3); }
.tab-dot { width:6px; height:6px; border-radius:50%; flex-shrink:0; }
.tab-dot.critical { background:#ef4444; }
.tab-dot.warning  { background:#f97316; }

/* Two-column content */
.tab-content { display:grid; grid-template-columns:1fr 340px; }
.main-panel  { padding:20px; }
.side-panel  { padding:16px 18px; background:#fafbfc; border-left:1px solid var(--border-lt); }

/* Alert card */
.alert-card { border:1px solid var(--border); border-left:3px solid var(--border); border-radius:10px; background:var(--surface); margin-bottom:14px; }
.alert-card:last-child { margin-bottom:0; }
.alert-card.sev-critical { border-left-color:#ef4444; }
.alert-card.sev-warning  { border-left-color:#f97316; }
.alert-card.sev-info     { border-left-color:var(--blue); }
.alert-card.sev-ok       { border-left-color:var(--green); }
.alert-head  { display:flex; align-items:center; justify-content:space-between; padding:14px 16px 0; }
.sev-badge   { padding:2px 8px; border-radius:99px; font-size:10px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; }
.sev-badge.sev-critical { background:#fee2e2; color:#b91c1c; }
.sev-badge.sev-warning  { background:#ffedd5; color:#c2410c; }
.sev-badge.sev-info     { background:var(--blue-lt); color:var(--blue-dk); }
.sev-badge.sev-ok       { background:var(--green-lt); color:var(--green-dk); }
.conf-lbl    { font-size:11px; color:var(--text-3); }
.alert-title { padding:10px 16px 0; font-size:15px; font-weight:700; line-height:1.3; }
.alert-body  { padding:14px 16px; display:flex; flex-direction:column; gap:11px; }
.field-label { font-size:10px; font-weight:700; letter-spacing:.8px; text-transform:uppercase; color:var(--text-3); margin-bottom:3px; }
.field-text  { font-size:13px; color:var(--text-2); line-height:1.6; }
.impact-box  { background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:10px 14px; display:flex; gap:8px; align-items:flex-start; }
.impact-box svg { width:14px; height:14px; stroke:#ef4444; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; margin-top:2px; }
.result-box  { background:#f0fdf4; border:1px solid #86efac; border-radius:8px; padding:10px 14px; display:flex; gap:8px; align-items:flex-start; }
.result-box svg { width:14px; height:14px; stroke:#16a34a; fill:none; stroke-width:2.5; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; margin-top:2px; }
.result-box .field-text  { color:#15803d; }
.result-box .field-label { color:#16a34a; }
.rec-wrap { display:flex; gap:8px; }
.rec-arrow { color:var(--text-3); flex-shrink:0; margin-top:1px; }
.alert-footer { padding:0 16px 16px; display:flex; gap:10px; flex-wrap:wrap; }
.btn-danger { display:inline-flex; align-items:center; gap:6px; padding:9px 18px; border-radius:8px; background:#ef4444; color:#fff; border:none; cursor:pointer; font-size:13px; font-weight:600; text-decoration:none; transition:background .12s; }
.btn-danger:hover { background:#dc2626; }
.btn-danger svg { width:13px; height:13px; stroke:currentColor; fill:none; stroke-width:2.5; stroke-linecap:round; stroke-linejoin:round; }
.btn-ghost  { display:inline-flex; align-items:center; gap:6px; padding:9px 18px; border-radius:8px; background:var(--surface); color:var(--text-2); border:1px solid var(--border); cursor:pointer; font-size:13px; font-weight:500; text-decoration:none; transition:all .12s; }
.btn-ghost:hover { background:var(--surface-alt); }
.btn-ghost svg { width:13px; height:13px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }

/* Side panel */
.sp-head  { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
.sp-title { font-size:10px; font-weight:700; color:var(--text-3); text-transform:uppercase; letter-spacing:.8px; }
.sp-link  { font-size:12px; color:var(--green); font-weight:600; text-decoration:none; }
.sp-link:hover { text-decoration:underline; }
.action-list { display:flex; flex-direction:column; gap:6px; }
.action-item { display:flex; align-items:flex-start; gap:10px; padding:10px 12px; border-radius:8px; background:var(--surface); border:1px solid var(--border); cursor:pointer; transition:background .12s; text-decoration:none; }
.action-item:hover { background:#f9fafb; }
.action-icon { width:32px; height:32px; border-radius:7px; display:grid; place-items:center; flex-shrink:0; }
.action-icon svg { width:15px; height:15px; stroke:#fff; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
.action-body  { flex:1; min-width:0; }
.action-title { font-size:12.5px; font-weight:600; color:var(--text); line-height:1.3; }
.action-sub   { font-size:11px; color:var(--text-3); margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.action-meta  { display:flex; align-items:center; justify-content:space-between; margin-top:5px; }
.impact-lbl   { font-size:10px; color:var(--text-3); }
.impact-val   { font-size:12px; font-weight:700; color:var(--green); }
.priority-badge { padding:2px 7px; border-radius:99px; font-size:10px; font-weight:700; }
.priority-badge.critique  { background:#fee2e2; color:#b91c1c; }
.priority-badge.important { background:#ffedd5; color:#c2410c; }
.priority-badge.moyen     { background:var(--blue-lt); color:var(--blue-dk); }
.action-chevron { color:var(--text-3); font-size:16px; flex-shrink:0; align-self:center; margin-left:4px; }

/* Bottom row */
.trend-card, .products-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:18px 20px; }
.bcard-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
.bcard-title { font-size:13px; font-weight:700; color:var(--text); }
.bcard-meta  { display:flex; align-items:center; gap:8px; font-size:12px; color:var(--text-3); }
.trend-pct { display:inline-flex; align-items:center; gap:3px; padding:2px 8px; border-radius:99px; font-size:11px; font-weight:600; }
.trend-pct.up   { background:var(--green-lt); color:var(--green-dk); }
.trend-pct.down { background:var(--red-lt); color:var(--red); }

/* Sidebar extras */
.logo-sub { font-size:11px; color:var(--text-3); }
body.sb-col .logo-sub { display:none; }
.nav-badge { min-width:18px; height:18px; padding:0 5px; border-radius:99px; font-size:10px; font-weight:700; background:#ef4444; color:#fff; display:inline-flex; align-items:center; justify-content:center; margin-left:auto; }

/* Skeleton */
.sk { background:linear-gradient(90deg,#f0f0f0 25%,#e8e8e8 50%,#f0f0f0 75%); background-size:200% 100%; animation:sk 1.4s infinite; border-radius:6px; display:block; }
@keyframes sk { 0%{background-position:200% 0}100%{background-position:-200% 0} }
.err-box { background:var(--red-lt); border:1px solid #fecaca; border-radius:8px; padding:12px 16px; color:var(--red-dk); font-size:13px; }
.empty-msg { text-align:center; padding:40px 20px; color:var(--text-3); font-size:13px; }

@media(max-width:1280px) { .dash-top { grid-template-columns:1fr 280px; } }
@media(max-width:1100px) {
  .dash-top    { grid-template-columns:1fr; }
  .tab-content { grid-template-columns:1fr; }
  .side-panel  { border-left:none; border-top:1px solid var(--border-lt); }
}
@media(max-width:768px) {
  .topbar-date { display:none; }
  .kpi-strip   { grid-template-columns:repeat(2,1fr); }
  .tab-btn     { padding:10px 10px; font-size:11.5px; gap:4px; }
  .tab-btn svg { width:12px; height:12px; }
  .content     { padding:12px 12px 28px !important; gap:12px; }
  .topbar-actions { gap:6px; }
  .tb-btn      { padding:6px 10px; font-size:12px; }
  .g-title     { font-size:15px; }
  .hc-score    { font-size:34px; }
  .kpi-val     { font-size:17px; }
  .alert-title { font-size:14px; }
}
@media(max-width:480px) {
  .kpi-strip   { grid-template-columns:1fr; }
  .kpi-item    { border-right:none; border-bottom:1px solid var(--border); }
  .kpi-item:last-child { border-bottom:none; }
  .tab-btn span:not(.tab-count):not(.tab-dot) { display:none; }
  .tab-btn     { padding:12px 10px; }
  .g-icon      { width:36px; height:36px; border-radius:8px; }
  .g-icon svg  { width:18px; height:18px; }
  .g-title     { font-size:14px; }
  .tb-btn .btn-label { display:none; }
  .hc-score    { font-size:30px; }
}
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left" style="flex-direction:row;align-items:center;gap:10px;">
      <button class="hamburger" onclick="openSidebar()" aria-label="Menu">
        <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div style="min-width:0;overflow:hidden;">
        <div class="topbar-title">Briefing quotidien</div>
        <div class="topbar-meta">
          <span class="status-dot" id="aiDot"></span>
          <span id="aiStatus">Analyse en cours…</span>
          <span class="topbar-date">·</span>
          <span class="topbar-date"><?= $today ?></span>
          <span class="topbar-date" id="genTimeWrap" style="display:none">· <span id="genTime"></span></span>
        </div>
      </div>
    </div>
    <div class="topbar-actions">
      <button class="tb-btn" onclick="loadAll()">
        <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3"/></svg>
        <span class="btn-label">Actualiser</span>
      </button>
      <div class="bell-wrap">
        <a href="/analytics/alerts.php" class="bell-btn" title="Alertes">
          <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        </a>
        <span class="bell-badge" id="bellBadge"></span>
      </div>
    </div>
  </div>

  <div class="content">

    <!-- Top row: greeting + health -->
    <div class="dash-top">
      <div class="greeting-card">
        <div class="g-head">
          <div class="g-icon"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
          <div>
            <div class="g-title">Bonjour, <span><?= htmlspecialchars($user['pharmacy_name']) ?></span> 👋</div>
            <div class="g-sub" id="gSub">
              <span class="sk" style="width:90%;height:13px;display:block;margin-bottom:5px"></span>
              <span class="sk" style="width:70%;height:13px;display:block"></span>
            </div>
          </div>
        </div>
        <div class="kpi-strip" id="kpiStrip">
          <?php for($i=0;$i<4;$i++): ?>
          <div class="kpi-item">
            <span class="sk" style="width:80px;height:20px;margin-bottom:4px"></span>
            <span class="sk" style="width:100%;height:10px"></span>
          </div>
          <?php endfor; ?>
        </div>
      </div>

      <div class="health-card" id="healthCard">
        <div class="hc-top">
          <div class="hc-label">Score de santé IA</div>
          <span class="sk" style="width:40px;height:22px;border-radius:99px"></span>
        </div>
        <div class="hc-score-row">
          <div class="hc-score" id="hcScore"><span class="sk" style="width:70px;height:42px;display:inline-block"></span></div>
          <div class="hc-denom">/100</div>
        </div>
        <div class="hc-dims" id="hcDims">
          <?php for($i=0;$i<5;$i++): ?>
          <div class="hc-dim">
            <div class="hc-dim-meta">
              <span class="sk" style="width:120px;height:11px"></span>
              <span class="sk" style="width:28px;height:11px"></span>
            </div>
            <div class="hc-track"><div class="hc-fill" style="width:0;background:var(--border)"></div></div>
          </div>
          <?php endfor; ?>
        </div>
        <a href="/analytics/" class="hc-link" style="display:none" id="hcLink">
          Voir le détail
          <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </div>
    </div>

    <!-- Tab section -->
    <div class="tab-section" id="tabSection">
      <div class="tab-bar" id="tabBar">
        <div style="display:flex;gap:12px;padding:14px 16px">
          <?php for($i=0;$i<5;$i++): ?><span class="sk" style="width:100px;height:16px;border-radius:4px"></span><?php endfor; ?>
        </div>
      </div>
      <div class="tab-content">
        <div class="main-panel" id="mainPanel">
          <div class="alert-card sev-ok" style="padding:0">
            <div class="alert-head"><span class="sk" style="width:70px;height:18px;border-radius:99px"></span></div>
            <div style="padding:10px 16px 0"><span class="sk" style="width:75%;height:18px"></span></div>
            <div class="alert-body" style="gap:8px">
              <span class="sk" style="width:100%;height:13px"></span>
              <span class="sk" style="width:90%;height:13px"></span>
              <span class="sk" style="width:100%;height:54px;border-radius:8px"></span>
              <span class="sk" style="width:100%;height:54px;border-radius:8px"></span>
            </div>
          </div>
        </div>
        <div class="side-panel" id="sidePanel">
          <div class="sp-head">
            <span class="sp-title">Actions du jour</span>
          </div>
          <div class="action-list">
            <?php for($i=0;$i<4;$i++): ?>
            <div class="action-item" style="cursor:default">
              <span class="sk" style="width:32px;height:32px;border-radius:7px;flex-shrink:0"></span>
              <div style="flex:1">
                <span class="sk" style="width:90%;height:13px;margin-bottom:4px"></span>
                <span class="sk" style="width:60%;height:10px"></span>
              </div>
            </div>
            <?php endfor; ?>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
const TABS = [
  {id:'risks',         label:'Ce qui menace',       cls:'t-risks',   icon:'<svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>'},
  {id:'opportunities', label:'Argent sur la table',  cls:'t-opp',     icon:'<svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>'},
  {id:'actions',       label:'Actions du jour',      cls:'t-actions', icon:'<svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>'},
  {id:'forecasts',     label:'Ce qui va se passer',  cls:'t-fcast',   icon:'<svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>'},
  {id:'insights',      label:"Ce que j'ai trouvé",  cls:'t-insight', icon:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'},
];
const ACTION_COLORS = ['#ef4444','#f97316','#3b82f6','#eab308','#8b5cf6','#0d9488'];
const ACTION_ICONS = [
  '<svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>',
  '<svg viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
  '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  '<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
  '<svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
];
const ZAP   = '<svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';
const CHECK = '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>';
const ARROW = '<svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
const CART  = '<svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>';

let briefData = null;
let activeTab = 'risks';

function fmt(n) {
  if (!n && n !== 0) return '—';
  n = parseFloat(n);
  if (n >= 1e6) return (n/1e6).toFixed(1) + 'M XAF';
  if (n >= 1e3) return Math.round(n/1e3) + 'k XAF';
  return Math.round(n).toLocaleString('fr') + ' XAF';
}

function priorityBadge(sev) {
  if (sev === 'critical') return '<span class="priority-badge critique">Critique</span>';
  if (sev === 'warning')  return '<span class="priority-badge important">Important</span>';
  return '<span class="priority-badge moyen">Moyen</span>';
}

function renderGreeting(data) {
  const g = data.greeting || {};
  const rows = (data.data_rows || 0).toLocaleString('fr');
  const inv  = (data.inventory_count || 0).toLocaleString('fr');
  document.getElementById('gSub').innerHTML =
    `Pendant votre absence, j'ai analysé <strong>${rows} transactions</strong> et <strong>${inv} articles</strong> en stock. `+
    `Voici les cinq décisions les plus importantes que vous devrez prendre aujourd'hui.`;

  const kpis = [
    { val: fmt(g.revenue_at_risk),            lbl: 'CA à risque aujourd\'hui', cls: 'red',  spark: 'down' },
    { val: fmt(g.revenue_recoverable),         lbl: 'CA récupérable',           cls: 'teal', spark: 'up'   },
    { val: (g.products_requiring_action||'—')+' produits', lbl: 'Produits à traiter', cls: 'amber', spark: 'flat' },
    { val: (g.monthly_probability||'—')+'%',  lbl: 'Probabilité objectif mensuel', cls: 'blue', spark: 'up' },
  ];
  const colors = { red:'#ef4444', teal:'#0d9488', amber:'#f97316', blue:'#3b82f6' };
  document.getElementById('kpiStrip').innerHTML = kpis.map((k,i) =>
    `<div class="kpi-item">
      <div class="kpi-val ${k.cls}">${k.val}</div>
      <div class="kpi-lbl">${k.lbl}</div>
      <canvas class="kpi-spark" id="spark${i}"></canvas>
    </div>`
  ).join('');
  requestAnimationFrame(() => kpis.forEach((k, i) => drawSparkline('spark' + i, k.spark, colors[k.cls])));
}

function renderHealth(hs) {
  if (!hs) return;
  const score = hs.score || 0;
  const label = (hs.label || 'bon').toLowerCase();
  const scoreEl = document.getElementById('hcScore');
  scoreEl.textContent = score;
  scoreEl.style.color = score >= 85 ? 'var(--green)' : score >= 70 ? 'var(--blue)' : score >= 55 ? '#f97316' : '#ef4444';

  const topEl = document.querySelector('#healthCard .hc-top');
  topEl.innerHTML = `<div class="hc-label">Score de santé IA</div><span class="hc-badge ${label}">${hs.label}</span>`;

  const DIM_COLORS = { default: 'var(--blue)' };
  const dimColors = ['#3b82f6','var(--green)','#ef4444','var(--green)','#3b82f6'];
  const dims = hs.breakdown || {};
  document.getElementById('hcDims').innerHTML = Object.entries(dims).map(([name, val], i) =>
    `<div class="hc-dim">
      <div class="hc-dim-meta">
        <span class="hc-dim-name">${name}</span>
        <span class="hc-dim-pct">${val}%</span>
      </div>
      <div class="hc-track"><div class="hc-fill" style="width:${val}%;background:${dimColors[i]||'var(--green)'}"></div></div>
    </div>`
  ).join('');
  document.getElementById('hcLink').style.display = 'flex';
}

function renderTabs(sections) {
  document.getElementById('tabBar').innerHTML = sections.map(sec => {
    const t = TABS.find(x => x.id === sec.id) || { cls: '', label: sec.id, icon: '' };
    const cards = sec.cards || [];
    const crit = cards.some(c => c.severity === 'critical');
    const warn = !crit && cards.some(c => c.severity === 'warning');
    const dot = crit ? '<span class="tab-dot critical"></span>' : warn ? '<span class="tab-dot warning"></span>' : '';
    return `<button class="tab-btn ${t.cls}${sec.id === activeTab ? ' active' : ''}" onclick="switchTab('${sec.id}')">
      ${t.icon}<span>${t.label}</span><span class="tab-count">${cards.length}</span>${dot}
    </button>`;
  }).join('');
}

function switchTab(id) {
  if (!briefData) return;
  activeTab = id;
  document.querySelectorAll('.tab-btn').forEach(b => {
    const isActive = b.getAttribute('onclick') === `switchTab('${id}')`;
    const meta = TABS.find(t => t.id === id) || {};
    b.className = 'tab-btn ' + (isActive ? (meta.cls || '') + ' active' : b.className.replace(' active', '').replace(/t-\S+/,'').trim() + ' ' + (TABS.find(t => b.getAttribute('onclick')?.includes(t.id))?.cls || ''));
    if (isActive) b.className = 'tab-btn ' + meta.cls + ' active';
    else {
      const bid = b.getAttribute('onclick')?.match(/switchTab\('(\w+)'\)/)?.[1];
      const bm = TABS.find(t => t.id === bid) || {};
      b.className = 'tab-btn ' + bm.cls;
    }
  });
  renderMainPanel(briefData.sections);
}

function renderMainPanel(sections) {
  const sec = sections.find(s => s.id === activeTab);
  const cards = sec?.cards || [];
  if (!cards.length) {
    document.getElementById('mainPanel').innerHTML = '<div class="empty-msg">Aucune donnée pour cette section.</div>';
    return;
  }
  document.getElementById('mainPanel').innerHTML = cards.map(card => renderAlertCard(card)).join('');
}

function renderAlertCard(card) {
  const sev = card.severity || 'info';
  const SEV_LABEL = { critical: 'Critique', warning: 'Attention', info: 'Info', ok: 'OK' };
  const result = card.expected_result
    ? `<div class="result-box">${CHECK}<div><div class="field-label" style="color:#16a34a">Résultat attendu</div><div class="field-text" style="color:#15803d">${card.expected_result}</div></div></div>` : '';
  const btnPrimary = card.action_target
    ? (sev === 'critical'
        ? `<a href="${card.action_target}" class="btn-danger">${CART} ${card.action_label||'Agir maintenant'}</a>`
        : `<a href="${card.action_target}" class="btn-ghost">${card.action_label||'Voir'} ${ARROW}</a>`)
    : '';
  const btnSec = card.action_target && sev === 'critical'
    ? `<a href="${card.action_target}" class="btn-ghost">Voir les produits concernés ${ARROW}</a>` : '';
  return `<div class="alert-card sev-${sev}">
    <div class="alert-head">
      <span class="sev-badge sev-${sev}">${SEV_LABEL[sev]||'Info'}</span>
      <span class="conf-lbl">Confiance : ${card.confidence}%</span>
    </div>
    <div class="alert-title">${card.headline}</div>
    <div class="alert-body">
      <div><div class="field-label">Pourquoi l'IA a détecté ça</div><div class="field-text">${card.explanation}</div></div>
      <div class="impact-box">${ZAP}<div><div class="field-label">Impact business</div><div class="field-text">${card.impact}</div></div></div>
      <div><div class="field-label">Recommandation</div><div class="rec-wrap"><span class="rec-arrow">→</span><div class="field-text">${card.recommendation}</div></div></div>
      ${result}
    </div>
    ${btnPrimary || btnSec ? `<div class="alert-footer">${btnPrimary}${btnSec}</div>` : ''}
  </div>`;
}

function renderActionsPanel(sections) {
  const actionSec = sections.find(s => s.id === 'actions');
  const cards = (actionSec?.cards || []).slice(0, 5);
  if (!cards.length) {
    document.getElementById('sidePanel').innerHTML = '<div class="sp-head"><span class="sp-title">Actions du jour</span></div><div class="empty-msg">Aucune action.</div>';
    return;
  }
  document.getElementById('sidePanel').innerHTML =
    `<div class="sp-head"><span class="sp-title">Actions du jour</span><a href="/analytics/" class="sp-link">Voir tout →</a></div>
    <div class="action-list">${cards.map((c, i) => {
      const bg = ACTION_COLORS[i % ACTION_COLORS.length];
      const icon = ACTION_ICONS[i % ACTION_ICONS.length];
      const badge = priorityBadge(c.severity);
      const impact = c.impact ? `<div class="action-meta"><span class="impact-lbl">Impact estimé</span>${badge}</div>` : `<div class="action-meta">${badge}</div>`;
      return `<a class="action-item" href="${c.action_target||'#'}">
        <div class="action-icon" style="background:${bg}">${icon}</div>
        <div class="action-body">
          <div class="action-title">${c.headline}</div>
          <div class="action-sub">${c.explanation?.substring(0,60)||''}</div>
          ${impact}
        </div>
        <span class="action-chevron">›</span>
      </a>`;
    }).join('')}</div>`;
}

function renderBellBadge(sections) {
  const total = sections.reduce((sum, s) => sum + (s.cards||[]).filter(c => ['critical','warning'].includes(c.severity)).length, 0);
  if (total > 0) {
    const b = document.getElementById('bellBadge');
    b.textContent = total;
    b.style.display = 'flex';
  }
  // Alertes nav badge — add once, update after
  const alertsLink = document.querySelector('.nav-link[href="/analytics/alerts.php"]');
  if (alertsLink && total > 0) {
    let nb = alertsLink.querySelector('.nav-badge');
    if (!nb) { nb = document.createElement('span'); nb.className = 'nav-badge'; alertsLink.appendChild(nb); }
    nb.textContent = total;
    nb.style.display = 'inline-flex';
  }
}

// ── Sparkline (with area fill) ────────────────────────────
function hexRgb(hex) {
  const n = parseInt(hex.slice(1), 16);
  return [(n>>16)&255, (n>>8)&255, n&255];
}
function drawSparkline(id, trend, color) {
  const canvas = document.getElementById(id);
  if (!canvas) return;
  const dpr = window.devicePixelRatio || 1;
  canvas.width  = canvas.offsetWidth  * dpr || 100 * dpr;
  canvas.height = canvas.offsetHeight * dpr || 36 * dpr;
  const ctx = canvas.getContext('2d');
  ctx.scale(dpr, dpr);
  const W = canvas.offsetWidth || 100, H = canvas.offsetHeight || 36;

  const base = 0.45 + Math.random() * 0.2;
  const vals = Array.from({length:10}, (_, i) => {
    const t = i / 9;
    const noise = (Math.random() - 0.5) * 0.12;
    return trend === 'up'   ? base + t * 0.35 + noise
         : trend === 'down' ? base + 0.35 - t * 0.35 + noise
         : base + Math.sin(t * Math.PI) * 0.1 + noise;
  });
  const min = Math.min(...vals) - 0.02, max = Math.max(...vals) + 0.02;
  const range = max - min || 0.1;
  const toX = i => (i / (vals.length - 1)) * W;
  const toY = v => H - 2 - ((v - min) / range) * (H - 4);

  ctx.clearRect(0, 0, W, H);

  // Area fill with gradient
  const [r, g, b] = hexRgb(color);
  const grad = ctx.createLinearGradient(0, 0, 0, H);
  grad.addColorStop(0, `rgba(${r},${g},${b},0.2)`);
  grad.addColorStop(1, `rgba(${r},${g},${b},0.02)`);
  ctx.beginPath();
  vals.forEach((v, i) => { i === 0 ? ctx.moveTo(toX(i), toY(v)) : ctx.lineTo(toX(i), toY(v)); });
  ctx.lineTo(toX(vals.length - 1), H);
  ctx.lineTo(0, H);
  ctx.closePath();
  ctx.fillStyle = grad;
  ctx.fill();

  // Line
  ctx.beginPath();
  vals.forEach((v, i) => { i === 0 ? ctx.moveTo(toX(i), toY(v)) : ctx.lineTo(toX(i), toY(v)); });
  ctx.strokeStyle = color;
  ctx.lineWidth = 1.8;
  ctx.lineJoin = 'round';
  ctx.lineCap  = 'round';
  ctx.stroke();
}

// ── Main loader ───────────────────────────────────────────
async function loadAll() {
  document.getElementById('aiDot').classList.remove('online');
  document.getElementById('aiStatus').textContent = 'Analyse en cours…';

  const [briefRes] = await Promise.allSettled([
    fetch('/analytics/api.php?type=brief', {credentials:'same-origin'}).then(r=>r.json()),
  ]);

  // Brief
  if (briefRes.status === 'fulfilled' && briefRes.value?.sections) {
    const data = briefRes.value;
    briefData = data;
    document.getElementById('aiDot').classList.add('online');
    document.getElementById('aiStatus').textContent = 'digiMind · en ligne';
    if (data.generated_at) {
      const t = new Date(data.generated_at).toLocaleTimeString('fr', {hour:'2-digit',minute:'2-digit'});
      document.getElementById('genTime').textContent = 'Généré à ' + t;
      document.getElementById('genTimeWrap').style.display = 'inline';
    }
    const sec = data.sections;
    const critSec = sec.find(s => (s.cards||[]).some(c => c.severity==='critical'));
    activeTab = critSec?.id || sec[0]?.id || 'risks';
    renderGreeting(data);
    renderHealth(data.health_score);
    renderTabs(sec);
    renderMainPanel(sec);
    renderActionsPanel(sec);
    renderBellBadge(sec);
  } else {
    document.getElementById('aiStatus').textContent = 'Service indisponible';
    document.getElementById('mainPanel').innerHTML = '<div class="err-box">Service IA indisponible. Vérifiez que le serveur Python est en ligne.</div>';
  }

}

loadAll();
</script>
</body>
</html>
