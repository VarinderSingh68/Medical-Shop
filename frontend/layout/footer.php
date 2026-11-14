<?php
declare(strict_types=1);

use App\Core\Security;
?>
    <!-- Trust Strip Banner -->
    <section class="trust-strip-section">
        <div class="container">
            <div class="trust-strip-grid">
                <div class="trust-strip-item">
                    <div class="trust-strip-icon teal">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div>
                        <strong>100% Genuine Medicines</strong>
                        <p>Direct from licensed manufacturers</p>
                    </div>
                </div>
                <div class="trust-strip-item">
                    <div class="trust-strip-icon blue">
                        <i class="fa-solid fa-motorcycle"></i>
                    </div>
                    <div>
                        <strong>Rapid Doorstep Delivery</strong>
                        <p>Temperature-controlled cold chain</p>
                    </div>
                </div>
                <div class="trust-strip-item">
                    <div class="trust-strip-icon green">
                        <i class="fa-solid fa-user-doctor"></i>
                    </div>
                    <div>
                        <strong>Licensed Pharmacist Verification</strong>
                        <p>Every prescription reviewed</p>
                    </div>
                </div>
                <div class="trust-strip-item">
                    <div class="trust-strip-icon amber">
                        <i class="fa-solid fa-hand-holding-dollar"></i>
                    </div>
                    <div>
                        <strong>COD & UPI on Delivery</strong>
                        <p>Pay upon doorstep verification</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Footer Section -->
    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <!-- Column 1: Brand & Contact -->
                <div class="footer-col-main">
                    <a href="<?= app_url('index.php'); ?>" class="footer-brand">
                        <div class="logo-badge">
                            <i class="fa-solid fa-notes-medical"></i>
                        </div>
                        <span>HomeMed</span>
                    </a>
                    <p class="footer-tagline">
                        <?= Security::e($settings['tagline'] ?? 'Any Medicine, Delivered to Your Doorstep. All illnesses and diseases covered.'); ?>
                    </p>
                    <div class="footer-contact-card">
                        <div class="contact-card-row">
                            <i class="fa-solid fa-id-card"></i>
                            <div>
                                <span>Drug License</span>
                                <strong><?= Security::e($settings['drug_license_no'] ?? 'DL-2026-MEDIX-7890'); ?></strong>
                            </div>
                        </div>
                        <div class="contact-card-row">
                            <i class="fa-solid fa-phone"></i>
                            <div>
                                <span>Customer Support</span>
                                <strong><?= Security::e($settings['phone'] ?? '+91 98765 43210'); ?></strong>
                            </div>
                        </div>
                        <div class="contact-card-row">
                            <i class="fa-solid fa-location-dot"></i>
                            <div>
                                <span>Pharmacy Hub</span>
                                <strong><?= Security::e($settings['address'] ?? '124 Healthcare Avenue, Medical City Hub'); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Column 2: Disease Categories -->
                <div>
                    <h4 class="footer-title">Major Disease Categories</h4>
                    <ul class="footer-links">
                        <li><a href="<?= app_url('medicines.php?cat=1'); ?>"><i class="fa-solid fa-angle-right"></i> Cardiology & Heart Care</a></li>
                        <li><a href="<?= app_url('medicines.php?cat=2'); ?>"><i class="fa-solid fa-angle-right"></i> Diabetes & Endocrine</a></li>
                        <li><a href="<?= app_url('medicines.php?cat=3'); ?>"><i class="fa-solid fa-angle-right"></i> Asthma & Respiratory</a></li>
                        <li><a href="<?= app_url('medicines.php?cat=4'); ?>"><i class="fa-solid fa-angle-right"></i> Neurology & Brain Care</a></li>
                        <li><a href="<?= app_url('medicines.php?cat=5'); ?>"><i class="fa-solid fa-angle-right"></i> Gastroenterology & Digestion</a></li>
                        <li><a href="<?= app_url('medicines.php?cat=6'); ?>"><i class="fa-solid fa-angle-right"></i> Orthopedics & Pain Relief</a></li>
                    </ul>
                </div>

                <!-- Column 3: Patient Services -->
                <div>
                    <h4 class="footer-title">Patient Services</h4>
                    <ul class="footer-links">
                        <li><a href="<?= app_url('upload-prescription.php'); ?>"><i class="fa-solid fa-angle-right"></i> 1-Click Prescription Upload</a></li>
                        <li><a href="<?= app_url('track-order.php'); ?>"><i class="fa-solid fa-angle-right"></i> Track Home Delivery</a></li>
                        <li><a href="<?= app_url('cart.php'); ?>"><i class="fa-solid fa-angle-right"></i> My Medicine Cart</a></li>
                        <li><a href="<?= app_url('contact.php'); ?>"><i class="fa-solid fa-angle-right"></i> 24/7 Doctor Consult</a></li>
                        <li><a href="<?= app_url('backend/admin/login.php'); ?>"><i class="fa-solid fa-angle-right"></i> Pharmacist Back-Office</a></li>
                    </ul>
                </div>

                <!-- Column 4: 24/7 Emergency Delivery -->
                <div>
                    <h4 class="footer-title">24/7 Emergency Dispatch</h4>
                    <div class="footer-emergency-box">
                        <span class="emergency-label">EMERGENCY HOTLINE</span>
                        <div class="emergency-number">
                            <i class="fa-solid fa-phone-volume"></i>
                            <span><?= Security::e($settings['emergency_hotline'] ?? '+91 1800 123 4567'); ?></span>
                        </div>
                        <p class="emergency-desc">
                            Immediate doorstep dispatch for acute respiratory, cardiac, and insulin requirements.
                        </p>
                    </div>

                    <div style="margin-top: 18px;">
                        <span style="font-size:0.8rem; font-weight:700; color:#94a3b8; display:block; margin-bottom:8px;">DOWNLOAD OUR APP</span>
                        <div style="display:flex; gap:10px;">
                            <a href="javascript:void(0);" class="app-badge-btn">
                                <i class="fa-brands fa-google-play"></i>
                                <div>
                                    <small>GET IT ON</small>
                                    <strong>Google Play</strong>
                                </div>
                            </a>
                            <a href="javascript:void(0);" class="app-badge-btn">
                                <i class="fa-brands fa-apple"></i>
                                <div>
                                    <small>Download on</small>
                                    <strong>App Store</strong>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Bottom Strip -->
            <div class="footer-bottom-bar">
                <div>
                    &copy; <?= date('Y'); ?> <strong>HomeMed Pharmacy</strong>. All medicines dispensed strictly under registered pharmacist supervision in compliance with Drugs & Cosmetics Act.
                </div>
                <div class="footer-badges-strip">
                    <span><i class="fa-solid fa-lock"></i> 256-Bit SSL Encrypted</span>
                    <span><i class="fa-solid fa-shield-halved"></i> 100% Genuine Batch</span>
                    <span><i class="fa-solid fa-temperature-arrow-down"></i> Cold Chain Assured</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- App JavaScript -->
    <script src="<?= app_url('assets/js/app.js'); ?>"></script>
</body>
</html>
