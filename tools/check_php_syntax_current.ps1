Set-Location 'C:\xampp\htdocs\sensushardware'
$errorsFile = 'C:\xampp\htdocs\sensushardware\tools\php_current_syntax_errors.txt'
if (Test-Path $errorsFile) { Remove-Item $errorsFile }
$errors = @()
Get-ChildItem -Recurse -Include *.php | ForEach-Object {
    $f = $_.FullName
    $result = & 'C:\xampp\php\php.exe' -l $f 2>&1
    if ($LASTEXITCODE -ne 0) {
        $result | Out-File -FilePath $errorsFile -Append -Encoding utf8
    }
}
if (-Not (Test-Path $errorsFile)) {
    'NO_ERRORS' | Out-File -FilePath $errorsFile -Encoding utf8
}
Write-Host 'Done'
