<?php
require_once __DIR__ . '/config/auth.php';
ai_check_auth();
header('Content-Type: application/json');

$user   = ai_user();
$action = $_POST['action'] ?? '';
$db     = analytics_db();

function ok(string $msg, array $extra = []): never
{
    echo json_encode(['ok' => true, 'message' => $msg] + $extra);
    exit;
}
function fail(string $err): never
{
    echo json_encode(['ok' => false, 'error' => $err]);
    exit;
}

switch ($action) {

    // ── Display name ─────────────────────────────────────────────────────────
    case 'profile':
        $name = trim($_POST['display_name'] ?? '');
        if ($name === '') fail('Le nom est requis.');
        if (mb_strlen($name) > 120) fail('Nom trop long (max 120 caractères).');
        $db->prepare("UPDATE ai_users SET display_name=? WHERE id=?")
           ->execute([$name, $user['id']]);
        $_SESSION['ai_display_name'] = $name;
        ok('Profil mis à jour.');

    // ── Send OTP to new login email ───────────────────────────────────────────
    case 'otp_send':
        $email = trim(strtolower($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Adresse email invalide.');

        $st = $db->prepare("SELECT id FROM ai_users WHERE email=? AND id!=?");
        $st->execute([$email, $user['id']]);
        if ($st->fetch()) fail('Cette adresse est déjà utilisée par un autre compte.');

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['ai_otp_hash']    = password_hash($otp, PASSWORD_DEFAULT);
        $_SESSION['ai_otp_pending'] = $email;
        $_SESSION['ai_otp_expires'] = time() + 600;
        $_SESSION['ai_otp_type']    = 'login_email';

        require_once __DIR__ . '/config/mailer.php';
        $sent = ai_send_mail(
            $email,
            'Votre code de vérification digiMind',
            ai_otp_email_html($otp, $user['display_name'],
                'Vous avez demandé à modifier votre adresse email de connexion. Entrez le code ci-dessous pour confirmer.')
        );
        if (!$sent) fail('Impossible d\'envoyer l\'email. Vérifiez l\'adresse saisie.');
        ok('Code envoyé à ' . $email . '. Valable 10 minutes.');

    // ── Verify OTP and apply new login email ──────────────────────────────────
    case 'otp_verify':
        $code = trim($_POST['code'] ?? '');
        if (empty($_SESSION['ai_otp_hash']) || ($_SESSION['ai_otp_type'] ?? '') !== 'login_email')
            fail('Aucun code en attente. Cliquez d\'abord sur « Envoyer le code ».');
        if (time() > ($_SESSION['ai_otp_expires'] ?? 0))
            fail('Code expiré. Veuillez en demander un nouveau.');
        if (!password_verify($code, $_SESSION['ai_otp_hash']))
            fail('Code incorrect. Vérifiez et réessayez.');

        $email = $_SESSION['ai_otp_pending'];
        $st = $db->prepare("SELECT id FROM ai_users WHERE email=? AND id!=?");
        $st->execute([$email, $user['id']]);
        if ($st->fetch()) fail('Cette adresse a été prise entre-temps.');

        $db->prepare("UPDATE ai_users SET email=? WHERE id=?")
           ->execute([$email, $user['id']]);
        $_SESSION['ai_email'] = $email;
        unset($_SESSION['ai_otp_hash'], $_SESSION['ai_otp_pending'],
              $_SESSION['ai_otp_expires'], $_SESSION['ai_otp_type']);
        ok('Email de connexion mis à jour.', ['new_email' => $email]);

    // ── OTP notification email (no verification required) ────────────────────
    case 'otp_email':
        $email = trim(strtolower($_POST['otp_email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
            fail('Adresse email invalide.');
        $db->prepare("UPDATE ai_users SET otp_email=? WHERE id=?")
           ->execute([$email ?: null, $user['id']]);
        ok($email ? 'Email OTP enregistré.' : 'Email OTP supprimé.');

    // ── Change password ───────────────────────────────────────────────────────
    case 'password':
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!$current || !$new || !$confirm) fail('Tous les champs sont requis.');
        if ($new !== $confirm) fail('Les nouveaux mots de passe ne correspondent pas.');
        if (mb_strlen($new) < 8) fail('Le mot de passe doit contenir au moins 8 caractères.');

        $st = $db->prepare("SELECT password FROM ai_users WHERE id=?");
        $st->execute([$user['id']]);
        $row = $st->fetch();
        if (!$row || !password_verify($current, $row['password']))
            fail('Mot de passe actuel incorrect.');

        $db->prepare("UPDATE ai_users SET password=? WHERE id=?")
           ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        ok('Mot de passe mis à jour.');

    // ── Pharmacy name (admin only) ────────────────────────────────────────────
    case 'pharmacy':
        if ($user['role'] !== 'admin') fail('Accès refusé.');
        $name = trim($_POST['pharmacy_name'] ?? '');
        if ($name === '') fail('Le nom est requis.');
        if (mb_strlen($name) > 150) fail('Nom trop long (max 150 caractères).');
        $db->prepare("UPDATE ai_pharmacies SET name=? WHERE id=?")
           ->execute([$name, $user['pharmacy_id']]);
        $_SESSION['ai_pharmacy_name'] = $name;
        ok('Nom de la pharmacie mis à jour.', ['new_name' => $name]);

    default:
        fail('Action inconnue.');
}
