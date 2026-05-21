<?php

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once BASE_PATH . '/pendataan/module.php';

device_inventory_require_secure_transport_download();
device_inventory_ensure_runtime_schema($pdo);

$token = device_inventory_extract_download_token();
$payload = device_inventory_consume_token($pdo, $token);

if (!$payload) {
    http_response_code(404);
    exit('Token launcher tidak valid, sudah dipakai, atau sudah kedaluwarsa.');
}

$deviceType = strtoupper((string) ($payload['device_type'] ?? 'CLIENT'));
$filename = $deviceType === 'SERVER' ? 'pendataan-server-rs.bat' : 'pendataan-komputer-rs.bat';
$payload['upload_token'] = device_inventory_register_upload_token($pdo, $payload);
$bat = device_inventory_generate_bat($payload);

header('Content-Type: application/octet-stream');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $bat;
