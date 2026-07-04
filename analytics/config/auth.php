<?php
/**
 * Analytics dashboard authentication.
 * Session is namespaced separately from the ERP (ai_sess_*).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_name('digipharmai_session');
    session_start();
}

require_once __DIR__ . '/db.php';

function ai_check_auth(): void
{
    if (empty($_SESSION['ai_user_id'])) {
        header('Location: /analytics/login.php');
        exit;
    }
}

function ai_verify_login(string $email, string $password): array|false
{
    $db = analytics_db();
    $st = $db->prepare(
        "SELECT u.*, p.name AS pharmacy_name, p.api_key, p.slug AS pharmacy_slug
         FROM ai_users u
         JOIN ai_pharmacies p ON p.id = u.pharmacy_id
         WHERE u.email = ? AND u.is_active = 1 LIMIT 1"
    );
    $st->execute([trim(strtolower($email))]);
    $user = $st->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $db->prepare("UPDATE ai_users SET last_login = NOW() WHERE id = ?")
           ->execute([$user['id']]);
        return $user;
    }
    return false;
}

function ai_logout(): void
{
    $_SESSION = [];
    session_destroy();
    header('Location: /analytics/login.php');
    exit;
}

function ai_user(): array
{
    return [
        'id'            => $_SESSION['ai_user_id']       ?? 0,
        'display_name'  => $_SESSION['ai_display_name']  ?? 'Utilisateur',
        'email'         => $_SESSION['ai_email']         ?? '',
        'role'          => $_SESSION['ai_role']          ?? 'viewer',
        'pharmacy_id'   => $_SESSION['ai_pharmacy_id']   ?? 0,
        'pharmacy_name' => $_SESSION['ai_pharmacy_name'] ?? '',
        'api_key'       => $_SESSION['ai_api_key']       ?? '',
    ];
}
