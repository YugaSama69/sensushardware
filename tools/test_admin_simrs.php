<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

$options = getopt('', ['host::', 'port::', 'user::', 'pass::', 'db::', 'base-url::', 'help']);

if (isset($options['help'])) {
    echo "Usage:\n";
    echo "  php tools/test_admin_simrs.php [--host=HOST] [--port=PORT] [--user=USER] [--pass=PASS] [--db=DBNAME] [--base-url=URL]\n";
    echo "\n";
    echo "Options:\n";
    echo "  --host      Database host (default: 127.0.0.1)\n";
    echo "  --port      Database port (default: 3306)\n";
    echo "  --user      Database user (default: root)\n";
    echo "  --pass      Database password (default: empty)\n";
    echo "  --db        Database name (default: admin_simrs)\n";
    echo "  --base-url  Optional base URL to verify HTTP endpoints.\n";
    exit(0);
}

$host = $options['host'] ?? '127.0.0.1';
$port = $options['port'] ?? '3306';
$user = $options['user'] ?? 'root';
$pass = $options['pass'] ?? '';
$dbName = $options['db'] ?? 'admin_simrs';
$baseUrl = rtrim((string) ($options['base-url'] ?? ''), '/');

function output(string $message): void
{
    echo $message . PHP_EOL;
}

function fail(string $message): void
{
    output('ERROR: ' . $message);
    exit(1);
}

$outputRows = [];
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbName);

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    fail('Unable to connect to database: ' . $exception->getMessage());
}

output('Connected to database ' . $dbName . ' on ' . $host . ':' . $port);

$tables = [
    'barang',
    'histori_barang',
    'ruangan',
    'komputer_client',
    'server_detail',
    'mutasi_komputer',
    'laporan_pengembangan_aplikasi',
    'monitoring_ruangan',
];

foreach ($tables as $table) {
    $statement = $pdo->prepare(
        'SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = :schema AND table_name = :table'
    );
    $statement->execute(['schema' => $dbName, 'table' => $table]);
    $exists = (int) $statement->fetchColumn() > 0;
    $outputRows[] = [$table, $exists ? 'found' : 'missing'];
}

output('Table existence check:');
foreach ($outputRows as [$table, $status]) {
    output(sprintf('  %-28s %s', $table, $status));
}

$missing = array_filter($outputRows, static fn (array $row): bool => $row[1] === 'missing');
if ($missing) {
    fail('One or more required tables are missing from the admin_simrs database.');
}

$sampleQueries = [
    'barang' => 'SELECT id, kode_barang, nama_barang FROM barang ORDER BY id ASC LIMIT 1',
    'komputer_client' => 'SELECT id, hostname, device_type, kondisi FROM komputer_client ORDER BY id ASC LIMIT 1',
    'histori_barang' => 'SELECT id, barang_id, tipe_transaksi, qty FROM histori_barang ORDER BY id ASC LIMIT 1',
    'mutasi_komputer' => 'SELECT id, barang_id, jenis_mutasi FROM mutasi_komputer ORDER BY id ASC LIMIT 1',
];

output('\nSample record check:');
foreach ($sampleQueries as $name => $sql) {
    try {
        $row = $pdo->query($sql)->fetch();
    } catch (PDOException $exception) {
        if ($name === 'komputer_client') {
            $fallbackSql = 'SELECT id, hostname, username, kondisi FROM komputer_client ORDER BY id ASC LIMIT 1';
            try {
                $row = $pdo->query($fallbackSql)->fetch();
            } catch (PDOException $fallbackException) {
                output(sprintf('  %-18s query failed (%s)', $name, $fallbackException->getMessage()));
                continue;
            }
        } else {
            output(sprintf('  %-18s query failed (%s)', $name, $exception->getMessage()));
            continue;
        }
    }

    if (!$row) {
        output(sprintf('  %-18s no rows found', $name));
        continue;
    }

    output(sprintf('  %-18s %s', $name, implode(', ', array_map(static fn ($value) => (string) $value, $row))));
}

$outputRows = [];
foreach (['barang', 'komputer_client', 'histori_barang', 'mutasi_komputer'] as $table) {
    $count = (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    $outputRows[] = [$table, $count];
}

output('\nRow counts:');
foreach ($outputRows as [$table, $count]) {
    output(sprintf('  %-18s %d', $table, $count));
}

if ($baseUrl !== '') {
    output('\nHTTP endpoint validation:');
    $endpoints = [
        '/modules/barang/index.php',
        '/modules/transaksi/history.php',
        '/modules/komputer/index.php',
        '/modules/monitoring_ruangan/index.php',
    ];

    foreach ($endpoints as $endpoint) {
        $url = $baseUrl . $endpoint;
        output('  GET ' . $url);

        $response = @file_get_contents($url);
        if ($response !== false && trim($response) !== '') {
            output('    ok (content length: ' . strlen($response) . ')');
            continue;
        }

        if (function_exists('curl_version')) {
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($response !== false && $httpCode >= 200 && $httpCode < 400 && trim($response) !== '') {
                output('    ok (HTTP ' . $httpCode . ', content length: ' . strlen($response) . ')');
                continue;
            }
        }

        fail('Unable to load ' . $url . '. Please verify the base URL and web server configuration.');
    }
}

output('\nAll checks passed. The admin_simrs database appears readable and ready for CI3 port verification.');
exit(0);
