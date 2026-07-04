<?php
require_once __DIR__ . '/config/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = sa_verify_login($username, $password);
    if ($user) {
        $_SESSION['sa_logged_in']   = true;
        $_SESSION['sa_user']        = $user['username'];
        $_SESSION['sa_user_id']     = $user['id'];
        $_SESSION['sa_display_name']= $user['display_name'] ?? $user['username'];
        $_SESSION['sa_login_at']    = date('Y-m-d H:i:s');
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
<title>SuperAdmin — Digitech Congo</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --green:      #1ea84b;
    --green-dark: #0d6e2f;
    --ink:        #080d09;
    --ink-soft:   rgba(255,255,255,0.5);
    --border:     #dadce0;
    --text:       #202124;
    --muted:      #5f6368;
}

body {
    font-family: 'Inter', -apple-system, sans-serif;
    min-height: 100vh;
    display: flex;
    background: #f8f9fa;
}

/* ── Shell ─────────────────────────────────────── */
.auth-shell {
    display: flex;
    width: 100%;
    min-height: 100vh;
}

/* ── Left dark panel ────────────────────────────── */
.auth-left {
    width: 50%;
    background: var(--ink);
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    padding: 2.5rem;
}

.blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(90px);
    opacity: 0.25;
    animation: blobPulse 10s ease-in-out infinite;
    pointer-events: none;
}
.blob1 {
    width: 380px; height: 380px;
    background: radial-gradient(circle, #1ea84b 0%, transparent 70%);
    top: -100px; left: -80px;
    animation-delay: 0s;
}
.blob2 {
    width: 280px; height: 280px;
    background: radial-gradient(circle, #0d6e2f 0%, transparent 70%);
    bottom: 60px; right: -60px;
    animation-delay: 4s;
}
@keyframes blobPulse {
    0%, 100% { transform: scale(1) translate(0, 0); }
    50%       { transform: scale(1.1) translate(10px, -8px); }
}

.auth-left::after {
    content: '';
    position: absolute;
    inset: 0;
    background-image: radial-gradient(circle, rgba(255,255,255,0.05) 1px, transparent 1px);
    background-size: 24px 24px;
    pointer-events: none;
}

.left-inner {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    height: 100%;
}

/* Brand */
.brand-link {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    width: fit-content;
}
.brand-icon {
    width: 44px; height: 44px;
    background: var(--green);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
}
.brand-names { display: flex; flex-direction: column; }
.brand-name {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 19px;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.4px;
    line-height: 1.1;
}
.brand-by {
    font-size: 10px;
    color: var(--ink-soft);
    font-weight: 500;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    margin-top: 2px;
}

/* Content */
.left-content {
    margin-top: auto;
    margin-bottom: auto;
    padding: 2rem 0;
}
.left-heading {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: clamp(1.75rem, 2.5vw, 2.4rem);
    font-weight: 800;
    color: #fff;
    line-height: 1.2;
    letter-spacing: -0.04em;
    margin-bottom: 1rem;
}
.left-heading em {
    font-style: italic;
    background: linear-gradient(135deg, #1ea84b, #34c363);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.left-sub {
    color: var(--ink-soft);
    font-size: 14.5px;
    line-height: 1.65;
    max-width: 320px;
    margin-bottom: 2.5rem;
}

/* Info pills */
.info-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.info-item {
    display: flex;
    align-items: center;
    gap: 12px;
}
.info-dot {
    width: 34px; height: 34px;
    background: rgba(30, 168, 75, 0.14);
    border: 1px solid rgba(30, 168, 75, 0.28);
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    color: var(--green);
}
.info-dot svg { width: 15px; height: 15px; }
.info-text {
    font-size: 13px;
    color: rgba(255,255,255,0.68);
    font-weight: 500;
    line-height: 1.4;
}

/* Footer */
.left-footer {
    padding-top: 1.5rem;
    border-top: 1px solid rgba(255,255,255,0.07);
}
.left-footer p {
    font-size: 11.5px;
    color: rgba(255,255,255,0.28);
}

/* ── Right white panel ──────────────────────────── */
.auth-right {
    width: 50%;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem 2rem;
}

.form-box {
    width: 100%;
    max-width: 380px;
}

/* Admin badge */
.admin-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(30,168,75,0.08);
    border: 1px solid rgba(30,168,75,0.2);
    border-radius: 100px;
    padding: 4px 12px 4px 8px;
    font-size: 11.5px;
    font-weight: 600;
    color: var(--green);
    letter-spacing: 0.03em;
    margin-bottom: 1.5rem;
}
.admin-badge-dot {
    width: 6px; height: 6px;
    background: var(--green);
    border-radius: 50%;
}

.form-header { margin-bottom: 1.75rem; }
.form-title {
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text);
    letter-spacing: -0.03em;
    margin-bottom: 4px;
}
.form-desc {
    font-size: 13.5px;
    color: var(--muted);
    line-height: 1.5;
}

/* Fields */
.field { margin-bottom: 1rem; }
.field label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 6px;
    letter-spacing: 0.01em;
}
.field input {
    width: 100%;
    padding: 11px 14px;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    color: var(--text);
    background: #fff;
    transition: border-color .15s, box-shadow .15s;
    outline: none;
}
.field input:hover { border-color: #adb5bd; }
.field input:focus {
    border-color: var(--green);
    box-shadow: 0 0 0 3px rgba(30, 168, 75, 0.1);
}
.field input::placeholder { color: #adb5bd; }

/* Error */
.error-msg {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 11px 14px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 10px;
    font-size: 13.5px;
    color: #dc2626;
    margin-bottom: 16px;
}
.error-msg svg { width: 15px; height: 15px; flex-shrink: 0; }

/* Button */
.btn-main {
    width: 100%;
    padding: 12px;
    background: var(--green);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    font-family: 'Inter', sans-serif;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: background .15s, box-shadow .15s;
    margin-top: 8px;
}
.btn-main:hover {
    background: var(--green-dark);
    box-shadow: 0 4px 14px rgba(14, 110, 47, 0.28);
}

/* Back link */
.back-link {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 1.25rem;
    font-size: 13px;
    color: var(--muted);
    text-decoration: none;
    transition: color .15s;
}
.back-link:hover { color: var(--text); }
.back-link svg { width: 13px; height: 13px; }

/* Responsive */
@media (max-width: 860px) {
    .auth-left { display: none; }
    .auth-right { width: 100%; }
}
</style>
</head>
<body>
<div class="auth-shell">

    <!-- Left panel -->
    <div class="auth-left">
        <div class="blob blob1"></div>
        <div class="blob blob2"></div>
        <div class="left-inner">

            <a href="../landing" class="brand-link">
                <div class="brand-icon">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M11 2H9v7H2v2h7v7h2v-7h7V9h-7V2z"/></svg>
                </div>
                <div class="brand-names">
                    <div class="brand-name">digiPharm</div>
                    <div class="brand-by">by Digitech Congo</div>
                </div>
            </a>

            <div class="left-content">
                <h1 class="left-heading">Espace<br><em>SuperAdmin.</em></h1>
                <p class="left-sub">Accès réservé à l'équipe Digitech Congo. Gérez les pharmacies, les abonnements et la configuration de la plateforme.</p>

                <div class="info-list">
                    <div class="info-item">
                        <div class="info-dot">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
                            </svg>
                        </div>
                        <div class="info-text">Gestion des pharmacies & comptes clients</div>
                    </div>
                    <div class="info-item">
                        <div class="info-dot">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8m-4-4v4"/>
                            </svg>
                        </div>
                        <div class="info-text">Supervision de la plateforme & activités</div>
                    </div>
                    <div class="info-item">
                        <div class="info-dot">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                        </div>
                        <div class="info-text">Accès sécurisé · Authentification directe</div>
                    </div>
                </div>
            </div>

            <div class="left-footer">
                <p>© <?= date('Y') ?> Digital Technologies Congo</p>
            </div>

        </div>
    </div>

    <!-- Right panel -->
    <div class="auth-right">
        <div class="form-box">

            <div class="admin-badge">
                <div class="admin-badge-dot"></div>
                Accès Digitech
            </div>

            <div class="form-header">
                <div class="form-title">Connexion Admin</div>
                <div class="form-desc">Identifiants Digitech requis pour accéder au panneau</div>
            </div>

            <?php if ($error): ?>
            <div class="error-msg">
                <svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <div class="field">
                    <label for="username">Identifiant</label>
                    <input type="text" id="username" name="username" placeholder="digitech" required autofocus autocomplete="username">
                </div>
                <div class="field">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn-main">
                    <svg style="width:15px;height:15px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    Se connecter
                </button>
            </form>

            <a href="../landing" class="back-link">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Retour à l'accueil
            </a>

        </div>
    </div>

</div>
</body>
</html>
