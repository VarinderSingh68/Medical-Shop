<?php
declare(strict_types=1);

require_once __DIR__ . '/../../backend/config/config.php';
require_once __DIR__ . '/../../backend/config/database.php';
require_once __DIR__ . '/../../backend/core/Security.php';
require_once __DIR__ . '/../../backend/models/Medicine.php';

use App\Core\Security;
use App\Models\Medicine;

Security::applyHeaders();
header('Content-Type: application/json; charset=UTF-8');

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$db = DatabaseConnection::getInstance();
$medModel = new Medicine($db);

$medicines = $medModel->getAll(null, $query, true, 6);
$results = [];

foreach ($medicines as $m) {
    $results[] = [
        'id'           => (int)$m['id'],
        'name'         => $m['name'],
        'generic'      => $m['generic_name'],
        'dosage_form'  => $m['dosage_form'],
        'strength'     => $m['strength'],
        'category'     => $m['category_name'],
        'price'        => format_price($m['selling_price']),
        'raw_price'    => (float)$m['selling_price'],
        'rx'           => (bool)$m['requires_prescription'],
        'url'          => app_url('medicine-detail.php?id=' . (int)$m['id'])
    ];
}

echo json_encode(['results' => $results]);
