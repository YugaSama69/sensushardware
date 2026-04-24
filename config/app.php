<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

define('APP_NAME', 'Sistem Sensus Hardware & Inventaris Elektronik');
define('BASE_PATH', dirname(__DIR__));

if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionPath = BASE_PATH . '/storage/sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0777, true);
    }
    session_save_path($sessionPath);
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : null;
$baseUrl = '';

if ($documentRoot) {
    $normalizedBasePath = str_replace('\\', '/', realpath(BASE_PATH));
    $normalizedDocumentRoot = str_replace('\\', '/', $documentRoot);
    $relativePath = trim(str_replace($normalizedDocumentRoot, '', $normalizedBasePath), '/');
    $baseUrl = $relativePath !== '' ? '/' . $relativePath : '';
}

define('BASE_URL', $baseUrl);

require_once __DIR__ . '/database.php';
require_once BASE_PATH . '/includes/helpers.php';
require_once BASE_PATH . '/includes/auth.php';
