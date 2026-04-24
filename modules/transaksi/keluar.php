<?php

require_once dirname(__DIR__, 2) . '/config/app.php';

$pageTitle = 'Barang Keluar';
$errors = [];

if (is_post()) {
    verify_csrf();

    $barangId = (int) ($_POST['barang_id'] ?? 0);
    $qtyKeluar = (int) ($_POST['qty'] ?? 0);
    $namaPengguna = trim($_POST['nama_pengguna'] ?? '');
    $tujuan = trim($_POST['tujuan'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($barangId <= 0) {
        $errors[] = 'Barang wajib dipilih.';
    }

    if ($qtyKeluar <= 0) {
        $errors[] = 'Jumlah keluar harus lebih dari 0.';
    }

    if ($namaPengguna === '') {
        $errors[] = 'Nama pengambil wajib diisi.';
    }

    if ($tujuan === '') {
        $errors[] = 'Tujuan penggunaan wajib diisi.';
    }

    $item = null;
    if (!$errors) {
        $item = fetch_one($pdo, 'SELECT * FROM barang WHERE id = :id LIMIT 1', ['id' => $barangId]);
        if (!$item) {
            $errors[] = 'Data barang tidak ditemukan.';
        }
    }

    if (!$errors && $item) {
        $stokSebelum = (int) $item['qty'];

        if ($qtyKeluar > $stokSebelum) {
            $errors[] = 'Stok tidak mencukupi untuk transaksi keluar.';
        } else {
            $stokSesudah = $stokSebelum - $qtyKeluar;
            $tanggal = date('Y-m-d');
            $jam = date('H:i:s');

            try {
                $pdo->beginTransaction();

                $update = $pdo->prepare('UPDATE barang SET qty = :qty WHERE id = :id');
                $update->execute([
                    'qty' => $stokSesudah,
                    'id' => $barangId,
                ]);

                $history = $pdo->prepare('
                    INSERT INTO histori_barang (barang_id, tipe_transaksi, qty, stok_sebelum, stok_sesudah, nama_pengguna, tujuan, keterangan, tanggal, jam, created_at)
                    VALUES (:barang_id, :tipe_transaksi, :qty, :stok_sebelum, :stok_sesudah, :nama_pengguna, :tujuan, :keterangan, :tanggal, :jam, NOW())
                ');
                $history->execute([
                    'barang_id' => $barangId,
                    'tipe_transaksi' => 'keluar',
                    'qty' => $qtyKeluar,
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $stokSesudah,
                    'nama_pengguna' => $namaPengguna,
                    'tujuan' => $tujuan,
                    'keterangan' => $keterangan,
                    'tanggal' => $tanggal,
                    'jam' => $jam,
                ]);

                $pdo->commit();
                set_flash('success', 'Stok barang berhasil dikurangi.');
                redirect('modules/transaksi/keluar.php');
            } catch (Throwable $throwable) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Gagal menyimpan transaksi barang keluar.';
            }
        }
    }
}

$items = fetch_all(
    $pdo,
    'SELECT b.id, b.nama_barang, b.qty, r.nama_ruangan
     FROM barang b
     LEFT JOIN ruangan r ON r.id = b.ruangan_id
     ORDER BY b.nama_barang ASC'
);
$recentEntries = fetch_all(
    $pdo,
    'SELECT h.*, b.nama_barang
     FROM histori_barang h
     INNER JOIN barang b ON b.id = h.barang_id
     WHERE h.tipe_transaksi = "keluar"
     ORDER BY h.tanggal DESC, h.jam DESC, h.id DESC
     LIMIT 10'
);

require_once BASE_PATH . '/includes/layout_top.php';
?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4">
                <h5 class="mb-1">Form Barang Keluar</h5>
                <p class="text-muted mb-0">Kurangi stok barang dengan validasi stok otomatis.</p>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <?= csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label">Pilih Barang</label>
                        <select name="barang_id" class="form-select" required>
                            <option value="">Pilih barang</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?= $item['id']; ?>"><?= e($item['nama_barang']); ?> - <?= e($item['nama_ruangan'] ?: '-'); ?> (stok: <?= e((string) $item['qty']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah Keluar</label>
                        <input type="number" name="qty" class="form-control" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal</label>
                        <input type="text" class="form-control" value="<?= e(format_date_id(date('Y-m-d'))); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jam</label>
                        <input type="text" class="form-control" value="<?= e(date('H:i')); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Pengambil</label>
                        <input type="text" name="nama_pengguna" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tujuan Penggunaan</label>
                        <input type="text" name="tujuan" class="form-control" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-danger w-100 mt-4">Simpan Barang Keluar</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-4">
                <h5 class="mb-1">Riwayat Barang Keluar</h5>
                <p class="text-muted mb-0">10 transaksi keluar terbaru.</p>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Qty</th>
                                <th>Stok</th>
                                <th>Pengambil</th>
                                <th>Tujuan</th>
                                <th>Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$recentEntries): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">Belum ada transaksi barang keluar.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($recentEntries as $entry): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($entry['nama_barang']); ?></strong>
                                        <div class="small text-muted"><?= e($entry['keterangan'] ?: '-'); ?></div>
                                    </td>
                                    <td><?= format_number($entry['qty']); ?></td>
                                    <td><?= format_number($entry['stok_sebelum']); ?> -> <?= format_number($entry['stok_sesudah']); ?></td>
                                    <td><?= e($entry['nama_pengguna']); ?></td>
                                    <td><?= e($entry['tujuan'] ?: '-'); ?></td>
                                    <td><?= e(format_date_id($entry['tanggal'])); ?> <?= e(format_time_id($entry['jam'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/layout_bottom.php'; ?>
