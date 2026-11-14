<?php
declare(strict_types=1);

$pageTitle = 'Disease & Medical Categories';
require_once __DIR__ . '/layout/header.php';
require_once __DIR__ . '/../models/Category.php';

use App\Core\Security;
use App\Models\Category;

$catModel = new Category($db);
$csrfToken = Security::generateCsrf();

// Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
        Security::setFlash('danger', 'Security validation expired.');
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $data = [
                'name'        => Security::sanitizeString($_POST['name'] ?? ''),
                'icon'        => Security::sanitizeString($_POST['icon'] ?? 'fa-heartbeat'),
                'description' => Security::sanitizeString($_POST['description'] ?? ''),
                'is_featured' => isset($_POST['is_featured']) ? 1 : 0
            ];

            if ($action === 'create') {
                $catModel->create($data);
                Security::setFlash('success', "Disease category '{$data['name']}' added.");
            } else {
                $id = (int)$_POST['category_id'];
                $catModel->update($id, $data);
                Security::setFlash('success', "Disease category updated successfully.");
            }
            header("Location: categories.php");
            exit;
        } elseif ($action === 'delete') {
            $id = (int)$_POST['category_id'];
            if ($catModel->delete($id)) {
                Security::setFlash('success', "Category removed.");
            } else {
                Security::setFlash('danger', "Cannot delete category: Medicines are currently mapped to this disease. Please reassign them first.");
            }
            header("Location: categories.php");
            exit;
        }
    }
}

// Edit Mode
$editItem = null;
if (isset($_GET['edit'])) {
    $editItem = $catModel->find((int)$_GET['edit']);
}

$categories = $catModel->getAll();
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
    <div>
        <h3 style="color:var(--secondary); font-size:1.4rem;">Disease & Medical Conditions</h3>
        <span style="font-size:0.85rem; color:var(--text-muted);">Organize medicines according to human diseases, chronic ailments and medical specialties</span>
    </div>
</div>

<div style="display:grid; grid-template-columns:3fr 2fr; gap:24px; align-items:start;">
    <!-- Categories List -->
    <div class="card">
        <div class="card-header">
            <h4 class="card-title"><i class="fa-solid fa-viruses"></i> Active Disease Specialties (<?= count($categories); ?>)</h4>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Specialty / Disease</th>
                        <th>Icon</th>
                        <th>Medicines Count</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:30px; color:var(--text-muted);">No disease categories found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($categories as $c): ?>
                            <tr>
                                <td>
                                    <strong><?= Security::e($c['name']); ?></strong><br>
                                    <span style="font-size:0.8rem; color:var(--text-muted);"><?= Security::e($c['description'] ?? ''); ?></span>
                                </td>
                                <td>
                                    <div style="width:36px; height:36px; border-radius:8px; background:#e0f2fe; color:var(--primary); display:flex; align-items:center; justify-content:center;">
                                        <i class="fa-solid <?= Security::e($c['icon']); ?>"></i>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= (int)$c['medicine_count']; ?> medicines</span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:6px;">
                                        <a href="categories.php?edit=<?= (int)$c['id']; ?>" class="btn btn-secondary btn-sm" title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                        <form action="" method="POST" onsubmit="return confirm('Delete this disease category?');" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                                            <input type="hidden" name="category_id" value="<?= (int)$c['id']; ?>">
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

    <!-- Add / Edit Form -->
    <div class="card">
        <h4 class="card-title" style="margin-bottom:16px;">
            <i class="fa-solid <?= $editItem ? 'fa-pen-to-square' : 'fa-circle-plus'; ?>"></i>
            <?= $editItem ? 'Edit Disease Category' : 'Add New Disease Specialty'; ?>
        </h4>

        <?php if ($editItem): ?>
            <div style="margin-bottom:12px;">
                <a href="categories.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark"></i> Cancel Edit</a>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <input type="hidden" name="action" value="<?= $editItem ? 'update' : 'create'; ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
            <?php if ($editItem): ?>
                <input type="hidden" name="category_id" value="<?= (int)$editItem['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Disease Category / Specialty Name *</label>
                <input type="text" name="name" class="form-control" required placeholder="e.g. Oncology & Chemotherapy, Nephrology" value="<?= Security::e($editItem['name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>FontAwesome Icon Class *</label>
                <input type="text" name="icon" class="form-control" required placeholder="fa-heart-pulse, fa-brain, fa-lungs" value="<?= Security::e($editItem['icon'] ?? 'fa-heartbeat'); ?>">
                <span style="font-size:0.75rem; color:var(--text-muted);">Use any standard FontAwesome class (e.g. fa-virus, fa-bone, fa-syringe)</span>
            </div>

            <div class="form-group">
                <label>Medical Scope & Conditions Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Explain which diseases fall under this category"><?= Security::e($editItem['description'] ?? ''); ?></textarea>
            </div>

            <div style="margin-bottom:18px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" name="is_featured" value="1" <?= (!$editItem || $editItem['is_featured']) ? 'checked' : ''; ?>>
                    <strong>Show in Homepage Featured Specialties</strong>
                </label>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:12px;">
                <i class="fa-solid fa-floppy-disk"></i> <?= $editItem ? 'Update Disease Category' : 'Save Specialty'; ?>
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
