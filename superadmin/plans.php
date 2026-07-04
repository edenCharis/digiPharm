<?php
require_once __DIR__ . '/config/auth.php';
sa_check_auth();

$db = sa_db();

// Ensure table exists with seed data
$db->exec("CREATE TABLE IF NOT EXISTS plans (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    slug       VARCHAR(30) NOT NULL UNIQUE,
    name       VARCHAR(80) NOT NULL,
    price_usd  DECIMAL(10,2) NOT NULL DEFAULT 0,
    price_xaf  INT NOT NULL DEFAULT 0,
    features   TEXT NULL,
    max_users  INT NOT NULL DEFAULT 3,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Seed default plans if none exist
$count = (int) $db->query("SELECT COUNT(*) FROM plans")->fetchColumn();
if ($count === 0) {
    $db->prepare("INSERT INTO plans (slug, name, price_usd, price_xaf, features, max_users, sort_order) VALUES
        ('starter', 'Basique',  10.00,  6000,  'Caisse · Stock · Rapports',            3,  1),
        ('pro',     'Pro + IA', 25.00, 15000, 'Tout Basique · IA · SFEC Congo',  15, 2)")->execute();
}

$flash = ['type' => $_SESSION['flash_type'] ?? '', 'msg' => $_SESSION['flash_msg'] ?? ''];
unset($_SESSION['flash_type'], $_SESSION['flash_msg']);

// ── Actions ────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save' && isset($_POST['plan_id'])) {
        $id        = (int) $_POST['plan_id'];
        $name      = trim($_POST['name'] ?? '');
        $price_usd = (float) str_replace(',', '.', $_POST['price_usd'] ?? 0);
        $price_xaf = (int) str_replace([' ', ',', '.'], '', $_POST['price_xaf'] ?? 0);
        $features  = trim($_POST['features'] ?? '');
        $max_users = max(1, (int) ($_POST['max_users'] ?? 1));

        if (!$name || $price_usd < 0 || $price_xaf < 0) {
            $flash = ['type' => 'error', 'msg' => 'Données invalides.'];
        } else {
            $db->prepare("UPDATE plans SET name=?, price_usd=?, price_xaf=?, features=?, max_users=? WHERE id=?")
               ->execute([$name, $price_usd, $price_xaf, $features, $max_users, $id]);
            $flash = ['type' => 'success', 'msg' => 'Forfait mis à jour.'];
        }
    }

    if ($action === 'toggle' && isset($_POST['plan_id'])) {
        $id = (int) $_POST['plan_id'];
        $db->prepare("UPDATE plans SET is_active = 1 - is_active WHERE id = ?")->execute([$id]);
        $flash = ['type' => 'info', 'msg' => 'Statut du forfait modifié.'];
    }

    if ($action === 'create') {
        $slug      = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($_POST['slug'] ?? '')));
        $name      = trim($_POST['name'] ?? '');
        $price_usd = (float) str_replace(',', '.', $_POST['price_usd'] ?? 0);
        $price_xaf = (int) str_replace([' ', ',', '.'], '', $_POST['price_xaf'] ?? 0);
        $features  = trim($_POST['features'] ?? '');
        $max_users = max(1, (int) ($_POST['max_users'] ?? 1));

        if (!$slug || !$name) {
            $flash = ['type' => 'error', 'msg' => 'Slug et nom requis.'];
        } else {
            $exists = $db->prepare("SELECT id FROM plans WHERE slug = ?");
            $exists->execute([$slug]);
            if ($exists->fetch()) {
                $flash = ['type' => 'error', 'msg' => "Le slug \"$slug\" existe déjà."];
            } else {
                $max_order = (int) $db->query("SELECT MAX(sort_order) FROM plans")->fetchColumn();
                $db->prepare("INSERT INTO plans (slug, name, price_usd, price_xaf, features, max_users, sort_order) VALUES (?,?,?,?,?,?,?)")
                   ->execute([$slug, $name, $price_usd, $price_xaf, $features, $max_users, $max_order + 1]);
                $flash = ['type' => 'success', 'msg' => "Forfait <strong>$name</strong> créé."];
            }
        }
    }

    $_SESSION['flash_type'] = $flash['type'];
    $_SESSION['flash_msg']  = $flash['msg'];
    header('Location: /superadmin/plans.php');
    exit;
}

// ── Data ───────────────────────────────────────────────────────────────────
$plans = $db->query("SELECT * FROM plans ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/config/layout_header.php';
?>

<?php if ($flash['msg']): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= $flash['msg'] ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:1.5rem;align-items:start">

    <!-- Plan list -->
    <div style="display:flex;flex-direction:column;gap:1rem">
    <?php foreach ($plans as $p): ?>
    <div class="form-card" style="<?= !$p['is_active'] ? 'opacity:.6' : '' ?>">

        <!-- Header -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.1rem">
            <div style="display:flex;align-items:center;gap:10px">
                <div style="font-size:.95rem;font-weight:700;color:#111827"><?= htmlspecialchars($p['name']) ?></div>
                <code style="font-size:.7rem;background:#F3F4F6;padding:2px 8px;border-radius:5px;color:#6B7280"><?= htmlspecialchars($p['slug']) ?></code>
                <?php if (!$p['is_active']): ?>
                <span class="badge badge-suspended" style="font-size:.65rem">Désactivé</span>
                <?php else: ?>
                <span class="badge badge-active" style="font-size:.65rem">Actif</span>
                <?php endif; ?>
            </div>
            <form method="POST" style="margin:0">
                <input type="hidden" name="action"  value="toggle">
                <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
                <button class="btn-sm btn-outline" style="font-size:.75rem">
                    <?= $p['is_active'] ? 'Désactiver' : 'Activer' ?>
                </button>
            </form>
        </div>

        <!-- Prices display -->
        <div style="display:flex;gap:1rem;margin-bottom:1.25rem">
            <div style="flex:1;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:9px;padding:.85rem 1rem">
                <div style="font-size:.65rem;font-weight:600;color:#16A34A;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">Prix USD (HT)</div>
                <div style="font-size:1.6rem;font-weight:800;color:#111827;letter-spacing:-0.03em">$<?= number_format($p['price_usd'], 2) ?></div>
                <div style="font-size:.72rem;color:#6B7280;margin-top:2px">par mois</div>
            </div>
            <div style="flex:1;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:9px;padding:.85rem 1rem">
                <div style="font-size:.65rem;font-weight:600;color:#1D4ED8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">Équivalent XAF (HT)</div>
                <div style="font-size:1.6rem;font-weight:800;color:#111827;letter-spacing:-0.03em"><?= number_format($p['price_xaf'], 0, ',', ' ') ?></div>
                <div style="font-size:.72rem;color:#6B7280;margin-top:2px">FCFA / mois</div>
            </div>
            <div style="flex:1;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:9px;padding:.85rem 1rem">
                <div style="font-size:.65rem;font-weight:600;color:#6B7280;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">Max utilisateurs</div>
                <div style="font-size:1.6rem;font-weight:800;color:#111827;letter-spacing:-0.03em"><?= $p['max_users'] ?></div>
                <div style="font-size:.72rem;color:#6B7280;margin-top:2px">comptes</div>
            </div>
        </div>

        <!-- Edit form -->
        <form method="POST">
            <input type="hidden" name="action"  value="save">
            <input type="hidden" name="plan_id" value="<?= $p['id'] ?>">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 80px;gap:.75rem;align-items:end">
                <div class="form-group" style="margin:0">
                    <label>Nom du forfait</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($p['name']) ?>" required>
                </div>
                <div class="form-group" style="margin:0">
                    <label>Prix USD (HT)</label>
                    <input type="number" name="price_usd" value="<?= $p['price_usd'] ?>" min="0" step="0.01" required
                           style="font-variant-numeric:tabular-nums">
                </div>
                <div class="form-group" style="margin:0">
                    <label>Prix XAF (HT)</label>
                    <input type="number" name="price_xaf" value="<?= $p['price_xaf'] ?>" min="0" step="50" required
                           style="font-variant-numeric:tabular-nums">
                </div>
                <div class="form-group" style="margin:0">
                    <label>Nb users</label>
                    <input type="number" name="max_users" value="<?= $p['max_users'] ?>" min="1" required>
                </div>
            </div>
            <div class="form-group" style="margin-top:.75rem;margin-bottom:0">
                <label>Fonctionnalités <span style="font-weight:400;color:#9CA3AF">(affiché sur la page d'inscription)</span></label>
                <input type="text" name="features" value="<?= htmlspecialchars($p['features'] ?? '') ?>"
                       placeholder="Caisse · Stock · Rapports · 3 utilisateurs">
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:.9rem">
                <button type="submit" class="btn-sm btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
    <?php endforeach; ?>
    </div>

    <!-- Create new plan -->
    <div class="form-card">
        <div style="font-size:.9rem;font-weight:700;color:#111827;margin-bottom:1rem">Nouveau forfait</div>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label>Slug <span style="font-weight:400;color:#9CA3AF">(identifiant unique)</span></label>
                <input type="text" name="slug" placeholder="enterprise" pattern="[a-z0-9_-]+" required autocomplete="off">
            </div>
            <div class="form-group">
                <label>Nom affiché</label>
                <input type="text" name="name" placeholder="Entreprise" required>
            </div>
            <div class="form-group">
                <label>Prix USD HT ($/mois)</label>
                <input type="number" name="price_usd" value="0" min="0" step="0.01" required>
            </div>
            <div class="form-group">
                <label>Prix XAF HT (FCFA/mois)</label>
                <input type="number" name="price_xaf" value="0" min="0" step="50" required>
            </div>
            <div class="form-group">
                <label>Max utilisateurs</label>
                <input type="number" name="max_users" value="3" min="1" required>
            </div>
            <div class="form-group">
                <label>Fonctionnalités</label>
                <input type="text" name="features" placeholder="Caisse · Stock · Rapports">
            </div>
            <button type="submit" class="btn-sm btn-primary" style="width:100%;justify-content:center;padding:.6rem">
                ➕ Créer le forfait
            </button>
        </form>
    </div>

</div>

<?php require_once __DIR__ . '/config/layout_footer.php'; ?>
