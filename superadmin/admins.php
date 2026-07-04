<?php
require_once __DIR__ . '/config/auth.php';
sa_check_auth();

$db = sa_db();

$flash = ['type' => $_SESSION['flash_type'] ?? '', 'msg' => $_SESSION['flash_msg'] ?? ''];
unset($_SESSION['flash_type'], $_SESSION['flash_msg']);

$current_sa_id = (int) ($_SESSION['sa_user_id'] ?? 0);

// ── Actions ────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Create
    if ($action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $display  = trim($_POST['display_name'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$username || !$password) {
            $flash = ['type' => 'error', 'msg' => "L'identifiant et le mot de passe sont requis."];
        } elseif (strlen($password) < 8) {
            $flash = ['type' => 'error', 'msg' => "Le mot de passe doit contenir au moins 8 caractères."];
        } else {
            $exists = $db->prepare("SELECT id FROM superadmin_users WHERE username = ?");
            $exists->execute([$username]);
            if ($exists->fetch()) {
                $flash = ['type' => 'error', 'msg' => "L'identifiant \"$username\" est déjà utilisé."];
            } else {
                $db->prepare("INSERT INTO superadmin_users (username, password, display_name) VALUES (?, ?, ?)")
                   ->execute([$username, password_hash($password, PASSWORD_BCRYPT), $display ?: $username]);
                $flash = ['type' => 'success', 'msg' => "Compte <strong>$username</strong> créé avec succès."];
            }
        }
    }

    // Change own password
    if ($action === 'change_password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $me  = $db->prepare("SELECT * FROM superadmin_users WHERE id = ?");
        $me->execute([$current_sa_id]);
        $me = $me->fetch(PDO::FETCH_ASSOC);

        if (!$me || !password_verify($old, $me['password'])) {
            $flash = ['type' => 'error', 'msg' => "Ancien mot de passe incorrect."];
        } elseif (strlen($new) < 8) {
            $flash = ['type' => 'error', 'msg' => "Le nouveau mot de passe doit contenir au moins 8 caractères."];
        } else {
            $db->prepare("UPDATE superadmin_users SET password = ? WHERE id = ?")
               ->execute([password_hash($new, PASSWORD_BCRYPT), $current_sa_id]);
            $flash = ['type' => 'success', 'msg' => "Mot de passe mis à jour."];
        }
    }

    // Delete
    if ($action === 'delete' && isset($_POST['admin_id'])) {
        $targetId = (int) $_POST['admin_id'];
        $total = (int) $db->query("SELECT COUNT(*) FROM superadmin_users")->fetchColumn();
        $target = $db->prepare("SELECT * FROM superadmin_users WHERE id = ?");
        $target->execute([$targetId]);
        $target = $target->fetch(PDO::FETCH_ASSOC);

        if ($targetId === $current_sa_id) {
            $flash = ['type' => 'error', 'msg' => "Vous ne pouvez pas supprimer votre propre compte."];
        } elseif ($target && $target['is_owner']) {
            $flash = ['type' => 'error', 'msg' => "Le compte propriétaire ne peut pas être supprimé."];
        } elseif ($total <= 1) {
            $flash = ['type' => 'error', 'msg' => "Impossible — c'est le seul compte superadmin."];
        } else {
            $db->prepare("DELETE FROM superadmin_users WHERE id = ?")->execute([$targetId]);
            $flash = ['type' => 'success', 'msg' => "Compte supprimé."];
        }
    }

    $_SESSION['flash_type'] = $flash['type'];
    $_SESSION['flash_msg']  = $flash['msg'];
    header('Location: /superadmin/admins.php');
    exit;
}

// ── Data ───────────────────────────────────────────────────────────────────

$admins = $db->query("SELECT * FROM superadmin_users ORDER BY is_owner DESC, created_at ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/config/layout_header.php';
?>

<?php if ($flash['msg']): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?>"><?= $flash['msg'] ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem;align-items:start">

    <!-- List -->
    <div class="table-card">
        <div class="table-header">
            <span class="table-title">Comptes SuperAdmin (<?= count($admins) ?>)</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Identifiant</th>
                    <th>Nom affiché</th>
                    <th>Rôle</th>
                    <th>Créé le</th>
                    <th style="width:100px">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($admins as $a): ?>
            <tr>
                <td>
                    <div style="font-weight:600;font-family:monospace;font-size:.85rem">
                        <?= htmlspecialchars($a['username']) ?>
                        <?php if ((int)$a['id'] === $current_sa_id): ?>
                        <span style="font-size:.7rem;background:#DCFCE7;color:#15803D;padding:1px 7px;border-radius:99px;font-family:inherit;margin-left:4px">vous</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td><?= htmlspecialchars($a['display_name'] ?? '—') ?></td>
                <td>
                    <?php if ($a['is_owner']): ?>
                    <span class="badge badge-pro">Propriétaire</span>
                    <?php else: ?>
                    <span class="badge badge-basic">Admin</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:.78rem;color:#9CA3AF"><?= date('d/m/Y', strtotime($a['created_at'])) ?></td>
                <td>
                    <?php $canDelete = (int)$a['id'] !== $current_sa_id && !$a['is_owner']; ?>
                    <?php if ($canDelete): ?>
                    <form method="POST" style="margin:0">
                        <input type="hidden" name="action"   value="delete">
                        <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
                        <button class="btn-sm btn-danger"
                            onclick="return confirm('Supprimer le compte <?= htmlspecialchars(addslashes($a['username'])) ?> ?')">
                            Supprimer
                        </button>
                    </form>
                    <?php else: ?>
                    <span style="font-size:.75rem;color:#D1D5DB">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Side forms -->
    <div style="display:flex;flex-direction:column;gap:1.25rem">

        <!-- Create -->
        <div class="form-card">
            <div style="font-size:.95rem;font-weight:700;color:#111827;margin-bottom:1.1rem">Créer un compte</div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label>Identifiant *</label>
                    <input type="text" name="username" placeholder="jean.dupont" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Nom affiché</label>
                    <input type="text" name="display_name" placeholder="Jean Dupont" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Mot de passe * <span style="font-weight:400;color:#9CA3AF">(min. 8 caractères)</span></label>
                    <input type="password" name="password" placeholder="••••••••" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn-sm btn-primary" style="width:100%;justify-content:center;padding:.6rem">
                    ➕ Créer le compte
                </button>
            </form>
        </div>

        <!-- Change own password -->
        <div class="form-card">
            <div style="font-size:.95rem;font-weight:700;color:#111827;margin-bottom:1.1rem">Changer mon mot de passe</div>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label>Ancien mot de passe</label>
                    <input type="password" name="old_password" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label>Nouveau mot de passe <span style="font-weight:400;color:#9CA3AF">(min. 8 caractères)</span></label>
                    <input type="password" name="new_password" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn-sm btn-outline" style="width:100%;justify-content:center;padding:.6rem">
                    🔑 Mettre à jour
                </button>
            </form>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/config/layout_footer.php'; ?>
