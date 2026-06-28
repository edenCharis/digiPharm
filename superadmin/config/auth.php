<?php
// ============================================================
//  DigiPharma AI — Superadmin Auth Guard (Digitech)
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Superadmin credentials Digitech
define('SA_USERNAME', 'digitech');
define('SA_PASSWORD_HASH', password_hash('Digitech@2026!', PASSWORD_BCRYPT));

function sa_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        // Utilise la classe Database du projet
        require_once dirname(__DIR__, 2) . '/config/database.php';
        // $pdo est créé automatiquement par database.php
        $pdo = $GLOBALS['pdo'] ?? (new Database())->connect();
    }
    return $pdo;
}

function sa_check_auth(): void {
    if (empty($_SESSION['sa_logged_in']) || $_SESSION['sa_logged_in'] !== true) {
        header('Location: /superadmin/login.php');
        exit;
    }
}

function sa_logout(): void {
    session_destroy();
    header('Location: /superadmin/login.php');
    exit;
}
