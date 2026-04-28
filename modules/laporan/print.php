<?php

require_once dirname(__DIR__, 2) . '/config/app.php';

require_login();

$filters = normalize_report_filters($_GET);
$rows = get_report_rows($pdo, $filters);
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Print Laporan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-size: 12px; padding: 24px; }
        .table th, .table td { font-size: 12px; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-1"><?= e(APP_NAME); ?></h3>
            <div>Dicetak pada <?= e(date('d M Y H:i')); ?></div>
        </div>
        <button class="btn btn-dark no-print" onclick="window.print()">Print</button>
    </div>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>No</th>
                <th>Nama Barang</th>
                <th>Ruangan</th>
                <th>Kondisi</th>
                <th>Transaksi</th>
                <th>Qty</th>
                <th>Stok</th>
                <th>Pengguna</th>
                <th>Tujuan</th>
                <th>Hari / Tanggal / Jam</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $index => $row): ?>
                <tr>
                    <td><?= $index + 1; ?></td>
                    <td><?= e($row['nama_barang']); ?></td>
                    <td><?= e($row['nama_ruangan_transaksi'] ?: '-'); ?></td>
                    <td><?= e($row['kondisi'] ?: '-'); ?></td>
                    <td><?= ucfirst(e($row['tipe_transaksi'])); ?></td>
                    <td><?= format_number($row['qty']); ?></td>
                    <td><?= format_number($row['stok_sebelum']); ?> -> <?= format_number($row['stok_sesudah']); ?></td>
                    <td><?= e($row['nama_pengguna']); ?></td>
                    <td><?= e($row['tujuan'] ?: '-'); ?></td>
                    <td><?= e(day_name_id($row['tanggal'])); ?>, <?= e(format_date_id($row['tanggal'])); ?> <?= e(format_time_id($row['jam'])); ?></td>
                    <td><?= e($row['keterangan'] ?: '-'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
