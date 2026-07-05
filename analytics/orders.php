<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
ai_check_auth();
$user       = ai_user();
$activePage = 'orders';
$pid        = $user['pharmacy_id'];
$db         = analytics_db();

// Load orders
$stmt = $db->prepare("
    SELECT o.*,
           (SELECT COUNT(*) FROM ai_purchase_order_items WHERE order_id = o.id) AS item_count
    FROM ai_purchase_orders o
    WHERE o.pharmacy_id = ?
    ORDER BY o.created_at DESC
    LIMIT 200
");
$stmt->execute([$pid]);
$orders = $stmt->fetchAll();

// Load suppliers for dropdown
$supStmt = $db->prepare("SELECT name, contact FROM ai_suppliers WHERE pharmacy_id = ? ORDER BY name");
$supStmt->execute([$pid]);
$suppliers = $supStmt->fetchAll();

// Load inventory for product picker
$invStmt = $db->prepare("
    SELECT product_id, product_name, category, stock_quantity, unit_cost
    FROM ai_inventory
    WHERE pharmacy_id = ?
      AND snapshot_date = (SELECT MAX(snapshot_date) FROM ai_inventory WHERE pharmacy_id = ?)
    ORDER BY product_name
    LIMIT 500
");
$invStmt->execute([$pid, $pid]);
$inventory = $invStmt->fetchAll();

// Pre-fill supplier from query param
$preSupplier = htmlspecialchars($_GET['supplier'] ?? '');

// Status badge helper
function statusBadge(string $status): string {
    $map = [
        'draft'     => ['label' => 'Brouillon',  'cls' => '#94a3b8', 'bg' => '#f1f5f9'],
        'sent'      => ['label' => 'Envoyé',     'cls' => '#1d4ed8', 'bg' => '#dbeafe'],
        'confirmed' => ['label' => 'Confirmé',   'cls' => '#166534', 'bg' => '#dcfce7'],
        'declined'  => ['label' => 'Décliné',    'cls' => '#991b1b', 'bg' => '#fee2e2'],
        'shipped'   => ['label' => 'Expédié',    'cls' => '#7c3aed', 'bg' => '#ede9fe'],
        'delivered' => ['label' => 'Livré',      'cls' => '#065f46', 'bg' => '#d1fae5'],
        'cancelled' => ['label' => 'Annulé',     'cls' => '#374151', 'bg' => '#f3f4f6'],
    ];
    $d = $map[$status] ?? ['label' => $status, 'cls' => '#6b7280', 'bg' => '#f3f4f6'];
    return "<span style='display:inline-flex;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:700;color:{$d['cls']};background:{$d['bg']}'>{$d['label']}</span>";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Commandes — digiMind</title>
<style>
<?php require_once __DIR__ . '/includes/common.css.php'; ?>
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.page-title{font-size:22px;font-weight:700;color:var(--text)}
.page-sub{font-size:13px;color:var(--text-3);margin-top:2px}
/* ── local overrides (common.css.php vars apply) ─────────────────── */
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px}
.page-title{font-size:22px;font-weight:700;color:var(--text)}
.page-sub{font-size:13px;color:var(--text-3);margin-top:2px}

.orders-table{width:100%;border-collapse:collapse;font-size:13px}
.orders-table th{padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--border)}
.orders-table td{padding:12px 14px;border-bottom:1px solid var(--border-lt);color:var(--text-2);vertical-align:middle}
.orders-table tr:last-child td{border-bottom:none}
.orders-table tr:hover td{background:var(--surface-alt)}
.ref{font-weight:700;font-family:monospace;font-size:12px;color:var(--green)}
.empty-row td{text-align:center;padding:60px;color:var(--text-3)}
.action-cell{display:flex;gap:6px;align-items:center;flex-wrap:wrap}

/* ── Modal ───────────────────────────────────────────────────────── */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:flex-start;justify-content:center;padding:24px 16px;overflow-y:auto}
.modal-overlay.open{display:flex}
.modal{background:var(--surface);border:1px solid var(--border);border-radius:14px;width:100%;max-width:660px;position:relative;box-shadow:0 16px 48px rgba(0,0,0,.18)}
.modal-header{padding:20px 24px 0;display:flex;align-items:center;justify-content:space-between}
.modal-title{font-size:17px;font-weight:700;color:var(--text)}
.modal-close{background:none;border:none;color:var(--text-3);cursor:pointer;padding:4px;display:flex;border-radius:6px}
.modal-close:hover{background:var(--surface-alt);color:var(--text)}
.modal-body{padding:20px 24px 24px}

.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.form-row.single{grid-template-columns:1fr}
.field{display:flex;flex-direction:column;gap:5px}
.field label{font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em}
.field input,.field select,.field textarea{
  padding:9px 11px;border:1.5px solid var(--border);border-radius:8px;
  font-size:14px;background:var(--bg);color:var(--text);
  font-family:inherit;width:100%;box-sizing:border-box;
}
.field input:focus,.field select:focus,.field textarea:focus{
  outline:none;border-color:var(--green);box-shadow:0 0 0 3px rgba(26,127,75,.1)
}
.field textarea{resize:vertical;min-height:60px}

.section-sep{border:none;border-top:1px solid var(--border-lt);margin:18px 0 16px}
.section-label{font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px}

/* Product search */
.psearch-wrap{position:relative;margin-bottom:12px}
.psearch-wrap input{
  padding:9px 11px 9px 36px;border:1.5px solid var(--border);border-radius:8px;
  font-size:14px;background:var(--bg);color:var(--text);width:100%;box-sizing:border-box;
}
.psearch-wrap input:focus{outline:none;border-color:var(--green);box-shadow:0 0 0 3px rgba(26,127,75,.1)}
.psearch-icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-3);pointer-events:none}
.pdropdown{position:absolute;top:calc(100% + 3px);left:0;right:0;
  background:var(--surface);border:1.5px solid var(--border);border-radius:8px;
  max-height:210px;overflow-y:auto;z-index:300;display:none;
  box-shadow:0 8px 24px rgba(0,0,0,.1)}
.popt{padding:9px 14px;cursor:pointer;font-size:13px;display:flex;justify-content:space-between;align-items:center;gap:12px;border-bottom:1px solid var(--border-lt)}
.popt:last-child{border-bottom:none}
.popt:hover{background:var(--surface-alt)}
.popt-name{font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.popt-cat{font-size:11px;color:var(--text-3);margin-top:1px}
.popt-stock{font-size:12px;color:var(--text-3);text-align:right;flex-shrink:0}

/* Item rows */
.items-list{display:flex;flex-direction:column;gap:6px}
.item-row{display:grid;grid-template-columns:1fr 80px 100px 28px;gap:8px;align-items:center;
  background:var(--surface-alt);border:1px solid var(--border-lt);border-radius:8px;padding:8px 12px}
.item-name{font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.item-hint{font-size:11px;color:var(--text-3);margin-top:1px}
.item-input{padding:5px 7px;border:1.5px solid var(--border);border-radius:6px;font-size:13px;
  background:var(--bg);color:var(--text);width:100%;text-align:center;box-sizing:border-box}
.item-input:focus{outline:none;border-color:var(--green)}
.item-del{background:none;border:none;color:var(--text-3);cursor:pointer;padding:2px;display:flex;border-radius:4px}
.item-del:hover{color:#dc2626;background:#fee2e2}

/* Modal footer */
.modal-footer{display:flex;gap:10px;justify-content:flex-end;padding-top:20px;border-top:1px solid var(--border-lt);margin-top:4px}

/* Toast */
.toast{display:none;position:fixed;bottom:24px;right:24px;background:var(--green);color:#fff;
  padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;z-index:500;
  box-shadow:0 4px 20px rgba(0,0,0,.2)}

/* ── Mobile: table → cards ─────────────────────────────────────────── */
@media(max-width:680px){
  /* Remove the scrolling wrapper effect */
  .orders-table { display:block; }
  .orders-table thead { display:none; }
  .orders-table tbody { display:flex; flex-direction:column; gap:10px; padding:12px; }
  .orders-table tr { display:grid; grid-template-columns:1fr auto; grid-template-rows:auto auto auto;
    background:var(--surface); border:1px solid var(--border); border-radius:10px; padding:14px;
    column-gap:12px; row-gap:6px; }
  .orders-table tr:last-child { border-bottom:1px solid var(--border); }
  /* ref (col1, row1) */
  .orders-table td:nth-child(1) { grid-column:1; grid-row:1; }
  /* fournisseur (col1, row2) */
  .orders-table td:nth-child(2) { grid-column:1; grid-row:2; font-size:14px; color:var(--text); }
  /* articles — hide on mobile */
  .orders-table td:nth-child(3) { display:none; }
  /* statut (col2, row1) */
  .orders-table td:nth-child(4) { grid-column:2; grid-row:1; text-align:right; }
  /* livraison (col2, row2) */
  .orders-table td:nth-child(5) { grid-column:2; grid-row:2; font-size:11px; color:var(--text-3); text-align:right; }
  /* créé le — hide on mobile */
  .orders-table td:nth-child(6) { display:none; }
  /* actions (row3, full width) */
  .orders-table td:nth-child(7) { grid-column:1/-1; grid-row:3; border-top:1px solid var(--border-lt); margin-top:4px; padding-top:10px; }
  .orders-table td { border:none; padding:0; font-size:13px; }
  .action-cell { gap:8px; }

  /* Modal bottom sheet */
  .modal-overlay { padding:0; align-items:flex-end; }
  .modal { border-radius:16px 16px 0 0; max-height:92vh; overflow-y:auto; }
  .modal-header { padding:16px 16px 0; position:sticky; top:0; background:var(--surface); z-index:1; border-radius:16px 16px 0 0; }
  .modal-body { padding:16px; }
  .modal-footer { position:sticky; bottom:0; background:var(--surface); padding:14px 16px; margin:0 -16px -16px; border-top:1px solid var(--border-lt); }
  .form-row { grid-template-columns:1fr; gap:10px; }
  .item-row { grid-template-columns:1fr 64px 80px 28px; }
  .page-header { margin-bottom:14px; }
  .page-title  { font-size:17px; }
  .toast { bottom:12px; right:12px; left:12px; text-align:center; }
}
</style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left" style="flex-direction:row;align-items:center;gap:8px;">
      <button class="hamburger" onclick="openSidebar()" aria-label="Menu">
        <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="topbar-title">Commandes fournisseurs</div>
    </div>
    <button class="btn btn-primary" onclick="openModal()" style="display:flex;align-items:center;gap:6px;padding:7px 13px;font-size:13px;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <span class="btn-label">Nouvelle commande</span>
    </button>
  </div>

  <div class="content">
    <div class="page-header">
      <div>
        <div class="page-title">Bons de commande</div>
        <div class="page-sub"><?= count($orders) ?> commande<?= count($orders)!==1?'s':'' ?></div>
      </div>
    </div>

    <!-- Orders table -->
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow-x:auto">
      <table class="orders-table">
        <thead>
          <tr>
            <th>Référence</th>
            <th>Fournisseur</th>
            <th>Articles</th>
            <th>Statut</th>
            <th>Livraison souhaitée</th>
            <th>Créé le</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($orders)): ?>
          <tr class="empty-row"><td colspan="7">Aucune commande — cliquez sur "Nouvelle commande" pour commencer</td></tr>
        <?php else: foreach ($orders as $o): ?>
          <tr>
            <td><span class="ref"><?= htmlspecialchars($o['order_ref'] ?? '#'.$o['id']) ?></span></td>
            <td style="font-weight:600"><?= htmlspecialchars($o['supplier_name']) ?></td>
            <td style="text-align:center"><?= (int)$o['item_count'] ?></td>
            <td><?= statusBadge($o['status']) ?></td>
            <td><?= $o['requested_delivery_date'] ? date('d/m/Y', strtotime($o['requested_delivery_date'])) : '—' ?></td>
            <td style="color:var(--text-3)"><?= date('d/m/Y', strtotime($o['created_at'])) ?></td>
            <td>
              <div class="action-cell">
                <?php if (in_array($o['status'], ['shipped', 'confirmed'])): ?>
                  <button class="btn btn-sm btn-primary" onclick="markDelivered(<?= $o['id'] ?>, this)">Livré ✓</button>
                <?php elseif ($o['status'] === 'sent'): ?>
                  <span style="font-size:11px;color:var(--text-3)">En attente fournisseur</span>
                <?php elseif ($o['status'] === 'draft'): ?>
                  <button class="btn btn-sm btn-outline" onclick="resendOrder(<?= $o['id'] ?>, this)">Envoyer</button>
                <?php endif; ?>
                <?php if (!in_array($o['status'], ['delivered','cancelled'])): ?>
                  <button class="btn btn-sm btn-outline" style="color:#dc2626" onclick="cancelOrder(<?= $o['id'] ?>, this)">Annuler</button>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Create order modal -->
<div class="modal-overlay" id="orderModal" onclick="if(event.target===this)closeModal()">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Nouvelle commande</div>
      <button class="modal-close" onclick="closeModal()" type="button">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div class="modal-body">
      <form id="orderForm" onsubmit="submitOrder(event)">

        <!-- Row 1: supplier + email -->
        <div class="form-row">
          <div class="field">
            <label>Fournisseur *</label>
            <?php if ($suppliers): ?>
            <select id="supplierSelect" onchange="prefillContact()" required>
              <option value="">— Sélectionner —</option>
              <?php foreach ($suppliers as $s): ?>
              <option value="<?= htmlspecialchars($s['name']) ?>" data-contact="<?= htmlspecialchars($s['contact'] ?? '') ?>">
                <?= htmlspecialchars($s['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?php else: ?>
            <input type="text" id="supplierSelect" placeholder="Nom du fournisseur" required>
            <?php endif; ?>
          </div>
          <div class="field">
            <label>Email fournisseur *</label>
            <input type="email" id="supplierEmail" placeholder="contact@fournisseur.com" required>
          </div>
        </div>

        <!-- Row 2: date + notes -->
        <div class="form-row">
          <div class="field">
            <label>Livraison souhaitée</label>
            <input type="date" id="requestedDate" min="<?= date('Y-m-d') ?>">
          </div>
          <div class="field">
            <label>Notes</label>
            <input type="text" id="orderNotes" placeholder="Instructions particulières…">
          </div>
        </div>

        <hr class="section-sep">
        <div class="section-label">Produits à commander</div>

        <!-- Product search -->
        <div class="psearch-wrap">
          <svg class="psearch-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="productSearch" placeholder="Rechercher un produit de l'inventaire…"
            autocomplete="off" oninput="filterProducts(this.value)"
            onfocus="showDropdown()" onblur="setTimeout(hideDropdown,200)">
          <div class="pdropdown" id="productDropdown"></div>
        </div>

        <!-- Added items -->
        <div class="items-list" id="itemsList"></div>
        <div id="itemsEmpty" style="text-align:center;padding:20px 0;color:var(--text-3);font-size:13px">
          Aucun produit ajouté — recherchez ci-dessus pour ajouter
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline" onclick="closeModal()">Annuler</button>
          <button type="submit" class="btn btn-primary" id="submitBtn">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Créer et envoyer
          </button>
        </div>

      </form>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
// Inventory data from PHP
const INVENTORY = <?= json_encode(array_values($inventory)) ?>;
const PRE_SUPPLIER = <?= json_encode($preSupplier) ?>;

let orderItems = []; // { product_id, product_name, stock_quantity, unit_cost, quantity }

// Open/close modal
function openModal() {
  document.getElementById('orderModal').classList.add('open');
  if (PRE_SUPPLIER) {
    const sel = document.getElementById('supplierSelect');
    if (sel.tagName === 'SELECT') {
      for (let o of sel.options) { if (o.value === PRE_SUPPLIER) { o.selected = true; prefillContact(); break; } }
    } else {
      sel.value = PRE_SUPPLIER;
    }
  }
}
function closeModal() { document.getElementById('orderModal').classList.remove('open'); }

// Prefill email from supplier contact field
function prefillContact() {
  const sel = document.getElementById('supplierSelect');
  const contact = sel.options[sel.selectedIndex]?.dataset?.contact || '';
  if (contact.includes('@')) document.getElementById('supplierEmail').value = contact;
}

// Product search + dropdown
function filterProducts(q) {
  const dd = document.getElementById('productDropdown');
  const lq = q.toLowerCase().trim();
  if (lq.length < 2) { dd.style.display = 'none'; return; }

  const matches = INVENTORY.filter(p =>
    p.product_name?.toLowerCase().includes(lq) || p.category?.toLowerCase().includes(lq)
  ).slice(0, 25);

  dd.innerHTML = matches.length
    ? matches.map(p => {
        const safe = JSON.stringify(p).replace(/"/g,'&quot;');
        return `<div class="popt" onmousedown="addItem(${safe})">
          <div style="min-width:0">
            <div class="popt-name">${p.product_name}</div>
            <div class="popt-cat">${p.category || ''}</div>
          </div>
          <div class="popt-stock">
            <div>${p.stock_quantity ?? '?'} unités</div>
            <div>${p.unit_cost ? Math.round(p.unit_cost).toLocaleString()+' CFA' : ''}</div>
          </div>
        </div>`;
      }).join('')
    : '<div style="padding:12px 14px;color:var(--text-3);font-size:13px">Aucun résultat</div>';

  dd.style.display = 'block';
}
function showDropdown() {
  const v = document.getElementById('productSearch').value;
  if (v.length >= 2) filterProducts(v);
}
function hideDropdown() { document.getElementById('productDropdown').style.display = 'none'; }

function addItem(p) {
  if (orderItems.some(i => i.product_id === p.product_id)) return;
  orderItems.push({ product_id: p.product_id, product_name: p.product_name,
    stock_quantity: p.stock_quantity, unit_cost: p.unit_cost, quantity: 1 });
  renderItems();
  document.getElementById('productSearch').value = '';
  hideDropdown();
}

function removeItem(pid) {
  orderItems = orderItems.filter(i => i.product_id !== pid);
  renderItems();
}

function renderItems() {
  const list  = document.getElementById('itemsList');
  const empty = document.getElementById('itemsEmpty');
  if (!orderItems.length) {
    list.innerHTML = '';
    empty.style.display = 'block';
    return;
  }
  empty.style.display = 'none';
  list.innerHTML = orderItems.map(item => {
    const pid  = item.product_id.replace(/'/g,"\\'");
    const hint = [
      item.stock_quantity != null ? `Stock: ${item.stock_quantity}` : '',
      item.unit_cost ? Math.round(item.unit_cost).toLocaleString()+' CFA/u' : '',
    ].filter(Boolean).join(' · ');
    return `<div class="item-row">
      <div style="min-width:0">
        <div class="item-name">${item.product_name}</div>
        ${hint ? `<div class="item-hint">${hint}</div>` : ''}
      </div>
      <input class="item-input" type="number" min="1" value="${item.quantity}"
        onchange="updateQty('${pid}', this.value)" placeholder="Qté" title="Quantité">
      <input class="item-input" type="number" min="0" step="any" value="${item.unit_cost || ''}"
        onchange="updatePrice('${pid}', this.value)" placeholder="Prix (CFA)" title="Prix unitaire">
      <button class="item-del" onclick="removeItem('${pid}')" type="button" title="Retirer">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>`;
  }).join('');
}

function updateQty(id, v)   { const i = orderItems.find(x => x.product_id === id); if (i) i.quantity  = parseInt(v)   || 1;   }
function updatePrice(id, v) { const i = orderItems.find(x => x.product_id === id); if (i) i.unit_cost = parseFloat(v) || null; }

// Submit order
async function submitOrder(e) {
  e.preventDefault();
  if (!orderItems.length) { alert('Ajoutez au moins un produit.'); return; }

  const sel = document.getElementById('supplierSelect');
  const supplierName = sel.tagName === 'SELECT' ? sel.value : sel.value;
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.textContent = 'Envoi…';

  const payload = {
    action:          'create',
    supplier_name:   supplierName,
    supplier_email:  document.getElementById('supplierEmail').value,
    requested_date:  document.getElementById('requestedDate').value,
    notes:           document.getElementById('orderNotes').value,
    items:           orderItems.map(i => ({
      product_id:   i.product_id,
      product_name: i.product_name,
      quantity:     i.quantity,
      unit_price:   i.unit_cost,
    })),
  };

  try {
    const r = await fetch('/analytics/orders-save.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    });
    const data = await r.json();
    if (data.ok) {
      showToast('Commande ' + data.order_ref + ' créée et envoyée ✓');
      setTimeout(() => location.reload(), 1500);
    } else {
      alert('Erreur: ' + (data.error || 'Inconnue'));
      btn.disabled = false;
      btn.textContent = 'Créer et envoyer';
    }
  } catch(err) {
    alert('Erreur réseau: ' + err.message);
    btn.disabled = false;
    btn.textContent = 'Créer et envoyer';
  }
}

// Mark as delivered
async function markDelivered(orderId, btn) {
  if (!confirm('Marquer cette commande comme livrée ?')) return;
  btn.disabled = true;
  const r = await fetch('/analytics/orders-save.php', {
    method: 'POST', headers: {'Content-Type':'application/json'}, credentials:'same-origin',
    body: JSON.stringify({ action: 'deliver', order_id: orderId }),
  });
  const data = await r.json();
  if (data.ok) { showToast('Commande marquée comme livrée'); setTimeout(()=>location.reload(), 1200); }
  else { alert('Erreur: '+(data.error||'')); btn.disabled=false; }
}

// Cancel order
async function cancelOrder(orderId, btn) {
  if (!confirm('Annuler cette commande ?')) return;
  btn.disabled = true;
  const r = await fetch('/analytics/orders-save.php', {
    method: 'POST', headers: {'Content-Type':'application/json'}, credentials:'same-origin',
    body: JSON.stringify({ action: 'cancel', order_id: orderId }),
  });
  const data = await r.json();
  if (data.ok) { showToast('Commande annulée'); setTimeout(()=>location.reload(), 1200); }
  else { alert('Erreur: '+(data.error||'')); btn.disabled=false; }
}

// Resend existing order
async function resendOrder(orderId, btn) {
  if (!confirm('Renvoyer l\'email fournisseur pour cette commande ?')) return;
  btn.disabled = true;
  const r = await fetch('/analytics/orders-save.php', {
    method: 'POST', headers: {'Content-Type':'application/json'}, credentials:'same-origin',
    body: JSON.stringify({ action: 'send', order_id: orderId }),
  });
  const data = await r.json();
  if (data.ok) { showToast('Email renvoyé ✓'); btn.disabled=false; }
  else { alert('Erreur: '+(data.error||'')); btn.disabled=false; }
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.style.display = 'block';
  setTimeout(() => t.style.display='none', 3500);
}

function openSidebar()  { document.getElementById('sidebarOverlay').classList.add('open'); document.querySelector('.sidebar').classList.add('open'); }
function closeSidebar() { document.getElementById('sidebarOverlay').classList.remove('open'); document.querySelector('.sidebar').classList.remove('open'); }
</script>
</body>
</html>
