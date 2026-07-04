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
<title>digiMind — Inventaire</title>
<style>
<?php include __DIR__ . '/includes/common.css.php'; ?>
.stock-bar-wrap { width:80px;height:6px;background:var(--border-lt);border-radius:3px;display:inline-block;vertical-align:middle; }
.stock-bar { height:100%;border-radius:3px; }
input.search-box { padding:7px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;width:240px;outline:none; }
input.search-box:focus { border-color:var(--green); }

/* Pagination */
.pagination { display:flex;align-items:center;gap:4px;padding:14px 18px;border-top:1px solid var(--border-lt);justify-content:space-between; }
.pagination-info { font-size:12.5px;color:var(--text-3); }
.pagination-controls { display:flex;align-items:center;gap:4px; }
.pg-btn { min-width:32px;height:32px;border:1px solid var(--border);border-radius:6px;background:var(--surface);color:var(--text-2);font-size:13px;cursor:pointer;display:grid;place-items:center;padding:0 6px;transition:all .12s; }
.pg-btn:hover:not(:disabled) { background:var(--surface-alt);border-color:var(--text-3); }
.pg-btn.active { background:var(--green);color:#fff;border-color:var(--green);font-weight:600; }
.pg-btn:disabled { opacity:.35;cursor:default; }
.per-page-select { padding:5px 8px;border:1px solid var(--border);border-radius:6px;font-size:12.5px;color:var(--text-2);background:var(--surface);cursor:pointer; }
</style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <div class="topbar-left" style="flex-direction:row;align-items:center;gap:10px;">
      <button class="hamburger" onclick="openSidebar()" aria-label="Menu">
        <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div>
        <div class="topbar-title"><?= $pageTitle ?></div>
        <div class="topbar-meta"><span class="status-dot" id="aiDot"></span><span id="aiStatus">Chargement…</span></div>
      </div>
    </div>
    <div class="topbar-right">
      <input class="search-box" type="text" id="searchBox" placeholder="Rechercher un produit…" oninput="onSearch()">
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
        <span class="card-title">Stock actuel — ordre décroissant</span>
        <div style="display:flex;align-items:center;gap:8px">
          <span class="card-meta" id="invMeta"></span>
          <select class="per-page-select" id="perPageSelect" onchange="onPerPage()">
            <option value="25">25 / page</option>
            <option value="50" selected>50 / page</option>
            <option value="100">100 / page</option>
            <option value="200">200 / page</option>
          </select>
        </div>
      </div>
      <div class="card-body" style="padding:0">
        <div style="overflow-x:auto">
          <table class="tbl" id="invTable">
            <thead>
              <tr>
                <th>#</th>
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
              <tr><td colspan="9" class="tbl-empty">Chargement…</td></tr>
            </tbody>
          </table>
        </div>
        <div class="pagination">
          <span class="pagination-info" id="pgInfo"></span>
          <div class="pagination-controls" id="pgControls"></div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
<?php include __DIR__ . '/includes/chart.js.php'; ?>

let allRows    = [];
let filtered   = [];
let currentPage = 1;

function perPage() { return parseInt(document.getElementById('perPageSelect').value) || 50; }

function stockColor(dos) {
  if (dos === null || dos > 30) return '#22c55e';
  if (dos > 14) return '#84cc16';
  if (dos > 7)  return '#f59e0b';
  if (dos > 3)  return '#f97316';
  return '#ef4444';
}

async function load() {
  document.getElementById('invBody').innerHTML = '<tr><td colspan="9" class="tbl-empty">Chargement…</td></tr>';
  const d = await fetchAI('inventory');
  allRows = d.items || [];

  let critical = 0, low = 0, value = 0;
  allRows.forEach(it => {
    const dos = it.dos ?? null;
    if (dos !== null && dos <= 3) critical++;
    else if (dos !== null && dos <= 14) low++;
    if (it.unit_cost) value += (it.stock_quantity || 0) * it.unit_cost;
  });
  setText('kTotal', allRows.length.toLocaleString('fr'));
  setText('kCritical', critical);
  setText('kLow', low);
  setText('kValue', fmt(value));

  applyFilter();
}

function applyFilter() {
  const q = document.getElementById('searchBox').value.toLowerCase().trim();
  filtered = q ? allRows.filter(r => (r.product_name||'').toLowerCase().includes(q)) : allRows;
  currentPage = 1;
  render();
}

function onSearch() { applyFilter(); }
function onPerPage() { currentPage = 1; render(); }

function render() {
  const pp    = perPage();
  const total = filtered.length;
  const pages = Math.max(1, Math.ceil(total / pp));
  if (currentPage > pages) currentPage = pages;
  const start = (currentPage - 1) * pp;
  const slice = filtered.slice(start, start + pp);

  // Table rows
  const body = document.getElementById('invBody');
  if (!slice.length) {
    body.innerHTML = '<tr><td colspan="9" class="tbl-empty">Aucun produit trouvé</td></tr>';
  } else {
    body.innerHTML = slice.map((it, i) => {
      const dos     = it.dos ?? null;
      const color   = stockColor(dos);
      const pct     = dos === null ? 100 : Math.min(100, (dos / 30) * 100);
      const dosText = dos === null ? '—' : dos > 300 ? '>300j' : Number(dos).toFixed(1) + 'j';
      const exp     = it.expiry_date ? new Date(it.expiry_date + 'T00:00:00').toLocaleDateString('fr') : '—';
      const expClass = it.expiry_date && (new Date(it.expiry_date) - new Date()) < 30*86400000 ? 'red' : 'text-2';
      return `<tr>
        <td style="color:var(--text-3);font-size:12px">${start + i + 1}</td>
        <td>${it.product_name || '—'}</td>
        <td>${it.category || '—'}</td>
        <td class="num" style="font-weight:600">${Number(it.stock_quantity||0).toLocaleString('fr')}</td>
        <td class="num" style="color:${color};font-weight:600">${dosText}</td>
        <td>
          <div class="stock-bar-wrap">
            <div class="stock-bar" style="width:${pct}%;background:${color}"></div>
          </div>
        </td>
        <td class="num">${it.unit_cost ? fmt(it.unit_cost)+' XAF' : '—'}</td>
        <td class="num">${it.unit_price ? fmt(it.unit_price)+' XAF' : '—'}</td>
        <td style="color:var(--${expClass})">${exp}</td>
      </tr>`;
    }).join('');
  }

  // Info
  const from = total ? start + 1 : 0;
  const to   = Math.min(start + pp, total);
  setText('invMeta', `${total.toLocaleString('fr')} produits`);
  setText('pgInfo', `${from}–${to} sur ${total.toLocaleString('fr')}`);

  // Controls
  const ctrl = document.getElementById('pgControls');
  const maxBtns = 7;
  let html = `<button class="pg-btn" onclick="goPage(${currentPage-1})" ${currentPage===1?'disabled':''}>&#8249;</button>`;

  let startP = Math.max(1, currentPage - Math.floor(maxBtns/2));
  let endP   = Math.min(pages, startP + maxBtns - 1);
  if (endP - startP < maxBtns - 1) startP = Math.max(1, endP - maxBtns + 1);

  if (startP > 1) html += `<button class="pg-btn" onclick="goPage(1)">1</button>${startP>2?'<span style="padding:0 4px;color:var(--text-3)">…</span>':''}`;
  for (let p = startP; p <= endP; p++)
    html += `<button class="pg-btn${p===currentPage?' active':''}" onclick="goPage(${p})">${p}</button>`;
  if (endP < pages) html += `${endP<pages-1?'<span style="padding:0 4px;color:var(--text-3)">…</span>':''}<button class="pg-btn" onclick="goPage(${pages})">${pages}</button>`;
  html += `<button class="pg-btn" onclick="goPage(${currentPage+1})" ${currentPage===pages?'disabled':''}>&#8250;</button>`;
  ctrl.innerHTML = html;
}

function goPage(p) {
  const pages = Math.max(1, Math.ceil(filtered.length / perPage()));
  currentPage = Math.max(1, Math.min(p, pages));
  render();
  document.querySelector('.card').scrollIntoView({behavior:'smooth', block:'start'});
}

load();

function openSidebar()  { document.querySelector('.sidebar').classList.add('open'); document.getElementById('sidebarOverlay').classList.add('open'); }
function closeSidebar() { document.querySelector('.sidebar').classList.remove('open'); document.getElementById('sidebarOverlay').classList.remove('open'); }
</script>
</body></html>
