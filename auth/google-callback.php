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

// 1. Check for errors returned by Google
if (!empty($_GET['error'])) {
    $err = Security::sanitizeString((string)$_GET['error']);
    header("Location: " . app_url('login.php?error=' . urlencode('Google authorization declined: ' . $err)));
    exit;
}

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

if ($code === '') {
    header("Location: " . app_url('login.php?error=' . urlencode('Missing authorization code from Google.')));
    exit;
}

// 2. Exchange authorization code with Google
$result = GoogleAuth::handleCallback($code, $state);

if (!$result['success']) {
    $errorMsg = $result['error'] ?? 'Google authentication failed.';
    header("Location: " . app_url('login.php?error=' . urlencode($errorMsg)));
    exit;
}

// 3. Find or register customer in database
try {
    $db = DatabaseConnection::getInstance();
    $customerModel = new Customer($db);
    $customer = $customerModel->createOrUpdateGoogleUser($result['user']);

    // Check account status
    if (($customer['status'] ?? 'Active') !== 'Active') {
        header("Location: " . app_url('login.php?error=' . urlencode('Your account has been suspended. Please contact pharmacy support.')));
        exit;
    }

    // Set Customer Session
    $_SESSION['customer_id'] = (int)$customer['id'];
    $_SESSION['customer_name'] = $customer['name'];
    $_SESSION['customer_email'] = $customer['email'];
    $_SESSION['customer_avatar'] = $customer['avatar'] ?? null;
    $_SESSION['customer_phone'] = $customer['phone'] ?? null;

    // Determine redirect destination
    $returnTo = $_SESSION['oauth_return_to'] ?? '';
    unset($_SESSION['oauth_return_to']);

    if ($returnTo !== '' && !str_contains($returnTo, 'login.php')) {
        header("Location: " . $returnTo);
    } elseif (!empty($_SESSION['cart'])) {
        header("Location: " . app_url('cart.php'));
    } else {
        header("Location: " . app_url('account.php?welcome=1'));
    }
    exit;

} catch (\Throwable $e) {
    error_log('Google OAuth callback error: ' . $e->getMessage());
    header("Location: " . app_url('login.php?error=' . urlencode('Database error while signing in with Google.')));
    exit;
}
