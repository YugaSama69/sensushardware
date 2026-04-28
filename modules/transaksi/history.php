<?php

require_once dirname(__DIR__, 2) . '/config/app.php';

$pageTitle = 'History Barang';

if (is_post()) {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id > 0) {
        $statement = $pdo->prepare('DELETE FROM histori_barang WHERE id = :id');
        $statement->execute(['id' => $id]);
        set_flash('success', 'Data history berhasil dihapus.');
        redirect('modules/transaksi/history.php');
    }
}

$historyRows = fetch_all(
    $pdo,
    'SELECT h.*, b.nama_barang, b.kondisi, b.keterangan AS keterangan_barang, COALESCE(h.ruangan_nama, r.nama_ruangan) AS nama_ruangan_transaksi
     FROM histori_barang h
     INNER JOIN barang b ON b.id = h.barang_id
     LEFT JOIN ruangan r ON r.id = h.ruangan_id
     ORDER BY h.tanggal DESC, h.jam DESC, h.id DESC'
);

require_once BASE_PATH . '/includes/layout_top.php';
?>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-4">
        <h5 class="mb-1">History Barang</h5>
        <p class="text-muted mb-0">Riwayat lengkap barang masuk dan keluar dari terbaru ke terlama.</p>
    </div>
    <div class="card-body">
        <div class="alert alert-warning border-0">
            Menghapus history hanya menghapus catatan transaksi, tidak mengubah stok barang saat ini. Setelah history barang terkait kosong, data barang bisa dihapus dari menu Data Barang, lalu ruangan bisa dihapus.
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle datatable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Barang</th>
                        <th>Ruangan</th>
                        <th>Kondisi</th>
                        <th>Jenis</th>
                        <th>Qty</th>
                        <th>Stok Sebelum</th>
                        <th>Stok Sesudah</th>
                        <th>Pengguna</th>
                        <th>Hari</th>
                        <th>Tanggal</th>
                        <th>Jam</th>
                        <th>Keterangan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historyRows as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1; ?></td>
                            <td>
                                <strong><?= e($row['nama_barang']); ?></strong>
                                <div class="small text-muted"><?= e($row['keterangan_barang'] ?: '-'); ?></div>
                            </td>
                            <td><?= e($row['nama_ruangan_transaksi'] ?: '-'); ?></td>
                            <td><span class="badge text-bg-<?= condition_badge($row['kondisi']); ?>"><?= e($row['kondisi']); ?></span></td>
                            <td><span class="badge text-bg-<?= transaction_badge($row['tipe_transaksi']); ?>"><?= ucfirst(e($row['tipe_transaksi'])); ?></span></td>
                            <td><?= format_number($row['qty']); ?></td>
                            <td><?= format_number($row['stok_sebelum']); ?></td>
                            <td><?= format_number($row['stok_sesudah']); ?></td>
                            <td>
                                <?= e($row['nama_pengguna']); ?>
                                <div class="small text-muted"><?= e($row['tujuan'] ?: '-'); ?></div>
                            </td>
                            <td><?= e(day_name_id($row['tanggal'])); ?></td>
                            <td><?= e(format_date_id($row['tanggal'])); ?></td>
                            <td><?= e(format_time_id($row['jam'])); ?></td>
                            <td><?= e($row['keterangan'] ?: '-'); ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteHistoryModal<?= $row['id']; ?>">Hapus</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php foreach ($historyRows as $row): ?>
    <div class="modal fade" id="deleteHistoryModal<?= $row['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Hapus History</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $row['id']; ?>">
                        <p>Yakin ingin menghapus history transaksi berikut?</p>
                        <div class="bg-light rounded-4 p-3 small">
                            <div><strong>Barang:</strong> <?= e($row['nama_barang']); ?></div>
                            <div><strong>Ruangan:</strong> <?= e($row['nama_ruangan_transaksi'] ?: '-'); ?></div>
                            <div><strong>Kondisi:</strong> <?= e($row['kondisi']); ?></div>
                            <div><strong>Jenis:</strong> <?= ucfirst(e($row['tipe_transaksi'])); ?></div>
                            <div><strong>Qty:</strong> <?= format_number($row['qty']); ?></div>
                            <div><strong>Tanggal:</strong> <?= e(format_date_id($row['tanggal'])); ?> <?= e(format_time_id($row['jam'])); ?></div>
                        </div>
                        <div class="alert alert-warning mt-3 mb-0">
                            Stok barang tidak akan berubah. Jika stok perlu disesuaikan, edit stok di menu Data Barang.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus History</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require_once BASE_PATH . '/includes/layout_bottom.php'; ?>
