<?php

require_once __DIR__ . '/../config/app.php';
require_once BASE_PATH . '/modules/monitoring_ruangan/module.php';

require_login();
require_module_access();

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'GET') {
    $action = trim((string) ($_GET['action'] ?? 'list'));

    if ($action === 'stats') {
        $stats = monitoring_get_dashboard_stats($pdo);
        monitoring_json_response([
            'success' => true,
            'data' => [
                'today_total' => $stats['today_total'],
                'problem_rooms' => $stats['problem_rooms'],
                'abnormal_temperature' => $stats['abnormal_temperature'],
                'unlocked_access' => $stats['unlocked_access'],
                'latest_entry' => $stats['latest_entry'] ? monitoring_json_row($stats['latest_entry']) : null,
                'chart' => monitoring_chart_dataset($stats['chart_rows']),
            ],
        ]);
    }

    if ($action === 'detail') {
        $id = (int) ($_GET['id'] ?? 0);
        $row = $id > 0 ? monitoring_find_row($pdo, $id) : null;

        if (!$row) {
            monitoring_json_response([
                'success' => false,
                'message' => 'Detail monitoring tidak ditemukan.',
            ], 404);
        }

        monitoring_json_response([
            'success' => true,
            'data' => monitoring_json_row($row),
        ]);
    }

    $filters = monitoring_normalize_filters($_GET);
    $rows = monitoring_get_rows($pdo, $filters);
    $limit = max(1, min((int) ($_GET['limit'] ?? 20), 100));
    $rows = array_slice($rows, 0, $limit);

    monitoring_json_response([
        'success' => true,
        'data' => array_map('monitoring_json_row', $rows),
    ]);
}

if ($method !== 'POST') {
    monitoring_json_response([
        'success' => false,
        'message' => 'Method tidak diizinkan.',
    ], 405);
}

$payload = $_POST;

if ($payload === []) {
    $rawBody = file_get_contents('php://input');
    $decoded = json_decode($rawBody ?: '', true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

try {
    monitoring_require_csrf_token_from_request($payload);
} catch (Throwable $throwable) {
    monitoring_json_response([
        'success' => false,
        'message' => 'Token CSRF tidak valid.',
    ], 419);
}

$action = trim((string) ($payload['action'] ?? 'create'));

$errors = [];

if ($action === 'update_signature') {
    $id = (int) ($payload['id'] ?? 0);
    $signatureBase64 = trim((string) ($payload['signature_base64'] ?? ''));

    if (!monitoring_update_signature($pdo, $id, $signatureBase64, $errors)) {
        monitoring_json_response([
            'success' => false,
            'message' => $errors ? implode(' ', $errors) : 'Paraf monitoring gagal disimpan.',
            'errors' => $errors,
        ], 422);
    }

    $updatedRow = monitoring_find_row($pdo, $id);
    monitoring_json_response([
        'success' => true,
        'message' => 'Paraf monitoring berhasil disimpan.',
        'data' => $updatedRow ? monitoring_json_row($updatedRow) : ['id' => $id],
    ]);
}

$id = monitoring_create_entry($pdo, $payload, [
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
    'device_info' => monitoring_device_info(),
], $errors);

if ($id === null) {
    monitoring_json_response([
        'success' => false,
        'message' => $errors ? implode(' ', $errors) : 'Data monitoring gagal disimpan.',
        'errors' => $errors,
    ], 422);
}

$createdRow = monitoring_find_row($pdo, $id);

monitoring_json_response([
    'success' => true,
    'message' => 'Monitoring ruangan server berhasil disimpan.',
    'data' => $createdRow ? monitoring_json_row($createdRow) : ['id' => $id],
], 201);
