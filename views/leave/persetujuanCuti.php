<?php

require_once __DIR__ . '/../../src/common/Auth.php';
require_once __DIR__ . '/../../src/common/DBConnection.php';
require_once __DIR__ . '/../../src/service/LeaveService.php';

Auth::requireHr();

$user = Auth::user();
$leaveService = new LeaveService();

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$result = $leaveService->pending($page, 10);
$totalPages = max(1, (int)ceil($result['total'] / $result['per_page']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Persetujuan Cuti - Human Resource Management System</title>
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
                <div class="title_left"><h3>Persetujuan Cuti</h3></div>
            </div>
            <div class="clearfix"></div>

            <div id="alertArea"></div>

            <div class="row">
                <div class="col-md-12 col-sm-12 col-xs-12">
                    <div class="x_panel">
                        <div class="x_title">
                            <h2>Pengajuan Menunggu Persetujuan <small>total <?= (int)$result['total'] ?> pengajuan</small></h2>
                            <div class="clearfix"></div>
                        </div>
                        <div class="x_content">
                            <?php if (empty($result['items'])) { ?>
                                <p class="text-muted">Tidak ada pengajuan cuti yang menunggu persetujuan.</p>
                            <?php } else { ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                        <tr>
                                            <th>Nama Karyawan</th>
                                            <th>Departemen/Jabatan</th>
                                            <th>Tanggal Mulai</th>
                                            <th>Tanggal Selesai</th>
                                            <th>Jumlah Hari</th>
                                            <th>Alasan Cuti</th>
                                            <th>Nama Pengganti</th>
                                            <th>Tanggal Pengajuan</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($result['items'] as $row) { ?>
                                            <tr>
                                                <td><?= htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name'])) ?></td>
                                                <td><?= htmlspecialchars(($row['department_name'] ?: '-') . ' / ' . ($row['designation_name'] ?: '-')) ?></td>
                                                <td><?= htmlspecialchars($row['start_date']) ?></td>
                                                <td><?= htmlspecialchars($row['end_date']) ?></td>
                                                <td><?= htmlspecialchars($row['total_days']) ?></td>
                                                <td><?= htmlspecialchars(mb_strimwidth($row['reason'], 0, 60, '...')) ?></td>
                                                <td><?= htmlspecialchars($row['replacement_first_name'] ? trim($row['replacement_first_name'] . ' ' . $row['replacement_last_name']) : '-') ?></td>
                                                <td><?= htmlspecialchars(substr($row['submitted_at'], 0, 16)) ?></td>
                                                <td><span class="label label-warning">Menunggu Persetujuan</span></td>
                                                <td>
                                                    <button type="button" class="btn btn-xs btn-primary btn-review"
                                                            data-id="<?= (int)$row['id'] ?>"
                                                            data-name="<?= htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name'])) ?>"
                                                            data-nik="<?= htmlspecialchars($row['employee_nik'] ?: '-') ?>"
                                                            data-dept="<?= htmlspecialchars(($row['department_name'] ?: '-') . ' / ' . ($row['designation_name'] ?: '-')) ?>"
                                                            data-reason="<?= htmlspecialchars($row['reason']) ?>"
                                                            data-start="<?= htmlspecialchars($row['start_date']) ?>"
                                                            data-end="<?= htmlspecialchars($row['end_date']) ?>"
                                                            data-days="<?= htmlspecialchars($row['total_days']) ?>"
                                                            data-replacement="<?= htmlspecialchars($row['replacement_first_name'] ? trim($row['replacement_first_name'] . ' ' . $row['replacement_last_name']) : '-') ?>"
                                                            data-submitted="<?= htmlspecialchars($row['submitted_at']) ?>">
                                                        Tinjau
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

        <!-- Modal detail / keputusan -->
        <div class="modal fade" id="reviewModal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">×</span></button>
                        <h4 class="modal-title">Detail Pengajuan Cuti</h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="mId">
                        <p><strong>Karyawan:</strong> <span id="mName"></span> (NIK: <span id="mNik"></span>)</p>
                        <p><strong>Departemen/Jabatan:</strong> <span id="mDept"></span></p>
                        <p><strong>Periode:</strong> <span id="mPeriod"></span> — <span id="mDays"></span> hari</p>
                        <p><strong>Nama Pengganti:</strong> <span id="mReplacement"></span></p>
                        <p><strong>Tanggal Pengajuan:</strong> <span id="mSubmitted"></span></p>
                        <p><strong>Alasan Cuti:</strong></p>
                        <p id="mReason" class="well well-sm"></p>

                        <div class="form-group" id="rejectWrap" style="display:none;">
                            <label for="mRejectReason">Alasan Penolakan <span class="text-danger">*</span></label>
                            <textarea id="mRejectReason" class="form-control" rows="3" placeholder="Wajib diisi saat menolak"></textarea>
                            <span class="text-danger" id="errReject"></span>
                        </div>

                        <div class="alert alert-warning" id="confirmInfo" style="display:none;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-danger" id="btnReject"><i class="fa fa-times"></i> Tolak</button>
                        <button type="button" class="btn btn-success" id="btnApprove"><i class="fa fa-check"></i> Setujui</button>
                        <button type="button" class="btn btn-danger" id="btnConfirmReject" style="display:none;">Konfirmasi Tolak</button>
                        <button type="button" class="btn btn-success" id="btnConfirmApprove" style="display:none;">Konfirmasi Setujui</button>
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
(function () {
    function showAlert(type, message) {
        $('#alertArea').html(
            '<div class="alert alert-' + type + ' alert-dismissible fade in" role="alert">' +
            '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>' +
            message + '</div>'
        );
    }

    function resetButtons() {
        $('#btnApprove, #btnReject').show();
        $('#btnConfirmApprove, #btnConfirmReject, #rejectWrap, #confirmInfo').hide();
        $('#mRejectReason').val('');
        $('#errReject').text('');
    }

    $('.btn-review').on('click', function () {
        var $b = $(this);
        resetButtons();
        $('#mId').val($b.data('id'));
        $('#mName').text($b.data('name'));
        $('#mNik').text($b.data('nik'));
        $('#mDept').text($b.data('dept'));
        $('#mPeriod').text($b.data('start') + ' s/d ' + $b.data('end'));
        $('#mDays').text($b.data('days'));
        $('#mReplacement').text($b.data('replacement'));
        $('#mSubmitted').text($b.data('submitted'));
        $('#mReason').text($b.data('reason'));
        $('#reviewModal').modal('show');
    });

    $('#btnApprove').on('click', function () {
        $('#confirmInfo').text('Setujui pengajuan cuti ini? Kuota cuti karyawan akan dikurangi.').show();
        $('#btnApprove, #btnReject').hide();
        $('#btnConfirmApprove').show();
    });

    $('#btnReject').on('click', function () {
        $('#rejectWrap').show();
        $('#confirmInfo').text('Tolak pengajuan cuti ini? Kuota tidak akan berkurang.').show();
        $('#btnApprove, #btnReject').hide();
        $('#btnConfirmReject').show();
    });

    function decide(action) {
        var payload = {action: action, id: parseInt($('#mId').val(), 10)};
        if (action === 'reject') {
            payload.rejection_reason = $('#mRejectReason').val().trim();
            if (!payload.rejection_reason) {
                $('#errReject').text('Alasan penolakan wajib diisi.');
                return;
            }
        }
        $.ajax({
            url: '../../src/api/leave-requests.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function (res) {
                $('#reviewModal').modal('hide');
                if (res && res.success) {
                    showAlert('success', res.message);
                    setTimeout(function () { window.location.reload(); }, 900);
                } else {
                    showAlert('danger', (res && res.message) || 'Gagal memproses pengajuan.');
                }
            },
            error: function (xhr) {
                var res = xhr.responseJSON;
                showAlert('danger', (res && res.message) || 'Koneksi gagal, silakan coba lagi.');
            }
        });
    }

    $('#btnConfirmApprove').on('click', function () { decide('approve'); });
    $('#btnConfirmReject').on('click', function () { decide('reject'); });
})();
</script>
</body>
</html>
