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

// Load plans from DB (falls back to hardcoded defaults if table doesn't exist yet)
$dbPlans = [];
try {
    $dbPlans = $db->fetchAll("SELECT * FROM plans WHERE is_active = 1 ORDER BY sort_order, id", []);
} catch (Exception $e) {}
if (empty($dbPlans)) {
    $dbPlans = [
        ['slug'=>'starter','name'=>'Basique', 'price_usd'=>10,'price_xaf'=>6000,'features'=>'Caisse · Stock · Rapports','max_users'=>3],
        ['slug'=>'pro',    'name'=>'Pro + IA','price_usd'=>25,'price_xaf'=>15000,'features'=>'Tout Basique · IA · SFEC Congo','max_users'=>15],
    ];
}
$validSlugs = array_column($dbPlans, 'slug');
$plansBySlug = array_column($dbPlans, null, 'slug');

$plan     = in_array($_GET['plan'] ?? '', $validSlugs) ? $_GET['plan'] : $validSlugs[0];
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
        'plan'             => in_array($_POST['plan'] ?? '', $validSlugs) ? $_POST['plan'] : $validSlugs[0],
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
$selectedPlan = $plansBySlug[$formData['plan']] ?? $dbPlans[0];
$isPro = $formData['plan'] === 'pro';
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Créer votre compte — digiPharm</title>
<style>
:root {
  --green:        #16A34A;
  --green-h:      #15803D;
  --green-lit:    #4ADE80;
  --green-glow:   rgba(22,163,74,.18);
  --green-surf:   rgba(22,163,74,.10);
  --green-border: rgba(22,163,74,.25);
  --ink:          #07120D;
  --ink-1:        #081510;
  --ink-2:        #0D1713;
  --ink-card:     rgba(255,255,255,.04);
  --ink-b:        rgba(255,255,255,.08);
  --ink-b2:       rgba(255,255,255,.13);
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

html, body {
  height: 100%;
  font-family: var(--font);
  -webkit-font-smoothing: antialiased;
}

.auth-shell { display: flex; width: 100%; min-height: 100vh; }

/* ═══ LEFT PANEL ═══ */
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
  display: flex; flex-direction: column; gap: 2rem;
  min-height: 100vh;
}

.brand { display: flex; align-items: center; gap: 11px; flex-shrink: 0; text-decoration: none; }
.brand-mark {
  width: 37px; height: 37px; background: var(--green); border-radius: 10px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  box-shadow: 0 0 0 1px rgba(22,163,74,.5), 0 4px 16px rgba(22,163,74,.28);
}
.brand-name { font-size: 16px; font-weight: 800; color: #fff; letter-spacing: -.4px; }
.brand-tag  { font-size: 9.5px; color: var(--ink-mute); letter-spacing: .05em; text-transform: uppercase; font-weight: 500; }

.l-hero { flex-shrink: 0; }
.l-hero h1 {
  font-size: clamp(24px, 2.5vw, 34px); font-weight: 800; color: #fff;
  line-height: 1.15; letter-spacing: -.05em; margin-bottom: .7rem;
}
.l-hero h1 em { color: var(--green-lit); font-style: normal; }
.l-hero p { font-size: 13px; color: var(--ink-dim); line-height: 1.7; max-width: 320px; }

/* plan selector */
.plan-label {
  font-size: 10.5px; font-weight: 700; color: var(--ink-mute);
  text-transform: uppercase; letter-spacing: .08em; margin-bottom: 10px;
}
.plan-opts { display: flex; flex-direction: column; gap: 8px; }
.plan-opt { display: block; cursor: pointer; }
.plan-opt input { position: absolute; opacity: 0; pointer-events: none; }
.plan-card {
  border: 1.5px solid var(--ink-b); border-radius: 14px; padding: 14px 16px;
  display: flex; align-items: center; gap: 14px;
  background: var(--ink-card); backdrop-filter: blur(8px);
  transition: border-color 200ms, background 200ms, box-shadow 200ms;
}
.plan-opt input:checked + .plan-card {
  border-color: var(--green); background: var(--green-surf);
  box-shadow: 0 0 0 1px var(--green), 0 4px 20px var(--green-glow);
}
.plan-radio {
  width: 17px; height: 17px; border-radius: 50%; border: 2px solid rgba(255,255,255,.2);
  flex-shrink: 0; display: flex; align-items: center; justify-content: center;
  transition: border-color 200ms, background 200ms;
}
.plan-opt input:checked + .plan-card .plan-radio { border-color: var(--green); background: var(--green); }
.plan-opt input:checked + .plan-card .plan-radio::after {
  content: ''; width: 6px; height: 6px; border-radius: 50%; background: #fff;
}
.plan-name { font-size: 13.5px; font-weight: 700; color: #fff; }
.plan-rec  {
  font-size: 9.5px; font-weight: 700; color: var(--green-lit);
  border: 1px solid rgba(74,222,128,.3); border-radius: 100px; padding: 1px 7px;
}
.plan-price { font-size: 18px; font-weight: 800; color: #fff; line-height: 1; margin-top: 2px; }
.plan-price span { font-size: 11px; font-weight: 400; color: var(--ink-mute); }
.plan-price-xaf { font-size: 11px; font-weight: 600; color: rgba(74,222,128,.7); margin-top: 2px; font-variant-numeric: tabular-nums; }
.plan-feats { font-size: 11px; color: rgba(255,255,255,.35); margin-top: 3px; }

/* trust */
.trust { margin-top: auto; padding-top: 2rem; border-top: 1px solid var(--ink-b); display: flex; flex-direction: column; gap: 10px; }
.trust-item { display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--ink-dim); }
.trust-ico {
  width: 22px; height: 22px; background: rgba(22,163,74,.15); border-radius: 50%;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  color: var(--green-lit);
}
.trust-ico svg { width: 11px; height: 11px; stroke-width: 2.5; }

/* ═══ RIGHT PANEL ═══ */
.r {
  width: 50%; background: var(--surf);
  display: flex; align-items: flex-start; justify-content: center;
  overflow-y: auto; padding: 3rem 2rem; position: relative;
  scrollbar-width: thin;
}
.r::before {
  content: ''; position: absolute; inset: 0; pointer-events: none;
  background-image: radial-gradient(rgba(22,163,74,.024) 1px, transparent 1px);
  background-size: 24px 24px;
}

.r-inner {
  width: 100%; max-width: 520px; position: relative; z-index: 1;
  animation: cardIn .45s cubic-bezier(.16,1,.3,1) both;
}
@keyframes cardIn { from { opacity:0; transform:translateY(14px); } to { opacity:1; transform:translateY(0); } }

.card {
  background: #fff; border-radius: 24px; padding: 44px 44px 36px;
  border: 1px solid rgba(0,0,0,.055); box-shadow: var(--sh-card); margin-bottom: 0;
}

/* card icon */
.card-ico {
  width: 52px; height: 52px;
  background: linear-gradient(135deg, #F0FDF4, #DCFCE7);
  border: 1.5px solid rgba(22,163,74,.22); border-radius: 15px;
  display: flex; align-items: center; justify-content: center;
  color: var(--green); margin-bottom: 20px;
  box-shadow: 0 4px 14px rgba(22,163,74,.14);
}
.card-ico svg { width: 22px; height: 22px; }

.c-title { font-size: 1.6rem; font-weight: 800; color: var(--text); letter-spacing: -.045em; line-height: 1.1; margin-bottom: 5px; }
.c-desc  { font-size: 13.5px; color: var(--text-2); line-height: 1.55; margin-bottom: 24px; }

/* errors */
.err-box {
  background: var(--red-bg); border: 1px solid var(--red-border);
  border-radius: 10px; padding: 12px 14px; margin-bottom: 18px;
  display: flex; flex-direction: column; gap: 5px;
}
.err-item { display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: var(--red); }
.err-item svg { width: 14px; height: 14px; flex-shrink: 0; margin-top: 1px; }
.err-item a { color: var(--red); font-weight: 600; }

/* fields */
.fgrid { display: grid; grid-template-columns: 1fr 1fr; gap: 13px; }
.fg { display: flex; flex-direction: column; gap: 6px; }
.fg.full { grid-column: 1 / -1; }
.f-lbl { font-size: 12.5px; font-weight: 600; color: var(--text); letter-spacing: .01em; }
.f-lbl .req { color: var(--red); }
.f-input {
  width: 100%; height: 48px; padding: 0 14px;
  border: 1.5px solid var(--border); border-radius: 12px;
  font-size: 14px; font-family: var(--font); color: var(--text);
  background: var(--surf-2); outline: none; appearance: none; -webkit-appearance: none;
  transition: border-color 200ms, box-shadow 200ms, background 200ms;
}
.f-input::placeholder { color: var(--text-3); }
.f-input:hover { border-color: #D1D5DB; background: #fff; }
.f-input:focus { border-color: var(--green); box-shadow: 0 0 0 3.5px rgba(22,163,74,.1); background: #fff; }

/* submit */
.btn-go {
  width: 100%; height: 52px; background: var(--green); color: #fff;
  border: none; border-radius: 14px; font-size: 15px; font-weight: 700; font-family: var(--font);
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;
  margin-top: 20px; letter-spacing: -.01em;
  transition: background 250ms, transform 200ms, box-shadow 250ms;
  box-shadow: var(--sh-btn);
}
.btn-go:hover { background: var(--green-h); transform: translateY(-2px); box-shadow: var(--sh-btn-h); }
.btn-go:active { transform: translateY(0); }
.btn-go svg { width: 17px; height: 17px; }

.card-foot-link {
  text-align: center; margin-top: 16px;
  font-size: 13px; color: var(--text-2);
}
.card-foot-link a { color: var(--green); font-weight: 600; text-decoration: none; }
.card-foot-link a:hover { color: var(--green-h); }

/* success */
.success-wrap { text-align: center; }
.success-ico {
  width: 72px; height: 72px; background: linear-gradient(135deg,#F0FDF4,#DCFCE7);
  border: 1.5px solid rgba(22,163,74,.2); border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 20px; color: var(--green);
}
.success-ico svg { width: 32px; height: 32px; }
.success-title { font-size: 1.5rem; font-weight: 800; color: var(--text); letter-spacing: -.04em; margin-bottom: 8px; }
.success-sub { font-size: 14px; color: var(--text-2); line-height: 1.7; margin-bottom: 24px; }
.success-recap {
  background: var(--surf-2); border: 1px solid var(--border); border-radius: 14px;
  padding: 16px; margin-bottom: 24px; text-align: left;
}
.recap-row { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid var(--border); }
.recap-row:last-child { border: none; }
.recap-lbl { font-size: 12.5px; color: var(--text-3); }
.recap-val { font-size: 13px; font-weight: 600; color: var(--text); }
.btn-login {
  display: inline-flex; align-items: center; gap: 9px;
  background: var(--green); color: #fff; text-decoration: none;
  padding: 13px 28px; border-radius: 14px; font-size: 15px; font-weight: 700;
  transition: background 200ms, transform 200ms; box-shadow: var(--sh-btn);
}
.btn-login:hover { background: var(--green-h); transform: translateY(-1px); }
.btn-login svg { width: 16px; height: 16px; }

@media (max-width: 900px) { .l { display: none; } .r { width: 100%; } }
@media (max-width: 560px) { .fgrid { grid-template-columns: 1fr; } .card { padding: 28px 22px 24px; } }
@media (prefers-reduced-motion: reduce) { .l-glow-1, .l-glow-2, .r-inner { animation: none; } }
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

    <div class="l-hero">
      <h1>Rejoignez<br><em>digiPharm.</em></h1>
      <p>La plateforme de gestion de pharmacie conçue pour le Congo. Commencez gratuitement en quelques minutes.</p>
    </div>

    <div>
      <div class="plan-label">Choisissez votre forfait</div>
      <div class="plan-opts">
        <?php foreach ($dbPlans as $i => $pl): ?>
        <label class="plan-opt" id="lbl-<?= htmlspecialchars($pl['slug']) ?>">
          <input type="radio" name="plan_ui" value="<?= htmlspecialchars($pl['slug']) ?>"
                 <?= $formData['plan'] === $pl['slug'] ? 'checked' : '' ?>>
          <div class="plan-card">
            <div class="plan-radio"></div>
            <div style="flex:1">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:2px">
                <span class="plan-name"><?= htmlspecialchars($pl['name']) ?></span>
                <?php if ($i === 1): ?><span class="plan-rec">★ Recommandé</span><?php endif; ?>
              </div>
              <div class="plan-price">
                $<?= number_format((float)$pl['price_usd'], 2) ?>
                <span>HT / mois</span>
              </div>
              <div class="plan-price-xaf">
                <?= number_format((int)$pl['price_xaf'], 0, ',', ' ') ?> XAF HT/mois
              </div>
              <?php if ($pl['features']): ?>
              <div class="plan-feats"><?= htmlspecialchars($pl['features']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="trust">
      <div class="trust-item">
        <div class="trust-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12l5 5L20 7"/></svg></div>
        14 jours gratuits — sans engagement
      </div>
      <div class="trust-item">
        <div class="trust-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12l5 5L20 7"/></svg></div>
        Aucune carte bancaire requise
      </div>
      <div class="trust-item">
        <div class="trust-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M5 12l5 5L20 7"/></svg></div>
        Conforme SFEC Congo · Activation sous 24h
      </div>
    </div>
  </div>
</div>

<!-- ═══ RIGHT ═══ -->
<div class="r">
  <div class="r-inner">
    <div class="card">

      <?php if ($success):
        $sp = $plansBySlug[$formData['plan']] ?? $dbPlans[0];
        $planLabel = htmlspecialchars($sp['name']) . ' — $' . number_format((float)$sp['price_usd'], 2) . ' / ' . number_format((int)$sp['price_xaf'], 0, ',', ' ') . ' XAF HT/mois';
      ?>

      <div class="success-wrap">
        <div class="success-ico">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <h1 class="success-title">Demande reçue !</h1>
        <p class="success-sub">Notre équipe activera votre compte sous 24h et vous enverra vos identifiants de connexion par email.</p>
        <div class="success-recap">
          <div class="recap-row"><span class="recap-lbl">Pharmacie</span><span class="recap-val"><?= htmlspecialchars($formData['pharmacy_name']) ?></span></div>
          <div class="recap-row"><span class="recap-lbl">Responsable</span><span class="recap-val"><?= htmlspecialchars($formData['responsible_name']) ?></span></div>
          <div class="recap-row"><span class="recap-lbl">Email</span><span class="recap-val"><?= htmlspecialchars($formData['email']) ?></span></div>
          <div class="recap-row"><span class="recap-lbl">Forfait</span><span class="recap-val"><?= $planLabel ?></span></div>
        </div>
        <a href="login" class="btn-login">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          Aller à la connexion
        </a>
      </div>

      <?php else: ?>

      <div class="card-ico">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>
          <path d="M22 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
        </svg>
      </div>

      <h1 class="c-title">Créer votre compte</h1>
      <p class="c-desc">Remplissez le formulaire ci-dessous. Notre équipe vous contactera pour activer votre accès.</p>

      <?php if (!empty($errors)): ?>
      <div class="err-box" role="alert">
        <?php foreach ($errors as $err): ?>
        <div class="err-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span><?= $err ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <input type="hidden" name="plan" id="hiddenPlan" value="<?= htmlspecialchars($formData['plan']) ?>">

        <div class="fgrid">

          <div class="fg full">
            <label class="f-lbl">Nom de la pharmacie <span class="req">*</span></label>
            <input type="text" name="pharmacy_name" class="f-input"
              placeholder="ex : Pharmacie Centrale de Brazzaville"
              value="<?= htmlspecialchars($formData['pharmacy_name']) ?>"
              autocomplete="organization" required>
          </div>

          <div class="fg full">
            <label class="f-lbl">Nom du responsable / gérant <span class="req">*</span></label>
            <input type="text" name="responsible_name" class="f-input"
              placeholder="Prénom et nom"
              value="<?= htmlspecialchars($formData['responsible_name']) ?>"
              autocomplete="name" required>
          </div>

          <div class="fg">
            <label class="f-lbl">Email professionnel <span class="req">*</span></label>
            <input type="email" name="email" class="f-input"
              placeholder="contact@mapharmacie.cg"
              value="<?= htmlspecialchars($formData['email']) ?>"
              autocomplete="email" required>
          </div>

          <div class="fg">
            <label class="f-lbl">Téléphone</label>
            <input type="tel" name="phone" class="f-input"
              placeholder="+242 06 XXX XX XX"
              value="<?= htmlspecialchars($formData['phone']) ?>"
              autocomplete="tel">
          </div>

          <div class="fg full">
            <label class="f-lbl">Ville / Localisation</label>
            <input type="text" name="city" class="f-input"
              placeholder="Brazzaville, Pointe-Noire, Dolisie…"
              value="<?= htmlspecialchars($formData['city']) ?>">
          </div>

        </div>

        <button type="submit" class="btn-go">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
          Démarrer mon essai gratuit
        </button>

        <p class="card-foot-link">Déjà client ? <a href="login">Se connecter →</a></p>
      </form>

      <?php endif; ?>
    </div>
  </div>
</div>

</div>
<script>
const radios = document.querySelectorAll('input[name=plan_ui]');
const hidden  = document.getElementById('hiddenPlan');
radios.forEach(r => r.addEventListener('change', () => { if (hidden) hidden.value = r.value; }));
</script>
</body>
</html>
