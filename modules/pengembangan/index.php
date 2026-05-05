<?php

require_once dirname(__DIR__, 2) . '/config/app.php';

$pageTitle = 'Laporan Pembangunan dan Pengembangan Aplikasi Rumah Sakit Welas Asih';
$errors = [];
$currentUser = current_user();
$inputUser = trim((string) ($currentUser['username'] ?? $currentUser['nama'] ?? 'Administrator'));
$isAdminUser = is_admin_user();
$canManageAllPengembangan = $isAdminUser || strtolower((string) ($currentUser['username'] ?? '')) === 'noval';
$canEditInputUser = $canManageAllPengembangan;
$canManageBidangUnit = $canManageAllPengembangan;
$filters = normalize_pengembangan_filters($_GET);
$bidangUnitOptions = array_map(
    static fn (array $row): string => (string) ($row['nama_unit'] ?? ''),
    fetch_all($pdo, 'SELECT nama_unit FROM master_bidang_unit_pengembangan ORDER BY nama_unit ASC')
);

if (is_post()) {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $newBidangUnit = trim((string) ($_POST['nama_unit'] ?? ''));
    $id = (int) ($_POST['id'] ?? 0);
    $bulanTahunInput = trim($_POST['bulan_tahun'] ?? '');
    $namaKegiatan = trim($_POST['nama_kegiatan'] ?? '');
    $bidangUnit = trim($_POST['bidang_unit'] ?? '');
    $capaianInput = trim((string) ($_POST['capaian'] ?? ''));
    $capaianValue = str_replace(['%', ','], ['', '.'], $capaianInput);
    $capaian = $capaianValue;
    $bulanTahun = $bulanTahunInput !== '' ? $bulanTahunInput . '-01' : '';
    $existingRow = null;
    $updatedInputUser = $inputUser;

    if ($action === 'create_bidang_unit') {
        if (!$canManageBidangUnit) {
            set_flash('danger', 'Hanya admin dan noval yang bisa menambah Bidang/Bagian/Unit.');
            redirect('modules/pengembangan/index.php');
        }

        if ($newBidangUnit === '') {
            set_flash('danger', 'Nama Bidang/Bagian/Unit wajib diisi.');
            redirect('modules/pengembangan/index.php');
        }

        $existingBidangUnit = fetch_one(
            $pdo,
            'SELECT id FROM master_bidang_unit_pengembangan WHERE nama_unit = :nama_unit LIMIT 1',
            ['nama_unit' => $newBidangUnit]
        );

        if ($existingBidangUnit) {
            set_flash('warning', 'Bidang/Bagian/Unit tersebut sudah ada.');
            redirect('modules/pengembangan/index.php');
        }

        $statement = $pdo->prepare('INSERT INTO master_bidang_unit_pengembangan (nama_unit) VALUES (:nama_unit)');
        $statement->execute(['nama_unit' => $newBidangUnit]);
        set_flash('success', 'Bidang/Bagian/Unit berhasil ditambahkan.');
        redirect('modules/pengembangan/index.php');
    }

    if (in_array($action, ['update', 'delete'], true) && $id > 0) {
        $existingRow = fetch_one($pdo, 'SELECT id, input_user FROM laporan_pengembangan_aplikasi WHERE id = :id', ['id' => $id]);

        if (!$existingRow) {
            set_flash('danger', 'Data laporan pengembangan tidak ditemukan.');
            redirect('modules/pengembangan/index.php');
        }

        if (!$canManageAllPengembangan && ($existingRow['input_user'] ?? '') !== $inputUser) {
            set_flash('danger', 'Laporan hanya bisa diedit atau dihapus oleh user yang membuatnya.');
            redirect('modules/pengembangan/index.php');
        }

        $updatedInputUser = $canEditInputUser
            ? trim((string) ($_POST['input_user'] ?? ($existingRow['input_user'] ?? $inputUser)))
            : trim((string) ($existingRow['input_user'] ?? $inputUser));
    }

    if ($action !== 'delete') {
        if (!preg_match('/^\d{4}-\d{2}$/', $bulanTahunInput)) {
            $errors[] = 'Bulan Tahun wajib diisi dengan format bulan yang valid.';
        }

        if ($namaKegiatan === '') {
            $errors[] = 'Nama kegiatan wajib diisi.';
        }

        if ($bidangUnit === '') {
            $errors[] = 'Bidang/Bagian/Unit wajib diisi.';
        } elseif (!in_array($bidangUnit, $bidangUnitOptions, true)) {
            $errors[] = 'Bidang/Bagian/Unit harus dipilih dari daftar yang tersedia.';
        }

        if ($capaianInput === '') {
            $errors[] = 'Capaian wajib diisi.';
        } elseif (!is_numeric($capaianValue)) {
            $errors[] = 'Capaian harus berupa angka.';
        } elseif ((float) $capaianValue < 0 || (float) $capaianValue > 100) {
            $errors[] = 'Capaian harus diisi antara 0 sampai 100.';
        } else {
            $capaian = rtrim(rtrim(number_format((float) $capaianValue, 2, '.', ''), '0'), '.');
        }

        if ($action === 'update' && $canEditInputUser && $updatedInputUser === '') {
            $errors[] = 'User input wajib dipilih.';
        }
    }

    if (!$errors) {
        if ($action === 'create') {
            $statement = $pdo->prepare('
                INSERT INTO laporan_pengembangan_aplikasi (bulan_tahun, nama_kegiatan, bidang_unit, capaian, input_user)
                VALUES (:bulan_tahun, :nama_kegiatan, :bidang_unit, :capaian, :input_user)
            ');
            $statement->execute([
                'bulan_tahun' => $bulanTahun,
                'nama_kegiatan' => $namaKegiatan,
                'bidang_unit' => $bidangUnit,
                'capaian' => $capaian,
                'input_user' => $inputUser,
            ]);
            set_flash('success', 'Laporan pengembangan berhasil ditambahkan.');
            redirect('modules/pengembangan/index.php');
        }

        if ($action === 'update' && $id > 0) {
            $statement = $pdo->prepare('
                UPDATE laporan_pengembangan_aplikasi
                SET bulan_tahun = :bulan_tahun,
                    nama_kegiatan = :nama_kegiatan,
                    bidang_unit = :bidang_unit,
                    capaian = :capaian,
                    input_user = :input_user
                WHERE id = :id
            ');
            $statement->execute([
                'id' => $id,
                'bulan_tahun' => $bulanTahun,
                'nama_kegiatan' => $namaKegiatan,
                'bidang_unit' => $bidangUnit,
                'capaian' => $capaian,
                'input_user' => $updatedInputUser,
            ]);
            set_flash('success', 'Laporan pengembangan berhasil diperbarui.');
            redirect('modules/pengembangan/index.php');
        }
    }

    if ($action === 'delete' && $id > 0) {
        $statement = $pdo->prepare('DELETE FROM laporan_pengembangan_aplikasi WHERE id = :id');
        $statement->execute(['id' => $id]);
        set_flash('success', 'Laporan pengembangan berhasil dihapus.');
        redirect('modules/pengembangan/index.php');
    }
}

$rows = get_pengembangan_rows($pdo, $filters);
$filterOptions = get_pengembangan_filter_options($pdo);
$exportQuery = build_query_string($filters);
$userInputOptions = fetch_all(
    $pdo,
    'SELECT username
     FROM users
     WHERE role IN ("admin", "pengembangan")
     ORDER BY username ASC'
);

require_once BASE_PATH . '/includes/layout_top.php';
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 pt-4">
        <h5 class="mb-1">Filter Laporan Pengembangan</h5>
        <p class="text-muted mb-0">Saring data berdasarkan Bulan Tahun dan user penginput, lalu export sesuai hasil filter.</p>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Bulan Tahun</label>
                <input type="month" name="bulan_tahun" class="form-control" value="<?= e($filters['bulan_tahun']); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">User Input</label>
                <select name="input_user" class="form-select">
                    <option value="">Semua User</option>
                    <?php foreach ($filterOptions['input_user'] as $userOption): ?>
                        <option value="<?= e($userOption); ?>" <?= $filters['input_user'] === $userOption ? 'selected' : ''; ?>>
                            <?= e($userOption); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary">Terapkan</button>
                <a href="<?= e(url('modules/pengembangan/index.php')); ?>" class="btn btn-light">Reset</a>
                <a href="<?= e(url('modules/pengembangan/export_excel.php' . ($exportQuery !== '' ? '?' . $exportQuery : ''))); ?>" class="btn btn-success">Export Excel</a>
                <a href="<?= e(url('modules/pengembangan/export_pdf.php' . ($exportQuery !== '' ? '?' . $exportQuery : ''))); ?>" class="btn btn-outline-danger" target="_blank">Export PDF</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap gap-3 pt-4">
        <div>
            <h5 class="mb-1">Laporan Pembangunan dan Pengembangan Aplikasi Rumah Sakit Welas Asih</h5>
            <p class="text-muted mb-0">Catat kegiatan pembangunan dan pengembangan aplikasi per bulan.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($canManageBidangUnit): ?>
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addBidangUnitModal">Tambah Bidang/Bagian/Unit</button>
            <?php endif; ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPengembanganModal">Tambah Laporan</button>
        </div>
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
                        <th>Bulan Tahun</th>
                        <th>Nama Kegiatan</th>
                        <th>Bidang/Bagian/Unit</th>
                        <th>Capaian</th>
                        <th>User Input</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $index => $row): ?>
                        <?php $canManage = $canManageAllPengembangan || ($row['input_user'] ?? '') === $inputUser; ?>
                        <tr>
                            <td><?= $index + 1; ?></td>
                            <td><span class="fw-semibold"><?= e(format_month_year_id($row['bulan_tahun'])); ?></span></td>
                            <td><?= e($row['nama_kegiatan']); ?></td>
                            <td><?= e($row['bidang_unit']); ?></td>
                            <td><?= e(format_percentage($row['capaian'])); ?></td>
                            <td><span class="badge text-bg-light"><?= e($row['input_user'] ?: '-'); ?></span></td>
                            <td class="text-end">
                                <?php if ($canManage): ?>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPengembanganModal<?= $row['id']; ?>">Edit</button>
                                    <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deletePengembanganModal<?= $row['id']; ?>">Hapus</button>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Read only</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addPengembanganModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Laporan Pengembangan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="create">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Bulan Tahun</label>
                            <input type="month" name="bulan_tahun" class="form-control" value="<?= date('Y-m'); ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Nama Kegiatan</label>
                            <input type="text" name="nama_kegiatan" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Bidang/Bagian/Unit</label>
                            <select name="bidang_unit" class="form-select" required>
                                <option value="">Pilih Bidang/Bagian/Unit</option>
                                <?php foreach ($bidangUnitOptions as $bidangOption): ?>
                                    <option value="<?= e($bidangOption); ?>"><?= e($bidangOption); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Capaian (%)</label>
                            <input type="number" name="capaian" class="form-control" min="0" max="100" step="0.01" required>
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

<?php if ($canManageBidangUnit): ?>
    <div class="modal fade" id="addBidangUnitModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Tambah Bidang/Bagian/Unit</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="create_bidang_unit">
                        <div>
                            <label class="form-label">Nama Bidang/Bagian/Unit</label>
                            <input type="text" name="nama_unit" class="form-control" placeholder="Contoh: IT / SIMRS" required>
                            <div class="form-text">Daftar ini akan menjadi pilihan dropdown untuk semua user di menu laporan pengembangan.</div>
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
<?php endif; ?>

<?php foreach ($rows as $row): ?>
    <?php if ($canManageAllPengembangan || ($row['input_user'] ?? '') === $inputUser): ?>
        <div class="modal fade" id="editPengembanganModal<?= $row['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="post">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Laporan Pengembangan</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?= $row['id']; ?>">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Bulan Tahun</label>
                                    <input type="month" name="bulan_tahun" class="form-control" value="<?= e(date('Y-m', strtotime($row['bulan_tahun']))); ?>" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Nama Kegiatan</label>
                                    <input type="text" name="nama_kegiatan" class="form-control" value="<?= e($row['nama_kegiatan']); ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Bidang/Bagian/Unit</label>
                                    <select name="bidang_unit" class="form-select" required>
                                        <option value="">Pilih Bidang/Bagian/Unit</option>
                                        <?php foreach ($bidangUnitOptions as $bidangOption): ?>
                                            <option value="<?= e($bidangOption); ?>" <?= $row['bidang_unit'] === $bidangOption ? 'selected' : ''; ?>>
                                                <?= e($bidangOption); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Capaian (%)</label>
                                    <input type="number" name="capaian" class="form-control" min="0" max="100" step="0.01" value="<?= e(str_replace(',', '.', str_replace('%', '', (string) $row['capaian']))); ?>" required>
                                </div>
                                <?php if ($canEditInputUser): ?>
                                    <div class="col-12">
                                        <label class="form-label">User Input</label>
                                        <select name="input_user" class="form-select" required>
                                            <?php foreach ($userInputOptions as $userOption): ?>
                                                <option value="<?= e($userOption['username']); ?>" <?= ($row['input_user'] ?? '') === $userOption['username'] ? 'selected' : ''; ?>>
                                                    <?= e($userOption['username']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>
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

        <div class="modal fade" id="deletePengembanganModal<?= $row['id']; ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="post">
                        <div class="modal-header">
                            <h5 class="modal-title">Hapus Laporan Pengembangan</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $row['id']; ?>">
                            <p class="mb-0">Yakin ingin menghapus laporan <strong><?= e($row['nama_kegiatan']); ?></strong>?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger">Hapus</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php require_once BASE_PATH . '/includes/layout_bottom.php'; ?>
