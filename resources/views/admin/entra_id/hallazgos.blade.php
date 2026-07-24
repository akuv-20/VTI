@extends('layouts.app')

@section('content')
<div class="container-fluid vti-page">

    <div class="vti-page-header mb-2">
        <h4>
            <i class="bi bi-heart-pulse me-1" style="color:#0078d4"></i>
            <a href="{{ route('admin.entra_id.dashboard') }}" class="text-decoration-none">Salud de datos</a>
            <span class="text-muted fw-normal mx-1">/</span>
            {{ $regla->etiqueta }}
        </h4>
        <a href="{{ route('admin.entra_id.dashboard') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
    </div>

    {{-- Resumen de la regla --}}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="row g-3 align-items-center">
                <div class="col-md">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge {{ $regla->severidad === 'error' ? 'bg-danger' : 'bg-warning text-dark' }}"
                              style="font-size:.68rem">
                            {{ $regla->severidad === 'error' ? 'ERROR' : 'AVISO' }}
                        </span>
                        <span class="text-muted" style="font-size:.78rem">
                            {{ $regla->tipo_etiqueta }}
                            @if($regla->campo)
                                · <code>{{ $regla->campo }}</code> ({{ $regla->campo_etiqueta }})
                            @endif
                        </span>
                    </div>
                    @if($regla->descripcion)
                        <div class="text-muted" style="font-size:.83rem">{{ $regla->descripcion }}</div>
                    @endif
                    @if($regla->solo_habilitados)
                        <div class="text-muted mt-1" style="font-size:.75rem">
                            <i class="bi bi-funnel me-1"></i>Solo evalúa cuentas habilitadas
                        </div>
                    @endif
                </div>
                <div class="col-md-auto">
                    <div class="d-flex gap-4 text-center">
                        <div>
                            <div class="fw-bold" style="font-size:1.3rem;color:#64748b">{{ number_format($resultado['evaluadas']) }}</div>
                            <div class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em">Evaluadas</div>
                        </div>
                        <div>
                            <div class="fw-bold" style="font-size:1.3rem;color:#22c55e">{{ number_format($resultado['ok']) }}</div>
                            <div class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em">Correctas</div>
                        </div>
                        <div>
                            <div class="fw-bold" style="font-size:1.3rem;color:{{ $regla->severidad === 'error' ? '#dc2626' : '#b45309' }}">
                                {{ number_format($resultado['fallos']) }}
                            </div>
                            <div class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.05em">Hallazgos</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(!$resultado['disponible'])
        <div class="alert alert-warning">
            <i class="bi bi-lock-fill me-2"></i>{{ $resultado['motivo'] }}
        </div>
    @elseif($resultado['hallazgos']->isEmpty())
        <div class="text-center py-5 text-success">
            <i class="bi bi-check-circle" style="font-size:3rem;opacity:.5"></i>
            <p class="mt-2 mb-0 fw-semibold">Sin hallazgos</p>
            <p class="text-muted" style="font-size:.85rem">Todas las cuentas cumplen esta regla.</p>
        </div>
    @else

    {{-- Buscador local --}}
    <div class="mb-2">
        <div class="input-group input-group-sm" style="max-width:340px">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" id="filtro-hallazgos" class="form-control"
                   placeholder="Filtrar por nombre, correo, área…" autocomplete="off">
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" style="font-size:.83rem" id="tabla-hallazgos">
                <thead class="table-light">
                    <tr>
                        <th>Cuenta</th>
                        <th>Área / Cargo</th>
                        <th>Valor actual</th>
                        <th>Problema</th>
                        <th class="text-center" style="width:100px">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($resultado['hallazgos'] as $h)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $h['nombre'] }}</div>
                            <div class="text-muted font-monospace" style="font-size:.74rem">
                                {{ $h['mail'] ?: $h['upn'] }}
                            </div>
                        </td>
                        <td style="font-size:.8rem">
                            @if($h['department'] || $h['jobTitle'])
                                @if($h['department'])<div>{{ $h['department'] }}</div>@endif
                                @if($h['jobTitle'])<div class="text-muted" style="font-size:.76rem">{{ $h['jobTitle'] }}</div>@endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if(!empty($h['detalle']))
                                <code style="font-size:.78rem;color:#b45309;background:#fef3c7;padding:1px 5px;border-radius:4px">{{ $h['detalle'] }}</code>
                            @else
                                <span class="text-muted fst-italic" style="font-size:.78rem">(vacío)</span>
                            @endif
                        </td>
                        <td class="text-muted" style="font-size:.79rem">
                            {{ $h['motivo'] ?? '—' }}
                            @if(!empty($h['sugerido']))
                                <i class="bi bi-arrow-right mx-1" style="font-size:.7rem"></i>
                                <span class="text-success fw-semibold">{{ $h['sugerido'] }}</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($h['habilitada'])
                                <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size:.7rem">Habilitada</span>
                            @else
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size:.7rem">Deshabilitada</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.getElementById('filtro-hallazgos').addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('#tabla-hallazgos tbody tr').forEach(tr => {
                tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    </script>

    @endif
</div>
@endsection
