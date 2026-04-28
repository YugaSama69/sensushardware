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

function generate_barang_code(PDO $pdo): string
{
    $nextId = (int) fetch_scalar($pdo, 'SELECT COALESCE(MAX(id), 0) + 1 FROM barang');
    return 'BRG-' . str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
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
        'ram' => trim($input['ram'] ?? ''),
        'storage' => trim($input['storage'] ?? ''),
        'os_name' => trim($input['os_name'] ?? ''),
        'tahun_inventaris' => trim($input['tahun_inventaris'] ?? ''),
        'ruangan' => trim($input['ruangan'] ?? ''),
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

    return fetch_all($pdo, $sql, $params);
}

function get_computer_client_filter_options(PDO $pdo): array
{
    $fetchValues = static function (array $rows): array {
        return array_map(static fn (array $row): string => (string) ($row['value'] ?? ''), $rows);
    };

    return [
        'merk' => $fetchValues(fetch_all($pdo, 'SELECT DISTINCT merk AS value FROM komputer_client WHERE merk <> "" ORDER BY merk ASC')),
        'processor' => $fetchValues(fetch_all($pdo, 'SELECT DISTINCT processor AS value FROM komputer_client WHERE processor <> "" ORDER BY processor ASC')),
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
