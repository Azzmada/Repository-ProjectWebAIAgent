<?php
/**
 * TerbangLebih — Application configuration
 * ------------------------------------------------------------------
 * All environment specific values can be overridden with environment
 * variables so the project works both on XAMPP/Laragon and production.
 */

declare(strict_types=1);

if (defined('TBL_CONFIG_LOADED')) {
    return;
}
define('TBL_CONFIG_LOADED', true);

// ---------------------------------------------------------------
// General
// ---------------------------------------------------------------
define('APP_NAME', 'TerbangLebih');
define('APP_VERSION', '2.0.0');
define('APP_ENV', getenv('APP_ENV') ?: 'local');
define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: '1', FILTER_VALIDATE_BOOLEAN));
define('APP_TIMEZONE', getenv('APP_TIMEZONE') ?: 'Asia/Jakarta');

date_default_timezone_set(APP_TIMEZONE);

define('ROOT_PATH', dirname(__DIR__));
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('MAIL_LOG_PATH', STORAGE_PATH . '/mail');

// ---------------------------------------------------------------
// Database (MySQL / MariaDB)
// ---------------------------------------------------------------
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'TerbangLebih');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '');

// ---------------------------------------------------------------
// URL detection
// ---------------------------------------------------------------
// Detect the public base path of the project (works in a subfolder
// such as http://localhost/terbanglebih/ as well as a virtual host).
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
$appRoot = realpath(ROOT_PATH);
$basePath = '';

if ($docRoot && $appRoot) {
    $docRoot = str_replace('\\', '/', $docRoot);
    $appRoot = str_replace('\\', '/', $appRoot);
    if (str_starts_with($appRoot, $docRoot)) {
        $basePath = substr($appRoot, strlen($docRoot));
    }
}
$basePath = rtrim(str_replace('\\', '/', $basePath), '/');

$appUrl = getenv('APP_URL');
define('BASE_URL', $appUrl !== false && $appUrl !== '' ? rtrim($appUrl, '/') : $basePath);

// Pretty URLs are optional. When enabled, .htaccess maps /pesawat to
// index.php?page=pesawat and page_url() will emit clean links.
define('PRETTY_URLS', filter_var(getenv('APP_PRETTY') ?: '0', FILTER_VALIDATE_BOOLEAN));

// ---------------------------------------------------------------
// Security / session
// ---------------------------------------------------------------
define('SESSION_NAME', 'tbl_session');
define('SESSION_LIFETIME', 60 * 60 * 4); // 4 hours
define('CSRF_KEY', '_csrf_token');

define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_WINDOW_SECONDS', 60);
define('LOGIN_LOCKOUT_SECONDS', 300);

define('BOOKING_EXPIRE_MINUTES', 120);
define('MAX_PASSENGERS', 9);
define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024); // 2 MB

// ---------------------------------------------------------------
// Mail (PHPMailer). Leave MAIL_HOST empty to log e-mails to storage.
// ---------------------------------------------------------------
define('MAIL_HOST', getenv('MAIL_HOST') ?: '');
define('MAIL_PORT', (int) (getenv('MAIL_PORT') ?: 587));
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls'); // tls|ssl|''
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'no-reply@terbanglebih.test');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: APP_NAME);

// ---------------------------------------------------------------
// Error handling / logging
// ---------------------------------------------------------------
if (!is_dir(STORAGE_PATH)) {
    @mkdir(STORAGE_PATH, 0775, true);
}

ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/php-error.log');

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
}
