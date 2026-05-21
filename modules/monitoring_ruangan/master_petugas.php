<?php

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once __DIR__ . '/module.php';
require_module_access();

$pageTitle = 'Master Petugas Monitoring';
$pageSubtitle = 'Kelola petugas monitoring lengkap dengan NIP/NIK, jabatan, dan status aktif untuk kebutuhan audit.';
$monitoringNavActive = 'master_petugas';
$errors = [];

if (is_post()) {
    verify_csrf();
    $action = trim((string) ($_POST['action'] ?? ''));
    $id = (int) ($_POST['id'] ?? 0);
    $namaLengkap = monitoring_clean_text($_POST['nama_lengkap'] ?? '', 120);
    $nipNik = monitoring_clean_text($_POST['nip_nik'] ?? '', 50);
    $jabatan = monitoring_clean_text($_POST['jabatan'] ?? '', 120);
    $statusAktif = (int) ($_POST['status_aktif'] ?? 1) === 1 ? 1 : 0;

    if ($action !== 'delete') {
        if ($namaLengkap === '') {
            $errors[] = 'Nama lengkap petugas wajib diisi.';
        }

        if ($nipNik === '') {
            $errors[] = 'NIP/NIK petugas wajib diisi.';
        }

        if ($jabatan === '') {
            $errors[] = 'Jabatan petugas wajib diisi.';
        }
    }

    if (!$errors && in_array($action, ['create', 'update'], true)) {
        $duplicate = fetch_one(
            $pdo,
            'SELECT id FROM monitoring_master_petugas WHERE nip_nik = :nip_nik' . ($action === 'update' ? ' AND id <> :id' : '') . ' LIMIT 1',
            $action === 'update'
                ? ['nip_nik' => $nipNik, 'id' => $id]
                : ['nip_nik' => $nipNik]
        );

        if ($duplicate) {
            $errors[] = 'NIP/NIK sudah terdaftar pada master petugas monitoring.';
        }
    }

    if (!$errors) {
        if ($action === 'create') {
            $statement = $pdo->prepare(
                'INSERT INTO monitoring_master_petugas (nama_lengkap, nip_nik, jabatan, status_aktif, created_at, updated_at)
                 VALUES (:nama_lengkap, :nip_nik, :jabatan, :status_aktif, NOW(), NOW())'
            );
            $statement->execute([
                'nama_lengkap' => $namaLengkap,
                'nip_nik' => $nipNik,
                'jabatan' => $jabatan,
                'status_aktif' => $statusAktif,
            ]);

            set_flash('success', 'Master petugas monitoring berhasil ditambahkan.');
            redirect('modules/monitoring_ruangan/master_petugas.php');
        }

        if ($action === 'update' && $id > 0) {
            $statement = $pdo->prepare(
                'UPDATE monitoring_master_petugas
                 SET nama_lengkap = :nama_lengkap, nip_nik = :nip_nik, jabatan = :jabatan, status_aktif = :status_aktif, updated_at = NOW()
                 WHERE id = :id'
            );
            $statement->execute([
                'id' => $id,
                'nama_lengkap' => $namaLengkap,
                'nip_nik' => $nipNik,
                'jabatan' => $jabatan,
                'status_aktif' => $statusAktif,
            ]);

            set_flash('success', 'Master petugas monitoring berhasil diperbarui.');
            redirect('modules/monitoring_ruangan/master_petugas.php');
        }

        if ($action === 'delete' && $id > 0) {
            if (monitoring_staff_in_use($pdo, $id)) {
                set_flash('danger', 'Petugas tidak dapat dihapus karena sudah dipakai pada histori monitoring.');
            } else {
                $statement = $pdo->prepare('DELETE FROM monitoring_master_petugas WHERE id = :id');
                $statement->execute(['id' => $id]);
                set_flash('success', 'Master petugas monitoring berhasil dihapus.');
            }

            redirect('modules/monitoring_ruangan/master_petugas.php');
        }
    }
}

$staffRows = monitoring_get_staff($pdo);

require_once BASE_PATH . '/includes/layout_top.php';
require_once __DIR__ . '/_nav.php';
?>

<section class="monitoring-hero-surface mb-4">
    <div>
        <span class="monitoring-kicker">Master Data</span>
        <h3 class="monitoring-hero-title">Petugas monitoring tampil lebih profesional dan gampang dipelihara.</h3>
        <p class="monitoring-hero-text mb-0">Daftar petugas, NIP/NIK, dan jabatan sekarang dibungkus dengan tampilan yang lebih rapi dan sejalan dengan tema dashboard aplikasi.</p>
    </div>
    <div class="monitoring-hero-side">
        <div class="monitoring-quick-stats">
            <div class="monitoring-quick-stat">
                <span>Total Petugas</span>
                <strong><?= format_number(count($staffRows)); ?></strong>
            </div>
            <div class="monitoring-quick-stat">
                <span>Aktif</span>
                <strong><?= format_number(count(array_filter($staffRows, static fn (array $staffRow): bool => (int) $staffRow['status_aktif'] === 1))); ?></strong>
            </div>
        </div>
    </div>
</section>

<div class="card border-0 shadow-sm monitoring-surface-card monitoring-table-card">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap gap-3 pt-4 monitoring-card-header">
        <div>
            <h5 class="mb-1">Master Petugas Monitoring</h5>
            <p class="text-muted mb-0">Kelola petugas yang akan muncul di dropdown/searchable select form monitoring.</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMonitoringStaffModal">Tambah Petugas</button>
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
                        <th>Nama Lengkap</th>
                        <th>NIP/NIK</th>
                        <th>Jabatan</th>
                        <th>Status Aktif</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffRows as $index => $staffRow): ?>
                        <tr>
                            <td><?= $index + 1; ?></td>
                            <td><?= e($staffRow['nama_lengkap']); ?></td>
                            <td><?= e($staffRow['nip_nik']); ?></td>
                            <td><?= e($staffRow['jabatan']); ?></td>
                            <td>
                                <span class="badge text-bg-<?= (int) $staffRow['status_aktif'] === 1 ? 'success' : 'secondary'; ?>">
                                    <?= e(monitoring_active_label($staffRow['status_aktif'])); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editMonitoringStaffModal<?= (int) $staffRow['id']; ?>">Edit</button>
                                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteMonitoringStaffModal<?= (int) $staffRow['id']; ?>">Hapus</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addMonitoringStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Petugas Monitoring</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama_lengkap" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">NIP/NIK</label>
                        <input type="text" name="nip_nik" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jabatan</label>
                        <input type="text" name="jabatan" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Status Aktif</label>
                        <select name="status_aktif" class="form-select">
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
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

<?php foreach ($staffRows as $staffRow): ?>
    <div class="modal fade" id="editMonitoringStaffModal<?= (int) $staffRow['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Petugas Monitoring</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= (int) $staffRow['id']; ?>">
                        <div class="mb-3">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" class="form-control" value="<?= e($staffRow['nama_lengkap']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">NIP/NIK</label>
                            <input type="text" name="nip_nik" class="form-control" value="<?= e($staffRow['nip_nik']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Jabatan</label>
                            <input type="text" name="jabatan" class="form-control" value="<?= e($staffRow['jabatan']); ?>" required>
                        </div>
                        <div>
                            <label class="form-label">Status Aktif</label>
                            <select name="status_aktif" class="form-select">
                                <option value="1" <?= (int) $staffRow['status_aktif'] === 1 ? 'selected' : ''; ?>>Aktif</option>
                                <option value="0" <?= (int) $staffRow['status_aktif'] === 0 ? 'selected' : ''; ?>>Nonaktif</option>
                            </select>
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

    <div class="modal fade" id="deleteMonitoringStaffModal<?= (int) $staffRow['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Hapus Petugas Monitoring</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $staffRow['id']; ?>">
                        <p class="mb-0">Yakin ingin menghapus petugas <strong><?= e($staffRow['nama_lengkap']); ?></strong>?</p>
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
