<?php
declare(strict_types=1);

$pageTitle = 'Medicine Inventory Management';
require_once __DIR__ . '/layout/header.php';
require_once __DIR__ . '/../models/Medicine.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../core/Uploader.php';

use App\Core\Security;
use App\Models\Medicine;
use App\Models\Category;

$medModel = new Medicine($db);
$catModel = new Category($db);

$categories = $catModel->getAll();
$csrfToken = Security::generateCsrf();

// Form Actions: Add / Edit / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
        Security::setFlash('danger', 'Security token expired.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $data = [
                'category_id'           => (int)$_POST['category_id'],
                'name'                  => Security::sanitizeString($_POST['name'] ?? ''),
                'generic_name'          => Security::sanitizeString($_POST['generic_name'] ?? ''),
                'dosage_form'           => Security::sanitizeString($_POST['dosage_form'] ?? 'Tablet'),
                'strength'              => Security::sanitizeString($_POST['strength'] ?? ''),
                'manufacturer'          => Security::sanitizeString($_POST['manufacturer'] ?? ''),
                'batch_number'          => Security::sanitizeString($_POST['batch_number'] ?? ''),
                'expiry_date'           => Security::sanitizeString($_POST['expiry_date'] ?? ''),
                'purchase_price'        => Security::sanitizeFloat($_POST['purchase_price'] ?? 0),
                'selling_price'         => Security::sanitizeFloat($_POST['selling_price'] ?? 0),
                'stock_quantity'        => Security::sanitizeInt($_POST['stock_quantity'] ?? 0),
                'low_stock_threshold'   => Security::sanitizeInt($_POST['low_stock_threshold'] ?? 10),
                'requires_prescription' => isset($_POST['requires_prescription']) ? 1 : 0,
                'indications'           => Security::sanitizeString($_POST['indications'] ?? ''),
                'side_effects'          => Security::sanitizeString($_POST['side_effects'] ?? ''),
                'is_active'             => isset($_POST['is_active']) ? 1 : 0,
            ];

            if ($action === 'create') {
                $medModel->create($data);
                Security::setFlash('success', "Medicine '{$data['name']}' added to inventory.");
            } else {
                $id = (int)$_POST['medicine_id'];
                $medModel->update($id, $data);
                Security::setFlash('success', "Medicine '{$data['name']}' updated successfully.");
            }
            header("Location: medicines.php");
            exit;
        } elseif ($action === 'delete') {
            $id = (int)$_POST['medicine_id'];
            $medModel->delete($id);
            Security::setFlash('success', "Medicine removed from active inventory.");
            header("Location: medicines.php");
            exit;
        }
    }
}

// Edit Mode
$editItem = null;
if (isset($_GET['edit'])) {
    $editItem = $medModel->find((int)$_GET['edit']);
}

// Filter List
$catFilter = isset($_GET['category']) && $_GET['category'] !== '' ? (int)$_GET['category'] : null;
$search = isset($_GET['q']) ? trim($_GET['q']) : null;
$onlyLowStock = (isset($_GET['filter']) && $_GET['filter'] === 'low_stock');

// Pagination
$perPage = 50;
$page = max(1, (int)($_GET['p'] ?? 1));

if ($onlyLowStock) {
    $medicines = $medModel->getLowStock();
    $totalMedicines = count($medicines);
    $totalPages = 1;
} else {
    $totalMedicines = $medModel->count($catFilter, $search, false);
    $totalPages = max(1, (int)ceil($totalMedicines / $perPage));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $perPage;
    $medicines = $medModel->getAll($catFilter, $search, false, $perPage, $offset);
}

$buildAdminPageUrl = function(int $targetPage) use ($catFilter, $search, $onlyLowStock): string {
    $params = ['p' => $targetPage];
    if ($catFilter !== null) $params['category'] = $catFilter;
    if (!empty($search)) $params['q'] = $search;
    if ($onlyLowStock) $params['filter'] = 'low_stock';
    return 'medicines.php?' . http_build_query($params);
};
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <h3 style="color:var(--secondary); font-size:1.4rem;">Medicine Inventory</h3>
        <span style="font-size:0.85rem; color:var(--text-muted);">Manage medicines across all diseases, batch numbers, dosage forms & stock limits</span>
    </div>
    <button onclick="document.getElementById('medicine-form-card').scrollIntoView({behavior:'smooth'});" class="btn btn-primary">
        <i class="fa-solid fa-plus"></i> Add New Medicine
    </button>
</div>

<!-- Search & Filter Bar -->
<div class="card" style="padding:16px 20px; margin-bottom:20px;">
    <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
        <input type="text" name="q" value="<?= Security::e($search ?? ''); ?>" placeholder="Search by Brand Name, Generic or Disease..." class="form-control" style="flex:2; min-width:200px;">
        <select name="category" class="form-control" style="flex:1; min-width:180px;">
            <option value="">All Disease Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['id']; ?>" <?= ($catFilter === (int)$cat['id']) ? 'selected' : ''; ?>>
                    <?= Security::e($cat['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
        <?php if (!empty($search) || $catFilter !== null || $onlyLowStock): ?>
            <a href="medicines.php" class="btn btn-secondary">Clear</a>
        <?php endif; ?>
    </form>
</div>

<div style="display:grid; grid-template-columns:3fr 2fr; gap:24px; align-items:start;">
    <!-- Medicines Table -->
    <div class="card">
        <div class="card-header">
            <h4 class="card-title"><i class="fa-solid fa-pills"></i> Inventory Registry (<?= number_format($totalMedicines); ?> total)</h4>
            <span style="font-size:0.82rem; color:var(--text-muted);">
                Showing <?= number_format($totalMedicines > 0 ? ($offset ?? 0) + 1 : 0); ?> &ndash; <?= number_format(min($totalMedicines, ($offset ?? 0) + count($medicines))); ?>
            </span>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Medicine & Composition</th>
                        <th>Disease Category</th>
                        <th>Batch / Expiry</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($medicines)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">No medicines found matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($medicines as $m): ?>
                            <tr>
                                <td>
                                    <strong><?= Security::e($m['name']); ?></strong> 
                                    <?php if ($m['requires_prescription']): ?>
                                        <span class="badge badge-danger" style="font-size:0.7rem;">Rx</span>
                                    <?php endif; ?>
                                    <br>
                                    <span style="font-size:0.8rem; color:var(--text-muted);"><?= Security::e($m['generic_name']); ?></span><br>
                                    <span style="font-size:0.75rem; background:#f1f5f9; padding:2px 6px; border-radius:4px;"><?= Security::e($m['dosage_form']); ?> &bull; <?= Security::e($m['strength']); ?></span>
                                </td>
                                <td><?= Security::e($m['category_name']); ?></td>
                                <td style="font-size:0.85rem;">
                                    <code><?= Security::e($m['batch_number']); ?></code><br>
                                    <span style="color:var(--text-muted); font-size:0.8rem;">Exp: <?= Security::e($m['expiry_date']); ?></span>
                                </td>
                                <td>
                                    <strong><?= format_price($m['selling_price']); ?></strong><br>
                                    <span style="font-size:0.75rem; color:var(--text-muted);">Cost: <?= format_price($m['purchase_price']); ?></span>
                                </td>
                                <td>
                                    <?php if ($m['stock_quantity'] <= $m['low_stock_threshold']): ?>
                                        <span class="badge badge-danger"><?= (int)$m['stock_quantity']; ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-success"><?= (int)$m['stock_quantity']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex; gap:6px;">
                                        <a href="medicines.php?edit=<?= (int)$m['id']; ?>" class="btn btn-secondary btn-sm" title="Edit Medicine">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <form action="" method="POST" onsubmit="return confirm('Remove this medicine permanently?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                                            <input type="hidden" name="medicine_id" value="<?= (int)$m['id']; ?>">
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

        <?php if ($totalPages > 1): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; padding:14px 16px; border-top:1px solid var(--border); flex-wrap:wrap; gap:10px;">
                <span style="font-size:0.82rem; color:var(--text-muted);">Page <strong><?= $page; ?></strong> of <strong><?= $totalPages; ?></strong></span>
                <div style="display:flex; gap:6px; align-items:center;">
                    <?php if ($page > 1): ?>
                        <a href="<?= $buildAdminPageUrl(1); ?>" class="btn btn-secondary btn-sm" title="First Page"><i class="fa-solid fa-angles-left"></i></a>
                        <a href="<?= $buildAdminPageUrl($page - 1); ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-chevron-left"></i> Prev</a>
                    <?php endif; ?>

                    <?php
                        $startP = max(1, $page - 2);
                        $endP = min($totalPages, $page + 2);
                        for ($p = $startP; $p <= $endP; $p++):
                    ?>
                        <a href="<?= $buildAdminPageUrl($p); ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary'; ?>" style="min-width:32px; justify-content:center;">
                            <?= $p; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="<?= $buildAdminPageUrl($page + 1); ?>" class="btn btn-secondary btn-sm">Next <i class="fa-solid fa-chevron-right"></i></a>
                        <a href="<?= $buildAdminPageUrl($totalPages); ?>" class="btn btn-secondary btn-sm" title="Last Page"><i class="fa-solid fa-angles-right"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add / Edit Medicine Form -->
    <div class="card" id="medicine-form-card">
        <h4 class="card-title" style="margin-bottom:16px;">
            <i class="fa-solid <?= $editItem ? 'fa-pen-to-square' : 'fa-circle-plus'; ?>"></i>
            <?= $editItem ? 'Edit Medicine Details' : 'Add New Medicine to Stock'; ?>
        </h4>

        <?php if ($editItem): ?>
            <div style="margin-bottom:12px;">
                <a href="medicines.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Cancel Edit</a>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <input type="hidden" name="action" value="<?= $editItem ? 'update' : 'create'; ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
            <?php if ($editItem): ?>
                <input type="hidden" name="medicine_id" value="<?= (int)$editItem['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Disease / Medical Category *</label>
                <select name="category_id" class="form-control" required>
                    <option value="">Select Disease Category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id']; ?>" <?= ($editItem && (int)$editItem['category_id'] === (int)$cat['id']) ? 'selected' : ''; ?>>
                            <?= Security::e($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Medicine Brand / Trade Name *</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. Telma-H, Glycomet" value="<?= Security::e($editItem['name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Generic Composition / Salts *</label>
                <input type="text" name="generic_name" class="form-control" required placeholder="e.g. Telmisartan + Hydrochlorothiazide" value="<?= Security::e($editItem['generic_name'] ?? ''); ?>">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label>Dosage Form *</label>
                    <select name="dosage_form" class="form-control" required>
                        <?php foreach (['Tablet', 'Capsule', 'Syrup', 'Injection', 'Ointment', 'Drops', 'Inhaler', 'Powder'] as $form): ?>
                            <option value="<?= $form; ?>" <?= ($editItem && $editItem['dosage_form'] === $form) ? 'selected' : ''; ?>><?= $form; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Strength *</label>
                    <input type="text" name="strength" class="form-control" required placeholder="e.g. 500mg, 10ml" value="<?= Security::e($editItem['strength'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Manufacturer / Pharmaceutical Brand *</label>
                <input type="text" name="manufacturer" class="form-control" required placeholder="e.g. Cipla, Sun Pharma" value="<?= Security::e($editItem['manufacturer'] ?? ''); ?>">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label>Batch Number *</label>
                    <input type="text" name="batch_number" class="form-control" required placeholder="BAT-1029" value="<?= Security::e($editItem['batch_number'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Expiry Date *</label>
                    <input type="date" name="expiry_date" class="form-control" required value="<?= Security::e($editItem['expiry_date'] ?? date('Y-m-d', strtotime('+2 years'))); ?>">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label>Purchase Price (₹) *</label>
                    <input type="number" step="0.01" name="purchase_price" class="form-control" required value="<?= Security::e((string)($editItem['purchase_price'] ?? '0.00')); ?>">
                </div>
                <div class="form-group">
                    <label>Selling Price (₹) *</label>
                    <input type="number" step="0.01" name="selling_price" class="form-control" required value="<?= Security::e((string)($editItem['selling_price'] ?? '0.00')); ?>">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                <div class="form-group">
                    <label>Current Stock Units *</label>
                    <input type="number" name="stock_quantity" class="form-control" required value="<?= (int)($editItem['stock_quantity'] ?? 100); ?>">
                </div>
                <div class="form-group">
                    <label>Low Stock Alert Level</label>
                    <input type="number" name="low_stock_threshold" class="form-control" required value="<?= (int)($editItem['low_stock_threshold'] ?? 10); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Disease Indications / Health Uses</label>
                <textarea name="indications" class="form-control" rows="2" placeholder="e.g. Treats high blood pressure and reduces risk of cardiovascular events"><?= Security::e($editItem['indications'] ?? ''); ?></textarea>
            </div>

            <div style="margin-bottom:18px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="requires_prescription" value="1" <?= ($editItem && $editItem['requires_prescription']) ? 'checked' : ''; ?>>
                    <strong>Requires Doctor's Prescription (Schedule H/X)</strong>
                </label>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:12px;">
                <i class="fa-solid fa-floppy-disk"></i> <?= $editItem ? 'Update Medicine' : 'Save Medicine to Inventory'; ?>
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
