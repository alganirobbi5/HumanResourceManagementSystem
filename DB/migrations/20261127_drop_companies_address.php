<?php

// Migration idempoten: hapus kolom address dari tabel companies.
require_once __DIR__ . '/../src/common/DBConnection.php';

$pdo = DBConnection::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$exists = $pdo->query("SHOW COLUMNS FROM companies LIKE 'address'");
if ($exists && $exists->fetch()) {
    $pdo->exec('ALTER TABLE companies DROP COLUMN address');
    echo "Kolom companies.address berhasil dihapus.\n";
} else {
    echo "Kolom companies.address sudah tidak ada.\n";
}
