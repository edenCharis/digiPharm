<?php
require_once dirname(__DIR__) . '/config/auth.php';
sa_check_auth();

$db = sa_db();

// Filtres
$filter_pharmacy = (int)($_GET['pharmacy_id'] ?? 0);
$limit = 100;

$where  = ["1=1"];
$params = [];
if ($filter_pharmacy) { $where[] = "l.pharmacy_id = ?"; $params[] = $filter_pharmacy; }

$logs = $db->prepare("
    SELECT l.*, p.name AS pharmacy_name, u.username
    FROM log l
    LEFT JOIN pharmacies p ON p.id = l.pharmacy_id
    LEFT JOIN user u       ON u.id = l.userId
    WHERE " . implode(' AND ', $where) . "
    ORDER BY l.id DESC
    LIMIT $limit
");
$logs->execute($params);
$logs = $logs->fetchAll();

$pharmacies = $db->query("SELECT id, name FROM pharmacies ORDER BY name")->fetchAll();

// Colonnes disponibles dans log (adapter selon ton schéma réel)
$log_columns = $db->query("SHOW COLUMNS FROM log")->fetchAll(PDO::FETCH_COLUMN);

require_once dirname(__DIR__) . '/config/layout_header.php';
?>

<!-- Filtre -->
<div class="table-card" style="margin-bottom:1.25rem;">
    <div style="padding:1rem 1.25rem;">
        <form method="GET" style="display:flex;gap:0.75rem;align-items:flex-end;">
            <div class="form-group" style="margin:0;min-width:220px;">
                <label style="font-size:0.75rem;">Filtrer par pharmacie</label>
                <select name="pharmacy_id">
                    <option value="0">Toutes les pharmacies</option>
                    <?php foreach ($pharmacies as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $filter_pharmacy===$p['id']?'selected':'' ?>>
                        <?= htmlspecialchars($p['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-sm btn-primary" style="padding:0.6rem 1rem;">Filtrer</button>
            <a href="logs.php" class="btn-sm btn-outline" style="padding:0.6rem 1rem;">Reset</a>
        </form>
    </div>
</div>

<!-- Logs table -->
<div class="table-card">
    <div class="table-header">
        <div class="table-title">Logs système (<?= count($logs) ?> derniers)</div>
    </div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <?php if (in_array('createdAt', $log_columns) || in_array('created_at', $log_columns)): ?>
                <th>Date</th>
                <?php endif; ?>
                <th>Pharmacie</th>
                <th>Utilisateur</th>
                <?php
                // Afficher les colonnes métier dynamiquement
                $skip = ['id','pharmacy_id','userId','createdAt','created_at'];
                $show_cols = array_filter($log_columns, fn($c) => !in_array($c, $skip));
                foreach ($show_cols as $col):
                ?>
                <th><?= ucfirst($col) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td style="color:#9CA3AF;font-size:0.8rem;"><?= $log['id'] ?></td>
                <?php if (in_array('createdAt', $log_columns)): ?>
                <td style="font-size:0.8rem;white-space:nowrap;color:#6B7280;">
                    <?= $log['createdAt'] ? date('d/m/Y H:i', strtotime($log['createdAt'])) : '—' ?>
                </td>
                <?php elseif (in_array('created_at', $log_columns)): ?>
                <td style="font-size:0.8rem;white-space:nowrap;color:#6B7280;">
                    <?= $log['created_at'] ? date('d/m/Y H:i', strtotime($log['created_at'])) : '—' ?>
                </td>
                <?php endif; ?>
                <td>
                    <span class="log-pharmacy"><?= htmlspecialchars($log['pharmacy_name'] ?? '—') ?></span>
                </td>
                <td style="font-size:0.85rem;font-family:monospace;">
                    <?= htmlspecialchars($log['username'] ?? '—') ?>
                </td>
                <?php foreach ($show_cols as $col): ?>
                <td style="font-size:0.85rem;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?= htmlspecialchars((string)($log[$col] ?? '—')) ?>
                </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
            <tr><td colspan="10" style="text-align:center;color:#9CA3AF;padding:2rem;">Aucun log disponible</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once dirname(__DIR__) . '/config/layout_footer.php'; ?>
