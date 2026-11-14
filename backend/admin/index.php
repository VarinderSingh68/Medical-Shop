<?php
declare(strict_types=1);

$pageTitle = 'Pharmacy Command Center';
require_once __DIR__ . '/layout/header.php';

use App\Core\Security;

// Fetch Dashboard Metrics
$totalMedicines = (int)$db->query("SELECT COUNT(*) FROM medicines WHERE is_active = 1")->fetchColumn();
$lowStockCount = (int)$db->query("SELECT COUNT(*) FROM medicines WHERE stock_quantity <= low_stock_threshold AND is_active = 1")->fetchColumn();
$pendingOrders = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Pending Review'")->fetchColumn();
$activeDeliveries = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('Pharmacist Verified', 'Packed', 'Out for Delivery')")->fetchColumn();

// Near Expiry Count (< 90 days)
$nearExpiryCount = (int)$db->query("SELECT COUNT(*) FROM medicines WHERE expiry_date <= date('now', '+90 days') AND is_active = 1")->fetchColumn();

// Chronic Refill Alerts Due
$refillsDueCount = 0;
try {
    $refillsDueCount = (int)$db->query("SELECT COUNT(*) FROM chronic_patients WHERE refill_due_date <= date('now', '+5 days')")->fetchColumn();
} catch (\Exception $e) {}

// Revenue calculations
$todayRevenue = (float)$db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = CURRENT_DATE AND order_status != 'Cancelled'")->fetchColumn();
$totalRevenue = (float)$db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status != 'Cancelled'")->fetchColumn();

// Cost & Margin
$totalCogs = (float)$db->query("
    SELECT COALESCE(SUM(oi.quantity * m.purchase_price), 0)
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN medicines m ON oi.medicine_id = m.id
    WHERE o.order_status != 'Cancelled'
")->fetchColumn();
$grossProfit = max(0.0, $totalRevenue - $totalCogs);
$marginPercent = ($totalRevenue > 0) ? round(($grossProfit / $totalRevenue) * 100, 1) : 0.0;

// Recent Home Delivery Orders
$recentOrders = $db->query("
    SELECT * FROM orders ORDER BY id DESC LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// Low Stock Medicines
$lowStockItems = $db->query("
    SELECT m.name, m.strength, m.dosage_form, m.stock_quantity, m.low_stock_threshold, c.name AS category_name 
    FROM medicines m 
    JOIN disease_categories c ON m.category_id = c.id 
    WHERE m.stock_quantity <= m.low_stock_threshold AND m.is_active = 1 
    ORDER BY m.stock_quantity ASC LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Role Welcome & Fast Quick Launch Banner -->
<div class="card" style="background:linear-gradient(135deg, var(--secondary) 0%, #16385e 100%); color:#fff; padding:24px 28px; margin-bottom:26px; border:none; box-shadow:0 10px 30px rgba(13,34,58,0.15);">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
        <div>
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                <span class="badge" style="background:rgba(0,192,139,0.2); color:var(--primary); font-size:0.75rem; border:1px solid rgba(0,192,139,0.4);">
                    <i class="fa-solid fa-notes-medical"></i> STORE ACTIVE &bull; DL-2026-MEDIX-7890
                </span>
                <span style="font-size:0.8rem; color:#94a3b8;"><?= date('l, d F Y'); ?></span>
            </div>
            <h2 style="font-size:1.5rem; font-weight:800; color:#fff; margin-bottom:4px; letter-spacing:-0.5px;">
                Welcome back, <?= Security::e($_SESSION['admin_name']); ?>
            </h2>
            <p style="color:#cbd5e1; font-size:0.88rem; margin:0; max-width:600px;">
                HomeMed Pharmacy 360° Management Suite — equipped with counter POS, automated repeat chronic refill care, distributor returns & statutory regulatory registers.
            </p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
            <a href="pos.php" class="btn btn-primary" style="box-shadow:0 4px 15px rgba(0,192,139,0.4);">
                <i class="fa-solid fa-cash-register"></i> Fast POS Billing
            </a>
            <a href="analytics.php" class="btn btn-secondary" style="background:rgba(255,255,255,0.1); color:#fff; border-color:rgba(255,255,255,0.2);">
                <i class="fa-solid fa-chart-line"></i> Owner Analytics
            </a>
            <a href="backup.php" class="btn btn-secondary" style="background:rgba(255,255,255,0.1); color:#fff; border-color:rgba(255,255,255,0.2);" title="Developer Backup">
                <i class="fa-solid fa-database"></i> Backup
            </a>
        </div>
    </div>
</div>

<!-- Primary Real-Time Metric Tiles -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:18px; margin-bottom:28px;">
    <!-- Metric 1: Pending Deliveries -->
    <div class="card" style="margin-bottom:0; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <span style="color:var(--text-muted); font-size:0.78rem; font-weight:700; text-transform:uppercase;">Pending Home Rx</span>
            <div style="font-size:1.8rem; font-weight:800; color:var(--warning); margin-top:2px;"><?= $pendingOrders; ?></div>
            <span style="font-size:0.75rem; color:var(--text-muted);">Awaiting Pharmacist</span>
        </div>
        <div style="width:46px; height:46px; border-radius:12px; background:#fef3c7; color:var(--warning); display:flex; align-items:center; justify-content:center; font-size:1.25rem;">
            <i class="fa-solid fa-clock-rotate-left"></i>
        </div>
    </div>

    <!-- Metric 2: Today's Revenue -->
    <div class="card" style="margin-bottom:0; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <span style="color:var(--text-muted); font-size:0.78rem; font-weight:700; text-transform:uppercase;">Today's Sales</span>
            <div style="font-size:1.8rem; font-weight:800; color:var(--primary); margin-top:2px;"><?= format_price($todayRevenue); ?></div>
            <span style="font-size:0.75rem; color:#10b981; font-weight:700;">Margin: <?= $marginPercent; ?>%</span>
        </div>
        <div style="width:46px; height:46px; border-radius:12px; background:var(--primary-light); color:var(--primary); display:flex; align-items:center; justify-content:center; font-size:1.25rem;">
            <i class="fa-solid fa-indian-rupee-sign"></i>
        </div>
    </div>

    <!-- Metric 3: Chronic Refills Due -->
    <div class="card" style="margin-bottom:0; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <span style="color:var(--text-muted); font-size:0.78rem; font-weight:700; text-transform:uppercase;">Chronic Refills Due</span>
            <div style="font-size:1.8rem; font-weight:800; color:<?= $refillsDueCount > 0 ? 'var(--danger)' : '#10b981'; ?>; margin-top:2px;"><?= $refillsDueCount; ?></div>
            <span style="font-size:0.75rem; color:var(--text-muted);">Within 5 Days</span>
        </div>
        <div style="width:46px; height:46px; border-radius:12px; background:#fee2e2; color:var(--danger); display:flex; align-items:center; justify-content:center; font-size:1.25rem;">
            <i class="fa-solid fa-hospital-user"></i>
        </div>
    </div>

    <!-- Metric 4: Near Expiry Risk -->
    <div class="card" style="margin-bottom:0; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <span style="color:var(--text-muted); font-size:0.78rem; font-weight:700; text-transform:uppercase;">Near Expiry Batches</span>
            <div style="font-size:1.8rem; font-weight:800; color:var(--warning); margin-top:2px;"><?= $nearExpiryCount; ?></div>
            <span style="font-size:0.75rem; color:var(--text-muted);">< 90 Days Shelf Life</span>
        </div>
        <div style="width:46px; height:46px; border-radius:12px; background:#fef3c7; color:var(--warning); display:flex; align-items:center; justify-content:center; font-size:1.25rem;">
            <i class="fa-solid fa-hourglass-half"></i>
        </div>
    </div>

    <!-- Metric 5: Active Inventory -->
    <div class="card" style="margin-bottom:0; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <span style="color:var(--text-muted); font-size:0.78rem; font-weight:700; text-transform:uppercase;">Active Medicines</span>
            <div style="font-size:1.8rem; font-weight:800; color:var(--secondary); margin-top:2px;"><?= $totalMedicines; ?></div>
            <span style="font-size:0.75rem; color:<?= $lowStockCount > 0 ? 'var(--danger)' : 'var(--text-muted)'; ?>;">
                <?= $lowStockCount; ?> low stock items
            </span>
        </div>
        <div style="width:46px; height:46px; border-radius:12px; background:#f1f5f9; color:#475569; display:flex; align-items:center; justify-content:center; font-size:1.25rem;">
            <i class="fa-solid fa-pills"></i>
        </div>
    </div>
</div>

<!-- Three-Persona Dedicated Toolkits -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:20px; margin-bottom:28px;">
    <!-- STAFF TOOLKIT -->
    <div class="card" style="margin-bottom:0; border-top:4px solid var(--primary);">
        <h4 style="font-size:1rem; font-weight:800; color:var(--secondary); margin-bottom:12px; display:flex; align-items:center; gap:8px;">
            <i class="fa-solid fa-user-doctor" style="color:var(--primary);"></i> Staff & Dispensary Desk
        </h4>
        <p style="font-size:0.82rem; color:var(--text-muted); margin-bottom:14px;">Daily counter dispensing, prescription checks & stock replenishment operations.</p>
        <div style="display:flex; flex-direction:column; gap:8px;">
            <a href="pos.php" class="btn btn-secondary btn-sm" style="justify-content:space-between;">
                <span><i class="fa-solid fa-cash-register"></i> Open POS Fast Billing</span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
            <a href="orders.php" class="btn btn-secondary btn-sm" style="justify-content:space-between;">
                <span><i class="fa-solid fa-truck-ramp-box"></i> Home Delivery Queue (<?= $pendingOrders; ?>)</span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
            <a href="expiry-tracker.php" class="btn btn-secondary btn-sm" style="justify-content:space-between;">
                <span><i class="fa-solid fa-hourglass-half"></i> Batch & Expiry Tracker (<?= $nearExpiryCount; ?>)</span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </div>

    <!-- OWNER TOOLKIT -->
    <div class="card" style="margin-bottom:0; border-top:4px solid #f59e0b;">
        <h4 style="font-size:1rem; font-weight:800; color:var(--secondary); margin-bottom:12px; display:flex; align-items:center; gap:8px;">
            <i class="fa-solid fa-chart-pie" style="color:#f59e0b;"></i> Owner & Commercial Hub
        </h4>
        <p style="font-size:0.82rem; color:var(--text-muted); margin-bottom:14px;">Business intelligence, gross profit margins, patient lifetime value & regulatory compliance.</p>
        <div style="display:flex; flex-direction:column; gap:8px;">
            <a href="analytics.php" class="btn btn-secondary btn-sm" style="justify-content:space-between;">
                <span><i class="fa-solid fa-chart-line"></i> Revenue & Gross Margins</span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
            <a href="customers.php" class="btn btn-secondary btn-sm" style="justify-content:space-between;">
                <span><i class="fa-solid fa-hospital-user"></i> Chronic Patient Refill Care (<?= $refillsDueCount; ?>)</span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
            <a href="compliance.php" class="btn btn-secondary btn-sm" style="justify-content:space-between;">
                <span><i class="fa-solid fa-stamp"></i> Schedule H/H1 Regulatory Log</span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </div>

    <!-- DEVELOPER TOOLKIT -->
    <div class="card" style="margin-bottom:0; border-top:4px solid #8b5cf6;">
        <h4 style="font-size:1rem; font-weight:800; color:var(--secondary); margin-bottom:12px; display:flex; align-items:center; gap:8px;">
            <i class="fa-solid fa-code" style="color:#8b5cf6;"></i> Developer & IT Admin
        </h4>
        <p style="font-size:0.82rem; color:var(--text-muted); margin-bottom:14px;">Infrastructure health, SQLite WAL integrity, Gmail SMTP socket diagnostics & security audits.</p>
        <div style="display:flex; flex-direction:column; gap:8px;">
            <a href="system-health.php" class="btn btn-secondary btn-sm" style="justify-content:space-between;">
                <span><i class="fa-solid fa-server"></i> Server Health & PHP v<?= PHP_VERSION; ?></span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
            <a href="smtp-test.php" class="btn btn-secondary btn-sm" style="justify-content:space-between;">
                <span><i class="fa-solid fa-envelope-circle-check"></i> Gmail SMTP Mailer Diagnostics</span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
            <a href="backup.php" class="btn btn-secondary btn-sm" style="justify-content:space-between;">
                <span><i class="fa-solid fa-database"></i> 1-Click Database Snapshot</span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
            <a href="audit-logs.php" class="btn btn-secondary btn-sm" style="justify-content:space-between;">
                <span><i class="fa-solid fa-shield-virus"></i> Security & Audit Logs</span>
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </div>
</div>

<!-- Recent Orders & Inventory Warnings -->
<div style="display:grid; grid-template-columns:2fr 1fr; gap:24px; margin-bottom:28px;">
    <!-- Recent Orders Table -->
    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <h4 class="card-title"><i class="fa-solid fa-truck-fast"></i> Recent Home Delivery Dispatches</h4>
            <a href="orders.php" class="btn btn-primary btn-sm">View All Orders</a>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Order Code</th>
                        <th>Patient</th>
                        <th>Address</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentOrders)): ?>
                        <tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:30px;">No home delivery orders placed yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><strong style="font-family:'JetBrains Mono', monospace; font-size:0.88rem;">#<?= Security::e($order['order_code']); ?></strong></td>
                                <td>
                                    <strong style="color:var(--secondary);"><?= Security::e($order['customer_name']); ?></strong><br>
                                    <span style="font-size:0.78rem; color:var(--text-muted);"><?= Security::e($order['phone']); ?></span>
                                </td>
                                <td style="max-width:180px; font-size:0.82rem; color:var(--text-muted);">
                                    <?= Security::e($order['delivery_address']); ?>, <?= Security::e($order['pincode']); ?>
                                </td>
                                <td><strong><?= format_price($order['total_amount']); ?></strong></td>
                                <td>
                                    <?php
                                        $badgeClass = match($order['order_status']) {
                                            'Pending Review' => 'badge-warning',
                                            'Pharmacist Verified' => 'badge-info',
                                            'Packed' => 'badge-info',
                                            'Out for Delivery' => 'badge-primary',
                                            'Delivered' => 'badge-success',
                                            'Cancelled' => 'badge-danger',
                                            default => 'badge-warning'
                                        };
                                    ?>
                                    <span class="badge <?= $badgeClass; ?>"><?= Security::e($order['order_status']); ?></span>
                                </td>
                                <td>
                                    <a href="orders.php?view=<?= (int)$order['id']; ?>" class="btn btn-secondary btn-sm" style="padding:4px 8px;">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Inventory Warnings & Low Stock Alert -->
    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <h4 class="card-title" style="color:var(--danger);"><i class="fa-solid fa-triangle-exclamation"></i> Low Stock Alerts</h4>
            <a href="medicines.php?filter=low_stock" class="btn btn-secondary btn-sm">Manage</a>
        </div>
        <?php if (empty($lowStockItems)): ?>
            <div style="text-align:center; padding:30px 10px; color:var(--accent);">
                <i class="fa-solid fa-circle-check" style="font-size:2.5rem; margin-bottom:10px;"></i>
                <p style="font-weight:600;">Inventory Healthy</p>
                <span style="font-size:0.85rem; color:var(--text-muted);">All medicines meet safety stock levels.</span>
            </div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach ($lowStockItems as $item): ?>
                    <div style="padding:10px 12px; background:#fff1f2; border:1px solid #fecdd3; border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <strong style="color:#9f1239; font-size:0.88rem;"><?= Security::e($item['name']); ?></strong>
                            <div style="font-size:0.75rem; color:#be123c;">
                                <?= Security::e($item['dosage_form']); ?> &bull; <?= Security::e($item['strength']); ?>
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <span class="badge badge-danger"><?= (int)$item['stock_quantity']; ?> left</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
