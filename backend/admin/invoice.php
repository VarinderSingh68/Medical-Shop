<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Security.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Setting.php';

use App\Core\Auth;
use App\Core\Security;
use App\Models\Order;
use App\Models\Setting;

Auth::checkAdmin();
Security::applyHeaders();

$db = DatabaseConnection::getInstance();
$orderModel = new Order($db);
$settingModel = new Setting($db);

$settings = $settingModel->getAll();
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$orderCode = isset($_GET['code']) ? trim($_GET['code']) : '';

$order = null;
if ($orderId > 0) {
    $order = $orderModel->find($orderId);
} elseif (!empty($orderCode)) {
    $order = $orderModel->findByCode($orderCode);
}

if (!$order) {
    die("Invoice not found or invalid Order ID.");
}

$items = $order['items'] ?? [];
$isRx = !empty($order['prescription_file']);

$shopName = $settings['shop_name'] ?? 'HomeMed Pharmacy & Surgical Store';
$shopAddress = $settings['shop_address'] ?? 'Shop 12-14, Medical Square, Central Healthcare Complex, Mumbai - 400001';
$shopPhone = $settings['contact_phone'] ?? '+91 98200 88990';
$shopEmail = $settings['contact_email'] ?? 'care@homemedpharmacy.com';
$gstNumber = $settings['gst_number'] ?? '27AADCH1234F1Z8';
$drugLicense = $settings['drug_license_no'] ?? 'MH-MZ1-182940 / MH-MZ2-182941';
$pharmacistName = $_SESSION['admin_name'] ?? 'Lead Pharmacist (Reg. #PH-98421)';

// Tax calculation (Pharma standard: 6% CGST + 6% SGST included in MRP)
$taxableSubtotal = round($order['subtotal'] / 1.12, 2);
$totalGst = round($order['subtotal'] - $taxableSubtotal, 2);
$cgst = round($totalGst / 2, 2);
$sgst = round($totalGst - $cgst, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice #<?= Security::e($order['order_code']); ?> - <?= Security::e($shopName); ?></title>
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        :root {
            --primary: #00c08b;
            --secondary: #0d223a;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Plus Jakarta Sans', system-ui, sans-serif; }
        body { background:#f1f5f9; color:var(--text-dark); padding:30px 15px; }

        .invoice-wrapper {
            max-width: 840px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 40px;
            border: 1px solid var(--border);
        }

        .no-print-toolbar {
            max-width: 840px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: 14px 24px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            border: 1px solid var(--border);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            font-size: 0.9rem;
            font-weight: 700;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            border: none;
            transition: all 0.2s;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: #00a87a; }
        .btn-secondary { background: #f1f5f9; color: var(--secondary); border: 1px solid var(--border); }
        .btn-secondary:hover { background: #e2e8f0; }

        /* Invoice Layout */
        .header-section {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 24px;
            margin-bottom: 24px;
        }
        .pharmacy-brand {
            display: flex;
            gap: 14px;
            align-items: center;
        }
        .brand-icon {
            width: 48px;
            height: 48px;
            background: #00c08b;
            color: #fff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
        }
        .pharmacy-name { font-size: 1.35rem; font-weight: 800; color: var(--secondary); }
        .pharmacy-sub { font-size: 0.82rem; color: var(--text-muted); margin-top: 3px; }

        .invoice-title-block { text-align: right; }
        .invoice-badge {
            background: #e6f9f3;
            color: #00a87a;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            margin-bottom: 6px;
        }
        .invoice-num { font-size: 1.25rem; font-weight: 800; font-family:'JetBrains Mono', monospace; color: var(--secondary); }

        .meta-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
            font-size: 0.88rem;
        }
        .meta-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px 20px;
        }
        .meta-card h4 {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            font-size: 0.88rem;
        }
        .items-table th {
            background: #f8fafc;
            color: var(--secondary);
            font-weight: 700;
            text-align: left;
            padding: 12px 14px;
            border-bottom: 2px solid #cbd5e1;
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        .items-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #e2e8f0;
        }
        .items-table tr:hover { background: #fafbfc; }

        /* Calculation Summary */
        .calc-wrapper {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 28px;
        }
        .calc-box {
            width: 320px;
            background: #f8fafc;
            border-radius: 10px;
            padding: 16px 20px;
            border: 1px solid #e2e8f0;
            font-size: 0.9rem;
        }
        .calc-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            color: var(--text-muted);
        }
        .calc-row.grand-total {
            border-top: 2px solid #cbd5e1;
            padding-top: 10px;
            margin-top: 8px;
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--secondary);
        }

        /* Legal & Signatures */
        .footer-legal {
            border-top: 1px dashed #cbd5e1;
            padding-top: 20px;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            font-size: 0.78rem;
            color: var(--text-muted);
        }
        .signature-box {
            text-align: center;
            border-left: 1px solid #e2e8f0;
            padding-left: 20px;
        }
        .signature-line {
            height: 45px;
            border-bottom: 1px solid #94a3b8;
            margin-bottom: 6px;
        }

        @media print {
            body { background: #fff; padding: 0; }
            .no-print-toolbar { display: none !important; }
            .invoice-wrapper {
                box-shadow: none;
                border: none;
                padding: 0;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- Non-print Toolbar -->
    <div class="no-print-toolbar">
        <div>
            <a href="orders.php?view=<?= $order['id']; ?>" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> Back to Order
            </a>
        </div>
        <div style="display:flex; gap:10px;">
            <button onclick="window.print();" class="btn btn-primary">
                <i class="fa-solid fa-print"></i> Print Tax Invoice / PDF
            </button>
            <a href="<?= app_url('backend/admin/pos.php'); ?>" class="btn btn-secondary">
                <i class="fa-solid fa-cash-register"></i> New POS Bill
            </a>
        </div>
    </div>

    <!-- Official Printable Invoice Canvas -->
    <div class="invoice-wrapper">
        <!-- Header -->
        <div class="header-section">
            <div class="pharmacy-brand">
                <div class="brand-icon">
                    <i class="fa-solid fa-notes-medical"></i>
                </div>
                <div>
                    <div class="pharmacy-name"><?= Security::e($shopName); ?></div>
                    <div class="pharmacy-sub"><?= Security::e($shopAddress); ?></div>
                    <div class="pharmacy-sub">
                        <strong>DL No:</strong> <?= Security::e($drugLicense); ?> | 
                        <strong>GSTIN:</strong> <?= Security::e($gstNumber); ?>
                    </div>
                    <div class="pharmacy-sub"><strong>Helpline:</strong> <?= Security::e($shopPhone); ?> | <?= Security::e($shopEmail); ?></div>
                </div>
            </div>

            <div class="invoice-title-block">
                <span class="invoice-badge">Retail Drug Sale Tax Invoice</span>
                <div class="invoice-num">#<?= Security::e($order['order_code']); ?></div>
                <div style="font-size:0.85rem; color:var(--text-muted); margin-top:4px;">
                    Date: <strong><?= Security::e(format_date($order['created_at'], 'd M Y, h:i A')); ?></strong>
                </div>
                <div style="font-size:0.82rem; color:var(--text-muted);">
                    Payment: <strong><?= Security::e($order['payment_method']); ?> (<?= Security::e($order['payment_status']); ?>)</strong>
                </div>
            </div>
        </div>

        <!-- Meta Grid -->
        <div class="meta-grid">
            <div class="meta-card">
                <h4>Patient & Doorstep Delivery Address</h4>
                <div style="font-weight:700; color:var(--secondary); font-size:1rem; margin-bottom:4px;"><?= Security::e($order['customer_name']); ?></div>
                <div>Phone: <strong><?= Security::e($order['phone']); ?></strong></div>
                <div style="margin-top:4px; line-height:1.4; color:#334155;"><?= nl2br(Security::e($order['delivery_address'])); ?></div>
                <div style="margin-top:4px;">City / PIN: <strong><?= Security::e($order['city']); ?> - <?= Security::e($order['pincode']); ?></strong></div>
            </div>

            <div class="meta-card">
                <h4>Prescription & Clinical Dispense</h4>
                <div>Prescription Status: 
                    <?php if ($isRx): ?>
                        <strong style="color:var(--primary);"><i class="fa-solid fa-circle-check"></i> Doctor Rx Verified</strong>
                    <?php else: ?>
                        <strong style="color:#64748b;">OTC / Direct Sale</strong>
                    <?php endif; ?>
                </div>
                <div style="margin-top:6px;">Order Status: <strong><?= Security::e($order['order_status']); ?></strong></div>
                <div style="margin-top:6px;">Doctor Notes: 
                    <em style="color:#475569;"><?= !empty($order['doctor_notes']) ? Security::e($order['doctor_notes']) : 'Standard dosage per physician guidelines.'; ?></em>
                </div>
                <div style="margin-top:6px;">Dispensed By: <strong><?= Security::e($pharmacistName); ?></strong></div>
            </div>
        </div>

        <!-- Dispensed Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Medicine Description & Generic</th>
                    <th>Batch</th>
                    <th>Exp.</th>
                    <th style="text-align:center;">Qty</th>
                    <th style="text-align:right;">Rate (₹)</th>
                    <th style="text-align:right;">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding:20px; color:var(--text-muted);">
                            Direct Prescription Dispense Order (Items packaged according to doctor script)
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $i = 1; foreach ($items as $item): ?>
                        <tr>
                            <td><?= $i++; ?></td>
                            <td>
                                <strong><?= Security::e($item['name']); ?></strong>
                                <div style="font-size:0.75rem; color:var(--text-muted);">
                                    <?= Security::e($item['dosage_form'] ?? 'Tablet'); ?> <?= Security::e($item['strength'] ?? ''); ?>
                                    <?= !empty($item['generic_name']) ? ' | ' . Security::e($item['generic_name']) : ''; ?>
                                </div>
                            </td>
                            <td style="font-family:'JetBrains Mono', monospace; font-size:0.8rem;"><?= Security::e($item['batch_number'] ?? 'MED-2026B'); ?></td>
                            <td style="font-family:'JetBrains Mono', monospace; font-size:0.8rem;"><?= Security::e(!empty($item['expiry_date']) ? date('m/y', strtotime($item['expiry_date'])) : '12/28'); ?></td>
                            <td style="text-align:center; font-weight:700;"><?= (int)$item['quantity']; ?></td>
                            <td style="text-align:right; font-family:'JetBrains Mono', monospace;"><?= number_format((float)$item['unit_price'], 2); ?></td>
                            <td style="text-align:right; font-weight:700; font-family:'JetBrains Mono', monospace;"><?= number_format((float)$item['total_price'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Calculations -->
        <div class="calc-wrapper">
            <div class="calc-box">
                <div class="calc-row">
                    <span>Taxable Base Value</span>
                    <span style="font-family:'JetBrains Mono', monospace;">₹<?= number_format($taxableSubtotal, 2); ?></span>
                </div>
                <div class="calc-row">
                    <span>CGST (6%)</span>
                    <span style="font-family:'JetBrains Mono', monospace;">₹<?= number_format($cgst, 2); ?></span>
                </div>
                <div class="calc-row">
                    <span>SGST (6%)</span>
                    <span style="font-family:'JetBrains Mono', monospace;">₹<?= number_format($sgst, 2); ?></span>
                </div>
                <div class="calc-row">
                    <span>Medicine Subtotal</span>
                    <span style="font-family:'JetBrains Mono', monospace;">₹<?= number_format((float)$order['subtotal'], 2); ?></span>
                </div>
                <div class="calc-row">
                    <span>Doorstep Cold-Chain Delivery</span>
                    <span style="font-family:'JetBrains Mono', monospace;">₹<?= number_format((float)$order['delivery_fee'], 2); ?></span>
                </div>
                <div class="calc-row grand-total">
                    <span>Net Invoice Total</span>
                    <span style="color:var(--primary); font-family:'JetBrains Mono', monospace;">₹<?= number_format((float)$order['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>

        <!-- Footer Disclaimers & Pharmacist Seal -->
        <div class="footer-legal">
            <div>
                <strong>Terms of Sale & Patient Advisory:</strong>
                <ul style="padding-left:16px; margin-top:6px; line-height:1.5;">
                    <li>Medicines sold under valid registered pharmacy supervision. Keep out of reach of children.</li>
                    <li>Store in cool, dry conditions away from direct sunlight (20°C - 25°C).</li>
                    <li>Prescription Schedule H & H1 drugs are non-returnable once seal is opened.</li>
                    <li>For 24/7 pharmacist assistance or medication guidance, call <?= Security::e($shopPhone); ?>.</li>
                </ul>
            </div>
            <div class="signature-box">
                <div class="signature-line"></div>
                <div style="font-weight:700; color:var(--secondary);"><?= Security::e($pharmacistName); ?></div>
                <div style="font-size:0.75rem;">Registered Pharmacist on Duty</div>
                <div style="font-size:0.72rem; color:var(--primary); font-weight:700; margin-top:3px;">
                    <i class="fa-solid fa-stamp"></i> Digitally Verified & Sealed
                </div>
            </div>
        </div>
    </div>

</body>
</html>
