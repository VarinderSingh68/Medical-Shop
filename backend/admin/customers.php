<?php
declare(strict_types=1);

$pageTitle = 'Chronic Patients & Refill Care';
require_once __DIR__ . '/layout/header.php';
require_once __DIR__ . '/../core/Mailer.php';

use App\Core\Security;
use App\Core\Mailer;

$csrfToken = Security::generateCsrf();

// Handle New Chronic Patient Submission & Refill Email Alerts
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
        Security::setFlash('danger', 'Security validation failed.');
    } else {
        $action = $_POST['action'];

        if ($action === 'create_patient') {
            $fullName = Security::sanitizeString($_POST['full_name'] ?? '');
            $phone = Security::sanitizeString($_POST['phone'] ?? '');
            $email = Security::sanitizeString($_POST['email'] ?? '');
            $address = Security::sanitizeString($_POST['address'] ?? '');
            $condition = Security::sanitizeString($_POST['primary_condition'] ?? '');
            $meds = Security::sanitizeString($_POST['regular_medicines'] ?? '');
            $refillDate = Security::sanitizeString($_POST['refill_due_date'] ?? date('Y-m-d', strtotime('+30 days')));
            $notes = Security::sanitizeString($_POST['notes'] ?? '');

            if (!empty($fullName) && !empty($phone)) {
                try {
                    $ins = $db->prepare("
                        INSERT INTO chronic_patients (full_name, phone, email, address, primary_condition, regular_medicines, refill_due_date, notes)
                        VALUES (:name, :phone, :email, :addr, :cond, :meds, :rdate, :notes)
                    ");
                    $ins->execute([
                        'name'  => $fullName,
                        'phone' => $phone,
                        'email' => $email,
                        'addr'  => $address,
                        'cond'  => $condition,
                        'meds'  => $meds,
                        'rdate' => $refillDate,
                        'notes' => $notes
                    ]);
                    Security::setFlash('success', "Patient '{$fullName}' registered for monthly refill care.");
                } catch (\Exception $e) {
                    Security::setFlash('danger', "Failed to register patient: " . $e->getMessage());
                }
                header("Location: customers.php");
                exit;
            }
        } elseif ($action === 'send_refill_email') {
            $patientId = (int)($_POST['patient_id'] ?? 0);
            $stmt = $db->prepare("SELECT * FROM chronic_patients WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $patientId]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($patient && !empty($patient['email'])) {
                $mailRes = Mailer::sendRefillReminder(
                    $patient['email'],
                    $patient['full_name'],
                    $patient['primary_condition'] ?? 'Chronic Condition',
                    $patient['regular_medicines'] ?? 'Monthly Regimen',
                    $patient['refill_due_date'] ?? date('Y-m-d'),
                    $patient['notes'] ?? ''
                );

                if ($mailRes['success']) {
                    $up = $db->prepare("UPDATE chronic_patients SET refill_status = 'Alert Sent' WHERE id = :id");
                    $up->execute(['id' => $patientId]);
                    Security::setFlash('success', "Refill reminder email sent to {$patient['full_name']} ({$patient['email']})!");
                } else {
                    Security::setFlash('danger', "Failed to dispatch email: " . $mailRes['message']);
                }
            } else {
                Security::setFlash('warning', "Patient has no registered email address on file.");
            }
            header("Location: customers.php");
            exit;
        } elseif ($action === 'send_all_due_emails') {
            $duePatients = $db->query("
                SELECT * FROM chronic_patients 
                WHERE refill_due_date <= date('now', '+5 days') AND email IS NOT NULL AND email != ''
            ")->fetchAll(PDO::FETCH_ASSOC);

            $sentCount = 0;
            $failCount = 0;

            foreach ($duePatients as $dp) {
                $res = Mailer::sendRefillReminder(
                    $dp['email'],
                    $dp['full_name'],
                    $dp['primary_condition'] ?? 'Chronic Care',
                    $dp['regular_medicines'] ?? 'Prescribed Regimen',
                    $dp['refill_due_date'],
                    $dp['notes'] ?? ''
                );
                if ($res['success']) {
                    $up = $db->prepare("UPDATE chronic_patients SET refill_status = 'Alert Sent' WHERE id = :id");
                    $up->execute(['id' => $dp['id']]);
                    $sentCount++;
                } else {
                    $failCount++;
                }
            }

            if ($sentCount > 0) {
                Security::setFlash('success', "Successfully dispatched {$sentCount} automated refill alerts via SMTP!" . ($failCount > 0 ? " ({$failCount} failed)" : ""));
            } else {
                Security::setFlash('info', "No patients with valid email addresses are due for reminders within 5 days.");
            }
            header("Location: customers.php");
            exit;
        } elseif ($action === 'mark_refill_completed') {
            $patientId = (int)($_POST['patient_id'] ?? 0);
            if ($patientId > 0) {
                $newDue = date('Y-m-d', strtotime('+30 days'));
                $up = $db->prepare("UPDATE chronic_patients SET last_dispensed = CURRENT_DATE, refill_due_date = :due, refill_status = 'Pending Alert' WHERE id = :id");
                $up->execute(['due' => $newDue, 'id' => $patientId]);

                Security::setFlash('success', "Monthly refill completed and next delivery date scheduled for {$newDue}.");
                header("Location: customers.php");
                exit;
            }
        }
    }
}

// Fetch all chronic patients
$patients = $db->query("
    SELECT * FROM chronic_patients 
    ORDER BY refill_due_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Metrics
$today = new DateTime('today');
$dueSoonCount = 0;
$overdueCount = 0;

foreach ($patients as &$p) {
    $rDate = new DateTime($p['refill_due_date']);
    $diff = (int)$today->diff($rDate)->format('%r%a');
    $p['days_to_refill'] = $diff;

    if ($diff < 0) {
        $p['status_badge'] = 'badge-danger';
        $p['status_label'] = 'Refill Overdue (' . abs($diff) . 'd)';
        $overdueCount++;
    } elseif ($diff <= 5) {
        $p['status_badge'] = 'badge-warning';
        $p['status_label'] = 'Due Soon (' . $diff . 'd)';
        $dueSoonCount++;
    } else {
        $p['status_badge'] = 'badge-success';
        $p['status_label'] = 'Upcoming (' . $diff . 'd)';
    }
}
unset($p);
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:1.4rem; color:var(--secondary); font-weight:800; display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-hospital-user" style="color:var(--primary);"></i> Chronic Care & Repeat Refill Directory
        </h2>
        <span style="font-size:0.85rem; color:var(--text-muted);">Retain long-term patients with automated 30-day chronic medicine refill reminders & doorstep replenishment</span>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <form action="" method="POST" style="margin:0;" onsubmit="return confirm('Send automated email refill alerts to all patients whose medications are due within 5 days?');">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
            <input type="hidden" name="action" value="send_all_due_emails">
            <button type="submit" class="btn btn-secondary btn-sm" style="display:inline-flex; align-items:center; gap:6px;">
                <i class="fa-solid fa-paper-plane" style="color:var(--primary);"></i> Dispatch All Due Email Alerts
            </button>
        </form>
        <button type="button" onclick="document.getElementById('addPatientModal').style.display='flex'" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-user-plus"></i> Register Chronic Patient
        </button>
    </div>
</div>

<!-- Refill KPI Summary -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:20px; margin-bottom:28px;">
    <div class="card" style="margin-bottom:0; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <span style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Refills Overdue</span>
            <div style="font-size:1.8rem; font-weight:800; color:var(--danger); margin-top:4px;"><?= $overdueCount; ?></div>
            <span style="font-size:0.8rem; color:var(--danger); font-weight:600;">Immediate reminder call needed</span>
        </div>
        <div style="width:48px; height:48px; border-radius:12px; background:#fee2e2; color:var(--danger); display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
            <i class="fa-solid fa-phone-volume"></i>
        </div>
    </div>

    <div class="card" style="margin-bottom:0; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <span style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Due Within 5 Days</span>
            <div style="font-size:1.8rem; font-weight:800; color:var(--warning); margin-top:4px;"><?= $dueSoonCount; ?></div>
            <span style="font-size:0.8rem; color:var(--warning); font-weight:600;">Send WhatsApp refill notice</span>
        </div>
        <div style="width:48px; height:48px; border-radius:12px; background:#fef3c7; color:var(--warning); display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
            <i class="fa-brands fa-whatsapp"></i>
        </div>
    </div>

    <div class="card" style="margin-bottom:0; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <span style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Active Chronic Profiles</span>
            <div style="font-size:1.8rem; font-weight:800; color:var(--secondary); margin-top:4px;"><?= count($patients); ?></div>
            <span style="font-size:0.8rem; color:var(--text-muted);">Hypertension, Diabetes & Cardiac</span>
        </div>
        <div style="width:48px; height:48px; border-radius:12px; background:var(--primary-light); color:var(--primary); display:flex; align-items:center; justify-content:center; font-size:1.3rem;">
            <i class="fa-solid fa-heart-pulse"></i>
        </div>
    </div>
</div>

<!-- Main Table Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-users" style="color:var(--primary);"></i> Chronic Care Patient Roster</h3>
        <input type="text" id="patient-search" placeholder="Search patient name, condition or phone..." class="form-control" style="width:260px; padding:6px 12px; font-size:0.88rem;">
    </div>

    <div class="table-responsive">
        <table id="patient-table">
            <thead>
                <tr>
                    <th>Patient Name & Contact</th>
                    <th>Primary Condition</th>
                    <th>Prescribed Chronic Regimen</th>
                    <th>Refill Due Date</th>
                    <th>Refill Urgency</th>
                    <th>Doorstep Address</th>
                    <th>Pharmacist Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($patients)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:30px; color:var(--text-muted);">No chronic patients registered yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($patients as $pt): 
                        $waText = urlencode("Hello " . $pt['full_name'] . ", this is HomeMed 24/7 Pharmacy. Your monthly refill for " . $pt['regular_medicines'] . " is scheduled. Would you like our pharmacist to pack and deliver it to your address today?");
                        $waUrl = "https://wa.me/" . preg_replace('/\D/', '', $pt['phone']) . "?text=" . $waText;
                    ?>
                        <tr class="patient-row" data-search="<?= strtolower(Security::e($pt['full_name'] . ' ' . $pt['phone'] . ' ' . $pt['primary_condition'])); ?>">
                            <td>
                                <strong style="color:var(--secondary); font-size:0.95rem;"><?= Security::e($pt['full_name']); ?></strong>
                                <div style="font-size:0.82rem; margin-top:2px;">
                                    <a href="tel:<?= Security::e($pt['phone']); ?>" style="color:var(--primary); text-decoration:none; font-weight:700;">
                                        <i class="fa-solid fa-phone"></i> <?= Security::e($pt['phone']); ?>
                                    </a>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-info" style="font-size:0.78rem;"><?= Security::e($pt['primary_condition']); ?></span>
                            </td>
                            <td>
                                <strong style="color:var(--secondary);"><?= Security::e($pt['regular_medicines']); ?></strong>
                                <?php if (!empty($pt['notes'])): ?>
                                    <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;"><?= Security::e($pt['notes']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="font-family:'JetBrains Mono', monospace;"><?= Security::e(date('d M Y', strtotime($pt['refill_due_date']))); ?></strong>
                            </td>
                            <td>
                                <span class="badge <?= $pt['status_badge']; ?>"><?= $pt['status_label']; ?></span>
                            </td>
                            <td style="max-width:200px; font-size:0.82rem; color:var(--text-muted);">
                                <?= Security::e($pt['address']); ?>
                            </td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <?php if (!empty($pt['email'])): ?>
                                        <form action="" method="POST" style="margin:0;" onsubmit="return confirm('Send refill reminder email to <?= Security::e($pt['full_name']); ?> (<?= Security::e($pt['email']); ?>)?');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                                            <input type="hidden" name="action" value="send_refill_email">
                                            <input type="hidden" name="patient_id" value="<?= $pt['id']; ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm" style="padding:5px 9px; color:var(--primary);" title="Send Email Refill Alert">
                                                <i class="fa-solid fa-envelope"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="<?= Security::e($waUrl); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-success btn-sm" style="padding:5px 9px;" title="WhatsApp Patient">
                                        <i class="fa-brands fa-whatsapp"></i>
                                    </a>
                                    <a href="tel:<?= Security::e($pt['phone']); ?>" class="btn btn-secondary btn-sm" style="padding:5px 9px;" title="Call Patient">
                                        <i class="fa-solid fa-phone"></i>
                                    </a>
                                    <form action="" method="POST" style="margin:0;" onsubmit="return confirm('Confirm that 30-day refill has been dispensed for <?= Security::e($pt['full_name']); ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                                        <input type="hidden" name="action" value="mark_refill_completed">
                                        <input type="hidden" name="patient_id" value="<?= $pt['id']; ?>">
                                        <button type="submit" class="btn btn-primary btn-sm" style="padding:5px 10px;" title="Mark Dispensed (+30 Days)">
                                            <i class="fa-solid fa-check"></i> Dispensed
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Register Patient -->
<div id="addPatientModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:16px; padding:28px; width:100%; max-width:540px; box-shadow:0 20px 50px rgba(0,0,0,0.2); max-height:90vh; overflow-y:auto;">
        <h4 style="color:var(--secondary); font-size:1.2rem; font-weight:800; margin-bottom:8px;">
            <i class="fa-solid fa-user-plus" style="color:var(--primary);"></i> Register Chronic Patient Profile
        </h4>
        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:18px;">Set up automated 30-day repeat replenishment for lifelong chronic treatments.</p>
        
        <form action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
            <input type="hidden" name="action" value="create_patient">

            <div class="form-group">
                <label>Full Patient Name *</label>
                <input type="text" name="full_name" class="form-control" required placeholder="e.g. Ramesh Chandra (Sr. Citizen)">
            </div>

            <div class="form-grid" style="grid-template-columns:1fr 1fr;">
                <div class="form-group">
                    <label>Mobile Number *</label>
                    <input type="text" name="phone" class="form-control" required placeholder="+91 98200 12345">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="patient@gmail.com">
                </div>
            </div>

            <div class="form-grid" style="grid-template-columns:1fr 1fr;">
                <div class="form-group">
                    <label>Chronic Condition Category *</label>
                    <select name="primary_condition" class="form-control" required>
                        <option value="Cardiology & BP">Cardiology & Hypertension</option>
                        <option value="Diabetes Type 2">Diabetes & Endocrine</option>
                        <option value="Respiratory & Asthma">Respiratory, Asthma & COPD</option>
                        <option value="Neurology & Brain">Neurology & Psychiatric</option>
                        <option value="Arthritis & Orthopedic">Arthritis & Joint Care</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Next Refill Due Date *</label>
                    <input type="date" name="refill_due_date" class="form-control" required value="<?= date('Y-m-d', strtotime('+30 days')); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Regular Chronic Medicines *</label>
                <input type="text" name="regular_medicines" class="form-control" required placeholder="e.g. Telma-H 40mg, Glycomet-GP 2">
            </div>

            <div class="form-group">
                <label>Delivery Home Address</label>
                <textarea name="address" class="form-control" rows="2" placeholder="Full address with apartment & landmark..."></textarea>
            </div>

            <div class="form-group">
                <label>Pharmacist Notes / Delivery Instructions</label>
                <input type="text" name="notes" class="form-control" placeholder="e.g. Call before delivery, senior citizen">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('addPatientModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check"></i> Register Patient</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('patient-search').addEventListener('input', function() {
        const val = this.value.toLowerCase().trim();
        document.querySelectorAll('.patient-row').forEach(row => {
            const s = row.getAttribute('data-search');
            row.style.display = s.includes(val) ? '' : 'none';
        });
    });
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
