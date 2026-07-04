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
  --green:       #1a7f4b;
  --green-dk:    #155e38;
  --green-lt:    #e8f5ee;
  --green-bg:    #f0faf4;
  --amber:       #d97706;
  --amber-lt:    #fef3c7;
  --amber-dk:    #92400e;
  --red:         #dc2626;
  --red-lt:      #fee2e2;
  --red-dk:      #7f1d1d;
  --blue:        #2563eb;
  --blue-lt:     #dbeafe;
  --blue-dk:     #1e3a8a;
  --border:      #dadce0;
  --border-lt:   #f0f0f0;
  --text:        #111827;
  --text-2:      #4b5563;
  --text-3:      #9ca3af;
  --surface:     #ffffff;
  --surface-alt: #f8f9fa;
  --bg:          #f3f4f6;
  --sidebar-w:   240px;
  --header-h:    56px;
  --radius:      10px;
  --shadow:      0 1px 4px rgba(0,0,0,.08);
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  display: flex;
}

/* ── Sidebar ──────────────────────────────────────────────── */
.sidebar {
  width: var(--sidebar-w);
  min-height: 100vh;
  background: var(--surface);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 100;
}
.sidebar-logo {
  padding: 18px 20px;
  display: flex;
  align-items: center;
  gap: 10px;
  border-bottom: 1px solid var(--border-lt);
}
.logo-icon {
  width: 32px; height: 32px;
  background: var(--green);
  border-radius: 7px;
  display: grid; place-items: center;
}
.logo-icon svg { width: 18px; height: 18px; stroke: #fff; fill: none; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
.logo-text { font-size: 15px; font-weight: 700; color: var(--text); letter-spacing: -.3px; }
.logo-text span { color: var(--green); }
.sidebar-pharmacy {
  padding: 12px 20px;
  font-size: 11px;
  font-weight: 600;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: .6px;
  border-bottom: 1px solid var(--border-lt);
}
nav { flex: 1; padding: 8px 0; }
.nav-section { padding: 16px 20px 4px; font-size: 10px; font-weight: 700; color: var(--text-3); text-transform: uppercase; letter-spacing: .6px; }
.nav-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 14px;
  margin: 1px 8px;
  border-radius: 8px;
  font-size: 13.5px;
  color: var(--text-2);
  text-decoration: none;
  transition: background .12s, color .12s;
}
.nav-link svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }
.nav-link:hover  { background: var(--surface-alt); color: var(--text); }
.nav-link.active { background: var(--green-lt); color: var(--green-dk); font-weight: 600; }
.sidebar-footer {
  padding: 14px 16px;
  border-top: 1px solid var(--border-lt);
  display: flex;
  align-items: center;
  gap: 10px;
}
.avatar {
  width: 32px; height: 32px;
  background: var(--green);
  border-radius: 50%;
  display: grid; place-items: center;
  font-size: 13px; font-weight: 700; color: #fff;
  flex-shrink: 0;
}
.avatar-info { flex: 1; min-width: 0; }
.avatar-name { font-size: 13px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.avatar-role  { font-size: 11px; color: var(--text-3); }
.logout-btn   { color: var(--text-3); text-decoration: none; font-size: 11px; }
.logout-btn:hover { color: var(--red); }

/* ── Main ─────────────────────────────────────────────────── */
.main {
  margin-left: var(--sidebar-w);
  flex: 1;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

/* ── Topbar ───────────────────────────────────────────────── */
.topbar {
  height: var(--header-h);
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: 0 28px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 50;
}
.topbar-left { display: flex; flex-direction: column; gap: 2px; }
.topbar-title { font-size: 16px; font-weight: 700; color: var(--text); }
.topbar-meta  { font-size: 12px; color: var(--text-3); display: flex; align-items: center; gap: 5px; }
.topbar-right { display: flex; align-items: center; gap: 10px; }
.status-dot {
  display: inline-block; width: 7px; height: 7px;
  border-radius: 50%; background: var(--text-3);
  animation: pulse 2s infinite;
}
.status-dot.online { background: var(--green); }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

.refresh-btn {
  padding: 6px 12px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  cursor: pointer;
  color: var(--text-2);
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 5px;
  transition: background .12s;
}
.refresh-btn:hover { background: var(--surface-alt); }
.refresh-btn svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* ── Content ──────────────────────────────────────────────── */
.content { padding: 24px 28px; flex: 1; }

/* ── Greeting banner ──────────────────────────────────────── */
.brief-greeting {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 20px 24px;
  margin-bottom: 28px;
  display: flex;
  align-items: flex-start;
  gap: 16px;
}
.brief-brain {
  font-size: 28px;
  line-height: 1;
  flex-shrink: 0;
  margin-top: 2px;
}
.brief-greeting-body { flex: 1; }
.brief-hello {
  font-size: 18px;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 6px;
  line-height: 1.3;
}
.brief-summary {
  font-size: 13.5px;
  color: var(--text-2);
  line-height: 1.6;
}
.brief-summary strong { color: var(--text); }
.brief-time {
  flex-shrink: 0;
  text-align: right;
}
.brief-date    { font-size: 12px; font-weight: 600; color: var(--text-3); text-transform: capitalize; margin-bottom: 4px; }
.brief-quality { font-size: 11px; color: var(--text-3); }

/* ── Sections ─────────────────────────────────────────────── */
.brief-section { margin-bottom: 28px; }

.section-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
}
.section-icon {
  width: 32px; height: 32px;
  border-radius: 8px;
  display: grid; place-items: center;
  flex-shrink: 0;
}
.section-icon svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
.section-icon.s-risks   { background: var(--red-lt);   color: var(--red); }
.section-icon.s-opp     { background: var(--green-lt); color: var(--green); }
.section-icon.s-actions { background: var(--amber-lt); color: var(--amber); }
.section-icon.s-fcast   { background: var(--blue-lt);  color: var(--blue); }
.section-icon.s-insight { background: #ede9fe;          color: #7c3aed; }

.section-title { font-size: 15px; font-weight: 700; color: var(--text); }
.section-count { font-size: 12px; color: var(--text-3); margin-left: auto; }

.section-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(420px, 1fr));
  gap: 14px;
}

/* ── Decision card ────────────────────────────────────────── */
.brief-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  border-left: 4px solid var(--border);
  padding: 18px 18px 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  transition: box-shadow .15s;
}
.brief-card:hover { box-shadow: 0 2px 12px rgba(0,0,0,.09); }

.brief-card.sev-critical { border-left-color: var(--red); }
.brief-card.sev-warning  { border-left-color: var(--amber); }
.brief-card.sev-info     { border-left-color: var(--blue); }
.brief-card.sev-ok       { border-left-color: var(--green); }

.card-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.sev-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 99px;
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: .5px;
  text-transform: uppercase;
}
.sev-badge.sev-critical { background: var(--red-lt);   color: var(--red-dk); }
.sev-badge.sev-warning  { background: var(--amber-lt); color: var(--amber-dk); }
.sev-badge.sev-info     { background: var(--blue-lt);  color: var(--blue-dk); }
.sev-badge.sev-ok       { background: var(--green-lt); color: var(--green-dk); }

.conf-chip {
  font-size: 11px;
  color: var(--text-3);
  white-space: nowrap;
}

.card-headline {
  font-size: 14.5px;
  font-weight: 700;
  color: var(--text);
  line-height: 1.35;
}

.card-explanation {
  font-size: 13px;
  color: var(--text-2);
  line-height: 1.55;
}

.card-impact {
  background: var(--surface-alt);
  border: 1px solid var(--border-lt);
  border-radius: 7px;
  padding: 9px 12px;
  font-size: 12.5px;
  color: var(--text-2);
  display: flex;
  align-items: flex-start;
  gap: 7px;
  line-height: 1.45;
}
.card-impact svg { width: 14px; height: 14px; stroke: var(--amber); fill: none; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; margin-top: 1px; }

.card-rec {
  font-size: 12.5px;
  color: var(--text-2);
  line-height: 1.5;
  padding-left: 14px;
  position: relative;
}
.card-rec::before {
  content: '→';
  position: absolute;
  left: 0;
  color: var(--text-3);
}

.card-footer {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 2px;
}
.conf-bar-wrap {
  flex: 1;
  height: 4px;
  background: var(--border-lt);
  border-radius: 2px;
  overflow: hidden;
}
.conf-bar {
  height: 100%;
  border-radius: 2px;
  transition: width .4s ease;
}
.conf-bar.sev-critical { background: var(--red); }
.conf-bar.sev-warning  { background: var(--amber); }
.conf-bar.sev-info     { background: var(--blue); }
.conf-bar.sev-ok       { background: var(--green); }

.card-action {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 5px 10px;
  border: 1px solid var(--border);
  border-radius: 7px;
  font-size: 12px;
  font-weight: 500;
  color: var(--text-2);
  text-decoration: none;
  background: var(--surface);
  white-space: nowrap;
  transition: all .12s;
  flex-shrink: 0;
}
.card-action:hover { background: var(--surface-alt); border-color: var(--text-3); color: var(--text); }
.card-action svg { width: 11px; height: 11px; stroke: currentColor; fill: none; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round; }

/* ── Skeleton / loading ───────────────────────────────────── */
.skeleton {
  background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.4s infinite;
  border-radius: 6px;
  display: block;
}
@keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

.error-banner {
  background: var(--red-lt);
  border: 1px solid #fca5a5;
  border-radius: var(--radius);
  padding: 16px 20px;
  color: var(--red-dk);
  font-size: 13.5px;
}

@media (max-width: 1100px) {
  .section-cards { grid-template-columns: 1fr; }
}
@media (max-width: 700px) {
  .brief-greeting { flex-direction: column; }
  .brief-time { text-align: left; }
}
</style>
</head>
<body>

<!-- ── Sidebar ──────────────────────────────────────────────────────────── -->
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
    <div class="avatar-info">
      <div class="avatar-name"><?= htmlspecialchars($user['display_name']) ?></div>
      <div class="avatar-role"><?= $user['role'] === 'admin' ? 'Administrateur' : 'Lecteur' ?></div>
    </div>
    <a href="/analytics/logout.php" class="logout-btn" title="Déconnexion">✕</a>
  </div>
</aside>

<!-- ── Main ─────────────────────────────────────────────────────────────── -->
<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <div class="topbar-title">Briefing quotidien</div>
      <div class="topbar-meta">
        <span class="status-dot" id="aiDot"></span>
        <span id="aiStatus">Chargement…</span>
      </div>
    </div>
    <div class="topbar-right">
      <button class="refresh-btn" onclick="loadBrief()">
        <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3"/></svg>
        Actualiser
      </button>
    </div>
  </div>

  <div class="content">

    <!-- Greeting -->
    <div class="brief-greeting" id="briefGreeting">
      <div class="brief-brain">🧠</div>
      <div class="brief-greeting-body">
        <div class="brief-hello">Bonjour, <strong><?= htmlspecialchars($user['pharmacy_name']) ?></strong></div>
        <div class="brief-summary" id="briefSummary">
          <span class="skeleton" style="width:480px;height:16px;margin-bottom:6px"></span>
          <span class="skeleton" style="width:320px;height:14px"></span>
        </div>
      </div>
      <div class="brief-time">
        <div class="brief-date"><?= $today ?></div>
        <div class="brief-quality" id="briefQuality"></div>
      </div>
    </div>

    <!-- Sections -->
    <div id="briefSections">
      <!-- Skeleton sections while loading -->
      <?php for ($s = 0; $s < 3; $s++): ?>
      <div class="brief-section">
        <div class="section-header">
          <span class="skeleton" style="width:32px;height:32px;border-radius:8px"></span>
          <span class="skeleton" style="width:140px;height:18px"></span>
        </div>
        <div class="section-cards">
          <?php for ($c = 0; $c < 2; $c++): ?>
          <div class="brief-card" style="border-left-color:var(--border-lt)">
            <span class="skeleton" style="width:80px;height:18px;border-radius:99px"></span>
            <span class="skeleton" style="width:90%;height:20px"></span>
            <span class="skeleton" style="width:100%;height:40px"></span>
            <span class="skeleton" style="width:100%;height:36px;border-radius:7px"></span>
          </div>
          <?php endfor; ?>
        </div>
      </div>
      <?php endfor; ?>
    </div>

  </div>
</div>

<script>
const SECTION_META = {
  risks:         { label: 'Risques',          cls: 's-risks',   icon: '<svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' },
  opportunities: { label: 'Opportunités',     cls: 's-opp',     icon: '<svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>' },
  actions:       { label: 'Actions du jour',  cls: 's-actions', icon: '<svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>' },
  forecasts:     { label: 'Prévisions',       cls: 's-fcast',   icon: '<svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>' },
  insights:      { label: 'Découvertes IA',   cls: 's-insight', icon: '<svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="2" x2="9" y2="4"/><line x1="15" y1="2" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="22"/><line x1="15" y1="20" x2="15" y2="22"/><line x1="20" y1="9" x2="22" y2="9"/><line x1="20" y1="14" x2="22" y2="14"/><line x1="2" y1="9" x2="4" y2="9"/><line x1="2" y1="14" x2="4" y2="14"/></svg>' },
};

const SEV_LABELS = { critical:'CRITIQUE', warning:'ATTENTION', info:'INFO', ok:'INFO' };
const ARROW_SVG  = '<svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
const ZAP_SVG    = '<svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';

function renderCard(card) {
  const sevClass = card.severity === 'ok' ? 'ok' : card.severity;
  const badgeLabel = SEV_LABELS[card.severity] || 'INFO';

  const actionHtml = card.action_label && card.action_target
    ? `<a href="${card.action_target}" class="card-action">${card.action_label} ${ARROW_SVG}</a>`
    : '';

  return `
    <div class="brief-card sev-${sevClass}">
      <div class="card-top">
        <span class="sev-badge sev-${sevClass}">${badgeLabel}</span>
        <span class="conf-chip">${card.confidence}% confiance</span>
      </div>
      <div class="card-headline">${card.headline}</div>
      <div class="card-explanation">${card.explanation}</div>
      <div class="card-impact">
        ${ZAP_SVG}
        <span>${card.impact}</span>
      </div>
      <div class="card-rec">${card.recommendation}</div>
      <div class="card-footer">
        <div class="conf-bar-wrap">
          <div class="conf-bar sev-${sevClass}" style="width:${card.confidence}%"></div>
        </div>
        ${actionHtml}
      </div>
    </div>`;
}

function renderSection(section) {
  const meta = SECTION_META[section.id] || { label: section.title, cls: 's-insight', icon: '' };
  const topSev = section.cards.length > 0 ? section.cards[0].severity : 'ok';
  const cards  = section.cards.map(renderCard).join('');
  const count  = section.cards.length > 1 ? `${section.cards.length} analyses` : `${section.cards.length} analyse`;

  return `
    <div class="brief-section" id="s-${section.id}">
      <div class="section-header">
        <div class="section-icon ${meta.cls}">${meta.icon}</div>
        <div class="section-title">${section.title}</div>
        <div class="section-count">${count}</div>
      </div>
      <div class="section-cards">${cards}</div>
    </div>`;
}

async function loadBrief() {
  const aiDot    = document.getElementById('aiDot');
  const aiStatus = document.getElementById('aiStatus');
  aiStatus.textContent = 'Analyse en cours…';
  aiDot.classList.remove('online');

  let data;
  try {
    const r = await fetch('/analytics/api.php?type=brief');
    data = await r.json();
  } catch (e) {
    document.getElementById('briefSections').innerHTML =
      '<div class="error-banner">Service IA indisponible — vérifiez que le serveur Python est en ligne.</div>';
    aiStatus.textContent = 'Service indisponible';
    return;
  }

  if (!data || data.available === false) {
    document.getElementById('briefSections').innerHTML =
      `<div class="error-banner">${data?.error || 'Erreur inconnue'}</div>`;
    aiStatus.textContent = 'Erreur';
    return;
  }

  aiDot.classList.add('online');
  aiStatus.textContent = 'DigiPharm AI · en ligne';

  // Greeting
  const rows = (data.data_rows || 0).toLocaleString('fr');
  const inv  = (data.inventory_count || 0).toLocaleString('fr');
  const genAt = data.generated_at
    ? new Date(data.generated_at).toLocaleTimeString('fr', { hour: '2-digit', minute: '2-digit' })
    : '';
  document.getElementById('briefSummary').innerHTML =
    `Cette nuit, j'ai analysé <strong>${rows} transactions</strong> et <strong>${inv} articles</strong> en stock. Voici ce qui requiert votre attention aujourd'hui.`;
  document.getElementById('briefQuality').textContent = genAt ? `Généré à ${genAt}` : '';

  // Sections
  if (data.sections && data.sections.length) {
    document.getElementById('briefSections').innerHTML =
      data.sections.map(renderSection).join('');
  } else {
    document.getElementById('briefSections').innerHTML =
      '<div class="error-banner">Aucune donnée disponible — lancez une synchronisation depuis la page Sync.</div>';
  }
}

loadBrief();
</script>
</body>
</html>
