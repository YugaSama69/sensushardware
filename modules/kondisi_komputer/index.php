<?php

require_once dirname(__DIR__, 2) . '/config/app.php';

$pageTitle = 'Data Kondisi Komputer';

$conditionRows = fetch_all(
    $pdo,
    'SELECT COALESCE(NULLIF(kondisi, ""), "Baik") AS kondisi_label, COUNT(*) AS total
     FROM komputer_client
     GROUP BY COALESCE(NULLIF(kondisi, ""), "Baik")'
);

$conditionSummary = [
    'Baik' => 0,
    'Rusak' => 0,
    'Perbaikan' => 0,
];

foreach ($conditionRows as $conditionRow) {
    $conditionName = (string) ($conditionRow['kondisi_label'] ?? '');
    if (array_key_exists($conditionName, $conditionSummary)) {
        $conditionSummary[$conditionName] = (int) ($conditionRow['total'] ?? 0);
    }
}

$totalKomputer = array_sum($conditionSummary);

require_once BASE_PATH . '/includes/layout_top.php';
?>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-3">
        <a class="metric-card card-sky d-block text-decoration-none" href="<?= e(url('modules/komputer/index.php')); ?>">
            <span>Total Komputer Client</span>
            <h3><?= format_number($totalKomputer); ?></h3>
            <small>Seluruh komputer yang sudah terdata</small>
        </a>
    </div>
    <div class="col-md-6 col-xl-3">
        <a class="metric-card card-emerald d-block text-decoration-none" href="<?= e(url('modules/komputer/index.php?kondisi=Baik')); ?>">
            <span>Jumlah Kondisi Komputer Baik</span>
            <h3><?= format_number($conditionSummary['Baik']); ?></h3>
            <small>Klik untuk melihat komputer kondisi baik</small>
        </a>
    </div>
    <div class="col-md-6 col-xl-3">
        <a class="metric-card card-rose d-block text-decoration-none" href="<?= e(url('modules/komputer/index.php?kondisi=Rusak')); ?>">
            <span>Jumlah Kondisi Komputer Rusak</span>
            <h3><?= format_number($conditionSummary['Rusak']); ?></h3>
            <small>Klik untuk melihat komputer kondisi rusak</small>
        </a>
    </div>
    <div class="col-md-6 col-xl-3">
        <a class="metric-card card-amber d-block text-decoration-none" href="<?= e(url('modules/komputer/index.php?kondisi=Perbaikan')); ?>">
            <span>Jumlah Kondisi Komputer Perbaikan</span>
            <h3><?= format_number($conditionSummary['Perbaikan']); ?></h3>
            <small>Klik untuk melihat komputer dalam perbaikan</small>
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 pt-4 pb-0">
        <h5 class="mb-1">Ringkasan Kondisi Komputer</h5>
        <p class="text-muted mb-0">Monitoring kondisi komputer client berdasarkan status inventaris saat ini.</p>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Kondisi</th>
                        <th>Jumlah Komputer</th>
                        <th>Persentase</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $rowNumber = 1; ?>
                    <?php foreach ($conditionSummary as $conditionName => $conditionTotal): ?>
                        <tr>
                            <td><?= $rowNumber++; ?></td>
                            <td><span class="badge text-bg-<?= condition_badge($conditionName); ?>"><?= e($conditionName); ?></span></td>
                            <td><?= format_number($conditionTotal); ?> komputer</td>
                            <td><?= $totalKomputer > 0 ? format_percentage(($conditionTotal / $totalKomputer) * 100) : '0%'; ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('modules/komputer/index.php?kondisi=' . urlencode($conditionName))); ?>">Lihat Data</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/layout_bottom.php'; ?>
