<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/Auth.php';

use App\Core\Auth;
use App\Core\Security;

Security::applyHeaders();
$db = DatabaseConnection::getInstance();
$auth = new Auth($db);

// If already authenticated in current session, redirect directly to dashboard
if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    session_write_close();
    header("Location: " . app_url('backend/admin/index.php'));
    exit;
}

$error = '';
$csrfToken = Security::generateCsrf();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!Security::validateCsrf($submittedToken)) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = 'Both username and password are required.';
        } else {
            $result = $auth->login($username, $password);
            if ($result['success']) {
                if (!empty($result['requires_otp'])) {
                    session_write_close();
                    header("Location: " . app_url('backend/admin/verify-otp.php'));
                    exit;
                }
                session_write_close();
                header("Location: " . app_url('backend/admin/index.php'));
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacist & Admin Authentication - HomeMed</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Plus Jakarta Sans', system-ui, sans-serif; }
        body { background:linear-gradient(135deg, #0d223a 0%, #061322 100%); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .login-box { background:#fff; width:100%; max-width:440px; border-radius:20px; padding:40px; box-shadow:0 25px 60px rgba(0,0,0,0.5); }
        .brand-header { text-align:center; margin-bottom:28px; }
        .logo-icon { width:52px; height:52px; background:#00c08b; border-radius:12px; display:inline-flex; align-items:center; justify-content:center; color:#fff; font-size:1.8rem; margin-bottom:12px; box-shadow:0 8px 20px rgba(0,192,139,0.35); }
        .brand-header h2 { font-size:1.6rem; color:#0d223a; margin-bottom:4px; font-weight:800; letter-spacing:-0.5px; }
        .brand-header p { font-size:0.88rem; color:#64748b; }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; font-size:0.88rem; font-weight:700; color:#334155; margin-bottom:6px; }
        .input-group { position:relative; }
        .input-group i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#94a3b8; }
        .input-group input { width:100%; padding:12px 14px 12px 42px; border:1px solid #cbd5e1; border-radius:10px; font-size:0.95rem; font-family:inherit; outline:none; }
        .input-group input:focus { border-color:#00c08b; box-shadow:0 0 0 3px rgba(0,192,139,0.15); }
        .btn-login { width:100%; background:#00c08b; color:#fff; border:none; padding:14px; font-size:1rem; font-weight:700; border-radius:50px; cursor:pointer; transition:.2s; margin-top:10px; box-shadow:0 6px 18px rgba(0,192,139,0.3); }
        .btn-login:hover { background:#00a87a; transform:translateY(-1px); }
        .alert-error { background:#fee2e2; border-left:4px solid #ef4444; color:#991b1b; padding:12px 16px; border-radius:6px; margin-bottom:20px; font-size:0.88rem; display:flex; align-items:center; gap:10px; }
        .credentials-pill { background:#e6f9f3; border:1px dashed #00c08b; border-radius:10px; padding:12px; margin-top:24px; font-size:0.85rem; color:#0f766e; text-align:center; }
        .back-link { display:block; text-align:center; margin-top:20px; font-size:0.85rem; color:#64748b; text-decoration:none; font-weight:600; }
        .back-link:hover { color:#00c08b; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="brand-header">
            <div class="logo-icon">
                <i class="fa-solid fa-notes-medical"></i>
            </div>
            <h2>HomeMed Admin</h2>
            <p>Pharmacist & Store Manager Access Control</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= Security::e($error); ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">

            <div class="form-group">
                <label for="username">Username or Email</label>
                <div class="input-group">
                    <i class="fa-solid fa-user-shield"></i>
                    <input type="text" id="username" name="username" required placeholder="admin" autofocus>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-group">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="password" name="password" required placeholder="••••••••••••">
                </div>
            </div>

            <button type="submit" class="btn-login">
                <i class="fa-solid fa-right-to-bracket"></i> Sign In to Back-Office
            </button>
        </form>

        <a href="<?= app_url('index.php'); ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Return to Public Pharmacy Store
        </a>
    </div>
</body>
</html>
