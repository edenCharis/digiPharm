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
        require_once dirname(__DIR__, 2) . '/config/database.php';
        $pdo = $GLOBALS['pdo'] ?? (new Database())->connect();

        // Ensure the pharmacies table exists with the full schema.
        // MODIFY COLUMN is a no-op if the ENUM already matches, so this is safe to run every time.
        $pdo->exec("CREATE TABLE IF NOT EXISTS pharmacies (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            name             VARCHAR(255) NOT NULL,
            responsible_name VARCHAR(255),
            email            VARCHAR(255),
            phone            VARCHAR(50),
            address          VARCHAR(255),
            city             VARCHAR(100),
            plan             ENUM('basic','pro','enterprise') DEFAULT 'basic',
            status           ENUM('active','trial','suspended') DEFAULT 'trial',
            trial_ends_at    DATETIME NULL,
            created_at       DATETIME DEFAULT NOW()
        )");
        // Expand ENUM in case an older version of the table was created without 'trial' or 'enterprise'
        try {
            $pdo->exec("ALTER TABLE pharmacies
                MODIFY COLUMN plan   ENUM('basic','pro','enterprise') DEFAULT 'basic',
                MODIFY COLUMN status ENUM('active','trial','suspended') DEFAULT 'trial',
                ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL AFTER phone,
                ADD COLUMN IF NOT EXISTS responsible_name VARCHAR(255) NULL AFTER name"
            );
        } catch (PDOException $e) { /* ADD COLUMN IF NOT EXISTS may not be supported on old MySQL — ignore */ }
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
