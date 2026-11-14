<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/core/Security.php';
require_once __DIR__ . '/../backend/core/GoogleAuth.php';
require_once __DIR__ . '/../backend/models/Customer.php';

use App\Core\Security;
use App\Core\GoogleAuth;
use App\Models\Customer;

Security::applyHeaders();

// If already logged in, redirect to account dashboard
if (!empty($_SESSION['customer_id'])) {
    header("Location: " . app_url('account.php'));
    exit;
}

$db = DatabaseConnection::getInstance();
$customerModel = new Customer($db);

$error = '';
$success = '';
$activeTab = $activeTab ?? ($_GET['tab'] ?? 'login');
if (!in_array($activeTab, ['login', 'register'], true)) {
    $activeTab = 'login';
}

if (!empty($_GET['error'])) {
    $error = Security::sanitizeString($_GET['error']);
}
if (!empty($_GET['logged_out'])) {
    $success = 'You have been signed out safely. See you soon!';
}

// Handle Form Submissions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!Security::validateCsrf($csrfToken)) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $action = $_POST['auth_action'] ?? 'login';

        // ── 1. LOGIN PROCESSING ──
        if ($action === 'login') {
            $activeTab = 'login';
            $email = trim($_POST['email'] ?? '');
            $password = (string)($_POST['password'] ?? '');

            if ($email === '' || $password === '') {
                $error = 'Please enter both your email address and password.';
            } else {
                $customer = $customerModel->findByEmail($email);
                if (!$customer) {
                    $error = 'Invalid email address or password.';
                } elseif (($customer['status'] ?? 'Active') !== 'Active') {
                    $error = 'This account has been suspended. Please contact customer support.';
                } elseif (empty($customer['password'])) {
                    // Registered with Google
                    $error = 'This account was created with Google. Please click "Continue with Google" below.';
                } elseif (!$customerModel->verifyPassword($password, $customer['password'])) {
                    $error = 'Invalid email address or password.';
                } else {
                    // Login successful!
                    $_SESSION['customer_id'] = (int)$customer['id'];
                    $_SESSION['customer_name'] = $customer['name'];
                    $_SESSION['customer_email'] = $customer['email'];
                    $_SESSION['customer_avatar'] = $customer['avatar'] ?? null;
                    $_SESSION['customer_phone'] = $customer['phone'] ?? null;

                    $returnTo = $_GET['return_to'] ?? '';
                    if ($returnTo !== '' && !str_contains($returnTo, 'login.php')) {
                        header("Location: " . $returnTo);
                    } elseif (!empty($_SESSION['cart'])) {
                        header("Location: " . app_url('cart.php'));
                    } else {
                        header("Location: " . app_url('account.php'));
                    }
                    exit;
                }
            }
        }

        // ── 2. REGISTRATION PROCESSING ──
        if ($action === 'register') {
            $activeTab = 'register';
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');

            if ($name === '' || $email === '' || $password === '') {
                $error = 'Please fill in all required fields (Name, Email, Password).';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters long.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Password confirmation does not match.';
            } else {
                // Check if email already exists
                $existing = $customerModel->findByEmail($email);
                if ($existing) {
                    $error = 'An account with this email address already exists. Please Sign In.';
                } else {
                    $newId = $customerModel->create([
                        'name'     => $name,
                        'email'    => $email,
                        'phone'    => $phone,
                        'password' => $password,
                    ]);

                    if ($newId) {
                        $_SESSION['customer_id'] = $newId;
                        $_SESSION['customer_name'] = $name;
                        $_SESSION['customer_email'] = strtolower($email);
                        $_SESSION['customer_avatar'] = null;
                        $_SESSION['customer_phone'] = $phone ?: null;

                        if (!empty($_SESSION['cart'])) {
                            header("Location: " . app_url('cart.php'));
                        } else {
                            header("Location: " . app_url('account.php?new=1'));
                        }
                        exit;
                    } else {
                        $error = 'Failed to create your account. Please try again.';
                    }
                }
            }
        }
    }
}

$csrfToken = Security::generateCsrf();
$pageTitle = ($activeTab === 'register') ? "Create Account" : "Customer Sign In";
require_once __DIR__ . '/layout/header.php';
?>

<main class="container" style="max-width: 520px; margin: 3.5rem auto 5rem; min-height: 60vh;">
    <div style="background:#ffffff; border-radius: 20px; padding: 2.5rem; box-shadow: 0 12px 40px rgba(0,0,0,0.07); border: 1px solid #e2e8f0;">
        
        <!-- Header Branding -->
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="display:inline-flex; align-items:center; justify-content:center; width:52px; height:52px; border-radius:14px; background:linear-gradient(135deg, #0d9488 0%, #059669 100%); color:#ffffff; font-size:1.5rem; margin-bottom:0.75rem; box-shadow:0 8px 16px rgba(13,148,136,0.25);">
                <i class="fa-solid fa-heart-pulse"></i>
            </div>
            <h1 style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-bottom: 0.35rem;">Welcome to HomeMed</h1>
            <p style="color: #64748b; font-size: 0.9rem;">Fast prescription delivery, refill alerts & verified medicines</p>
        </div>

        <!-- Tab Toggle Buttons -->
        <div style="display: flex; background: #f1f5f9; padding: 4px; border-radius: 12px; margin-bottom: 1.75rem;">
            <button type="button" id="tab-btn-login" onclick="switchAuthTab('login')" 
                    style="flex:1; padding:0.65rem; border:none; border-radius:9px; font-weight:700; font-size:0.92rem; cursor:pointer; transition:all 0.2s ease; <?= $activeTab === 'login' ? 'background:#fff; color:#0f172a; box-shadow:0 2px 6px rgba(0,0,0,0.08);' : 'background:transparent; color:#64748b;' ?>">
                <i class="fa-solid fa-arrow-right-to-bracket" style="margin-right:6px;"></i> Sign In
            </button>
            <button type="button" id="tab-btn-register" onclick="switchAuthTab('register')" 
                    style="flex:1; padding:0.65rem; border:none; border-radius:9px; font-weight:700; font-size:0.92rem; cursor:pointer; transition:all 0.2s ease; <?= $activeTab === 'register' ? 'background:#fff; color:#0f172a; box-shadow:0 2px 6px rgba(0,0,0,0.08);' : 'background:transparent; color:#64748b;' ?>">
                <i class="fa-solid fa-user-plus" style="margin-right:6px;"></i> Create Account
            </button>
        </div>

        <!-- Flash Notifications -->
        <?php if (!empty($error)): ?>
            <div style="background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:0.85rem 1rem; border-radius:10px; font-size:0.9rem; margin-bottom:1.25rem; display:flex; align-items:center; gap:0.6rem;">
                <i class="fa-solid fa-circle-exclamation" style="font-size:1.1rem;"></i>
                <span><?= Security::e($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#16a34a; padding:0.85rem 1rem; border-radius:10px; font-size:0.9rem; margin-bottom:1.25rem; display:flex; align-items:center; gap:0.6rem;">
                <i class="fa-solid fa-circle-check" style="font-size:1.1rem;"></i>
                <span><?= Security::e($success); ?></span>
            </div>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════════════════ -->
        <!-- GOOGLE OAUTH CONTINUED BUTTON (Prominent on Both Tabs) -->
        <!-- ══════════════════════════════════════════════════════ -->
        <a href="<?= app_url('auth/google-login.php'); ?>" 
           style="display: flex; align-items: center; justify-content: center; gap: 0.75rem; width: 100%; padding: 0.8rem 1rem; background: #ffffff; border: 1.5px solid #cbd5e1; border-radius: 12px; color: #1e293b; font-weight: 600; font-size: 0.95rem; text-decoration: none; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: all 0.2s ease; margin-bottom: 1.5rem;"
           onmouseover="this.style.borderColor='#94a3b8'; this.style.backgroundColor='#f8fafc';"
           onmouseout="this.style.borderColor='#cbd5e1'; this.style.backgroundColor='#ffffff';">
            <svg width="20" height="20" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
            </svg>
            <span id="google-btn-text"><?= $activeTab === 'register' ? 'Sign up with Google' : 'Continue with Google'; ?></span>
        </a>

        <!-- Divider with Text -->
        <div style="display:flex; align-items:center; text-align:center; margin-bottom:1.5rem;">
            <div style="flex:1; border-bottom:1px solid #e2e8f0;"></div>
            <span style="padding:0 0.75rem; color:#94a3b8; font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Or with email</span>
            <div style="flex:1; border-bottom:1px solid #e2e8f0;"></div>
        </div>

        <!-- ══════════════════════════════════════════════════════ -->
        <!-- TAB 1: SIGN IN FORM                                    -->
        <!-- ══════════════════════════════════════════════════════ -->
        <div id="auth-panel-login" style="display: <?= $activeTab === 'login' ? 'block' : 'none'; ?>;">
            <form action="<?= app_url('login.php'); ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                <input type="hidden" name="auth_action" value="login">

                <div style="margin-bottom: 1.15rem;">
                    <label style="display:block; font-size:0.88rem; font-weight:700; color:#334155; margin-bottom:0.4rem;">
                        Email Address <span style="color:#ef4444;">*</span>
                    </label>
                    <div style="position:relative;">
                        <i class="fa-solid fa-envelope" style="position:absolute; left:14px; top:14px; color:#94a3b8;"></i>
                        <input type="email" name="email" required placeholder="name@example.com"
                               value="<?= Security::e($_POST['email'] ?? ''); ?>"
                               style="width:100%; padding:0.75rem 0.75rem 0.75rem 2.6rem; border:1.5px solid #cbd5e1; border-radius:10px; font-size:0.95rem; font-family:inherit; outline:none;"
                               onfocus="this.style.borderColor='var(--brand-teal, #0d9488)';"
                               onblur="this.style.borderColor='#cbd5e1';">
                    </div>
                </div>

                <div style="margin-bottom: 1.25rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
                        <label style="font-size:0.88rem; font-weight:700; color:#334155;">
                            Password <span style="color:#ef4444;">*</span>
                        </label>
                        <a href="javascript:void(0);" onclick="alert('Password reset link has been dispatched to your email if registered.');" style="font-size:0.8rem; color:var(--brand-teal, #0d9488); text-decoration:none; font-weight:600;">
                            Forgot Password?
                        </a>
                    </div>
                    <div style="position:relative;">
                        <i class="fa-solid fa-lock" style="position:absolute; left:14px; top:14px; color:#94a3b8;"></i>
                        <input type="password" id="login-password" name="password" required placeholder="Enter your password"
                               style="width:100%; padding:0.75rem 2.6rem 0.75rem 2.6rem; border:1.5px solid #cbd5e1; border-radius:10px; font-size:0.95rem; font-family:inherit; outline:none;"
                               onfocus="this.style.borderColor='var(--brand-teal, #0d9488)';"
                               onblur="this.style.borderColor='#cbd5e1';">
                        <button type="button" onclick="togglePasswordVisibility('login-password', this)" style="position:absolute; right:12px; top:12px; background:transparent; border:none; color:#94a3b8; cursor:pointer; font-size:0.95rem;">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" 
                        style="width:100%; padding:0.85rem; background:linear-gradient(135deg, #0d9488 0%, #059669 100%); color:#ffffff; border:none; border-radius:12px; font-size:1rem; font-weight:700; cursor:pointer; box-shadow:0 4px 14px rgba(13,148,136,0.3); transition:all 0.2s ease;">
                    Sign In to Account
                </button>
            </form>
        </div>

        <!-- ══════════════════════════════════════════════════════ -->
        <!-- TAB 2: REGISTER / SIGN UP FORM                         -->
        <!-- ══════════════════════════════════════════════════════ -->
        <div id="auth-panel-register" style="display: <?= $activeTab === 'register' ? 'block' : 'none'; ?>;">
            <form action="<?= app_url('login.php'); ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                <input type="hidden" name="auth_action" value="register">

                <div style="margin-bottom: 1.15rem;">
                    <label style="display:block; font-size:0.88rem; font-weight:700; color:#334155; margin-bottom:0.4rem;">
                        Full Name <span style="color:#ef4444;">*</span>
                    </label>
                    <div style="position:relative;">
                        <i class="fa-solid fa-user" style="position:absolute; left:14px; top:14px; color:#94a3b8;"></i>
                        <input type="text" name="name" required placeholder="e.g. Varinder Singh"
                               value="<?= Security::e($_POST['name'] ?? ''); ?>"
                               style="width:100%; padding:0.75rem 0.75rem 0.75rem 2.6rem; border:1.5px solid #cbd5e1; border-radius:10px; font-size:0.95rem; font-family:inherit; outline:none;"
                               onfocus="this.style.borderColor='var(--brand-teal, #0d9488)';"
                               onblur="this.style.borderColor='#cbd5e1';">
                    </div>
                </div>

                <div style="margin-bottom: 1.15rem;">
                    <label style="display:block; font-size:0.88rem; font-weight:700; color:#334155; margin-bottom:0.4rem;">
                        Email Address <span style="color:#ef4444;">*</span>
                    </label>
                    <div style="position:relative;">
                        <i class="fa-solid fa-envelope" style="position:absolute; left:14px; top:14px; color:#94a3b8;"></i>
                        <input type="email" name="email" required placeholder="name@example.com"
                               value="<?= Security::e($_POST['email'] ?? ''); ?>"
                               style="width:100%; padding:0.75rem 0.75rem 0.75rem 2.6rem; border:1.5px solid #cbd5e1; border-radius:10px; font-size:0.95rem; font-family:inherit; outline:none;"
                               onfocus="this.style.borderColor='var(--brand-teal, #0d9488)';"
                               onblur="this.style.borderColor='#cbd5e1';">
                    </div>
                </div>

                <div style="margin-bottom: 1.15rem;">
                    <label style="display:block; font-size:0.88rem; font-weight:700; color:#334155; margin-bottom:0.4rem;">
                        Mobile Phone <span style="font-weight:400; color:#94a3b8;">(for delivery SMS alerts)</span>
                    </label>
                    <div style="position:relative;">
                        <i class="fa-solid fa-phone" style="position:absolute; left:14px; top:14px; color:#94a3b8;"></i>
                        <input type="tel" name="phone" placeholder="+91 98765 43210"
                               value="<?= Security::e($_POST['phone'] ?? ''); ?>"
                               style="width:100%; padding:0.75rem 0.75rem 0.75rem 2.6rem; border:1.5px solid #cbd5e1; border-radius:10px; font-size:0.95rem; font-family:inherit; outline:none;"
                               onfocus="this.style.borderColor='var(--brand-teal, #0d9488)';"
                               onblur="this.style.borderColor='#cbd5e1';">
                    </div>
                </div>

                <div style="margin-bottom: 1.15rem;">
                    <label style="display:block; font-size:0.88rem; font-weight:700; color:#334155; margin-bottom:0.4rem;">
                        Password <span style="color:#ef4444;">*</span> <span style="font-weight:400; font-size:0.78rem; color:#64748b;">(min 6 chars)</span>
                    </label>
                    <div style="position:relative;">
                        <i class="fa-solid fa-lock" style="position:absolute; left:14px; top:14px; color:#94a3b8;"></i>
                        <input type="password" id="reg-password" name="password" required placeholder="Choose a secure password"
                               style="width:100%; padding:0.75rem 2.6rem 0.75rem 2.6rem; border:1.5px solid #cbd5e1; border-radius:10px; font-size:0.95rem; font-family:inherit; outline:none;"
                               onfocus="this.style.borderColor='var(--brand-teal, #0d9488)';"
                               onblur="this.style.borderColor='#cbd5e1';">
                        <button type="button" onclick="togglePasswordVisibility('reg-password', this)" style="position:absolute; right:12px; top:12px; background:transparent; border:none; color:#94a3b8; cursor:pointer; font-size:0.95rem;">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div style="margin-bottom: 1.35rem;">
                    <label style="display:block; font-size:0.88rem; font-weight:700; color:#334155; margin-bottom:0.4rem;">
                        Confirm Password <span style="color:#ef4444;">*</span>
                    </label>
                    <div style="position:relative;">
                        <i class="fa-solid fa-lock" style="position:absolute; left:14px; top:14px; color:#94a3b8;"></i>
                        <input type="password" id="reg-confirm-password" name="confirm_password" required placeholder="Re-enter your password"
                               style="width:100%; padding:0.75rem 2.6rem 0.75rem 2.6rem; border:1.5px solid #cbd5e1; border-radius:10px; font-size:0.95rem; font-family:inherit; outline:none;"
                               onfocus="this.style.borderColor='var(--brand-teal, #0d9488)';"
                               onblur="this.style.borderColor='#cbd5e1';">
                        <button type="button" onclick="togglePasswordVisibility('reg-confirm-password', this)" style="position:absolute; right:12px; top:12px; background:transparent; border:none; color:#94a3b8; cursor:pointer; font-size:0.95rem;">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" 
                        style="width:100%; padding:0.85rem; background:linear-gradient(135deg, #0d9488 0%, #059669 100%); color:#ffffff; border:none; border-radius:12px; font-size:1rem; font-weight:700; cursor:pointer; box-shadow:0 4px 14px rgba(13,148,136,0.3); transition:all 0.2s ease;">
                    Create Patient Account
                </button>
            </form>
        </div>

        <!-- Pharmacist Back-Office Link -->
        <div style="margin-top: 2rem; padding-top: 1.25rem; border-top: 1px dashed #e2e8f0; text-align: center;">
            <p style="font-size: 0.82rem; color: #64748b; margin-bottom: 0.25rem;">Are you a licensed dispensing pharmacist?</p>
            <a href="<?= app_url('backend/admin/login.php'); ?>" style="color: #0284c7; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.35rem;">
                <i class="fa-solid fa-user-shield"></i> Access Pharmacist Admin Portal &rarr;
            </a>
        </div>
    </div>
</main>

<script>
function switchAuthTab(tab) {
    const loginPanel = document.getElementById('auth-panel-login');
    const regPanel = document.getElementById('auth-panel-register');
    const loginBtn = document.getElementById('tab-btn-login');
    const regBtn = document.getElementById('tab-btn-register');
    const googleText = document.getElementById('google-btn-text');

    if (tab === 'login') {
        loginPanel.style.display = 'block';
        regPanel.style.display = 'none';
        loginBtn.style.background = '#ffffff';
        loginBtn.style.color = '#0f172a';
        loginBtn.style.boxShadow = '0 2px 6px rgba(0,0,0,0.08)';
        regBtn.style.background = 'transparent';
        regBtn.style.color = '#64748b';
        regBtn.style.boxShadow = 'none';
        if (googleText) googleText.textContent = 'Continue with Google';
    } else {
        loginPanel.style.display = 'none';
        regPanel.style.display = 'block';
        regBtn.style.background = '#ffffff';
        regBtn.style.color = '#0f172a';
        regBtn.style.boxShadow = '0 2px 6px rgba(0,0,0,0.08)';
        loginBtn.style.background = 'transparent';
        loginBtn.style.color = '#64748b';
        loginBtn.style.boxShadow = 'none';
        if (googleText) googleText.textContent = 'Sign up with Google';
    }
}

function togglePasswordVisibility(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
