<?php
declare(strict_types=1);

/**
 * PHP Built-in Web Server Router
 * Handles static asset serving, MIME types, and seamless routing to frontend/
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// ============================================================
// SECURITY BLOCK: Deny access to sensitive paths & file types
// ============================================================
$blockedPatterns = [
    '/env\//i',           // .env files
    '/\.env/i',           // .env files directly
    '/\.sqlite/i',        // SQLite database files
    '/\.sqlite3/i',       // SQLite3 files
    '/storage\/sessions/i', // Session files
    '/storage\/backups/i',  // Backup files (served via authenticated PHP only)
    '/storage\/prescriptions/i', // Prescription images (served via authenticated PHP only)
    '/backend\/config\//i',  // Config directory
    '/backend\/core\//i',    // Core PHP classes
    '/backend\/models\//i',  // Model PHP classes
    '/vendor\//i',           // Composer vendor (if ever added)
    '/\.git\//i',            // Git directory
    '/\.php~/i',             // PHP backup files
    '/\.bak$/i',             // Backup files
    '/\.log$/i',             // Log files
];

foreach ($blockedPatterns as $pattern) {
    if (preg_match($pattern, $uri)) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo '403 Forbidden';
        return true;
    }
}


// Normalize URI
$filePath = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $uri);

// 1. Static Asset Handling (CSS, JS, Images, Fonts)
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css'   => 'text/css; charset=UTF-8',
        'js'    => 'application/javascript; charset=UTF-8',
        'json'  => 'application/json',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'webp'  => 'image/webp',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',
        'pdf'   => 'application/pdf',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
    ];

    if (isset($mimeTypes[$extension])) {
        header('Content-Type: ' . $mimeTypes[$extension]);
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        return true;
    }
    return false; // Built-in server serves php or other files
}

// 2. Admin URL Shortcuts (e.g. /admin, /admin/, /admin/login)
$normalizedUri = rtrim($uri, '/');
if ($normalizedUri === '/admin') {
    require_once __DIR__ . '/admin/index.php';
    return true;
}
if ($normalizedUri === '/admin/login' || $uri === '/admin/login.php') {
    require_once __DIR__ . '/backend/config/config.php';
    header('Location: ' . app_url('backend/admin/login.php'));
    exit;
}

// Check if request targets an admin file through /admin/* (e.g. /admin/orders.php)
if (str_starts_with($uri, '/admin/')) {
    $adminFile = substr($uri, 7);
    $targetAdminPath = __DIR__ . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $adminFile);
    if (file_exists($targetAdminPath) && !is_dir($targetAdminPath)) {
        require_once __DIR__ . '/backend/config/config.php';
        header('Location: ' . app_url('backend/admin/' . $adminFile));
        exit;
    }
}

// 3. Check if request targets a frontend file directly (e.g. /medicines.php)
$frontendPath = __DIR__ . DIRECTORY_SEPARATOR . 'frontend' . str_replace('/', DIRECTORY_SEPARATOR, $uri);
if (file_exists($frontendPath) && !is_dir($frontendPath)) {
    require_once $frontendPath;
    return true;
}

// 4. Admin routes (e.g. /backend/admin/login.php)
if (str_starts_with($uri, '/backend/admin') && file_exists($filePath)) {
    return false;
}

// 5. Default fallback: Route to public homepage
require_once __DIR__ . '/frontend/index.php';
