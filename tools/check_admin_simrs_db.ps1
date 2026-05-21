$mysqlExe = 'C:\xampp\mysql\bin\mysql.exe'
$database = 'admin_simrs'
$query = "SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.tables WHERE table_schema = '$database' ORDER BY TABLE_NAME;"
& $mysqlExe -uroot -e $query
