<?php
/**
 * env.php
 * Charge les variables d'environnement pour l'application
 */

// Optionnel : si tu veux lire un vrai fichier .env plus tard, tu peux utiliser vlucas/phpdotenv
// require_once __DIR__ . '/../vendor/autoload.php';
// $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
// $dotenv->load();

// Database Configuration
define('DB_CONNECTION', 'mysql');
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'u680380822_sgpgaly');
define('DB_USER', 'u680380822_sgpgaly');
define('DB_PASS', '1kNLlP*6dO?');
define('DB_CHARSET', 'utf8mb4');

// App Configuration
define('APP_NAME', 'PharmaApp');
define('APP_ENV', 'prod');
define('APP_DEBUG', true);
define('APP_URL', 'https://sgp-galy.com');

// OTP Configuration
define('OTP_LENGTH', 6);
define('OTP_EXPIRY_MINUTES', 5);
define('OTP_DEBUG_MODE', true);
define('OTP_EMAIL_SUBJECT', 'Code de vérification - PharmaSys');

// Email Configuration
define('MAIL_ENABLED', true);
define('MAIL_DEBUG', false);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_USERNAME', 'system@sgp-galy.com');
define('SMTP_PASSWORD', 'F:6SEc>3');

// Email From Settings
define('MAIL_FROM_ADDRESS', 'system@sgp-galy.com');
define('MAIL_FROM_NAME', 'PharmaSys Support');

// Support Contact
define('SUPPORT_EMAIL', 'support@pharmasys.com');

// User Role Redirects
define('SELLER_REDIRECT', 'seller/index.php');
define('CASHIER_REDIRECT', 'cashier/index.php');
define('ADMIN_REDIRECT', 'admin/index.php');
define('STOCK_MANAGER_REDIRECT', 'stock-manager/index.php');
define('LOGIN_REDIRECT', '../logout.php');

// Session Settings
define('SESSION_LIFETIME', 3600);
define('SESSION_SECURE', false);
