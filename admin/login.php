<?php
declare(strict_types=1);

/**
 * Public Admin Login Shortcut
 */

require_once __DIR__ . '/../backend/config/config.php';

$target = app_url('backend/admin/login.php');
if (str_contains($target, '/home/')) {
    $target = '/backend/admin/login.php';
}
header("Location: " . $target);
exit;
