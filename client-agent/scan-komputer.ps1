param(
    [Parameter(Mandatory = $true)]
    [string]$ServerUrl,

    [Parameter(Mandatory = $true)]
    [string]$Ruangan,

    [Parameter(Mandatory = $true)]
    [string]$TahunInventaris,

    [Parameter(Mandatory = $true)]
    [string]$NamaUser
)

$ErrorActionPreference = 'Stop'

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

try {
    $computerSystem = Get-CimInstance Win32_ComputerSystem
    $processor = Get-CimInstance Win32_Processor | Select-Object -First 1
    $memory = Get-CimInstance Win32_PhysicalMemory
    $os = Get-CimInstance Win32_OperatingSystem
    $baseBoard = Get-CimInstance Win32_BaseBoard | Select-Object -First 1
    $gpu = Get-CimInstance Win32_VideoController
    $network = Get-CimInstance Win32_NetworkAdapterConfiguration |
        Where-Object { $_.IPEnabled -eq $true -and $_.MACAddress } |
        Select-Object -First 1

    $ipAddress = '-'
    if ($network -and $network.IPAddress) {
        $ipAddress = ($network.IPAddress | Where-Object { $_ -match '^\d{1,3}(\.\d{1,3}){3}$' } | Select-Object -First 1)
    }

    $macAddress = if ($network) { $network.MACAddress } else { '-' }

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
        $diskDrives = Get-CimInstance Win32_DiskDrive
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
    }

    $json = $payload | ConvertTo-Json -Depth 4
    $response = Invoke-RestMethod -Uri $ServerUrl -Method Post -Body $json -ContentType 'application/json'

    if ($response.success -eq $true) {
        Write-Host 'Data berhasil dikirim.' -ForegroundColor Green
        Write-Host "Hostname: $($payload.hostname)"
        Write-Host "IP      : $($payload.ip_address)"
        Write-Host "Ruangan : $Ruangan"
        Write-Host "Tahun   : $TahunInventaris"
        Write-Host "Nama User : $NamaUser"
        exit 0
    }

    Write-Host "Data gagal dikirim: $($response.message)" -ForegroundColor Red
    exit 1
} catch {
    Write-Host "Terjadi error: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
