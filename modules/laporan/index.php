<?php

require_once dirname(__DIR__, 2) . '/config/app.php';

$pageTitle = 'Laporan';
$filters = normalize_report_filters($_GET);
$rows = get_report_rows($pdo, $filters);
$rooms = fetch_all($pdo, 'SELECT * FROM ruangan ORDER BY nama_ruangan ASC');
$items = fetch_all($pdo, 'SELECT id, nama_barang FROM barang ORDER BY nama_barang ASC');
$queryString = build_query_string($filters);

require_once BASE_PATH . '/includes/layout_top.php';
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 pt-4">
        <h5 class="mb-1">Filter Laporan</h5>
        <p class="text-muted mb-0">Saring histori berdasarkan tanggal, periode, ruangan, barang, dan jenis transaksi.</p>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Tanggal</label>
                <input type="date" name="tanggal" class="form-control" value="<?= e($filters['tanggal']); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Bulan</label>
                <select name="bulan" class="form-select">
                    <option value="">Semua</option>
                    <?php for ($month = 1; $month <= 12; $month++): ?>
                        <option value="<?= $month; ?>" <?= (int) $filters['bulan'] === $month ? 'selected' : ''; ?>><?= date('F', mktime(0, 0, 0, $month, 1)); ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tahun</label>
                <select name="tahun" class="form-select">
                    <option value="">Semua</option>
                    <?php for ($year = (int) date('Y'); $year >= 2020; $year--): ?>
                        <option value="<?= $year; ?>" <?= (int) $filters['tahun'] === $year ? 'selected' : ''; ?>><?= $year; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Nama Ruangan</label>
                <select name="ruangan_id" class="form-select">
                    <option value="">Semua</option>
                    <?php foreach ($rooms as $room): ?>
                        <option value="<?= $room['id']; ?>" <?= (int) $filters['ruangan_id'] === (int) $room['id'] ? 'selected' : ''; ?>><?= e($room['nama_ruangan']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Nama Barang</label>
                <select name="barang_id" class="form-select">
                    <option value="">Semua</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?= $item['id']; ?>" <?= (int) $filters['barang_id'] === (int) $item['id'] ? 'selected' : ''; ?>><?= e($item['nama_barang']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Jenis</label>
                <select name="tipe_transaksi" class="form-select">
                    <option value="">Semua</option>
                    <option value="masuk" <?= $filters['tipe_transaksi'] === 'masuk' ? 'selected' : ''; ?>>Masuk</option>
                    <option value="keluar" <?= $filters['tipe_transaksi'] === 'keluar' ? 'selected' : ''; ?>>Keluar</option>
                </select>
            </div>
            <div class="col-12 d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                <a href="<?= e(url('modules/laporan/index.php')); ?>" class="btn btn-light">Reset</a>
                <a href="<?= e(url('modules/laporan/export_excel.php' . ($queryString ? '?' . $queryString : ''))); ?>" class="btn btn-success">Export Excel</a>
                <a href="<?= e(url('modules/laporan/export_pdf.php' . ($queryString ? '?' . $queryString : ''))); ?>" class="btn btn-danger">Export PDF</a>
                <a href="<?= e(url('modules/laporan/print.php' . ($queryString ? '?' . $queryString : ''))); ?>" class="btn btn-outline-dark" target="_blank">Print</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-1">Hasil Laporan</h5>
            <p class="text-muted mb-0"><?= count($rows); ?> data ditemukan.</p>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle datatable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Barang</th>
                        <th>Ruangan</th>
                        <th>Transaksi</th>
                        <th>Qty</th>
                        <th>Stok</th>
                        <th>Pengguna</th>
                        <th>Tujuan</th>
                        <th>Hari</th>
                        <th>Tanggal</th>
                        <th>Jam</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1; ?></td>
                            <td>
                                <strong><?= e($row['nama_barang']); ?></strong>
                                <div class="small text-muted"><?= e($row['kode_barang']); ?></div>
                            </td>
                            <td><?= e($row['nama_ruangan'] ?: '-'); ?></td>
                            <td><span class="badge text-bg-<?= transaction_badge($row['tipe_transaksi']); ?>"><?= ucfirst(e($row['tipe_transaksi'])); ?></span></td>
                            <td><?= format_number($row['qty']); ?></td>
                            <td><?= format_number($row['stok_sebelum']); ?> -> <?= format_number($row['stok_sesudah']); ?></td>
                            <td><?= e($row['nama_pengguna']); ?></td>
                            <td><?= e($row['tujuan'] ?: '-'); ?></td>
                            <td><?= e(day_name_id($row['tanggal'])); ?></td>
                            <td><?= e(format_date_id($row['tanggal'])); ?></td>
                            <td><?= e(format_time_id($row['jam'])); ?></td>
                            <td><?= e($row['keterangan'] ?: '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/layout_bottom.php'; ?>
