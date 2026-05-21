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
            Sort-Object -Property @{ Expression = { if ($_.HasGateway) { 0 } else { 1 } } }, IPAddress |
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
    if ($Kondisi -notin @('Baik', 'Rusak', 'Perbaikan')) {
        throw "Kondisi komputer tidak valid."
    }

    $computerSystem = Get-SystemInstance -ClassName 'Win32_ComputerSystem'
    $processor = Get-SystemInstance -ClassName 'Win32_Processor' | Select-Object -First 1
    $memory = Get-SystemInstance -ClassName 'Win32_PhysicalMemory'
    $os = Get-SystemInstance -ClassName 'Win32_OperatingSystem'
    $baseBoard = Get-SystemInstance -ClassName 'Win32_BaseBoard' | Select-Object -First 1
    $gpu = Get-SystemInstance -ClassName 'Win32_VideoController'
    $network = Get-SystemInstance -ClassName 'Win32_NetworkAdapterConfiguration' |
        Where-Object { $_.IPEnabled -eq $true -and $_.MACAddress } |
        Select-Object -First 1
    $networkDetails = Get-Ipv4NetworkDetails
    $ipAddress = $networkDetails.PrimaryIp
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

    $json = $payload | ConvertTo-Json -Depth 4
    $response = Invoke-JsonPost -Url $ServerUrl -JsonBody $json

    if ($response.success -eq $true) {
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

    Write-Host "Data gagal dikirim: $($response.message)" -ForegroundColor Red
    exit 1
} catch {
    Write-Host "Terjadi error: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
