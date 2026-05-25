param(
    [Parameter(Mandatory = $true)]
    [string]$ServerUrl,

    [Parameter(Mandatory = $true)]
    [string]$Ruangan,

    [Parameter(Mandatory = $true)]
    [string]$TahunInventaris,

    [Parameter(Mandatory = $true)]
    [string]$NamaUser,

    [Parameter(Mandatory = $true)]
    [string]$Kondisi
)

$ErrorActionPreference = 'Stop'

function Write-ScanBanner {
    Write-Host ''
    Write-Host '============================================================' -ForegroundColor DarkCyan
    Write-Host '      PENDATAAN INVENTARIS KOMPUTER - SILEGIT' -ForegroundColor Cyan
    Write-Host '============================================================' -ForegroundColor DarkCyan
    Write-Host ("Komputer    : {0}" -f $env:COMPUTERNAME) -ForegroundColor Gray
    Write-Host ("User Windows: {0}" -f $env:USERNAME) -ForegroundColor Gray
    Write-Host ''
}

function Write-ScanStep {
    param(
        [Parameter(Mandatory = $true)]
        [int]$Number,

        [Parameter(Mandatory = $true)]
        [int]$Total,

        [Parameter(Mandatory = $true)]
        [string]$Title
    )

    Write-Host ("[{0}/{1}] {2}" -f $Number, $Total, $Title) -ForegroundColor Yellow
}

function Write-ScanInfo {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Message
    )

    Write-Host ("    > {0}" -f $Message) -ForegroundColor Gray
}

function Write-ScanSuccess {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Message
    )

    Write-Host ("    OK  {0}" -f $Message) -ForegroundColor Green
}

function Write-ScanFailure {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Message
    )

    Write-Host ("    GAGAL  {0}" -f $Message) -ForegroundColor Red
}

function Get-SystemInstance {
    param(
        [Parameter(Mandatory = $true)]
        [string]$ClassName
    )

    if (Get-Command Get-CimInstance -ErrorAction SilentlyContinue) {
        return Get-CimInstance $ClassName
    }

    return Get-WmiObject -Class $ClassName
}

function Set-BestSecurityProtocol {
    param(
        [Parameter(Mandatory = $true)]
        [string]$TargetUrl
    )

    if ($TargetUrl -notmatch '^https://') {
        return
    }

    try {
        $available = [enum]::GetNames([Net.SecurityProtocolType])
        $selected = 0

        foreach ($name in @('Tls12', 'Tls11', 'Tls')) {
            if ($available -contains $name) {
                $selected = $selected -bor [int][Net.SecurityProtocolType]::$name
            }
        }

        if ($selected -ne 0) {
            [Net.ServicePointManager]::SecurityProtocol = $selected
        }
    } catch {
        # Abaikan jika versi Windows/PowerShell lama tidak mendukung pengaturan ini.
    }
}

function Invoke-JsonPost {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Url,

        [Parameter(Mandatory = $true)]
        [string]$JsonBody
    )

    Set-BestSecurityProtocol -TargetUrl $Url

    if (Get-Command Invoke-RestMethod -ErrorAction SilentlyContinue) {
        return Invoke-RestMethod -Uri $Url -Method Post -Body $JsonBody -ContentType 'application/json'
    }

    $request = [System.Net.WebRequest]::Create($Url)
    $request.Method = 'POST'
    $request.ContentType = 'application/json'
    $bytes = [System.Text.Encoding]::UTF8.GetBytes($JsonBody)
    $request.ContentLength = $bytes.Length

    $requestStream = $request.GetRequestStream()
    $requestStream.Write($bytes, 0, $bytes.Length)
    $requestStream.Close()

    $response = $request.GetResponse()
    $responseStream = $response.GetResponseStream()
    $reader = New-Object System.IO.StreamReader($responseStream)
    $resultText = $reader.ReadToEnd()
    $reader.Close()
    $responseStream.Close()
    $response.Close()

    if (Get-Command ConvertFrom-Json -ErrorAction SilentlyContinue) {
        return $resultText | ConvertFrom-Json
    }

    if ($resultText -match '"success"\s*:\s*true') {
        return @{ success = $true; message = 'OK' }
    }

    $message = 'Gagal mengirim data.'
    if ($resultText -match '"message"\s*:\s*"([^"]+)"') {
        $message = $matches[1]
    }

    return @{ success = $false; message = $message }
}

function Convert-ToGbText {
    param([double]$Bytes)
    if ($Bytes -le 0) {
        return '0 GB'
    }

    return ("{0:N1} GB" -f ($Bytes / 1GB))
}

function Join-DeviceNames {
    param([array]$Items)
    if (-not $Items -or $Items.Count -eq 0) {
        return '-'
    }

    return ($Items | Where-Object { $_ } | Select-Object -Unique) -join ', '
}

function Get-Ipv4Priority {
    param([string]$Value)

    if ([string]::IsNullOrWhiteSpace($Value)) {
        return 99
    }

    if ($Value -notmatch '^\d{1,3}(\.\d{1,3}){3}$' -or $Value -match '^127\.') {
        return 99
    }

    if ($Value -match '^192\.168\.') {
        return 0
    }

    if ($Value -match '^10\.') {
        return 0
    }

    if ($Value -match '^172\.(1[6-9]|2[0-9]|3[0-1])\.') {
        return 0
    }

    if ($Value -match '^169\.254\.') {
        return 3
    }

    return 1
}

function Get-Ipv4NetworkDetails {
    $result = [ordered]@{
        PrimaryIp       = '-'
        AllIps          = @()
        NetworkAdapters = @()
    }

    $entries = @()

    try {
        if (Get-Command Get-NetIPConfiguration -ErrorAction SilentlyContinue) {
            $configs = @(Get-NetIPConfiguration | Where-Object { $_.IPv4Address })
            foreach ($config in $configs) {
                $adapterName = ''
                if ($config.InterfaceAlias) {
                    $adapterName = [string]$config.InterfaceAlias
                } elseif ($config.InterfaceDescription) {
                    $adapterName = [string]$config.InterfaceDescription
                }

                $gateway = if ($config.IPv4DefaultGateway -and $config.IPv4DefaultGateway.NextHop) { [string]$config.IPv4DefaultGateway.NextHop } else { '' }

                foreach ($ipv4 in @($config.IPv4Address)) {
                    $ipAddress = if ($ipv4 -and $ipv4.IPAddress) { [string]$ipv4.IPAddress } else { '' }
                    if ($ipAddress -notmatch '^\d{1,3}(\.\d{1,3}){3}$' -or $ipAddress -match '^127\.') {
                        continue
                    }

                    $entries += [pscustomobject]@{
                        IPAddress   = $ipAddress
                        AdapterName = $adapterName
                        HasGateway  = ($gateway -ne '')
                    }
                }
            }
        }
    } catch {
        # Fallback ke WMI di bawah.
    }

    if ($entries.Count -eq 0) {
        $networkAdapters = @(Get-SystemInstance -ClassName 'Win32_NetworkAdapterConfiguration' |
            Where-Object { $_.IPEnabled -eq $true -and $_.MACAddress })

        foreach ($adapter in $networkAdapters) {
            foreach ($ipAddress in @($adapter.IPAddress)) {
                $ipAddress = [string]$ipAddress
                if ($ipAddress -notmatch '^\d{1,3}(\.\d{1,3}){3}$' -or $ipAddress -match '^127\.') {
                    continue
                }

                $entries += [pscustomobject]@{
                    IPAddress   = $ipAddress
                    AdapterName = [string]$adapter.Description
                    HasGateway  = (@($adapter.DefaultIPGateway).Count -gt 0)
                }
            }
        }
    }

    $entries = @(
        $entries |
            Sort-Object -Property @{ Expression = { Get-Ipv4Priority -Value ([string] $_.IPAddress) } }, @{ Expression = { if ($_.HasGateway) { 0 } else { 1 } } }, IPAddress |
            Select-Object -Unique IPAddress, AdapterName, HasGateway
    )

    if ($entries.Count -gt 0) {
        $result.PrimaryIp = [string]$entries[0].IPAddress
        $result.AllIps = @($entries | ForEach-Object { $_.IPAddress } | Select-Object -Unique)

        $grouped = @{}
        foreach ($entry in $entries) {
            $adapterName = if ([string]::IsNullOrWhiteSpace([string]$entry.AdapterName)) { 'Adapter' } else { [string]$entry.AdapterName }
            if (-not $grouped.ContainsKey($adapterName)) {
                $grouped[$adapterName] = New-Object System.Collections.ArrayList
            }

            if (-not $grouped[$adapterName].Contains([string]$entry.IPAddress)) {
                [void]$grouped[$adapterName].Add([string]$entry.IPAddress)
            }
        }

        $networkAdaptersPayload = @()
        foreach ($adapterName in $grouped.Keys) {
            $networkAdaptersPayload += @{
                adapter_name = $adapterName
                status = 'active'
                ip_addresses = @($grouped[$adapterName])
            }
        }

        $result.NetworkAdapters = @($networkAdaptersPayload)
    }

    return $result
}

try {
    Write-ScanBanner

    $totalSteps = 6

    Write-ScanStep -Number 1 -Total $totalSteps -Title 'Validasi input pendataan'
    if ($Kondisi -notin @('Baik', 'Rusak', 'Perbaikan')) {
        throw "Kondisi komputer tidak valid."
    }
    Write-ScanInfo ("Ruangan: {0}" -f $Ruangan)
    Write-ScanInfo ("Tahun inventaris: {0}" -f $TahunInventaris)
    Write-ScanInfo ("Nama user: {0}" -f $NamaUser)
    Write-ScanInfo ("Kondisi: {0}" -f $Kondisi)
    Write-ScanSuccess 'Validasi input selesai.'

    Write-ScanStep -Number 2 -Total $totalSteps -Title 'Membaca identitas dan sistem operasi'
    $computerSystem = Get-SystemInstance -ClassName 'Win32_ComputerSystem'
    $os = Get-SystemInstance -ClassName 'Win32_OperatingSystem'
    Write-ScanInfo ("Manufacturer / Model: {0} / {1}" -f $computerSystem.Manufacturer, $computerSystem.Model)
    Write-ScanInfo ("OS: {0}" -f $os.Caption)
    Write-ScanSuccess 'Identitas sistem berhasil dibaca.'

    Write-ScanStep -Number 3 -Total $totalSteps -Title 'Membaca hardware utama'
    $processor = Get-SystemInstance -ClassName 'Win32_Processor' | Select-Object -First 1
    $memory = Get-SystemInstance -ClassName 'Win32_PhysicalMemory'
    $baseBoard = Get-SystemInstance -ClassName 'Win32_BaseBoard' | Select-Object -First 1
    $gpu = Get-SystemInstance -ClassName 'Win32_VideoController'
    Write-ScanInfo ("Processor: {0}" -f $processor.Name)
    Write-ScanInfo ("Core: {0}" -f [int]$processor.NumberOfCores)
    Write-ScanInfo ("RAM Total: {0}" -f (Convert-ToGbText (($memory | Measure-Object -Property Capacity -Sum).Sum)))
    Write-ScanInfo ("VGA: {0}" -f (Join-DeviceNames ($gpu | ForEach-Object { $_.Name })))
    Write-ScanSuccess 'Pembacaan hardware utama selesai.'

    Write-ScanStep -Number 4 -Total $totalSteps -Title 'Membaca jaringan dan alamat IP'
    $network = Get-SystemInstance -ClassName 'Win32_NetworkAdapterConfiguration' |
        Where-Object { $_.IPEnabled -eq $true -and $_.MACAddress } |
        Select-Object -First 1
    $networkDetails = Get-Ipv4NetworkDetails
    $ipAddress = $networkDetails.PrimaryIp
    $macAddress = if ($network) { $network.MACAddress } else { '-' }
    Write-ScanInfo ("IP utama: {0}" -f $ipAddress)
    if ($networkDetails.AllIps.Count -gt 1) {
        Write-ScanInfo ("IP tambahan: {0}" -f (($networkDetails.AllIps | Select-Object -Skip 1) -join ', '))
    }
    Write-ScanInfo ("Jumlah IP aktif: {0}" -f $networkDetails.AllIps.Count)
    Write-ScanInfo ("MAC Address: {0}" -f $macAddress)
    Write-ScanSuccess 'Informasi jaringan berhasil dibaca.'

    Write-ScanStep -Number 5 -Total $totalSteps -Title 'Membaca media penyimpanan dan menyusun payload'
    $ssdText = '-'
    $hddText = '-'

    try {
        $physicalDisks = Get-PhysicalDisk
        $ssdDisks = $physicalDisks | Where-Object { $_.MediaType -eq 'SSD' }
        $hddDisks = $physicalDisks | Where-Object { $_.MediaType -eq 'HDD' }

        if ($ssdDisks) {
            $ssdText = Join-DeviceNames ($ssdDisks | ForEach-Object { "$($_.FriendlyName) ($(Convert-ToGbText $_.Size))" })
        }

        if ($hddDisks) {
            $hddText = Join-DeviceNames ($hddDisks | ForEach-Object { "$($_.FriendlyName) ($(Convert-ToGbText $_.Size))" })
        }
    } catch {
        $diskDrives = Get-SystemInstance -ClassName 'Win32_DiskDrive'
        $diskText = Join-DeviceNames ($diskDrives | ForEach-Object { "$($_.Model) ($(Convert-ToGbText $_.Size))" })
        $hddText = $diskText
    }

    $payload = [ordered]@{
        hostname = $env:COMPUTERNAME
        username = $env:USERNAME
        ip_address = $ipAddress
        mac_address = $macAddress
        merk = $computerSystem.Manufacturer
        model = $computerSystem.Model
        processor = $processor.Name
        core = [int]$processor.NumberOfCores
        ram = Convert-ToGbText (($memory | Measure-Object -Property Capacity -Sum).Sum)
        ssd = $ssdText
        hdd = $hddText
        vga = Join-DeviceNames ($gpu | ForEach-Object { $_.Name })
        motherboard = "$($baseBoard.Manufacturer) $($baseBoard.Product)"
        os_name = $os.Caption
        os_version = $os.Version
        architecture = $os.OSArchitecture
        tahun_inventaris = $TahunInventaris
        ruangan = $Ruangan
        petugas = $NamaUser
        kondisi = $Kondisi
        multiple_ip = ($networkDetails.AllIps -join ', ')
        network_adapters = $networkDetails.NetworkAdapters
    }
    Write-ScanInfo ("SSD: {0}" -f $ssdText)
    Write-ScanInfo ("HDD: {0}" -f $hddText)
    Write-ScanInfo ("Payload siap untuk host {0}" -f $payload.hostname)
    Write-ScanSuccess 'Payload inventaris berhasil disusun.'

    Write-ScanStep -Number 6 -Total $totalSteps -Title 'Mengirim data ke server'
    $json = $payload | ConvertTo-Json -Depth 4
    Write-ScanInfo ("Endpoint: {0}" -f $ServerUrl)
    $response = Invoke-JsonPost -Url $ServerUrl -JsonBody $json

    if ($response.success -eq $true) {
        Write-ScanSuccess 'Server menerima data inventaris.'
        Write-Host ''
        Write-Host 'Data berhasil dikirim.' -ForegroundColor Green
        Write-Host "Hostname: $($payload.hostname)"
        Write-Host "IP      : $($payload.ip_address)"
        if ($payload.multiple_ip -and $payload.multiple_ip -ne '') {
            Write-Host "Semua IP: $($payload.multiple_ip)"
        }
        Write-Host "Ruangan : $Ruangan"
        Write-Host "Tahun   : $TahunInventaris"
        Write-Host "Nama User : $NamaUser"
        Write-Host "Kondisi : $Kondisi"
        exit 0
    }

    Write-ScanFailure ("Server menolak data: {0}" -f $response.message)
    Write-Host "Data gagal dikirim: $($response.message)" -ForegroundColor Red
    exit 1
} catch {
    Write-ScanFailure $_.Exception.Message
    Write-Host "Terjadi error: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
