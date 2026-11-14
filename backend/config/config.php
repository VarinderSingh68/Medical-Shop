<?php
declare(strict_types=1);

/**
 * Global Application Configuration & Helper Functions
 * 20-Year Architecture Standard: Secure, strict typing, clean paths
 */

// ── Production Security: Suppress error output to end users ──
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);                     // Log all, but show nothing
ini_set('log_errors', '1');
// Log to storage/logs/ — never to the webroot
define('LOG_PATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs');
@mkdir(LOG_PATH, 0755, true);
ini_set('error_log', LOG_PATH . DIRECTORY_SEPARATOR . 'php_errors.log');

// Remove PHP version fingerprint from HTTP headers
header_remove('X-Powered-By');

// Define Root Paths
define('APP_ROOT', dirname(__DIR__, 2));
define('BACKEND_PATH', APP_ROOT . DIRECTORY_SEPARATOR . 'backend');
define('FRONTEND_PATH', APP_ROOT . DIRECTORY_SEPARATOR . 'frontend');
define('STORAGE_PATH', APP_ROOT . DIRECTORY_SEPARATOR . 'storage');
define('ENV_PATH', APP_ROOT . DIRECTORY_SEPARATOR . 'env');

// Configure safe persistent session path on Drive E:
$sessionPath = STORAGE_PATH . DIRECTORY_SEPARATOR . 'sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0777, true);
}
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    if (is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }
}

// Admin session timeout: 2 hours of inactivity
define('ADMIN_SESSION_TIMEOUT', 7200);

// Start Secure Session if not already active and headers not yet sent
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,       // Session cookie: expires when browser closes (admin security)
        'path'     => '/',
        'secure'   => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'    // Lax allows external OAuth redirects (Google/identity providers) while isolating CSRF
    ]);
    session_start();
}

// Autoload Core Classes with Cross-Platform Case Compatibility (Windows & Linux)
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = BACKEND_PATH . DIRECTORY_SEPARATOR;

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $parts = explode('\\', $relativeClass);
    $className = array_pop($parts);
    $subDir = strtolower(implode(DIRECTORY_SEPARATOR, $parts));
    if ($subDir !== '') {
        $subDir .= DIRECTORY_SEPARATOR;
    }

    // Candidates in order of precedence:
    // 1. backend/core/Auth.php (lowercase folder, PascalCase class)
    // 2. backend/core/auth.php (all lowercase)
    // 3. backend/Core/Auth.php (exact case)
    $candidates = [
        $baseDir . $subDir . $className . '.php',
        $baseDir . strtolower(str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)) . '.php',
        $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php',
    ];

    foreach ($candidates as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Helper for dynamic Base URL resolution
function app_url(string $path = ''): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        ? "https://" : "http://";

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    
    // Normalize script name and remove physical document root if present in SCRIPT_NAME (e.g. FastCGI)
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($docRoot !== '' && str_starts_with($scriptName, $docRoot)) {
        $scriptName = substr($scriptName, strlen($docRoot));
    }
    
    $dirName = str_replace('\\', '/', dirname($scriptName));
    
    // Strip trailing admin, backend, core, frontend, auth, or api subdirectories if present
    $baseDir = preg_replace('#/(backend(/.*)?|frontend(/.*)?|admin(/.*)?|auth(/.*)?|api(/.*)?)$#i', '', $dirName);
    $baseDir = trim($baseDir, '/.');

    $cleanPath = ltrim($path, '/');
    $url = $protocol . $host;
    if ($baseDir !== '') {
        $url .= '/' . $baseDir;
    }
    if ($cleanPath !== '') {
        $url .= '/' . $cleanPath;
    }
    return $url;
}

// Price formatter helper
function format_price(float|int|string $amount): string {
    return '₹' . number_format((float)$amount, 2);
}

// Date formatter helper
function format_date(string $date, string $format = 'd M Y, h:i A'): string {
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : 'N/A';
}
