<?php

require_once dirname(__DIR__, 2) . '/config/app.php';

$pageTitle = 'List IP Komputer Client';
$selectedSegment = trim($_GET['segment'] ?? '');

$rows = fetch_all(
    $pdo,
    'SELECT kc.id, kc.hostname, kc.ip_address, kc.ruangan, kc.petugas, kc.tanggal, kc.jam
     FROM komputer_client kc
     INNER JOIN (
        SELECT ip_address, MAX(id) AS latest_id
        FROM komputer_client
        WHERE ip_address IS NOT NULL AND ip_address <> ""
        GROUP BY ip_address
     ) latest ON latest.latest_id = kc.id
     ORDER BY kc.ip_address ASC'
);

$segmentCounts = [];
$validIpCount = 0;

foreach ($rows as &$row) {
    $ipAddress = trim((string) ($row['ip_address'] ?? ''));
    $segments = preg_match('/^\d{1,3}(?:\.\d{1,3}){3}$/', $ipAddress) ? explode('.', $ipAddress) : [];
    $row['ip_segments'] = $segments;
    $row['ip_segment_label'] = count($segments) >= 3 ? $segments[0] . '.' . $segments[1] . '.' . $segments[2] : '-';

    if ($segments) {
        $validIpCount++;
        $segmentKey = $row['ip_segment_label'];
        $segmentCounts[$segmentKey] = ($segmentCounts[$segmentKey] ?? 0) + 1;
    }
}
unset($row);

ksort($segmentCounts, SORT_NATURAL);
$totalSegments = count($segmentCounts);
$rows = array_values(array_filter($rows, static function (array $row) use ($selectedSegment): bool {
    if ($selectedSegment === '') {
        return true;
    }

    return (string) ($row['ip_segment_label'] ?? '') === $selectedSegment;
}));
$filteredIpCount = count($rows);
$connectedRoomCount = count(array_unique(array_filter(array_map(static fn (array $row): string => (string) ($row['ruangan'] ?? ''), $rows))));

require_once BASE_PATH . '/includes/layout_top.php';
?>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="metric-card">
            <span class="metric-label">Total IP Terdeteksi</span>
            <h3><?= format_number($validIpCount); ?></h3>
            <p>Jumlah IP komputer client yang sudah terisi.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card">
            <span class="metric-label">Total Segment</span>
            <h3><?= format_number($totalSegments); ?></h3>
            <p>Dihitung dari tiga blok awal IP, misalnya `192.168.2`.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card">
            <span class="metric-label"><?= $selectedSegment !== '' ? 'IP Sesuai Filter' : 'Ruangan Terhubung'; ?></span>
            <h3><?= format_number($selectedSegment !== '' ? $filteredIpCount : $connectedRoomCount); ?></h3>
            <p>
                <?= $selectedSegment !== '' ? 'Jumlah IP yang sesuai dengan segment yang dipilih.' : 'Total ruangan yang sudah memiliki data IP client.'; ?>
            </p>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white border-0 pt-4">
        <h5 class="mb-1">Ringkasan Segment IP</h5>
        <p class="text-muted mb-0">Memudahkan melihat persebaran jaringan komputer client per segment.</p>
    </div>
    <div class="card-body">
        <?php if (!$segmentCounts): ?>
            <p class="text-muted mb-0">Belum ada data segment IP yang bisa ditampilkan.</p>
        <?php else: ?>
            <form method="get" class="row g-3 align-items-end mb-4">
                <div class="col-md-5 col-lg-4">
                    <label class="form-label">Filter Segment IP</label>
                    <select name="segment" class="form-select">
                        <option value="">Semua segment</option>
                        <?php foreach ($segmentCounts as $segment => $count): ?>
                            <option value="<?= e($segment); ?>" <?= $selectedSegment === $segment ? 'selected' : ''; ?>>
                                <?= e($segment); ?> (<?= format_number($count); ?> client)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-7 col-lg-8 d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary">Terapkan</button>
                    <a href="<?= e(url('modules/ip_komputer/index.php')); ?>" class="btn btn-light">Reset</a>
                </div>
            </form>

            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($segmentCounts as $segment => $count): ?>
                    <a
                        href="<?= e(url('modules/ip_komputer/index.php' . ($segment !== '' ? '?segment=' . urlencode($segment) : ''))); ?>"
                        class="badge rounded-pill ip-segment-summary text-decoration-none <?= $selectedSegment === $segment ? 'ip-segment-summary-active' : ''; ?>"
                    >
                        <strong><?= e($segment); ?></strong>
                        <span><?= format_number($count); ?> client</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-4">
        <h5 class="mb-1">Daftar IP Komputer Client</h5>
        <p class="text-muted mb-0">
            Menampilkan data IP terbaru untuk setiap komputer client yang sudah memiliki alamat IP.
            <?php if ($selectedSegment !== ''): ?>
                Filter aktif: <strong><?= e($selectedSegment); ?></strong>
            <?php endif; ?>
        </p>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle datatable">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>IP</th>
                        <th>Segment IP</th>
                        <th>Ruangan</th>
                        <th>Nama User</th>
                        <th>Hostname</th>
                        <th>Tanggal Scan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <?= $selectedSegment !== '' ? 'Belum ada IP komputer client untuk segment yang dipilih.' : 'Belum ada IP komputer client yang terdata.'; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1; ?></td>
                            <td>
                                <?php if ($row['ip_segments']): ?>
                                    <div class="ip-segment-group">
                                        <?php foreach ($row['ip_segments'] as $segmentIndex => $segment): ?>
                                            <span class="ip-segment-badge ip-segment-<?= $segmentIndex + 1; ?>"><?= e($segment); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                                <div class="small text-muted mt-1"><?= e($row['ip_address'] ?: '-'); ?></div>
                            </td>
                            <td>
                                <span class="badge rounded-pill text-bg-light border"><?= e($row['ip_segment_label']); ?></span>
                            </td>
                            <td><?= e($row['ruangan'] ?: '-'); ?></td>
                            <td><?= e($row['petugas'] ?: '-'); ?></td>
                            <td><?= e($row['hostname'] ?: '-'); ?></td>
                            <td><?= e(format_date_id($row['tanggal'] ?? null)); ?> <?= e(format_time_id($row['jam'] ?? null)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/layout_bottom.php'; ?>
