<?php
declare(strict_types=1);

/**
 * Public Admin Login Shortcut
 */

require_once __DIR__ . '/../backend/config/config.php';

header("Location: " . app_url('backend/admin/login.php'));
exit;
