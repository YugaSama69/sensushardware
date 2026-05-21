$sourceSql = 'C:\xampp\htdocs\sensushardware\database\sensus_hardware.sql'
$targetSql = 'C:\xampp\htdocs\sensushardware\tools\sensus_hardware_admin_simrs.sql'
$mysqlExe = 'C:\xampp\mysql\bin\mysql.exe'
$phpExe = 'C:\xampp\php\php.exe'

$content = Get-Content -Path $sourceSql -Raw
$content = $content -replace 'CREATE DATABASE IF NOT EXISTS sensus_hardware CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;', 'CREATE DATABASE IF NOT EXISTS admin_simrs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
$content = $content -replace 'USE sensus_hardware;', 'USE admin_simrs;'
Set-Content -Path $targetSql -Value $content -Encoding utf8
Get-Content -Path $targetSql | & $mysqlExe -uroot
& $phpExe 'C:\xampp\htdocs\sensushardware\tools\test_admin_simrs.php' --host=127.0.0.1 --port=3306 --user=root --pass= --db=admin_simrs
