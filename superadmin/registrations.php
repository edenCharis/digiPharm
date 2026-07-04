<?php
require_once __DIR__ . '/config/auth.php';
sa_check_auth();

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/Mailer.php';

$db = sa_db(); // returns PDO

$flash = ['type' => $_SESSION['flash_type'] ?? '', 'msg' => $_SESSION['flash_msg'] ?? ''];
unset($_SESSION['flash_type'], $_SESSION['flash_msg']);

// ── PDO helpers ────────────────────────────────────────────────────────────

function db_exec(PDO $db, string $sql, array $p = []): void {
    $db->prepare($sql)->execute($p);
}
function db_fetch(PDO $db, string $sql, array $p = []): array|false {
    $s = $db->prepare($sql); $s->execute($p);
    return $s->fetch(PDO::FETCH_ASSOC);
}
function db_all(PDO $db, string $sql, array $p = []): array {
    $s = $db->prepare($sql); $s->execute($p);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

// ── Helpers ────────────────────────────────────────────────────────────────

function generateUUID(): string {
    $d = random_bytes(16);
    $d[6] = chr(ord($d[6]) & 0x0f | 0x40);
    $d[8] = chr(ord($d[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

function generateTempPassword(int $len = 10): string {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#';
    $pwd = '';
    for ($i = 0; $i < $len; $i++) $pwd .= $chars[random_int(0, strlen($chars) - 1)];
    return $pwd;
}

// ── Ensure table exists ────────────────────────────────────────────────────

try { $db->exec("CREATE TABLE IF NOT EXISTS pharmacy_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan VARCHAR(20) NOT NULL DEFAULT 'basic',
    pharmacy_name VARCHAR(255) NOT NULL,
    responsible_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}

// ── Actions ────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['reg_id'])) {
    $regId  = (int) $_POST['reg_id'];
    $action = $_POST['action'];

    try {
        $reg = db_fetch($db, "SELECT * FROM pharmacy_registrations WHERE id = ?", [$regId]);
        if (!$reg) throw new Exception("Inscription introuvable.");

        if ($action === 'approve' && $reg['status'] !== 'approved') {

            $db->exec("CREATE TABLE IF NOT EXISTS pharmacies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                responsible_name VARCHAR(255),
                email VARCHAR(255),
                phone VARCHAR(50),
                address VARCHAR(255),
                city VARCHAR(100),
                plan ENUM('basic','pro','enterprise') DEFAULT 'basic',
                status ENUM('active','trial','suspended') DEFAULT 'trial',
                trial_ends_at DATETIME NULL,
                created_at DATETIME DEFAULT NOW()
            )");

            try { $db->exec("ALTER TABLE pharmacy_registrations ADD COLUMN pharmacy_id INT NULL AFTER id"); } catch (Exception $e) {}
            try { $db->exec("ALTER TABLE pharmacy_registrations ADD COLUMN approved_at DATETIME NULL AFTER status"); } catch (Exception $e) {}

            // Create pharmacy — map plan: basic→starter (DB enum: starter/pro/enterprise)
            $planMapped = $reg['plan'] === 'pro' ? 'pro' : 'starter';
            db_exec($db,
                "INSERT INTO pharmacies (name, email, phone, city, plan, status, trial_ends_at)
                 VALUES (?, ?, ?, ?, ?, 'trial', DATE_ADD(CURDATE(), INTERVAL 14 DAY))",
                [$reg['pharmacy_name'], $reg['email'], $reg['phone'] ?? '', $reg['city'] ?? '', $planMapped]
            );
            $newPharmacyId = (int) $db->lastInsertId();

            // Unique username
            $baseUser = strtolower(explode('@', $reg['email'])[0]);
            $username = $baseUser; $suffix = 1;
            while (db_fetch($db, "SELECT id FROM user WHERE username = ?", [$username]))
                $username = $baseUser . $suffix++;

            $tempPass = generateTempPassword();
            db_exec($db,
                "INSERT INTO user (id, username, password, role, email, statut, pharmacy_id)
                 VALUES (?, ?, ?, 'ADMIN', ?, 1, ?)",
                [generateUUID(), $username, password_hash($tempPass, PASSWORD_DEFAULT), $reg['email'], $newPharmacyId]
            );

            db_exec($db,
                "UPDATE pharmacy_registrations SET status='approved', approved_at=NOW(), pharmacy_id=? WHERE id=?",
                [$newPharmacyId, $regId]
            );

            $loginUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/';
            Mailer::accountActivation($reg['email'], $reg['pharmacy_name'], $reg['responsible_name'], $username, $tempPass, $loginUrl);

            $flash = ['type' => 'success', 'msg' => "Compte activé pour <strong>{$reg['pharmacy_name']}</strong> (pharmacie #{$newPharmacyId}). Email envoyé à {$reg['email']}."];

        } elseif ($action === 'reject' && $reg['status'] !== 'rejected') {
            db_exec($db, "UPDATE pharmacy_registrations SET status='rejected' WHERE id=?", [$regId]);
            Mailer::registrationRejected($reg['email'], $reg['pharmacy_name'], $reg['responsible_name']);
            $flash = ['type' => 'warning', 'msg' => "Demande de <strong>{$reg['pharmacy_name']}</strong> rejetée."];

        } elseif ($action === 'delete') {
            db_exec($db, "DELETE FROM pharmacy_registrations WHERE id=?", [$regId]);
            $flash = ['type' => 'info', 'msg' => "Inscription supprimée."];
        }

    } catch (Exception $e) {
        $flash = ['type' => 'error', 'msg' => "Erreur : " . htmlspecialchars($e->getMessage())];
    }

    $_SESSION['flash_type'] = $flash['type'];
    $_SESSION['flash_msg']  = $flash['msg'];
    $qs = $statusFilter ?? '';
    header('Location: /superadmin/registrations.php' . ($qs ? '?status=' . $qs : ''));
    exit;
}

// ── Data ───────────────────────────────────────────────────────────────────

$statusFilter = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected']) ? $_GET['status'] : '';

if ($statusFilter) {
    $regs = db_all($db, "SELECT * FROM pharmacy_registrations WHERE status=? ORDER BY created_at DESC", [$statusFilter]);
} else {
    $regs = db_all($db, "SELECT * FROM pharmacy_registrations ORDER BY created_at DESC");
}

$stats_reg = db_fetch($db, "SELECT
    COUNT(*) AS total,
    SUM(status='pending')  AS pending,
    SUM(status='approved') AS approved,
    SUM(status='rejected') AS rejected
FROM pharmacy_registrations");

require_once __DIR__ . '/config/layout_header.php';
?>

<?php if ($flash['msg']): ?>
<div class="alert alert-<?= in_array($flash['type'], ['success','error','warning','info']) ? $flash['type'] : 'info' ?>">
    <?= $flash['msg'] ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="kpi-grid">
    <div class="kpi-card teal">
        <div class="kpi-value"><?= (int)$stats_reg['total'] ?></div>
        <div class="kpi-label">Total des demandes</div>
    </div>
    <div class="kpi-card yellow">
        <div class="kpi-value"><?= (int)$stats_reg['pending'] ?></div>
        <div class="kpi-label">En attente</div>
    </div>
    <div class="kpi-card green">
        <div class="kpi-value"><?= (int)$stats_reg['approved'] ?></div>
        <div class="kpi-label">Approuvées</div>
    </div>
    <div class="kpi-card red">
        <div class="kpi-value"><?= (int)$stats_reg['rejected'] ?></div>
        <div class="kpi-label">Rejetées</div>
    </div>
</div>

<!-- Table -->
<div class="table-card">
    <div class="table-header">
        <span class="table-title">Demandes d'accès</span>
        <a href="/register" target="_blank" class="btn-sm btn-outline">Voir la page d'inscription →</a>
    </div>

    <div style="padding:0 1.25rem">
        <div class="filter-tabs">
            <a href="/superadmin/registrations.php"                 class="filter-tab <?= !$statusFilter             ? 'active' : '' ?>">Toutes     <span class="count"><?= (int)$stats_reg['total']    ?></span></a>
            <a href="/superadmin/registrations.php?status=pending"  class="filter-tab <?= $statusFilter==='pending'  ? 'active' : '' ?>">En attente <span class="count"><?= (int)$stats_reg['pending']  ?></span></a>
            <a href="/superadmin/registrations.php?status=approved" class="filter-tab <?= $statusFilter==='approved' ? 'active' : '' ?>">Approuvées <span class="count"><?= (int)$stats_reg['approved'] ?></span></a>
            <a href="/superadmin/registrations.php?status=rejected" class="filter-tab <?= $statusFilter==='rejected' ? 'active' : '' ?>">Rejetées   <span class="count"><?= (int)$stats_reg['rejected'] ?></span></a>
        </div>
    </div>

    <?php if (empty($regs)): ?>
    <div class="empty-state">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <h3>Aucune demande</h3>
        <p style="font-size:.8rem;margin-top:4px">Les inscriptions via la page d'accueil apparaîtront ici.</p>
    </div>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table>
        <thead>
            <tr>
                <th>Pharmacie</th>
                <th>Responsable</th>
                <th>Contact</th>
                <th>Forfait</th>
                <th>Statut</th>
                <th>Date</th>
                <th style="width:180px">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($regs as $r): ?>
        <tr>
            <td>
                <div style="font-weight:600;color:#111827"><?= htmlspecialchars($r['pharmacy_name']) ?></div>
                <?php if ($r['city']): ?>
                <div style="font-size:.75rem;color:#9CA3AF;margin-top:2px"><?= htmlspecialchars($r['city']) ?></div>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($r['responsible_name']) ?></td>
            <td>
                <div><?= htmlspecialchars($r['email']) ?></div>
                <?php if ($r['phone']): ?>
                <div style="font-size:.75rem;color:#9CA3AF"><?= htmlspecialchars($r['phone']) ?></div>
                <?php endif; ?>
            </td>
            <td><span class="badge badge-<?= $r['plan'] ?>"><?= $r['plan'] === 'pro' ? '★ Pro + IA' : 'Basique' ?></span></td>
            <td>
                <span class="badge badge-<?= $r['status'] ?>">
                    <?= match($r['status']) { 'pending' => 'En attente', 'approved' => 'Approuvée', 'rejected' => 'Rejetée', default => $r['status'] } ?>
                </span>
            </td>
            <td style="font-size:.75rem;color:#9CA3AF;white-space:nowrap">
                <?= date('d/m/Y', strtotime($r['created_at'])) ?><br>
                <?= date('H:i',   strtotime($r['created_at'])) ?>
            </td>
            <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                <?php if ($r['status'] === 'pending'): ?>
                    <form method="POST" style="margin:0">
                        <input type="hidden" name="reg_id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button class="btn-sm btn-primary" onclick="return confirm('Approuver et créer le compte pour <?= htmlspecialchars(addslashes($r['pharmacy_name'])) ?> ?')">✓ Approuver</button>
                    </form>
                    <form method="POST" style="margin:0">
                        <input type="hidden" name="reg_id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <button class="btn-sm btn-danger" onclick="return confirm('Rejeter la demande ?')">✕ Rejeter</button>
                    </form>
                <?php elseif ($r['status'] === 'approved'): ?>
                    <span style="font-size:.8rem;color:#16A34A;font-weight:600">✓ Activé</span>
                <?php else: ?>
                    <form method="POST" style="margin:0">
                        <input type="hidden" name="reg_id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button class="btn-sm btn-warning" onclick="return confirm('Ré-approuver ?')">↺ Ré-activer</button>
                    </form>
                <?php endif; ?>
                    <form method="POST" style="margin:0">
                        <input type="hidden" name="reg_id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn-sm btn-outline" style="color:#9CA3AF" onclick="return confirm('Supprimer définitivement ?')">✕</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/config/layout_footer.php'; ?>
