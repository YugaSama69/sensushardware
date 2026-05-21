<?php

require_once __DIR__ . '/../config/app.php';
require_once BASE_PATH . '/pendataan/module.php';

header('Content-Type: application/json; charset=utf-8');
device_inventory_ensure_runtime_schema($pdo);

if (!is_post()) {
    device_inventory_json_response(405, [
        'success' => false,
        'message' => 'Method tidak diizinkan.',
    ]);
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
    $payload = $_POST;
}

if (!isset($payload['petugas']) && isset($payload['nama_user'])) {
    $payload['petugas'] = $payload['nama_user'];
}

$data = device_inventory_normalize_upload_payload($payload);
$errors = [];
device_inventory_validate_upload_payload($data, $errors);

if ($errors) {
    device_inventory_log_event('invalid_payload', [
        'endpoint' => 'api/komputer_client.php',
        'errors' => $errors,
        'device_type' => $data['device_type'] ?? 'CLIENT',
    ]);

    device_inventory_json_response(422, [
        'success' => false,
        'message' => implode(' ', $errors),
    ]);
}

try {
    $pdo->beginTransaction();
    $result = device_inventory_persist_device($pdo, $data);
    $pdo->commit();

    device_inventory_log_event('upload_success', [
        'endpoint' => 'api/komputer_client.php',
        'hostname' => $data['hostname'],
        'device_type' => $data['device_type'],
        'ruangan' => $data['ruangan'],
    ]);

    device_inventory_json_response(200, [
        'success' => true,
        'message' => 'Data berhasil dikirim.',
        'hostname' => $data['hostname'],
        'device_type' => $data['device_type'],
        'tanggal' => $result['tanggal'],
        'jam' => $result['jam'],
    ]);
} catch (Throwable $throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    device_inventory_log_event('upload_failed', [
        'endpoint' => 'api/komputer_client.php',
        'hostname' => $data['hostname'] ?? '',
        'device_type' => $data['device_type'] ?? 'CLIENT',
        'message' => $throwable->getMessage(),
    ]);

    device_inventory_json_response(500, [
        'success' => false,
        'message' => 'Gagal menyimpan data device.',
    ]);
}
