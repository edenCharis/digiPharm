<?php
/**
 * digiMind — Trial registration page
 * Saves request to dm_trial_requests (digipharmai_db) and emails the admin team.
 */
$success = false;
$error   = '';

require_once __DIR__ . '/config/db.php';
require_once dirname(__DIR__) . '/config/env.php';

// Create table on first run
try {
    $adb = analytics_db();
    $adb->exec("
        CREATE TABLE IF NOT EXISTS dm_trial_requests (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (Exception $e) {
    // Non-fatal: table may already exist
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pharmacy = trim($_POST['pharmacy_name'] ?? '');
    $contact  = trim($_POST['contact_name']  ?? '');
    $email    = trim($_POST['email']         ?? '');
    $phone    = trim($_POST['phone']         ?? '');
    $city     = trim($_POST['city']          ?? '');
    $message  = trim($_POST['message']       ?? '');

    if (!$pharmacy || !$contact || !$email) {
        $error = 'Veuillez remplir tous les champs obligatoires.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } else {
        try {
            $adb->prepare("
                INSERT INTO dm_trial_requests (pharmacy_name, contact_name, email, phone, city, message)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$pharmacy, $contact, $email, $phone ?: null, $city ?: null, $message ?: null]);

            // Notify admin team via email
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
                    $mail->addAddress('service-client@digitaltechnologiescongo.com');
                    $mail->Subject = "[digiMind] Nouvelle demande d'essai — {$pharmacy}";
                    $mail->Body    = "Nouvelle demande d'essai digiMind\n\n"
                                   . "Pharmacie : {$pharmacy}\n"
                                   . "Contact   : {$contact}\n"
                                   . "Email     : {$email}\n"
                                   . "Téléphone : {$phone}\n"
                                   . "Ville     : {$city}\n\n"
                                   . "Message   : {$message}\n\n"
                                   . "Approuver via le superadmin → /superadmin/digimind/users.php";
                    $mail->send();
                } catch (\Exception $ignored) {}
            }

            $success = true;
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                $error = 'Cette adresse email a déjà soumis une demande. Notre équipe reviendra vers vous.';
            } else {
                $error = 'Une erreur est survenue. Veuillez réessayer ou nous contacter directement.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Essai gratuit — digiMind AI</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --teal:       #0d9488;
  --teal-dark:  #0f766e;
  --teal-glow:  rgba(13,148,136,.2);
  --teal-light: #5eead4;
  --ink:        #030f0e;
  --font:       'Plus Jakarta Sans', system-ui, sans-serif;
  --font-body:  'Inter', system-ui, sans-serif;
}
html { min-height: 100%; }
body {
  font-family: var(--font-body); min-height: 100vh;
  background: var(--ink); -webkit-font-smoothing: antialiased;
  display: flex; flex-direction: column;
}

/* background glow */
.bg-glow {
  position: fixed; inset: 0; z-index: 0; pointer-events: none;
  background:
    radial-gradient(ellipse 70% 60% at 15% 20%, rgba(13,148,136,.13) 0%, transparent 60%),
    radial-gradient(ellipse 50% 50% at 85% 80%, rgba(26,127,75,.07) 0%, transparent 60%);
}
.bg-grid {
  position: fixed; inset: 0; z-index: 0; pointer-events: none;
  background-image: radial-gradient(rgba(255,255,255,.03) 1px, transparent 1px);
  background-size: 32px 32px;
}

/* nav */
.nav {
  position: relative; z-index: 10;
  height: 58px; display: flex; align-items: center;
  padding: 0 48px; border-bottom: 1px solid rgba(255,255,255,.06);
  background: rgba(3,15,14,.7); backdrop-filter: blur(16px);
}
.nav-inner { max-width: 1100px; margin: 0 auto; width: 100%; display: flex; align-items: center; justify-content: space-between; }
.logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
.logo-mark {
  width: 32px; height: 32px; border-radius: 8px;
  background: linear-gradient(135deg, var(--teal), #1a7f4b);
  display: flex; align-items: center; justify-content: center;
}
.logo-mark svg { width: 14px; height: 14px; color: #fff; fill: none; stroke: currentColor; stroke-width: 2.5; stroke-linecap: round; }
.logo-name { font-family: var(--font); font-size: 16px; font-weight: 700; color: #fff; }
.logo-badge {
  font-size: 9px; font-weight: 700; color: var(--teal-light);
  background: rgba(13,148,136,.2); border: 1px solid rgba(13,148,136,.3);
  padding: 2px 6px; border-radius: 100px; letter-spacing: .06em; text-transform: uppercase;
}
.nav-back {
  font-size: 13px; color: rgba(255,255,255,.4); text-decoration: none;
  display: flex; align-items: center; gap: 6px; transition: color .15s;
}
.nav-back:hover { color: rgba(255,255,255,.7); }
.nav-back svg { width: 14px; height: 14px; }

/* page layout */
.page {
  flex: 1; position: relative; z-index: 1;
  display: flex; align-items: flex-start; justify-content: center;
  padding: 60px 24px 80px;
}
.page-grid {
  width: 100%; max-width: 980px;
  display: grid; grid-template-columns: 1fr 420px; gap: 48px; align-items: start;
}

/* left — pitch */
.pitch { padding-top: 8px; }
.pitch-pill {
  display: inline-flex; align-items: center; gap: 7px;
  border: 1px solid rgba(13,148,136,.3); background: rgba(13,148,136,.08);
  border-radius: 100px; padding: 4px 12px 4px 7px;
  font-size: 11px; font-weight: 700; color: var(--teal-light);
  letter-spacing: .05em; text-transform: uppercase; margin-bottom: 24px;
}
.pitch-dot {
  width: 18px; height: 18px; background: rgba(13,148,136,.2); border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
}
.pitch-dot::after {
  content: ''; width: 6px; height: 6px; background: var(--teal-light);
  border-radius: 50%; animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse { 0%,100% { opacity:1; transform:scale(1); } 50% { opacity:.4; transform:scale(.75); } }

.pitch h1 {
  font-family: var(--font); font-size: clamp(28px, 3.2vw, 42px);
  font-weight: 800; letter-spacing: -1.5px; line-height: 1.1;
  color: #fff; margin-bottom: 16px;
}
.pitch h1 em {
  font-style: italic;
  background: linear-gradient(135deg, #99f6e4, var(--teal));
  -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
  padding-right: 0.08em; display: inline-block;
}
.pitch-sub { font-size: 15px; color: rgba(255,255,255,.5); line-height: 1.72; max-width: 400px; margin-bottom: 36px; }

.perks { display: flex; flex-direction: column; gap: 16px; margin-bottom: 40px; }
.perk { display: flex; gap: 14px; align-items: flex-start; }
.perk-icon {
  width: 36px; height: 36px; border-radius: 9px;
  background: rgba(13,148,136,.12); border: 1px solid rgba(13,148,136,.2);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  color: var(--teal-light);
}
.perk-icon svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; }
.perk-body h4 { font-family: var(--font); font-size: 14px; font-weight: 700; color: rgba(255,255,255,.85); margin-bottom: 2px; }
.perk-body p  { font-size: 12.5px; color: rgba(255,255,255,.36); line-height: 1.55; }

.trial-box {
  background: rgba(13,148,136,.08); border: 1px solid rgba(13,148,136,.2);
  border-radius: 12px; padding: 16px 20px;
  display: flex; align-items: center; gap: 14px;
}
.trial-box-icon { font-size: 28px; flex-shrink: 0; }
.trial-box-text { font-size: 13px; color: rgba(255,255,255,.55); line-height: 1.6; }
.trial-box-text strong { color: var(--teal-light); }

/* right — form card */
.form-card {
  background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08);
  border-radius: 18px; padding: 36px;
  backdrop-filter: blur(8px);
}
.form-card h2 { font-family: var(--font); font-size: 20px; font-weight: 800; color: #fff; margin-bottom: 4px; letter-spacing: -.4px; }
.form-card p.fc-sub { font-size: 13px; color: rgba(255,255,255,.4); margin-bottom: 28px; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.form-group { margin-bottom: 16px; }
.form-label {
  display: block; font-size: 12px; font-weight: 600;
  color: rgba(255,255,255,.55); margin-bottom: 7px; letter-spacing: .02em;
}
.form-label span { color: rgba(239,68,68,.7); }
.form-input {
  width: 100%; padding: 11px 14px; border-radius: 9px;
  background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.1);
  color: #fff; font-size: 14px; font-family: var(--font-body);
  transition: border-color .15s, background .15s; outline: none;
  -webkit-appearance: none; appearance: none;
}
.form-input::placeholder { color: rgba(255,255,255,.22); }
.form-input:focus { border-color: var(--teal); background: rgba(13,148,136,.08); }
textarea.form-input { resize: vertical; min-height: 80px; }

.form-divider {
  height: 1px; background: rgba(255,255,255,.07); margin: 20px 0;
}

.form-notice {
  background: rgba(13,148,136,.12); border: 1px solid rgba(13,148,136,.25);
  border-radius: 8px; padding: 10px 14px;
  font-size: 12.5px; color: rgba(255,255,255,.55); line-height: 1.6; margin-bottom: 20px;
}
.form-notice strong { color: var(--teal-light); }

.btn-submit {
  width: 100%; padding: 14px; background: var(--teal); color: #fff;
  border: none; border-radius: 10px; font-family: var(--font); font-size: 15px; font-weight: 700;
  cursor: pointer; transition: all .2s; display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-submit:hover:not(:disabled) { background: var(--teal-dark); transform: translateY(-1px); box-shadow: 0 8px 24px var(--teal-glow); }
.btn-submit:disabled { opacity: .6; cursor: wait; }
.btn-submit svg { width: 16px; height: 16px; }

.form-fine { font-size: 11.5px; color: rgba(255,255,255,.25); text-align: center; margin-top: 12px; line-height: 1.6; }
.form-fine a { color: rgba(255,255,255,.35); }

/* alerts */
.alert {
  border-radius: 10px; padding: 14px 18px; margin-bottom: 20px;
  font-size: 13.5px; line-height: 1.6;
}
.alert-error { background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.25); color: #fca5a5; }
.alert-success { background: rgba(13,148,136,.12); border: 1px solid rgba(13,148,136,.3); color: var(--teal-light); }

/* success state */
.success-card {
  text-align: center; padding: 20px 0;
}
.success-icon {
  width: 64px; height: 64px; background: rgba(13,148,136,.15); border-radius: 50%;
  display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;
  border: 1px solid rgba(13,148,136,.3);
}
.success-icon svg { width: 28px; height: 28px; color: var(--teal-light); fill: none; stroke: currentColor; stroke-width: 2.5; stroke-linecap: round; }
.success-card h3 { font-family: var(--font); font-size: 22px; font-weight: 800; color: #fff; margin-bottom: 10px; }
.success-card p  { font-size: 14px; color: rgba(255,255,255,.5); line-height: 1.7; margin-bottom: 28px; }
.btn-back {
  display: inline-block; padding: 12px 28px; background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.15); border-radius: 9px;
  color: rgba(255,255,255,.7); text-decoration: none; font-size: 14px; font-weight: 600;
  font-family: var(--font); transition: all .15s;
}
.btn-back:hover { background: rgba(255,255,255,.13); color: #fff; }

/* responsive */
@media (max-width: 820px) {
  .page-grid { grid-template-columns: 1fr; }
  .pitch h1 { font-size: 28px; }
  .nav { padding: 0 20px; }
}
@media (max-width: 480px) {
  .form-row { grid-template-columns: 1fr; }
  .form-card { padding: 24px 20px; }
}
</style>
</head>
<body>

<div class="bg-glow"></div>
<div class="bg-grid"></div>

<nav class="nav">
  <div class="nav-inner">
    <a href="/analytics/landing" class="logo">
      <div class="logo-mark">
        <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      </div>
      <span class="logo-name">digiMind</span>
      <span class="logo-badge">AI</span>
    </a>
    <a href="/analytics/landing" class="nav-back">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
      Retour
    </a>
  </div>
</nav>

<div class="page">
  <div class="page-grid">

    <!-- LEFT — pitch -->
    <div class="pitch">
      <div class="pitch-pill">
        <span class="pitch-dot"></span>
        Essai gratuit 7 jours
      </div>
      <h1>Démarrez votre<br>essai <em>digiMind</em><br>maintenant.</h1>
      <p class="pitch-sub">
        Accès complet à toutes les fonctionnalités pendant 7 jours. Aucune carte bancaire requise. Annulation en un clic.
      </p>

      <div class="perks">
        <div class="perk">
          <div class="perk-icon">
            <svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 1010 10A10 10 0 0012 2z"/><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          </div>
          <div class="perk-body">
            <h4>Briefing IA dès la première synchronisation</h4>
            <p>Vos données digiPharm sont analysées automatiquement en quelques minutes.</p>
          </div>
        </div>
        <div class="perk">
          <div class="perk-icon">
            <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg>
          </div>
          <div class="perk-body">
            <h4>Alertes de risque en temps réel</h4>
            <p>Ruptures imminentes, péremptions, baisses de CA — détectées avant qu'elles coûtent.</p>
          </div>
        </div>
        <div class="perk">
          <div class="perk-icon">
            <svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
          </div>
          <div class="perk-body">
            <h4>Prévisions de fin de mois</h4>
            <p>L'IA calcule votre probabilité d'atteindre votre objectif mensuel chaque jour.</p>
          </div>
        </div>
        <div class="perk">
          <div class="perk-icon">
            <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 7.8 19.79 19.79 0 01.13 2.25 2 2 0 012.11 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 7.09a16 16 0 006 6l.45-.45a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
          </div>
          <div class="perk-body">
            <h4>Support inclus dès le premier jour</h4>
            <p>Notre équipe vous accompagne pour la mise en route et répondra à toutes vos questions.</p>
          </div>
        </div>
      </div>

      <div class="trial-box">
        <div class="trial-box-icon">🎁</div>
        <div class="trial-box-text">
          <strong>7 jours gratuits</strong>, puis <strong>20 $/mois</strong> — annulable à tout moment. Après votre inscription, notre équipe active votre accès sous 24h.
        </div>
      </div>
    </div>

    <!-- RIGHT — form -->
    <div class="form-card">

      <?php if ($success): ?>
      <div class="success-card">
        <div class="success-icon">
          <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
        </div>
        <h3>Demande reçue !</h3>
        <p>
          Merci pour votre intérêt pour digiMind. Notre équipe activera votre accès sous <strong style="color:var(--teal-light)">24 heures</strong>.<br><br>
          Vous recevrez un email à <strong style="color:#fff"><?= htmlspecialchars($_POST['email'] ?? '') ?></strong> avec vos identifiants de connexion.
        </p>
        <a href="/analytics/landing" class="btn-back">← Retour à l'accueil</a>
      </div>

      <?php else: ?>

      <h2>Créer votre compte</h2>
      <p class="fc-sub">Accès complet · 7 jours gratuits · Sans engagement</p>

      <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="" id="regForm" onsubmit="handleSubmit(event)">
        <div class="form-group">
          <label class="form-label">Nom de la pharmacie <span>*</span></label>
          <input type="text" name="pharmacy_name" class="form-input"
                 placeholder="Pharmacie Centrale Brazza"
                 value="<?= htmlspecialchars($_POST['pharmacy_name'] ?? '') ?>" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Votre nom <span>*</span></label>
            <input type="text" name="contact_name" class="form-input"
                   placeholder="Dr. Jean Dupont"
                   value="<?= htmlspecialchars($_POST['contact_name'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Ville</label>
            <input type="text" name="city" class="form-input"
                   placeholder="Brazzaville"
                   value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email <span>*</span></label>
            <input type="email" name="email" class="form-input"
                   placeholder="vous@pharmacie.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Téléphone</label>
            <input type="tel" name="phone" class="form-input"
                   placeholder="+242 06 XXX XX XX"
                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Message (optionnel)</label>
          <textarea name="message" class="form-input"
                    placeholder="Décrivez votre pharmacie, le nombre de ventes quotidiennes, vos besoins…"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
        </div>

        <div class="form-divider"></div>

        <div class="form-notice">
          Après validation, notre équipe vous contactera sous <strong>24h</strong> pour activer votre accès et vous envoyer vos identifiants de connexion.
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          Démarrer mon essai gratuit
        </button>

        <p class="form-fine">
          En soumettant ce formulaire, vous acceptez d'être contacté par l'équipe Digital Technologies Congo.<br>
          <a href="/analytics/landing">Voir les conditions d'utilisation</a>
        </p>
      </form>

      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function handleSubmit(e) {
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;animation:spin 1s linear infinite"><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" opacity=".25"/><path d="M12 3a9 9 0 019 9"/></svg> Envoi en cours…`;
}
</script>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
</body>
</html>
