<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/core/Security.php';
require_once __DIR__ . '/../backend/core/GoogleAuth.php';

use App\Core\Security;
use App\Core\GoogleAuth;

Security::applyHeaders();

$returnTo = $_GET['return_to'] ?? app_url('index.php');

// 1. If Google OAuth is configured, redirect straight to Google
if (GoogleAuth::isConfigured()) {
    $authUrl = GoogleAuth::getAuthUrl($returnTo);
    header("Location: " . $authUrl);
    exit;
}

// 2. If user requested Quick Demo Sandbox Sign-In
if (isset($_GET['demo']) && $_GET['demo'] === '1') {
    require_once __DIR__ . '/../backend/config/database.php';
    require_once __DIR__ . '/../backend/models/Customer.php';

    $db = DatabaseConnection::getInstance();
    $customerModel = new \App\Models\Customer($db);

    $demoUser = $customerModel->createOrUpdateGoogleUser([
        'google_id' => 'google-demo-1092837465',
        'email'     => 'demo.patient@gmail.com',
        'name'      => 'Varinder Singh (Google Demo)',
        'avatar'    => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&auto=format&fit=crop&q=80',
    ]);

    $_SESSION['customer_id'] = (int)$demoUser['id'];
    $_SESSION['customer_name'] = $demoUser['name'];
    $_SESSION['customer_email'] = $demoUser['email'];
    $_SESSION['customer_avatar'] = $demoUser['avatar'];

    header("Location: " . app_url('account.php?welcome=1'));
    exit;
}

// 3. Otherwise show setup guidance with 1-click test button
$pageTitle = "Google Sign-In Setup";
require_once __DIR__ . '/../frontend/layout/header.php';
?>
<main class="container" style="max-width: 760px; margin: 4rem auto; min-height: 60vh;">
    <div style="background:#fff; border-radius: 16px; padding: 2.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border: 1px solid #e2e8f0;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="display:inline-flex; align-items:center; justify-content:center; width:64px; height:64px; border-radius:50%; background:#f1f5f9; margin-bottom:1rem;">
                <svg width="32" height="32" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                </svg>
            </div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: #0f172a; margin-bottom: 0.5rem;">Google Sign-In Configuration</h1>
            <p style="color: #64748b; font-size: 0.95rem;">To connect official Google accounts, add your Google OAuth Client ID and Secret in your <code>env/.env</code> file.</p>
        </div>

        <div style="background:#f8fafc; border-left: 4px solid var(--brand-teal, #0d9488); padding: 1.25rem; border-radius: 8px; margin-bottom: 2rem;">
            <h3 style="font-size: 1rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem;"><i class="fa-solid fa-circle-info" style="color:var(--brand-teal, #0d9488);"></i> Quick Test Mode Available</h3>
            <p style="font-size: 0.9rem; color: #475569; margin-bottom: 1rem;">You can instantly test the customer account workflow right now without waiting for Google API keys:</p>
            <a href="<?= app_url('auth/google-login.php?demo=1'); ?>" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:0.5rem; padding:0.65rem 1.25rem; background:var(--brand-teal, #0d9488); color:#fff; text-decoration:none; border-radius:8px; font-weight:600; font-size:0.9rem;">
                <i class="fa-solid fa-bolt"></i> Test Instant Customer Login
            </a>
        </div>

        <div style="border-top: 1px solid #e2e8f0; padding-top: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin-bottom: 1rem;">How to Activate Live Google Sign-In:</h3>
            <ol style="font-size: 0.9rem; color: #334155; line-height: 1.8; padding-left: 1.25rem;">
                <li>Visit the <strong><a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a></strong>.</li>
                <li>Create a project (or select an existing one) and navigate to <strong>APIs & Services ➔ Credentials</strong>.</li>
                <li>Click <strong>Create Credentials ➔ OAuth Client ID</strong> (Application type: <em>Web application</em>).</li>
                <li>Add your <strong>Authorized Redirect URI</strong>:
                    <div style="background:#1e293b; color:#38bdf8; padding:0.5rem 0.75rem; border-radius:6px; font-family:monospace; margin:0.4rem 0; word-break:break-all;">
                        <?= Security::e(app_url('auth/google-callback.php')); ?>
                    </div>
                </li>
                <li>Copy your <strong>Client ID</strong> and <strong>Client Secret</strong> into <code>env/.env</code>:
<pre style="background:#1e293b; color:#e2e8f0; padding:0.75rem; border-radius:6px; font-size:0.85rem; margin-top:0.5rem; overflow-x:auto;">
GOOGLE_CLIENT_ID="your-client-id.apps.googleusercontent.com"
GOOGLE_CLIENT_SECRET="your-client-secret"
GOOGLE_REDIRECT_URI="<?= Security::e(app_url('auth/google-callback.php')); ?>"
</pre>
                </li>
            </ol>
        </div>

        <div style="text-align:center; margin-top: 2rem;">
            <a href="<?= app_url('login.php'); ?>" style="color: #64748b; font-size: 0.9rem; text-decoration: none;">
                <i class="fa-solid fa-arrow-left"></i> Return to Regular Sign In / Sign Up
            </a>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../frontend/layout/footer.php'; ?>
