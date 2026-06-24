<?php
// ============================================================
//  DigiPharma AI — Superadmin Auth Guard (Digitech)
// ============================================================

session_start();

// Superadmin credentials — à mettre dans des variables d'environnement en prod
define('SA_USERNAME', 'digitech');
define('SA_PASSWORD_HASH', password_hash('Digitech@2026!', PASSWORD_BCRYPT));

// DB connection (réutilise env.php du projet)
require_once dirname(__DIR__, 2) . '/env.php';

function sa_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

function sa_check_auth(): void {
    if (empty($_SESSION['sa_logged_in']) || $_SESSION['sa_logged_in'] !== true) {
        header('Location: ' . sa_base() . '/login.php');
        exit;
    }
}

function sa_base(): string {
    return rtrim(dirname($_SERVER['SCRIPT_NAME']), '/pharmacies');
}

function sa_logout(): void {
    session_destroy();
    header('Location: ' . sa_base() . '/login.php');
    exit;
}
