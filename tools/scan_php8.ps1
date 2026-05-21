Set-Location 'C:\laragon\www\sensushardware'
Get-ChildItem -Recurse -Include *.php | Select-String -Pattern '\bmatch\s*\(', '\bstr_contains\s*\(', '\bstr_starts_with\s*\(', '\bstr_ends_with\s*\(', ':\s*mixed\b', '\|\s*int\b', '\|\s*float\b', '\|\s*string\b', '\|\s*bool\b', '\|\s*array\b', '\|\s*null\b' | ForEach-Object { $_.Path + ':' + $_.LineNumber + ': ' + $_.Line }
