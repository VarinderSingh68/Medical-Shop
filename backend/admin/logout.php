<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Auth.php';

use App\Core\Auth;

Auth::logout();
header("Location: " . app_url('backend/admin/login.php'));
exit;
