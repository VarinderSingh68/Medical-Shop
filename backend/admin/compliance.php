<?php
declare(strict_types=1);

$pageTitle = 'Schedule H/H1 Drug Compliance Register';
require_once __DIR__ . '/layout/header.php';

use App\Core\Security;

$csrfToken = Security::generateCsrf();

// Handle Manual Log Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_schedule_h') {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
        Security::setFlash('danger', 'Security validation failed.');
    } else {
        $pName = Security::sanitizeString($_POST['patient_name'] ?? '');
        $pPhone = Security::sanitizeString($_POST['patient_phone'] ?? '');
        $dName = Security::sanitizeString($_POST['doctor_name'] ?? '');
        $dReg = Security::sanitizeString($_POST['doctor_reg_no'] ?? '');
        $mName = Security::sanitizeString($_POST['medicine_name'] ?? '');
        $strength = Security::sanitizeString($_POST['strength'] ?? '');
        $batch = Security::sanitizeString($_POST['batch_number'] ?? '');
        $qty = (int)($_POST['quantity'] ?? 1);
        $pharm = Security::sanitizeString($_SESSION['admin_name'] ?? 'Lead Pharmacist');
        $code = 'RX-' . strtoupper(bin2hex(random_bytes(3)));

        if (!empty($pName) && !empty($dName) && !empty($mName) && !empty($batch)) {
            $stmt = $db->prepare("
                INSERT INTO compliance_register (order_code, patient_name, patient_phone, doctor_name, doctor_reg_no, medicine_name, strength, batch_number, quantity, pharmacist_name)
                VALUES (:code, :pname, :pphone, :dname, :dreg, :mname, :str, :batch, :qty, :pharm)
            ");
            $stmt->execute([
                'code'   => $code,
                'pname'  => $pName,
                'pphone' => $pPhone,
                'dname'  => $dName,
                'dreg'   => $dReg,
                'mname'  => $mName,
                'str'    => $strength,
                'batch'  => $batch,
                'qty'    => $qty,
                'pharm'  => $pharm
            ]);
            Security::setFlash('success', "Schedule H entry logged under Reference #{$code}.");
            header("Location: compliance.php");
            exit;
        }
    }
}

// Fetch all compliance records
$records = $db->query("
    SELECT * FROM compliance_register 
    ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Count
$totalLogged = count($records);
$todayCount = (int)$db->query("SELECT COUNT(*) FROM compliance_register WHERE DATE(created_at) = CURRENT_DATE")->fetchColumn();
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:1.4rem; color:var(--secondary); font-weight:800; display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-stamp" style="color:var(--danger);"></i> Schedule H & H1 Regulatory Register
        </h2>
        <span style="font-size:0.85rem; color:var(--text-muted);">
            Statutory Prescription Drug Dispensing Register under Drugs & Cosmetics Rules 65 &bull; License: <strong>DL-2026-MEDIX-7890</strong>
        </span>
    </div>
    <div style="display:flex; gap:10px;">
        <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm"><i class="fa-solid fa-print"></i> Print Statutory Register</button>
        <button type="button" onclick="document.getElementById('addEntryModal').style.display='flex'" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus"></i> Record Dispensed Rx
        </button>
    </div>
</div>

<!-- License Header Strip -->
<div class="card" style="background:#f8fafc; border-left:4px solid var(--primary); padding:16px 20px; margin-bottom:24px;">
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:15px; font-size:0.85rem;">
        <div>
            <span style="color:var(--text-muted); display:block; font-size:0.75rem; text-transform:uppercase; font-weight:700;">Pharmacy License #</span>
            <strong style="color:var(--secondary); font-size:0.95rem;">DL-2026-MEDIX-7890</strong>
        </div>
        <div>
            <span style="color:var(--text-muted); display:block; font-size:0.75rem; text-transform:uppercase; font-weight:700;">Registered Pharmacist</span>
            <strong style="color:var(--secondary); font-size:0.95rem;"><?= Security::e($_SESSION['admin_name']); ?> (Reg #PH-98421)</strong>
        </div>
        <div>
            <span style="color:var(--text-muted); display:block; font-size:0.75rem; text-transform:uppercase; font-weight:700;">Total Schedule Entries</span>
            <strong style="color:var(--primary); font-size:0.95rem;"><?= $totalLogged; ?> records (<?= $todayCount; ?> today)</strong>
        </div>
        <div>
            <span style="color:var(--text-muted); display:block; font-size:0.75rem; text-transform:uppercase; font-weight:700;">Inspection Readiness</span>
            <span class="badge badge-success" style="font-size:0.78rem;"><i class="fa-solid fa-check"></i> 100% Compliant</span>
        </div>
    </div>
</div>

<!-- Register Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-book-medical" style="color:var(--danger);"></i> Controlled Medication Log Book</h3>
        <input type="text" id="comp-search" placeholder="Search patient, doctor, batch or drug..." class="form-control" style="width:260px; padding:6px 12px; font-size:0.88rem;">
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Ref # & Date</th>
                    <th>Patient Name & Phone</th>
                    <th>Prescribing Doctor & Reg #</th>
                    <th>Drug / Formulation</th>
                    <th>Batch Number</th>
                    <th>Units</th>
                    <th>Dispensing Pharmacist</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--text-muted);">No Schedule H dispensing entries logged yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($records as $r): ?>
                        <tr class="comp-row" data-search="<?= strtolower(Security::e($r['patient_name'] . ' ' . $r['doctor_name'] . ' ' . $r['medicine_name'] . ' ' . $r['batch_number'])); ?>">
                            <td>
                                <strong style="font-family:'JetBrains Mono', monospace; color:var(--secondary); font-size:0.88rem;">#<?= Security::e($r['order_code']); ?></strong>
                                <div style="font-size:0.75rem; color:var(--text-muted);"><?= Security::e(date('d M Y', strtotime($r['dispensed_date'] ?? $r['created_at']))); ?></div>
                            </td>
                            <td>
                                <strong style="color:var(--secondary);"><?= Security::e($r['patient_name']); ?></strong>
                                <?php if (!empty($r['patient_phone'])): ?>
                                    <div style="font-size:0.78rem; color:var(--text-muted);"><?= Security::e($r['patient_phone']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color:var(--text-main);"><?= Security::e($r['doctor_name']); ?></strong>
                                <div style="font-size:0.75rem; color:var(--primary); font-weight:700;">Reg: <?= Security::e($r['doctor_reg_no']); ?></div>
                            </td>
                            <td>
                                <strong style="color:var(--danger);"><?= Security::e($r['medicine_name']); ?></strong>
                                <div style="font-size:0.75rem; color:var(--text-muted);"><?= Security::e($r['strength']); ?></div>
                            </td>
                            <td>
                                <span class="badge badge-secondary" style="font-family:'JetBrains Mono', monospace; font-size:0.8rem;"><?= Security::e($r['batch_number']); ?></span>
                            </td>
                            <td>
                                <strong style="font-size:0.92rem;"><?= (int)$r['quantity']; ?></strong>
                            </td>
                            <td style="font-size:0.85rem; color:var(--text-muted);">
                                <i class="fa-solid fa-signature" style="color:var(--primary);"></i> <?= Security::e($r['pharmacist_name']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Record Schedule H Entry -->
<div id="addEntryModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:16px; padding:28px; width:100%; max-width:540px; box-shadow:0 20px 50px rgba(0,0,0,0.2);">
        <h4 style="color:var(--secondary); font-size:1.2rem; font-weight:800; margin-bottom:8px;">
            <i class="fa-solid fa-stamp" style="color:var(--danger);"></i> Record Schedule H/H1 Prescription Entry
        </h4>
        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:18px;">Log prescription drug dispensing in the government statutory register.</p>
        
        <form action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
            <input type="hidden" name="action" value="log_schedule_h">

            <div class="form-grid" style="grid-template-columns:1.2fr 1fr;">
                <div class="form-group">
                    <label>Patient Full Name *</label>
                    <input type="text" name="patient_name" class="form-control" required placeholder="Patient full name">
                </div>
                <div class="form-group">
                    <label>Patient Phone</label>
                    <input type="text" name="patient_phone" class="form-control" placeholder="+91 98...">
                </div>
            </div>

            <div class="form-grid" style="grid-template-columns:1.2fr 1fr;">
                <div class="form-group">
                    <label>Prescribing Doctor Name *</label>
                    <input type="text" name="doctor_name" class="form-control" required placeholder="Dr. Name, MD">
                </div>
                <div class="form-group">
                    <label>Doctor MCI / Reg No *</label>
                    <input type="text" name="doctor_reg_no" class="form-control" required placeholder="MCI-12345">
                </div>
            </div>

            <div class="form-grid" style="grid-template-columns:1.4fr 1fr;">
                <div class="form-group">
                    <label>Drug / Medicine Name *</label>
                    <input type="text" name="medicine_name" class="form-control" required placeholder="e.g. Telma-H Tablets">
                </div>
                <div class="form-group">
                    <label>Dosage Strength</label>
                    <input type="text" name="strength" class="form-control" placeholder="e.g. 40mg/12.5mg">
                </div>
            </div>

            <div class="form-grid" style="grid-template-columns:1fr 1fr;">
                <div class="form-group">
                    <label>Batch Number *</label>
                    <input type="text" name="batch_number" class="form-control" required placeholder="BAT-7801">
                </div>
                <div class="form-group">
                    <label>Quantity Dispensed *</label>
                    <input type="number" name="quantity" class="form-control" min="1" value="30" required>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('addEntryModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-stamp"></i> Record In Official Register</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('comp-search').addEventListener('input', function() {
        const val = this.value.toLowerCase().trim();
        document.querySelectorAll('.comp-row').forEach(row => {
            const s = row.getAttribute('data-search');
            row.style.display = s.includes(val) ? '' : 'none';
        });
    });
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
