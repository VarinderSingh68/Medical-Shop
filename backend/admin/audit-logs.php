<?php
declare(strict_types=1);

$pageTitle = 'Security & Audit Logs';
require_once __DIR__ . '/layout/header.php';

use App\Core\Security;

$csrfToken = Security::generateCsrf();

// Handle Clear Logs (Super Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
        Security::setFlash('danger', 'Security validation failed.');
    } else {
        if (($_SESSION['admin_role'] ?? '') === 'super_admin') {
            $db->exec("DELETE FROM audit_logs");
            Security::setFlash('success', 'Audit trail cleared.');
        } else {
            Security::setFlash('danger', 'Only Super Admin can purge security logs.');
        }
        header("Location: audit-logs.php");
        exit;
    }
}

// Fetch Audit Logs
$logs = $db->query("
    SELECT * FROM audit_logs 
    ORDER BY id DESC 
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Recent Failed / Login Attempts
$loginAttempts = $db->query("
    SELECT * FROM login_attempts 
    ORDER BY id DESC 
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:1.4rem; color:var(--secondary); font-weight:800; display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-shield-virus" style="color:var(--primary);"></i> Security & System Audit Trail
        </h2>
        <span style="font-size:0.85rem; color:var(--text-muted);">Immutable forensic activity logs, administrator actions, inventory adjustments & failed authorization attempts</span>
    </div>
    <div style="display:flex; gap:10px;">
        <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm"><i class="fa-solid fa-print"></i> Print Audit Log</button>
        <?php if (($_SESSION['admin_role'] ?? '') === 'super_admin'): ?>
            <form action="" method="POST" style="margin:0;" onsubmit="return confirm('Purge audit log records? This cannot be undone.');">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                <input type="hidden" name="action" value="clear_logs">
                <button type="submit" class="btn btn-secondary btn-sm" style="color:var(--danger); border-color:#fecaca;">
                    <i class="fa-solid fa-trash-can"></i> Purge Logs
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid; grid-template-columns: 2fr 1fr; gap:24px;">
    <!-- Main Audit Trail -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-list-check" style="color:var(--primary);"></i> Administrative Actions Log</h3>
            <input type="text" id="log-search" placeholder="Search action, user or details..." class="form-control" style="width:240px; padding:6px 12px; font-size:0.88rem;">
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action Type</th>
                        <th>Event Details</th>
                        <th>Client IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);">No administrative events recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr class="log-row" data-search="<?= strtolower(Security::e($l['admin_name'] . ' ' . $l['action'] . ' ' . $l['details'])); ?>">
                                <td style="font-size:0.82rem; color:var(--text-muted); white-space:nowrap;">
                                    <?= Security::e(date('d M Y, h:i:s A', strtotime($l['created_at']))); ?>
                                </td>
                                <td>
                                    <strong style="color:var(--secondary); font-size:0.88rem;"><?= Security::e($l['admin_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-info" style="font-size:0.75rem; font-family:'JetBrains Mono', monospace;"><?= Security::e($l['action']); ?></span>
                                </td>
                                <td style="font-size:0.85rem; color:var(--text-main);">
                                    <?= Security::e($l['details']); ?>
                                </td>
                                <td>
                                    <span style="font-family:'JetBrains Mono', monospace; font-size:0.8rem; color:var(--text-muted);"><?= Security::e($l['ip_address']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Security & Login Attempts Side Log -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-lock" style="color:var(--danger);"></i> Login Attempts</h3>
        </div>

        <p style="font-size:0.82rem; color:var(--text-muted); margin-bottom:14px;">
            Rate limiter automatically blocks IPs exceeding 10 failed password attempts within 15 minutes.
        </p>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Attempted Username</th>
                        <th>IP Address</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($loginAttempts)): ?>
                        <tr><td colspan="3" style="text-align:center; padding:20px; color:var(--text-muted);">No failed attempts logged.</td></tr>
                    <?php else: ?>
                        <?php foreach ($loginAttempts as $la): ?>
                            <tr>
                                <td>
                                    <span style="font-weight:700; color:var(--danger);"><?= Security::e($la['username']); ?></span>
                                </td>
                                <td>
                                    <span style="font-family:'JetBrains Mono', monospace; font-size:0.8rem;"><?= Security::e($la['ip_address']); ?></span>
                                </td>
                                <td style="font-size:0.78rem; color:var(--text-muted);">
                                    <?= Security::e(date('d M, h:i A', strtotime($la['attempted_at']))); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.getElementById('log-search').addEventListener('input', function() {
        const val = this.value.toLowerCase().trim();
        document.querySelectorAll('.log-row').forEach(row => {
            const s = row.getAttribute('data-search');
            row.style.display = s.includes(val) ? '' : 'none';
        });
    });
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
