@php
$colores = [
    'danger'    => ['bg'=>'#fee2e2','ic'=>'#dc2626','border'=>'#fca5a5'],
    'warning'   => ['bg'=>'#fef3c7','ic'=>'#d97706','border'=>'#fcd34d'],
    'success'   => ['bg'=>'#dcfce7','ic'=>'#16a34a','border'=>'#86efac'],
    'secondary' => ['bg'=>'#f1f5f9','ic'=>'#475569','border'=>'#cbd5e1'],
];
$c = $colores[$color] ?? $colores['secondary'];
@endphp
<div class="card border-0 shadow-sm rounded-3 h-100" style="border-left:4px solid {{ $c['border'] }} !important">
    <div class="card-header bg-white border-bottom py-2 d-flex align-items-center gap-2">
        <span class="rounded-2 d-flex align-items-center justify-content-center flex-shrink-0"
              style="width:28px;height:28px;background:{{ $c['bg'] }}">
            <i class="bi {{ $icono }}" style="font-size:.85rem;color:{{ $c['ic'] }}"></i>
        </span>
        <span class="fw-semibold" style="font-size:.85rem">{{ $titulo }}</span>
        <span class="badge ms-auto rounded-pill" style="background:{{ $c['bg'] }};color:{{ $c['ic'] }}">
            {{ $filas->count() }}
        </span>
    </div>
    @if($filas->isEmpty())
        <div class="card-body text-center text-muted py-4" style="font-size:.83rem">
            <i class="bi bi-check-circle-fill text-success me-1"></i> Sin alertas
        </div>
    @else
        <div class="card-body p-0" style="overflow-x:auto">
            <table class="vti-table" style="font-size:.78rem">
                <thead>
                    <tr>
                        @foreach($columnas as $col)
                        <th>{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($filas as $fila)
                    <tr>
                        @foreach($fila as $celda)
                        <td>{{ $celda }}</td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
