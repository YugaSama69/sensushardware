<?php

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once __DIR__ . '/module.php';
require_module_access();

$id = (int) ($_GET['id'] ?? 0);
$row = $id > 0 ? monitoring_find_row($pdo, $id) : null;

if (!$row) {
    set_flash('warning', 'Detail monitoring tidak ditemukan.');
    redirect('modules/monitoring_ruangan/histori.php');
}

$pageTitle = 'Detail Monitoring';
$pageSubtitle = 'Audit lengkap input monitoring ruangan server beserta petugas, signature, IP, dan browser.';
$monitoringNavActive = 'histori';
$pageScriptUrls = [
    'https://cdn.jsdelivr.net/npm/signature_pad@4.2.0/dist/signature_pad.umd.min.js',
];

require_once BASE_PATH . '/includes/layout_top.php';
require_once __DIR__ . '/_nav.php';
?>

<div class="row g-4" data-monitoring-histori data-api-endpoint="<?= e(url('api/monitoring_ruangan.php')); ?>" data-csrf-token="<?= e(csrf_token()); ?>">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-start flex-wrap gap-3 pt-4">
                <div>
                    <h5 class="mb-1">Monitoring #<?= (int) $row['id']; ?></h5>
                    <p class="text-muted mb-0"><?= e($row['nama_ruangan']); ?> • <?= e(date('d/m/Y', strtotime($row['tanggal']))); ?></p>
                </div>
                <span class="badge text-bg-<?= monitoring_status_badge_class($row['status']); ?>"><?= e(monitoring_status_label($row['status'])); ?></span>
            </div>
            <div class="card-body">
                <div class="monitoring-detail-grid">
                    <div class="monitoring-detail-item">
                        <span>Tanggal</span>
                        <strong><?= e(date('d/m/Y', strtotime($row['tanggal']))); ?></strong>
                    </div>
                    <div class="monitoring-detail-item">
                        <span>Ruangan</span>
                        <strong><?= e($row['nama_ruangan']); ?></strong>
                    </div>
                    <div class="monitoring-detail-item">
                        <span>Lokasi</span>
                        <strong><?= e($row['lokasi_ruangan']); ?></strong>
                    </div>
                    <div class="monitoring-detail-item">
                        <span>Suhu</span>
                        <strong><?= e(monitoring_temperature_display($row)); ?></strong>
                    </div>
                    <div class="monitoring-detail-item">
                        <span>Akses Masuk</span>
                        <strong><?= e(monitoring_access_label($row['akses_masuk'])); ?></strong>
                    </div>
                    <div class="monitoring-detail-item">
                        <span>Petugas Monitoring</span>
                        <strong><?= e($row['nama_lengkap']); ?></strong>
                    </div>
                    <div class="monitoring-detail-item">
                        <span>Jabatan</span>
                        <strong><?= e($row['jabatan']); ?></strong>
                    </div>
                    <div class="monitoring-detail-item monitoring-detail-item-wide">
                        <span>Catatan</span>
                        <strong><?= e($row['catatan'] ?: '-'); ?></strong>
                    </div>
                    <div class="monitoring-detail-item monitoring-detail-item-wide">
                        <span>Badge Warning</span>
                        <div class="monitoring-badge-row">
                            <?php foreach (monitoring_issue_badges($row) as $badge): ?>
                                <span class="badge text-bg-light border"><?= e($badge); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="monitoring-signature-panel mt-4">
                    <div class="monitoring-signature-preview-label">Preview Signature Petugas</div>
                    <?php if (monitoring_has_signature($row)): ?>
                        <img src="<?= e($row['signature_base64']); ?>" alt="Signature petugas" class="monitoring-signature-detail-image">
                    <?php else: ?>
                        <div class="monitoring-signature-empty">Paraf belum tersedia untuk data ini.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h5 class="mb-1">Metadata Audit</h5>
                <p class="text-muted mb-0">Informasi siapa yang menginput dan dari perangkat mana.</p>
            </div>
            <div class="card-body">
                <div class="monitoring-detail-item">
                    <span>User Login Penginput</span>
                    <strong><?= e($row['created_by']); ?></strong>
                </div>
                <div class="monitoring-detail-item">
                    <span>Timestamp Input</span>
                    <strong><?= e(date('d/m/Y H:i:s', strtotime($row['created_at']))); ?></strong>
                </div>
                <div class="monitoring-detail-item">
                    <span>Terakhir Diubah</span>
                    <strong><?= e(date('d/m/Y H:i:s', strtotime($row['updated_at']))); ?></strong>
                </div>
                <div class="monitoring-detail-item">
                    <span>IP Address</span>
                    <strong><?= e($row['ip_address'] ?: '-'); ?></strong>
                </div>
                <div class="monitoring-detail-item monitoring-detail-item-wide">
                    <span>Device / Browser</span>
                    <strong><?= e($row['device_info'] ?: '-'); ?></strong>
                </div>
                <div class="d-flex gap-2 flex-wrap mt-4">
                    <a href="<?= e(url('modules/monitoring_ruangan/histori.php')); ?>" class="btn btn-light">Kembali ke Histori</a>
                    <a href="<?= e(url('modules/monitoring_ruangan/edit.php?id=' . (int) $row['id'])); ?>" class="btn btn-outline-secondary">Edit Data</a>
                    <button
                        type="button"
                        class="btn btn-primary"
                        data-monitoring-signature-trigger
                        data-monitoring-id="<?= (int) $row['id']; ?>"
                        data-monitoring-petugas="<?= e($row['nama_lengkap']); ?>"
                        data-monitoring-ruangan="<?= e($row['nama_ruangan']); ?>"
                        data-monitoring-tanggal="<?= e(date('d/m/Y', strtotime($row['tanggal']))); ?>"
                        data-monitoring-signature="<?= e($row['signature_base64'] ?? ''); ?>"
                    >
                        <?= monitoring_has_signature($row) ? 'Ubah Paraf' : 'Lengkapi Paraf'; ?>
                    </button>
                    <a href="<?= e(url('modules/monitoring_ruangan/export_pdf.php?id=' . (int) $row['id'])); ?>" class="btn btn-danger">Export PDF</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade monitoring-signature-modal" id="monitoringDetailSignatureModal" tabindex="-1" aria-hidden="true" data-monitoring-signature-modal>
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content monitoring-signature-modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" data-monitoring-signature-title>Lengkapi Paraf</h5>
                    <div class="small text-muted" data-monitoring-signature-subtitle>Gambar paraf langsung di popup ini.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" data-monitoring-signature-error></div>
                <input type="hidden" value="" data-monitoring-signature-id>
                <input type="hidden" value="" data-monitoring-signature-output>
                <div class="monitoring-signature-wrap">
                    <canvas class="monitoring-signature-canvas" data-monitoring-signature-pad aria-label="Canvas paraf detail monitoring"></canvas>
                </div>
                <div class="d-flex gap-2 flex-wrap mt-3">
                    <button type="button" class="btn btn-outline-secondary" data-monitoring-signature-clear>Clear</button>
                    <button type="button" class="btn btn-outline-dark" data-monitoring-signature-reset>Ulang</button>
                </div>
                <div class="monitoring-signature-preview mt-3">
                    <div class="monitoring-signature-preview-label">Preview Signature</div>
                    <div class="monitoring-signature-empty" data-monitoring-signature-empty>Belum ada paraf tersimpan.</div>
                    <img src="" alt="Preview paraf detail monitoring" class="d-none" data-monitoring-signature-preview>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" data-monitoring-signature-submit>Simpan Paraf</button>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/layout_bottom.php'; ?>
