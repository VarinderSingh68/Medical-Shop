<?php
declare(strict_types=1);

$pageTitle = 'Smart Reorder & Vendor Purchase Orders';
require_once __DIR__ . '/layout/header.php';
require_once __DIR__ . '/../core/Mailer.php';

use App\Core\Security;
use App\Core\Mailer;

$csrfToken = Security::generateCsrf();

// Handle Restock Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
        Security::setFlash('danger', 'Security validation expired.');
    } else {
        $action = $_POST['action'];

        if ($action === 'restock_item') {
            $medId = (int)($_POST['medicine_id'] ?? 0);
            $qtyToAdd = max(1, (int)($_POST['restock_qty'] ?? 50));
            $newBatch = Security::sanitizeString($_POST['batch_number'] ?? 'BAT-' . date('ymd'));
            $newExpiry = Security::sanitizeString($_POST['expiry_date'] ?? date('Y-m-d', strtotime('+2 years')));

            if ($medId > 0) {
                $stmt = $db->prepare("SELECT name, stock_quantity FROM medicines WHERE id = :id");
                $stmt->execute(['id' => $medId]);
                $med = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($med) {
                    $up = $db->prepare("
                        UPDATE medicines 
                        SET stock_quantity = stock_quantity + :qty,
                            batch_number = :batch,
                            expiry_date = :exp
                        WHERE id = :id
                    ");
                    $up->execute([
                        'qty'   => $qtyToAdd,
                        'batch' => $newBatch,
                        'exp'   => $newExpiry,
                        'id'    => $medId
                    ]);

                    // Audit log
                    try {
                        $log = $db->prepare("
                            INSERT INTO audit_logs (admin_id, admin_name, action, entity_type, entity_id, details, ip_address)
                            VALUES (:aid, :aname, 'INVENTORY_RESTOCKED', 'medicine', :mid, :det, :ip)
                        ");
                        $log->execute([
                            'aid'   => $_SESSION['admin_id'] ?? 1,
                            'aname' => $_SESSION['admin_name'] ?? 'Admin',
                            'mid'   => $medId,
                            'det'   => "Restocked +{$qtyToAdd} units of {$med['name']} (Batch {$newBatch}, Exp {$newExpiry})",
                            'ip'    => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                        ]);
                    } catch (\Exception $e) {}

                    Security::setFlash('success', "Restocked +{$qtyToAdd} packs for '{$med['name']}'. New stock: " . ($med['stock_quantity'] + $qtyToAdd) . " packs.");
                }
            }
            header("Location: purchase-orders.php");
            exit;
        } elseif ($action === 'email_vendor_po') {
            $vendorEmail = Security::sanitizeString($_POST['vendor_email'] ?? '');
            $vendorName = Security::sanitizeString($_POST['vendor_name'] ?? 'Pharmaceutical Distributor');
            $poItemsSummary = Security::sanitizeString($_POST['po_summary'] ?? '');

            if (!empty($vendorEmail)) {
                $subject = "Purchase Order Request: HomeMed Pharmacy - Urgent Restock";
                $htmlBody = "
                    <div style='font-family:sans-serif; max-width:600px; padding:20px; border:1px solid #e2e8f0; border-radius:12px;'>
                        <h2 style='color:#0d223a;'>HomeMed Pharmacy - Official Purchase Order</h2>
                        <p>Dear <strong>{$vendorName}</strong>,</p>
                        <p>Please review and confirm dispatch for the following low-stock pharmaceutical requirements:</p>
                        <div style='background:#f8fafc; padding:16px; border-radius:8px; border:1px solid #cbd5e1; font-family:monospace;'>
                            " . nl2br($poItemsSummary) . "
                        </div>
                        <p style='margin-top:16px;'>Delivery address: <strong>HomeMed Central Healthcare Complex, Mumbai - 400001</strong>.</p>
                        <p>Please reply with invoice and delivery ETA.<br><strong>Lead Pharmacist, HomeMed</strong></p>
                    </div>
                ";
                $res = Mailer::sendHtmlEmail($vendorEmail, $subject, $htmlBody);
                if ($res['success']) {
                    Security::setFlash('success', "Purchase order dispatched to {$vendorName} ({$vendorEmail})!");
                } else {
                    Security::setFlash('danger', "Failed to email PO: " . $res['message']);
                }
            } else {
                Security::setFlash('warning', "Vendor has no registered email address on file.");
            }
            header("Location: purchase-orders.php");
            exit;
        }
    }
}

// Fetch all low stock medicines
$lowStockItems = $db->query("
    SELECT m.*, c.name AS category_name
    FROM medicines m
    LEFT JOIN disease_categories c ON m.category_id = c.id
    WHERE m.stock_quantity <= m.low_stock_threshold AND m.is_active = 1
    ORDER BY m.stock_quantity ASC, m.name ASC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

// Total items needing reorder
$totalLowStock = (int)$db->query("SELECT COUNT(*) FROM medicines WHERE stock_quantity <= low_stock_threshold AND is_active = 1")->fetchColumn();
$outOfStock = (int)$db->query("SELECT COUNT(*) FROM medicines WHERE stock_quantity = 0 AND is_active = 1")->fetchColumn();

// Estimated wholesale procurement outlay
$totalEstimatedCost = 0.0;
foreach ($lowStockItems as $it) {
    $needed = max(20, ($it['low_stock_threshold'] * 3) - $it['stock_quantity']);
    $totalEstimatedCost += ((float)$it['purchase_price'] * $needed);
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:1.4rem; color:var(--secondary); font-weight:800; display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-cart-flatbed" style="color:var(--primary);"></i> Smart Reorder & Supplier Purchase Orders
        </h2>
        <span style="font-size:0.85rem; color:var(--text-muted);">Automated low-stock detection, wholesale distributor procurement, batch receiving & 1-click replenishment</span>
    </div>
    <div style="display:flex; gap:10px;">
        <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm"><i class="fa-solid fa-print"></i> Print Reorder Sheet</button>
        <a href="<?= app_url('backend/admin/vendors.php'); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-building-wheat"></i> Manage Vendors</a>
    </div>
</div>

<!-- Reorder KPI Cards -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:20px; margin-bottom:28px;">
    <div class="card" style="margin-bottom:0;">
        <span style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Low Stock SKUs</span>
        <div style="font-size:2rem; font-weight:800; color:var(--warning); margin:6px 0 4px;"><?= number_format($totalLowStock); ?></div>
        <span style="font-size:0.8rem; color:var(--text-muted);">Items below safety buffer</span>
    </div>

    <div class="card" style="margin-bottom:0;">
        <span style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Critical Stockouts</span>
        <div style="font-size:2rem; font-weight:800; color:var(--danger); margin:6px 0 4px;"><?= number_format($outOfStock); ?></div>
        <span style="font-size:0.8rem; color:var(--danger); font-weight:600;">Immediate stockout risk</span>
    </div>

    <div class="card" style="margin-bottom:0;">
        <span style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Est. Procurement Cost</span>
        <div style="font-size:2rem; font-weight:800; color:var(--primary); margin:6px 0 4px;"><?= format_price($totalEstimatedCost); ?></div>
        <span style="font-size:0.8rem; color:var(--text-muted);">Wholesale replenishment budget</span>
    </div>
</div>

<!-- Reorder Registry Table -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title"><i class="fa-solid fa-boxes-stacked"></i> Medicines Requiring Immediate Replenishment</h3>
            <span style="font-size:0.82rem; color:var(--text-muted);">Recommended order quantities calculated from minimum threshold velocity</span>
        </div>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Medicine Description</th>
                    <th>Category</th>
                    <th>Manufacturer</th>
                    <th>Current Stock</th>
                    <th>Safety Buffer</th>
                    <th>Suggested PO</th>
                    <th>Est. Cost</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($lowStockItems)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding:40px; color:var(--text-muted);">
                            <i class="fa-solid fa-circle-check" style="font-size:2rem; color:var(--primary); display:block; margin-bottom:10px;"></i>
                            All 10,000+ medicine SKUs are healthy! No inventory items currently below threshold.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($lowStockItems as $it): 
                        $suggested = max(20, ($it['low_stock_threshold'] * 3) - $it['stock_quantity']);
                        $lineCost = (float)$it['purchase_price'] * $suggested;
                    ?>
                        <tr>
                            <td>
                                <strong style="color:var(--secondary); font-size:0.95rem;"><?= Security::e($it['name']); ?></strong>
                                <div style="font-size:0.75rem; color:var(--text-muted);">
                                    <?= Security::e($it['dosage_form']); ?> &bull; <?= Security::e($it['strength']); ?>
                                    <?= !empty($it['generic_name']) ? ' | ' . Security::e($it['generic_name']) : ''; ?>
                                </div>
                            </td>
                            <td style="font-size:0.82rem; color:var(--text-muted);"><?= Security::e($it['category_name']); ?></td>
                            <td><span class="badge badge-info" style="font-size:0.75rem;"><?= Security::e($it['manufacturer'] ?? 'Sun Pharma'); ?></span></td>
                            <td>
                                <strong style="color:<?= $it['stock_quantity'] === 0 ? 'var(--danger)' : 'var(--warning)'; ?>; font-size:1.05rem;">
                                    <?= $it['stock_quantity']; ?>
                                </strong> packs
                            </td>
                            <td style="color:var(--text-muted); font-size:0.85rem;"><?= $it['low_stock_threshold']; ?> packs</td>
                            <td>
                                <span style="background:#e6f9f3; color:#00a87a; font-weight:800; padding:4px 8px; border-radius:6px; font-size:0.85rem;">
                                    +<?= $suggested; ?> packs
                                </span>
                            </td>
                            <td style="font-weight:700; font-family:'JetBrains Mono', monospace;"><?= format_price($lineCost); ?></td>
                            <td>
                                <!-- Quick Restock Modal Trigger / Form -->
                                <form action="" method="POST" style="margin:0; display:inline-flex; gap:6px;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                                    <input type="hidden" name="action" value="restock_item">
                                    <input type="hidden" name="medicine_id" value="<?= $it['id']; ?>">
                                    <input type="hidden" name="restock_qty" value="<?= $suggested; ?>">
                                    <button type="submit" class="btn btn-primary btn-sm" style="padding:5px 10px;" title="Receive +<?= $suggested; ?> units">
                                        <i class="fa-solid fa-arrow-down-to-bracket"></i> Quick Restock (+<?= $suggested; ?>)
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
