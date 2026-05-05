<?php

declare(strict_types=1);

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return BASE_URL . ($path !== '' ? '/' . $path : '');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf_token'] ?? '';

    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Token CSRF tidak valid.');
    }
}

function format_date_id(?string $date): string
{
    if (!$date) {
        return '-';
    }

    return date('d M Y', strtotime($date));
}

function format_time_id(?string $time): string
{
    if (!$time) {
        return '-';
    }

    return date('H:i', strtotime($time));
}

function format_month_year_id(?string $value): string
{
    if (!$value) {
        return '-';
    }

    $timestamp = strtotime(strlen($value) === 7 ? $value . '-01' : $value);
    if ($timestamp === false) {
        return (string) $value;
    }

    $months = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    $monthNumber = (int) date('n', $timestamp);
    return ($months[$monthNumber] ?? date('F', $timestamp)) . ' ' . date('Y', $timestamp);
}

function day_name_id(?string $date): string
{
    if (!$date) {
        return '-';
    }

    $days = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
    ];

    $english = date('l', strtotime($date));
    return $days[$english] ?? $english;
}

function format_number(int|float|string $value): string
{
    return number_format((float) $value, 0, ',', '.');
}

function format_percentage(int|float|string|null $value): string
{
    $normalized = str_replace(',', '.', str_replace('%', '', (string) ($value ?? '0')));
    $formatted = rtrim(rtrim(number_format((float) $normalized, 2, '.', ''), '0'), '.');
    return $formatted . '%';
}

function normalize_ram_group_label(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '' || $value === '-') {
        return null;
    }

    $normalized = str_replace(',', '.', $value);
    if (preg_match('/(\d+(?:\.\d+)?)/', $normalized, $matches) !== 1) {
        return null;
    }

    $ramNumber = rtrim(rtrim(number_format((float) $matches[1], 2, '.', ''), '0'), '.');
    return $ramNumber . ' GB';
}

function normalize_os_group_label(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '' || $value === '-') {
        return null;
    }

    $normalizedOs = strtolower($value);
    if (str_contains($normalizedOs, 'windows 11')) {
        return 'Windows 11';
    }
    if (str_contains($normalizedOs, 'windows 10')) {
        return 'Windows 10';
    }
    if (str_contains($normalizedOs, 'windows 8')) {
        return 'Windows 8';
    }
    if (str_contains($normalizedOs, 'windows 7')) {
        return 'Windows 7';
    }
    if (str_contains($normalizedOs, 'windows')) {
        return 'Windows Lainnya';
    }
    if (str_contains($normalizedOs, 'ubuntu')) {
        return 'Ubuntu';
    }
    if (str_contains($normalizedOs, 'linux')) {
        return 'Linux';
    }

    return $value;
}

function condition_badge(string $condition): string
{
    return match ($condition) {
        'Baik' => 'success',
        'Rusak' => 'danger',
        default => 'warning',
    };
}

function transaction_badge(string $type): string
{
    return $type === 'masuk' ? 'success' : 'danger';
}

function current_path(): string
{
    return str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
}

function is_active_menu(string $needle): bool
{
    return str_contains(current_path(), $needle);
}

function fetch_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
}

function fetch_all(PDO $pdo, string $sql, array $params = []): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function fetch_one(PDO $pdo, string $sql, array $params = []): ?array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $result = $statement->fetch();
    return $result ?: null;
}

function barang_code_prefix_from_name(string $name): string
{
    $normalized = strtoupper(preg_replace('/[^A-Z0-9]+/i', ' ', trim($name)) ?? '');
    $tokens = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $stopwords = ['DAN', 'TO', 'FOR', 'WITH', 'THE', 'OF', 'A', 'AN', 'DI', 'KE', 'DARI'];
    $tokens = array_values(array_filter($tokens, static fn (string $token): bool => !in_array($token, $stopwords, true)));

    if (!$tokens) {
        return 'BRG';
    }

    $prefix = '';
    foreach ($tokens as $token) {
        if (strlen($prefix) >= 8) {
            break;
        }

        if (preg_match('/^\d+$/', $token) === 1) {
            $segment = substr($token, 0, 2);
        } elseif (strlen($token) <= 4) {
            $segment = $token;
        } else {
            $segment = substr($token, 0, 3);
        }

        $prefix .= $segment;
    }

    $prefix = substr($prefix, 0, 8);

    if (strlen($prefix) < 3) {
        $prefix = str_pad($prefix, 3, 'X');
    }

    return $prefix;
}

function generate_barang_code_from_name(PDO $pdo, string $name, ?int $excludeId = null): string
{
    $prefix = barang_code_prefix_from_name($name);
    $params = ['prefix' => $prefix . '-%'];
    $sql = 'SELECT kode_barang FROM barang WHERE kode_barang LIKE :prefix';

    if ($excludeId !== null) {
        $sql .= ' AND id <> :exclude_id';
        $params['exclude_id'] = $excludeId;
    }

    $rows = fetch_all($pdo, $sql, $params);
    $maxSequence = 0;

    foreach ($rows as $row) {
        if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', (string) ($row['kode_barang'] ?? ''), $matches) === 1) {
            $maxSequence = max($maxSequence, (int) $matches[1]);
        }
    }

    return $prefix . '-' . str_pad((string) ($maxSequence + 1), 3, '0', STR_PAD_LEFT);
}

function normalize_hex_color(?string $value, string $default = '#64748B'): string
{
    $value = strtoupper(trim((string) $value));
    if (preg_match('/^#?[0-9A-F]{6}$/', $value) !== 1) {
        return $default;
    }

    return '#' . ltrim($value, '#');
}

function get_contrast_text_color(string $backgroundHex): string
{
    $hex = ltrim(normalize_hex_color($backgroundHex), '#');
    $red = hexdec(substr($hex, 0, 2));
    $green = hexdec(substr($hex, 2, 2));
    $blue = hexdec(substr($hex, 4, 2));
    $brightness = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

    return $brightness >= 160 ? '#0F172A' : '#FFFFFF';
}

function get_master_barang_labels(PDO $pdo): array
{
    return fetch_all(
        $pdo,
        'SELECT id, nama_label, warna_label
         FROM master_label_barang
         ORDER BY nama_label ASC'
    );
}

function get_barang_label_map(PDO $pdo): array
{
    $map = [];

    foreach (get_master_barang_labels($pdo) as $row) {
        $labelName = trim((string) ($row['nama_label'] ?? ''));
        if ($labelName === '') {
            continue;
        }

        $map[$labelName] = normalize_hex_color($row['warna_label'] ?? '#64748B');
    }

    return $map;
}

function get_barang_label_options(PDO $pdo): array
{
    $labels = array_map(
        static fn (array $row): string => (string) ($row['nama_label'] ?? ''),
        get_master_barang_labels($pdo)
    );

    $rows = fetch_all(
        $pdo,
        'SELECT DISTINCT label_barang
         FROM barang
         WHERE label_barang IS NOT NULL AND TRIM(label_barang) <> ""
         ORDER BY label_barang ASC'
    );

    foreach ($rows as $row) {
        $label = trim((string) ($row['label_barang'] ?? ''));
        if ($label !== '' && !in_array($label, $labels, true)) {
            $labels[] = $label;
        }
    }

    if (!$labels) {
        $labels[] = 'Lainnya';
    }

    sort($labels, SORT_NATURAL | SORT_FLAG_CASE);

    return $labels;
}

function get_low_stock_items(PDO $pdo, int $limit = 5): array
{
    return fetch_all(
        $pdo,
        'SELECT b.nama_barang, b.qty
         FROM barang b
         WHERE b.qty < 5
         ORDER BY b.qty ASC, b.nama_barang ASC
         LIMIT ' . (int) $limit
    );
}

function normalize_report_filters(array $input): array
{
    return [
        'tanggal' => trim($input['tanggal'] ?? ''),
        'bulan' => trim($input['bulan'] ?? ''),
        'tahun' => trim($input['tahun'] ?? ''),
        'ruangan_id' => trim($input['ruangan_id'] ?? ''),
        'barang_id' => trim($input['barang_id'] ?? ''),
        'kondisi' => trim($input['kondisi'] ?? ''),
        'tipe_transaksi' => trim($input['tipe_transaksi'] ?? ''),
    ];
}

function normalize_computer_client_filters(array $input): array
{
    return [
        'merk' => trim($input['merk'] ?? ''),
        'processor' => trim($input['processor'] ?? ''),
        'kondisi' => trim($input['kondisi'] ?? ''),
        'ram' => trim($input['ram'] ?? ''),
        'ram_group' => trim($input['ram_group'] ?? ''),
        'storage' => trim($input['storage'] ?? ''),
        'storage_mode' => trim($input['storage_mode'] ?? ''),
        'os_name' => trim($input['os_name'] ?? ''),
        'os_group' => trim($input['os_group'] ?? ''),
        'tahun_inventaris' => trim($input['tahun_inventaris'] ?? ''),
        'ruangan' => trim($input['ruangan'] ?? ''),
    ];
}

function normalize_pengembangan_filters(array $input): array
{
    return [
        'bulan_tahun' => trim($input['bulan_tahun'] ?? ''),
        'input_user' => trim($input['input_user'] ?? ''),
    ];
}

function get_computer_client_rows(PDO $pdo, array $filters): array
{
    $sql = 'SELECT * FROM komputer_client WHERE 1=1';
    $params = [];

    if ($filters['merk'] !== '') {
        $sql .= ' AND merk = :merk';
        $params['merk'] = $filters['merk'];
    }

    if ($filters['processor'] !== '') {
        $sql .= ' AND processor = :processor';
        $params['processor'] = $filters['processor'];
    }

    if ($filters['kondisi'] !== '') {
        $sql .= ' AND kondisi = :kondisi';
        $params['kondisi'] = $filters['kondisi'];
    }

    if ($filters['ram'] !== '') {
        $sql .= ' AND ram = :ram';
        $params['ram'] = $filters['ram'];
    }

    if ($filters['storage'] !== '') {
        $sql .= ' AND (ssd = :storage_ssd OR hdd = :storage_hdd)';
        $params['storage_ssd'] = $filters['storage'];
        $params['storage_hdd'] = $filters['storage'];
    }

    if ($filters['os_name'] !== '') {
        $sql .= ' AND os_name = :os_name';
        $params['os_name'] = $filters['os_name'];
    }

    if ($filters['tahun_inventaris'] !== '') {
        $sql .= ' AND tahun_inventaris = :tahun_inventaris';
        $params['tahun_inventaris'] = $filters['tahun_inventaris'];
    }

    if ($filters['ruangan'] !== '') {
        $sql .= ' AND ruangan = :ruangan';
        $params['ruangan'] = $filters['ruangan'];
    }

    $sql .= ' ORDER BY tanggal DESC, jam DESC, id DESC';

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

function get_computer_client_filter_options(PDO $pdo): array
{
    $fetchValues = static function (array $rows): array {
        return array_map(static fn (array $row): string => (string) ($row['value'] ?? ''), $rows);
    };

    return [
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
    ];
}

function get_room_name_options(PDO $pdo): array
{
    $rows = fetch_all(
        $pdo,
        'SELECT value
         FROM (
            SELECT DISTINCT nama_ruangan AS value FROM ruangan WHERE nama_ruangan <> ""
            UNION
            SELECT DISTINCT ruangan AS value FROM komputer_client WHERE ruangan <> ""
         ) room_values
         ORDER BY value ASC'
    );

    return array_map(static fn (array $row): string => (string) ($row['value'] ?? ''), $rows);
}

function get_pengembangan_rows(PDO $pdo, array $filters): array
{
    $sql = 'SELECT * FROM laporan_pengembangan_aplikasi WHERE 1=1';
    $params = [];

    if ($filters['bulan_tahun'] !== '') {
        $sql .= ' AND bulan_tahun = :bulan_tahun';
        $params['bulan_tahun'] = $filters['bulan_tahun'] . '-01';
    }

    if ($filters['input_user'] !== '') {
        $sql .= ' AND input_user = :input_user';
        $params['input_user'] = $filters['input_user'];
    }

    $sql .= ' ORDER BY bulan_tahun DESC, id DESC';

    return fetch_all($pdo, $sql, $params);
}

function get_pengembangan_filter_options(PDO $pdo): array
{
    return [
        'input_user' => array_map(
            static fn (array $row): string => (string) ($row['value'] ?? ''),
            fetch_all($pdo, 'SELECT DISTINCT input_user AS value FROM laporan_pengembangan_aplikasi WHERE input_user <> "" ORDER BY input_user ASC')
        ),
    ];
}

function get_report_rows(PDO $pdo, array $filters): array
{
    $sql = 'SELECT h.*, b.kode_barang, b.nama_barang, b.kondisi, COALESCE(h.ruangan_nama, r.nama_ruangan) AS nama_ruangan_transaksi
            FROM histori_barang h
            INNER JOIN barang b ON b.id = h.barang_id
            LEFT JOIN ruangan r ON r.id = h.ruangan_id
            WHERE 1=1';

    $params = [];

    if ($filters['tanggal'] !== '') {
        $sql .= ' AND h.tanggal = :tanggal';
        $params['tanggal'] = $filters['tanggal'];
    }

    if ($filters['bulan'] !== '') {
        $sql .= ' AND MONTH(h.tanggal) = :bulan';
        $params['bulan'] = (int) $filters['bulan'];
    }

    if ($filters['tahun'] !== '') {
        $sql .= ' AND YEAR(h.tanggal) = :tahun';
        $params['tahun'] = (int) $filters['tahun'];
    }

    if ($filters['ruangan_id'] !== '') {
        $sql .= ' AND h.ruangan_id = :ruangan_id';
        $params['ruangan_id'] = (int) $filters['ruangan_id'];
    }

    if ($filters['barang_id'] !== '') {
        $sql .= ' AND b.id = :barang_id';
        $params['barang_id'] = (int) $filters['barang_id'];
    }

    if ($filters['kondisi'] !== '') {
        $sql .= ' AND b.kondisi = :kondisi';
        $params['kondisi'] = $filters['kondisi'];
    }

    if ($filters['tipe_transaksi'] !== '') {
        $sql .= ' AND h.tipe_transaksi = :tipe_transaksi';
        $params['tipe_transaksi'] = $filters['tipe_transaksi'];
    }

    $sql .= ' ORDER BY h.tanggal DESC, h.jam DESC, h.id DESC';

    $statement = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $statement->bindValue(':' . $key, $value);
    }
    $statement->execute();

    return $statement->fetchAll();
}

function build_query_string(array $filters): string
{
    return http_build_query(array_filter($filters, static fn ($value) => $value !== ''));
}

function create_pdf_content(array $lines): string
{
    $escaped = array_map(static function (string $line): string {
        $line = str_replace('\\', '\\\\', $line);
        $line = str_replace('(', '\\(', $line);
        $line = str_replace(')', '\\)', $line);
        return $line;
    }, $lines);

    $content = "BT\n/F1 10 Tf\n40 800 Td\n14 TL\n";
    foreach ($escaped as $line) {
        $content .= '(' . $line . ") Tj\nT*\n";
    }
    $content .= "ET";

    return $content;
}

function output_simple_pdf(string $filename, string $title, array $lines): void
{
    $chunks = array_chunk($lines, 45);
    $objects = [];

    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = null;
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';

    $pageObjectNumbers = [];
    $nextObject = 4;

    foreach ($chunks as $chunk) {
        $pageObject = $nextObject++;
        $contentObject = $nextObject++;

        $pageObjectNumbers[] = $pageObject;

        $pageLines = array_merge([$title, str_repeat('=', 90), ''], $chunk);
        $stream = create_pdf_content($pageLines);

        $objects[$contentObject] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObject . ' 0 R >>';
    }

    $kids = implode(' ', array_map(static fn ($number) => $number . ' 0 R', $pageObjectNumbers));
    $objects[2] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($pageObjectNumbers) . ' >>';

    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [0];

    foreach ($objects as $number => $content) {
        $offsets[$number] = strlen($pdf);
        $pdf .= $number . " 0 obj\n" . $content . "\nendobj\n";
    }

    $xrefPosition = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";

    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
    }

    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefPosition . "\n%%EOF";

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $pdf;
    exit;
}
