<?php

require_once dirname(__DIR__, 2) . '/config/app.php';

$pageTitle = 'Dashboard';

$totalBarang = (int) fetch_scalar($pdo, 'SELECT COUNT(*) FROM barang');
$totalRuangan = (int) fetch_scalar($pdo, 'SELECT COUNT(*) FROM ruangan');
$stokTotal = (int) fetch_scalar($pdo, 'SELECT COALESCE(SUM(qty), 0) FROM barang');
$barangMasukBulanIni = (int) fetch_scalar(
    $pdo,
    'SELECT COALESCE(SUM(qty), 0) FROM histori_barang WHERE tipe_transaksi = "masuk" AND MONTH(tanggal) = MONTH(CURRENT_DATE()) AND YEAR(tanggal) = YEAR(CURRENT_DATE())'
);
$barangKeluarBulanIni = (int) fetch_scalar(
    $pdo,
    'SELECT COALESCE(SUM(qty), 0) FROM histori_barang WHERE tipe_transaksi = "keluar" AND MONTH(tanggal) = MONTH(CURRENT_DATE()) AND YEAR(tanggal) = YEAR(CURRENT_DATE())'
);
$recentHistory = fetch_all(
    $pdo,
    'SELECT h.*, b.nama_barang, COALESCE(h.ruangan_nama, r.nama_ruangan) AS nama_ruangan_transaksi
     FROM histori_barang h
     INNER JOIN barang b ON b.id = h.barang_id
     LEFT JOIN ruangan r ON r.id = h.ruangan_id
     ORDER BY h.tanggal DESC, h.jam DESC, h.id DESC
     LIMIT 8'
);

require_once BASE_PATH . '/includes/layout_top.php';
?>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="metric-card card-sky">
            <span>Total Barang</span>
            <h3><?= format_number($totalBarang); ?></h3>
            <small><?= format_number($stokTotal); ?> unit stok aktif</small>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="metric-card card-amber">
            <span>Total Ruangan</span>
            <h3><?= format_number($totalRuangan); ?></h3>
            <small>Ruangan terdaftar</small>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="metric-card card-emerald">
            <span>Barang Masuk Bulan Ini</span>
            <h3><?= format_number($barangMasukBulanIni); ?></h3>
            <small>Akumulasi transaksi masuk</small>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="metric-card card-rose">
            <span>Barang Keluar Bulan Ini</span>
            <h3><?= format_number($barangKeluarBulanIni); ?></h3>
            <small>Akumulasi transaksi keluar</small>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h5 class="mb-1">Riwayat Transaksi Terbaru</h5>
                <p class="text-muted mb-0">Pantau mutasi stok terakhir dari semua ruangan.</p>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Ruangan</th>
                                <th>Jenis</th>
                                <th>Qty</th>
                                <th>Waktu</th>
                                <th>Pengguna</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$recentHistory): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Belum ada transaksi.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($recentHistory as $row): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($row['nama_barang']); ?></strong>
                                        <div class="small text-muted"><?= e($row['keterangan'] ?: '-'); ?></div>
                                    </td>
                                    <td><?= e($row['nama_ruangan_transaksi'] ?: '-'); ?></td>
                                    <td><span class="badge text-bg-<?= transaction_badge($row['tipe_transaksi']); ?>"><?= ucfirst(e($row['tipe_transaksi'])); ?></span></td>
                                    <td><?= format_number($row['qty']); ?></td>
                                    <td><?= e(day_name_id($row['tanggal'])); ?>, <?= e(format_date_id($row['tanggal'])); ?> <?= e(format_time_id($row['jam'])); ?></td>
                                    <td><?= e($row['nama_pengguna']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h5 class="mb-1">Quick Access</h5>
                <p class="text-muted mb-0">Jalur cepat untuk pekerjaan harian admin.</p>
            </div>
            <div class="card-body d-grid gap-3">
                <a class="quick-link-card" href="<?= e(url('modules/barang/index.php')); ?>">
                    <strong>Kelola Data Barang</strong>
                    <span>Tambah, edit, dan cek kondisi inventaris.</span>
                </a>
                <a class="quick-link-card" href="<?= e(url('modules/transaksi/masuk.php')); ?>">
                    <strong>Input Barang Masuk</strong>
                    <span>Tambahkan stok dan simpan histori otomatis.</span>
                </a>
                <a class="quick-link-card" href="<?= e(url('modules/transaksi/keluar.php')); ?>">
                    <strong>Input Barang Keluar</strong>
                    <span>Kurangi stok dengan validasi stok minimum.</span>
                </a>
                <a class="quick-link-card" href="<?= e(url('modules/laporan/index.php')); ?>">
                    <strong>Buka Laporan</strong>
                    <span>Filter, print, export Excel, dan export PDF.</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/layout_bottom.php'; ?>
