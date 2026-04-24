<?php

require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_post()) {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method tidak diizinkan.',
    ]);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);

if (!is_array($payload)) {
    $payload = $_POST;
}

if (!isset($payload['petugas']) && isset($payload['nama_user'])) {
    $payload['petugas'] = $payload['nama_user'];
}

$fields = [
    'hostname',
    'username',
    'ip_address',
    'mac_address',
    'merk',
    'model',
    'processor',
    'core',
    'ram',
    'ssd',
    'hdd',
    'vga',
    'motherboard',
    'os_name',
    'os_version',
    'architecture',
    'tahun_inventaris',
    'ruangan',
    'petugas',
];

$data = [];
foreach ($fields as $field) {
    $data[$field] = trim((string) ($payload[$field] ?? ''));
}

$data['core'] = (int) ($payload['core'] ?? 0);
$data['tahun_inventaris'] = trim((string) ($payload['tahun_inventaris'] ?? date('Y')));
$data['ip_address'] = $data['ip_address'] !== '' ? $data['ip_address'] : ($_SERVER['REMOTE_ADDR'] ?? '');
$tanggal = date('Y-m-d');
$jam = date('H:i:s');

$errors = [];

if ($data['hostname'] === '') {
    $errors[] = 'Hostname tidak terbaca.';
}

if ($data['mac_address'] === '') {
    $errors[] = 'MAC address tidak terbaca.';
}

if ($data['ruangan'] === '') {
    $errors[] = 'Ruangan wajib diisi.';
}

if ($data['petugas'] === '') {
    $errors[] = 'Nama user wajib diisi.';
}

if (!preg_match('/^\d{4}$/', $data['tahun_inventaris'])) {
    $errors[] = 'Tahun inventaris harus 4 digit.';
}

if ($errors) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => implode(' ', $errors),
    ]);
    exit;
}

try {
    $statement = $pdo->prepare('
        INSERT INTO komputer_client (
            hostname, username, ip_address, mac_address, merk, model, processor, core, ram, ssd, hdd,
            vga, motherboard, os_name, os_version, architecture, tahun_inventaris, ruangan, petugas, tanggal, jam, created_at
        ) VALUES (
            :hostname, :username, :ip_address, :mac_address, :merk, :model, :processor, :core, :ram, :ssd, :hdd,
            :vga, :motherboard, :os_name, :os_version, :architecture, :tahun_inventaris, :ruangan, :petugas, :tanggal, :jam, NOW()
        )
        ON DUPLICATE KEY UPDATE
            username = VALUES(username),
            ip_address = VALUES(ip_address),
            merk = VALUES(merk),
            model = VALUES(model),
            processor = VALUES(processor),
            core = VALUES(core),
            ram = VALUES(ram),
            ssd = VALUES(ssd),
            hdd = VALUES(hdd),
            vga = VALUES(vga),
            motherboard = VALUES(motherboard),
            os_name = VALUES(os_name),
            os_version = VALUES(os_version),
            architecture = VALUES(architecture),
            tahun_inventaris = VALUES(tahun_inventaris),
            ruangan = VALUES(ruangan),
            petugas = VALUES(petugas),
            tanggal = VALUES(tanggal),
            jam = VALUES(jam),
            created_at = NOW()
    ');

    $statement->execute([
        'hostname' => $data['hostname'],
        'username' => $data['username'],
        'ip_address' => $data['ip_address'],
        'mac_address' => $data['mac_address'],
        'merk' => $data['merk'],
        'model' => $data['model'],
        'processor' => $data['processor'],
        'core' => $data['core'],
        'ram' => $data['ram'],
        'ssd' => $data['ssd'],
        'hdd' => $data['hdd'],
        'vga' => $data['vga'],
        'motherboard' => $data['motherboard'],
        'os_name' => $data['os_name'],
        'os_version' => $data['os_version'],
        'architecture' => $data['architecture'],
        'tahun_inventaris' => $data['tahun_inventaris'],
        'ruangan' => $data['ruangan'],
        'petugas' => $data['petugas'],
        'tanggal' => $tanggal,
        'jam' => $jam,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Data berhasil dikirim.',
        'hostname' => $data['hostname'],
        'tanggal' => $tanggal,
        'jam' => $jam,
    ]);
} catch (Throwable $throwable) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menyimpan data komputer client.',
    ]);
}
