<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
ai_check_auth();
$user       = ai_user();
$activePage = 'suppliers';

// Load synced suppliers list for the create-order dropdown (used in orders.php)
$suppliersDb = analytics_db()->prepare(
    "SELECT source_supplier_id, name, contact FROM ai_suppliers WHERE pharmacy_id = ? ORDER BY name"
);
$suppliersDb->execute([$user['pharmacy_id']]);
$knownSuppliers = $suppliersDb->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Fournisseurs — digiMind</title>
<style>
<?php require_once __DIR__ . '/includes/common.css.php'; ?>
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px}
.page-title{font-size:22px;font-weight:700;color:var(--text)}
.page-sub{font-size:13px;color:var(--text-3);margin-top:2px}
.supplier-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;margin-bottom:32px}
.supplier-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:20px;position:relative}
.sup-header{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px}
.sup-name{font-size:15px;font-weight:700;color:var(--text);line-height:1.3}
.sup-meta{font-size:12px;color:var(--text-3);margin-top:3px}
.badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.badge-excellent{background:#dcfce7;color:#166534}
.badge-fiable{background:#dbeafe;color:#1e40af}
.badge-moyen{background:#fef9c3;color:#854d0e}
.badge-risque{background:#fee2e2;color:#991b1b}
.score-circle{display:flex;flex-direction:column;align-items:center;min-width:56px}
.score-val{font-size:28px;font-weight:800;line-height:1;color:var(--text)}
.score-lbl{font-size:10px;color:var(--text-3);margin-top:2px;text-align:center}
.dim-row{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.dim-label{font-size:12px;color:var(--text-3);width:120px;flex-shrink:0}
.dim-bar-wrap{flex:1;background:var(--border);border-radius:6px;height:7px;overflow:hidden}
.dim-bar{height:100%;border-radius:6px;transition:width .6s ease}
.dim-val{font-size:12px;font-weight:600;color:var(--text);width:30px;text-align:right}
.sup-stats{display:flex;gap:16px;margin-top:16px;padding-top:16px;border-top:1px solid var(--border)}
.stat-item{text-align:center;flex:1}
.stat-item .n{font-size:16px;font-weight:700;color:var(--text)}
.stat-item .l{font-size:11px;color:var(--text-3)}
.empty-state{text-align:center;padding:80px 24px;color:var(--text-3)}
.empty-state .icon{font-size:48px;margin-bottom:16px}
.empty-state p{font-size:14px;max-width:360px;margin:8px auto;line-height:1.6}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;border:none;cursor:pointer;text-decoration:none}
.btn-primary{background:#1a7f4b;color:#fff}
.btn-primary:hover{background:#155e38}
.section-title{font-size:13px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.06em;margin:28px 0 14px}

/* order link */
.order-link{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#1a7f4b;text-decoration:none;margin-top:12px;font-weight:600}
.order-link:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <button class="hamburger" onclick="openSidebar()" aria-label="Menu">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>
    <div class="topbar-title">Fournisseurs</div>
  </div>

  <div class="content">
    <div class="page-header">
      <div>
        <div class="page-title">Score de fiabilité fournisseurs</div>
        <div class="page-sub">Basé sur les livraisons historiques synchronisées depuis votre ERP</div>
      </div>
      <a href="/analytics/orders.php" class="btn btn-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouvelle commande
      </a>
    </div>

    <!-- Loading state -->
    <div id="loadingState" style="text-align:center;padding:60px;color:var(--text-3)">
      <div style="font-size:14px">Chargement des scores…</div>
    </div>

    <!-- Supplier grid (populated by JS) -->
    <div id="supplierGrid" class="supplier-grid" style="display:none"></div>

    <!-- Empty state -->
    <div id="emptyState" class="empty-state" style="display:none">
      <div class="icon">🏭</div>
      <div style="font-size:20px;font-weight:700;color:var(--text);margin-bottom:8px">Aucune donnée fournisseur</div>
      <p>Les scores apparaîtront après la prochaine synchronisation ETL — les données de livraison seront extraites de votre ERP et analysées automatiquement.</p>
      <a href="/analytics/sync.php" class="btn btn-primary" style="margin-top:20px">Lancer la synchronisation</a>
    </div>

    <!-- Known suppliers from DB (even if no delivery history yet) -->
    <?php if ($knownSuppliers): ?>
    <div id="knownSuppliersSection" style="display:none">
      <div class="section-title">Fournisseurs connus (<?= count($knownSuppliers) ?>)</div>
      <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden">
        <table style="width:100%;border-collapse:collapse">
          <thead><tr style="border-bottom:1px solid var(--border)">
            <th style="padding:12px 16px;text-align:left;font-size:12px;color:var(--text-3);font-weight:600">Nom</th>
            <th style="padding:12px 16px;text-align:left;font-size:12px;color:var(--text-3);font-weight:600">Contact</th>
            <th style="padding:12px 16px;text-align:right;font-size:12px;color:var(--text-3);font-weight:600"></th>
          </tr></thead>
          <tbody>
          <?php foreach ($knownSuppliers as $sup): ?>
          <tr style="border-bottom:1px solid var(--border)">
            <td style="padding:12px 16px;font-size:14px;font-weight:600;color:var(--text)"><?= htmlspecialchars($sup['name']) ?></td>
            <td style="padding:12px 16px;font-size:13px;color:var(--text-3)"><?= htmlspecialchars($sup['contact'] ?? '—') ?></td>
            <td style="padding:12px 16px;text-align:right">
              <a href="/analytics/orders.php?supplier=<?= urlencode($sup['name']) ?>" style="font-size:12px;color:#1a7f4b;font-weight:600;text-decoration:none">Commander →</a>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
const DIM_COLORS = { validation: '#1a7f4b', completion: '#2563eb', regularity: '#7c3aed' };
const BADGE_CLASS = { Excellent: 'badge-excellent', Fiable: 'badge-fiable', Moyen: 'badge-moyen', Risqué: 'badge-risque' };

function scoreColor(s) {
  if (s >= 85) return '#1a7f4b';
  if (s >= 70) return '#2563eb';
  if (s >= 55) return '#d97706';
  return '#dc2626';
}

function renderCard(sup) {
  const badge = `<span class="badge ${BADGE_CLASS[sup.label] || 'badge-moyen'}">${sup.label}</span>`;
  const leadText = sup.avg_lead_days != null ? `${sup.avg_lead_days}j délai moy.` : 'Délai inconnu';
  const valCFA = sup.total_value_cfa > 0
    ? (sup.total_value_cfa >= 1e6 ? `${(sup.total_value_cfa/1e6).toFixed(1)}M CFA` : `${Math.round(sup.total_value_cfa/1000)}k CFA`)
    : '—';

  return `<div class="supplier-card">
    <div class="sup-header">
      <div>
        <div class="sup-name">${sup.supplier_name}</div>
        <div class="sup-meta">${sup.delivery_count} livraison${sup.delivery_count>1?'s':''} · ${leadText}</div>
        <div style="margin-top:8px">${badge}</div>
      </div>
      <div class="score-circle">
        <div class="score-val" style="color:${scoreColor(sup.score)}">${sup.score}</div>
        <div class="score-lbl">/ 100</div>
      </div>
    </div>

    <div>
      <div class="dim-row">
        <div class="dim-label">Taux validation</div>
        <div class="dim-bar-wrap"><div class="dim-bar" style="width:${sup.validation_rate}%;background:${DIM_COLORS.validation}"></div></div>
        <div class="dim-val">${sup.validation_rate}%</div>
      </div>
      <div class="dim-row">
        <div class="dim-label">Complétion livr.</div>
        <div class="dim-bar-wrap"><div class="dim-bar" style="width:${sup.completion_rate}%;background:${DIM_COLORS.completion}"></div></div>
        <div class="dim-val">${sup.completion_rate}%</div>
      </div>
      <div class="dim-row" style="margin-bottom:0">
        <div class="dim-label">Régularité</div>
        <div class="dim-bar-wrap"><div class="dim-bar" style="width:${sup.regularity_score}%;background:${DIM_COLORS.regularity}"></div></div>
        <div class="dim-val">${sup.regularity_score}%</div>
      </div>
    </div>

    <div class="sup-stats">
      <div class="stat-item"><div class="n">${sup.delivery_count}</div><div class="l">Livraisons</div></div>
      <div class="stat-item"><div class="n">${sup.item_count || '—'}</div><div class="l">Articles</div></div>
      <div class="stat-item"><div class="n">${valCFA}</div><div class="l">Valeur totale</div></div>
    </div>

    <a class="order-link" href="/analytics/orders.php?supplier=${encodeURIComponent(sup.supplier_name)}">
      Commander →
    </a>
  </div>`;
}

async function loadSuppliers() {
  try {
    const r = await fetch('/analytics/api.php?type=suppliers', { credentials: 'same-origin' });
    const data = await r.json();
    document.getElementById('loadingState').style.display = 'none';

    const sups = data.suppliers || [];
    if (sups.length === 0) {
      document.getElementById('emptyState').style.display = 'block';
      const ks = document.getElementById('knownSuppliersSection');
      if (ks) ks.style.display = 'block';
      return;
    }

    const grid = document.getElementById('supplierGrid');
    grid.innerHTML = sups.map(renderCard).join('');
    grid.style.display = 'grid';

    const ks = document.getElementById('knownSuppliersSection');
    if (ks) ks.style.display = 'block';
  } catch(e) {
    document.getElementById('loadingState').innerHTML = '<div style="color:#dc2626;font-size:14px">Erreur de chargement — service analytics indisponible.</div>';
  }
}

function openSidebar()  { document.getElementById('sidebarOverlay').classList.add('open'); document.querySelector('.sidebar').classList.add('open'); }
function closeSidebar() { document.getElementById('sidebarOverlay').classList.remove('open'); document.querySelector('.sidebar').classList.remove('open'); }

loadSuppliers();
</script>
</body>
</html>
