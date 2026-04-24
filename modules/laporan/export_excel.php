<?php

require_once dirname(__DIR__, 2) . '/config/app.php';

require_login();

$filters = normalize_report_filters($_GET);
$rows = get_report_rows($pdo, $filters);

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename=laporan-sensus-hardware.xls');
?><table border="1">
    <thead>
        <tr>
            <th>No</th>
            <th>Nama Barang</th>
            <th>Ruangan</th>
            <th>Jenis Transaksi</th>
            <th>Qty</th>
            <th>Stok Sebelum</th>
            <th>Stok Sesudah</th>
            <th>Nama Pengguna / Pengambil</th>
            <th>Tujuan</th>
            <th>Hari</th>
            <th>Tanggal</th>
            <th>Jam</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $index => $row): ?>
            <tr>
                <td><?= $index + 1; ?></td>
                <td><?= e($row['nama_barang']); ?></td>
                <td><?= e($row['nama_ruangan'] ?: '-'); ?></td>
                <td><?= ucfirst(e($row['tipe_transaksi'])); ?></td>
                <td><?= format_number($row['qty']); ?></td>
                <td><?= format_number($row['stok_sebelum']); ?></td>
                <td><?= format_number($row['stok_sesudah']); ?></td>
                <td><?= e($row['nama_pengguna']); ?></td>
                <td><?= e($row['tujuan'] ?: '-'); ?></td>
                <td><?= e(day_name_id($row['tanggal'])); ?></td>
                <td><?= e(format_date_id($row['tanggal'])); ?></td>
                <td><?= e(format_time_id($row['jam'])); ?></td>
                <td><?= e($row['keterangan'] ?: '-'); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
