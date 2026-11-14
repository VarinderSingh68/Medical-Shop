<?php
declare(strict_types=1);

/**
 * Secure Authenticated Prescription Document Viewer
 * HIPAA & Medical Privacy Compliance: Only authenticated staff can view patient prescriptions.
 * Direct public web access to storage/prescriptions/ is blocked.
 */

require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../../backend/core/Security.php';
require_once __DIR__ . '/../../backend/core/Auth.php';

use App\Core\Auth;
use App\Core\Security;

// Enforce admin/pharmacist authentication
Auth::checkAdmin();
Security::applyHeaders();

$rawFile = $_GET['file'] ?? '';
$filename = basename($rawFile);

// Strict filename validation: must match generated rx format
if (!preg_match('/^rx_\d{8}_\d{6}_[a-f0-9]{16}\.(jpg|jpeg|png|webp|pdf)$/i', $filename)) {
    http_response_code(400);
    die('Invalid prescription identifier format.');
}

$prescriptionsDir = realpath(STORAGE_PATH . DIRECTORY_SEPARATOR . 'prescriptions');
if (!$prescriptionsDir) {
    http_response_code(404);
    die('Prescriptions repository unavailable.');
}

$targetFile = realpath($prescriptionsDir . DIRECTORY_SEPARATOR . $filename);

// Path traversal validation: ensure resolved path is strictly inside the prescriptions folder
if ($targetFile === false || strpos($targetFile, $prescriptionsDir . DIRECTORY_SEPARATOR) !== 0 || !file_exists($targetFile)) {
    http_response_code(404);
    die('Prescription file not found.');
}

$ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
$mimeMap = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'pdf'  => 'application/pdf',
];

$contentType = $mimeMap[$ext] ?? 'application/octet-stream';

// Set secure streaming headers
header('Content-Type: ' . $contentType);
header('Content-Length: ' . filesize($targetFile));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($ext === 'pdf') {
    header('Content-Disposition: inline; filename="' . $filename . '"');
} else {
    header('Content-Disposition: inline; filename="' . $filename . '"');
}

readfile($targetFile);
exit;
