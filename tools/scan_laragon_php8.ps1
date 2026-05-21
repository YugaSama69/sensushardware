$laragonPath = 'C:\laragon\www\sensushardware'
if (-Not (Test-Path $laragonPath)) {
    Write-Host "LARAGON_PATH_MISSING"
    exit 1
}
Write-Host "Laragon path: $laragonPath"
Get-ChildItem -Path 'C:\laragon' -Directory -ErrorAction SilentlyContinue | Select-Object -First 20 | ForEach-Object { Write-Host $_.FullName }
Set-Location $laragonPath
$results = Get-ChildItem -Recurse -Include *.php | Select-String -Pattern '\bmatch\s*\(', '\?->', ':\s*mixed\b', '\|\s*int\b', '\|\s*float\b', '\|\s*string\b', '\|\s*bool\b', '\|\s*array\b', '\|\s*null\b'
$results | ForEach-Object { Write-Host "$($_.Path):$($_.LineNumber): $($_.Line)" }
$results | Sort-Object | ForEach-Object { "$($_.Path):$($_.LineNumber): $($_.Line)" } | Out-File -FilePath 'C:\xampp\htdocs\sensushardware\tools\scan_laragon_php8.txt' -Encoding utf8
Write-Host 'SCAN_DONE'