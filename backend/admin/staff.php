<?php
declare(strict_types=1);

$pageTitle = 'Staff Accounts & Role-Based Access';
require_once __DIR__ . '/layout/header.php';

use App\Core\Security;

$csrfToken = Security::generateCsrf();

// Form Actions: Add / Status Toggle / Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
        Security::setFlash('danger', 'Security validation failed.');
    } else {
        $action = $_POST['action'];

        if ($action === 'create_staff') {
            $name = Security::sanitizeString($_POST['full_name'] ?? '');
            $username = strtolower(trim($_POST['username'] ?? ''));
            $email = strtolower(trim($_POST['email'] ?? ''));
            $rawRole = Security::sanitizeString($_POST['role'] ?? 'pharmacist');
            $password = trim($_POST['password'] ?? '');

            // Whitelist allowed roles (prevents role escalation via tampered form)
            $allowedRoles = ['super_admin', 'pharmacist', 'billing_staff', 'inventory_manager', 'delivery_partner'];
            $role = in_array($rawRole, $allowedRoles, true) ? $rawRole : 'pharmacist';

            // Password strength: min 8 chars, at least 1 uppercase, 1 digit, 1 special char
            $passwordValid = strlen($password) >= 8
                && preg_match('/[A-Z]/', $password)
                && preg_match('/[0-9]/', $password)
                && preg_match('/[^a-zA-Z0-9]/', $password);

            if (!empty($name) && !empty($username) && !empty($email) && $passwordValid) {
                // Validate email format
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Security::setFlash('danger', 'Please enter a valid email address.');
                } else {
                    // Check duplicate
                    $check = $db->prepare("SELECT COUNT(*) FROM admin_users WHERE username = :u OR email = :e");
                    $check->execute(['u' => $username, 'e' => $email]);
                    if ((int)$check->fetchColumn() > 0) {
                        Security::setFlash('danger', 'A staff member with this username or email already exists.');
                    } else {
                        $targetAlgo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
                        $hash = password_hash($password, $targetAlgo);
                        $ins = $db->prepare("
                            INSERT INTO admin_users (full_name, username, email, password, role, status)
                            VALUES (:name, :u, :e, :p, :r, 'Active')
                        ");
                        $ins->execute([
                            'name' => $name,
                            'u' => $username,
                            'e' => $email,
                            'p' => $hash,
                            'r' => $role
                        ]);

                        // Audit
                        try {
                            $log = $db->prepare("INSERT INTO audit_logs (admin_id, admin_name, action, entity_type, entity_id, details, ip_address) VALUES (:aid, :aname, 'STAFF_CREATED', 'admin_user', :sid, :det, :ip)");
                            $log->execute([
                                'aid'   => $_SESSION['admin_id'] ?? 1,
                                'aname' => $_SESSION['admin_name'] ?? 'Admin',
                                'sid'   => (int)$db->lastInsertId(),
                                'det'   => "Created new staff account {$username} with role {$role}",
                                'ip'    => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                            ]);
                        } catch (\Exception $e) {}

                        Security::setFlash('success', "Staff member '{$name}' created with role: {$role}.");
                    }
                }
            } else {
                Security::setFlash('danger', 'Password must be at least 8 characters with 1 uppercase letter, 1 number, and 1 special character.');
            }
            header("Location: staff.php");
            exit;
        } elseif ($action === 'toggle_status') {
            $staffId = (int)($_POST['staff_id'] ?? 0);
            $newStatus = ($_POST['current_status'] === 'Active') ? 'Suspended' : 'Active';

            // Prevent suspending own account
            if ($staffId === (int)$_SESSION['admin_id']) {
                Security::setFlash('danger', 'You cannot suspend your own logged-in account.');
            } else {
                $up = $db->prepare("UPDATE admin_users SET status = :stat WHERE id = :id");
                $up->execute(['stat' => $newStatus, 'id' => $staffId]);
                Security::setFlash('success', "Staff account status changed to {$newStatus}.");
            }
            header("Location: staff.php");
            exit;
        } elseif ($action === 'reset_password') {
            $staffId = (int)($_POST['staff_id'] ?? 0);
            $newPass = trim($_POST['new_password'] ?? '');

            // Password strength check on reset too
            $resetPassValid = strlen($newPass) >= 8
                && preg_match('/[A-Z]/', $newPass)
                && preg_match('/[0-9]/', $newPass)
                && preg_match('/[^a-zA-Z0-9]/', $newPass);

            if ($staffId > 0 && $resetPassValid) {
                $targetAlgo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
                $hash = password_hash($newPass, $targetAlgo);
                $up = $db->prepare("UPDATE admin_users SET password = :p WHERE id = :id");
                $up->execute(['p' => $hash, 'id' => $staffId]);
                Security::setFlash('success', "Password successfully reset for staff ID #{$staffId}.");
            } else {
                Security::setFlash('danger', 'New password must be at least 8 characters with 1 uppercase, 1 number, and 1 special character.');
            }
            header("Location: staff.php");
            exit;
        }
    }
}

// Fetch all staff members
$staffMembers = $db->query("
    SELECT id, full_name, username, email, role, status, last_login, created_at
    FROM admin_users
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:1.4rem; color:var(--secondary); font-weight:800; display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-users-gear" style="color:var(--primary);"></i> Staff Accounts & Access Control
        </h2>
        <span style="font-size:0.85rem; color:var(--text-muted);">Manage pharmacist profiles, assign operational roles & enforce store access permissions</span>
    </div>
    <div style="display:flex; gap:10px;">
        <button type="button" onclick="document.getElementById('addStaffModal').style.display='flex'" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-user-plus"></i> Add New Staff Member
        </button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-id-card-clip" style="color:var(--primary);"></i> Active Pharmacy Personnel</h3>
        <span style="font-size:0.82rem; color:var(--text-muted);"><?= count($staffMembers); ?> total team members</span>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Staff Member</th>
                    <th>Username</th>
                    <th>Email Address</th>
                    <th>Designated Role</th>
                    <th>Account Status</th>
                    <th>Last Active</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staffMembers as $s): ?>
                    <tr>
                        <td>
                            <strong style="color:var(--secondary); font-size:0.95rem;"><?= Security::e($s['full_name']); ?></strong>
                            <?php if ($s['id'] === $_SESSION['admin_id']): ?>
                                <span class="badge badge-info" style="font-size:0.7rem; margin-left:6px;">You</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="font-family:'JetBrains Mono', monospace; font-weight:700; color:var(--primary);"><?= Security::e($s['username']); ?></span>
                        </td>
                        <td style="font-size:0.88rem; color:var(--text-muted);">
                            <?= Security::e($s['email']); ?>
                        </td>
                        <td>
                            <?php if ($s['role'] === 'super_admin'): ?>
                                <span class="badge badge-success"><i class="fa-solid fa-crown"></i> Super Admin</span>
                            <?php elseif ($s['role'] === 'pharmacist'): ?>
                                <span class="badge badge-info"><i class="fa-solid fa-user-doctor"></i> Lead Pharmacist</span>
                            <?php elseif ($s['role'] === 'store_manager'): ?>
                                <span class="badge badge-warning"><i class="fa-solid fa-shop"></i> Store Manager</span>
                            <?php else: ?>
                                <span class="badge badge-secondary"><?= Security::e($s['role']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($s['status'] === 'Active'): ?>
                                <span class="badge badge-success"><i class="fa-solid fa-circle-check"></i> Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger"><i class="fa-solid fa-circle-pause"></i> Suspended</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.82rem; color:var(--text-muted);">
                            <?= !empty($s['last_login']) ? Security::e(date('d M Y, h:i A', strtotime($s['last_login']))) : 'Never'; ?>
                        </td>
                        <td style="text-align:right;">
                            <div style="display:inline-flex; gap:8px;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="openResetModal(<?= $s['id']; ?>, '<?= Security::e(addslashes($s['full_name'])); ?>')" title="Reset Password">
                                    <i class="fa-solid fa-key"></i>
                                </button>
                                <?php if ($s['id'] !== $_SESSION['admin_id']): ?>
                                    <form action="" method="POST" style="margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="staff_id" value="<?= $s['id']; ?>">
                                        <input type="hidden" name="current_status" value="<?= $s['status']; ?>">
                                        <button type="submit" class="btn <?= $s['status'] === 'Active' ? 'btn-danger' : 'btn-success'; ?> btn-sm" title="<?= $s['status'] === 'Active' ? 'Suspend' : 'Activate'; ?>">
                                            <i class="fa-solid <?= $s['status'] === 'Active' ? 'fa-user-lock' : 'fa-user-check'; ?>"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add New Staff -->
<div id="addStaffModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:16px; padding:28px; width:100%; max-width:500px; box-shadow:0 20px 50px rgba(0,0,0,0.2);">
        <h4 style="color:var(--secondary); font-size:1.2rem; font-weight:800; margin-bottom:8px;">
            <i class="fa-solid fa-user-plus" style="color:var(--primary);"></i> Add Staff Member
        </h4>
        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:18px;">Create login credentials for pharmacists, counter dispensers, and store managers.</p>
        
        <form action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
            <input type="hidden" name="action" value="create_staff">

            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" class="form-control" required placeholder="e.g. Dr. Anil Kulkarni, B.Pharm">
            </div>

            <div class="form-grid" style="grid-template-columns:1fr 1fr;">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="username" class="form-control" required placeholder="pharmacist_anil">
                </div>
                <div class="form-group">
                    <label>Operational Role *</label>
                    <select name="role" class="form-control" required>
                        <option value="pharmacist">Lead Pharmacist (Rx & Dispense)</option>
                        <option value="store_manager">Store Manager (Inventory & Orders)</option>
                        <option value="delivery_coordinator">Delivery Coordinator</option>
                        <option value="super_admin">Super Administrator</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Work Email Address *</label>
                <input type="email" name="email" class="form-control" required placeholder="anil@homemed.com">
            </div>

            <div class="form-group">
                <label>Initial Login Password * (Min 6 chars)</label>
                <input type="password" name="password" class="form-control" required minlength="6" placeholder="••••••••">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('addStaffModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-check"></i> Create Staff Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reset Password -->
<div id="resetPassModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:16px; padding:28px; width:100%; max-width:440px; box-shadow:0 20px 50px rgba(0,0,0,0.2);">
        <h4 style="color:var(--secondary); font-size:1.15rem; font-weight:800; margin-bottom:8px;">Reset Staff Password</h4>
        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:16px;" id="reset-modal-sub">Reset credentials for team member.</p>
        
        <form action="" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="staff_id" id="reset-staff-id" value="0">

            <div class="form-group">
                <label>New Password * (Min 6 chars)</label>
                <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Enter new strong password">
            </div>

            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('resetPassModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-key"></i> Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openResetModal(staffId, staffName) {
        document.getElementById('reset-staff-id').value = staffId;
        document.getElementById('reset-modal-sub').innerText = `Reset password for ${staffName}`;
        document.getElementById('resetPassModal').style.display = 'flex';
    }
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
