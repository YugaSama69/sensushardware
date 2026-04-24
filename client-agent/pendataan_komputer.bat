@echo off
title Pendataan Komputer Rumah Sakit
color 0A

REM Ubah SERVER_BASE sesuai alamat server XAMPP di LAN rumah sakit.
set "SERVER_BASE=http://192.168.2.69/sensushardware"
set "RUANGAN=Lab Komputer"
set "TAHUN_INVENTARIS=2026"
set "NAMA_USER=Nama User"

set "SCRIPT_URL=%SERVER_BASE%/client-agent/scan-komputer.ps1"
set "API_URL=%SERVER_BASE%/api/komputer_client.php"
set "SCRIPT_PATH=%TEMP%\scan-komputer-rs.ps1"

echo Mengunduh agent pendataan dari %SCRIPT_URL%
powershell -NoProfile -ExecutionPolicy Bypass -Command "try { Invoke-WebRequest -Uri '%SCRIPT_URL%' -UseBasicParsing -OutFile '%SCRIPT_PATH%'; & '%SCRIPT_PATH%' -ServerUrl '%API_URL%' -Ruangan '%RUANGAN%' -TahunInventaris '%TAHUN_INVENTARIS%' -NamaUser '%NAMA_USER%'; exit $LASTEXITCODE } catch { Write-Host 'Gagal menjalankan pendataan:' $_.Exception.Message -ForegroundColor Red; exit 1 }"

echo.
pause
