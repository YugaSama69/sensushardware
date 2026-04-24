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
        'SELECT b.nama_barang, b.qty, r.nama_ruangan
         FROM barang b
         LEFT JOIN ruangan r ON r.id = b.ruangan_id
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
        'tipe_transaksi' => trim($input['tipe_transaksi'] ?? ''),
    ];
}

function get_report_rows(PDO $pdo, array $filters): array
{
    $sql = 'SELECT h.*, b.kode_barang, b.nama_barang, r.nama_ruangan
            FROM histori_barang h
            INNER JOIN barang b ON b.id = h.barang_id
            LEFT JOIN ruangan r ON r.id = b.ruangan_id
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
        $sql .= ' AND r.id = :ruangan_id';
        $params['ruangan_id'] = (int) $filters['ruangan_id'];
    }

    if ($filters['barang_id'] !== '') {
        $sql .= ' AND b.id = :barang_id';
        $params['barang_id'] = (int) $filters['barang_id'];
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
