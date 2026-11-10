<?php

require_once __DIR__ . '/../../src/common/Auth.php';
require_once __DIR__ . '/../../src/common/Config.php';

Auth::requireLogin();

$user = Auth::user();

// Admin lama (tabel admins) belum punya data karyawan: tampilkan pesan, jangan login ulang.
if (!$user['employee_id']) {
    include_once __DIR__ . '/../../src/common/DBConnection.php';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Absensi - Human Resource Management System</title>
        <link href="../../resource/css/bootstrap.css" rel="stylesheet">
        <link href="../../resource/css/font-awesome.css" rel="stylesheet">
        <link href="../../resource/css/custom.css" rel="stylesheet">
    </head>
    <body class="nav-md">
    <div class="container body">
        <div class="main_container">
            <?php include '../partPage/sideAndTopBarMenu.html' ?>
            <div class="right_col" role="main">
                <div class="page-title"><div class="title_left"><h3>Absensi</h3></div></div>
                <div class="clearfix"></div>
                <div class="alert alert-info" role="alert">
                    <i class="fa fa-info-circle"></i>
                    Akun HR ini belum terhubung ke data karyawan, sehingga absensi tidak dapat dilakukan.
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

include_once __DIR__ . '/../../src/common/DBConnection.php';
include_once __DIR__ . '/../../src/service/AttendanceService.php';

$conn = new DBConnection();
$attendanceService = new AttendanceService();

$today = (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
$todayRecord = $conn->selectOne(
    'SELECT * FROM attendances WHERE employee_id = ? AND `date` = ? LIMIT 1',
    array((int)$user['employee_id'], $today)
);

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$history = $attendanceService->mine($user['employee_id'], $page, 10);
$totalPages = max(1, (int)ceil($history['total'] / $history['per_page']));

$successMessage = isset($_GET['status']) && $_GET['status'] === 'success' ? 'Absensi berhasil disimpan.' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Absensi - Human Resource Management System</title>
    <link href="../../resource/css/bootstrap.css" rel="stylesheet">
    <link href="../../resource/css/font-awesome.css" rel="stylesheet">
    <link href="../../resource/css/nprogress.css" rel="stylesheet">
    <link href="../../resource/css/custom.css" rel="stylesheet">
    <style>
        #cameraPreview { width: 100%; max-width: 420px; background: #000; min-height: 240px; }
        #photoPreview { max-width: 420px; width: 100%; border: 1px solid #ddd; }
        .perm-badge { margin-right: 5px; }
        .geo-info { font-family: monospace; }
    </style>
</head>
<body class="nav-md">
<div class="container body">
    <div class="main_container">
        <?php include '../partPage/sideAndTopBarMenu.html' ?>

        <div class="right_col" role="main">
            <div class="page-title">
                <div class="title_left"><h3>Absensi</h3></div>
            </div>
            <div class="clearfix"></div>

            <?php if ($successMessage) { ?>
                <div class="alert alert-success alert-dismissible fade in" role="alert">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">×</span></button>
                    <?= htmlspecialchars($successMessage) ?>
                </div>
            <?php } ?>
            <div id="alertArea"></div>

            <div class="row">
                <div class="col-md-4 col-sm-6 col-xs-12">
                    <div class="x_panel">
                        <div class="x_title"><h2>Identitas Karyawan</h2><div class="clearfix"></div></div>
                        <div class="x_content">
                            <p><strong><?= htmlspecialchars($user['display_name']) ?></strong></p>
                            <p>NIK: <?= htmlspecialchars($user['employee_nik'] ?: '-') ?></p>
                            <p>Departemen: <?= htmlspecialchars($user['department_name'] ?: '-') ?></p>
                            <p>Jabatan: <?= htmlspecialchars($user['designation_name'] ?: '-') ?></p>
                            <hr>
                            <p><i class="fa fa-calendar"></i> <span id="currentDate">-</span></p>
                            <p><i class="fa fa-clock-o"></i> <span id="currentTime">-</span> <small>(waktu server)</small></p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 col-sm-6 col-xs-12">
                    <div class="x_panel">
                        <div class="x_title"><h2>Status Izin Perangkat</h2><div class="clearfix"></div></div>
                        <div class="x_content">
                            <p>Status izin hanya diminta saat proses absensi dimulai.</p>
                            <span id="geoBadge" class="label label-default perm-badge">Lokasi: belum diminta</span>
                            <span id="camBadge" class="label label-default perm-badge">Kamera: belum diminta</span>
                            <hr>
                            <p class="geo-info">Latitude: <span id="latValue">-</span></p>
                            <p class="geo-info">Longitude: <span id="lngValue">-</span></p>
                            <p class="geo-info">Akurasi: <span id="accValue">-</span></p>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 col-sm-6 col-xs-12">
                    <div class="x_panel">
                        <div class="x_title"><h2>Absensi Hari Ini</h2><div class="clearfix"></div></div>
                        <div class="x_content">
                            <?php if ($todayRecord) { ?>
                                <span class="label label-success">Sudah absen</span>
                                <p>Jam: <strong><?= htmlspecialchars(substr($todayRecord['clock_in'], 0, 5)) ?></strong></p>
                                <p>Lokasi: <?= htmlspecialchars($todayRecord['latitude']) ?>, <?= htmlspecialchars($todayRecord['longitude']) ?></p>
                                <p>Status: <?= htmlspecialchars($todayRecord['status']) ?></p>
                            <?php } else { ?>
                                <span class="label label-warning">Belum absen</span>
                                <p>Anda belum melakukan absensi hari ini.</p>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 col-sm-6 col-xs-12">
                    <div class="x_panel">
                        <div class="x_title">
                            <h2>Ambil Absensi <small>izinkan lokasi & kamera</small></h2>
                            <div class="clearfix"></div>
                        </div>
                        <div class="x_content">
                            <p id="flowHint">Tekan tombol berikut untuk memulai. Sistem akan meminta izin akses lokasi (GPS) dan kamera.</p>

                            <button type="button" id="btnStart" class="btn btn-primary">
                                <i class="fa fa-map-marker"></i> Mulai Absensi
                            </button>

                            <div id="step2" style="display:none; margin-top:15px;">
                                <h4>Langkah 2: Ambil Foto Selfie</h4>
                                <video id="cameraPreview" autoplay playsinline muted></video>
                                <canvas id="photoCanvas" style="display:none;"></canvas>
                                <div style="margin-top:10px;">
                                    <button type="button" id="btnCapture" class="btn btn-default"><i class="fa fa-camera"></i> Ambil Foto</button>
                                    <button type="button" id="btnRetake" class="btn btn-default" style="display:none;"><i class="fa fa-refresh"></i> Ulangi Foto</button>
                                </div>
                                <div id="photoWrap" style="display:none; margin-top:10px;">
                                    <p>Preview foto yang akan dikirim:</p>
                                    <img id="photoPreview" alt="Preview selfie">
                                </div>
                            </div>

                            <div id="processIndicator" style="display:none; margin-top:10px;">
                                <i class="fa fa-spinner fa-spin"></i> <span id="processText">Memproses...</span>
                            </div>

                            <div style="margin-top:15px;">
                                <button type="button" id="btnSubmit" class="btn btn-success" disabled>
                                    <i class="fa fa-paper-plane"></i> Kirim Absensi
                                </button>
                            </div>
                            <div id="formError" class="text-danger" style="margin-top:10px;"></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-sm-6 col-xs-12">
                    <div class="x_panel">
                        <div class="x_title"><h2>Riwayat Absensi Saya</h2><div class="clearfix"></div></div>
                        <div class="x_content">
                            <?php if (empty($history['items'])) { ?>
                                <p class="text-muted">Belum ada data absensi.</p>
                            <?php } else { ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered">
                                        <thead>
                                        <tr>
                                            <th>Tanggal</th>
                                            <th>Jam</th>
                                            <th>Lokasi</th>
                                            <th>Status</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($history['items'] as $row) { ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['date']) ?></td>
                                                <td><?= htmlspecialchars(substr($row['clock_in'], 0, 5)) ?></td>
                                                <td><?= htmlspecialchars($row['latitude'] . ', ' . $row['longitude']) ?></td>
                                                <td><span class="label label-success"><?= htmlspecialchars($row['status']) ?></span></td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if ($totalPages > 1) { ?>
                                    <ul class="pagination">
                                        <?php for ($p = 1; $p <= $totalPages; $p++) { ?>
                                            <li class="<?= $p === $page ? 'active' : '' ?>">
                                                <a href="?page=<?= $p ?>"><?= $p ?></a>
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
    var geo = null, photoBlob = null, stream = null;
    var maxSelfieBytes = <?= (int)Config::MAX_SELFIE_BYTES ?>;
    var gpsTimeoutSeconds = <?= (int)Config::GPS_TIMEOUT_SECONDS ?>;

    function setBadge(id, text, cls) {
        var el = document.getElementById(id);
        el.className = 'label ' + cls + ' perm-badge';
        el.textContent = text;
    }
    function showError(msg) {
        document.getElementById('formError').innerHTML = '<i class="fa fa-times-circle"></i> ' + msg;
    }
    function clearError() { document.getElementById('formError').innerHTML = ''; }
    function process(text) {
        var el = document.getElementById('processIndicator');
        if (text) {
            document.getElementById('processText').textContent = text;
            el.style.display = 'block';
        } else {
            el.style.display = 'none';
        }
    }
    function refreshSubmitState() {
        var ready = !!geo && !!photoBlob;
        document.getElementById('btnSubmit').disabled = !ready;
    }

    function tickClock() {
        var now = new Date();
        document.getElementById('currentDate').textContent = now.toLocaleDateString('id-ID', {weekday:'long', year:'numeric', month:'long', day:'numeric'});
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('id-ID');
    }
    tickClock();
    setInterval(tickClock, 1000);

    function getLocation() {
        return new Promise(function (resolve, reject) {
            if (!('geolocation' in navigator)) {
                reject({code: 0, message: 'Browser tidak mendukung akses lokasi.'});
                return;
            }
            navigator.geolocation.getCurrentPosition(function (pos) {
                resolve({
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                    accuracy: pos.coords.accuracy
                });
            }, function (err) {
                var msg;
                if (err.code === err.PERMISSION_DENIED) {
                    msg = 'Izin lokasi ditolak. Aktifkan izin lokasi untuk situs ini melalui pengaturan browser (ikon gembok di address bar), lalu coba lagi.';
                } else if (err.code === err.POSITION_UNAVAILABLE) {
                    msg = 'Lokasi tidak dapat diperoleh. Pastikan GPS/perangkat terhubung, lalu coba lagi.';
                } else if (err.code === err.TIMEOUT) {
                    msg = 'Permintaan lokasi waktu habis (timeout). Silakan coba lagi.';
                } else {
                    msg = 'Lokasi tidak dapat diperoleh. Silakan coba lagi.';
                }
                reject({code: err.code, message: msg});
            }, {enableHighAccuracy: true, timeout: gpsTimeoutSeconds * 1000, maximumAge: 0});
        });
    }

    function getCamera() {
        return new Promise(function (resolve, reject) {
            if (!('mediaDevices' in navigator) || !navigator.mediaDevices.getUserMedia) {
                reject('Browser tidak mendukung akses kamera.');
                return;
            }
            navigator.mediaDevices.getUserMedia({video: {facingMode: 'user', width: {ideal: 720}}, audio: false})
                .then(function (s) { stream = s; resolve(s); })
                .catch(function () {
                    reject('Kamera tidak dapat diakses. Pastikan izin kamera diberikan dan tidak dipakai aplikasi lain, lalu coba lagi.');
                });
        });
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
    }

    document.getElementById('btnStart').addEventListener('click', function () {
        clearError();
        this.disabled = true;
        process('Meminta izin lokasi dan kamera...');

        getLocation().then(function (g) {
            geo = g;
            document.getElementById('latValue').textContent = g.latitude.toFixed(7);
            document.getElementById('lngValue').textContent = g.longitude.toFixed(7);
            document.getElementById('accValue').textContent = (g.accuracy != null ? Math.round(g.accuracy) + ' m' : '-');
            setBadge('geoBadge', 'Lokasi: diberikan', 'label-success');
            refreshSubmitState();
            return getCamera();
        }).then(function () {
            setBadge('camBadge', 'Kamera: diberikan', 'label-success');
            process('');
            document.getElementById('step2').style.display = 'block';
            document.getElementById('cameraPreview').style.display = 'block';
            document.getElementById('photoWrap').style.display = 'none';
            document.getElementById('btnCapture').style.display = 'inline-block';
            document.getElementById('btnRetake').style.display = 'none';
            document.getElementById('btnStart').disabled = false;
            document.getElementById('btnStart').innerHTML = '<i class="fa fa-refresh"></i> Ulangi Ambil Lokasi';
        }).catch(function (err) {
            process('');
            document.getElementById('btnStart').disabled = false;
            var msg = (err && err.message) ? err.message : err;
            if (err && err.code === 1) {
                setBadge('geoBadge', 'Lokasi: ditolak', 'label-danger');
            }
            showError(msg);
        });
    });

    document.getElementById('btnCapture').addEventListener('click', function () {
        clearError();
        var video = document.getElementById('cameraPreview');
        var canvas = document.getElementById('photoCanvas');
        if (!video.videoWidth) {
            showError('Kamera belum siap, tunggu sebentar lalu coba lagi.');
            return;
        }
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        canvas.toBlob(function (blob) {
            if (!blob) {
                showError('Gagal mengambil foto, silakan coba lagi.');
                return;
            }
            if (blob.size > maxSelfieBytes) {
                showError('Ukuran foto terlalu besar, maksimal ' + (maxSelfieBytes / 1048576) + ' MB.');
                return;
            }
            photoBlob = blob;
            var url = URL.createObjectURL(blob);
            document.getElementById('photoPreview').src = url;
            document.getElementById('photoWrap').style.display = 'block';
            video.style.display = 'none';
            this.style.display = 'none';
            document.getElementById('btnRetake').style.display = 'inline-block';
            refreshSubmitState();
        }.bind(this), 'image/jpeg', 0.85);
    });

    document.getElementById('btnRetake').addEventListener('click', function () {
        photoBlob = null;
        document.getElementById('photoPreview').src = '';
        document.getElementById('photoWrap').style.display = 'none';
        document.getElementById('cameraPreview').style.display = 'block';
        document.getElementById('btnCapture').style.display = 'inline-block';
        this.style.display = 'none';
        refreshSubmitState();
        clearError();
    });

    document.getElementById('btnSubmit').addEventListener('click', function () {
        clearError();
        if (!geo) {
            showError('Lokasi belum diperoleh. Tekan Mulai Absensi terlebih dahulu.');
            return;
        }
        if (!photoBlob) {
            showError('Foto selfie belum diambil.');
            return;
        }
        var btn = this;
        btn.disabled = true;
        process('Mengirim absensi...');

        var fd = new FormData();
        fd.append('latitude', geo.latitude);
        fd.append('longitude', geo.longitude);
        if (geo.accuracy != null) fd.append('gps_accuracy', geo.accuracy);
        fd.append('request_id', 'web-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10));
        fd.append('selfie', photoBlob, 'selfie.jpg');

        fetch('../../src/api/attendances.php', {method: 'POST', body: fd, credentials: 'same-origin'})
            .then(function (res) { return res.json().then(function (data) { return {ok: res.ok, data: data}; }); })
            .then(function (r) {
                process('');
                if (r.data && r.data.success) {
                    stopCamera();
                    window.location.href = 'absensiSaya.php?status=success';
                    return;
                }
                showError((r.data && r.data.message) ? r.data.message : 'Gagal mengirim absensi.');
                btn.disabled = false;
                refreshSubmitState();
            })
            .catch(function () {
                process('');
                showError('Koneksi terputus saat mengirim absensi. Periksa jaringan lalu coba lagi (pengiriman ganda dicegah).');
                btn.disabled = false;
                refreshSubmitState();
            });
    });

    window.addEventListener('beforeunload', stopCamera);
})();
</script>
</body>
</html>
