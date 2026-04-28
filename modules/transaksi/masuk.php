<?php

require_once dirname(__DIR__, 2) . '/config/app.php';

$pageTitle = 'Barang Masuk';
$errors = [];

if (is_post()) {
    verify_csrf();

    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        $ruanganId = (int) ($_POST['ruangan_id'] ?? 0);
        $barangId = (int) ($_POST['barang_id'] ?? 0);
        $qtyTambah = (int) ($_POST['qty'] ?? 0);
        $namaPengguna = trim($_POST['nama_pengguna'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');
        $tanggal = trim($_POST['tanggal'] ?? date('Y-m-d'));
        $jam = trim($_POST['jam'] ?? date('H:i'));

        if ($ruanganId <= 0) {
            $errors[] = 'Ruangan wajib dipilih.';
        }

        if ($barangId <= 0) {
            $errors[] = 'Barang wajib dipilih.';
        }

        if ($qtyTambah <= 0) {
            $errors[] = 'Jumlah tambah harus lebih dari 0.';
        }

        if ($namaPengguna === '') {
            $errors[] = 'Nama petugas wajib diisi.';
        }

        if ($tanggal === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $errors[] = 'Tanggal wajib diisi dengan format yang valid.';
        }

        if ($jam === '' || !preg_match('/^\d{2}:\d{2}$/', $jam)) {
            $errors[] = 'Jam wajib diisi dengan format yang valid.';
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
            $stokSesudah = $stokSebelum + $qtyTambah;
            $room = fetch_one($pdo, 'SELECT id, nama_ruangan FROM ruangan WHERE id = :id LIMIT 1', ['id' => $ruanganId]);

            if (!$room) {
                $errors[] = 'Ruangan tidak ditemukan.';
            }
        }

        if (!$errors && $item) {
            $room = fetch_one($pdo, 'SELECT id, nama_ruangan FROM ruangan WHERE id = :id LIMIT 1', ['id' => $ruanganId]);

            try {
                $pdo->beginTransaction();

                $update = $pdo->prepare('UPDATE barang SET qty = :qty WHERE id = :id');
                $update->execute([
                    'qty' => $stokSesudah,
                    'id' => $barangId,
                ]);

                $history = $pdo->prepare('
                    INSERT INTO histori_barang (barang_id, ruangan_id, ruangan_nama, tipe_transaksi, qty, stok_sebelum, stok_sesudah, nama_pengguna, tujuan, keterangan, tanggal, jam, created_at)
                    VALUES (:barang_id, :ruangan_id, :ruangan_nama, :tipe_transaksi, :qty, :stok_sebelum, :stok_sesudah, :nama_pengguna, :tujuan, :keterangan, :tanggal, :jam, NOW())
                ');
                $history->execute([
                    'barang_id' => $barangId,
                    'ruangan_id' => $room['id'],
                    'ruangan_nama' => $room['nama_ruangan'],
                    'tipe_transaksi' => 'masuk',
                    'qty' => $qtyTambah,
                    'stok_sebelum' => $stokSebelum,
                    'stok_sesudah' => $stokSesudah,
                    'nama_pengguna' => $namaPengguna,
                    'tujuan' => '-',
                    'keterangan' => $keterangan,
                    'tanggal' => $tanggal,
                    'jam' => $jam . ':00',
                ]);

                $pdo->commit();
                set_flash('success', 'Stok barang berhasil ditambahkan.');
                redirect('modules/transaksi/masuk.php');
            } catch (Throwable $throwable) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Gagal menyimpan transaksi barang masuk.';
            }
        }
    }

    if ($action === 'update') {
        $historyId = (int) ($_POST['history_id'] ?? 0);
        $qtyTambah = (int) ($_POST['qty'] ?? 0);
        $namaPengguna = trim($_POST['nama_pengguna'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');

        if ($historyId <= 0) {
            $errors[] = 'Data history tidak valid.';
        }

        if ($qtyTambah <= 0) {
            $errors[] = 'Jumlah tambah harus lebih dari 0.';
        }

        if ($namaPengguna === '') {
            $errors[] = 'Nama petugas wajib diisi.';
        }

        $historyRow = null;
        $item = null;

        if (!$errors) {
            $historyRow = fetch_one($pdo, 'SELECT * FROM histori_barang WHERE id = :id AND tipe_transaksi = "masuk" LIMIT 1', ['id' => $historyId]);
            if (!$historyRow) {
                $errors[] = 'History barang masuk tidak ditemukan.';
            } else {
                $item = fetch_one($pdo, 'SELECT * FROM barang WHERE id = :id LIMIT 1', ['id' => $historyRow['barang_id']]);
                if (!$item) {
                    $errors[] = 'Data barang tidak ditemukan.';
                }
            }
        }

        if (!$errors && $historyRow && $item) {
            $stokSekarang = (int) $item['qty'];
            $selisihQty = $qtyTambah - (int) $historyRow['qty'];
            $stokBaru = $stokSekarang + $selisihQty;

            if ($stokBaru < 0) {
                $errors[] = 'Perubahan qty membuat stok menjadi negatif.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $updateBarang = $pdo->prepare('UPDATE barang SET qty = :qty WHERE id = :id');
                    $updateBarang->execute([
                        'qty' => $stokBaru,
                        'id' => $item['id'],
                    ]);

                    $updateHistory = $pdo->prepare('
                        UPDATE histori_barang
                        SET qty = :qty,
                            stok_sesudah = :stok_sesudah,
                            nama_pengguna = :nama_pengguna,
                            keterangan = :keterangan
                        WHERE id = :id
                    ');
                    $updateHistory->execute([
                        'qty' => $qtyTambah,
                        'stok_sesudah' => (int) $historyRow['stok_sebelum'] + $qtyTambah,
                        'nama_pengguna' => $namaPengguna,
                        'keterangan' => $keterangan,
                        'id' => $historyId,
                    ]);

                    $pdo->commit();
                    set_flash('success', 'Riwayat barang masuk berhasil diperbarui.');
                    redirect('modules/transaksi/masuk.php');
                } catch (Throwable $throwable) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = 'Gagal memperbarui riwayat barang masuk.';
                }
            }
        }
    }

    if ($action === 'delete') {
        $historyId = (int) ($_POST['history_id'] ?? 0);

        if ($historyId <= 0) {
            $errors[] = 'Data history tidak valid.';
        }

        $historyRow = null;
        $item = null;

        if (!$errors) {
            $historyRow = fetch_one($pdo, 'SELECT * FROM histori_barang WHERE id = :id AND tipe_transaksi = "masuk" LIMIT 1', ['id' => $historyId]);
            if (!$historyRow) {
                $errors[] = 'History barang masuk tidak ditemukan.';
            } else {
                $item = fetch_one($pdo, 'SELECT * FROM barang WHERE id = :id LIMIT 1', ['id' => $historyRow['barang_id']]);
                if (!$item) {
                    $errors[] = 'Data barang tidak ditemukan.';
                }
            }
        }

        if (!$errors && $historyRow && $item) {
            $stokSekarang = (int) $item['qty'];
            $stokBaru = $stokSekarang - (int) $historyRow['qty'];

            if ($stokBaru < 0) {
                $errors[] = 'History tidak bisa dihapus karena stok saat ini sudah terpakai oleh transaksi lain.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $updateBarang = $pdo->prepare('UPDATE barang SET qty = :qty WHERE id = :id');
                    $updateBarang->execute([
                        'qty' => $stokBaru,
                        'id' => $item['id'],
                    ]);

                    $deleteHistory = $pdo->prepare('DELETE FROM histori_barang WHERE id = :id');
                    $deleteHistory->execute(['id' => $historyId]);

                    $pdo->commit();
                    set_flash('success', 'Riwayat barang masuk berhasil dihapus.');
                    redirect('modules/transaksi/masuk.php');
                } catch (Throwable $throwable) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = 'Gagal menghapus riwayat barang masuk.';
                }
            }
        }
    }
}

$items = fetch_all(
    $pdo,
    'SELECT b.id, b.nama_barang, b.qty, b.kondisi, b.keterangan AS keterangan_barang
     FROM barang b
     ORDER BY b.nama_barang ASC'
);
$rooms = fetch_all($pdo, 'SELECT id, nama_ruangan FROM ruangan ORDER BY nama_ruangan ASC');
$recentEntries = fetch_all(
    $pdo,
    'SELECT h.*, b.nama_barang, b.kondisi, b.keterangan AS keterangan_barang, COALESCE(h.ruangan_nama, r.nama_ruangan) AS nama_ruangan_transaksi
     FROM histori_barang h
     INNER JOIN barang b ON b.id = h.barang_id
     LEFT JOIN ruangan r ON r.id = h.ruangan_id
     WHERE h.tipe_transaksi = "masuk"
     ORDER BY h.tanggal DESC, h.jam DESC, h.id DESC
     LIMIT 10'
);

require_once BASE_PATH . '/includes/layout_top.php';
?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4">
                <h5 class="mb-1">Form Barang Masuk</h5>
                <p class="text-muted mb-0">Tambah stok barang dan catat histori otomatis.</p>
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

                <form method="post" class="room-filter-form">
                    <?= csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label">Nama Ruangan</label>
                        <select name="ruangan_id" class="form-select" data-room-filter required>
                            <option value="">Pilih ruangan</option>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?= $room['id']; ?>" <?= (int) ($_POST['ruangan_id'] ?? 0) === (int) $room['id'] ? 'selected' : ''; ?>><?= e($room['nama_ruangan']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pilih Barang</label>
                        <select name="barang_id" class="form-select" data-item-select required>
                            <option value="">Pilih barang</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?= $item['id']; ?>" <?= (int) ($_POST['barang_id'] ?? 0) === (int) $item['id'] ? 'selected' : ''; ?>>
                                    <?= e($item['nama_barang']); ?> - <?= e($item['kondisi']); ?> (stok: <?= e((string) $item['qty']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Pilih barang, lalu transaksi akan dicatat ke ruangan yang Anda pilih di atas.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah Tambah</label>
                        <input type="number" name="qty" class="form-control" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="tanggal" class="form-control" value="<?= e($_POST['tanggal'] ?? date('Y-m-d')); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jam</label>
                        <input type="time" name="jam" class="form-control" value="<?= e($_POST['jam'] ?? date('H:i')); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Petugas</label>
                        <input type="text" name="nama_pengguna" class="form-control" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-4">Simpan Barang Masuk</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-4">
                <h5 class="mb-1">Riwayat Barang Masuk</h5>
                <p class="text-muted mb-0">10 transaksi masuk terbaru.</p>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th>Ruangan</th>
                                <th>Kondisi</th>
                                <th>Qty</th>
                                <th>Stok</th>
                                <th>Petugas</th>
                                <th>Tanggal</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$recentEntries): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">Belum ada transaksi barang masuk.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($recentEntries as $entry): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($entry['nama_barang']); ?></strong>
                                        <div class="small text-muted"><?= e($entry['keterangan'] ?: '-'); ?></div>
                                        <div class="small text-muted"><?= e($entry['keterangan_barang'] ?: '-'); ?></div>
                                    </td>
                                    <td><?= e($entry['nama_ruangan_transaksi'] ?: '-'); ?></td>
                                    <td><span class="badge text-bg-<?= condition_badge($entry['kondisi']); ?>"><?= e($entry['kondisi']); ?></span></td>
                                    <td><?= format_number($entry['qty']); ?></td>
                                    <td><?= format_number($entry['stok_sebelum']); ?> -> <?= format_number($entry['stok_sesudah']); ?></td>
                                    <td><?= e($entry['nama_pengguna']); ?></td>
                                    <td><?= e(format_date_id($entry['tanggal'])); ?> <?= e(format_time_id($entry['jam'])); ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editMasukModal<?= $entry['id']; ?>">Edit</button>
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteMasukModal<?= $entry['id']; ?>">Hapus</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php foreach ($recentEntries as $entry): ?>
    <div class="modal fade" id="editMasukModal<?= $entry['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Riwayat Barang Masuk</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="history_id" value="<?= $entry['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Barang</label>
                            <input type="text" class="form-control" value="<?= e($entry['nama_barang']); ?> - <?= e($entry['nama_ruangan_transaksi'] ?: '-'); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Qty</label>
                            <input type="number" name="qty" class="form-control" min="1" value="<?= e((string) $entry['qty']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nama Petugas</label>
                            <input type="text" name="nama_pengguna" class="form-control" value="<?= e($entry['nama_pengguna']); ?>" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="3"><?= e($entry['keterangan']); ?></textarea>
                        </div>
                        <div class="alert alert-warning mt-3 mb-0">
                            Mengubah qty akan menyesuaikan stok barang saat ini.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteMasukModal<?= $entry['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Hapus Riwayat Barang Masuk</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="history_id" value="<?= $entry['id']; ?>">
                        <p>Yakin ingin menghapus transaksi barang masuk ini?</p>
                        <div class="bg-light rounded-4 p-3 small">
                            <div><strong>Barang:</strong> <?= e($entry['nama_barang']); ?></div>
                            <div><strong>Ruangan:</strong> <?= e($entry['nama_ruangan_transaksi'] ?: '-'); ?></div>
                            <div><strong>Qty:</strong> <?= format_number($entry['qty']); ?></div>
                            <div><strong>Tanggal:</strong> <?= e(format_date_id($entry['tanggal'])); ?> <?= e(format_time_id($entry['jam'])); ?></div>
                        </div>
                        <div class="alert alert-warning mt-3 mb-0">
                            Menghapus transaksi ini akan mengurangi stok barang saat ini.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require_once BASE_PATH . '/includes/layout_bottom.php'; ?>
