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
.page-sub{font-size:13px;color:var(--muted);margin-top:2px}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:8px;font-size:14px;font-weight:600;border:none;cursor:pointer;text-decoration:none;transition:background .15s}
.btn-primary{background:#1a7f4b;color:#fff}
.btn-primary:hover{background:#155e38}
.btn-outline{background:transparent;color:var(--text);border:1.5px solid var(--border)}
.btn-outline:hover{background:var(--hover)}
.btn-sm{padding:6px 12px;font-size:12px}
.btn-red{background:#dc2626;color:#fff}
.btn-red:hover{background:#b91c1c}
.orders-table{width:100%;border-collapse:collapse;font-size:13px}
.orders-table th{padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid var(--border)}
.orders-table td{padding:12px 14px;border-bottom:1px solid var(--border);color:var(--text);vertical-align:middle}
.orders-table tr:last-child td{border-bottom:none}
.orders-table tr:hover td{background:var(--hover)}
.ref{font-weight:700;font-family:monospace;font-size:12px;color:#1a7f4b}
.empty-row td{text-align:center;padding:60px;color:var(--muted)}

/* Modal */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:flex-start;justify-content:center;padding:32px 16px;overflow-y:auto}
.modal-overlay.open{display:flex}
.modal{background:var(--card);border-radius:16px;width:100%;max-width:720px;padding:32px;position:relative;box-shadow:0 20px 60px rgba(0,0,0,.3)}
.modal-title{font-size:18px;font-weight:700;color:var(--text);margin-bottom:24px}
.modal-close{position:absolute;top:20px;right:20px;background:none;border:none;color:var(--muted);cursor:pointer;font-size:20px;line-height:1}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-full{grid-column:1/-1}
.field-group{display:flex;flex-direction:column;gap:6px}
label{font-size:12px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
input[type=text],input[type=email],input[type=date],select,textarea{width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;background:var(--bg);color:var(--text);box-sizing:border-box;font-family:inherit}
input:focus,select:focus,textarea:focus{outline:none;border-color:#1a7f4b;box-shadow:0 0 0 3px rgba(26,127,75,.12)}
textarea{resize:vertical;min-height:70px}
.divider{grid-column:1/-1;border:none;border-top:1px solid var(--border);margin:4px 0}

/* Product picker */
.product-search-wrap{position:relative;grid-column:1/-1}
.product-search{width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:8px;font-size:14px;background:var(--bg);color:var(--text);box-sizing:border-box}
.product-search:focus{outline:none;border-color:#1a7f4b}
.product-dropdown{position:absolute;top:calc(100% + 4px);left:0;right:0;background:var(--card);border:1.5px solid var(--border);border-radius:8px;max-height:200px;overflow-y:auto;z-index:300;display:none;box-shadow:0 8px 24px rgba(0,0,0,.12)}
.product-option{padding:10px 14px;cursor:pointer;font-size:13px;display:flex;justify-content:space-between;gap:12px}
.product-option:hover{background:var(--hover)}
.product-option .pname{font-weight:600;color:var(--text)}
.product-option .pstock{font-size:11px;color:var(--muted)}

/* Item list */
.items-list{grid-column:1/-1;display:flex;flex-direction:column;gap:8px;margin-top:4px}
.item-row{display:grid;grid-template-columns:1fr 90px 100px auto;gap:8px;align-items:center;background:var(--hover);border-radius:8px;padding:10px 12px}
.item-name{font-size:13px;font-weight:600;color:var(--text)}
.item-sub{font-size:11px;color:var(--muted)}
.item-qty{width:100%;padding:6px 8px;border:1.5px solid var(--border);border-radius:6px;font-size:13px;background:var(--bg);color:var(--text);text-align:center}
.item-price{width:100%;padding:6px 8px;border:1.5px solid var(--border);border-radius:6px;font-size:13px;background:var(--bg);color:var(--text)}
.item-del{background:none;border:none;color:#dc2626;cursor:pointer;padding:4px;display:flex}

/* Action row in orders table */
.action-cell{display:flex;gap:6px;align-items:center}

/* Info panel for an order */
.order-detail-toast{display:none;position:fixed;bottom:24px;right:24px;background:#1a7f4b;color:#fff;padding:14px 20px;border-radius:10px;font-size:13px;font-weight:600;z-index:500;box-shadow:0 4px 20px rgba(0,0,0,.2)}

@media(max-width:640px){
  .form-grid{grid-template-columns:1fr}
  .item-row{grid-template-columns:1fr 70px 80px auto}
}
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
    <div class="topbar-title">Commandes fournisseurs</div>
  </div>

  <div class="content">
    <div class="page-header">
      <div>
        <div class="page-title">Bons de commande</div>
        <div class="page-sub"><?= count($orders) ?> commande<?= count($orders)!==1?'s':'' ?></div>
      </div>
      <button class="btn btn-primary" onclick="openModal()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouvelle commande
      </button>
    </div>

    <!-- Orders table -->
    <div style="background:var(--card);border:1px solid var(--border);border-radius:12px;overflow-x:auto">
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
            <td style="color:var(--muted)"><?= date('d/m/Y', strtotime($o['created_at'])) ?></td>
            <td>
              <div class="action-cell">
                <?php if (in_array($o['status'], ['shipped', 'confirmed'])): ?>
                  <button class="btn btn-sm btn-primary" onclick="markDelivered(<?= $o['id'] ?>, this)">Livré ✓</button>
                <?php elseif ($o['status'] === 'sent'): ?>
                  <span style="font-size:11px;color:var(--muted)">En attente fournisseur</span>
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
    <button class="modal-close" onclick="closeModal()">✕</button>
    <div class="modal-title">Nouvelle commande fournisseur</div>

    <form id="orderForm" onsubmit="submitOrder(event)">
      <div class="form-grid">

        <!-- Supplier -->
        <div class="field-group">
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

        <!-- Email -->
        <div class="field-group">
          <label>Email fournisseur *</label>
          <input type="email" id="supplierEmail" placeholder="contact@fournisseur.com" required>
        </div>

        <!-- Expected date -->
        <div class="field-group">
          <label>Livraison souhaitée</label>
          <input type="date" id="requestedDate" min="<?= date('Y-m-d') ?>">
        </div>

        <!-- Notes -->
        <div class="field-group">
          <label>Notes</label>
          <input type="text" id="orderNotes" placeholder="Instructions particulières…">
        </div>

        <hr class="divider">

        <!-- Product picker -->
        <div class="product-search-wrap">
          <label style="display:block;margin-bottom:6px">Ajouter des produits</label>
          <input type="text" class="product-search" id="productSearch" placeholder="Rechercher un produit…" autocomplete="off" oninput="filterProducts(this.value)" onfocus="showDropdown()" onblur="setTimeout(hideDropdown,200)">
          <div class="product-dropdown" id="productDropdown"></div>
        </div>

        <!-- Item list -->
        <div class="items-list" id="itemsList"></div>

      </div>

      <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:28px">
        <button type="button" class="btn btn-outline" onclick="closeModal()">Annuler</button>
        <button type="submit" class="btn btn-primary" id="submitBtn">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          Créer et envoyer
        </button>
      </div>
    </form>
  </div>
</div>

<div class="order-detail-toast" id="toast"></div>

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
  const matches = lq.length < 2 ? [] : INVENTORY.filter(p =>
    p.product_name?.toLowerCase().includes(lq) || p.category?.toLowerCase().includes(lq)
  ).slice(0, 20);

  dd.innerHTML = matches.map(p => `
    <div class="product-option" onmousedown="addItem(${JSON.stringify(p).replace(/"/g,'&quot;')})">
      <div><div class="pname">${p.product_name}</div><div class="pstock">${p.category || ''}</div></div>
      <div style="text-align:right"><div class="pname">${p.stock_quantity ?? '?'} unités</div><div class="pstock">${p.unit_cost ? Math.round(p.unit_cost).toLocaleString()+' CFA' : ''}</div></div>
    </div>`).join('') || '<div style="padding:14px;color:var(--muted);font-size:13px">Aucun résultat</div>';

  dd.style.display = lq.length >= 2 ? 'block' : 'none';
}
function showDropdown() { if (document.getElementById('productSearch').value.length >= 2) filterProducts(document.getElementById('productSearch').value); }
function hideDropdown() { document.getElementById('productDropdown').style.display = 'none'; }

function addItem(p) {
  if (orderItems.some(i => i.product_id === p.product_id)) return;
  orderItems.push({ product_id: p.product_id, product_name: p.product_name,
    stock_quantity: p.stock_quantity, unit_cost: p.unit_cost, quantity: 1 });
  renderItems();
  document.getElementById('productSearch').value = '';
  hideDropdown();
}

function removeItem(id) {
  orderItems = orderItems.filter(i => i.product_id !== id);
  renderItems();
}

function renderItems() {
  const list = document.getElementById('itemsList');
  if (!orderItems.length) { list.innerHTML = ''; return; }
  list.innerHTML = orderItems.map(item => `
    <div class="item-row">
      <div>
        <div class="item-name">${item.product_name}</div>
        <div class="item-sub">Stock: ${item.stock_quantity ?? '?'} · ${item.unit_cost ? Math.round(item.unit_cost).toLocaleString()+' CFA/u' : ''}</div>
      </div>
      <input class="item-qty" type="number" min="1" value="${item.quantity}"
        onchange="updateQty('${item.product_id}', this.value)" placeholder="Qté">
      <input class="item-price" type="number" min="0" value="${item.unit_cost || ''}"
        onchange="updatePrice('${item.product_id}', this.value)" placeholder="Prix unit.">
      <button class="item-del" onclick="removeItem('${item.product_id}')" type="button">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>`).join('');
}

function updateQty(id, v) { const i = orderItems.find(x => x.product_id === id); if (i) i.quantity = parseInt(v)||1; }
function updatePrice(id, v) { const i = orderItems.find(x => x.product_id === id); if (i) i.unit_cost = parseFloat(v)||null; }

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
