@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4>
            <i class="bi bi-display-fill me-2"></i>{{ $equipo->nombre_equipo }}
        </h4>
        <a href="{{ route('inventario_ti.index') }}" class="btn btn-outline-secondary btn-sm">
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
                    @if(trim($equipo->nombre_usuario))
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

        {{-- Panel derecho: generar acta --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-3 mb-3">
                <div class="card-header bg-white fw-semibold border-bottom py-2">
                    <i class="bi bi-file-earmark-plus-fill me-1 text-primary"></i> Generar Acta de Entrega
                </div>
                <div class="card-body">
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
                    <form method="POST" action="{{ route('inventario_ti.acta.store', $equipo->id) }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-semibold" style="font-size:.83rem">Condición del Equipo</label>
                            <div class="d-flex gap-3">
                                @foreach(['Nuevo','Usado'] as $cond)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="condicion"
                                           id="cond_{{ $cond }}" value="{{ $cond }}"
                                           {{ $cond === 'Usado' ? 'checked' : '' }}
                                           required>
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
            <div class="card border-0 shadow-sm rounded-3">
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
                                    <a href="{{ route('inventario_ti.actas.imprimir', $acta) }}"
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
        </div>

    </div>
</div>
@endsection
