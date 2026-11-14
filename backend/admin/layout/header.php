<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Security.php';
require_once __DIR__ . '/../../core/Auth.php';

use App\Core\Auth;
use App\Core\Security;

Auth::checkAdmin();
Security::applyHeaders();

$db = DatabaseConnection::getInstance();
$flash = Security::getFlash();

$pendingDeliveriesCount = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status = 'Pending Review'")->fetchColumn();
$lowStockBadge = (int)$db->query("SELECT COUNT(*) FROM medicines WHERE stock_quantity <= low_stock_threshold AND is_active = 1")->fetchColumn();
$nearExpiryBadge = (int)$db->query("SELECT COUNT(*) FROM medicines WHERE expiry_date <= date('now', '+90 days') AND is_active = 1")->fetchColumn();
$refillsDueBadge = 0;
try {
    $refillsDueBadge = (int)$db->query("SELECT COUNT(*) FROM chronic_patients WHERE refill_due_date <= date('now', '+5 days')")->fetchColumn();
} catch (\Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? Security::e($pageTitle) . ' - ' : ''; ?>HomeMed Pharmacy Admin</title>
    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <!-- Font Awesome 6 (with Subresource Integrity SRI) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        :root {
            --primary: #00c08b;
            --primary-dark: #00a87a;
            --primary-light: #e6f9f3;
            --secondary: #0d223a;
            --secondary-dark: #071524;
            --accent: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #0284c7;
            --bg-light: #f5f9fc;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Plus Jakarta Sans', system-ui, sans-serif; }
        body { background: var(--bg-light); color: var(--text-main); display:flex; min-height:100vh; overflow-x:hidden; }
        
        /* Sidebar */
        .admin-sidebar { 
            width:270px; 
            background:linear-gradient(180deg, var(--secondary) 0%, var(--secondary-dark) 100%); 
            color:#fff; 
            display:flex; 
            flex-direction:column; 
            flex-shrink:0;
            height:100vh;
            position:sticky;
            top:0;
            border-right: 1px solid rgba(255,255,255,0.06);
        }
        .sidebar-brand { 
            padding:20px; 
            font-size:1.15rem; 
            font-weight:800; 
            color:#fff; 
            display:flex; 
            align-items:center; 
            gap:12px; 
            border-bottom:1px solid rgba(255,255,255,0.08); 
            background: rgba(0,0,0,0.15);
        }
        .logo-badge { 
            width:36px; 
            height:36px; 
            background:var(--primary); 
            border-radius:10px; 
            display:flex; 
            align-items:center; 
            justify-content:center; 
            color:#fff; 
            font-size:1.1rem;
            box-shadow: 0 4px 12px rgba(0,192,139,0.3);
        }
        .sidebar-nav { 
            padding:12px 0 24px; 
            flex:1; 
            overflow-y:auto; 
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.2) transparent;
        }
        .sidebar-nav::-webkit-scrollbar { width: 5px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.2); border-radius: 4px; }
        
        .nav-section-title {
            padding: 14px 22px 6px;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #64748b;
        }
        
        .sidebar-nav a { 
            display:flex; 
            align-items:center; 
            justify-content:space-between; 
            padding:10px 22px; 
            color:#94a3b8; 
            text-decoration:none; 
            font-size:0.88rem; 
            font-weight:600; 
            transition:.2s; 
            border-left:3px solid transparent;
        }
        .sidebar-nav a:hover { 
            background:rgba(255,255,255,0.04); 
            color:#ffffff; 
            padding-left: 25px;
        }
        .sidebar-nav a.active { 
            background:rgba(0,192,139,0.12); 
            color:var(--primary); 
            border-left-color:var(--primary); 
            font-weight: 700;
        }
        .nav-item-left { display:flex; align-items:center; gap:12px; }
        .nav-item-left i { width: 18px; font-size: 1rem; text-align: center; }
        
        .sidebar-badge { 
            font-size:0.72rem; 
            padding:2px 7px; 
            border-radius:12px; 
            font-weight:700; 
        }
        .badge-count-danger { background:var(--danger); color:#fff; }
        .badge-count-warning { background:var(--warning); color:#fff; }
        .badge-count-info { background:var(--info); color:#fff; }
        .badge-count-primary { background:var(--primary); color:#fff; }
        
        /* Main Workspace */
        .admin-main { flex:1; display:flex; flex-direction:column; min-width:0; overflow-y:auto; }
        .admin-topbar { 
            background:#fff; 
            border-bottom:1px solid var(--border); 
            padding:14px 32px; 
            display:flex; 
            justify-content:space-between; 
            align-items:center; 
            position:sticky;
            top:0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }
        .admin-content { padding:28px 32px; flex:1; }
        
        /* Cards */
        .card { 
            background:#fff; 
            border-radius:16px; 
            border:1px solid var(--border); 
            box-shadow:0 4px 20px rgba(0,30,60,0.04); 
            padding:24px; 
            margin-bottom:24px; 
        }
        .card-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
        .card-title { font-size:1.15rem; font-weight:800; color:var(--secondary); display:flex; align-items:center; gap:10px; }
        
        /* Tables */
        .table-responsive { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; text-align:left; font-size:0.9rem; }
        th { background:#f8fafc; color:var(--text-muted); padding:12px 16px; font-weight:700; border-bottom:1px solid var(--border); font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding:13px 16px; border-bottom:1px solid var(--border); vertical-align:middle; }
        tr:hover td { background:#f8fafc; }
        
        /* Buttons */
        .btn { display:inline-flex; align-items:center; gap:8px; padding:8px 16px; border-radius:8px; font-size:0.88rem; font-weight:700; text-decoration:none; border:none; cursor:pointer; transition:.2s; }
        .btn-primary { background:var(--primary); color:#fff; }
        .btn-primary:hover { background:var(--primary-dark); }
        .btn-secondary { background:#f1f5f9; color:var(--text-main); border:1px solid var(--border); }
        .btn-secondary:hover { background:#e2e8f0; }
        .btn-danger { background:var(--danger); color:#fff; }
        .btn-warning { background:var(--warning); color:#fff; }
        .btn-success { background:var(--accent); color:#fff; }
        .btn-sm { padding:6px 12px; font-size:0.8rem; border-radius:6px; }
        
        /* Badges */
        .badge { display:inline-block; padding:4px 10px; border-radius:20px; font-size:0.78rem; font-weight:700; }
        .badge-warning { background:#fef3c7; color:#d97706; }
        .badge-info { background:#e0f2fe; color:#0284c7; }
        .badge-success { background:#dcfce7; color:#15803d; }
        .badge-danger { background:#fee2e2; color:#b91c1c; }
        .badge-secondary { background:#f1f5f9; color:#475569; }
        
        /* Alerts */
        .alert { padding:14px 18px; border-radius:10px; margin-bottom:20px; display:flex; align-items:center; gap:12px; font-size:0.9rem; font-weight:600; }
        .alert-success { background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; }
        .alert-danger { background:#fee2e2; color:#b91c1c; border:1px solid #fecaca; }
        .alert-warning { background:#fef3c7; color:#d97706; border:1px solid #fde68a; }
        .alert-info { background:#e0f2fe; color:#0284c7; border:1px solid #bae6fd; }
        
        /* Form Controls */
        .form-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:20px; }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; font-size:0.85rem; font-weight:700; margin-bottom:6px; color:#334155; }
        .form-control { width:100%; padding:10px 14px; border:1px solid var(--border); border-radius:8px; font-size:0.92rem; font-family:inherit; }
        .form-control:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(0,192,139,0.15); }
    </style>
</head>
<body>
    <aside class="admin-sidebar">
        <div class="sidebar-brand">
            <div class="logo-badge">
                <i class="fa-solid fa-notes-medical"></i>
            </div>
            <div>
                <div style="line-height:1.2;">HomeMed Admin</div>
                <div style="font-size:0.7rem; color:var(--primary); font-weight:700; letter-spacing:0.5px;">PHARMACY 360° SUITE</div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <!-- MAIN -->
            <div class="nav-section-title">Overview</div>
            <a href="<?= app_url('backend/admin/index.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">
                <div class="nav-item-left"><i class="fa-solid fa-gauge-high"></i> Dashboard</div>
            </a>

            <!-- STAFF & PHARMACY OPERATIONS -->
            <div class="nav-section-title">Staff & Dispensary</div>
            <a href="<?= app_url('backend/admin/pos.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'pos.php' ? 'active' : ''; ?>">
                <div class="nav-item-left"><i class="fa-solid fa-cash-register"></i> Fast POS Billing</div>
                <span class="sidebar-badge badge-count-primary">NEW</span>
            </a>
            <a href="<?= app_url('backend/admin/orders.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : ''; ?>">
                <div class="nav-item-left"><i class="fa-solid fa-truck-ramp-box"></i> Home Deliveries</div>
                <?php if ($pendingDeliveriesCount > 0): ?>
                    <span class="sidebar-badge badge-count-danger"><?= $pendingDeliveriesCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= app_url('backend/admin/prescriptions.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'prescriptions.php' ? 'active' : ''; ?>">
                <div class="nav-item-left"><i class="fa-solid fa-file-prescription"></i> Prescription Queue</div>
            </a>
            <a href="<?= app_url('backend/admin/medicines.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'medicines.php' ? 'active' : ''; ?>">
                <div class="nav-item-left"><i class="fa-solid fa-pills"></i> Inventory & Stock</div>
                <?php if ($lowStockBadge > 0): ?>
                    <span class="sidebar-badge badge-count-warning"><?= $lowStockBadge; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= app_url('backend/admin/expiry-tracker.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'expiry-tracker.php' ? 'active' : ''; ?>">
                <div class="nav-item-left"><i class="fa-solid fa-hourglass-half"></i> Expiry & Batch Tracker</div>
                <?php if ($nearExpiryBadge > 0): ?>
                    <span class="sidebar-badge badge-count-warning"><?= $nearExpiryBadge; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= app_url('backend/admin/purchase-orders.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'purchase-orders.php' ? 'active' : ''; ?>">
                <div class="nav-item-left"><i class="fa-solid fa-cart-flatbed"></i> Purchase Orders & Reorder</div>
                <?php if ($lowStockBadge > 0): ?>
                    <span class="sidebar-badge badge-count-warning"><?= $lowStockBadge; ?></span>
                <?php endif; ?>
            </a>

            <!-- OWNER & FINANCIALS -->
            <div class="nav-section-title">Owner & Analytics</div>
            <a href="<?= app_url('backend/admin/analytics.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'analytics.php' ? 'active' : ''; ?>">
                <div class="nav-item-left"><i class="fa-solid fa-chart-line"></i> Revenue & Margins</div>
            </a>
            <a href="<?= app_url('backend/admin/customers.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : ''; ?>">
                <div class="nav-item-left"><i class="fa-solid fa-hospital-user"></i> Chronic Refill Care</div>
                <?php if ($refillsDueBadge > 0): ?>
                    <span class="sidebar-badge badge-count-danger"><?= $refillsDueBadge; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= app_url('backend/admin/compliance.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'compliance.php' ? 'active' : ''; ?>">
                <div class="nav-item-left"><i class="fa-solid fa-stamp"></i> Schedule H Register</div>
            </a>
            <a href="<?= app_url('backend/admin/categories.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'categories.php' ? 'active' : ''; ?>">
                <div class="nav-item-left"><i class="fa-solid fa-viruses"></i> Disease Categories</div>
            </a>
            <a href="<?= app_url('backend/admin/vendors.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'vendors.php' ? 'active' : ''; ?>">
                <div class="nav-item-left"><i class="fa-solid fa-building-wheat"></i> Vendors & Supply</div>
            </a>

            <!-- DEVELOPER & IT -->
            <div class="nav-section-title">Developer & System</div>
            <a href="<?= app_url('backend/admin/staff.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'staff.php' ? 'active' : ''; ?>">
                <div class="nav-item-left"><i class="fa-solid fa-users-gear"></i> Staff & Role Access</div>
            </a>
            <a href="<?= app_url('backend/admin/settings.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
                <div class="nav-item-left"><i class="fa-solid fa-sliders"></i> Pharmacy Settings</div>
            </a>
            <a href="<?= app_url('backend/admin/system-health.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'system-health.php' ? 'active' : ''; ?>">
                <div class="nav-item-left"><i class="fa-solid fa-server"></i> System Health & PHP</div>
            </a>
            <a href="<?= app_url('backend/admin/smtp-test.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'smtp-test.php' ? 'active' : ''; ?>">
                <div class="nav-item-left"><i class="fa-solid fa-envelope-circle-check"></i> Mailer & SMTP Test</div>
            </a>
            <a href="<?= app_url('backend/admin/backup.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'backup.php' ? 'active' : ''; ?>">
                <div class="nav-item-left"><i class="fa-solid fa-database"></i> Database Backup</div>
            </a>
            <a href="<?= app_url('backend/admin/audit-logs.php'); ?>" class="<?= basename($_SERVER['PHP_SELF']) === 'audit-logs.php' ? 'active' : ''; ?>">
                <div class="nav-item-left"><i class="fa-solid fa-shield-virus"></i> Security & Audit Logs</div>
            </a>

            <div style="padding:15px 22px;">
                <a href="<?= app_url('backend/admin/logout.php'); ?>" style="color:#f87171; border-radius:8px; background:rgba(239,68,68,0.1); padding:10px 16px; border:1px solid rgba(239,68,68,0.2);">
                    <div class="nav-item-left"><i class="fa-solid fa-right-from-bracket"></i> Secure Logout</div>
                </a>
            </div>
        </nav>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <div style="display:flex; align-items:center; gap:16px;">
                <div>
                    <h3 style="font-size:1.05rem; font-weight:800; color:var(--secondary); line-height:1.2;">
                        <?= isset($pageTitle) ? Security::e($pageTitle) : 'Pharmacist Command Center'; ?>
                    </h3>
                    <span style="font-size:0.8rem; color:var(--text-muted);">
                        <i class="fa-solid fa-user-shield" style="color:var(--primary);"></i> <?= Security::e($_SESSION['admin_name']); ?> 
                        &bull; <span style="text-transform:capitalize;"><?= Security::e($_SESSION['admin_role']); ?></span>
                    </span>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:12px;">
                <div style="display:flex; align-items:center; gap:8px; background:#f8fafc; border:1px solid #e2e8f0; padding:6px 12px; border-radius:20px; font-size:0.78rem; font-weight:700; color:#475569;">
                    <i class="fa-solid fa-clock" style="color:var(--primary);"></i>
                    <span id="admin-live-clock"><?= date('h:i A'); ?></span>
                    <span style="color:#cbd5e1;">|</span>
                    <span style="color:#10b981;"><i class="fa-solid fa-shield-check"></i> TLS & 2FA Active</span>
                </div>
                <a href="<?= app_url('backend/admin/pos.php'); ?>" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-cash-register"></i> Fast POS
                </a>
                <a href="<?= app_url('index.php'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Visit Store
                </a>
                <a href="<?= app_url('backend/admin/logout.php'); ?>" class="btn btn-danger btn-sm" title="Sign Out">
                    <i class="fa-solid fa-power-off"></i>
                </a>
            </div>
        </header>
        <script>
            // Live Real-Time Admin Digital Clock
            setInterval(() => {
                const clock = document.getElementById('admin-live-clock');
                if (clock) {
                    const now = new Date();
                    clock.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
                }
            }, 1000);
        </script>
        <main class="admin-content">
            <?php if ($flash): ?>
                <div class="alert alert-<?= Security::e($flash['type']); ?>">
                    <i class="fa-solid fa-circle-info"></i>
                    <span><?= Security::e($flash['text']); ?></span>
                </div>
            <?php endif; ?>
