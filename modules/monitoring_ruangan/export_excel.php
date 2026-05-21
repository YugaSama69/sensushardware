<?php

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once __DIR__ . '/module.php';

require_login();
require_module_access();

$filters = monitoring_normalize_filters($_GET);
$rows = monitoring_get_rows($pdo, $filters);

monitoring_output_xlsx(
    'monitoring-ruangan-server.xlsx',
    'Monitoring Ruangan Server',
    $rows
);
