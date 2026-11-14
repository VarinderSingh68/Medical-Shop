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

if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    header("Location: " . app_url('cart.php'));
    exit;
}

$subtotal = 0.0;
$hasRxRequired = false;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += ($item['price'] * $item['quantity']);
    if (!empty($item['requires_prescription'])) {
        $hasRxRequired = true;
    }
}

$standardDeliveryFee = (float)$settingModel->get('delivery_fee', '40.00');
$freeDeliveryAbove = (float)$settingModel->get('free_delivery_above', '500.00');
$deliveryFee = ($subtotal >= $freeDeliveryAbove) ? 0.0 : $standardDeliveryFee;
$totalAmount = $subtotal + $deliveryFee;

$error = '';
$csrfToken = Security::generateCsrf();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $customerName = Security::sanitizeString($_POST['customer_name'] ?? '');
        $phone = Security::sanitizeString($_POST['phone'] ?? '');
        $deliveryAddress = Security::sanitizeString($_POST['delivery_address'] ?? '');
        $city = Security::sanitizeString($_POST['city'] ?? '');
        $pincode = Security::sanitizeString($_POST['pincode'] ?? '');
        $doctorNotes = Security::sanitizeString($_POST['doctor_notes'] ?? '');
        $paymentMethod = Security::sanitizeString($_POST['payment_method'] ?? 'Cash On Delivery');

        $rxFilename = null;
        if (!empty($_FILES['prescription']['name'])) {
            $rxFilename = Uploader::uploadPrescription($_FILES['prescription']);
        }

        if ($hasRxRequired && empty($rxFilename)) {
            $error = 'One or more medicines in your cart require a Doctor\'s Prescription. Please upload a clear photo or PDF.';
        } elseif (empty($customerName) || empty($phone) || empty($deliveryAddress) || empty($city) || empty($pincode)) {
            $error = 'Please fill all required delivery address fields.';
        } else {
            $orderData = [
                'customer_name'     => $customerName,
                'phone'             => $phone,
                'delivery_address'  => $deliveryAddress,
                'city'              => $city,
                'pincode'           => $pincode,
                'doctor_notes'      => $doctorNotes,
                'subtotal'          => $subtotal,
                'delivery_fee'      => $deliveryFee,
                'total_amount'      => $totalAmount,
                'payment_method'    => $paymentMethod,
                'payment_status'    => 'Pending',
                'order_status'      => 'Pending Review',
                'prescription_file' => $rxFilename
            ];

            $orderCode = $orderModel->create($orderData, $_SESSION['cart']);
            $_SESSION['cart'] = [];
            $_SESSION['last_order_code'] = $orderCode;

            header("Location: " . app_url("order-success.php?code=" . urlencode($orderCode)));
            exit;
        }
    }
}

$pageTitle = 'Checkout & Delivery Details';
require_once __DIR__ . '/layout/header.php';
?>

<div class="container" style="padding-top:35px; padding-bottom:60px;">
    <h1 style="font-size:2rem; font-weight:800; color:var(--text-heading); margin-bottom:8px;">
        <i class="fa-solid fa-house-chimney-medical" style="color:var(--brand-teal);"></i> Doorstep Delivery Details
    </h1>
    <p style="color:var(--text-muted); margin-bottom:28px;">Provide your patient delivery address for rapid medicine fulfillment</p>

    <?php if (!empty($error)): ?>
        <div style="background:#fee2e2; border-left:4px solid #ef4444; color:#991b1b; padding:14px 20px; border-radius:8px; margin-bottom:24px; font-weight:600;">
            <i class="fa-solid fa-triangle-exclamation"></i> <?= Security::e($error); ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">

        <div style="display:grid; grid-template-columns:1.6fr 1fr; gap:30px; align-items:start;">
            <!-- Left: Delivery Form -->
            <div style="background:#fff; border:1px solid var(--border-color); border-radius:var(--radius-card); padding:30px; box-shadow:var(--shadow-subtle);">
                <h3 style="font-size:1.15rem; font-weight:800; color:var(--text-heading); margin-bottom:20px; border-bottom:1px solid var(--border-color); padding-bottom:10px;">
                    1. Patient & Contact Information
                </h3>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Full Name *</label>
                        <input type="text" name="customer_name" required placeholder="e.g. Ramesh Kumar" style="width:100%; padding:10px 14px; border:1px solid var(--border-color); border-radius:8px; font-size:0.95rem; outline:none;" value="<?= Security::e($_POST['customer_name'] ?? ''); ?>">
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Mobile Phone *</label>
                        <input type="tel" name="phone" required placeholder="e.g. +91 98765 43210" style="width:100%; padding:10px 14px; border:1px solid var(--border-color); border-radius:8px; font-size:0.95rem; outline:none;" value="<?= Security::e($_POST['phone'] ?? ''); ?>">
                    </div>
                </div>

                <h3 style="font-size:1.15rem; font-weight:800; color:var(--text-heading); margin:20px 0 16px; border-bottom:1px solid var(--border-color); padding-bottom:10px;">
                    2. Home Delivery Address
                </h3>

                <div style="margin-bottom:16px;">
                    <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Street Address (Flat / House No, Building, Landmark) *</label>
                    <textarea name="delivery_address" rows="3" required placeholder="Flat 402, Sunshine Apartments, Near City Hospital" style="width:100%; padding:10px 14px; border:1px solid var(--border-color); border-radius:8px; font-size:0.95rem; outline:none; font-family:inherit;"><?= Security::e($_POST['delivery_address'] ?? ''); ?></textarea>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">City / District *</label>
                        <input type="text" name="city" required placeholder="City" style="width:100%; padding:10px 14px; border:1px solid var(--border-color); border-radius:8px; font-size:0.95rem; outline:none;" value="<?= Security::e($_POST['city'] ?? ''); ?>">
                    </div>

                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Postal PIN Code *</label>
                        <input type="text" name="pincode" required placeholder="e.g. 400001" style="width:100%; padding:10px 14px; border:1px solid var(--border-color); border-radius:8px; font-size:0.95rem; outline:none;" value="<?= Security::e($_POST['pincode'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Prescription Upload Section -->
                <h3 style="font-size:1.15rem; font-weight:800; color:var(--text-heading); margin:20px 0 16px; border-bottom:1px solid var(--border-color); padding-bottom:10px;">
                    3. Doctor's Prescription <?= $hasRxRequired ? '<span style="color:#ef4444; font-size:0.85rem;">(Required)</span>' : '<span style="color:var(--text-muted); font-size:0.85rem;">(Optional)</span>'; ?>
                </h3>

                <div class="rx-dropzone" onclick="document.getElementById('prescription-input').click();">
                    <i class="fa-solid fa-camera" style="font-size:2rem; color:var(--brand-teal); margin-bottom:8px;"></i>
                    <p style="margin:0; font-weight:700; font-size:0.95rem; color:var(--text-heading);">Click or Drag & Drop Doctor's Prescription Photo or PDF</p>
                    <span style="font-size:0.8rem; color:var(--text-muted);">JPG, PNG, WEBP, or PDF (Max 10MB)</span>
                    <input type="file" id="prescription-input" name="prescription" accept="image/*,application/pdf" style="display:none;" <?= $hasRxRequired ? 'required' : ''; ?>>
                    <div id="rx-filename" style="margin-top:10px; font-weight:700; color:var(--brand-teal);"></div>
                    <img id="prescription-preview" style="max-height:160px; max-width:100%; margin:12px auto 0; border-radius:8px; display:none; border:1px solid #cbd5e1;">
                </div>

                <h3 style="font-size:1.15rem; font-weight:800; color:var(--text-heading); margin:24px 0 16px; border-bottom:1px solid var(--border-color); padding-bottom:10px;">
                    4. Payment Preference
                </h3>

                <div class="payment-selector-grid">
                    <label class="payment-card-label">
                        <input type="radio" name="payment_method" value="Cash On Delivery" checked>
                        <div>
                            <strong>Cash on Delivery (COD)</strong>
                            <div style="font-size:0.8rem; color:var(--text-muted);">Pay upon doorstep delivery</div>
                        </div>
                    </label>

                    <label class="payment-card-label">
                        <input type="radio" name="payment_method" value="UPI / Card on Delivery">
                        <div>
                            <strong>UPI / Card on Delivery</strong>
                            <div style="font-size:0.8rem; color:var(--text-muted);">GPay, PhonePe, Paytm, Debit/Credit Card</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Right: Order Breakdown -->
            <div style="background:#fff; border:1px solid var(--border-color); border-radius:var(--radius-card); padding:24px; box-shadow:var(--shadow-subtle); position:sticky; top:90px;">
                <h3 style="font-size:1.15rem; font-weight:800; color:var(--text-heading); margin-bottom:16px;">Order Summary</h3>

                <div style="max-height:220px; overflow-y:auto; margin-bottom:16px; padding-right:6px;">
                    <?php foreach ($_SESSION['cart'] as $item): ?>
                        <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:0.9rem;">
                            <div>
                                <strong><?= (int)$item['quantity']; ?>x</strong> <?= Security::e($item['name']); ?>
                                <span style="font-size:0.75rem; color:var(--text-muted); display:block;"><?= Security::e($item['strength']); ?></span>
                            </div>
                            <span><?= format_price($item['price'] * $item['quantity']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="border-top:1px solid var(--border-color); padding-top:12px; margin-bottom:10px; display:flex; justify-content:space-between; font-size:0.95rem;">
                    <span style="color:var(--text-muted);">Medicines Subtotal:</span>
                    <strong><?= format_price($subtotal); ?></strong>
                </div>

                <div style="display:flex; justify-content:space-between; margin-bottom:14px; font-size:0.95rem;">
                    <span style="color:var(--text-muted);">Delivery Charge:</span>
                    <strong><?= $deliveryFee === 0.0 ? '<span style="color:var(--brand-teal);">FREE</span>' : format_price($deliveryFee); ?></strong>
                </div>

                <div style="border-top:2px solid var(--border-color); padding-top:14px; display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <span style="font-size:1.05rem; font-weight:700;">Total Payable:</span>
                    <span style="font-size:1.6rem; font-weight:800; color:var(--text-heading);"><?= format_price($totalAmount); ?></span>
                </div>

                <button type="submit" class="btn-hero-upload" style="width:100%; justify-content:center; padding:15px; font-size:1.05rem; border-radius:var(--radius-pill);">
                    <i class="fa-solid fa-check-circle"></i> Confirm Home Delivery Order
                </button>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
