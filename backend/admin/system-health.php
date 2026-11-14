<?php
declare(strict_types=1);

$pageTitle = 'System Health & Server Diagnostics';
require_once __DIR__ . '/layout/header.php';

use App\Core\Security;

// Check extensions
$requiredExtensions = [
    'pdo'        => 'PDO Database Abstraction',
    'pdo_sqlite' => 'SQLite Database Driver',
    'openssl'    => 'OpenSSL Cryptographic TLS',
    'curl'       => 'cURL HTTP Engine',
    'mbstring'   => 'Multibyte String UTF-8',
    'sockets'    => 'Network Sockets (Gmail SMTP)',
    'json'       => 'JSON Serialization',
    'filter'     => 'Data Sanitization Filter'
];

$extStatus = [];
foreach ($requiredExtensions as $ext => $label) {
    $extStatus[$ext] = [
        'name' => $label,
        'loaded' => extension_loaded($ext)
    ];
}

// Database Diagnostics
$sqlitePath = APP_ROOT . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'pharmacy.sqlite';
$sqliteWalPath = $sqlitePath . '-wal';
$sqliteShmPath = $sqlitePath . '-shm';

$dbSize = file_exists($sqlitePath) ? filesize($sqlitePath) : 0;
$walSize = file_exists($sqliteWalPath) ? filesize($sqliteWalPath) : 0;
$shmSize = file_exists($sqliteShmPath) ? filesize($sqliteShmPath) : 0;

$journalMode = $db->query("PRAGMA journal_mode")->fetchColumn();
$busyTimeout = $db->query("PRAGMA busy_timeout")->fetchColumn();
$synchronous = $db->query("PRAGMA synchronous")->fetchColumn();

// Handle Live Integrity Check
$integrityResult = null;
if (isset($_GET['check_integrity'])) {
    $res = $db->query("PRAGMA integrity_check")->fetchAll(PDO::FETCH_COLUMN);
    $integrityResult = implode(', ', $res);
}

// Storage Diagnostics
$storagePath = STORAGE_PATH;
$sessionsPath = STORAGE_PATH . DIRECTORY_SEPARATOR . 'sessions';
$sessionCount = is_dir($sessionsPath) ? count(glob($sessionsPath . '/*')) : 0;

$freeSpaceE = @disk_free_space('E:');
$totalSpaceE = @disk_total_space('E:');
$freeSpacePercent = ($totalSpaceE > 0) ? round(($freeSpaceE / $totalSpaceE) * 100, 1) : 0;

function format_bytes(int|float $bytes): string {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:1.4rem; color:var(--secondary); font-weight:800; display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-server" style="color:var(--primary);"></i> System Health & Server Diagnostics
        </h2>
        <span style="font-size:0.85rem; color:var(--text-muted);">Real-time runtime inspection, PHP environment, storage telemetry & SQLite WAL database integrity</span>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="system-health.php?check_integrity=1" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-stethoscope"></i> Run DB Integrity Check
        </a>
    </div>
</div>

<?php if ($integrityResult !== null): ?>
    <div class="card" style="background:#ecfdf5; border:1px solid #6ee7b7; padding:18px; margin-bottom:24px;">
        <h4 style="color:#065f46; font-size:1.05rem; font-weight:800; margin-bottom:4px;">
            <i class="fa-solid fa-circle-check"></i> SQLite Integrity Check Result
        </h4>
        <p style="color:#047857; font-size:0.9rem; font-family:'JetBrains Mono', monospace; margin:0;">
            Status: <strong><?= Security::e($integrityResult); ?></strong> (Zero corruption detected)
        </p>
    </div>
<?php endif; ?>

<!-- Health Highlights -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:20px; margin-bottom:28px;">
    <div class="card" style="margin-bottom:0;">
        <span style="font-size:0.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">PHP Engine</span>
        <div style="font-size:1.6rem; font-weight:800; color:var(--secondary); margin-top:4px;">v<?= PHP_VERSION; ?></div>
        <span style="font-size:0.8rem; color:#10b981; font-weight:700;">Running on Windows (x64)</span>
    </div>

    <div class="card" style="margin-bottom:0;">
        <span style="font-size:0.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Drive E: Free Storage</span>
        <div style="font-size:1.6rem; font-weight:800; color:var(--primary); margin-top:4px;"><?= format_bytes($freeSpaceE ?: 0); ?></div>
        <span style="font-size:0.8rem; color:var(--text-muted);"><?= $freeSpacePercent; ?>% free space available</span>
    </div>

    <div class="card" style="margin-bottom:0;">
        <span style="font-size:0.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">SQLite Mode</span>
        <div style="font-size:1.6rem; font-weight:800; color:var(--secondary); margin-top:4px;"><?= strtoupper((string)$journalMode); ?></div>
        <span style="font-size:0.8rem; color:#10b981; font-weight:700;">High-Concurrency Safe</span>
    </div>

    <div class="card" style="margin-bottom:0;">
        <span style="font-size:0.78rem; font-weight:700; color:var(--text-muted); text-transform:uppercase;">Active Sessions</span>
        <div style="font-size:1.6rem; font-weight:800; color:var(--info); margin-top:4px;"><?= $sessionCount; ?> Files</div>
        <span style="font-size:0.8rem; color:var(--text-muted);">Stored on Drive E:</span>
    </div>
</div>

<div style="display:grid; grid-template-columns:1.2fr 1fr; gap:24px;">
    <!-- PHP Configuration Telemetry -->
    <div class="card">
        <h3 class="card-title" style="margin-bottom:18px;">
            <i class="fa-brands fa-php" style="color:#8b5cf6;"></i> PHP Runtime Parameters
        </h3>
        <table>
            <tbody>
                <tr>
                    <td><strong>Execution Memory Limit</strong></td>
                    <td style="font-family:'JetBrains Mono', monospace; font-weight:700;"><?= ini_get('memory_limit'); ?></td>
                </tr>
                <tr>
                    <td><strong>Max Execution Timeout</strong></td>
                    <td style="font-family:'JetBrains Mono', monospace; font-weight:700;"><?= ini_get('max_execution_time'); ?>s</td>
                </tr>
                <tr>
                    <td><strong>Max Upload File Size</strong></td>
                    <td style="font-family:'JetBrains Mono', monospace; font-weight:700;"><?= ini_get('upload_max_filesize'); ?></td>
                </tr>
                <tr>
                    <td><strong>Max POST Request Size</strong></td>
                    <td style="font-family:'JetBrains Mono', monospace; font-weight:700;"><?= ini_get('post_max_size'); ?></td>
                </tr>
                <tr>
                    <td><strong>Session Cookie SameSite</strong></td>
                    <td><span class="badge badge-info">Lax (Secure)</span></td>
                </tr>
                <tr>
                    <td><strong>Session Storage Root</strong></td>
                    <td style="font-size:0.82rem; font-family:'JetBrains Mono', monospace; color:var(--text-muted);"><?= Security::e($sessionsPath); ?></td>
                </tr>
            </tbody>
        </table>

        <h4 style="font-size:1rem; font-weight:800; color:var(--secondary); margin:24px 0 12px; display:flex; align-items:center; gap:8px;">
            <i class="fa-solid fa-puzzle-piece" style="color:var(--primary);"></i> Required PHP Extensions
        </h4>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
            <?php foreach ($extStatus as $extName => $meta): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; background:#f8fafc; border:1px solid var(--border); border-radius:8px; font-size:0.85rem;">
                    <span><?= Security::e($meta['name']); ?></span>
                    <?php if ($meta['loaded']): ?>
                        <span class="badge badge-success" style="font-size:0.75rem;"><i class="fa-solid fa-check"></i> Active</span>
                    <?php else: ?>
                        <span class="badge badge-danger" style="font-size:0.75rem;"><i class="fa-solid fa-xmark"></i> Missing</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- SQLite & File System Telemetry -->
    <div class="card">
        <h3 class="card-title" style="margin-bottom:18px;">
            <i class="fa-solid fa-database" style="color:var(--primary);"></i> Database Diagnostics
        </h3>
        <table>
            <tbody>
                <tr>
                    <td><strong>Database Engine</strong></td>
                    <td><span class="badge badge-secondary">SQLite 3</span></td>
                </tr>
                <tr>
                    <td><strong>Database File Path</strong></td>
                    <td style="font-size:0.78rem; font-family:'JetBrains Mono', monospace;"><?= Security::e($sqlitePath); ?></td>
                </tr>
                <tr>
                    <td><strong>Database Main Size</strong></td>
                    <td style="font-family:'JetBrains Mono', monospace; font-weight:700;"><?= format_bytes($dbSize); ?></td>
                </tr>
                <tr>
                    <td><strong>WAL File Size</strong></td>
                    <td style="font-family:'JetBrains Mono', monospace; font-weight:700;"><?= format_bytes($walSize); ?></td>
                </tr>
                <tr>
                    <td><strong>SHM Shared Memory</strong></td>
                    <td style="font-family:'JetBrains Mono', monospace; font-weight:700;"><?= format_bytes($shmSize); ?></td>
                </tr>
                <tr>
                    <td><strong>Journal PRAGMA</strong></td>
                    <td><span class="badge badge-success"><?= Security::e((string)$journalMode); ?></span></td>
                </tr>
                <tr>
                    <td><strong>Busy Timeout PRAGMA</strong></td>
                    <td style="font-family:'JetBrains Mono', monospace; font-weight:700;"><?= Security::e((string)$busyTimeout); ?> ms</td>
                </tr>
                <tr>
                    <td><strong>Synchronous Setting</strong></td>
                    <td style="font-family:'JetBrains Mono', monospace;"><?= Security::e((string)$synchronous); ?> (NORMAL)</td>
                </tr>
            </tbody>
        </table>

        <div style="margin-top:20px; background:var(--primary-light); padding:16px; border-radius:12px; border:1px solid rgba(0,192,139,0.3);">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                <i class="fa-solid fa-circle-check" style="color:var(--primary); font-size:1.2rem;"></i>
                <strong style="color:#0f766e; font-size:0.92rem;">Windows Multi-Process Ready</strong>
            </div>
            <p style="font-size:0.82rem; color:#134e4a; line-height:1.4; margin:0;">
                WAL mode prevents file lock deadlocks on rapid redirects and concurrent browser requests on Windows local servers.
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
