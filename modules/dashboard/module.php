<?php

declare(strict_types=1);

function dashboard_device_filter_options(): array
{
    return [
        'ALL' => 'All',
        'CLIENT' => 'Client',
        'SERVER' => 'Server',
    ];
}

function dashboard_normalize_device_filter(array $input): string
{
    $filter = strtoupper(trim((string) ($input['device_filter'] ?? 'ALL')));

    return array_key_exists($filter, dashboard_device_filter_options()) ? $filter : 'ALL';
}

function dashboard_device_cache_directory(): string
{
    $path = BASE_PATH . '/storage/cache/dashboard_device';
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }

    return $path;
}

function dashboard_device_cache_path(string $filter): string
{
    return dashboard_device_cache_directory() . '/stats-' . strtolower($filter) . '.json';
}

function dashboard_device_cache_ttl_seconds(): int
{
    return 60;
}

function dashboard_device_filter_allows(string $activeFilter, string $deviceType): bool
{
    return $activeFilter === 'ALL' || $activeFilter === $deviceType;
}

function dashboard_device_stats_query(PDO $pdo, string $filter): array
{
    $stats = [
        'filter' => $filter,
        'total_server' => 0,
        'total_client' => 0,
        'windows_server' => 0,
        'linux_server' => 0,
        'hypervisor' => 0,
        'hdd_only' => 0,
        'server_bermasalah' => 0,
        'generated_at' => date('c'),
    ];

    if (dashboard_device_filter_allows($filter, 'SERVER')) {
        $stats['total_server'] = (int) fetch_scalar($pdo, 'SELECT COUNT(*) FROM komputer_client WHERE device_type = "SERVER"');
        $stats['windows_server'] = (int) fetch_scalar(
            $pdo,
            'SELECT COUNT(*)
             FROM komputer_client
             WHERE device_type = "SERVER"
               AND LOWER(COALESCE(os_name, "")) LIKE "%windows server%"'
        );
        $stats['linux_server'] = (int) fetch_scalar(
            $pdo,
            'SELECT COUNT(*)
             FROM komputer_client
             WHERE device_type = "SERVER"
               AND (
                    LOWER(COALESCE(os_name, "")) LIKE "%linux%"
                    OR LOWER(COALESCE(os_name, "")) LIKE "%ubuntu%"
                    OR LOWER(COALESCE(os_name, "")) LIKE "%debian%"
                    OR LOWER(COALESCE(os_name, "")) LIKE "%centos%"
                    OR LOWER(COALESCE(os_name, "")) LIKE "%red hat%"
                    OR LOWER(COALESCE(os_name, "")) LIKE "%rhel%"
                    OR LOWER(COALESCE(os_name, "")) LIKE "%rocky%"
                    OR LOWER(COALESCE(os_name, "")) LIKE "%alma%"
               )'
        );
        $stats['hypervisor'] = (int) fetch_scalar(
            $pdo,
            'SELECT COUNT(*)
             FROM komputer_client kc
             LEFT JOIN server_detail sd ON sd.komputer_id = kc.id
             WHERE kc.device_type = "SERVER"
               AND (
                    LOWER(COALESCE(sd.virtualization, "")) = "virtual"
                    OR LOWER(COALESCE(sd.virtual_fisik, "")) = "virtual"
                    OR TRIM(COALESCE(sd.hypervisor, "")) <> ""
               )'
        );
        $stats['server_bermasalah'] = (int) fetch_scalar(
            $pdo,
            'SELECT COUNT(*)
             FROM komputer_client
             WHERE device_type = "SERVER"
               AND kondisi <> "Baik"'
        );
    }

    if (dashboard_device_filter_allows($filter, 'CLIENT')) {
        $stats['total_client'] = (int) fetch_scalar($pdo, 'SELECT COUNT(*) FROM komputer_client WHERE device_type = "CLIENT"');
    }

    if ($filter === 'ALL') {
        $stats['hdd_only'] = (int) fetch_scalar(
            $pdo,
            'SELECT COUNT(*)
             FROM komputer_client
             WHERE (ssd IS NULL OR TRIM(ssd) = "" OR TRIM(ssd) = "-")
               AND hdd IS NOT NULL
               AND TRIM(hdd) <> ""
               AND TRIM(hdd) <> "-"'
        );
    } else {
        $stats['hdd_only'] = (int) fetch_scalar(
            $pdo,
            'SELECT COUNT(*)
             FROM komputer_client
             WHERE device_type = :device_type
               AND (ssd IS NULL OR TRIM(ssd) = "" OR TRIM(ssd) = "-")
               AND hdd IS NOT NULL
               AND TRIM(hdd) <> ""
               AND TRIM(hdd) <> "-"',
            ['device_type' => $filter]
        );
    }

    return $stats;
}

function dashboard_device_stats(PDO $pdo, string $filter, bool $useCache = true): array
{
    $cacheFile = dashboard_device_cache_path($filter);

    if ($useCache && is_file($cacheFile) && (time() - filemtime($cacheFile)) < dashboard_device_cache_ttl_seconds()) {
        $decoded = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    $stats = dashboard_device_stats_query($pdo, $filter);
    file_put_contents($cacheFile, json_encode($stats, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    return $stats;
}
