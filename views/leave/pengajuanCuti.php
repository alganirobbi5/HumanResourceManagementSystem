<?php

require_once __DIR__ . '/../../src/common/Auth.php';
require_once __DIR__ . '/../../src/common/DBConnection.php';
require_once __DIR__ . '/../../src/common/Config.php';
require_once __DIR__ . '/../../src/service/LeaveService.php';

Auth::requireLogin();

$user = Auth::user();

// Akun tanpa data karyawan: tampilkan pesan, jangan melempar ke halaman login.
if (!$user['employee_id']) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Pengajuan Cuti - Human Resource Management System</title>
        <link href="../../resource/css/bootstrap.css" rel="stylesheet">
        <link href="../../resource/css/font-awesome.css" rel="stylesheet">
        <link href="../../resource/css/custom.css" rel="stylesheet">
    </head>
    <body class="nav-md">
    <div class="container body">
        <div class="main_container">
            <?php include '../partPage/sideAndTopBarMenu.html' ?>
            <div class="right_col" role="main">
                <div class="page-title"><div class="title_left"><h3>Pengajuan Cuti</h3></div></div>
                <div class="clearfix"></div>
                <div class="alert alert-info" role="alert">
                    <i class="fa fa-info-circle"></i>
                    Akun HR ini belum terhubung ke data karyawan, sehingga pengajuan cuti tidak dapat dilakukan.
                </div>
            </div>
            <?php include '../partPage/footer.html' ?>
        </div>
    </div>
    <script src="../../resource/js/jquery.min.js"></script>
    <script src="../../resource/js/bootstrap.min.js"></script>
    <script src="../../resource/js/custom.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

$leaveService = new LeaveService();
$balance = $leaveService->getBalance($user['employee_id']);
$recent = $leaveService->mine($user['employee_id'], 1, 5);

$dayRuleLabel = Config::LEAVE_DAY_COUNT_RULE === 'kerja' ? 'hari kerja (Senin-Jumat)' : 'hari kalender';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengajuan Cuti - Human Resource Management System</title>
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
                <div class="title_left"><h3>Pengajuan Cuti</h3></div>
            </div>
            <div class="clearfix"></div>

            <div id="alertArea"></div>

            <div class="row">
                <div class="col-md-4 col-sm-6 col-xs-12">
                    <div class="x_panel">
                        <div class="x_title"><h2>Sisa Kuota Cuti</h2><div class="clearfix"></div></div>
                        <div class="x_content">
                            <h3 class="text-primary"><?= htmlspecialchars($balance['remaining_days']) ?> hari</h3>
                            <p>Jatah: <?= htmlspecialchars($balance['entitled_days']) ?> hari</p>
                            <p>Telah digunakan: <?= htmlspecialchars($balance['used_days']) ?> hari</p>
                            <p>Tahun: <strong><?= (int)$balance['entitlement_year'] ?></strong></p>
                            <p class="text-muted">Perhitungan jumlah hari menggunakan <?= htmlspecialchars($dayRuleLabel) ?>.</p>
                        </div>
                    </div>
                </div>

                <div class="col-md-8 col-sm-6 col-xs-12">
                    <div class="x_panel">
                        <div class="x_title"><h2>Formulir Pengajuan Cuti</h2><div class="clearfix"></div></div>
                        <div class="x_content">
                            <form id="leaveForm" class="form-horizontal form-label-left" novalidate>
                                <div class="item form-group">
                                    <label class="control-label col-md-3 col-sm-3 col-xs-12" for="reason">Alasan Cuti <span class="required">*</span></label>
                                    <div class="col-md-8 col-sm-8 col-xs-12">
                                        <textarea id="reason" name="reason" class="form-control" rows="3" required placeholder="Tuliskan alasan cuti"></textarea>
                                        <span class="text-danger" id="err-reason"></span>
                                    </div>
                                </div>

                                <div class="item form-group">
                                    <label class="control-label col-md-3 col-sm-3 col-xs-12" for="start_date">Tanggal Mulai <span class="required">*</span></label>
                                    <div class="col-md-8 col-sm-8 col-xs-12">
                                        <input type="date" id="start_date" name="start_date" class="form-control" required>
                                        <span class="text-danger" id="err-start_date"></span>
                                    </div>
                                </div>

                                <div class="item form-group">
                                    <label class="control-label col-md-3 col-sm-3 col-xs-12" for="end_date">Tanggal Selesai <span class="required">*</span></label>
                                    <div class="col-md-8 col-sm-8 col-xs-12">
                                        <input type="date" id="end_date" name="end_date" class="form-control" required>
                                        <span class="text-danger" id="err-end_date"></span>
                                    </div>
                                </div>

                                <div class="item form-group">
                                    <label class="control-label col-md-3 col-sm-3 col-xs-12" for="total_days">Jumlah Hari</label>
                                    <div class="col-md-8 col-sm-8 col-xs-12">
                                        <input type="number" id="total_days" name="total_days" class="form-control" readonly>
                                        <span class="help-block">Dihitung otomatis dari tanggal (<?= htmlspecialchars($dayRuleLabel) ?>). Nilai final dihitung ulang oleh server.</span>
                                    </div>
                                </div>

                                <div class="item form-group">
                                    <label class="control-label col-md-3 col-sm-3 col-xs-12" for="replacement_employee_id">Nama Pengganti <span class="required">*</span></label>
                                    <div class="col-md-8 col-sm-8 col-xs-12">
                                        <select id="replacement_employee_id" name="replacement_employee_id" class="form-control" required>
                                            <option value="">-- Pilih karyawan pengganti --</option>
                                        </select>
                                        <span class="text-danger" id="err-replacement_employee_id"></span>
                                    </div>
                                </div>

                                <div class="ln_solid"></div>
                                <div class="form-group">
                                    <div class="col-md-8 col-md-offset-3">
                                        <button type="submit" id="btnSubmit" class="btn btn-success"><i class="fa fa-paper-plane"></i> Ajukan Cuti</button>
                                        <a href="../leave/riwayatCuti.php" class="btn btn-default">Lihat Riwayat Lengkap</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12 col-sm-12 col-xs-12">
                    <div class="x_panel">
                        <div class="x_title">
                            <h2>Status Pengajuan Terakhir <small><?= (int)$recent['total'] ?> pengajuan</small></h2>
                            <div class="clearfix"></div>
                        </div>
                        <div class="x_content">
                            <?php if (empty($recent['items'])) { ?>
                                <p class="text-muted">Belum ada pengajuan cuti.</p>
                            <?php } else { ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                        <tr>
                                            <th>Tanggal Pengajuan</th>
                                            <th>Periode</th>
                                            <th>Jumlah Hari</th>
                                            <th>Status</th>
                                            <th>Alasan Penolakan</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($recent['items'] as $row) {
                                            $badge = $row['status'] === 'approved' ? 'success' : ($row['status'] === 'rejected' ? 'danger' : 'warning');
                                            $label = $row['status'] === 'approved' ? 'Disetujui' : ($row['status'] === 'rejected' ? 'Ditolak' : 'Menunggu Persetujuan');
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars(substr($row['submitted_at'], 0, 10)) ?></td>
                                                <td><?= htmlspecialchars($row['start_date'] . ' s/d ' . $row['end_date']) ?></td>
                                                <td><?= htmlspecialchars($row['total_days']) ?></td>
                                                <td><span class="label label-<?= $badge ?>"><?= $label ?></span></td>
                                                <td><?= htmlspecialchars($row['rejection_reason'] ?: '-') ?></td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php } ?>
                        </div>
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
    var dayRule = <?= json_encode(Config::LEAVE_DAY_COUNT_RULE) ?>;
    var allowPastStart = <?= Config::ALLOW_PAST_START_DATE ? 'true' : 'false' ?>;

    function showAlert(type, message) {
        $('#alertArea').html(
            '<div class="alert alert-' + type + ' alert-dismissible fade in" role="alert">' +
            '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>' +
            message + '</div>'
        );
    }

    function clearErrors() {
        $('.text-danger').text('');
    }

    function calculateDays() {
        var s = $('#start_date').val(), e = $('#end_date').val();
        $('#total_days').val('');
        if (!s || !e) return 0;
        var ds = new Date(s + 'T00:00:00'), de = new Date(e + 'T00:00:00');
        if (isNaN(ds) || isNaN(de) || de < ds) return 0;
        if (dayRule === 'kerja') {
            var days = 0, cur = new Date(ds.getTime());
            while (cur <= de) {
                var dow = cur.getDay();
                if (dow !== 0 && dow !== 6) days++;
                cur.setDate(cur.getDate() + 1);
            }
            $('#total_days').val(days);
            return days;
        }
        var diff = Math.round((de - ds) / 86400000) + 1;
        $('#total_days').val(diff);
        return diff;
    }

    $('#start_date, #end_date').on('change', calculateDays);

    // Muat daftar karyawan pengganti
    $.getJSON('../../src/api/employees.php', function (res) {
        if (res && res.success && res.data) {
            res.data.forEach(function (emp) {
                $('#replacement_employee_id').append(
                    $('<option>').val(emp.id).text(emp.name + ' (' + emp.nik + ')')
                );
            });
        }
    });

    $('#leaveForm').on('submit', function (ev) {
        ev.preventDefault();
        clearErrors();
        $('#alertArea').empty();

        var payload = {
            action: 'create',
            reason: $('#reason').val().trim(),
            start_date: $('#start_date').val(),
            end_date: $('#end_date').val(),
            replacement_employee_id: parseInt($('#replacement_employee_id').val(), 10) || 0
        };

        var valid = true;
        if (!payload.reason) { $('#err-reason').text('Alasan cuti wajib diisi.'); valid = false; }
        if (!payload.start_date) { $('#err-start_date').text('Tanggal mulai wajib dipilih.'); valid = false; }
        if (!payload.end_date) { $('#err-end_date').text('Tanggal selesai wajib dipilih.'); valid = false; }
        if (payload.start_date && payload.end_date && payload.end_date < payload.start_date) {
            $('#err-end_date').text('Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
            valid = false;
        }
        if (!payload.replacement_employee_id) {
            $('#err-replacement_employee_id').text('Nama pengganti wajib dipilih.');
            valid = false;
        }
        if (payload.start_date && !allowPastStart && payload.start_date < new Date().toISOString().slice(0, 10)) {
            $('#err-start_date').text('Tanggal mulai tidak boleh di masa lampau.');
            valid = false;
        }
        if (!valid) return;

        var btn = $('#btnSubmit').prop('disabled', true);
        $.ajax({
            url: '../../src/api/leave-requests.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            success: function (res) {
                if (res && res.success) {
                    showAlert('success', res.message + ' <a href="../leave/riwayatCuti.php">Lihat riwayat</a>');
                    $('#leaveForm')[0].reset();
                    $('#total_days').val('');
                } else {
                    showAlert('danger', (res && res.message) || 'Gagal mengirim pengajuan.');
                    btn.prop('disabled', false);
                }
            },
            error: function (xhr) {
                var res = xhr.responseJSON;
                showAlert('danger', (res && res.message) || 'Koneksi gagal, silakan coba lagi.');
                btn.prop('disabled', false);
            }
        });
    });
})();
</script>
</body>
</html>
