<?php
require_once __DIR__ . '/config/auth.php';
ai_check_auth();
$user = ai_user();
$activePage = 'inventory';
$pageTitle  = 'Inventaire';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DigiPharm AI — Inventaire</title>
<style>
<?php include __DIR__ . '/includes/common.css.php'; ?>
.stock-bar-wrap { width:80px;height:6px;background:var(--border-lt);border-radius:3px;display:inline-block;vertical-align:middle; }
.stock-bar { height:100%;border-radius:3px; }
input.search-box { padding:7px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;width:260px;outline:none; }
input.search-box:focus { border-color:var(--green); }
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
      <input class="search-box" type="text" id="searchBox" placeholder="Rechercher un produit…" oninput="filterTable()">
      <button class="refresh-btn" onclick="load()">
        <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3"/></svg>
        Actualiser
      </button>
    </div>
  </div>
  <div class="content">
    <div class="kpi-row">
      <div class="kpi"><div class="kpi-label">Total produits</div><div class="kpi-value" id="kTotal">—</div></div>
      <div class="kpi"><div class="kpi-label">En rupture (&lt;3j)</div><div class="kpi-value red" id="kCritical">—</div></div>
      <div class="kpi"><div class="kpi-label">Stock faible (&lt;14j)</div><div class="kpi-value amber" id="kLow">—</div></div>
      <div class="kpi"><div class="kpi-label">Valeur stock</div><div class="kpi-value" id="kValue">—</div><div class="kpi-sub">XAF</div></div>
    </div>

    <div class="card">
      <div class="card-header">
        <span class="card-title">Stock actuel</span>
        <span class="card-meta" id="invMeta"></span>
      </div>
      <div class="card-body" style="padding:0">
        <div style="overflow-x:auto">
          <table class="tbl" id="invTable">
            <thead>
              <tr>
                <th>Produit</th>
                <th>Catégorie</th>
                <th class="num">Stock</th>
                <th class="num">J. restants</th>
                <th>Niveau</th>
                <th class="num">Prix achat</th>
                <th class="num">Prix vente</th>
                <th>Péremption</th>
              </tr>
            </thead>
            <tbody id="invBody">
              <tr><td colspan="8" class="tbl-empty">Chargement…</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
<?php include __DIR__ . '/includes/chart.js.php'; ?>

let allRows = [];

function stockColor(dos) {
  if (dos === null || dos > 30) return '#22c55e';
  if (dos > 14) return '#84cc16';
  if (dos > 7)  return '#f59e0b';
  if (dos > 3)  return '#f97316';
  return '#ef4444';
}

async function load() {
  const d = await fetchAI('inventory');
  const items = d.items || [];
  allRows = items;
  setText('kTotal', items.length.toLocaleString('fr'));
  setText('invMeta', `${items.length} produits`);

  let critical = 0, low = 0, value = 0;
  items.forEach(it => {
    const dos = it.dos ?? null;
    if (dos !== null && dos <= 3) critical++;
    else if (dos !== null && dos <= 14) low++;
    if (it.unit_cost) value += (it.stock_quantity || 0) * it.unit_cost;
  });
  setText('kCritical', critical);
  setText('kLow', low);
  setText('kValue', fmt(value));

  renderTable(items);
}

function renderTable(items) {
  const body = document.getElementById('invBody');
  if (!items.length) {
    body.innerHTML = '<tr><td colspan="8" class="tbl-empty">Aucun produit trouvé</td></tr>';
    return;
  }
  body.innerHTML = items.map(it => {
    const dos = it.dos ?? null;
    const color = stockColor(dos);
    const pct = dos === null ? 100 : Math.min(100, (dos / 30) * 100);
    const dosText = dos === null ? '—' : dos > 300 ? '>300j' : dos.toFixed(1) + 'j';
    const exp = it.expiry_date ? new Date(it.expiry_date).toLocaleDateString('fr') : '—';
    const today = new Date();
    const expClass = it.expiry_date && (new Date(it.expiry_date) - today) < 30*86400000 ? 'red' : '';
    return `<tr data-name="${(it.product_name||'').toLowerCase()}">
      <td>${it.product_name || '—'}</td>
      <td>${it.category || '—'}</td>
      <td class="num">${Number(it.stock_quantity||0).toLocaleString('fr')}</td>
      <td class="num" style="color:${color};font-weight:600">${dosText}</td>
      <td>
        <div class="stock-bar-wrap">
          <div class="stock-bar" style="width:${pct}%;background:${color}"></div>
        </div>
      </td>
      <td class="num">${it.unit_cost ? fmt(it.unit_cost)+' XAF' : '—'}</td>
      <td class="num">${it.unit_price ? fmt(it.unit_price)+' XAF' : '—'}</td>
      <td style="color:var(--${expClass||'text-2'})">${exp}</td>
    </tr>`;
  }).join('');
}

function filterTable() {
  const q = document.getElementById('searchBox').value.toLowerCase();
  const filtered = q ? allRows.filter(r => (r.product_name||'').toLowerCase().includes(q)) : allRows;
  renderTable(filtered);
}

load();
</script>
</body></html>
