<?php

require_once __DIR__ . '/../../src/common/Auth.php';
require_once __DIR__ . '/../../src/common/DBConnection.php';
require_once __DIR__ . '/../../src/service/LeaveService.php';

Auth::requireLogin();

$user = Auth::user();
$isHr = Auth::isHr();
$leaveService = new LeaveService();

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$noEmployee = false;

if ($isHr) {
    $filters = array();
    if (in_array($statusFilter, array('pending', 'approved', 'rejected'), true)) {
        $filters['status'] = $statusFilter;
    }
    if ($keyword !== '') {
        $filters['keyword'] = $keyword;
    }
    $result = $leaveService->all($filters, $page, 15);
    $title = 'Riwayat Cuti Seluruh Karyawan';
} else {
    if (!$user['employee_id']) {
        // Akun tanpa data karyawan: tampilkan pesan, jangan melempar ke halaman login.
        $noEmployee = true;
        $result = array('items' => array(), 'total' => 0, 'per_page' => 15);
        $title = 'Riwayat Cuti Saya';
    } else {
        $result = $leaveService->mine($user['employee_id'], $page, 15);
        $title = 'Riwayat Cuti Saya';
    }
}

$totalPages = max(1, (int)ceil($result['total'] / $result['per_page']));

function leaveBadge($status)
{
    if ($status === 'approved') {
        return '<span class="label label-success">Disetujui</span>';
    }
    if ($status === 'rejected') {
        return '<span class="label label-danger">Ditolak</span>';
    }
    return '<span class="label label-warning">Menunggu Persetujuan</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Riwayat Cuti - Human Resource Management System</title>
    <link href="../../resource/css/bootstrap.css" rel="stylesheet">
    <link href="../../resource/css/font-awesome.css" rel="stylesheet">
    <link href="../../resource/css/nprogress.css" rel="stylesheet">
    <link href="../../resource/css/custom.css" rel="stylesheet">
</head>
<body class="nav-md">
<div class="container body">
    <div class="main_container">
        <?php include '../partPage/sideAndTopBarMenu.html' ?>

        <div class="right_col" role="main">
            <div class="page-title">
                <div class="title_left"><h3><?= htmlspecialchars($title) ?></h3></div>
            </div>
            <div class="clearfix"></div>

            <?php if ($noEmployee) { ?>
                <div class="alert alert-info" role="alert">
                    <i class="fa fa-info-circle"></i>
                    Akun HR ini belum terhubung ke data karyawan, sehingga riwayat cuti pribadi tidak tersedia.
                </div>
            <?php } else { ?>

            <div class="row">
                <div class="col-md-12 col-sm-12 col-xs-12">
                    <div class="x_panel">
                        <div class="x_title"><h2>Filter</h2><div class="clearfix"></div></div>
                        <div class="x_content">
                            <form class="form-inline" method="get" action="">
                                <?php if ($isHr) { ?>
                                    <div class="form-group">
                                        <label for="keyword">Nama Karyawan</label>
                                        <input type="text" class="form-control" id="keyword" name="keyword"
                                               value="<?= htmlspecialchars($keyword) ?>">
                                    </div>
                                <?php } ?>
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select id="status" name="status" class="form-control">
                                        <option value="">Semua</option>
                                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Menunggu Persetujuan</option>
                                        <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Disetujui</option>
                                        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Terapkan</button>
                                <a href="riwayatCuti.php" class="btn btn-default">Reset</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12 col-sm-12 col-xs-12">
                    <div class="x_panel">
                        <div class="x_title">
                            <h2>Daftar Pengajuan <small>total <?= (int)$result['total'] ?> data</small></h2>
                            <div class="clearfix"></div>
                        </div>
                        <div class="x_content">
                            <?php if (empty($result['items'])) { ?>
                                <p class="text-muted">Belum ada data pengajuan cuti.</p>
                            <?php } else { ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                        <tr>
                                            <th>Karyawan</th>
                                            <th>Periode</th>
                                            <th>Hari</th>
                                            <th>Alasan</th>
                                            <th>Pengganti</th>
                                            <th>Status</th>
                                            <th>Keputusan</th>
                                            <th>Alasan Penolakan</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($result['items'] as $row) { ?>
                                            <tr>
                                                <td><?= htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name'])) ?></td>
                                                <td><?= htmlspecialchars($row['start_date'] . ' s/d ' . $row['end_date']) ?></td>
                                                <td><?= htmlspecialchars($row['total_days']) ?></td>
                                                <td><?= htmlspecialchars(mb_strimwidth($row['reason'], 0, 50, '...')) ?></td>
                                                <td><?= htmlspecialchars($row['replacement_first_name'] ? trim($row['replacement_first_name'] . ' ' . $row['replacement_last_name']) : '-') ?></td>
                                                <td><?= leaveBadge($row['status']) ?></td>
                                                <td>
                                                    <?php if ($row['reviewed_at']) { ?>
                                                        <?= htmlspecialchars(($row['reviewer_first_name'] ? trim($row['reviewer_first_name'] . ' ' . $row['reviewer_last_name']) : '-') . ' — ' . substr($row['reviewed_at'], 0, 16)) ?>
                                                    <?php } else { ?>-<?php } ?>
                                                </td>
                                                <td><?= htmlspecialchars($row['rejection_reason'] ?: '-') ?></td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($totalPages > 1) { ?>
                                    <ul class="pagination">
                                        <?php
                                        $qs = $_GET;
                                        for ($p = 1; $p <= $totalPages; $p++) {
                                            $qs['page'] = $p;
                                            $link = '?' . http_build_query($qs);
                                        ?>
                                            <li class="<?= $p === $page ? 'active' : '' ?>">
                                                <a href="<?= htmlspecialchars($link) ?>"><?= $p ?></a>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>

        <?php include '../partPage/footer.html' ?>
    </div>
</div>

<script src="../../resource/js/jquery.min.js"></script>
<script src="../../resource/js/bootstrap.min.js"></script>
<script src="../../resource/js/fastclick.js"></script>
<script src="../../resource/js/nprogress.js"></script>
<script src="../../resource/js/custom.min.js"></script>
</body>
</html>
