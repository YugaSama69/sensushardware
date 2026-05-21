@echo off
title Pendataan Komputer Rumah Sakit
color 0A
echo ==================================================
echo Pendataan Inventaris Komputer Rumah Sakit
echo ==================================================
echo Server    : https://admin-simrs.rsudwelasasih.my.id/sensushardware
echo Ruangan   : INSTALASI SIM RS
echo Tahun     : 2021
echo Nama User : BAYU
echo.
echo Mengambil agent pendataan...

set "SERVER_BASE=https://admin-simrs.rsudwelasasih.my.id/sensushardware"
set "SCRIPT_URL=%SERVER_BASE%/client-agent/scan-device.ps1"
set "API_URL=%SERVER_BASE%/api/komputer_client.php"
set "SCRIPT_PATH=%TEMP%\scan-device-rs.ps1"
set "DEVICE_TYPE=CLIENT"
set "RUANGAN=INSTALASI SIM RS"
set "TAHUN_INVENTARIS=2021"
set "NAMA_USER=BAYU"
set "KONDISI=Baik"

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
"try { ^
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12 -bor [Net.SecurityProtocolType]::Tls11 -bor [Net.SecurityProtocolType]::Tls; ^
    Invoke-WebRequest -Uri '%SCRIPT_URL%' -UseBasicParsing -OutFile '%SCRIPT_PATH%'; ^
    exit 0 ^
} catch { ^
    Write-Host 'Gagal mengambil agent pendataan:' $_.Exception.Message -ForegroundColor Red; ^
    exit 1 ^
}"

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo Gagal mengunduh agent pendataan.
    pause
    exit /b 1
)

start "Pendataan Komputer RS" /wait powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -Command ^
"& '%SCRIPT_PATH%' -ServerUrl '%API_URL%' -DeviceType '%DEVICE_TYPE%' -Ruangan '%RUANGAN%' -TahunInventaris '%TAHUN_INVENTARIS%' -NamaUser '%NAMA_USER%' -Kondisi '%KONDISI%'; exit `$LASTEXITCODE"

echo.
if %ERRORLEVEL% EQU 0 (
    echo Data berhasil dikirim.
) else (
    echo Data gagal dikirim. Pastikan jaringan, HTTPS, dan server aktif.
)
echo.
pause
