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

$scopes = Get-DhcpServerv4Scope
$payloadScopes = @()

foreach ($sc in $scopes) {
    $stats    = Get-DhcpServerv4ScopeStatistics -ScopeId $sc.ScopeId
    $reservas = Get-DhcpServerv4Reservation -ScopeId $sc.ScopeId
    $leases   = Get-DhcpServerv4Lease -ScopeId $sc.ScopeId

    $listaReservas = @()
    foreach ($r in $reservas) {
        $lease  = $leases | Where-Object { $_.IPAddress -eq $r.IPAddress } | Select-Object -First 1
        $activa = $false
        $expira = $null
        if ($lease) {
            $activa = @('Active','ActiveReservation') -contains "$($lease.AddressState)"
            if ($lease.LeaseExpiryTime) { $expira = $lease.LeaseExpiryTime.ToString("s") }
        }
        $listaReservas += [ordered]@{
            ip           = "$($r.IPAddress)"
            mac          = "$($r.ClientId)"
            nombre       = "$($r.Name)"
            descripcion  = "$($r.Description)"
            activa       = $activa
            lease_expira = $expira
        }
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
