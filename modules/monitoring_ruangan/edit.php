<?php

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once __DIR__ . '/module.php';
require_module_access();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$row = $id > 0 ? monitoring_find_row($pdo, $id) : null;

if (!$row) {
    set_flash('warning', 'Data monitoring tidak ditemukan.');
    redirect('modules/monitoring_ruangan/histori.php');
}

$pageTitle = 'Edit Monitoring';
$pageSubtitle = 'Perbarui data monitoring yang sudah tersimpan tanpa mengubah jejak paraf dan metadata audit.';
$monitoringNavActive = 'histori';
$rooms = monitoring_get_rooms($pdo, true);
$staff = monitoring_get_staff($pdo, true);
$errors = [];
$old = [
    'tanggal' => (string) $row['tanggal'],
    'ruangan_id' => (string) $row['ruangan_id'],
    'suhu' => (string) $row['suhu'],
    'akses_masuk' => (string) $row['akses_masuk'],
    'petugas_id' => (string) $row['petugas_id'],
    'catatan' => (string) ($row['catatan'] ?? ''),
];

if (is_post()) {
    verify_csrf();
    $old = [
        'tanggal' => trim((string) ($_POST['tanggal'] ?? $row['tanggal'])),
        'ruangan_id' => trim((string) ($_POST['ruangan_id'] ?? $row['ruangan_id'])),
        'suhu' => trim((string) ($_POST['suhu'] ?? $row['suhu'])),
        'akses_masuk' => trim((string) ($_POST['akses_masuk'] ?? $row['akses_masuk'])),
        'petugas_id' => trim((string) ($_POST['petugas_id'] ?? $row['petugas_id'])),
        'catatan' => trim((string) ($_POST['catatan'] ?? ($row['catatan'] ?? ''))),
    ];

    if (monitoring_update_entry($pdo, $id, $_POST, $errors)) {
        set_flash('success', 'Data monitoring berhasil diperbarui.');
        redirect('modules/monitoring_ruangan/detail.php?id=' . $id);
    }
}

require_once BASE_PATH . '/includes/layout_top.php';
require_once __DIR__ . '/_nav.php';
?>

<section class="monitoring-hero-surface mb-4">
    <div>
        <span class="monitoring-kicker">Edit Histori</span>
        <h3 class="monitoring-hero-title">Perbarui data monitoring tanpa input ulang dari awal.</h3>
        <p class="monitoring-hero-text mb-0">Gunakan halaman ini untuk membetulkan tanggal, ruangan, suhu, akses masuk, petugas, atau catatan. Paraf sekarang bisa dilengkapi langsung dari modal pada histori.</p>
    </div>
    <div class="monitoring-hero-side">
        <div class="monitoring-quick-stats">
            <div class="monitoring-quick-stat">
                <span>Monitoring ID</span>
                <strong>#<?= (int) $row['id']; ?></strong>
            </div>
            <div class="monitoring-quick-stat">
                <span>Status Saat Ini</span>
                <strong><?= e(monitoring_status_label($row['status'])); ?></strong>
            </div>
        </div>
    </div>
</section>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm monitoring-surface-card h-100">
            <div class="card-header bg-white border-0 pt-4 pb-0 monitoring-card-header">
                <h5 class="mb-1">Ringkasan Saat Ini</h5>
                <p class="text-muted mb-0">Cek data lama sebelum mengubah isinya.</p>
            </div>
            <div class="card-body">
                <div class="monitoring-detail-item">
                    <span>Tanggal</span>
                    <strong><?= e(date('d/m/Y', strtotime($row['tanggal']))); ?></strong>
                </div>
                <div class="monitoring-detail-item">
                    <span>Ruangan</span>
                    <strong><?= e($row['nama_ruangan']); ?></strong>
                </div>
                <div class="monitoring-detail-item">
                    <span>Petugas</span>
                    <strong><?= e($row['nama_lengkap']); ?></strong>
                </div>
                <div class="monitoring-detail-item">
                    <span>Suhu / Akses</span>
                    <strong><?= e(monitoring_temperature_label($row['suhu'])); ?> • <?= e(monitoring_access_label($row['akses_masuk'])); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card border-0 shadow-sm monitoring-surface-card">
            <div class="card-header bg-white border-0 pt-4 pb-0 monitoring-card-header">
                <h5 class="mb-1">Edit Data Monitoring</h5>
                <p class="text-muted mb-0">Jam monitoring tetap disimpan internal dan tidak ditampilkan di form edit ini.</p>
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

                <form method="post" class="monitoring-form-shell p-0">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">

                    <div class="monitoring-section-card">
                        <div class="monitoring-section-title">A. Tanggal Monitoring</div>
                        <label class="form-label">Tanggal</label>
                        <input type="date" name="tanggal" class="form-control form-control-lg" value="<?= e($old['tanggal']); ?>" required>
                    </div>

                    <div class="monitoring-section-card">
                        <div class="monitoring-section-title">B. Kondisi Ruangan</div>
                        <div class="mb-4">
                            <label class="form-label">Ruangan</label>
                            <select name="ruangan_id" class="form-select form-select-lg" required>
                                <option value="">Pilih ruangan server</option>
                                <?php foreach ($rooms as $roomOption): ?>
                                    <option value="<?= (int) $roomOption['id']; ?>" <?= (int) $old['ruangan_id'] === (int) $roomOption['id'] ? 'selected' : ''; ?>>
                                        <?= e($roomOption['nama_ruangan']); ?> • <?= e($roomOption['lokasi']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Suhu Ruangan</label>
                            <div class="monitoring-radio-group">
                                <?php foreach (monitoring_temperature_options() as $value => $label): ?>
                                    <label class="monitoring-radio-card">
                                        <input type="radio" name="suhu" value="<?= e($value); ?>" <?= $old['suhu'] === $value ? 'checked' : ''; ?> required>
                                        <span><?= e($label); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div>
                            <label class="form-label">Akses Masuk</label>
                            <div class="monitoring-radio-group">
                                <?php foreach (monitoring_access_options() as $value => $label): ?>
                                    <label class="monitoring-radio-card">
                                        <input type="radio" name="akses_masuk" value="<?= e($value); ?>" <?= $old['akses_masuk'] === $value ? 'checked' : ''; ?> required>
                                        <span><?= e($label); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="monitoring-section-card">
                        <div class="monitoring-section-title">C. Petugas & Catatan</div>
                        <div class="mb-4">
                            <label class="form-label">Petugas Monitoring</label>
                            <select name="petugas_id" class="form-select form-select-lg" required>
                                <option value="">Pilih petugas monitoring</option>
                                <?php foreach ($staff as $staffRow): ?>
                                    <option value="<?= (int) $staffRow['id']; ?>" <?= (int) $old['petugas_id'] === (int) $staffRow['id'] ? 'selected' : ''; ?>>
                                        <?= e($staffRow['nama_lengkap']); ?> - <?= e($staffRow['jabatan']); ?> (<?= e($staffRow['nip_nik']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Catatan</label>
                            <textarea name="catatan" rows="4" class="form-control" placeholder="Isi catatan jika diperlukan."><?= e($old['catatan']); ?></textarea>
                        </div>
                    </div>

                    <div class="monitoring-form-footer">
                        <button type="submit" class="btn btn-primary btn-lg">Simpan Perubahan</button>
                        <a href="<?= e(url('modules/monitoring_ruangan/detail.php?id=' . (int) $row['id'])); ?>" class="btn btn-light btn-lg">Batal</a>
                        <a href="<?= e(url('modules/monitoring_ruangan/histori.php')); ?>" class="btn btn-outline-primary btn-lg">Kembali ke Histori</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/layout_bottom.php'; ?>
