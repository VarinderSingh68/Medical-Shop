<?php
declare(strict_types=1);

$pageTitle = 'Any Medicine, Delivered to Your Doorstep';
require_once __DIR__ . '/layout/header.php';
require_once __DIR__ . '/../backend/models/Medicine.php';

use App\Core\Security;
use App\Models\Medicine;

$medModel = new Medicine($db);
$featuredMedicines = $medModel->getAll(null, null, true, 8);
?>

<!-- 1. HERO SECTION (Exact Reference Visual Alignment) -->
<section class="hero-section">
    <div class="container">
        <div class="hero-grid">
            <!-- Left Hero Content -->
            <div class="hero-content">
                <h1 class="hero-title">
                    Any Medicine, Delivered to Your Doorstep.
                </h1>
                <p class="hero-subtitle">
                    All illnesses and diseases covered. Fast same-day delivery by licensed pharmacists.
                </p>

                <!-- Search & Upload Bar (Pill Shape with Embedded Upload Button) -->
                <div class="hero-search-wrapper">
                    <form action="<?= app_url('medicines.php'); ?>" method="GET" class="hero-search-bar">
                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                        <input type="text" id="hero-search-input" name="q" placeholder="Search by medicine name, disease (e.g. Diabetes, Asthma), or brand..." autocomplete="off">
                        <a href="<?= app_url('upload-prescription.php'); ?>" class="btn-hero-upload">
                            <i class="fa-solid fa-file-prescription"></i>
                            <span>Upload Prescription</span>
                        </a>
                    </form>
                </div>

                <!-- Value Proposition Trust Badges -->
                <div class="trust-badges-row">
                    <div class="trust-badge-card">
                        <div class="badge-icon blue">
                            <i class="fa-solid fa-certificate"></i>
                        </div>
                        <span>100% Genuine Medicines</span>
                    </div>

                    <div class="trust-badge-card">
                        <div class="badge-icon orange">
                            <i class="fa-solid fa-motorcycle"></i>
                        </div>
                        <span>Doorstep Delivery</span>
                    </div>

                    <div class="trust-badge-card">
                        <div class="badge-icon teal">
                            <i class="fa-solid fa-globe"></i>
                        </div>
                        <span>All Diseases Covered</span>
                    </div>
                </div>
            </div>

            <!-- Right 3D Delivery Rider Illustration Visual -->
            <div class="hero-visual">
                <div class="visual-stage">
                    <div class="rider-card">
                        <!-- Floating Medical Pills Art (Matching Reference Elements) -->
                        <div class="floating-pill pill-1">
                            <i class="fa-solid fa-envelope-open-text"></i>
                            <span>Doctor Rx Verified</span>
                        </div>
                        <div class="floating-pill pill-2">
                            <i class="fa-solid fa-shield-virus"></i>
                            <span>Cold-Chain 4°C</span>
                        </div>
                        <div class="floating-pill pill-3">
                            <i class="fa-solid fa-pills"></i>
                            <span>All Brands in Stock</span>
                        </div>

                        <!-- 3D Vector Graphic Delivery Pharmacist -->
                        <svg viewBox="0 0 400 360" width="100%" height="100%" style="filter: drop-shadow(0 20px 25px rgba(0, 30, 60, 0.15));">
                            <!-- Background Aura -->
                            <circle cx="200" cy="180" r="140" fill="#e0f8f1" opacity="0.8"/>
                            <circle cx="200" cy="180" r="105" fill="#cff4e8" opacity="0.6"/>
                            
                            <!-- Road Shadow -->
                            <ellipse cx="200" cy="315" rx="140" ry="16" fill="rgba(10, 40, 70, 0.12)"/>

                            <!-- Delivery Scooter Wheels -->
                            <circle cx="120" cy="285" r="32" fill="#1e293b"/>
                            <circle cx="120" cy="285" r="18" fill="#94a3b8"/>
                            <circle cx="120" cy="285" r="6" fill="#f8fafc"/>
                            
                            <circle cx="280" cy="285" r="32" fill="#1e293b"/>
                            <circle cx="280" cy="285" r="18" fill="#94a3b8"/>
                            <circle cx="280" cy="285" r="6" fill="#f8fafc"/>

                            <!-- Scooter Chassis & Mint Body -->
                            <path d="M 120 280 L 170 280 L 220 250 L 280 280 L 260 210 L 150 210 Z" fill="#00c08b"/>
                            <path d="M 160 210 L 210 210 L 240 140 L 220 140 Z" fill="#00a87a"/>
                            <rect x="235" y="130" width="18" height="60" rx="6" fill="#38bdf8" opacity="0.85"/>
                            <circle cx="245" cy="190" r="12" fill="#fef08a"/>

                            <!-- Pharmacy Green Delivery Storage Box -->
                            <rect x="90" y="150" width="70" height="65" rx="10" fill="#00a87a"/>
                            <rect x="85" y="145" width="80" height="15" rx="6" fill="#008a63"/>
                            <!-- White Medical Cross on Box -->
                            <rect x="120" y="165" width="10" height="30" rx="3" fill="#ffffff"/>
                            <rect x="110" y="175" width="30" height="10" rx="3" fill="#ffffff"/>

                            <!-- Rider Body (Green uniform) -->
                            <ellipse cx="190" cy="180" rx="26" ry="34" fill="#00c08b"/>
                            <!-- Arms and Handlebar -->
                            <path d="M 185 165 L 225 175" stroke="#008a63" stroke-width="12" stroke-linecap="round"/>
                            <circle cx="225" cy="175" r="8" fill="#fbcfe8"/>
                            
                            <!-- Scooter Legs -->
                            <path d="M 190 200 L 195 245 L 215 255" stroke="#1e293b" stroke-width="14" stroke-linecap="round" fill="none"/>
                            <ellipse cx="220" cy="258" rx="12" ry="7" fill="#00c08b"/>

                            <!-- Rider Head & Face -->
                            <circle cx="195" cy="115" r="22" fill="#fed7aa"/>
                            <!-- Green Helmet -->
                            <path d="M 172 115 C 172 90, 218 90, 218 115 Z" fill="#00a87a"/>
                            <circle cx="195" cy="98" r="5" fill="#ffffff"/>
                            <!-- Smile & Eyes -->
                            <circle cx="204" cy="115" r="3" fill="#0f172a"/>
                            <path d="M 200 124 Q 206 130 212 124" stroke="#0f172a" stroke-width="2" fill="none" stroke-linecap="round"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 2. SPLIT SECTION: BROWSE BY DISEASE + HOW IT WORKS -->
<section class="main-browse-section">
    <div class="container">
        <div class="split-layout">
            <!-- Left Side: Browse by Disease Category (4x2 Grid) -->
            <div class="disease-container">
                <div class="section-head-bar">
                    <div>
                        <h2 class="section-title">Browse by Disease Category</h2>
                        <div class="section-subtitle">Shop by Disease or Condition</div>
                    </div>
                    <a href="<?= app_url('medicines.php'); ?>" class="link-view-all">
                        <span>View All Diseases</span>
                        <i class="fa-solid fa-arrow-right" style="font-size:0.8rem;"></i>
                    </a>
                </div>

                <!-- Dynamic Specialized Disease Cards -->
                <div class="disease-cards-grid">
                    <?php 
                    $colorThemes = ['cyan', 'red', 'purple', 'blue', 'indigo', 'orange', 'green', 'amber', 'cyan', 'red', 'purple', 'blue', 'indigo', 'orange', 'green', 'amber'];
                    $cIdx = 0;
                    foreach ($navCategories as $cat): 
                        $theme = $colorThemes[$cIdx % count($colorThemes)];
                        $cIdx++;
                    ?>
                        <a href="<?= app_url('medicines.php?cat=' . (int)$cat['id']); ?>" class="disease-tile">
                            <div class="tile-icon-box <?= $theme; ?>">
                                <i class="fa-solid <?= Security::e($cat['icon'] ?: 'fa-pills'); ?>"></i>
                            </div>
                            <div class="tile-text">
                                <h4><?= Security::e($cat['name']); ?></h4>
                                <span><?= (int)$cat['medicine_count']; ?> Products Available</span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right Side: "How it Works" Card (Exact match with reference) -->
            <div class="how-it-works-card">
                <h2 class="section-title">How it Works</h2>
                <div class="section-subtitle">Get your medicines in 3 easy steps</div>

                <div class="steps-list">
                    <!-- Step 1 -->
                    <div class="step-row">
                        <div class="step-num-bubble">1</div>
                        <div class="step-details">
                            <h4>Upload Rx or Search</h4>
                            <p>User uploads prescription or searches</p>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="step-row">
                        <div class="step-num-bubble">2</div>
                        <div class="step-details">
                            <h4>Pharmacist Review</h4>
                            <p>Expert pharmacists review and pack</p>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="step-row">
                        <div class="step-num-bubble">3</div>
                        <div class="step-details">
                            <h4>Fast Delivery</h4>
                            <p>Track your package to your doorstep</p>
                        </div>
                    </div>
                </div>

                <!-- Express Upload CTA Box -->
                <div style="margin-top:30px; background:#f0fdf4; border:1px dashed #86efac; border-radius:12px; padding:18px; text-align:center;">
                    <p style="font-size:0.85rem; color:#166534; font-weight:600; margin-bottom:12px;">
                        Have a Doctor's Prescription?
                    </p>
                    <a href="<?= app_url('upload-prescription.php'); ?>" class="btn-hero-upload" style="width:100%; justify-content:center;">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span>Upload Prescription Now</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- 3. IN-STOCK MEDICINES CATALOG SECTION -->
<section style="padding: 20px 0 60px;">
    <div class="container">
        <div class="section-head-bar" style="margin-bottom:24px;">
            <div>
                <h2 class="section-title">Essential Medicines in Stock</h2>
                <div class="section-subtitle">Guaranteed genuine batch supplies available for immediate home delivery</div>
            </div>
            <a href="<?= app_url('medicines.php'); ?>" class="link-view-all">
                <span>Explore All Medicines</span>
                <i class="fa-solid fa-arrow-right" style="font-size:0.8rem;"></i>
            </a>
        </div>

        <div class="medicine-grid">
            <?php foreach ($featuredMedicines as $med): ?>
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

                        <div class="med-generic">
                            <?= Security::e($med['generic_name']); ?>
                        </div>

                        <span class="med-spec-pill">
                            <?= Security::e($med['dosage_form']); ?> &bull; <?= Security::e($med['strength']); ?>
                        </span>
                    </div>

                    <div class="med-card-footer">
                        <div>
                            <span style="font-size:0.75rem; color:var(--text-muted); display:block;">Doorstep Price</span>
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
    </div>
</section>

<!-- 4. BOTTOM APP BAR (Matching Reference Image) -->
<div class="bottom-app-bar">
    <div class="container">
        <div class="bottom-app-wrapper">
            <div style="font-weight:700; color:var(--text-heading); font-size:0.95rem;">
                Download the App
            </div>
            <div class="app-badges-group">
                <a href="javascript:void(0);" class="app-store-pill">
                    <i class="fa-brands fa-google-play"></i>
                    <span>Google Play</span>
                </a>
                <a href="javascript:void(0);" class="app-store-pill">
                    <i class="fa-brands fa-apple"></i>
                    <span>App Store</span>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- 5. FLOATING "CHAT WITH PHARMACIST" WIDGET (Matching Bottom Right Pill) -->
<a href="javascript:void(0);" onclick="openPharmacistChat();" class="floating-chat-pill">
    <span class="chat-beacon-dot"></span>
    <i class="fa-solid fa-comments"></i>
    <span>Chat with Pharmacist</span>
</a>

<!-- Pharmacist Live Chat Modal -->
<div id="chat-modal" style="display:none; position:fixed; bottom:85px; right:28px; width:340px; background:#fff; border-radius:16px; box-shadow:0 15px 35px rgba(0,25,50,0.25); border:1px solid #e2e8f0; z-index:10000; overflow:hidden;">
    <div style="background:#0d223a; color:#fff; padding:16px; display:flex; justify-content:space-between; align-items:center;">
        <div style="display:flex; align-items:center; gap:10px;">
            <div style="width:10px; height:10px; border-radius:50%; background:#00c08b;"></div>
            <strong>Licensed Pharmacist On Duty</strong>
        </div>
        <button onclick="closePharmacistChat();" style="background:none; border:none; color:#94a3b8; font-size:1.1rem; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div style="padding:16px; font-size:0.88rem; color:#475569; max-height:240px; overflow-y:auto;" id="chat-log">
        <div style="background:#f1f5f9; padding:10px 14px; border-radius:12px; margin-bottom:10px;">
            Hello! I am your registered pharmacist. Ask me about your disease, medicine dosages, or prescription verification for home delivery.
        </div>
    </div>
    <div style="padding:12px; border-top:1px solid #e2e8f0; display:flex; gap:8px;">
        <input type="text" id="chat-input" placeholder="Type medicine question..." style="flex:1; border:1px solid #cbd5e1; border-radius:20px; padding:8px 14px; font-size:0.85rem; outline:none;">
        <button onclick="sendChatMessage();" style="background:#00c08b; color:#fff; border:none; border-radius:50%; width:34px; height:34px; cursor:pointer;"><i class="fa-solid fa-paper-plane"></i></button>
    </div>
</div>

<script>
function changeDeliveryLocation() {
    const newLoc = prompt("Enter your Delivery City or Postal PIN code:", "<?= Security::e($currentCity); ?>");
    if (newLoc && newLoc.trim() !== '') {
        document.getElementById('user-location').innerText = newLoc.trim();
    }
}

function openPharmacistChat() {
    document.getElementById('chat-modal').style.display = 'block';
}

function closePharmacistChat() {
    document.getElementById('chat-modal').style.display = 'none';
}

function sendChatMessage() {
    const input = document.getElementById('chat-input');
    const log = document.getElementById('chat-log');
    if (input.value.trim() !== '') {
        const userMsg = document.createElement('div');
        userMsg.style.cssText = 'background:#e0f2fe; color:#0369a1; padding:8px 12px; border-radius:12px; margin-bottom:10px; text-align:right;';
        userMsg.innerText = input.value;
        log.appendChild(userMsg);

        setTimeout(() => {
            const reply = document.createElement('div');
            reply.style.cssText = 'background:#f1f5f9; padding:8px 12px; border-radius:12px; margin-bottom:10px;';
            reply.innerHTML = '<strong>Pharmacist:</strong> We have all medicines in stock for immediate home delivery. You can also upload your prescription directly or call our 24/7 hotline at <strong><?= Security::e($settings["emergency_hotline"] ?? "+91 1800 123 4567"); ?></strong>.';
            log.appendChild(reply);
            log.scrollTop = log.scrollHeight;
        }, 500);

        input.value = '';
    }
}
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
