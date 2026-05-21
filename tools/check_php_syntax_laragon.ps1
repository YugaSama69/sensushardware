Set-Location 'C:\laragon\www\sensushardware'
$errorsFile = 'C:\xampp\htdocs\sensushardware\tools\php_syntax_errors.txt'
if (Test-Path $errorsFile) { Remove-Item $errorsFile }
Get-ChildItem -Recurse -Include *.php | ForEach-Object {
    $f = $_.FullName
    $out = & 'C:\xampp\php\php.exe' -l $f 2>&1
    if ($LASTEXITCODE -ne 0) { $out | Out-File -FilePath $errorsFile -Append -Encoding utf8 }
}
if (-Not (Test-Path $errorsFile)) { 'NO_ERRORS' | Out-File $errorsFile -Encoding utf8 }
Write-Host 'Done'