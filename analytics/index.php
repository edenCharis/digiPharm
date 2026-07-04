<?php
require_once __DIR__ . '/config/auth.php';
ai_check_auth();
$user = ai_user();
$initials = strtoupper(substr($user['display_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DigiPharm AI — <?= htmlspecialchars($user['pharmacy_name']) ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --green:       #1a7f4b;
  --green-dk:    #155e38;
  --green-lt:    #e8f5ee;
  --green-bg:    #f0faf4;
  --amber:       #d97706;
  --amber-lt:    #fef3c7;
  --red:         #dc2626;
  --red-lt:      #fee2e2;
  --blue:        #2563eb;
  --blue-lt:     #dbeafe;
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

/* ── Sidebar ─────────────────────────────────────────────── */
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
.nav-link:hover { background: var(--surface-alt); color: var(--text); }
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
.avatar-role { font-size: 11px; color: var(--text-3); }
.logout-btn { color: var(--text-3); text-decoration: none; font-size: 11px; }
.logout-btn:hover { color: var(--red); }

/* ── Main ────────────────────────────────────────────────── */
.main {
  margin-left: var(--sidebar-w);
  flex: 1;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

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
.topbar-title { font-size: 16px; font-weight: 700; color: var(--text); }
.topbar-meta { font-size: 12px; color: var(--text-3); }

.topbar-right { display: flex; align-items: center; gap: 12px; }
.period-select {
  padding: 6px 10px;
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 13px;
  color: var(--text);
  background: var(--surface);
  cursor: pointer;
}
.refresh-btn {
  padding: 6px 10px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  cursor: pointer;
  color: var(--text-2);
  font-size: 13px;
  display: flex;
  align-items: center;
  gap: 5px;
}
.refresh-btn svg { width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* ── Content ─────────────────────────────────────────────── */
.content { padding: 24px 28px; flex: 1; }

/* Insight banner */
.insight-banner {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 16px 20px;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 12px;
}
.insight-icon {
  width: 36px; height: 36px;
  border-radius: 8px;
  display: grid; place-items: center;
  flex-shrink: 0;
}
.insight-icon.ok   { background: var(--green-lt); }
.insight-icon.warn { background: var(--amber-lt); }
.insight-icon.crit { background: var(--red-lt); }
.insight-icon svg { width: 18px; height: 18px; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; fill: none; }
.insight-icon.ok   svg { stroke: var(--green); }
.insight-icon.warn svg { stroke: var(--amber); }
.insight-icon.crit svg { stroke: var(--red); }
.insight-text { font-size: 14px; font-weight: 500; color: var(--text); }
.insight-meta { font-size: 12px; color: var(--text-3); margin-top: 2px; }

/* KPI row */
.kpi-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-bottom: 20px;
}
.kpi {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 16px 18px;
}
.kpi-label { font-size: 11.5px; font-weight: 600; color: var(--text-3); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 8px; }
.kpi-value { font-size: 26px; font-weight: 700; color: var(--text); font-variant-numeric: tabular-nums; line-height: 1; }
.kpi-value.red  { color: var(--red); }
.kpi-value.amber { color: var(--amber); }
.kpi-value.green { color: var(--green); }
.kpi-sub { font-size: 12px; color: var(--text-3); margin-top: 4px; }
.kpi-badge {
  display: inline-block;
  padding: 2px 7px;
  border-radius: 99px;
  font-size: 11px;
  font-weight: 600;
  margin-top: 6px;
}
.kpi-badge.up   { background: var(--green-lt); color: var(--green-dk); }
.kpi-badge.down { background: var(--red-lt);   color: var(--red); }
.kpi-badge.flat { background: var(--border-lt); color: var(--text-3); }

/* Two-column layout */
.grid-2 {
  display: grid;
  grid-template-columns: 1fr 360px;
  gap: 16px;
  margin-bottom: 16px;
}

/* Card */
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
}
.card-header {
  padding: 14px 18px;
  border-bottom: 1px solid var(--border-lt);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.card-title { font-size: 14px; font-weight: 700; color: var(--text); }
.card-meta  { font-size: 12px; color: var(--text-3); }
.card-body  { padding: 18px; }

/* Chart area */
.chart-area { height: 220px; position: relative; }
.chart-canvas { width: 100%; height: 100%; }

/* Alerts list */
.alert-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid var(--border-lt);
}
.alert-item:last-child { border-bottom: none; }
.alert-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  margin-top: 5px;
  flex-shrink: 0;
}
.alert-dot.critical { background: var(--red); }
.alert-dot.warning  { background: var(--amber); }
.alert-dot.info     { background: var(--blue); }
.alert-name { font-size: 13px; font-weight: 500; color: var(--text); }
.alert-msg  { font-size: 12px; color: var(--text-2); }

/* Top products table */
.tbl { width: 100%; border-collapse: collapse; font-size: 13px; }
.tbl th { text-align: left; padding: 8px 12px; font-size: 11px; font-weight: 700; color: var(--text-3); text-transform: uppercase; letter-spacing: .4px; border-bottom: 1px solid var(--border); }
.tbl td { padding: 10px 12px; border-bottom: 1px solid var(--border-lt); color: var(--text-2); }
.tbl tr:last-child td { border-bottom: none; }
.tbl td:first-child { font-weight: 500; color: var(--text); }
.tbl td.num { text-align: right; font-variant-numeric: tabular-nums; }

/* Loading state */
.skeleton { background: linear-gradient(90deg, #f0f0f0 25%, #e8e8e8 50%, #f0f0f0 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite; border-radius: 6px; height: 20px; }
@keyframes shimmer { 0% { background-position: 200% 0 } 100% { background-position: -200% 0 } }

.status-dot {
  display: inline-block; width: 7px; height: 7px;
  border-radius: 50%; background: var(--text-3); margin-right: 5px;
  animation: pulse 2s infinite;
}
.status-dot.online { background: var(--green); }
@keyframes pulse { 0%,100% { opacity:1 } 50% { opacity:.4 } }

@media (max-width: 1100px) {
  .kpi-row { grid-template-columns: repeat(2,1fr); }
  .grid-2  { grid-template-columns: 1fr; }
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
    <div>
      <div class="topbar-title">Vue d'ensemble</div>
      <div class="topbar-meta">
        <span class="status-dot" id="aiDot"></span>
        <span id="aiStatus">Chargement…</span>
      </div>
    </div>
    <div class="topbar-right">
      <select class="period-select" id="periodSelect" onchange="loadAll()">
        <option value="7">7 jours</option>
        <option value="30" selected>30 jours</option>
        <option value="90">90 jours</option>
        <option value="180">6 mois</option>
      </select>
      <button class="refresh-btn" onclick="loadAll()">
        <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3"/></svg>
        Actualiser
      </button>
    </div>
  </div>

  <div class="content">

    <!-- Insight banner -->
    <div class="insight-banner" id="insightBanner">
      <div class="insight-icon ok" id="insightIconWrap">
        <svg viewBox="0 0 24 24" id="insightIcon"><polyline points="20 6 9 17 4 12"/></svg>
      </div>
      <div>
        <div class="insight-text" id="insightText"><span class="skeleton" style="width:340px;display:block"></span></div>
        <div class="insight-meta" id="insightMeta"></div>
      </div>
    </div>

    <!-- KPIs -->
    <div class="kpi-row">
      <div class="kpi">
        <div class="kpi-label">Chiffre d'affaires</div>
        <div class="kpi-value" id="kpiRevenue"><span class="skeleton" style="width:100px"></span></div>
        <div class="kpi-sub" id="kpiRevSub"></div>
        <div id="kpiRevBadge"></div>
      </div>
      <div class="kpi">
        <div class="kpi-label">Transactions</div>
        <div class="kpi-value" id="kpiTx"><span class="skeleton" style="width:60px"></span></div>
        <div class="kpi-sub" id="kpiTxSub"></div>
      </div>
      <div class="kpi">
        <div class="kpi-label">Panier moyen</div>
        <div class="kpi-value" id="kpiBasket"><span class="skeleton" style="width:80px"></span></div>
        <div class="kpi-sub">XAF / vente</div>
      </div>
      <div class="kpi">
        <div class="kpi-label">Alertes actives</div>
        <div class="kpi-value" id="kpiAlerts"><span class="skeleton" style="width:40px"></span></div>
        <div class="kpi-sub" id="kpiAlertSub"></div>
      </div>
    </div>

    <!-- Chart + Alerts -->
    <div class="grid-2">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Évolution du chiffre d'affaires</span>
          <span class="card-meta" id="chartMeta"></span>
        </div>
        <div class="card-body">
          <div class="chart-area">
            <canvas id="revenueChart" class="chart-canvas"></canvas>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <span class="card-title">Alertes</span>
          <span class="card-meta" id="alertCount"></span>
        </div>
        <div class="card-body" id="alertsList" style="padding:0 18px">
          <div style="padding:18px 0"><span class="skeleton" style="display:block;margin-bottom:10px"></span><span class="skeleton" style="display:block;margin-bottom:10px"></span><span class="skeleton" style="display:block;width:60%"></span></div>
        </div>
      </div>
    </div>

    <!-- Top products -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Top produits</span>
        <span class="card-meta" id="topMeta"></span>
      </div>
      <div class="card-body" style="padding:0">
        <div style="overflow-x:auto">
          <table class="tbl">
            <thead>
              <tr>
                <th>#</th>
                <th>Produit</th>
                <th>Catégorie</th>
                <th class="num">Qté vendue</th>
                <th class="num">Chiffre d'affaires (XAF)</th>
              </tr>
            </thead>
            <tbody id="topTable">
              <tr><td colspan="5"><span class="skeleton" style="display:block;margin:12px 0"></span></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<script>
// ── Mini chart (no external lib dependency) ───────────────────────────────
function drawLineChart(canvasId, labels, values, color) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const dpr = window.devicePixelRatio || 1;
  canvas.width  = canvas.offsetWidth  * dpr;
  canvas.height = canvas.offsetHeight * dpr;
  ctx.scale(dpr, dpr);

  const W = canvas.offsetWidth, H = canvas.offsetHeight;
  const pad = { t: 12, r: 12, b: 28, l: 64 };
  const cW = W - pad.l - pad.r;
  const cH = H - pad.t - pad.b;

  const max = Math.max(...values) * 1.1 || 1;
  const min = 0;

  // Grid lines
  ctx.strokeStyle = '#f0f0f0';
  ctx.lineWidth = 1;
  [0.25, 0.5, 0.75, 1].forEach(pct => {
    const y = pad.t + cH * (1 - pct);
    ctx.beginPath(); ctx.moveTo(pad.l, y); ctx.lineTo(W - pad.r, y); ctx.stroke();
    ctx.fillStyle = '#9ca3af';
    ctx.font = '10px system-ui';
    ctx.textAlign = 'right';
    ctx.fillText(fmt(max * pct), pad.l - 6, y + 3);
  });

  // X labels — show every N-th
  const step = Math.ceil(labels.length / 6);
  ctx.fillStyle = '#9ca3af';
  ctx.font = '10px system-ui';
  ctx.textAlign = 'center';
  labels.forEach((lbl, i) => {
    if (i % step !== 0) return;
    const x = pad.l + (i / (labels.length - 1 || 1)) * cW;
    const d = new Date(lbl);
    ctx.fillText(`${d.getDate()}/${d.getMonth()+1}`, x, H - pad.b + 14);
  });

  // Area fill
  const grad = ctx.createLinearGradient(0, pad.t, 0, pad.t + cH);
  grad.addColorStop(0, color + '33');
  grad.addColorStop(1, color + '00');
  ctx.beginPath();
  values.forEach((v, i) => {
    const x = pad.l + (i / (values.length - 1 || 1)) * cW;
    const y = pad.t + cH * (1 - (v - min) / (max - min));
    i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
  });
  ctx.lineTo(pad.l + cW, pad.t + cH);
  ctx.lineTo(pad.l, pad.t + cH);
  ctx.closePath();
  ctx.fillStyle = grad;
  ctx.fill();

  // Line
  ctx.beginPath();
  ctx.strokeStyle = color;
  ctx.lineWidth = 2.5;
  ctx.lineJoin = 'round';
  values.forEach((v, i) => {
    const x = pad.l + (i / (values.length - 1 || 1)) * cW;
    const y = pad.t + cH * (1 - (v - min) / (max - min));
    i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
  });
  ctx.stroke();

  // Last point dot
  if (values.length > 0) {
    const li = values.length - 1;
    const lx = pad.l + cW;
    const ly = pad.t + cH * (1 - (values[li] - min) / (max - min));
    ctx.beginPath();
    ctx.arc(lx, ly, 4, 0, Math.PI * 2);
    ctx.fillStyle = color;
    ctx.fill();
    ctx.strokeStyle = '#fff';
    ctx.lineWidth = 2;
    ctx.stroke();
  }
}

function fmt(n) {
  if (n >= 1e6) return (n/1e6).toFixed(1)+'M';
  if (n >= 1e3) return (n/1e3).toFixed(0)+'k';
  return Math.round(n).toLocaleString('fr');
}

// ── Data loading ──────────────────────────────────────────────────────────
const base = '/analytics/api.php';

async function fetchJSON(type, extra='') {
  const days = document.getElementById('periodSelect').value;
  const res = await fetch(`${base}?type=${type}&days=${days}${extra}`);
  return res.json();
}

async function loadDashboard() {
  const d = await fetchJSON('dashboard');
  if (!d.available && d.available !== undefined) {
    document.getElementById('aiStatus').textContent = 'Service IA indisponible';
    return;
  }
  document.getElementById('aiDot').classList.add('online');
  document.getElementById('aiStatus').textContent = 'DigiPharm AI · en ligne';

  // Insight
  const iconWrap = document.getElementById('insightIconWrap');
  const iconEl   = document.getElementById('insightIcon');
  iconWrap.className = 'insight-icon ' + (d.alerts_critical > 0 ? 'crit' : d.alerts_warning > 0 ? 'warn' : 'ok');
  if (d.alerts_critical > 0) {
    iconEl.innerHTML = '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>';
  } else if (d.alerts_warning > 0) {
    iconEl.innerHTML = '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>';
  }
  document.getElementById('insightText').textContent = d.insight_text || '—';
  document.getElementById('insightMeta').textContent =
    `Qualité des données : ${d.model_quality} · ${d.data_rows?.toLocaleString('fr') || '?'} lignes`;

  // KPIs
  document.getElementById('kpiRevenue').textContent = fmt(d.total_revenue || 0) + ' XAF';
  document.getElementById('kpiRevSub').textContent  = `${document.getElementById('periodSelect').options[document.getElementById('periodSelect').selectedIndex].text}`;
  const gr = d.revenue_trend || 0;
  const badge = document.getElementById('kpiRevBadge');
  badge.innerHTML = `<span class="kpi-badge ${gr>0?'up':gr<0?'down':'flat'}">${gr>0?'▲':'▼'} ${Math.abs(gr)}%</span>`;

  document.getElementById('kpiTx').textContent  = (d.total_tx || 0).toLocaleString('fr');
  document.getElementById('kpiTxSub').textContent = 'transactions';

  document.getElementById('kpiBasket').textContent  = fmt(d.avg_basket || 0);

  const alertTotal = (d.alerts_critical||0) + (d.alerts_warning||0);
  const kpiAl = document.getElementById('kpiAlerts');
  kpiAl.textContent = alertTotal;
  kpiAl.className   = 'kpi-value ' + (d.alerts_critical>0?'red':d.alerts_warning>0?'amber':'green');
  document.getElementById('kpiAlertSub').textContent =
    `${d.alerts_critical||0} critique(s) · ${d.alerts_warning||0} avertissement(s)`;

  // Top products
  const body = document.getElementById('topTable');
  const days  = document.getElementById('periodSelect').value;
  document.getElementById('topMeta').textContent = `${days} derniers jours`;
  if (d.top_products && d.top_products.length) {
    body.innerHTML = d.top_products.map((p,i) => `
      <tr>
        <td>${i+1}</td>
        <td>${p.name||p.product_name}</td>
        <td>${p.category||'—'}</td>
        <td class="num">${(p.qty||0).toLocaleString('fr')}</td>
        <td class="num">${fmt(p.revenue||0)} XAF</td>
      </tr>
    `).join('');
  } else {
    body.innerHTML = '<tr><td colspan="5" style="color:#9ca3af;padding:16px 12px">Aucune donnée</td></tr>';
  }
}

async function loadTrends() {
  const d = await fetchJSON('trends');
  if (!d.series || !d.series.length) {
    document.getElementById('chartMeta').textContent = 'Aucune donnée';
    return;
  }
  const days = document.getElementById('periodSelect').value;
  document.getElementById('chartMeta').textContent = `${days} derniers jours`;
  drawLineChart(
    'revenueChart',
    d.series.map(r => r.date),
    d.series.map(r => r.revenue),
    '#1a7f4b'
  );
}

async function loadAlerts() {
  const d = await fetchJSON('alerts');
  const list = d.alerts || [];
  document.getElementById('alertCount').textContent = `${list.length} alerte(s)`;
  const container = document.getElementById('alertsList');
  if (!list.length) {
    container.innerHTML = '<p style="padding:14px 0;color:#9ca3af;font-size:13px">Aucune alerte active</p>';
    return;
  }
  container.innerHTML = list.slice(0,8).map(a => `
    <div class="alert-item">
      <div class="alert-dot ${a.severity}"></div>
      <div>
        <div class="alert-name">${a.product_name}</div>
        <div class="alert-msg">${a.message}</div>
      </div>
    </div>
  `).join('');
}

async function loadAll() {
  await Promise.all([loadDashboard(), loadTrends(), loadAlerts()]);
}

loadAll();
</script>
</body>
</html>
