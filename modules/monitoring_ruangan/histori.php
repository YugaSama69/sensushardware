<?php

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once __DIR__ . '/module.php';
require_module_access();

$pageTitle = 'Histori Monitoring';
$pageSubtitle = 'Filter histori monitoring berdasarkan tanggal, ruangan, petugas, dan status lalu preview signature tanpa keluar halaman.';
$monitoringNavActive = 'histori';
$pageScriptUrls = [
    'https://cdn.jsdelivr.net/npm/signature_pad@4.2.0/dist/signature_pad.umd.min.js',
];

$filters = monitoring_normalize_filters($_GET);
$rows = monitoring_get_rows($pdo, $filters);
$rooms = monitoring_get_rooms($pdo);
$staff = monitoring_get_staff($pdo);
$queryString = monitoring_build_query_string($filters);

require_once BASE_PATH . '/includes/layout_top.php';
require_once __DIR__ . '/_nav.php';
?>

<section class="monitoring-hero-surface mb-4">
    <div>
        <span class="monitoring-kicker">Audit Monitoring</span>
        <h3 class="monitoring-hero-title">Histori monitoring yang rapi untuk audit, cetak, dan penelusuran cepat.</h3>
        <p class="monitoring-hero-text mb-0">Filter yang ringkas, preview paraf langsung, dan export yang siap dipakai tanpa meninggalkan gaya visual utama aplikasi.</p>
    </div>
    <div class="monitoring-hero-side">
        <div class="monitoring-quick-stats">
            <div class="monitoring-quick-stat">
                <span>Total Data</span>
                <strong><?= format_number(count($rows)); ?></strong>
            </div>
            <div class="monitoring-quick-stat">
                <span>Status Filter</span>
                <strong><?= $filters['status'] !== '' ? e(monitoring_status_label($filters['status'])) : 'Semua'; ?></strong>
            </div>
        </div>
    </div>
</section>

<div class="card border-0 shadow-sm mb-4 monitoring-surface-card" data-monitoring-histori data-api-endpoint="<?= e(url('api/monitoring_ruangan.php')); ?>" data-csrf-token="<?= e(csrf_token()); ?>">
    <div class="card-header bg-white border-0 pt-4 monitoring-card-header">
        <h5 class="mb-1">Filter Histori Monitoring</h5>
        <p class="text-muted mb-0">Gunakan filter ini untuk audit harian, verifikasi warning, dan kebutuhan export.</p>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3 monitoring-filter-form">
            <div class="col-md-3">
                <label class="form-label">Cari</label>
                <input type="text" name="search" class="form-control" value="<?= e($filters['search']); ?>" placeholder="Ruangan, petugas, jabatan, catatan">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tanggal Dari</label>
                <input type="date" name="date_from" class="form-control" value="<?= e($filters['date_from']); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tanggal Sampai</label>
                <input type="date" name="date_to" class="form-control" value="<?= e($filters['date_to']); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Ruangan</label>
                <select name="ruangan_id" class="form-select">
                    <option value="">Semua ruangan</option>
                    <?php foreach ($rooms as $room): ?>
                        <option value="<?= (int) $room['id']; ?>" <?= (int) $filters['ruangan_id'] === (int) $room['id'] ? 'selected' : ''; ?>>
                            <?= e($room['nama_ruangan']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Petugas</label>
                <select name="petugas_id" class="form-select">
                    <option value="">Semua petugas</option>
                    <?php foreach ($staff as $staffRow): ?>
                        <option value="<?= (int) $staffRow['id']; ?>" <?= (int) $filters['petugas_id'] === (int) $staffRow['id'] ? 'selected' : ''; ?>>
                            <?= e($staffRow['nama_lengkap']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua</option>
                    <?php foreach (monitoring_status_options() as $statusValue => $statusLabel): ?>
                        <option value="<?= e($statusValue); ?>" <?= $filters['status'] === $statusValue ? 'selected' : ''; ?>><?= e($statusLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 d-flex gap-2 flex-wrap monitoring-filter-actions">
                <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                <a href="<?= e(url('modules/monitoring_ruangan/histori.php')); ?>" class="btn btn-light">Reset</a>
                <a href="<?= e(url('modules/monitoring_ruangan/export_excel.php' . ($queryString !== '' ? '?' . $queryString : ''))); ?>" class="btn btn-success">Export Excel</a>
                <a href="<?= e(url('modules/monitoring_ruangan/export_pdf.php' . ($queryString !== '' ? '?' . $queryString : ''))); ?>" class="btn btn-danger">Export PDF</a>
                <a href="<?= e(url('modules/monitoring_ruangan/form.php')); ?>" class="btn btn-outline-dark">Monitoring Baru</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm monitoring-surface-card monitoring-table-card">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap gap-3 pt-4 monitoring-card-header">
        <div>
            <h5 class="mb-1">Histori Monitoring Tersimpan</h5>
            <p class="text-muted mb-0"><?= format_number(count($rows)); ?> data siap ditinjau.</p>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle datatable monitoring-history-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>Ruangan</th>
                        <th>Suhu</th>
                        <th>Akses Masuk</th>
                        <th>Petugas</th>
                        <th>Status</th>
                        <th>Paraf</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $index => $row): ?>
                        <tr>
                            <td data-label="No"><?= $index + 1; ?></td>
                            <td data-label="Tanggal" data-order="<?= e($row['tanggal']); ?>"><?= e(date('d/m/Y', strtotime($row['tanggal']))); ?></td>
                            <td data-label="Ruangan">
                                <strong><?= e($row['nama_ruangan']); ?></strong>
                                <div class="small text-muted"><?= e($row['lokasi_ruangan']); ?></div>
                            </td>
                            <td data-label="Suhu"><?= e(monitoring_temperature_display($row)); ?></td>
                            <td data-label="Akses Masuk"><?= e(monitoring_access_label($row['akses_masuk'])); ?></td>
                            <td data-label="Petugas">
                                <strong><?= e($row['nama_lengkap']); ?></strong>
                                <div class="small text-muted"><?= e($row['jabatan']); ?></div>
                            </td>
                            <td data-label="Status">
                                <span class="badge text-bg-<?= monitoring_status_badge_class($row['status']); ?>"><?= e(monitoring_status_label($row['status'])); ?></span>
                                <div class="small text-muted mt-1"><?= e(monitoring_status_icon($row['status'])); ?></div>
                            </td>
                            <td data-label="Paraf">
                                <?php if (monitoring_has_signature($row)): ?>
                                    <button
                                        type="button"
                                        class="monitoring-signature-thumb"
                                        data-bs-toggle="modal"
                                        data-bs-target="#<?= e(monitoring_modal_id('signaturePreviewModal', (int) $row['id'])); ?>"
                                    >
                                        <img src="<?= e($row['signature_base64']); ?>" alt="Paraf <?= e($row['nama_lengkap']); ?>">
                                    </button>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Belum paraf</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Action">
                                <div class="d-flex gap-2 flex-wrap">
                                    <a href="<?= e(url('modules/monitoring_ruangan/edit.php?id=' . (int) $row['id'])); ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    <a href="<?= e(url('modules/monitoring_ruangan/detail.php?id=' . (int) $row['id'])); ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                                    <button
                                        type="button"
                                        class="btn btn-sm <?= monitoring_has_signature($row) ? 'btn-outline-dark' : 'btn-primary'; ?>"
                                        data-monitoring-signature-trigger
                                        data-monitoring-id="<?= (int) $row['id']; ?>"
                                        data-monitoring-petugas="<?= e($row['nama_lengkap']); ?>"
                                        data-monitoring-ruangan="<?= e($row['nama_ruangan']); ?>"
                                        data-monitoring-tanggal="<?= e(date('d/m/Y', strtotime($row['tanggal']))); ?>"
                                        data-monitoring-signature="<?= e($row['signature_base64'] ?? ''); ?>"
                                    >
                                        <?= monitoring_has_signature($row) ? 'Ubah Paraf' : 'Lengkapi Paraf'; ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade monitoring-signature-modal" id="monitoringHistoriSignatureModal" tabindex="-1" aria-hidden="true" data-monitoring-signature-modal>
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
                    <canvas class="monitoring-signature-canvas" data-monitoring-signature-pad aria-label="Canvas paraf histori"></canvas>
                </div>
                <div class="d-flex gap-2 flex-wrap mt-3">
                    <button type="button" class="btn btn-outline-secondary" data-monitoring-signature-clear>Clear</button>
                    <button type="button" class="btn btn-outline-dark" data-monitoring-signature-reset>Ulang</button>
                </div>
                <div class="monitoring-signature-preview mt-3">
                    <div class="monitoring-signature-preview-label">Preview Signature</div>
                    <div class="monitoring-signature-empty" data-monitoring-signature-empty>Belum ada paraf tersimpan.</div>
                    <img src="" alt="Preview paraf histori" class="d-none" data-monitoring-signature-preview>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary" data-monitoring-signature-submit>Simpan Paraf</button>
            </div>
        </div>
    </div>
</div>

<?php foreach ($rows as $row): ?>
    <?php if (!monitoring_has_signature($row)) { continue; } ?>
    <div class="modal fade" id="<?= e(monitoring_modal_id('signaturePreviewModal', (int) $row['id'])); ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Preview Signature</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="<?= e($row['signature_base64']); ?>" alt="Signature petugas" class="monitoring-signature-modal-image">
                    <div class="small text-muted mt-3"><?= e($row['nama_lengkap']); ?> • <?= e($row['nama_ruangan']); ?></div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require_once BASE_PATH . '/includes/layout_bottom.php'; ?>
