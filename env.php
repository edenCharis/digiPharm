
<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u680380822_sgpgaly');
define('DB_PASS', '1kNLlP*6dO?');
define('DB_NAME', 'u680380822_sgpgaly');

define('DB_PORT', 3306);
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'Pharma App');
define('APP_URL', 'https://sgp-galy.com');
define('APP_VERSION', '1.0.0');

// Directory Paths
define('ROOT_PATH', dirname(__FILE__));
define('UPLOADS_PATH', ROOT_PATH . '/uploads');

// Security
define('ENCRYPTION_KEY', 'your-secret-key');
define('SESSION_TIMEOUT', 3600); // 1 hour

// Email Configuration
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@example.com');
define('SMTP_PASS', 'your-password');

// Error Reporting
define('DISPLAY_ERRORS', true);
ini_set('display_errors', DISPLAY_ERRORS);
error_reporting(E_ALL);
?>