<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>digiPharm — Connexion</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,600;0,800;1,700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green:      #1ea84b;
            --green-dark: #0d6e2f;
            --ink:        #080d09;
            --ink-soft:   rgba(255,255,255,0.55);
            --border:     #dadce0;
            --text:       #202124;
            --muted:      #5f6368;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
        }

        /* ── Shell ───────────────────────────────────────────── */
        .auth-shell {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        /* ── Left dark panel ─────────────────────────────────── */
        .auth-left {
            width: 50%;
            background: var(--ink);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            padding: 2.5rem;
        }

        /* Animated blobs */
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.35;
            animation: blobPulse 8s ease-in-out infinite;
        }
        .blob1 {
            width: 420px; height: 420px;
            background: radial-gradient(circle, #1ea84b 0%, transparent 70%);
            top: -120px; left: -100px;
            animation-delay: 0s;
        }
        .blob2 {
            width: 340px; height: 340px;
            background: radial-gradient(circle, #0d6e2f 0%, transparent 70%);
            bottom: 80px; right: -80px;
            animation-delay: 3s;
        }
        @keyframes blobPulse {
            0%, 100% { transform: scale(1) translate(0, 0); }
            50%       { transform: scale(1.08) translate(12px, -10px); }
        }

        /* Dot grid texture */
        .auth-left::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: radial-gradient(circle, rgba(255,255,255,0.06) 1px, transparent 1px);
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
            font-size: 22px;
            font-weight: 900;
        }
        .brand-name {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 20px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.4px;
        }
        .brand-by {
            font-size: 10.5px;
            color: var(--ink-soft);
            font-weight: 500;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            margin-top: 2px;
        }

        /* Main content */
        .left-content {
            margin-top: auto;
            margin-bottom: auto;
            padding: 2rem 0;
        }
        .left-heading {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: clamp(2rem, 3vw, 2.75rem);
            font-weight: 800;
            color: #fff;
            line-height: 1.15;
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
            font-size: 15px;
            line-height: 1.6;
            max-width: 340px;
            margin-bottom: 2.5rem;
        }

        /* Feature list */
        .feature-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .feature-dot {
            width: 36px; height: 36px;
            background: rgba(30, 168, 75, 0.15);
            border: 1px solid rgba(30, 168, 75, 0.3);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: var(--green);
        }
        .feature-dot svg { width: 17px; height: 17px; }
        .feature-text {
            font-size: 13.5px;
            color: rgba(255,255,255,0.75);
            font-weight: 500;
            line-height: 1.4;
        }

        /* Left footer */
        .left-footer {
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .register-link {
            color: rgba(255,255,255,0.5);
            font-size: 13px;
            text-decoration: none;
            transition: color .2s;
        }
        .register-link:hover { color: #fff; }
        .register-link span { color: var(--green); font-weight: 600; }

        /* ── Right white panel ───────────────────────────────── */
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
            max-width: 400px;
        }

        .form-header {
            margin-bottom: 2rem;
        }
        .form-title {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 1.625rem;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.03em;
            margin-bottom: 4px;
        }
        .form-desc {
            font-size: 14px;
            color: var(--muted);
            line-height: 1.5;
        }

        /* Form fields */
        .field { margin-bottom: 1rem; }
        .field label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 6px;
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
            box-shadow: 0 0 0 3px rgba(30, 168, 75, 0.12);
        }
        .field input::placeholder { color: #adb5bd; }

        /* Password wrapper */
        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 44px; }
        .pw-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            padding: 4px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            transition: color .15s;
        }
        .pw-toggle:hover { color: var(--muted); }
        .pw-toggle svg { width: 18px; height: 18px; }

        /* OTP input */
        .otp-input {
            text-align: center;
            font-size: 2rem !important;
            font-weight: 700 !important;
            letter-spacing: 0.5rem !important;
            padding: 14px !important;
            font-family: 'Inter', monospace !important;
        }

        /* Buttons */
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
            margin-top: 6px;
        }
        .btn-main:hover:not(:disabled) {
            background: var(--green-dark);
            box-shadow: 0 4px 12px rgba(14, 110, 47, 0.3);
        }
        .btn-main:disabled { background: #d1d5db; cursor: not-allowed; }

        .btn-secondary {
            width: 100%;
            padding: 11px;
            background: #f8f9fa;
            color: var(--muted);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 500;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background .15s, border-color .15s;
            margin-top: 8px;
        }
        .btn-secondary:hover:not(:disabled) { background: #f1f3f5; border-color: #adb5bd; }
        .btn-secondary:disabled { opacity: 0.55; cursor: not-allowed; }

        /* Messages */
        .msg-area { margin-bottom: 16px; }
        .msg {
            padding: 11px 14px;
            border-radius: 10px;
            font-size: 13.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .msg svg { width: 16px; height: 16px; flex-shrink: 0; }
        .msg.error   { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
        .msg.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #0d652d; }
        .msg.info    { background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; }

        /* Countdown */
        .countdown {
            font-size: 12px;
            color: var(--muted);
            text-align: center;
            margin-top: 10px;
        }

        /* Helpers */
        .hidden { display: none !important; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideRight {
            from { opacity: 0; transform: translateX(16px); }
            to   { opacity: 1; transform: translateX(0); }
        }
        .fade-in      { animation: fadeIn .25s ease-out; }
        .slide-in-right { animation: slideRight .25s ease-out; }

        .loading-spinner {
            width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,0.35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Responsive — stack on small screens */
        @media (max-width: 860px) {
            .auth-left { display: none; }
            .auth-right { width: 100%; }
        }
    </style>
</head>
<body>
<div class="auth-shell">

    <!-- ── Left panel ──────────────────────────────────────── -->
    <div class="auth-left">
        <div class="blob blob1"></div>
        <div class="blob blob2"></div>
        <div class="left-inner">

            <a href="landing" class="brand-link">
                <div class="brand-icon">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M11 2H9v7H2v2h7v7h2v-7h7V9h-7V2z"/></svg>
                </div>
                <div>
                    <div class="brand-name">digiPharm</div>
                    <div class="brand-by">by Digitech Congo</div>
                </div>
            </a>

            <div class="left-content">
                <h1 class="left-heading">Bon retour sur<br><em>digiPharm.</em></h1>
                <p class="left-sub">Connectez-vous pour accéder à votre tableau de bord et gérer votre pharmacie en temps réel.</p>

                <div class="feature-list">
                    <div class="feature-item">
                        <div class="feature-dot">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                            </svg>
                        </div>
                        <div class="feature-text">Ventes & chiffre d'affaires en temps réel</div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-dot">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/>
                            </svg>
                        </div>
                        <div class="feature-text">Gestion de stock intelligente avec alertes</div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-dot">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                <path d="M9 12l2 2 4-4"/>
                            </svg>
                        </div>
                        <div class="feature-text">Connexion sécurisée par code OTP par email</div>
                    </div>
                </div>
            </div>

            <div class="left-footer">
                <a href="register" class="register-link">
                    Pas encore client ? <span>Créer un compte gratuit →</span>
                </a>
            </div>

        </div>
    </div>

    <!-- ── Right panel ─────────────────────────────────────── -->
    <div class="auth-right">
        <div class="form-box">

            <div class="form-header">
                <div class="form-title" id="cardTitle">Connexion</div>
                <div class="form-desc" id="cardDescription">Entrez vos identifiants pour accéder à votre espace</div>
            </div>

            <div class="msg-area" id="messageContainer"></div>

            <!-- Step 1: Login -->
            <form id="loginForm">
                <div class="field">
                    <label for="username">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" placeholder="Votre identifiant" autocomplete="username" required>
                </div>

                <div class="field">
                    <label for="password">Mot de passe</label>
                    <div class="pw-wrap">
                        <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" class="password-input" required>
                        <button type="button" class="pw-toggle" onclick="togglePassword()" aria-label="Afficher le mot de passe">
                            <svg id="eyeIcon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <svg id="eyeOffIcon" class="hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-main" id="loginButton">
                    <svg style="width:16px;height:16px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    <span id="loginButtonText">Se connecter</span>
                </button>
            </form>

            <!-- Step 2: OTP -->
            <form id="otpForm" class="hidden">
                <div class="field">
                    <label for="otp">Code de vérification</label>
                    <input type="text" id="otp" name="otp" class="otp-input" placeholder="000000" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" inputmode="numeric" required>
                    <div style="font-size:12px;color:#9ca3af;margin-top:6px;">Code à 6 chiffres envoyé à votre email</div>
                </div>

                <button type="submit" class="btn-main" id="otpButton">
                    <svg style="width:16px;height:16px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span id="otpButtonText">Vérifier le code</span>
                </button>

                <button type="button" class="btn-secondary" id="resendButton">
                    <svg style="width:15px;height:15px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span id="resendButtonText">Renvoyer le code</span>
                </button>

                <button type="button" class="btn-secondary" id="backButton">
                    <svg style="width:15px;height:15px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Retour
                </button>

                <div id="countdown" class="countdown hidden"></div>
            </form>

        </div>
    </div>

</div>

<script>
    let currentStep = 'login';
    let countdownTimer = null;
    let resendCountdown = 60;

    const loginForm   = document.getElementById('loginForm');
    const otpForm     = document.getElementById('otpForm');
    const messageContainer = document.getElementById('messageContainer');
    const cardTitle   = document.getElementById('cardTitle');
    const cardDescription = document.getElementById('cardDescription');

    const loginButton = document.getElementById('loginButton');
    const otpButton   = document.getElementById('otpButton');
    const resendButton = document.getElementById('resendButton');
    const backButton  = document.getElementById('backButton');

    const loginButtonText  = document.getElementById('loginButtonText');
    const otpButtonText    = document.getElementById('otpButtonText');
    const resendButtonText = document.getElementById('resendButtonText');

    function togglePassword() {
        const input = document.getElementById('password');
        const eyeOn  = document.getElementById('eyeIcon');
        const eyeOff = document.getElementById('eyeOffIcon');
        if (input.type === 'password') {
            input.type = 'text';
            eyeOn.classList.add('hidden');
            eyeOff.classList.remove('hidden');
        } else {
            input.type = 'password';
            eyeOn.classList.remove('hidden');
            eyeOff.classList.add('hidden');
        }
    }

    function showMessage(message, type = 'info') {
        const icons = {
            error:   '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
            success: '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
            info:    '<svg fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
        };
        const div = document.createElement('div');
        div.className = `msg ${type} fade-in`;
        div.innerHTML = (icons[type] || icons.info) + `<span>${message}</span>`;
        messageContainer.innerHTML = '';
        messageContainer.appendChild(div);
    }

    function clearMessages() { messageContainer.innerHTML = ''; }

    function switchToOTPStep() {
        currentStep = 'otp';
        loginForm.classList.add('hidden');
        otpForm.classList.remove('hidden');
        otpForm.classList.add('slide-in-right');
        cardTitle.textContent = 'Vérification OTP';
        cardDescription.textContent = 'Un code de vérification a été envoyé à votre email';
        document.getElementById('otp').focus();
        startResendCountdown();
    }

    function switchToLoginStep() {
        currentStep = 'login';
        otpForm.classList.add('hidden');
        loginForm.classList.remove('hidden');
        loginForm.classList.add('slide-in-right');
        cardTitle.textContent = 'Connexion';
        cardDescription.textContent = 'Entrez vos identifiants pour accéder à votre espace';
        clearCountdown();
        clearMessages();
    }

    function startResendCountdown() {
        resendCountdown = 60;
        resendButton.disabled = true;
        const cntDiv = document.getElementById('countdown');
        cntDiv.classList.remove('hidden');
        countdownTimer = setInterval(() => {
            resendCountdown--;
            cntDiv.textContent = `Nouveau code disponible dans ${resendCountdown}s`;
            resendButtonText.textContent = `Renvoyer le code (${resendCountdown}s)`;
            if (resendCountdown <= 0) {
                clearCountdown();
                resendButton.disabled = false;
                resendButtonText.textContent = 'Renvoyer le code';
                cntDiv.textContent = 'Vous pouvez demander un nouveau code';
            }
        }, 1000);
    }

    function clearCountdown() {
        if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
        document.getElementById('countdown').classList.add('hidden');
    }

    function setButtonLoading(button, textEl, loading) {
        if (loading) {
            button.disabled = true;
            textEl.innerHTML = '<div class="loading-spinner"></div>';
        } else {
            button.disabled = false;
            if (button === loginButton)  textEl.textContent = 'Se connecter';
            if (button === otpButton)    textEl.textContent = 'Vérifier le code';
            if (button === resendButton) textEl.textContent = 'Renvoyer le code';
        }
    }

    async function makeRequest(action, data) {
        try {
            const form = new FormData();
            form.append('action', action);
            for (const k in data) form.append(k, data[k]);
            const r = await fetch('config/auth.php', { method: 'POST', body: form });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return await r.json();
        } catch (e) {
            return { success: false, message: 'Erreur réseau. Réessayez.' };
        }
    }

    loginForm.addEventListener('submit', async e => {
        e.preventDefault();
        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value;
        if (!username || !password) { showMessage('Veuillez remplir tous les champs', 'error'); return; }
        clearMessages();
        setButtonLoading(loginButton, loginButtonText, true);
        const r = await makeRequest('login', { username, password });
        setButtonLoading(loginButton, loginButtonText, false);
        if (r.success) {
            if (r.redirect) { showMessage(r.message, 'success'); setTimeout(() => window.location.href = r.redirect, 800); return; }
            showMessage(r.message, 'success');
            setTimeout(switchToOTPStep, 1200);
        } else {
            showMessage(r.message, 'error');
        }
    });

    otpForm.addEventListener('submit', async e => {
        e.preventDefault();
        const otp = document.getElementById('otp').value.trim();
        if (!otp || otp.length !== 6) { showMessage('Entrez le code à 6 chiffres', 'error'); return; }
        clearMessages();
        setButtonLoading(otpButton, otpButtonText, true);
        const r = await makeRequest('verify_otp', { otp });
        setButtonLoading(otpButton, otpButtonText, false);
        if (r.success) {
            showMessage(r.message, 'success');
            setTimeout(() => window.location.href = r.redirect, 800);
        } else {
            showMessage(r.message, 'error');
            document.getElementById('otp').value = '';
            document.getElementById('otp').focus();
        }
    });

    resendButton.addEventListener('click', async () => {
        clearMessages();
        setButtonLoading(resendButton, resendButtonText, true);
        const r = await makeRequest('resend_otp', {});
        setButtonLoading(resendButton, resendButtonText, false);
        if (r.success) { showMessage(r.message, 'success'); startResendCountdown(); }
        else showMessage(r.message, 'error');
    });

    backButton.addEventListener('click', switchToLoginStep);

    document.getElementById('otp').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length === 6) setTimeout(() => otpForm.dispatchEvent(new Event('submit')), 300);
    });

    document.querySelectorAll('.field input').forEach(inp =>
        inp.addEventListener('input', clearMessages)
    );

    document.addEventListener('keydown', e => {
        if (e.ctrlKey && e.key === 'h' && currentStep === 'login') { e.preventDefault(); togglePassword(); }
        if (e.key === 'Escape' && currentStep === 'otp') switchToLoginStep();
    });

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('username').focus();
    });
</script>
</body>
</html>
