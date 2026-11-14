<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/core/Security.php';
require_once __DIR__ . '/../backend/models/Order.php';
require_once __DIR__ . '/../backend/models/Setting.php';

use App\Core\Security;
use App\Models\Order;
use App\Models\Setting;

$db = DatabaseConnection::getInstance();
$orderModel = new Order($db);
$settingModel = new Setting($db);

$orderCode = trim($_GET['code'] ?? '');
$order = !empty($orderCode) ? $orderModel->findByCode($orderCode) : null;

$pageTitle = 'Order Placed Successfully';
require_once __DIR__ . '/layout/header.php';
?>

<div class="container" style="padding-top:50px; padding-bottom:80px; max-width:700px;">
    <div style="background:#fff; border:1px solid var(--border-color); border-radius:var(--radius-card); padding:45px 35px; text-align:center; box-shadow:var(--shadow-subtle);">
        <div class="success-check-badge">
            <i class="fa-solid fa-check"></i>
        </div>

        <h1 style="font-size:2rem; font-weight:800; color:var(--text-heading); margin-bottom:8px;">
            Home Delivery Order Placed!
        </h1>
        <p style="color:var(--text-muted); font-size:1.05rem; margin-bottom:28px;">
            Our registered pharmacist has received your medicine request and is preparing your order for doorstep dispatch.
        </p>

        <!-- Order Tracking Code Pill -->
        <div style="background:var(--brand-teal-light); border:2px dashed var(--brand-teal); border-radius:14px; padding:24px; margin-bottom:28px;">
            <span style="font-size:0.85rem; color:#0f766e; font-weight:700; text-transform:uppercase;">Your Tracking Code</span>
            <div style="font-size:2.4rem; font-weight:800; color:var(--brand-teal); margin:6px 0; letter-spacing:1.5px;" id="order-code">
                <?= Security::e($orderCode); ?>
            </div>
            <button type="button" onclick="navigator.clipboard.writeText('<?= Security::e($orderCode); ?>'); if(typeof showToast==='function') showToast('Tracking Code Copied!', 'Code: <?= Security::e($orderCode); ?>');" style="background:#ffffff; border:1px solid var(--brand-teal); color:var(--brand-teal); padding:6px 16px; border-radius:20px; font-weight:700; font-size:0.82rem; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:all 0.2s;" onmouseover="this.style.background='var(--brand-teal)'; this.style.color='#fff';" onmouseout="this.style.background='#fff'; this.style.color='var(--brand-teal)';">
                <i class="fa-regular fa-copy"></i> Copy Tracking Code
            </button>
            <div style="font-size:0.8rem; color:var(--text-muted); margin-top:8px;">Save this code to check your live home delivery status anytime</div>
        </div>

        <?php if ($order): 
            $isOwnerSession = (!empty($_SESSION['last_order_code']) && hash_equals($_SESSION['last_order_code'], $orderCode));
            $displayName = $isOwnerSession ? $order['customer_name'] : Security::maskName($order['customer_name']);
            $displayPhone = $isOwnerSession ? $order['phone'] : Security::maskPhone($order['phone']);
            $displayAddress = $isOwnerSession ? ($order['delivery_address'] . ', ' . $order['city']) : ($order['city'] . ' - ' . $order['pincode']);
        ?>
            <div style="background:#f8fafc; border-radius:12px; padding:20px; text-align:left; margin-bottom:28px; font-size:0.95rem; border:1px solid var(--border-color);">
                <h4 style="margin-bottom:10px; color:var(--text-heading);"><i class="fa-solid fa-truck" style="color:var(--brand-teal);"></i> Delivery Information</h4>
                <p style="margin:4px 0;"><strong>Patient:</strong> <?= Security::e($displayName); ?> (<?= Security::e($displayPhone); ?>)</p>
                <p style="margin:4px 0;"><strong>Doorstep Address:</strong> <?= Security::e($displayAddress); ?> - <?= Security::e($order['pincode']); ?></p>
                <p style="margin:4px 0;"><strong>Payment:</strong> <?= Security::e($order['payment_method']); ?> (Total: <strong><?= format_price($order['total_amount']); ?></strong>)</p>
                <p style="margin:4px 0;"><strong>Current Status:</strong> <span style="background:#e0f2fe; color:#0284c7; padding:2px 8px; border-radius:10px; font-weight:700; font-size:0.8rem;"><?= Security::e($order['order_status']); ?></span></p>
            </div>
        <?php endif; ?>

        <div style="display:flex; justify-content:center; gap:14px; flex-wrap:wrap;">
            <a href="<?= app_url('track-order.php?code=' . urlencode($orderCode)); ?>" class="btn-hero-upload" style="padding:14px 28px; font-size:1rem; border-radius:var(--radius-pill);">
                <i class="fa-solid fa-motorcycle"></i> Track Home Delivery
            </a>
            <a href="<?= app_url('index.php'); ?>" class="btn-signin-nav" style="border:1px solid #cbd5e1; padding:14px 28px; font-size:1rem; border-radius:var(--radius-pill);">
                <i class="fa-solid fa-arrow-left"></i> Return to Homepage
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
