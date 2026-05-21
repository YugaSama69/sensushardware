<?php

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once __DIR__ . '/module.php';
require_module_access();

$pageTitle = 'Dashboard Monitoring';
$pageSubtitle = 'Pantau checklist harian ruangan server, warning suhu, akses pintu, dan histori monitoring secara cepat.';
$monitoringNavActive = 'dashboard';
$pageScriptUrls = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js',
];

$stats = monitoring_get_dashboard_stats($pdo);
$chartData = monitoring_chart_dataset($stats['chart_rows']);

require_once BASE_PATH . '/includes/layout_top.php';
require_once __DIR__ . '/_nav.php';
?>

<section class="monitoring-hero-surface monitoring-hero-surface-dashboard mb-4">
    <div>
        <span class="monitoring-kicker">Server Room Center</span>
        <h3 class="monitoring-hero-title">Pantau kondisi ruang server dalam satu layar yang ringkas dan cepat dibaca.</h3>
        <p class="monitoring-hero-text mb-0">Dashboard ini menampilkan ritme checklist harian, warning aktif, dan input terbaru petugas dengan gaya visual yang selaras dengan tema aplikasi.</p>
    </div>
    <div class="monitoring-hero-side">
        <div class="monitoring-chip-cloud">
            <span class="monitoring-chip">Realtime Database</span>
            <span class="monitoring-chip">Mobile Ready</span>
            <span class="monitoring-chip">Digital Signature</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= e(url('modules/monitoring_ruangan/form.php')); ?>" class="btn btn-light">Isi Monitoring</a>
            <a href="<?= e(url('modules/monitoring_ruangan/histori.php')); ?>" class="btn btn-outline-light">Buka Histori</a>
        </div>
    </div>
</section>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="metric-card card-sky monitoring-metric-card">
            <span>Total Monitoring Hari Ini</span>
            <h3><?= format_number($stats['today_total']); ?></h3>
            <small><?= e(date('d/m/Y')); ?> sampai saat ini</small>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="metric-card card-amber monitoring-metric-card">
            <span>Ruangan Bermasalah</span>
            <h3><?= format_number($stats['problem_rooms']); ?></h3>
            <small>Minimal ada warning hari ini</small>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="metric-card card-rose monitoring-metric-card">
            <span>Suhu Abnormal</span>
            <h3><?= format_number($stats['abnormal_temperature']); ?></h3>
            <small>Di atas ambang 16-21°C</small>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="metric-card card-emerald monitoring-metric-card">
            <span>Akses Tidak Terkunci</span>
            <h3><?= format_number($stats['unlocked_access']); ?></h3>
            <small>Perlu tindak lanjut petugas</small>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm h-100 monitoring-surface-card">
            <div class="card-header bg-white border-0 pt-4 pb-0 monitoring-card-header">
                <h5 class="mb-1">Grafik Monitoring 7 Hari Terakhir</h5>
                <p class="text-muted mb-0">Bandingkan total checklist dengan total warning agar kondisi ruangan mudah dipantau.</p>
            </div>
            <div class="card-body">
                <div class="monitoring-chart-wrap">
                    <canvas
                        data-monitoring-chart
                        data-chart-labels='<?= e(json_encode($chartData['labels'], JSON_UNESCAPED_SLASHES)); ?>'
                        data-chart-totals='<?= e(json_encode($chartData['totals'], JSON_UNESCAPED_SLASHES)); ?>'
                        data-chart-warnings='<?= e(json_encode($chartData['warnings'], JSON_UNESCAPED_SLASHES)); ?>'
                    ></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100 monitoring-surface-card">
            <div class="card-header bg-white border-0 pt-4 pb-0 monitoring-card-header">
                <h5 class="mb-1">Monitoring Terakhir</h5>
                <p class="text-muted mb-0">Snapshot input terbaru dari petugas monitoring.</p>
            </div>
            <div class="card-body">
                <?php if (!$stats['latest_entry']): ?>
                    <div class="monitoring-empty-state">
                        <strong>Belum ada data monitoring.</strong>
                        <span>Mulai dari form baru agar dashboard terisi realtime.</span>
                        <a href="<?= e(url('modules/monitoring_ruangan/form.php')); ?>" class="btn btn-primary">Isi Monitoring Baru</a>
                    </div>
                <?php else: ?>
                    <?php $latest = $stats['latest_entry']; ?>
                    <div class="monitoring-latest-card">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <div>
                                <div class="monitoring-kicker"><?= e(format_date_id($latest['tanggal'])); ?></div>
                                <h5 class="mb-1"><?= e($latest['nama_ruangan']); ?></h5>
                                <div class="text-muted"><?= e($latest['nama_lengkap']); ?> • <?= e($latest['jabatan']); ?></div>
                            </div>
                            <span class="badge text-bg-<?= monitoring_status_badge_class($latest['status']); ?>"><?= e(monitoring_status_label($latest['status'])); ?></span>
                        </div>
                        <div class="monitoring-inline-grid">
                            <div>
                                <small>Suhu</small>
                                <strong><?= e(monitoring_temperature_display($latest)); ?></strong>
                            </div>
                            <div>
                                <small>Akses</small>
                                <strong><?= e(monitoring_access_label($latest['akses_masuk'])); ?></strong>
                            </div>
                        </div>
                        <div class="monitoring-badge-row mt-3">
                            <?php foreach (monitoring_issue_badges($latest) as $badge): ?>
                                <span class="badge text-bg-light border"><?= e($badge); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="d-flex gap-2 flex-wrap mt-4">
                            <a href="<?= e(url('modules/monitoring_ruangan/detail.php?id=' . (int) $latest['id'])); ?>" class="btn btn-outline-primary">Lihat Detail</a>
                            <a href="<?= e(url('modules/monitoring_ruangan/form.php')); ?>" class="btn btn-primary">Monitoring Baru</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-4">
        <a class="quick-link-card monitoring-quick-link" href="<?= e(url('modules/monitoring_ruangan/form.php')); ?>">
            <strong>Form Monitoring Baru</strong>
            <span>Masuk ke checklist digital yang cepat diisi dari HP, tablet, atau desktop.</span>
        </a>
    </div>
    <div class="col-xl-4">
        <a class="quick-link-card monitoring-quick-link" href="<?= e(url('modules/monitoring_ruangan/histori.php')); ?>">
            <strong>Histori Monitoring</strong>
            <span>Telusuri data, preview signature, filter petugas, dan ekspor laporan.</span>
        </a>
    </div>
    <div class="col-xl-4">
        <a class="quick-link-card monitoring-quick-link" href="<?= e(url('modules/monitoring_ruangan/master_ruangan.php')); ?>">
            <strong>Kelola Master Ruangan & Petugas</strong>
            <span>Siapkan ruangan server dan data petugas agar monitoring tetap konsisten.</span>
        </a>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/layout_bottom.php'; ?>
