$root = 'C:\laragon\www\sensushardware'
$phpExe = 'C:\xampp\php\php.exe'
$errors = @()
Set-Location $root
$files = Get-ChildItem -Recurse -Include *.php | Where-Object { $_.FullName -notmatch '\\system\\' }
foreach ($f in $files) {
    $out = & $phpExe -l $f.FullName 2>&1
    if ($LASTEXITCODE -ne 0) {
        $errors += "ERROR: $($f.FullName)`n$out"
    }
}
if ($errors.Count -eq 0) {
    Write-Host 'NO_SYNTAX_ERRORS'
} else {
    $errors | ForEach-Object { Write-Host $_ }
}
