<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/crypto.php';
ai_check_auth();
$user = ai_user();

if ($user['role'] !== 'admin') {
    http_response_code(403);
    die('Accès réservé aux administrateurs.');
}

$db  = analytics_db();
$pid = $user['pharmacy_id'];
$msg = '';
$msgType = 'ok';

// Default schema map (matches DigiPharm ERP schema — editable)
$defaultSchema = [
    'sales_table'          => 'sale',
    'sales_id_col'         => 'id',
    'sales_date_col'       => 'saleDate',
    'items_table'          => 'saleitem',
    'items_sale_fk'        => 'saleId',
    'items_product_fk'     => 'productId',
    'items_quantity_col'   => 'quantity',
    'items_unit_price_col' => 'unitPrice',
    'products_table'       => 'product',
    'products_id_col'      => 'id',
    'products_name_col'    => 'name',
    'products_stock_col'   => 'stock',
    'products_cost_col'    => 'purchasePrice',
    'products_price_col'   => 'sellingPrice',
    'products_expiry_col'  => 'expiryDate',
];

// Load existing source
$source = $db->prepare("SELECT * FROM ai_data_sources WHERE pharmacy_id = ? LIMIT 1");
$source->execute([$pid]);
$src = $source->fetch() ?: [];
$schema = !empty($src['schema_map']) ? json_decode($src['schema_map'], true) : $defaultSchema;

// Handle form save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'save') {
        $connType   = $_POST['conn_type']  ?? 'ssh';
        $sshHost    = trim($_POST['ssh_host']    ?? '');
        $sshPort    = (int)($_POST['ssh_port']   ?? 22);
        $sshUser    = trim($_POST['ssh_user']    ?? 'root');
        $sshPass    = trim($_POST['ssh_password'] ?? '');

        $dbHost     = trim($_POST['db_host'] ?? '127.0.0.1');
        $dbPort     = (int)($_POST['db_port'] ?? 3306);
        $dbName     = trim($_POST['db_name']  ?? '');
        $dbUser     = trim($_POST['db_user']  ?? 'root');
        $dbPass     = trim($_POST['db_password'] ?? '');

        // Schema map from POST
        $newSchema = [];
        foreach ($defaultSchema as $key => $_) {
            $newSchema[$key] = trim($_POST["schema_$key"] ?? $defaultSchema[$key]);
        }

        // Encrypt passwords (only update if new value provided)
        $encSshPass = $sshPass ? ai_encrypt($sshPass) : ($src['ssh_password'] ?? '');
        $encDbPass  = $dbPass  ? ai_encrypt($dbPass)  : ($src['db_password']  ?? '');

        if (empty($src)) {
            $db->prepare("
                INSERT INTO ai_data_sources
                    (pharmacy_id, conn_type, ssh_host, ssh_port, ssh_user, ssh_password,
                     db_host, db_port, db_name, db_user, db_password, schema_map)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
            ")->execute([
                $pid, $connType, $sshHost, $sshPort, $sshUser, $encSshPass,
                $dbHost, $dbPort, $dbName, $dbUser, $encDbPass,
                json_encode($newSchema)
            ]);
        } else {
            $db->prepare("
                UPDATE ai_data_sources SET
                    conn_type=?, ssh_host=?, ssh_port=?, ssh_user=?, ssh_password=?,
                    db_host=?, db_port=?, db_name=?, db_user=?, db_password=?,
                    schema_map=?, updated_at=NOW()
                WHERE pharmacy_id=?
            ")->execute([
                $connType, $sshHost, $sshPort, $sshUser, $encSshPass,
                $dbHost, $dbPort, $dbName, $dbUser, $encDbPass,
                json_encode($newSchema), $pid
            ]);
        }

        // Reload
        $source->execute([$pid]);
        $src = $source->fetch() ?: [];
        $schema = json_decode($src['schema_map'], true);
        $msg = 'Configuration sauvegardée.';
    }
}

// Reload for display
$source->execute([$pid]);
$src = $source->fetch() ?: [];
if (!empty($src['schema_map'])) $schema = json_decode($src['schema_map'], true);

$initials = strtoupper(substr($user['display_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DigiPharm AI — Paramètres source de données</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --green:#1a7f4b; --green-dk:#155e38; --green-lt:#e8f5ee;
  --red:#dc2626; --red-lt:#fee2e2; --amber:#d97706; --amber-lt:#fef3c7;
  --border:#dadce0; --border-lt:#f0f0f0;
  --text:#111827; --text-2:#4b5563; --text-3:#9ca3af;
  --surface:#ffffff; --surface-alt:#f8f9fa; --bg:#f3f4f6;
  --sidebar-w:240px; --header-h:56px; --radius:10px;
}
body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; }

/* sidebar (same as index.php) */
.sidebar { width:var(--sidebar-w); min-height:100vh; background:var(--surface); border-right:1px solid var(--border); display:flex; flex-direction:column; position:fixed; top:0; left:0; bottom:0; z-index:100; }
.sidebar-logo { padding:18px 20px; display:flex; align-items:center; gap:10px; border-bottom:1px solid var(--border-lt); }
.logo-icon { width:32px; height:32px; background:var(--green); border-radius:7px; display:grid; place-items:center; }
.logo-icon svg { width:18px; height:18px; stroke:#fff; fill:none; stroke-width:2.2; stroke-linecap:round; stroke-linejoin:round; }
.logo-text { font-size:15px; font-weight:700; color:var(--text); letter-spacing:-.3px; }
.logo-text span { color:var(--green); }
.sidebar-pharmacy { padding:12px 20px; font-size:11px; font-weight:600; color:var(--text-3); text-transform:uppercase; letter-spacing:.6px; border-bottom:1px solid var(--border-lt); }
nav { flex:1; padding:8px 0; }
.nav-section { padding:16px 20px 4px; font-size:10px; font-weight:700; color:var(--text-3); text-transform:uppercase; letter-spacing:.6px; }
.nav-link { display:flex; align-items:center; gap:10px; padding:8px 14px; margin:1px 8px; border-radius:8px; font-size:13.5px; color:var(--text-2); text-decoration:none; transition:background .12s,color .12s; }
.nav-link svg { width:16px; height:16px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; }
.nav-link:hover { background:var(--surface-alt); color:var(--text); }
.nav-link.active { background:var(--green-lt); color:var(--green-dk); font-weight:600; }
.sidebar-footer { padding:14px 16px; border-top:1px solid var(--border-lt); display:flex; align-items:center; gap:10px; }
.avatar { width:32px; height:32px; background:var(--green); border-radius:50%; display:grid; place-items:center; font-size:13px; font-weight:700; color:#fff; flex-shrink:0; }
.avatar-info { flex:1; min-width:0; }
.avatar-name { font-size:13px; font-weight:600; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.avatar-role { font-size:11px; color:var(--text-3); }
.logout-btn { color:var(--text-3); text-decoration:none; font-size:11px; }
.logout-btn:hover { color:var(--red); }

/* main */
.main { margin-left:var(--sidebar-w); flex:1; display:flex; flex-direction:column; }
.topbar { height:var(--header-h); background:var(--surface); border-bottom:1px solid var(--border); padding:0 28px; display:flex; align-items:center; justify-content:space-between; }
.topbar-title { font-size:16px; font-weight:700; }
.content { padding:24px 28px; max-width:860px; }

/* cards */
.card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); margin-bottom:20px; }
.card-header { padding:16px 20px; border-bottom:1px solid var(--border-lt); display:flex; align-items:center; gap:10px; }
.card-header-icon { width:30px; height:30px; border-radius:7px; display:grid; place-items:center; background:var(--green-lt); }
.card-header-icon svg { width:15px; height:15px; stroke:var(--green); fill:none; stroke-width:2.2; stroke-linecap:round; stroke-linejoin:round; }
.card-title { font-size:14px; font-weight:700; }
.card-desc  { font-size:12px; color:var(--text-3); margin-top:1px; }
.card-body  { padding:20px; }

/* tabs */
.tabs { display:flex; gap:0; border-bottom:1px solid var(--border); margin:-20px -20px 20px; padding:0 20px; }
.tab { padding:10px 16px; font-size:13px; font-weight:500; color:var(--text-3); cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; transition:color .12s,border-color .12s; }
.tab.active { color:var(--green); border-color:var(--green); font-weight:600; }
.tab-panel { display:none; }
.tab-panel.active { display:block; }

/* form */
.field { margin-bottom:16px; }
.field label { display:block; font-size:12.5px; font-weight:600; color:var(--text-2); margin-bottom:5px; }
.field input, .field select {
  width:100%; padding:9px 12px; border:1px solid var(--border); border-radius:8px;
  font-size:13.5px; color:var(--text); background:#fff; outline:none; transition:border-color .15s;
}
.field input:focus, .field select:focus { border-color:var(--green); }
.field .hint { font-size:11.5px; color:var(--text-3); margin-top:4px; }
.field input[type=password] { font-family:monospace; }
.field .placeholder-hint { font-size:11px; color:var(--text-3); margin-top:3px; font-style:italic; }

.grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.grid-3 { display:grid; grid-template-columns:2fr 1fr 1fr; gap:16px; }

/* buttons */
.btn { padding:9px 18px; border-radius:8px; font-size:13.5px; font-weight:600; cursor:pointer; border:none; transition:background .15s; }
.btn-primary { background:var(--green); color:#fff; }
.btn-primary:hover { background:var(--green-dk); }
.btn-outline { background:var(--surface); color:var(--text-2); border:1px solid var(--border); }
.btn-outline:hover { background:var(--surface-alt); color:var(--text); }
.btn-test { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
.btn-test:hover { background:#dbeafe; }
.btn-sync { background:#f0faf4; color:var(--green-dk); border:1px solid #bbf7d0; }
.btn-sync:hover { background:var(--green-lt); }

.btn-row { display:flex; gap:10px; align-items:center; margin-top:4px; flex-wrap:wrap; }

/* notice */
.notice { padding:11px 15px; border-radius:8px; font-size:13px; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
.notice.ok   { background:var(--green-lt); color:var(--green-dk); border:1px solid #bbf7d0; }
.notice.error { background:var(--red-lt);  color:var(--red);     border:1px solid #fecaca; }
.notice.warn  { background:var(--amber-lt); color:#92400e;        border:1px solid #fde68a; }
.notice svg { width:15px; height:15px; stroke:currentColor; fill:none; stroke-width:2.5; stroke-linecap:round; flex-shrink:0; }

/* status pill */
.status-row { display:flex; align-items:center; gap:8px; padding:10px 14px; background:var(--surface-alt); border-radius:8px; font-size:13px; margin-bottom:16px; }
.dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.dot.green { background:#22c55e; }
.dot.red   { background:var(--red); }
.dot.gray  { background:var(--text-3); }

/* schema table */
.schema-table { width:100%; border-collapse:collapse; font-size:13px; }
.schema-table th { text-align:left; padding:8px 10px; font-size:11px; font-weight:700; color:var(--text-3); text-transform:uppercase; letter-spacing:.4px; border-bottom:1px solid var(--border); }
.schema-table td { padding:6px 10px; border-bottom:1px solid var(--border-lt); }
.schema-table td:first-child { color:var(--text-3); font-family:monospace; font-size:12px; }
.schema-table input { width:100%; padding:5px 8px; border:1px solid var(--border); border-radius:6px; font-size:13px; font-family:monospace; }
.schema-table input:focus { border-color:var(--green); outline:none; }

#testResult { margin-top:12px; display:none; }
#syncResult  { margin-top:12px; display:none; }
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon"><svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
    <div class="logo-text">DigiPharm<span> AI</span></div>
  </div>
  <div class="sidebar-pharmacy"><?= htmlspecialchars($user['pharmacy_name']) ?></div>
  <nav>
    <div class="nav-section">Analyse</div>
    <a href="/analytics/" class="nav-link">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Vue d'ensemble
    </a>
    <div class="nav-section" style="margin-top:8px">Données</div>
    <a href="/analytics/sync.php" class="nav-link">
      <svg viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>Synchronisation
    </a>
    <a href="/analytics/settings.php" class="nav-link active">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Paramètres
    </a>
  </nav>
  <div class="sidebar-footer">
    <div class="avatar"><?= $initials ?></div>
    <div class="avatar-info">
      <div class="avatar-name"><?= htmlspecialchars($user['display_name']) ?></div>
      <div class="avatar-role"><?= $user['role'] === 'admin' ? 'Administrateur' : 'Lecteur' ?></div>
    </div>
    <a href="/analytics/logout.php" class="logout-btn" title="Déconnexion">✕</a>
  </div>
</aside>

<div class="main">
  <div class="topbar">
    <div class="topbar-title">Source de données</div>
  </div>

  <div class="content">

    <?php if ($msg): ?>
    <div class="notice ok">
      <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
      <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <!-- Connection status -->
    <?php if (!empty($src)): ?>
    <div class="status-row">
      <?php if ($src['last_test_ok'] === null): ?>
        <div class="dot gray"></div> <span>Source configurée — connexion jamais testée</span>
      <?php elseif ($src['last_test_ok']): ?>
        <div class="dot green"></div>
        <span>Connexion OK</span>
        <span style="color:var(--text-3);font-size:12px;margin-left:4px">· Testé le <?= date('d/m/Y H:i', strtotime($src['last_tested_at'])) ?></span>
      <?php else: ?>
        <div class="dot red"></div>
        <span style="color:var(--red)">Connexion échouée</span>
        <span style="color:var(--text-3);font-size:12px;margin-left:4px">· <?= htmlspecialchars($src['last_test_error'] ?? '') ?></span>
      <?php endif; ?>
      <?php if (!empty($src['last_synced_at'])): ?>
        <span style="margin-left:auto;color:var(--text-3);font-size:12px">
          Dernier sync : <?= date('d/m/Y H:i', strtotime($src['last_synced_at'])) ?>
          (<?= number_format($src['last_sync_rows']) ?> lignes)
        </span>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="POST" id="settingsForm">
      <input type="hidden" name="action" value="save">

      <!-- Connection card -->
      <div class="card">
        <div class="card-header">
          <div class="card-header-icon">
            <svg viewBox="0 0 24 24"><path d="M9 3H5a2 2 0 0 0-2 2v4"/><path d="M9 21H5a2 2 0 0 1-2-2v-4"/><path d="M15 3h4a2 2 0 0 1 2 2v4"/><path d="M15 21h4a2 2 0 0 0 2-2v-4"/><rect x="7" y="8" width="10" height="8" rx="1"/></svg>
          </div>
          <div>
            <div class="card-title">Connexion au serveur source</div>
            <div class="card-desc">Serveur hébergeant la base de données de la pharmacie</div>
          </div>
        </div>
        <div class="card-body">

          <div class="field" style="margin-bottom:20px">
            <label>Mode de connexion</label>
            <select name="conn_type" id="connType" onchange="toggleConnType()">
              <option value="ssh" <?= ($src['conn_type'] ?? 'ssh') === 'ssh' ? 'selected' : '' ?>>
                SSH tunnel (recommandé — MySQL non exposé)
              </option>
              <option value="direct" <?= ($src['conn_type'] ?? '') === 'direct' ? 'selected' : '' ?>>
                TCP direct (MySQL accessible depuis internet)
              </option>
            </select>
            <div class="hint">SSH tunnel : on se connecte au serveur via SSH, puis MySQL en local. Plus sécurisé.</div>
          </div>

          <div id="sshFields">
            <div style="font-size:12px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px">Accès SSH</div>
            <div class="grid-3">
              <div class="field">
                <label>Hôte SSH (IP ou domaine)</label>
                <input type="text" name="ssh_host" value="<?= htmlspecialchars($src['ssh_host'] ?? '') ?>" placeholder="185.xxx.xxx.xxx">
              </div>
              <div class="field">
                <label>Port SSH</label>
                <input type="number" name="ssh_port" value="<?= $src['ssh_port'] ?? 22 ?>" min="1" max="65535">
              </div>
              <div class="field">
                <label>Utilisateur SSH</label>
                <input type="text" name="ssh_user" value="<?= htmlspecialchars($src['ssh_user'] ?? 'root') ?>">
              </div>
            </div>
            <div class="field">
              <label>Mot de passe SSH</label>
              <input type="password" name="ssh_password" placeholder="<?= !empty($src['ssh_password']) ? '••••••••  (laisser vide pour garder l\'actuel)' : 'Mot de passe du serveur' ?>">
              <div class="hint">Stocké chiffré (AES-256). Laissez vide pour conserver la valeur actuelle.</div>
            </div>
          </div>

          <div style="font-size:12px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin:20px 0 12px">Base de données MySQL</div>
          <div class="grid-3">
            <div class="field">
              <label id="dbHostLabel">Hôte MySQL (sur le serveur distant)</label>
              <input type="text" name="db_host" value="<?= htmlspecialchars($src['db_host'] ?? '127.0.0.1') ?>">
              <div class="hint" id="dbHostHint">En mode SSH : généralement 127.0.0.1</div>
            </div>
            <div class="field">
              <label>Port MySQL</label>
              <input type="number" name="db_port" value="<?= $src['db_port'] ?? 3306 ?>" min="1" max="65535">
            </div>
            <div class="field">
              <label>Nom de la base</label>
              <input type="text" name="db_name" value="<?= htmlspecialchars($src['db_name'] ?? '') ?>" placeholder="pharmacie_galy" required>
            </div>
          </div>
          <div class="grid-2">
            <div class="field">
              <label>Utilisateur MySQL</label>
              <input type="text" name="db_user" value="<?= htmlspecialchars($src['db_user'] ?? 'root') ?>">
            </div>
            <div class="field">
              <label>Mot de passe MySQL</label>
              <input type="password" name="db_password" placeholder="<?= !empty($src['db_password']) ? '•••••••• (laisser vide pour garder l\'actuel)' : 'Mot de passe MySQL' ?>">
            </div>
          </div>

          <div class="btn-row">
            <button type="button" class="btn btn-test" onclick="testConnection()">
              ⚡ Tester la connexion
            </button>
            <div id="testResult"></div>
          </div>
        </div>
      </div>

      <!-- Schema mapping card -->
      <div class="card">
        <div class="card-header">
          <div class="card-header-icon">
            <svg viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
          </div>
          <div>
            <div class="card-title">Mapping des colonnes</div>
            <div class="card-desc">Indiquez les noms des tables et colonnes dans la base source</div>
          </div>
        </div>
        <div class="card-body">
          <div style="overflow-x:auto">
            <table class="schema-table">
              <thead>
                <tr>
                  <th style="width:220px">Champ standard</th>
                  <th>Valeur dans votre base (table ou colonne)</th>
                  <th style="width:200px">Description</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $schemaLabels = [
                    'sales_table'          => ['Table des ventes',           'ex: sale, ventes, invoices'],
                    'sales_id_col'         => ['Colonne ID de vente',        'clé primaire de la table ventes'],
                    'sales_date_col'       => ['Colonne date de vente',      'ex: saleDate, date_vente, created_at'],
                    'items_table'          => ['Table des lignes de vente',  'ex: saleitem, vente_lignes'],
                    'items_sale_fk'        => ['FK vers la vente',           'ex: saleId, vente_id'],
                    'items_product_fk'     => ['FK vers le produit',         'ex: productId, produit_id'],
                    'items_quantity_col'   => ['Colonne quantité',           'ex: quantity, qte'],
                    'items_unit_price_col' => ['Colonne prix unitaire',      'ex: unitPrice, prix_unitaire'],
                    'products_table'       => ['Table des produits',         'ex: product, produits, medicaments'],
                    'products_id_col'      => ['Colonne ID produit',         'clé primaire produits'],
                    'products_name_col'    => ['Colonne nom du produit',     'ex: name, nom, designation'],
                    'products_stock_col'   => ['Colonne stock actuel',       'ex: stock, quantite_stock'],
                    'products_cost_col'    => ['Colonne prix achat',         'ex: purchasePrice, prix_achat'],
                    'products_price_col'   => ['Colonne prix vente',         'ex: sellingPrice, prix_vente'],
                    'products_expiry_col'  => ['Colonne date péremption',    'ex: expiryDate, date_peremption'],
                ];
                foreach ($schemaLabels as $key => [$label, $desc]):
                    $val = $schema[$key] ?? $defaultSchema[$key] ?? '';
                ?>
                <tr>
                  <td><?= htmlspecialchars($label) ?></td>
                  <td><input type="text" name="schema_<?= $key ?>" value="<?= htmlspecialchars($val) ?>"></td>
                  <td style="color:var(--text-3);font-size:11.5px"><?= htmlspecialchars($desc) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="btn-row" style="margin-bottom:40px">
        <button type="submit" class="btn btn-primary">Sauvegarder</button>
        <button type="button" class="btn btn-sync" onclick="syncNow()">
          ↻ Synchroniser maintenant
        </button>
        <div id="syncResult"></div>
      </div>
    </form>

  </div>
</div>

<script>
function toggleConnType() {
  const type = document.getElementById('connType').value;
  document.getElementById('sshFields').style.display = type === 'ssh' ? '' : 'none';
  document.getElementById('dbHostLabel').textContent =
    type === 'ssh' ? 'Hôte MySQL (sur le serveur distant)' : 'Hôte MySQL (IP publique)';
  document.getElementById('dbHostHint').textContent =
    type === 'ssh' ? 'En mode SSH : généralement 127.0.0.1' : 'IP ou domaine du serveur MySQL';
}
toggleConnType();

async function testConnection() {
  const form = document.getElementById('settingsForm');
  const data = new FormData(form);
  data.set('action', 'test');

  const res  = document.getElementById('testResult');
  res.style.display = 'block';
  res.innerHTML = '<span style="color:var(--text-3);font-size:13px">Test en cours…</span>';

  try {
    const r = await fetch('/analytics/api.php?type=etl_test', {
      method: 'POST',
      body:   data,
    });
    const json = await r.json();
    if (json.ok) {
      res.innerHTML = `<div class="notice ok" style="margin:0"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Connexion réussie · ${json.tables} tables trouvées</div>`;
    } else {
      res.innerHTML = `<div class="notice error" style="margin:0"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>${json.error}</div>`;
    }
  } catch(e) {
    res.innerHTML = `<div class="notice error" style="margin:0">Erreur réseau</div>`;
  }
}

async function syncNow() {
  const res = document.getElementById('syncResult');
  res.style.display = 'block';
  res.innerHTML = '<span style="color:var(--text-3);font-size:13px">Synchronisation en cours…</span>';

  try {
    const r = await fetch('/analytics/api.php?type=etl_sync');
    const json = await r.json();
    if (json.ok) {
      res.innerHTML = `<div class="notice ok" style="margin:0"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>${json.message}</div>`;
    } else {
      res.innerHTML = `<div class="notice error" style="margin:0">${json.error}</div>`;
    }
  } catch(e) {
    res.innerHTML = `<div class="notice error" style="margin:0">Erreur</div>`;
  }
}
</script>
</body>
</html>
