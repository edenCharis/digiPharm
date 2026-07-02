<?php
require_once dirname(__DIR__) . '/config/auth.php';
sa_check_auth();

$db     = sa_db();
$errors = [];
$ok     = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $city       = trim($_POST['city'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $plan       = $_POST['plan'] ?? 'starter';
    $status     = $_POST['status'] ?? 'trial';
    $trial_days = (int)($_POST['trial_days'] ?? 30);

    // Compte admin
    $admin_name  = trim($_POST['admin_name'] ?? '');
    $admin_user  = trim($_POST['admin_username'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');
    $admin_pass  = trim($_POST['admin_password'] ?? '');

    if (!$name)       $errors[] = "Le nom de la pharmacie est requis.";
    if (!$city)       $errors[] = "La ville est requise.";
    if (!$admin_user) $errors[] = "Le nom d'utilisateur admin est requis.";
    if (!$admin_pass) $errors[] = "Le mot de passe admin est requis.";

    if (empty($errors)) {
        $trial_ends = $status === 'trial'
            ? date('Y-m-d', strtotime("+{$trial_days} days"))
            : null;

        // Créer la pharmacie
        $stmt = $db->prepare("
            INSERT INTO pharmacies (name, address, city, phone, email, plan, status, trial_ends_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $address, $city, $phone, $email, $plan, $status, $trial_ends]);
        $pharmacy_id = $db->lastInsertId();

        // Créer le compte admin (schema: UUID id, no name column, role uppercase, statut=1)
        $hash    = password_hash($admin_pass, PASSWORD_BCRYPT);
        $uuid    = sprintf('%s%s-%s-%s-%s-%s%s%s', ...str_split(bin2hex(random_bytes(16)), 4));
        $db->prepare("
            INSERT INTO user (id, username, email, password, role, statut, pharmacy_id)
            VALUES (?, ?, ?, ?, 'ADMIN', 1, ?)
        ")->execute([$uuid, $admin_user, $admin_email, $hash, $pharmacy_id]);

        header("Location: list.php?msg=created");
        exit;
    }
}

require_once dirname(__DIR__) . '/config/layout_header.php';
?>

<div style="max-width:720px;">

    <?php foreach ($errors as $e): ?>
        <div class="alert alert-error">⚠️ <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div class="table-card">
        <div class="table-header">
            <div class="table-title">Informations de la pharmacie</div>
        </div>
        <div style="padding:1.5rem;">
            <form method="POST">

                <div class="form-grid">
                    <div class="form-group full">
                        <label>Nom de la pharmacie *</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                               placeholder="ex: Pharmacie Centrale de Brazzaville" required>
                    </div>
                    <div class="form-group">
                        <label>Ville *</label>
                        <select name="city">
                            <option value="Brazzaville"  <?= ($_POST['city']??'')==='Brazzaville'  ?'selected':'' ?>>Brazzaville</option>
                            <option value="Pointe-Noire" <?= ($_POST['city']??'')==='Pointe-Noire' ?'selected':'' ?>>Pointe-Noire</option>
                            <option value="Autre">Autre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Adresse</label>
                        <input type="text" name="address" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                               placeholder="Avenue, quartier...">
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                               placeholder="+242 06 ...">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               placeholder="pharmacie@example.com">
                    </div>
                    <div class="form-group">
                        <label>Plan SaaS</label>
                        <select name="plan">
                            <option value="starter"    <?= ($_POST['plan']??'starter')==='starter'    ?'selected':'' ?>>Starter — 15 $/mois</option>
                            <option value="pro"        <?= ($_POST['plan']??'')==='pro'        ?'selected':'' ?>>Pro — 25 $/mois</option>
                            <option value="enterprise" <?= ($_POST['plan']??'')==='enterprise' ?'selected':'' ?>>Enterprise — 40 $/mois</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Statut initial</label>
                        <select name="status" id="statusSelect">
                            <option value="trial"     <?= ($_POST['status']??'trial')==='trial'     ?'selected':'' ?>>Trial (période d'essai)</option>
                            <option value="active"    <?= ($_POST['status']??'')==='active'    ?'selected':'' ?>>Active</option>
                            <option value="suspended" <?= ($_POST['status']??'')==='suspended' ?'selected':'' ?>>Suspendue</option>
                        </select>
                    </div>
                    <div class="form-group" id="trialDaysGroup">
                        <label>Durée d'essai (jours)</label>
                        <input type="number" name="trial_days" value="<?= $_POST['trial_days'] ?? 30 ?>" min="7" max="90">
                    </div>
                </div>

                <hr style="border:none;border-top:1px solid #E5E7EB;margin:1.5rem 0;">

                <div style="font-size:0.9rem;font-weight:700;color:#1A1A2E;margin-bottom:1rem;">
                    Compte administrateur de la pharmacie
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Nom complet</label>
                        <input type="text" name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>"
                               placeholder="Jean Dupont">
                    </div>
                    <div class="form-group">
                        <label>Nom d'utilisateur *</label>
                        <input type="text" name="admin_username" value="<?= htmlspecialchars($_POST['admin_username'] ?? '') ?>"
                               placeholder="admin_pharmacie" required>
                    </div>
                    <div class="form-group">
                        <label>Email admin</label>
                        <input type="email" name="admin_email" value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>"
                               placeholder="admin@pharmacie.com">
                    </div>
                    <div class="form-group">
                        <label>Mot de passe *</label>
                        <input type="text" name="admin_password" value="<?= htmlspecialchars($_POST['admin_password'] ?? '') ?>"
                               placeholder="Mot de passe initial" required>
                        <small style="color:#6B7280;font-size:0.75rem;">Le pharmacien pourra le changer après connexion</small>
                    </div>
                </div>

                <div style="display:flex;gap:0.75rem;margin-top:1rem;">
                    <button type="submit" class="btn-sm btn-primary" style="padding:0.7rem 1.5rem;font-size:0.9rem;">
                        ✅ Créer la pharmacie
                    </button>
                    <a href="list.php" class="btn-sm btn-outline" style="padding:0.7rem 1.5rem;font-size:0.9rem;">Annuler</a>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
const statusSelect    = document.getElementById('statusSelect');
const trialDaysGroup  = document.getElementById('trialDaysGroup');
statusSelect.addEventListener('change', () => {
    trialDaysGroup.style.display = statusSelect.value === 'trial' ? 'block' : 'none';
});
trialDaysGroup.style.display = statusSelect.value === 'trial' ? 'block' : 'none';
</script>

<?php require_once dirname(__DIR__) . '/config/layout_footer.php'; ?>
