<?php
declare(strict_types=1);

require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../../backend/core/Security.php';
require_once __DIR__ . '/../../backend/models/Setting.php';
require_once __DIR__ . '/../../backend/models/Category.php';

use App\Core\Security;
use App\Models\Setting;
use App\Models\Category;

Security::applyHeaders();
$db = DatabaseConnection::getInstance();
$settingModel = new Setting($db);
$catModel = new Category($db);

$settings = $settingModel->getAll();
$navCategories = $catModel->getAll();

// Calculate Cart Item Count
$cartCount = 0;
if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int)($item['quantity'] ?? 0);
    }
}

// Current active delivery location
$currentCity = $_SESSION['delivery_city'] ?? 'Mumbai, India';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? Security::e($pageTitle) . ' - ' : ''; ?><?= Security::e($settings['shop_name'] ?? 'HomeMed - Online Pharmacy'); ?></title>
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome 6 (with Subresource Integrity SRI) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <!-- Main HomeMed Stylesheet -->
    <link rel="stylesheet" href="<?= app_url('assets/css/style.css'); ?>">
</head>
<body>
    <!-- Top Modern Navigation Bar (Matching Reference UI) -->
    <header class="site-header">
        <div class="container">
            <div class="nav-wrapper">
                <!-- Brand & Location Group -->
                <div class="brand-group">
                    <a href="<?= app_url('index.php'); ?>" class="brand-logo">
                        <div class="logo-badge">
                            <i class="fa-solid fa-notes-medical"></i>
                        </div>
                        <span>HomeMed</span>
                    </a>

                    <!-- Deliver To Location Pill -->
                    <div class="location-pill" onclick="changeDeliveryLocation();" title="Click to change delivery city">
                        <i class="fa-solid fa-location-dot" style="color:var(--brand-teal);"></i>
                        <span>Deliver to: <strong id="user-location"><?= Security::e($currentCity); ?></strong></span>
                        <i class="fa-solid fa-chevron-down" style="font-size:0.75rem; opacity:0.7;"></i>
                    </div>
                </div>

                <!-- Navigation Menu Links -->
                <nav class="nav-menu">
                    <a href="<?= app_url('medicines.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'medicines.php' ? 'active' : ''; ?>">Browse Medicines</a>
                    <a href="<?= app_url('upload-prescription.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'upload-prescription.php' ? 'active' : ''; ?>">Upload Prescription</a>
                    <a href="<?= app_url('track-order.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'track-order.php' ? 'active' : ''; ?>">Track Order</a>
                    <a href="<?= app_url('contact.php'); ?>">24/7 Doctor Consult</a>
                    <a href="<?= app_url('medicines.php?offers=1'); ?>">Offers</a>
                </nav>

                <!-- Right Action Buttons -->
                <div class="nav-actions">
                    <!-- Search Icon Trigger -->
                    <a href="javascript:void(0);" onclick="document.getElementById('hero-search-input')?.focus();" class="nav-icon-btn" title="Search Medicines">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </a>

                    <!-- Green Pill Cart Button -->
                    <a href="<?= app_url('cart.php'); ?>" class="btn-cart-nav">
                        <i class="fa-solid fa-bag-shopping"></i>
                        <span>Cart (<?= $cartCount; ?> items)</span>
                    </a>

                    <!-- Customer Account / Authentication Navigation -->
                    <?php if (!empty($_SESSION['customer_id'])): ?>
                        <?php 
                        $custFirstName = htmlspecialchars(explode(' ', trim($_SESSION['customer_name'] ?? 'User'))[0]);
                        if (empty($_SESSION['customer_avatar']) && !empty($_SESSION['customer_id'])) {
                            try {
                                $avatarStmt = $db->prepare("SELECT avatar FROM customers WHERE id = :id LIMIT 1");
                                $avatarStmt->execute([':id' => (int)$_SESSION['customer_id']]);
                                $avRow = $avatarStmt->fetch(PDO::FETCH_ASSOC);
                                if (!empty($avRow['avatar'])) {
                                    $_SESSION['customer_avatar'] = $avRow['avatar'];
                                }
                            } catch (\Throwable $e) {}
                        }
                        $custAvatar = $_SESSION['customer_avatar'] ?? null;
                        ?>
                        <div class="nav-user-wrapper" id="nav-user-dropdown-container">
                            <a href="<?= app_url('account.php'); ?>" class="btn-signin-nav" style="background:#f0fdf4; color:#166534; border:1px solid #bbf7d0;">
                                <?php if ($custAvatar): ?>
                                    <img src="<?= Security::e($custAvatar); ?>" alt="Avatar" style="width:24px; height:24px; border-radius:50%; object-fit:cover;" referrerpolicy="no-referrer">
                                <?php else: ?>
                                    <i class="fa-solid fa-circle-user" style="color:#16a34a; font-size:1.1rem;"></i>
                                <?php endif; ?>
                                <span>Hi, <?= $custFirstName; ?></span>
                                <i class="fa-solid fa-chevron-down" style="font-size:0.65rem; opacity:0.6;"></i>
                            </a>
                            <div class="nav-user-menu">
                                <a href="<?= app_url('account.php'); ?>">
                                    <i class="fa-solid fa-user" style="color:var(--brand-teal);"></i> My Account & Profile
                                </a>
                                <a href="<?= app_url('account.php'); ?>">
                                    <i class="fa-solid fa-box-open" style="color:#0284c7;"></i> Orders & Prescriptions
                                </a>
                                <a href="<?= app_url('track-order.php'); ?>">
                                    <i class="fa-solid fa-truck" style="color:#f59e0b;"></i> Track Live Delivery
                                </a>
                                <div class="menu-divider"></div>
                                <a href="<?= app_url('logout.php'); ?>" style="color:#dc2626;">
                                    <i class="fa-solid fa-right-from-bracket"></i> Sign Out
                                </a>
                            </div>
                        </div>
                    <?php elseif (!empty($_SESSION['admin_logged_in'])): ?>
                        <a href="<?= app_url('backend/admin/index.php'); ?>" class="btn-signin-nav" style="background:#e0f2fe; color:#0284c7;">
                            <i class="fa-solid fa-gauge-high"></i>
                            <span>Admin</span>
                        </a>
                    <?php else: ?>
                        <div class="nav-user-wrapper" id="nav-user-dropdown-container">
                            <a href="<?= app_url('login.php'); ?>" class="btn-signin-nav">
                                <i class="fa-solid fa-user"></i>
                                <span>Sign In</span>
                                <i class="fa-solid fa-chevron-down" style="font-size:0.65rem; opacity:0.5; margin-left:-2px;"></i>
                            </a>
                            <div class="nav-user-menu">
                                <a href="<?= app_url('login.php?tab=login'); ?>">
                                    <i class="fa-solid fa-arrow-right-to-bracket" style="color:var(--brand-teal);"></i> Sign In
                                </a>
                                <a href="<?= app_url('login.php?tab=register'); ?>">
                                    <i class="fa-solid fa-user-plus" style="color:#059669;"></i> Create Account (Sign Up)
                                </a>
                                <a href="<?= app_url('auth/google-login.php'); ?>">
                                    <svg width="15" height="15" viewBox="0 0 24 24" style="flex-shrink:0;">
                                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                    </svg>
                                    <span>Continue with Google</span>
                                </a>
                                <div class="menu-divider"></div>
                                <a href="<?= app_url('backend/admin/login.php'); ?>" style="font-size:0.8rem; color:#64748b;">
                                    <i class="fa-solid fa-user-shield"></i> Pharmacist Back-Office
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Mobile Menu Toggle Button -->
                    <button type="button" class="nav-icon-btn mobile-menu-toggle" onclick="toggleMobileMenu();" title="Toggle Navigation Menu">
                        <i class="fa-solid fa-bars" id="mobile-menu-icon"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation Drawer -->
        <div class="mobile-drawer" id="mobile-drawer">
            <a href="<?= app_url('medicines.php'); ?>"><i class="fa-solid fa-pills" style="color:var(--brand-teal);"></i> Browse Medicines</a>
            <a href="<?= app_url('upload-prescription.php'); ?>"><i class="fa-solid fa-file-prescription" style="color:var(--brand-teal);"></i> Upload Prescription</a>
            <a href="<?= app_url('track-order.php'); ?>"><i class="fa-solid fa-truck-fast" style="color:var(--brand-teal);"></i> Track Order</a>
            <a href="<?= app_url('contact.php'); ?>"><i class="fa-solid fa-user-doctor" style="color:var(--brand-teal);"></i> 24/7 Doctor Consult</a>
            <a href="<?= app_url('cart.php'); ?>"><i class="fa-solid fa-bag-shopping" style="color:var(--brand-teal);"></i> Cart (<?= $cartCount; ?> items)</a>
            <div style="border-top:1px solid #e2e8f0; margin:8px 0;"></div>
            <?php if (!empty($_SESSION['customer_id'])): ?>
                <a href="<?= app_url('account.php'); ?>"><i class="fa-solid fa-user" style="color:var(--brand-teal);"></i> My Account (<?= Security::e($_SESSION['customer_name']); ?>)</a>
                <a href="<?= app_url('logout.php'); ?>" style="color:#dc2626;"><i class="fa-solid fa-right-from-bracket"></i> Sign Out</a>
            <?php else: ?>
                <a href="<?= app_url('login.php?tab=login'); ?>"><i class="fa-solid fa-arrow-right-to-bracket" style="color:var(--brand-teal);"></i> Sign In</a>
                <a href="<?= app_url('login.php?tab=register'); ?>"><i class="fa-solid fa-user-plus" style="color:#059669;"></i> Create Account</a>
                <a href="<?= app_url('auth/google-login.php'); ?>"><i class="fa-brands fa-google" style="color:#ea4335;"></i> Continue with Google</a>
            <?php endif; ?>
            <div style="border-top:1px solid #e2e8f0; margin:8px 0;"></div>
            <a href="<?= app_url('backend/admin/login.php'); ?>" style="font-size:0.85rem; color:#64748b;"><i class="fa-solid fa-lock" style="color:#64748b;"></i> Pharmacist Back-Office</a>
        </div>
    </header>
