@extends('layouts.app')

@php
    use App\Services\ActividadBuzones;

    $f    = $filtros['activos'];
    $orden = $f['orden'] ?? 'recibidos';
    $dir   = $f['dir'] ?? 'desc';

    // Cada encabezado ordenable conserva los filtros y alterna la dirección.
    $enlaceOrden = function ($campo) use ($f, $orden, $dir) {
        return route('admin.buzones.index', array_filter(array_merge($f, [
            'orden' => $campo,
            'dir'   => ($orden === $campo && $dir === 'desc') ? 'asc' : 'desc',
        ]), fn($v) => $v !== null && $v !== ''));
    };
    $flecha = fn($campo) => $orden === $campo
        ? '<i class="bi bi-caret-' . ($dir === 'desc' ? 'down' : 'up') . '-fill ms-1"></i>'
        : '';
@endphp

@section('content')
<style>
    /* buzl- : listado de buzones */
    .buzl-filtros {
        background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
        padding: .9rem 1.1rem; margin-bottom: 1rem;
    }
    .buzl-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px,1fr)); gap: .6rem; margin-bottom: 1rem; }
    .buzl-tile {
        background: #fff; border: 1px solid #e2e8f0; border-left-width: 4px; border-radius: 10px;
        padding: .7rem .9rem; text-decoration: none; display: block; color: inherit;
        transition: box-shadow .12s;
    }
    .buzl-tile:hover { box-shadow: 0 3px 12px rgba(0,0,0,.08); color: inherit; }
    .buzl-tile.active { box-shadow: 0 0 0 2px rgba(59,130,246,.35); }
    .buzl-tile-val { font-size: 1.5rem; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; }
    .buzl-tile-lbl { font-size: .72rem; color: #64748b; margin-top: .25rem; display: flex; align-items: center; gap: .3rem; }

    .buzl-badge {
        display: inline-flex; align-items: center; gap: .25rem;
        font-size: .7rem; font-weight: 600; padding: 1px 7px; border-radius: 20px;
        border: 1px solid transparent; white-space: nowrap;
    }
    .buzl-tabla th a { color: inherit; text-decoration: none; }
    .buzl-tabla th a:hover { color: #2563eb; }
    .buzl-num { font-variant-numeric: tabular-nums; text-align: right; }
    .buzl-nunca { color: #a63a22; font-weight: 600; }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4 class="d-flex align-items-center gap-2 flex-wrap">
            <span><i class="bi bi-envelope-exclamation me-2" style="color:#a63a22"></i>Actividad de buzones</span>
            <span class="badge bg-light text-secondary border" style="font-size:.7rem">unifrutti.com</span>
        </h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.buzones.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-bar-chart-line me-1"></i>Informe
            </a>
            <a href="{{ route('admin.buzones.excel', $f) }}" class="btn btn-success btn-sm"
               title="Un libro con una hoja por estado. Respeta departamento, licencia, mes y búsqueda; los filtros de estado no aplican porque el informe es el desglose.">
                <i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel
            </a>
            <button class="btn btn-outline-secondary btn-sm" type="button"
                    data-bs-toggle="collapse" data-bs-target="#paneExcluidos">
                <i class="bi bi-slash-circle me-1"></i>Excluidos
                @if($excluidos->count()) <span class="badge bg-secondary ms-1">{{ $excluidos->count() }}</span> @endif
            </button>
        </div>
    </div>

    {{-- Buzones excluidos --}}
    <div class="collapse @if($errors->any()) show @endif" id="paneExcluidos">
        <div class="buzl-filtros">
            <p style="font-size:.84rem;color:#475569">
                Los buzones compartidos y funcionales no tienen inicio de sesión propio —se acceden
                por delegación— así que aparecerían como «nunca activado» sin serlo. Excluidos aquí,
                salen del análisis al instante.
            </p>

            <form method="POST" action="{{ route('admin.buzones.excluir') }}" class="row g-2 align-items-end mb-3">
                @csrf
                <div class="col-md-5">
                    <label class="form-label" style="font-size:.78rem;font-weight:600">
                        Correo del buzón
                        <span class="text-muted fw-normal">— puedes pegar varios de una vez</span>
                    </label>
                    <textarea name="upn" class="form-control form-control-sm" rows="2" required
                              placeholder="info.pv@unifrutti.com&#10;cuadraturalind@unifrutti.com">{{ old('upn') }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label" style="font-size:.78rem;font-weight:600">Motivo</label>
                    <input type="text" name="motivo" class="form-control form-control-sm"
                           placeholder="Buzón compartido de Post Venta" value="{{ old('motivo') }}" required>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg me-1"></i>Excluir</button>
                </div>
            </form>

            @if($excluidos->isEmpty())
                <p class="text-muted mb-0" style="font-size:.84rem">Ningún buzón excluido todavía.</p>
            @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" style="font-size:.84rem">
                    <thead class="table-light">
                        <tr><th>Buzón</th><th>Motivo</th><th>Agregado</th><th style="width:60px"></th></tr>
                    </thead>
                    <tbody>
                        @foreach($excluidos as $e)
                        <tr>
                            <td class="font-monospace" style="font-size:.8rem">{{ $e->upn }}</td>
                            <td>{{ $e->motivo }}</td>
                            <td class="text-muted">{{ $e->created_at?->format('d/m/Y') }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.buzones.incluir', $e) }}"
                                      onsubmit="return confirm('¿Devolver {{ $e->upn }} al análisis?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger py-0 px-1" title="Quitar de la lista">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Papelera: quitar un buzón de la lista no lo borra de verdad --}}
            @if($papelera->isNotEmpty())
            <hr class="my-3">
            <p class="mb-2" style="font-size:.8rem;color:#64748b">
                <i class="bi bi-trash3 me-1"></i>
                <strong>Quitados de la lista.</strong> No se borran: quedan aquí por si los necesitas de vuelta.
            </p>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" style="font-size:.82rem">
                    <tbody>
                        @foreach($papelera as $e)
                        <tr class="text-muted">
                            <td class="font-monospace" style="font-size:.78rem">{{ $e->upn }}</td>
                            <td>{{ $e->motivo }}</td>
                            <td>quitado el {{ $e->deleted_at?->format('d/m/Y') }}</td>
                            <td class="text-end" style="width:110px">
                                <form method="POST" action="{{ route('admin.buzones.restaurar', $e->id) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size:.74rem">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- Indicadores que filtran --}}
    <div class="buzl-tiles">
        <a href="{{ route('admin.buzones.index') }}"
           class="buzl-tile {{ empty($f['clase']) && !request()->boolean('ver_excluidos') ? 'active' : '' }}"
           style="border-left-color:#3b82f6">
            <div class="buzl-tile-val">{{ number_format($resumen['total']) }}</div>
            <div class="buzl-tile-lbl"><i class="bi bi-list-ul"></i>Todos</div>
        </a>
        @foreach(ActividadBuzones::CLASES as $clave => [$lbl, $desc, $color, $ico])
        <a href="{{ route('admin.buzones.index', ['clase' => $clave]) }}"
           class="buzl-tile {{ ($f['clase'] ?? null) === $clave ? 'active' : '' }}"
           style="border-left-color:{{ $color }}" title="{{ $desc }}">
            <div class="buzl-tile-val" style="color:{{ $color }}">{{ number_format($resumen[$clave]) }}</div>
            <div class="buzl-tile-lbl"><i class="bi {{ $ico }}"></i>{{ $lbl }}</div>
        </a>
        @endforeach
        @if($resumen['excluidos'] > 0)
        <a href="{{ route('admin.buzones.index', ['ver_excluidos' => 1]) }}"
           class="buzl-tile {{ request()->boolean('ver_excluidos') ? 'active' : '' }}"
           style="border-left-color:#94a3b8">
            <div class="buzl-tile-val" style="color:#64748b">{{ number_format($resumen['excluidos']) }}</div>
            <div class="buzl-tile-lbl"><i class="bi bi-slash-circle"></i>Excluidos</div>
        </a>
        @endif
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('admin.buzones.index') }}" class="buzl-filtros">
        @if($f['clase'] ?? null)         <input type="hidden" name="clase" value="{{ $f['clase'] }}"> @endif
        @if(request()->boolean('ver_excluidos')) <input type="hidden" name="ver_excluidos" value="1"> @endif
        <input type="hidden" name="orden" value="{{ $orden }}">
        <input type="hidden" name="dir" value="{{ $dir }}">

        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" style="font-size:.76rem;font-weight:600">Buscar</label>
                <input type="text" name="q" value="{{ $f['q'] ?? '' }}" class="form-control form-control-sm"
                       placeholder="Correo, nombre o departamento…">
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:.76rem;font-weight:600">Departamento</label>
                <select name="departamento" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($filtros['departamentos'] as $d)
                        <option value="{{ $d }}" @selected(($f['departamento'] ?? null) === $d)>{{ $d }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:.76rem;font-weight:600">Último acceso</label>
                <select name="acceso" class="form-select form-select-sm">
                    <option value="">Cualquiera</option>
                    @foreach(\App\Http\Controllers\Admin\ActividadBuzonesController::ACCESOS as $clave => $lbl)
                        <option value="{{ $clave }}" @selected(($f['acceso'] ?? null) === (string) $clave)>
                            {{ $lbl }} ({{ number_format($filtros['accesos'][$clave] ?? 0) }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-1">
                <button class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-funnel me-1"></i>Filtrar</button>
                <a href="{{ route('admin.buzones.index') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>

            <div class="col-md-3">
                <label class="form-label" style="font-size:.76rem;font-weight:600">Licencia</label>
                <select name="licencia" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($filtros['licencias'] as $l)
                        <option value="{{ $l }}" @selected(($f['licencia'] ?? null) === $l)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:.76rem;font-weight:600">Creado en</label>
                <select name="cohorte" class="form-select form-select-sm">
                    <option value="">Cualquier mes</option>
                    @foreach($filtros['cohortes'] as $c)
                        <option value="{{ $c }}" @selected(($f['cohorte'] ?? null) === $c)>{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <p class="text-muted mb-1" style="font-size:.74rem;line-height:1.5">
                    Los tramos de días cuentan solo a quienes alguna vez entraron.
                    «Nunca accedió» va aparte para que los números no se pisen.
                </p>
            </div>
        </div>
    </form>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <small class="text-muted">
            <strong>{{ number_format($total) }}</strong> buzones con estos filtros
        </small>
        <small class="text-muted">
            <i class="bi bi-clock-history me-1"></i>Actualizado {{ $generado->diffForHumans() }}
        </small>
    </div>

    <div class="vti-table-wrapper">
        <table class="vti-table buzl-tabla">
            <thead>
                <tr>
                    <th><a href="{{ $enlaceOrden('upn') }}">Correo{!! $flecha('upn') !!}</a></th>
                    <th><a href="{{ $enlaceOrden('nombre') }}">Nombre{!! $flecha('nombre') !!}</a></th>
                    <th><a href="{{ $enlaceOrden('departamento') }}">Departamento{!! $flecha('departamento') !!}</a></th>
                    <th><a href="{{ $enlaceOrden('creado') }}">Creado{!! $flecha('creado') !!}</a></th>
                    <th><a href="{{ $enlaceOrden('ultimo_acceso') }}">Último acceso{!! $flecha('ultimo_acceso') !!}</a></th>
                    <th class="text-end"><a href="{{ $enlaceOrden('enviados') }}">Env.{!! $flecha('enviados') !!}</a></th>
                    <th class="text-end"><a href="{{ $enlaceOrden('recibidos') }}">Rec.{!! $flecha('recibidos') !!}</a></th>
                    <th class="text-end"><a href="{{ $enlaceOrden('mb') }}">Tamaño{!! $flecha('mb') !!}</a></th>
                    <th>Licencias</th>
                    <th><a href="{{ $enlaceOrden('clase') }}">Estado{!! $flecha('clase') !!}</a></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($buzones as $b)
                @php [$lbl, $desc, $color, $ico] = ActividadBuzones::CLASES[$b['clase']]; @endphp
                <tr>
                    <td class="font-monospace" style="font-size:.78rem">{{ $b['upn'] }}</td>
                    <td>{{ $b['nombre'] }}</td>
                    <td style="font-size:.8rem">{{ $b['departamento'] ?: '—' }}</td>
                    <td style="font-size:.8rem">{{ $b['creado']?->format('m/Y') ?: '—' }}</td>
                    <td style="font-size:.8rem">
                        @if($b['ultimo_acceso'])
                            {{ $b['ultimo_acceso']->format('d/m/Y') }}
                            <span class="text-muted" style="font-size:.74rem">· {{ number_format($b['dias_sin_acceso']) }} d</span>
                        @else
                            <span class="buzl-nunca">nunca</span>
                        @endif
                    </td>
                    <td class="buzl-num">{{ number_format($b['enviados']) }}</td>
                    <td class="buzl-num">{{ number_format($b['recibidos']) }}</td>
                    <td class="buzl-num" style="font-size:.8rem">
                        {{ $b['mb'] >= 1024 ? number_format($b['mb']/1024, 1) . ' GB' : number_format($b['mb'], 0) . ' MB' }}
                    </td>
                    <td style="font-size:.72rem" class="font-monospace">{{ implode(' ', $b['licencias']) ?: '—' }}</td>
                    <td>
                        <span class="buzl-badge" style="background:{{ $color }}1a;color:{{ $color }};border-color:{{ $color }}66">
                            <i class="bi {{ $ico }}"></i>{{ $lbl }}
                        </span>
                    </td>
                    <td class="text-end">
                        @if(!$b['excluido'])
                        <form method="POST" action="{{ route('admin.buzones.excluir') }}" class="d-inline"
                              onsubmit="return confirm('¿Excluir {{ $b['upn'] }} del análisis?')">
                            @csrf
                            <input type="hidden" name="upn" value="{{ $b['upn'] }}">
                            <input type="hidden" name="motivo" value="Buzón compartido o funcional">
                            <button class="btn btn-sm btn-link text-secondary py-0 px-1"
                                    title="Excluir: es un buzón compartido">
                                <i class="bi bi-slash-circle"></i>
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr class="vti-empty">
                    <td colspan="11">
                        <i class="bi bi-inbox" style="font-size:1.4rem"></i>
                        <div class="mt-1">No hay buzones con estos filtros.</div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center px-1">
        <small class="text-muted">{{ number_format($total) }} buzones</small>
        {{ $buzones->links() }}
    </div>

</div>
@endsection
