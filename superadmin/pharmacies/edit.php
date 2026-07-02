<?php
require_once dirname(__DIR__) . '/config/auth.php';
sa_check_auth();

$db = sa_db();
$id = (int)($_GET['id'] ?? 0);

if (!$id) { header('Location: list.php'); exit; }

$pharmacy = $db->query("SELECT * FROM pharmacies WHERE id = $id")->fetch();
if (!$pharmacy) { header('Location: list.php'); exit; }

$errors = [];
$ok     = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $responsible   = trim($_POST['responsible_name'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $city          = trim($_POST['city'] ?? '');
    $address       = trim($_POST['address'] ?? '');
    $plan          = in_array($_POST['plan'] ?? '', ['basic','pro','enterprise']) ? $_POST['plan'] : 'basic';
    $status        = in_array($_POST['status'] ?? '', ['active','trial','suspended']) ? $_POST['status'] : $pharmacy['status'];
    $trial_ends_at = $_POST['trial_ends_at'] ? $_POST['trial_ends_at'] . ':00' : null;

    if (!$name) $errors[] = "Le nom est requis.";

    if (empty($errors)) {
        $db->prepare("
            UPDATE pharmacies SET
                name             = ?,
                responsible_name = ?,
                email            = ?,
                phone            = ?,
                city             = ?,
                address          = ?,
                plan             = ?,
                status           = ?,
                trial_ends_at    = ?
            WHERE id = ?
        ")->execute([$name, $responsible, $email, $phone, $city, $address, $plan, $status, $trial_ends_at, $id]);

        header("Location: view.php?id=$id&msg=updated");
        exit;
    }

    // Repopulate from POST on error
    $pharmacy = array_merge($pharmacy, $_POST);
}

$trial_ends_fmt = $pharmacy['trial_ends_at'] ? date('Y-m-d\TH:i', strtotime($pharmacy['trial_ends_at'])) : '';

require_once dirname(__DIR__) . '/config/layout_header.php';
?>

<div style="max-width:640px;">

    <div style="margin-bottom:1rem;">
        <a href="view.php?id=<?= $id ?>" class="btn-sm btn-outline">← Retour</a>
    </div>

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Modifier — <?= htmlspecialchars($pharmacy['name']) ?></div>
        </div>
        <div style="padding:1.5rem;">
            <form method="POST">

                <div class="form-grid">
                    <div class="form-group full">
                        <label>Nom de la pharmacie *</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($pharmacy['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Responsable</label>
                        <input type="text" name="responsible_name" value="<?= htmlspecialchars($pharmacy['responsible_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($pharmacy['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($pharmacy['phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Ville</label>
                        <input type="text" name="city" value="<?= htmlspecialchars($pharmacy['city'] ?? '') ?>">
                    </div>
                    <div class="form-group full">
                        <label>Adresse</label>
                        <input type="text" name="address" value="<?= htmlspecialchars($pharmacy['address'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label>Plan</label>
                        <select name="plan">
                            <option value="basic"      <?= ($pharmacy['plan'] ?? '') === 'basic'      ? 'selected' : '' ?>>Basic — 10 $/mois</option>
                            <option value="pro"        <?= ($pharmacy['plan'] ?? '') === 'pro'        ? 'selected' : '' ?>>Pro + IA — 25 $/mois</option>
                            <option value="enterprise" <?= ($pharmacy['plan'] ?? '') === 'enterprise' ? 'selected' : '' ?>>Enterprise</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Statut</label>
                        <select name="status">
                            <option value="trial"     <?= ($pharmacy['status'] ?? '') === 'trial'     ? 'selected' : '' ?>>Trial (essai)</option>
                            <option value="active"    <?= ($pharmacy['status'] ?? '') === 'active'    ? 'selected' : '' ?>>Active</option>
                            <option value="suspended" <?= ($pharmacy['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspendue</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label>Fin de période d'essai</label>
                        <input type="datetime-local" name="trial_ends_at" value="<?= $trial_ends_fmt ?>">
                        <small style="color:#6B7280;font-size:0.75rem;">Laisser vide pour pas de limite.</small>
                    </div>
                </div>

                <div style="display:flex;gap:0.75rem;margin-top:0.5rem;">
                    <button type="submit" class="btn-sm btn-primary" style="padding:0.7rem 1.5rem;">
                        ✅ Enregistrer
                    </button>
                    <a href="view.php?id=<?= $id ?>" class="btn-sm btn-outline" style="padding:0.7rem 1.5rem;">Annuler</a>
                </div>

            </form>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/config/layout_footer.php'; ?>
