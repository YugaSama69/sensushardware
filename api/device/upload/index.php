<?php

require_once dirname(__DIR__, 3) . '/config/app.php';
require_once BASE_PATH . '/pendataan/module.php';
device_inventory_ensure_runtime_schema($pdo);

if (!is_post()) {
    device_inventory_json_response(405, [
        'success' => false,
        'message' => 'Method tidak diizinkan.',
    ]);
}

if (!device_inventory_is_https_request()) {
    device_inventory_log_event('upload_failed', [
        'endpoint' => 'api/device/upload',
        'reason' => 'non_https_request',
    ]);

    device_inventory_json_response(426, [
        'success' => false,
        'message' => 'Upload API hanya menerima koneksi HTTPS.',
    ]);
}

$rawBody = file_get_contents('php://input');
$token = trim((string) ($_SERVER['HTTP_X_DEVICE_TOKEN'] ?? ''));
$providedHash = trim((string) ($_SERVER['HTTP_X_DEVICE_HASH'] ?? ''));

if ($rawBody === '' || $token === '' || $providedHash === '') {
    device_inventory_log_event('invalid_payload', [
        'endpoint' => 'api/device/upload',
        'reason' => 'missing_body_or_headers',
    ]);

    device_inventory_json_response(422, [
        'success' => false,
        'message' => 'Payload, token, atau hash upload tidak lengkap.',
    ]);
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    device_inventory_log_event('invalid_payload', [
        'endpoint' => 'api/device/upload',
        'reason' => 'invalid_json',
    ]);

    device_inventory_json_response(422, [
        'success' => false,
        'message' => 'Payload JSON tidak valid.',
    ]);
}

if (!isset($payload['petugas']) && isset($payload['nama_user'])) {
    $payload['petugas'] = $payload['nama_user'];
}

$data = device_inventory_normalize_upload_payload($payload);
$errors = [];
device_inventory_validate_upload_payload($data, $errors);

if ($errors) {
    device_inventory_log_event('invalid_payload', [
        'endpoint' => 'api/device/upload',
        'errors' => $errors,
        'hostname' => $data['hostname'] ?? '',
        'device_type' => $data['device_type'] ?? 'CLIENT',
    ]);

    device_inventory_json_response(422, [
        'success' => false,
        'message' => implode(' ', $errors),
    ]);
}

if (device_inventory_payload_is_timed_out($data['sent_at'])) {
    device_inventory_log_event('timeout', [
        'endpoint' => 'api/device/upload',
        'hostname' => $data['hostname'] ?? '',
        'sent_at' => $data['sent_at'],
    ]);

    device_inventory_json_response(408, [
        'success' => false,
        'message' => 'Request upload melebihi batas waktu yang diizinkan.',
    ]);
}

try {
    $pdo->beginTransaction();

    $tokenRow = device_inventory_lock_upload_token($pdo, $token);
    if (!$tokenRow) {
        $pdo->rollBack();
        device_inventory_log_event('upload_failed', [
            'endpoint' => 'api/device/upload',
            'reason' => 'invalid_token',
            'hostname' => $data['hostname'] ?? '',
        ]);

        device_inventory_json_response(401, [
            'success' => false,
            'message' => 'Token upload tidak valid.',
        ]);
    }

    if (!empty($tokenRow['used_at'])) {
        $pdo->rollBack();
        device_inventory_log_event('upload_failed', [
            'endpoint' => 'api/device/upload',
            'reason' => 'used_token',
            'token_id' => $tokenRow['id'] ?? null,
            'hostname' => $data['hostname'] ?? '',
        ]);

        device_inventory_json_response(409, [
            'success' => false,
            'message' => 'Token upload sudah digunakan.',
        ]);
    }

    if (strtotime((string) $tokenRow['expires_at']) < time()) {
        $pdo->rollBack();
        device_inventory_log_event('timeout', [
            'endpoint' => 'api/device/upload',
            'reason' => 'expired_token',
            'token_id' => $tokenRow['id'] ?? null,
            'hostname' => $data['hostname'] ?? '',
        ]);

        device_inventory_json_response(408, [
            'success' => false,
            'message' => 'Token upload sudah kedaluwarsa.',
        ]);
    }

    $expectedHash = device_inventory_upload_hash($token, $rawBody);
    if (!hash_equals($expectedHash, $providedHash)) {
        $pdo->rollBack();
        device_inventory_log_event('hash_mismatch', [
            'endpoint' => 'api/device/upload',
            'token_id' => $tokenRow['id'] ?? null,
            'hostname' => $data['hostname'] ?? '',
        ]);

        device_inventory_json_response(401, [
            'success' => false,
            'message' => 'Hash payload tidak valid.',
        ]);
    }

    $registeredPayload = device_inventory_decrypt_payload((string) $tokenRow['payload_encrypted']);
    $expectedDeviceType = strtoupper((string) ($registeredPayload['device_type'] ?? 'CLIENT'));
    $expectedRoom = trim((string) ($registeredPayload['ruangan'] ?? ''));
    $expectedYear = trim((string) ($registeredPayload['tahun_inventaris'] ?? ''));
    $expectedUser = trim((string) ($registeredPayload['nama_user'] ?? ''));
    $expectedCondition = trim((string) ($registeredPayload['kondisi'] ?? 'Baik'));

    if (
        $data['device_type'] !== $expectedDeviceType
        || $data['ruangan'] !== $expectedRoom
        || $data['tahun_inventaris'] !== $expectedYear
        || $data['petugas'] !== $expectedUser
        || $data['kondisi'] !== $expectedCondition
    ) {
        $pdo->rollBack();
        device_inventory_log_event('invalid_payload', [
            'endpoint' => 'api/device/upload',
            'reason' => 'payload_does_not_match_token',
            'hostname' => $data['hostname'] ?? '',
            'token_id' => $tokenRow['id'] ?? null,
        ]);

        device_inventory_json_response(422, [
            'success' => false,
            'message' => 'Data upload tidak sesuai dengan token registrasi.',
        ]);
    }

    $result = device_inventory_persist_device($pdo, $data);
    device_inventory_mark_upload_token_used($pdo, (int) $tokenRow['id'], $expectedHash);
    $pdo->commit();

    device_inventory_log_event('upload_success', [
        'endpoint' => 'api/device/upload',
        'token_id' => $tokenRow['id'] ?? null,
        'hostname' => $data['hostname'],
        'device_type' => $data['device_type'],
        'ruangan' => $data['ruangan'],
    ]);

    device_inventory_json_response(200, [
        'success' => true,
        'message' => 'Data device berhasil diupload.',
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
        'endpoint' => 'api/device/upload',
        'hostname' => $data['hostname'] ?? '',
        'device_type' => $data['device_type'] ?? 'CLIENT',
        'message' => $throwable->getMessage(),
    ]);

    device_inventory_json_response(500, [
        'success' => false,
        'message' => 'Upload device belum berhasil diproses.',
    ]);
}
