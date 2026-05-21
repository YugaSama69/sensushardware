<?php

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once __DIR__ . '/module.php';

require_login();
require_module_access();

$filter = dashboard_normalize_device_filter($_GET);
$stats = dashboard_device_stats($pdo, $filter, false);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'stats' => $stats,
]);
