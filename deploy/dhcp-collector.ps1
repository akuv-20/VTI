# ============================================================
#  Recolector DHCP -> VTI
#  Ejecutar en el servidor DHCP (Windows Server con rol DHCP).
#  Programar en el Task Scheduler cada 2-4 horas.
#
#  Ajusta $Endpoint y $Token con los valores de
#  VTI -> DHCP -> Configuración.
# ============================================================
$Endpoint = "https://TU-SERVIDOR-VTI/api/dhcp/importar"
$Token    = "PEGA-AQUI-EL-TOKEN"

# Scopes a los que SÍ se les hace ping (por sufijo del ScopeId).
# Ej: '32.0' pingea el scope que termine en .32.0 (10.x.32.0 / 172.x.32.0, etc.)
# Los scopes que no estén aquí NO se pingean (se reportan solo por lease DHCP).
$PingScopes  = @('32.0','24.0','8.0','2.0')   # Planta Rapel, Oro Verde, Planta el Nevado, Datacenter
$PingTimeout = 800   # milisegundos por IP

# ── Ping paralelo (rápido, aunque haya muchas IPs muertas) ───────────────────
function Test-IPsParallel {
    param([string[]]$Ips, [int]$TimeoutMs = 800)
    $resultado = @{}
    if (-not $Ips -or $Ips.Count -eq 0) { return $resultado }
    $pingers = @()
    $tareas  = @{}
    foreach ($ip in $Ips) {
        try {
            $p = New-Object System.Net.NetworkInformation.Ping
            $pingers += $p
            $tareas[$ip] = $p.SendPingAsync($ip, $TimeoutMs)
        } catch {
            $resultado[$ip] = $false
        }
    }
    try { [System.Threading.Tasks.Task]::WaitAll($tareas.Values) } catch {}
    foreach ($ip in $tareas.Keys) {
        try { $resultado[$ip] = ($tareas[$ip].Result.Status -eq 'Success') }
        catch { $resultado[$ip] = $false }
    }
    foreach ($p in $pingers) { $p.Dispose() }
    return $resultado
}

$scopes = Get-DhcpServerv4Scope
$payloadScopes = @()

foreach ($sc in $scopes) {
    $stats    = Get-DhcpServerv4ScopeStatistics -ScopeId $sc.ScopeId
    $reservas = Get-DhcpServerv4Reservation -ScopeId $sc.ScopeId
    $leases   = Get-DhcpServerv4Lease -ScopeId $sc.ScopeId

    # ¿Este scope se pingea?
    $scopeStr = "$($sc.ScopeId)"
    $hacePing = $false
    foreach ($seg in $PingScopes) {
        if ($scopeStr.EndsWith(".$seg") -or $scopeStr -eq $seg) { $hacePing = $true; break }
    }

    # Ping en bloque a todas las IPs reservadas del scope
    $pingMap = @{}
    if ($hacePing) {
        $ips = @($reservas | ForEach-Object { "$($_.IPAddress)" })
        $pingMap = Test-IPsParallel -Ips $ips -TimeoutMs $PingTimeout
    }

    $listaReservas = @()
    foreach ($r in $reservas) {
        $lease  = $leases | Where-Object { $_.IPAddress -eq $r.IPAddress } | Select-Object -First 1
        $activa = $false
        $expira = $null
        if ($lease) {
            $activa = @('Active','ActiveReservation') -contains "$($lease.AddressState)"
            if ($lease.LeaseExpiryTime) { $expira = $lease.LeaseExpiryTime.ToString("s") }
        }

        $item = [ordered]@{
            ip           = "$($r.IPAddress)"
            mac          = "$($r.ClientId)"
            nombre       = "$($r.Name)"
            descripcion  = "$($r.Description)"
            activa       = $activa
            lease_expira = $expira
        }
        # Solo agrega ping_ok si este scope se pingeó
        if ($hacePing) {
            $item.ping_ok = [bool]$pingMap["$($r.IPAddress)"]
        }
        $listaReservas += $item
    }

    $payloadScopes += [ordered]@{
        scope_id          = "$($sc.ScopeId)"
        nombre            = "$($sc.Name)"
        descripcion       = "$($sc.Description)"
        subnet_mask       = "$($sc.SubnetMask)"
        rango_inicio      = "$($sc.StartRange)"
        rango_fin         = "$($sc.EndRange)"
        estado            = "$($sc.State)"
        total_direcciones = [int]($stats.InUse + $stats.Free)
        en_uso            = [int]$stats.InUse
        libres            = [int]$stats.Free
        porcentaje_uso    = [double]$stats.PercentageInUse
        reservas          = $listaReservas
    }
}

$payload = [ordered]@{
    generado_at = (Get-Date).ToString("s")
    scopes      = $payloadScopes
} | ConvertTo-Json -Depth 6

try {
    Invoke-RestMethod -Uri $Endpoint -Method Post `
        -Headers @{ "X-DHCP-Token" = $Token } `
        -ContentType "application/json; charset=utf-8" `
        -Body ([System.Text.Encoding]::UTF8.GetBytes($payload))
    Write-Host "Snapshot enviado: $($payloadScopes.Count) scopes"
} catch {
    Write-Error "Error al enviar a VTI: $($_.Exception.Message)"
}
