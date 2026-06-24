<?php
require_once __DIR__ . '/config/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === SA_USERNAME && password_verify($password, SA_PASSWORD_HASH)) {
        $_SESSION['sa_logged_in'] = true;
        $_SESSION['sa_user']      = $username;
        $_SESSION['sa_login_at']  = date('Y-m-d H:i:s');
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Identifiants incorrects.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>DigiPharma AI — Digitech SuperAdmin</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #0D7C66 0%, #1A1A2E 100%);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}
.card {
    background: white;
    border-radius: 12px;
    padding: 2.5rem;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 25px 50px rgba(0,0,0,0.3);
}
.logo { text-align: center; margin-bottom: 1.5rem; }
.logo-icon {
    width: 60px; height: 60px;
    background: #0D7C66;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.75rem;
}
.logo-icon svg { width: 32px; height: 32px; fill: white; }
h1 { font-size: 1.4rem; color: #1A1A2E; font-weight: 700; }
.subtitle { font-size: 0.8rem; color: #6B7280; margin-top: 0.25rem; }
.badge {
    display: inline-block;
    background: #E8F5F2;
    color: #0D7C66;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.2rem 0.6rem;
    border-radius: 999px;
    margin-top: 0.5rem;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}
.form-group { margin-bottom: 1rem; }
label { display: block; font-size: 0.85rem; font-weight: 500; color: #374151; margin-bottom: 0.4rem; }
input {
    width: 100%; padding: 0.65rem 0.9rem;
    border: 1.5px solid #E5E7EB;
    border-radius: 8px;
    font-size: 0.9rem;
    transition: border-color 0.15s;
}
input:focus { outline: none; border-color: #0D7C66; box-shadow: 0 0 0 3px rgba(13,124,102,0.1); }
.btn {
    width: 100%; padding: 0.75rem;
    background: #0D7C66; color: white;
    border: none; border-radius: 8px;
    font-size: 0.95rem; font-weight: 600;
    cursor: pointer; margin-top: 0.5rem;
    transition: background 0.15s;
}
.btn:hover { background: #0a6354; }
.error {
    background: #FEF2F2; border: 1px solid #FECACA;
    color: #DC2626; padding: 0.75rem; border-radius: 8px;
    font-size: 0.85rem; margin-bottom: 1rem;
}
.footer { text-align: center; margin-top: 1.5rem; font-size: 0.75rem; color: #9CA3AF; }
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="logo-icon">
            <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
        </div>
        <h1>DigiPharma AI</h1>
        <p class="subtitle">Panneau d'administration</p>
        <span class="badge">Digitech SuperAdmin</span>
    </div>

    <?php if ($error): ?>
        <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Identifiant</label>
            <input type="text" name="username" placeholder="digitech" required autofocus>
        </div>
        <div class="form-group">
            <label>Mot de passe</label>
            <input type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn">Se connecter</button>
    </form>

    <div class="footer">© <?= date('Y') ?> Digitech — DigiPharma AI Platform</div>
</div>
</body>
</html>
