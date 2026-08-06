<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/db.php';
ai_check_auth();
$user       = ai_user();
$activePage = 'import';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>digiMind — Importer des données</title>
<style>
<?php include __DIR__ . '/includes/common.css.php'; ?>

/* ── Page-specific ─────────────────────────────────────────────── */
.content { padding:24px 28px; flex:1; }

.page-header { margin-bottom:24px; }
.page-title  { font-size:20px; font-weight:700; color:var(--text); }
.page-sub    { font-size:13px; color:var(--text-3); margin-top:4px; }

/* Step pills */
.steps { display:flex; align-items:center; gap:0; margin-bottom:28px; }
.step  { display:flex; align-items:center; gap:8px; }
.step-num { width:26px; height:26px; border-radius:50%; display:grid; place-items:center;
            font-size:12px; font-weight:700; background:var(--border-lt); color:var(--text-3);
            flex-shrink:0; transition:background .2s, color .2s; }
.step-num.done   { background:var(--green); color:#fff; }
.step-num.active { background:var(--green-dk); color:#fff; }
.step-label { font-size:12.5px; color:var(--text-3); font-weight:500; }
.step-label.active { color:var(--text); font-weight:600; }
.step-sep { width:32px; height:1px; background:var(--border); margin:0 4px; flex-shrink:0; }

/* Type selector */
.type-row { display:flex; gap:12px; margin-bottom:20px; }
.type-card { flex:1; border:2px solid var(--border); border-radius:10px; padding:18px;
             cursor:pointer; transition:border-color .15s, background .15s; background:var(--surface); }
.type-card:hover { border-color:#9ca3af; }
.type-card.selected { border-color:var(--green); background:var(--green-lt); }
.type-card input[type=radio] { display:none; }
.type-icon { width:36px; height:36px; border-radius:8px; background:var(--surface-alt);
             display:grid; place-items:center; margin-bottom:10px; }
.type-icon svg { width:18px; height:18px; stroke:var(--green-dk); fill:none;
                 stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
.type-name { font-size:14px; font-weight:700; color:var(--text); margin-bottom:4px; }
.type-desc { font-size:12px; color:var(--text-3); line-height:1.5; }

/* Drop zone */
.drop-zone { border:2px dashed var(--border); border-radius:12px; padding:40px 24px;
             text-align:center; cursor:pointer; transition:border-color .2s, background .2s;
             background:var(--surface); position:relative; }
.drop-zone:hover, .drop-zone.drag-over { border-color:var(--green); background:var(--green-lt); }
.drop-zone input[type=file] { position:absolute; inset:0; opacity:0; cursor:pointer; width:100%; height:100%; }
.dz-icon { width:48px; height:48px; margin:0 auto 12px; border-radius:50%;
           background:var(--green-lt); display:grid; place-items:center; }
.dz-icon svg { width:24px; height:24px; stroke:var(--green-dk); fill:none;
               stroke-width:2; stroke-linecap:round; stroke-linejoin:round; }
.dz-title { font-size:15px; font-weight:600; color:var(--text); margin-bottom:6px; }
.dz-sub   { font-size:12.5px; color:var(--text-3); }
.dz-formats { margin-top:10px; display:flex; justify-content:center; gap:6px; flex-wrap:wrap; }
.dz-tag { padding:2px 8px; border-radius:99px; background:var(--surface-alt);
          font-size:11px; font-weight:600; color:var(--text-3); }

/* File info bar */
.file-bar { display:flex; align-items:center; gap:12px; padding:12px 16px;
            background:var(--green-lt); border:1px solid var(--green);
            border-radius:8px; margin-bottom:16px; }
.file-bar svg { width:18px; height:18px; stroke:var(--green-dk); fill:none;
                stroke-width:2; stroke-linecap:round; flex-shrink:0; }
.file-bar-name { font-size:13px; font-weight:600; color:var(--green-dk); flex:1; }
.file-bar-rows { font-size:12px; color:var(--green-dk); opacity:.8; }
.file-bar-clear { margin-left:auto; background:none; border:none; cursor:pointer;
                  color:var(--green-dk); opacity:.7; padding:2px; line-height:1; font-size:16px; }
.file-bar-clear:hover { opacity:1; }

/* Preview table */
.section-label { font-size:11px; font-weight:700; text-transform:uppercase;
                 letter-spacing:.6px; color:var(--text-3); margin-bottom:8px; }
.preview-wrap { overflow-x:auto; border:1px solid var(--border); border-radius:8px;
                margin-bottom:20px; max-height:200px; overflow-y:auto; }
.preview-tbl { width:100%; border-collapse:collapse; font-size:12.5px; min-width:400px; }
.preview-tbl th { padding:8px 12px; text-align:left; font-size:11px; font-weight:700;
                  text-transform:uppercase; letter-spacing:.4px; color:var(--text-3);
                  background:var(--surface-alt); border-bottom:1px solid var(--border);
                  white-space:nowrap; position:sticky; top:0; }
.preview-tbl td { padding:7px 12px; border-bottom:1px solid var(--border-lt);
                  color:var(--text-2); white-space:nowrap; max-width:180px;
                  overflow:hidden; text-overflow:ellipsis; }
.preview-tbl tr:last-child td { border-bottom:none; }

/* Column mapper */
.mapper-grid { display:grid; grid-template-columns:1fr auto 1fr; gap:8px 12px; align-items:center; margin-bottom:20px; }
.mapper-header { font-size:11px; font-weight:700; text-transform:uppercase;
                 letter-spacing:.4px; color:var(--text-3); padding-bottom:4px;
                 border-bottom:1px solid var(--border-lt); margin-bottom:4px; }
.mapper-col-name { font-size:13px; color:var(--text); font-weight:500;
                   background:var(--surface-alt); border:1px solid var(--border);
                   border-radius:6px; padding:7px 10px; white-space:nowrap;
                   overflow:hidden; text-overflow:ellipsis; }
.mapper-arrow { color:var(--text-3); display:flex; align-items:center; }
.mapper-arrow svg { width:14px; height:14px; stroke:currentColor; fill:none;
                    stroke-width:2; stroke-linecap:round; }
.mapper-select { width:100%; padding:7px 10px; border:1px solid var(--border);
                 border-radius:6px; font-size:13px; color:var(--text);
                 background:var(--surface); outline:none; cursor:pointer; }
.mapper-select:focus { border-color:var(--green); }
.mapper-select.mapped { border-color:var(--green); background:var(--green-lt); color:var(--green-dk); }
.mapper-select.required-miss { border-color:var(--red); background:var(--red-lt); }

/* Options row */
.opt-row { display:flex; align-items:center; gap:16px; padding:14px 16px;
           background:var(--surface-alt); border:1px solid var(--border);
           border-radius:8px; margin-bottom:20px; flex-wrap:wrap; }
.opt-label { font-size:13px; color:var(--text); display:flex; align-items:center; gap:7px; cursor:pointer; }
.opt-label input { accent-color:var(--green); width:14px; height:14px; cursor:pointer; }
.opt-note { font-size:12px; color:var(--text-3); }

/* Action row */
.action-row { display:flex; align-items:center; gap:12px; }
.btn-primary { padding:10px 22px; background:var(--green); color:#fff; border:none;
               border-radius:8px; font-size:14px; font-weight:600; cursor:pointer;
               transition:background .15s; display:flex; align-items:center; gap:7px; }
.btn-primary:hover { background:var(--green-dk); }
.btn-primary:disabled { opacity:.5; cursor:not-allowed; }
.btn-primary svg { width:15px; height:15px; stroke:#fff; fill:none; stroke-width:2.5;
                   stroke-linecap:round; stroke-linejoin:round; }
.btn-secondary { padding:10px 18px; background:var(--surface); color:var(--text-2);
                 border:1px solid var(--border); border-radius:8px; font-size:14px;
                 font-weight:500; cursor:pointer; transition:background .15s; }
.btn-secondary:hover { background:var(--surface-alt); }
.import-progress { display:none; align-items:center; gap:8px; font-size:13px; color:var(--text-3); }
.spinner { width:16px; height:16px; border:2px solid var(--border);
           border-top-color:var(--green); border-radius:50%; animation:spin .7s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }

/* Result box */
.result-box { border-radius:10px; padding:18px 20px; }
.result-box.ok  { background:var(--green-lt); border:1px solid var(--green); }
.result-box.err { background:var(--red-lt);   border:1px solid var(--red); }
.result-box.warn { background:var(--amber-lt); border:1px solid var(--amber); }
.result-title { font-size:15px; font-weight:700; margin-bottom:6px; }
.result-box.ok  .result-title { color:var(--green-dk); }
.result-box.err .result-title { color:var(--red); }
.result-box.warn .result-title { color:var(--amber); }
.result-body { font-size:13px; line-height:1.65; }
.result-box.ok  .result-body { color:var(--green-dk); opacity:.85; }
.result-box.err .result-body { color:var(--red); opacity:.85; }
.result-box.warn .result-body { color:var(--amber); opacity:.85; }
.err-list { margin:8px 0 0 16px; font-size:12px; }

/* Tooltip on required badge */
.req-badge { display:inline-block; padding:0 5px; border-radius:3px; font-size:10px;
             font-weight:700; background:var(--red-lt); color:var(--red);
             margin-left:4px; vertical-align:middle; }

/* Help section */
.help-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
             padding:20px 22px; margin-top:24px; }
.help-title { font-size:13px; font-weight:700; color:var(--text); margin-bottom:12px;
              display:flex; align-items:center; gap:7px; }
.help-title svg { width:15px; height:15px; stroke:var(--green); fill:none; stroke-width:2;
                  stroke-linecap:round; stroke-linejoin:round; }
.help-cols { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
.help-col h4 { font-size:11px; font-weight:700; text-transform:uppercase;
               letter-spacing:.5px; color:var(--text-3); margin-bottom:8px; }
.help-field { display:flex; align-items:baseline; gap:6px; padding:5px 0;
              border-bottom:1px solid var(--border-lt); }
.help-field:last-child { border-bottom:none; }
.hf-name { font-size:12.5px; font-weight:600; color:var(--text); width:130px; flex-shrink:0; }
.hf-desc { font-size:12px; color:var(--text-3); }

@media(max-width:768px) {
  .type-row { flex-direction:column; }
  .mapper-grid { grid-template-columns:1fr auto 1fr; gap:6px; }
  .help-cols { grid-template-columns:1fr; }
  .opt-row { flex-direction:column; align-items:flex-start; gap:10px; }
}
</style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<div class="main">
  <?php $pageTitle = 'Importer des données'; include __DIR__ . '/includes/topbar.php'; ?>

  <div class="content">

    <!-- Steps indicator -->
    <div class="steps">
      <div class="step">
        <div class="step-num active" id="s1num">1</div>
        <div class="step-label active" id="s1lbl">Type de données</div>
      </div>
      <div class="step-sep"></div>
      <div class="step">
        <div class="step-num" id="s2num">2</div>
        <div class="step-label" id="s2lbl">Fichier</div>
      </div>
      <div class="step-sep"></div>
      <div class="step">
        <div class="step-num" id="s3num">3</div>
        <div class="step-label" id="s3lbl">Correspondance des colonnes</div>
      </div>
      <div class="step-sep"></div>
      <div class="step">
        <div class="step-num" id="s4num">4</div>
        <div class="step-label" id="s4lbl">Résultat</div>
      </div>
    </div>

    <div class="card" style="padding:24px;">

      <!-- Step 1: Data type -->
      <div id="step1">
        <div class="section-label">Que souhaitez-vous importer ?</div>
        <div class="type-row">
          <label class="type-card selected" id="card-sales">
            <input type="radio" name="dataType" value="sales" checked>
            <div class="type-icon">
              <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            </div>
            <div class="type-name">Données de ventes</div>
            <div class="type-desc">Historique des ventes, transactions, chiffre d'affaires par produit et par date.</div>
          </label>
          <label class="type-card" id="card-inventory">
            <input type="radio" name="dataType" value="inventory">
            <div class="type-icon">
              <svg viewBox="0 0 24 24"><path d="M21 16V8l-9-5-9 5v8l9 5 9-5z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
            </div>
            <div class="type-name">Inventaire / Stock</div>
            <div class="type-desc">Niveaux de stock actuels, prix d'achat, prix de vente, dates de péremption.</div>
          </label>
        </div>
      </div>

      <!-- Step 2: File upload -->
      <div id="step2" style="margin-top:20px;">
        <div class="section-label">Déposez votre fichier</div>
        <div class="drop-zone" id="dropZone">
          <input type="file" id="fileInput" accept=".csv,.xlsx,.xls,.ods,.tsv" />
          <div class="dz-icon">
            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          </div>
          <div class="dz-title">Glissez votre fichier ici ou cliquez pour parcourir</div>
          <div class="dz-sub">Exportez depuis votre logiciel, puis importez ici</div>
          <div class="dz-formats">
            <span class="dz-tag">CSV</span>
            <span class="dz-tag">Excel .xlsx</span>
            <span class="dz-tag">Excel .xls</span>
            <span class="dz-tag">TSV</span>
          </div>
        </div>
      </div>

      <!-- File info bar (hidden until file loaded) -->
      <div id="fileBar" style="display:none; margin-top:16px;">
        <div class="file-bar">
          <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          <span class="file-bar-name" id="fileName">—</span>
          <span class="file-bar-rows" id="fileRows"></span>
          <button class="file-bar-clear" onclick="resetFile()" title="Supprimer">✕</button>
        </div>
      </div>

      <!-- Step 3: Preview + Column mapper (hidden until file loaded) -->
      <div id="step3" style="display:none; margin-top:20px;">
        <div class="section-label">Aperçu des données (5 premières lignes)</div>
        <div class="preview-wrap">
          <table class="preview-tbl" id="previewTable"></table>
        </div>

        <div class="section-label">Correspondance des colonnes
          <span style="font-size:11px;font-weight:400;color:var(--text-3);margin-left:8px;">
            Associez chaque colonne de votre fichier au champ digiMind correspondant
          </span>
        </div>
        <div class="mapper-grid" id="mapperGrid">
          <!-- injected by JS -->
          <div class="mapper-header" style="grid-column:1">Colonne dans votre fichier</div>
          <div></div>
          <div class="mapper-header" style="grid-column:3">Champ digiMind</div>
        </div>

        <!-- Options -->
        <div class="opt-row">
          <label class="opt-label">
            <input type="checkbox" id="replaceRange" checked>
            Remplacer les données existantes pour la même période
          </label>
          <span class="opt-note">Évite les doublons si vous ré-importez le même fichier</span>
        </div>

        <!-- Action -->
        <div class="action-row">
          <button class="btn-primary" id="importBtn" onclick="startImport()">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            Importer
          </button>
          <button class="btn-secondary" onclick="resetFile()">Changer de fichier</button>
          <div class="import-progress" id="importProgress">
            <div class="spinner"></div>
            <span>Importation en cours…</span>
          </div>
        </div>
      </div>

      <!-- Step 4: Result -->
      <div id="step4" style="display:none; margin-top:20px;">
        <div id="resultBox"></div>
        <div style="margin-top:16px;">
          <button class="btn-secondary" onclick="resetAll()">Importer un autre fichier</button>
        </div>
      </div>

    </div><!-- /card -->

    <!-- Help reference -->
    <div class="help-card">
      <div class="help-title">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Champs acceptés — référence
      </div>
      <div class="help-cols">
        <div>
          <h4>Ventes <span style="font-weight:400;text-transform:none;letter-spacing:0;">(ai_sales)</span></h4>
          <?php
          $salesFields = [
            ['sale_date',      'Date de la vente',              true],
            ['product_name',   'Nom du produit',                true],
            ['quantity',       'Quantité vendue',               true],
            ['unit_price',     'Prix unitaire (XAF)',           true],
            ['product_id',     'Référence / code produit',      false],
            ['category',       'Catégorie',                     false],
            ['revenue',        'Montant total (calculé si absent)', false],
            ['cost',           'Coût unitaire',                 false],
            ['source_sale_id', 'Identifiant vente source',      false],
          ];
          foreach ($salesFields as [$n, $d, $req]): ?>
          <div class="help-field">
            <span class="hf-name"><?= $n ?><?php if ($req): ?> <span class="req-badge">req</span><?php endif; ?></span>
            <span class="hf-desc"><?= $d ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <div>
          <h4>Inventaire <span style="font-weight:400;text-transform:none;letter-spacing:0;">(ai_inventory)</span></h4>
          <?php
          $invFields = [
            ['product_name',   'Nom du produit',                true],
            ['stock_quantity', 'Quantité en stock',             true],
            ['snapshot_date',  'Date de l\'inventaire',         false],
            ['product_id',     'Référence / code produit',      false],
            ['category',       'Catégorie',                     false],
            ['unit_cost',      'Prix d\'achat unitaire',        false],
            ['unit_price',     'Prix de vente unitaire',        false],
            ['expiry_date',    'Date de péremption',            false],
          ];
          foreach ($invFields as [$n, $d, $req]): ?>
          <div class="help-field">
            <span class="hf-name"><?= $n ?><?php if ($req): ?> <span class="req-badge">req</span><?php endif; ?></span>
            <span class="hf-desc"><?= $d ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<!-- SheetJS for Excel support -->
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script>
// Topbar stubs (no live analytics needed on this page)
function load() {}
const aiDot = document.getElementById('aiDot');
const aiStatus = document.getElementById('aiStatus');
if (aiStatus) aiStatus.textContent = 'Prêt';

// ── Schema definitions ────────────────────────────────────────────────────
const SCHEMAS = {
  sales: {
    required: ['sale_date', 'product_name', 'quantity', 'unit_price'],
    optional: ['product_id', 'category', 'revenue', 'cost', 'source_sale_id'],
    labels: {
      sale_date:      'Date de vente',
      product_name:   'Nom produit',
      product_id:     'Référence produit',
      category:       'Catégorie',
      quantity:       'Quantité',
      unit_price:     'Prix unitaire',
      revenue:        'Montant total',
      cost:           'Coût unitaire',
      source_sale_id: 'ID vente source',
    }
  },
  inventory: {
    required: ['product_name', 'stock_quantity'],
    optional: ['product_id', 'category', 'snapshot_date', 'unit_cost', 'unit_price', 'expiry_date'],
    labels: {
      product_name:   'Nom produit',
      product_id:     'Référence produit',
      category:       'Catégorie',
      stock_quantity: 'Stock',
      snapshot_date:  'Date inventaire',
      unit_cost:      'Prix achat',
      unit_price:     'Prix vente',
      expiry_date:    'Date péremption',
    }
  }
};

// ── Auto-suggest heuristics ───────────────────────────────────────────────
const HINTS = {
  sales: {
    sale_date:      ['date', 'jour', 'day', 'vente', 'sale', 'period'],
    product_name:   ['produit', 'product', 'médicament', 'article', 'nom', 'name', 'libelle', 'désignation', 'designation'],
    product_id:     ['référence', 'reference', 'ref', 'code', 'sku', 'id produit', 'codif'],
    category:       ['catégorie', 'categorie', 'cat', 'category', 'famille', 'type'],
    quantity:       ['quantité', 'quantite', 'qty', 'qté', 'qte', 'nb', 'nombre'],
    unit_price:     ['prix unit', 'pu', 'price', 'tarif', 'prix de vente', 'pv'],
    revenue:        ['ca', 'montant', 'total', 'revenue', 'chiffre', 'recette'],
    cost:           ['coût', 'cout', 'cost', 'prix achat', 'pa'],
    source_sale_id: ['ticket', 'facture', 'invoice', 'id vente', 'sale id'],
  },
  inventory: {
    product_name:   ['produit', 'product', 'médicament', 'article', 'nom', 'name', 'libelle'],
    product_id:     ['référence', 'reference', 'ref', 'code', 'sku'],
    category:       ['catégorie', 'categorie', 'cat', 'famille', 'type'],
    stock_quantity: ['stock', 'quantité', 'qté', 'qty', 'inventaire', 'solde'],
    snapshot_date:  ['date', 'jour', 'snapshot', 'inventaire'],
    unit_cost:      ['prix achat', 'pa', 'coût', 'cout', 'cost'],
    unit_price:     ['prix vente', 'pv', 'prix', 'tarif'],
    expiry_date:    ['péremption', 'peremption', 'dlc', 'expiry', 'expiration', 'dluo'],
  }
};

// ── State ─────────────────────────────────────────────────────────────────
let parsedHeaders = [];
let parsedRows    = [];
let dataType      = 'sales';

// ── Type card toggle ──────────────────────────────────────────────────────
document.querySelectorAll('input[name=dataType]').forEach(r => {
  r.addEventListener('change', () => {
    document.getElementById('card-sales').classList.toggle('selected', r.value === 'sales');
    document.getElementById('card-inventory').classList.toggle('selected', r.value === 'inventory');
    dataType = r.value;
    if (parsedHeaders.length) renderMapper();
  });
});
document.querySelectorAll('.type-card').forEach(c => {
  c.addEventListener('click', () => {
    const inp = c.querySelector('input');
    inp.checked = true;
    inp.dispatchEvent(new Event('change'));
  });
});

// ── Drop zone ─────────────────────────────────────────────────────────────
const dz = document.getElementById('dropZone');
const fi = document.getElementById('fileInput');

dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag-over'); });
dz.addEventListener('dragleave', () => dz.classList.remove('drag-over'));
dz.addEventListener('drop', e => { e.preventDefault(); dz.classList.remove('drag-over'); handleFiles(e.dataTransfer.files); });
fi.addEventListener('change', () => handleFiles(fi.files));

function handleFiles(files) {
  if (!files.length) return;
  const file = files[0];
  const reader = new FileReader();
  reader.onload = e => parseFile(file.name, e.target.result);
  if (file.name.endsWith('.csv') || file.name.endsWith('.tsv')) {
    reader.readAsText(file, 'UTF-8');
  } else {
    reader.readAsArrayBuffer(file);
  }
}

function parseFile(name, data) {
  let wb;
  try {
    if (typeof data === 'string') {
      wb = XLSX.read(data, { type: 'string', raw: false });
    } else {
      wb = XLSX.read(new Uint8Array(data), { type: 'array' });
    }
    const ws      = wb.Sheets[wb.SheetNames[0]];
    const allRows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '', raw: false });
    if (!allRows.length) { alert('Le fichier semble vide.'); return; }

    // Find first non-empty row as header
    let headerIdx = 0;
    for (let i = 0; i < Math.min(5, allRows.length); i++) {
      if (allRows[i].some(c => String(c).trim() !== '')) { headerIdx = i; break; }
    }
    parsedHeaders = allRows[headerIdx].map(c => String(c).trim());
    parsedRows    = allRows.slice(headerIdx + 1).filter(r => r.some(c => String(c).trim() !== ''));

    document.getElementById('fileName').textContent = name;
    document.getElementById('fileRows').textContent  = parsedRows.length.toLocaleString('fr') + ' lignes';
    document.getElementById('fileBar').style.display  = 'flex';
    document.getElementById('dropZone').style.display = 'none';

    renderPreview();
    renderMapper();
    document.getElementById('step3').style.display = 'block';
    setStep(3);
  } catch (err) {
    alert('Impossible de lire ce fichier. Vérifiez qu\'il s\'agit d\'un CSV ou d\'un fichier Excel valide.\n\n' + err.message);
  }
}

// ── Preview ───────────────────────────────────────────────────────────────
function renderPreview() {
  const preview = parsedRows.slice(0, 5);
  const tbl = document.getElementById('previewTable');
  const hdr = parsedHeaders.map(h => `<th>${esc(h)}</th>`).join('');
  const rows = preview.map(r => '<tr>' + parsedHeaders.map((_, i) => `<td>${esc(String(r[i] ?? ''))}</td>`).join('') + '</tr>').join('');
  tbl.innerHTML = `<thead><tr>${hdr}</tr></thead><tbody>${rows}</tbody>`;
}

// ── Mapper ────────────────────────────────────────────────────────────────
function autoSuggest(colName, type) {
  const lower = colName.toLowerCase();
  const hints = HINTS[type];
  for (const [field, keywords] of Object.entries(hints)) {
    if (keywords.some(k => lower.includes(k))) return field;
  }
  return '';
}

function renderMapper() {
  const schema = SCHEMAS[dataType];
  const allFields = [...schema.required, ...schema.optional];
  const grid = document.getElementById('mapperGrid');

  // Keep header row, replace rest
  const headers = grid.querySelector('.mapper-header');
  const headers2 = grid.children[2];

  grid.innerHTML = '';

  // Re-add headers
  const h1 = document.createElement('div');
  h1.className = 'mapper-header'; h1.textContent = 'Colonne dans votre fichier';
  const hArr = document.createElement('div');
  const h2 = document.createElement('div');
  h2.className = 'mapper-header'; h2.textContent = 'Champ digiMind';
  grid.appendChild(h1); grid.appendChild(hArr); grid.appendChild(h2);

  const opts = '<option value="">— Ignorer —</option>'
    + allFields.map(f => {
        const req = schema.required.includes(f) ? ' ★' : '';
        return `<option value="${f}">${schema.labels[f] || f}${req}</option>`;
      }).join('');

  parsedHeaders.forEach((col, idx) => {
    const suggested = autoSuggest(col, dataType);

    const colEl = document.createElement('div');
    colEl.className = 'mapper-col-name';
    colEl.textContent = col || `Colonne ${idx + 1}`;

    const arrow = document.createElement('div');
    arrow.className = 'mapper-arrow';
    arrow.innerHTML = '<svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';

    const sel = document.createElement('select');
    sel.className = 'mapper-select' + (suggested ? ' mapped' : '');
    sel.id = `map_${idx}`;
    sel.innerHTML = opts;
    sel.value = suggested;
    sel.addEventListener('change', function () {
      this.className = 'mapper-select' + (this.value ? ' mapped' : '');
    });

    grid.appendChild(colEl);
    grid.appendChild(arrow);
    grid.appendChild(sel);
  });
}

// ── Import ────────────────────────────────────────────────────────────────
function startImport() {
  const schema = SCHEMAS[dataType];

  // Build mapping: {fieldName: colIndex}
  const mapping = {};
  parsedHeaders.forEach((_, idx) => {
    const sel = document.getElementById(`map_${idx}`);
    if (sel && sel.value) mapping[sel.value] = idx;
  });

  // Check required fields
  const missing = schema.required.filter(f => !(f in mapping));
  if (missing.length) {
    const labels = missing.map(f => schema.labels[f] || f);
    // Highlight
    parsedHeaders.forEach((_, idx) => {
      const sel = document.getElementById(`map_${idx}`);
      if (sel) sel.classList.remove('required-miss');
    });
    alert('Champs obligatoires non assignés :\n• ' + labels.join('\n• ') + '\n\nAssignez ces colonnes avant d\'importer.');
    return;
  }

  // Map rows
  const mapped = parsedRows.map(r => {
    const obj = {};
    for (const [field, idx] of Object.entries(mapping)) {
      obj[field] = String(r[idx] ?? '').trim();
    }
    return obj;
  }).filter(r => Object.values(r).some(v => v !== ''));

  if (!mapped.length) { alert('Aucune ligne de données à importer.'); return; }

  // Show progress
  document.getElementById('importBtn').disabled = true;
  document.getElementById('importProgress').style.display = 'flex';

  const payload = {
    type: dataType,
    rows: mapped,
    replace_range: document.getElementById('replaceRange').checked,
  };

  fetch('/analytics/import-handler.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
  .then(r => r.json())
  .then(showResult)
  .catch(err => showResult({ ok: false, error: err.message }));
}

function showResult(data) {
  document.getElementById('importBtn').disabled = false;
  document.getElementById('importProgress').style.display = 'none';
  document.getElementById('step3').style.display = 'none';
  document.getElementById('step4').style.display = 'block';
  setStep(4);

  const box = document.getElementById('resultBox');
  if (data.ok) {
    const warn = data.skipped > 0;
    box.className = 'result-box ' + (warn ? 'warn' : 'ok');
    box.innerHTML = `
      <div class="result-title">${warn ? 'Importation terminée avec avertissements' : 'Importation réussie'}</div>
      <div class="result-body">
        <strong>${(data.inserted || 0).toLocaleString('fr')}</strong> ligne(s) importée(s)<br>
        ${data.skipped ? `<strong>${data.skipped}</strong> ligne(s) ignorée(s) (données invalides)` : ''}
        ${data.replaced ? `<br>Données existantes pour la même période remplacées.` : ''}
        ${data.errors?.length ? `<ul class="err-list">${data.errors.slice(0,5).map(e => `<li>${esc(e)}</li>`).join('')}</ul>` : ''}
      </div>`;
  } else {
    box.className = 'result-box err';
    box.innerHTML = `
      <div class="result-title">Erreur lors de l'importation</div>
      <div class="result-body">${esc(data.error || 'Erreur inconnue')}</div>`;
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────
function setStep(n) {
  for (let i = 1; i <= 4; i++) {
    const num = document.getElementById(`s${i}num`);
    const lbl = document.getElementById(`s${i}lbl`);
    if (i < n)  { num.className = 'step-num done';   num.innerHTML = '✓'; lbl.className = 'step-label'; }
    else if (i === n) { num.className = 'step-num active'; num.textContent = i; lbl.className = 'step-label active'; }
    else { num.className = 'step-num'; num.textContent = i; lbl.className = 'step-label'; }
  }
}

function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function resetFile() {
  parsedHeaders = []; parsedRows = [];
  document.getElementById('fileInput').value = '';
  document.getElementById('fileBar').style.display   = 'none';
  document.getElementById('dropZone').style.display  = '';
  document.getElementById('step3').style.display     = 'none';
  document.getElementById('step4').style.display     = 'none';
  document.getElementById('importBtn').disabled      = false;
  document.getElementById('importProgress').style.display = 'none';
  setStep(2);
}

function resetAll() {
  resetFile();
  setStep(1);
}

// Init
setStep(1);
</script>
</body>
</html>
