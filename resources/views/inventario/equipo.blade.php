@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4 class="d-flex align-items-center gap-2 flex-wrap">
            <span><i class="bi bi-display-fill me-2" style="color:{{ $dom->color() }}"></i>{{ $equipo->nombre_equipo }}</span>
            @include('inventario._dominio', ['seccion' => 'equipos'])
        </h4>
        <a href="{{ route("inventario.{$dom->clave}.equipos") }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>

    <div class="row g-3">

        {{-- Ficha del equipo --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-3 mb-3">
                <div class="card-header bg-white fw-semibold border-bottom py-2">
                    <i class="bi bi-info-circle me-1 text-primary"></i> Datos del Equipo
                </div>
                <div class="card-body">
                    <div class="row g-2" style="font-size:.86rem">
                        @php
                            $campos = [
                                'Nombre'            => $equipo->nombre_equipo,
                                'Tipo'              => $equipo->tipo ?? null,
                                'Marca'             => $equipo->marca ?? null,
                                'Modelo'            => $equipo->modelo ?? null,
                                'N° Serie'          => $equipo->numero_serie ?? null,
                                'N° Inventario'     => $equipo->numero_inventario ?? null,
                                'Sistema Operativo' => $equipo->sistema_operativo ?? null,
                                'Procesador'        => $hardware['procesador'] ?? null,
                                'Memoria RAM'       => $hardware['ram'] ?? null,
                                'Disco Principal'   => $hardware['disco'] ?? null,
                                'Ubicación'         => $equipo->ubicacion ?? null,
                            ];
                        @endphp
                        @foreach($campos as $label => $valor)
                        <div class="col-6">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">{{ $label }}</div>
                            <div class="fw-semibold">{{ $valor ?: '—' }}</div>
                        </div>
                        @endforeach
                        @if($equipo->comment)
                        <div class="col-12 mt-1">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Comentario</div>
                            <div>{{ $equipo->comment }}</div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Usuario asignado --}}
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white fw-semibold border-bottom py-2">
                    <i class="bi bi-person-fill me-1 text-success"></i> Usuario Asignado
                </div>
                <div class="card-body" style="font-size:.86rem">
                    @if(trim($equipo->nombre_usuario ?? ''))
                        <div class="fw-bold fs-6">{{ $equipo->nombre_usuario }}</div>
                        @if($equipo->telefono_usuario)
                            <div class="text-muted"><i class="bi bi-telephone me-1"></i>{{ $equipo->telefono_usuario }}</div>
                        @endif
                    @else
                        <span class="text-muted fst-italic">Sin usuario asignado</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Panel derecho --}}
        <div class="col-lg-5">

            {{-- Agente de inventario --}}
            <div class="card border-0 shadow-sm rounded-3 mb-3">
                <div class="card-header bg-white fw-semibold border-bottom py-2">
                    <i class="bi bi-hdd-network-fill me-1 text-primary"></i> Agente de Inventario
                </div>
                <div class="card-body" style="font-size:.86rem">
                    @if($agente)
                        @php
                            $ultimo = $agente->last_contact ? \Carbon\Carbon::parse($agente->last_contact) : null;
                            $dias   = $ultimo ? (int) floor($ultimo->diffInDays(now())) : null;
                            $mudo   = $dias === null || $dias > $diasAgente;
                        @endphp

                        <div class="d-flex align-items-center gap-2 mb-3">
                            @if($mudo)
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                    <i class="bi bi-volume-mute me-1"></i>Sin reportar
                                </span>
                                <span class="text-muted" style="font-size:.8rem">
                                    supera los {{ $diasAgente }} días configurados
                                </span>
                            @else
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="bi bi-check-circle-fill me-1"></i>Reportando
                                </span>
                            @endif
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Último reporte</div>
                                <div class="fw-semibold {{ $mudo ? 'text-danger' : '' }}">
                                    @if($ultimo)
                                        {{ $ultimo->format('d/m/Y H:i') }}
                                        <div class="fw-normal text-muted" style="font-size:.76rem">hace {{ number_format($dias) }} días</div>
                                    @else
                                        —
                                    @endif
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Versión</div>
                                <div class="fw-semibold">{{ $agente->version ?: '—' }}</div>
                            </div>
                            @if($agente->deviceid)
                            <div class="col-12">
                                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Device ID</div>
                                <div class="font-monospace" style="font-size:.76rem;word-break:break-all">{{ $agente->deviceid }}</div>
                            </div>
                            @endif
                        </div>
                    @else
                        <div class="alert alert-warning no-autodismiss d-flex gap-2 mb-0" style="font-size:.85rem">
                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
                            <div>
                                <strong>Sin agente instalado.</strong>
                                Este equipo existe en GLPI pero ningún agente ha reportado por él.
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Antivirus --}}
            <div class="card border-0 shadow-sm rounded-3 mb-3">
                <div class="card-header bg-white fw-semibold border-bottom py-2">
                    <i class="bi bi-shield-fill-check me-1 text-success"></i> Antivirus
                </div>
                <div class="card-body" style="font-size:.86rem">
                    @php
                        $avNombre = $dom->antivirus();
                        $avLista  = $dom->antivirusLista();
                        // Protegido si tiene activo CUALQUIERA de los antivirus
                        // corporativos del dominio (Bitdefender o ESET en Verfrut).
                        $avActivo = !empty($avLista) && $antivirus->first(function ($a) use ($avLista) {
                            if ((int) $a->is_active !== 1) return false;
                            foreach ($avLista as $n) {
                                if (str_contains(strtoupper($a->name), strtoupper($n))) return true;
                            }
                            return false;
                        });
                        // Respaldo: el producto está entre el software instalado
                        // aunque el agente no lo haya reportado en la tabla de AV.
                        $avPorSoftware = !empty($avLista) && $avSoftware->isNotEmpty();
                        $avOk = $avActivo || $avPorSoftware;
                    @endphp

                    @if($avNombre && !$avOk)
                        <div class="alert alert-danger no-autodismiss d-flex gap-2 mb-3" style="font-size:.85rem">
                            <i class="bi bi-shield-exclamation flex-shrink-0 mt-1"></i>
                            <div>
                                <strong>Sin {{ $avNombre }} activo.</strong>
                                Este equipo no tiene {{ $avNombre }} instalado o está desactivado.
                            </div>
                        </div>
                    @endif

                    @if(!$avActivo && $avPorSoftware)
                        <div class="alert alert-warning no-autodismiss d-flex gap-2 mb-3" style="font-size:.85rem">
                            <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                            <div>
                                <strong>Detectado por software instalado.</strong>
                                El agente no reportó el antivirus en la sección de AV, pero el
                                producto figura entre el software instalado:
                                <ul class="mb-0 mt-1">
                                    @foreach($avSoftware as $s)
                                        <li>{{ $s->name }} @if($s->version)<span class="text-muted">· {{ $s->version }}</span>@endif</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    @forelse($antivirus as $av)
                    @php $activo = (int) $av->is_active === 1; @endphp
                    <div class="d-flex align-items-start gap-2 {{ !$loop->last ? 'mb-2 pb-2 border-bottom' : '' }}">
                        <i class="bi {{ $activo ? 'bi-shield-check text-success' : 'bi-shield-slash text-muted' }} mt-1"></i>
                        <div class="flex-fill" style="min-width:0">
                            <div class="fw-semibold">
                                {{ $av->name }}
                                @if($activo)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle ms-1" style="font-size:.68rem">activo</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle ms-1" style="font-size:.68rem">inactivo</span>
                                @endif
                            </div>
                            <div class="text-muted" style="font-size:.76rem">
                                @if($av->antivirus_version) versión {{ $av->antivirus_version }} @endif
                                @if($av->signature_version) · firmas {{ $av->signature_version }} @endif
                                @if((int) $av->is_uptodate === 1) · actualizado @endif
                            </div>
                        </div>
                    </div>
                    @empty
                        <span class="text-muted fst-italic">Sin antivirus registrado en GLPI.</span>
                    @endforelse
                </div>
            </div>

            {{-- Generar acta de entrega --}}
            <div class="card border-0 shadow-sm rounded-3 mb-3">
                <div class="card-header bg-white fw-semibold border-bottom py-2">
                    <i class="bi bi-file-earmark-plus-fill me-1 text-primary"></i> Generar Acta de Entrega
                </div>
                <div class="card-body">
                    @error('acta')
                        <div class="alert alert-danger no-autodismiss d-flex gap-2" style="font-size:.85rem">
                            <i class="bi bi-x-circle-fill flex-shrink-0 mt-1"></i>
                            <div>{{ $message }}</div>
                        </div>
                    @enderror

                    @php
                        $faltantes = [];
                        if (!trim($equipo->nombre_usuario ?? '')) $faltantes[] = 'Usuario asignado';
                        if (!($equipo->ubicacion ?? null))        $faltantes[] = 'Ubicación';
                    @endphp

                    @if(count($faltantes))
                        <div class="alert alert-warning no-autodismiss d-flex gap-2 mb-0" style="font-size:.85rem">
                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
                            <div>
                                <strong>No es posible generar el acta.</strong>
                                Faltan los siguientes datos del equipo en GLPI:
                                <ul class="mb-1 mt-1">
                                    @foreach($faltantes as $f)
                                        <li>{{ $f }}</li>
                                    @endforeach
                                </ul>
                                Completa esta información en GLPI y vuelve a cargar esta página.
                            </div>
                        </div>
                    @else
                    <form method="POST" action="{{ route("inventario.{$dom->clave}.actas.store", $equipo->id) }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size:.83rem">Condición del Equipo</label>
                            <div class="d-flex gap-3">
                                @foreach(['Nuevo','Usado'] as $cond)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="condicion"
                                           id="cond_{{ $cond }}" value="{{ $cond }}"
                                           {{ $cond === 'Usado' ? 'checked' : '' }} required>
                                    <label class="form-check-label" for="cond_{{ $cond }}">{{ $cond }}</label>
                                </div>
                                @endforeach
                            </div>
                            @error('condicion')
                                <div class="text-danger" style="font-size:.78rem">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size:.83rem">Accesorios incluidos</label>
                            <div class="row g-2">
                                @foreach(['monitor' => 'Monitor', 'mouse' => 'Mouse', 'teclado' => 'Teclado', 'mochila' => 'Mochila/Maletín'] as $key => $label)
                                <div class="col-6">
                                    <div class="d-flex gap-2 align-items-center" style="font-size:.84rem">
                                        <span class="text-muted" style="min-width:70px">{{ $label }}</span>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <input type="radio" class="btn-check" name="accesorios[{{ $key }}]"
                                                   id="acc_{{ $key }}_si" value="SI" autocomplete="off">
                                            <label class="btn btn-outline-success" for="acc_{{ $key }}_si">Sí</label>
                                            <input type="radio" class="btn-check" name="accesorios[{{ $key }}]"
                                                   id="acc_{{ $key }}_no" value="NO" autocomplete="off">
                                            <label class="btn btn-outline-danger" for="acc_{{ $key }}_no">No</label>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size:.83rem">Observación</label>
                            <textarea name="observacion" class="form-control" rows="2"
                                      placeholder="Observaciones opcionales…"
                                      style="font-size:.83rem">{{ old('observacion') }}</textarea>
                            @error('observacion')
                                <div class="text-danger" style="font-size:.78rem">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-file-earmark-check-fill me-1"></i> Generar e Imprimir Acta
                            </button>
                        </div>
                    </form>
                    @endif
                </div>
            </div>

            {{-- Historial de actas --}}
            @if($actas->count())
            <div class="card border-0 shadow-sm rounded-3 mb-3">
                <div class="card-header bg-white fw-semibold border-bottom py-2">
                    <i class="bi bi-clock-history me-1 text-secondary"></i> Historial de Actas
                </div>
                <div class="card-body p-0">
                    <table class="vti-table" style="font-size:.8rem">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha</th>
                                <th>Entregado por</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($actas as $acta)
                            <tr>
                                <td class="text-muted">{{ str_pad($acta->id, 4, '0', STR_PAD_LEFT) }}</td>
                                <td>{{ $acta->fecha_emision->format('d/m/Y') }}</td>
                                <td>{{ $acta->entregado_por ?? '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route("inventario.{$dom->clave}.actas.imprimir", $acta) }}"
                                       class="btn btn-outline-primary btn-sm" target="_blank">
                                        <i class="bi bi-printer-fill"></i>
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- Trazabilidad --}}
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white fw-semibold border-bottom py-2">
                    <i class="bi bi-clock-history me-1 text-secondary"></i> Trazabilidad en GLPI
                </div>
                <div class="card-body" style="font-size:.86rem">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Creado</div>
                            <div class="fw-semibold">
                                {{ $equipo->date_creation ? \Carbon\Carbon::parse($equipo->date_creation)->format('d/m/Y') : '—' }}
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.06em">Última modificación</div>
                            <div class="fw-semibold">
                                {{ $equipo->date_mod ? \Carbon\Carbon::parse($equipo->date_mod)->format('d/m/Y') : '—' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>
@endsection
