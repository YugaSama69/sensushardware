<?php

require_once dirname(__DIR__, 2) . '/config/app.php';

$pageTitle = 'Data Barang';
$errors = [];
$filterKondisi = trim($_GET['kondisi'] ?? '');
$labelCollapseOpen = ($_GET['labels'] ?? '') === 'open';
$labelMasters = get_master_barang_labels($pdo);
$labelColorMap = get_barang_label_map($pdo);
$labelOptions = array_map(static fn (array $row): string => (string) ($row['nama_label'] ?? ''), $labelMasters);

if (is_post()) {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if (in_array($action, ['create_label', 'update_label', 'delete_label'], true)) {
        $labelId = (int) ($_POST['label_id'] ?? 0);
        $namaLabel = trim($_POST['nama_label'] ?? '');
        $warnaLabel = normalize_hex_color($_POST['warna_label'] ?? '#64748B');

        if ($action === 'delete_label' && $labelId > 0) {
            $label = fetch_one($pdo, 'SELECT * FROM master_label_barang WHERE id = :id LIMIT 1', ['id' => $labelId]);

            if (!$label) {
                set_flash('danger', 'Label barang tidak ditemukan.');
                redirect('modules/barang/index.php?labels=open');
            }

            $usedCount = (int) fetch_scalar($pdo, 'SELECT COUNT(*) FROM barang WHERE label_barang = :label', [
                'label' => $label['nama_label'],
            ]);

            if ($usedCount > 0) {
                set_flash('danger', 'Label tidak dapat dihapus karena masih dipakai oleh data barang.');
                redirect('modules/barang/index.php?labels=open');
            }

            $statement = $pdo->prepare('DELETE FROM master_label_barang WHERE id = :id');
            $statement->execute(['id' => $labelId]);
            set_flash('success', 'Label barang berhasil dihapus.');
            redirect('modules/barang/index.php?labels=open');
        }

        if ($namaLabel === '') {
            $errors[] = 'Nama label wajib diisi.';
        }

        $duplicateLabel = fetch_one(
            $pdo,
            'SELECT id FROM master_label_barang WHERE LOWER(nama_label) = LOWER(:nama_label) AND id <> :id LIMIT 1',
            [
                'nama_label' => $namaLabel,
                'id' => $action === 'update_label' ? $labelId : 0,
            ]
        );

        if ($duplicateLabel) {
            $errors[] = 'Nama label sudah digunakan.';
        }

        if ($errors) {
            $labelCollapseOpen = true;
        }

        if (!$errors && $action === 'create_label') {
            $statement = $pdo->prepare('
                INSERT INTO master_label_barang (nama_label, warna_label, created_at)
                VALUES (:nama_label, :warna_label, NOW())
            ');
            $statement->execute([
                'nama_label' => $namaLabel,
                'warna_label' => $warnaLabel,
            ]);
            set_flash('success', 'Label barang berhasil ditambahkan.');
            redirect('modules/barang/index.php?labels=open');
        }

        if (!$errors && $action === 'update_label' && $labelId > 0) {
            $existingLabel = fetch_one($pdo, 'SELECT * FROM master_label_barang WHERE id = :id LIMIT 1', ['id' => $labelId]);

            if (!$existingLabel) {
                $errors[] = 'Label barang tidak ditemukan.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $statement = $pdo->prepare('
                        UPDATE master_label_barang
                        SET nama_label = :nama_label,
                            warna_label = :warna_label
                        WHERE id = :id
                    ');
                    $statement->execute([
                        'id' => $labelId,
                        'nama_label' => $namaLabel,
                        'warna_label' => $warnaLabel,
                    ]);

                    if ($existingLabel['nama_label'] !== $namaLabel) {
                        $syncBarang = $pdo->prepare('UPDATE barang SET label_barang = :label_baru WHERE label_barang = :label_lama');
                        $syncBarang->execute([
                            'label_baru' => $namaLabel,
                            'label_lama' => $existingLabel['nama_label'],
                        ]);
                    }

                    $pdo->commit();
                    set_flash('success', 'Label barang berhasil diperbarui.');
                    redirect('modules/barang/index.php?labels=open');
                } catch (Throwable $throwable) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = 'Gagal memperbarui label barang.';
                    $labelCollapseOpen = true;
                }
            }
        }
    }

    if (in_array($action, ['create', 'update', 'delete'], true)) {
        $id = (int) ($_POST['id'] ?? 0);
        $namaBarang = trim($_POST['nama_barang'] ?? '');
        $kodeBarang = $namaBarang !== '' ? generate_barang_code_from_name($pdo, $namaBarang, $action === 'update' ? $id : null) : '';
        $tanggalInventaris = trim($_POST['tanggal_inventaris'] ?? '');
        $qty = (int) ($_POST['qty'] ?? 0);
        $labelBarang = trim($_POST['label_barang'] ?? '');
        $kondisi = trim($_POST['kondisi'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');
        $masterLabelNames = array_map(static fn (array $row): string => (string) ($row['nama_label'] ?? ''), $labelMasters);
        $tanggalInventarisObject = null;
        $tahunInventaris = '';

        if ($tanggalInventaris !== '') {
            $tanggalInventarisObject = DateTime::createFromFormat('Y-m-d', $tanggalInventaris);

            if (
                $tanggalInventarisObject instanceof DateTime
                && $tanggalInventarisObject->format('Y-m-d') === $tanggalInventaris
            ) {
                $tahunInventaris = $tanggalInventarisObject->format('Y');
            } else {
                $tanggalInventarisObject = null;
            }
        }

        if ($action !== 'delete') {
            if ($kodeBarang === '') {
                $errors[] = 'Kode barang wajib diisi.';
            }

            if ($namaBarang === '') {
                $errors[] = 'Nama barang wajib diisi.';
            }

            if ($tanggalInventarisObject === null) {
                $errors[] = 'Tanggal inventaris wajib diisi dengan format tanggal yang valid.';
            }

            if ($qty < 0) {
                $errors[] = 'Qty tidak boleh negatif.';
            }

            if ($labelBarang === '') {
                $errors[] = 'Label jenis barang wajib dipilih.';
            } elseif (!in_array($labelBarang, $masterLabelNames, true)) {
                $errors[] = 'Label jenis barang harus dipilih dari master label barang.';
            }

            if (!in_array($kondisi, ['Baik', 'Rusak', 'Perbaikan'], true)) {
                $errors[] = 'Kondisi barang tidak valid.';
            }
        }

        if (!$errors && $action === 'create') {
            $statement = $pdo->prepare('
                INSERT INTO barang (kode_barang, nama_barang, ruangan_id, tahun_inventaris, qty, label_barang, kondisi, keterangan, created_at)
                VALUES (:kode_barang, :nama_barang, :ruangan_id, :tahun_inventaris, :qty, :label_barang, :kondisi, :keterangan, :created_at)
            ');
            $statement->execute([
                'kode_barang' => $kodeBarang,
                'nama_barang' => $namaBarang,
                'ruangan_id' => null,
                'tahun_inventaris' => $tahunInventaris,
                'qty' => $qty,
                'label_barang' => $labelBarang,
                'kondisi' => $kondisi,
                'keterangan' => $keterangan,
                'created_at' => $tanggalInventaris . ' 00:00:00',
            ]);
            set_flash('success', 'Data barang berhasil ditambahkan.');
            redirect('modules/barang/index.php');
        }

        if (!$errors && $action === 'update' && $id > 0) {
            $statement = $pdo->prepare('
                UPDATE barang
                SET kode_barang = :kode_barang,
                    nama_barang = :nama_barang,
                    ruangan_id = :ruangan_id,
                    tahun_inventaris = :tahun_inventaris,
                    qty = :qty,
                    label_barang = :label_barang,
                    kondisi = :kondisi,
                    keterangan = :keterangan,
                    created_at = :created_at
                WHERE id = :id
            ');
            $statement->execute([
                'id' => $id,
                'kode_barang' => $kodeBarang,
                'nama_barang' => $namaBarang,
                'ruangan_id' => null,
                'tahun_inventaris' => $tahunInventaris,
                'qty' => $qty,
                'label_barang' => $labelBarang,
                'kondisi' => $kondisi,
                'keterangan' => $keterangan,
                'created_at' => $tanggalInventaris . ' 00:00:00',
            ]);
            set_flash('success', 'Data barang berhasil diperbarui.');
            redirect('modules/barang/index.php');
        }

        if (!$errors && $action === 'delete' && $id > 0) {
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

$labelMasters = get_master_barang_labels($pdo);
$labelColorMap = get_barang_label_map($pdo);
$labelOptions = array_map(static fn (array $row): string => (string) ($row['nama_label'] ?? ''), $labelMasters);

$itemParams = [];
$itemSql = 'SELECT b.* FROM barang b WHERE 1=1';

if ($filterKondisi !== '') {
    $itemSql .= ' AND b.kondisi = :kondisi';
    $itemParams['kondisi'] = $filterKondisi;
}

$itemSql .= ' ORDER BY b.nama_barang ASC';
$items = fetch_all($pdo, $itemSql, $itemParams);

require_once BASE_PATH . '/includes/layout_top.php';
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 pt-4">
        <h5 class="mb-1">Filter Data Barang</h5>
        <p class="text-muted mb-0">Saring daftar barang berdasarkan kondisi inventaris.</p>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Kondisi Barang</label>
                <select name="kondisi" class="form-select">
                    <option value="">Semua kondisi</option>
                    <?php foreach (['Baik', 'Rusak', 'Perbaikan'] as $condition): ?>
                        <option value="<?= e($condition); ?>" <?= $filterKondisi === $condition ? 'selected' : ''; ?>><?= e($condition); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8 d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary">Terapkan</button>
                <a href="<?= e(url('modules/barang/index.php')); ?>" class="btn btn-light">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap gap-3 pt-4">
        <div>
            <h5 class="mb-1">Master Label Barang</h5>
            <p class="text-muted mb-0">Buat nama label dan pilih warna di sini. Form tambah barang hanya memilih dari master label ini.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addLabelModal">Tambah Label</button>
            <button class="btn btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#masterLabelBarangCollapse" aria-expanded="false" aria-controls="masterLabelBarangCollapse">
                Lihat / Sembunyikan Label
            </button>
        </div>
    </div>
    <div class="collapse <?= $labelCollapseOpen ? 'show' : ''; ?>" id="masterLabelBarangCollapse">
        <div class="card-body pt-0">
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
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Label</th>
                            <th>Warna</th>
                            <th>Dipakai</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$labelMasters): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada master label barang.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($labelMasters as $index => $labelMaster): ?>
                            <?php
                            $labelName = (string) ($labelMaster['nama_label'] ?? '');
                            $labelColor = $labelColorMap[$labelName] ?? '#64748B';
                            $labelTextColor = get_contrast_text_color($labelColor);
                            $usedCount = (int) fetch_scalar($pdo, 'SELECT COUNT(*) FROM barang WHERE label_barang = :label', ['label' => $labelName]);
                            ?>
                            <tr>
                                <td><?= $index + 1; ?></td>
                                <td><?= e($labelName); ?></td>
                                <td>
                                    <span class="badge" style="background-color: <?= e($labelColor); ?>; color: <?= e($labelTextColor); ?>;">
                                        <?= e($labelColor); ?>
                                    </span>
                                </td>
                                <td><?= format_number($usedCount); ?> barang</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editLabelModal<?= $labelMaster['id']; ?>">Edit</button>
                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteLabelModal<?= $labelMaster['id']; ?>">Hapus</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap gap-3 pt-4">
        <div>
            <h5 class="mb-1">Master Data Barang</h5>
            <p class="text-muted mb-0">Kelola master inventaris hardware dan elektronik tanpa penguncian ke ruangan.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal" <?= !$labelOptions ? 'disabled' : ''; ?>>Tambah Barang</button>
    </div>
    <div class="card-body">
        <?php if (!$labelOptions): ?>
            <div class="alert alert-warning">
                Tambahkan master label barang terlebih dahulu sebelum menambah data barang baru.
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle datatable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kode Barang</th>
                        <th>Nama Barang</th>
                        <th>Label</th>
                        <th>Tanggal Inventaris</th>
                        <th>Qty</th>
                        <th>Kondisi</th>
                        <th>Keterangan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $index => $item): ?>
                        <?php
                        $itemLabel = (string) ($item['label_barang'] ?? 'Lainnya');
                        $itemLabelColor = $labelColorMap[$itemLabel] ?? '#64748B';
                        $itemLabelTextColor = get_contrast_text_color($itemLabelColor);
                        ?>
                        <tr>
                            <td><?= $index + 1; ?></td>
                            <td><span class="fw-semibold"><?= e($item['kode_barang']); ?></span></td>
                            <td><strong><?= e($item['nama_barang']); ?></strong></td>
                            <td>
                                <span class="badge" style="background-color: <?= e($itemLabelColor); ?>; color: <?= e($itemLabelTextColor); ?>;">
                                    <?= e($itemLabel); ?>
                                </span>
                            </td>
                            <td>
                                <span class="fw-semibold"><?= e(format_date_id((string) ($item['created_at'] ?? ''))); ?></span>
                            </td>
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

<div class="modal fade" id="addLabelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Label Barang</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="create_label">
                    <div class="mb-3">
                        <label class="form-label">Nama Label</label>
                        <input type="text" name="nama_label" class="form-control" placeholder="Contoh: PC Desktop" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Warna Label</label>
                        <input type="color" name="warna_label" class="form-control form-control-color w-100" value="#64748B" title="Pilih warna label">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Label</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($labelMasters as $labelMaster): ?>
    <div class="modal fade" id="editLabelModal<?= $labelMaster['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Label Barang</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="update_label">
                        <input type="hidden" name="label_id" value="<?= $labelMaster['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Nama Label</label>
                            <input type="text" name="nama_label" class="form-control" value="<?= e($labelMaster['nama_label']); ?>" required>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Warna Label</label>
                            <input type="color" name="warna_label" class="form-control form-control-color w-100" value="<?= e(normalize_hex_color($labelMaster['warna_label'])); ?>" title="Pilih warna label">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Update Label</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteLabelModal<?= $labelMaster['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Hapus Label Barang</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="delete_label">
                        <input type="hidden" name="label_id" value="<?= $labelMaster['id']; ?>">
                        <p class="mb-0">Yakin ingin menghapus label <strong><?= e($labelMaster['nama_label']); ?></strong>?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Hapus Label</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

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
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Kode Barang</label>
                            <input type="text" name="kode_barang" class="form-control" value="" placeholder="Otomatis mengikuti nama barang" readonly data-barang-code-target>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Nama Barang</label>
                            <input type="text" name="nama_barang" class="form-control" required data-barang-name-source>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tanggal Inventaris</label>
                            <input type="date" name="tanggal_inventaris" class="form-control" value="<?= e(date('Y-m-d')); ?>" required>
                            <div class="form-text">Isi manual tanggal inventaris lengkap.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Jumlah Barang (Qty)</label>
                            <input type="number" name="qty" class="form-control" min="0" value="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Label Jenis Barang</label>
                            <select name="label_barang" class="form-select" required>
                                <option value="">Pilih label barang</option>
                                <?php foreach ($labelOptions as $labelOption): ?>
                                    <option value="<?= e($labelOption); ?>"><?= e($labelOption); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Kelola nama dan warna label dari master label barang.</div>
                        </div>
                        <div class="col-md-6">
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
                            <div class="col-md-4">
                                <label class="form-label">Kode Barang</label>
                                <input type="text" name="kode_barang" class="form-control" value="<?= e(generate_barang_code_from_name($pdo, $item['nama_barang'], (int) $item['id'])); ?>" readonly data-barang-code-target>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Nama Barang</label>
                                <input type="text" name="nama_barang" class="form-control" value="<?= e($item['nama_barang']); ?>" required data-barang-name-source>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tanggal Inventaris</label>
                                <input type="date" name="tanggal_inventaris" class="form-control" value="<?= e(date('Y-m-d', strtotime((string) ($item['created_at'] ?? 'now')))); ?>" required>
                                <div class="form-text">Isi manual tanggal inventaris lengkap.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Jumlah Barang (Qty)</label>
                                <input type="number" name="qty" class="form-control" min="0" value="<?= e($item['qty']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Label Jenis Barang</label>
                                <select name="label_barang" class="form-select" required>
                                    <option value="">Pilih label barang</option>
                                    <?php foreach ($labelOptions as $labelOption): ?>
                                        <option value="<?= e($labelOption); ?>" <?= ($item['label_barang'] ?? '') === $labelOption ? 'selected' : ''; ?>><?= e($labelOption); ?></option>
                                    <?php endforeach; ?>
                                    <?php if (($item['label_barang'] ?? '') !== '' && !in_array($item['label_barang'], $labelOptions, true)): ?>
                                        <option value="<?= e($item['label_barang']); ?>" selected><?= e($item['label_barang']); ?></option>
                                    <?php endif; ?>
                                </select>
                                <div class="form-text">Mutasi komputer hanya memakai label `PC Desktop`, `AIO`, atau `Laptop`.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Kondisi Barang</label>
                                <select name="kondisi" class="form-select" required>
                                    <?php foreach (['Baik', 'Rusak', 'Perbaikan'] as $condition): ?>
                                        <option value="<?= e($condition); ?>" <?= $item['kondisi'] === $condition ? 'selected' : ''; ?>><?= e($condition); ?></option>
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
