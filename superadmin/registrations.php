<?php
require_once __DIR__ . '/config/auth.php';
sa_check_auth();

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/Mailer.php';
require_once dirname(__DIR__) . '/analytics/config/db.php';

$db  = sa_db();
$adb = analytics_db();

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

function makeSlug(string $name): string {
    $s = strtolower(trim($name));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'pharmacie';
}

function makeApiKey(): string {
    return bin2hex(random_bytes(32)); // 64 hex chars
}

function redirectReg(string $src = '', string $st = ''): never {
    $params = array_filter(['source' => $src, 'status' => $st]);
    header('Location: /superadmin/registrations.php' . ($params ? '?' . http_build_query($params) : ''));
    exit;
}

// ── Ensure tables ──────────────────────────────────────────────────────────

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

try { $adb->exec("CREATE TABLE IF NOT EXISTS dm_trial_requests (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pharmacy_name VARCHAR(200) NOT NULL,
    contact_name  VARCHAR(200) NOT NULL,
    email         VARCHAR(200) NOT NULL,
    phone         VARCHAR(60)  DEFAULT NULL,
    city          VARCHAR(120) DEFAULT NULL,
    message       TEXT         DEFAULT NULL,
    status        ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}

// ── Actions ────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action']  ?? '';
    $source     = $_POST['source']  ?? '';
    $regId      = (int) ($_POST['reg_id'] ?? 0);
    $dmId       = (int) ($_POST['dm_id']  ?? 0);

    // ── digiMind trial request actions ────────────────────────────────────
    if ($source === 'digimind' && $dmId) {
        try {
            $req = $adb->prepare("SELECT * FROM dm_trial_requests WHERE id = ?")->execute([$dmId])
                ?: null;
            $stmt = $adb->prepare("SELECT * FROM dm_trial_requests WHERE id = ?");
            $stmt->execute([$dmId]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$req) throw new Exception("Demande introuvable.");

            if ($action === 'approve' && $req['status'] !== 'approved') {

                // Unique slug for ai_pharmacies
                $baseSlug = makeSlug($req['pharmacy_name']);
                $slug = $baseSlug; $n = 1;
                while ($adb->prepare("SELECT id FROM ai_pharmacies WHERE slug=?")->execute([$slug])
                    && $adb->query("SELECT id FROM ai_pharmacies WHERE slug='$slug'")->fetchColumn()) {
                    $slug = $baseSlug . '-' . $n++;
                }

                // Create pharmacy
                $apiKey = makeApiKey();
                $adb->prepare(
                    "INSERT INTO ai_pharmacies (name, slug, api_key, plan, is_active) VALUES (?,?,?,'starter',1)"
                )->execute([$req['pharmacy_name'], $slug, $apiKey]);
                $newPharmacyId = (int) $adb->lastInsertId();

                // Create user
                $tempPass = generateTempPassword();
                $adb->prepare(
                    "INSERT INTO ai_users (pharmacy_id, email, display_name, role, password, is_active)
                     VALUES (?, ?, ?, 'admin', ?, 1)"
                )->execute([$newPharmacyId, $req['email'], $req['contact_name'], password_hash($tempPass, PASSWORD_BCRYPT)]);

                // Update trial request
                $adb->prepare("UPDATE dm_trial_requests SET status='approved' WHERE id=?")->execute([$dmId]);

                // Send credentials email
                if (defined('SMTP_HOST')) {
                    require_once dirname(__DIR__) . '/vendor/autoload.php';
                    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host       = SMTP_HOST;
                        $mail->SMTPAuth   = true;
                        $mail->Username   = SMTP_USERNAME;
                        $mail->Password   = SMTP_PASSWORD;
                        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                        $mail->Port       = SMTP_PORT;
                        $mail->CharSet    = 'UTF-8';
                        $mail->setFrom(SMTP_USERNAME, 'digiMind');
                        $mail->addAddress($req['email'], $req['contact_name']);
                        $mail->Subject = "Votre accès digiMind AI est prêt — {$req['pharmacy_name']}";
                        $loginUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/analytics/';
                        $mail->isHTML(false);
                        $mail->Body =
                            "Bonjour {$req['contact_name']},\n\n"
                            . "Votre essai gratuit digiMind AI a été activé.\n\n"
                            . "Pharmacie : {$req['pharmacy_name']}\n"
                            . "Email     : {$req['email']}\n"
                            . "Mot de passe temporaire : {$tempPass}\n\n"
                            . "Connectez-vous ici : {$loginUrl}\n\n"
                            . "Pensez à changer votre mot de passe après la première connexion.\n\n"
                            . "L'équipe digiMind\ndigitaltechnologiescongo.com";
                        $mail->send();
                    } catch (\Exception $ignored) {}
                }

                $flash = ['type' => 'success', 'msg' => "Accès digiMind activé pour <strong>{$req['pharmacy_name']}</strong>. Identifiants envoyés à {$req['email']}."];

            } elseif ($action === 'reject' && $req['status'] !== 'rejected') {
                $adb->prepare("UPDATE dm_trial_requests SET status='rejected' WHERE id=?")->execute([$dmId]);
                $flash = ['type' => 'warning', 'msg' => "Demande digiMind de <strong>{$req['pharmacy_name']}</strong> rejetée."];

            } elseif ($action === 'delete') {
                $adb->prepare("DELETE FROM dm_trial_requests WHERE id=?")->execute([$dmId]);
                $flash = ['type' => 'info', 'msg' => "Demande supprimée."];
            }

        } catch (Exception $e) {
            $flash = ['type' => 'error', 'msg' => "Erreur : " . htmlspecialchars($e->getMessage())];
        }

        $_SESSION['flash_type'] = $flash['type'];
        $_SESSION['flash_msg']  = $flash['msg'];
        redirectReg('digimind', $_GET['status'] ?? '');
    }

    // ── digiPharm registration actions (existing logic) ───────────────────
    if ($regId) {
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

                $planMapped = $reg['plan'] === 'pro' ? 'pro' : 'starter';
                db_exec($db,
                    "INSERT INTO pharmacies (name, email, phone, city, plan, status, trial_ends_at)
                     VALUES (?, ?, ?, ?, ?, 'trial', DATE_ADD(CURDATE(), INTERVAL 14 DAY))",
                    [$reg['pharmacy_name'], $reg['email'], $reg['phone'] ?? '', $reg['city'] ?? '', $planMapped]
                );
                $newPharmacyId = (int) $db->lastInsertId();

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
        redirectReg('digipharm', $_GET['status'] ?? '');
    }
}

// ── Filters ────────────────────────────────────────────────────────────────

$sourceFilter = in_array($_GET['source'] ?? '', ['digipharm', 'digimind']) ? $_GET['source'] : '';
$statusFilter = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected'])  ? $_GET['status'] : '';

function filterUrl(string $src, string $st = ''): string {
    global $sourceFilter, $statusFilter;
    $s  = $src !== '__keep__' ? $src : $sourceFilter;
    $st = $st  !== '__keep__' ? $st  : $statusFilter;
    $p  = array_filter(['source' => $s, 'status' => $st]);
    return '/superadmin/registrations.php' . ($p ? '?' . http_build_query($p) : '');
}

// ── Fetch & normalize ──────────────────────────────────────────────────────

$rows = [];

// digiPharm rows
if ($sourceFilter !== 'digimind') {
    $pharmaWhere = $statusFilter ? "WHERE status=?" : "WHERE 1";
    $pharmaArgs  = $statusFilter ? [$statusFilter] : [];
    $pharmaRegs  = db_all($db, "SELECT * FROM pharmacy_registrations $pharmaWhere ORDER BY created_at DESC", $pharmaArgs);
    foreach ($pharmaRegs as $r) {
        $rows[] = [
            'source'        => 'digipharm',
            'id'            => $r['id'],
            'pharmacy_name' => $r['pharmacy_name'],
            'contact_name'  => $r['responsible_name'],
            'email'         => $r['email'],
            'phone'         => $r['phone'] ?? '',
            'city'          => $r['city'] ?? '',
            'plan'          => $r['plan'],
            'status'        => $r['status'],
            'created_at'    => $r['created_at'],
            'message'       => '',
        ];
    }
}

// digiMind rows
if ($sourceFilter !== 'digipharm') {
    $mindWhere = $statusFilter ? "WHERE status=?" : "WHERE 1";
    $mindArgs  = $statusFilter ? [$statusFilter] : [];
    $mindStmt  = $adb->prepare("SELECT * FROM dm_trial_requests $mindWhere ORDER BY created_at DESC");
    $mindStmt->execute($mindArgs);
    $mindRegs = $mindStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($mindRegs as $r) {
        $rows[] = [
            'source'        => 'digimind',
            'id'            => $r['id'],
            'pharmacy_name' => $r['pharmacy_name'],
            'contact_name'  => $r['contact_name'],
            'email'         => $r['email'],
            'phone'         => $r['phone'] ?? '',
            'city'          => $r['city'] ?? '',
            'plan'          => null,
            'status'        => $r['status'],
            'created_at'    => $r['created_at'],
            'message'       => $r['message'] ?? '',
        ];
    }
}

// Sort merged list by created_at DESC
usort($rows, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

// ── Stats ──────────────────────────────────────────────────────────────────

$statsBase = db_fetch($db, "SELECT
    COUNT(*) AS total,
    SUM(status='pending')  AS pending,
    SUM(status='approved') AS approved,
    SUM(status='rejected') AS rejected
FROM pharmacy_registrations") ?: ['total'=>0,'pending'=>0,'approved'=>0,'rejected'=>0];

$mindStmt = $adb->query("SELECT
    COUNT(*) AS total,
    SUM(status='pending')  AS pending,
    SUM(status='approved') AS approved,
    SUM(status='rejected') AS rejected
FROM dm_trial_requests");
$statsMind = $mindStmt->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'pending'=>0,'approved'=>0,'rejected'=>0];

$stats = [
    'total'    => (int)$statsBase['total']    + (int)$statsMind['total'],
    'pending'  => (int)$statsBase['pending']  + (int)$statsMind['pending'],
    'approved' => (int)$statsBase['approved'] + (int)$statsMind['approved'],
    'rejected' => (int)$statsBase['rejected'] + (int)$statsMind['rejected'],
];

// Per-source totals for tabs
$pharmaTotal = (int)$statsBase['total'];
$mindTotal   = (int)$statsMind['total'];

require_once __DIR__ . '/config/layout_header.php';
?>

<style>
.source-badge { display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:99px;font-size:.7rem;font-weight:700;letter-spacing:.02em; }
.source-badge.digipharm { background:#d1fae5;color:#065f46; }
.source-badge.digimind  { background:#ccfbf1;color:#0f766e; }
.filter-group { display:flex;flex-direction:column;gap:0; }
.filter-row   { display:flex;flex-wrap:wrap;align-items:center;gap:4px;padding:10px 1.25rem;border-bottom:1px solid #f3f4f6; }
.filter-row:last-child { border-bottom:none; }
.filter-label { font-size:.7rem;font-weight:600;color:#9CA3AF;text-transform:uppercase;letter-spacing:.05em;min-width:60px; }
</style>

<?php if ($flash['msg']): ?>
<div class="alert alert-<?= in_array($flash['type'], ['success','error','warning','info']) ? $flash['type'] : 'info' ?>">
    <?= $flash['msg'] ?>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="kpi-grid">
    <div class="kpi-card teal">
        <div class="kpi-value"><?= $stats['total'] ?></div>
        <div class="kpi-label">Total des demandes</div>
    </div>
    <div class="kpi-card yellow">
        <div class="kpi-value"><?= $stats['pending'] ?></div>
        <div class="kpi-label">En attente</div>
    </div>
    <div class="kpi-card green">
        <div class="kpi-value"><?= $stats['approved'] ?></div>
        <div class="kpi-label">Approuvées</div>
    </div>
    <div class="kpi-card red">
        <div class="kpi-value"><?= $stats['rejected'] ?></div>
        <div class="kpi-label">Rejetées</div>
    </div>
</div>

<!-- Table -->
<div class="table-card">
    <div class="table-header">
        <span class="table-title">Demandes d'accès</span>
        <div style="display:flex;gap:8px">
            <a href="/register" target="_blank" class="btn-sm btn-outline">digiPharm →</a>
            <a href="/analytics/register" target="_blank" class="btn-sm btn-outline">digiMind →</a>
        </div>
    </div>

    <div class="filter-group">
        <!-- Source filter -->
        <div class="filter-row">
            <span class="filter-label">Source</span>
            <div class="filter-tabs" style="margin:0;padding:0;border:none">
                <a href="<?= filterUrl('') ?>"          class="filter-tab <?= !$sourceFilter            ? 'active' : '' ?>">Toutes     <span class="count"><?= $stats['total']  ?></span></a>
                <a href="<?= filterUrl('digipharm') ?>" class="filter-tab <?= $sourceFilter==='digipharm' ? 'active' : '' ?>">digiPharm  <span class="count"><?= $pharmaTotal ?></span></a>
                <a href="<?= filterUrl('digimind') ?>"  class="filter-tab <?= $sourceFilter==='digimind'  ? 'active' : '' ?>">digiMind   <span class="count"><?= $mindTotal   ?></span></a>
            </div>
        </div>
        <!-- Status filter -->
        <div class="filter-row">
            <span class="filter-label">Statut</span>
            <div class="filter-tabs" style="margin:0;padding:0;border:none">
                <a href="<?= filterUrl('__keep__', '') ?>"         class="filter-tab <?= !$statusFilter             ? 'active' : '' ?>">Toutes     <span class="count"><?= $stats['total']    ?></span></a>
                <a href="<?= filterUrl('__keep__', 'pending') ?>"  class="filter-tab <?= $statusFilter==='pending'  ? 'active' : '' ?>">En attente <span class="count"><?= $stats['pending']  ?></span></a>
                <a href="<?= filterUrl('__keep__', 'approved') ?>" class="filter-tab <?= $statusFilter==='approved' ? 'active' : '' ?>">Approuvées <span class="count"><?= $stats['approved'] ?></span></a>
                <a href="<?= filterUrl('__keep__', 'rejected') ?>" class="filter-tab <?= $statusFilter==='rejected' ? 'active' : '' ?>">Rejetées   <span class="count"><?= $stats['rejected'] ?></span></a>
            </div>
        </div>
    </div>

    <?php if (empty($rows)): ?>
    <div class="empty-state">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <h3>Aucune demande</h3>
        <p style="font-size:.8rem;margin-top:4px">Les inscriptions apparaîtront ici.</p>
    </div>
    <?php else: ?>
    <div class="table-scroll">
    <table>
        <thead>
            <tr>
                <th>Source</th>
                <th>Pharmacie</th>
                <th>Contact</th>
                <th>Email / Tél</th>
                <th>Forfait</th>
                <th>Statut</th>
                <th>Date</th>
                <th style="width:180px">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td>
                <span class="source-badge <?= $r['source'] ?>">
                    <?= $r['source'] === 'digipharm' ? '💊 digiPharm' : '🧠 digiMind' ?>
                </span>
            </td>
            <td>
                <div style="font-weight:600;color:#111827"><?= htmlspecialchars($r['pharmacy_name']) ?></div>
                <?php if ($r['city']): ?>
                <div style="font-size:.75rem;color:#9CA3AF;margin-top:2px"><?= htmlspecialchars($r['city']) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <?= htmlspecialchars($r['contact_name']) ?>
                <?php if ($r['message']): ?>
                <div style="font-size:.7rem;color:#9CA3AF;margin-top:2px;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                     title="<?= htmlspecialchars($r['message']) ?>">
                    <?= htmlspecialchars(mb_substr($r['message'], 0, 60)) ?>…
                </div>
                <?php endif; ?>
            </td>
            <td>
                <div><?= htmlspecialchars($r['email']) ?></div>
                <?php if ($r['phone']): ?>
                <div style="font-size:.75rem;color:#9CA3AF"><?= htmlspecialchars($r['phone']) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($r['source'] === 'digipharm'): ?>
                    <span class="badge badge-<?= $r['plan'] ?>"><?= $r['plan'] === 'pro' ? '★ Pro + IA' : 'Basique' ?></span>
                <?php else: ?>
                    <span class="badge badge-teal" style="background:#ccfbf1;color:#0f766e;border-color:#99f6e4">$20 / mois</span>
                <?php endif; ?>
            </td>
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
                <?php
                $src    = $r['source'];
                $idKey  = $src === 'digimind' ? 'dm_id' : 'reg_id';
                $idVal  = $r['id'];
                $name   = htmlspecialchars(addslashes($r['pharmacy_name']));
                ?>
                <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                <?php if ($r['status'] === 'pending'): ?>
                    <form method="POST" style="margin:0">
                        <input type="hidden" name="source"  value="<?= $src ?>">
                        <input type="hidden" name="<?= $idKey ?>" value="<?= $idVal ?>">
                        <input type="hidden" name="action"  value="approve">
                        <button class="btn-sm btn-primary" onclick="return confirm('Approuver et créer le compte pour <?= $name ?> ?')">✓ Approuver</button>
                    </form>
                    <form method="POST" style="margin:0">
                        <input type="hidden" name="source"  value="<?= $src ?>">
                        <input type="hidden" name="<?= $idKey ?>" value="<?= $idVal ?>">
                        <input type="hidden" name="action"  value="reject">
                        <button class="btn-sm btn-danger" onclick="return confirm('Rejeter la demande ?')">✕ Rejeter</button>
                    </form>
                <?php elseif ($r['status'] === 'approved'): ?>
                    <span style="font-size:.8rem;color:#16A34A;font-weight:600">✓ Activé</span>
                <?php else: ?>
                    <form method="POST" style="margin:0">
                        <input type="hidden" name="source"  value="<?= $src ?>">
                        <input type="hidden" name="<?= $idKey ?>" value="<?= $idVal ?>">
                        <input type="hidden" name="action"  value="approve">
                        <button class="btn-sm btn-warning" onclick="return confirm('Ré-approuver ?')">↺ Ré-activer</button>
                    </form>
                <?php endif; ?>
                    <form method="POST" style="margin:0">
                        <input type="hidden" name="source"  value="<?= $src ?>">
                        <input type="hidden" name="<?= $idKey ?>" value="<?= $idVal ?>">
                        <input type="hidden" name="action"  value="delete">
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
