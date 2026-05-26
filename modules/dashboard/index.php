<?php

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once __DIR__ . '/module.php';

$pageTitle = 'Dashboard';
$deviceDashboardFilter = dashboard_normalize_device_filter($_GET);
$deviceStats = dashboard_device_stats($pdo, $deviceDashboardFilter);

$totalBarang = (int) fetch_scalar($pdo, 'SELECT COUNT(*) FROM barang');
$totalRuangan = (int) fetch_scalar($pdo, 'SELECT COUNT(*) FROM ruangan');
$stokTotal = (int) fetch_scalar($pdo, 'SELECT COALESCE(SUM(qty), 0) FROM barang');
$barangMasukBulanIni = (int) fetch_scalar(
    $pdo,
    'SELECT COALESCE(SUM(qty), 0) FROM histori_barang WHERE tipe_transaksi = "masuk" AND MONTH(tanggal) = MONTH(CURRENT_DATE()) AND YEAR(tanggal) = YEAR(CURRENT_DATE())'
);
$barangKeluarBulanIni = (int) fetch_scalar(
    $pdo,
    'SELECT COALESCE(SUM(qty), 0) FROM histori_barang WHERE tipe_transaksi = "keluar" AND MONTH(tanggal) = MONTH(CURRENT_DATE()) AND YEAR(tanggal) = YEAR(CURRENT_DATE())'
);
$totalKomputerClient = (int) fetch_scalar($pdo, 'SELECT COUNT(*) FROM komputer_client WHERE device_type = "CLIENT"');
$totalServer = (int) fetch_scalar($pdo, 'SELECT COUNT(*) FROM komputer_client WHERE device_type = "SERVER"');
$totalKomputerHddOnly = (int) fetch_scalar(
    $pdo,
    'SELECT COUNT(*)
     FROM komputer_client
     WHERE device_type = "CLIENT"
       AND (ssd IS NULL OR TRIM(ssd) = "" OR TRIM(ssd) = "-")
       AND hdd IS NOT NULL
       AND TRIM(hdd) <> ""
       AND TRIM(hdd) <> "-"'
);
$computerSpecRows = fetch_all($pdo, 'SELECT ram, os_name, tahun_inventaris, kondisi FROM komputer_client WHERE device_type = "CLIENT"');
$ramBreakdown = [];
$osBreakdown = [];
$tahunInventarisBreakdown = [];
$computerConditionSummary = [
    'Baik' => 0,
    'Rusak' => 0,
    'Perbaikan' => 0,
];
$inventorySummaryBreakdown = [
    'Komputer Client' => $totalKomputerClient,
    'Server' => $totalServer,
    'Network' => 0,
    'Peripheral' => 0,
    'Lainnya' => 0,
];

foreach ($computerSpecRows as $specRow) {
    $ramValue = trim((string) ($specRow['ram'] ?? ''));
    if ($ramValue !== '' && $ramValue !== '-') {
        $normalizedRam = str_replace(',', '.', $ramValue);
        if (preg_match('/(\d+(?:\.\d+)?)/', $normalizedRam, $matches) === 1) {
            $ramNumber = rtrim(rtrim(number_format((float) $matches[1], 2, '.', ''), '0'), '.');
            $ramLabel = $ramNumber . ' GB';
            $ramBreakdown[$ramLabel] = ($ramBreakdown[$ramLabel] ?? 0) + 1;
        }
    }

    $osValue = trim((string) ($specRow['os_name'] ?? ''));
    if ($osValue !== '' && $osValue !== '-') {
        $normalizedOs = strtolower($osValue);
        $osLabel = $osValue;

        if (str_contains($normalizedOs, 'windows 11')) {
            $osLabel = 'Windows 11';
        } elseif (str_contains($normalizedOs, 'windows 10')) {
            $osLabel = 'Windows 10';
        } elseif (str_contains($normalizedOs, 'windows 8')) {
            $osLabel = 'Windows 8';
        } elseif (str_contains($normalizedOs, 'windows 7')) {
            $osLabel = 'Windows 7';
        } elseif (str_contains($normalizedOs, 'windows')) {
            $osLabel = 'Windows Lainnya';
        } elseif (str_contains($normalizedOs, 'ubuntu')) {
            $osLabel = 'Ubuntu';
        } elseif (str_contains($normalizedOs, 'linux')) {
            $osLabel = 'Linux';
        }

        $osBreakdown[$osLabel] = ($osBreakdown[$osLabel] ?? 0) + 1;
    }

    $tahunInventarisValue = trim((string) ($specRow['tahun_inventaris'] ?? ''));
    if ($tahunInventarisValue !== '' && $tahunInventarisValue !== '-') {
        $tahunInventarisBreakdown[$tahunInventarisValue] = ($tahunInventarisBreakdown[$tahunInventarisValue] ?? 0) + 1;
    }

    $computerCondition = trim((string) ($specRow['kondisi'] ?? ''));
    if (!in_array($computerCondition, ['Baik', 'Rusak', 'Perbaikan'], true)) {
        $computerCondition = 'Baik';
    }
    $computerConditionSummary[$computerCondition] = ($computerConditionSummary[$computerCondition] ?? 0) + 1;
}

uksort($ramBreakdown, static function (string $left, string $right): int {
    return (float) $left <=> (float) $right;
});
arsort($osBreakdown);
uksort($tahunInventarisBreakdown, static function (string $left, string $right): int {
    return (int) $right <=> (int) $left;
});

$recentHistory = fetch_all(
    $pdo,
    'SELECT h.*, b.nama_barang, COALESCE(h.ruangan_nama, r.nama_ruangan) AS nama_ruangan_transaksi
     FROM histori_barang h
     INNER JOIN barang b ON b.id = h.barang_id
     LEFT JOIN ruangan r ON r.id = h.ruangan_id
     ORDER BY h.tanggal DESC, h.jam DESC, h.id DESC
     LIMIT 8'
);
$trendRows = fetch_all(
    $pdo,
    'SELECT tanggal,
            SUM(CASE WHEN tipe_transaksi = "masuk" THEN qty ELSE 0 END) AS masuk_total,
            SUM(CASE WHEN tipe_transaksi = "keluar" THEN qty ELSE 0 END) AS keluar_total
     FROM histori_barang
     WHERE tanggal >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 DAY)
     GROUP BY tanggal
     ORDER BY tanggal ASC'
);
$trendMap = [];
foreach ($trendRows as $trendRow) {
    $trendMap[(string) $trendRow['tanggal']] = [
        'masuk' => (int) ($trendRow['masuk_total'] ?? 0),
        'keluar' => (int) ($trendRow['keluar_total'] ?? 0),
    ];
}
$trendLabels = [];
$trendMasukSeries = [];
$trendKeluarSeries = [];
for ($dayOffset = 6; $dayOffset >= 0; $dayOffset--) {
    $dayKey = date('Y-m-d', strtotime('-' . $dayOffset . ' day'));
    $trendLabels[] = date('d M', strtotime($dayKey));
    $trendMasukSeries[] = (int) ($trendMap[$dayKey]['masuk'] ?? 0);
    $trendKeluarSeries[] = (int) ($trendMap[$dayKey]['keluar'] ?? 0);
}
$trendMaxValue = max(array_merge([1], $trendMasukSeries, $trendKeluarSeries));
$buildTrendPoints = static function (array $values, int $width = 420, int $height = 220, int $paddingX = 28, int $paddingY = 26) use ($trendMaxValue): string {
    $count = count($values);
    if ($count === 0) {
        return '';
    }

    $innerWidth = $width - ($paddingX * 2);
    $innerHeight = $height - ($paddingY * 2);
    $stepX = $count > 1 ? $innerWidth / ($count - 1) : 0;
    $points = [];

    foreach ($values as $index => $value) {
        $x = $paddingX + ($stepX * $index);
        $ratio = $trendMaxValue > 0 ? ((float) $value / (float) $trendMaxValue) : 0;
        $y = $height - $paddingY - ($ratio * $innerHeight);
        $points[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
    }

    return implode(' ', $points);
};
$inventoryTotalDevices = array_sum($inventorySummaryBreakdown);
$clientPercent = $inventoryTotalDevices > 0 ? round(($inventorySummaryBreakdown['Komputer Client'] / $inventoryTotalDevices) * 100, 2) : 0;
$serverPercent = $inventoryTotalDevices > 0 ? round(($inventorySummaryBreakdown['Server'] / $inventoryTotalDevices) * 100, 2) : 0;
$inventoryRingStyle = sprintf(
    'conic-gradient(#2188ff 0%% %.2f%%, #25d2ff %.2f%% %.2f%%, #8b5cf6 %.2f%% 100%%)',
    $clientPercent,
    $clientPercent,
    $clientPercent + $serverPercent,
    $clientPercent + $serverPercent
);
$inventoryLegendColors = [
    'Komputer Client' => '#2188ff',
    'Server' => '#25d2ff',
    'Network' => '#8b5cf6',
    'Peripheral' => '#ffb020',
    'Lainnya' => '#94a3b8',
];
$dashboardIcon = static function (string $icon): string {
    switch ($icon) {
        case 'inventory':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3z"/><path d="M12 12l8-4.5"/><path d="M12 12v9"/><path d="M12 12L4 7.5"/></svg>';
        case 'room':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 8h.01"/><path d="M15 8h.01"/><path d="M9 12h.01"/><path d="M15 12h.01"/><path d="M10 21v-4h4v4"/></svg>';
        case 'incoming':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 4v12"/><path d="M7 11l5 5 5-5"/><path d="M5 20h14"/></svg>';
        case 'outgoing':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20V8"/><path d="M17 13l-5-5-5 5"/><path d="M5 4h14"/></svg>';
        case 'server':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="6" rx="2"/><rect x="4" y="14" width="16" height="6" rx="2"/><path d="M8 7h.01"/><path d="M8 17h.01"/><path d="M16 7h2"/><path d="M16 17h2"/></svg>';
        case 'client':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8"/><path d="M12 16v4"/></svg>';
        case 'windows':
            return '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 5.3L10.8 4v7.4H3V5.3zm8.8-1.4L21 2.5v8.9h-9.2V3.9zM3 12.6h7.8V20L3 18.7v-6.1zm8.8 0H21v8.9l-9.2-1.4v-7.5z"/></svg>';
        case 'linux':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M12 3c2.2 0 3.7 2.1 3.7 4.8 0 1-.2 2-.5 2.9 1.9 1 3.3 2.9 3.3 5.1 0 3.1-2.8 5.2-6.5 5.2S5.5 18.9 5.5 15.8c0-2.2 1.4-4.1 3.3-5.1-.3-.9-.5-1.9-.5-2.9C8.3 5.1 9.8 3 12 3z"/><path d="M9.5 10.5h.01"/><path d="M14.5 10.5h.01"/><path d="M9.5 15.8c1.1.8 3.9.8 5 0"/></svg>';
        case 'hypervisor':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 7h10v10H7z"/><path d="M3 12h4"/><path d="M17 12h4"/><path d="M12 3v4"/><path d="M12 17v4"/></svg>';
        case 'hdd':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="3"/><circle cx="12" cy="12" r="3.5"/><path d="M12 12l4 4"/></svg>';
        case 'alert':
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3l9 16H3L12 3z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>';
        default:
            return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/></svg>';
    }
};
$dashboardSparkline = static function (): string {
    return '<div class="dashboard-summary-sparkline" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>';
};
$buildTrendDots = static function (array $values, int $width = 420, int $height = 220, int $paddingX = 28, int $paddingY = 26) use ($trendMaxValue): array {
    $count = count($values);
    if ($count === 0) {
        return [];
    }

    $innerWidth = $width - ($paddingX * 2);
    $innerHeight = $height - ($paddingY * 2);
    $stepX = $count > 1 ? $innerWidth / ($count - 1) : 0;
    $points = [];

    foreach ($values as $index => $value) {
        $x = $paddingX + ($stepX * $index);
        $ratio = $trendMaxValue > 0 ? ((float) $value / (float) $trendMaxValue) : 0;
        $y = $height - $paddingY - ($ratio * $innerHeight);
        $points[] = [
            'x' => number_format($x, 2, '.', ''),
            'y' => number_format($y, 2, '.', ''),
            'value' => (int) $value,
        ];
    }

    return $points;
};
$buildTrendArea = static function (array $values, int $width = 420, int $height = 220, int $paddingX = 28, int $paddingY = 26) use ($buildTrendPoints): string {
    $linePoints = $buildTrendPoints($values, $width, $height, $paddingX, $paddingY);
    if ($linePoints === '') {
        return '';
    }

    $firstX = number_format($paddingX, 2, '.', '');
    $lastX = number_format($width - $paddingX, 2, '.', '');
    $baseY = number_format($height - $paddingY, 2, '.', '');

    return $firstX . ',' . $baseY . ' ' . $linePoints . ' ' . $lastX . ',' . $baseY;
};
$trendPeakValue = max(array_merge([0], $trendMasukSeries, $trendKeluarSeries));
$trendMasukDots = $buildTrendDots($trendMasukSeries);
$trendKeluarDots = $buildTrendDots($trendKeluarSeries);

$pageScripts = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    const deviceStatsRoot = document.querySelector('[data-device-dashboard]');
    if (!deviceStatsRoot) {
        return;
    }

    const filterButtons = deviceStatsRoot.querySelectorAll('[data-device-filter]');
    const statsUrl = deviceStatsRoot.getAttribute('data-stats-url');

    const applyStats = function (stats) {
        if (!stats) {
            return;
        }

        deviceStatsRoot.querySelectorAll('[data-device-stat]').forEach(function (element) {
            const key = element.getAttribute('data-device-stat');
            if (Object.prototype.hasOwnProperty.call(stats, key)) {
                element.textContent = new Intl.NumberFormat('id-ID').format(Number(stats[key] || 0));
            }
        });
    };

    const setActiveFilter = function (filter) {
        filterButtons.forEach(function (button) {
            const isActive = button.getAttribute('data-device-filter') === filter;
            button.classList.toggle('active', isActive);
            button.classList.toggle('btn-primary', isActive);
            button.classList.toggle('btn-outline-primary', !isActive);
        });
    };

    const fetchStats = function (filter) {
        if (!statsUrl) {
            return;
        }

        deviceStatsRoot.classList.add('opacity-75');
        fetch(statsUrl + '?device_filter=' + encodeURIComponent(filter), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return {
                        ok: response.ok,
                        data: data
                    };
                });
            })
            .then(function (result) {
                if (!result.ok || !result.data || result.data.success !== true) {
                    throw new Error('Statistik device belum berhasil dimuat.');
                }

                applyStats(result.data.stats);
                setActiveFilter(filter);
            })
            .catch(function () {
                // Biarkan angka lama tetap tampil jika refresh statistik gagal.
            })
            .finally(function () {
                deviceStatsRoot.classList.remove('opacity-75');
            });
    };

    filterButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            fetchStats(button.getAttribute('data-device-filter') || 'ALL');
        });
    });
});
JS;

require_once BASE_PATH . '/includes/layout_top.php';
?>
<div class="dashboard-neon-grid">
    <section class="dashboard-neon-metrics">
        <div class="dashboard-summary-card summary-blue">
            <div class="dashboard-summary-copy">
                <span>Total Barang</span>
                <h3><?= format_number($totalBarang); ?></h3>
                <small><?= format_number($stokTotal); ?> unit stok aktif</small>
            </div>
            <div class="dashboard-summary-icon"><?= $dashboardIcon('inventory'); ?></div>
            <?= $dashboardSparkline(); ?>
        </div>
        <div class="dashboard-summary-card summary-violet">
            <div class="dashboard-summary-copy">
                <span>Total Ruangan</span>
                <h3><?= format_number($totalRuangan); ?></h3>
                <small>Ruangan terdaftar</small>
            </div>
            <div class="dashboard-summary-icon"><?= $dashboardIcon('room'); ?></div>
            <?= $dashboardSparkline(); ?>
        </div>
        <div class="dashboard-summary-card summary-green">
            <div class="dashboard-summary-copy">
                <span>Barang Masuk Bulan Ini</span>
                <h3><?= format_number($barangMasukBulanIni); ?></h3>
                <small>Akumulasi transaksi masuk</small>
            </div>
            <div class="dashboard-summary-icon"><?= $dashboardIcon('incoming'); ?></div>
            <?= $dashboardSparkline(); ?>
        </div>
        <div class="dashboard-summary-card summary-red">
            <div class="dashboard-summary-copy">
                <span>Barang Keluar Bulan Ini</span>
                <h3><?= format_number($barangKeluarBulanIni); ?></h3>
                <small>Akumulasi transaksi keluar</small>
            </div>
            <div class="dashboard-summary-icon"><?= $dashboardIcon('outgoing'); ?></div>
            <?= $dashboardSparkline(); ?>
        </div>
        <div class="dashboard-surface dashboard-ring-card">
            <div class="dashboard-surface-heading">
                <div>
                    <h5>Ringkasan Inventaris</h5>
                    <p>Total device yang sudah masuk ke sistem.</p>
                </div>
            </div>
            <div class="dashboard-ring-layout">
                <div class="dashboard-ring-wrap" style="--ring-fill: <?= e($inventoryRingStyle); ?>;">
                    <div class="dashboard-ring-core">
                        <strong><?= format_number($inventoryTotalDevices); ?></strong>
                        <span>Total Device</span>
                    </div>
                </div>
                <div class="dashboard-ring-legend">
                    <?php foreach ($inventorySummaryBreakdown as $label => $value): ?>
                        <div class="dashboard-legend-item">
                            <span><i class="dashboard-legend-dot" style="--legend-color: <?= e($inventoryLegendColors[$label] ?? '#2188ff'); ?>;"></i><?= e($label); ?></span>
                            <strong><?= format_number($value); ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="dashboard-feature-grid">
        <div
            class="dashboard-surface"
            data-device-dashboard
            data-stats-url="<?= e(url('modules/dashboard/device_stats.php')); ?>"
        >
            <div class="dashboard-surface-heading">
                <div>
                    <h5>Statistik Device Otomatis</h5>
                    <p>Ringkasan server dan komputer client dari hasil upload agent inventory.</p>
                </div>
                <div class="dashboard-filter-pills" role="group" aria-label="Filter statistik device">
                    <?php foreach (dashboard_device_filter_options() as $filterValue => $filterLabel): ?>
                        <button
                            type="button"
                            class="btn <?= $deviceDashboardFilter === $filterValue ? 'btn-primary active' : 'btn-outline-primary'; ?>"
                            data-device-filter="<?= e($filterValue); ?>"
                        ><?= e($filterLabel); ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="dashboard-device-grid">
                <a class="dashboard-mini-card mini-amber" href="<?= e(url('modules/komputer/index.php?device_type=SERVER')); ?>">
                    <div class="dashboard-mini-icon"><?= $dashboardIcon('server'); ?></div>
                    <span>Total Server</span>
                    <strong data-device-stat="total_server"><?= format_number($deviceStats['total_server']); ?></strong>
                    <small>Semua server yang terdata</small>
                </a>
                <a class="dashboard-mini-card mini-blue" href="<?= e(url('modules/komputer/index.php?device_type=CLIENT')); ?>">
                    <div class="dashboard-mini-icon"><?= $dashboardIcon('client'); ?></div>
                    <span>Total Komputer Client</span>
                    <strong data-device-stat="total_client"><?= format_number($deviceStats['total_client']); ?></strong>
                    <small>Client aktif pada inventory</small>
                </a>
                <a class="dashboard-mini-card mini-green" href="<?= e(url('modules/komputer/index.php?device_type=SERVER')); ?>">
                    <div class="dashboard-mini-icon"><?= $dashboardIcon('windows'); ?></div>
                    <span>Windows Server</span>
                    <strong data-device-stat="windows_server"><?= format_number($deviceStats['windows_server']); ?></strong>
                    <small>Server berbasis Windows</small>
                </a>
                <a class="dashboard-mini-card mini-red" href="<?= e(url('modules/komputer/index.php?device_type=SERVER')); ?>">
                    <div class="dashboard-mini-icon"><?= $dashboardIcon('linux'); ?></div>
                    <span>Linux Server</span>
                    <strong data-device-stat="linux_server"><?= format_number($deviceStats['linux_server']); ?></strong>
                    <small>Server berbasis Linux</small>
                </a>
                <a class="dashboard-mini-card mini-violet" href="<?= e(url('modules/komputer/index.php?device_type=SERVER')); ?>">
                    <div class="dashboard-mini-icon"><?= $dashboardIcon('hypervisor'); ?></div>
                    <span>Hypervisor</span>
                    <strong data-device-stat="hypervisor"><?= format_number($deviceStats['hypervisor']); ?></strong>
                    <small>Server virtual / memakai hypervisor</small>
                </a>
                <a class="dashboard-mini-card mini-gold" href="<?= e(url('modules/komputer/index.php?storage_mode=hdd_only')); ?>">
                    <div class="dashboard-mini-icon"><?= $dashboardIcon('hdd'); ?></div>
                    <span>HDD Only</span>
                    <strong data-device-stat="hdd_only"><?= format_number($deviceStats['hdd_only']); ?></strong>
                    <small>Perangkat tanpa SSD</small>
                </a>
                <a class="dashboard-mini-card mini-danger" href="<?= e(url('modules/komputer/index.php?device_type=SERVER&kondisi=Rusak')); ?>">
                    <div class="dashboard-mini-icon"><?= $dashboardIcon('alert'); ?></div>
                    <span>Server Bermasalah</span>
                    <strong data-device-stat="server_bermasalah"><?= format_number($deviceStats['server_bermasalah']); ?></strong>
                    <small>Server dengan kondisi bukan baik</small>
                </a>
            </div>
        </div>

        <div class="dashboard-surface dashboard-trend-card">
            <div class="dashboard-surface-heading">
                <div>
                    <h5>Tren Inventaris</h5>
                    <p>Pergerakan barang masuk dan keluar 7 hari terakhir.</p>
                </div>
                <div class="dashboard-heading-meta">
                    <span class="dashboard-chip">7 Hari Terakhir</span>
                    <span class="dashboard-chip dashboard-chip-highlight">Puncak <?= format_number($trendPeakValue); ?> unit</span>
                </div>
            </div>
            <div class="dashboard-trend-chart">
                <div class="dashboard-trend-canvas">
                    <div class="dashboard-trend-glow trend-glow-in" aria-hidden="true"></div>
                    <div class="dashboard-trend-glow trend-glow-out" aria-hidden="true"></div>
                    <svg viewBox="0 0 420 220" preserveAspectRatio="none" role="img" aria-label="Grafik tren inventaris">
                        <?php for ($gridIndex = 0; $gridIndex < 5; $gridIndex++): ?>
                            <?php $y = 26 + (($gridIndex / 4) * 168); ?>
                            <line x1="28" y1="<?= number_format($y, 2, '.', ''); ?>" x2="392" y2="<?= number_format($y, 2, '.', ''); ?>" class="trend-grid-line"></line>
                        <?php endfor; ?>
                        <polygon points="<?= e($buildTrendArea($trendMasukSeries)); ?>" class="trend-area trend-area-in"></polygon>
                        <polygon points="<?= e($buildTrendArea($trendKeluarSeries)); ?>" class="trend-area trend-area-out"></polygon>
                        <polyline points="<?= e($buildTrendPoints($trendMasukSeries)); ?>" class="trend-line trend-line-in"></polyline>
                        <polyline points="<?= e($buildTrendPoints($trendKeluarSeries)); ?>" class="trend-line trend-line-out"></polyline>
                        <?php foreach ($trendMasukDots as $dot): ?>
                            <circle cx="<?= e((string) $dot['x']); ?>" cy="<?= e((string) $dot['y']); ?>" r="4.2" class="trend-dot trend-dot-in"></circle>
                        <?php endforeach; ?>
                        <?php foreach ($trendKeluarDots as $dot): ?>
                            <circle cx="<?= e((string) $dot['x']); ?>" cy="<?= e((string) $dot['y']); ?>" r="4.2" class="trend-dot trend-dot-out"></circle>
                        <?php endforeach; ?>
                    </svg>
                </div>
                <div class="dashboard-trend-labels">
                    <?php foreach ($trendLabels as $trendLabel): ?>
                        <span><?= e($trendLabel); ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="dashboard-trend-legend">
                    <span><i class="legend-dot legend-dot-in"></i>Barang Masuk</span>
                    <span><i class="legend-dot legend-dot-out"></i>Barang Keluar</span>
                </div>
            </div>
        </div>
    </section>

    <section class="dashboard-breakdown-grid">
        <div class="dashboard-surface">
            <div class="dashboard-surface-heading">
                <div>
                    <h5>RAM Per Jenis</h5>
                    <p>Jumlah komputer untuk tiap kapasitas RAM.</p>
                </div>
            </div>
            <div class="dashboard-bar-list">
                <?php if (!$ramBreakdown): ?>
                    <div class="dashboard-empty-copy">Belum ada data RAM komputer client.</div>
                <?php endif; ?>
                <?php $maxRamBreakdown = $ramBreakdown ? max($ramBreakdown) : 1; ?>
                <?php foreach ($ramBreakdown as $ramLabel => $totalRamType): ?>
                    <a class="dashboard-bar-item" href="<?= e(url('modules/komputer/index.php?ram_group=' . urlencode($ramLabel))); ?>">
                        <strong><?= e($ramLabel); ?></strong>
                        <span class="dashboard-bar-track"><i style="width: <?= e((string) max(12, round(($totalRamType / $maxRamBreakdown) * 100))); ?>%;"></i></span>
                        <em><?= format_number($totalRamType); ?> komputer</em>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="dashboard-surface">
            <div class="dashboard-surface-heading">
                <div>
                    <h5>OS Per Jenis</h5>
                    <p>Jumlah komputer untuk tiap kelompok sistem operasi.</p>
                </div>
            </div>
            <div class="dashboard-bar-list">
                <?php if (!$osBreakdown): ?>
                    <div class="dashboard-empty-copy">Belum ada data OS komputer client.</div>
                <?php endif; ?>
                <?php $maxOsBreakdown = $osBreakdown ? max($osBreakdown) : 1; ?>
                <?php foreach ($osBreakdown as $osLabel => $totalOsType): ?>
                    <a class="dashboard-bar-item" href="<?= e(url('modules/komputer/index.php?os_group=' . urlencode($osLabel))); ?>">
                        <strong><?= e($osLabel); ?></strong>
                        <span class="dashboard-bar-track"><i style="width: <?= e((string) max(12, round(($totalOsType / $maxOsBreakdown) * 100))); ?>%;"></i></span>
                        <em><?= format_number($totalOsType); ?> komputer</em>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="dashboard-surface">
            <div class="dashboard-surface-heading">
                <div>
                    <h5>PC Per Tahun Inventaris</h5>
                    <p>Jumlah komputer client untuk tiap tahun inventaris.</p>
                </div>
            </div>
            <div class="dashboard-bar-list">
                <?php if (!$tahunInventarisBreakdown): ?>
                    <div class="dashboard-empty-copy">Belum ada data tahun inventaris komputer client.</div>
                <?php endif; ?>
                <?php $maxTahunInventaris = $tahunInventarisBreakdown ? max($tahunInventarisBreakdown) : 1; ?>
                <?php foreach ($tahunInventarisBreakdown as $tahunInventaris => $totalTahunInventaris): ?>
                    <a class="dashboard-bar-item" href="<?= e(url('modules/komputer/index.php?tahun_inventaris=' . urlencode($tahunInventaris))); ?>">
                        <strong><?= e($tahunInventaris); ?></strong>
                        <span class="dashboard-bar-track"><i style="width: <?= e((string) max(12, round(($totalTahunInventaris / $maxTahunInventaris) * 100))); ?>%;"></i></span>
                        <em><?= format_number($totalTahunInventaris); ?> komputer</em>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="dashboard-bottom-grid">
        <div class="dashboard-surface">
            <div class="dashboard-surface-heading">
                <div>
                    <h5>Riwayat Transaksi Terbaru</h5>
                    <p>Pantau mutasi stok terakhir dari semua ruangan.</p>
                </div>
                <a href="<?= e(url('modules/transaksi/history.php')); ?>" class="dashboard-link-chip">Lihat Semua</a>
            </div>
            <div class="table-responsive dashboard-table-wrap">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th>Ruangan</th>
                            <th>Jenis</th>
                            <th>Qty</th>
                            <th>Waktu</th>
                            <th>Pengguna</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$recentHistory): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Belum ada transaksi.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($recentHistory as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= e($row['nama_barang']); ?></strong>
                                    <div class="small text-muted"><?= e($row['keterangan'] ?: '-'); ?></div>
                                </td>
                                <td><?= e($row['nama_ruangan_transaksi'] ?: '-'); ?></td>
                                <td><span class="badge text-bg-<?= transaction_badge($row['tipe_transaksi']); ?>"><?= ucfirst(e($row['tipe_transaksi'])); ?></span></td>
                                <td><?= format_number($row['qty']); ?></td>
                                <td><?= e(day_name_id($row['tanggal'])); ?>, <?= e(format_date_id($row['tanggal'])); ?> <?= e(format_time_id($row['jam'])); ?></td>
                                <td><?= e($row['nama_pengguna']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="dashboard-surface">
            <div class="dashboard-surface-heading">
                <div>
                    <h5>Data Kondisi Komputer</h5>
                    <p>Pantau jumlah komputer baik, rusak, dan perbaikan dari dashboard.</p>
                </div>
                <a href="<?= e(url('modules/kondisi_komputer/index.php')); ?>" class="dashboard-link-chip">Lihat Detail</a>
            </div>
            <div class="dashboard-condition-grid">
                <a class="dashboard-condition-card condition-good" href="<?= e(url('modules/komputer/index.php?kondisi=Baik')); ?>">
                    <strong>Komputer Baik</strong>
                    <span><?= format_number($computerConditionSummary['Baik']); ?></span>
                    <small>komputer dalam kondisi baik.</small>
                </a>
                <a class="dashboard-condition-card condition-bad" href="<?= e(url('modules/komputer/index.php?kondisi=Rusak')); ?>">
                    <strong>Komputer Rusak</strong>
                    <span><?= format_number($computerConditionSummary['Rusak']); ?></span>
                    <small>komputer perlu perhatian.</small>
                </a>
                <a class="dashboard-condition-card condition-fix" href="<?= e(url('modules/komputer/index.php?kondisi=Perbaikan')); ?>">
                    <strong>Komputer Perbaikan</strong>
                    <span><?= format_number($computerConditionSummary['Perbaikan']); ?></span>
                    <small>komputer sedang diperbaiki.</small>
                </a>
            </div>
        </div>
    </section>
</div>

<?php require_once BASE_PATH . '/includes/layout_bottom.php'; ?>
