<?php
// ============================================================
//  digiPharm — Superadmin Auth Guard (Digitech)
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function sa_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        require_once dirname(__DIR__, 2) . '/config/database.php';
        $pdo = $GLOBALS['pdo'] ?? (new Database())->connect();

        // Superadmin users table
        $pdo->exec("CREATE TABLE IF NOT EXISTS superadmin_users (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            username     VARCHAR(80) NOT NULL UNIQUE,
            password     VARCHAR(255) NOT NULL,
            display_name VARCHAR(120) DEFAULT NULL,
            is_owner     TINYINT(1) NOT NULL DEFAULT 0,
            created_at   DATETIME DEFAULT NOW()
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // Seed initial owner account if none exists
        $count = (int) $pdo->query("SELECT COUNT(*) FROM superadmin_users")->fetchColumn();
        if ($count === 0) {
            $stmt = $pdo->prepare("INSERT INTO superadmin_users (username, password, display_name, is_owner) VALUES (?, ?, ?, 1)");
            $stmt->execute(['digitech', password_hash('Digitech@2026!', PASSWORD_BCRYPT), 'Digitech Congo']);
        }

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

function sa_verify_login(string $username, string $password): array|false {
    $db   = sa_db();
    $stmt = $db->prepare("SELECT * FROM superadmin_users WHERE username = ? LIMIT 1");
    $stmt->execute([trim($username)]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    return false;
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
