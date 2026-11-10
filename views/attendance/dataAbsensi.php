<?php

require_once __DIR__ . '/../../src/common/Auth.php';
require_once __DIR__ . '/../../src/common/DBConnection.php';
require_once __DIR__ . '/../../src/service/AttendanceService.php';

Auth::requireHr();

$user = Auth::user();
$attendanceService = new AttendanceService();

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$filters = array();
if (!empty($_GET['date'])) {
    $filters['date'] = $_GET['date'];
}
if (!empty($_GET['start_date'])) {
    $filters['start_date'] = $_GET['start_date'];
}
if (!empty($_GET['end_date'])) {
    $filters['end_date'] = $_GET['end_date'];
}
if (!empty($_GET['keyword'])) {
    $filters['keyword'] = trim($_GET['keyword']);
}

$result = $attendanceService->all($filters, $page, 15);
$totalPages = max(1, (int)ceil($result['total'] / $result['per_page']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Absensi - Human Resource Management System</title>
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
                <div class="title_left"><h3>Data Absensi Karyawan</h3></div>
            </div>
            <div class="clearfix"></div>

            <div class="row">
                <div class="col-md-12 col-sm-12 col-xs-12">
                    <div class="x_panel">
                        <div class="x_title">
                            <h2>Filter</h2>
                            <div class="clearfix"></div>
                        </div>
                        <div class="x_content">
                            <form class="form-inline" method="get" action="">
                                <div class="form-group">
                                    <label for="keyword">Nama / NIK</label>
                                    <input type="text" class="form-control" id="keyword" name="keyword"
                                           value="<?= htmlspecialchars(isset($filters['keyword']) ? $filters['keyword'] : '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="date">Tanggal</label>
                                    <input type="date" class="form-control" id="date" name="date"
                                           value="<?= htmlspecialchars(isset($filters['date']) ? $filters['date'] : '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="start_date">Dari</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date"
                                           value="<?= htmlspecialchars(isset($filters['start_date']) ? $filters['start_date'] : '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="end_date">Sampai</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date"
                                           value="<?= htmlspecialchars(isset($filters['end_date']) ? $filters['end_date'] : '') ?>">
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Terapkan</button>
                                <a href="dataAbsensi.php" class="btn btn-default">Reset</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12 col-sm-12 col-xs-12">
                    <div class="x_panel">
                        <div class="x_title">
                            <h2>Daftar Absensi <small>total <?= (int)$result['total'] ?> data</small></h2>
                            <div class="clearfix"></div>
                        </div>
                        <div class="x_content">
                            <?php if (empty($result['items'])) { ?>
                                <p class="text-muted">Tidak ada data absensi untuk filter yang dipilih.</p>
                            <?php } else { ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                        <tr>
                                            <th>No.</th>
                                            <th>NIK</th>
                                            <th>Nama Karyawan</th>
                                            <th>Departemen</th>
                                            <th>Tanggal</th>
                                            <th>Jam</th>
                                            <th>Lokasi</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php $no = ($result['page'] - 1) * $result['per_page']; ?>
                                        <?php foreach ($result['items'] as $row) { $no++; ?>
                                            <tr>
                                                <td><?= $no ?></td>
                                                <td><?= htmlspecialchars($row['employee_nik'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name'])) ?></td>
                                                <td><?= htmlspecialchars(($row['department_name'] ?: '-') . ' / ' . ($row['designation_name'] ?: '-')) ?></td>
                                                <td><?= htmlspecialchars($row['date']) ?></td>
                                                <td><?= htmlspecialchars(substr($row['clock_in'], 0, 5)) ?></td>
                                                <td><?= htmlspecialchars($row['latitude'] . ', ' . $row['longitude']) ?></td>
                                                <td><span class="label label-success"><?= htmlspecialchars($row['status']) ?></span></td>
                                                <td>
                                                    <button type="button" class="btn btn-xs btn-info btn-detail"
                                                            data-id="<?= (int)$row['id'] ?>"
                                                            data-name="<?= htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name'])) ?>"
                                                            data-date="<?= htmlspecialchars($row['date']) ?>"
                                                            data-time="<?= htmlspecialchars(substr($row['clock_in'], 0, 5)) ?>"
                                                            data-loc="<?= htmlspecialchars($row['latitude'] . ', ' . $row['longitude']) ?>"
                                                            data-acc="<?= htmlspecialchars($row['gps_accuracy'] !== null ? round($row['gps_accuracy']) . ' m' : '-') ?>"
                                                            data-status="<?= htmlspecialchars($row['status']) ?>"
                                                            data-has-photo="<?= $row['selfie_path'] ? '1' : '0' ?>">
                                                        Detail
                                                    </button>
                                                </td>
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
        </div>

        <!-- Modal detail -->
        <div class="modal fade" id="detailModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                        <h4 class="modal-title">Detail Absensi</h4>
                    </div>
                    <div class="modal-body">
                        <p><strong>Karyawan:</strong> <span id="dName"></span></p>
                        <p><strong>Tanggal:</strong> <span id="dDate"></span> <strong>Jam:</strong> <span id="dTime"></span></p>
                        <p><strong>Lokasi:</strong> <span id="dLoc"></span></p>
                        <p><strong>Akurasi GPS:</strong> <span id="dAcc"></span></p>
                        <p><strong>Status:</strong> <span id="dStatus"></span></p>
                        <div id="dPhotoWrap">
                            <p><strong>Selfie:</strong></p>
                            <img id="dPhoto" style="max-width:100%;" alt="Selfie absensi">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>

        <?php include '../partPage/footer.html' ?>
    </div>
</div>

<script src="../../resource/js/jquery.min.js"></script>
<script src="../../resource/js/bootstrap.min.js"></script>
<script src="../../resource/js/fastclick.js"></script>
<script src="../../resource/js/nprogress.js"></script>
<script src="../../resource/js/custom.min.js"></script>
<script>
$(function () {
    $('.btn-detail').on('click', function () {
        var $btn = $(this);
        $('#dName').text($btn.data('name'));
        $('#dDate').text($btn.data('date'));
        $('#dTime').text($btn.data('time'));
        $('#dLoc').text($btn.data('loc'));
        $('#dAcc').text($btn.data('acc'));
        $('#dStatus').text($btn.data('status'));
        if ($btn.data('has-photo') === '1') {
            $('#dPhotoWrap').show();
            $('#dPhoto').attr('src', '../../src/api/selfie.php?id=' + $btn.data('id'));
        } else {
            $('#dPhotoWrap').hide();
            $('#dPhoto').attr('src', '');
        }
        $('#detailModal').modal('show');
    });
});
</script>
</body>
</html>
