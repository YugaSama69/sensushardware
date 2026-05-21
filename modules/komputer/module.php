<?php

declare(strict_types=1);

function komputer_inventory_device_type_options(): array
{
    return [
        'CLIENT' => 'Komputer Client',
        'SERVER' => 'Server',
    ];
}

function komputer_inventory_device_type_label(string $deviceType): string
{
    $options = komputer_inventory_device_type_options();

    return $options[$deviceType] ?? 'Komputer Client';
}

function komputer_inventory_device_type_badge_class(string $deviceType): string
{
    return $deviceType === 'SERVER' ? 'warning' : 'primary';
}

function komputer_inventory_normalize_filters(array $input): array
{
    $deviceType = strtoupper(trim((string) ($input['device_type'] ?? 'CLIENT')));
    if (!array_key_exists($deviceType, komputer_inventory_device_type_options())) {
        $deviceType = 'CLIENT';
    }

    return [
        'device_type' => $deviceType,
        'merk' => trim((string) ($input['merk'] ?? '')),
        'processor' => trim((string) ($input['processor'] ?? '')),
        'kondisi' => trim((string) ($input['kondisi'] ?? '')),
        'ram' => trim((string) ($input['ram'] ?? '')),
        'ram_group' => trim((string) ($input['ram_group'] ?? '')),
        'storage' => trim((string) ($input['storage'] ?? '')),
        'storage_mode' => trim((string) ($input['storage_mode'] ?? '')),
        'os_name' => trim((string) ($input['os_name'] ?? '')),
        'os_group' => trim((string) ($input['os_group'] ?? '')),
        'tahun_inventaris' => trim((string) ($input['tahun_inventaris'] ?? '')),
        'ruangan' => trim((string) ($input['ruangan'] ?? '')),
        'virtualization' => trim((string) ($input['virtualization'] ?? '')),
        'server_role' => trim((string) ($input['server_role'] ?? '')),
    ];
}

function komputer_inventory_server_fields(array $input): array
{
    return [
        'virtualization' => trim((string) ($input['virtualization'] ?? '')),
        'raid' => trim((string) ($input['raid'] ?? '')),
        'hypervisor' => trim((string) ($input['hypervisor'] ?? '')),
        'uptime' => trim((string) ($input['uptime'] ?? '')),
        'total_thread' => (int) ($input['total_thread'] ?? 0),
        'multiple_nic' => trim((string) ($input['multiple_nic'] ?? '')),
        'multiple_ip' => trim((string) ($input['multiple_ip'] ?? '')),
        'domain_joined' => trim((string) ($input['domain_joined'] ?? '')),
        'server_role' => trim((string) ($input['server_role'] ?? '')),
        'jenis_server' => trim((string) ($input['jenis_server'] ?? '')),
        'fungsi_server' => trim((string) ($input['fungsi_server'] ?? '')),
        'virtual_fisik' => trim((string) ($input['virtual_fisik'] ?? '')),
        'lokasi_rack' => trim((string) ($input['lokasi_rack'] ?? '')),
        'ip_utama' => trim((string) ($input['ip_utama'] ?? '')),
    ];
}

function komputer_inventory_payload(array $input): array
{
    $deviceType = strtoupper(trim((string) ($input['device_type'] ?? 'CLIENT')));
    if (!array_key_exists($deviceType, komputer_inventory_device_type_options())) {
        $deviceType = 'CLIENT';
    }

    return [
        'device_type' => $deviceType,
        'hostname' => trim((string) ($input['hostname'] ?? '')),
        'username' => trim((string) ($input['username'] ?? '')),
        'ip_address' => trim((string) ($input['ip_address'] ?? '')),
        'mac_address' => trim((string) ($input['mac_address'] ?? '')),
        'merk' => trim((string) ($input['merk'] ?? '')),
        'model' => trim((string) ($input['model'] ?? '')),
        'processor' => trim((string) ($input['processor'] ?? '')),
        'core' => (int) ($input['core'] ?? 0),
        'kondisi' => trim((string) ($input['kondisi'] ?? 'Baik')),
        'ram' => trim((string) ($input['ram'] ?? '')),
        'ssd' => trim((string) ($input['ssd'] ?? '')),
        'hdd' => trim((string) ($input['hdd'] ?? '')),
        'vga' => trim((string) ($input['vga'] ?? '')),
        'motherboard' => trim((string) ($input['motherboard'] ?? '')),
        'serial_number' => trim((string) ($input['serial_number'] ?? '')),
        'os_name' => trim((string) ($input['os_name'] ?? '')),
        'os_version' => trim((string) ($input['os_version'] ?? '')),
        'architecture' => trim((string) ($input['architecture'] ?? '')),
        'tahun_inventaris' => trim((string) ($input['tahun_inventaris'] ?? date('Y'))),
        'ruangan' => trim((string) ($input['ruangan'] ?? '')),
        'petugas' => trim((string) ($input['nama_user'] ?? ($input['petugas'] ?? ''))),
    ];
}

function komputer_inventory_validate_payload(array $payload, array &$errors): void
{
    if ($payload['hostname'] === '') {
        $errors[] = 'Hostname wajib diisi.';
    }

    if ($payload['mac_address'] === '') {
        $errors[] = 'MAC address wajib diisi.';
    }

    if ($payload['ruangan'] === '') {
        $errors[] = 'Ruangan wajib diisi.';
    }

    if ($payload['petugas'] === '') {
        $errors[] = 'Nama user wajib diisi.';
    }

    if (!preg_match('/^\d{4}$/', $payload['tahun_inventaris'])) {
        $errors[] = 'Tahun inventaris harus 4 digit.';
    }

    if (!in_array($payload['kondisi'], ['Baik', 'Rusak', 'Perbaikan'], true)) {
        $errors[] = 'Kondisi device tidak valid.';
    }
}

function komputer_inventory_save_server_detail(PDO $pdo, int $computerId, array $serverFields): void
{
    $statement = $pdo->prepare('
        INSERT INTO server_detail (
            komputer_id, virtualization, raid, hypervisor, uptime, total_thread, multiple_nic, multiple_ip,
            domain_joined, server_role, jenis_server, fungsi_server, virtual_fisik, lokasi_rack, ip_utama,
            created_at, updated_at
        ) VALUES (
            :komputer_id, :virtualization, :raid, :hypervisor, :uptime, :total_thread, :multiple_nic, :multiple_ip,
            :domain_joined, :server_role, :jenis_server, :fungsi_server, :virtual_fisik, :lokasi_rack, :ip_utama,
            NOW(), NOW()
        )
        ON DUPLICATE KEY UPDATE
            virtualization = VALUES(virtualization),
            raid = VALUES(raid),
            hypervisor = VALUES(hypervisor),
            uptime = VALUES(uptime),
            total_thread = VALUES(total_thread),
            multiple_nic = VALUES(multiple_nic),
            multiple_ip = VALUES(multiple_ip),
            domain_joined = VALUES(domain_joined),
            server_role = VALUES(server_role),
            jenis_server = VALUES(jenis_server),
            fungsi_server = VALUES(fungsi_server),
            virtual_fisik = VALUES(virtual_fisik),
            lokasi_rack = VALUES(lokasi_rack),
            ip_utama = VALUES(ip_utama),
            updated_at = NOW()
    ');

    $statement->execute([
        'komputer_id' => $computerId,
        'virtualization' => $serverFields['virtualization'],
        'raid' => $serverFields['raid'],
        'hypervisor' => $serverFields['hypervisor'],
        'uptime' => $serverFields['uptime'],
        'total_thread' => $serverFields['total_thread'],
        'multiple_nic' => $serverFields['multiple_nic'],
        'multiple_ip' => $serverFields['multiple_ip'],
        'domain_joined' => $serverFields['domain_joined'],
        'server_role' => $serverFields['server_role'],
        'jenis_server' => $serverFields['jenis_server'],
        'fungsi_server' => $serverFields['fungsi_server'],
        'virtual_fisik' => $serverFields['virtual_fisik'],
        'lokasi_rack' => $serverFields['lokasi_rack'],
        'ip_utama' => $serverFields['ip_utama'],
    ]);
}

function komputer_inventory_table_exists(PDO $pdo, string $tableName): bool
{
    static $cache = [];

    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = :table_name'
    );
    $statement->execute(['table_name' => $tableName]);

    $cache[$tableName] = (int) $statement->fetchColumn() > 0;

    return $cache[$tableName];
}

function komputer_inventory_active_ip_select_sql(PDO $pdo): string
{
    if (!komputer_inventory_table_exists($pdo, 'client_ip_addresses')) {
        return ", '' AS active_ip_list, 0 AS active_ip_count";
    }

    return ', cip.active_ip_list, cip.active_ip_count';
}

function komputer_inventory_active_ip_join_sql(PDO $pdo): string
{
    if (!komputer_inventory_table_exists($pdo, 'client_ip_addresses')) {
        return '';
    }

    return '
        LEFT JOIN (
            SELECT client_id,
                   GROUP_CONCAT(ip_address ORDER BY ip_address SEPARATOR "\n") AS active_ip_list,
                   COUNT(*) AS active_ip_count
            FROM client_ip_addresses
            WHERE status = "active"
            GROUP BY client_id
        ) cip ON cip.client_id = kc.id
    ';
}

function komputer_inventory_update_device(PDO $pdo, int $id, array $input, array &$errors): bool
{
    $payload = komputer_inventory_payload($input);
    $serverFields = komputer_inventory_server_fields($input);

    komputer_inventory_validate_payload($payload, $errors);

    if ($payload['device_type'] === 'SERVER') {
        $ipAddresses = komputer_inventory_collect_ip_addresses($payload['ip_address'], $serverFields['multiple_ip'], $errors);
        if ($ipAddresses && $payload['ip_address'] === '') {
            $payload['ip_address'] = $ipAddresses[0];
        }
        $serverFields['multiple_ip'] = implode(PHP_EOL, $ipAddresses);
    } else {
        $ipAddresses = komputer_inventory_collect_ip_addresses(
            $payload['ip_address'],
            (string) ($input['client_multiple_ip'] ?? ''),
            $errors
        );
        if ($ipAddresses && $payload['ip_address'] === '') {
            $payload['ip_address'] = $ipAddresses[0];
        }
    }

    if ($errors) {
        return false;
    }

    $pdo->beginTransaction();

    try {
        $statement = $pdo->prepare('
            UPDATE komputer_client
            SET device_type = :device_type,
                hostname = :hostname,
                username = :username,
                ip_address = :ip_address,
                mac_address = :mac_address,
                merk = :merk,
                model = :model,
                processor = :processor,
                core = :core,
                kondisi = :kondisi,
                ram = :ram,
                ssd = :ssd,
                hdd = :hdd,
                vga = :vga,
                motherboard = :motherboard,
                serial_number = :serial_number,
                os_name = :os_name,
                os_version = :os_version,
                architecture = :architecture,
                tahun_inventaris = :tahun_inventaris,
                ruangan = :ruangan,
                petugas = :petugas
            WHERE id = :id
        ');
        $payload['id'] = $id;
        $statement->execute($payload);

        komputer_inventory_save_server_detail($pdo, $id, $serverFields);
        komputer_inventory_sync_client_ip_addresses($pdo, $id, $ipAddresses ?? []);

        $pdo->commit();
        return true;
    } catch (Throwable $throwable) {
        $pdo->rollBack();
        $errors[] = 'Data device belum berhasil diperbarui.';
        return false;
    }
}

function komputer_inventory_create_server(PDO $pdo, array $input, array &$errors): ?int
{
    $payload = komputer_inventory_payload($input);
    $payload['device_type'] = 'SERVER';
    $serverFields = komputer_inventory_server_fields($input);

    komputer_inventory_validate_payload($payload, $errors);

    if ($errors) {
        return null;
    }

    $pdo->beginTransaction();

    try {
        $statement = $pdo->prepare('
            INSERT INTO komputer_client (
                device_type, hostname, username, ip_address, mac_address, merk, model, processor, core,
                kondisi, ram, ssd, hdd, vga, motherboard, os_name, os_version, architecture,
                serial_number,
                tahun_inventaris, ruangan, petugas, tanggal, jam, created_at
            ) VALUES (
                :device_type, :hostname, :username, :ip_address, :mac_address, :merk, :model, :processor, :core,
                :kondisi, :ram, :ssd, :hdd, :vga, :motherboard, :os_name, :os_version, :architecture,
                :serial_number,
                :tahun_inventaris, :ruangan, :petugas, :tanggal, :jam, NOW()
            )
        ');

        $statement->execute([
            'device_type' => $payload['device_type'],
            'hostname' => $payload['hostname'],
            'username' => $payload['username'],
            'ip_address' => $payload['ip_address'],
            'mac_address' => $payload['mac_address'],
            'merk' => $payload['merk'],
            'model' => $payload['model'],
            'processor' => $payload['processor'],
            'core' => $payload['core'],
            'kondisi' => $payload['kondisi'],
            'ram' => $payload['ram'],
            'ssd' => $payload['ssd'],
            'hdd' => $payload['hdd'],
            'vga' => $payload['vga'],
            'motherboard' => $payload['motherboard'],
            'serial_number' => $payload['serial_number'],
            'os_name' => $payload['os_name'],
            'os_version' => $payload['os_version'],
            'architecture' => $payload['architecture'],
            'tahun_inventaris' => $payload['tahun_inventaris'],
            'ruangan' => $payload['ruangan'],
            'petugas' => $payload['petugas'],
            'tanggal' => date('Y-m-d'),
            'jam' => date('H:i:s'),
        ]);

        $id = (int) $pdo->lastInsertId();
        komputer_inventory_save_server_detail($pdo, $id, $serverFields);

        $pdo->commit();
        return $id;
    } catch (Throwable $throwable) {
        $pdo->rollBack();
        $errors[] = 'Data server belum berhasil ditambahkan.';
        return null;
    }
}

function komputer_inventory_find_device(PDO $pdo, int $id): ?array
{
    $activeIpSelect = komputer_inventory_active_ip_select_sql($pdo);
    $activeIpJoin = komputer_inventory_active_ip_join_sql($pdo);
    $row = fetch_one(
        $pdo,
        'SELECT kc.*, sd.virtualization, sd.raid, sd.hypervisor, sd.uptime, sd.total_thread, sd.multiple_nic,
                sd.multiple_ip, sd.domain_joined, sd.server_role, sd.jenis_server, sd.fungsi_server,
                sd.virtual_fisik, sd.lokasi_rack, sd.ip_utama' . $activeIpSelect . '
         FROM komputer_client kc
         LEFT JOIN server_detail sd ON sd.komputer_id = kc.id
         ' . $activeIpJoin . '
         WHERE kc.id = :id
         LIMIT 1',
        ['id' => $id]
    );

    return $row ?: null;
}

function komputer_inventory_rows(PDO $pdo, array $filters): array
{
    $activeIpSelect = komputer_inventory_active_ip_select_sql($pdo);
    $activeIpJoin = komputer_inventory_active_ip_join_sql($pdo);
    $sql = '
        SELECT kc.*, sd.virtualization, sd.raid, sd.hypervisor, sd.uptime, sd.total_thread, sd.multiple_nic,
               sd.multiple_ip, sd.domain_joined, sd.server_role, sd.jenis_server, sd.fungsi_server,
               sd.virtual_fisik, sd.lokasi_rack, sd.ip_utama' . $activeIpSelect . '
        FROM komputer_client kc
        LEFT JOIN server_detail sd ON sd.komputer_id = kc.id
        ' . $activeIpJoin . '
        WHERE kc.device_type = :device_type
    ';
    $params = [
        'device_type' => $filters['device_type'],
    ];

    if ($filters['merk'] !== '') {
        $sql .= ' AND kc.merk = :merk';
        $params['merk'] = $filters['merk'];
    }

    if ($filters['processor'] !== '') {
        $sql .= ' AND kc.processor = :processor';
        $params['processor'] = $filters['processor'];
    }

    if ($filters['kondisi'] !== '') {
        $sql .= ' AND kc.kondisi = :kondisi';
        $params['kondisi'] = $filters['kondisi'];
    }

    if ($filters['ram'] !== '') {
        $sql .= ' AND kc.ram = :ram';
        $params['ram'] = $filters['ram'];
    }

    if ($filters['storage'] !== '') {
        $sql .= ' AND (kc.ssd = :storage_ssd OR kc.hdd = :storage_hdd)';
        $params['storage_ssd'] = $filters['storage'];
        $params['storage_hdd'] = $filters['storage'];
    }

    if ($filters['os_name'] !== '') {
        $sql .= ' AND kc.os_name = :os_name';
        $params['os_name'] = $filters['os_name'];
    }

    if ($filters['tahun_inventaris'] !== '') {
        $sql .= ' AND kc.tahun_inventaris = :tahun_inventaris';
        $params['tahun_inventaris'] = $filters['tahun_inventaris'];
    }

    if ($filters['ruangan'] !== '') {
        $sql .= ' AND kc.ruangan = :ruangan';
        $params['ruangan'] = $filters['ruangan'];
    }

    if ($filters['virtualization'] !== '' && $filters['device_type'] === 'SERVER') {
        $sql .= ' AND sd.virtualization = :virtualization';
        $params['virtualization'] = $filters['virtualization'];
    }

    if ($filters['server_role'] !== '' && $filters['device_type'] === 'SERVER') {
        $sql .= ' AND sd.server_role = :server_role';
        $params['server_role'] = $filters['server_role'];
    }

    $sql .= ' ORDER BY kc.tanggal DESC, kc.jam DESC, kc.id DESC';

    $rows = fetch_all($pdo, $sql, $params);

    if ($filters['ram_group'] !== '') {
        $rows = array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            return normalize_ram_group_label($row['ram'] ?? null) === $filters['ram_group'];
        }));
    }

    if ($filters['os_group'] !== '') {
        $rows = array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            return normalize_os_group_label($row['os_name'] ?? null) === $filters['os_group'];
        }));
    }

    if ($filters['storage_mode'] === 'hdd_only') {
        $rows = array_values(array_filter($rows, static function (array $row): bool {
            $ssd = trim((string) ($row['ssd'] ?? ''));
            $hdd = trim((string) ($row['hdd'] ?? ''));

            return ($ssd === '' || $ssd === '-') && $hdd !== '' && $hdd !== '-';
        }));
    }

    return $rows;
}

function komputer_inventory_filter_options(PDO $pdo): array
{
    $fetchValues = static function (array $rows): array {
        return array_map(static fn (array $row): string => (string) ($row['value'] ?? ''), $rows);
    };

    return [
        'device_type' => komputer_inventory_device_type_options(),
        'merk' => $fetchValues(fetch_all($pdo, 'SELECT DISTINCT merk AS value FROM komputer_client WHERE merk <> "" ORDER BY merk ASC')),
        'processor' => $fetchValues(fetch_all($pdo, 'SELECT DISTINCT processor AS value FROM komputer_client WHERE processor <> "" ORDER BY processor ASC')),
        'kondisi' => ['Baik', 'Rusak', 'Perbaikan'],
        'ram' => $fetchValues(fetch_all($pdo, 'SELECT DISTINCT ram AS value FROM komputer_client WHERE ram <> "" ORDER BY ram ASC')),
        'storage' => $fetchValues(fetch_all(
            $pdo,
            'SELECT value
             FROM (
                SELECT DISTINCT ssd AS value FROM komputer_client WHERE ssd <> ""
                UNION
                SELECT DISTINCT hdd AS value FROM komputer_client WHERE hdd <> ""
             ) storage_values
             ORDER BY value ASC'
        )),
        'os_name' => $fetchValues(fetch_all($pdo, 'SELECT DISTINCT os_name AS value FROM komputer_client WHERE os_name <> "" ORDER BY os_name ASC')),
        'tahun_inventaris' => $fetchValues(fetch_all($pdo, 'SELECT DISTINCT tahun_inventaris AS value FROM komputer_client WHERE tahun_inventaris <> "" ORDER BY tahun_inventaris DESC')),
        'ruangan' => get_room_name_options($pdo),
        'virtualization' => $fetchValues(fetch_all($pdo, 'SELECT DISTINCT virtualization AS value FROM server_detail WHERE virtualization <> "" ORDER BY virtualization ASC')),
        'server_role' => $fetchValues(fetch_all($pdo, 'SELECT DISTINCT server_role AS value FROM server_detail WHERE server_role <> "" ORDER BY server_role ASC')),
    ];
}

function komputer_inventory_tab_counts(PDO $pdo, array $filters): array
{
    $counts = [];

    foreach (array_keys(komputer_inventory_device_type_options()) as $deviceType) {
        $tabFilters = $filters;
        $tabFilters['device_type'] = $deviceType;
        $counts[$deviceType] = count(komputer_inventory_rows($pdo, $tabFilters));
    }

    return $counts;
}

function komputer_inventory_server_role_display(array $row): string
{
    return trim((string) ($row['server_role'] ?? '')) ?: '-';
}

function komputer_inventory_multiple_ip_lines(?string $value): array
{
    $value = trim((string) $value);
    if ($value === '') {
        return [];
    }

    $parts = preg_split('/\r\n|\r|\n|,/', $value) ?: [];
    $lines = array_values(array_filter(array_map(static fn (string $item): string => trim($item), $parts)));

    return $lines;
}

function komputer_inventory_parse_ip_input(?string $value, array &$invalidEntries = []): array
{
    $lines = komputer_inventory_multiple_ip_lines($value);
    $ipAddresses = [];
    $invalidEntries = [];

    foreach ($lines as $line) {
        if (filter_var($line, FILTER_VALIDATE_IP) === false) {
            $invalidEntries[] = $line;
            continue;
        }

        if (!in_array($line, $ipAddresses, true)) {
            $ipAddresses[] = $line;
        }
    }

    return $ipAddresses;
}

function komputer_inventory_collect_ip_addresses(string $primaryIp, ?string $secondaryInput, array &$errors): array
{
    $invalidEntries = [];
    $ipAddresses = komputer_inventory_parse_ip_input($secondaryInput, $invalidEntries);

    if ($invalidEntries) {
        $errors[] = 'Daftar IP berisi format tidak valid: ' . implode(', ', $invalidEntries) . '.';
    }

    $primaryIp = trim($primaryIp);
    if ($primaryIp !== '') {
        if (filter_var($primaryIp, FILTER_VALIDATE_IP) === false) {
            $errors[] = 'IP address utama tidak valid.';
        } else {
            $ipAddresses = array_values(array_filter(
                $ipAddresses,
                static fn (string $ipAddress): bool => $ipAddress !== $primaryIp
            ));
            array_unshift($ipAddresses, $primaryIp);
        }
    }

    return $ipAddresses;
}

function komputer_inventory_sync_client_ip_addresses(PDO $pdo, int $clientId, array $ipAddresses): void
{
    if ($clientId <= 0 || !komputer_inventory_table_exists($pdo, 'client_ip_addresses')) {
        return;
    }

    $activeIps = [];
    $upsertStatement = $pdo->prepare('
        INSERT INTO client_ip_addresses (
            client_id, ip_address, adapter_name, status, created_at, updated_at
        ) VALUES (
            :client_id, :ip_address, "", "active", NOW(), NOW()
        )
        ON DUPLICATE KEY UPDATE
            status = "active",
            updated_at = NOW()
    ');

    foreach ($ipAddresses as $ipAddress) {
        $ipAddress = trim($ipAddress);
        if ($ipAddress === '' || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            continue;
        }

        if (in_array($ipAddress, $activeIps, true)) {
            continue;
        }

        $activeIps[] = $ipAddress;
        $upsertStatement->execute([
            'client_id' => $clientId,
            'ip_address' => $ipAddress,
        ]);
    }

    if ($activeIps) {
        $placeholders = [];
        $params = ['client_id' => $clientId];

        foreach ($activeIps as $index => $ipAddress) {
            $placeholder = ':ip_' . $index;
            $placeholders[] = $placeholder;
            $params['ip_' . $index] = $ipAddress;
        }

        $sql = '
            UPDATE client_ip_addresses
            SET status = "inactive",
                updated_at = NOW()
            WHERE client_id = :client_id
              AND ip_address NOT IN (' . implode(', ', $placeholders) . ')
        ';
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return;
    }

    $statement = $pdo->prepare('
        UPDATE client_ip_addresses
        SET status = "inactive",
            updated_at = NOW()
        WHERE client_id = :client_id
    ');
    $statement->execute(['client_id' => $clientId]);
}

function komputer_inventory_client_ip_lines(array $row): array
{
    $lines = komputer_inventory_multiple_ip_lines($row['active_ip_list'] ?? '');
    if ($lines) {
        return $lines;
    }

    $primaryIp = trim((string) ($row['ip_address'] ?? ''));
    return $primaryIp !== '' ? [$primaryIp] : [];
}

function komputer_inventory_primary_ip(array $row): string
{
    $lines = komputer_inventory_client_ip_lines($row);
    if ($lines) {
        return (string) $lines[0];
    }

    return trim((string) ($row['ip_address'] ?? ''));
}

function komputer_inventory_secondary_ip_lines(array $row): array
{
    $lines = komputer_inventory_client_ip_lines($row);
    if (count($lines) <= 1) {
        return [];
    }

    return array_values(array_slice($lines, 1));
}

function komputer_inventory_server_ip_lines(array $row): array
{
    $lines = komputer_inventory_multiple_ip_lines($row['active_ip_list'] ?? '');
    if ($lines) {
        return $lines;
    }

    $lines = komputer_inventory_multiple_ip_lines($row['multiple_ip'] ?? '');
    if ($lines) {
        return $lines;
    }

    $primaryIp = trim((string) ($row['ip_address'] ?? ''));
    return $primaryIp !== '' ? [$primaryIp] : [];
}
