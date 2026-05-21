$sourceSql = 'C:\xampp\htdocs\sensushardware\database\komputer_client.sql'
$mysqlExe = 'C:\xampp\mysql\bin\mysql.exe'
$phpExe = 'C:\xampp\php\php.exe'

$content = Get-Content -Path $sourceSql -Raw
$content = $content -replace 'USE sensus_hardware;', 'USE admin_simrs;'
$content | & $mysqlExe -uroot
& $phpExe 'C:\xampp\htdocs\sensushardware\tools\test_admin_simrs.php' --host=127.0.0.1 --port=3306 --user=root --pass= --db=admin_simrs
