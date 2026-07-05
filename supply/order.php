<?php
/**
 * digiSupply — Supplier order confirmation page.
 * Accessible via token link only. No account required.
 */
require_once __DIR__ . '/config/db.php';

$token = trim($_GET['token'] ?? '');
if (!$token) {
    header('Location: /supply/');
    exit;
}

$data = supply_resolve_token($token);
if (!$data) {
    http_response_code(404);
    ?><!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Lien invalide — digiSupply</title>
    <style>*{box-sizing:border-box}body{font-family:system-ui,sans-serif;background:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px}
    .card{background:#fff;border-radius:12px;padding:48px 32px;max-width:380px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.08)}
    h2{color:#111827;margin-bottom:12px}p{color:#6b7280;font-size:14px;line-height:1.6}</style>
    </head><body><div class="card"><div style="font-size:40px;margin-bottom:20px">🔒</div>
    <h2>Lien invalide ou expiré</h2>
    <p>Ce lien de commande n'est plus valide. Contactez la pharmacie pour obtenir un nouveau lien.</p>
    <a href="/supply/" style="display:inline-block;margin-top:20px;color:#1e40af;font-size:14px">← Portail fournisseur</a>
    </div></body></html><?php
    exit;
}

$order   = $data;
$items   = $data['items'];
$status  = $order['status'];

// Status labels
$statusMap = [
    'draft'     => ['Brouillon',  '#94a3b8'],
    'sent'      => ['En attente', '#1d4ed8'],
    'confirmed' => ['Confirmé',   '#166534'],
    'declined'  => ['Décliné',    '#991b1b'],
    'shipped'   => ['Expédié',    '#7c3aed'],
    'delivered' => ['Livré',      '#065f46'],
    'cancelled' => ['Annulé',     '#374151'],
];
[$statusLabel, $statusColor] = $statusMap[$status] ?? [$status, '#6b7280'];

// Total
$total = 0;
foreach ($items as $item) {
    if ($item['unit_price']) $total += $item['quantity_requested'] * $item['unit_price'];
}

// Requested delivery
$reqDate = $order['requested_delivery_date']
    ? date('d/m/Y', strtotime($order['requested_delivery_date']))
    : 'Dès que possible';

// Confirmed delivery
$confDate = $order['confirmed_delivery_date']
    ? date('d/m/Y', strtotime($order['confirmed_delivery_date']))
    : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Commande <?= htmlspecialchars($order['order_ref'] ?? '#'.$order['id']) ?> — digiSupply</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
:root{--blue:#1e40af;--blue-dk:#1e3a8a;--blue-lt:#dbeafe;--green:#166534;--green-lt:#dcfce7;--red:#991b1b;--red-lt:#fee2e2;--text:#111827;--muted:#6b7280;--border:#e5e7eb;--bg:#f1f5f9;--card:#fff}
@media(prefers-color-scheme:dark){:root{--text:#f1f5f9;--muted:#94a3b8;--border:#334155;--bg:#0f172a;--card:#1e293b}}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);min-height:100vh;padding:0}
.topbar{background:var(--blue);padding:14px 24px;display:flex;align-items:center;justify-content:space-between}
.logo{font-size:18px;font-weight:800;color:#fff;letter-spacing:-0.5px}
.logo span{color:#bfdbfe}
.order-ref{font-size:12px;color:#bfdbfe;font-family:monospace}
.container{max-width:640px;margin:0 auto;padding:24px 16px}
.section{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:20px;margin-bottom:16px}
.section-title{font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:14px}
.meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.meta-item .label{font-size:11px;color:var(--muted);margin-bottom:2px}
.meta-item .val{font-size:14px;font-weight:600;color:var(--text)}
.status-badge{display:inline-flex;padding:4px 12px;border-radius:20px;font-size:13px;font-weight:700}
table{width:100%;border-collapse:collapse}
th{padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);border-bottom:1px solid var(--border);text-transform:uppercase;letter-spacing:.04em}
td{padding:12px 12px;font-size:14px;color:var(--text);border-bottom:1px solid var(--border)}
tr:last-child td{border-bottom:none}
.prod-name{font-weight:600}
.total-row td{font-weight:700;font-size:15px;background:var(--bg)}
.action-section{background:var(--card);border:1px solid var(--border);border-radius:12px;padding:24px;margin-bottom:16px}
.action-title{font-size:16px;font-weight:700;color:var(--text);margin-bottom:6px}
.action-sub{font-size:13px;color:var(--muted);margin-bottom:20px;line-height:1.5}
.btn{width:100%;padding:14px;border-radius:8px;font-size:15px;font-weight:700;border:none;cursor:pointer;margin-bottom:12px;display:flex;align-items:center;justify-content:center;gap:10px}
.btn-confirm{background:#166534;color:#fff}
.btn-confirm:hover{background:#14532d}
.btn-decline{background:#dc2626;color:#fff}
.btn-decline:hover{background:#b91c1c}
.btn-ship{background:var(--blue);color:#fff}
.btn-ship:hover{background:var(--blue-dk)}
.field-group{margin-bottom:16px}
.field-group label{display:block;font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px}
.field-group input,
.field-group textarea{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;background:var(--bg);color:var(--text);font-family:inherit}
.field-group input:focus,
.field-group textarea:focus{outline:none;border-color:var(--blue)}
.field-group textarea{resize:vertical;min-height:80px}
.sub-form{display:none;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:16px;margin-top:-4px;margin-bottom:12px}
.sub-form.open{display:block}
.done-banner{background:var(--green-lt);border:1px solid #bbf7d0;border-radius:10px;padding:20px;text-align:center}
.done-banner .icon{font-size:32px;margin-bottom:10px}
.done-banner .msg{font-size:16px;font-weight:700;color:var(--green)}
.done-banner .sub{font-size:13px;color:#166534;margin-top:6px}
.declined-banner{background:var(--red-lt);border:1px solid #fca5a5;border-radius:10px;padding:20px;text-align:center}
.declined-banner .msg{font-size:16px;font-weight:700;color:var(--red)}
.declined-banner .sub{font-size:13px;color:var(--red);margin-top:6px}
.footer{text-align:center;padding:32px 16px;font-size:12px;color:var(--muted)}
.err{color:#dc2626;font-size:13px;margin-top:8px}
</style>
</head>
<body>

<div class="topbar">
  <div class="logo">digi<span>Supply</span></div>
  <div class="order-ref"><?= htmlspecialchars($order['order_ref'] ?? 'CMD') ?></div>
</div>

<div class="container">

  <!-- Order summary -->
  <div class="section">
    <div class="section-title">Détails de la commande</div>
    <div class="meta-grid">
      <div class="meta-item">
        <div class="label">Pharmacie</div>
        <div class="val"><?= htmlspecialchars($order['pharmacy_name']) ?></div>
      </div>
      <div class="meta-item">
        <div class="label">Statut</div>
        <div class="val">
          <span class="status-badge" style="color:<?= $statusColor ?>;background:<?= $statusColor ?>22"><?= $statusLabel ?></span>
        </div>
      </div>
      <div class="meta-item">
        <div class="label">Livraison souhaitée</div>
        <div class="val"><?= $reqDate ?></div>
      </div>
      <?php if ($confDate): ?>
      <div class="meta-item">
        <div class="label">Livraison confirmée</div>
        <div class="val" style="color:var(--green)"><?= $confDate ?></div>
      </div>
      <?php endif; ?>
      <?php if ($order['notes']): ?>
      <div class="meta-item" style="grid-column:1/-1">
        <div class="label">Notes</div>
        <div class="val"><?= htmlspecialchars($order['notes']) ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Products -->
  <div class="section">
    <div class="section-title">Produits commandés</div>
    <table>
      <thead><tr>
        <th>Produit</th>
        <th style="text-align:center">Qté demandée</th>
        <th style="text-align:right">Prix unit.</th>
      </tr></thead>
      <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td class="prod-name"><?= htmlspecialchars($item['product_name']) ?></td>
          <td style="text-align:center;font-variant-numeric:tabular-nums"><?= (int)$item['quantity_requested'] ?></td>
          <td style="text-align:right;color:var(--muted)">
            <?= $item['unit_price'] ? number_format((float)$item['unit_price'], 0, ',', ' ') . ' CFA' : '—' ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($total > 0): ?>
        <tr class="total-row">
          <td colspan="2">Total estimé</td>
          <td style="text-align:right"><?= number_format($total, 0, ',', ' ') ?> CFA</td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Action section -->
  <?php if ($status === 'sent'): ?>
  <div class="action-section">
    <div class="action-title">Votre réponse</div>
    <div class="action-sub">Confirmez ou déclinez cette commande. Vous pouvez aussi proposer une date de livraison alternative.</div>

    <!-- Confirm form -->
    <form method="post" action="/supply/confirm.php" onsubmit="return validateConfirm(this)">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <input type="hidden" name="action" value="confirm">
      <button type="button" class="btn btn-confirm" onclick="toggleForm('confirmForm', this)">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
        Confirmer la commande
      </button>
      <div class="sub-form" id="confirmForm">
        <div class="field-group">
          <label>Date de livraison confirmée *</label>
          <input type="date" name="delivery_date" required min="<?= date('Y-m-d') ?>">
        </div>
        <div class="field-group" style="margin-bottom:0">
          <label>Message (facultatif)</label>
          <textarea name="message" placeholder="Ex: Livraison possible le matin…"></textarea>
        </div>
        <button type="submit" class="btn btn-confirm" style="margin-top:16px;margin-bottom:0">Envoyer la confirmation →</button>
      </div>
    </form>

    <!-- Decline form -->
    <form method="post" action="/supply/confirm.php" onsubmit="return validateDecline(this)">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <input type="hidden" name="action" value="decline">
      <button type="button" class="btn btn-decline" style="margin-bottom:0" onclick="toggleForm('declineForm', this)">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        Décliner
      </button>
      <div class="sub-form" id="declineForm">
        <div class="field-group" style="margin-bottom:0">
          <label>Motif du refus *</label>
          <textarea name="reason" placeholder="Ex: Rupture de stock, produit arrêté…" required></textarea>
        </div>
        <button type="submit" class="btn btn-decline" style="margin-top:16px;margin-bottom:0">Confirmer le refus</button>
      </div>
    </form>
  </div>

  <?php elseif ($status === 'confirmed'): ?>
  <div class="done-banner">
    <div class="icon">✅</div>
    <div class="msg">Commande confirmée</div>
    <?php if ($confDate): ?><div class="sub">Livraison prévue le <?= $confDate ?></div><?php endif; ?>
  </div>
  <!-- Supplier can mark as shipped -->
  <form method="post" action="/supply/confirm.php" style="margin-top:12px">
    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
    <input type="hidden" name="action" value="ship">
    <button type="submit" class="btn btn-ship">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
      Marquer comme expédiée
    </button>
  </form>

  <?php elseif ($status === 'declined'): ?>
  <div class="declined-banner">
    <div class="msg">Commande déclinée</div>
    <?php if ($order['supplier_decline_reason']): ?>
      <div class="sub"><?= htmlspecialchars($order['supplier_decline_reason']) ?></div>
    <?php endif; ?>
  </div>

  <?php elseif (in_array($status, ['shipped', 'delivered', 'cancelled'])): ?>
  <div class="done-banner">
    <div class="icon"><?= $status === 'cancelled' ? '❌' : '📦' ?></div>
    <div class="msg"><?= $statusLabel ?></div>
  </div>
  <?php endif; ?>

</div>

<div class="footer">digiSupply · digiMind · Digital Technologies Congo</div>

<script>
function toggleForm(id, btn) {
  const form = document.getElementById(id);
  const isOpen = form.classList.contains('open');
  // Close all sub-forms first
  document.querySelectorAll('.sub-form').forEach(f => f.classList.remove('open'));
  if (!isOpen) form.classList.add('open');
}
function validateConfirm(form) {
  return !!form.querySelector('[name="delivery_date"]')?.value;
}
function validateDecline(form) {
  return !!form.querySelector('[name="reason"]')?.value.trim();
}
</script>
</body>
</html>
