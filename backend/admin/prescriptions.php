<?php
declare(strict_types=1);

$pageTitle = 'Prescription Review Queue';
require_once __DIR__ . '/layout/header.php';
require_once __DIR__ . '/../models/Order.php';

use App\Core\Security;
use App\Models\Order;

$orderModel = new Order($db);
$csrfToken = Security::generateCsrf();

// Fetch orders with uploaded prescriptions
$stmt = $db->query("
    SELECT * FROM orders 
    WHERE prescription_file IS NOT NULL AND prescription_file != '' 
    ORDER BY id DESC
");
$prescriptionOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <h3 style="color:var(--secondary); font-size:1.4rem;"><i class="fa-solid fa-file-prescription"></i> Prescription Verification Center</h3>
        <span style="font-size:0.85rem; color:var(--text-muted);">Verify patient prescriptions, doctor signatures, and dispense schedule medications safely for home delivery</span>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Order Code</th>
                    <th>Date</th>
                    <th>Patient Name & Phone</th>
                    <th>Home Address</th>
                    <th>Prescription Document</th>
                    <th>Current Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($prescriptionOrders)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:40px; color:var(--text-muted);">No prescription uploads waiting for verification.</td></tr>
                <?php else: ?>
                    <?php foreach ($prescriptionOrders as $po): ?>
                        <tr>
                            <td><strong>#<?= Security::e($po['order_code']); ?></strong></td>
                            <td style="font-size:0.85rem; color:var(--text-muted);"><?= Security::e(format_date($po['created_at'], 'd M, h:i A')); ?></td>
                            <td>
                                <strong><?= Security::e($po['customer_name']); ?></strong><br>
                                <a href="tel:<?= Security::e($po['phone']); ?>" style="color:var(--primary); font-size:0.85rem;"><?= Security::e($po['phone']); ?></a>
                            </td>
                            <td style="max-width:220px; font-size:0.85rem;">
                                <?= Security::e($po['delivery_address']); ?>, <?= Security::e($po['pincode']); ?>
                            </td>
                            <td>
                                <?php 
                                    $viewerUrl = 'view-prescription.php?file=' . urlencode($po['prescription_file']); 
                                    $ext = strtolower(pathinfo($po['prescription_file'], PATHINFO_EXTENSION));
                                ?>
                                <?php if ($ext === 'pdf'): ?>
                                    <a href="<?= $viewerUrl; ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">
                                        <i class="fa-solid fa-file-pdf" style="color:var(--danger);"></i> View PDF
                                    </a>
                                <?php else: ?>
                                    <a href="<?= $viewerUrl; ?>" target="_blank" rel="noopener noreferrer" style="display:inline-block;">
                                        <img src="<?= $viewerUrl; ?>" alt="Rx" style="width:50px; height:50px; object-fit:cover; border-radius:6px; border:1px solid #cbd5e1;">
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-warning"><?= Security::e($po['order_status']); ?></span>
                            </td>
                            <td>
                                <a href="orders.php?view=<?= (int)$po['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fa-solid fa-user-doctor"></i> Inspect & Dispatch
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
