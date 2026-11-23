<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';

$diagnosticsFlag = getenv('HRIS_DIAGNOSTICS_ENABLED');
if ($diagnosticsFlag !== false && strtolower(trim($diagnosticsFlag)) === '0') {
    http_response_code(404);
    echo json_encode(array('success' => false, 'message' => 'Not found'));
    exit;
}

$user = Auth::requireApiHr();

try {
    $config = DBConnection::config();

    $pdo = DBConnection::pdo();
    $pdo->query('SELECT 1');

    DBConnection::enableSafeErrors();
    $legacy = new DBConnection();
    if (!($legacy->getPdo() instanceof PDO)) {
        throw new RuntimeException('Legacy connection unavailable');
    }

    Json::ok('Database connection successful', array(
        'checked_at' => date('c'),
        'database' => array(
            'host' => $config['host'],
            'port' => $config['port'],
            'name' => $config['db'],
        ),
    ));
} catch (Exception $e) {
    Json::error('Database connection failed', 500);
}
