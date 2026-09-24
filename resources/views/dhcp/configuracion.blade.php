@extends('layouts.app')
@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-gear me-2"></i>DHCP · Configuración</h4>
        <a href="{{ route('dhcp.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Panel
        </a>
    </div>

    <div class="row g-4" style="max-width:920px">

        {{-- Umbral + token --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-semibold py-2" style="font-size:.9rem">
                    <i class="bi bi-sliders me-1 text-primary"></i>Parámetros
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('dhcp.configuracion.guardar') }}" class="mb-4">
                        @csrf
                        <label class="form-label fw-semibold" style="font-size:.85rem">
                            Umbral de inactividad (días)
                        </label>
                        <div class="input-group input-group-sm" style="max-width:220px">
                            <input type="number" name="umbral_dias" class="form-control" min="1" max="3650"
                                   value="{{ old('umbral_dias', $umbral) }}" required>
                            <span class="input-group-text">días</span>
                            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i></button>
                        </div>
                        <div class="form-text">
                            Una reserva se marca "a depurar" si no se ve conectada hace más de este tiempo.
                        </div>
                    </form>

                    <hr>

                    <label class="form-label fw-semibold" style="font-size:.85rem">Token de ingesta</label>
                    @if($token)
                        <div class="input-group input-group-sm mb-2">
                            <input type="text" class="form-control font-monospace" id="tokenField"
                                   value="{{ $token }}" readonly style="font-size:.78rem">
                            <button class="btn btn-outline-secondary" type="button" onclick="copiar('tokenField', this)">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    @else
                        <p class="text-muted small mb-2">Aún no se ha generado un token.</p>
                    @endif
                    <form method="POST" action="{{ route('dhcp.configuracion.guardar') }}"
                          data-confirm="el token actual"
                          data-confirm-verb="regenerar"
                          data-confirm-title="Regenerar token"
                          data-confirm-sub="El script del DHCP dejará de funcionar hasta que actualices el nuevo token."
                          data-confirm-btn="Sí, regenerar"
                          data-confirm-icon="bi-arrow-repeat"
                          data-confirm-color="warning">
                        @csrf
                        <input type="hidden" name="accion" value="regenerar_token">
                        <button type="submit" class="btn btn-outline-warning btn-sm">
                            <i class="bi bi-arrow-repeat me-1"></i>{{ $token ? 'Regenerar token' : 'Generar token' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Endpoint --}}
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent fw-semibold py-2" style="font-size:.9rem">
                    <i class="bi bi-link-45deg me-1 text-primary"></i>Endpoint de ingesta
                </div>
                <div class="card-body">
                    <label class="form-label fw-semibold" style="font-size:.85rem">URL (POST)</label>
                    <div class="input-group input-group-sm mb-3">
                        <input type="text" class="form-control font-monospace" id="endpointField"
                               value="{{ $endpoint }}" readonly style="font-size:.78rem">
                        <button class="btn btn-outline-secondary" type="button" onclick="copiar('endpointField', this)">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                    <p class="text-muted small mb-0">
                        El script del servidor DHCP hace <code>POST</code> a esta URL con el token en la cabecera
                        <code>X-DHCP-Token</code> y el snapshot en formato JSON. Recomendado: cada 2–4 horas
                        vía el Programador de tareas de Windows.
                    </p>
                </div>
            </div>
        </div>

        {{-- Script PowerShell --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent fw-semibold py-2 d-flex justify-content-between align-items-center" style="font-size:.9rem">
                    <span><i class="bi bi-filetype-ps1 me-1 text-primary"></i>Script recolector (PowerShell)</span>
                    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="copiar('scriptField', this)">
                        <i class="bi bi-clipboard me-1"></i>Copiar
                    </button>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        Guárdalo como <code>C:\Scripts\dhcp-collector.ps1</code> en el servidor DHCP, ajusta las
                        variables <code>$Endpoint</code> y <code>$Token</code>, y prográmalo en el Programador de tareas.
                        Requiere el rol/feature de DHCP (los cmdlets <code>DhcpServer</code>).
                    </p>
                    <textarea id="scriptField" class="form-control font-monospace" rows="18"
                              readonly style="font-size:.74rem;white-space:pre;background:#0f172a;color:#e2e8f0"># ============================================================
#  Recolector DHCP -> VTI
#  Programar en el Task Scheduler cada 2-4 horas.
# ============================================================
$Endpoint = "{{ $endpoint }}"
$Token    = "{{ $token ?: 'PEGA-AQUI-EL-TOKEN' }}"

# Log con marca de tiempo (útil para auditar la tarea programada bajo SYSTEM)
$LogDir  = if ($PSScriptRoot) { $PSScriptRoot } else { "C:\Scripts" }
$LogFile = Join-Path $LogDir "dhcp-collector.log"
function Write-Log([string]$msg) {
    $linea = "{0}  {1}" -f (Get-Date -Format "yyyy-MM-dd HH:mm:ss"), $msg
    try { Add-Content -Path $LogFile -Value $linea -Encoding UTF8 } catch {}
    Write-Host $linea
}
Write-Log "=== Inicio (usuario: $env:USERNAME) ==="

# Scopes a los que SÍ se les hace ping (por sufijo del ScopeId).
$PingScopes  = @('32.0','24.0','8.0','2.0')   # Planta Rapel, Oro Verde, Planta Nevado, Datacenter
$PingTimeout = 800

function Test-IPsParallel {
    param([string[]]$Ips, [int]$TimeoutMs = 800)
    $resultado = @{}
    if (-not $Ips -or $Ips.Count -eq 0) { return $resultado }
    $pingers = @(); $tareas = @{}
    foreach ($ip in $Ips) {
        try { $p = New-Object System.Net.NetworkInformation.Ping; $pingers += $p; $tareas[$ip] = $p.SendPingAsync($ip, $TimeoutMs) }
        catch { $resultado[$ip] = $false }
    }
    try { [System.Threading.Tasks.Task]::WaitAll($tareas.Values) } catch {}
    foreach ($ip in $tareas.Keys) { try { $resultado[$ip] = ($tareas[$ip].Result.Status -eq 'Success') } catch { $resultado[$ip] = $false } }
    foreach ($p in $pingers) { $p.Dispose() }
    return $resultado
}

Write-Log "Leyendo scopes del DHCP..."
$scopes = Get-DhcpServerv4Scope
Write-Log "Scopes encontrados: $($scopes.Count)"
$payloadScopes = @()

foreach ($sc in $scopes) {
    $stats = Get-DhcpServerv4ScopeStatistics -ScopeId $sc.ScopeId
    $reservas = Get-DhcpServerv4Reservation -ScopeId $sc.ScopeId
    $leases   = Get-DhcpServerv4Lease -ScopeId $sc.ScopeId

    $scopeStr = "$($sc.ScopeId)"; $hacePing = $false
    foreach ($seg in $PingScopes) { if ($scopeStr.EndsWith(".$seg") -or $scopeStr -eq $seg) { $hacePing = $true; break } }
    $pingMap = @{}
    if ($hacePing) {
        $ips = @($reservas | ForEach-Object { "$($_.IPAddress)" })
        Write-Log "Ping a $scopeStr ($($ips.Count) IPs)..."
        $pingMap = Test-IPsParallel -Ips $ips -TimeoutMs $PingTimeout
    }

    $listaReservas = @()
    foreach ($r in $reservas) {
        $lease = $leases | Where-Object { $_.IPAddress -eq $r.IPAddress } | Select-Object -First 1
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
        if ($hacePing) { $item.ping_ok = [bool]$pingMap["$($r.IPAddress)"] }
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

try { [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12 } catch {}

Write-Log "Enviando POST a $Endpoint ..."
try {
    $resp = Invoke-RestMethod -Uri $Endpoint -Method Post `
        -Headers @{ "X-DHCP-Token" = $Token } `
        -ContentType "application/json; charset=utf-8" `
        -Body ([System.Text.Encoding]::UTF8.GetBytes($payload)) `
        -TimeoutSec 30
    Write-Log "OK - Snapshot enviado: $($payloadScopes.Count) scopes (respuesta: $($resp | ConvertTo-Json -Compress))"
} catch {
    Write-Log "ERROR al enviar a VTI: $($_.Exception.Message)"
    Write-Error "Error al enviar a VTI: $($_.Exception.Message)"
}
Write-Log "=== Fin ==="
</textarea>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
function copiar(id, btn) {
    const el = document.getElementById(id);
    el.select();
    el.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(el.value).then(() => {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg"></i>';
        setTimeout(() => btn.innerHTML = original, 1200);
    });
}
</script>
@endpush
