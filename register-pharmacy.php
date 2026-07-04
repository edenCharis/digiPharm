<?php
require_once 'config/database.php';
require_once 'includes/Mailer.php';

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

$plan     = $_GET['plan'] ?? 'basic';
$errors   = [];
$success  = false;
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

    if (!$formData['pharmacy_name'])
        $errors[] = 'Le nom de la pharmacie est requis.';
    if (!$formData['responsible_name'])
        $errors[] = 'Le nom du responsable est requis.';
    if (!$formData['email'] || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL))
        $errors[] = 'Adresse email invalide.';

    if (empty($errors)) {
        try {
            $existing = $db->fetch("SELECT id FROM pharmacy_registrations WHERE email = ?", [$formData['email']]);
            if ($existing) {
                $errors[] = 'Cette adresse email est déjà enregistrée. <a href="login">Connectez-vous</a> ou utilisez une autre adresse.';
            } else {
                $db->execute(
                    "INSERT INTO pharmacy_registrations (plan, pharmacy_name, responsible_name, email, phone, city) VALUES (?, ?, ?, ?, ?, ?)",
                    [$formData['plan'], $formData['pharmacy_name'], $formData['responsible_name'], $formData['email'], $formData['phone'], $formData['city']]
                );
                Mailer::registrationConfirmation(
                    $formData['email'],
                    $formData['pharmacy_name'],
                    $formData['responsible_name'],
                    $formData['plan']
                );
                $success = true;
            }
        } catch (Exception $e) {
            $errors[] = 'Une erreur est survenue. Veuillez réessayer.';
        }
    }
}
$isPro = ($formData['plan'] === 'pro');
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Créer votre compte — digiPharm</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --green:      #1ea84b;
  --green-dark: #0d6e2f;
  --green-glow: rgba(30,168,75,.18);
  --green-bg:   rgba(30,168,75,.1);
  --green-lit:  #5edd85;
  --ink:        #080d09;
  --ink-2:      #111a12;
  --border-d:   rgba(255,255,255,.08);
  --dim:        rgba(255,255,255,.5);
  --mute:       rgba(255,255,255,.28);
  --surface:    #ffffff;
  --gray-50:    #f6f8f7;
  --gray-100:   #edf0ed;
  --gray-400:   #8a9a8d;
  --gray-600:   #4a5e4d;
  --border-l:   #dadce0;
  --red:        #d93025;
  --red-bg:     #fce8e6;
  --font:       'Plus Jakarta Sans', system-ui, sans-serif;
  --font-body:  'Inter', system-ui, sans-serif;
}
html, body { height: 100%; }
body { font-family: var(--font-body); -webkit-font-smoothing: antialiased; background: var(--ink); }

/* ══ LAYOUT ══════════════════════════════════════════════════════════ */
.shell {
  display: flex; min-height: 100vh;
}

/* ══ LEFT PANEL — dark ══════════════════════════════════════════════ */
.left {
  width: 50%; flex-shrink: 0;
  background: var(--ink);
  display: flex; flex-direction: column;
  padding: 48px 56px;
  position: relative; overflow: hidden;
  border-right: 1px solid var(--border-d);
}

/* mesh blobs */
.left::before {
  content: ''; position: absolute;
  width: 500px; height: 500px; border-radius: 50%; pointer-events: none;
  background: radial-gradient(circle, rgba(30,168,75,.12) 0%, transparent 65%);
  top: -150px; left: -150px;
}
.left::after {
  content: ''; position: absolute;
  width: 300px; height: 300px; border-radius: 50%; pointer-events: none;
  background: radial-gradient(circle, rgba(30,168,75,.08) 0%, transparent 65%);
  bottom: 50px; right: -80px;
}

.left-content { position: relative; z-index: 2; display: flex; flex-direction: column; min-height: 100%; flex: 1; }

/* logo */
.logo { display: flex; align-items: center; gap: 10px; text-decoration: none; margin-bottom: 56px; }
.logo-mark {
  width: 36px; height: 36px; background: var(--green); border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font); font-size: 18px; font-weight: 900; color: #fff;
}
.logo-name { font-family: var(--font); font-size: 18px; font-weight: 700; color: #fff; letter-spacing: -.3px; }

/* left headline */
.left h1 {
  font-family: var(--font); font-size: 28px; font-weight: 800;
  color: #fff; letter-spacing: -1px; line-height: 1.2; margin-bottom: 12px;
}
.left h1 em { font-style: normal; color: var(--green-lit); }
.left-sub { font-size: 14px; color: var(--dim); line-height: 1.7; margin-bottom: 40px; }

/* plan selector */
.plan-label { font-size: 10.5px; font-weight: 700; color: var(--mute); text-transform: uppercase; letter-spacing: .08em; margin-bottom: 12px; }

.plan-opt { display: block; cursor: pointer; margin-bottom: 10px; }
.plan-opt input { position: absolute; opacity: 0; pointer-events: none; }
.plan-card {
  border: 1.5px solid var(--border-d); border-radius: 12px; padding: 16px 18px;
  display: flex; align-items: center; gap: 14px;
  transition: all .2s; background: rgba(255,255,255,.03);
}
.plan-opt input:checked + .plan-card {
  border-color: var(--green); background: rgba(30,168,75,.08);
  box-shadow: 0 0 0 1px var(--green), 0 4px 20px rgba(30,168,75,.15);
}
.plan-radio {
  width: 18px; height: 18px; border-radius: 50%; border: 2px solid rgba(255,255,255,.2);
  flex-shrink: 0; display: flex; align-items: center; justify-content: center;
  transition: all .2s;
}
.plan-opt input:checked + .plan-card .plan-radio {
  border-color: var(--green); background: var(--green);
}
.plan-opt input:checked + .plan-card .plan-radio::after {
  content: ''; width: 6px; height: 6px; border-radius: 50%; background: #fff;
}
.plan-info { flex: 1; }
.plan-name-row { display: flex; align-items: center; gap: 8px; margin-bottom: 3px; }
.plan-nm { font-family: var(--font); font-size: 14px; font-weight: 700; color: #fff; }
.plan-rec {
  font-size: 10px; font-weight: 700; color: var(--green-lit);
  border: 1px solid rgba(94,221,133,.3); border-radius: 100px; padding: 1px 8px;
}
.plan-pr { font-family: var(--font); font-size: 20px; font-weight: 800; color: #fff; line-height: 1; }
.plan-pr span { font-size: 12px; font-weight: 400; color: var(--mute); }
.plan-feats-mini { font-size: 11.5px; color: rgba(255,255,255,.38); margin-top: 4px; }

/* trust list */
.trust-list { margin-top: auto; padding-top: 40px; display: flex; flex-direction: column; gap: 12px; }
.trust-item { display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--dim); }
.trust-check {
  width: 20px; height: 20px; background: rgba(30,168,75,.15); border-radius: 50%;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.trust-check svg { width: 10px; height: 10px; color: var(--green-lit); stroke-width: 2.5; }

/* ══ RIGHT PANEL — white ════════════════════════════════════════════ */
.right {
  width: 50%; background: var(--surface);
  display: flex; flex-direction: column;
  overflow-y: auto;
}
.right-top {
  padding: 24px 40px; display: flex; align-items: center; justify-content: flex-end;
  border-bottom: 1px solid var(--gray-100); flex-shrink: 0;
}
.login-link { font-size: 13.5px; color: var(--gray-400); }
.login-link a { color: var(--green); font-weight: 600; text-decoration: none; margin-left: 4px; }
.login-link a:hover { text-decoration: underline; }

.right-body {
  flex: 1; padding: 40px 56px 56px;
  display: flex; flex-direction: column; justify-content: center;
}

/* form header */
.form-head { margin-bottom: 32px; }
.form-head h2 {
  font-family: var(--font); font-size: 24px; font-weight: 800;
  color: #111; letter-spacing: -.5px; margin-bottom: 6px;
}
.form-head p { font-size: 14px; color: var(--gray-400); line-height: 1.6; }

/* info box */
.info-box {
  background: #f0faf3; border: 1px solid rgba(30,168,75,.18);
  border-radius: 10px; padding: 12px 16px; margin-bottom: 24px;
  font-size: 13px; color: #1a5c2a; line-height: 1.6;
  display: flex; align-items: flex-start; gap: 10px;
}
.info-box svg { width: 15px; height: 15px; flex-shrink: 0; color: var(--green); margin-top: 1px; }

/* error box */
.error-box {
  background: var(--red-bg); border: 1px solid rgba(217,48,37,.2);
  border-radius: 10px; padding: 14px 16px; margin-bottom: 20px;
  display: flex; flex-direction: column; gap: 6px;
}
.error-box li {
  font-size: 13.5px; color: var(--red); list-style: none;
  display: flex; align-items: flex-start; gap: 8px;
}
.error-box li svg { width: 14px; height: 14px; flex-shrink: 0; margin-top: 2px; }
.error-box li a { color: var(--red); font-weight: 600; }

/* form grid */
.fgrid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.fg { display: flex; flex-direction: column; gap: 6px; }
.fg.full { grid-column: 1 / -1; }
.fg label { font-size: 12.5px; font-weight: 600; color: var(--gray-600); }
.fg label .req { color: var(--red); }
.fg input {
  padding: 11px 14px; border: 1.5px solid var(--gray-100);
  border-radius: 9px; font-size: 14px; font-family: var(--font-body);
  color: #111; background: var(--gray-50); outline: none; width: 100%;
  transition: border-color .15s, box-shadow .15s, background .15s;
}
.fg input:focus {
  border-color: var(--green); background: #fff;
  box-shadow: 0 0 0 3px rgba(30,168,75,.1);
}
.fg input::placeholder { color: var(--gray-400); }

/* hidden plan input */
input[name=plan] { display: none; }

.divider { height: 1px; background: var(--gray-100); grid-column: 1/-1; margin: 4px 0; }

/* submit */
.btn-submit {
  width: 100%; padding: 11px 20px; background: var(--green); color: #fff; border: none;
  border-radius: 9px; font-family: var(--font); font-size: 14px; font-weight: 700;
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: all .2s; box-shadow: 0 2px 12px rgba(30,168,75,.25); margin-top: 16px;
}
.btn-submit:hover { background: var(--green-dark); transform: translateY(-1px); box-shadow: 0 6px 24px rgba(30,168,75,.32); }
.btn-submit svg { width: 17px; height: 17px; }

.form-foot { text-align: center; margin-top: 14px; font-size: 13px; color: var(--gray-400); }
.form-foot a { color: var(--green); font-weight: 600; text-decoration: none; }

/* ══ SUCCESS ═════════════════════════════════════════════════════════ */
.success-wrap {
  flex: 1; display: flex; align-items: center; justify-content: center; padding: 48px;
}
.success-box { max-width: 480px; text-align: center; }
.success-icon {
  width: 80px; height: 80px; background: #f0faf3; border-radius: 50%;
  display: flex; align-items: center; justify-content: center; margin: 0 auto 28px;
  border: 2px solid rgba(30,168,75,.15);
}
.success-icon svg { width: 36px; height: 36px; color: var(--green); }
.success-box h2 { font-family: var(--font); font-size: 26px; font-weight: 800; color: #111; letter-spacing: -.5px; margin-bottom: 12px; }
.success-box p { font-size: 15px; color: var(--gray-400); line-height: 1.7; margin-bottom: 28px; }
.success-recap {
  background: var(--gray-50); border: 1px solid var(--gray-100); border-radius: 12px;
  padding: 20px; margin-bottom: 28px; text-align: left;
}
.recap-row { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; border-bottom: 1px solid var(--gray-100); }
.recap-row:last-child { border: none; }
.recap-lbl { font-size: 12.5px; color: var(--gray-400); }
.recap-val { font-size: 13.5px; font-weight: 600; color: #111; }
.btn-to-login {
  display: inline-flex; align-items: center; gap: 9px;
  background: var(--green); color: #fff; text-decoration: none;
  padding: 13px 28px; border-radius: 10px; font-family: var(--font);
  font-size: 15px; font-weight: 700; transition: all .2s;
  box-shadow: 0 2px 12px rgba(30,168,75,.25);
}
.btn-to-login:hover { background: var(--green-dark); transform: translateY(-1px); }
.btn-to-login svg { width: 16px; height: 16px; }
.link-back { display: inline-flex; align-items: center; gap: 6px; margin-top: 14px; font-size: 13.5px; color: var(--gray-400); text-decoration: none; }
.link-back:hover { color: var(--green); }
.link-back svg { width: 13px; height: 13px; }

/* ══ RESPONSIVE ══════════════════════════════════════════════════════ */
@media (max-width: 960px) {
  .left { display: none; }
  .right { width: 100%; }
  .right-body { padding: 32px 36px 48px; }
  .right-top { padding: 18px 36px; }
}
@media (max-width: 520px) {
  .fgrid { grid-template-columns: 1fr; }
  .right-body { padding: 24px 20px 40px; }
  .right-top { padding: 16px 20px; }
}
</style>
</head>
<body>
<div class="shell">

  <!-- ══ LEFT PANEL ══════════════════════════════════════════════════ -->
  <div class="left">
    <div class="left-content">
      <a href="landing" class="logo">
        <div class="logo-mark">
            <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor"><path d="M11 2H9v7H2v2h7v7h2v-7h7V9h-7V2z"/></svg>
          </div>
        <span class="logo-name">digiPharm</span>
      </a>

      <h1>Rejoignez<br><em>digiPharm.</em></h1>
      <p class="left-sub">La plateforme de gestion de pharmacie conçue pour le Congo. Commencez gratuitement en quelques minutes.</p>

      <div class="plan-label">Choisissez votre forfait</div>

      <!-- Plan selector (drives hidden input in form via JS) -->
      <label class="plan-opt" id="lbl-basic">
        <input type="radio" name="plan_ui" value="basic" <?= !$isPro ? 'checked' : '' ?>>
        <div class="plan-card">
          <div class="plan-radio"></div>
          <div class="plan-info">
            <div class="plan-name-row"><span class="plan-nm">Basique</span></div>
            <div class="plan-pr">$10 <span>HT / mois</span></div>
            <div class="plan-feats-mini">Caisse · Stock · Rapports · 3 utilisateurs</div>
          </div>
        </div>
      </label>

      <label class="plan-opt" id="lbl-pro">
        <input type="radio" name="plan_ui" value="pro" <?= $isPro ? 'checked' : '' ?>>
        <div class="plan-card">
          <div class="plan-radio"></div>
          <div class="plan-info">
            <div class="plan-name-row">
              <span class="plan-nm">Pro + IA</span>
              <span class="plan-rec">★ Recommandé</span>
            </div>
            <div class="plan-pr">$25 <span>HT / mois</span></div>
            <div class="plan-feats-mini">Tout Basique + IA + SFEC · 15 utilisateurs</div>
          </div>
        </div>
      </label>

      <div class="trust-list">
        <div class="trust-item">
          <div class="trust-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12l5 5L20 7"/></svg></div>
          14 jours gratuits — sans engagement
        </div>
        <div class="trust-item">
          <div class="trust-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12l5 5L20 7"/></svg></div>
          Aucune carte bancaire requise
        </div>
        <div class="trust-item">
          <div class="trust-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12l5 5L20 7"/></svg></div>
          Conforme SFEC Congo
        </div>
        <div class="trust-item">
          <div class="trust-check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12l5 5L20 7"/></svg></div>
          Activation sous 24h
        </div>
      </div>
    </div>
  </div>

  <!-- ══ RIGHT PANEL ═════════════════════════════════════════════════ -->
  <div class="right">
    <div class="right-top">
      <span class="login-link">Déjà client ?<a href="login">Se connecter</a></span>
    </div>

    <?php if ($success):
      $planLabel = $formData['plan'] === 'pro' ? 'Pro + IA — $25 HT/mois' : 'Basique — $10 HT/mois';
    ?>
    <!-- SUCCESS -->
    <div class="success-wrap">
      <div class="success-box">
        <div class="success-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <h2>Demande reçue !</h2>
        <p>Votre demande a bien été enregistrée. Notre équipe activera votre compte sous 24h et vous enverra vos identifiants par email.</p>
        <div class="success-recap">
          <div class="recap-row"><span class="recap-lbl">Pharmacie</span><span class="recap-val"><?= htmlspecialchars($formData['pharmacy_name']) ?></span></div>
          <div class="recap-row"><span class="recap-lbl">Responsable</span><span class="recap-val"><?= htmlspecialchars($formData['responsible_name']) ?></span></div>
          <div class="recap-row"><span class="recap-lbl">Email</span><span class="recap-val"><?= htmlspecialchars($formData['email']) ?></span></div>
          <div class="recap-row"><span class="recap-lbl">Forfait</span><span class="recap-val"><?= $planLabel ?></span></div>
        </div>
        <a href="login" class="btn-to-login">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          Aller à la connexion
        </a>
        <br>
        <a href="landing" class="link-back">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
          Retour à l'accueil
        </a>
      </div>
    </div>

    <?php else: ?>
    <!-- FORM -->
    <div class="right-body">
      <div class="form-head">
        <h2>Informations de votre pharmacie</h2>
        <p>Remplissez le formulaire ci-dessous. Notre équipe vous contactera pour activer votre accès.</p>
      </div>

      <?php if (!empty($errors)): ?>
      <div class="error-box" role="alert">
        <?php foreach ($errors as $err): ?>
        <li>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?= $err ?>
        </li>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="info-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Après validation, votre période d'essai de <strong>14 jours</strong> démarrera à l'activation de votre compte.
      </div>

      <form method="POST" action="" novalidate>
        <!-- hidden plan synced from left panel -->
        <input type="hidden" name="plan" id="hiddenPlan" value="<?= htmlspecialchars($formData['plan']) ?>">

        <div class="fgrid">

          <div class="fg full">
            <label>Nom de la pharmacie <span class="req">*</span></label>
            <input type="text" name="pharmacy_name"
              placeholder="ex : Pharmacie Centrale de Brazzaville"
              value="<?= htmlspecialchars($formData['pharmacy_name']) ?>"
              autocomplete="organization" required>
          </div>

          <div class="fg full">
            <label>Nom du responsable / gérant <span class="req">*</span></label>
            <input type="text" name="responsible_name"
              placeholder="Prénom et nom"
              value="<?= htmlspecialchars($formData['responsible_name']) ?>"
              autocomplete="name" required>
          </div>

          <div class="fg">
            <label>Email professionnel <span class="req">*</span></label>
            <input type="email" name="email"
              placeholder="contact@mapharmacje.cg"
              value="<?= htmlspecialchars($formData['email']) ?>"
              autocomplete="email" required>
          </div>

          <div class="fg">
            <label>Téléphone</label>
            <input type="tel" name="phone"
              placeholder="+242 06 XXX XX XX"
              value="<?= htmlspecialchars($formData['phone']) ?>"
              autocomplete="tel">
          </div>

          <div class="fg full">
            <label>Ville / Localisation</label>
            <input type="text" name="city"
              placeholder="Brazzaville, Pointe-Noire, Dolisie…"
              value="<?= htmlspecialchars($formData['city']) ?>">
          </div>

        </div>

        <button type="submit" class="btn-submit">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
          Démarrer mon essai gratuit
        </button>

        <p class="form-foot">
          En envoyant ce formulaire vous acceptez nos <a href="#">conditions d'utilisation</a>.
        </p>
      </form>
    </div>
    <?php endif; ?>

  </div><!-- /right -->
</div><!-- /shell -->

<script>
// Sync left panel plan selection → hidden form input
const radios = document.querySelectorAll('input[name=plan_ui]');
const hidden  = document.getElementById('hiddenPlan');
radios.forEach(r => {
  r.addEventListener('change', () => { if (hidden) hidden.value = r.value; });
});
</script>
</body>
</html>
