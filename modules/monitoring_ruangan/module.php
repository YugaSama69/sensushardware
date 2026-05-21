<?php

declare(strict_types=1);

const MONITORING_SIGNATURE_MAX_BYTES = 524288;

function monitoring_normalize_filters(array $input): array
{
    return [
        'search' => trim((string) ($input['search'] ?? '')),
        'date_from' => trim((string) ($input['date_from'] ?? '')),
        'date_to' => trim((string) ($input['date_to'] ?? '')),
        'ruangan_id' => trim((string) ($input['ruangan_id'] ?? '')),
        'petugas_id' => trim((string) ($input['petugas_id'] ?? '')),
        'status' => trim((string) ($input['status'] ?? '')),
    ];
}

function monitoring_status_options(): array
{
    return [
        'normal' => 'Normal',
        'warning' => 'Warning',
        'kritikal' => 'Kritikal',
    ];
}

function monitoring_temperature_options(): array
{
    return [
        '20_21' => '16-21°C',
        'gt_20_21' => '>21°C',
    ];
}

function monitoring_access_options(): array
{
    return [
        'terkunci' => 'Terkunci',
        'tidak_terkunci' => 'Tidak Terkunci',
    ];
}

function monitoring_temperature_label(?string $value): string
{
    $options = monitoring_temperature_options();
    $label = $options[$value ?? ''] ?? '-';
    return str_replace('Â°C', '°C', $label);
}

function monitoring_temperature_value_from_row(array $row): float
{
    $temperatureCode = trim((string) ($row['suhu'] ?? ''));
    if ($temperatureCode === 'gt_20_21') {
        return 21.0;
    }

    $seedSource = implode('|', [
        (string) ($row['id'] ?? ''),
        (string) ($row['tanggal'] ?? ''),
        (string) ($row['ruangan_id'] ?? ''),
        (string) ($row['petugas_id'] ?? ''),
    ]);
    $seed = abs((int) crc32($seedSource));

    return 16.0 + (($seed % 50) / 10);
}

function monitoring_format_temperature_value(float $value): string
{
    $rounded = round($value, 1);
    $whole = round($rounded);

    if (abs($rounded - $whole) < 0.05) {
        return (string) ((int) $whole) . '°C';
    }

    return number_format($rounded, 1, '.', '') . '°C';
}

function monitoring_temperature_display(array $row): string
{
    $temperatureCode = trim((string) ($row['suhu'] ?? ''));
    if (!array_key_exists($temperatureCode, monitoring_temperature_options())) {
        return monitoring_temperature_label($temperatureCode);
    }

    return monitoring_format_temperature_value(monitoring_temperature_value_from_row($row));
}

function monitoring_access_label(?string $value): string
{
    $options = monitoring_access_options();
    return $options[$value ?? ''] ?? '-';
}

function monitoring_status_label(?string $value): string
{
    $options = monitoring_status_options();
    return $options[$value ?? ''] ?? '-';
}

function monitoring_status_badge_class(?string $value): string
{
    switch ($value) {
        case 'normal':
            return 'success';
        case 'warning':
            return 'warning';
        case 'kritikal':
            return 'danger';
        default:
            return 'secondary';
    }
}

function monitoring_status_icon(?string $value): string
{
    switch ($value) {
        case 'normal':
            return 'Normal';
        case 'warning':
            return 'Perlu perhatian';
        case 'kritikal':
            return 'Kritis';
        default:
            return 'Tidak diketahui';
    }
}

function monitoring_active_label($value): string
{
    return (int) $value === 1 ? 'Aktif' : 'Nonaktif';
}

function monitoring_build_query_string(array $filters): string
{
    return http_build_query(array_filter($filters, static fn ($value) => $value !== ''));
}

function monitoring_get_rooms(PDO $pdo, bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM monitoring_master_ruangan';
    $params = [];

    if ($activeOnly) {
        $sql .= ' WHERE status_aktif = :status_aktif';
        $params['status_aktif'] = 1;
    }

    $sql .= ' ORDER BY nama_ruangan ASC';

    return fetch_all($pdo, $sql, $params);
}

function monitoring_get_staff(PDO $pdo, bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM monitoring_master_petugas';
    $params = [];

    if ($activeOnly) {
        $sql .= ' WHERE status_aktif = :status_aktif';
        $params['status_aktif'] = 1;
    }

    $sql .= ' ORDER BY nama_lengkap ASC';

    return fetch_all($pdo, $sql, $params);
}

function monitoring_get_room_map(PDO $pdo, bool $activeOnly = false): array
{
    $rows = monitoring_get_rooms($pdo, $activeOnly);
    $map = [];

    foreach ($rows as $row) {
        $map[(int) $row['id']] = $row;
    }

    return $map;
}

function monitoring_get_staff_map(PDO $pdo, bool $activeOnly = false): array
{
    $rows = monitoring_get_staff($pdo, $activeOnly);
    $map = [];

    foreach ($rows as $row) {
        $map[(int) $row['id']] = $row;
    }

    return $map;
}

function monitoring_calculate_status(string $temperature, string $access): string
{
    $isTemperatureWarning = $temperature === 'gt_20_21';
    $isAccessWarning = $access === 'tidak_terkunci';

    if ($isTemperatureWarning && $isAccessWarning) {
        return 'kritikal';
    }

    if ($isTemperatureWarning || $isAccessWarning) {
        return 'warning';
    }

    return 'normal';
}

function monitoring_issue_badges(array $row): array
{
    $badges = [];

    if (($row['suhu'] ?? '') === 'gt_20_21') {
        $badges[] = 'Suhu abnormal';
    }

    if (($row['akses_masuk'] ?? '') === 'tidak_terkunci') {
        $badges[] = 'Akses tidak terkunci';
    }

    if (!$badges) {
        $badges[] = 'Kondisi normal';
    }

    return $badges;
}

function monitoring_signature_status_label(array $row): string
{
    return monitoring_has_signature($row) ? 'Sudah paraf' : 'Belum paraf';
}

function monitoring_device_info(): string
{
    $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($userAgent === '') {
        return 'Browser tidak terdeteksi';
    }

    return substr($userAgent, 0, 255);
}

function monitoring_clean_text(?string $value, int $maxLength = 2000): string
{
    $value = trim(strip_tags((string) $value));

    if ($value === '') {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function monitoring_prepare_payload(array $input): array
{
    $tanggal = trim((string) ($input['tanggal'] ?? date('Y-m-d')));
    $jamMonitoring = trim((string) ($input['jam_monitoring'] ?? date('H:i:s')));

    if (preg_match('/^\d{2}:\d{2}$/', $jamMonitoring) === 1) {
        $jamMonitoring .= ':00';
    }

    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $jamMonitoring)) {
        $jamMonitoring = date('H:i:s');
    }

    return [
        'tanggal' => $tanggal,
        'jam_monitoring' => $jamMonitoring,
        'ruangan_id' => (int) ($input['ruangan_id'] ?? 0),
        'suhu' => trim((string) ($input['suhu'] ?? '')),
        'akses_masuk' => trim((string) ($input['akses_masuk'] ?? '')),
        'petugas_id' => (int) ($input['petugas_id'] ?? 0),
        'catatan' => monitoring_clean_text($input['catatan'] ?? '', 2000),
        'signature_base64' => trim((string) ($input['signature_base64'] ?? '')),
    ];
}

function monitoring_validate_entry_payload(PDO $pdo, array $payload, array &$errors, bool $requireSignature = true): ?array
{
    $signatureInfo = null;

    if ($requireSignature) {
        $signatureInfo = monitoring_validate_signature($payload['signature_base64'], $errors);
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payload['tanggal'])) {
        $errors[] = 'Tanggal monitoring tidak valid.';
    }

    if (!array_key_exists($payload['suhu'], monitoring_temperature_options())) {
        $errors[] = 'Pilihan suhu ruangan tidak valid.';
    }

    if (!array_key_exists($payload['akses_masuk'], monitoring_access_options())) {
        $errors[] = 'Pilihan akses masuk tidak valid.';
    }

    $room = fetch_one(
        $pdo,
        'SELECT * FROM monitoring_master_ruangan WHERE id = :id AND status_aktif = 1 LIMIT 1',
        ['id' => $payload['ruangan_id']]
    );
    if (!$room) {
        $errors[] = 'Ruangan monitoring wajib dipilih dari master ruangan aktif.';
    }

    $staff = fetch_one(
        $pdo,
        'SELECT * FROM monitoring_master_petugas WHERE id = :id AND status_aktif = 1 LIMIT 1',
        ['id' => $payload['petugas_id']]
    );
    if (!$staff) {
        $errors[] = 'Petugas monitoring wajib dipilih dari master petugas aktif.';
    }

    $needsNote = $payload['suhu'] === 'gt_20_21' || $payload['akses_masuk'] === 'tidak_terkunci';
    if ($needsNote && $payload['catatan'] === '') {
        $errors[] = 'Catatan wajib diisi jika suhu di atas batas atau akses tidak terkunci.';
    }

    if ($errors) {
        return null;
    }

    return [
        'room' => $room,
        'staff' => $staff,
        'status' => monitoring_calculate_status($payload['suhu'], $payload['akses_masuk']),
        'signature_info' => $signatureInfo,
    ];
}

function monitoring_validate_signature(string $signatureBase64, array &$errors): ?array
{
    if ($signatureBase64 === '') {
        $errors[] = 'Paraf/tanda tangan wajib diisi.';
        return null;
    }

    if (!str_starts_with($signatureBase64, 'data:image/png;base64,')) {
        $errors[] = 'Format tanda tangan harus berupa PNG base64.';
        return null;
    }

    $binary = base64_decode(substr($signatureBase64, strlen('data:image/png;base64,')), true);
    if ($binary === false || $binary === '') {
        $errors[] = 'Data tanda tangan tidak valid.';
        return null;
    }

    if (strlen($binary) > MONITORING_SIGNATURE_MAX_BYTES) {
        $errors[] = 'Ukuran tanda tangan terlalu besar. Maksimal 512 KB.';
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->buffer($binary);
    if ($mime !== 'image/png') {
        $errors[] = 'Tanda tangan harus berformat image/png.';
        return null;
    }

    $dimensions = monitoring_png_dimensions($binary);
    if ($dimensions === null || $dimensions['width'] < 10 || $dimensions['height'] < 10) {
        $errors[] = 'Ukuran tanda tangan tidak valid.';
        return null;
    }

    return [
        'mime' => $mime,
        'bytes' => strlen($binary),
        'width' => $dimensions['width'],
        'height' => $dimensions['height'],
        'binary' => $binary,
    ];
}

function monitoring_png_dimensions(string $binary): ?array
{
    if (substr($binary, 0, 8) !== "\x89PNG\x0D\x0A\x1A\x0A") {
        return null;
    }

    $width = unpack('N', substr($binary, 16, 4))[1] ?? 0;
    $height = unpack('N', substr($binary, 20, 4))[1] ?? 0;

    if ($width <= 0 || $height <= 0) {
        return null;
    }

    return [
        'width' => (int) $width,
        'height' => (int) $height,
    ];
}

function monitoring_create_entry(PDO $pdo, array $input, array $serverMeta, array &$errors = []): ?int
{
    $payload = monitoring_prepare_payload($input);
    $validation = monitoring_validate_entry_payload($pdo, $payload, $errors, true);
    if ($validation === null) {
        return null;
    }

    $user = current_user();

    $statement = $pdo->prepare(
        'INSERT INTO monitoring_ruangan (
            tanggal,
            jam_monitoring,
            ruangan_id,
            suhu,
            akses_masuk,
            petugas_id,
            catatan,
            signature_base64,
            status,
            created_by,
            created_at,
            updated_at,
            ip_address,
            device_info
        ) VALUES (
            :tanggal,
            :jam_monitoring,
            :ruangan_id,
            :suhu,
            :akses_masuk,
            :petugas_id,
            :catatan,
            :signature_base64,
            :status,
            :created_by,
            NOW(),
            NOW(),
            :ip_address,
            :device_info
        )'
    );

    $statement->execute([
        'tanggal' => $payload['tanggal'],
        'jam_monitoring' => $payload['jam_monitoring'],
        'ruangan_id' => $payload['ruangan_id'],
        'suhu' => $payload['suhu'],
        'akses_masuk' => $payload['akses_masuk'],
        'petugas_id' => $payload['petugas_id'],
        'catatan' => $payload['catatan'] !== '' ? $payload['catatan'] : null,
        'signature_base64' => $payload['signature_base64'],
        'status' => $validation['status'],
        'created_by' => (string) ($user['username'] ?? 'system'),
        'ip_address' => substr((string) ($serverMeta['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 45),
        'device_info' => substr((string) ($serverMeta['device_info'] ?? monitoring_device_info()), 0, 255),
    ]);

    return (int) $pdo->lastInsertId();
}

function monitoring_update_entry(PDO $pdo, int $id, array $input, array &$errors = []): bool
{
    if ($id <= 0) {
        $errors[] = 'Data monitoring tidak valid.';
        return false;
    }

    $existing = monitoring_find_row($pdo, $id);
    if (!$existing) {
        $errors[] = 'Data monitoring tidak ditemukan.';
        return false;
    }

    $payload = monitoring_prepare_payload($input);
    $payload['signature_base64'] = trim((string) ($existing['signature_base64'] ?? ''));
    $payload['jam_monitoring'] = (string) ($existing['jam_monitoring'] ?? date('H:i:s'));

    $validation = monitoring_validate_entry_payload($pdo, $payload, $errors, false);
    if ($validation === null) {
        return false;
    }

    $statement = $pdo->prepare(
        'UPDATE monitoring_ruangan
         SET tanggal = :tanggal,
             ruangan_id = :ruangan_id,
             suhu = :suhu,
             akses_masuk = :akses_masuk,
             petugas_id = :petugas_id,
             catatan = :catatan,
             status = :status,
             updated_at = NOW()
         WHERE id = :id'
    );

    $statement->execute([
        'id' => $id,
        'tanggal' => $payload['tanggal'],
        'ruangan_id' => $payload['ruangan_id'],
        'suhu' => $payload['suhu'],
        'akses_masuk' => $payload['akses_masuk'],
        'petugas_id' => $payload['petugas_id'],
        'catatan' => $payload['catatan'] !== '' ? $payload['catatan'] : null,
        'status' => $validation['status'],
    ]);

    return true;
}

function monitoring_get_rows(PDO $pdo, array $filters): array
{
    $sql = 'SELECT
                m.*,
                r.nama_ruangan,
                r.lokasi AS lokasi_ruangan,
                p.nama_lengkap,
                p.nip_nik,
                p.jabatan
            FROM monitoring_ruangan m
            INNER JOIN monitoring_master_ruangan r ON r.id = m.ruangan_id
            INNER JOIN monitoring_master_petugas p ON p.id = m.petugas_id
            WHERE 1=1';
    $params = [];

    if ($filters['date_from'] !== '') {
        $sql .= ' AND m.tanggal >= :date_from';
        $params['date_from'] = $filters['date_from'];
    }

    if ($filters['date_to'] !== '') {
        $sql .= ' AND m.tanggal <= :date_to';
        $params['date_to'] = $filters['date_to'];
    }

    if ($filters['ruangan_id'] !== '') {
        $sql .= ' AND m.ruangan_id = :ruangan_id';
        $params['ruangan_id'] = (int) $filters['ruangan_id'];
    }

    if ($filters['petugas_id'] !== '') {
        $sql .= ' AND m.petugas_id = :petugas_id';
        $params['petugas_id'] = (int) $filters['petugas_id'];
    }

    if ($filters['status'] !== '') {
        $sql .= ' AND m.status = :status';
        $params['status'] = $filters['status'];
    }

    if ($filters['search'] !== '') {
        $sql .= ' AND (
            r.nama_ruangan LIKE :search
            OR p.nama_lengkap LIKE :search
            OR p.jabatan LIKE :search
            OR COALESCE(m.catatan, "") LIKE :search
            OR m.created_by LIKE :search
        )';
        $params['search'] = '%' . $filters['search'] . '%';
    }

    $sql .= ' ORDER BY m.tanggal DESC, m.jam_monitoring DESC, m.id DESC';

    return fetch_all($pdo, $sql, $params);
}

function monitoring_find_row(PDO $pdo, int $id): ?array
{
    return fetch_one(
        $pdo,
        'SELECT
            m.*,
            r.nama_ruangan,
            r.lokasi AS lokasi_ruangan,
            p.nama_lengkap,
            p.nip_nik,
            p.jabatan
         FROM monitoring_ruangan m
         INNER JOIN monitoring_master_ruangan r ON r.id = m.ruangan_id
         INNER JOIN monitoring_master_petugas p ON p.id = m.petugas_id
         WHERE m.id = :id
         LIMIT 1',
        ['id' => $id]
    );
}

function monitoring_get_dashboard_stats(PDO $pdo): array
{
    $today = date('Y-m-d');

    $chartRows = fetch_all(
        $pdo,
        'SELECT
            tanggal,
            COUNT(*) AS total_monitoring,
            SUM(CASE WHEN status <> "normal" THEN 1 ELSE 0 END) AS total_warning
         FROM monitoring_ruangan
         WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY tanggal
         ORDER BY tanggal ASC'
    );

    return [
        'today_total' => (int) fetch_scalar($pdo, 'SELECT COUNT(*) FROM monitoring_ruangan WHERE tanggal = CURDATE()'),
        'problem_rooms' => (int) fetch_scalar(
            $pdo,
            'SELECT COUNT(DISTINCT ruangan_id) FROM monitoring_ruangan WHERE tanggal = CURDATE() AND status <> "normal"'
        ),
        'abnormal_temperature' => (int) fetch_scalar(
            $pdo,
            'SELECT COUNT(*) FROM monitoring_ruangan WHERE tanggal = CURDATE() AND suhu = "gt_20_21"'
        ),
        'unlocked_access' => (int) fetch_scalar(
            $pdo,
            'SELECT COUNT(*) FROM monitoring_ruangan WHERE tanggal = CURDATE() AND akses_masuk = "tidak_terkunci"'
        ),
        'latest_entry' => fetch_one(
            $pdo,
            'SELECT
                m.*,
                r.nama_ruangan,
                p.nama_lengkap,
                p.jabatan
             FROM monitoring_ruangan m
             INNER JOIN monitoring_master_ruangan r ON r.id = m.ruangan_id
             INNER JOIN monitoring_master_petugas p ON p.id = m.petugas_id
             ORDER BY m.tanggal DESC, m.jam_monitoring DESC, m.id DESC
             LIMIT 1'
        ),
        'chart_rows' => $chartRows,
        'today' => $today,
    ];
}

function monitoring_chart_dataset(array $chartRows): array
{
    $indexed = [];
    foreach ($chartRows as $row) {
        $indexed[$row['tanggal']] = $row;
    }

    $labels = [];
    $totals = [];
    $warnings = [];

    for ($dayOffset = 6; $dayOffset >= 0; $dayOffset--) {
        $date = date('Y-m-d', strtotime('-' . $dayOffset . ' day'));
        $labels[] = date('d/m', strtotime($date));
        $totals[] = (int) ($indexed[$date]['total_monitoring'] ?? 0);
        $warnings[] = (int) ($indexed[$date]['total_warning'] ?? 0);
    }

    return [
        'labels' => $labels,
        'totals' => $totals,
        'warnings' => $warnings,
    ];
}

function monitoring_room_in_use(PDO $pdo, int $id): bool
{
    return (int) fetch_scalar($pdo, 'SELECT COUNT(*) FROM monitoring_ruangan WHERE ruangan_id = :id', ['id' => $id]) > 0;
}

function monitoring_staff_in_use(PDO $pdo, int $id): bool
{
    return (int) fetch_scalar($pdo, 'SELECT COUNT(*) FROM monitoring_ruangan WHERE petugas_id = :id', ['id' => $id]) > 0;
}

function monitoring_modal_id(string $prefix, int $id): string
{
    return $prefix . $id;
}

function monitoring_json_row(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'tanggal' => $row['tanggal'],
        'jam_monitoring' => $row['jam_monitoring'],
        'ruangan' => $row['nama_ruangan'] ?? '',
        'suhu' => monitoring_temperature_display($row),
        'akses_masuk' => monitoring_access_label($row['akses_masuk'] ?? ''),
        'petugas' => $row['nama_lengkap'] ?? '',
        'jabatan' => $row['jabatan'] ?? '',
        'status' => $row['status'] ?? '',
        'status_label' => monitoring_status_label($row['status'] ?? ''),
        'catatan' => $row['catatan'] ?? '',
        'signature_base64' => $row['signature_base64'] ?? '',
        'has_signature' => trim((string) ($row['signature_base64'] ?? '')) !== '',
        'created_by' => $row['created_by'] ?? '',
        'created_at' => $row['created_at'] ?? '',
        'ip_address' => $row['ip_address'] ?? '',
        'device_info' => $row['device_info'] ?? '',
    ];
}

function monitoring_has_signature(array $row): bool
{
    return trim((string) ($row['signature_base64'] ?? '')) !== '';
}

function monitoring_update_signature(PDO $pdo, int $id, string $signatureBase64, array &$errors = []): bool
{
    if ($id <= 0) {
        $errors[] = 'Data monitoring tidak valid.';
        return false;
    }

    $existing = monitoring_find_row($pdo, $id);
    if (!$existing) {
        $errors[] = 'Data monitoring tidak ditemukan.';
        return false;
    }

    monitoring_validate_signature($signatureBase64, $errors);
    if ($errors) {
        return false;
    }

    $statement = $pdo->prepare(
        'UPDATE monitoring_ruangan
         SET signature_base64 = :signature_base64, updated_at = NOW()
         WHERE id = :id'
    );

    $statement->execute([
        'id' => $id,
        'signature_base64' => $signatureBase64,
    ]);

    return true;
}

function monitoring_json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function monitoring_require_csrf_token_from_request(array $input): void
{
    $token = (string) ($input['_csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

    if (!hash_equals(csrf_token(), $token)) {
        throw new RuntimeException('Token CSRF tidak valid.');
    }
}

function monitoring_pdf_escape(string $value): string
{
    $value = str_replace('\\', '\\\\', $value);
    $value = str_replace('(', '\\(', $value);
    $value = str_replace(')', '\\)', $value);
    return preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
}

function monitoring_pdf_wrap(string $text, int $width = 70): array
{
    $clean = trim((string) $text);
    if ($clean === '') {
        return ['-'];
    }

    $wrapped = wordwrap($clean, $width, "\n", true);
    return explode("\n", $wrapped);
}

function monitoring_png_to_pdf_image(string $dataUri): ?array
{
    $errors = [];
    $signatureInfo = monitoring_validate_signature($dataUri, $errors);
    if (!$signatureInfo) {
        return null;
    }

    $binary = $signatureInfo['binary'];
    if (substr($binary, 0, 8) !== "\x89PNG\x0D\x0A\x1A\x0A") {
        return null;
    }

    $offset = 8;
    $width = 0;
    $height = 0;
    $bitDepth = 0;
    $colorType = 0;
    $idat = '';

    while ($offset < strlen($binary)) {
        $lengthData = unpack('N', substr($binary, $offset, 4));
        $length = (int) ($lengthData[1] ?? 0);
        $offset += 4;
        $chunkType = substr($binary, $offset, 4);
        $offset += 4;
        $chunkData = substr($binary, $offset, $length);
        $offset += $length + 4;

        if ($chunkType === 'IHDR') {
            $ihdr = unpack('Nwidth/Nheight/Cbit/Ccolor/Ccompression/Cfilter/Cinterlace', $chunkData);
            $width = (int) ($ihdr['width'] ?? 0);
            $height = (int) ($ihdr['height'] ?? 0);
            $bitDepth = (int) ($ihdr['bit'] ?? 0);
            $colorType = (int) ($ihdr['color'] ?? 0);
        } elseif ($chunkType === 'IDAT') {
            $idat .= $chunkData;
        } elseif ($chunkType === 'IEND') {
            break;
        }
    }

    if ($width <= 0 || $height <= 0 || $bitDepth !== 8 || !in_array($colorType, [2, 6], true)) {
        return null;
    }

    $inflated = zlib_decode($idat);
    if (!is_string($inflated) || $inflated === '') {
        return null;
    }

    $channels = $colorType === 6 ? 4 : 3;
    $bytesPerPixel = $channels;
    $rowLength = $width * $channels;
    $position = 0;
    $previousRow = array_fill(0, $rowLength, 0);
    $rgbBinary = '';
    $alphaBinary = '';

    for ($rowIndex = 0; $rowIndex < $height; $rowIndex++) {
        $filterType = ord($inflated[$position]);
        $position++;

        $rawRow = substr($inflated, $position, $rowLength);
        $position += $rowLength;

        $currentRow = [];
        for ($i = 0; $i < $rowLength; $i++) {
            $current = ord($rawRow[$i]);
            $left = $i >= $bytesPerPixel ? $currentRow[$i - $bytesPerPixel] : 0;
            $up = $previousRow[$i] ?? 0;
            $upLeft = $i >= $bytesPerPixel ? ($previousRow[$i - $bytesPerPixel] ?? 0) : 0;

            switch ($filterType) {
                case 1:
                    $value = ($current + $left) & 255;
                    break;
                case 2:
                    $value = ($current + $up) & 255;
                    break;
                case 3:
                    $value = ($current + (int) floor(($left + $up) / 2)) & 255;
                    break;
                case 4:
                    $value = ($current + monitoring_png_paeth($left, $up, $upLeft)) & 255;
                    break;
                default:
                    $value = $current;
                    break;
            }

            $currentRow[$i] = $value;
        }

        if ($colorType === 6) {
            for ($pixel = 0; $pixel < $width; $pixel++) {
                $base = $pixel * 4;
                $rgbBinary .= chr($currentRow[$base]) . chr($currentRow[$base + 1]) . chr($currentRow[$base + 2]);
                $alphaBinary .= chr($currentRow[$base + 3]);
            }
        } else {
            for ($pixel = 0; $pixel < $width; $pixel++) {
                $base = $pixel * 3;
                $rgbBinary .= chr($currentRow[$base]) . chr($currentRow[$base + 1]) . chr($currentRow[$base + 2]);
            }
        }

        $previousRow = $currentRow;
    }

    return [
        'width' => $width,
        'height' => $height,
        'rgb_stream' => gzcompress($rgbBinary),
        'alpha_stream' => $alphaBinary !== '' ? gzcompress($alphaBinary) : null,
    ];
}

function monitoring_png_paeth(int $left, int $up, int $upLeft): int
{
    $predictor = $left + $up - $upLeft;
    $pa = abs($predictor - $left);
    $pb = abs($predictor - $up);
    $pc = abs($predictor - $upLeft);

    if ($pa <= $pb && $pa <= $pc) {
        return $left;
    }

    if ($pb <= $pc) {
        return $up;
    }

    return $upLeft;
}

function monitoring_export_rows(array $rows): array
{
    $exportRows = [];

    foreach ($rows as $index => $row) {
        $ruangan = trim((string) ($row['nama_ruangan'] ?? ''));
        $lokasi = trim((string) ($row['lokasi_ruangan'] ?? ''));
        if ($lokasi !== '') {
            $ruangan = trim($ruangan . "\n" . $lokasi);
        }

        $petugas = trim((string) ($row['nama_lengkap'] ?? ''));
        $jabatan = trim((string) ($row['jabatan'] ?? ''));
        if ($jabatan !== '') {
            $petugas = trim($petugas . "\n" . $jabatan);
        }

        $exportRows[] = [
            'no' => (string) ($index + 1),
            'tanggal' => date('d/m/Y', strtotime((string) ($row['tanggal'] ?? date('Y-m-d')))),
            'ruangan' => $ruangan !== '' ? $ruangan : '-',
            'suhu' => monitoring_temperature_display($row),
            'akses_masuk' => monitoring_access_label((string) ($row['akses_masuk'] ?? '')),
            'petugas' => $petugas !== '' ? $petugas : '-',
            'status' => monitoring_status_label((string) ($row['status'] ?? '')),
            'paraf_text' => '',
            'signature_base64' => (string) ($row['signature_base64'] ?? ''),
        ];
    }

    return $exportRows;
}

function monitoring_signature_png_binary(string $dataUri): ?string
{
    $dataUri = trim($dataUri);
    if ($dataUri === '' || !str_starts_with($dataUri, 'data:image/png;base64,')) {
        return null;
    }

    $binary = base64_decode(substr($dataUri, strlen('data:image/png;base64,')), true);
    return is_string($binary) && $binary !== '' ? $binary : null;
}

function monitoring_xlsx_xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function monitoring_xlsx_column_name(int $index): string
{
    $name = '';

    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = intdiv($index, 26);
    }

    return $name;
}

function monitoring_zip_pack(array $files): string
{
    $localData = '';
    $centralDirectory = '';
    $offset = 0;
    $entryCount = 0;

    $date = getdate();
    $dosTime = (($date['hours'] & 0x1F) << 11) | (($date['minutes'] & 0x3F) << 5) | (int) floor($date['seconds'] / 2);
    $dosDate = ((max(1980, (int) $date['year']) - 1980) << 9) | (($date['mon'] & 0x0F) << 5) | ($date['mday'] & 0x1F);

    foreach ($files as $name => $content) {
        $name = str_replace('\\', '/', (string) $name);
        $content = (string) $content;
        $crc = (int) sprintf('%u', crc32($content));
        $size = strlen($content);
        $nameLength = strlen($name);

        $localHeader = pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0,
            0,
            $dosTime,
            $dosDate,
            $crc,
            $size,
            $size,
            $nameLength,
            0
        );

        $localData .= $localHeader . $name . $content;

        $centralHeader = pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            20,
            20,
            0,
            0,
            $dosTime,
            $dosDate,
            $crc,
            $size,
            $size,
            $nameLength,
            0,
            0,
            0,
            0,
            32,
            $offset
        );

        $centralDirectory .= $centralHeader . $name;
        $offset = strlen($localData);
        $entryCount++;
    }

    $endRecord = pack(
        'VvvvvVVv',
        0x06054b50,
        0,
        0,
        $entryCount,
        $entryCount,
        strlen($centralDirectory),
        strlen($localData),
        0
    );

    return $localData . $centralDirectory . $endRecord;
}

function monitoring_output_xlsx(string $filename, string $title, array $rows): void
{
    $exportRows = monitoring_export_rows($rows);
    $columns = [
        ['key' => 'no', 'label' => 'No', 'width' => 6, 'wrap' => false, 'style' => 3],
        ['key' => 'tanggal', 'label' => 'Tanggal', 'width' => 14, 'wrap' => false, 'style' => 3],
        ['key' => 'ruangan', 'label' => 'Ruangan', 'width' => 28, 'wrap' => true, 'style' => 4],
        ['key' => 'suhu', 'label' => 'Suhu', 'width' => 12, 'wrap' => false, 'style' => 3],
        ['key' => 'akses_masuk', 'label' => 'Akses Masuk', 'width' => 18, 'wrap' => true, 'style' => 4],
        ['key' => 'petugas', 'label' => 'Petugas', 'width' => 28, 'wrap' => true, 'style' => 4],
        ['key' => 'status', 'label' => 'Status', 'width' => 14, 'wrap' => false, 'style' => 3],
        ['key' => 'paraf_text', 'label' => 'Paraf / Sign', 'width' => 18, 'wrap' => true, 'style' => 5],
    ];

    foreach ($columns as $index => $column) {
        $maxLineLength = strlen($column['label']);
        foreach ($exportRows as $row) {
            foreach (preg_split('/\r\n|\r|\n/', (string) ($row[$column['key']] ?? '')) ?: [] as $line) {
                $maxLineLength = max($maxLineLength, strlen($line));
            }
        }

        $columns[$index]['width'] = min(max($column['width'], ($maxLineLength * 1.12) + 2), $column['key'] === 'paraf_text' ? 22 : 36);
    }

    $images = [];
    $imageFiles = [];
    $drawingAnchors = [];
    $drawingRels = [];
    $drawingCounter = 1;

    foreach ($rows as $index => $row) {
        $binary = monitoring_signature_png_binary((string) ($row['signature_base64'] ?? ''));
        if ($binary === null) {
            continue;
        }

        $imageName = 'image' . $drawingCounter . '.png';
        $imageFiles['xl/media/' . $imageName] = $binary;
        $drawingRels[] = '<Relationship Id="rId' . $drawingCounter . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/' . $imageName . '"/>';
        $drawingAnchors[] = '
            <xdr:oneCellAnchor>
                <xdr:from>
                    <xdr:col>7</xdr:col>
                    <xdr:colOff>19050</xdr:colOff>
                    <xdr:row>' . ($index + 1) . '</xdr:row>
                    <xdr:rowOff>19050</xdr:rowOff>
                </xdr:from>
                <xdr:ext cx="952500" cy="304800"/>
                <xdr:pic>
                    <xdr:nvPicPr>
                        <xdr:cNvPr id="' . $drawingCounter . '" name="Signature ' . $drawingCounter . '"/>
                        <xdr:cNvPicPr/>
                    </xdr:nvPicPr>
                    <xdr:blipFill>
                        <a:blip r:embed="rId' . $drawingCounter . '"/>
                        <a:stretch><a:fillRect/></a:stretch>
                    </xdr:blipFill>
                    <xdr:spPr>
                        <a:xfrm><a:off x="0" y="0"/><a:ext cx="952500" cy="304800"/></a:xfrm>
                        <a:prstGeom prst="rect"><a:avLst/></a:prstGeom>
                    </xdr:spPr>
                </xdr:pic>
                <xdr:clientData/>
            </xdr:oneCellAnchor>';
        $drawingCounter++;
    }

    $sheetRowsXml = '';
    $headerCells = '';

    foreach ($columns as $index => $column) {
        $cellRef = monitoring_xlsx_column_name($index + 1) . '1';
        $headerCells .= '<c r="' . $cellRef . '" s="1" t="inlineStr"><is><t>' . monitoring_xlsx_xml_escape($column['label']) . '</t></is></c>';
    }

    $sheetRowsXml .= '<row r="1" ht="24" customHeight="1">' . $headerCells . '</row>';

    foreach ($exportRows as $rowIndex => $row) {
        $excelRow = $rowIndex + 2;
        $rowCells = '';
        $hasSignature = monitoring_signature_png_binary((string) ($row['signature_base64'] ?? '')) !== null;
        $estimatedLines = 1;

        foreach (['ruangan', 'petugas', 'akses_masuk', 'paraf_text'] as $wrapKey) {
            $lineCount = count(preg_split('/\r\n|\r|\n/', (string) ($row[$wrapKey] ?? '')) ?: []);
            $estimatedLines = max($estimatedLines, $lineCount);
        }

        $rowHeight = max(22, 18 + ($estimatedLines * 12));
        if ($hasSignature) {
            $rowHeight = max($rowHeight, 54);
        }

        foreach ($columns as $columnIndex => $column) {
            $cellRef = monitoring_xlsx_column_name($columnIndex + 1) . $excelRow;
            $value = (string) ($row[$column['key']] ?? '');
            $rowCells .= '<c r="' . $cellRef . '" s="' . $column['style'] . '" t="inlineStr"><is><t xml:space="preserve">' . monitoring_xlsx_xml_escape($value) . '</t></is></c>';
        }

        $sheetRowsXml .= '<row r="' . $excelRow . '" ht="' . $rowHeight . '" customHeight="1">' . $rowCells . '</row>';
    }

    $colsXml = '';
    foreach ($columns as $index => $column) {
        $colsXml .= '<col min="' . ($index + 1) . '" max="' . ($index + 1) . '" width="' . number_format($column['width'], 2, '.', '') . '" customWidth="1"/>';
    }

    $worksheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<dimension ref="A1:H' . max(1, count($exportRows) + 1) . '"/>'
        . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="18"/>'
        . '<cols>' . $colsXml . '</cols>'
        . '<sheetData>' . $sheetRowsXml . '</sheetData>';

    if ($drawingAnchors) {
        $worksheetXml .= '<drawing r:id="rId1"/>';
    }

    $worksheetXml .= '</worksheet>';

    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2">'
        . '<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><b/><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
        . '</fonts>'
        . '<fills count="2">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFEAF1FF"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="6">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="1" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . ($drawingAnchors ? '<Default Extension="png" ContentType="image/png"/>' : '')
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . ($drawingAnchors ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '')
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>',
        'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            . '<Application>PHP Native Export</Application>'
            . '<TitlesOfParts><vt:vector size="1" baseType="lpstr"><vt:lpstr>Monitoring</vt:lpstr></vt:vector></TitlesOfParts>'
            . '</Properties>',
        'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . monitoring_xlsx_xml_escape($title) . '</dc:title>'
            . '<dc:creator>' . monitoring_xlsx_xml_escape(APP_NAME) . '</dc:creator>'
            . '<cp:lastModifiedBy>' . monitoring_xlsx_xml_escape(APP_NAME) . '</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:modified>'
            . '</cp:coreProperties>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Monitoring" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        'xl/styles.xml' => $stylesXml,
        'xl/worksheets/sheet1.xml' => $worksheetXml,
    ];

    if ($drawingAnchors) {
        $files['xl/worksheets/_rels/sheet1.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
            . '</Relationships>';
        $files['xl/drawings/drawing1.xml'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . implode('', $drawingAnchors)
            . '</xdr:wsDr>';
        $files['xl/drawings/_rels/drawing1.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . implode('', $drawingRels)
            . '</Relationships>';

        foreach ($imageFiles as $path => $binary) {
            $files[$path] = $binary;
        }
    }

    $xlsxBinary = monitoring_zip_pack($files);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($xlsxBinary));
    echo $xlsxBinary;
    exit;
}

function monitoring_output_pdf(string $filename, string $title, array $rows): void
{
    $exportRows = monitoring_export_rows($rows);
    $columns = [
        ['key' => 'no', 'label' => 'No', 'width' => 28, 'align' => 'center'],
        ['key' => 'tanggal', 'label' => 'Tanggal', 'width' => 62, 'align' => 'center'],
        ['key' => 'ruangan', 'label' => 'Ruangan', 'width' => 170, 'align' => 'left'],
        ['key' => 'suhu', 'label' => 'Suhu', 'width' => 62, 'align' => 'center'],
        ['key' => 'akses_masuk', 'label' => 'Akses Masuk', 'width' => 96, 'align' => 'left'],
        ['key' => 'petugas', 'label' => 'Petugas', 'width' => 145, 'align' => 'left'],
        ['key' => 'status', 'label' => 'Status', 'width' => 72, 'align' => 'center'],
        ['key' => 'paraf_text', 'label' => 'Paraf / Sign', 'width' => 137, 'align' => 'center'],
    ];

    $pageWidth = 842;
    $pageHeight = 595;
    $marginX = 24;
    $marginTop = 24;
    $marginBottom = 24;
    $tableTop = $pageHeight - 86;
    $headerHeight = 24;
    $contentFont = 8.4;
    $contentLeading = 9.6;
    $titleFont = 14;
    $metaFont = 8.6;

    $format = static function (float $number): string {
        $formatted = number_format($number, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        return $formatted === '-0' ? '0' : $formatted;
    };

    $encodePdfText = static function (string $text): string {
        $text = str_replace('Â°C', '°C', $text);
        $converted = function_exists('iconv') ? iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text) : $text;
        if ($converted === false) {
            $converted = preg_replace('/[^\x20-\x7E]/', '?', $text) ?? '';
        }

        $escaped = '';
        $length = strlen($converted);
        for ($index = 0; $index < $length; $index++) {
            $char = ord($converted[$index]);

            if ($char === 92 || $char === 40 || $char === 41) {
                $escaped .= '\\' . chr($char);
            } elseif ($char < 32 || $char > 126) {
                $escaped .= sprintf('\\%03o', $char);
            } else {
                $escaped .= chr($char);
            }
        }

        return $escaped;
    };

    $estimateWidth = static function (string $text, float $fontSize): float {
        $text = str_replace(["\r", "\n"], '', $text);
        return max(0.0, strlen($text) * $fontSize * 0.48);
    };

    $wrapForWidth = static function (string $text, float $maxWidth, float $fontSize) use ($estimateWidth): array {
        $paragraphs = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];
        $lines = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                $lines[] = '-';
                continue;
            }

            $words = preg_split('/\s+/', $paragraph) ?: [];
            $current = '';

            foreach ($words as $word) {
                $candidate = $current === '' ? $word : $current . ' ' . $word;
                if ($estimateWidth($candidate, $fontSize) <= $maxWidth || $current === '') {
                    $current = $candidate;
                    continue;
                }

                $lines[] = $current;
                $current = $word;
            }

            if ($current !== '') {
                $lines[] = $current;
            }
        }

        return $lines ?: ['-'];
    };

    $textCommand = static function (string $font, float $size, float $x, float $y, string $text) use ($encodePdfText, $format): string {
        return "0 g\nBT\n/" . $font . ' ' . $format($size) . " Tf\n" . $format($x) . ' ' . $format($y) . " Td\n(" . $encodePdfText($text) . ") Tj\nET\n";
    };

    $drawHeader = static function () use (&$currentStream, $columns, $pageHeight, $marginX, $tableTop, $headerHeight, $format, $textCommand): void {
        $x = $marginX;
        $bottom = $tableTop - $headerHeight;

        foreach ($columns as $column) {
            $currentStream .= "0.94 g\n" . $format($x) . ' ' . $format($bottom) . ' ' . $format($column['width']) . ' ' . $format($headerHeight) . " re f\n";
            $currentStream .= "0 G\n" . $format($x) . ' ' . $format($bottom) . ' ' . $format($column['width']) . ' ' . $format($headerHeight) . " re S\n";
            $labelX = $x + 4;
            $labelWidth = $column['width'] - 8;
            $label = $column['label'];
            $labelTextWidth = strlen($label) * 5.1;
            if ($column['align'] === 'center') {
                $labelX = $x + max(4, ($labelWidth - $labelTextWidth) / 2);
            }
            $currentStream .= $textCommand('F2', 8.5, $labelX, $bottom + 8, $label);
            $x += $column['width'];
        }
    };

    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = null;
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

    $nextObject = 5;
    $signatureObjects = [];

    foreach ($rows as $index => $row) {
        $signature = monitoring_png_to_pdf_image((string) ($row['signature_base64'] ?? ''));
        if (!$signature) {
            continue;
        }

        $alphaObject = null;
        if ($signature['alpha_stream'] !== null) {
            $alphaObject = $nextObject++;
            $objects[$alphaObject] =
                '<< /Type /XObject /Subtype /Image /Width ' . $signature['width'] .
                ' /Height ' . $signature['height'] .
                ' /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length ' . strlen($signature['alpha_stream']) . " >>\nstream\n" .
                $signature['alpha_stream'] . "\nendstream";
        }

        $imageObject = $nextObject++;
        $dictionary = '<< /Type /XObject /Subtype /Image /Width ' . $signature['width'] .
            ' /Height ' . $signature['height'] .
            ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode';
        if ($alphaObject !== null) {
            $dictionary .= ' /SMask ' . $alphaObject . ' 0 R';
        }
        $dictionary .= ' /Length ' . strlen($signature['rgb_stream']) . " >>\nstream\n" . $signature['rgb_stream'] . "\nendstream";
        $objects[$imageObject] = $dictionary;

        $signatureObjects[$index] = [
            'name' => 'SIG' . ($index + 1),
            'object_id' => $imageObject,
            'width' => $signature['width'],
            'height' => $signature['height'],
        ];
    }

    $pageReferences = [];
    $currentStream = '';
    $currentY = $tableTop;
    $currentXObjects = [];

    $startPage = static function () use (&$currentStream, &$currentY, &$currentXObjects, $title, $exportRows, $marginX, $pageHeight, $tableTop, $titleFont, $metaFont, $textCommand, $drawHeader): void {
        $currentStream = '';
        $currentXObjects = [];
        $currentStream .= $textCommand('F2', $titleFont, $marginX, $pageHeight - 32, $title);
        $currentStream .= $textCommand('F1', $metaFont, $marginX, $pageHeight - 47, 'Tanggal Export: ' . date('d/m/Y H:i') . ' | Total Data: ' . count($exportRows));
        $currentStream .= $textCommand('F1', 8.0, $marginX, $pageHeight - 59, 'Kolom export: No, Tanggal, Ruangan, Suhu, Akses Masuk, Petugas, Status, Paraf / Sign');
        $drawHeader();
        $currentY = $tableTop - 24;
    };

    $finalizePage = static function () use (&$objects, &$nextObject, &$pageReferences, &$currentStream, &$currentXObjects): void {
        $contentObject = $nextObject++;
        $pageObject = $nextObject++;
        $pageReferences[] = $pageObject;

        $objects[$contentObject] = '<< /Length ' . strlen($currentStream) . " >>\nstream\n" . $currentStream . "\nendstream";

        $resources = '<< /Font << /F1 3 0 R /F2 4 0 R >>';
        if ($currentXObjects) {
            $xObjects = [];
            foreach ($currentXObjects as $name => $objectId) {
                $xObjects[] = '/' . $name . ' ' . $objectId . ' 0 R';
            }
            $resources .= ' /XObject << ' . implode(' ', $xObjects) . ' >>';
        }
        $resources .= ' >>';

        $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources ' . $resources . ' /Contents ' . $contentObject . ' 0 R >>';
    };

    $startPage();

    if (!$exportRows) {
        $currentStream .= $textCommand('F1', 10.5, $marginX, $currentY - 18, 'Tidak ada data monitoring pada filter yang dipilih.');
        $finalizePage();
    } else {
        foreach ($exportRows as $index => $exportRow) {
            $signatureMeta = $signatureObjects[$index] ?? null;
            $cellLines = [];
            $maxLines = 1;

            foreach ($columns as $column) {
                if ($column['key'] === 'paraf_text') {
                    $cellLines[$column['key']] = [];
                    if ($signatureMeta !== null) {
                        continue;
                    }
                }

                $wrapped = $wrapForWidth((string) ($exportRow[$column['key']] ?? '-'), $column['width'] - 8, $contentFont);
                $cellLines[$column['key']] = $wrapped;
                $maxLines = max($maxLines, count($wrapped));
            }

            $rowHeight = max(24, ($maxLines * $contentLeading) + 8);
            if ($signatureMeta !== null) {
                $rowHeight = max($rowHeight, 42);
            }

            if (($currentY - $rowHeight) < $marginBottom) {
                $finalizePage();
                $startPage();
            }

            $x = $marginX;
            $rowBottom = $currentY - $rowHeight;

            foreach ($columns as $column) {
                $currentStream .= "0 G\n" . $format($x) . ' ' . $format($rowBottom) . ' ' . $format($column['width']) . ' ' . $format($rowHeight) . " re S\n";

                if ($column['key'] === 'paraf_text' && $signatureMeta !== null) {
                    $targetWidth = min($column['width'] - 10, 108.0);
                    $ratio = $signatureMeta['height'] / max($signatureMeta['width'], 1);
                    $targetHeight = $targetWidth * $ratio;
                    $maxHeight = $rowHeight - 10;

                    if ($targetHeight > $maxHeight) {
                        $targetHeight = $maxHeight;
                        $targetWidth = $targetHeight / max($ratio, 0.001);
                    }

                    $imageX = $x + (($column['width'] - $targetWidth) / 2);
                    $imageY = $rowBottom + (($rowHeight - $targetHeight) / 2);
                    $currentStream .= "q\n";
                    $currentStream .= $format($targetWidth) . ' 0 0 ' . $format($targetHeight) . ' ' . $format($imageX) . ' ' . $format($imageY) . " cm\n";
                    $currentStream .= '/' . $signatureMeta['name'] . " Do\n";
                    $currentStream .= "Q\n";
                    $currentXObjects[$signatureMeta['name']] = $signatureMeta['object_id'];
                } else {
                    $baseline = $currentY - 12;
                    foreach ($cellLines[$column['key']] as $line) {
                        $textX = $x + 4;
                        if ($column['align'] === 'center') {
                            $lineWidth = $estimateWidth($line, $contentFont);
                            $textX = $x + max(4, (($column['width'] - 8) - $lineWidth) / 2);
                        }

                        $currentStream .= $textCommand('F1', $contentFont, $textX, $baseline, $line);
                        $baseline -= $contentLeading;
                    }
                }

                $x += $column['width'];
            }

            $currentY = $rowBottom;
        }

        $finalizePage();
    }

    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn (int $pageObject): string => $pageObject . ' 0 R', $pageReferences)) . '] /Count ' . count($pageReferences) . ' >>';

    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $number => $content) {
        $offsets[$number] = strlen($pdf);
        $pdf .= $number . " 0 obj\n" . $content . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
    }

    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $pdf;
    exit;
}
