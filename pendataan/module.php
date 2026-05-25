<?php

declare(strict_types=1);

function device_inventory_device_type_options(): array
{
    return [
        'CLIENT' => 'Komputer Client',
        'SERVER' => 'Server',
    ];
}

function device_inventory_condition_options(): array
{
    return ['Baik', 'Rusak', 'Perbaikan'];
}

function device_inventory_virtual_mode_options(): array
{
    return [
        'VIRTUAL' => 'Virtual',
        'FISIK' => 'Fisik',
    ];
}

function device_inventory_is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }

    return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function device_inventory_current_host(): string
{
    return strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
}

function device_inventory_is_local_override_host(): bool
{
    $host = device_inventory_current_host();
    $host = explode(':', $host)[0];

    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return true;
    }

    if (preg_match('/^10\./', $host) === 1) {
        return true;
    }

    if (preg_match('/^192\.168\./', $host) === 1) {
        return true;
    }

    if (preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $host) === 1) {
        return true;
    }

    return false;
}

function device_inventory_secure_transport_ready(): bool
{
    return device_inventory_is_https_request() || device_inventory_is_local_override_host();
}

function device_inventory_require_secure_transport_json(): void
{
    if (device_inventory_secure_transport_ready()) {
        return;
    }

    http_response_code(426);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Fitur launcher hanya dapat digunakan melalui HTTPS.',
    ]);
    exit;
}

function device_inventory_require_secure_transport_download(): void
{
    if (device_inventory_secure_transport_ready()) {
        return;
    }

    http_response_code(426);
    exit('Launcher hanya dapat diunduh melalui HTTPS.');
}

function device_inventory_absolute_url(string $path, bool $forceHttps = false): string
{
    $scheme = $forceHttps ? 'https' : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . url($path);
}

function device_inventory_log_directory(): string
{
    $path = BASE_PATH . '/application/logs/device_inventory';
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }

    return $path;
}

function device_inventory_log_event(string $event, array $context = []): void
{
    $line = json_encode([
        'timestamp' => date('c'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '-',
        'context' => $context,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($line === false) {
        $line = date('c') . ' ' . $event;
    }

    $file = device_inventory_log_directory() . '/' . date('Y-m-d') . '.log';
    file_put_contents($file, $line . PHP_EOL, FILE_APPEND);
}

function device_inventory_table_exists(PDO $pdo, string $tableName): bool
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

function device_inventory_column_exists(PDO $pdo, string $tableName, string $columnName): bool
{
    static $cache = [];
    $cacheKey = $tableName . '.' . $columnName;

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table_name
           AND column_name = :column_name'
    );
    $statement->execute([
        'table_name' => $tableName,
        'column_name' => $columnName,
    ]);

    $cache[$cacheKey] = (int) $statement->fetchColumn() > 0;

    return $cache[$cacheKey];
}

function device_inventory_index_exists(PDO $pdo, string $tableName, string $indexName): bool
{
    static $cache = [];
    $cacheKey = $tableName . '.' . $indexName;

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = :table_name
           AND index_name = :index_name'
    );
    $statement->execute([
        'table_name' => $tableName,
        'index_name' => $indexName,
    ]);

    $cache[$cacheKey] = (int) $statement->fetchColumn() > 0;

    return $cache[$cacheKey];
}

function device_inventory_ensure_runtime_schema(PDO $pdo): void
{
    static $alreadyEnsured = false;

    if ($alreadyEnsured) {
        return;
    }

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS inventory_launcher_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            token_hash CHAR(64) NOT NULL,
            payload_encrypted LONGTEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            request_ip VARCHAR(45) NULL,
            request_user_agent VARCHAR(255) NULL,
            download_ip VARCHAR(45) NULL,
            UNIQUE KEY uq_inventory_launcher_token_hash (token_hash),
            INDEX idx_inventory_launcher_expiry (expires_at),
            INDEX idx_inventory_launcher_used_at (used_at)
        )
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS inventory_upload_tokens (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            token_hash CHAR(64) NOT NULL,
            payload_encrypted LONGTEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            request_ip VARCHAR(45) NULL,
            request_user_agent VARCHAR(255) NULL,
            upload_ip VARCHAR(45) NULL,
            payload_hash CHAR(64) NULL,
            UNIQUE KEY uq_inventory_upload_token_hash (token_hash),
            INDEX idx_inventory_upload_tokens_expires_at (expires_at),
            INDEX idx_inventory_upload_tokens_used_at (used_at)
        )
    ');

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS client_ip_addresses (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            adapter_name VARCHAR(255) NULL,
            status ENUM("active", "inactive") NOT NULL DEFAULT "active",
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_client_ip_address (client_id, ip_address),
            INDEX idx_client_ip_client (client_id),
            INDEX idx_client_ip_status (status),
            INDEX idx_client_ip_address (ip_address)
        )
    ');

    if (device_inventory_table_exists($pdo, 'komputer_client')) {
        if (!device_inventory_column_exists($pdo, 'komputer_client', 'serial_number')) {
            $pdo->exec('ALTER TABLE komputer_client ADD COLUMN serial_number VARCHAR(150) NULL AFTER motherboard');
        }

        if (!device_inventory_column_exists($pdo, 'komputer_client', 'updated_at')) {
            $pdo->exec('ALTER TABLE komputer_client ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
        }

        if (!device_inventory_index_exists($pdo, 'komputer_client', 'idx_komputer_mac_address')) {
            $pdo->exec('ALTER TABLE komputer_client ADD INDEX idx_komputer_mac_address (mac_address)');
        }

        if (!device_inventory_index_exists($pdo, 'komputer_client', 'uq_komputer_mac_address')) {
            $duplicateMacCount = (int) $pdo->query(
                'SELECT COUNT(*)
                 FROM (
                    SELECT REPLACE(REPLACE(UPPER(mac_address), ":", ""), "-", "") AS mac_key
                    FROM komputer_client
                    WHERE COALESCE(mac_address, "") <> ""
                    GROUP BY mac_key
                    HAVING COUNT(*) > 1
                 ) duplicate_mac'
            )->fetchColumn();

            if ($duplicateMacCount === 0) {
                $pdo->exec('ALTER TABLE komputer_client ADD UNIQUE KEY uq_komputer_mac_address (mac_address)');
            }
        }
    }

    $pdo->exec('
        CREATE TABLE IF NOT EXISTS server_detail (
            id INT AUTO_INCREMENT PRIMARY KEY,
            komputer_id INT NOT NULL,
            virtualization VARCHAR(120) NULL,
            raid VARCHAR(120) NULL,
            hypervisor VARCHAR(120) NULL,
            uptime VARCHAR(120) NULL,
            total_thread INT NULL,
            multiple_nic TEXT NULL,
            multiple_ip TEXT NULL,
            domain_joined VARCHAR(10) NULL,
            server_role VARCHAR(150) NULL,
            jenis_server VARCHAR(120) NULL,
            fungsi_server VARCHAR(150) NULL,
            virtual_fisik VARCHAR(20) NULL,
            lokasi_rack VARCHAR(120) NULL,
            ip_utama VARCHAR(64) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_server_detail_komputer (komputer_id),
            INDEX idx_server_detail_role (server_role),
            INDEX idx_server_detail_virtualization (virtualization)
        )
    ');

    $serverDetailColumns = [
        'total_thread' => 'ALTER TABLE server_detail ADD COLUMN total_thread INT NULL AFTER uptime',
        'multiple_nic' => 'ALTER TABLE server_detail ADD COLUMN multiple_nic TEXT NULL AFTER total_thread',
        'domain_joined' => 'ALTER TABLE server_detail ADD COLUMN domain_joined VARCHAR(10) NULL AFTER multiple_ip',
        'jenis_server' => 'ALTER TABLE server_detail ADD COLUMN jenis_server VARCHAR(120) NULL AFTER server_role',
        'fungsi_server' => 'ALTER TABLE server_detail ADD COLUMN fungsi_server VARCHAR(150) NULL AFTER jenis_server',
        'virtual_fisik' => 'ALTER TABLE server_detail ADD COLUMN virtual_fisik VARCHAR(20) NULL AFTER fungsi_server',
        'lokasi_rack' => 'ALTER TABLE server_detail ADD COLUMN lokasi_rack VARCHAR(120) NULL AFTER virtual_fisik',
        'ip_utama' => 'ALTER TABLE server_detail ADD COLUMN ip_utama VARCHAR(64) NULL AFTER lokasi_rack',
    ];

    foreach ($serverDetailColumns as $columnName => $sql) {
        if (!device_inventory_column_exists($pdo, 'server_detail', $columnName)) {
            $pdo->exec($sql);
        }
    }

    $alreadyEnsured = true;
}

function device_inventory_sanitize_text(string $value, int $maxLength = 150): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if ($value === '') {
        return '';
    }

    return function_exists('mb_substr')
        ? mb_substr($value, 0, $maxLength)
        : substr($value, 0, $maxLength);
}

function device_inventory_registration_payload(array $input): array
{
    $deviceType = strtoupper(trim((string) ($input['device_type'] ?? 'CLIENT')));
    if (!array_key_exists($deviceType, device_inventory_device_type_options())) {
        $deviceType = 'CLIENT';
    }

    return [
        'device_type' => $deviceType,
        'ruangan' => device_inventory_sanitize_text((string) ($input['ruangan'] ?? ''), 120),
        'tahun_inventaris' => trim((string) ($input['tahun_inventaris'] ?? date('Y'))),
        'nama_user' => device_inventory_sanitize_text((string) ($input['nama_user'] ?? ''), 120),
        'kondisi' => device_inventory_sanitize_text((string) ($input['kondisi'] ?? 'Baik'), 30),
        'jenis_server' => device_inventory_sanitize_text((string) ($input['jenis_server'] ?? ''), 120),
        'fungsi_server' => device_inventory_sanitize_text((string) ($input['fungsi_server'] ?? ''), 150),
        'virtual_fisik' => strtoupper(device_inventory_sanitize_text((string) ($input['virtual_fisik'] ?? ''), 20)),
        'operating_system' => device_inventory_sanitize_text((string) ($input['operating_system'] ?? ''), 150),
        'lokasi_rack' => device_inventory_sanitize_text((string) ($input['lokasi_rack'] ?? ''), 120),
        'ip_utama' => device_inventory_sanitize_text((string) ($input['ip_utama'] ?? ''), 64),
        'created_at' => date('Y-m-d H:i:s'),
    ];
}

function device_inventory_validate_registration_payload(array $payload, array &$errors): void
{
    if (!array_key_exists($payload['device_type'], device_inventory_device_type_options())) {
        $errors[] = 'Tipe device tidak valid.';
    }

    if ($payload['ruangan'] === '') {
        $errors[] = 'Ruangan wajib diisi.';
    }

    if ($payload['nama_user'] === '') {
        $errors[] = 'Nama user wajib diisi.';
    }

    if (!preg_match('/^\d{4}$/', $payload['tahun_inventaris'])) {
        $errors[] = 'Tahun inventaris harus 4 digit.';
    }

    if (!in_array($payload['kondisi'], device_inventory_condition_options(), true)) {
        $errors[] = 'Kondisi device tidak valid.';
    }

    if ($payload['device_type'] === 'SERVER') {
        if ($payload['jenis_server'] === '') {
            $errors[] = 'Jenis server wajib diisi.';
        }

        if ($payload['fungsi_server'] === '') {
            $errors[] = 'Fungsi server wajib diisi.';
        }

        if (!array_key_exists($payload['virtual_fisik'], device_inventory_virtual_mode_options())) {
            $errors[] = 'Virtual/Fisik wajib dipilih.';
        }

        if ($payload['operating_system'] === '') {
            $errors[] = 'Operating system server wajib diisi.';
        }

        if ($payload['lokasi_rack'] === '') {
            $errors[] = 'Lokasi rack wajib diisi.';
        }

        if ($payload['ip_utama'] === '' || filter_var($payload['ip_utama'], FILTER_VALIDATE_IP) === false) {
            $errors[] = 'IP utama server wajib berupa IP yang valid.';
        }
    }
}

function device_inventory_key_path(): string
{
    $directory = BASE_PATH . '/storage/keys';
    if (!is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    return $directory . '/device_inventory.key';
}

function device_inventory_secret(): string
{
    $path = device_inventory_key_path();

    if (!is_file($path)) {
        file_put_contents($path, bin2hex(random_bytes(32)));
    }

    return trim((string) file_get_contents($path));
}

function device_inventory_encrypt_payload(array $payload): string
{
    $key = hash('sha256', device_inventory_secret(), true);
    $iv = random_bytes((int) openssl_cipher_iv_length('AES-256-CBC'));
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $ciphertext = openssl_encrypt($json ?: '{}', 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

    if ($ciphertext === false) {
        throw new RuntimeException('Gagal mengenkripsi payload launcher.');
    }

    $mac = hash_hmac('sha256', $iv . $ciphertext, $key);

    return base64_encode((string) json_encode([
        'iv' => base64_encode($iv),
        'value' => base64_encode($ciphertext),
        'mac' => $mac,
    ], JSON_UNESCAPED_SLASHES));
}

function device_inventory_decrypt_payload(string $encoded): array
{
    $key = hash('sha256', device_inventory_secret(), true);
    $decoded = base64_decode($encoded, true);
    if ($decoded === false) {
        throw new RuntimeException('Payload launcher tidak valid.');
    }

    $data = json_decode($decoded, true);
    if (!is_array($data)) {
        throw new RuntimeException('Payload launcher tidak dapat dibaca.');
    }

    $iv = base64_decode((string) ($data['iv'] ?? ''), true);
    $ciphertext = base64_decode((string) ($data['value'] ?? ''), true);
    $mac = (string) ($data['mac'] ?? '');

    if ($iv === false || $ciphertext === false || $mac === '') {
        throw new RuntimeException('Payload launcher tidak lengkap.');
    }

    $expectedMac = hash_hmac('sha256', $iv . $ciphertext, $key);
    if (!hash_equals($expectedMac, $mac)) {
        throw new RuntimeException('MAC payload launcher tidak cocok.');
    }

    $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($plaintext === false) {
        throw new RuntimeException('Payload launcher gagal dibuka.');
    }

    $payload = json_decode($plaintext, true);
    if (!is_array($payload)) {
        throw new RuntimeException('Payload launcher rusak.');
    }

    return $payload;
}

function device_inventory_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function device_inventory_register_token(PDO $pdo, array $payload): string
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = device_inventory_token_hash($token);
    $encryptedPayload = device_inventory_encrypt_payload($payload);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $statement = $pdo->prepare('
        INSERT INTO inventory_launcher_tokens (
            token_hash, payload_encrypted, expires_at, created_at, request_ip, request_user_agent
        ) VALUES (
            :token_hash, :payload_encrypted, :expires_at, NOW(), :request_ip, :request_user_agent
        )
    ');
    $statement->execute([
        'token_hash' => $tokenHash,
        'payload_encrypted' => $encryptedPayload,
        'expires_at' => $expiresAt,
        'request_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'request_user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    device_inventory_log_event('generate_launcher', [
        'device_type' => $payload['device_type'] ?? 'CLIENT',
        'ruangan' => $payload['ruangan'] ?? '',
        'nama_user' => $payload['nama_user'] ?? '',
        'expires_at' => $expiresAt,
    ]);

    return $token;
}

function device_inventory_extract_download_token(): string
{
    $queryToken = trim((string) ($_GET['token'] ?? ''));
    if ($queryToken !== '') {
        return $queryToken;
    }

    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $requestPath = rtrim(str_replace('\\', '/', $requestPath), '/');

    if ($basePath !== '' && str_starts_with($requestPath, $basePath . '/')) {
        return trim(substr($requestPath, strlen($basePath) + 1));
    }

    return '';
}

function device_inventory_consume_token(PDO $pdo, string $token): ?array
{
    if ($token === '') {
        device_inventory_log_event('invalid_token', ['reason' => 'empty']);
        return null;
    }

    $tokenHash = device_inventory_token_hash($token);
    $pdo->beginTransaction();

    try {
        $statement = $pdo->prepare('SELECT * FROM inventory_launcher_tokens WHERE token_hash = :token_hash LIMIT 1 FOR UPDATE');
        $statement->execute(['token_hash' => $tokenHash]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $pdo->rollBack();
            device_inventory_log_event('invalid_token', ['reason' => 'not_found']);
            return null;
        }

        if (!empty($row['used_at'])) {
            $pdo->rollBack();
            device_inventory_log_event('invalid_token', ['reason' => 'used', 'token_id' => $row['id'] ?? null]);
            return null;
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            $pdo->rollBack();
            device_inventory_log_event('expired_token', ['token_id' => $row['id'] ?? null]);
            return null;
        }

        $payload = device_inventory_decrypt_payload((string) $row['payload_encrypted']);

        $update = $pdo->prepare('
            UPDATE inventory_launcher_tokens
            SET used_at = NOW(), download_ip = :download_ip
            WHERE id = :id
        ');
        $update->execute([
            'id' => $row['id'],
            'download_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        $pdo->commit();

        device_inventory_log_event('download_launcher', [
            'token_id' => $row['id'] ?? null,
            'device_type' => $payload['device_type'] ?? 'CLIENT',
            'ruangan' => $payload['ruangan'] ?? '',
        ]);

        return $payload;
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        device_inventory_log_event('invalid_token', ['reason' => 'exception', 'message' => $throwable->getMessage()]);
        return null;
    }
}

function device_inventory_download_url(string $token): string
{
    return device_inventory_absolute_url('inventory/download_launcher/' . rawurlencode($token), true);
}

function device_inventory_upload_token_expiry_minutes(): int
{
    return 10;
}

function device_inventory_upload_request_max_age_seconds(): int
{
    return 120;
}

function device_inventory_register_upload_token(PDO $pdo, array $payload): string
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = device_inventory_token_hash($token);
    $encryptedPayload = device_inventory_encrypt_payload($payload);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . device_inventory_upload_token_expiry_minutes() . ' minutes'));

    $statement = $pdo->prepare('
        INSERT INTO inventory_upload_tokens (
            token_hash, payload_encrypted, expires_at, created_at, request_ip, request_user_agent
        ) VALUES (
            :token_hash, :payload_encrypted, :expires_at, NOW(), :request_ip, :request_user_agent
        )
    ');
    $statement->execute([
        'token_hash' => $tokenHash,
        'payload_encrypted' => $encryptedPayload,
        'expires_at' => $expiresAt,
        'request_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'request_user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    return $token;
}

function device_inventory_upload_hash(string $token, string $rawBody): string
{
    return hash_hmac('sha256', $rawBody, $token);
}

function device_inventory_lock_upload_token(PDO $pdo, string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $statement = $pdo->prepare('SELECT * FROM inventory_upload_tokens WHERE token_hash = :token_hash LIMIT 1 FOR UPDATE');
    $statement->execute([
        'token_hash' => device_inventory_token_hash($token),
    ]);

    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function device_inventory_mark_upload_token_used(PDO $pdo, int $id, string $payloadHash): void
{
    $statement = $pdo->prepare('
        UPDATE inventory_upload_tokens
        SET used_at = NOW(),
            upload_ip = :upload_ip,
            payload_hash = :payload_hash
        WHERE id = :id
    ');
    $statement->execute([
        'id' => $id,
        'upload_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'payload_hash' => $payloadHash,
    ]);
}

function device_inventory_upload_fields(): array
{
    return [
        'device_type',
        'hostname',
        'username',
        'ip_address',
        'mac_address',
        'merk',
        'model',
        'processor',
        'core',
        'total_thread',
        'ram',
        'ssd',
        'hdd',
        'vga',
        'motherboard',
        'serial_number',
        'os_name',
        'os_version',
        'architecture',
        'tahun_inventaris',
        'ruangan',
        'petugas',
        'kondisi',
        'virtualization',
        'raid',
        'hypervisor',
        'uptime',
        'multiple_nic',
        'multiple_ip',
        'domain_joined',
        'server_role',
        'jenis_server',
        'fungsi_server',
        'virtual_fisik',
        'lokasi_rack',
        'ip_utama',
        'sent_at',
    ];
}

function device_inventory_normalize_mac_address(string $value): string
{
    $hex = strtoupper(preg_replace('/[^A-F0-9]/i', '', $value) ?? '');
    if ($hex === '') {
        return '';
    }

    if (strlen($hex) === 12) {
        return implode(':', str_split($hex, 2));
    }

    return strtoupper(trim($value));
}

function device_inventory_normalize_network_adapters($adapters): array
{
    if (!is_array($adapters)) {
        return [];
    }

    $normalized = [];

    foreach ($adapters as $adapter) {
        if (!is_array($adapter)) {
            continue;
        }

        $adapterName = device_inventory_sanitize_text(
            (string) ($adapter['adapter_name'] ?? $adapter['description'] ?? ''),
            255
        );

        $ipAddresses = $adapter['ip_addresses'] ?? $adapter['ips'] ?? [];
        if (!is_array($ipAddresses)) {
            $ipAddresses = preg_split('/[\r\n,;]+/', (string) $ipAddresses) ?: [];
        }

        $validIps = [];
        foreach ($ipAddresses as $ipAddress) {
            $ipAddress = trim((string) $ipAddress);
            if ($ipAddress === '' || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
                continue;
            }

            $validIps[] = $ipAddress;
        }

        $validIps = array_values(array_unique($validIps));
        if (!$validIps) {
            continue;
        }

        $normalized[] = [
            'adapter_name' => $adapterName,
            'status' => 'active',
            'ip_addresses' => $validIps,
        ];
    }

    return $normalized;
}

function device_inventory_extract_ip_rows(array $data): array
{
    $rows = [];
    $seen = [];

    foreach (($data['network_adapters'] ?? []) as $adapter) {
        $adapterName = device_inventory_sanitize_text((string) ($adapter['adapter_name'] ?? ''), 255);
        foreach (($adapter['ip_addresses'] ?? []) as $ipAddress) {
            $ipAddress = trim((string) $ipAddress);
            if ($ipAddress === '' || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
                continue;
            }

            if (isset($seen[$ipAddress])) {
                continue;
            }

            $seen[$ipAddress] = true;
            $rows[] = [
                'ip_address' => $ipAddress,
                'adapter_name' => $adapterName,
                'status' => 'active',
            ];
        }
    }

    if (!$rows) {
        $fallbackIps = preg_split('/[\r\n,;]+/', (string) ($data['multiple_ip'] ?? '')) ?: [];
        array_unshift($fallbackIps, (string) ($data['ip_address'] ?? ''));

        foreach ($fallbackIps as $ipAddress) {
            $ipAddress = trim((string) $ipAddress);
            if ($ipAddress === '' || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
                continue;
            }

            if (isset($seen[$ipAddress])) {
                continue;
            }

            $seen[$ipAddress] = true;
            $rows[] = [
                'ip_address' => $ipAddress,
                'adapter_name' => '',
                'status' => 'active',
            ];
        }
    }

    return $rows;
}

function device_inventory_find_device_by_mac(PDO $pdo, string $macAddress): ?array
{
    $macKey = preg_replace('/[^A-F0-9]/i', '', $macAddress) ?? '';
    if ($macKey === '') {
        return null;
    }

    return fetch_one(
        $pdo,
        'SELECT id, device_type
         FROM komputer_client
         WHERE REPLACE(REPLACE(UPPER(mac_address), ":", ""), "-", "") = :mac_key
         ORDER BY id DESC
         LIMIT 1',
        ['mac_key' => strtoupper($macKey)]
    );
}

function device_inventory_sync_client_ip_addresses(PDO $pdo, int $clientId, array $ipRows): void
{
    if ($clientId <= 0 || !device_inventory_table_exists($pdo, 'client_ip_addresses')) {
        return;
    }

    $activeIps = [];
    $upsertStatement = $pdo->prepare('
        INSERT INTO client_ip_addresses (
            client_id, ip_address, adapter_name, status, created_at, updated_at
        ) VALUES (
            :client_id, :ip_address, :adapter_name, "active", NOW(), NOW()
        )
        ON DUPLICATE KEY UPDATE
            adapter_name = VALUES(adapter_name),
            status = "active",
            updated_at = NOW()
    ');

    foreach ($ipRows as $ipRow) {
        $ipAddress = trim((string) ($ipRow['ip_address'] ?? ''));
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
            'adapter_name' => device_inventory_sanitize_text((string) ($ipRow['adapter_name'] ?? ''), 255),
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

function device_inventory_normalize_upload_payload(array $payload): array
{
    $fields = device_inventory_upload_fields();
    $data = [];

    foreach ($fields as $field) {
        $data[$field] = device_inventory_sanitize_text((string) ($payload[$field] ?? ''), 500);
    }

    $data['core'] = max(0, (int) ($payload['core'] ?? 0));
    $data['total_thread'] = max(0, (int) ($payload['total_thread'] ?? 0));
    $data['device_type'] = strtoupper($data['device_type'] !== '' ? $data['device_type'] : 'CLIENT');
    $data['tahun_inventaris'] = trim((string) ($payload['tahun_inventaris'] ?? date('Y')));
    $data['kondisi'] = in_array($data['kondisi'], ['Baik', 'Rusak', 'Perbaikan'], true) ? $data['kondisi'] : 'Baik';
    $data['ip_address'] = $data['ip_address'] !== '' ? $data['ip_address'] : ($_SERVER['REMOTE_ADDR'] ?? '');
    $data['mac_address'] = device_inventory_normalize_mac_address($data['mac_address']);
    $data['domain_joined'] = in_array(strtolower($data['domain_joined']), ['yes', 'ya', 'true', '1'], true) ? 'Ya' : 'Tidak';
    $data['network_adapters'] = device_inventory_normalize_network_adapters($payload['network_adapters'] ?? []);

    if (!array_key_exists($data['device_type'], device_inventory_device_type_options())) {
        $data['device_type'] = 'CLIENT';
    }

    return $data;
}

function device_inventory_validate_upload_payload(array $data, array &$errors): void
{
    if ($data['hostname'] === '') {
        $errors[] = 'Hostname tidak terbaca.';
    }

    if ($data['mac_address'] === '') {
        $errors[] = 'MAC address tidak terbaca.';
    }

    if ($data['ruangan'] === '') {
        $errors[] = 'Ruangan wajib diisi.';
    }

    if ($data['petugas'] === '') {
        $errors[] = 'Nama user wajib diisi.';
    }

    if (!array_key_exists($data['device_type'], device_inventory_device_type_options())) {
        $errors[] = 'Tipe device tidak valid.';
    }

    if (!preg_match('/^\d{4}$/', $data['tahun_inventaris'])) {
        $errors[] = 'Tahun inventaris harus 4 digit.';
    }

    if ($data['sent_at'] === '' || strtotime($data['sent_at']) === false) {
        $errors[] = 'Timestamp upload tidak valid.';
    }
}

function device_inventory_payload_is_timed_out(string $sentAt): bool
{
    $timestamp = strtotime($sentAt);
    if ($timestamp === false) {
        return true;
    }

    return abs(time() - $timestamp) > device_inventory_upload_request_max_age_seconds();
}

function device_inventory_persist_device(PDO $pdo, array $data): array
{
    $tanggal = date('Y-m-d');
    $jam = date('H:i:s');
    $ipRows = device_inventory_extract_ip_rows($data);
    $existing = device_inventory_find_device_by_mac($pdo, $data['mac_address']);
    $computerId = (int) ($existing['id'] ?? 0);

    if ($computerId > 0) {
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
                petugas = :petugas,
                kondisi = :kondisi,
                tanggal = :tanggal,
                jam = :jam,
                updated_at = NOW()
            WHERE id = :id
        ');
        $statement->execute([
            'id' => $computerId,
            'device_type' => $data['device_type'],
            'hostname' => $data['hostname'],
            'username' => $data['username'],
            'ip_address' => $data['ip_address'],
            'mac_address' => $data['mac_address'],
            'merk' => $data['merk'],
            'model' => $data['model'],
            'processor' => $data['processor'],
            'core' => $data['core'],
            'ram' => $data['ram'],
            'ssd' => $data['ssd'],
            'hdd' => $data['hdd'],
            'vga' => $data['vga'],
            'motherboard' => $data['motherboard'],
            'serial_number' => $data['serial_number'],
            'os_name' => $data['os_name'],
            'os_version' => $data['os_version'],
            'architecture' => $data['architecture'],
            'tahun_inventaris' => $data['tahun_inventaris'],
            'ruangan' => $data['ruangan'],
            'petugas' => $data['petugas'],
            'kondisi' => $data['kondisi'],
            'tanggal' => $tanggal,
            'jam' => $jam,
        ]);
    } else {
        $statement = $pdo->prepare('
            INSERT INTO komputer_client (
                device_type, hostname, username, ip_address, mac_address, merk, model, processor, core, ram, ssd, hdd,
                vga, motherboard, serial_number, os_name, os_version, architecture, tahun_inventaris, ruangan, petugas,
                kondisi, tanggal, jam, created_at
            ) VALUES (
                :device_type, :hostname, :username, :ip_address, :mac_address, :merk, :model, :processor, :core, :ram, :ssd, :hdd,
                :vga, :motherboard, :serial_number, :os_name, :os_version, :architecture, :tahun_inventaris, :ruangan, :petugas,
                :kondisi, :tanggal, :jam, NOW()
            )
        ');
        $statement->execute([
            'device_type' => $data['device_type'],
            'hostname' => $data['hostname'],
            'username' => $data['username'],
            'ip_address' => $data['ip_address'],
            'mac_address' => $data['mac_address'],
            'merk' => $data['merk'],
            'model' => $data['model'],
            'processor' => $data['processor'],
            'core' => $data['core'],
            'ram' => $data['ram'],
            'ssd' => $data['ssd'],
            'hdd' => $data['hdd'],
            'vga' => $data['vga'],
            'motherboard' => $data['motherboard'],
            'serial_number' => $data['serial_number'],
            'os_name' => $data['os_name'],
            'os_version' => $data['os_version'],
            'architecture' => $data['architecture'],
            'tahun_inventaris' => $data['tahun_inventaris'],
            'ruangan' => $data['ruangan'],
            'petugas' => $data['petugas'],
            'kondisi' => $data['kondisi'],
            'tanggal' => $tanggal,
            'jam' => $jam,
        ]);
        $computerId = (int) $pdo->lastInsertId();
    }

    device_inventory_sync_client_ip_addresses($pdo, $computerId, $ipRows);

    if ($computerId > 0 && $data['device_type'] === 'SERVER') {
        $detailStatement = $pdo->prepare('
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
        $detailStatement->execute([
            'komputer_id' => $computerId,
            'virtualization' => $data['virtualization'],
            'raid' => $data['raid'],
            'hypervisor' => $data['hypervisor'],
            'uptime' => $data['uptime'],
            'total_thread' => $data['total_thread'],
            'multiple_nic' => $data['multiple_nic'],
            'multiple_ip' => $data['multiple_ip'],
            'domain_joined' => $data['domain_joined'],
            'server_role' => $data['server_role'],
            'jenis_server' => $data['jenis_server'],
            'fungsi_server' => $data['fungsi_server'],
            'virtual_fisik' => $data['virtual_fisik'],
            'lokasi_rack' => $data['lokasi_rack'],
            'ip_utama' => $data['ip_utama'],
        ]);
    } elseif ($computerId > 0) {
        $deleteDetail = $pdo->prepare('DELETE FROM server_detail WHERE komputer_id = :komputer_id');
        $deleteDetail->execute(['komputer_id' => $computerId]);
    }

    return [
        'id' => $computerId,
        'tanggal' => $tanggal,
        'jam' => $jam,
    ];
}

function device_inventory_bat_safe_value(string $value): string
{
    $value = str_replace(["\r", "\n"], ' ', $value);
    $value = preg_replace('/[%!"^&|<>]/', '', $value) ?? '';
    return trim($value);
}

function device_inventory_generate_bat(array $payload): string
{
    $serverUrl = device_inventory_absolute_url('api/device/upload/', true);
    $scriptUrl = device_inventory_absolute_url('client-agent/scan-device.ps1', true);

    $ruangan = device_inventory_bat_safe_value((string) ($payload['ruangan'] ?? ''));
    $tahun = device_inventory_bat_safe_value((string) ($payload['tahun_inventaris'] ?? date('Y')));
    $namaUser = device_inventory_bat_safe_value((string) ($payload['nama_user'] ?? ''));
    $kondisi = device_inventory_bat_safe_value((string) ($payload['kondisi'] ?? 'Baik'));
    $deviceType = device_inventory_bat_safe_value((string) ($payload['device_type'] ?? 'CLIENT'));
    $jenisServer = device_inventory_bat_safe_value((string) ($payload['jenis_server'] ?? ''));
    $fungsiServer = device_inventory_bat_safe_value((string) ($payload['fungsi_server'] ?? ''));
    $virtualFisik = device_inventory_bat_safe_value((string) ($payload['virtual_fisik'] ?? ''));
    $operatingSystem = device_inventory_bat_safe_value((string) ($payload['operating_system'] ?? ''));
    $lokasiRack = device_inventory_bat_safe_value((string) ($payload['lokasi_rack'] ?? ''));
    $ipUtama = device_inventory_bat_safe_value((string) ($payload['ip_utama'] ?? ''));
    $uploadToken = device_inventory_bat_safe_value((string) ($payload['upload_token'] ?? ''));

    return <<<BAT
@echo off
setlocal EnableExtensions EnableDelayedExpansion
title Launcher Pendataan Device RS
color 0B

set "LOG_DIR=%ProgramData%\\SiAEGIS\\device_inventory_logs"
if not exist "%LOG_DIR%" mkdir "%LOG_DIR%" >nul 2>&1
set "LOG_FILE=%LOG_DIR%\\launcher-%COMPUTERNAME%-%RANDOM%.log"

echo [%DATE% %TIME%] Launcher started>>"%LOG_FILE%"
echo Device type: {$deviceType}>>"%LOG_FILE%"

where powershell.exe >nul 2>&1
if errorlevel 1 (
    echo Powershell tidak ditemukan.>>"%LOG_FILE%"
    echo Powershell tidak tersedia pada device ini.
    pause
    exit /b 1
)

set "SCRIPT_URL={$scriptUrl}"
set "API_URL={$serverUrl}"
set "SCRIPT_PATH=%TEMP%\\scan-device-siaegis.ps1"
set "DEVICE_TYPE={$deviceType}"
set "RUANGAN={$ruangan}"
set "TAHUN_INVENTARIS={$tahun}"
set "NAMA_USER={$namaUser}"
set "KONDISI={$kondisi}"
set "JENIS_SERVER={$jenisServer}"
set "FUNGSI_SERVER={$fungsiServer}"
set "VIRTUAL_FISIK={$virtualFisik}"
set "OPERATING_SYSTEM={$operatingSystem}"
set "LOKASI_RACK={$lokasiRack}"
set "IP_UTAMA={$ipUtama}"
set "UPLOAD_TOKEN={$uploadToken}"

echo ============================================================
echo            PENDATAAN INVENTARIS DEVICE - SILEGIT
echo ============================================================
echo Server   : %API_URL%
echo Tipe     : %DEVICE_TYPE%
echo Ruangan  : %RUANGAN%
echo Tahun    : %TAHUN_INVENTARIS%
echo NamaUser : %NAMA_USER%
echo Kondisi  : %KONDISI%
echo.

echo Memvalidasi koneksi ke server...>>"%LOG_FILE%"
echo [1/3] Memvalidasi koneksi ke server...

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
"\$ErrorActionPreference='Stop'; ^
try { ^
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; ^
    \$response = Invoke-WebRequest -Uri '%SCRIPT_URL%' -Method Head -UseBasicParsing; ^
    if (\$response.StatusCode -ge 200 -and \$response.StatusCode -lt 400) { exit 0 } ^
    else { exit 9 } ^
} catch { ^
    exit 9 ^
}" >>"%LOG_FILE%" 2>&1

if errorlevel 1 (
    echo Koneksi ke server gagal.
    pause
    exit /b 1
)
echo     OK koneksi server tersedia.

echo Mengunduh PowerShell agent...>>"%LOG_FILE%"
echo [2/3] Mengunduh agent pendataan...

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
"\$ErrorActionPreference='Stop'; ^
try { ^
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; ^
    Invoke-WebRequest -Uri '%SCRIPT_URL%' -UseBasicParsing -OutFile '%SCRIPT_PATH%'; ^
    exit 0 ^
} catch { ^
    exit 11 ^
}" >>"%LOG_FILE%" 2>&1

if errorlevel 1 (
    echo Gagal mengunduh agent.
    pause
    exit /b 1
)
echo     OK agent berhasil diunduh.

echo Menjalankan agent...>>"%LOG_FILE%"
echo [3/3] Menjalankan proses scan device...
echo.

powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT_PATH%" ^
-ServerUrl "%API_URL%" ^
-UploadToken "%UPLOAD_TOKEN%" ^
-DeviceType "%DEVICE_TYPE%" ^
-Ruangan "%RUANGAN%" ^
-TahunInventaris "%TAHUN_INVENTARIS%" ^
-NamaUser "%NAMA_USER%" ^
-Kondisi "%KONDISI%" ^
-JenisServer "%JENIS_SERVER%" ^
-FungsiServer "%FUNGSI_SERVER%" ^
-VirtualFisik "%VIRTUAL_FISIK%" ^
-OperatingSystem "%OPERATING_SYSTEM%" ^
-LokasiRack "%LOKASI_RACK%" ^
-IpUtama "%IP_UTAMA%"

set "EXIT_CODE=%ERRORLEVEL%"

if not "%EXIT_CODE%"=="0" (
    echo Data gagal dikirim.
    echo Log: %LOG_FILE%
    pause
    exit /b %EXIT_CODE%
)

echo Data berhasil dikirim.
echo Log: %LOG_FILE%
pause
exit /b 0
BAT;
}

function device_inventory_json_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}
