param(
    [string]$WorkbookPath = 'C:\Users\admin\Downloads\Monitoring Ruangan Server.xlsx',
    [string]$MySqlExe = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$Database = 'sensus_hardware',
    [string]$DbUser = 'root',
    [string]$DbPassword = ''
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if (-not (Test-Path -LiteralPath $WorkbookPath)) {
    throw "Workbook tidak ditemukan: $WorkbookPath"
}

if (-not (Test-Path -LiteralPath $MySqlExe)) {
    throw "mysql.exe tidak ditemukan: $MySqlExe"
}

Add-Type -AssemblyName System.IO.Compression.FileSystem

function Get-XmlNodeText {
    param($Node)

    if ($null -eq $Node) {
        return ''
    }

    if ($Node -is [string]) {
        return [string]$Node
    }

    if ($Node.PSObject.Properties['InnerText']) {
        return [string]$Node.InnerText
    }

    return [string]$Node
}

function Get-WorksheetXml {
    param(
        [System.IO.Compression.ZipArchive]$Zip,
        [string]$EntryName
    )

    $entry = $Zip.GetEntry($EntryName)
    if ($null -eq $entry) {
        throw "Entry workbook tidak ditemukan: $EntryName"
    }

    $reader = New-Object System.IO.StreamReader($entry.Open())
    try {
        return [xml]$reader.ReadToEnd()
    } finally {
        $reader.Close()
    }
}

function Get-SharedStrings {
    param([System.IO.Compression.ZipArchive]$Zip)

    $xml = Get-WorksheetXml -Zip $Zip -EntryName 'xl/sharedStrings.xml'
    $values = @()

    foreach ($node in $xml.sst.si) {
        if ($null -ne $node.t) {
            $values += (Get-XmlNodeText $node.t)
            continue
        }

        if ($null -ne $node.r) {
            $values += (($node.r | ForEach-Object { Get-XmlNodeText $_.t }) -join '')
            continue
        }

        $values += (Get-XmlNodeText $node)
    }

    return ,$values
}

function Convert-CellValue {
    param(
        $Cell,
        [string[]]$SharedStrings
    )

    if ($null -eq $Cell) {
        return ''
    }

    $cellType = if ($Cell.PSObject.Properties['t']) { [string]$Cell.t } else { '' }
    $cellValue = if ($Cell.PSObject.Properties['v']) { [string]$Cell.v } else { '' }

    if ($cellType -eq 's') {
        $index = [int]$cellValue
        if ($index -ge 0 -and $index -lt $SharedStrings.Count) {
            return [string]$SharedStrings[$index]
        }
        return ''
    }

    if ($cellType -eq 'inlineStr') {
        return Get-XmlNodeText $Cell.is
    }

    return $cellValue
}

function Parse-WorkbookRows {
    param([string]$Path)

    $zip = [System.IO.Compression.ZipFile]::OpenRead($Path)
    try {
        $sharedStrings = Get-SharedStrings -Zip $zip
        $rows = @()
        $sheetMap = @{
            'JAN' = 'xl/worksheets/sheet1.xml'
            'FEB' = 'xl/worksheets/sheet2.xml'
            'MARET' = 'xl/worksheets/sheet3.xml'
        }

        foreach ($sheetName in $sheetMap.Keys) {
            $xml = Get-WorksheetXml -Zip $zip -EntryName $sheetMap[$sheetName]

            foreach ($row in $xml.worksheet.sheetData.row) {
                if ([int]$row.r -lt 5) {
                    continue
                }

                $cells = @{}
                foreach ($cell in ($row.c | Where-Object { $_ -is [System.Xml.XmlElement] })) {
                    $column = ([string]$cell.r) -replace '\d', ''
                    $cells[$column] = Convert-CellValue -Cell $cell -SharedStrings $sharedStrings
                }

                $rowNumber = ''
                if ($cells.ContainsKey('A')) {
                    $rowNumber = ([string]$cells['A']).Trim()
                }
                if ($rowNumber -eq '') {
                    continue
                }

                $dateSerial = ''
                if ($cells.ContainsKey('B')) {
                    $dateSerial = ([string]$cells['B']).Trim()
                }
                if ($dateSerial -notmatch '^\d+(\.\d+)?$') {
                    continue
                }

                $tanggal = [datetime]::FromOADate([double]$dateSerial).ToString('yyyy-MM-dd')
                $petugas = if ($cells.ContainsKey('G')) { ([string]$cells['G']).Trim() } else { '' }
                $suhuOk = if ($cells.ContainsKey('C')) { ([string]$cells['C']).Trim() } else { '' }
                $suhuHigh = if ($cells.ContainsKey('D')) { ([string]$cells['D']).Trim() } else { '' }
                $aksesTerkunci = if ($cells.ContainsKey('E')) { ([string]$cells['E']).Trim() } else { '' }
                $aksesTidakTerkunci = if ($cells.ContainsKey('F')) { ([string]$cells['F']).Trim() } else { '' }

                if ($petugas -eq '') {
                    continue
                }

                $rows += [pscustomobject]@{
                    sheet = $sheetName
                    tanggal = $tanggal
                    petugas = $petugas
                    suhu = if ($suhuHigh -ne '') { 'gt_20_21' } else { '20_21' }
                    akses_masuk = if ($aksesTidakTerkunci -ne '') { 'tidak_terkunci' } else { 'terkunci' }
                    signature_base64 = $null
                    status = if ($suhuHigh -ne '' -and $aksesTidakTerkunci -ne '') {
                        'kritikal'
                    } elseif ($suhuHigh -ne '' -or $aksesTidakTerkunci -ne '') {
                        'warning'
                    } else {
                        'normal'
                    }
                    catatan = 'Import histori Excel ' + $sheetName + ' 2026. Jam monitoring tidak tersedia di file sumber dan paraf menyusul manual.'
                }
            }
        }

        return ,$rows
    } finally {
        $zip.Dispose()
    }
}

function Quote-SqlString {
    param([AllowNull()][string]$Value)

    if ($null -eq $Value) {
        return 'NULL'
    }

    return "'" + ($Value -replace "\\", "\\\\" -replace "'", "''") + "'"
}

$rows = Parse-WorkbookRows -Path $WorkbookPath
if ($rows.Count -eq 0) {
    throw 'Tidak ada data Januari-Maret yang berhasil dibaca dari workbook.'
}

$uniqueStaff = $rows.petugas | Sort-Object -Unique
$staffSeed = @(
    @{ nama = 'Fachrie'; nip = 'PET-001'; jabatan = 'Petugas Monitoring' },
    @{ nama = 'Asep Yuga'; nip = 'PET-002'; jabatan = 'Petugas Monitoring' },
    @{ nama = 'Tanzar Bayu'; nip = 'PET-003'; jabatan = 'Petugas Monitoring' },
    @{ nama = 'Abdul Manaf'; nip = 'PET-004'; jabatan = 'Petugas Monitoring' }
)

$sqlLines = New-Object System.Collections.Generic.List[string]
$sqlLines.Add("USE $Database;")
$sqlLines.Add('START TRANSACTION;')
$sqlLines.Add('ALTER TABLE monitoring_ruangan MODIFY signature_base64 LONGTEXT NULL;')
$sqlLines.Add(@"
INSERT INTO monitoring_master_ruangan (nama_ruangan, lokasi, status_aktif, created_at, updated_at)
VALUES ('Ruang Server Utama', 'GEDUNG INSENTIF LAMA', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE lokasi = VALUES(lokasi), status_aktif = VALUES(status_aktif), updated_at = NOW();
"@.Trim())

foreach ($staff in $staffSeed) {
    $sqlLines.Add(
        "INSERT INTO monitoring_master_petugas (nama_lengkap, nip_nik, jabatan, status_aktif, created_at, updated_at) " +
        "VALUES (" + (Quote-SqlString $staff.nama) + ', ' + (Quote-SqlString $staff.nip) + ', ' + (Quote-SqlString $staff.jabatan) + ", 1, NOW(), NOW()) " +
        "ON DUPLICATE KEY UPDATE nama_lengkap = VALUES(nama_lengkap), jabatan = VALUES(jabatan), status_aktif = 1, updated_at = NOW();"
    )
}

$nameList = ($staffSeed | ForEach-Object { Quote-SqlString $_.nama }) -join ', '
$sqlLines.Add("UPDATE monitoring_master_petugas SET status_aktif = CASE WHEN nama_lengkap IN ($nameList) THEN 1 ELSE 0 END, updated_at = NOW();")

foreach ($row in $rows) {
    $sqlLines.Add(@"
INSERT INTO monitoring_ruangan (
    tanggal,
    jam_monitoring,
    ruangan_id,
    suhu,
    akses_masuk,
    petugas_id,
    catatan,
    signature_base64,
    status,
    created_by,
    created_at,
    updated_at,
    ip_address,
    device_info
)
SELECT
    $(Quote-SqlString $row.tanggal),
    '00:00:00',
    room.id,
    $(Quote-SqlString $row.suhu),
    $(Quote-SqlString $row.akses_masuk),
    staff.id,
    $(Quote-SqlString $row.catatan),
    NULL,
    $(Quote-SqlString $row.status),
    'excel-import-q1-2026',
    NOW(),
    NOW(),
    '127.0.0.1',
    'Imported from Excel workbook Monitoring Ruangan Server.xlsx (Q1 2026)'
FROM monitoring_master_ruangan room
INNER JOIN monitoring_master_petugas staff ON staff.nama_lengkap = $(Quote-SqlString $row.petugas)
WHERE room.nama_ruangan = 'Ruang Server Utama'
  AND NOT EXISTS (
      SELECT 1
      FROM monitoring_ruangan existing
      WHERE existing.tanggal = $(Quote-SqlString $row.tanggal)
        AND existing.ruangan_id = room.id
        AND existing.petugas_id = staff.id
        AND existing.created_by = 'excel-import-q1-2026'
  );
"@.Trim())
}

$sqlLines.Add('COMMIT;')

$sqlFile = Join-Path -Path $env:TEMP -ChildPath 'import_monitoring_q1_2026.sql'
[System.IO.File]::WriteAllLines($sqlFile, $sqlLines)

$arguments = @('-u', $DbUser)
if ($DbPassword -ne '') {
    $arguments += "-p$DbPassword"
}
$arguments += $Database

Get-Content -LiteralPath $sqlFile | & $MySqlExe @arguments

Write-Output 'Import selesai.'
Write-Output ("Workbook: {0}" -f $WorkbookPath)
Write-Output ("Total data diimpor/diupsert: {0}" -f $rows.Count)
Write-Output ("Petugas aktif: {0}" -f ($uniqueStaff -join ', '))
