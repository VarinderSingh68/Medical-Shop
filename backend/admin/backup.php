<?php
declare(strict_types=1);

$pageTitle = 'Database Backup & Snapshots';
require_once __DIR__ . '/layout/header.php';

use App\Core\Security;

$csrfToken = Security::generateCsrf();
$backupDir = STORAGE_PATH . DIRECTORY_SEPARATOR . 'backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0777, true);
}

$sqliteFile = APP_ROOT . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'pharmacy.sqlite';

// Handle Direct Download of Live Database — require CSRF token in URL
if (isset($_GET['download']) && $_GET['download'] === 'live') {
    // Validate CSRF token passed as GET parameter to prevent CSRF on downloads
    if (!Security::validateCsrf($_GET['_token'] ?? '')) {
        http_response_code(403);
        die('Security token validation failed. Please use the Download button in the admin panel.');
    }
    if (file_exists($sqliteFile)) {
        // Flush WAL to main DB
        try { $db->exec("PRAGMA wal_checkpoint(FULL);"); } catch (\Exception $e) {}
        
        header('Content-Type: application/x-sqlite3');
        header('Content-Disposition: attachment; filename="pharmacy_' . date('Ymd_His') . '.sqlite"');
        header('Content-Length: ' . filesize($sqliteFile));
        readfile($sqliteFile);
        exit;
    }
}

// Handle Download of an existing backup file — require CSRF + sanitize filename strictly
if (isset($_GET['download_file'])) {
    // Validate CSRF token
    if (!Security::validateCsrf($_GET['_token'] ?? '')) {
        http_response_code(403);
        die('Security token validation failed. Please use the Download button in the admin panel.');
    }
    // Strict filename sanitization: allow only alphanumerics, underscores, hyphens, dots
    $rawName = $_GET['download_file'] ?? '';
    $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', basename($rawName));
    // Only allow .sqlite files from the backup directory
    if (!preg_match('/^pharmacy_snapshot_\d{8}_\d{6}\.sqlite$/', $safeName)) {
        http_response_code(400);
        die('Invalid backup file name.');
    }
    $target = $backupDir . DIRECTORY_SEPARATOR . $safeName;
    // Verify the resolved path is inside backup dir (path traversal prevention)
    $realTarget = realpath($target);
    $realBackupDir = realpath($backupDir);
    if ($realTarget === false || $realBackupDir === false || strpos($realTarget, $realBackupDir . DIRECTORY_SEPARATOR) !== 0) {
        http_response_code(403);
        die('Access denied.');
    }
    if (file_exists($realTarget)) {
        header('Content-Type: application/x-sqlite3');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Content-Length: ' . filesize($realTarget));
        readfile($realTarget);
        exit;
    }
}

// Handle Snapshot Creation & Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Security::validateCsrf($_POST['csrf_token'] ?? '')) {
        Security::setFlash('danger', 'Security token expired.');
    } else {
        $action = $_POST['action'];

        if ($action === 'create_backup') {
            try {
                // Checkpoint WAL first
                $db->exec("PRAGMA wal_checkpoint(TRUNCATE);");
                
                $snapshotName = 'pharmacy_snapshot_' . date('Ymd_His') . '.sqlite';
                $destPath = $backupDir . DIRECTORY_SEPARATOR . $snapshotName;

                if (copy($sqliteFile, $destPath)) {
                    // Log audit
                    $log = $db->prepare("INSERT INTO audit_logs (admin_id, admin_name, action, entity_type, entity_id, details, ip_address) VALUES (:aid, :aname, 'DATABASE_BACKUP_CREATED', 'database', 0, :det, :ip)");
                    $log->execute([
                        'aid'   => $_SESSION['admin_id'] ?? 1,
                        'aname' => $_SESSION['admin_name'] ?? 'Admin',
                        'det'   => "Created manual database snapshot: {$snapshotName}",
                        'ip'    => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                    ]);

                    Security::setFlash('success', "Database snapshot '{$snapshotName}' created successfully.");
                } else {
                    Security::setFlash('danger', 'Failed to copy database file to backup directory.');
                }
            } catch (\Exception $e) {
                Security::setFlash('danger', 'Backup error: ' . $e->getMessage());
            }
            header("Location: backup.php");
            exit;
        } elseif ($action === 'delete_backup') {
            $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', basename($_POST['filename'] ?? ''));
            if (preg_match('/^pharmacy_snapshot_\d{8}_\d{6}\.sqlite$/', $filename)) {
                $target = $backupDir . DIRECTORY_SEPARATOR . $filename;
                $realTarget = realpath($target);
                $realBackupDir = realpath($backupDir);
                if ($realTarget && $realBackupDir && strpos($realTarget, $realBackupDir . DIRECTORY_SEPARATOR) === 0 && file_exists($realTarget)) {
                    unlink($realTarget);
                    Security::setFlash('success', "Backup '{$filename}' deleted.");
                } else {
                    Security::setFlash('danger', 'Invalid backup file or path.');
                }
            } else {
                Security::setFlash('danger', 'Invalid backup filename format.');
            }
            header("Location: backup.php");
            exit;
        }
    }
}

// List existing backups
$backupFiles = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $f) {
        if ($f !== '.' && $f !== '..' && str_ends_with($f, '.sqlite')) {
            $p = $backupDir . DIRECTORY_SEPARATOR . $f;
            $backupFiles[] = [
                'name' => $f,
                'size' => filesize($p),
                'created' => filemtime($p)
            ];
        }
    }
}
usort($backupFiles, fn($a, $b) => $b['created'] <=> $a['created']);

function format_bytes(int|float $bytes): string {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:1.4rem; color:var(--secondary); font-weight:800; display:flex; align-items:center; gap:10px;">
            <i class="fa-solid fa-database" style="color:var(--primary);"></i> Database Backup & Disaster Recovery
        </h2>
        <span style="font-size:0.85rem; color:var(--text-muted);">Create instant point-in-time SQLite snapshots, export production data & preserve pharmacy audit trails</span>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="backup.php?download=live&_token=<?= urlencode($csrfToken); ?>" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-cloud-arrow-down"></i> Download Live DB (.sqlite)
        </a>
        <form action="" method="POST" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
            <input type="hidden" name="action" value="create_backup">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-camera"></i> Create Instant Snapshot
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-box-archive" style="color:var(--primary);"></i> Archived Database Snapshots</h3>
        <span style="font-size:0.82rem; color:var(--text-muted);">Stored securely in storage/backups on Drive E:</span>
    </div>

    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Snapshot File Name</th>
                    <th>Created Timestamp</th>
                    <th>File Size</th>
                    <th>Integrity State</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($backupFiles)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:36px; color:var(--text-muted);">
                            <i class="fa-solid fa-database" style="font-size:2rem; opacity:0.3; margin-bottom:10px; display:block;"></i>
                            No snapshots created yet. Click "Create Instant Snapshot" to take your first backup.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($backupFiles as $bf): ?>
                        <tr>
                            <td>
                                <strong style="font-family:'JetBrains Mono', monospace; color:var(--secondary); font-size:0.92rem;"><?= Security::e($bf['name']); ?></strong>
                            </td>
                            <td style="font-size:0.88rem; color:var(--text-muted);">
                                <?= Security::e(date('d M Y, h:i:s A', $bf['created'])); ?>
                            </td>
                            <td>
                                <span class="badge badge-secondary" style="font-family:'JetBrains Mono', monospace; font-size:0.82rem;">
                                    <?= format_bytes($bf['size']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-success"><i class="fa-solid fa-check"></i> Consistent Snapshot</span>
                            </td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex; gap:8px;">
                                    <a href="backup.php?download_file=<?= urlencode($bf['name']); ?>&_token=<?= urlencode($csrfToken); ?>" class="btn btn-primary btn-sm" title="Download to PC">
                                        <i class="fa-solid fa-download"></i> Download
                                    </a>
                                    <form action="" method="POST" style="margin:0;" onsubmit="return confirm('Delete backup <?= Security::e($bf['name']); ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken; ?>">
                                        <input type="hidden" name="action" value="delete_backup">
                                        <input type="hidden" name="filename" value="<?= Security::e($bf['name']); ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>
