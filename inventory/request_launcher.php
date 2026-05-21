<?php

require_once __DIR__ . '/../config/app.php';
require_once BASE_PATH . '/pendataan/module.php';

if (!is_post()) {
    device_inventory_json_response(405, [
        'success' => false,
        'message' => 'Method tidak diizinkan.',
    ]);
}

device_inventory_require_secure_transport_json();
verify_csrf();
device_inventory_ensure_runtime_schema($pdo);

$payload = device_inventory_registration_payload($_POST);
$errors = [];
device_inventory_validate_registration_payload($payload, $errors);

if ($errors) {
    device_inventory_log_event('invalid_request', [
        'errors' => $errors,
        'device_type' => $payload['device_type'] ?? 'CLIENT',
    ]);

    device_inventory_json_response(422, [
        'success' => false,
        'message' => implode(' ', $errors),
    ]);
}

try {
    $token = device_inventory_register_token($pdo, $payload);

    device_inventory_json_response(200, [
        'success' => true,
        'message' => 'Launcher berhasil dibuat. Download akan dimulai.',
        'download_url' => device_inventory_download_url($token),
        'expires_in_minutes' => 10,
    ]);
} catch (Throwable $throwable) {
    device_inventory_log_event('generate_launcher_failed', [
        'message' => $throwable->getMessage(),
        'device_type' => $payload['device_type'] ?? 'CLIENT',
    ]);

    device_inventory_json_response(500, [
        'success' => false,
        'message' => 'Launcher belum berhasil dibuat.',
    ]);
}
