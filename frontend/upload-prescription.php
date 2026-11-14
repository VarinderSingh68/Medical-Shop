<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/core/Security.php';
require_once __DIR__ . '/../backend/core/Uploader.php';
require_once __DIR__ . '/../backend/models/Order.php';
require_once __DIR__ . '/../backend/models/Setting.php';

use App\Core\Security;
use App\Core\Uploader;
use App\Models\Order;
use App\Models\Setting;

$db = DatabaseConnection::getInstance();
$orderModel = new Order($db);
$settingModel = new Setting($db);

$error = '';
$csrfToken = Security::generateCsrf();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh and try again.';
    } elseif (!Security::checkRateLimit('rx_upload', 5, 600)) {
        $error = 'Upload rate limit reached (max 5 uploads per 10 minutes). Please wait before trying again.';
    } else {
        $customerName = Security::sanitizeString($_POST['customer_name'] ?? '');
        $phone = Security::sanitizeString($_POST['phone'] ?? '');
        $deliveryAddress = Security::sanitizeString($_POST['delivery_address'] ?? '');
        $city = Security::sanitizeString($_POST['city'] ?? '');
        $pincode = Security::sanitizeString($_POST['pincode'] ?? '');
        $doctorNotes = Security::sanitizeString($_POST['doctor_notes'] ?? '');

        // Upload prescription
        $rxFilename = Uploader::uploadPrescription($_FILES['prescription'] ?? []);

        if (empty($customerName) || empty($phone) || empty($deliveryAddress) || empty($city) || empty($pincode)) {
            $error = 'Please fill all required patient and delivery address fields.';
        } elseif (!$rxFilename) {
            $error = 'Please upload a valid prescription file (JPG, PNG, WEBP, or PDF up to 10MB).';
        } else {
            $standardDeliveryFee = (float)$settingModel->get('delivery_fee', '40.00');

            $orderData = [
                'customer_name'     => $customerName,
                'phone'             => $phone,
                'delivery_address'  => $deliveryAddress,
                'city'              => $city,
                'pincode'           => $pincode,
                'doctor_notes'      => $doctorNotes,
                'subtotal'          => 0.00,
                'delivery_fee'      => $standardDeliveryFee,
                'total_amount'      => $standardDeliveryFee,
                'payment_method'    => 'Cash On Delivery',
                'payment_status'    => 'Pending',
                'order_status'      => 'Pending Review',
                'prescription_file' => $rxFilename
            ];

            $orderCode = $orderModel->create($orderData, []);
            $_SESSION['last_order_code'] = $orderCode;

            header("Location: " . app_url("order-success.php?code=" . urlencode($orderCode)));
            exit;
        }
    }
}

$pageTitle = 'Upload Doctor Prescription for Home Delivery';
require_once __DIR__ . '/layout/header.php';
?>

<div class="container" style="padding-top:40px; padding-bottom:80px; max-width:760px;">
    <div style="background:#fff; border:1px solid var(--border-color); border-radius:var(--radius-card); padding:40px; box-shadow:var(--shadow-subtle);">
        <h1 style="font-size:2rem; font-weight:800; color:var(--text-heading); margin-bottom:8px; display:flex; align-items:center; gap:12px;">
            <i class="fa-solid fa-file-prescription" style="color:var(--brand-teal);"></i> 1-Click Prescription Home Delivery
        </h1>
        <p style="color:var(--text-muted); margin-bottom:28px;">
            Upload your doctor's prescription photo or PDF. Our registered pharmacist verifies dosage and delivers the medicine directly to your doorstep.
        </p>

        <?php if (!empty($error)): ?>
            <div style="background:#fee2e2; border-left:4px solid #ef4444; color:#991b1b; padding:14px 20px; border-radius:8px; margin-bottom:24px; font-weight:600;">
                <i class="fa-solid fa-triangle-exclamation"></i> <?= Security::e($error); ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">

            <!-- Prescription Upload Area -->
            <div style="margin-bottom:28px;">
                <label style="display:block; font-weight:700; font-size:1rem; color:var(--text-heading); margin-bottom:8px;">
                    1. Upload Doctor's Prescription *
                </label>
                <div class="rx-dropzone" onclick="document.getElementById('prescription-input').click();">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size:2.8rem; color:var(--brand-teal); margin-bottom:12px;"></i>
                    <h3 style="font-size:1.15rem; color:var(--text-heading); margin-bottom:4px;">Click or Drag & Drop Doctor's Prescription Here</h3>
                    <p style="font-size:0.85rem; color:var(--text-muted); margin:0;">Supports JPG, PNG, WEBP, and PDF (Max 10MB)</p>
                    <input type="file" id="prescription-input" name="prescription" accept="image/*,application/pdf" style="display:none;" required>
                    <div id="rx-filename" style="margin-top:12px; font-weight:700; color:var(--brand-teal);"></div>
                    <img id="prescription-preview" style="max-height:200px; max-width:100%; margin:16px auto 0; border-radius:8px; display:none; border:1px solid #cbd5e1;">
                </div>
            </div>

            <!-- Patient Information -->
            <h3 style="font-size:1.15rem; font-weight:800; color:var(--text-heading); margin-bottom:16px; border-bottom:1px solid var(--border-color); padding-bottom:8px;">
                2. Patient & Delivery Information
            </h3>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Patient Full Name *</label>
                    <input type="text" name="customer_name" required placeholder="e.g. Rahul Sharma" style="width:100%; padding:10px 14px; border:1px solid var(--border-color); border-radius:8px; font-size:0.95rem; outline:none;" value="<?= Security::e($_POST['customer_name'] ?? ''); ?>">
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Contact Phone Number *</label>
                    <input type="tel" name="phone" required placeholder="e.g. +91 98765 43210" style="width:100%; padding:10px 14px; border:1px solid var(--border-color); border-radius:8px; font-size:0.95rem; outline:none;" value="<?= Security::e($_POST['phone'] ?? ''); ?>">
                </div>
            </div>

            <div style="margin-bottom:16px;">
                <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Home Delivery Address *</label>
                <textarea name="delivery_address" rows="3" required placeholder="Flat/House number, Floor, Apartment name, Street, Landmark" style="width:100%; padding:10px 14px; border:1px solid var(--border-color); border-radius:8px; font-size:0.95rem; outline:none; font-family:inherit;"><?= Security::e($_POST['delivery_address'] ?? ''); ?></textarea>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">City / Town *</label>
                    <input type="text" name="city" required placeholder="City" style="width:100%; padding:10px 14px; border:1px solid var(--border-color); border-radius:8px; font-size:0.95rem; outline:none;" value="<?= Security::e($_POST['city'] ?? ''); ?>">
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Postal PIN Code *</label>
                    <input type="text" name="pincode" required placeholder="e.g. 400001" style="width:100%; padding:10px 14px; border:1px solid var(--border-color); border-radius:8px; font-size:0.95rem; outline:none;" value="<?= Security::e($_POST['pincode'] ?? ''); ?>">
                </div>
            </div>

            <div style="margin-bottom:24px;">
                <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Disease Details / Special Instructions</label>
                <textarea name="doctor_notes" rows="2" placeholder="e.g. Please send 1 month supply; patient is diabetic" style="width:100%; padding:10px 14px; border:1px solid var(--border-color); border-radius:8px; font-size:0.95rem; outline:none; font-family:inherit;"><?= Security::e($_POST['doctor_notes'] ?? ''); ?></textarea>
            </div>

            <div style="background:#f8fafc; border:1px solid var(--border-color); border-radius:12px; padding:16px; margin-bottom:24px; display:flex; align-items:center; gap:14px;">
                <i class="fa-solid fa-hand-holding-dollar" style="font-size:1.8rem; color:var(--brand-teal);"></i>
                <div style="font-size:0.88rem;">
                    <strong style="color:var(--text-heading);">Cash / UPI on Doorstep Delivery:</strong>
                    <div style="color:var(--text-muted);">Our pharmacist will call you with the medicine bill before dispatching. You pay only upon arrival.</div>
                </div>
            </div>

            <button type="submit" class="btn-hero-upload" style="width:100%; justify-content:center; padding:16px; font-size:1.05rem; border-radius:var(--radius-pill);">
                <i class="fa-solid fa-truck-medical"></i> Submit Prescription for Doorstep Delivery
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
