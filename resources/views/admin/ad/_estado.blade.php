{{--
    Estado de la cuenta de Active Directory, para incrustar junto al formulario
    de edición: el técnico ve en la misma pantalla qué le pasa al usuario y
    puede corregirlo sin cambiar de página.

    Espera $estado (array del servicio EstadoCuentaAd) o null si la consulta
    falló, en cuyo caso se muestra el aviso y la edición sigue funcionando.
--}}

@once
@push('styles')
<style>
/* add- : estado de cuenta de Active Directory */
.add-veredicto { border-radius:12px; padding:.95rem 1.1rem; display:flex; gap:.8rem;
                 align-items:flex-start; margin-bottom:1rem }
.add-veredicto i  { font-size:1.5rem; line-height:1; flex:0 0 auto; margin-top:.1rem }
.add-veredicto h5 { font-size:1rem; font-weight:700; margin:0 0 .2rem }
.add-veredicto p  { font-size:.84rem; margin:0; opacity:.92 }
.add-ok       { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d }
.add-aviso    { background:#fffbeb; border:1px solid #fde68a; color:#b45309 }
.add-problema { background:#fef2f2; border:1px solid #fecaca; color:#b91c1c }

.add-card    { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:.9rem 1.05rem; margin-bottom:1rem }
.add-card h6 { font-size:.71rem; font-weight:700; color:#64748b; text-transform:uppercase;
               letter-spacing:.05em; margin-bottom:.7rem }

.add-senal     { display:flex; align-items:flex-start; gap:.6rem; padding:.45rem 0;
                 border-bottom:1px solid #f1f5f9; font-size:.85rem }
.add-senal:last-child { border-bottom:0 }
.add-senal i   { font-size:1rem; flex:0 0 1.25rem; text-align:center; margin-top:.05rem }
.add-senal-tit { font-weight:600; color:#1e293b }
.add-senal-det { font-size:.78rem; color:#64748b }
.add-i-ok       { color:#16a34a }
.add-i-aviso    { color:#d97706 }
.add-i-problema { color:#dc2626 }

.add-dato    { padding:.4rem 0; border-bottom:1px solid #f1f5f9; display:flex;
               justify-content:space-between; gap:.75rem; align-items:baseline }
.add-dato:last-child { border-bottom:0 }
.add-dato dt { font-size:.76rem; color:#94a3b8; font-weight:600; margin:0 }
.add-dato dd { font-size:.82rem; color:#1e293b; margin:0; text-align:right;
               font-variant-numeric:tabular-nums }

.add-pol   { display:flex; gap:1.1rem; flex-wrap:wrap; font-size:.78rem; color:#475569 }
.add-pol b { display:block; font-size:1.1rem; color:#1e293b; font-variant-numeric:tabular-nums }
</style>
@endpush
@endonce

@if(!$estado)
    <div class="alert alert-warning py-2" style="font-size:.82rem">
        <i class="bi bi-exclamation-triangle-fill me-1"></i>
        No se pudo leer el estado de la cuenta en Active Directory. Los datos de edición siguen disponibles.
    </div>
@else
    @php $res = $estado['resumen']; $pol = $estado['politica']; @endphp

    {{-- El veredicto primero: es la respuesta a "¿por qué no puede entrar?" --}}
    <div class="add-veredicto add-{{ $res['nivel'] }}">
        <i class="bi {{ $res['nivel'] === 'ok' ? 'bi-check-circle-fill'
                      : ($res['nivel'] === 'aviso' ? 'bi-exclamation-triangle-fill' : 'bi-x-octagon-fill') }}"></i>
        <div class="flex-fill">
            <h5>{{ $res['titulo'] }}</h5>
            <p>{{ $res['detalle'] }}</p>
        </div>

        {{-- Desbloquear se ofrece aquí y no en la lista de acciones porque es la
             respuesta directa al veredicto: se lee el problema y se resuelve en
             el mismo sitio. Al volver, la ficha se recarga con el estado nuevo. --}}
        @if(($estado['bloqueada'] ?? false) && !empty($rutaDesbloquear))
            <form method="POST" action="{{ $rutaDesbloquear }}" class="flex-shrink-0" data-loader>
                @csrf
                <button type="submit" class="btn btn-danger btn-sm text-nowrap"
                        title="Pone lockoutTime en 0 y libera la cuenta de inmediato">
                    <i class="bi bi-unlock-fill me-1"></i>Desbloquear
                </button>
            </form>
        @endif
    </div>

    @if($estado['aviso'])
        <div class="alert alert-warning py-2" style="font-size:.78rem">
            <i class="bi bi-info-circle-fill me-1"></i>{{ $estado['aviso'] }}
        </div>
    @endif

    <div class="add-card">
        <h6><i class="bi bi-clipboard-check me-1"></i>Revisión</h6>
        @foreach($estado['senales'] as $s)
            <div class="add-senal">
                <i class="bi {{ $s['icono'] }} add-i-{{ $s['nivel'] }}"></i>
                <div>
                    <div class="add-senal-tit">{{ $s['titulo'] }}</div>
                    <div class="add-senal-det">{{ $s['detalle'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="add-card">
        <h6><i class="bi bi-calendar3 me-1"></i>Fechas</h6>
        <dl class="mb-0">
            @foreach($estado['datos'] as $etiqueta => $valor)
                <div class="add-dato">
                    <dt>{{ $etiqueta }}</dt>
                    <dd>{{ $valor }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    @if($estado['intentos'])
    <div class="add-card">
        <h6><i class="bi bi-shield-exclamation me-1"></i>Intentos fallidos por controlador</h6>
        <div class="add-pol">
            @foreach($estado['intentos'] as $host => $n)
                <div>
                    <b class="{{ $n !== null && $pol['intentos'] && $n >= $pol['intentos'] ? 'text-danger' : '' }}">
                        {{ $n === null ? '—' : $n }}
                    </b>
                    <span class="font-monospace" style="font-size:.72rem">{{ $host }}</span>
                </div>
            @endforeach
        </div>
        <div class="text-muted mt-2" style="font-size:.73rem">
            Este contador no se replica entre controladores: el que importa es el más alto.
        </div>
    </div>
    @endif

    @if($pol['intentos'] || $pol['vigencia_dias'])
    <div class="add-card">
        <h6><i class="bi bi-sliders me-1"></i>Política del dominio</h6>
        <div class="add-pol">
            @if($pol['intentos'])      <div><b>{{ $pol['intentos'] }}</b> intentos para bloquear</div> @endif
            @if($pol['duracion_min'])  <div><b>{{ $pol['duracion_min'] }}</b> min de bloqueo</div> @endif
            @if($pol['vigencia_dias']) <div><b>{{ $pol['vigencia_dias'] }}</b> días de vigencia</div> @endif
            @if($pol['largo_min'])     <div><b>{{ $pol['largo_min'] }}</b> caracteres mín.</div> @endif
        </div>
    </div>
    @endif

    {{-- Lo que este tablero no puede responder: si no se dice, el técnico lo
         busca acá y no está. --}}
    <div class="text-muted" style="font-size:.74rem;line-height:1.5">
        <i class="bi bi-lightbulb me-1"></i>
        <strong>Si se bloquea una y otra vez</strong>, la causa no está en Active Directory: suele ser un
        dispositivo con la contraseña antigua guardada —el correo del teléfono, una sesión remota abierta
        o un servicio—. Identificarlo exige revisar el evento 4740 del controlador de dominio principal.
    </div>
@endif
