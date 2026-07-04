<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
ai_check_auth();
$user = ai_user();
$activePage = 'sync';
$pageTitle  = 'Synchronisation';

$db = analytics_db();
$pid = $user['pharmacy_id'];

// Load source info
$src = $db->prepare("SELECT * FROM ai_data_sources WHERE pharmacy_id = ? LIMIT 1");
$src->execute([$pid]);
$source = $src->fetch() ?: [];

// Load ETL run history
$runs = $db->prepare("SELECT * FROM ai_etl_runs WHERE pharmacy_id = ? ORDER BY run_at DESC LIMIT 20");
$runs->execute([$pid]);
$history = $runs->fetchAll();

// Data stats
$stats = $db->prepare("SELECT COUNT(*) AS sales_rows, MIN(sale_date) AS first_date, MAX(sale_date) AS last_date FROM ai_sales WHERE pharmacy_id = ?");
$stats->execute([$pid]);
$stat = $stats->fetch() ?: [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>digiMind — Synchronisation</title>
<style>
<?php include __DIR__ . '/includes/common.css.php'; ?>
.source-card { background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:20px; }
.source-card h3 { font-size:14px;font-weight:700;color:var(--text);margin-bottom:14px; }
.source-info { display:grid;grid-template-columns:repeat(3,1fr);gap:14px; }
.source-field { font-size:12.5px; }
.source-field .label { color:var(--text-3);font-weight:600;text-transform:uppercase;letter-spacing:.4px;font-size:11px;margin-bottom:3px; }
.source-field .val { color:var(--text);font-weight:500; }
.run-row { display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-lt); }
.run-row:last-child { border-bottom:none; }
.run-dot { width:8px;height:8px;border-radius:50%;flex-shrink:0; }
.run-dot.success { background:#22c55e; }
.run-dot.failed  { background:var(--red); }
.run-dot.partial { background:var(--amber); }
.run-date { font-size:13px;color:var(--text);font-weight:500;min-width:140px; }
.run-rows { font-size:13px;color:var(--text-2);min-width:100px; }
.run-dur  { font-size:12px;color:var(--text-3);min-width:70px; }
.run-err  { font-size:12px;color:var(--red);flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.sync-actions { display:flex;gap:10px;align-items:center;margin-bottom:20px; }
.btn-danger { background:#fee2e2;color:var(--red);border:1px solid #fecaca;padding:9px 18px;border-radius:8px;font-size:13.5px;font-weight:600;cursor:pointer; }
#syncMsg { font-size:13px;color:var(--text-2); }
</style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <div class="topbar-title"><?= $pageTitle ?></div>
      <div class="topbar-meta">
        <?php if (!empty($source['last_synced_at'])): ?>
          Dernier sync : <?= date('d/m/Y H:i', strtotime($source['last_synced_at'])) ?>
        <?php else: ?>
          Aucune synchronisation effectuée
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="content">

    <!-- Data stats -->
    <div class="kpi-row">
      <div class="kpi">
        <div class="kpi-label">Lignes de vente</div>
        <div class="kpi-value"><?= number_format($stat['sales_rows'] ?? 0) ?></div>
      </div>
      <div class="kpi">
        <div class="kpi-label">Première vente</div>
        <div class="kpi-value" style="font-size:18px"><?= $stat['first_date'] ? date('d/m/Y', strtotime($stat['first_date'])) : '—' ?></div>
      </div>
      <div class="kpi">
        <div class="kpi-label">Dernière vente</div>
        <div class="kpi-value" style="font-size:18px"><?= $stat['last_date'] ? date('d/m/Y', strtotime($stat['last_date'])) : '—' ?></div>
      </div>
      <div class="kpi">
        <div class="kpi-label">Syncs effectués</div>
        <div class="kpi-value"><?= count($history) ?></div>
      </div>
    </div>

    <!-- Source info -->
    <?php if (!empty($source)): ?>
    <div class="source-card">
      <h3>Source configurée</h3>
      <div class="source-info">
        <div class="source-field">
          <div class="label">Mode</div>
          <div class="val"><?= $source['conn_type'] === 'ssh' ? 'Tunnel SSH' : 'TCP direct' ?></div>
        </div>
        <?php if ($source['conn_type'] === 'ssh'): ?>
        <div class="source-field">
          <div class="label">Serveur SSH</div>
          <div class="val"><?= htmlspecialchars($source['ssh_host']) ?>:<?= $source['ssh_port'] ?></div>
        </div>
        <?php endif; ?>
        <div class="source-field">
          <div class="label">Base de données</div>
          <div class="val"><?= htmlspecialchars($source['db_name']) ?></div>
        </div>
        <div class="source-field">
          <div class="label">Dernier test</div>
          <div class="val">
            <?php if ($source['last_tested_at']): ?>
              <?= $source['last_test_ok'] ? '✓ OK' : '✗ Échec' ?>
              — <?= date('d/m/Y H:i', strtotime($source['last_tested_at'])) ?>
            <?php else: ?>—<?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="notice error" style="margin-bottom:20px">
      Aucune source configurée. <a href="/analytics/settings.php" style="color:inherit;font-weight:600">Configurer →</a>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="sync-actions">
      <button class="btn btn-sync" onclick="syncNow(false)">↻ Synchroniser (incrémental)</button>
      <button class="btn-danger" onclick="syncNow(true)">↺ Sync complet (tout reimporter)</button>
      <span id="syncMsg"></span>
    </div>

    <!-- History -->
    <div class="card">
      <div class="card-header">
        <span class="card-title">Historique des synchronisations</span>
        <span class="card-meta"><?= count($history) ?> dernières exécutions</span>
      </div>
      <div class="card-body" style="padding:0 18px">
        <?php if (empty($history)): ?>
          <p style="padding:20px 0;color:var(--text-3);font-size:13px">Aucun historique</p>
        <?php else: ?>
          <?php foreach ($history as $r): ?>
          <div class="run-row">
            <div class="run-dot <?= $r['status'] ?>"></div>
            <div class="run-date"><?= date('d/m/Y H:i', strtotime($r['run_at'])) ?></div>
            <div class="run-rows"><?= number_format($r['rows_synced']) ?> lignes</div>
            <div class="run-dur"><?= $r['duration_sec'] ?>s</div>
            <?php if ($r['status'] === 'failed' && $r['error_message']): ?>
              <div class="run-err"><?= htmlspecialchars(substr($r['error_message'], 0, 120)) ?></div>
            <?php else: ?>
              <span class="severity-badge <?= $r['status'] === 'success' ? 'info' : 'warning' ?>"><?= $r['status'] ?></span>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>
<script>
async function syncNow(full) {
  const msg = document.getElementById('syncMsg');
  msg.textContent = 'Synchronisation en cours…';
  try {
    const url = `/analytics/api.php?type=etl_sync${full ? '&full=1' : ''}`;
    const r = await fetch(url);
    const d = await r.json();
    msg.textContent = d.ok ? (d.message || 'Terminé') : ('Erreur : ' + (d.error || '?'));
    if (d.ok) setTimeout(() => location.reload(), 2000);
  } catch(e) {
    msg.textContent = 'Erreur réseau';
  }
}
</script>
</body></html>
