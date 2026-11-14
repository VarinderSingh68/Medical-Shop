<?php
declare(strict_types=1);

$pageTitle = 'Contact Us & 24/7 Doctor Consult';
require_once __DIR__ . '/layout/header.php';

use App\Core\Security;

$csrfToken = Security::generateCsrf();
$successMsg = '';
$errorMsg = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Security validation failed. Please refresh and try again.';
    } elseif (!Security::checkRateLimit('contact_form', 5, 300)) {
        $errorMsg = 'Too many requests. Please wait 5 minutes before submitting another message.';
    } else {
        $successMsg = 'Thank you! Your emergency message has been received by our pharmacist on duty. We will call you immediately.';
    }
}
?>

<div class="container" style="padding-top:40px; padding-bottom:80px; max-width:960px;">
    <div style="text-align:center; margin-bottom:40px;">
        <h1 style="font-size:2.2rem; font-weight:800; color:var(--text-heading); margin-bottom:8px;">
            24/7 Doctor Consult & Pharmacy Helpline
        </h1>
        <p style="color:var(--text-muted); font-size:1.05rem;">
            Dedicated pharmacy helpline and doorstep dispatch team for urgent medical assistance.
        </p>
    </div>

    <?php if (!empty($successMsg)): ?>
        <div style="background:#dcfce7; border:1px solid #86efac; color:#15803d; padding:16px 20px; border-radius:12px; margin-bottom:30px; font-weight:600; text-align:center;">
            <i class="fa-solid fa-circle-check"></i> <?= Security::e($successMsg); ?>
        </div>
    <?php elseif (!empty($errorMsg)): ?>
        <div style="background:#fee2e2; border:1px solid #fecaca; color:#991b1b; padding:16px 20px; border-radius:12px; margin-bottom:30px; font-weight:600; text-align:center;">
            <i class="fa-solid fa-circle-exclamation"></i> <?= Security::e($errorMsg); ?>
        </div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns:1.2fr 1fr; gap:40px; align-items:start;">
        <!-- Contact Form -->
        <div style="background:#fff; border:1px solid var(--border-color); border-radius:var(--radius-card); padding:32px; box-shadow:var(--shadow-subtle);">
            <h3 style="font-size:1.2rem; font-weight:800; color:var(--text-heading); margin-bottom:18px;">
                <i class="fa-solid fa-paper-plane" style="color:var(--brand-teal);"></i> Request Emergency Callback
            </h3>

            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Your Name *</label>
                    <input type="text" name="name" required style="width:100%; padding:10px 14px; border:1px solid var(--border-color); border-radius:8px; font-size:0.95rem; outline:none;" placeholder="e.g. Ananya Patel">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Phone Number *</label>
                        <input type="tel" name="phone" required style="width:100%; padding:10px 14px; border:1px solid var(--border-color); border-radius:8px; font-size:0.95rem; outline:none;" placeholder="+91 98765 43210">
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Email (Optional)</label>
                        <input type="email" name="email" style="width:100%; padding:10px 14px; border:1px solid var(--border-color); border-radius:8px; font-size:0.95rem; outline:none;" placeholder="name@email.com">
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block; font-weight:600; font-size:0.9rem; margin-bottom:6px;">Disease / Medicine Required *</label>
                    <textarea name="message" rows="3" required style="width:100%; padding:10px 14px; border:1px solid var(--border-color); border-radius:8px; font-size:0.95rem; outline:none; font-family:inherit;" placeholder="Specify disease or medication needed..."></textarea>
                </div>

                <button type="submit" class="btn-hero-upload" style="width:100%; justify-content:center; padding:14px; font-size:1rem; border-radius:var(--radius-pill);">
                    <i class="fa-solid fa-headset"></i> Request Immediate Pharmacist Call
                </button>
            </form>
        </div>

        <!-- Information Column -->
        <div>
            <div style="background:var(--nav-bg); color:#fff; border-radius:var(--radius-card); padding:28px; margin-bottom:24px; box-shadow:var(--shadow-card);">
                <span style="font-size:0.8rem; font-weight:700; text-transform:uppercase; color:var(--brand-teal);">24/7 Delivery Hotline</span>
                <div style="font-size:1.8rem; font-weight:800; margin:8px 0 12px; color:#fff;">
                    <?= Security::e($settings['emergency_hotline'] ?? '+91 1800 123 4567'); ?>
                </div>
                <p style="font-size:0.88rem; color:var(--nav-muted); margin:0;">Call anytime for emergency cardiac medications, insulin cold chain, and urgent antibiotic delivery.</p>
            </div>

            <div style="background:#fff; border:1px solid var(--border-color); border-radius:var(--radius-card); padding:28px; box-shadow:var(--shadow-subtle);">
                <h4 style="font-size:1.1rem; font-weight:800; color:var(--text-heading); margin-bottom:16px;">
                    <i class="fa-solid fa-store" style="color:var(--brand-teal);"></i> Pharmacy Store Location
                </h4>

                <div style="display:flex; flex-direction:column; gap:14px; font-size:0.92rem;">
                    <div>
                        <strong style="display:block; color:var(--text-heading);">Address:</strong>
                        <span style="color:var(--text-muted);"><?= Security::e($settings['address'] ?? 'Healthcare Hub'); ?></span>
                    </div>

                    <div>
                        <strong style="display:block; color:var(--text-heading);">Hours:</strong>
                        <span style="color:var(--text-muted);">Counter: 8:00 AM &ndash; 11:00 PM<br><strong style="color:var(--brand-teal);">Home Delivery: 24 Hours / 7 Days</strong></span>
                    </div>

                    <div>
                        <strong style="display:block; color:var(--text-heading);">Drug License:</strong>
                        <code style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-size:0.85rem;"><?= Security::e($settings['drug_license_no'] ?? 'DL-2026-MEDIX-7890'); ?></code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
