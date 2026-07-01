<?php
require_once 'config/database.php';

// Create registrations table if it doesn't exist
try {
    $db->execute("CREATE TABLE IF NOT EXISTS pharmacy_registrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        plan VARCHAR(20) NOT NULL DEFAULT 'basic',
        pharmacy_name VARCHAR(255) NOT NULL,
        responsible_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        city VARCHAR(100) DEFAULT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {}

$plan    = $_GET['plan'] ?? 'basic';
$errors  = [];
$success = false;
$formData = [
    'plan'             => $plan,
    'pharmacy_name'    => '',
    'responsible_name' => '',
    'email'            => '',
    'phone'            => '',
    'city'             => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'plan'             => in_array($_POST['plan'] ?? '', ['basic', 'pro']) ? $_POST['plan'] : 'basic',
        'pharmacy_name'    => trim($_POST['pharmacy_name'] ?? ''),
        'responsible_name' => trim($_POST['responsible_name'] ?? ''),
        'email'            => trim($_POST['email'] ?? ''),
        'phone'            => trim($_POST['phone'] ?? ''),
        'city'             => trim($_POST['city'] ?? ''),
    ];

    if (!$formData['pharmacy_name'])                             $errors[] = 'Le nom de la pharmacie est requis.';
    if (!$formData['responsible_name'])                          $errors[] = 'Le nom du responsable est requis.';
    if (!$formData['email'] || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL))
                                                                 $errors[] = 'Adresse email invalide.';

    if (empty($errors)) {
        try {
            $existing = $db->fetch("SELECT id FROM pharmacy_registrations WHERE email = ?", [$formData['email']]);
            if ($existing) {
                $errors[] = 'Cette adresse email est déjà enregistrée. <a href="index.php">Connectez-vous</a> ou utilisez une autre adresse.';
            } else {
                $db->execute(
                    "INSERT INTO pharmacy_registrations (plan, pharmacy_name, responsible_name, email, phone, city) VALUES (?, ?, ?, ?, ?, ?)",
                    [$formData['plan'], $formData['pharmacy_name'], $formData['responsible_name'], $formData['email'], $formData['phone'], $formData['city']]
                );
                $success = true;
            }
        } catch (Exception $e) {
            $errors[] = 'Une erreur est survenue. Veuillez réessayer.';
        }
    }
}
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Créer votre compte — digiPharm</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --green:#188038;--green-dark:#0d652d;--green-bg:#e6f4ea;--green-light:#f0faf3;
  --border:#dadce0;--border-focus:#188038;
  --text-900:#202124;--text-600:#5f6368;--text-400:#80868b;
  --surface:#ffffff;--surface-alt:#f8f9fa;
  --red:#d93025;--red-bg:#fce8e6;
  --radius:10px;
  --shadow:0 1px 3px rgba(0,0,0,.10);
  --shadow-md:0 4px 20px rgba(0,0,0,.10);
}
html,body{height:100%}
body{
  font-family:'Roboto',system-ui,sans-serif;
  color:var(--text-900);
  background:var(--surface-alt);
  -webkit-font-smoothing:antialiased;
  min-height:100vh;
  display:flex;flex-direction:column;
}

/* ─── TOP BAR ─── */
.topbar{
  background:var(--surface);border-bottom:1px solid var(--border);
  padding:0 40px;height:60px;display:flex;align-items:center;flex-shrink:0;
}
.topbar-inner{max-width:1100px;margin:0 auto;width:100%;display:flex;align-items:center;justify-content:space-between}
.back-link{
  display:flex;align-items:center;gap:7px;
  color:var(--text-600);text-decoration:none;font-size:14px;font-weight:500;
  transition:color .15s;
}
.back-link:hover{color:var(--green)}
.back-link svg{width:16px;height:16px}
.topbar-logo{display:flex;align-items:center;gap:9px;text-decoration:none;color:var(--text-900)}
.topbar-logo-icon{width:32px;height:32px;background:var(--green);border-radius:8px;display:flex;align-items:center;justify-content:center}
.topbar-logo-name{font-size:16px;font-weight:700;letter-spacing:-.3px}
.topbar-logo-name span{color:var(--green)}
.login-link{font-size:14px;color:var(--text-400);text-decoration:none}
.login-link:hover{color:var(--green)}
.login-link strong{color:var(--text-900)}

/* ─── PAGE LAYOUT ─── */
.page{flex:1;padding:40px 20px 60px;display:flex;flex-direction:column;align-items:center}
.page-head{text-align:center;max-width:520px;margin-bottom:36px}
.trial-pill{
  display:inline-flex;align-items:center;gap:6px;
  background:var(--green-bg);color:var(--green);
  padding:5px 14px;border-radius:100px;font-size:13px;font-weight:500;
  margin-bottom:16px;
}
.trial-pill svg{width:13px;height:13px}
.page-title{font-size:28px;font-weight:700;letter-spacing:-.5px;margin-bottom:8px}
.page-sub{font-size:15px;color:var(--text-600)}

/* ─── PLAN PICKER ─── */
.plan-picker{
  display:grid;grid-template-columns:1fr 1fr;
  gap:14px;width:100%;max-width:700px;margin-bottom:28px;
}
.plan-opt{position:relative;cursor:pointer}
.plan-opt input[type=radio]{position:absolute;opacity:0;pointer-events:none}
.plan-opt-card{
  border:2px solid var(--border);border-radius:14px;padding:20px 22px;
  display:flex;align-items:flex-start;gap:14px;
  transition:border-color .2s,box-shadow .2s;
  background:var(--surface);
}
.plan-opt input:checked + .plan-opt-card{
  border-color:var(--green);
  box-shadow:0 0 0 1px var(--green),0 2px 12px rgba(24,128,56,.12);
}
.plan-opt-radio{
  width:18px;height:18px;border-radius:50%;
  border:2px solid var(--border);margin-top:2px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  transition:border-color .2s;
}
.plan-opt input:checked + .plan-opt-card .plan-opt-radio{
  border-color:var(--green);
  background:var(--green);
}
.plan-opt input:checked + .plan-opt-card .plan-opt-radio::after{
  content:'';width:6px;height:6px;border-radius:50%;background:#fff;
}
.plan-opt-info{flex:1}
.plan-opt-name{font-size:15px;font-weight:600;margin-bottom:3px;display:flex;align-items:center;gap:8px}
.plan-opt-name .badge{
  font-size:11px;font-weight:600;padding:2px 8px;
  border-radius:100px;background:var(--green);color:#fff;
}
.plan-opt-price{font-size:20px;font-weight:700;color:var(--text-900);letter-spacing:-.5px;margin-bottom:6px}
.plan-opt-price span{font-size:13px;font-weight:400;color:var(--text-400)}
.plan-opt-feats{font-size:12px;color:var(--text-400);line-height:1.55}

/* ─── FORM CARD ─── */
.form-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:16px;padding:36px 40px;
  width:100%;max-width:700px;
  box-shadow:var(--shadow);
}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group.full{grid-column:1/-1}
.form-label{font-size:13px;font-weight:500;color:var(--text-600)}
.form-label .req{color:var(--red)}
.form-input{
  padding:11px 14px;border:1.5px solid var(--border);border-radius:9px;
  font-size:14px;font-family:inherit;color:var(--text-900);
  background:var(--surface);outline:none;width:100%;
  transition:border-color .15s,box-shadow .15s;
}
.form-input:focus{border-color:var(--border-focus);box-shadow:0 0 0 3px rgba(24,128,56,.12)}
.form-input::placeholder{color:var(--text-400)}
.form-hint{font-size:12px;color:var(--text-400)}

.form-divider{height:1px;background:var(--border);margin:24px 0;grid-column:1/-1}

/* ─── TRIAL INFO ─── */
.trial-info{
  background:var(--green-light);border:1px solid rgba(24,128,56,.15);
  border-radius:10px;padding:14px 16px;
  display:flex;align-items:flex-start;gap:10px;
  margin-bottom:22px;font-size:13px;color:var(--text-600);
  grid-column:1/-1;line-height:1.55;
}
.trial-info svg{width:16px;height:16px;color:var(--green);flex-shrink:0;margin-top:1px}

/* ─── ERRORS ─── */
.error-box{
  background:var(--red-bg);border:1px solid rgba(217,48,37,.2);
  border-radius:10px;padding:14px 16px;margin-bottom:20px;
  display:flex;flex-direction:column;gap:5px;
}
.error-box li{font-size:13.5px;color:var(--red);list-style:none;display:flex;align-items:flex-start;gap:8px}
.error-box li svg{width:15px;height:15px;flex-shrink:0;margin-top:2px}
.error-box li a{color:var(--red);font-weight:500}

/* ─── SUBMIT BTN ─── */
.btn-submit{
  width:100%;padding:14px;background:var(--green);color:#fff;
  border:none;border-radius:10px;font-size:15px;font-weight:500;
  font-family:inherit;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:9px;
  box-shadow:0 2px 8px rgba(24,128,56,.28);
  transition:all .2s;margin-top:6px;
}
.btn-submit:hover{background:var(--green-dark);box-shadow:0 4px 16px rgba(24,128,56,.35)}
.btn-submit svg{width:17px;height:17px}

.form-footer{text-align:center;margin-top:16px;font-size:13px;color:var(--text-400)}
.form-footer a{color:var(--green);text-decoration:none;font-weight:500}

/* ─── SUCCESS ─── */
.success-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:16px;padding:56px 40px;
  width:100%;max-width:540px;text-align:center;
  box-shadow:var(--shadow);
}
.success-icon{
  width:72px;height:72px;border-radius:50%;background:var(--green-bg);
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 24px;
}
.success-icon svg{width:34px;height:34px;color:var(--green)}
.success-title{font-size:24px;font-weight:700;margin-bottom:12px;letter-spacing:-.3px}
.success-sub{font-size:15px;color:var(--text-600);line-height:1.7;margin-bottom:32px}
.success-detail{
  background:var(--surface-alt);border-radius:10px;padding:18px;
  font-size:14px;color:var(--text-600);margin-bottom:28px;
  border:1px solid var(--border);text-align:left;
}
.success-detail .row{display:flex;justify-content:space-between;align-items:center;padding:4px 0}
.success-detail .row:not(:last-child){border-bottom:1px solid var(--border-light)}
.success-detail .lbl{color:var(--text-400);font-size:13px}
.success-detail .val{font-weight:500;color:var(--text-900)}
.btn-login{
  display:inline-flex;align-items:center;gap:8px;
  padding:13px 28px;background:var(--green);color:#fff;
  border-radius:10px;text-decoration:none;font-size:15px;font-weight:500;
  box-shadow:0 2px 8px rgba(24,128,56,.28);
  transition:all .2s;
}
.btn-login:hover{background:var(--green-dark)}
.btn-back-landing{
  display:inline-flex;align-items:center;gap:6px;
  margin-top:16px;color:var(--text-400);text-decoration:none;font-size:14px;
}
.btn-back-landing:hover{color:var(--green)}

@media(max-width:640px){
  .plan-picker{grid-template-columns:1fr}
  .form-card{padding:24px 20px}
  .form-grid{grid-template-columns:1fr}
  .topbar{padding:0 20px}
}
</style>
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
  <div class="topbar-inner">
    <a href="landing.php" class="back-link">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
      Retour
    </a>
    <a href="landing.php" class="topbar-logo">
      <div class="topbar-logo-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
      </div>
      <span class="topbar-logo-name">digi<span>Pharm</span></span>
    </a>
    <a href="index.php" class="login-link">Déjà client ? <strong>Connexion</strong></a>
  </div>
</div>

<div class="page">

<?php if ($success):
  $planLabel = $formData['plan'] === 'pro' ? 'Pro + IA — $25/mois' : 'Basique — $10/mois';
?>
  <!-- ─── SUCCESS STATE ─── -->
  <div class="success-card">
    <div class="success-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    </div>
    <h1 class="success-title">Demande reçue !</h1>
    <p class="success-sub">
      Votre demande d'inscription a bien été enregistrée. Notre équipe va activer votre compte dans les prochaines heures et vous envoyer vos identifiants de connexion.
    </p>
    <div class="success-detail">
      <div class="row">
        <span class="lbl">Pharmacie</span>
        <span class="val"><?= htmlspecialchars($formData['pharmacy_name']) ?></span>
      </div>
      <div class="row">
        <span class="lbl">Responsable</span>
        <span class="val"><?= htmlspecialchars($formData['responsible_name']) ?></span>
      </div>
      <div class="row">
        <span class="lbl">Email</span>
        <span class="val"><?= htmlspecialchars($formData['email']) ?></span>
      </div>
      <div class="row">
        <span class="lbl">Forfait</span>
        <span class="val"><?= $planLabel ?></span>
      </div>
    </div>
    <a href="index.php" class="btn-login">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
      Aller à la connexion
    </a>
    <br>
    <a href="landing.php" class="btn-back-landing">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
      Retour à l'accueil
    </a>
  </div>

<?php else: ?>
  <!-- ─── FORM STATE ─── -->
  <div class="page-head">
    <div class="trial-pill">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      14 jours gratuits — aucune carte bancaire
    </div>
    <h1 class="page-title">Créer votre compte pharmacie</h1>
    <p class="page-sub">Choisissez votre forfait et remplissez le formulaire. C'est rapide.</p>
  </div>

  <form method="POST" action="" novalidate style="width:100%;max-width:700px">

    <!-- PLAN PICKER -->
    <div class="plan-picker">
      <label class="plan-opt">
        <input type="radio" name="plan" value="basic" <?= $formData['plan'] === 'basic' ? 'checked' : '' ?>>
        <div class="plan-opt-card">
          <div class="plan-opt-radio"></div>
          <div class="plan-opt-info">
            <div class="plan-opt-name">Basique</div>
            <div class="plan-opt-price">$10 <span>HT / mois</span></div>
            <div class="plan-opt-feats">Caisse · Stock · Rapports<br>Jusqu'à 3 utilisateurs</div>
          </div>
        </div>
      </label>
      <label class="plan-opt">
        <input type="radio" name="plan" value="pro" <?= $formData['plan'] === 'pro' ? 'checked' : '' ?>>
        <div class="plan-opt-card">
          <div class="plan-opt-radio"></div>
          <div class="plan-opt-info">
            <div class="plan-opt-name">Pro + IA <span class="badge">Recommandé</span></div>
            <div class="plan-opt-price">$25 <span>HT / mois</span></div>
            <div class="plan-opt-feats">Tout Basique + IA + SFEC<br>Jusqu'à 15 utilisateurs</div>
          </div>
        </div>
      </label>
    </div>

    <!-- FORM CARD -->
    <div class="form-card">

      <?php if (!empty($errors)): ?>
      <div class="error-box" role="alert">
        <?php foreach ($errors as $e): ?>
        <li>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?= $e ?>
        </li>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="form-grid">

        <div class="trial-info">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          Après validation de votre demande, notre équipe vous contactera pour activer votre accès. Votre période d'essai de 14 jours commencera à l'activation.
        </div>

        <div class="form-group full">
          <label class="form-label">Nom de la pharmacie <span class="req">*</span></label>
          <input type="text" name="pharmacy_name" class="form-input"
            placeholder="ex : Pharmacie Centrale de Brazzaville"
            value="<?= htmlspecialchars($formData['pharmacy_name']) ?>"
            autocomplete="organization" required>
        </div>

        <div class="form-group full">
          <label class="form-label">Nom du responsable / gérant <span class="req">*</span></label>
          <input type="text" name="responsible_name" class="form-input"
            placeholder="Prénom et nom"
            value="<?= htmlspecialchars($formData['responsible_name']) ?>"
            autocomplete="name" required>
        </div>

        <div class="form-group">
          <label class="form-label">Email professionnel <span class="req">*</span></label>
          <input type="email" name="email" class="form-input"
            placeholder="contact@mapharmacje.cg"
            value="<?= htmlspecialchars($formData['email']) ?>"
            autocomplete="email" required>
        </div>

        <div class="form-group">
          <label class="form-label">Téléphone</label>
          <input type="tel" name="phone" class="form-input"
            placeholder="+242 06 XXX XX XX"
            value="<?= htmlspecialchars($formData['phone']) ?>"
            autocomplete="tel">
        </div>

        <div class="form-group full">
          <label class="form-label">Ville / Localisation</label>
          <input type="text" name="city" class="form-input"
            placeholder="ex : Brazzaville, Pointe-Noire, Dolisie..."
            value="<?= htmlspecialchars($formData['city']) ?>">
        </div>

        <div class="form-divider"></div>

      </div>

      <button type="submit" class="btn-submit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
        Démarrer mon essai gratuit de 14 jours
      </button>

      <p class="form-footer">
        En envoyant ce formulaire vous acceptez nos
        <a href="#">conditions d'utilisation</a>.
        Déjà client ? <a href="index.php">Se connecter</a>
      </p>
    </div>
  </form>

<?php endif; ?>
</div>

<script>
lucide.createIcons();

// Highlight selected plan card on radio change
document.querySelectorAll('.plan-opt input[type=radio]').forEach(r => {
  r.addEventListener('change', () => {
    document.querySelectorAll('.plan-opt input[type=radio]').forEach(other => {
      other.closest('.plan-opt').querySelector('.plan-opt-card').style.transition = 'all .2s';
    });
  });
});
</script>
</body>
</html>
