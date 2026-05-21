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

$pageTitle = monitoring_has_signature($row) ? 'Ubah Paraf Monitoring' : 'Lengkapi Paraf Monitoring';
$pageSubtitle = 'Tambahkan atau perbarui paraf digital untuk data monitoring historis tanpa mengubah informasi utama lainnya.';
$monitoringNavActive = 'histori';
$pageScriptUrls = [
    'https://cdn.jsdelivr.net/npm/signature_pad@4.2.0/dist/signature_pad.umd.min.js',
];
$errors = [];
$signatureBase64 = trim((string) ($row['signature_base64'] ?? ''));

if (is_post()) {
    verify_csrf();
    $signatureBase64 = trim((string) ($_POST['signature_base64'] ?? ''));

    if (monitoring_update_signature($pdo, $id, $signatureBase64, $errors)) {
        set_flash('success', 'Paraf monitoring berhasil disimpan.');
        redirect('modules/monitoring_ruangan/detail.php?id=' . $id);
    }
}

$pageScripts = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-monitoring-signature-form]');
    if (!form || typeof window.SignaturePad === 'undefined') {
        return;
    }

    const canvas = form.querySelector('[data-signature-pad]');
    const output = form.querySelector('[data-signature-output]');
    const preview = form.querySelector('[data-signature-preview]');
    const emptyState = form.querySelector('[data-signature-empty-state]');
    const clearButtons = form.querySelectorAll('[data-signature-clear], [data-signature-reset]');
    const errorBox = form.querySelector('[data-signature-error]');

    const signaturePad = new window.SignaturePad(canvas, {
        backgroundColor: 'rgb(255,255,255)',
        penColor: '#0f172a',
        minWidth: 0.9,
        maxWidth: 2.2
    });

    const paintCanvasBackground = function () {
        const context = canvas.getContext('2d');
        if (!context) {
            return;
        }

        context.save();
        context.setTransform(1, 0, 0, 1, 0, 0);
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.restore();
    };

    const syncPreview = function () {
        if (signaturePad.isEmpty()) {
            output.value = '';
            preview.classList.add('d-none');
            preview.removeAttribute('src');
            emptyState.classList.remove('d-none');
            return;
        }

        const dataUrl = signaturePad.toDataURL('image/png');
        output.value = dataUrl;
        preview.src = dataUrl;
        preview.classList.remove('d-none');
        emptyState.classList.add('d-none');
    };

    const clearSignature = function () {
        signaturePad.clear();
        paintCanvasBackground();
        syncPreview();
    };

    const resizeCanvas = function () {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const data = !signaturePad.isEmpty() ? signaturePad.toData() : null;
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext('2d').scale(ratio, ratio);
        paintCanvasBackground();
        signaturePad.clear();
        if (data && data.length) {
            signaturePad.fromData(data);
        }
        syncPreview();
    };

    signaturePad.addEventListener('endStroke', syncPreview);
    clearButtons.forEach(function (button) {
        button.addEventListener('click', clearSignature);
    });

    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    if (output.value.trim() !== '') {
        signaturePad.fromDataURL(output.value);
        preview.src = output.value;
        preview.classList.remove('d-none');
        emptyState.classList.add('d-none');
    }

    form.addEventListener('submit', function (event) {
        if (errorBox) {
            errorBox.classList.add('d-none');
            errorBox.textContent = '';
        }

        syncPreview();
        if (output.value.trim() === '') {
            event.preventDefault();
            if (errorBox) {
                errorBox.textContent = 'Paraf/tanda tangan wajib diisi.';
                errorBox.classList.remove('d-none');
            }
        }
    });
});
JS;

require_once BASE_PATH . '/includes/layout_top.php';
require_once __DIR__ . '/_nav.php';
?>

<section class="monitoring-hero-surface mb-4">
    <div>
        <span class="monitoring-kicker">Lengkapi Histori</span>
        <h3 class="monitoring-hero-title"><?= monitoring_has_signature($row) ? 'Perbarui paraf digital petugas.' : 'Tambahkan paraf digital untuk data monitoring lama.'; ?></h3>
        <p class="monitoring-hero-text mb-0">Data utama dari histori tidak akan berubah. Halaman ini hanya dipakai untuk melengkapi paraf pada monitoring tanggal <?= e(date('d/m/Y', strtotime($row['tanggal']))); ?> di <?= e($row['nama_ruangan']); ?>.</p>
    </div>
    <div class="monitoring-hero-side">
        <div class="monitoring-quick-stats">
            <div class="monitoring-quick-stat">
                <span>Petugas</span>
                <strong><?= e($row['nama_lengkap']); ?></strong>
            </div>
            <div class="monitoring-quick-stat">
                <span>Status</span>
                <strong><?= e(monitoring_status_label($row['status'])); ?></strong>
            </div>
        </div>
    </div>
</section>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm monitoring-surface-card h-100">
            <div class="card-header bg-white border-0 pt-4 pb-0 monitoring-card-header">
                <h5 class="mb-1">Ringkasan Data</h5>
                <p class="text-muted mb-0">Pastikan data yang diparaf sudah sesuai.</p>
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
                    <span>Petugas Monitoring</span>
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
                <h5 class="mb-1"><?= monitoring_has_signature($row) ? 'Ubah Paraf' : 'Lengkapi Paraf'; ?></h5>
                <p class="text-muted mb-0">Gambar langsung di canvas, lalu simpan untuk memperbarui histori monitoring.</p>
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

                <form method="post" data-monitoring-signature-form>
                    <?= csrf_field(); ?>
                    <input type="hidden" name="id" value="<?= (int) $row['id']; ?>">
                    <input type="hidden" name="signature_base64" value="<?= e($signatureBase64); ?>" data-signature-output>

                    <div class="alert alert-danger d-none" data-signature-error></div>

                    <div class="monitoring-signature-wrap">
                        <canvas class="monitoring-signature-canvas" data-signature-pad aria-label="Canvas paraf monitoring"></canvas>
                    </div>

                    <div class="d-flex gap-2 flex-wrap mt-3 mb-3">
                        <button type="button" class="btn btn-outline-secondary" data-signature-clear>Clear Signature</button>
                        <button type="button" class="btn btn-outline-dark" data-signature-reset>Ulang Tanda Tangan</button>
                    </div>

                    <div class="monitoring-signature-preview">
                        <div class="monitoring-signature-preview-label">Preview Signature</div>
                        <div class="monitoring-signature-empty <?= $signatureBase64 !== '' ? 'd-none' : ''; ?>" data-signature-empty-state>Belum ada paraf tersimpan.</div>
                        <img src="<?= e($signatureBase64); ?>" alt="Preview signature monitoring" class="<?= $signatureBase64 !== '' ? '' : 'd-none'; ?>" data-signature-preview>
                    </div>

                    <div class="d-flex gap-2 flex-wrap mt-4">
                        <button type="submit" class="btn btn-primary">Simpan Paraf</button>
                        <a href="<?= e(url('modules/monitoring_ruangan/detail.php?id=' . (int) $row['id'])); ?>" class="btn btn-light">Kembali ke Detail</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/layout_bottom.php'; ?>
