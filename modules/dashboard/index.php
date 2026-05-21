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

$pageScripts = <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
    const deviceStatsRoot = document.querySelector('[data-device-dashboard]');
    if (!deviceStatsRoot) {
        return;
    }

    const filterButtons = deviceStatsRoot.querySelectorAll('[data-device-filter]');
    const generatedAtLabel = deviceStatsRoot.querySelector('[data-device-generated-at]');
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

        if (generatedAtLabel && stats.generated_at) {
            generatedAtLabel.textContent = 'Update cache: ' + stats.generated_at.replace('T', ' ').replace(/\+.*/, '');
        }
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
                if (generatedAtLabel) {
                    generatedAtLabel.textContent = 'Statistik device belum berhasil dimuat.';
                }
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

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="metric-card card-sky">
            <span>Total Barang</span>
            <h3><?= format_number($totalBarang); ?></h3>
            <small><?= format_number($stokTotal); ?> unit stok aktif</small>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="metric-card card-amber">
            <span>Total Ruangan</span>
            <h3><?= format_number($totalRuangan); ?></h3>
            <small>Ruangan terdaftar</small>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="metric-card card-emerald">
            <span>Barang Masuk Bulan Ini</span>
            <h3><?= format_number($barangMasukBulanIni); ?></h3>
            <small>Akumulasi transaksi masuk</small>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="metric-card card-rose">
            <span>Barang Keluar Bulan Ini</span>
            <h3><?= format_number($barangKeluarBulanIni); ?></h3>
            <small>Akumulasi transaksi keluar</small>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <a class="metric-card card-sky d-block text-decoration-none" href="<?= e(url('modules/komputer/index.php')); ?>">
            <span>Total Komputer Client</span>
            <h3><?= format_number($totalKomputerClient); ?></h3>
            <small>Data komputer client terdaftar</small>
        </a>
    </div>
    <div class="col-md-6 col-xl-3">
        <a class="metric-card card-amber d-block text-decoration-none" href="<?= e(url('modules/komputer/index.php')); ?>">
            <span>Jenis RAM</span>
            <h3><?= format_number(count($ramBreakdown)); ?></h3>
            <small>Kelompok RAM yang terdeteksi</small>
        </a>
    </div>
    <div class="col-md-6 col-xl-3">
        <a class="metric-card card-emerald d-block text-decoration-none" href="<?= e(url('modules/komputer/index.php')); ?>">
            <span>Jenis OS</span>
            <h3><?= format_number(count($osBreakdown)); ?></h3>
            <small>Kelompok OS yang terdeteksi</small>
        </a>
    </div>
    <div class="col-md-6 col-xl-3">
        <a class="metric-card card-rose d-block text-decoration-none" href="<?= e(url('modules/komputer/index.php?storage_mode=hdd_only')); ?>">
            <span>Komputer HDD Saja</span>
            <h3><?= format_number($totalKomputerHddOnly); ?></h3>
            <small>Belum SSD dan masih menggunakan HDD</small>
        </a>
    </div>
</div>

<div
    class="card border-0 shadow-sm mb-4"
    data-device-dashboard
    data-stats-url="<?= e(url('modules/dashboard/device_stats.php')); ?>"
>
    <div class="card-header bg-white border-0 pt-4 pb-0">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div>
                <h5 class="mb-1">Statistik Device Otomatis</h5>
                <p class="text-muted mb-0">Ringkasan server dan komputer client dari hasil upload agent inventory.</p>
            </div>
            <div class="btn-group" role="group" aria-label="Filter statistik device">
                <?php foreach (dashboard_device_filter_options() as $filterValue => $filterLabel): ?>
                    <button
                        type="button"
                        class="btn <?= $deviceDashboardFilter === $filterValue ? 'btn-primary active' : 'btn-outline-primary'; ?>"
                        data-device-filter="<?= e($filterValue); ?>"
                    ><?= e($filterLabel); ?></button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-4 mb-3">
            <div class="col-md-6 col-xl-3">
                <a class="metric-card card-amber d-block text-decoration-none" href="<?= e(url('modules/komputer/index.php?device_type=SERVER')); ?>">
                    <span>Total Server</span>
                    <h3 data-device-stat="total_server"><?= format_number($deviceStats['total_server']); ?></h3>
                    <small>Semua server yang terdata</small>
                </a>
            </div>
            <div class="col-md-6 col-xl-3">
                <a class="metric-card card-sky d-block text-decoration-none" href="<?= e(url('modules/komputer/index.php?device_type=CLIENT')); ?>">
                    <span>Total Komputer Client</span>
                    <h3 data-device-stat="total_client"><?= format_number($deviceStats['total_client']); ?></h3>
                    <small>Client aktif pada inventory</small>
                </a>
            </div>
            <div class="col-md-6 col-xl-3">
                <a class="metric-card card-emerald d-block text-decoration-none" href="<?= e(url('modules/komputer/index.php?device_type=SERVER')); ?>">
                    <span>Windows Server</span>
                    <h3 data-device-stat="windows_server"><?= format_number($deviceStats['windows_server']); ?></h3>
                    <small>Server berbasis Windows</small>
                </a>
            </div>
            <div class="col-md-6 col-xl-3">
                <a class="metric-card card-rose d-block text-decoration-none" href="<?= e(url('modules/komputer/index.php?device_type=SERVER')); ?>">
                    <span>Linux Server</span>
                    <h3 data-device-stat="linux_server"><?= format_number($deviceStats['linux_server']); ?></h3>
                    <small>Server berbasis Linux</small>
                </a>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-xl-4">
                <a class="metric-card card-sky d-block text-decoration-none" href="<?= e(url('modules/komputer/index.php?device_type=SERVER')); ?>">
                    <span>Hypervisor</span>
                    <h3 data-device-stat="hypervisor"><?= format_number($deviceStats['hypervisor']); ?></h3>
                    <small>Server virtual / memakai hypervisor</small>
                </a>
            </div>
            <div class="col-md-6 col-xl-4">
                <a class="metric-card card-amber d-block text-decoration-none" href="<?= e(url('modules/komputer/index.php?storage_mode=hdd_only')); ?>">
                    <span>HDD Only</span>
                    <h3 data-device-stat="hdd_only"><?= format_number($deviceStats['hdd_only']); ?></h3>
                    <small>Perangkat tanpa SSD</small>
                </a>
            </div>
            <div class="col-md-6 col-xl-4">
                <a class="metric-card card-rose d-block text-decoration-none" href="<?= e(url('modules/komputer/index.php?device_type=SERVER&kondisi=Rusak')); ?>">
                    <span>Server Bermasalah</span>
                    <h3 data-device-stat="server_bermasalah"><?= format_number($deviceStats['server_bermasalah']); ?></h3>
                    <small>Server dengan kondisi bukan baik</small>
                </a>
            </div>
        </div>
        <div class="small text-muted mt-3" data-device-generated-at>
            Update cache: <?= e(str_replace('T', ' ', preg_replace('/\+.*/', '', (string) $deviceStats['generated_at']))); ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h5 class="mb-1">RAM Per Jenis</h5>
                <p class="text-muted mb-0">Jumlah komputer untuk tiap kapasitas RAM.</p>
            </div>
            <div class="card-body d-grid gap-3">
                <?php if (!$ramBreakdown): ?>
                    <div class="text-muted">Belum ada data RAM komputer client.</div>
                <?php endif; ?>
                <?php foreach ($ramBreakdown as $ramLabel => $totalRamType): ?>
                    <a class="quick-link-card" href="<?= e(url('modules/komputer/index.php?ram_group=' . urlencode($ramLabel))); ?>">
                        <strong><?= e($ramLabel); ?></strong>
                        <span><?= format_number($totalRamType); ?> komputer</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h5 class="mb-1">OS Per Jenis</h5>
                <p class="text-muted mb-0">Jumlah komputer untuk tiap kelompok sistem operasi.</p>
            </div>
            <div class="card-body d-grid gap-3">
                <?php if (!$osBreakdown): ?>
                    <div class="text-muted">Belum ada data OS komputer client.</div>
                <?php endif; ?>
                <?php foreach ($osBreakdown as $osLabel => $totalOsType): ?>
                    <a class="quick-link-card" href="<?= e(url('modules/komputer/index.php?os_group=' . urlencode($osLabel))); ?>">
                        <strong><?= e($osLabel); ?></strong>
                        <span><?= format_number($totalOsType); ?> komputer</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h5 class="mb-1">PC Per Tahun Inventaris</h5>
                <p class="text-muted mb-0">Jumlah komputer client untuk tiap tahun inventaris.</p>
            </div>
            <div class="card-body d-grid gap-3">
                <?php if (!$tahunInventarisBreakdown): ?>
                    <div class="text-muted">Belum ada data tahun inventaris komputer client.</div>
                <?php endif; ?>
                <?php foreach ($tahunInventarisBreakdown as $tahunInventaris => $totalTahunInventaris): ?>
                    <a class="quick-link-card" href="<?= e(url('modules/komputer/index.php?tahun_inventaris=' . urlencode($tahunInventaris))); ?>">
                        <strong><?= e($tahunInventaris); ?></strong>
                        <span><?= format_number($totalTahunInventaris); ?> komputer</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h5 class="mb-1">Riwayat Transaksi Terbaru</h5>
                <p class="text-muted mb-0">Pantau mutasi stok terakhir dari semua ruangan.</p>
            </div>
            <div class="card-body">
                <div class="table-responsive">
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
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-0 pt-4 pb-0">
                <h5 class="mb-1">Data Kondisi Komputer</h5>
                <p class="text-muted mb-0">Pantau jumlah komputer baik, rusak, dan perbaikan dari dashboard.</p>
            </div>
            <div class="card-body d-grid gap-3">
                <a class="quick-link-card" href="<?= e(url('modules/komputer/index.php?kondisi=Baik')); ?>">
                    <strong>Komputer Baik</strong>
                    <span><?= format_number($computerConditionSummary['Baik']); ?> komputer dalam kondisi baik.</span>
                </a>
                <a class="quick-link-card" href="<?= e(url('modules/komputer/index.php?kondisi=Rusak')); ?>">
                    <strong>Komputer Rusak</strong>
                    <span><?= format_number($computerConditionSummary['Rusak']); ?> komputer perlu perhatian.</span>
                </a>
                <a class="quick-link-card" href="<?= e(url('modules/komputer/index.php?kondisi=Perbaikan')); ?>">
                    <strong>Komputer Perbaikan</strong>
                    <span><?= format_number($computerConditionSummary['Perbaikan']); ?> komputer sedang diperbaiki.</span>
                </a>
                <a class="quick-link-card" href="<?= e(url('modules/kondisi_komputer/index.php')); ?>">
                    <strong>Buka Data Kondisi Komputer</strong>
                    <span>Lihat ringkasan lengkap kondisi komputer client.</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/layout_bottom.php'; ?>
