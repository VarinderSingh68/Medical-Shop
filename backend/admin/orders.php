<?php
declare(strict_types=1);

$pageTitle = 'Home Delivery Orders';
require_once __DIR__ . '/layout/header.php';
require_once __DIR__ . '/../models/Order.php';

use App\Core\Security;
use App\Models\Order;

$orderModel = new Order($db);
$csrfToken = Security::generateCsrf();

// Handle Status & Notes Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
        Security::setFlash('danger', 'Security validation failed.');
    } else {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $newStatus = trim($_POST['order_status'] ?? '');
        $paymentStatus = trim($_POST['payment_status'] ?? '');
        $deliveryNotes = trim($_POST['delivery_boy_notes'] ?? '');

        if ($orderId > 0 && !empty($newStatus)) {
            $orderModel->updateStatus($orderId, $newStatus, $paymentStatus, $deliveryNotes);
            Security::setFlash('success', "Order #{$orderId} delivery status updated to '{$newStatus}'.");
            header("Location: orders.php?view=" . $orderId);
            exit;
        }
    }
}

// Single Order Detail View
$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$viewOrder = $viewId > 0 ? $orderModel->find($viewId) : null;

// Filter list
$statusFilter = isset($_GET['status']) && $_GET['status'] !== '' ? trim($_GET['status']) : null;
$ordersList = $orderModel->getAll($statusFilter);
?>

<?php if ($viewOrder): ?>
    <!-- Single Order Detail View Card -->
    <div style="margin-bottom:20px;">
        <a href="orders.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to Orders List</a>
    </div>

    <div style="display:grid; grid-template-columns:2fr 1fr; gap:24px; margin-bottom:30px;">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Home Delivery Order #<?= Security::e($viewOrder['order_code']); ?></h3>
                    <span style="font-size:0.85rem; color:var(--text-muted);">Placed on <?= Security::e(format_date($viewOrder['created_at'])); ?></span>
                </div>
                <div style="display:flex; align-items:center; gap:10px;">
                    <a href="invoice.php?id=<?= $viewOrder['id']; ?>" target="_blank" class="btn btn-secondary btn-sm" style="display:inline-flex; align-items:center; gap:6px;">
                        <i class="fa-solid fa-file-invoice-dollar" style="color:var(--primary);"></i> Tax Invoice (PDF)
                    </a>
                    <span class="badge badge-warning" style="font-size:0.9rem;"><?= Security::e($viewOrder['order_status']); ?></span>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; background:#f8fafc; padding:20px; border-radius:8px; margin-bottom:24px;">
                <div>
                    <h4 style="font-size:0.95rem; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Patient & Delivery Details</h4>
                    <p style="margin:4px 0;"><strong>Name:</strong> <?= Security::e($viewOrder['customer_name']); ?></p>
                    <p style="margin:4px 0;"><strong>Phone:</strong> <a href="tel:<?= Security::e($viewOrder['phone']); ?>" style="color:var(--primary); font-weight:600;"><?= Security::e($viewOrder['phone']); ?></a></p>
                    <p style="margin:4px 0;"><strong>Delivery Address:</strong><br><?= nl2br(Security::e($viewOrder['delivery_address'])); ?></p>
                    <p style="margin:4px 0;"><strong>City / PIN:</strong> <?= Security::e($viewOrder['city']); ?> - <?= Security::e($viewOrder['pincode']); ?></p>
                </div>
                <div>
                    <h4 style="font-size:0.95rem; color:var(--text-muted); margin-bottom:8px; text-transform:uppercase;">Payment & Instructions</h4>
                    <p style="margin:4px 0;"><strong>Method:</strong> <?= Security::e($viewOrder['payment_method']); ?></p>
                    <p style="margin:4px 0;"><strong>Payment Status:</strong> 
                        <span class="badge <?= $viewOrder['payment_status'] === 'Paid' ? 'badge-success' : 'badge-warning'; ?>">
                            <?= Security::e($viewOrder['payment_status']); ?>
                        </span>
                    </p>
                    <p style="margin:4px 0;"><strong>Patient / Doctor Notes:</strong><br><em style="color:#475569;"><?= !empty($viewOrder['doctor_notes']) ? Security::e($viewOrder['doctor_notes']) : 'No special disease instructions provided.'; ?></em></p>
                </div>
            </div>

            <!-- Ordered Medicines List -->
            <h4 style="margin-bottom:12px; color:var(--secondary);"><i class="fa-solid fa-capsules"></i> Prescribed / Ordered Medicines</h4>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Type / Strength</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($viewOrder['items'])): ?>
                            <tr>
                                <td colspan="5" style="text-align:center; color:var(--text-muted); padding:20px;">
                                    <em>This is a direct Prescription Upload order. The pharmacist can inspect the attached prescription and prepare the final medicine kit.</em>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($viewOrder['items'] as $it): ?>
                                <tr>
                                    <td>
                                        <strong><?= Security::e($it['name']); ?></strong><br>
                                        <span style="font-size:0.8rem; color:var(--text-muted);"><?= Security::e($it['generic_name']); ?></span>
                                    </td>
                                    <td><?= Security::e($it['dosage_form']); ?> &bull; <?= Security::e($it['strength']); ?></td>
                                    <td><?= (int)$it['quantity']; ?></td>
                                    <td><?= format_price($it['unit_price']); ?></td>
                                    <td><strong><?= format_price($it['total_price']); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" style="text-align:right; font-weight:600;">Delivery Fee:</td>
                            <td><?= format_price($viewOrder['delivery_fee']); ?></td>
                        </tr>
                        <tr>
                            <td colspan="4" style="text-align:right; font-weight:700; font-size:1.1rem;">Total Payable:</td>
                            <td style="font-weight:700; font-size:1.1rem; color:var(--primary);"><?= format_price($viewOrder['total_amount']); ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Prescription Document Viewer -->
            <?php if (!empty($viewOrder['prescription_file'])): ?>
                <div style="margin-top:24px; padding:20px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px;">
                    <h4 style="color:#1e40af; margin-bottom:12px;"><i class="fa-solid fa-file-prescription"></i> Attached Doctor's Prescription</h4>
                    <p style="font-size:0.9rem; color:#1e3a8a; margin-bottom:12px;">Verify doctor's registration, date of prescription, dosage, and patient disease condition before approving.</p>
                    
                    <?php 
                        $viewerUrl = 'view-prescription.php?file=' . urlencode($viewOrder['prescription_file']); 
                        $ext = strtolower(pathinfo($viewOrder['prescription_file'], PATHINFO_EXTENSION));
                    ?>

                    <?php if ($ext === 'pdf'): ?>
                        <a href="<?= $viewerUrl; ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-file-pdf"></i> Open Prescription PDF in New Tab
                        </a>
                    <?php else: ?>
                        <div style="max-width:100%; text-align:center;">
                            <a href="<?= $viewerUrl; ?>" target="_blank" rel="noopener noreferrer">
                                <img src="<?= $viewerUrl; ?>" alt="Prescription" style="max-width:100%; max-height:400px; border-radius:8px; border:1px solid #cbd5e1; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                            </a>
                            <div style="margin-top:8px;">
                                <a href="<?= $viewerUrl; ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm"><i class="fa-solid fa-magnifying-glass-plus"></i> Click to Zoom Full Size</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Order Action / Status Updater -->
        <div class="card">
            <h4 class="card-title" style="margin-bottom:16px;"><i class="fa-solid fa-truck-ramp-box"></i> Dispatch & Delivery Actions</h4>
            
            <form action="" method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                <input type="hidden" name="order_id" value="<?= (int)$viewOrder['id']; ?>">

                <div class="form-group">
                    <label>Home Delivery Progress Stage *</label>
                    <select name="order_status" class="form-control" required>
                        <?php 
                        $statuses = ['Pending Review', 'Pharmacist Verified', 'Packed', 'Out for Delivery', 'Delivered', 'Cancelled'];
                        foreach ($statuses as $st): ?>
                            <option value="<?= $st; ?>" <?= $viewOrder['order_status'] === $st ? 'selected' : ''; ?>>
                                <?= $st; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Payment Status</label>
                    <select name="payment_status" class="form-control" required>
                        <option value="Pending" <?= $viewOrder['payment_status'] === 'Pending' ? 'selected' : ''; ?>>Pending (COD / Payment on arrival)</option>
                        <option value="Paid" <?= $viewOrder['payment_status'] === 'Paid' ? 'selected' : ''; ?>>Paid in Full</option>
                        <option value="Refunded" <?= $viewOrder['payment_status'] === 'Refunded' ? 'selected' : ''; ?>>Refunded</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Delivery Executive / Rider Notes</label>
                    <textarea name="delivery_boy_notes" class="form-control" rows="3" placeholder="e.g. Assigned to Rider Suresh (+91 98765 00000). Call patient before reaching gate."><?= Security::e($viewOrder['delivery_boy_notes'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:12px;">
                    <i class="fa-solid fa-check"></i> Save & Dispatch Update
                </button>
            </form>
        </div>
    </div>

<?php else: ?>

    <!-- All Orders Listing Table -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title"><i class="fa-solid fa-truck-fast"></i> Home Delivery Orders Registry</h3>
                <span style="font-size:0.85rem; color:var(--text-muted);">Manage doorstep deliveries, patient addresses & prescription orders</span>
            </div>
            <!-- Status Filter Tabs -->
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <a href="orders.php" class="btn btn-sm <?= $statusFilter === null ? 'btn-primary' : 'btn-secondary'; ?>">All</a>
                <a href="orders.php?status=Pending+Review" class="btn btn-sm <?= $statusFilter === 'Pending Review' ? 'btn-primary' : 'btn-secondary'; ?>">Pending Review</a>
                <a href="orders.php?status=Pharmacist+Verified" class="btn btn-sm <?= $statusFilter === 'Pharmacist Verified' ? 'btn-primary' : 'btn-secondary'; ?>">Verified</a>
                <a href="orders.php?status=Out+for+Delivery" class="btn btn-sm <?= $statusFilter === 'Out for Delivery' ? 'btn-primary' : 'btn-secondary'; ?>">Out for Delivery</a>
                <a href="orders.php?status=Delivered" class="btn btn-sm <?= $statusFilter === 'Delivered' ? 'btn-primary' : 'btn-secondary'; ?>">Delivered</a>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Order Code</th>
                        <th>Order Date</th>
                        <th>Patient Name</th>
                        <th>Phone</th>
                        <th>City / PIN</th>
                        <th>Amount</th>
                        <th>Prescription</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ordersList)): ?>
                        <tr><td colspan="9" style="text-align:center; padding:30px; color:var(--text-muted);">No orders found matching the filter.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ordersList as $o): ?>
                            <tr>
                                <td><strong>#<?= Security::e($o['order_code']); ?></strong></td>
                                <td style="font-size:0.85rem; color:var(--text-muted);"><?= Security::e(format_date($o['created_at'], 'd M, h:i A')); ?></td>
                                <td><strong><?= Security::e($o['customer_name']); ?></strong></td>
                                <td><?= Security::e($o['phone']); ?></td>
                                <td><?= Security::e($o['city']); ?> - <?= Security::e($o['pincode']); ?></td>
                                <td><strong><?= format_price($o['total_amount']); ?></strong></td>
                                <td>
                                    <?php if (!empty($o['prescription_file'])): ?>
                                        <span class="badge badge-info"><i class="fa-solid fa-file-prescription"></i> Rx Attached</span>
                                    <?php else: ?>
                                        <span style="font-size:0.8rem; color:var(--text-muted);">Direct</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $badgeClass = match($o['order_status']) {
                                            'Pending Review' => 'badge-warning',
                                            'Pharmacist Verified' => 'badge-info',
                                            'Packed' => 'badge-info',
                                            'Out for Delivery' => 'badge-primary',
                                            'Delivered' => 'badge-success',
                                            'Cancelled' => 'badge-danger',
                                            default => 'badge-warning'
                                        };
                                    ?>
                                    <span class="badge <?= $badgeClass; ?>"><?= Security::e($o['order_status']); ?></span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:6px;">
                                        <a href="orders.php?view=<?= (int)$o['id']; ?>" class="btn btn-primary btn-sm" title="View & Manage Order">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <a href="invoice.php?id=<?= (int)$o['id']; ?>" target="_blank" class="btn btn-secondary btn-sm" title="Print/Download Tax Invoice">
                                            <i class="fa-solid fa-receipt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
