<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/core/Security.php';
require_once __DIR__ . '/../backend/core/DrugSafetyChecker.php';
require_once __DIR__ . '/../backend/models/Medicine.php';
require_once __DIR__ . '/../backend/models/Setting.php';

use App\Core\Security;
use App\Core\DrugSafetyChecker;
use App\Models\Medicine;
use App\Models\Setting;

$db = DatabaseConnection::getInstance();
$medModel = new Medicine($db);
$settingModel = new Setting($db);

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$csrfToken = Security::generateCsrf();

// Handle Cart Actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';
    $isAjax = !empty($_POST['ajax']) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    // Protect destructive actions with CSRF token check
    if (in_array($action, ['clear', 'remove'], true)) {
        if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
            header("Location: " . app_url('cart.php'));
            exit;
        }
    }

    if ($action === 'add') {
        $medId = (int)($_POST['medicine_id'] ?? 0);
        $qty = min(50, max(1, (int)($_POST['quantity'] ?? 1)));

        $med = $medModel->find($medId);
        if ($med) {
            if (isset($_SESSION['cart'][$medId])) {
                $_SESSION['cart'][$medId]['quantity'] = min(50, $_SESSION['cart'][$medId]['quantity'] + $qty);
            } else {
                $_SESSION['cart'][$medId] = [
                    'id'                   => $med['id'],
                    'name'                 => $med['name'],
                    'generic_name'         => $med['generic_name'],
                    'dosage_form'          => $med['dosage_form'],
                    'strength'             => $med['strength'],
                    'price'                => (float)$med['selling_price'],
                    'requires_prescription'=> (bool)$med['requires_prescription'],
                    'quantity'             => $qty
                ];
            }

            if ($isAjax) {
                $totalCount = 0;
                foreach ($_SESSION['cart'] as $it) $totalCount += (int)$it['quantity'];
                header('Content-Type: application/json');
                echo json_encode([
                    'success'   => true,
                    'cartCount' => $totalCount,
                    'name'      => $med['name'],
                    'strength'  => $med['strength'],
                    'price'     => format_price($med['selling_price'])
                ]);
                exit;
            }
        }
        header("Location: " . app_url('cart.php'));
        exit;
    } elseif ($action === 'update') {
        $medId = (int)($_POST['medicine_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 1);

        if ($qty <= 0) {
            unset($_SESSION['cart'][$medId]);
        } elseif (isset($_SESSION['cart'][$medId])) {
            $_SESSION['cart'][$medId]['quantity'] = $qty;
        }

        if ($isAjax) {
            $totalCount = 0;
            $subtotal = 0.0;
            foreach ($_SESSION['cart'] as $it) {
                $totalCount += (int)$it['quantity'];
                $subtotal += ($it['price'] * $it['quantity']);
            }
            header('Content-Type: application/json');
            echo json_encode([
                'success'   => true,
                'cartCount' => $totalCount,
                'subtotal'  => format_price($subtotal)
            ]);
            exit;
        }

        header("Location: " . app_url('cart.php'));
        exit;
    } elseif ($action === 'remove') {
        $medId = (int)($_POST['medicine_id'] ?? 0);
        unset($_SESSION['cart'][$medId]);
        header("Location: " . app_url('cart.php'));
        exit;
    } elseif ($action === 'clear') {
        $_SESSION['cart'] = [];
        header("Location: " . app_url('cart.php'));
        exit;
    }
}

// Calculate Totals
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

$deliveryFee = ($subtotal >= $freeDeliveryAbove || $subtotal === 0.0) ? 0.0 : $standardDeliveryFee;
$totalAmount = $subtotal + $deliveryFee;

// Clinical Drug Interaction Safety Matrix
$clinicalInteractions = DrugSafetyChecker::checkInteractions(array_values($_SESSION['cart']));

$pageTitle = 'My Medicine Cart';
require_once __DIR__ . '/layout/header.php';
?>

<div class="container" style="padding-top:35px; padding-bottom:60px;">
    <h1 style="font-size:2rem; font-weight:800; color:var(--text-heading); margin-bottom:8px;">
        <i class="fa-solid fa-cart-shopping" style="color:var(--brand-teal);"></i> Your Home Delivery Cart
    </h1>
    <p style="color:var(--text-muted); margin-bottom:28px;">Review medications before submitting your delivery address</p>

    <?php if (!empty($clinicalInteractions)): ?>
        <div style="background:#fff1f2; border:1px solid #fecdd3; border-radius:12px; padding:18px 22px; margin-bottom:28px; color:#9f1239;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                <i class="fa-solid fa-triangle-exclamation" style="font-size:1.3rem; color:#e11d48;"></i>
                <h4 style="font-weight:800; font-size:1.05rem; margin:0;">Clinical Drug Safety Alert Detected</h4>
            </div>
            <p style="font-size:0.85rem; line-height:1.5; margin-bottom:12px;">Our registered pharmacist safety matrix identified potential adverse interactions between medications in your cart:</p>
            <?php foreach ($clinicalInteractions as $ci): ?>
                <div style="background:#ffffff; border-radius:8px; padding:12px 16px; margin-bottom:8px; border-left:4px solid #e11d48; font-size:0.85rem;">
                    <strong><?= Security::e($ci['drug1']); ?> + <?= Security::e($ci['drug2']); ?></strong> (<?= Security::e($ci['severity']); ?> Risk):
                    <div style="color:#475569; margin-top:3px;"><?= Security::e($ci['warning']); ?></div>
                    <div style="color:#0f766e; font-weight:700; margin-top:3px;"><i class="fa-solid fa-circle-check"></i> Recommendation: <?= Security::e($ci['action']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($_SESSION['cart'])): ?>
        <div style="background:#fff; border:1px solid var(--border-color); border-radius:var(--radius-card); padding:60px 20px; text-align:center; box-shadow:var(--shadow-subtle);">
            <i class="fa-solid fa-cart-arrow-down" style="font-size:3.5rem; color:#cbd5e1; margin-bottom:16px;"></i>
            <h3 style="color:var(--text-heading); margin-bottom:8px;">Your Cart is Empty</h3>
            <p style="color:var(--text-muted); max-width:400px; margin:0 auto 24px;">Search our inventory for medicines treating your disease, or upload your doctor's prescription directly.</p>
            <div style="display:flex; justify-content:center; gap:14px; flex-wrap:wrap;">
                <a href="<?= app_url('medicines.php'); ?>" class="btn-hero-upload"><i class="fa-solid fa-pills"></i> Browse Medicines</a>
                <a href="<?= app_url('upload-prescription.php'); ?>" class="btn-signin-nav" style="border:1px solid #cbd5e1;"><i class="fa-solid fa-file-prescription"></i> Upload Prescription</a>
            </div>
        </div>
    <?php else: ?>
        <div style="display:grid; grid-template-columns:2fr 1fr; gap:30px; align-items:start;">
            <!-- Cart Items List -->
            <div style="background:#fff; border:1px solid var(--border-color); border-radius:var(--radius-card); padding:28px; box-shadow:var(--shadow-subtle);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; border-bottom:1px solid var(--border-color); padding-bottom:12px;">
                    <h3 style="font-size:1.15rem; font-weight:800; color:var(--text-heading);">Medicines in Order (<?= count($_SESSION['cart']); ?>)</h3>
                    <form action="<?= app_url('cart.php'); ?>" method="POST" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                        <input type="hidden" name="action" value="clear">
                        <button type="submit" style="background:none; border:none; color:#ef4444; font-size:0.85rem; font-weight:600; cursor:pointer;">
                            <i class="fa-solid fa-trash-can"></i> Clear Cart
                        </button>
                    </form>
                </div>

                <?php foreach ($_SESSION['cart'] as $id => $item): ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:16px 0; border-bottom:1px solid #f1f5f9; gap:16px;">
                        <div style="flex:1;">
                            <h4 style="font-size:1.05rem; font-weight:700; color:var(--text-heading); margin-bottom:2px;">
                                <?= Security::e($item['name']); ?>
                                <?php if ($item['requires_prescription']): ?>
                                    <span class="rx-badge" style="position:static; margin-left:8px; display:inline-block;">Rx</span>
                                <?php endif; ?>
                            </h4>
                            <div style="font-size:0.82rem; color:var(--text-muted); margin-bottom:4px;"><?= Security::e($item['generic_name']); ?></div>
                            <span class="med-spec-pill"><?= Security::e($item['dosage_form']); ?> &bull; <?= Security::e($item['strength']); ?></span>
                        </div>

                        <!-- Stepper -->
                        <div style="display:flex; align-items:center; border:1px solid var(--border-color); border-radius:8px; overflow:hidden;">
                            <form action="<?= app_url('cart.php'); ?>" method="POST" style="margin:0;">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="medicine_id" value="<?= (int)$id; ?>">
                                <input type="hidden" name="quantity" value="<?= $item['quantity'] - 1; ?>">
                                <button type="submit" style="padding:6px 12px; border:none; background:#f1f5f9; cursor:pointer; font-weight:700;">-</button>
                            </form>
                            <span style="padding:6px 14px; font-weight:700; font-size:0.95rem;"><?= (int)$item['quantity']; ?></span>
                            <form action="<?= app_url('cart.php'); ?>" method="POST" style="margin:0;">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="medicine_id" value="<?= (int)$id; ?>">
                                <input type="hidden" name="quantity" value="<?= $item['quantity'] + 1; ?>">
                                <button type="submit" style="padding:6px 12px; border:none; background:#f1f5f9; cursor:pointer; font-weight:700;">+</button>
                            </form>
                        </div>

                        <div style="text-align:right; min-width:90px;">
                            <div style="font-size:1.15rem; font-weight:800; color:var(--text-heading);"><?= format_price($item['price'] * $item['quantity']); ?></div>
                            <span style="font-size:0.75rem; color:var(--text-muted);"><?= format_price($item['price']); ?> each</span>
                        </div>

                        <form action="<?= app_url('cart.php'); ?>" method="POST" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="medicine_id" value="<?= (int)$id; ?>">
                            <button type="submit" style="background:none; border:none; color:#94a3b8; cursor:pointer; font-size:1.1rem; padding:4px;" title="Remove">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>

                <?php if ($hasRxRequired): ?>
                    <div style="margin-top:20px; background:#fff1f2; border:1px solid #fecdd3; border-radius:12px; padding:14px; font-size:0.88rem; color:#9f1239; display:flex; align-items:center; gap:12px;">
                        <i class="fa-solid fa-file-prescription" style="font-size:1.5rem; color:#be123c;"></i>
                        <div>
                            <strong>Prescription Required:</strong> One or more medicines require a doctor's prescription. You can upload it during checkout.
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Order Summary -->
            <div style="background:#fff; border:1px solid var(--border-color); border-radius:var(--radius-card); padding:28px; box-shadow:var(--shadow-subtle);">
                <h3 style="font-size:1.15rem; font-weight:800; color:var(--text-heading); margin-bottom:16px;">Delivery Summary</h3>

                <div style="display:flex; justify-content:space-between; margin-bottom:10px; font-size:0.95rem;">
                    <span style="color:var(--text-muted);">Medicines Subtotal:</span>
                    <strong><?= format_price($subtotal); ?></strong>
                </div>

                <div style="display:flex; justify-content:space-between; margin-bottom:12px; font-size:0.95rem;">
                    <span style="color:var(--text-muted);">Doorstep Delivery:</span>
                    <div>
                        <?php if ($deliveryFee === 0.0): ?>
                            <strong style="color:var(--brand-teal);">FREE</strong>
                        <?php else: ?>
                            <strong><?= format_price($deliveryFee); ?></strong>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="border-top:1px solid var(--border-color); padding-top:14px; display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <div>
                        <span style="font-size:0.85rem; color:var(--text-muted); display:block;">Total Payable:</span>
                        <div style="font-size:1.7rem; font-weight:800; color:var(--text-heading);"><?= format_price($totalAmount); ?></div>
                    </div>
                    <span style="background:var(--brand-teal-light); color:var(--brand-teal); padding:4px 12px; border-radius:var(--radius-pill); font-size:0.8rem; font-weight:700;">COD / UPI</span>
                </div>

                <a href="<?= app_url('checkout.php'); ?>" class="btn-hero-upload" style="width:100%; justify-content:center; padding:15px; font-size:1.05rem; border-radius:var(--radius-pill);">
                    <i class="fa-solid fa-truck-fast"></i> Proceed to Doorstep Delivery
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
