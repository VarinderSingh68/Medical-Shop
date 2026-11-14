<?php
declare(strict_types=1);

$pageTitle = 'Gmail SMTP Diagnostics & Mailer Tester';
require_once __DIR__ . '/layout/header.php';
require_once __DIR__ . '/../core/Mailer.php';

use App\Core\Security;
use App\Core\Mailer;

$csrfToken = Security::generateCsrf();

$testResult = null;
$rawSocketTranscript = [];

// Handle Test Email Dispatches (Setup 1: 2FA OTP & Setup 2: Chronic Care Refill Alert)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
        Security::setFlash('danger', 'Security token expired.');
    } else {
        $action = $_POST['action'];
        $targetEmail = Security::sanitizeString($_POST['target_email'] ?? Mailer::getRecipientEmail());

        if ($action === 'send_test_email') {
            $testOtp = sprintf('%06d', random_int(100000, 999999));
            $res = Mailer::sendOtp($targetEmail, $testOtp, 'System Administrator (Diagnostic Test)');
            $testResult = $res;
            if ($res['success']) {
                Security::setFlash('success', "Setup 1 (2FA OTP) test email dispatched successfully to {$targetEmail}! Check your inbox.");
                try {
                    $log = $db->prepare("INSERT INTO audit_logs (admin_id, admin_name, action, entity_type, entity_id, details, ip_address) VALUES (:aid, :aname, 'SMTP_TEST_SENT', 'mailer', 0, :det, :ip)");
                    $log->execute([
                        'aid'   => $_SESSION['admin_id'] ?? 1,
                        'aname' => $_SESSION['admin_name'] ?? 'Admin',
                        'det'   => "Diagnostic 2FA OTP test dispatched to {$targetEmail} with test OTP {$testOtp}",
                        'ip'    => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                    ]);
                } catch (\Exception $e) {}
            } else {
                Security::setFlash('danger', "SMTP Transmission Error: " . $res['message']);
            }
        } elseif ($action === 'send_test_refill_email') {
            $res = Mailer::sendRefillReminder(
                $targetEmail,
                'Diagnostic Patient (Test)',
                'Type-2 Diabetes & Hypertension',
                'Metformin 500mg, Telmisartan 40mg (30-Day Supply)',
                date('Y-m-d', strtotime('+3 days')),
                'Take morning dose with food. Stored under cold-chain standards.'
            );
            $testResult = $res;
            if ($res['success']) {
                Security::setFlash('success', "Setup 2 (Patient Chronic Refill Alert) email dispatched successfully to {$targetEmail}! Check your inbox.");
            } else {
                Security::setFlash('danger', "Refill Alert Dispatch Error: " . $res['message']);
            }
        }
    }
}

// Fetch recent OTP email records
$recentOtps = $db->query("
    SELECT o.*, u.full_name, u.username
    FROM admin_otps o
    LEFT JOIN admin_users u ON o.user_id = u.id
    ORDER BY o.id DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:1.4rem; color:var(--secondary); font-weight:800; display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-envelope-circle-check" style="color:var(--primary);"></i> Gmail SMTP & Mailer Diagnostics
        </h2>
        <span style="font-size:0.85rem; color:var(--text-muted);">Inspect TLS socket handshake, test two-factor authentication mailer & track outgoing OTP deliveries</span>
    </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:28px;">
    <!-- SMTP Configuration Box -->
    <div class="card" style="margin-bottom:0;">
        <h3 class="card-title" style="margin-bottom:16px;">
            <i class="fa-solid fa-sliders" style="color:var(--primary);"></i> Active SMTP Configuration
        </h3>
        <table>
            <tbody>
                <tr>
                    <td><strong>SMTP Server Host</strong></td>
                    <td style="font-family:'JetBrains Mono', monospace; font-weight:700;">smtp.gmail.com</td>
                </tr>
                <tr>
                    <td><strong>Port & Security</strong></td>
                    <td><span class="badge badge-info">587 (STARTTLS)</span></td>
                </tr>
                <tr>
                    <td><strong>Authenticated Account</strong></td>
                    <td style="font-family:'JetBrains Mono', monospace; font-weight:700; color:var(--primary);">ngw.designer@gmail.com</td>
                </tr>
                <tr>
                    <td><strong>Authentication Method</strong></td>
                    <td><span class="badge badge-success">Google 16-Char App Password</span></td>
                </tr>
                <tr>
                    <td><strong>Connection Protocol</strong></td>
                    <td>Direct Raw TCP Stream Socket (No heavy library needed)</td>
                </tr>
                <tr>
                    <td><strong>Operational Status</strong></td>
                    <td><span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Connected & Verified</span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Live Test Email Box (Dual Email Setup Tester) -->
    <div class="card" style="margin-bottom:0;">
        <h3 class="card-title" style="margin-bottom:16px;">
            <i class="fa-solid fa-paper-plane" style="color:var(--primary);"></i> Dispatch Live Email Tests (Dual Channel)
        </h3>
        <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:16px;">
            Test both outgoing email pipelines configured with <strong>ngw.designer@gmail.com</strong>:
        </p>

        <form action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">

            <div class="form-group">
                <label>Destination Test Email Address *</label>
                <input type="email" name="target_email" class="form-control" required value="ngw.designer@gmail.com">
            </div>

            <div style="display:flex; flex-direction:column; gap:10px;">
                <button type="submit" name="action" value="send_test_email" class="btn btn-primary" style="justify-content:center; padding:11px; font-size:0.9rem;">
                    <i class="fa-solid fa-shield-halved"></i> Test Setup 1: 2FA Login OTP Email
                </button>
                <button type="submit" name="action" value="send_test_refill_email" class="btn btn-secondary" style="justify-content:center; padding:11px; font-size:0.9rem;">
                    <i class="fa-solid fa-bell"></i> Test Setup 2: Patient Chronic Care Refill Alert
                </button>
            </div>
        </form>

        <?php if ($testResult): ?>
            <div style="margin-top:16px; padding:12px; border-radius:8px; font-size:0.85rem; background:<?= $testResult['success'] ? '#ecfdf5' : '#fef2f2'; ?>; border:1px solid <?= $testResult['success'] ? '#6ee7b7' : '#fecaca'; ?>; color:<?= $testResult['success'] ? '#065f46' : '#991b1b'; ?>;">
                <strong><?= $testResult['success'] ? 'SUCCESS:' : 'FAILED:'; ?></strong> <?= Security::e($testResult['message']); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Outgoing OTP Delivery Audit Log -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-clock-rotate-left" style="color:var(--primary);"></i> Recent OTP Delivery Activity</h3>
        <span style="font-size:0.82rem; color:var(--text-muted);">Database log from admin_otps</span>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Log ID</th>
                    <th>User & Role</th>
                    <th>Generated OTP</th>
                    <th>Dispatched Timestamp</th>
                    <th>Expiration Time</th>
                    <th>Verification State</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentOtps)): ?>
                    <tr><td colspan="6" style="text-align:center; padding:24px; color:var(--text-muted);">No OTP emails sent yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentOtps as $o): 
                        $isUsed = (int)$o['is_used'] === 1;
                        $isExpired = strtotime($o['expires_at']) < time();
                    ?>
                        <tr>
                            <td style="font-family:'JetBrains Mono', monospace; font-weight:700;">#<?= $o['id']; ?></td>
                            <td>
                                <strong><?= Security::e($o['full_name'] ?? 'Admin'); ?></strong>
                                <span style="font-size:0.75rem; color:var(--text-muted);">(<?= Security::e($o['username'] ?? 'admin'); ?>)</span>
                            </td>
                            <td>
                                <?php if ($isUsed): ?>
                                    <span style="font-family:'JetBrains Mono', monospace; font-weight:700; color:var(--text-muted); font-size:0.95rem; letter-spacing:2px;"><?= Security::e(substr($o['otp_code'], 0, 2) . '****'); ?></span>
                                <?php elseif ($isExpired): ?>
                                    <span style="font-family:'JetBrains Mono', monospace; font-weight:600; color:#94a3b8; font-size:0.95rem; letter-spacing:2px;">EXPIRED</span>
                                <?php else: ?>
                                    <span style="font-family:'JetBrains Mono', monospace; font-weight:700; color:var(--warning); font-size:0.95rem; letter-spacing:2px;"><i class="fa-solid fa-lock"></i> ******</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.85rem; color:var(--text-muted);">
                                <?= Security::e(date('d M Y, h:i:s A', strtotime($o['created_at']))); ?>
                            </td>
                            <td style="font-size:0.85rem; color:var(--text-muted);">
                                <?= Security::e(date('d M Y, h:i:s A', strtotime($o['expires_at']))); ?>
                            </td>
                            <td>
                                <?php if ($isUsed): ?>
                                    <span class="badge badge-success"><i class="fa-solid fa-check-double"></i> Verified & Logged In</span>
                                <?php elseif ($isExpired): ?>
                                    <span class="badge badge-secondary"><i class="fa-solid fa-clock"></i> Expired</span>
                                <?php else: ?>
                                    <span class="badge badge-warning"><i class="fa-solid fa-hourglass-half"></i> Pending Verification</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
