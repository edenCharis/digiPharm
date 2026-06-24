<?php

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_reporting(E_ALL);
if ($_SERVER['HTTP_HOST'] === 'localhost') {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'pharmasys');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'u680380822_digiPharm');
    define('DB_USER', 'u680380822_digiPharrm');
    define('DB_PASS', 'K4Y:SY>Nt>');
}

// Commun aux deux environnements
define('DB_CONNECTION', 'mysql');
define('DB_PORT', 3306);
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'PharmaApp');
define('APP_ENV', 'prod');
define('APP_DEBUG', true);
define('APP_URL', 'https://sgp-galy.com');

define('OTP_LENGTH', 6);
define('OTP_EXPIRY_MINUTES', 5);
define('OTP_DEBUG_MODE', true);
define('OTP_EMAIL_SUBJECT', 'Code de vérification - PharmaSys');

define('MAIL_ENABLED', true);
define('MAIL_DEBUG', false);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
define('SMTP_USERNAME', 'system@sgp-galy.com');
define('SMTP_PASSWORD', 'F:6SEc>3');

define('MAIL_FROM_ADDRESS', 'system@sgp-galy.com');
define('MAIL_FROM_NAME', 'PharmaSys Support');
define('SUPPORT_EMAIL', 'support@pharmasys.com');

define('SELLER_REDIRECT', 'seller/index.php');
define('CASHIER_REDIRECT', 'cashier/index.php');
define('ADMIN_REDIRECT', 'admin/index.php');
define('STOCK_MANAGER_REDIRECT', 'stock-manager/index.php');
define('LOGIN_REDIRECT', '../logout.php');

define('SESSION_LIFETIME', 3600);
define('SESSION_SECURE', false);