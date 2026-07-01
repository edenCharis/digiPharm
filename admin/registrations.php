<?php
// This page is platform-level — superadmin only.
// Regular pharmacy admins must never access it.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../superadmin/config/auth.php';
sa_check_auth(); // redirects to /superadmin/login.php if not superadmin

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../config/database.php';
require_once '../includes/Mailer.php';

$flash = ['type' => '', 'msg' => ''];

// ─── Helpers ──────────────────────────────────────────────────────────────────

function generateUUID(): string {
    $d = random_bytes(16);
    $d[6] = chr(ord($d[6]) & 0x0f | 0x40);
    $d[8] = chr(ord($d[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

function generateTempPassword(int $len = 10): string {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#';
    $pwd = '';
    for ($i = 0; $i < $len; $i++) {
        $pwd .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pwd;
}

function slugUsername(string $email): string {
    return strtolower(explode('@', $email)[0]);
}

// ─── Actions ──────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['reg_id'])) {
    $regId = (int) $_POST['reg_id'];
    $action = $_POST['action'];

    try {
        $reg = $db->fetch(
            "SELECT * FROM pharmacy_registrations WHERE id = ?",
            [$regId]
        );

        if (!$reg) {
            throw new Exception("Inscription introuvable.");
        }

        if ($action === 'approve' && $reg['status'] !== 'approved') {

            // Build unique username from email
            $baseUser = slugUsername($reg['email']);
            $username = $baseUser;
            $suffix = 1;
            while ($db->fetch("SELECT id FROM user WHERE username = ?", [$username])) {
                $username = $baseUser . $suffix++;
            }

            $tempPass = generateTempPassword();
            $userId   = generateUUID();

            $db->execute(
                "INSERT INTO user (id, username, password, role, email, statut)
                 VALUES (?, ?, ?, 'ADMIN', ?, 1)",
                [$userId, $username, password_hash($tempPass, PASSWORD_DEFAULT), $reg['email']]
            );

            // Ensure approved_at column exists before using it
            try {
                $db->execute("ALTER TABLE pharmacy_registrations ADD COLUMN approved_at DATETIME NULL AFTER status");
            } catch (Exception $e) { /* column already exists — fine */ }

            $db->execute(
                "UPDATE pharmacy_registrations SET status = 'approved', approved_at = NOW() WHERE id = ?",
                [$regId]
            );

            $loginUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/index.php';
            Mailer::accountActivation(
                $reg['email'],
                $reg['pharmacy_name'],
                $reg['responsible_name'],
                $username,
                $tempPass,
                $loginUrl
            );

            $flash = ['type' => 'success', 'msg' => "✅ Compte activé pour <strong>{$reg['pharmacy_name']}</strong>. Email envoyé à {$reg['email']}."];

        } elseif ($action === 'reject' && $reg['status'] !== 'rejected') {

            $db->execute(
                "UPDATE pharmacy_registrations SET status = 'rejected' WHERE id = ?",
                [$regId]
            );

            Mailer::registrationRejected($reg['email'], $reg['pharmacy_name'], $reg['responsible_name']);
            $flash = ['type' => 'warning', 'msg' => "Inscription de <strong>{$reg['pharmacy_name']}</strong> rejetée."];

        } elseif ($action === 'delete') {

            $db->execute("DELETE FROM pharmacy_registrations WHERE id = ?", [$regId]);
            $flash = ['type' => 'info', 'msg' => "Inscription supprimée."];
        }

    } catch (Exception $e) {
        $flash = ['type' => 'danger', 'msg' => "Erreur : " . htmlspecialchars($e->getMessage())];
    }
}

// ─── Data ─────────────────────────────────────────────────────────────────────

$statusFilter = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected']) ? $_GET['status'] : '';

$where  = $statusFilter ? "WHERE status = ?" : "";
$params = $statusFilter ? [$statusFilter] : [];

$regs = $db->fetchAll(
    "SELECT * FROM pharmacy_registrations {$where} ORDER BY created_at DESC",
    $params
);

// Stats
$stats = $db->fetch(
    "SELECT
        COUNT(*) AS total,
        SUM(status='pending')  AS pending,
        SUM(status='approved') AS approved,
        SUM(status='rejected') AS rejected
     FROM pharmacy_registrations"
);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inscriptions — digiPharm Admin</title>
<link rel="stylesheet" href="../assets/css/design-system.css">
<link rel="stylesheet" href="../assets/css/base.css">
<link rel="stylesheet" href="../assets/css/header.css">
<link rel="stylesheet" href="../assets/css/sidebar.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<style>
.stat-pill {
    display: flex; flex-direction: column; align-items: center;
    background: var(--ds-surface); border: 1px solid var(--ds-border-light);
    border-radius: var(--ds-radius-lg); padding: 20px 28px;
    min-width: 140px; flex: 1;
}
.stat-pill-val { font-size: 32px; font-weight: 700; line-height: 1; }
.stat-pill-lbl { font-size: 12px; color: var(--ds-text-400); margin-top: 4px; text-transform: uppercase; letter-spacing: .05em; font-weight: 500; }
.stat-pill.pending  .stat-pill-val { color: #b06000; }
.stat-pill.approved .stat-pill-val { color: var(--ds-green); }
.stat-pill.rejected .stat-pill-val { color: var(--ds-red); }
.stat-pill.total    .stat-pill-val { color: var(--ds-text-900); }

.plan-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 500; padding: 3px 10px; border-radius: 100px; }
.plan-badge.basic { background: var(--ds-surface-alt); color: var(--ds-text-600); border: 1px solid var(--ds-border); }
.plan-badge.pro   { background: var(--ds-green-bg); color: var(--ds-green-dark); }

.status-dot {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 12px; font-weight: 500; padding: 3px 10px; border-radius: 100px;
}
.status-dot::before { content: ''; width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.status-dot.pending  { background: var(--ds-amber-bg);  color: var(--ds-amber-text); }
.status-dot.pending::before  { background: #f59e0b; }
.status-dot.approved { background: var(--ds-green-bg);  color: var(--ds-green-dark); }
.status-dot.approved::before { background: var(--ds-green); }
.status-dot.rejected { background: var(--ds-red-bg);    color: var(--ds-red); }
.status-dot.rejected::before { background: var(--ds-red); }

.reg-actions { display: flex; gap: 6px; align-items: center; }
.reg-actions form { margin: 0; }

.filter-tabs { display: flex; gap: 0; border-bottom: 1px solid var(--ds-border-light); margin-bottom: 24px; }
.filter-tab {
    padding: 10px 18px; font-size: 13.5px; font-weight: 500;
    color: var(--ds-text-600); text-decoration: none;
    border-bottom: 2px solid transparent;
    display: flex; align-items: center; gap: 7px;
    transition: color .15s;
}
.filter-tab:hover { color: var(--ds-text-900); }
.filter-tab.active { color: var(--ds-green-dark); border-bottom-color: var(--ds-green); }
.filter-tab .count {
    font-size: 11px; font-weight: 600; padding: 1px 7px;
    border-radius: 100px; background: var(--ds-surface-alt); color: var(--ds-text-600);
}
.filter-tab.active .count { background: var(--ds-green-bg); color: var(--ds-green-dark); }

.flash {
    padding: 13px 18px; border-radius: var(--ds-radius-sm);
    font-size: 14px; margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px;
}
.flash.success { background: var(--ds-green-bg);  color: var(--ds-green-dark); border: 1px solid #b7dfc5; }
.flash.warning { background: var(--ds-amber-bg);  color: var(--ds-amber-text); border: 1px solid var(--ds-amber-border); }
.flash.danger  { background: var(--ds-red-bg);    color: var(--ds-red);        border: 1px solid #f5c6c6; }
.flash.info    { background: #e8f0fe;              color: #1a73e8;              border: 1px solid #c5d5f5; }
</style>
</head>
<body>
<div class="app-layout">
  <?php include 'sidebar.php'; ?>
  <div class="main-wrapper">
    <?php include 'header.php'; ?>
    <main class="main-content" style="padding:28px">

      <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
          <h1 style="font-size:20px;font-weight:700;margin:0">Inscriptions pharmacies</h1>
          <p style="font-size:13px;color:var(--ds-text-400);margin:4px 0 0">Gérez les demandes d'accès à digiPharm</p>
        </div>
        <a href="../landing.php" target="_blank" class="btn btn-outline-success btn-sm d-flex align-items-center gap-2">
          <i data-lucide="external-link" style="width:14px;height:14px"></i>
          Voir la landing page
        </a>
      </div>

      <?php if ($flash['msg']): ?>
      <div class="flash <?= $flash['type'] ?>">
        <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'alert-circle' : 'info') ?>" style="width:16px;height:16px;flex-shrink:0"></i>
        <?= $flash['msg'] ?>
      </div>
      <?php endif; ?>

      <!-- Stats -->
      <div class="d-flex gap-3 flex-wrap mb-4">
        <div class="stat-pill total">
          <span class="stat-pill-val"><?= (int)$stats['total'] ?></span>
          <span class="stat-pill-lbl">Total</span>
        </div>
        <div class="stat-pill pending">
          <span class="stat-pill-val"><?= (int)$stats['pending'] ?></span>
          <span class="stat-pill-lbl">En attente</span>
        </div>
        <div class="stat-pill approved">
          <span class="stat-pill-val"><?= (int)$stats['approved'] ?></span>
          <span class="stat-pill-lbl">Approuvées</span>
        </div>
        <div class="stat-pill rejected">
          <span class="stat-pill-val"><?= (int)$stats['rejected'] ?></span>
          <span class="stat-pill-lbl">Rejetées</span>
        </div>
      </div>

      <!-- Table card -->
      <div class="card">
        <div class="card-body" style="padding:0">

          <!-- Filter tabs -->
          <div class="px-4 pt-3">
            <div class="filter-tabs">
              <a href="registrations.php" class="filter-tab <?= !$statusFilter ? 'active' : '' ?>">
                Toutes <span class="count"><?= (int)$stats['total'] ?></span>
              </a>
              <a href="registrations.php?status=pending" class="filter-tab <?= $statusFilter === 'pending' ? 'active' : '' ?>">
                En attente <span class="count"><?= (int)$stats['pending'] ?></span>
              </a>
              <a href="registrations.php?status=approved" class="filter-tab <?= $statusFilter === 'approved' ? 'active' : '' ?>">
                Approuvées <span class="count"><?= (int)$stats['approved'] ?></span>
              </a>
              <a href="registrations.php?status=rejected" class="filter-tab <?= $statusFilter === 'rejected' ? 'active' : '' ?>">
                Rejetées <span class="count"><?= (int)$stats['rejected'] ?></span>
              </a>
            </div>
          </div>

          <!-- Table -->
          <?php if (empty($regs)): ?>
          <div style="padding:60px;text-align:center;color:var(--ds-text-400)">
            <i data-lucide="inbox" style="width:40px;height:40px;margin-bottom:12px;display:block;margin-left:auto;margin-right:auto"></i>
            <p style="font-size:15px;font-weight:500;margin-bottom:4px">Aucune inscription</p>
            <p style="font-size:13px">Les demandes soumises via la landing page apparaîtront ici.</p>
          </div>
          <?php else: ?>
          <div style="overflow-x:auto">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>Pharmacie</th>
                <th>Responsable</th>
                <th>Contact</th>
                <th>Forfait</th>
                <th>Statut</th>
                <th>Date</th>
                <th style="width:160px">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($regs as $r): ?>
              <tr>
                <td>
                  <div style="font-weight:500;color:var(--ds-text-900)"><?= htmlspecialchars($r['pharmacy_name']) ?></div>
                  <?php if ($r['city']): ?>
                  <div style="font-size:12px;color:var(--ds-text-400)">
                    <i data-lucide="map-pin" style="width:11px;height:11px"></i>
                    <?= htmlspecialchars($r['city']) ?>
                  </div>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($r['responsible_name']) ?></td>
                <td>
                  <div style="font-size:13px"><?= htmlspecialchars($r['email']) ?></div>
                  <?php if ($r['phone']): ?>
                  <div style="font-size:12px;color:var(--ds-text-400)"><?= htmlspecialchars($r['phone']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="plan-badge <?= $r['plan'] ?>">
                    <?php if ($r['plan'] === 'pro'): ?>
                    <i data-lucide="sparkles" style="width:11px;height:11px"></i> Pro + IA
                    <?php else: ?>
                    Basique
                    <?php endif; ?>
                  </span>
                </td>
                <td><span class="status-dot <?= htmlspecialchars($r['status']) ?>"><?php
                  echo match($r['status']) {
                    'pending'  => 'En attente',
                    'approved' => 'Approuvée',
                    'rejected' => 'Rejetée',
                    default    => $r['status'],
                  };
                ?></span></td>
                <td style="font-size:12px;color:var(--ds-text-400);white-space:nowrap">
                  <?= date('d/m/Y', strtotime($r['created_at'])) ?><br>
                  <?= date('H:i', strtotime($r['created_at'])) ?>
                </td>
                <td>
                  <div class="reg-actions">
                    <?php if ($r['status'] === 'pending'): ?>
                    <form method="POST">
                      <input type="hidden" name="reg_id" value="<?= $r['id'] ?>">
                      <input type="hidden" name="action" value="approve">
                      <button class="btn btn-success btn-sm" title="Approuver & activer le compte"
                        onclick="return confirm('Approuver et créer le compte pour <?= htmlspecialchars(addslashes($r['pharmacy_name'])) ?> ?')">
                        <i data-lucide="check" style="width:13px;height:13px"></i> Approuver
                      </button>
                    </form>
                    <form method="POST">
                      <input type="hidden" name="reg_id" value="<?= $r['id'] ?>">
                      <input type="hidden" name="action" value="reject">
                      <button class="btn btn-outline-danger btn-sm" title="Rejeter"
                        onclick="return confirm('Rejeter la demande de <?= htmlspecialchars(addslashes($r['pharmacy_name'])) ?> ?')">
                        <i data-lucide="x" style="width:13px;height:13px"></i>
                      </button>
                    </form>
                    <?php elseif ($r['status'] === 'approved'): ?>
                    <span style="font-size:12px;color:var(--ds-green);font-weight:500">
                      <i data-lucide="check-circle" style="width:13px;height:13px"></i> Activé
                    </span>
                    <?php else: ?>
                    <form method="POST">
                      <input type="hidden" name="reg_id" value="<?= $r['id'] ?>">
                      <input type="hidden" name="action" value="approve">
                      <button class="btn btn-outline-success btn-sm btn-sm"
                        onclick="return confirm('Ré-approuver <?= htmlspecialchars(addslashes($r['pharmacy_name'])) ?> ?')">
                        <i data-lucide="rotate-ccw" style="width:13px;height:13px"></i> Ré-activer
                      </button>
                    </form>
                    <?php endif; ?>
                    <form method="POST">
                      <input type="hidden" name="reg_id" value="<?= $r['id'] ?>">
                      <input type="hidden" name="action" value="delete">
                      <button class="btn btn-outline-secondary btn-sm" title="Supprimer"
                        onclick="return confirm('Supprimer définitivement cette inscription ?')">
                        <i data-lucide="trash-2" style="width:13px;height:13px"></i>
                      </button>
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
      </div>

    </main>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>lucide.createIcons();</script>
<?php include '../assets/pharmasys.js' ? '' : ''; ?>
<script src="../assets/js/pharmasys.js"></script>
</body>
</html>
