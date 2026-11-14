<?php
declare(strict_types=1);

$pageTitle = 'Executive Revenue & Profit Analytics';
require_once __DIR__ . '/layout/header.php';

use App\Core\Security;

// 1. Core Financial Metrics
$totalOrders = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status != 'Cancelled'")->fetchColumn();
$grossRevenue = (float)$db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_status != 'Cancelled'")->fetchColumn();
$todayRevenue = (float)$db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = CURRENT_DATE AND order_status != 'Cancelled'")->fetchColumn();
$weekRevenue = (float)$db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) >= DATE('now', '-7 days') AND order_status != 'Cancelled'")->fetchColumn();
$monthRevenue = (float)$db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) >= DATE('now', '-30 days') AND order_status != 'Cancelled'")->fetchColumn();

// 2. Cost of Goods Sold (COGS) & Profit Margin estimation
$cogsQuery = $db->query("
    SELECT COALESCE(SUM(oi.quantity * m.purchase_price), 0)
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN medicines m ON oi.medicine_id = m.id
    WHERE o.order_status != 'Cancelled'
");
$totalCogs = (float)$cogsQuery->fetchColumn();
$grossProfit = max(0.0, $grossRevenue - $totalCogs);
$marginPercent = ($grossRevenue > 0) ? round(($grossProfit / $grossRevenue) * 100, 1) : 0.0;
$aov = ($totalOrders > 0) ? ($grossRevenue / $totalOrders) : 0.0;

// 3. Payment Method Distribution
$paymentBreakdown = $db->query("
    SELECT payment_method, COUNT(*) AS count, COALESCE(SUM(total_amount), 0) AS total
    FROM orders 
    WHERE order_status != 'Cancelled'
    GROUP BY payment_method
")->fetchAll(PDO::FETCH_ASSOC);

// 4. Disease Category Revenue Breakdown
$categoryRevenue = $db->query("
    SELECT c.name, COUNT(oi.id) as units_sold, COALESCE(SUM(oi.total_price), 0) as category_total
    FROM disease_categories c
    JOIN medicines m ON m.category_id = c.id
    JOIN order_items oi ON oi.medicine_id = m.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.order_status != 'Cancelled'
    GROUP BY c.id
    ORDER BY category_total DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 5. Top 10 Best-Selling Medicines
$topSelling = $db->query("
    SELECT m.name, m.strength, m.dosage_form, m.batch_number, c.name AS category_name,
           SUM(oi.quantity) AS total_units,
           SUM(oi.total_price) AS total_sales,
           SUM(oi.quantity * m.purchase_price) AS total_cost
    FROM order_items oi
    JOIN medicines m ON oi.medicine_id = m.id
    JOIN disease_categories c ON m.category_id = c.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.order_status != 'Cancelled'
    GROUP BY m.id
    ORDER BY total_sales DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Total units sold across all medicines
$totalUnitsSold = (int)$db->query("
    SELECT COALESCE(SUM(oi.quantity), 0)
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE o.order_status != 'Cancelled'
")->fetchColumn();

// 6. 14-Day Sales Velocity & Profit Trend for Chart.js
$daysMap = [];
for ($d = 13; $d >= 0; $d--) {
    $dateKey = date('Y-m-d', strtotime("-{$d} days"));
    $daysMap[$dateKey] = [
        'date' => date('d M', strtotime($dateKey)),
        'revenue' => 0.0,
        'profit' => 0.0,
        'orders' => 0
    ];
}

$dailyQuery = $db->query("
    SELECT DATE(o.created_at) AS sale_date,
           COUNT(DISTINCT o.id) AS order_count,
           COALESCE(SUM(oi.total_price), 0) AS rev,
           COALESCE(SUM(oi.quantity * m.purchase_price), 0) AS cogs
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    JOIN medicines m ON oi.medicine_id = m.id
    WHERE o.order_status != 'Cancelled' AND DATE(o.created_at) >= DATE('now', '-14 days')
    GROUP BY DATE(o.created_at)
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($dailyQuery as $row) {
    $k = $row['sale_date'];
    if (isset($daysMap[$k])) {
        $daysMap[$k]['revenue'] = round((float)$row['rev'], 2);
        $daysMap[$k]['profit'] = max(0.0, round((float)$row['rev'] - (float)$row['cogs'], 2));
        $daysMap[$k]['orders'] = (int)$row['order_count'];
    }
}

$trendLabels = array_column($daysMap, 'date');
$trendRevenue = array_column($daysMap, 'revenue');
$trendProfit = array_column($daysMap, 'profit');

// Chart 2: Category Top 6
$catLabels = [];
$catTotals = [];
foreach (array_slice($categoryRevenue, 0, 6) as $cr) {
    $catLabels[] = $cr['name'];
    $catTotals[] = round((float)$cr['category_total'], 2);
}

// Chart 3: Payment Breakdown
$payLabels = [];
$payTotals = [];
foreach ($paymentBreakdown as $pb) {
    $payLabels[] = $pb['payment_method'];
    $payTotals[] = round((float)$pb['total'], 2);
}

// Chart 4: Top 5 Medicines
$topMedLabels = [];
$topMedUnits = [];
foreach (array_slice($topSelling, 0, 5) as $tm) {
    $topMedLabels[] = $tm['name'] . ' ' . $tm['strength'];
    $topMedUnits[] = (int)$tm['total_units'];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:1.4rem; color:var(--secondary); font-weight:800; display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-chart-line" style="color:var(--primary);"></i> Revenue & Profit Margin Intelligence
        </h2>
        <span style="font-size:0.85rem; color:var(--text-muted);">Real-time profitability, Cost of Goods Sold (COGS), daily billing velocity & therapeutic category market share</span>
    </div>
    <div style="display:flex; gap:10px;">
        <button type="button" onclick="window.print()" class="btn btn-secondary btn-sm"><i class="fa-solid fa-print"></i> Print Executive Report</button>
        <a href="<?= app_url('backend/admin/pos.php'); ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-cash-register"></i> Open POS Billing</a>
    </div>
</div>

<!-- Primary Financial KPIs -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:20px; margin-bottom:28px;">
    <!-- Gross Revenue -->
    <div class="card" style="margin-bottom:0;">
        <span style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Gross Revenue</span>
        <div style="font-size:2rem; font-weight:800; color:var(--primary); margin:6px 0 4px;"><?= format_price($grossRevenue); ?></div>
        <div style="display:flex; justify-content:space-between; font-size:0.8rem; color:var(--text-muted);">
            <span>Today: <strong><?= format_price($todayRevenue); ?></strong></span>
            <span>30D: <strong><?= format_price($monthRevenue); ?></strong></span>
        </div>
    </div>

    <!-- Estimated Gross Profit -->
    <div class="card" style="margin-bottom:0;">
        <span style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Estimated Gross Profit</span>
        <div style="font-size:2rem; font-weight:800; color:#10b981; margin:6px 0 4px;"><?= format_price($grossProfit); ?></div>
        <div style="display:flex; justify-content:space-between; font-size:0.8rem; color:var(--text-muted);">
            <span>Profit Margin: <strong><?= $marginPercent; ?>%</strong></span>
            <span>COGS: <strong><?= format_price($totalCogs); ?></strong></span>
        </div>
    </div>

    <!-- Total Orders & AOV -->
    <div class="card" style="margin-bottom:0;">
        <span style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Fulfilled Orders & AOV</span>
        <div style="font-size:2rem; font-weight:800; color:var(--secondary); margin:6px 0 4px;"><?= number_format($totalOrders); ?></div>
        <div style="display:flex; justify-content:space-between; font-size:0.8rem; color:var(--text-muted);">
            <span>Avg Order Value:</span>
            <strong><?= format_price($aov); ?></strong>
        </div>
    </div>

    <!-- Units Dispensed -->
    <div class="card" style="margin-bottom:0;">
        <span style="font-size:0.8rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Medicines Dispensed</span>
        <div style="font-size:2rem; font-weight:800; color:var(--info); margin:6px 0 4px;"><?= number_format($totalUnitsSold); ?></div>
        <div style="font-size:0.8rem; color:var(--text-muted);">
            <span>Prescription & OTC packs dispensed</span>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- CHART ROW 1: 14-Day Sales Velocity & Categories -->
<!-- ============================================== -->
<div style="display:grid; grid-template-columns: 2fr 1fr; gap:24px; margin-bottom:28px;">
    <!-- Revenue & Profit Trend Chart -->
    <div class="card" style="margin-bottom:0; padding:22px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
            <div>
                <h3 style="font-size:1.1rem; font-weight:800; color:var(--secondary); margin-bottom:2px;">
                    <i class="fa-solid fa-chart-area" style="color:var(--primary);"></i> 14-Day Revenue vs Profit Trajectory
                </h3>
                <span style="font-size:0.82rem; color:var(--text-muted);">Daily sales volume against net pharmacy margin</span>
            </div>
            <div style="display:flex; gap:12px; font-size:0.82rem; font-weight:700;">
                <span style="color:#00c08b;"><i class="fa-solid fa-circle" style="font-size:0.6rem;"></i> Revenue</span>
                <span style="color:#0284c7;"><i class="fa-solid fa-circle" style="font-size:0.6rem;"></i> Gross Profit</span>
            </div>
        </div>
        <div style="position:relative; height:280px; width:100%;">
            <canvas id="revenueTrendChart"></canvas>
        </div>
    </div>

    <!-- Therapeutic Category Market Share -->
    <div class="card" style="margin-bottom:0; padding:22px;">
        <div style="margin-bottom:14px;">
            <h3 style="font-size:1.1rem; font-weight:800; color:var(--secondary); margin-bottom:2px;">
                <i class="fa-solid fa-chart-pie" style="color:var(--info);"></i> Condition Market Share
            </h3>
            <span style="font-size:0.82rem; color:var(--text-muted);">Sales breakdown by disease sector</span>
        </div>
        <div style="position:relative; height:260px; width:100%;">
            <canvas id="categoryShareChart"></canvas>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- CHART ROW 2: Payment Channels & Top Medicines -->
<!-- ============================================== -->
<div style="display:grid; grid-template-columns: 1fr 1fr; gap:24px; margin-bottom:28px;">
    <!-- Payment Methods Chart -->
    <div class="card" style="margin-bottom:0; padding:22px;">
        <div style="margin-bottom:14px;">
            <h3 style="font-size:1.1rem; font-weight:800; color:var(--secondary); margin-bottom:2px;">
                <i class="fa-solid fa-wallet" style="color:#f59e0b;"></i> Payment Channel Distribution
            </h3>
            <span style="font-size:0.82rem; color:var(--text-muted);">Digital UPI vs Cash on Delivery vs Cards</span>
        </div>
        <div style="position:relative; height:250px; width:100%;">
            <canvas id="paymentMethodsChart"></canvas>
        </div>
    </div>

    <!-- Top 5 Dispensed Medicines Horizontal Bar -->
    <div class="card" style="margin-bottom:0; padding:22px;">
        <div style="margin-bottom:14px;">
            <h3 style="font-size:1.1rem; font-weight:800; color:var(--secondary); margin-bottom:2px;">
                <i class="fa-solid fa-pills" style="color:var(--primary);"></i> Top 5 Medicines Dispensed
            </h3>
            <span style="font-size:0.82rem; color:var(--text-muted);">Highest volume packs packaged</span>
        </div>
        <div style="position:relative; height:250px; width:100%;">
            <canvas id="topMedicinesChart"></canvas>
        </div>
    </div>
</div>

<!-- Detailed Data Tables -->
<div style="display:grid; grid-template-columns: 2fr 1fr; gap:24px; margin-bottom:28px;">
    <!-- Best-Selling Medicines Table -->
    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-trophy" style="color:#f59e0b;"></i> Top-Selling Medicines by Revenue</h3>
            <span style="font-size:0.82rem; color:var(--text-muted);">Ranked by gross invoice value</span>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Medicine</th>
                        <th>Category</th>
                        <th>Units</th>
                        <th>Gross Revenue</th>
                        <th>Est. Profit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($topSelling)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:30px; color:var(--text-muted);">No sales data available yet.</td></tr>
                    <?php else: ?>
                        <?php $rank = 1; foreach ($topSelling as $ts): 
                            $profit = max(0, (float)$ts['total_sales'] - (float)$ts['total_cost']);
                        ?>
                            <tr>
                                <td>
                                    <span style="width:24px; height:24px; border-radius:50%; background:<?= $rank <= 3 ? 'var(--primary-light)' : '#f1f5f9'; ?>; color:<?= $rank <= 3 ? 'var(--primary)' : 'var(--text-muted)'; ?>; display:inline-flex; align-items:center; justify-content:center; font-weight:800; font-size:0.8rem;">
                                        <?= $rank++; ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color:var(--secondary);"><?= Security::e($ts['name']); ?></strong>
                                    <div style="font-size:0.75rem; color:var(--text-muted);"><?= Security::e($ts['dosage_form']); ?> <?= Security::e($ts['strength']); ?></div>
                                </td>
                                <td style="font-size:0.82rem; color:var(--text-muted);"><?= Security::e($ts['category_name']); ?></td>
                                <td><strong><?= $ts['total_units']; ?></strong> packs</td>
                                <td style="font-weight:700; color:var(--primary);"><?= format_price($ts['total_sales']); ?></td>
                                <td style="font-weight:700; color:#10b981;"><?= format_price($profit); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Category Market Share Progress Bars -->
    <div class="card" style="margin-bottom:0;">
        <h4 style="font-size:1.05rem; font-weight:800; color:var(--secondary); margin-bottom:16px; display:flex; align-items:center; gap:8px;">
            <i class="fa-solid fa-viruses" style="color:var(--info);"></i> Revenue by Condition
        </h4>
        <?php foreach ($categoryRevenue as $cr): 
            $catPct = ($grossRevenue > 0) ? round(((float)$cr['category_total'] / $grossRevenue) * 100, 1) : 0;
        ?>
            <div style="margin-bottom:12px;">
                <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:3px;">
                    <span style="font-weight:600;"><?= Security::e($cr['name']); ?></span>
                    <span style="font-weight:700; color:var(--secondary);"><?= format_price($cr['category_total']); ?></span>
                </div>
                <div style="width:100%; height:6px; background:#e2e8f0; border-radius:3px; overflow:hidden;">
                    <div style="width:<?= $catPct; ?>%; height:100%; background:#0284c7; border-radius:3px;"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Chart.js Engine (Self-Hosted Local Bundle) -->
<script src="<?= app_url('assets/js/chart.umd.min.js'); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. 14-Day Revenue vs Profit Line Chart
    const ctxTrend = document.getElementById('revenueTrendChart');
    if (ctxTrend) {
        new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: <?= json_encode($trendLabels); ?>,
                datasets: [
                    {
                        label: 'Gross Sales (₹)',
                        data: <?= json_encode($trendRevenue); ?>,
                        borderColor: '#00c08b',
                        backgroundColor: 'rgba(0, 192, 139, 0.12)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: '#00c08b',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Gross Profit (₹)',
                        data: <?= json_encode($trendProfit); ?>,
                        borderColor: '#0284c7',
                        backgroundColor: 'rgba(2, 132, 199, 0.08)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: '#0284c7',
                        pointRadius: 3,
                        pointHoverRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(c) {
                                return c.dataset.label + ': ₹' + Number(c.parsed.y).toLocaleString('en-IN', {minimumFractionDigits:2});
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: {
                            callback: function(v) { return '₹' + v; },
                            font: { family: 'Plus Jakarta Sans', size: 11 }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Plus Jakarta Sans', size: 11 } }
                    }
                }
            }
        });
    }

    // 2. Category Share Doughnut
    const ctxCat = document.getElementById('categoryShareChart');
    if (ctxCat) {
        new Chart(ctxCat, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($catLabels); ?>,
                datasets: [{
                    data: <?= json_encode($catTotals); ?>,
                    backgroundColor: [
                        '#00c08b', '#0284c7', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#64748b'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(c) {
                                return ' ' + c.label + ': ₹' + Number(c.parsed).toLocaleString('en-IN', {minimumFractionDigits:2});
                            }
                        }
                    }
                },
                cutout: '68%'
            }
        });
    }

    // 3. Payment Methods Chart
    const ctxPay = document.getElementById('paymentMethodsChart');
    if (ctxPay) {
        new Chart(ctxPay, {
            type: 'pie',
            data: {
                labels: <?= json_encode($payLabels); ?>,
                datasets: [{
                    data: <?= json_encode($payTotals); ?>,
                    backgroundColor: ['#10b981', '#f59e0b', '#6366f1', '#06b6d4'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(c) {
                                return ' ' + c.label + ': ₹' + Number(c.parsed).toLocaleString('en-IN', {minimumFractionDigits:2});
                            }
                        }
                    }
                }
            }
        });
    }

    // 4. Top Medicines Horizontal Bar Chart
    const ctxTop = document.getElementById('topMedicinesChart');
    if (ctxTop) {
        new Chart(ctxTop, {
            type: 'bar',
            data: {
                labels: <?= json_encode($topMedLabels); ?>,
                datasets: [{
                    label: 'Units Dispensed',
                    data: <?= json_encode($topMedUnits); ?>,
                    backgroundColor: '#00c08b',
                    borderRadius: 6
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { stepSize: 1 }
                    },
                    y: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
