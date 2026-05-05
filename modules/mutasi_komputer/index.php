<?php

require_once dirname(__DIR__, 2) . '/config/app.php';

$pageTitle = 'Mutasi Komputer';
$errors = [];
$jenisMutasiOptions = [
    'komputer_baru' => 'Pengiriman Komputer Baru',
    'pergantian_komputer_rusak' => 'Pergantian Komputer Rusak',
];
$mutasiComputerLabels = ['PC Desktop', 'AIO', 'Laptop'];

if (is_post()) {
    verify_csrf();

    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        $jenisMutasi = trim($_POST['jenis_mutasi'] ?? '');
        $ruanganId = (int) ($_POST['ruangan_id'] ?? 0);
        $barangId = (int) ($_POST['barang_id'] ?? 0);
        $komputerClientId = (int) ($_POST['komputer_client_id'] ?? 0);
        $qty = (int) ($_POST['qty'] ?? 0);
        $namaPetugas = trim($_POST['nama_petugas'] ?? '');
        $tanggal = trim($_POST['tanggal'] ?? date('Y-m-d'));
        $jam = trim($_POST['jam'] ?? date('H:i'));
        $keterangan = trim($_POST['keterangan'] ?? '');

        if (!array_key_exists($jenisMutasi, $jenisMutasiOptions)) {
            $errors[] = 'Jenis mutasi wajib dipilih.';
        }

        if ($ruanganId <= 0) {
            $errors[] = 'Ruangan tujuan wajib dipilih.';
        }

        if ($barangId <= 0) {
            $errors[] = 'Barang komputer wajib dipilih.';
        }

        if ($qty <= 0) {
            $errors[] = 'Jumlah mutasi harus lebih dari 0.';
        }

        if ($namaPetugas === '') {
            $errors[] = 'Nama petugas wajib diisi.';
        }

        if ($tanggal === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            $errors[] = 'Tanggal wajib diisi dengan format yang valid.';
        }

        if ($jam === '' || !preg_match('/^\d{2}:\d{2}$/', $jam)) {
            $errors[] = 'Jam wajib diisi dengan format yang valid.';
        }

        $barang = null;
        $ruangan = null;
        $komputerReferensi = null;

        if (!$errors) {
            $barang = fetch_one($pdo, 'SELECT * FROM barang WHERE id = :id LIMIT 1', ['id' => $barangId]);
            $ruangan = fetch_one($pdo, 'SELECT id, nama_ruangan FROM ruangan WHERE id = :id LIMIT 1', ['id' => $ruanganId]);

            if (!$barang) {
                $errors[] = 'Barang yang dipilih tidak ditemukan.';
            } elseif (!in_array(trim((string) ($barang['label_barang'] ?? '')), $mutasiComputerLabels, true)) {
                $errors[] = 'Barang yang dipilih tidak termasuk label komputer untuk mutasi.';
            }

            if (!$ruangan) {
                $errors[] = 'Ruangan tujuan tidak ditemukan.';
            }

            if ($jenisMutasi === 'pergantian_komputer_rusak') {
                if ($komputerClientId <= 0) {
                    $errors[] = 'Pilih komputer rusak yang menjadi referensi penggantian.';
                } else {
                    $komputerReferensi = fetch_one($pdo, 'SELECT id, hostname, ruangan, kondisi FROM komputer_client WHERE id = :id LIMIT 1', ['id' => $komputerClientId]);
                    if (!$komputerReferensi) {
                        $errors[] = 'Komputer referensi tidak ditemukan.';
                    } elseif (($komputerReferensi['kondisi'] ?? '') !== 'Rusak') {
                        $errors[] = 'Komputer referensi harus berkondisi Rusak.';
                    }
                }
            }
        }

        if (!$errors && $barang && $ruangan) {
            $stokSebelum = (int) $barang['qty'];
            if ($qty > $stokSebelum) {
                $errors[] = 'Stok barang tidak mencukupi untuk mutasi komputer.';
            } else {
                $stokSesudah = $stokSebelum - $qty;
                $hostnameReferensi = $komputerReferensi['hostname'] ?? null;
                $tujuanHistori = $jenisMutasiOptions[$jenisMutasi];
                if ($hostnameReferensi) {
                    $tujuanHistori .= ' - ' . $hostnameReferensi;
                }

                try {
                    $pdo->beginTransaction();

                    $updateBarang = $pdo->prepare('UPDATE barang SET qty = :qty WHERE id = :id');
                    $updateBarang->execute([
                        'qty' => $stokSesudah,
                        'id' => $barangId,
                    ]);

                    $insertHistori = $pdo->prepare('
                        INSERT INTO histori_barang (barang_id, ruangan_id, ruangan_nama, tipe_transaksi, qty, stok_sebelum, stok_sesudah, nama_pengguna, tujuan, keterangan, tanggal, jam, created_at)
                        VALUES (:barang_id, :ruangan_id, :ruangan_nama, :tipe_transaksi, :qty, :stok_sebelum, :stok_sesudah, :nama_pengguna, :tujuan, :keterangan, :tanggal, :jam, NOW())
                    ');
                    $insertHistori->execute([
                        'barang_id' => $barangId,
                        'ruangan_id' => $ruangan['id'],
                        'ruangan_nama' => $ruangan['nama_ruangan'],
                        'tipe_transaksi' => 'keluar',
                        'qty' => $qty,
                        'stok_sebelum' => $stokSebelum,
                        'stok_sesudah' => $stokSesudah,
                        'nama_pengguna' => $namaPetugas,
                        'tujuan' => $tujuanHistori,
                        'keterangan' => $keterangan,
                        'tanggal' => $tanggal,
                        'jam' => $jam . ':00',
                    ]);

                    $historiBarangId = (int) $pdo->lastInsertId();

                    $insertMutasi = $pdo->prepare('
                        INSERT INTO mutasi_komputer (
                            barang_id, histori_barang_id, ruangan_id, ruangan_nama, komputer_client_id, hostname_referensi,
                            jenis_mutasi, qty, stok_sebelum, stok_sesudah, nama_petugas, tanggal, jam, keterangan, created_at
                        ) VALUES (
                            :barang_id, :histori_barang_id, :ruangan_id, :ruangan_nama, :komputer_client_id, :hostname_referensi,
                            :jenis_mutasi, :qty, :stok_sebelum, :stok_sesudah, :nama_petugas, :tanggal, :jam, :keterangan, NOW()
                        )
                    ');
                    $insertMutasi->execute([
                        'barang_id' => $barangId,
                        'histori_barang_id' => $historiBarangId,
                        'ruangan_id' => $ruangan['id'],
                        'ruangan_nama' => $ruangan['nama_ruangan'],
                        'komputer_client_id' => $komputerReferensi['id'] ?? null,
                        'hostname_referensi' => $hostnameReferensi,
                        'jenis_mutasi' => $jenisMutasi,
                        'qty' => $qty,
                        'stok_sebelum' => $stokSebelum,
                        'stok_sesudah' => $stokSesudah,
                        'nama_petugas' => $namaPetugas,
                        'tanggal' => $tanggal,
                        'jam' => $jam . ':00',
                        'keterangan' => $keterangan,
                    ]);

                    $pdo->commit();
                    set_flash('success', 'Mutasi komputer berhasil disimpan dan stok barang sudah dikurangi.');
                    redirect('modules/mutasi_komputer/index.php');
                } catch (Throwable $throwable) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = 'Gagal menyimpan mutasi komputer.';
                }
            }
        }
    }

    if ($action === 'delete') {
        $mutasiId = (int) ($_POST['mutasi_id'] ?? 0);

        if ($mutasiId <= 0) {
            $errors[] = 'Data mutasi tidak valid.';
        }

        $mutasi = null;
        $barang = null;

        if (!$errors) {
            $mutasi = fetch_one($pdo, 'SELECT * FROM mutasi_komputer WHERE id = :id LIMIT 1', ['id' => $mutasiId]);
            if (!$mutasi) {
                $errors[] = 'Data mutasi komputer tidak ditemukan.';
            } else {
                $barang = fetch_one($pdo, 'SELECT * FROM barang WHERE id = :id LIMIT 1', ['id' => $mutasi['barang_id']]);
                if (!$barang) {
                    $errors[] = 'Data barang tidak ditemukan.';
                }
            }
        }

        if (!$errors && $mutasi && $barang) {
            $stokSekarang = (int) $barang['qty'];
            $stokBaru = $stokSekarang + (int) $mutasi['qty'];

            try {
                $pdo->beginTransaction();

                $updateBarang = $pdo->prepare('UPDATE barang SET qty = :qty WHERE id = :id');
                $updateBarang->execute([
                    'qty' => $stokBaru,
                    'id' => $barang['id'],
                ]);

                $deleteHistori = $pdo->prepare('DELETE FROM histori_barang WHERE id = :id');
                $deleteHistori->execute(['id' => $mutasi['histori_barang_id']]);

                $deleteMutasi = $pdo->prepare('DELETE FROM mutasi_komputer WHERE id = :id');
                $deleteMutasi->execute(['id' => $mutasiId]);

                $pdo->commit();
                set_flash('success', 'Mutasi komputer berhasil dihapus dan stok barang sudah dikembalikan.');
                redirect('modules/mutasi_komputer/index.php');
            } catch (Throwable $throwable) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'Gagal menghapus mutasi komputer.';
            }
        }
    }
}

$items = fetch_all(
    $pdo,
    'SELECT id, kode_barang, nama_barang, qty, kondisi, tahun_inventaris, label_barang
     FROM barang
     WHERE label_barang IN ("PC Desktop", "AIO", "Laptop")
     ORDER BY nama_barang ASC'
);
$rooms = fetch_all($pdo, 'SELECT id, nama_ruangan FROM ruangan ORDER BY nama_ruangan ASC');
$komputerRusakOptions = fetch_all(
    $pdo,
    'SELECT id, hostname, ruangan, kondisi
     FROM komputer_client
     WHERE kondisi = "Rusak"
     ORDER BY hostname ASC'
);
$selectedComputer = null;
foreach ($komputerRusakOptions as $computerOption) {
    if ((int) ($computerOption['id'] ?? 0) === (int) ($_POST['komputer_client_id'] ?? 0)) {
        $selectedComputer = $computerOption;
        break;
    }
}
$mutasiStats = [
    'total_mutasi' => (int) fetch_scalar($pdo, 'SELECT COUNT(*) FROM mutasi_komputer'),
    'komputer_baru' => (int) fetch_scalar($pdo, 'SELECT COUNT(*) FROM mutasi_komputer WHERE jenis_mutasi = "komputer_baru"'),
    'pergantian_rusak' => (int) fetch_scalar($pdo, 'SELECT COUNT(*) FROM mutasi_komputer WHERE jenis_mutasi = "pergantian_komputer_rusak"'),
    'total_unit' => (int) fetch_scalar($pdo, 'SELECT COALESCE(SUM(qty), 0) FROM mutasi_komputer'),
];
$mutasiRows = fetch_all(
    $pdo,
    'SELECT m.*, b.kode_barang, b.nama_barang, b.label_barang, b.kondisi AS kondisi_barang
     FROM mutasi_komputer m
     INNER JOIN barang b ON b.id = m.barang_id
     ORDER BY m.tanggal DESC, m.jam DESC, m.id DESC'
);

require_once BASE_PATH . '/includes/layout_top.php';
?>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="metric-card card-sky">
            <span>Total Mutasi</span>
            <h3><?= format_number($mutasiStats['total_mutasi']); ?></h3>
            <small>Seluruh transaksi mutasi komputer</small>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="metric-card card-emerald">
            <span>Komputer Baru</span>
            <h3><?= format_number($mutasiStats['komputer_baru']); ?></h3>
            <small>Pengiriman komputer baru</small>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="metric-card card-rose">
            <span>Pergantian Rusak</span>
            <h3><?= format_number($mutasiStats['pergantian_rusak']); ?></h3>
            <small>Pergantian komputer rusak/perbaikan</small>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="metric-card card-amber">
            <span>Total Unit Mutasi</span>
            <h3><?= format_number($mutasiStats['total_unit']); ?></h3>
            <small>Jumlah unit yang sudah dikirim</small>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4">
                <h5 class="mb-1">Form Mutasi Komputer</h5>
                <p class="text-muted mb-0">Catat pengiriman komputer baru atau pergantian komputer rusak dan kurangi stok otomatis.</p>
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

                <?php if (!$items): ?>
                    <div class="alert alert-warning">
                        Belum ada barang dengan label `PC Desktop`, `AIO`, atau `Laptop`. Atur label barang terlebih dahulu dari menu `Data Barang`.
                    </div>
                <?php endif; ?>

                <form method="post">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Jenis Mutasi</label>
                        <select name="jenis_mutasi" class="form-select" data-mutasi-type-select required>
                            <option value="">Pilih jenis mutasi</option>
                            <?php foreach ($jenisMutasiOptions as $jenisKey => $jenisLabel): ?>
                                <option value="<?= e($jenisKey); ?>" <?= ($_POST['jenis_mutasi'] ?? '') === $jenisKey ? 'selected' : ''; ?>>
                                    <?= e($jenisLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ruangan Tujuan</label>
                        <select name="ruangan_id" class="form-select" required>
                            <option value="">Pilih ruangan tujuan</option>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?= $room['id']; ?>" <?= (int) ($_POST['ruangan_id'] ?? 0) === (int) $room['id'] ? 'selected' : ''; ?>>
                                    <?= e($room['nama_ruangan']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Barang yang Dikirim</label>
                        <select name="barang_id" class="form-select" required>
                            <option value="">Pilih barang</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?= $item['id']; ?>" <?= (int) ($_POST['barang_id'] ?? 0) === (int) $item['id'] ? 'selected' : ''; ?>>
                                    <?= e($item['kode_barang']); ?> - <?= e($item['nama_barang']); ?> - <?= e($item['label_barang'] ?: 'Lainnya'); ?> - <?= e($item['kondisi']); ?> (stok: <?= format_number($item['qty']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Barang diambil dari stok master `Data Barang` dengan label `PC Desktop`, `AIO`, atau `Laptop`.</div>
                    </div>
                    <div class="mb-3 <?= ($_POST['jenis_mutasi'] ?? '') === 'pergantian_komputer_rusak' ? '' : 'd-none'; ?>" data-mutasi-reference-wrapper>
                        <label class="form-label">Referensi Komputer Rusak</label>
                        <select name="komputer_client_id" class="form-select" data-mutasi-reference-input>
                            <option value="">Pilih komputer rusak yang akan diganti</option>
                            <?php foreach ($komputerRusakOptions as $computer): ?>
                                <option
                                    value="<?= $computer['id']; ?>"
                                    data-hostname="<?= e($computer['hostname']); ?>"
                                    data-ruangan="<?= e($computer['ruangan']); ?>"
                                    data-kondisi="<?= e($computer['kondisi']); ?>"
                                    <?= (int) ($_POST['komputer_client_id'] ?? 0) === (int) $computer['id'] ? 'selected' : ''; ?>
                                >
                                    <?= e($computer['hostname']); ?> - <?= e($computer['ruangan']); ?> - <?= e($computer['kondisi']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Wajib dipilih jika jenis mutasi adalah pergantian komputer rusak.</div>
                        <div class="alert alert-warning mt-3 mb-0 <?= (int) ($_POST['komputer_client_id'] ?? 0) > 0 ? '' : 'd-none'; ?>" data-mutasi-reference-detail>
                            <strong>Data komputer rusak yang akan diganti</strong>
                            <div class="mt-2 small">
                                <div><strong>Hostname:</strong> <span data-mutasi-detail-hostname><?= e($selectedComputer['hostname'] ?? ''); ?></span></div>
                                <div><strong>Ruangan:</strong> <span data-mutasi-detail-ruangan><?= e($selectedComputer['ruangan'] ?? ''); ?></span></div>
                                <div><strong>Kondisi:</strong> <span data-mutasi-detail-kondisi><?= e($selectedComputer['kondisi'] ?? ''); ?></span></div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jumlah Unit</label>
                        <input type="number" name="qty" class="form-control" min="1" value="<?= e($_POST['qty'] ?? '1'); ?>" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Tanggal</label>
                            <input type="date" name="tanggal" class="form-control" value="<?= e($_POST['tanggal'] ?? date('Y-m-d')); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jam</label>
                            <input type="time" name="jam" class="form-control" value="<?= e($_POST['jam'] ?? date('H:i')); ?>" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Nama Petugas</label>
                        <input type="text" name="nama_petugas" class="form-control" value="<?= e($_POST['nama_petugas'] ?? ($currentUser['nama'] ?? '')); ?>" required>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="3"><?= e($_POST['keterangan'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-4">Simpan Mutasi Komputer</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-4">
                <h5 class="mb-1">Riwayat Mutasi Komputer</h5>
                <p class="text-muted mb-0">Pantau seluruh pengiriman komputer baru dan pergantian komputer rusak.</p>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle datatable">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Jenis Mutasi</th>
                                <th>Barang</th>
                                <th>Ruangan</th>
                                <th>Referensi</th>
                                <th>Qty</th>
                                <th>Stok</th>
                                <th>Petugas</th>
                                <th>Tanggal</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mutasiRows as $index => $mutasi): ?>
                                <tr>
                                    <td><?= $index + 1; ?></td>
                                    <td>
                                        <span class="badge text-bg-<?= $mutasi['jenis_mutasi'] === 'komputer_baru' ? 'success' : 'warning'; ?>">
                                            <?= e($jenisMutasiOptions[$mutasi['jenis_mutasi']] ?? $mutasi['jenis_mutasi']); ?>
                                        </span>
                                        <div class="small text-muted"><?= e($mutasi['keterangan'] ?: '-'); ?></div>
                                    </td>
                                    <td>
                                        <strong><?= e($mutasi['nama_barang']); ?></strong>
                                        <div class="small text-muted"><?= e($mutasi['kode_barang']); ?> | <?= e($mutasi['label_barang'] ?: 'Lainnya'); ?> | <?= e($mutasi['kondisi_barang']); ?></div>
                                    </td>
                                    <td><?= e($mutasi['ruangan_nama']); ?></td>
                                    <td><?= e($mutasi['hostname_referensi'] ?: '-'); ?></td>
                                    <td><?= format_number($mutasi['qty']); ?></td>
                                    <td><?= format_number($mutasi['stok_sebelum']); ?> -> <?= format_number($mutasi['stok_sesudah']); ?></td>
                                    <td><?= e($mutasi['nama_petugas']); ?></td>
                                    <td><?= e(format_date_id($mutasi['tanggal'])); ?> <?= e(format_time_id($mutasi['jam'])); ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteMutasiModal<?= $mutasi['id']; ?>">Hapus</button>
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

<?php foreach ($mutasiRows as $mutasi): ?>
    <div class="modal fade" id="deleteMutasiModal<?= $mutasi['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Hapus Mutasi Komputer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="mutasi_id" value="<?= $mutasi['id']; ?>">
                        <p>Yakin ingin menghapus mutasi komputer ini?</p>
                        <div class="bg-light rounded-4 p-3 small">
                            <div><strong>Jenis:</strong> <?= e($jenisMutasiOptions[$mutasi['jenis_mutasi']] ?? $mutasi['jenis_mutasi']); ?></div>
                            <div><strong>Barang:</strong> <?= e($mutasi['nama_barang']); ?></div>
                            <div><strong>Ruangan:</strong> <?= e($mutasi['ruangan_nama']); ?></div>
                            <div><strong>Qty:</strong> <?= format_number($mutasi['qty']); ?></div>
                            <div><strong>Referensi:</strong> <?= e($mutasi['hostname_referensi'] ?: '-'); ?></div>
                        </div>
                        <div class="alert alert-warning mt-3 mb-0">
                            Menghapus mutasi akan mengembalikan stok barang dan menghapus histori keluar yang terkait.
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
