<?php

require_once dirname(__DIR__, 2) . '/config/app.php';

require_login();

$filters = normalize_computer_client_filters($_GET);
$rows = get_computer_client_rows($pdo, $filters);

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename=data-komputer-client.xls');
?><table border="1">
    <thead>
        <tr>
            <th>No</th>
            <th>Hostname</th>
            <th>Username Windows</th>
            <th>IP Address</th>
            <th>MAC Address</th>
            <th>Merk</th>
            <th>Model</th>
            <th>Processor</th>
            <th>Core</th>
            <th>Kondisi</th>
            <th>RAM</th>
            <th>SSD</th>
            <th>HDD</th>
            <th>VGA / GPU</th>
            <th>Motherboard</th>
            <th>OS</th>
            <th>Versi OS</th>
            <th>Arsitektur</th>
            <th>Tahun Inventaris</th>
            <th>Ruangan</th>
            <th>Nama User</th>
            <th>Tanggal</th>
            <th>Jam</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $index => $row): ?>
            <tr>
                <td><?= $index + 1; ?></td>
                <td><?= e($row['hostname']); ?></td>
                <td><?= e($row['username']); ?></td>
                <td><?= e($row['ip_address']); ?></td>
                <td><?= e($row['mac_address']); ?></td>
                <td><?= e($row['merk']); ?></td>
                <td><?= e($row['model']); ?></td>
                <td><?= e($row['processor']); ?></td>
                <td><?= e((string) $row['core']); ?></td>
                <td><?= e($row['kondisi'] ?: 'Baik'); ?></td>
                <td><?= e($row['ram']); ?></td>
                <td><?= e($row['ssd']); ?></td>
                <td><?= e($row['hdd']); ?></td>
                <td><?= e($row['vga']); ?></td>
                <td><?= e($row['motherboard']); ?></td>
                <td><?= e($row['os_name']); ?></td>
                <td><?= e($row['os_version']); ?></td>
                <td><?= e($row['architecture']); ?></td>
                <td><?= e($row['tahun_inventaris']); ?></td>
                <td><?= e($row['ruangan']); ?></td>
                <td><?= e($row['petugas']); ?></td>
                <td><?= e(format_date_id($row['tanggal'])); ?></td>
                <td><?= e(format_time_id($row['jam'])); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
