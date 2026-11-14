<?php
declare(strict_types=1);

$pageTitle = 'Pharmacy Store Settings';
require_once __DIR__ . '/layout/header.php';
require_once __DIR__ . '/../models/Setting.php';

use App\Core\Security;
use App\Models\Setting;

$settingModel = new Setting($db);
$csrfToken = Security::generateCsrf();

// Handle Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
        Security::setFlash('danger', 'Security validation expired.');
    } else {
        $data = [
            'shop_name'           => Security::sanitizeString($_POST['shop_name'] ?? ''),
            'tagline'             => Security::sanitizeString($_POST['tagline'] ?? ''),
            'phone'               => Security::sanitizeString($_POST['phone'] ?? ''),
            'emergency_hotline'   => Security::sanitizeString($_POST['emergency_hotline'] ?? ''),
            'email'               => Security::sanitizeString($_POST['email'] ?? ''),
            'address'             => Security::sanitizeString($_POST['address'] ?? ''),
            'drug_license_no'     => Security::sanitizeString($_POST['drug_license_no'] ?? ''),
            'delivery_fee'        => Security::sanitizeFloat($_POST['delivery_fee'] ?? 40.0),
            'free_delivery_above' => Security::sanitizeFloat($_POST['free_delivery_above'] ?? 500.0),
            'notice_ticker'       => Security::sanitizeString($_POST['notice_ticker'] ?? ''),
            'google_client_id'     => trim($_POST['google_client_id'] ?? ''),
            'google_client_secret' => trim($_POST['google_client_secret'] ?? ''),
            'google_redirect_uri'  => trim($_POST['google_redirect_uri'] ?? ''),
        ];

        $settingModel->updateBulk($data);
        Security::setFlash('success', 'Store settings and home delivery policies updated successfully.');
        header("Location: settings.php");
        exit;
    }
}

$settings = $settingModel->getAll();
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <h3 style="color:var(--secondary); font-size:1.4rem;">Store & Home Delivery Settings</h3>
        <span style="font-size:0.85rem; color:var(--text-muted);">Configure public pharmacy information, delivery fees, emergency numbers & pharmacy compliance license</span>
    </div>
</div>

<div class="card" style="max-width:900px;">
    <form action="" method="POST">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">

        <h4 style="color:var(--primary); margin-bottom:16px; border-bottom:1px solid var(--border); padding-bottom:8px;">
            <i class="fa-solid fa-store"></i> General Pharmacy Identity
        </h4>

        <div class="form-grid">
            <div class="form-group">
                <label>Pharmacy / Medical Store Name *</label>
                <input type="text" name="shop_name" class="form-control" required value="<?= Security::e($settings['shop_name'] ?? 'Medix 24/7 Super-Specialty Home Pharmacy'); ?>">
            </div>
            <div class="form-group">
                <label>Tagline / Slogan</label>
                <input type="text" name="tagline" class="form-control" value="<?= Security::e($settings['tagline'] ?? 'Prescription & Emergency Medicines Delivered to Your Doorstep'); ?>">
            </div>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label>Store Phone Number (Customer Care) *</label>
                <input type="text" name="phone" class="form-control" required value="<?= Security::e($settings['phone'] ?? '+91 98765 43210'); ?>">
            </div>
            <div class="form-group">
                <label>24/7 Emergency Delivery Helpline *</label>
                <input type="text" name="emergency_hotline" class="form-control" required value="<?= Security::e($settings['emergency_hotline'] ?? '+91 1800 123 4567'); ?>">
            </div>
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label>Official Pharmacy Email</label>
                <input type="email" name="email" class="form-control" value="<?= Security::e($settings['email'] ?? 'support@medixpharmacy.com'); ?>">
            </div>
            <div class="form-group">
                <label>Pharmacy Drug License Registration No *</label>
                <input type="text" name="drug_license_no" class="form-control" required value="<?= Security::e($settings['drug_license_no'] ?? 'DL-2026-MEDIX-7890'); ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Physical Pharmacy Store Address *</label>
            <textarea name="address" class="form-control" rows="2" required><?= Security::e($settings['address'] ?? '124 Healthcare Avenue, Medical City Hub'); ?></textarea>
        </div>

        <h4 style="color:var(--primary); margin:24px 0 16px; border-bottom:1px solid var(--border); padding-bottom:8px;">
            <i class="fa-solid fa-truck-fast"></i> Home Delivery Pricing & Thresholds
        </h4>

        <div class="form-grid">
            <div class="form-group">
                <label>Standard Home Delivery Charge (₹) *</label>
                <input type="number" step="0.01" name="delivery_fee" class="form-control" required value="<?= Security::e((string)($settings['delivery_fee'] ?? '40.00')); ?>">
                <span style="font-size:0.75rem; color:var(--text-muted);">Charged if order value is below free threshold</span>
            </div>
            <div class="form-group">
                <label>Free Home Delivery on Orders Above (₹) *</label>
                <input type="number" step="0.01" name="free_delivery_above" class="form-control" required value="<?= Security::e((string)($settings['free_delivery_above'] ?? '500.00')); ?>">
                <span style="font-size:0.75rem; color:var(--text-muted);">Zero delivery charge when cart value exceeds this</span>
            </div>
        </div>

        <h4 style="color:var(--primary); margin:24px 0 16px; border-bottom:1px solid var(--border); padding-bottom:8px;">
            <i class="fa-solid fa-bullhorn"></i> Live Website Notice & Announcement Ticker
        </h4>

        <div class="form-group">
            <label>Top Banner Notice (Displayed across customer site)</label>
            <textarea name="notice_ticker" class="form-control" rows="2"><?= Security::e($settings['notice_ticker'] ?? 'Free Emergency Home Delivery for Senior Citizens & Chronic Care Patients! Upload prescription for fast verification.'); ?></textarea>
        </div>

        <h4 style="color:var(--primary); margin:24px 0 16px; border-bottom:1px solid var(--border); padding-bottom:8px;">
            <i class="fa-brands fa-google"></i> Google OAuth 2.0 Customer Sign-In
        </h4>

        <div class="form-grid">
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Google OAuth Client ID</label>
                <input type="text" name="google_client_id" class="form-control" value="<?= Security::e($settings['google_client_id'] ?? ''); ?>" placeholder="e.g. 1003359995625-...apps.googleusercontent.com">
                <span style="font-size:0.75rem; color:var(--text-muted);">From Google Cloud Console ➔ APIs & Services ➔ Credentials</span>
            </div>
            <div class="form-group">
                <label>Google OAuth Client Secret</label>
                <input type="password" name="google_client_secret" class="form-control" value="<?= Security::e($settings['google_client_secret'] ?? ''); ?>" placeholder="GOCSPX-...">
            </div>
            <div class="form-group">
                <label>Google Redirect URI</label>
                <input type="text" name="google_redirect_uri" class="form-control" value="<?= Security::e($settings['google_redirect_uri'] ?? app_url('auth/google-callback.php')); ?>">
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="padding:14px 28px; font-size:1rem;">
            <i class="fa-solid fa-check"></i> Save Store Configurations
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
