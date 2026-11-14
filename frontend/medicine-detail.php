<?php
declare(strict_types=1);

require_once __DIR__ . '/../backend/config/config.php';
require_once __DIR__ . '/../backend/config/database.php';
require_once __DIR__ . '/../backend/core/Security.php';
require_once __DIR__ . '/../backend/core/DrugSafetyChecker.php';
require_once __DIR__ . '/../backend/models/Medicine.php';

use App\Core\Security;
use App\Core\DrugSafetyChecker;
use App\Models\Medicine;

$db = DatabaseConnection::getInstance();
$medModel = new Medicine($db);

$medId = (int)($_GET['id'] ?? 0);
$med = $medModel->find($medId);

if (!$med) {
    header("Location: " . app_url('medicines.php'));
    exit;
}

$pageTitle = $med['name'] . ' (' . $med['strength'] . ')';
$advisories = DrugSafetyChecker::getSafetyAdvisory($med['name'], $med['generic_name']);
require_once __DIR__ . '/layout/header.php';
?>

<div class="container" style="padding-top:35px; padding-bottom:60px;">
    <!-- Breadcrumb -->
    <div style="font-size:0.85rem; color:var(--text-muted); margin-bottom:20px;">
        <a href="<?= app_url('index.php'); ?>" style="color:var(--text-muted); text-decoration:none;">Home</a> &gt; 
        <a href="<?= app_url('medicines.php?cat=' . (int)$med['category_id']); ?>" style="color:var(--text-muted); text-decoration:none;"><?= Security::e($med['category_name']); ?></a> &gt; 
        <span style="color:var(--text-heading); font-weight:600;"><?= Security::e($med['name']); ?></span>
    </div>

    <div style="background:#fff; border:1px solid var(--border-color); border-radius:var(--radius-card); padding:35px; display:grid; grid-template-columns:1fr 1.3fr; gap:40px; margin-bottom:40px; box-shadow:var(--shadow-subtle);">
        <!-- Left Column: Visual -->
        <div>
            <div style="background:#f8fafc; border:1px solid var(--border-color); border-radius:14px; height:320px; display:flex; align-items:center; justify-content:center; position:relative;">
                <i class="fa-solid fa-pills" style="font-size:6rem; color:#94a3b8;"></i>
                <?php if ($med['requires_prescription']): ?>
                    <span class="rx-badge" style="top:20px; right:20px; font-size:0.85rem; padding:4px 12px;">
                        <i class="fa-solid fa-file-medical"></i> Doctor's Prescription Required
                    </span>
                <?php else: ?>
                    <span style="position:absolute; top:20px; right:20px; background:#dcfce7; color:#15803d; font-size:0.8rem; font-weight:700; padding:4px 10px; border-radius:6px;">
                        Over-The-Counter (OTC)
                    </span>
                <?php endif; ?>
            </div>

            <!-- Home Delivery Guarantee Banner -->
            <div style="margin-top:20px; background:var(--brand-teal-light); border:1px solid rgba(0, 192, 139, 0.2); border-radius:12px; padding:18px;">
                <h4 style="font-size:0.95rem; color:#0f766e; margin-bottom:8px; font-weight:800;">
                    <i class="fa-solid fa-shield-virus"></i> Pharmacy Delivery Standards
                </h4>
                <ul style="list-style:none; font-size:0.85rem; color:#115e59; line-height:1.7;">
                    <li><i class="fa-solid fa-check" style="margin-right:6px; color:var(--brand-teal);"></i> Dispensed directly by licensed pharmacist</li>
                    <li><i class="fa-solid fa-check" style="margin-right:6px; color:var(--brand-teal);"></i> Sealed tamper-evident pharmaceutical packaging</li>
                    <li><i class="fa-solid fa-check" style="margin-right:6px; color:var(--brand-teal);"></i> Batch: <code><?= Security::e($med['batch_number']); ?></code> (Exp: <?= Security::e($med['expiry_date']); ?>)</li>
                </ul>
            </div>
        </div>

        <!-- Right Column: Medicine Specs & Ordering -->
        <div>
            <span class="med-cat-tag">
                <?= Security::e($med['category_name']); ?>
            </span>
            <h1 style="font-size:2.2rem; font-weight:800; color:var(--text-heading); margin:4px 0 8px;">
                <?= Security::e($med['name']); ?>
            </h1>
            <div style="font-size:1.05rem; color:var(--text-muted); margin-bottom:14px;">
                <strong>Generic Composition:</strong> <?= Security::e($med['generic_name']); ?>
            </div>

            <div style="display:flex; gap:10px; margin-bottom:20px; flex-wrap:wrap;">
                <span class="med-spec-pill" style="font-size:0.88rem; padding:4px 14px;"><?= Security::e($med['dosage_form']); ?></span>
                <span class="med-spec-pill" style="font-size:0.88rem; padding:4px 14px;"><?= Security::e($med['strength']); ?></span>
                <span class="med-spec-pill" style="font-size:0.88rem; padding:4px 14px; background:#e0f2fe; color:#0284c7;">
                    <i class="fa-solid fa-industry"></i> <?= Security::e($med['manufacturer']); ?>
                </span>
            </div>

            <div style="background:#f8fafc; padding:20px; border-radius:14px; margin-bottom:24px; border:1px solid var(--border-color);">
                <div style="font-size:0.85rem; color:var(--text-muted);">MRP / Doorstep Delivery Price:</div>
                <div style="font-size:2.4rem; font-weight:800; color:var(--text-heading); margin-bottom:6px;">
                    <?= format_price($med['selling_price']); ?>
                </div>
                <div style="font-size:0.88rem; color:#15803d; font-weight:700;">
                    <i class="fa-solid fa-circle-check"></i> In Stock &bull; Ready for Doorstep Dispatch
                </div>
            </div>

            <!-- Order / Cart Form -->
            <form action="<?= app_url('cart.php'); ?>" method="POST" style="display:flex; gap:14px; margin-bottom:24px; align-items:center;">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="medicine_id" value="<?= (int)$med['id']; ?>">
                
                <div style="display:flex; align-items:center; border:1px solid var(--border-color); border-radius:8px; overflow:hidden;">
                    <button type="button" onclick="const q = document.getElementById('qty'); if (q.value > 1) q.value--;" style="padding:12px 16px; border:none; background:#f1f5f9; cursor:pointer; font-weight:700;">-</button>
                    <input type="number" id="qty" name="quantity" value="1" min="1" max="50" style="width:60px; text-align:center; border:none; font-size:1.1rem; font-weight:700;">
                    <button type="button" onclick="const q = document.getElementById('qty'); q.value++;" style="padding:12px 16px; border:none; background:#f1f5f9; cursor:pointer; font-weight:700;">+</button>
                </div>

                <button type="submit" class="btn-hero-upload btn-add-teal" style="flex:1; padding:15px; font-size:1.05rem; justify-content:center; border-radius:var(--radius-pill);">
                    <i class="fa-solid fa-cart-shopping"></i> Add to Home Delivery Order
                </button>
            </form>

            <?php if ($med['requires_prescription']): ?>
                <div style="background:#fff1f2; border:1px solid #fecdd3; border-radius:10px; padding:14px; font-size:0.88rem; color:#9f1239; margin-bottom:12px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> <strong>Schedule Medication:</strong> This drug requires a doctor's prescription. You can upload it during checkout or via our <a href="<?= app_url('upload-prescription.php'); ?>" style="color:#be123c; font-weight:700; text-decoration:underline;">Prescription Portal</a>.
                </div>
            <?php endif; ?>

            <?php if (!empty($advisories)): ?>
                <?php foreach ($advisories as $adv): ?>
                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:14px; font-size:0.88rem; color:#166534; margin-bottom:10px;">
                        <i class="fa-solid fa-shield-halved" style="color:#16a34a;"></i> <strong><?= Security::e($adv['type']); ?>:</strong> <?= Security::e($adv['msg']); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Indications & Health Uses -->
    <div style="background:#fff; border:1px solid var(--border-color); border-radius:var(--radius-card); padding:35px; box-shadow:var(--shadow-subtle);">
        <h3 style="font-size:1.3rem; font-weight:800; color:var(--text-heading); margin-bottom:16px;">
            <i class="fa-solid fa-stethoscope" style="color:var(--brand-teal);"></i> Medical Indications & Usage
        </h3>
        <p style="font-size:1rem; color:var(--text-body); line-height:1.7; margin-bottom:20px;">
            <?= !empty($med['indications']) ? nl2br(Security::e($med['indications'])) : 'Used for treating conditions under ' . Security::e($med['category_name']) . ' as directed by your physician.'; ?>
        </p>

        <?php if (!empty($med['side_effects'])): ?>
            <h4 style="font-size:1.1rem; font-weight:700; color:var(--text-heading); margin-bottom:10px;">Possible Side Effects & Precautions</h4>
            <p style="font-size:0.95rem; color:var(--text-muted); line-height:1.6;">
                <?= nl2br(Security::e($med['side_effects'])); ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
