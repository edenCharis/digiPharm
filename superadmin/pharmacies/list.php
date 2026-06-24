<?php
require_once dirname(__DIR__) . '/config/auth.php';
sa_check_auth();

$db = sa_db();

// Actions rapides
if ($_POST['action'] ?? '' === 'toggle_status') {
    $id     = (int)$_POST['id'];
    $status = $_POST['status'] === 'active' ? 'suspended' : 'active';
    $db->prepare("UPDATE pharmacies SET status=? WHERE id=?")->execute([$status, $id]);
    header('Location: list.php?msg=updated');
    exit;
}

$msg = $_GET['msg'] ?? '';

// Filtres
$search = trim($_GET['q'] ?? '');
$filter_status = $_GET['status'] ?? '';
$filter_plan   = $_GET['plan'] ?? '';

$where  = ["1=1"];
$params = [];

if ($search) {
    $where[]  = "(p.name LIKE ? OR p.city LIKE ? OR p.email LIKE ?)";
    $params   = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($filter_status) { $where[] = "p.status = ?"; $params[] = $filter_status; }
if ($filter_plan)   { $where[] = "p.plan = ?";   $params[] = $filter_plan; }

$sql = "
    SELECT p.*, COUNT(u.id) AS nb_users
    FROM pharmacies p
    LEFT JOIN user u ON u.pharmacy_id = p.id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY p.id
    ORDER BY p.created_at DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$pharmacies = $stmt->fetchAll();

require_once dirname(__DIR__) . '/config/layout_header.php';
?>

<?php if ($msg === 'created'): ?>
    <div class="alert alert-success">✅ Pharmacie créée avec succès.</div>
<?php elseif ($msg === 'updated'): ?>
    <div class="alert alert-success">✅ Statut mis à jour.</div>
<?php endif; ?>

<!-- Filtres -->
<div class="table-card" style="margin-bottom:1.25rem;">
    <div style="padding:1rem 1.25rem;">
        <form method="GET" style="display:flex;gap:0.75rem;align-items:flex-end;flex-wrap:wrap;">
            <div class="form-group" style="margin:0;flex:1;min-width:180px;">
                <label style="font-size:0.75rem;">Rechercher</label>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Nom, ville, email...">
            </div>
            <div class="form-group" style="margin:0;">
                <label style="font-size:0.75rem;">Statut</label>
                <select name="status">
                    <option value="">Tous</option>
                    <option value="active"    <?= $filter_status==='active'    ?'selected':'' ?>>Active</option>
                    <option value="trial"     <?= $filter_status==='trial'     ?'selected':'' ?>>Trial</option>
                    <option value="suspended" <?= $filter_status==='suspended' ?'selected':'' ?>>Suspendue</option>
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label style="font-size:0.75rem;">Plan</label>
                <select name="plan">
                    <option value="">Tous</option>
                    <option value="starter"    <?= $filter_plan==='starter'    ?'selected':'' ?>>Starter</option>
                    <option value="pro"        <?= $filter_plan==='pro'        ?'selected':'' ?>>Pro</option>
                    <option value="enterprise" <?= $filter_plan==='enterprise' ?'selected':'' ?>>Enterprise</option>
                </select>
            </div>
            <button type="submit" class="btn-sm btn-primary" style="padding:0.6rem 1rem;">Filtrer</button>
            <a href="list.php" class="btn-sm btn-outline" style="padding:0.6rem 1rem;">Reset</a>
        </form>
    </div>
</div>

<!-- Table -->
<div class="table-card">
    <div class="table-header">
        <div class="table-title">Pharmacies (<?= count($pharmacies) ?>)</div>
        <a href="create.php" class="btn-sm btn-primary">+ Nouvelle pharmacie</a>
    </div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Pharmacie</th>
                <th>Ville</th>
                <th>Contact</th>
                <th>Plan</th>
                <th>Statut</th>
                <th>Trial expire</th>
                <th>Users</th>
                <th>Créée le</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pharmacies as $p): ?>
            <tr>
                <td style="color:#9CA3AF;"><?= $p['id'] ?></td>
                <td style="font-weight:600;"><?= htmlspecialchars($p['name']) ?></td>
                <td><?= htmlspecialchars($p['city']) ?></td>
                <td>
                    <?php if ($p['email']): ?>
                        <div style="font-size:0.8rem;"><?= htmlspecialchars($p['email']) ?></div>
                    <?php endif; ?>
                    <?php if ($p['phone']): ?>
                        <div style="font-size:0.75rem;color:#6B7280;"><?= htmlspecialchars($p['phone']) ?></div>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-<?= $p['plan'] ?>"><?= ucfirst($p['plan']) ?></span></td>
                <td><span class="badge badge-<?= $p['status'] ?>">
                    <?= ['active'=>'Active','trial'=>'Trial','suspended'=>'Suspendue'][$p['status']] ?>
                </span></td>
                <td style="font-size:0.8rem;color:#6B7280;">
                    <?= $p['trial_ends_at'] ? date('d/m/Y', strtotime($p['trial_ends_at'])) : '—' ?>
                </td>
                <td style="text-align:center;"><?= $p['nb_users'] ?></td>
                <td style="font-size:0.8rem;color:#6B7280;">
                    <?= date('d/m/Y', strtotime($p['created_at'])) ?>
                </td>
                <td>
                    <div style="display:flex;gap:0.35rem;">
                        <a href="view.php?id=<?= $p['id'] ?>" class="btn-sm btn-outline">Voir</a>
                        <a href="edit.php?id=<?= $p['id'] ?>" class="btn-sm btn-outline">Éditer</a>
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="toggle_status">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="status" value="<?= $p['status'] ?>">
                            <button type="submit" class="btn-sm <?= $p['status']==='active' ? 'btn-danger' : 'btn-primary' ?>"
                                    onclick="return confirm('Confirmer ?')">
                                <?= $p['status'] === 'active' ? 'Suspendre' : 'Activer' ?>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($pharmacies)): ?>
            <tr><td colspan="10" style="text-align:center;color:#9CA3AF;padding:2rem;">Aucune pharmacie trouvée</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once dirname(__DIR__) . '/config/layout_footer.php'; ?>
