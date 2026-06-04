<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WOM {{ $importacion->periodo_label }} — {{ $importacion->factura }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; }

        @media print {
            @page { size: landscape; margin: 1cm 1.2cm; }
            .no-print { display: none !important; }
            body { font-size: 11px; }
        }

        .toolbar {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: .75rem 1rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }

        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 5px 8px; }
        thead tr { background: #FFFF00; }
        th { font-weight: 700; }
        tfoot tr { background: #f8fafc; font-weight: 700; }
        .text-end { text-align: right; }
        .fw-semibold { font-weight: 600; }
        .text-muted { color: #64748b; }
    </style>
</head>
<body>

<div class="toolbar no-print">
    <span class="fw-bold" style="color:#6f42c1">
        <i class="bi bi-phone me-1"></i>WOM — {{ $importacion->periodo_label }}
    </span>
    <span class="text-muted">Factura: {{ $importacion->factura }}</span>
    <span class="text-muted">| Líneas: {{ $importacion->total_lineas }}</span>
    <span class="text-muted">| Total: $ {{ number_format($totalGeneral, 0, ',', '.') }}</span>
    <div class="ms-auto d-flex gap-2">
        <button onclick="window.print()" class="btn btn-sm btn-primary">
            <i class="bi bi-printer-fill me-1"></i>Imprimir
        </button>
        <a href="#" onclick="window.close();return false;" class="btn btn-sm btn-outline-secondary">
            Cerrar
        </a>
    </div>
</div>

<div class="px-3">
    <br><br><br>

    <table>
        <thead>
            <tr>
                <th style="min-width:120px">Empresa</th>
                <th style="min-width:110px">Centro de Costo</th>
                <th style="min-width:130px">Ubicación</th>
                <th style="min-width:170px">Usuario</th>
                <th class="text-end" style="min-width:100px">Total</th>
            </tr>
        </thead>
        <tbody>
        @php
            $rows = [];
            foreach ($agrupado as $empresa => $ccs) {
                $totalEmpresa = 0;
                foreach ($ccs as $cc => $ubis) {
                    $totalCC = 0;
                    foreach ($ubis as $ubi => $usuarios) {
                        $totalUbi = count($usuarios);
                        $rows[$empresa][$cc][$ubi] = $totalUbi;
                        $totalCC      += $totalUbi;
                        $totalEmpresa += $totalUbi;
                    }
                    $rows[$empresa][$cc]['__cc'] = $totalCC;
                }
                $rows[$empresa]['__empresa'] = $totalEmpresa;
            }
        @endphp

        @foreach($agrupado as $empresa => $ccs)
            @php $primeraFilaEmpresa = true; @endphp
            @foreach($ccs as $cc => $ubis)
                @php $primeraFilaCC = true; @endphp
                @foreach($ubis as $ubi => $usuarios)
                    @php $primeraFilaUbi = true; @endphp
                    @foreach($usuarios as $usuario => $monto)
                    <tr>
                        @if($primeraFilaEmpresa)
                            <td rowspan="{{ $rows[$empresa]['__empresa'] }}" class="fw-semibold" style="vertical-align:middle">
                                {{ $empresa }}
                            </td>
                            @php $primeraFilaEmpresa = false; @endphp
                        @endif
                        @if($primeraFilaCC)
                            <td rowspan="{{ $rows[$empresa][$cc]['__cc'] }}" class="text-muted" style="vertical-align:middle">
                                {{ $cc }}
                            </td>
                            @php $primeraFilaCC = false; @endphp
                        @endif
                        @if($primeraFilaUbi)
                            <td rowspan="{{ $rows[$empresa][$cc][$ubi] }}" style="vertical-align:middle">
                                {{ $ubi }}
                            </td>
                            @php $primeraFilaUbi = false; @endphp
                        @endif
                        <td>{{ $usuario }}</td>
                        <td class="text-end fw-semibold">$ {{ number_format($monto, 0, ',', '.') }}</td>
                    </tr>
                    @endforeach
                @endforeach
            @endforeach
        @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="text-end pe-3">Total</td>
                <td class="text-end" style="font-size:1.05em">$ {{ number_format($totalGeneral, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>

</div>

</body>
</html>
