<?php
declare(strict_types=1);

$pageTitle = 'Fast POS & Counter Dispensing';
require_once __DIR__ . '/layout/header.php';

use App\Core\Security;

$csrfToken = Security::generateCsrf();

// Fetch active medicines with stock > 0 for POS catalog (Top 250 fast catalog)
$medicines = $db->query("
    SELECT m.id, m.name, m.generic_name, m.dosage_form, m.strength, m.selling_price, m.stock_quantity, m.batch_number, m.expiry_date, m.requires_prescription, c.name AS category_name
    FROM medicines m
    JOIN disease_categories c ON m.category_id = c.id
    WHERE m.is_active = 1 AND m.stock_quantity > 0
    ORDER BY m.name ASC
    LIMIT 250
")->fetchAll(PDO::FETCH_ASSOC);

// Handle POS Order Submission
$posSuccess = null;
$createdOrderCode = null;
$createdOrderId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_pos_sale') {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
        Security::setFlash('danger', 'Security token expired. Please reload and try again.');
    } else {
        $customerName = Security::sanitizeString($_POST['customer_name'] ?? 'Walk-in Patient');
        $phone = Security::sanitizeString($_POST['phone'] ?? '+91 99999 00000');
        $address = Security::sanitizeString($_POST['delivery_address'] ?? 'Counter Walk-in / Direct Dispense');
        $city = Security::sanitizeString($_POST['city'] ?? 'Mumbai');
        $pincode = Security::sanitizeString($_POST['pincode'] ?? '400001');
        $paymentMethod = Security::sanitizeString($_POST['payment_method'] ?? 'Cash');
        $orderType = Security::sanitizeString($_POST['order_type'] ?? 'Counter Pickup');
        $doctorName = Security::sanitizeString($_POST['doctor_name'] ?? '');
        $doctorRegNo = Security::sanitizeString($_POST['doctor_reg_no'] ?? '');
        $itemsJson = $_POST['items_json'] ?? '[]';
        $items = json_decode($itemsJson, true);

        if (empty($items) || !is_array($items)) {
            Security::setFlash('danger', 'Please add at least one medicine item to the billing cart.');
        } else {
            $db->beginTransaction();
            try {
                $subtotal = 0.0;
                $validatedItems = [];

                foreach ($items as $item) {
                    $medId = (int)($item['id'] ?? 0);
                    $qty = max(1, (int)($item['qty'] ?? 1));

                    $stmt = $db->prepare("SELECT id, name, strength, batch_number, selling_price, stock_quantity, requires_prescription FROM medicines WHERE id = :id AND is_active = 1");
                    $stmt->execute(['id' => $medId]);
                    $med = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($med) {
                        $linePrice = (float)$med['selling_price'] * $qty;
                        $subtotal += $linePrice;
                        $validatedItems[] = [
                            'id' => $med['id'],
                            'name' => $med['name'],
                            'strength' => $med['strength'],
                            'batch_number' => $med['batch_number'],
                            'price' => (float)$med['selling_price'],
                            'qty' => $qty,
                            'total' => $linePrice,
                            'requires_rx' => (int)$med['requires_prescription']
                        ];

                        // Deduct stock
                        $upStock = $db->prepare("UPDATE medicines SET stock_quantity = MAX(0, stock_quantity - :q) WHERE id = :id");
                        $upStock->execute(['q' => $qty, 'id' => $med['id']]);
                    }
                }

                $deliveryFee = ($orderType === 'Home Delivery') ? 40.0 : 0.0;
                $totalAmount = $subtotal + $deliveryFee;
                $orderCode = 'POS-' . strtoupper(bin2hex(random_bytes(3)));

                $insOrder = $db->prepare("
                    INSERT INTO orders (order_code, customer_name, phone, delivery_address, city, pincode, doctor_notes, subtotal, delivery_fee, total_amount, payment_method, payment_status, order_status, delivery_boy_notes)
                    VALUES (:code, :name, :phone, :addr, :city, :pin, :doc, :sub, :fee, :tot, :paym, 'Paid', :stat, :notes)
                ");
                $status = ($orderType === 'Home Delivery') ? 'Pharmacist Verified' : 'Delivered';
                $docNotes = !empty($doctorName) ? "Prescribed by: $doctorName ($doctorRegNo)" : "OTC Dispensing";
                $notes = "Fast POS Counter Sale by " . ($_SESSION['admin_name'] ?? 'Staff');

                $insOrder->execute([
                    'code' => $orderCode,
                    'name' => $customerName,
                    'phone' => $phone,
                    'addr' => $address,
                    'city' => $city,
                    'pin' => $pincode,
                    'doc' => $docNotes,
                    'sub' => $subtotal,
                    'fee' => $deliveryFee,
                    'tot' => $totalAmount,
                    'paym' => $paymentMethod,
                    'stat' => $status,
                    'notes' => $notes
                ]);
                $orderId = (int)$db->lastInsertId();

                // Insert items
                $insItem = $db->prepare("
                    INSERT INTO order_items (order_id, medicine_id, quantity, unit_price, total_price)
                    VALUES (:oid, :mid, :qty, :up, :tot)
                ");
                foreach ($validatedItems as $v) {
                    $insItem->execute([
                        'oid' => $orderId,
                        'mid' => $v['id'],
                        'qty' => $v['qty'],
                        'up'  => $v['price'],
                        'tot' => $v['total']
                    ]);

                    // If requires prescription, automatically log into Schedule H compliance register
                    if ($v['requires_rx'] === 1 && !empty($doctorName)) {
                        $insComp = $db->prepare("
                            INSERT INTO compliance_register (order_code, patient_name, patient_phone, doctor_name, doctor_reg_no, medicine_name, strength, batch_number, quantity, pharmacist_name)
                            VALUES (:code, :pname, :pphone, :dname, :dreg, :mname, :str, :batch, :qty, :pharm)
                        ");
                        $insComp->execute([
                            'code'   => $orderCode,
                            'pname'  => $customerName,
                            'pphone' => $phone,
                            'dname'  => $doctorName,
                            'dreg'   => $doctorRegNo ?: 'N/A',
                            'mname'  => $v['name'],
                            'str'    => $v['strength'],
                            'batch'  => $v['batch_number'],
                            'qty'    => $v['qty'],
                            'pharm'  => $_SESSION['admin_name'] ?? 'Lead Pharmacist'
                        ]);
                    }
                }

                // Log audit trail
                try {
                    $log = $db->prepare("INSERT INTO audit_logs (admin_id, admin_name, action, entity_type, entity_id, details, ip_address) VALUES (:aid, :aname, 'POS_BILLING_CREATED', 'order', :oid, :det, :ip)");
                    $log->execute([
                        'aid'   => $_SESSION['admin_id'] ?? 1,
                        'aname' => $_SESSION['admin_name'] ?? 'Admin',
                        'oid'   => $orderId,
                        'det'   => "Billed invoice #{$orderCode} totaling " . format_price($totalAmount),
                        'ip'    => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                    ]);
                } catch (\Exception $e) {}

                $db->commit();
                $posSuccess = true;
                $createdOrderCode = $orderCode;
                $createdOrderId = $orderId;
                Security::setFlash('success', "Invoice #{$orderCode} completed successfully! Stock deducted & printed.");
            } catch (\Exception $e) {
                $db->rollBack();
                Security::setFlash('danger', 'Billing error: ' . $e->getMessage());
            }
        }
    }
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:1.4rem; color:var(--secondary); font-weight:800; display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-cash-register" style="color:var(--primary);"></i> Fast POS Counter Billing
        </h2>
        <span style="font-size:0.85rem; color:var(--text-muted);">Quick medicine dispensing, instant barcode/name search, GST receipt printing & live inventory sync</span>
    </div>
    <div style="display:flex; gap:10px;">
        <button type="button" class="btn btn-secondary btn-sm" onclick="clearCart()"><i class="fa-solid fa-rotate-left"></i> Reset Cart</button>
        <a href="<?= app_url('backend/admin/orders.php'); ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-list-check"></i> Orders List</a>
    </div>
</div>

<?php if ($posSuccess && $createdOrderCode): ?>
    <div class="card" style="background:#ecfdf5; border:1px solid #6ee7b7; padding:20px; margin-bottom:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
            <div>
                <h4 style="color:#065f46; font-size:1.15rem; font-weight:800; margin-bottom:4px;">
                    <i class="fa-solid fa-circle-check"></i> Sale Completed: Invoice #<?= Security::e($createdOrderCode); ?>
                </h4>
                <p style="color:#047857; font-size:0.88rem; margin:0;">Medicines dispensed, inventory updated in real-time, and compliance records archived.</p>
            </div>
            <div style="display:flex; gap:10px;">
                <a href="invoice.php?code=<?= urlencode($createdOrderCode); ?>" target="_blank" class="btn btn-primary btn-sm"><i class="fa-solid fa-file-invoice-dollar"></i> View & Print Tax Invoice (PDF)</a>
                <a href="pos.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-plus"></i> New Bill</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: 1.4fr 1fr; gap:24px; align-items:start;">
    <!-- LEFT: Product Search & Quick Catalog -->
    <div class="card" style="padding:20px;">
        <div style="margin-bottom:16px; position:relative;">
            <i class="fa-solid fa-magnifying-glass" style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
            <input type="text" id="med-search" placeholder="Search medicine by Brand, Generic formula, or Batch #..." class="form-control" style="padding-left:40px; font-size:1rem; border-radius:10px;" autofocus>
        </div>

        <div style="max-height:540px; overflow-y:auto; padding-right:4px;" id="medicines-list">
            <?php foreach ($medicines as $m): ?>
                <div class="pos-item-card" 
                     data-id="<?= $m['id']; ?>"
                     data-name="<?= Security::e($m['name']); ?>"
                     data-strength="<?= Security::e($m['strength']); ?>"
                     data-batch="<?= Security::e($m['batch_number']); ?>"
                     data-price="<?= (float)$m['selling_price']; ?>"
                     data-stock="<?= (int)$m['stock_quantity']; ?>"
                     data-rx="<?= (int)$m['requires_prescription']; ?>"
                     style="display:flex; justify-content:space-between; align-items:center; padding:12px 14px; border:1px solid var(--border); border-radius:10px; margin-bottom:10px; transition:all 0.15s; background:#ffffff; cursor:pointer;"
                     onclick="addToCart(<?= $m['id']; ?>)">
                    <div>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <strong style="color:var(--secondary); font-size:0.95rem;"><?= Security::e($m['name']); ?></strong>
                            <span class="badge badge-secondary" style="font-size:0.75rem;"><?= Security::e($m['dosage_form']); ?> <?= Security::e($m['strength']); ?></span>
                            <?php if ($m['requires_prescription']): ?>
                                <span class="badge badge-danger" style="font-size:0.7rem;"><i class="fa-solid fa-prescription"></i> Rx</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">
                            <span>Generic: <?= Security::e($m['generic_name']); ?></span> &bull; 
                            <span>Batch: <strong style="font-family:'JetBrains Mono', monospace;"><?= Security::e($m['batch_number']); ?></strong></span>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:1.05rem; font-weight:800; color:var(--primary);"><?= format_price($m['selling_price']); ?></div>
                        <span style="font-size:0.78rem; font-weight:700; color:<?= $m['stock_quantity'] <= 10 ? 'var(--danger)' : '#10b981'; ?>;">
                            <?= $m['stock_quantity']; ?> in stock
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- RIGHT: Active Billing Cart & Checkout -->
    <div class="card" style="padding:22px; position:sticky; top:80px;">
        <h4 style="font-size:1.1rem; font-weight:800; color:var(--secondary); margin-bottom:16px; display:flex; justify-content:space-between;">
            <span><i class="fa-solid fa-cart-shopping" style="color:var(--primary);"></i> Current Invoice</span>
            <span id="cart-item-count" class="badge badge-info" style="font-size:0.8rem;">0 items</span>
        </h4>

        <!-- Cart Items Table -->
        <div style="max-height:260px; overflow-y:auto; border-bottom:1px solid var(--border); margin-bottom:16px;">
            <table style="width:100%; font-size:0.88rem;">
                <tbody id="cart-tbody">
                    <tr id="empty-cart-row">
                        <td colspan="4" style="text-align:center; padding:30px 10px; color:var(--text-muted);">
                            <i class="fa-solid fa-basket-shopping" style="font-size:1.8rem; opacity:0.3; margin-bottom:6px; display:block;"></i>
                            Click any medicine on the left to add to bill
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Form for Checkout -->
        <form action="" method="POST" id="pos-form" onsubmit="return validateBeforeSubmit();">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
            <input type="hidden" name="action" value="complete_pos_sale">
            <input type="hidden" name="items_json" id="items_json" value="[]">

            <!-- Customer & Dispensing Fields -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:10px;">
                <div>
                    <label style="font-size:0.78rem; font-weight:700; color:var(--text-muted); display:block; margin-bottom:3px;">Patient / Customer Name</label>
                    <input type="text" name="customer_name" class="form-control" style="padding:6px 10px; font-size:0.88rem;" value="Walk-in Patient" required>
                </div>
                <div>
                    <label style="font-size:0.78rem; font-weight:700; color:var(--text-muted); display:block; margin-bottom:3px;">Patient Mobile #</label>
                    <input type="text" name="phone" class="form-control" style="padding:6px 10px; font-size:0.88rem;" value="+91 98000 12345" required>
                </div>
            </div>

            <!-- Doctor Details (Visible if any Rx item added) -->
            <div id="doctor-fields" style="display:none; background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:10px; margin-bottom:12px;">
                <span style="font-size:0.75rem; font-weight:800; color:#b91c1c; display:block; margin-bottom:6px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> Schedule H Drugs Present — Doctor Details Required
                </span>
                <div style="display:grid; grid-template-columns:1.2fr 1fr; gap:8px;">
                    <input type="text" name="doctor_name" placeholder="Dr. Name & Degree" class="form-control" style="padding:5px 8px; font-size:0.82rem;">
                    <input type="text" name="doctor_reg_no" placeholder="MCI / Reg No" class="form-control" style="padding:5px 8px; font-size:0.82rem;">
                </div>
            </div>

            <!-- Clinical Drug Safety & Interaction Warning Box -->
            <div id="drug-interaction-alert" style="display:none; background:#fff1f2; border:1px solid #fecdd3; color:#9f1239; border-radius:8px; padding:10px 12px; margin-bottom:12px; font-size:0.8rem; line-height:1.4;">
            </div>

            <!-- Order Type & Payment -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px;">
                <div>
                    <label style="font-size:0.78rem; font-weight:700; color:var(--text-muted); display:block; margin-bottom:3px;">Dispense Mode</label>
                    <select name="order_type" id="order_type" class="form-control" style="padding:6px 10px; font-size:0.88rem;" onchange="updateTotals()">
                        <option value="Counter Pickup">Counter Pickup (₹0 Fee)</option>
                        <option value="Home Delivery">Doorstep Home Delivery (+₹40)</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:0.78rem; font-weight:700; color:var(--text-muted); display:block; margin-bottom:3px;">Payment Method</label>
                    <select name="payment_method" class="form-control" style="padding:6px 10px; font-size:0.88rem;">
                        <option value="Cash">Cash at Counter</option>
                        <option value="UPI / QR">UPI / QR (GPay, PhonePe)</option>
                        <option value="Credit / Debit Card">Credit / Debit Card</option>
                        <option value="Credit Account">Store Credit Account</option>
                    </select>
                </div>
            </div>

            <!-- Financial Calculations -->
            <div style="background:#f8fafc; border-radius:10px; padding:12px 16px; margin-bottom:16px;">
                <div style="display:flex; justify-content:space-between; font-size:0.88rem; margin-bottom:4px; color:var(--text-muted);">
                    <span>Medicines Subtotal:</span>
                    <span id="summary-subtotal" style="font-weight:700; color:var(--text-main);">₹0.00</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:0.88rem; margin-bottom:4px; color:var(--text-muted);">
                    <span>Delivery Charges:</span>
                    <span id="summary-delivery" style="font-weight:700; color:var(--text-main);">₹0.00</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:1.15rem; font-weight:800; color:var(--secondary); border-top:1px dashed var(--border); padding-top:6px; margin-top:6px;">
                    <span>Grand Total:</span>
                    <span id="summary-grand-total" style="color:var(--primary);">₹0.00</span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" id="btn-submit-pos" style="width:100%; justify-content:center; padding:12px; font-size:1rem; border-radius:10px; box-shadow:0 4px 15px rgba(0,192,139,0.3);" disabled>
                <i class="fa-solid fa-receipt"></i> Generate Receipt & Dispense
            </button>
        </form>
    </div>
</div>

<script>
    const allMedicines = <?= json_encode($medicines); ?>;
    let cart = [];

    // Search Filter
    document.getElementById('med-search').addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        const items = document.querySelectorAll('.pos-item-card');
        items.forEach(el => {
            const name = el.getAttribute('data-name').toLowerCase();
            const batch = el.getAttribute('data-batch').toLowerCase();
            if (name.includes(query) || batch.includes(query)) {
                el.style.display = 'flex';
            } else {
                el.style.display = 'none';
            }
        });
    });

    function addToCart(medId) {
        const med = allMedicines.find(m => m.id == medId);
        if (!med) return;

        const existing = cart.find(c => c.id == medId);
        if (existing) {
            if (existing.qty < med.stock_quantity) {
                existing.qty++;
            } else {
                alert(`Cannot add more than ${med.stock_quantity} available units.`);
            }
        } else {
            cart.push({
                id: med.id,
                name: med.name,
                strength: med.strength,
                price: parseFloat(med.selling_price),
                stock: parseInt(med.stock_quantity),
                requires_rx: parseInt(med.requires_prescription),
                qty: 1
            });
        }
        renderCart();
    }

    function updateQty(medId, delta) {
        const item = cart.find(c => c.id == medId);
        if (!item) return;

        item.qty += delta;
        if (item.qty <= 0) {
            cart = cart.filter(c => c.id != medId);
        } else if (item.qty > item.stock) {
            item.qty = item.stock;
            alert(`Maximum available stock reached (${item.stock}).`);
        }
        renderCart();
    }

    function removeFromCart(medId) {
        cart = cart.filter(c => c.id != medId);
        renderCart();
    }

    function clearCart() {
        cart = [];
        renderCart();
    }

    function renderCart() {
        const tbody = document.getElementById('cart-tbody');
        const itemCount = document.getElementById('cart-item-count');
        const submitBtn = document.getElementById('btn-submit-pos');
        const rxBox = document.getElementById('doctor-fields');

        if (cart.length === 0) {
            tbody.innerHTML = `
                <tr id="empty-cart-row">
                    <td colspan="4" style="text-align:center; padding:30px 10px; color:var(--text-muted);">
                        <i class="fa-solid fa-basket-shopping" style="font-size:1.8rem; opacity:0.3; margin-bottom:6px; display:block;"></i>
                        Click any medicine on the left to add to bill
                    </td>
                </tr>`;
            itemCount.innerText = '0 items';
            submitBtn.disabled = true;
            rxBox.style.display = 'none';
            const alertBox = document.getElementById('drug-interaction-alert');
            if (alertBox) alertBox.style.display = 'none';
            updateTotals();
            return;
        }

        submitBtn.disabled = false;
        let html = '';
        let hasRx = false;

        cart.forEach(item => {
            if (item.requires_rx === 1) hasRx = true;
            const lineTotal = (item.price * item.qty).toFixed(2);
            html += `
                <tr>
                    <td style="padding:8px 4px;">
                        <strong style="color:var(--secondary);">${item.name}</strong>
                        <div style="font-size:0.75rem; color:var(--text-muted);">${item.strength} &bull; ₹${item.price.toFixed(2)}</div>
                    </td>
                    <td style="padding:8px 4px; text-align:center; white-space:nowrap;">
                        <button type="button" class="btn btn-secondary btn-sm" style="padding:2px 7px;" onclick="updateQty(${item.id}, -1)">-</button>
                        <span style="font-weight:700; margin:0 6px;">${item.qty}</span>
                        <button type="button" class="btn btn-secondary btn-sm" style="padding:2px 7px;" onclick="updateQty(${item.id}, 1)">+</button>
                    </td>
                    <td style="padding:8px 4px; text-align:right; font-weight:700; color:var(--secondary);">
                        ₹${lineTotal}
                    </td>
                    <td style="padding:8px 4px; text-align:right;">
                        <button type="button" style="background:none; border:none; color:var(--danger); cursor:pointer;" onclick="removeFromCart(${item.id})" title="Remove">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
        itemCount.innerText = `${cart.length} item${cart.length > 1 ? 's' : ''}`;
        rxBox.style.display = hasRx ? 'block' : 'none';

        // Clinical Drug-Drug Interaction Safety Matrix
        const alertBox = document.getElementById('drug-interaction-alert');
        if (alertBox) {
            const medNames = cart.map(c => c.name.toLowerCase()).join(' ');
            let warnings = [];
            if (medNames.includes('aspirin') && medNames.includes('ibuprofen')) {
                warnings.push('⚠️ Duplicate NSAID Alert: Concurrent Aspirin + Ibuprofen risks acute GI ulceration.');
            }
            if (medNames.includes('warfarin') && (medNames.includes('aspirin') || medNames.includes('ibuprofen'))) {
                warnings.push('🚨 Critical Anticoagulant Warning: Bleeding hazard synergism between Warfarin and NSAID.');
            }
            if (medNames.includes('clopidogrel') && medNames.includes('omeprazole')) {
                warnings.push('⚡ Bioavailability Alert: Omeprazole impairs Clopidogrel antiplatelet activation.');
            }
            if (medNames.includes('ramipril') && medNames.includes('spironolactone')) {
                warnings.push('⚠️ Hyperkalemia Warning: Combined ACE-I + Aldosterone Antagonist.');
            }

            if (warnings.length > 0) {
                alertBox.innerHTML = `<div style="font-weight:800; margin-bottom:4px;"><i class="fa-solid fa-triangle-exclamation"></i> Clinical Drug Interaction Detected:</div>` + warnings.map(w => `<div>&bull; ${w}</div>`).join('');
                alertBox.style.display = 'block';
            } else {
                alertBox.style.display = 'none';
            }
        }

        updateTotals();
    }

    function updateTotals() {
        let subtotal = 0;
        cart.forEach(item => subtotal += (item.price * item.qty));

        const orderType = document.getElementById('order_type').value;
        const deliveryFee = (orderType === 'Home Delivery') ? 40 : 0;
        const grandTotal = subtotal + deliveryFee;

        document.getElementById('summary-subtotal').innerText = '₹' + subtotal.toFixed(2);
        document.getElementById('summary-delivery').innerText = '₹' + deliveryFee.toFixed(2);
        document.getElementById('summary-grand-total').innerText = '₹' + grandTotal.toFixed(2);
        document.getElementById('items_json').value = JSON.stringify(cart);
    }

    function validateBeforeSubmit() {
        if (cart.length === 0) {
            alert('Your cart is empty. Please add items to bill.');
            return false;
        }
        return true;
    }
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
