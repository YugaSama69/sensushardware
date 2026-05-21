<?php

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once __DIR__ . '/module.php';

require_login();
require_module_access();

$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {
    $row = monitoring_find_row($pdo, $id);
    $rows = $row ? [$row] : [];
    monitoring_output_pdf(
        'monitoring-ruangan-server-detail.pdf',
        'Detail Monitoring Ruangan Server',
        $rows
    );
    return;
}

$filters = monitoring_normalize_filters($_GET);
$rows = monitoring_get_rows($pdo, $filters);

monitoring_output_pdf(
    'monitoring-ruangan-server.pdf',
    'Histori Monitoring Ruangan Server',
    $rows
);
