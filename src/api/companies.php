<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../service/CompanyProfileService.php';

Auth::requireApiSuperAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = DBConnection::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$profiles = new CompanyProfileService($pdo);

try {
    if ($method === 'GET') {
        $rows = $pdo->query(
            'SELECT c.id, c.company_code, c.address, c.status, c.created_at,
                    COALESCE(NULLIF(cp.nama_perusahaan, ""), c.company_name) AS company_name
             FROM companies c
             LEFT JOIN company_profile cp ON cp.company_id = c.id
             ORDER BY c.id'
        )->fetchAll(PDO::FETCH_ASSOC);
        Json::ok('OK', array('data' => $rows));
    }

    if ($method === 'POST') {
        $data = Json::readRequest();
        $code = isset($data['company_code']) ? trim((string)$data['company_code']) : '';
        $name = isset($data['company_name']) ? trim((string)$data['company_name']) : '';
        $address = isset($data['address']) ? trim((string)$data['address']) : '';
        if ($code === '' || $name === '') {
            Json::error('company_code dan company_name wajib diisi', 422);
        }
        $exists = $pdo->prepare('SELECT id FROM companies WHERE company_code = ? LIMIT 1');
        $exists->execute(array($code));
        if ($exists->fetch()) {
            Json::error('company_code sudah digunakan', 409);
        }
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO companies (company_code, company_name, address, status) VALUES (?, ?, ?, 1)'
            );
            $stmt->execute(array($code, $name, $address));
            $id = (int)$pdo->lastInsertId();
            $profiles->upsert($id, $name, $code, $address);
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        $rowStmt = $pdo->prepare(
            'SELECT c.id, c.company_code, c.address, c.status, c.created_at,
                    COALESCE(NULLIF(cp.nama_perusahaan, ""), c.company_name) AS company_name
             FROM companies c
             LEFT JOIN company_profile cp ON cp.company_id = c.id
             WHERE c.id = ? LIMIT 1'
        );
        $rowStmt->execute(array($id));
        Json::ok('Perusahaan berhasil ditambahkan', array('data' => $rowStmt->fetch(PDO::FETCH_ASSOC)));
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        $data = Json::readRequest();
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $statusInput = isset($data['status']) ? $data['status']
            : (isset($data['action']) ? $data['action'] : null);
        $status = null;
        if (is_numeric($statusInput)) {
            $status = (int)$statusInput === 1 ? 1 : 0;
        } elseif (is_string($statusInput)) {
            $action = strtolower(trim($statusInput));
            if ($action === 'aktif' || $action === 'active' || $action === 'activate') {
                $status = 1;
            } elseif ($action === 'nonaktif' || $action === 'inactive' || $action === 'deactivate') {
                $status = 0;
            }
        }
        if ($id <= 0 || $status === null) {
            Json::error('id dan status wajib diisi', 422);
        }
        $stmt = $pdo->prepare('UPDATE companies SET status = ? WHERE id = ?');
        $stmt->execute(array($status, $id));
        if ($stmt->rowCount() === 0) {
            Json::error('Perusahaan tidak ditemukan', 404);
        }
        $rowStmt = $pdo->prepare(
            'SELECT c.id, c.company_code, c.address, c.status, c.created_at,
                    COALESCE(NULLIF(cp.nama_perusahaan, ""), c.company_name) AS company_name
             FROM companies c
             LEFT JOIN company_profile cp ON cp.company_id = c.id
             WHERE c.id = ? LIMIT 1'
        );
        $rowStmt->execute(array($id));
        Json::ok('Status perusahaan berhasil diperbarui', array('data' => $rowStmt->fetch(PDO::FETCH_ASSOC)));
    }
} catch (PDOException $e) {
    Json::error('Gagal mengelola data perusahaan', 500);
}

Json::error('Method tidak diizinkan', 405);
