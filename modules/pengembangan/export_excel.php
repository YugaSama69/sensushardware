<?php

require_once dirname(__DIR__, 2) . '/config/app.php';

require_login();
require_module_access();

$filters = normalize_pengembangan_filters($_GET);
$rows = get_pengembangan_rows($pdo, $filters);

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename=laporan-pengembangan-aplikasi.xls');
?><table border="1">
    <thead>
        <tr>
            <th>No</th>
            <th>Bulan Tahun</th>
            <th>Nama Kegiatan</th>
            <th>Bidang/Bagian/Unit</th>
            <th>Capaian</th>
            <th>User Input</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $index => $row): ?>
            <tr>
                <td><?= $index + 1; ?></td>
                <td><?= e(format_month_year_id($row['bulan_tahun'])); ?></td>
                <td><?= e($row['nama_kegiatan']); ?></td>
                <td><?= e($row['bidang_unit']); ?></td>
                <td><?= e(format_percentage($row['capaian'])); ?></td>
                <td><?= e($row['input_user'] ?: '-'); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
