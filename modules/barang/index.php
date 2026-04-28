<?php

require_once dirname(__DIR__, 2) . '/config/app.php';

$pageTitle = 'Data Barang';
$errors = [];

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $kodeBarang = trim($_POST['kode_barang'] ?? generate_barang_code($pdo));
    $namaBarang = trim($_POST['nama_barang'] ?? '');
    $tahunInventaris = trim($_POST['tahun_inventaris'] ?? '');
    $qty = (int) ($_POST['qty'] ?? 0);
    $kondisi = trim($_POST['kondisi'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($action !== 'delete') {
        if ($namaBarang === '') {
            $errors[] = 'Nama barang wajib diisi.';
        }

        if ($tahunInventaris === '' || !preg_match('/^\d{4}$/', $tahunInventaris)) {
            $errors[] = 'Tahun inventaris harus 4 digit.';
        }

        if ($qty < 0) {
            $errors[] = 'Qty tidak boleh negatif.';
        }

        if (!in_array($kondisi, ['Baik', 'Rusak', 'Perbaikan'], true)) {
            $errors[] = 'Kondisi barang tidak valid.';
        }
    }

    if (!$errors) {
        if ($action === 'create') {
            $statement = $pdo->prepare('
                INSERT INTO barang (kode_barang, nama_barang, ruangan_id, tahun_inventaris, qty, kondisi, keterangan, created_at)
                VALUES (:kode_barang, :nama_barang, :ruangan_id, :tahun_inventaris, :qty, :kondisi, :keterangan, NOW())
            ');
            $statement->execute([
                'kode_barang' => $kodeBarang,
                'nama_barang' => $namaBarang,
                'ruangan_id' => null,
                'tahun_inventaris' => $tahunInventaris,
                'qty' => $qty,
                'kondisi' => $kondisi,
                'keterangan' => $keterangan,
            ]);
            set_flash('success', 'Data barang berhasil ditambahkan.');
            redirect('modules/barang/index.php');
        }

        if ($action === 'update' && $id > 0) {
            $statement = $pdo->prepare('
                UPDATE barang
                SET nama_barang = :nama_barang, ruangan_id = :ruangan_id, tahun_inventaris = :tahun_inventaris, qty = :qty, kondisi = :kondisi, keterangan = :keterangan
                WHERE id = :id
            ');
            $statement->execute([
                'id' => $id,
                'nama_barang' => $namaBarang,
                'ruangan_id' => null,
                'tahun_inventaris' => $tahunInventaris,
                'qty' => $qty,
                'kondisi' => $kondisi,
                'keterangan' => $keterangan,
            ]);
            set_flash('success', 'Data barang berhasil diperbarui.');
            redirect('modules/barang/index.php');
        }

        if ($action === 'delete' && $id > 0) {
            $countHistory = (int) fetch_scalar($pdo, 'SELECT COUNT(*) FROM histori_barang WHERE barang_id = :id', ['id' => $id]);
            if ($countHistory > 0) {
                set_flash('danger', 'Barang tidak dapat dihapus karena sudah memiliki histori transaksi.');
            } else {
                $statement = $pdo->prepare('DELETE FROM barang WHERE id = :id');
                $statement->execute(['id' => $id]);
                set_flash('success', 'Data barang berhasil dihapus.');
            }
            redirect('modules/barang/index.php');
        }
    }
}

$items = fetch_all(
    $pdo,
    'SELECT b.*
     FROM barang b
     ORDER BY b.nama_barang ASC'
);

require_once BASE_PATH . '/includes/layout_top.php';
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap gap-3 pt-4">
        <div>
            <h5 class="mb-1">Master Data Barang</h5>
            <p class="text-muted mb-0">Kelola master inventaris hardware dan elektronik tanpa penguncian ke ruangan.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">Tambah Barang</button>
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

        <div class="table-responsive">
            <table class="table table-hover align-middle datatable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Barang</th>
                        <th>Tahun</th>
                        <th>Qty</th>
                        <th>Kondisi</th>
                        <th>Keterangan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $index => $item): ?>
                        <tr>
                            <td><?= $index + 1; ?></td>
                            <td>
                                <strong><?= e($item['nama_barang']); ?></strong>
                                <div class="small text-muted"><?= e($item['kode_barang']); ?></div>
                            </td>
                            <td><?= e($item['tahun_inventaris']); ?></td>
                            <td>
                                <span class="fw-semibold <?= (int) $item['qty'] < 5 ? 'text-danger' : 'text-dark'; ?>">
                                    <?= format_number($item['qty']); ?>
                                </span>
                            </td>
                            <td><span class="badge text-bg-<?= condition_badge($item['kondisi']); ?>"><?= e($item['kondisi']); ?></span></td>
                            <td><?= e($item['keterangan'] ?: '-'); ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editItemModal<?= $item['id']; ?>">Edit</button>
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteItemModal<?= $item['id']; ?>">Hapus</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addItemModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="kode_barang" value="<?= e(generate_barang_code($pdo)); ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama Barang</label>
                            <input type="text" name="nama_barang" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tahun Inventaris</label>
                            <input type="number" name="tahun_inventaris" class="form-control" min="2000" max="<?= date('Y'); ?>" value="<?= date('Y'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Jumlah Barang (Qty)</label>
                            <input type="number" name="qty" class="form-control" min="0" value="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Kondisi Barang</label>
                            <select name="kondisi" class="form-select" required>
                                <option value="Baik">Baik</option>
                                <option value="Rusak">Rusak</option>
                                <option value="Perbaikan">Perbaikan</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Keterangan</label>
                            <textarea name="keterangan" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($items as $item): ?>
    <div class="modal fade" id="editItemModal<?= $item['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Barang</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $item['id']; ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nama Barang</label>
                                <input type="text" name="nama_barang" class="form-control" value="<?= e($item['nama_barang']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tahun Inventaris</label>
                                <input type="number" name="tahun_inventaris" class="form-control" min="2000" max="<?= date('Y'); ?>" value="<?= e($item['tahun_inventaris']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Jumlah Barang (Qty)</label>
                                <input type="number" name="qty" class="form-control" min="0" value="<?= e($item['qty']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Kondisi Barang</label>
                                <select name="kondisi" class="form-select" required>
                                    <?php foreach (['Baik', 'Rusak', 'Perbaikan'] as $condition): ?>
                                        <option value="<?= $condition; ?>" <?= $item['kondisi'] === $condition ? 'selected' : ''; ?>><?= $condition; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Keterangan</label>
                                <textarea name="keterangan" class="form-control" rows="3"><?= e($item['keterangan']); ?></textarea>
                            </div>
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

    <div class="modal fade" id="deleteItemModal<?= $item['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Hapus Barang</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $item['id']; ?>">
                        <p class="mb-0">Yakin ingin menghapus barang <strong><?= e($item['nama_barang']); ?></strong>?</p>
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
