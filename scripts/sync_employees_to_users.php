<?php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Script ini hanya boleh dijalankan via CLI.\n";
    exit(1);
}

require_once __DIR__ . '/../src/common/DBConnection.php';
require_once __DIR__ . '/../src/service/EmployeeAccountService.php';

$pdo = DBConnection::pdo();
$service = new EmployeeAccountService($pdo);

$rows = $pdo->query(
    'SELECT e.id, e.user_name FROM employees e
     LEFT JOIN users u ON u.employee_id = e.id
     WHERE u.id IS NULL
     ORDER BY e.id'
)->fetchAll(PDO::FETCH_ASSOC);

$created = 0;
$skipped = 0;
$failed = 0;
foreach ($rows as $row) {
    try {
        if ($service->syncUser((int)$row['id'])) {
            $created++;
            echo 'CREATED id=' . (int)$row['id'] . ' username=' . $row['user_name'] . PHP_EOL;
        } else {
            $skipped++;
            echo 'SKIPPED id=' . (int)$row['id'] . ' username=' . $row['user_name'] . PHP_EOL;
        }
    } catch (Exception $e) {
        $failed++;
        echo 'FAILED id=' . (int)$row['id'] . ' username=' . $row['user_name'] . PHP_EOL;
    }
}

echo "DONE total=" . count($rows) . " created=" . $created . " skipped=" . $skipped . " failed=" . $failed . PHP_EOL;
