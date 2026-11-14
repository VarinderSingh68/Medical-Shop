<?php
declare(strict_types=1);

$pageTitle = 'Batch & Expiry Management';
require_once __DIR__ . '/layout/header.php';

use App\Core\Security;

$csrfToken = Security::generateCsrf();

// Handle Write-off / Vendor Return Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
        Security::setFlash('danger', 'Security token expired.');
    } else {
        $action = $_POST['action'];
        $medId = (int)($_POST['medicine_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 0);
        $reason = Security::sanitizeString($_POST['reason'] ?? 'Expired batch return');

        if ($medId > 0 && $qty > 0) {
            $stmt = $db->prepare("SELECT name, batch_number, stock_quantity FROM medicines WHERE id = :id");
            $stmt->execute(['id' => $medId]);
            $med = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($med) {
                $deduct = min($qty, (int)$med['stock_quantity']);
                $up = $db->prepare("UPDATE medicines SET stock_quantity = MAX(0, stock_quantity - :q) WHERE id = :id");
                $up->execute(['q' => $deduct, 'id' => $medId]);

                // Audit log
                try {
                    $log = $db->prepare("INSERT INTO audit_logs (admin_id, admin_name, action, entity_type, entity_id, details, ip_address) VALUES (:aid, :aname, 'BATCH_EXPIRY_WRITEOFF', 'medicine', :mid, :det, :ip)");
                    $log->execute([
                        'aid'   => $_SESSION['admin_id'] ?? 1,
                        'aname' => $_SESSION['admin_name'] ?? 'Admin',
                        'mid'   => $medId,
                        'det'   => "Batch {$med['batch_number']} deducted by {$deduct} units. Reason: {$reason}",
                        'ip'    => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                    ]);
                } catch (\Exception $e) {}

                Security::setFlash('success', "Batch #{$med['batch_number']} ({$med['name']}) reduced by {$deduct} units for: {$reason}");
                header("Location: expiry-tracker.php");
                exit;
            }
        }
    }
}

// KPI Metrics via optimized SQL aggregation
$kpis = $db->query("
    SELECT 
        COUNT(CASE WHEN expiry_date < date('now') THEN 1 END) AS expired_count,
        COUNT(CASE WHEN expiry_date >= date('now') AND expiry_date <= date('now', '+30 days') THEN 1 END) AS critical_30,
        COUNT(CASE WHEN expiry_date > date('now', '+30 days') AND expiry_date <= date('now', '+90 days') THEN 1 END) AS near_90,
        COALESCE(SUM(CASE WHEN expiry_date <= date('now', '+90 days') THEN stock_quantity * purchase_price END), 0) AS value_at_risk
    FROM medicines WHERE is_active = 1
")->fetch(PDO::FETCH_ASSOC);

$expiredCount = (int)($kpis['expired_count'] ?? 0);
$critical30Count = (int)($kpis['critical_30'] ?? 0);
$near90Count = (int)($kpis['near_90'] ?? 0);
$totalValueAtRisk = (float)($kpis['value_at_risk'] ?? 0.0);

// Fetch priority batches needing pharmacist attention (Top 200 closest to expiry)
$allBatches = $db->query("
    SELECT m.*, c.name AS category_name
    FROM medicines m
    JOIN disease_categories c ON m.category_id = c.id
    WHERE m.is_active = 1
    ORDER BY m.expiry_date ASC
    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

$today = new DateTime('today');

foreach ($allBatches as &$b) {
    $exp = new DateTime($b['expiry_date']);
    $diff = (int)$today->diff($exp)->format('%r%a'); // negative if past
    $b['days_remaining'] = $diff;

    if ($diff < 0) {
        $b['expiry_badge'] = 'badge-danger';
        $b['expiry_label'] = 'Expired (' . abs($diff) . 'd ago)';
    } elseif ($diff <= 30) {
        $b['expiry_badge'] = 'badge-warning';
        $b['expiry_label'] = 'Critical (' . $diff . 'd left)';
    } elseif ($diff <= 90) {
        $b['expiry_badge'] = 'badge-info';
        $b['expiry_label'] = 'Near Expiry (' . $diff . 'd left)';
    } else {
        $b['expiry_badge'] = 'badge-success';
        $b['expiry_label'] = 'Good (' . $diff . 'd left)';
    }
}
unset($b);
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:1.4rem; color:var(--secondary); font-weight:800; display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-hourglass-half" style="color:var(--warning);"></i> Batch & Expiry Management
        </h2>
        <span style="font-size:0.85rem; color:var(--text-muted);">Proactively audit pharmaceutical shelf life, eliminate expired stock liability & process distributor return claims</span>
    </div>
    <div style="display:flex; gap:10px;">
        <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm"><i class="fa-solid fa-print"></i> Export Expiry Log</button>
        <a href="<?= app_url('backend/admin/medicines.php'); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Add New Batch</a>
    </div>
</div>

<!-- KPI Summary Cards -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:20px; margin-bottom:28px;">
    <div class="card" style="margin-bottom:0; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <span style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Expired Batches</span>
            <div style="font-size:1.8rem; font-weight:800; color:var(--danger); margin-top:4px;"><?= $expiredCount; ?></div>
            <span style="font-size:0.8rem; color:var(--danger); font-weight:600;">Immediate quarantine required</span>
        </div>
        <div style="width:48px; height:48px; border-radius:12px; background:#fee2e2; color:var(--danger); display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
            <i class="fa-solid fa-ban"></i>
        </div>
    </div>

    <div class="card" style="margin-bottom:0; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <span style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Critical (< 30 Days)</span>
            <div style="font-size:1.8rem; font-weight:800; color:var(--warning); margin-top:4px;"><?= $critical30Count; ?></div>
            <span style="font-size:0.8rem; color:var(--warning); font-weight:600;">Fast-track or return to vendor</span>
        </div>
        <div style="width:48px; height:48px; border-radius:12px; background:#fef3c7; color:var(--warning); display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
    </div>

    <div class="card" style="margin-bottom:0; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <span style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Near Expiry (< 90 Days)</span>
            <div style="font-size:1.8rem; font-weight:800; color:var(--info); margin-top:4px;"><?= $near90Count; ?></div>
            <span style="font-size:0.8rem; color:var(--text-muted);">Monitor dispense rates</span>
        </div>
        <div style="width:48px; height:48px; border-radius:12px; background:#e0f2fe; color:var(--info); display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
            <i class="fa-solid fa-clock"></i>
        </div>
    </div>

    <div class="card" style="margin-bottom:0; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <span style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Capital at Expiry Risk</span>
            <div style="font-size:1.8rem; font-weight:800; color:var(--secondary); margin-top:4px;"><?= format_price($totalValueAtRisk); ?></div>
            <span style="font-size:0.8rem; color:var(--text-muted);">Purchase cost of expiring units</span>
        </div>
        <div style="width:48px; height:48px; border-radius:12px; background:var(--primary-light); color:var(--primary); display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
            <i class="fa-solid fa-wallet"></i>
        </div>
    </div>
</div>

<!-- Main Table Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-table-list" style="color:var(--primary);"></i> Batch Lifespan Register (Closest <?= count($allBatches); ?> Batches)</h3>
        <div style="display:flex; gap:10px; align-items:center;">
            <input type="text" id="filter-batch-input" placeholder="Search batch or medicine..." class="form-control" style="width:240px; padding:6px 12px; font-size:0.88rem;">
        </div>
    </div>

    <div class="table-responsive">
        <table id="batch-table">
            <thead>
                <tr>
                    <th>Medicine & Formulation</th>
                    <th>Batch #</th>
                    <th>Expiry Date</th>
                    <th>Current Shelf Life</th>
                    <th>Stock Units</th>
                    <th>Stock Value</th>
                    <th>Manufacturer</th>
                    <th>Regulatory Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allBatches)): ?>
                    <tr><td colspan="8" style="text-align:center; padding:30px; color:var(--text-muted);">No medicine batches recorded.</td></tr>
                <?php else: ?>
                    <?php foreach ($allBatches as $b): ?>
                        <tr class="batch-row" data-search="<?= strtolower(Security::e($b['name'] . ' ' . $b['batch_number'] . ' ' . $b['manufacturer'])); ?>">
                            <td>
                                <strong style="color:var(--secondary);"><?= Security::e($b['name']); ?></strong>
                                <div style="font-size:0.75rem; color:var(--text-muted);"><?= Security::e($b['dosage_form']); ?> <?= Security::e($b['strength']); ?> &bull; <?= Security::e($b['category_name']); ?></div>
                            </td>
                            <td>
                                <span style="font-family:'JetBrains Mono', monospace; font-weight:700; color:var(--secondary);"><?= Security::e($b['batch_number']); ?></span>
                            </td>
                            <td>
                                <strong style="font-size:0.88rem;"><?= Security::e(date('d M Y', strtotime($b['expiry_date']))); ?></strong>
                            </td>
                            <td>
                                <span class="badge <?= $b['expiry_badge']; ?>"><?= $b['expiry_label']; ?></span>
                            </td>
                            <td>
                                <strong style="font-size:0.92rem;"><?= $b['stock_quantity']; ?></strong> units
                            </td>
                            <td style="font-weight:700; color:var(--secondary);">
                                <?= format_price((float)$b['purchase_price'] * (int)$b['stock_quantity']); ?>
                            </td>
                            <td style="font-size:0.85rem; color:var(--text-muted);">
                                <?= Security::e($b['manufacturer']); ?>
                            </td>
                            <td>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="openWriteoffModal(<?= $b['id']; ?>, '<?= Security::e(addslashes($b['name'])); ?>', '<?= Security::e($b['batch_number']); ?>', <?= (int)$b['stock_quantity']; ?>)">
                                    <i class="fa-solid fa-box-archive"></i> Return / Write-off
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal for Write-off / Return -->
<div id="writeoffModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:16px; padding:28px; width:100%; max-width:480px; box-shadow:0 20px 50px rgba(0,0,0,0.2);">
        <h4 style="color:var(--secondary); font-size:1.2rem; font-weight:800; margin-bottom:12px;" id="modal-title">Return Batch to Vendor</h4>
        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:18px;">Deduct near-expiry or expired units from inventory and generate distributor return records.</p>
        
        <form action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
            <input type="hidden" name="action" value="writeoff">
            <input type="hidden" name="medicine_id" id="modal-med-id" value="0">

            <div class="form-group">
                <label>Units to Return / Write-off *</label>
                <input type="number" name="quantity" id="modal-qty" class="form-control" min="1" max="9999" required>
            </div>

            <div class="form-group">
                <label>Disposal / Return Reason *</label>
                <select name="reason" class="form-control" required>
                    <option value="Expired Stock - Return to Distributor for Credit Note">Expired Stock - Return to Distributor for Credit Note</option>
                    <option value="Near-Expiry (Sub-30 Days) - Vendor Recall">Near-Expiry (Sub-30 Days) - Vendor Recall</option>
                    <option value="Packaging Damage / Broken Seal">Packaging Damage / Broken Seal</option>
                    <option value="Batch Recall by Drug Controller Authority">Batch Recall by Drug Controller Authority</option>
                </select>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeWriteoffModal()">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-check"></i> Confirm Deduction</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('filter-batch-input').addEventListener('input', function() {
        const val = this.value.toLowerCase().trim();
        document.querySelectorAll('.batch-row').forEach(row => {
            const search = row.getAttribute('data-search');
            row.style.display = search.includes(val) ? '' : 'none';
        });
    });

    function openWriteoffModal(medId, medName, batchNo, maxStock) {
        document.getElementById('modal-med-id').value = medId;
        document.getElementById('modal-title').innerText = `Batch ${batchNo} (${medName})`;
        const qtyInput = document.getElementById('modal-qty');
        qtyInput.value = maxStock;
        qtyInput.max = maxStock;
        document.getElementById('writeoffModal').style.display = 'flex';
    }

    function closeWriteoffModal() {
        document.getElementById('writeoffModal').style.display = 'none';
    }
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
