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

                    <!-- Sign In / Admin Portal -->
                    <?php if (!empty($_SESSION['admin_logged_in'])): ?>
                        <a href="<?= app_url('backend/admin/index.php'); ?>" class="btn-signin-nav" style="background:#e0f2fe; color:#0284c7;">
                            <i class="fa-solid fa-gauge-high"></i>
                            <span>Admin</span>
                        </a>
                    <?php else: ?>
                        <a href="<?= app_url('backend/admin/login.php'); ?>" class="btn-signin-nav">
                            <i class="fa-solid fa-user"></i>
                            <span>Sign In</span>
                        </a>
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
            <a href="<?= app_url('backend/admin/login.php'); ?>"><i class="fa-solid fa-lock" style="color:var(--brand-teal);"></i> Pharmacist Back-Office</a>
        </div>
    </header>
