<?php
declare(strict_types=1);

$pageTitle = 'Suppliers & Vendors';
require_once __DIR__ . '/layout/header.php';
require_once __DIR__ . '/../models/Vendor.php';

use App\Core\Security;
use App\Models\Vendor;

$vendorModel = new Vendor($db);
$csrfToken = Security::generateCsrf();

// Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
        Security::setFlash('danger', 'Security validation expired.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $data = [
                'company_name'    => Security::sanitizeString($_POST['company_name'] ?? ''),
                'contact_person'  => Security::sanitizeString($_POST['contact_person'] ?? ''),
                'phone'           => Security::sanitizeString($_POST['phone'] ?? ''),
                'email'           => Security::sanitizeString($_POST['email'] ?? ''),
                'drug_license_no' => Security::sanitizeString($_POST['drug_license_no'] ?? ''),
                'gst_number'      => Security::sanitizeString($_POST['gst_number'] ?? ''),
                'address'         => Security::sanitizeString($_POST['address'] ?? ''),
                'status'          => Security::sanitizeString($_POST['status'] ?? 'Active'),
            ];

            if ($action === 'create') {
                $vendorModel->create($data);
                Security::setFlash('success', "Vendor '{$data['company_name']}' added to records.");
            } else {
                $id = (int)$_POST['vendor_id'];
                $vendorModel->update($id, $data);
                Security::setFlash('success', "Vendor details updated successfully.");
            }
            header("Location: vendors.php");
            exit;
        } elseif ($action === 'delete') {
            $id = (int)$_POST['vendor_id'];
            $vendorModel->delete($id);
            Security::setFlash('success', "Vendor deleted.");
            header("Location: vendors.php");
            exit;
        }
    }
}

// Edit Mode
$editItem = null;
if (isset($_GET['edit'])) {
    $editItem = $vendorModel->find((int)$_GET['edit']);
}

$vendors = $vendorModel->getAll();
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <h3 style="color:var(--secondary); font-size:1.4rem;">Vendors & Pharmaceutical Distributors</h3>
        <span style="font-size:0.85rem; color:var(--text-muted);">Manage licensed medicine wholesalers, batch supply contracts, and drug compliance numbers</span>
    </div>
</div>

<div style="display:grid; grid-template-columns:3fr 2fr; gap:24px; align-items:start;">
    <!-- Vendors Table -->
    <div class="card">
        <div class="card-header">
            <h4 class="card-title"><i class="fa-solid fa-building-wheat"></i> Registered Distributors (<?= count($vendors); ?>)</h4>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Company & Contact</th>
                        <th>Phone / Email</th>
                        <th>Drug License & GST</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vendors)): ?>
                        <tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);">No suppliers registered yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($vendors as $v): ?>
                            <tr>
                                <td>
                                    <strong><?= Security::e($v['company_name']); ?></strong><br>
                                    <span style="font-size:0.8rem; color:var(--text-muted);"><i class="fa-solid fa-user"></i> <?= Security::e($v['contact_person']); ?></span>
                                </td>
                                <td style="font-size:0.85rem;">
                                    <a href="tel:<?= Security::e($v['phone']); ?>"><?= Security::e($v['phone']); ?></a><br>
                                    <span style="color:var(--text-muted); font-size:0.8rem;"><?= Security::e($v['email'] ?? 'N/A'); ?></span>
                                </td>
                                <td style="font-size:0.85rem;">
                                    <code><?= Security::e($v['drug_license_no']); ?></code><br>
                                    <span style="font-size:0.75rem; color:var(--text-muted);">GST: <?= Security::e($v['gst_number'] ?? 'N/A'); ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $v['status'] === 'Active' ? 'badge-success' : 'badge-danger'; ?>">
                                        <?= Security::e($v['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:6px;">
                                        <a href="vendors.php?edit=<?= (int)$v['id']; ?>" class="btn btn-secondary btn-sm" title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <form action="" method="POST" onsubmit="return confirm('Delete this vendor?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                                            <input type="hidden" name="vendor_id" value="<?= (int)$v['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" title="Delete"><i class="fa-solid fa-trash"></i></button>
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

    <!-- Add / Edit Vendor Form -->
    <div class="card">
        <h4 class="card-title" style="margin-bottom:16px;">
            <i class="fa-solid <?= $editItem ? 'fa-pen-to-square' : 'fa-circle-plus'; ?>"></i>
            <?= $editItem ? 'Edit Vendor Details' : 'Register New Vendor / Supplier'; ?>
        </h4>

        <?php if ($editItem): ?>
            <div style="margin-bottom:12px;">
                <a href="vendors.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Cancel Edit</a>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <input type="hidden" name="action" value="<?= $editItem ? 'update' : 'create'; ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
            <?php if ($editItem): ?>
                <input type="hidden" name="vendor_id" value="<?= (int)$editItem['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Pharmaceutical Company / Supplier Name *</label>
                <input type="text" name="company_name" class="form-control" required placeholder="e.g. Medplus Distributors Pvt Ltd" value="<?= Security::e($editItem['company_name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Contact Person / Sales Manager *</label>
                <input type="text" name="contact_person" class="form-control" required placeholder="e.g. Ramesh Kumar" value="<?= Security::e($editItem['contact_person'] ?? ''); ?>">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label>Phone / WhatsApp *</label>
                    <input type="tel" name="phone" class="form-control" required placeholder="+91 9876543210" value="<?= Security::e($editItem['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="sales@distributor.com" value="<?= Security::e($editItem['email'] ?? ''); ?>">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label>Wholesale Drug License No *</label>
                    <input type="text" name="drug_license_no" class="form-control" required placeholder="WDL-2024-5541" value="<?= Security::e($editItem['drug_license_no'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>GST Identification Number</label>
                    <input type="text" name="gst_number" class="form-control" placeholder="29ABCDE1234F1Z5" value="<?= Security::e($editItem['gst_number'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Warehouse / Office Address *</label>
                <textarea name="address" class="form-control" rows="2" required placeholder="Warehouse address"><?= Security::e($editItem['address'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label>Supplier Status</label>
                <select name="status" class="form-control">
                    <option value="Active" <?= (!$editItem || $editItem['status'] === 'Active') ? 'selected' : ''; ?>>Active (Can order stock)</option>
                    <option value="Inactive" <?= ($editItem && $editItem['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive (Blacklisted / On hold)</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:12px;">
                <i class="fa-solid fa-floppy-disk"></i> <?= $editItem ? 'Update Vendor' : 'Save Supplier'; ?>
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
