<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/core/Security.php';
require_once __DIR__ . '/../backend/models/Order.php';

use App\Core\Security;
use App\Models\Order;

$db = DatabaseConnection::getInstance();
$orderModel = new Order($db);

$orderCode = trim($_GET['code'] ?? '');
$order = !empty($orderCode) ? $orderModel->findByCode($orderCode) : null;

$pageTitle = 'Track Medicine Home Delivery';
require_once __DIR__ . '/layout/header.php';
?>

<div class="container" style="padding-top:40px; padding-bottom:80px; max-width:820px;">
    <div style="background:#fff; border:1px solid var(--border-color); border-radius:var(--radius-card); padding:40px; box-shadow:var(--shadow-subtle);">
        <h1 style="font-size:2rem; font-weight:800; color:var(--text-heading); margin-bottom:8px; display:flex; align-items:center; gap:12px;">
            <i class="fa-solid fa-truck-fast" style="color:var(--brand-teal);"></i> Track Medicine Home Delivery
        </h1>
        <p style="color:var(--text-muted); margin-bottom:28px;">Enter your order code to monitor live dispatch from our registered pharmacy to your doorstep</p>

        <!-- Search Form -->
        <form method="GET" style="display:flex; gap:12px; margin-bottom:35px;">
            <input type="text" name="code" value="<?= Security::e($orderCode); ?>" placeholder="Enter Order Code (e.g. MED-A1B2C3)" required style="flex:1; border:1px solid var(--border-color); border-radius:var(--radius-pill); padding:12px 20px; font-size:1rem; outline:none;">
            <button type="submit" class="btn-hero-upload" style="border:none; cursor:pointer;">
                <i class="fa-solid fa-magnifying-glass"></i> Track Order
            </button>
        </form>

        <?php if ($order): ?>
            <?php
                $statusSequence = [
                    'Pending Review'      => 1,
                    'Pharmacist Verified' => 2,
                    'Packed'              => 3,
                    'Out for Delivery'    => 4,
                    'Delivered'           => 5,
                    'Cancelled'           => -1
                ];
                $currentStep = $statusSequence[$order['order_status']] ?? 1;
            ?>

            <!-- Order Header Card -->
            <div style="background:#f8fafc; border:1px solid var(--border-color); border-radius:12px; padding:20px; margin-bottom:30px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
                <div>
                    <span style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase; font-weight:700;">Order Code</span>
                    <div style="font-size:1.5rem; font-weight:800; color:var(--brand-teal);">#<?= Security::e($order['order_code']); ?></div>
                    <span style="font-size:0.85rem; color:var(--text-muted);">Ordered on <?= Security::e(format_date($order['created_at'])); ?></span>
                </div>
                <div>
                    <span style="font-size:0.8rem; color:var(--text-muted); text-transform:uppercase; font-weight:700; display:block;">Current Status</span>
                    <span style="background:#e0f2fe; color:#0284c7; padding:6px 14px; border-radius:var(--radius-pill); font-weight:700; font-size:0.9rem; display:inline-block;">
                        <?= Security::e($order['order_status']); ?>
                    </span>
                </div>
            </div>

            <?php if ($order['order_status'] === 'Cancelled'): ?>
                <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:12px; padding:20px; text-align:center; color:#991b1b; margin-bottom:30px;">
                    <i class="fa-solid fa-circle-xmark" style="font-size:2.5rem; margin-bottom:10px;"></i>
                    <h3 style="margin-bottom:6px;">Order Has Been Cancelled</h3>
                    <p style="font-size:0.9rem; margin:0;">Contact our 24/7 pharmacist helpline if you require urgent re-dispatch.</p>
                </div>
            <?php else: ?>
                <!-- Visual Stepper with HomeMed teal color -->
                <div style="display:flex; justify-content:space-between; position:relative; margin:40px 0 50px;">
                    <div style="position:absolute; top:22px; left:40px; right:40px; height:4px; background:#e2e8f0; z-index:1;"></div>
                    <div style="position:absolute; top:22px; left:40px; width:<?= min(100, max(0, ($currentStep - 1) * 25)); ?>%; height:4px; background:var(--brand-teal); z-index:2; transition:width .4s ease;"></div>

                    <div style="position:relative; z-index:3; text-align:center; width:90px;">
                        <div style="width:44px; height:44px; border-radius:50%; background:<?= $currentStep >= 1 ? 'var(--brand-teal)' : '#e2e8f0'; ?>; color:#fff; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-weight:700; transition:all 0.3s; <?= $currentStep === 1 ? 'box-shadow: 0 0 0 6px rgba(0,192,139,0.25); animation: radarPulse 2s infinite;' : ''; ?>">
                            <i class="fa-solid fa-receipt"></i>
                        </div>
                        <span style="font-size:0.8rem; font-weight:700; color:<?= $currentStep >= 1 ? 'var(--brand-teal)' : 'var(--text-muted)'; ?>;">Order Received</span>
                    </div>

                    <div style="position:relative; z-index:3; text-align:center; width:90px;">
                        <div style="width:44px; height:44px; border-radius:50%; background:<?= $currentStep >= 2 ? 'var(--brand-teal)' : '#e2e8f0'; ?>; color:#fff; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-weight:700; transition:all 0.3s; <?= $currentStep === 2 ? 'box-shadow: 0 0 0 6px rgba(0,192,139,0.25); animation: radarPulse 2s infinite;' : ''; ?>">
                            <i class="fa-solid fa-user-doctor"></i>
                        </div>
                        <span style="font-size:0.8rem; font-weight:700; color:<?= $currentStep >= 2 ? 'var(--brand-teal)' : 'var(--text-muted)'; ?>;">Rx Verified</span>
                    </div>

                    <div style="position:relative; z-index:3; text-align:center; width:90px;">
                        <div style="width:44px; height:44px; border-radius:50%; background:<?= $currentStep >= 3 ? 'var(--brand-teal)' : '#e2e8f0'; ?>; color:#fff; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-weight:700; transition:all 0.3s; <?= $currentStep === 3 ? 'box-shadow: 0 0 0 6px rgba(0,192,139,0.25); animation: radarPulse 2s infinite;' : ''; ?>">
                            <i class="fa-solid fa-box-open"></i>
                        </div>
                        <span style="font-size:0.8rem; font-weight:700; color:<?= $currentStep >= 3 ? 'var(--brand-teal)' : 'var(--text-muted)'; ?>;">Packed</span>
                    </div>

                    <div style="position:relative; z-index:3; text-align:center; width:90px;">
                        <div style="width:44px; height:44px; border-radius:50%; background:<?= $currentStep >= 4 ? 'var(--brand-teal)' : '#e2e8f0'; ?>; color:#fff; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-weight:700; transition:all 0.3s; <?= $currentStep === 4 ? 'box-shadow: 0 0 0 6px rgba(0,192,139,0.25); animation: radarPulse 2s infinite;' : ''; ?>">
                            <i class="fa-solid fa-motorcycle"></i>
                        </div>
                        <span style="font-size:0.8rem; font-weight:700; color:<?= $currentStep >= 4 ? 'var(--brand-teal)' : 'var(--text-muted)'; ?>;">Out for Delivery</span>
                    </div>

                    <div style="position:relative; z-index:3; text-align:center; width:90px;">
                        <div style="width:44px; height:44px; border-radius:50%; background:<?= $currentStep >= 5 ? 'var(--brand-teal)' : '#e2e8f0'; ?>; color:#fff; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-weight:700; transition:all 0.3s; <?= $currentStep === 5 ? 'box-shadow: 0 0 0 6px rgba(0,192,139,0.25); animation: radarPulse 2s infinite;' : ''; ?>">
                            <i class="fa-solid fa-house-chimney-check"></i>
                        </div>
                        <span style="font-size:0.8rem; font-weight:700; color:<?= $currentStep >= 5 ? 'var(--brand-teal)' : 'var(--text-muted)'; ?>;">Delivered</span>
                    </div>
                </div>

                <?php if ($currentStep === 4): ?>
                    <!-- Live Rider Dispatch Banner for Out for Delivery -->
                    <div style="background:linear-gradient(135deg, #e6f9f3 0%, #e0f2fe 100%); border:1px solid rgba(0, 192, 139, 0.3); border-radius:14px; padding:20px; margin-bottom:28px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px;">
                        <div style="display:flex; align-items:center; gap:14px;">
                            <div style="width:50px; height:50px; border-radius:50%; background:var(--brand-teal); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.4rem; box-shadow:0 0 0 6px rgba(0,192,139,0.2); animation:radarPulse 2s infinite;">
                                <i class="fa-solid fa-motorcycle"></i>
                            </div>
                            <div>
                                <strong style="font-size:1.05rem; color:var(--text-heading); display:block;">Pharmacy Rider On The Way!</strong>
                                <span style="font-size:0.85rem; color:#0f766e;">Temperature-controlled delivery kit &bull; Est. Arrival: <strong>Today within 45 mins</strong></span>
                            </div>
                        </div>
                        <a href="tel:<?= Security::e($settings['phone'] ?? '+919876543210'); ?>" class="btn-hero-upload" style="padding:10px 18px; font-size:0.85rem;">
                            <i class="fa-solid fa-phone"></i> Call Delivery Rider
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div style="background:#f8fafc; border:1px solid var(--border-color); border-radius:12px; padding:20px; font-size:0.95rem;">
                <h4 style="margin-bottom:12px; color:var(--text-heading);"><i class="fa-solid fa-location-dot" style="color:var(--brand-teal);"></i> Delivery Destination</h4>
                <p style="margin:4px 0;"><strong>Patient:</strong> <?= Security::e(Security::maskName($order['customer_name'])); ?> (<?= Security::e(Security::maskPhone($order['phone'])); ?>)</p>
                <p style="margin:4px 0;"><strong>City:</strong> <?= Security::e($order['city']); ?> - <?= Security::e($order['pincode']); ?></p>
                <p style="margin:4px 0;"><strong>Total Payable:</strong> <?= format_price($order['total_amount']); ?> (<?= Security::e($order['payment_method']); ?>)</p>
            </div>

        <?php elseif (!empty($orderCode)): ?>
            <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:12px; padding:24px; text-align:center; color:#991b1b;">
                <i class="fa-solid fa-circle-question" style="font-size:2.5rem; margin-bottom:10px;"></i>
                <h3 style="margin-bottom:6px;">No Order Found</h3>
                <p style="font-size:0.9rem; margin:0;">Could not find an order with code "<strong><?= Security::e($orderCode); ?></strong>".</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
