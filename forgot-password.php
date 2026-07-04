<?php
require_once 'config/database.php';
require_once 'includes/Mailer.php';

$step    = 'email';   // email → otp → reset → done
$errors  = [];
$info    = '';

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ── Step 1 : submit email ── */
    if (isset($_POST['step']) && $_POST['step'] === 'email') {
        $email = trim($_POST['email'] ?? '');
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Adresse email invalide.';
            $step = 'email';
        } else {
            $user = $db->fetch(
                "SELECT id, username, email FROM user WHERE email = ? AND statut = 1 LIMIT 1",
                [$email]
            );
            if (!$user) {
                // Pas d'info sur si l'email existe ou pas (sécurité)
                $step = 'sent';
                $_SESSION['reset_email'] = $email;
            } else {
                $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $_SESSION['reset_email']   = $email;
                $_SESSION['reset_otp']     = password_hash($otp, PASSWORD_DEFAULT);
                $_SESSION['reset_otp_exp'] = time() + 600;
                $_SESSION['reset_uid']     = $user['id'];
                try {
                    Mailer::sendOtp($email, $user['username'], $otp);
                } catch (Exception $e) {}
                $step = 'sent';
            }
        }
    }

    /* ── Step 2 : verify OTP ── */
    elseif (isset($_POST['step']) && $_POST['step'] === 'otp') {
        $otp = trim($_POST['otp'] ?? '');
        if (empty($_SESSION['reset_otp']) || empty($_SESSION['reset_otp_exp'])) {
            $errors[] = 'Session expirée. Recommencez.'; $step = 'email';
        } elseif (time() > $_SESSION['reset_otp_exp']) {
            $errors[] = 'Le code a expiré. Recommencez.';
            unset($_SESSION['reset_otp'], $_SESSION['reset_otp_exp']);
            $step = 'email';
        } elseif (!password_verify($otp, $_SESSION['reset_otp'])) {
            $errors[] = 'Code incorrect.'; $step = 'otp';
        } else {
            $_SESSION['reset_verified'] = true;
            $step = 'reset';
        }
    }

    /* ── Step 3 : new password ── */
    elseif (isset($_POST['step']) && $_POST['step'] === 'reset') {
        if (empty($_SESSION['reset_verified']) || empty($_SESSION['reset_uid'])) {
            $errors[] = 'Session invalide. Recommencez.'; $step = 'email';
        } else {
            $pw1 = $_POST['password'] ?? '';
            $pw2 = $_POST['password2'] ?? '';
            if (strlen($pw1) < 8)         $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
            elseif ($pw1 !== $pw2)        $errors[] = 'Les mots de passe ne correspondent pas.';
            if (empty($errors)) {
                $db->execute(
                    "UPDATE user SET password = ? WHERE id = ?",
                    [password_hash($pw1, PASSWORD_DEFAULT), $_SESSION['reset_uid']]
                );
                unset($_SESSION['reset_email'], $_SESSION['reset_otp'], $_SESSION['reset_otp_exp'],
                      $_SESSION['reset_uid'], $_SESSION['reset_verified']);
                $step = 'done';
            } else {
                $step = 'reset';
            }
        }
    }

} else {
    // Clear any leftover session on fresh GET
    if (!isset($_GET['otp'])) {
        unset($_SESSION['reset_email'], $_SESSION['reset_otp'], $_SESSION['reset_otp_exp'],
              $_SESSION['reset_uid'], $_SESSION['reset_verified']);
    }
    $step = 'email';
}

// Restore step from session if needed
if ($step === 'email' && !empty($_SESSION['reset_otp']) && empty($errors)) $step = 'otp';
if ($step === 'otp'   && !empty($_SESSION['reset_verified']))              $step = 'reset';

/* ── Left panel copy per step ── */
$leftCopy = [
    'email' => ['Récupérer votre accès',      'Entrez votre adresse email pour recevoir un code de vérification.'],
    'sent'  => ['Email envoyé',               'Si votre email est enregistré, vous recevrez un code sous quelques secondes.'],
    'otp'   => ['Code de vérification',       'Entrez le code à 6 chiffres envoyé à votre adresse email.'],
    'reset' => ['Nouveau mot de passe',        'Choisissez un mot de passe fort pour sécuriser votre compte.'],
    'done'  => ['Mot de passe réinitialisé',  'Votre mot de passe a été mis à jour. Vous pouvez vous connecter.'],
];
[$leftTitle, $leftSub] = $leftCopy[$step] ?? $leftCopy['email'];
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mot de passe oublié — digiPharm</title>
<style>
:root {
  --green:        #16A34A;
  --green-h:      #15803D;
  --green-lit:    #4ADE80;
  --green-glow:   rgba(22,163,74,.18);
  --ink:          #07120D;
  --ink-1:        #081510;
  --ink-2:        #0D1713;
  --ink-b:        rgba(255,255,255,.08);
  --ink-dim:      rgba(255,255,255,.45);
  --ink-mute:     rgba(255,255,255,.25);
  --text:         #111827;
  --text-2:       #6B7280;
  --text-3:       #9CA3AF;
  --border:       #E5E7EB;
  --surf:         #FFFFFF;
  --surf-2:       #F9FAFB;
  --red:          #DC2626;
  --red-bg:       #FEF2F2;
  --red-border:   #FECACA;
  --font: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  --sh-card: 0 1px 2px rgba(0,0,0,.04), 0 6px 18px rgba(0,0,0,.06),
             0 24px 56px rgba(0,0,0,.09), 0 56px 100px rgba(0,0,0,.08);
  --sh-btn:   0 4px 16px rgba(22,163,74,.32);
  --sh-btn-h: 0 8px 28px rgba(22,163,74,.46);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; font-family: var(--font); -webkit-font-smoothing: antialiased; }

.auth-shell { display: flex; width: 100%; min-height: 100vh; }

/* ═══ LEFT ═══ */
.l {
  width: 50%; min-height: 100vh;
  position: relative; overflow: hidden;
  background: linear-gradient(158deg, var(--ink) 0%, var(--ink-1) 52%, var(--ink-2) 100%);
  display: flex; flex-direction: column;
}
.l-dots {
  position: absolute; inset: 0; pointer-events: none;
  background-image: radial-gradient(rgba(255,255,255,.065) 1px, transparent 1px);
  background-size: 26px 26px;
}
.l-glow {
  position: absolute; border-radius: 50%; pointer-events: none;
}
.l-glow-1 {
  width: 500px; height: 500px;
  background: radial-gradient(circle, rgba(22,163,74,.22) 0%, rgba(22,163,74,.07) 42%, transparent 70%);
  top: -170px; left: -140px;
  animation: gd1 14s ease-in-out infinite alternate;
}
.l-glow-2 {
  width: 360px; height: 360px;
  background: radial-gradient(circle, rgba(22,163,74,.12) 0%, transparent 70%);
  bottom: -70px; right: -90px;
  animation: gd2 11s ease-in-out infinite alternate;
}
@keyframes gd1 { to { transform: translate(22px, 18px); } }
@keyframes gd2 { to { transform: translate(-18px, -14px); } }

.l-inner {
  position: relative; z-index: 1;
  padding: 2rem 2.375rem;
  display: flex; flex-direction: column; gap: 2rem; min-height: 100vh;
}
.brand { display: flex; align-items: center; gap: 11px; text-decoration: none; flex-shrink: 0; }
.brand-mark {
  width: 37px; height: 37px; background: var(--green); border-radius: 10px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  box-shadow: 0 0 0 1px rgba(22,163,74,.5), 0 4px 16px rgba(22,163,74,.28);
}
.brand-name { font-size: 16px; font-weight: 800; color: #fff; letter-spacing: -.4px; }
.brand-tag  { font-size: 9.5px; color: var(--ink-mute); letter-spacing: .05em; text-transform: uppercase; font-weight: 500; }

.l-copy { flex-shrink: 0; }
.l-copy h1 {
  font-size: clamp(24px, 2.5vw, 34px); font-weight: 800; color: #fff;
  line-height: 1.15; letter-spacing: -.05em; margin-bottom: .7rem;
}
.l-copy h1 em { color: var(--green-lit); font-style: normal; }
.l-copy p { font-size: 13px; color: var(--ink-dim); line-height: 1.7; max-width: 320px; }

/* steps indicator */
.steps { display: flex; flex-direction: column; gap: 0; }
.step-item {
  display: flex; align-items: flex-start; gap: 14px; padding: 0 0 24px;
  position: relative;
}
.step-item:not(:last-child)::before {
  content: ''; position: absolute; left: 13px; top: 28px; bottom: 0; width: 1px;
  background: var(--ink-b);
}
.step-num {
  width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700;
  border: 1.5px solid var(--ink-b); color: var(--ink-mute);
  background: transparent;
}
.step-num.done  { background: var(--green); border-color: var(--green); color: #fff; }
.step-num.active { border-color: var(--green); color: var(--green-lit); }
.step-lbl { font-size: 13px; font-weight: 500; color: var(--ink-mute); padding-top: 4px; line-height: 1.3; }
.step-lbl.active { color: rgba(255,255,255,.85); }
.step-lbl.done  { color: rgba(255,255,255,.5); }

.l-foot { margin-top: auto; padding-top: 2rem; border-top: 1px solid var(--ink-b); }
.l-foot a { font-size: 13px; color: rgba(255,255,255,.4); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: color .2s; }
.l-foot a:hover { color: rgba(255,255,255,.8); }
.l-foot svg { width: 13px; height: 13px; }

/* ═══ RIGHT ═══ */
.r {
  width: 50%; background: var(--surf);
  display: flex; align-items: center; justify-content: center;
  overflow-y: auto; padding: 2rem 1.5rem; position: relative;
  scrollbar-width: thin;
}
.r::before {
  content: ''; position: absolute; inset: 0; pointer-events: none;
  background-image: radial-gradient(rgba(22,163,74,.024) 1px, transparent 1px);
  background-size: 24px 24px;
}
.r-inner {
  width: 100%; max-width: 440px; position: relative; z-index: 1;
  animation: cardIn .45s cubic-bezier(.16,1,.3,1) both;
}
@keyframes cardIn { from { opacity:0; transform:translateY(14px); } to { opacity:1; transform:translateY(0); } }

.card {
  background: #fff; border-radius: 24px; padding: 44px 44px 36px;
  border: 1px solid rgba(0,0,0,.055); box-shadow: var(--sh-card);
}

.card-ico {
  width: 52px; height: 52px;
  background: linear-gradient(135deg, #F0FDF4, #DCFCE7);
  border: 1.5px solid rgba(22,163,74,.22); border-radius: 15px;
  display: flex; align-items: center; justify-content: center;
  color: var(--green); margin-bottom: 20px;
  box-shadow: 0 4px 14px rgba(22,163,74,.14);
}
.card-ico svg { width: 22px; height: 22px; }

.c-title { font-size: 1.5rem; font-weight: 800; color: var(--text); letter-spacing: -.045em; line-height: 1.1; margin-bottom: 5px; }
.c-desc  { font-size: 13.5px; color: var(--text-2); line-height: 1.55; margin-bottom: 22px; }

/* error */
.err-box {
  background: var(--red-bg); border: 1px solid var(--red-border);
  border-radius: 10px; padding: 11px 14px; margin-bottom: 16px;
  display: flex; flex-direction: column; gap: 5px;
}
.err-item { display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: var(--red); }
.err-item svg { width: 14px; height: 14px; flex-shrink: 0; margin-top: 1px; }

/* fields */
.field { margin-bottom: 13px; }
.f-lbl { display: block; font-size: 12.5px; font-weight: 600; color: var(--text); margin-bottom: 7px; }
.f-wrap { position: relative; }
.f-ico { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-3); pointer-events: none; display: flex; }
.f-ico svg { width: 16px; height: 16px; }
.f-input {
  width: 100%; height: 54px; padding: 0 44px;
  border: 1.5px solid var(--border); border-radius: 14px;
  font-size: 14px; font-family: var(--font); color: var(--text);
  background: var(--surf-2); outline: none; appearance: none;
  transition: border-color 200ms, box-shadow 200ms, background 200ms;
}
.f-input::placeholder { color: var(--text-3); }
.f-input:hover { border-color: #D1D5DB; background: #fff; }
.f-input:focus { border-color: var(--green); box-shadow: 0 0 0 3.5px rgba(22,163,74,.1); background: #fff; }
.f-input.otp-style {
  text-align: center; font-size: 2rem; font-weight: 700;
  letter-spacing: .5rem; padding: 0 1rem;
}
.pw-btn {
  position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer; padding: 4px;
  color: var(--text-3); display: flex; border-radius: 6px;
}
.pw-btn:hover { color: var(--text-2); }
.pw-btn svg { width: 16px; height: 16px; }

/* submit */
.btn-go {
  width: 100%; height: 54px; background: var(--green); color: #fff;
  border: none; border-radius: 14px; font-size: 15px; font-weight: 700; font-family: var(--font);
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
  letter-spacing: -.01em; margin-top: 4px;
  transition: background 250ms, transform 200ms, box-shadow 250ms;
  box-shadow: var(--sh-btn);
}
.btn-go:hover { background: var(--green-h); transform: translateY(-2px); box-shadow: var(--sh-btn-h); }
.btn-go svg { width: 17px; height: 17px; }

.card-foot-link {
  text-align: center; margin-top: 16px;
  font-size: 13px; color: var(--text-2);
}
.card-foot-link a { color: var(--green); font-weight: 600; text-decoration: none; }
.card-foot-link a:hover { color: var(--green-h); }

/* success state */
.success-ico {
  width: 72px; height: 72px;
  background: linear-gradient(135deg,#F0FDF4,#DCFCE7);
  border: 1.5px solid rgba(22,163,74,.2); border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 20px; color: var(--green);
}
.success-ico svg { width: 32px; height: 32px; }

/* pw strength */
.pw-strength { height: 3px; border-radius: 2px; background: var(--border); margin-top: 6px; overflow: hidden; }
.pw-strength-bar { height: 100%; border-radius: 2px; width: 0; transition: width .3s, background .3s; }
.pw-hint { font-size: 11.5px; color: var(--text-3); margin-top: 5px; }

/* otp hint */
.otp-hint { font-size: 12px; color: var(--text-3); text-align: center; margin-top: 6px; }

@media (max-width: 900px) { .l { display: none; } .r { width: 100%; } }
@media (max-width: 520px)  { .card { padding: 28px 22px 24px; } }
@media (prefers-reduced-motion: reduce) { .l-glow-1,.l-glow-2,.r-inner { animation: none; } }
</style>
</head>
<body>
<div class="auth-shell">

<!-- ═══ LEFT ═══ -->
<div class="l" aria-hidden="true">
  <div class="l-dots"></div>
  <div class="l-glow l-glow-1"></div>
  <div class="l-glow l-glow-2"></div>

  <div class="l-inner">
    <a href="landing" class="brand">
      <div class="brand-mark">
        <svg width="17" height="17" viewBox="0 0 20 20" fill="#fff"><path d="M11 2H9v7H2v2h7v7h2v-7h7V9h-7V2z"/></svg>
      </div>
      <div>
        <div class="brand-name">digiPharm</div>
        <div class="brand-tag">Modern Pharmacy OS</div>
      </div>
    </a>

    <div class="l-copy">
      <h1><?= $leftTitle ?></h1>
      <p><?= $leftSub ?></p>
    </div>

    <div class="steps">
      <?php
        $stepOrder = ['email' => 1, 'sent' => 1, 'otp' => 2, 'reset' => 3, 'done' => 4];
        $cur = $stepOrder[$step] ?? 1;
        $stepDefs = [
          [1, 'Adresse email'],
          [2, 'Code OTP'],
          [3, 'Nouveau mot de passe'],
          [4, 'Terminé'],
        ];
        foreach ($stepDefs as [$n, $lbl]):
          $cls = $n < $cur ? 'done' : ($n === $cur ? 'active' : '');
          $numContent = $n < $cur
            ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M5 12l5 5L20 7"/></svg>'
            : $n;
      ?>
      <div class="step-item">
        <div class="step-num <?= $cls ?>"><?= $numContent ?></div>
        <div class="step-lbl <?= $cls ?>"><?= $lbl ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="l-foot">
      <a href="login">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Retour à la connexion
      </a>
    </div>
  </div>
</div>

<!-- ═══ RIGHT ═══ -->
<div class="r">
  <div class="r-inner">
    <div class="card">

    <?php if ($step === 'done'): ?>

      <div style="text-align:center">
        <div class="success-ico">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <h1 class="c-title" style="text-align:center">Mot de passe mis à jour !</h1>
        <p class="c-desc" style="text-align:center;margin-bottom:28px">Votre nouveau mot de passe est actif. Vous pouvez maintenant vous connecter.</p>
        <a href="login" class="btn-go" style="text-decoration:none">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M11 16l-4-4m0 0l4-4m-4 4h14"/></svg>
          Se connecter
        </a>
      </div>

    <?php elseif ($step === 'sent'): ?>

      <div class="card-ico">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
          <polyline points="22,6 12,13 2,6"/>
        </svg>
      </div>
      <h1 class="c-title">Vérifiez votre email</h1>
      <p class="c-desc">Si <strong><?= htmlspecialchars($_SESSION['reset_email'] ?? '') ?></strong> est enregistrée, vous recevrez un code dans quelques secondes.</p>
      <a href="forgot-password" class="btn-go" style="text-decoration:none">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 101.85-4.36L1 10"/></svg>
        Entrer le code OTP
      </a>
      <p class="card-foot-link"><a href="login">← Retour à la connexion</a></p>

    <?php elseif ($step === 'otp'): ?>

      <div class="card-ico">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
          <polyline points="22,6 12,13 2,6"/>
        </svg>
      </div>
      <h1 class="c-title">Code de vérification</h1>
      <p class="c-desc">Entrez le code à 6 chiffres envoyé à votre email.</p>

      <?php if (!empty($errors)): ?>
      <div class="err-box">
        <?php foreach ($errors as $e): ?>
        <div class="err-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?= $e ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <input type="hidden" name="step" value="otp">
        <div class="field">
          <label class="f-lbl">Code OTP</label>
          <div class="f-wrap">
            <input type="text" name="otp" class="f-input otp-style"
                   placeholder="000000" maxlength="6" pattern="[0-9]{6}"
                   inputmode="numeric" autocomplete="one-time-code" autofocus required>
          </div>
          <div class="otp-hint">Code valide pendant 10 minutes</div>
        </div>
        <button type="submit" class="btn-go">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          Vérifier
        </button>
      </form>
      <p class="card-foot-link"><a href="forgot-password">Renvoyer un code →</a></p>

    <?php elseif ($step === 'reset'): ?>

      <div class="card-ico">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
          <path d="M7 11V7a5 5 0 0110 0v4"/>
        </svg>
      </div>
      <h1 class="c-title">Nouveau mot de passe</h1>
      <p class="c-desc">Choisissez un mot de passe fort d'au moins 8 caractères.</p>

      <?php if (!empty($errors)): ?>
      <div class="err-box">
        <?php foreach ($errors as $e): ?>
        <div class="err-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?= $e ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <input type="hidden" name="step" value="reset">
        <div class="field">
          <label class="f-lbl">Nouveau mot de passe</label>
          <div class="f-wrap">
            <span class="f-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg></span>
            <input type="password" name="password" id="pw1" class="f-input" placeholder="••••••••" autofocus required>
            <button type="button" class="pw-btn" onclick="togglePw('pw1','eye1a','eye1b')" aria-label="Afficher">
              <svg id="eye1a" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg id="eye1b" style="display:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <div class="pw-strength"><div class="pw-strength-bar" id="pwBar"></div></div>
          <div class="pw-hint" id="pwHint"></div>
        </div>
        <div class="field">
          <label class="f-lbl">Confirmer le mot de passe</label>
          <div class="f-wrap">
            <span class="f-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg></span>
            <input type="password" name="password2" id="pw2" class="f-input" placeholder="••••••••" required>
            <button type="button" class="pw-btn" onclick="togglePw('pw2','eye2a','eye2b')" aria-label="Afficher">
              <svg id="eye2a" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg id="eye2b" style="display:none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
        </div>
        <button type="submit" class="btn-go">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
          Enregistrer le mot de passe
        </button>
      </form>

    <?php else: /* step === email */ ?>

      <div class="card-ico">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/>
          <path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
      </div>
      <h1 class="c-title">Mot de passe oublié ?</h1>
      <p class="c-desc">Entrez votre adresse email. Nous vous enverrons un code pour réinitialiser votre mot de passe.</p>

      <?php if (!empty($errors)): ?>
      <div class="err-box">
        <?php foreach ($errors as $e): ?>
        <div class="err-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <?= $e ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <input type="hidden" name="step" value="email">
        <div class="field">
          <label class="f-lbl" for="email">Adresse email</label>
          <div class="f-wrap">
            <span class="f-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
            <input type="email" name="email" id="email" class="f-input"
                   placeholder="votre@email.com" autocomplete="email" autofocus required>
          </div>
        </div>
        <button type="submit" class="btn-go">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          Envoyer le code
        </button>
      </form>
      <p class="card-foot-link"><a href="login">← Retour à la connexion</a></p>

    <?php endif; ?>

    </div>
  </div>
</div>

</div>
<script>
function togglePw(inputId, onId, offId) {
  const inp = document.getElementById(inputId);
  const show = inp.type === 'password';
  inp.type = show ? 'text' : 'password';
  document.getElementById(onId).style.display  = show ? 'none' : '';
  document.getElementById(offId).style.display = show ? ''     : 'none';
}

const pw1 = document.getElementById('pw1');
const bar = document.getElementById('pwBar');
const hint = document.getElementById('pwHint');
if (pw1 && bar) {
  pw1.addEventListener('input', () => {
    const v = pw1.value;
    let score = 0;
    if (v.length >= 8)  score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const levels = [
      [0, '#E5E7EB', ''],
      [25, '#F87171', 'Trop court'],
      [50, '#FBBF24', 'Faible'],
      [75, '#60A5FA', 'Moyen'],
      [100, '#16A34A', 'Fort'],
    ];
    const [pct, color, label] = levels[score] || levels[0];
    bar.style.width = pct + '%';
    bar.style.background = color;
    if (hint) hint.textContent = label;
  });
}

// OTP auto-submit
const otpInput = document.querySelector('.otp-style');
if (otpInput) {
  otpInput.addEventListener('input', function () {
    this.value = this.value.replace(/[^0-9]/g, '');
    if (this.value.length === 6) setTimeout(() => this.closest('form').submit(), 300);
  });
}
</script>
</body>
</html>
