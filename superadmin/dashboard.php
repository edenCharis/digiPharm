<?php
require_once __DIR__ . '/config/auth.php';
sa_check_auth();

$db = sa_db();

// Stats globales
$stats = $db->query("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'active')    AS active,
        SUM(status = 'trial')     AS trial,
        SUM(status = 'suspended') AS suspended
    FROM pharmacies
")->fetch();

// Total users admins
$total_users = $db->query("SELECT COUNT(*) FROM user")->fetchColumn();

// Dernières pharmacies créées
$recent_pharmacies = $db->query("
    SELECT p.*, COUNT(u.id) AS nb_users
    FROM pharmacies p
    LEFT JOIN user u ON u.pharmacy_id = p.id
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 5
")->fetchAll();

// Derniers logs
$recent_logs = $db->query("
    SELECT l.*, p.name AS pharmacy_name
    FROM log l
    LEFT JOIN pharmacies p ON p.id = l.pharmacy_id
    ORDER BY l.id DESC
    LIMIT 10
")->fetchAll();

require_once __DIR__ . '/config/layout_header.php';
?>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card teal">
        <div class="kpi-value"><?= $stats['total'] ?></div>
        <div class="kpi-label">Pharmacies totales</div>
    </div>
    <div class="kpi-card green">
        <div class="kpi-value"><?= $stats['active'] ?></div>
        <div class="kpi-label">Pharmacies actives</div>
    </div>
    <div class="kpi-card yellow">
        <div class="kpi-value"><?= $stats['trial'] ?></div>
        <div class="kpi-label">En période d'essai</div>
    </div>
    <div class="kpi-card red">
        <div class="kpi-value"><?= $total_users ?></div>
        <div class="kpi-label">Comptes utilisateurs</div>
    </div>
</div>

<div style="display:grid; grid-template-columns:1.4fr 1fr; gap:1.5rem;">

    <!-- Dernières pharmacies -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Pharmacies récentes</div>
            <a href="pharmacies/list.php" class="btn-sm btn-outline">Voir tout</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Pharmacie</th>
                    <th>Plan</th>
                    <th>Statut</th>
                    <th>Utilisateurs</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_pharmacies as $p): ?>
                <tr>
                    <td>
                        <div style="font-weight:500;"><?= htmlspecialchars($p['name']) ?></div>
                        <div style="font-size:0.75rem;color:#6B7280;"><?= htmlspecialchars($p['city']) ?></div>
                    </td>
                    <td><span class="badge badge-<?= $p['plan'] ?>"><?= ucfirst($p['plan']) ?></span></td>
                    <td><span class="badge badge-<?= $p['status'] ?>">
                        <?= ['active'=>'Active','trial'=>'Trial','suspended'=>'Suspendue'][$p['status']] ?>
                    </span></td>
                    <td style="text-align:center;"><?= $p['nb_users'] ?></td>
                    <td>
                        <a href="pharmacies/view.php?id=<?= $p['id'] ?>" class="btn-sm btn-outline">Voir</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent_pharmacies)): ?>
                <tr><td colspan="5" style="text-align:center;color:#9CA3AF;padding:2rem;">
                    Aucune pharmacie créée
                </td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Derniers logs -->
    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Activité récente</div>
            <a href="pharmacies/logs.php" class="btn-sm btn-outline">Tous les logs</a>
        </div>
        <?php if (empty($recent_logs)): ?>
            <div style="text-align:center;color:#9CA3AF;padding:2rem;font-size:0.875rem;">Aucun log disponible</div>
        <?php else: ?>
            <?php foreach ($recent_logs as $log): ?>
            <div class="log-entry">
                <div class="log-dot"></div>
                <div style="flex:1;">
                    <div class="log-msg"><?= htmlspecialchars($log['action'] ?? $log['message'] ?? 'Action système') ?></div>
                    <div style="display:flex;gap:0.75rem;margin-top:2px;">
                        <?php if ($log['pharmacy_name']): ?>
                        <span class="log-pharmacy"><?= htmlspecialchars($log['pharmacy_name']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/config/layout_footer.php'; ?>
