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

// If already logged in, redirect directly to dashboard
if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: " . app_url('backend/admin/index.php'));
    exit;
}

// Enforce that this specific session has completed step 1 (username + password)
if (empty($_SESSION['otp_pending']) || empty($_SESSION['otp_user_id'])) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header("Location: " . app_url('backend/admin/login.php'));
    exit;
}

$error = '';
$success = '';
$recipientEmail = $_SESSION['otp_email'] ?? 'ngw.designer@gmail.com';
$expiresInSeconds = max(0, (int)($_SESSION['otp_expires'] ?? (time() + 900)) - time());

$csrfToken = Security::generateCsrf();

// Handle Form Submissions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? 'verify';

        if ($action === 'verify') {
            $enteredOtp = trim($_POST['otp'] ?? '');
            
            // Fallback: check digit inputs if present
            if (empty($enteredOtp)) {
                $enteredOtp = ($_POST['d1'] ?? '') . ($_POST['d2'] ?? '') . ($_POST['d3'] ?? '') . ($_POST['d4'] ?? '') . ($_POST['d5'] ?? '') . ($_POST['d6'] ?? '');
            }

            $cleaned = preg_replace('/\D/', '', $enteredOtp);

            if (empty($cleaned) || strlen($cleaned) !== 6) {
                $error = 'Please enter the complete 6-digit verification code.';
            } else {
                $res = $auth->verifyOtp($cleaned);
                if ($res['success']) {
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_write_close();
                    }
                    header("Location: " . app_url('backend/admin/index.php'));
                    exit;
                } else {
                    $error = $res['message'];
                }
            }
        } elseif ($action === 'resend') {
            $res = $auth->resendOtp();
            if ($res['success']) {
                $success = $res['message'];
                $expiresInSeconds = 900;
            } else {
                $error = $res['message'];
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
    <title>Two-Factor Authentication (2FA) - HomeMed Admin</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome (with Subresource Integrity SRI) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        :root {
            --primary: #00c08b;
            --primary-hover: #00a87a;
            --primary-light: #e6f9f3;
            --navy: #0d223a;
            --navy-dark: #061322;
            --text-heading: #091e3a;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }

        * { margin:0; padding:0; box-sizing:border-box; font-family:'Plus Jakarta Sans', system-ui, sans-serif; }
        
        body {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-dark) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .otp-box {
            background: #ffffff;
            width: 100%;
            max-width: 480px;
            border-radius: 24px;
            padding: 42px 36px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.45);
            animation: fadeIn 0.4s ease-out;
            position: relative;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes radarWave {
            0% { box-shadow: 0 0 0 0 rgba(0, 192, 139, 0.5); }
            70% { box-shadow: 0 0 0 14px rgba(0, 192, 139, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 192, 139, 0); }
        }

        .shield-icon-wrapper {
            width: 64px;
            height: 64px;
            background: var(--primary-light);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            color: var(--primary);
            font-size: 2rem;
            animation: radarWave 2.5s infinite;
        }

        .otp-header {
            text-align: center;
            margin-bottom: 26px;
        }

        .otp-header h2 {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--text-heading);
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .otp-header p {
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .email-pill {
            background: var(--primary-light);
            color: #0f766e;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.88rem;
            display: inline-block;
            margin-top: 6px;
            border: 1px solid rgba(0, 192, 139, 0.25);
        }

        .alert-box {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.88rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #86efac;
        }

        /* Prominent 6-Digit Code Input */
        .otp-input-container {
            margin: 22px 0 20px;
            position: relative;
        }

        .main-otp-input {
            width: 100%;
            height: 72px;
            letter-spacing: 16px;
            text-align: center;
            font-size: 2.4rem;
            font-weight: 800;
            font-family: 'JetBrains Mono', monospace;
            border: 2px solid var(--border);
            border-radius: 16px;
            background: #f8fafc;
            color: var(--text-heading);
            outline: none;
            transition: all 0.25s;
        }

        .main-otp-input:focus {
            border-color: var(--primary);
            background: #ffffff;
            box-shadow: 0 0 0 5px rgba(0, 192, 139, 0.2);
            transform: translateY(-2px);
        }

        .main-otp-input::placeholder {
            color: #cbd5e1;
            letter-spacing: 12px;
        }

        .timer-strip {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 24px;
            padding: 0 4px;
        }

        .timer-badge {
            font-weight: 700;
            color: var(--primary);
            font-family: 'JetBrains Mono', monospace;
        }

        .btn-verify {
            width: 100%;
            background: var(--primary);
            color: #ffffff;
            border: none;
            padding: 15px;
            border-radius: 50px;
            font-size: 1.05rem;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 8px 20px rgba(0, 192, 139, 0.35);
            transition: all 0.2s;
        }

        .btn-verify:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 12px 26px rgba(0, 192, 139, 0.45);
        }

        .btn-verify:active {
            transform: translateY(0) scale(0.98);
        }

        .actions-bar {
            margin-top: 24px;
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 12px;
            font-size: 0.88rem;
        }

        .btn-resend {
            background: none;
            border: none;
            color: var(--primary);
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            text-decoration: underline;
            transition: color 0.2s;
        }

        .btn-resend:hover {
            color: var(--primary-hover);
        }

        .cancel-link {
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: color 0.2s;
        }

        .cancel-link:hover {
            color: #ef4444;
        }
    </style>
</head>
<body>
    <div class="otp-box">
        <div class="shield-icon-wrapper">
            <i class="fa-solid fa-shield-halved"></i>
        </div>

        <div class="otp-header">
            <h2>Two-Factor Verification</h2>
            <p>
                Enter the 6-digit verification code dispatched to:
                <br>
                <span class="email-pill"><i class="fa-solid fa-envelope"></i> <?= Security::e($recipientEmail); ?></span>
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-box alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= Security::e($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert-box alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= Security::e($success); ?></span>
            </div>
        <?php endif; ?>

        <!-- OTP Input Form -->
        <form action="" method="POST" id="otp-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
            <input type="hidden" name="action" value="verify">

            <div class="otp-input-container">
                <input type="text" 
                       id="otp-field" 
                       name="otp" 
                       maxlength="6" 
                       inputmode="numeric" 
                       pattern="[0-9]{6}" 
                       placeholder="123456" 
                       class="main-otp-input" 
                       autofocus 
                       required>
            </div>

            <div class="timer-strip">
                <span>Code expires in:</span>
                <span class="timer-badge" id="countdown-timer">10:00</span>
            </div>

            <button type="submit" class="btn-verify" id="btn-submit">
                <i class="fa-solid fa-lock-open"></i> Verify & Sign In
            </button>
        </form>

        <!-- Resend Code Form -->
        <form action="" method="POST" id="resend-form" style="display:none;">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
            <input type="hidden" name="action" value="resend">
        </form>

        <div class="actions-bar">
            <div>
                Didn't receive the email? 
                <button type="button" class="btn-resend" id="resend-btn" onclick="document.getElementById('resend-form').submit();">
                    Resend Code
                </button>
            </div>

            <a href="<?= app_url('backend/admin/logout.php'); ?>" class="cancel-link">
                <i class="fa-solid fa-arrow-left"></i> Cancel & Sign In as Different User
            </a>
        </div>
    </div>

    <script>
        const otpInput = document.getElementById('otp-field');
        const form = document.getElementById('otp-form');
        const btnSubmit = document.getElementById('btn-submit');
        let isSubmitting = false;

        function submitVerification() {
            if (isSubmitting) return;
            const val = otpInput.value.replace(/\D/g, '');
            if (val.length !== 6) {
                otpInput.focus();
                return;
            }
            isSubmitting = true;
            btnSubmit.disabled = true;
            btnSubmit.style.opacity = '0.75';
            btnSubmit.style.cursor = 'wait';
            btnSubmit.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Verifying Code...';
            form.submit();
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            submitVerification();
        });

        // Only allow numbers, automatically clean any non-digit input
        otpInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
            if (this.value.length === 6) {
                submitVerification();
            }
        });

        // Paste support: strip spaces/dashes and auto-fill
        otpInput.addEventListener('paste', function(e) {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text');
            const digits = pasted.replace(/\D/g, '').slice(0, 6);
            this.value = digits;
            if (digits.length === 6) {
                submitVerification();
            }
        });

        // Live Countdown Timer
        let secondsLeft = <?= $expiresInSeconds; ?>;
        const timerEl = document.getElementById('countdown-timer');

        function updateTimer() {
            const m = Math.floor(secondsLeft / 60);
            const s = secondsLeft % 60;
            timerEl.innerText = `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;

            if (secondsLeft <= 0) {
                timerEl.innerText = 'Expired';
                timerEl.style.color = '#ef4444';
            } else {
                secondsLeft--;
                setTimeout(updateTimer, 1000);
            }
        }
        updateTimer();
    </script>
</body>
</html>
