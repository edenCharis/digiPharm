<?php
require_once dirname(__DIR__) . '/config/auth.php';
sa_check_auth();

$db = sa_db();
$id = (int)($_GET['id'] ?? 0);

if (!$id) { header('Location: list.php'); exit; }

$pharmacy = $db->query("SELECT * FROM pharmacies WHERE id = $id")->fetch();
if (!$pharmacy) { header('Location: list.php'); exit; }

// Quick status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    $new = $pharmacy['status'] === 'suspended' ? 'active' : 'suspended';
    $db->prepare("UPDATE pharmacies SET status = ? WHERE id = ?")->execute([$new, $id]);
    header("Location: view.php?id=$id");
    exit;
}

$users = $db->prepare("SELECT * FROM user WHERE pharmacy_id = ? ORDER BY role, username");
$users->execute([$id]);
$users = $users->fetchAll();

$logs = $db->prepare("
    SELECT l.*, u.username
    FROM log l
    LEFT JOIN user u ON u.id = l.userId
    WHERE u.pharmacy_id = ?
    ORDER BY l.id DESC LIMIT 20
");
$logs->execute([$id]);
$logs = $logs->fetchAll();

$trial_days_left = null;
if ($pharmacy['trial_ends_at']) {
    $trial_days_left = (int) ceil((strtotime($pharmacy['trial_ends_at']) - time()) / 86400);
}

require_once dirname(__DIR__) . '/config/layout_header.php';
?>

<div style="display:flex;gap:1.25rem;align-items:flex-start;margin-bottom:1.25rem;">
    <a href="list.php" class="btn-sm btn-outline">← Retour</a>
    <a href="edit.php?id=<?= $id ?>" class="btn-sm btn-primary">Modifier</a>
    <form method="POST" style="margin:0;">
        <input type="hidden" name="action" value="toggle_status">
        <button class="btn-sm <?= $pharmacy['status'] === 'suspended' ? 'btn-primary' : 'btn-danger' ?>"
                onclick="return confirm('Confirmer ?')">
            <?= $pharmacy['status'] === 'suspended' ? 'Réactiver' : 'Suspendre' ?>
        </button>
    </form>
</div>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:1.25rem;align-items:start;">

    <!-- Infos pharmacie -->
    <div>
        <div class="table-card" style="margin-bottom:1.25rem;">
            <div class="table-header">
                <div class="table-title"><?= htmlspecialchars($pharmacy['name']) ?></div>
                <span class="badge badge-<?= $pharmacy['status'] ?>">
                    <?= ['active'=>'Active','trial'=>'Trial','suspended'=>'Suspendue'][$pharmacy['status']] ?? $pharmacy['status'] ?>
                </span>
            </div>
            <div style="padding:1.25rem;">
                <table style="width:100%;border-collapse:collapse;">
                    <?php
                    $fields = [
                        'Plan'             => '<span class="badge badge-'.($pharmacy['plan'] ?? 'basic').'">'.ucfirst($pharmacy['plan'] ?? '—').'</span>',
                        'Email'            => htmlspecialchars($pharmacy['email'] ?? '—'),
                        'Téléphone'        => htmlspecialchars($pharmacy['phone'] ?? '—'),
                        'Ville'            => htmlspecialchars($pharmacy['city'] ?? '—'),
                        'Adresse'          => htmlspecialchars($pharmacy['address'] ?? '—'),
                        'Responsable'      => htmlspecialchars($pharmacy['responsible_name'] ?? '—'),
                        'Créée le'         => $pharmacy['created_at'] ? date('d/m/Y à H:i', strtotime($pharmacy['created_at'])) : '—',
                        'Fin de trial'     => $pharmacy['trial_ends_at']
                            ? date('d/m/Y', strtotime($pharmacy['trial_ends_at']))
                              . ($trial_days_left !== null
                                  ? ' <span style="font-size:0.75rem;color:'.($trial_days_left < 0 ? '#ef4444' : ($trial_days_left <= 3 ? '#f59e0b' : '#6B7280')).'">('
                                    .($trial_days_left < 0 ? abs($trial_days_left).' j. dépassé' : $trial_days_left.' j. restants').')</span>'
                                  : '')
                            : '—',
                    ];
                    foreach ($fields as $label => $value):
                    ?>
                    <tr>
                        <td style="padding:8px 0;font-size:0.8rem;color:#6B7280;font-weight:500;width:140px;vertical-align:top;"><?= $label ?></td>
                        <td style="padding:8px 0;font-size:0.875rem;"><?= $value ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <!-- Utilisateurs -->
        <div class="table-card">
            <div class="table-header">
                <div class="table-title">Utilisateurs (<?= count($users) ?>)</div>
            </div>
            <table>
                <thead>
                    <tr><th>Username</th><th>Email</th><th>Rôle</th><th>Statut</th></tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td style="font-family:monospace;font-size:0.85rem;"><?= htmlspecialchars($u['username']) ?></td>
                    <td style="font-size:0.8rem;color:#6B7280;"><?= htmlspecialchars($u['email'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= strtolower($u['role']) === 'admin' ? 'pro' : 'starter' ?>"><?= htmlspecialchars($u['role']) ?></span></td>
                    <td><span class="badge <?= $u['statut'] ? 'badge-active' : 'badge-suspended' ?>"><?= $u['statut'] ? 'Actif' : 'Inactif' ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr><td colspan="4" style="text-align:center;color:#9CA3AF;padding:1.5rem;">Aucun utilisateur</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Activité récente -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Activité récente</div>
        </div>
        <?php if (empty($logs)): ?>
        <div style="text-align:center;color:#9CA3AF;padding:2rem;font-size:0.875rem;">Aucun log disponible</div>
        <?php else: ?>
        <?php foreach ($logs as $log): ?>
        <div class="log-entry">
            <div class="log-dot"></div>
            <div style="flex:1;">
                <div class="log-msg"><?= htmlspecialchars($log['action'] ?? '—') ?></div>
                <div style="display:flex;gap:0.75rem;margin-top:2px;align-items:center;">
                    <span style="font-size:0.75rem;color:#6B7280;font-family:monospace;"><?= htmlspecialchars($log['username'] ?? '—') ?></span>
                    <?php if ($log['tableName'] ?? null): ?>
                    <span style="font-size:0.7rem;color:#9CA3AF;"><?= htmlspecialchars($log['tableName']) ?></span>
                    <?php endif; ?>
                    <span class="log-time"><?= $log['createdAt'] ? date('d/m H:i', strtotime($log['createdAt'])) : '' ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once dirname(__DIR__) . '/config/layout_footer.php'; ?>
