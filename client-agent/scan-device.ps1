param(
    [Parameter(Mandatory = $true)]
    [string]$ServerUrl,

    [string]$UploadToken = '',

    [Parameter(Mandatory = $true)]
    [string]$DeviceType,

    [Parameter(Mandatory = $true)]
    [string]$Ruangan,

    [Parameter(Mandatory = $true)]
    [string]$TahunInventaris,

    [Parameter(Mandatory = $true)]
    [string]$NamaUser,

    [Parameter(Mandatory = $true)]
    [string]$Kondisi,

    [string]$JenisServer = '',
    [string]$FungsiServer = '',
    [string]$VirtualFisik = '',
    [string]$OperatingSystem = '',
    [string]$LokasiRack = '',
    [string]$IpUtama = ''
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
        throw 'Endpoint upload harus menggunakan HTTPS.'
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

function Test-ValidIpv4Address {
    param([string]$Value)

    if ([string]::IsNullOrWhiteSpace($Value)) {
        return $false
    }

    if ($Value -notmatch '^\d{1,3}(\.\d{1,3}){3}$') {
        return $false
    }

    if ($Value -match '^127\.') {
        return $false
    }

    return $true
}

function Get-PreferredIpv4Addresses {
    $preferred = @()

    try {
        if (Get-Command Get-NetIPConfiguration -ErrorAction SilentlyContinue) {
            $configs = @(Get-NetIPConfiguration | Where-Object { $_.IPv4Address })

            foreach ($config in $configs) {
                $gateway = ''
                if ($config.IPv4DefaultGateway -and $config.IPv4DefaultGateway.NextHop) {
                    $gateway = [string] $config.IPv4DefaultGateway.NextHop
                }

                foreach ($ipv4 in @($config.IPv4Address)) {
                    $ipAddress = ''
                    if ($ipv4 -and $ipv4.IPAddress) {
                        $ipAddress = [string] $ipv4.IPAddress
                    }
                    if (-not (Test-ValidIpv4Address -Value $ipAddress)) {
                        continue
                    }

                    $adapterName = ''
                    if ($config.InterfaceAlias) {
                        $adapterName = [string] $config.InterfaceAlias
                    } elseif ($config.InterfaceDescription) {
                        $adapterName = [string] $config.InterfaceDescription
                    }

                    $prefixLength = 0
                    if ($ipv4 -and $null -ne $ipv4.PrefixLength) {
                        $prefixLength = [int] $ipv4.PrefixLength
                    }

                    $preferred += [pscustomobject]@{
                        IPAddress    = $ipAddress
                        AdapterName  = $adapterName
                        HasGateway   = ($gateway -ne '')
                        Gateway      = $gateway
                        PrefixLength = $prefixLength
                    }
                }
            }
        }
    } catch {
        # Abaikan dan lanjut ke fallback WMI.
    }

    if ($preferred.Count -eq 0) {
        try {
            $adapters = @(Get-SystemInstance -ClassName 'Win32_NetworkAdapterConfiguration' |
                Where-Object { $_.IPEnabled -eq $true -and $_.MACAddress })

            foreach ($adapter in $adapters) {
                $gateway = ''
                if ($adapter.DefaultIPGateway) {
                    $gateway = [string] (@($adapter.DefaultIPGateway) | Select-Object -First 1)
                }

                foreach ($ipAddress in @($adapter.IPAddress)) {
                    $ipAddress = [string] $ipAddress
                    if (-not (Test-ValidIpv4Address -Value $ipAddress)) {
                        continue
                    }

                    $adapterName = ''
                    if ($adapter.Description) {
                        $adapterName = [string] $adapter.Description
                    }

                    $preferred += [pscustomobject]@{
                        IPAddress    = $ipAddress
                        AdapterName  = $adapterName
                        HasGateway   = ($gateway -ne '')
                        Gateway      = $gateway
                        PrefixLength = 0
                    }
                }
            }
        } catch {
            # Fallback terakhir akan ditangani di bawah bila kosong.
        }
    }

    $preferred = @(
        $preferred |
            Sort-Object -Property @{ Expression = { if ($_.HasGateway) { 0 } else { 1 } } }, IPAddress |
            Select-Object -Unique IPAddress, AdapterName, HasGateway, Gateway, PrefixLength
    )

    return $preferred
}

function Get-UptimeText {
    param([Parameter(Mandatory = $true)]$OperatingSystemObject)

    try {
        $bootTime = $OperatingSystemObject.LastBootUpTime
        if ($bootTime -is [string]) {
            $bootTime = [Management.ManagementDateTimeConverter]::ToDateTime($bootTime)
        }

        $duration = (Get-Date) - $bootTime
        return ('{0} hari {1} jam {2} menit' -f [int]$duration.TotalDays, $duration.Hours, $duration.Minutes)
    } catch {
        return '-'
    }
}

function ConvertTo-JsonCompat {
    param(
        [Parameter(Mandatory = $true)]
        [object]$InputObject
    )

    if (Get-Command ConvertTo-Json -ErrorAction SilentlyContinue) {
        return $InputObject | ConvertTo-Json -Depth 6 -Compress
    }

    Add-Type -AssemblyName System.Web.Extensions
    $serializer = New-Object System.Web.Script.Serialization.JavaScriptSerializer
    $serializer.MaxJsonLength = 67108864
    return $serializer.Serialize($InputObject)
}

function Get-HmacSha256 {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Secret,

        [Parameter(Mandatory = $true)]
        [string]$Message
    )

    $encoding = New-Object System.Text.UTF8Encoding
    $secretBytes = $encoding.GetBytes($Secret)

    # Gunakan constructor yang kompatibel agar PowerShell tidak memecah byte[] menjadi banyak argumen.
    $hmac = New-Object System.Security.Cryptography.HMACSHA256 -ArgumentList (, $secretBytes)

    try {
        $hashBytes = $hmac.ComputeHash($encoding.GetBytes($Message))
        return ([BitConverter]::ToString($hashBytes)).Replace('-', '').ToLowerInvariant()
    } finally {
        if ($hmac) {
            $hmac.Dispose()
        }
    }
}

function Invoke-JsonPost {
    param(
        [Parameter(Mandatory = $true)]
        [string]$Url,

        [Parameter(Mandatory = $true)]
        [string]$JsonBody,

        [Parameter(Mandatory = $true)]
        [hashtable]$Headers
    )

    Set-BestSecurityProtocol -TargetUrl $Url

    if (Get-Command Invoke-RestMethod -ErrorAction SilentlyContinue) {
        if ($Headers.Count -gt 0) {
            return Invoke-RestMethod -Uri $Url -Method Post -Body $JsonBody -ContentType 'application/json' -Headers $Headers -TimeoutSec 60
        }

        return Invoke-RestMethod -Uri $Url -Method Post -Body $JsonBody -ContentType 'application/json' -TimeoutSec 60
    }

    $request = [System.Net.WebRequest]::Create($Url)
    $request.Method = 'POST'
    $request.ContentType = 'application/json'
    $request.Timeout = 60000
    $request.ReadWriteTimeout = 60000

    foreach ($key in $Headers.Keys) {
        $request.Headers.Add($key, [string]$Headers[$key])
    }

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

function Get-StorageBreakdown {
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
        $ssdCandidates = @()
        $hddCandidates = @()

        foreach ($disk in $diskDrives) {
            $entry = "$($disk.Model) ($(Convert-ToGbText $disk.Size))"
            $signature = (($disk.Model + ' ' + $disk.Caption + ' ' + $disk.InterfaceType) | Out-String).ToLowerInvariant()

            if ($signature -match 'ssd|nvme') {
                $ssdCandidates += $entry
            } else {
                $hddCandidates += $entry
            }
        }

        if ($ssdCandidates.Count -gt 0) {
            $ssdText = Join-DeviceNames $ssdCandidates
        }

        if ($hddCandidates.Count -gt 0) {
            $hddText = Join-DeviceNames $hddCandidates
        }
    }

    return @{
        ssd = $ssdText
        hdd = $hddText
    }
}

function Get-RaidText {
    try {
        $controllers = Get-SystemInstance -ClassName 'Win32_PnPEntity' | Where-Object {
            $_.Name -and $_.Name.ToLowerInvariant() -match 'raid|sas|smart array|megaraid|perc'
        }

        if ($controllers) {
            return Join-DeviceNames ($controllers | ForEach-Object { $_.Name })
        }
    } catch {
        # Abaikan dan pakai fallback di bawah.
    }

    return '-'
}

function Get-VirtualizationKind {
    param(
        [Parameter(Mandatory = $true)]
        $ComputerSystem
    )

    $signature = (($ComputerSystem.Manufacturer + ' ' + $ComputerSystem.Model) | Out-String).ToLowerInvariant()
    if ($signature -match 'vmware|virtual|hyper-v|kvm|xen|virtualbox|qemu') {
        return 'VIRTUAL'
    }

    return 'FISIK'
}

function Get-HypervisorName {
    param(
        [Parameter(Mandatory = $true)]
        $ComputerSystem
    )

    $signature = (($ComputerSystem.Manufacturer + ' ' + $ComputerSystem.Model) | Out-String).Trim()
    $normalized = $signature.ToLowerInvariant()

    if ($normalized -match 'vmware') {
        return 'VMware'
    }
    if ($normalized -match 'hyper-v') {
        return 'Hyper-V'
    }
    if ($normalized -match 'virtualbox') {
        return 'VirtualBox'
    }
    if ($normalized -match 'kvm|qemu') {
        return 'KVM'
    }
    if ($normalized -match 'xen') {
        return 'Xen'
    }

    if ($normalized -match 'virtual') {
        return $signature
    }

    return ''
}

function Get-ServerRoleText {
    param([string]$FallbackRole)

    if ($FallbackRole -ne '') {
        return $FallbackRole
    }

    try {
        $features = Get-SystemInstance -ClassName 'Win32_ServerFeature' | Select-Object -First 6
        if ($features) {
            return Join-DeviceNames ($features | ForEach-Object { $_.Name })
        }
    } catch {
        # Abaikan dan pakai fallback.
    }

    return '-'
}

function Get-NetworkAdapterPayload {
    param([array]$Adapters)

    $items = @()

    foreach ($adapter in $Adapters) {
        $adapterIps = @()
        if ($adapter.IPAddress) {
            $adapterIps = @($adapter.IPAddress | Where-Object { $_ -match '^\d{1,3}(\.\d{1,3}){3}$|:' })
        }

        if ($adapterIps.Count -eq 0) {
            continue
        }

        $items += @{
            adapter_name = [string]($adapter.Description)
            status = 'active'
            ip_addresses = @($adapterIps | Select-Object -Unique)
        }
    }

    return @($items)
}

function Get-NetworkAdapterPayloadFromPreferredEntries {
    param([array]$Entries)

    $grouped = @{}

    foreach ($entry in @($Entries)) {
        if (-not $entry) {
            continue
        }

        $ipAddress = [string]($entry.IPAddress)
        if (-not (Test-ValidIpv4Address -Value $ipAddress)) {
            continue
        }

        $adapterName = [string]($entry.AdapterName)
        if ([string]::IsNullOrWhiteSpace($adapterName)) {
            $adapterName = 'Adapter'
        }

        if (-not $grouped.ContainsKey($adapterName)) {
            $grouped[$adapterName] = New-Object System.Collections.ArrayList
        }

        if (-not $grouped[$adapterName].Contains($ipAddress)) {
            [void]$grouped[$adapterName].Add($ipAddress)
        }
    }

    $items = @()
    foreach ($adapterName in $grouped.Keys) {
        $items += @{
            adapter_name = $adapterName
            status = 'active'
            ip_addresses = @($grouped[$adapterName])
        }
    }

    return @($items)
}

try {
    if ($Kondisi -notin @('Baik', 'Rusak', 'Perbaikan')) {
        throw 'Kondisi device tidak valid.'
    }

    if ($DeviceType -notin @('CLIENT', 'SERVER')) {
        throw 'Tipe device tidak valid.'
    }

    $computerSystem = Get-SystemInstance -ClassName 'Win32_ComputerSystem'
    $processors = @(Get-SystemInstance -ClassName 'Win32_Processor')
    $memory = @(Get-SystemInstance -ClassName 'Win32_PhysicalMemory')
    $os = Get-SystemInstance -ClassName 'Win32_OperatingSystem'
    $baseBoard = Get-SystemInstance -ClassName 'Win32_BaseBoard' | Select-Object -First 1
    $bios = Get-SystemInstance -ClassName 'Win32_BIOS' | Select-Object -First 1
    $gpu = @(Get-SystemInstance -ClassName 'Win32_VideoController')
    $networkAdapters = @(Get-SystemInstance -ClassName 'Win32_NetworkAdapterConfiguration' |
        Where-Object { $_.IPEnabled -eq $true -and $_.MACAddress })
    $primaryNetwork = $networkAdapters | Select-Object -First 1
    $preferredIpv4Entries = @(Get-PreferredIpv4Addresses)
    $ipv4Addresses = @($preferredIpv4Entries | ForEach-Object { $_.IPAddress } | Where-Object { $_ } | Select-Object -Unique)
    $nicDescriptions = @()

    foreach ($adapter in $networkAdapters) {
        $nicLabel = @($adapter.Description, $adapter.MACAddress) | Where-Object { $_ -and $_ -ne '' }
        if ($nicLabel.Count -gt 0) {
            $nicDescriptions += ($nicLabel -join ' | ')
        }
    }

    $multipleNic = Join-DeviceNames ($nicDescriptions | Select-Object -Unique)
    $detectedIp = if ($ipv4Addresses.Count -gt 0) { [string] $ipv4Addresses[0] } else { '-' }
    $macAddress = if ($primaryNetwork) { $primaryNetwork.MACAddress } else { '-' }

    $storageBreakdown = Get-StorageBreakdown
    $detectedVirtualization = Get-VirtualizationKind -ComputerSystem $computerSystem
    $resolvedVirtualization = if ($DeviceType -eq 'SERVER' -and $VirtualFisik -ne '') { $VirtualFisik } else { $detectedVirtualization }
    $resolvedHypervisor = if ($resolvedVirtualization -eq 'VIRTUAL') { Get-HypervisorName -ComputerSystem $computerSystem } else { '' }
    $resolvedOsName = if ($DeviceType -eq 'SERVER' -and $OperatingSystem -ne '') { $OperatingSystem } else { $os.Caption }
    $resolvedIp = if ($DeviceType -eq 'SERVER' -and $IpUtama -ne '') { $IpUtama } else { $detectedIp }
    $serialNumber = if ($bios.SerialNumber) { $bios.SerialNumber } else { '-' }
    $totalCore = ($processors | Measure-Object -Property NumberOfCores -Sum).Sum
    $totalThread = ($processors | Measure-Object -Property NumberOfLogicalProcessors -Sum).Sum
    $serverRole = if ($DeviceType -eq 'SERVER') { Get-ServerRoleText -FallbackRole $FungsiServer } else { '' }

    $clientNetworkAdapters = @()
    $clientMultipleNic = $multipleNic
    $clientMultipleIp = ($ipv4Addresses -join ', ')

    if ($preferredIpv4Entries.Count -gt 0) {
        $clientNetworkAdapters = Get-NetworkAdapterPayloadFromPreferredEntries -Entries $preferredIpv4Entries
    }

    if ($clientNetworkAdapters.Count -eq 0) {
        $clientNetworkAdapters = Get-NetworkAdapterPayload -Adapters $networkAdapters
    }

    if ($DeviceType -eq 'CLIENT' -and $clientMultipleNic -eq '-') {
        $clientMultipleNic = ''
    }

    if ($DeviceType -eq 'CLIENT' -and $clientMultipleIp -eq '-') {
        $clientMultipleIp = ''
    }

    $payload = [ordered]@{
        device_type = $DeviceType
        hostname = $env:COMPUTERNAME
        username = $env:USERNAME
        ip_address = $resolvedIp
        mac_address = $macAddress
        merk = $computerSystem.Manufacturer
        model = $computerSystem.Model
        processor = Join-DeviceNames ($processors | ForEach-Object { $_.Name })
        core = [int]$totalCore
        total_thread = [int]$totalThread
        ram = Convert-ToGbText (($memory | Measure-Object -Property Capacity -Sum).Sum)
        ssd = $storageBreakdown.ssd
        hdd = $storageBreakdown.hdd
        vga = Join-DeviceNames ($gpu | ForEach-Object { $_.Name })
        motherboard = "$($baseBoard.Manufacturer) $($baseBoard.Product)"
        serial_number = $serialNumber
        os_name = $resolvedOsName
        os_version = $os.Version
        architecture = $os.OSArchitecture
        tahun_inventaris = $TahunInventaris
        ruangan = $Ruangan
        petugas = $NamaUser
        kondisi = $Kondisi
        virtualization = if ($DeviceType -eq 'SERVER') { $resolvedVirtualization } else { '' }
        raid = if ($DeviceType -eq 'SERVER') { Get-RaidText } else { '' }
        hypervisor = if ($DeviceType -eq 'SERVER') { $resolvedHypervisor } else { '' }
        uptime = if ($DeviceType -eq 'SERVER') { Get-UptimeText -OperatingSystemObject $os } else { '' }
        multiple_nic = $clientMultipleNic
        multiple_ip = $clientMultipleIp
        domain_joined = if ($DeviceType -eq 'SERVER' -and $computerSystem.PartOfDomain) { 'Ya' } else { 'Tidak' }
        server_role = if ($DeviceType -eq 'SERVER') { $serverRole } else { '' }
        jenis_server = if ($DeviceType -eq 'SERVER') { $JenisServer } else { '' }
        fungsi_server = if ($DeviceType -eq 'SERVER') { $FungsiServer } else { '' }
        virtual_fisik = if ($DeviceType -eq 'SERVER') { $resolvedVirtualization } else { '' }
        lokasi_rack = if ($DeviceType -eq 'SERVER') { $LokasiRack } else { '' }
        ip_utama = if ($DeviceType -eq 'SERVER') { $IpUtama } else { '' }
        network_adapters = $clientNetworkAdapters
        sent_at = (Get-Date).ToUniversalTime().ToString('o')
    }

    $json = ConvertTo-JsonCompat -InputObject $payload
    $headers = @{}

    if ($UploadToken -ne '') {
        $bodyHash = Get-HmacSha256 -Secret $UploadToken -Message $json
        $headers['X-Device-Token'] = $UploadToken
        $headers['X-Device-Hash'] = $bodyHash
        $headers['X-Requested-With'] = 'SiAEGIS-Agent'
    }

    $response = Invoke-JsonPost -Url $ServerUrl -JsonBody $json -Headers $headers

    if ($response.success -eq $true) {
        Write-Host 'Data berhasil dikirim.' -ForegroundColor Green
        Write-Host "Hostname: $($payload.hostname)"
        Write-Host "IP Address: $($payload.ip_address)"
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
