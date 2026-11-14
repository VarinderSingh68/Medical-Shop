<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/core/Security.php';
require_once __DIR__ . '/../backend/models/Customer.php';

use App\Core\Security;
use App\Models\Customer;

Security::applyHeaders();

// Require Customer Authentication
if (empty($_SESSION['customer_id'])) {
    header("Location: " . app_url('login.php?return_to=' . urlencode(app_url('account.php'))));
    exit;
}

$db = DatabaseConnection::getInstance();
$customerModel = new Customer($db);
$customer = $customerModel->findById((int)$_SESSION['customer_id']);

if (!$customer) {
    unset($_SESSION['customer_id']);
    header("Location: " . app_url('login.php'));
    exit;
}

$orders = $customerModel->getOrders((int)$customer['id']);
$success = '';
$error = '';

if (isset($_GET['welcome'])) {
    $success = 'Welcome, ' . Security::e($customer['name']) . '! Your account is active and verified.';
}

// Handle Profile Update
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['update_profile'])) {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');

        if ($name === '') {
            $error = 'Name cannot be empty.';
        } else {
            $updateData = [
                'name'    => $name,
                'phone'   => $phone,
                'address' => $address,
                'city'    => $city,
                'pincode' => $pincode,
            ];
            if ($newPassword !== '') {
                if (strlen($newPassword) < 6) {
                    $error = 'Password must be at least 6 characters.';
                } else {
                    $updateData['password'] = $newPassword;
                }
            }

            if ($error === '') {
                $customerModel->updateProfile((int)$customer['id'], $updateData);
                $_SESSION['customer_name'] = $name;
                $_SESSION['customer_phone'] = $phone;
                $customer = $customerModel->findById((int)$customer['id']);
                $success = 'Your profile details have been saved successfully.';
            }
        }
    }
}

$csrfToken = Security::generateCsrf();
$pageTitle = "My Patient Account";
require_once __DIR__ . '/layout/header.php';
?>

<main class="container" style="max-width: 1040px; margin: 3rem auto 5rem; min-height: 60vh;">
    <!-- Welcome Header Card -->
    <div style="background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%); color: #ffffff; border-radius: 20px; padding: 2rem 2.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1.5rem; box-shadow: 0 10px 25px rgba(13,148,136,0.25); margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; gap: 1.25rem;">
            <?php if (!empty($customer['avatar'])): ?>
                <img src="<?= Security::e($customer['avatar']); ?>" alt="Profile Avatar" style="width: 72px; height: 72px; border-radius: 50%; border: 3px solid rgba(255,255,255,0.85); object-fit: cover;" referrerpolicy="no-referrer">
            <?php else: ?>
                <div style="width: 72px; height: 72px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 2rem; border: 3px solid rgba(255,255,255,0.4);">
                    <i class="fa-solid fa-user"></i>
                </div>
            <?php endif; ?>
            <div>
                <h1 style="font-size: 1.6rem; font-weight: 800; margin-bottom: 0.25rem;"><?= Security::e($customer['name']); ?></h1>
                <p style="opacity: 0.88; font-size: 0.92rem; margin: 0; display:flex; align-items:center; gap:0.5rem;">
                    <span><i class="fa-solid fa-envelope"></i> <?= Security::e($customer['email']); ?></span>
                    <?php if (!empty($customer['google_id'])): ?>
                        <span style="background:rgba(255,255,255,0.25); padding:2px 8px; border-radius:12px; font-size:0.75rem; font-weight:600;"><i class="fa-brands fa-google"></i> Google Connected</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <a href="<?= app_url('medicines.php'); ?>" class="btn" style="background: rgba(255,255,255,0.2); color: #fff; border: 1px solid rgba(255,255,255,0.4); text-decoration: none; padding: 0.6rem 1.1rem; border-radius: 10px; font-weight: 600; font-size: 0.9rem;">
                <i class="fa-solid fa-pills"></i> Browse Medicines
            </a>
            <a href="<?= app_url('logout.php'); ?>" class="btn" style="background: #ffffff; color: #0f766e; text-decoration: none; padding: 0.6rem 1.1rem; border-radius: 10px; font-weight: 700; font-size: 0.9rem; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                <i class="fa-solid fa-right-from-bracket"></i> Sign Out
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($error)): ?>
        <div style="background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:0.9rem 1.25rem; border-radius:12px; font-size:0.95rem; margin-bottom:1.5rem; display:flex; align-items:center; gap:0.75rem;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span><?= Security::e($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div style="background:#f0fdf4; border:1px solid #bbf7d0; color:#16a34a; padding:0.9rem 1.25rem; border-radius:12px; font-size:0.95rem; margin-bottom:1.5rem; display:flex; align-items:center; gap:0.75rem;">
            <i class="fa-solid fa-circle-check"></i>
            <span><?= Security::e($success); ?></span>
        </div>
    <?php endif; ?>

    <!-- Two-Column Layout: Orders & Profile Settings -->
    <div style="display: grid; grid-template-columns: 1fr 340px; gap: 2rem;">
        
        <!-- Left: Past Orders -->
        <div>
            <div style="background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 1.75rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                    <h2 style="font-size: 1.2rem; font-weight: 700; color: #0f172a; margin: 0;">
                        <i class="fa-solid fa-box-archive" style="color:var(--brand-teal); margin-right: 6px;"></i> My Prescriptions & Orders
                    </h2>
                    <span style="background: #f1f5f9; color: #475569; font-size: 0.8rem; font-weight: 700; padding: 3px 10px; border-radius: 12px;">
                        <?= count($orders); ?> Total
                    </span>
                </div>

                <?php if (empty($orders)): ?>
                    <div style="text-align: center; padding: 3rem 1rem;">
                        <div style="width: 64px; height: 64px; border-radius: 50%; background: #f8fafc; color: #94a3b8; display: inline-flex; align-items: center; justify-content: center; font-size: 1.75rem; margin-bottom: 1rem;">
                            <i class="fa-solid fa-receipt"></i>
                        </div>
                        <h3 style="font-size: 1.05rem; font-weight: 700; color: #334155; margin-bottom: 0.35rem;">No orders placed yet</h3>
                        <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.25rem;">Upload your prescription or search medicines for fast doorstep delivery.</p>
                        <a href="<?= app_url('medicines.php'); ?>" style="display: inline-flex; align-items: center; gap: 0.5rem; background: var(--brand-teal); color: #fff; padding: 0.65rem 1.25rem; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 0.9rem;">
                            <i class="fa-solid fa-magnifying-glass"></i> Explore Pharmacy Catalog
                        </a>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($orders as $o): ?>
                            <div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.15rem; background: #fafafa; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                                <div>
                                    <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.25rem;">
                                        <span style="font-weight: 800; color: #0f172a; font-size: 0.95rem;">Order #<?= Security::e($o['order_code']); ?></span>
                                        <?php
                                        $badgeColor = match($o['order_status']) {
                                            'Delivered'           => 'background:#dcfce7; color:#15803d;',
                                            'Out for Delivery'    => 'background:#fef3c7; color:#b45309;',
                                            'Packed'              => 'background:#e0e7ff; color:#4338ca;',
                                            'Pharmacist Verified' => 'background:#e0f2fe; color:#0369a1;',
                                            'Cancelled'           => 'background:#fee2e2; color:#b91c1c;',
                                            default               => 'background:#f1f5f9; color:#475569;'
                                        };
                                        ?>
                                        <span style="font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 8px; <?= $badgeColor; ?>">
                                            <?= Security::e($o['order_status']); ?>
                                        </span>
                                    </div>
                                    <p style="color: #64748b; font-size: 0.82rem; margin: 0;">
                                        Placed on <?= format_date($o['created_at'], 'd M Y, h:i A'); ?> &bull; 
                                        <?= Security::e($o['city']); ?>
                                    </p>
                                </div>
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <span style="font-size: 1.05rem; font-weight: 800; color: #0f172a;">
                                        <?= format_price($o['total_amount']); ?>
                                    </span>
                                    <a href="<?= app_url('track-order.php?order_code=' . urlencode($o['order_code'])); ?>" style="background: #ffffff; border: 1px solid #cbd5e1; color: #0f172a; padding: 0.45rem 0.85rem; border-radius: 8px; font-size: 0.82rem; font-weight: 600; text-decoration: none;">
                                        <i class="fa-solid fa-truck"></i> Track
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Profile Details & Address -->
        <div>
            <div style="background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 1.75rem; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
                <h2 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin-bottom: 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9;">
                    <i class="fa-solid fa-address-card" style="color:var(--brand-teal); margin-right: 6px;"></i> Delivery Address
                </h2>

                <form action="<?= app_url('account.php'); ?>" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                    <input type="hidden" name="update_profile" value="1">

                    <div style="margin-bottom: 1rem;">
                        <label style="display:block; font-size:0.82rem; font-weight:700; color:#334155; margin-bottom:0.35rem;">Full Name</label>
                        <input type="text" name="name" required value="<?= Security::e($customer['name']); ?>"
                               style="width:100%; padding:0.65rem 0.85rem; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem; font-family:inherit;">
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <label style="display:block; font-size:0.82rem; font-weight:700; color:#334155; margin-bottom:0.35rem;">Phone Number</label>
                        <input type="tel" name="phone" value="<?= Security::e($customer['phone'] ?? ''); ?>" placeholder="+91 98765 43210"
                               style="width:100%; padding:0.65rem 0.85rem; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem; font-family:inherit;">
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <label style="display:block; font-size:0.82rem; font-weight:700; color:#334155; margin-bottom:0.35rem;">Street Address</label>
                        <textarea name="address" rows="2" placeholder="House/Flat No, Apartment, Street"
                                  style="width:100%; padding:0.65rem 0.85rem; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem; font-family:inherit; resize:vertical;"><?= Security::e($customer['address'] ?? ''); ?></textarea>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1rem;">
                        <div>
                            <label style="display:block; font-size:0.82rem; font-weight:700; color:#334155; margin-bottom:0.35rem;">City</label>
                            <input type="text" name="city" value="<?= Security::e($customer['city'] ?? ''); ?>" placeholder="Mumbai"
                                   style="width:100%; padding:0.65rem 0.85rem; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem; font-family:inherit;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.82rem; font-weight:700; color:#334155; margin-bottom:0.35rem;">Pincode</label>
                            <input type="text" name="pincode" value="<?= Security::e($customer['pincode'] ?? ''); ?>" placeholder="400001"
                                   style="width:100%; padding:0.65rem 0.85rem; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem; font-family:inherit;">
                        </div>
                    </div>

                    <div style="margin-bottom: 1.25rem;">
                        <label style="display:block; font-size:0.82rem; font-weight:700; color:#334155; margin-bottom:0.35rem;">
                            Change Password <span style="font-weight:400; color:#94a3b8;">(leave blank to keep)</span>
                        </label>
                        <input type="password" name="new_password" placeholder="New password"
                               style="width:100%; padding:0.65rem 0.85rem; border:1.5px solid #cbd5e1; border-radius:8px; font-size:0.9rem; font-family:inherit;">
                    </div>

                    <button type="submit" style="width:100%; padding:0.75rem; background:var(--brand-teal); color:#fff; border:none; border-radius:10px; font-weight:700; font-size:0.92rem; cursor:pointer;">
                        Save Profile Changes
                    </button>
                </form>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
