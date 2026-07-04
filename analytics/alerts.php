<?php
require_once __DIR__ . '/config/auth.php';
ai_check_auth();
$user = ai_user();
$activePage = 'alerts';
$pageTitle  = 'Alertes';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>digiMind — Alertes</title>
<style>
<?php include __DIR__ . '/includes/common.css.php'; ?>
.alert-card { background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:14px 18px;display:flex;align-items:flex-start;gap:14px;margin-bottom:10px; }
.alert-card:last-child { margin-bottom:0; }
.alert-icon { width:34px;height:34px;border-radius:8px;display:grid;place-items:center;flex-shrink:0; }
.alert-icon.critical { background:var(--red-lt); }
.alert-icon.warning  { background:var(--amber-lt); }
.alert-icon.info     { background:var(--blue-lt); }
.alert-icon svg { width:16px;height:16px;fill:none;stroke-width:2.2;stroke-linecap:round;stroke-linejoin:round; }
.alert-icon.critical svg { stroke:var(--red); }
.alert-icon.warning  svg { stroke:var(--amber); }
.alert-icon.info     svg { stroke:var(--blue); }
.alert-body { flex:1; }
.alert-product { font-size:13.5px;font-weight:600;color:var(--text); }
.alert-message { font-size:13px;color:var(--text-2);margin-top:2px; }
.alert-type { font-size:11px;color:var(--text-3);margin-top:4px;text-transform:uppercase;letter-spacing:.4px; }
.filter-row { display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap; }
.filter-btn { padding:5px 12px;border:1px solid var(--border);border-radius:99px;font-size:12.5px;font-weight:500;cursor:pointer;background:var(--surface);color:var(--text-2);transition:all .12s; }
.filter-btn.active { background:var(--text);color:#fff;border-color:var(--text); }
.filter-btn.crit.active { background:var(--red);border-color:var(--red);color:#fff; }
.filter-btn.warn.active { background:var(--amber);border-color:var(--amber);color:#fff; }
.filter-btn.info.active { background:var(--blue);border-color:var(--blue);color:#fff; }
.empty-state { text-align:center;padding:40px 20px;color:var(--text-3); }
.empty-state svg { width:40px;height:40px;stroke:var(--border);fill:none;stroke-width:1.5;margin-bottom:12px; }
.empty-state p { font-size:14px; }
</style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <div class="topbar-title"><?= $pageTitle ?></div>
      <div class="topbar-meta"><span class="status-dot" id="aiDot"></span><span id="aiStatus">Chargement…</span></div>
    </div>
    <div class="topbar-right">
      <button class="refresh-btn" onclick="load()">
        <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3"/></svg>
        Actualiser
      </button>
    </div>
  </div>
  <div class="content">
    <div class="kpi-row">
      <div class="kpi"><div class="kpi-label">Critiques</div><div class="kpi-value red" id="kCrit">—</div></div>
      <div class="kpi"><div class="kpi-label">Avertissements</div><div class="kpi-value amber" id="kWarn">—</div></div>
      <div class="kpi"><div class="kpi-label">Informations</div><div class="kpi-value" id="kInfo">—</div></div>
      <div class="kpi"><div class="kpi-label">Total</div><div class="kpi-value" id="kTotal">—</div></div>
    </div>

    <div class="filter-row">
      <button class="filter-btn active" onclick="setFilter('all',this)">Toutes</button>
      <button class="filter-btn crit" onclick="setFilter('critical',this)">Critiques</button>
      <button class="filter-btn warn" onclick="setFilter('warning',this)">Avertissements</button>
      <button class="filter-btn info" onclick="setFilter('info',this)">Informations</button>
    </div>

    <div id="alertsContainer">
      <div style="color:var(--text-3);font-size:13px;padding:20px 0">Chargement…</div>
    </div>
  </div>
</div>
<script>
<?php include __DIR__ . '/includes/chart.js.php'; ?>

let allAlerts = [];
let currentFilter = 'all';

const iconMap = {
  stockout_risk: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
  expiry_risk:   '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
  slow_mover:    '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
};
const typeLabel = {
  stockout_risk: 'Risque de rupture',
  expiry_risk:   'Risque de péremption',
  slow_mover:    'Produit dormant',
};

async function load() {
  const d = await fetchAI('alerts');
  allAlerts = d.alerts || [];
  setText('kCrit',  allAlerts.filter(a=>a.severity==='critical').length);
  setText('kWarn',  allAlerts.filter(a=>a.severity==='warning').length);
  setText('kInfo',  allAlerts.filter(a=>a.severity==='info').length);
  setText('kTotal', allAlerts.length);
  render();
}

function setFilter(f, btn) {
  currentFilter = f;
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  render();
}

function render() {
  const filtered = currentFilter === 'all'
    ? allAlerts
    : allAlerts.filter(a => a.severity === currentFilter);

  const container = document.getElementById('alertsContainer');
  if (!filtered.length) {
    container.innerHTML = `<div class="empty-state">
      <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      <p>Aucune alerte${currentFilter !== 'all' ? ' dans cette catégorie' : ''}</p>
    </div>`;
    return;
  }
  container.innerHTML = filtered.map(a => `
    <div class="alert-card">
      <div class="alert-icon ${a.severity}">
        <svg viewBox="0 0 24 24">${iconMap[a.type] || iconMap.slow_mover}</svg>
      </div>
      <div class="alert-body">
        <div class="alert-product">${a.product_name}</div>
        <div class="alert-message">${a.message}</div>
        <div class="alert-type">${typeLabel[a.type] || a.type} · <span class="severity-badge ${a.severity}">${a.severity}</span></div>
      </div>
    </div>
  `).join('');
}

load();
</script>
</body></html>
