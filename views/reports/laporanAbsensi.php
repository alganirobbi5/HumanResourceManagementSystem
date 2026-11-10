<?php

require_once __DIR__ . '/../../src/common/Auth.php';
require_once __DIR__ . '/../../src/common/Config.php';

Auth::requireHr();

$maxRange = Config::MAX_EXPORT_RANGE_DAYS;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Absensi - Human Resource Management System</title>
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
                <div class="title_left"><h3>Laporan Absensi</h3></div>
            </div>
            <div class="clearfix"></div>

            <div id="alertArea"></div>

            <div class="row">
                <div class="col-md-6 col-sm-6 col-xs-12">
                    <div class="x_panel">
                        <div class="x_title">
                            <h2>Export Laporan Absensi <small>.xlsx</small></h2>
                            <div class="clearfix"></div>
                        </div>
                        <div class="x_content">
                            <p>Pilih rentang tanggal lalu tekan <strong>Export Excel</strong>. File memiliki satu sheet untuk setiap tanggal pada rentang tersebut (maksimal <strong><?= (int)$maxRange ?> hari</strong>).</p>

                            <form id="exportForm" class="form-horizontal form-label-left">
                                <div class="item form-group">
                                    <label class="control-label col-md-3 col-sm-3 col-xs-12" for="start_date">Tanggal Mulai <span class="required">*</span></label>
                                    <div class="col-md-8 col-sm-8 col-xs-12">
                                        <input type="date" id="start_date" name="start_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="item form-group">
                                    <label class="control-label col-md-3 col-sm-3 col-xs-12" for="end_date">Tanggal Akhir <span class="required">*</span></label>
                                    <div class="col-md-8 col-sm-8 col-xs-12">
                                        <input type="date" id="end_date" name="end_date" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="col-md-8 col-md-offset-3">
                                        <p class="text-muted" id="periodInfo">Periode belum dipilih.</p>
                                        <button type="submit" id="btnExport" class="btn btn-success" disabled>
                                            <i class="fa fa-file-excel-o"></i> Export Excel
                                        </button>
                                        <span id="loading" style="display:none;"><i class="fa fa-spinner fa-spin"></i> Memproses file...</span>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-sm-6 col-xs-12">
                    <div class="x_panel">
                        <div class="x_title"><h2>Keterangan</h2><div class="clearfix"></div></div>
                        <div class="x_content">
                            <ul class="list-unstyled">
                                <li><i class="fa fa-check text-success"></i> Format file: <code>.xlsx</code></li>
                                <li><i class="fa fa-check text-success"></i> Satu sheet per tanggal kalender, termasuk tanggal tanpa absensi.</li>
                                <li><i class="fa fa-check text-success"></i> Nama sheet mengikuti tanggal (contoh: <code>2026-11-10</code>).</li>
                                <li><i class="fa fa-check text-success"></i> Kolom: No, ID Karyawan, Nama, Departemen/Jabatan, Tanggal, Jam, Latitude, Longitude, Status.</li>
                                <li><i class="fa fa-check text-success"></i> Data diambil dari database sesuai timezone perusahaan.</li>
                            </ul>
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
    var maxRange = <?= (int)$maxRange ?>;
    var today = new Date().toISOString().slice(0, 10);

    function showAlert(type, message) {
        $('#alertArea').html(
            '<div class="alert alert-' + type + ' alert-dismissible fade in" role="alert">' +
            '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>' +
            message + '</div>'
        );
    }

    function validate() {
        var s = $('#start_date').val(), e = $('#end_date').val();
        $('#periodInfo').text('Periode belum dipilih.');
        $('#btnExport').prop('disabled', true);

        if (!s || !e) return false;
        if (e < s) {
            showAlert('danger', 'Tanggal akhir tidak boleh lebih awal daripada tanggal mulai.');
            return false;
        }
        var days = Math.round((new Date(e + 'T00:00:00') - new Date(s + 'T00:00:00')) / 86400000) + 1;
        if (days > maxRange) {
            showAlert('danger', 'Rentang tanggal maksimal ' + maxRange + ' hari (pilih saat ini: ' + days + ' hari).');
            return false;
        }
        $('#alertArea').empty();
        $('#periodInfo').text('Periode: ' + s + ' s/d ' + e + ' (' + hari(days) + ')');
        $('#btnExport').prop('disabled', false);
        return true;
    }

    function hari(n) { return n + ' hari'; }

    $('#start_date, #end_date').on('change', validate);

    $('#exportForm').on('submit', function (ev) {
        ev.preventDefault();
        if (!validate()) return;

        var s = $('#start_date').val(), e = $('#end_date').val();
        var url = '../../src/api/export-attendance.php?start_date=' + encodeURIComponent(s) + '&end_date=' + encodeURIComponent(e);

        $('#btnExport').prop('disabled', true);
        $('#loading').show();

        fetch(url, {credentials: 'same-origin'})
            .then(function (res) {
                var contentType = res.headers.get('content-type') || '';
                if (!res.ok || contentType.indexOf('json') !== -1) {
                    return res.json().then(function (data) {
                        throw new Error((data && data.message) || 'Export gagal (HTTP ' + res.status + ').');
                    });
                }
                return res.blob();
            })
            .then(function (blob) {
                var a = document.createElement('a');
                var url = URL.createObjectURL(blob);
                a.href = url;
                a.download = 'laporan-absensi-' + s + '_sampai_' + e + '.xlsx';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                showAlert('success', 'File Excel berhasil dibuat dan sedang diunduh.');
            })
            .catch(function (err) {
                if (String(err.message).indexOf('login') !== -1 || String(err.message).indexOf('izin') !== -1) {
                    showAlert('danger', err.message + ' Silakan login kembali.');
                } else {
                    showAlert('danger', err.message || 'Export gagal, silakan coba lagi.');
                }
            })
            .then(function () {
                $('#loading').hide();
                $('#btnExport').prop('disabled', false);
            });
    });
})();
</script>
</body>
</html>
