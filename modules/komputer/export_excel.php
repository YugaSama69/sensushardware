<?php

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once __DIR__ . '/module.php';

require_login();

$filters = komputer_inventory_normalize_filters($_GET);
$rows = komputer_inventory_rows($pdo, $filters);
$isServerExport = $filters['device_type'] === 'SERVER';

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename=' . ($isServerExport ? 'data-server.xls' : 'data-komputer-client.xls'));
?><table border="1">
    <thead>
        <?php if ($isServerExport): ?>
            <tr>
                <th>No</th>
                <th>Device Type</th>
                <th>Hostname</th>
                <th>IP Address</th>
                <th>Multiple IP</th>
                <th>MAC Address</th>
                <th>Merk</th>
                <th>Model</th>
                <th>Processor</th>
                <th>Core</th>
                <th>RAM</th>
                <th>OS</th>
                <th>Versi OS</th>
                <th>Arsitektur</th>
                <th>Virtualization</th>
                <th>RAID</th>
                <th>Hypervisor</th>
                <th>Uptime</th>
                <th>Server Role</th>
                <th>Ruangan</th>
                <th>Petugas</th>
                <th>Kondisi</th>
                <th>Tanggal</th>
                <th>Jam</th>
            </tr>
        <?php else: ?>
            <tr>
                <th>No</th>
                <th>Device Type</th>
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
        <?php endif; ?>
    </thead>
    <tbody>
        <?php foreach ($rows as $index => $row): ?>
            <?php if ($isServerExport): ?>
                <tr>
                    <td><?= $index + 1; ?></td>
                    <td><?= e((string) $row['device_type']); ?></td>
                    <td><?= e((string) $row['hostname']); ?></td>
                    <td><?= e(komputer_inventory_primary_ip($row)); ?></td>
                    <td><?= e(implode(', ', komputer_inventory_server_ip_lines($row))); ?></td>
                    <td><?= e((string) ($row['mac_address'] ?? '')); ?></td>
                    <td><?= e((string) ($row['merk'] ?? '')); ?></td>
                    <td><?= e((string) ($row['model'] ?? '')); ?></td>
                    <td><?= e((string) ($row['processor'] ?? '')); ?></td>
                    <td><?= e((string) ($row['core'] ?? 0)); ?></td>
                    <td><?= e((string) ($row['ram'] ?? '')); ?></td>
                    <td><?= e((string) ($row['os_name'] ?? '')); ?></td>
                    <td><?= e((string) ($row['os_version'] ?? '')); ?></td>
                    <td><?= e((string) ($row['architecture'] ?? '')); ?></td>
                    <td><?= e((string) ($row['virtualization'] ?? '')); ?></td>
                    <td><?= e((string) ($row['raid'] ?? '')); ?></td>
                    <td><?= e((string) ($row['hypervisor'] ?? '')); ?></td>
                    <td><?= e((string) ($row['uptime'] ?? '')); ?></td>
                    <td><?= e((string) ($row['server_role'] ?? '')); ?></td>
                    <td><?= e((string) ($row['ruangan'] ?? '')); ?></td>
                    <td><?= e((string) ($row['petugas'] ?? '')); ?></td>
                    <td><?= e((string) ($row['kondisi'] ?? '')); ?></td>
                    <td><?= e(format_date_id((string) $row['tanggal'])); ?></td>
                    <td><?= e(format_time_id((string) $row['jam'])); ?></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td><?= $index + 1; ?></td>
                    <td><?= e((string) $row['device_type']); ?></td>
                    <td><?= e((string) $row['hostname']); ?></td>
                    <td><?= e((string) ($row['username'] ?? '')); ?></td>
                    <td><?= e(implode(', ', komputer_inventory_client_ip_lines($row))); ?></td>
                    <td><?= e((string) ($row['mac_address'] ?? '')); ?></td>
                    <td><?= e((string) ($row['merk'] ?? '')); ?></td>
                    <td><?= e((string) ($row['model'] ?? '')); ?></td>
                    <td><?= e((string) ($row['processor'] ?? '')); ?></td>
                    <td><?= e((string) ($row['core'] ?? 0)); ?></td>
                    <td><?= e((string) ($row['kondisi'] ?? 'Baik')); ?></td>
                    <td><?= e((string) ($row['ram'] ?? '')); ?></td>
                    <td><?= e((string) ($row['ssd'] ?? '')); ?></td>
                    <td><?= e((string) ($row['hdd'] ?? '')); ?></td>
                    <td><?= e((string) ($row['vga'] ?? '')); ?></td>
                    <td><?= e((string) ($row['motherboard'] ?? '')); ?></td>
                    <td><?= e((string) ($row['os_name'] ?? '')); ?></td>
                    <td><?= e((string) ($row['os_version'] ?? '')); ?></td>
                    <td><?= e((string) ($row['architecture'] ?? '')); ?></td>
                    <td><?= e((string) ($row['tahun_inventaris'] ?? '')); ?></td>
                    <td><?= e((string) ($row['ruangan'] ?? '')); ?></td>
                    <td><?= e((string) ($row['petugas'] ?? '')); ?></td>
                    <td><?= e(format_date_id((string) $row['tanggal'])); ?></td>
                    <td><?= e(format_time_id((string) $row['jam'])); ?></td>
                </tr>
            <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
</table>
