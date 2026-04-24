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
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="client-scan-card shadow-lg">
                    <div class="text-center mb-4">
                        <span class="badge rounded-pill text-bg-success px-3 py-2 mb-3">Inventaris IT Rumah Sakit</span>
                        <h1 class="fw-bold mb-3">Pendataan Inventaris Komputer Rumah Sakit</h1>
                        <p class="text-muted mb-0">Isi ruangan, tahun inventaris, dan nama user, lalu jalankan agent untuk membaca spesifikasi komputer ini dan mengirimkannya ke server inventaris.</p>
                    </div>

                    <form method="get" action="<?= e(url('pendataan/download_agent.php')); ?>" class="scan-form">
                        <input type="hidden" name="server" value="<?= e($baseAbsoluteUrl); ?>">
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Lokasi Ruangan</label>
                                <select name="ruangan" class="form-select form-select-lg" required>
                                    <option value="">Pilih ruangan</option>
                                    <?php foreach ($rooms as $room): ?>
                                        <option value="<?= e($room['nama_ruangan']); ?>"><?= e($room['nama_ruangan']); ?></option>
                                    <?php endforeach; ?>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tahun Inventaris</label>
                                <input type="number" name="tahun_inventaris" class="form-control form-control-lg" min="2000" max="<?= date('Y') + 1; ?>" value="<?= date('Y'); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Nama User</label>
                                <input type="text" name="nama_user" class="form-control form-control-lg" placeholder="Nama pengguna komputer" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success btn-lg w-100 scan-button">
                            Pendataan Komputer Ini
                        </button>
                    </form>

                    <div class="alert alert-info mt-4 mb-0">
                        <strong>Catatan keamanan browser:</strong> browser modern tidak mengizinkan halaman web menjalankan file BAT/PowerShell otomatis tanpa izin. Tombol ini akan mengunduh launcher pendataan, lalu jalankan file tersebut di komputer client.
                    </div>

                    <div class="scan-steps mt-4">
                        <div><strong>1.</strong> Klik tombol hijau.</div>
                        <div><strong>2.</strong> Buka file `pendataan-komputer-rs.bat` yang terunduh.</div>
                        <div><strong>3.</strong> Jika sukses, data langsung masuk ke dashboard admin.</div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
