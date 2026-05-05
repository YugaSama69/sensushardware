<?php

require_once dirname(__DIR__, 2) . '/config/app.php';

require_login();
require_module_access();

$filters = normalize_pengembangan_filters($_GET);
$rows = get_pengembangan_rows($pdo, $filters);
$filterLabel = build_query_string($filters);

$lines = [];
$lines[] = 'Tanggal Export: ' . date('d-m-Y H:i');
$lines[] = 'Filter: ' . ($filterLabel !== '' ? urldecode($filterLabel) : 'Semua data');
$lines[] = str_repeat('-', 90);

foreach ($rows as $index => $row) {
    $lines[] = sprintf(
        '%03d | %s | %s | %s | %s | %s',
        $index + 1,
        substr(format_month_year_id($row['bulan_tahun']), 0, 18),
        substr($row['input_user'] ?: '-', 0, 12),
        substr($row['bidang_unit'], 0, 20),
        format_percentage($row['capaian']),
        substr($row['nama_kegiatan'], 0, 24)
    );
}

if (!$rows) {
    $lines[] = 'Tidak ada data pada filter yang dipilih.';
}

output_simple_pdf(
    'laporan-pengembangan-aplikasi.pdf',
    'Laporan Pembangunan dan Pengembangan Aplikasi Rumah Sakit Welas Asih',
    $lines
);
