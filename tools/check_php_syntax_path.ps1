param(
    [string]$TargetPath = 'C:\laragon\www\sensushardware',
    [string]$PhpExe = 'C:\xampp\php\php.exe'
)
if (-Not (Test-Path $TargetPath)) {
    Write-Host "TARGET_PATH_MISSING: $TargetPath"
    exit 1
}
if (-Not (Test-Path $PhpExe)) {
    Write-Host "PHP_EXE_MISSING: $PhpExe"
    exit 1
}
$errorsFile = 'C:\xampp\htdocs\sensushardware\tools\php_laragon_syntax_errors.txt'
if (Test-Path $errorsFile) { Remove-Item $errorsFile }
Set-Location $TargetPath
Get-ChildItem -Recurse -Include *.php | ForEach-Object {
    $f = $_.FullName
    $result = & $PhpExe -l "$f" 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host "ERROR: $f"
        $result | Out-File -FilePath $errorsFile -Append -Encoding utf8
    }
}
if (-Not (Test-Path $errorsFile)) {
    'NO_ERRORS' | Out-File -FilePath $errorsFile -Encoding utf8
}
Write-Host 'DONE'
