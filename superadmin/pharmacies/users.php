<?php
require_once dirname(__DIR__) . '/config/auth.php';
sa_check_auth();

$db = sa_db();

// Ajouter un user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_user') {
    $pharmacy_id = (int)$_POST['pharmacy_id'];
    $username    = trim($_POST['username']);
    $email       = trim($_POST['email']);
    $password    = $_POST['password'];
    // Map form value to DB enum (uppercase)
    $role_map    = ['admin'=>'ADMIN','cashier'=>'CASHIER','seller'=>'SELLER','stock-manager'=>'STOCK-MANAGER'];
    $role        = $role_map[$_POST['role'] ?? 'admin'] ?? 'ADMIN';
    $hash        = password_hash($password, PASSWORD_BCRYPT);
    $uuid        = sprintf('%s%s-%s-%s-%s-%s%s%s', ...str_split(bin2hex(random_bytes(16)), 4));

    $db->prepare("
        INSERT INTO user (id, username, email, password, role, statut, pharmacy_id)
        VALUES (?, ?, ?, ?, ?, 1, ?)
    ")->execute([$uuid, $username, $email, $hash, $role, $pharmacy_id]);
    header('Location: users.php?msg=created');
    exit;
}

// Supprimer un user
if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM user WHERE id=?")->execute([(int)$_GET['delete']]);
    header('Location: users.php?msg=deleted');
    exit;
}

$msg = $_GET['msg'] ?? '';

// Récupérer tous les users avec pharmacie
$users = $db->query("
    SELECT u.*, p.name AS pharmacy_name, p.status AS pharmacy_status
    FROM user u
    LEFT JOIN pharmacies p ON p.id = u.pharmacy_id
    ORDER BY p.name, u.role, u.name
")->fetchAll();

// Pour le formulaire
$pharmacies = $db->query("SELECT id, name FROM pharmacies ORDER BY name")->fetchAll();

require_once dirname(__DIR__) . '/config/layout_header.php';
?>

<?php if ($msg === 'created'): ?><div class="alert alert-success">✅ Compte créé.</div><?php endif; ?>
<?php if ($msg === 'deleted'): ?><div class="alert alert-success">✅ Compte supprimé.</div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem;align-items:start;">

    <!-- Table users -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Comptes admin (<?= count($users) ?>)</div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Pharmacie</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td style="font-weight:500;"><?= htmlspecialchars($u['name'] ?? '—') ?></td>
                    <td style="font-family:monospace;font-size:0.85rem;"><?= htmlspecialchars($u['username']) ?></td>
                    <td style="font-size:0.85rem;color:#6B7280;"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
                    <td>
                        <span class="badge <?= $u['role']==='admin' ? 'badge-pro' : 'badge-starter' ?>">
                            <?= ucfirst($u['role'] ?? 'user') ?>
                        </span>
                    </td>
                    <td>
                        <div style="font-size:0.85rem;"><?= htmlspecialchars($u['pharmacy_name'] ?? '—') ?></div>
                        <span class="badge badge-<?= $u['pharmacy_status'] ?? 'trial' ?>" style="font-size:0.65rem;">
                            <?= ['active'=>'Active','trial'=>'Trial','suspended'=>'Suspendue'][$u['pharmacy_status']] ?? '—' ?>
                        </span>
                    </td>
                    <td>
                        <a href="users.php?delete=<?= $u['id'] ?>" class="btn-sm btn-danger"
                           onclick="return confirm('Supprimer ce compte ?')">Supprimer</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($users)): ?>
                <tr><td colspan="6" style="text-align:center;color:#9CA3AF;padding:2rem;">Aucun utilisateur</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Formulaire ajout -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Ajouter un compte</div>
        </div>
        <div style="padding:1.25rem;">
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="form-group">
                    <label>Pharmacie *</label>
                    <select name="pharmacy_id" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($pharmacies as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nom complet</label>
                    <input type="text" name="name" placeholder="Jean Dupont">
                </div>
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" placeholder="admin_pharmacie" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" placeholder="admin@pharmacie.com">
                </div>
                <div class="form-group">
                    <label>Rôle</label>
                    <select name="role">
                        <option value="admin">Admin</option>
                        <option value="cashier">Caissier</option>
                        <option value="seller">Vendeur</option>
                        <option value="stock-manager">Stock Manager</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mot de passe *</label>
                    <input type="text" name="password" placeholder="Mot de passe initial" required>
                </div>
                <button type="submit" class="btn-sm btn-primary" style="width:100%;justify-content:center;padding:0.65rem;">
                    ➕ Créer le compte
                </button>
            </form>
        </div>
    </div>

</div>

<?php require_once dirname(__DIR__) . '/config/layout_footer.php'; ?>
