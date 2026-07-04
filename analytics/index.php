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
<title>DigiPharm AI — Briefing quotidien</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --green:    #1a7f4b; --green-dk: #155e38; --green-lt: #e8f5ee;
  --amber:    #d97706; --amber-dk: #92400e; --amber-lt: #fef3c7;
  --red:      #dc2626; --red-dk:   #7f1d1d; --red-lt:   #fee2e2;
  --blue:     #2563eb; --blue-dk:  #1e3a8a; --blue-lt:  #dbeafe;
  --purple:   #7c3aed; --purple-lt:#ede9fe;
  --border:   #dadce0; --border-lt:#f0f0f0;
  --text:     #111827; --text-2:   #4b5563; --text-3: #9ca3af;
  --surface:  #ffffff; --surface-alt: #f8f9fa; --bg: #f3f4f6;
  --sidebar-w:240px;   --header-h: 56px;
  --radius:   10px;
}

body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; }

/* ── Sidebar ── */
.sidebar { width:var(--sidebar-w); min-height:100vh; background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0; left:0; bottom:0; z-index:100; }
.sidebar-logo { padding:18px 20px; display:flex; align-items:center; gap:10px; border-bottom:1px solid var(--border-lt); }
.logo-icon { width:32px; height:32px; background:var(--green); border-radius:7px; display:grid; place-items:center; }
.logo-icon svg { width:18px; height:18px; stroke:#fff; fill:none; stroke-width:2.2; stroke-linecap:round; stroke-linejoin:round; }
.logo-text { font-size:15px; font-weight:700; color:var(--text); letter-spacing:-.3px; }
.logo-text span { color:var(--green); }
.sidebar-pharmacy { padding:12px 20px; font-size:11px; font-weight:600; color:var(--text-3); text-transform:uppercase; letter-spacing:.6px; border-bottom:1px solid var(--border-lt); }
nav { flex:1; padding:8px 0; }
.nav-section { padding:16px 20px 4px; font-size:10px; font-weight:700; color:var(--text-3); text-transform:uppercase; letter-spacing:.6px; }
.nav-link { display:flex; align-items:center; gap:10px; padding:8px 14px; margin:1px 8px; border-radius:8px; font-size:13.5px; color:var(--text-2); text-decoration:none; transition:background .12s,color .12s; }
.nav-link svg { width:16px; height:16px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; }
.nav-link:hover  { background:var(--surface-alt); color:var(--text); }
.nav-link.active { background:var(--green-lt); color:var(--green-dk); font-weight:600; }
.sidebar-footer { padding:14px 16px; border-top:1px solid var(--border-lt); display:flex; align-items:center; gap:10px; }
.avatar { width:32px; height:32px; background:var(--green); border-radius:50%; display:grid; place-items:center; font-size:13px; font-weight:700; color:#fff; flex-shrink:0; }
.avatar-name { font-size:13px; font-weight:600; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.avatar-role { font-size:11px; color:var(--text-3); }
.logout-btn { color:var(--text-3); text-decoration:none; font-size:11px; margin-left:auto; }
.logout-btn:hover { color:var(--red); }

/* ── Main ── */
.main { margin-left:var(--sidebar-w); flex:1; min-height:100vh; display:flex; flex-direction:column; }

/* ── Topbar ── */
.topbar { height:var(--header-h); background:var(--surface); border-bottom:1px solid var(--border); padding:0 28px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; }
.topbar-left { display:flex; flex-direction:column; gap:2px; }
.topbar-title { font-size:16px; font-weight:700; }
.topbar-meta { font-size:12px; color:var(--text-3); display:flex; align-items:center; gap:5px; }
.status-dot { display:inline-block; width:7px; height:7px; border-radius:50%; background:var(--text-3); animation:pulse 2s infinite; }
.status-dot.online { background:var(--green); }
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.4} }
.refresh-btn { padding:6px 12px; border:1px solid var(--border); border-radius:8px; background:var(--surface); cursor:pointer; color:var(--text-2); font-size:13px; display:flex; align-items:center; gap:5px; transition:background .12s; }
.refresh-btn:hover { background:var(--surface-alt); }
.refresh-btn svg { width:13px; height:13px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }

/* ── Content ── */
.content { padding:20px 28px 32px; flex:1; display:flex; flex-direction:column; gap:20px; }

/* ── Greeting ── */
.greeting {
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:var(--radius);
  padding:18px 22px;
  display:flex;
  align-items:center;
  gap:14px;
}
.greeting-brain { font-size:26px; flex-shrink:0; }
.greeting-body { flex:1; }
.greeting-hello { font-size:17px; font-weight:700; margin-bottom:4px; }
.greeting-summary { font-size:13px; color:var(--text-2); line-height:1.6; }
.greeting-summary strong { color:var(--text); }
.greeting-meta { flex-shrink:0; text-align:right; }
.greeting-date { font-size:12px; font-weight:600; color:var(--text-3); margin-bottom:2px; }
.greeting-gen  { font-size:11px; color:var(--text-3); }

/* ── Tab bar ── */
.tab-wrap {
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:var(--radius);
  overflow:hidden;
  flex:1;
  display:flex;
  flex-direction:column;
}
.tab-bar {
  display:flex;
  border-bottom:1px solid var(--border);
  overflow-x:auto;
  scrollbar-width:none;
}
.tab-bar::-webkit-scrollbar { display:none; }

.tab-btn {
  display:flex;
  align-items:center;
  gap:7px;
  padding:13px 18px;
  font-size:13px;
  font-weight:500;
  color:var(--text-3);
  border:none;
  background:none;
  cursor:pointer;
  border-bottom:2px solid transparent;
  white-space:nowrap;
  transition:color .12s, border-color .12s;
  flex-shrink:0;
}
.tab-btn:hover { color:var(--text-2); background:var(--surface-alt); }
.tab-btn.active { color:var(--text); font-weight:600; }
.tab-btn.active.t-risks   { border-bottom-color:var(--red);    color:var(--red); }
.tab-btn.active.t-opp     { border-bottom-color:var(--green);  color:var(--green-dk); }
.tab-btn.active.t-actions { border-bottom-color:var(--amber);  color:var(--amber-dk); }
.tab-btn.active.t-fcast   { border-bottom-color:var(--blue);   color:var(--blue-dk); }
.tab-btn.active.t-insight { border-bottom-color:var(--purple); color:var(--purple); }

.tab-btn svg { width:14px; height:14px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; }

.tab-badge {
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-width:18px;
  height:18px;
  padding:0 5px;
  border-radius:99px;
  font-size:10.5px;
  font-weight:700;
  background:var(--border-lt);
  color:var(--text-3);
}
.tab-btn.active .tab-badge { background:currentColor; color:#fff; filter:brightness(1.2); }
.tab-dot {
  width:6px; height:6px; border-radius:50%; flex-shrink:0;
}
.tab-dot.critical { background:var(--red); }
.tab-dot.warning  { background:var(--amber); }

/* ── Tab panels ── */
.tab-panels { flex:1; }
.tab-panel { display:none; padding:20px 24px; }
.tab-panel.active { display:block; }

/* ── Section intro ── */
.section-intro {
  display:flex;
  align-items:center;
  gap:10px;
  padding:12px 16px;
  background:var(--surface-alt);
  border:1px solid var(--border-lt);
  border-radius:8px;
  margin-bottom:16px;
  font-size:13px;
  color:var(--text-2);
}
.section-intro svg { width:15px; height:15px; stroke:var(--text-3); fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; }

/* ── Brief cards ── */
.brief-cards { display:flex; flex-direction:column; gap:14px; }

.brief-card {
  background:var(--surface);
  border:1px solid var(--border);
  border-left:4px solid var(--border);
  border-radius:var(--radius);
  overflow:hidden;
}
.brief-card.sev-critical { border-left-color:var(--red); }
.brief-card.sev-warning  { border-left-color:var(--amber); }
.brief-card.sev-info     { border-left-color:var(--blue); }
.brief-card.sev-ok       { border-left-color:var(--green); }

/* Card header row */
.card-head {
  display:flex;
  align-items:center;
  gap:10px;
  padding:14px 18px 0;
}
.sev-badge {
  padding:2px 9px;
  border-radius:99px;
  font-size:10px;
  font-weight:700;
  letter-spacing:.6px;
  text-transform:uppercase;
  flex-shrink:0;
}
.sev-badge.sev-critical { background:var(--red-lt);    color:var(--red-dk); }
.sev-badge.sev-warning  { background:var(--amber-lt);  color:var(--amber-dk); }
.sev-badge.sev-info     { background:var(--blue-lt);   color:var(--blue-dk); }
.sev-badge.sev-ok       { background:var(--green-lt);  color:var(--green-dk); }
.conf-label { font-size:11.5px; color:var(--text-3); margin-left:auto; flex-shrink:0; }

/* Headline */
.card-headline {
  padding:10px 18px 0;
  font-size:15.5px;
  font-weight:700;
  line-height:1.3;
}

/* Body — 3 labeled sections */
.card-body { padding:14px 18px; display:flex; flex-direction:column; gap:12px; }

.card-section-label {
  font-size:10px;
  font-weight:700;
  letter-spacing:.8px;
  text-transform:uppercase;
  color:var(--text-3);
  margin-bottom:4px;
}
.card-section-text {
  font-size:13px;
  color:var(--text-2);
  line-height:1.6;
}

.card-impact-box {
  background:var(--surface-alt);
  border:1px solid var(--border-lt);
  border-radius:7px;
  padding:10px 14px;
  display:flex;
  align-items:flex-start;
  gap:8px;
}
.card-impact-box svg { width:14px; height:14px; stroke:var(--amber); fill:none; stroke-width:2.2; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; margin-top:2px; }
.card-impact-box .card-section-text { margin:0; }

.card-rec-text {
  font-size:13px;
  color:var(--text-2);
  line-height:1.6;
  padding-left:16px;
  position:relative;
}
.card-rec-text::before { content:'→'; position:absolute; left:0; color:var(--text-3); }

/* Card footer */
.card-foot {
  padding:0 18px 14px;
  display:flex;
  align-items:center;
  gap:12px;
}
.conf-bar-wrap { flex:1; height:4px; background:var(--border-lt); border-radius:2px; overflow:hidden; }
.conf-bar { height:100%; border-radius:2px; }
.conf-bar.sev-critical { background:var(--red); }
.conf-bar.sev-warning  { background:var(--amber); }
.conf-bar.sev-info     { background:var(--blue); }
.conf-bar.sev-ok       { background:var(--green); }
.conf-pct { font-size:11px; color:var(--text-3); white-space:nowrap; }
.card-action-btn {
  display:inline-flex; align-items:center; gap:5px;
  padding:5px 12px; border:1px solid var(--border); border-radius:7px;
  font-size:12px; font-weight:500; color:var(--text-2);
  text-decoration:none; background:var(--surface); white-space:nowrap;
  transition:all .12s; flex-shrink:0;
}
.card-action-btn:hover { background:var(--surface-alt); border-color:var(--text-3); color:var(--text); }
.card-action-btn svg { width:11px; height:11px; stroke:currentColor; fill:none; stroke-width:2.5; stroke-linecap:round; stroke-linejoin:round; }

/* ── Skeleton ── */
.skeleton { background:linear-gradient(90deg,#f0f0f0 25%,#e8e8e8 50%,#f0f0f0 75%); background-size:200% 100%; animation:shimmer 1.4s infinite; border-radius:6px; display:block; }
@keyframes shimmer { 0%{background-position:200% 0}100%{background-position:-200% 0} }

.err-banner { background:var(--red-lt); border:1px solid #fca5a5; border-radius:var(--radius); padding:14px 18px; color:var(--red-dk); font-size:13px; }

@media (max-width:700px) {
  .greeting { flex-direction:column; }
  .greeting-meta { text-align:left; }
}
</style>
</head>
<body>

<!-- ── Sidebar ── -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">
      <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
    </div>
    <div class="logo-text">DigiPharm<span> AI</span></div>
  </div>
  <div class="sidebar-pharmacy"><?= htmlspecialchars($user['pharmacy_name']) ?></div>
  <nav>
    <div class="nav-section">Analyse</div>
    <a href="/analytics/" class="nav-link active">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Vue d'ensemble
    </a>
    <a href="/analytics/trends.php" class="nav-link">
      <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      Tendances
    </a>
    <a href="/analytics/inventory.php" class="nav-link">
      <svg viewBox="0 0 24 24"><path d="M21 16V8l-9-5-9 5v8l9 5 9-5z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
      Inventaire
    </a>
    <a href="/analytics/alerts.php" class="nav-link">
      <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      Alertes
    </a>
    <div class="nav-section" style="margin-top:8px">Données</div>
    <a href="/analytics/sync.php" class="nav-link">
      <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>
      Synchronisation
    </a>
    <a href="/analytics/settings.php" class="nav-link">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      Paramètres
    </a>
  </nav>
  <div class="sidebar-footer">
    <div class="avatar"><?= $initials ?></div>
    <div>
      <div class="avatar-name"><?= htmlspecialchars($user['display_name']) ?></div>
      <div class="avatar-role"><?= $user['role'] === 'admin' ? 'Administrateur' : 'Lecteur' ?></div>
    </div>
    <a href="/analytics/logout.php" class="logout-btn" title="Déconnexion">✕</a>
  </div>
</aside>

<!-- ── Main ── -->
<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <div class="topbar-title">Briefing quotidien</div>
      <div class="topbar-meta">
        <span class="status-dot" id="aiDot"></span>
        <span id="aiStatus">Chargement…</span>
      </div>
    </div>
    <button class="refresh-btn" onclick="loadBrief()">
      <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3"/></svg>
      Actualiser
    </button>
  </div>

  <div class="content">

    <!-- Greeting -->
    <div class="greeting">
      <div class="greeting-brain">🧠</div>
      <div class="greeting-body">
        <div class="greeting-hello">Bonjour, <strong><?= htmlspecialchars($user['pharmacy_name']) ?></strong></div>
        <div class="greeting-summary" id="greetingSummary">
          <span class="skeleton" style="width:480px;max-width:100%;height:14px;display:block;margin-bottom:6px"></span>
          <span class="skeleton" style="width:320px;max-width:100%;height:13px;display:block"></span>
        </div>
      </div>
      <div class="greeting-meta">
        <div class="greeting-date"><?= $today ?></div>
        <div class="greeting-gen" id="greetingGen"></div>
      </div>
    </div>

    <!-- Tab interface -->
    <div class="tab-wrap">
      <div class="tab-bar" id="tabBar">
        <!-- Populated by JS -->
        <div style="padding:13px 18px;display:flex;gap:10px">
          <?php for ($i = 0; $i < 5; $i++): ?>
          <span class="skeleton" style="width:100px;height:16px;border-radius:4px"></span>
          <?php endfor; ?>
        </div>
      </div>
      <div class="tab-panels" id="tabPanels">
        <div class="tab-panel active" style="padding:20px 24px">
          <?php for ($i = 0; $i < 2; $i++): ?>
          <div class="brief-card sev-ok" style="margin-bottom:14px">
            <div class="card-head"><span class="skeleton" style="width:70px;height:18px;border-radius:99px"></span></div>
            <div style="padding:10px 18px 0"><span class="skeleton" style="width:75%;height:20px"></span></div>
            <div class="card-body" style="gap:10px">
              <span class="skeleton" style="width:40px;height:10px"></span>
              <span class="skeleton" style="width:100%;height:36px"></span>
              <span class="skeleton" style="width:100%;height:50px;border-radius:7px"></span>
            </div>
          </div>
          <?php endfor; ?>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</div>

<script>
const TABS = [
  { id:'risks',         label:'Risques',       cls:'t-risks',   icon:'<svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' },
  { id:'opportunities', label:'Opportunités',  cls:'t-opp',     icon:'<svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>' },
  { id:'actions',       label:'Actions',       cls:'t-actions', icon:'<svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>' },
  { id:'forecasts',     label:'Prévisions',    cls:'t-fcast',   icon:'<svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>' },
  { id:'insights',      label:'Découvertes',   cls:'t-insight', icon:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>' },
];

const SEV_LABELS = { critical:'CRITIQUE', warning:'ATTENTION', info:'INFO', ok:'INFO' };
const ARROW = '<svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
const ZAP   = '<svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';
const INFO  = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="8"/><line x1="12" y1="12" x2="12" y2="16"/></svg>';

let briefData = null;
let activeTab = 'risks';

function sectionIntro(section) {
  const cards = section.cards || [];
  const crit  = cards.filter(c => c.severity === 'critical').length;
  const warn  = cards.filter(c => c.severity === 'warning').length;
  if (section.id === 'risks') {
    if (crit > 0) return `${crit} situation${crit>1?'s':''} critique${crit>1?'s':''} nécessite${crit>1?'nt':''} une action immédiate. Examinez chaque point ci-dessous avant d'ouvrir la pharmacie.`;
    if (warn > 0) return `${warn} point${warn>1?'s':''} de vigilance détecté${warn>1?'s':''}. Aucune urgence immédiate, mais une attention dans la journée est recommandée.`;
    return 'Situation opérationnelle normale. Aucun risque critique détecté ce matin.';
  }
  if (section.id === 'opportunities') return `${cards.length} opportunité${cards.length>1?'s':''} de croissance identifiée${cards.length>1?'s':''} sur vos données des 30 derniers jours.`;
  if (section.id === 'actions') {
    const urgent = cards.filter(c => ['critical','warning'].includes(c.severity)).length;
    return urgent > 0
      ? `${urgent} action${urgent>1?'s':''} urgente${urgent>1?'s':''} à mener aujourd'hui, ${cards.length - urgent} recommandation${cards.length-urgent>1?'s':''} complémentaire${cards.length-urgent>1?'s':''}.`
      : `${cards.length} recommandation${cards.length>1?'s':''} pour optimiser vos opérations aujourd'hui.`;
  }
  if (section.id === 'forecasts') return 'Prévisions calculées sur la base de vos données historiques. Niveau de confiance indicatif — réévalué à chaque synchronisation.';
  if (section.id === 'insights') return `${cards.length} découverte${cards.length>1?'s':''} identifiée${cards.length>1?'s':''} par l'IA en analysant vos patterns de vente.`;
  return '';
}

function renderCard(card) {
  const sev      = card.severity === 'ok' ? 'ok' : card.severity;
  const badge    = SEV_LABELS[card.severity] || 'INFO';
  const actionHtml = card.action_label && card.action_target
    ? `<a href="${card.action_target}" class="card-action-btn">${card.action_label} ${ARROW}</a>`
    : '';

  return `
  <div class="brief-card sev-${sev}">
    <div class="card-head">
      <span class="sev-badge sev-${sev}">${badge}</span>
      <span class="conf-label">Confiance : ${card.confidence}%</span>
    </div>
    <div class="card-headline">${card.headline}</div>
    <div class="card-body">
      <div>
        <div class="card-section-label">Pourquoi</div>
        <div class="card-section-text">${card.explanation}</div>
      </div>
      <div class="card-impact-box">
        ${ZAP}
        <div>
          <div class="card-section-label" style="margin-bottom:3px">Impact business</div>
          <div class="card-section-text">${card.impact}</div>
        </div>
      </div>
      <div>
        <div class="card-section-label">Action recommandée</div>
        <div class="card-rec-text">${card.recommendation}</div>
      </div>
    </div>
    <div class="card-foot">
      <div class="conf-bar-wrap">
        <div class="conf-bar sev-${sev}" style="width:${card.confidence}%"></div>
      </div>
      <span class="conf-pct">${card.confidence}%</span>
      ${actionHtml}
    </div>
  </div>`;
}

function renderPanel(section) {
  const intro = sectionIntro(section);
  const cards = (section.cards || []).map(renderCard).join('');
  return `
    <div class="tab-panel${section.id === activeTab ? ' active' : ''}" id="panel-${section.id}">
      <div class="section-intro">
        ${INFO}
        ${intro}
      </div>
      <div class="brief-cards">${cards}</div>
    </div>`;
}

function buildTabBar(sections) {
  const bar = document.getElementById('tabBar');
  bar.innerHTML = sections.map(sec => {
    const tabMeta = TABS.find(t => t.id === sec.id) || {};
    const cards   = sec.cards || [];
    const crit    = cards.filter(c => c.severity === 'critical').length;
    const warn    = cards.filter(c => c.severity === 'warning').length;
    const dotHtml = crit > 0 ? '<span class="tab-dot critical"></span>'
                  : warn > 0 ? '<span class="tab-dot warning"></span>' : '';
    const isActive = sec.id === activeTab;
    return `
      <button class="tab-btn ${tabMeta.cls || ''}${isActive ? ' active' : ''}"
              onclick="switchTab('${sec.id}')">
        ${tabMeta.icon || ''}
        ${tabMeta.label || sec.title}
        <span class="tab-badge">${cards.length}</span>
        ${dotHtml}
      </button>`;
  }).join('');
}

function switchTab(id) {
  if (!briefData) return;
  activeTab = id;
  // Update tab buttons
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.classList.remove('active');
    if (btn.getAttribute('onclick') === `switchTab('${id}')`) btn.classList.add('active');
  });
  // Update panels
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  const target = document.getElementById(`panel-${id}`);
  if (target) target.classList.add('active');
}

async function loadBrief() {
  document.getElementById('aiDot').classList.remove('online');
  document.getElementById('aiStatus').textContent = 'Analyse en cours…';

  let data;
  try {
    const r = await fetch('/analytics/api.php?type=brief');
    data = await r.json();
  } catch(e) {
    document.getElementById('tabPanels').innerHTML =
      '<div class="tab-panel active"><div class="err-banner">Service IA indisponible. Vérifiez que le serveur Python est en ligne.</div></div>';
    document.getElementById('aiStatus').textContent = 'Service indisponible';
    return;
  }

  if (!data || data.available === false) {
    document.getElementById('tabPanels').innerHTML =
      `<div class="tab-panel active"><div class="err-banner">${data?.error || 'Erreur inconnue du service IA.'}</div></div>`;
    document.getElementById('aiStatus').textContent = 'Erreur';
    return;
  }

  briefData = data;
  document.getElementById('aiDot').classList.add('online');
  document.getElementById('aiStatus').textContent = 'DigiPharm AI · en ligne';

  // Greeting
  const rows = (data.data_rows || 0).toLocaleString('fr');
  const inv  = (data.inventory_count || 0).toLocaleString('fr');
  const genAt = data.generated_at
    ? new Date(data.generated_at).toLocaleTimeString('fr', { hour:'2-digit', minute:'2-digit' })
    : '';
  document.getElementById('greetingSummary').innerHTML =
    `Cette nuit, j'ai analysé <strong>${rows} transactions</strong> et <strong>${inv} articles</strong> en stock.
     Voici votre briefing du jour — naviguez par onglet.`;
  document.getElementById('greetingGen').textContent = genAt ? `Généré à ${genAt}` : '';

  // Pick active tab: first section with a critical card, else first section
  const sections = data.sections || [];
  const critSection = sections.find(s => (s.cards||[]).some(c => c.severity === 'critical'));
  activeTab = critSection ? critSection.id : (sections[0]?.id || 'risks');

  // Render tabs + panels
  if (sections.length) {
    buildTabBar(sections);
    document.getElementById('tabPanels').innerHTML = sections.map(renderPanel).join('');
  } else {
    document.getElementById('tabPanels').innerHTML =
      '<div class="tab-panel active"><div class="err-banner">Aucune donnée disponible. Lancez une synchronisation.</div></div>';
  }
}

loadBrief();
</script>
</body>
</html>
