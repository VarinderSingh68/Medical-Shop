<?php
declare(strict_types=1);

/**
 * Public Admin Shortcut Route (/admin)
 * Seamlessly forwards authenticated administrators to Dashboard, or guests to Login Portal
 */

require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/core/Auth.php';

use App\Core\Auth;

// 1. If session is already logged in, go straight to Dashboard
if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header("Location: " . app_url('backend/admin/index.php'));
    exit;
}

// 2. Otherwise redirect to login
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
header("Location: " . app_url('backend/admin/login.php'));
exit;
