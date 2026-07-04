<?php
require_once __DIR__ . '/config/auth.php';

if (!empty($_SESSION['ai_user_id'])) {
    header('Location: /analytics/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $user = ai_verify_login($email, $password);
        if ($user) {
            $_SESSION['ai_user_id']       = $user['id'];
            $_SESSION['ai_display_name']  = $user['display_name'] ?: $user['email'];
            $_SESSION['ai_email']         = $user['email'];
            $_SESSION['ai_role']          = $user['role'];
            $_SESSION['ai_pharmacy_id']   = $user['pharmacy_id'];
            $_SESSION['ai_pharmacy_name'] = $user['pharmacy_name'];
            $_SESSION['ai_api_key']       = $user['api_key'];
            header('Location: /analytics/');
            exit;
        }
        $error = 'Email ou mot de passe incorrect.';
    } else {
        $error = 'Remplissez tous les champs.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>digiMind — Connexion</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --green:      #1a7f4b;
  --green-lt:   #e8f5ee;
  --border:     #dadce0;
  --text:       #1a1a1a;
  --text-muted: #6b7280;
  --bg:         #f3f4f6;
  --surface:    #ffffff;
  --radius:     10px;
}

body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background: var(--bg);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

.card {
  background: var(--surface);
  border-radius: 16px;
  border: 1px solid var(--border);
  padding: 48px 40px;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 4px 24px rgba(0,0,0,.06);
}

.logo {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 32px;
}
.logo-icon {
  width: 38px; height: 38px;
  background: var(--green);
  border-radius: 8px;
  display: grid; place-items: center;
}
.logo-icon svg { width: 22px; height: 22px; stroke: #fff; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.logo-text { font-size: 18px; font-weight: 700; color: var(--text); letter-spacing: -.3px; }
.logo-text span { color: var(--green); }

h1 { font-size: 22px; font-weight: 700; color: var(--text); margin-bottom: 4px; }
.subtitle { color: var(--text-muted); font-size: 14px; margin-bottom: 28px; }

.field { margin-bottom: 16px; }
label { display: block; font-size: 13px; font-weight: 500; color: var(--text); margin-bottom: 6px; }
input[type=email], input[type=password] {
  width: 100%;
  padding: 10px 14px;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  font-size: 14px;
  color: var(--text);
  background: #fff;
  transition: border-color .15s;
  outline: none;
}
input:focus { border-color: var(--green); }

.error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #dc2626;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  margin-bottom: 16px;
}

.btn {
  width: 100%;
  padding: 11px;
  background: var(--green);
  color: #fff;
  border: none;
  border-radius: var(--radius);
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: background .15s;
  margin-top: 8px;
}
.btn:hover { background: #155e38; }

.footer { text-align: center; margin-top: 28px; color: var(--text-muted); font-size: 12px; }
.footer a { color: var(--green); text-decoration: none; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-icon">
      <svg viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
    </div>
    <div class="logo-text">digiMind</div>
  </div>

  <h1>Connexion</h1>
  <p class="subtitle">Tableau de bord analytique pharmaceutique</p>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             placeholder="votre@email.com" required autofocus>
    </div>
    <div class="field">
      <label for="password">Mot de passe</label>
      <input type="password" id="password" name="password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn">Se connecter</button>
  </form>

  <div class="footer">
    Propulsé par <a href="#">Digital Technologies Congo</a>
  </div>
</div>
</body>
</html>
