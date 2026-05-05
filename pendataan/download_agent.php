<?php

require_once __DIR__ . '/../config/app.php';

function bat_value(string $value): string
{
    $value = preg_replace('/[^a-zA-Z0-9\s\.\,\-\_\(\)\/]/', '', $value) ?? '';
    return trim($value);
}

$server = rtrim(trim($_GET['server'] ?? ''), '/');

if ($server === '') {
    $server = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
}

$ruangan = bat_value($_GET['ruangan'] ?? '');
$tahunInventaris = bat_value($_GET['tahun_inventaris'] ?? date('Y'));
$petugas = bat_value($_GET['nama_user'] ?? ($_GET['petugas'] ?? ''));
$kondisi = bat_value($_GET['kondisi'] ?? 'Baik');

if ($ruangan === '' || $petugas === '') {
    http_response_code(422);
    exit('Ruangan dan nama user wajib diisi.');
}

if (!preg_match('/^\d{4}$/', $tahunInventaris)) {
    http_response_code(422);
    exit('Tahun inventaris harus 4 digit.');
}

if (!in_array($kondisi, ['Baik', 'Rusak', 'Perbaikan'], true)) {
    http_response_code(422);
    exit('Kondisi komputer tidak valid.');
}

$scriptUrl = $server . '/client-agent/scan-komputer.ps1';
$apiUrl = $server . '/api/komputer_client.php';

$bat = <<<BAT
@echo off
title Pendataan Komputer Rumah Sakit
color 0A
echo ==================================================
echo Pendataan Inventaris Komputer Rumah Sakit
echo ==================================================
echo Server  : {$server}
echo Ruangan : {$ruangan}
echo Tahun   : {$tahunInventaris}
echo Nama User : {$petugas}
echo Kondisi : {$kondisi}
echo.
echo Mengambil agent pendataan...

set "SCRIPT_URL={$scriptUrl}"
set "API_URL={$apiUrl}"
set "SCRIPT_PATH=%TEMP%\\scan-komputer-rs.ps1"
set "RUANGAN={$ruangan}"
set "TAHUN_INVENTARIS={$tahunInventaris}"
set "NAMA_USER={$petugas}"
set "KONDISI={$kondisi}"

powershell -NoProfile -ExecutionPolicy Bypass -Command "try { [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri '%SCRIPT_URL%' -UseBasicParsing -OutFile '%SCRIPT_PATH%'; & '%SCRIPT_PATH%' -ServerUrl '%API_URL%' -Ruangan '%RUANGAN%' -TahunInventaris '%TAHUN_INVENTARIS%' -NamaUser '%NAMA_USER%' -Kondisi '%KONDISI%'; exit \$LASTEXITCODE } catch { Write-Host 'Gagal menjalankan pendataan:' \$_.Exception.Message -ForegroundColor Red; exit 1 }"

echo.
if %ERRORLEVEL% EQU 0 (
    echo Data berhasil dikirim.
) else (
    echo Data gagal dikirim. Pastikan jaringan LAN dan server XAMPP aktif.
)
echo.
pause
BAT;

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="pendataan-komputer-rs.bat"');
echo $bat;
