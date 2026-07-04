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
<title>DigiPharm AI — Tendances</title>
<style>
<?php include __DIR__ . '/includes/common.css.php'; ?>
</style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main">
  <?php include __DIR__ . '/includes/topbar.php'; ?>
  <div class="content">

    <div class="kpi-row">
      <div class="kpi"><div class="kpi-label">Chiffre d'affaires</div><div class="kpi-value" id="kRevenue">—</div><div id="kRevBadge"></div></div>
      <div class="kpi"><div class="kpi-label">Transactions</div><div class="kpi-value" id="kTx">—</div></div>
      <div class="kpi"><div class="kpi-label">Panier moyen</div><div class="kpi-value" id="kBasket">—</div><div class="kpi-sub">XAF / vente</div></div>
      <div class="kpi"><div class="kpi-label">Croissance</div><div class="kpi-value" id="kGrowth">—</div><div class="kpi-sub">vs période précédente</div></div>
    </div>

    <div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <span class="card-title">Évolution du chiffre d'affaires</span>
        <span class="card-meta" id="chartMeta"></span>
      </div>
      <div class="card-body">
        <div style="height:260px;position:relative"><canvas id="revenueChart" style="width:100%;height:100%"></canvas></div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><span class="card-title">Top produits — période sélectionnée</span></div>
      <div class="card-body" style="padding:0">
        <div style="overflow-x:auto">
          <table class="tbl">
            <thead><tr><th>#</th><th>Produit</th><th class="num">Qté</th><th class="num">CA (XAF)</th></tr></thead>
            <tbody id="topBody"><tr><td colspan="4" class="tbl-empty">Chargement…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>
<script>
<?php include __DIR__ . '/includes/chart.js.php'; ?>

async function load() {
  const days = document.getElementById('periodSelect').value;
  document.getElementById('chartMeta').textContent = `${days} derniers jours`;
  const d = await fetchAI('trends');
  if (!d.series) return;

  const gr = d.growth_rate ?? 0;
  setText('kRevenue', fmt(d.total_revenue ?? 0) + ' XAF');
  setText('kTx', (d.total_transactions ?? 0).toLocaleString('fr'));
  setText('kBasket', fmt(d.avg_basket ?? 0));
  setText('kGrowth', (gr >= 0 ? '+' : '') + gr + '%');
  document.getElementById('kGrowth').className = 'kpi-value ' + (gr > 0 ? 'green' : gr < 0 ? 'red' : '');
  document.getElementById('kRevBadge').innerHTML =
    `<span class="kpi-badge ${gr>0?'up':gr<0?'down':'flat'}">${gr>=0?'▲':'▼'} ${Math.abs(gr)}%</span>`;

  drawLineChart('revenueChart', d.series.map(r=>r.date), d.series.map(r=>r.revenue), '#1a7f4b');

  const body = document.getElementById('topBody');
  if (d.top_products?.length) {
    body.innerHTML = d.top_products.map((p,i)=>`<tr>
      <td>${i+1}</td><td>${p.name||p.product_name}</td>
      <td class="num">${(p.qty||0).toLocaleString('fr')}</td>
      <td class="num">${fmt(p.revenue||0)} XAF</td>
    </tr>`).join('');
  } else {
    body.innerHTML = '<tr><td colspan="4" class="tbl-empty">Aucune donnée</td></tr>';
  }
}
load();
</script>
</body></html>
