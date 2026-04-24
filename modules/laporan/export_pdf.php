<?php

require_once dirname(__DIR__, 2) . '/config/app.php';

require_login();

$filters = normalize_report_filters($_GET);
$rows = get_report_rows($pdo, $filters);
$filterLabel = build_query_string($filters);

$lines = [];
$lines[] = 'Tanggal Export: ' . date('d-m-Y H:i');
$lines[] = 'Filter: ' . ($filterLabel !== '' ? urldecode($filterLabel) : 'Semua data');
$lines[] = str_repeat('-', 90);

foreach ($rows as $index => $row) {
    $lines[] = sprintf(
        '%03d | %s | %s | %s | Qty:%s | %s->%s | %s | %s | %s %s',
        $index + 1,
        substr($row['nama_barang'], 0, 18),
        substr($row['nama_ruangan'] ?: '-', 0, 12),
        strtoupper($row['tipe_transaksi']),
        $row['qty'],
        $row['stok_sebelum'],
        $row['stok_sesudah'],
        substr($row['nama_pengguna'], 0, 12),
        day_name_id($row['tanggal']),
        $row['tanggal'],
        substr($row['jam'], 0, 5)
    );
    if (!empty($row['tujuan']) && $row['tujuan'] !== '-') {
        $lines[] = '    Tujuan: ' . substr($row['tujuan'], 0, 70);
    }
    if (!empty($row['keterangan'])) {
        $lines[] = '    Ket: ' . substr($row['keterangan'], 0, 72);
    }
}

if (!$rows) {
    $lines[] = 'Tidak ada data pada filter yang dipilih.';
}

output_simple_pdf('laporan-sensus-hardware.pdf', APP_NAME, $lines);
