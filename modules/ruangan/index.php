<?php

require_once dirname(__DIR__, 2) . '/config/app.php';

$pageTitle = 'Data Ruangan';
$errors = [];

if (is_post()) {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $namaRuangan = trim($_POST['nama_ruangan'] ?? '');
    $lokasi = trim($_POST['lokasi'] ?? '');
    $penanggungJawab = trim($_POST['penanggung_jawab'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);

    if ($action !== 'delete') {
        if ($namaRuangan === '') {
            $errors[] = 'Nama ruangan wajib diisi.';
        }

        if ($lokasi === '') {
            $errors[] = 'Lokasi wajib diisi.';
        }

        if ($penanggungJawab === '') {
            $errors[] = 'Penanggung jawab wajib diisi.';
        }
    }

    if (!$errors) {
        if ($action === 'create') {
            $statement = $pdo->prepare('INSERT INTO ruangan (nama_ruangan, lokasi, penanggung_jawab) VALUES (:nama_ruangan, :lokasi, :penanggung_jawab)');
            $statement->execute([
                'nama_ruangan' => $namaRuangan,
                'lokasi' => $lokasi,
                'penanggung_jawab' => $penanggungJawab,
            ]);
            set_flash('success', 'Data ruangan berhasil ditambahkan.');
            redirect('modules/ruangan/index.php');
        }

        if ($action === 'update' && $id > 0) {
            $statement = $pdo->prepare('UPDATE ruangan SET nama_ruangan = :nama_ruangan, lokasi = :lokasi, penanggung_jawab = :penanggung_jawab WHERE id = :id');
            $statement->execute([
                'id' => $id,
                'nama_ruangan' => $namaRuangan,
                'lokasi' => $lokasi,
                'penanggung_jawab' => $penanggungJawab,
            ]);
            set_flash('success', 'Data ruangan berhasil diperbarui.');
            redirect('modules/ruangan/index.php');
        }

        if ($action === 'delete' && $id > 0) {
            $countBarang = (int) fetch_scalar($pdo, 'SELECT COUNT(*) FROM barang WHERE ruangan_id = :id', ['id' => $id]);
            if ($countBarang > 0) {
                set_flash('danger', 'Ruangan tidak dapat dihapus karena masih digunakan oleh data barang.');
            } else {
                $statement = $pdo->prepare('DELETE FROM ruangan WHERE id = :id');
                $statement->execute(['id' => $id]);
                set_flash('success', 'Data ruangan berhasil dihapus.');
            }
            redirect('modules/ruangan/index.php');
        }
    }
}

$rooms = fetch_all($pdo, 'SELECT * FROM ruangan ORDER BY nama_ruangan ASC');

require_once BASE_PATH . '/includes/layout_top.php';
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap gap-3 pt-4">
        <div>
            <h5 class="mb-1">Master Data Ruangan</h5>
            <p class="text-muted mb-0">Kelola lokasi dan penanggung jawab tiap ruangan.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal">Tambah Ruangan</button>
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
                        <th>Nama Ruangan</th>
                        <th>Lokasi</th>
                        <th>Penanggung Jawab</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $index => $room): ?>
                        <tr>
                            <td><?= $index + 1; ?></td>
                            <td><?= e($room['nama_ruangan']); ?></td>
                            <td><?= e($room['lokasi']); ?></td>
                            <td><?= e($room['penanggung_jawab']); ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editRoomModal<?= $room['id']; ?>">Edit</button>
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteRoomModal<?= $room['id']; ?>">Hapus</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addRoomModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Ruangan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Nama Ruangan</label>
                        <input type="text" name="nama_ruangan" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Lokasi</label>
                        <input type="text" name="lokasi" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Penanggung Jawab</label>
                        <input type="text" name="penanggung_jawab" class="form-control" required>
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

<?php foreach ($rooms as $room): ?>
    <div class="modal fade" id="editRoomModal<?= $room['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Ruangan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $room['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Nama Ruangan</label>
                            <input type="text" name="nama_ruangan" class="form-control" value="<?= e($room['nama_ruangan']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Lokasi</label>
                            <input type="text" name="lokasi" class="form-control" value="<?= e($room['lokasi']); ?>" required>
                        </div>
                        <div>
                            <label class="form-label">Penanggung Jawab</label>
                            <input type="text" name="penanggung_jawab" class="form-control" value="<?= e($room['penanggung_jawab']); ?>" required>
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

    <div class="modal fade" id="deleteRoomModal<?= $room['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Hapus Ruangan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $room['id']; ?>">
                        <p class="mb-0">Yakin ingin menghapus ruangan <strong><?= e($room['nama_ruangan']); ?></strong>?</p>
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
