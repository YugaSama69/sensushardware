<?php

require_once __DIR__ . '/../config/app.php';

$rooms = fetch_all($pdo, 'SELECT nama_ruangan FROM ruangan ORDER BY nama_ruangan ASC');
$baseAbsoluteUrl = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pendataan Inventaris Komputer Rumah Sakit</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(url('assets/css/style.css')); ?>" rel="stylesheet">
</head>
<body class="client-scan-body">
    <main class="container py-4 py-lg-5 client-scan-shell">
        <div class="client-scan-card client-scan-card-simple shadow-lg">
            <section class="client-scan-simple-head text-center">
                <img src="<?= e(url('assets/images/siintel-login-logo.png')); ?>" alt="Logo SIINTEL" class="client-scan-logo">
                <span class="client-scan-form-badge">Inventaris IT Rumah Sakit</span>
                <h1 class="client-scan-simple-title">Pendataan Inventaris Komputer RSUD Welas Asih</h1>
                <!-- <p class="client-scan-simple-lead">Isi data singkat di bawah ini, lalu jalankan launcher untuk membaca spesifikasi komputer dan mengirimkannya langsung ke sistem inventaris.</p> -->
            </section>

            <form method="get" action="<?= e(url('pendataan/download_agent.php')); ?>" class="scan-form">
                <input type="hidden" name="server" value="<?= e($baseAbsoluteUrl); ?>">

                <div class="scan-section-card">
                    <div class="scan-section-title">Informasi Pendataan</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Lokasi Ruangan</label>
                            <input type="text" name="ruangan" class="form-control form-control-lg" list="daftar-ruangan" placeholder="Cari atau ketik ruangan" autocomplete="off" required>
                            <datalist id="daftar-ruangan">
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?= e($room['nama_ruangan']); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <div class="form-text">Pilih dari data ruangan yang ada atau ketik manual bila belum tersedia.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tahun Inventaris</label>
                            <input type="number" name="tahun_inventaris" class="form-control form-control-lg" min="2000" max="<?= date('Y') + 1; ?>" value="<?= date('Y'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nama User</label>
                            <input type="text" name="nama_user" class="form-control form-control-lg" placeholder="Nama pengguna komputer" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kondisi Komputer</label>
                            <select name="kondisi" class="form-select form-select-lg" required>
                                <option value="Baik">Baik</option>
                                <option value="Rusak">Rusak</option>
                                <option value="Perbaikan">Perbaikan</option>
                            </select>
                            <div class="form-text">Pilih kondisi komputer saat ini sebelum pendataan dijalankan.</div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-success btn-lg w-100 scan-button">
                    Pendataan Komputer Ini
                </button>
            </form>

            <div class="scan-notice mt-4">
                <strong>Catatan keamanan browser:</strong> Tombol ini akan mengunduh launcher pendataan. Jalankan file `pendataan-komputer-rs.bat` agar data komputer terkirim ke server.
            </div>

            <div class="scan-steps scan-steps-simple mt-4">
                <div class="scan-step-item">
                    <span class="scan-step-number">1</span>
                    <div>Isi ruangan, tahun inventaris, nama user, dan kondisi komputer.</div>
                </div>
                <div class="scan-step-item">
                    <span class="scan-step-number">2</span>
                    <div>Klik tombol pendataan untuk mengunduh launcher.</div>
                </div>
                <div class="scan-step-item">
                    <span class="scan-step-number">3</span>
                    <div>Jalankan launcher, lalu data masuk ke dashboard admin.</div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
