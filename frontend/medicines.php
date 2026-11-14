<?php
declare(strict_types=1);

$pageTitle = 'Browse All Medicines by Disease';
require_once __DIR__ . '/layout/header.php';
require_once __DIR__ . '/../backend/models/Medicine.php';

use App\Core\Security;
use App\Models\Medicine;

$medModel = new Medicine($db);

$catFilter = isset($_GET['cat']) && $_GET['cat'] !== '' ? (int)$_GET['cat'] : null;
$search = isset($_GET['q']) ? trim($_GET['q']) : null;

// Pagination setup
$perPage = 24;
$page = max(1, (int)($_GET['p'] ?? 1));
$totalMedicines = $medModel->count($catFilter, $search, true);
$totalPages = max(1, (int)ceil($totalMedicines / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$medicines = $medModel->getAll($catFilter, $search, true, $perPage, $offset);

$buildPageUrl = function(int $targetPage) use ($catFilter, $search): string {
    $params = ['p' => $targetPage];
    if ($catFilter !== null) $params['cat'] = $catFilter;
    if (!empty($search)) $params['q'] = $search;
    return app_url('medicines.php?' . http_build_query($params));
};

// Active category name if filtered
$activeCategoryName = 'All Diseases & Medical Conditions';
if ($catFilter) {
    foreach ($navCategories as $c) {
        if ((int)$c['id'] === $catFilter) {
            $activeCategoryName = $c['name'];
            break;
        }
    }
}
?>

<div class="container" style="padding-top:35px; padding-bottom:60px;">
    <!-- Breadcrumb / Header -->
    <div style="margin-bottom:24px;">
        <h1 style="font-size:2rem; font-weight:800; color:var(--text-heading);"><?= Security::e($activeCategoryName); ?></h1>
        <p style="color:var(--text-muted); font-size:0.95rem;">
            Showing <?= number_format($totalMedicines > 0 ? $offset + 1 : 0); ?> - <?= number_format(min($totalMedicines, $offset + count($medicines))); ?> of <?= number_format($totalMedicines); ?> available medicines for doorstep delivery
            <?php if (!empty($search)): ?>
                matching "<strong><?= Security::e($search); ?></strong>"
            <?php endif; ?>
        </p>
    </div>

    <div style="display:grid; grid-template-columns:280px 1fr; gap:30px; align-items:start;">
        <!-- Filter Sidebar -->
        <div style="background:#fff; border:1px solid var(--border-color); border-radius:var(--radius-card); padding:24px; box-shadow:var(--shadow-subtle);">
            <h3 style="font-size:1.05rem; font-weight:800; color:var(--text-heading); margin-bottom:16px; display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-filter" style="color:var(--brand-teal);"></i> Filter by Disease
            </h3>
            <ul style="list-style:none;">
                <li style="margin-bottom:6px;">
                    <a href="<?= app_url('medicines.php'); ?>" style="display:flex; justify-content:space-between; text-decoration:none; color:<?= $catFilter === null ? 'var(--brand-teal)' : 'var(--text-body)'; ?>; font-weight:<?= $catFilter === null ? '700' : '500'; ?>; padding:8px 12px; border-radius:8px; background:<?= $catFilter === null ? 'var(--brand-teal-light)' : 'transparent'; ?>; font-size:0.9rem;">
                        <span>All Diseases</span>
                    </a>
                </li>
                <?php foreach ($navCategories as $cat): ?>
                    <li style="margin-bottom:6px;">
                        <a href="<?= app_url('medicines.php?cat=' . (int)$cat['id'] . (!empty($search) ? '&q=' . urlencode($search) : '')); ?>" style="display:flex; justify-content:space-between; align-items:center; text-decoration:none; color:<?= $catFilter === (int)$cat['id'] ? 'var(--brand-teal)' : 'var(--text-body)'; ?>; font-weight:<?= $catFilter === (int)$cat['id'] ? '700' : '500'; ?>; padding:8px 12px; border-radius:8px; background:<?= $catFilter === (int)$cat['id'] ? 'var(--brand-teal-light)' : 'transparent'; ?>; font-size:0.9rem;">
                            <span><?= Security::e($cat['name']); ?></span>
                            <span style="color:var(--text-muted); font-size:0.8rem; background:#f1f5f9; padding:2px 8px; border-radius:10px;"><?= (int)$cat['medicine_count']; ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div style="margin-top:24px; padding-top:20px; border-top:1px solid var(--border-color);">
                <h4 style="font-size:0.95rem; font-weight:700; color:var(--text-heading); margin-bottom:8px;">Doctor's Prescription?</h4>
                <p style="font-size:0.82rem; color:var(--text-muted); margin-bottom:14px;">Upload your Rx photo and let our licensed pharmacist pack and deliver the exact dosage.</p>
                <a href="<?= app_url('upload-prescription.php'); ?>" class="btn-hero-upload" style="width:100%; justify-content:center;">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Upload Rx
                </a>
            </div>
        </div>

        <!-- Medicine Results Grid -->
        <div>
            <!-- Inline Search / Filter Toolbar -->
            <div style="background:#fff; border:1px solid var(--border-color); border-radius:var(--radius-card); padding:14px 18px; margin-bottom:24px; box-shadow:var(--shadow-subtle);">
                <form action="<?= app_url('medicines.php'); ?>" method="GET" style="display:flex; width:100%; gap:12px; align-items:center;">
                    <?php if ($catFilter): ?>
                        <input type="hidden" name="cat" value="<?= $catFilter; ?>">
                    <?php endif; ?>
                    <i class="fa-solid fa-magnifying-glass" style="color:var(--brand-teal); font-size:1.05rem;"></i>
                    <input type="text" name="q" value="<?= Security::e($search ?? ''); ?>" placeholder="Search medicine name, generic salt, or disease..." style="flex:1; border:none; outline:none; font-size:0.95rem; font-family:inherit; color:var(--text-heading);">
                    <?php if (!empty($search) || !empty($catFilter)): ?>
                        <a href="<?= app_url('medicines.php'); ?>" style="font-size:0.82rem; color:#ef4444; text-decoration:none; font-weight:700; white-space:nowrap; padding:4px 8px; border-radius:6px; background:#fee2e2;">
                            <i class="fa-solid fa-xmark"></i> Reset
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="btn-hero-upload" style="padding:9px 20px; font-size:0.85rem; border:none; cursor:pointer;">
                        Filter
                    </button>
                </form>
            </div>

            <?php if (empty($medicines)): ?>
                <div style="background:#fff; border:1px solid var(--border-color); border-radius:var(--radius-card); padding:60px 20px; text-align:center; box-shadow:var(--shadow-subtle);">
                    <i class="fa-solid fa-box-open" style="font-size:3rem; color:#cbd5e1; margin-bottom:16px;"></i>
                    <h3 style="color:var(--text-heading); margin-bottom:8px;">No Medicines Found</h3>
                    <p style="color:var(--text-muted); max-width:400px; margin:0 auto 20px;">We stock medicines for all diseases upon prescription. Upload your doctor's note for rapid fulfillment.</p>
                    <a href="<?= app_url('upload-prescription.php'); ?>" class="btn-hero-upload" style="display:inline-flex;">
                        <i class="fa-solid fa-file-prescription"></i> Upload Prescription for This Medicine
                    </a>
                </div>
            <?php else: ?>
                <div class="medicine-grid">
                    <?php foreach ($medicines as $med): ?>
                        <div class="medicine-card">
                            <div>
                                <?php if ($med['requires_prescription']): ?>
                                    <span class="rx-badge">Rx</span>
                                <?php endif; ?>

                                <span class="med-cat-tag">
                                    <?= Security::e($med['category_name']); ?>
                                </span>
                                
                                <a href="<?= app_url('medicine-detail.php?id=' . (int)$med['id']); ?>" class="med-title">
                                    <?= Security::e($med['name']); ?>
                                </a>
                                
                                <div class="med-generic"><?= Security::e($med['generic_name']); ?></div>
                                <span class="med-spec-pill"><?= Security::e($med['dosage_form']); ?> &bull; <?= Security::e($med['strength']); ?></span>
                                
                                <?php if (!empty($med['indications'])): ?>
                                    <p style="font-size:0.82rem; color:var(--text-muted); line-height:1.4; margin-bottom:10px;">
                                        <strong>For:</strong> <?= Security::e(substr($med['indications'], 0, 75)) . (strlen($med['indications']) > 75 ? '...' : ''); ?>
                                    </p>
                                <?php endif; ?>
                            </div>

                            <div class="med-card-footer">
                                <div>
                                    <span style="font-size:0.75rem; color:var(--text-muted); display:block;">Price</span>
                                    <span class="med-price-num"><?= format_price($med['selling_price']); ?></span>
                                </div>
                                <form action="<?= app_url('cart.php'); ?>" method="POST" style="margin:0;">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="medicine_id" value="<?= (int)$med['id']; ?>">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="btn-add-teal">
                                        <i class="fa-solid fa-cart-plus"></i> Order
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <!-- Pagination Bar -->
                    <div style="display:flex; justify-content:center; align-items:center; gap:8px; margin-top:36px; flex-wrap:wrap;">
                        <?php if ($page > 1): ?>
                            <a href="<?= $buildPageUrl(1); ?>" class="btn-signin-nav" style="padding:8px 14px; font-size:0.85rem;" title="First Page">
                                <i class="fa-solid fa-angles-left"></i> First
                            </a>
                            <a href="<?= $buildPageUrl($page - 1); ?>" class="btn-signin-nav" style="padding:8px 14px; font-size:0.85rem;" title="Previous Page">
                                <i class="fa-solid fa-chevron-left"></i> Prev
                            </a>
                        <?php endif; ?>

                        <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            if ($startPage > 1) echo '<span style="color:var(--text-muted); padding:0 4px;">...</span>';
                            for ($p = $startPage; $p <= $endPage; $p++):
                        ?>
                            <a href="<?= $buildPageUrl($p); ?>" style="display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; border-radius:10px; text-decoration:none; font-weight:700; font-size:0.9rem; transition:all .15s; <?= $p === $page ? 'background:var(--brand-teal); color:#fff; box-shadow:0 4px 10px rgba(0,192,139,0.3);' : 'background:#fff; border:1px solid var(--border-color); color:var(--text-body);'; ?>">
                                <?= $p; ?>
                            </a>
                        <?php endfor; ?>
                        <?php if ($endPage < $totalPages) echo '<span style="color:var(--text-muted); padding:0 4px;">...</span>'; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?= $buildPageUrl($page + 1); ?>" class="btn-signin-nav" style="padding:8px 14px; font-size:0.85rem;" title="Next Page">
                                Next <i class="fa-solid fa-chevron-right"></i>
                            </a>
                            <a href="<?= $buildPageUrl($totalPages); ?>" class="btn-signin-nav" style="padding:8px 14px; font-size:0.85rem;" title="Last Page">
                                Last (<?= $totalPages; ?>) <i class="fa-solid fa-angles-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
