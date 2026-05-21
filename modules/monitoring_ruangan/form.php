<?php

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once __DIR__ . '/module.php';
require_module_access();

$pageTitle = 'Form Monitoring Baru';
$pageSubtitle = 'Checklist digital ala Google Form yang ringan, cepat, dan nyaman dipakai petugas monitoring di HP atau tablet.';
$monitoringNavActive = 'form';
$pageScriptUrls = [
    'https://cdn.jsdelivr.net/npm/signature_pad@4.2.0/dist/signature_pad.umd.min.js',
];

$rooms = monitoring_get_rooms($pdo, true);
$staff = monitoring_get_staff($pdo, true);
$errors = [];
$old = [
    'tanggal' => date('Y-m-d'),
    'ruangan_id' => '',
    'suhu' => '20_21',
    'akses_masuk' => 'terkunci',
    'petugas_id' => '',
    'catatan' => '',
    'signature_base64' => '',
];

if (is_post()) {
    verify_csrf();
    $old = array_merge($old, [
        'tanggal' => trim((string) ($_POST['tanggal'] ?? date('Y-m-d'))),
        'ruangan_id' => trim((string) ($_POST['ruangan_id'] ?? '')),
        'suhu' => trim((string) ($_POST['suhu'] ?? '20_21')),
        'akses_masuk' => trim((string) ($_POST['akses_masuk'] ?? 'terkunci')),
        'petugas_id' => trim((string) ($_POST['petugas_id'] ?? '')),
        'catatan' => trim((string) ($_POST['catatan'] ?? '')),
        'signature_base64' => trim((string) ($_POST['signature_base64'] ?? '')),
    ]);

    $createdId = monitoring_create_entry($pdo, $_POST, [
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        'device_info' => monitoring_device_info(),
    ], $errors);

    if ($createdId !== null) {
        set_flash('success', 'Monitoring ruangan server berhasil disimpan.');
        redirect('modules/monitoring_ruangan/histori.php');
    }
}

require_once BASE_PATH . '/includes/layout_top.php';
require_once __DIR__ . '/_nav.php';
?>

<?php if (!$rooms || !$staff): ?>
    <div class="alert alert-warning border-0 shadow-sm">
        Modul monitoring butuh data master aktif terlebih dahulu.
        <a href="<?= e(url('modules/monitoring_ruangan/master_ruangan.php')); ?>" class="alert-link">Buka Master Ruangan</a>
        atau
        <a href="<?= e(url('modules/monitoring_ruangan/master_petugas.php')); ?>" class="alert-link">Buka Master Petugas</a>.
    </div>
<?php endif; ?>

<div class="monitoring-form-layout">
    <div class="monitoring-form-hero">
        <span class="monitoring-kicker">Monitoring Harian</span>
        <h3>Ruangan server tetap aman dengan checklist yang singkat dan jelas.</h3>
        <p>Setiap input tersimpan langsung ke database internal lengkap dengan petugas, status, signature digital, alamat IP, dan device browser.</p>
        <div class="monitoring-hero-points">
            <div>
                <strong>Mobile first</strong>
                <span>Ukuran tombol besar dan ritme form dibuat nyaman untuk HP/tablet.</span>
            </div>
            <div>
                <strong>Realtime ke database</strong>
                <span>Setiap submit langsung tercatat ke histori monitoring aplikasi internal.</span>
            </div>
            <div>
                <strong>Tanda tangan digital</strong>
                <span>Paraf disimpan sebagai base64 PNG agar mudah diaudit dan dicetak.</span>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm monitoring-form-card">
        <div class="card-body p-0">
            <?php if ($errors): ?>
                <div class="alert alert-danger rounded-0 border-0 mb-0">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form
                method="post"
                action="<?= e(url('modules/monitoring_ruangan/form.php')); ?>"
                class="monitoring-form-shell"
                data-monitoring-form
                data-api-endpoint="<?= e(url('api/monitoring_ruangan.php')); ?>"
                data-redirect-url="<?= e(url('modules/monitoring_ruangan/histori.php')); ?>"
            >
                <?= csrf_field(); ?>
                <div class="monitoring-form-intro mb-4">
                    <div class="monitoring-form-intro-item">
                        <span>Alur</span>
                        <strong>Pilih ruangan, checklist, paraf, lalu simpan.</strong>
                    </div>
                    <div class="monitoring-form-intro-item">
                        <span>Tujuan</span>
                        <strong>Buat monitoring harian terasa cepat seperti Google Form.</strong>
                    </div>
                </div>
                <div class="monitoring-section-card">
                    <div class="monitoring-section-title">A. Tanggal Monitoring</div>
                    <label class="form-label">Tanggal</label>
                    <input type="date" name="tanggal" class="form-control form-control-lg" value="<?= e($old['tanggal']); ?>" required>
                    <div class="form-text">Format tampilan perangkat mengikuti `dd/mm/yyyy`.</div>
                </div>

                <div class="monitoring-section-card">
                    <div class="monitoring-section-title">B. Kondisi Ruangan</div>
                    <div class="mb-4">
                        <label class="form-label">Ruangan</label>
                        <select name="ruangan_id" class="form-select form-select-lg" required <?= !$rooms ? 'disabled' : ''; ?>>
                            <option value="">Pilih ruangan server</option>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?= (int) $room['id']; ?>" <?= (int) $old['ruangan_id'] === (int) $room['id'] ? 'selected' : ''; ?>>
                                    <?= e($room['nama_ruangan']); ?> • <?= e($room['lokasi']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Suhu Ruangan</label>
                        <div class="monitoring-radio-group" data-monitoring-temperature-group>
                            <?php foreach (monitoring_temperature_options() as $value => $label): ?>
                                <label class="monitoring-radio-card">
                                    <input type="radio" name="suhu" value="<?= e($value); ?>" <?= $old['suhu'] === $value ? 'checked' : ''; ?> required>
                                    <span><?= e($label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="monitoring-inline-alert danger <?= $old['suhu'] === 'gt_20_21' ? '' : 'd-none'; ?>" data-monitoring-temperature-alert>
                            Warning: suhu ruangan berada di atas batas ideal 16-21°C.
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Akses Masuk</label>
                        <div class="monitoring-radio-group" data-monitoring-access-group>
                            <?php foreach (monitoring_access_options() as $value => $label): ?>
                                <label class="monitoring-radio-card">
                                    <input type="radio" name="akses_masuk" value="<?= e($value); ?>" <?= $old['akses_masuk'] === $value ? 'checked' : ''; ?> required>
                                    <span><?= e($label); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="monitoring-inline-alert danger <?= $old['akses_masuk'] === 'tidak_terkunci' ? '' : 'd-none'; ?>" data-monitoring-access-alert>
                            Alert merah: akses masuk tidak terkunci, catatan wajib diisi.
                        </div>
                    </div>
                </div>

                <div class="monitoring-section-card">
                    <div class="monitoring-section-title">C. Petugas & Catatan</div>
                    <div class="mb-4">
                        <label class="form-label">Petugas Monitoring</label>
                        <select name="petugas_id" class="form-select form-select-lg" required <?= !$staff ? 'disabled' : ''; ?>>
                            <option value="">Pilih petugas monitoring</option>
                            <?php foreach ($staff as $staffRow): ?>
                                <option value="<?= (int) $staffRow['id']; ?>" <?= (int) $old['petugas_id'] === (int) $staffRow['id'] ? 'selected' : ''; ?>>
                                    <?= e($staffRow['nama_lengkap']); ?> - <?= e($staffRow['jabatan']); ?> (<?= e($staffRow['nip_nik']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Pilih langsung dari daftar master petugas aktif.</div>
                    </div>

                    <div>
                        <label class="form-label">Catatan</label>
                        <textarea
                            name="catatan"
                            rows="4"
                            class="form-control"
                            placeholder="Isi jika ada suhu di atas batas, akses tidak terkunci, atau catatan lain."
                            data-monitoring-note
                        ><?= e($old['catatan']); ?></textarea>
                        <div class="form-text" data-monitoring-note-help>
                            Catatan wajib jika suhu `>21°C` atau akses masuk `Tidak Terkunci`.
                        </div>
                    </div>
                </div>

                <div class="monitoring-section-card">
                    <div class="monitoring-section-title">D. Paraf / Tanda Tangan Digital</div>
                    <input type="hidden" name="signature_base64" value="<?= e($old['signature_base64']); ?>" data-signature-output>
                    <div class="monitoring-signature-launcher">
                        <div>
                            <div class="monitoring-signature-preview-label mb-2">Status Paraf</div>
                            <p class="text-muted mb-0">Klik tombol paraf untuk membuka popup dan gambar tanda tangan langsung di canvas.</p>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#monitoringSignatureModal" data-signature-open>
                                Buka Paraf
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-signature-clear-trigger <?= $old['signature_base64'] === '' ? 'disabled' : ''; ?>>
                                Hapus Paraf
                            </button>
                        </div>
                    </div>
                    <div class="monitoring-signature-preview mt-3">
                        <div class="monitoring-signature-preview-label">Preview Signature</div>
                        <div class="monitoring-signature-empty <?= $old['signature_base64'] !== '' ? 'd-none' : ''; ?>" data-signature-empty-state>Belum ada paraf tersimpan.</div>
                        <img
                            src="<?= e($old['signature_base64']); ?>"
                            alt="Preview tanda tangan"
                            class="<?= $old['signature_base64'] !== '' ? '' : 'd-none'; ?>"
                            data-signature-preview
                        >
                    </div>
                </div>

                <div class="monitoring-form-footer">
                    <button type="submit" class="btn btn-primary btn-lg" <?= (!$rooms || !$staff) ? 'disabled' : ''; ?>>Simpan</button>
                    <button type="reset" class="btn btn-light btn-lg" <?= (!$rooms || !$staff) ? 'disabled' : ''; ?>>Reset Form</button>
                    <button type="button" class="btn btn-outline-primary btn-lg" data-bs-toggle="modal" data-bs-target="#monitoringSignatureModal" data-signature-open <?= (!$rooms || !$staff) ? 'disabled' : ''; ?>>Paraf</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="monitoringSignatureModal" tabindex="-1" aria-hidden="true" data-signature-modal>
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content monitoring-signature-modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Paraf Petugas</h5>
                    <div class="small text-muted">Gambar tanda tangan menggunakan mouse, stylus, atau jari.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="monitoring-signature-wrap" data-signature-wrapper>
                    <canvas
                        class="monitoring-signature-canvas"
                        data-signature-pad
                        aria-label="Canvas tanda tangan digital"
                    ></canvas>
                </div>
                <div class="form-text mt-3">Paraf akan disimpan sebagai base64 PNG ke database. Upload file gambar tidak digunakan.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-outline-secondary" data-signature-clear>Clear</button>
                <button type="button" class="btn btn-outline-dark" data-signature-reset>Ulang</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal" data-signature-save>Gunakan Paraf</button>
            </div>
        </div>
    </div>
</div>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div class="toast align-items-center text-bg-success border-0" role="status" aria-live="polite" aria-atomic="true" data-monitoring-toast>
        <div class="d-flex">
            <div class="toast-body" data-monitoring-toast-message>Monitoring berhasil disimpan.</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Tutup"></button>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/layout_bottom.php'; ?>
