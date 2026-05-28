<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrega de Facturas #{{ $entrega->id }} — {{ $entrega->created_at->format('d-m-Y') }}</title>
    <style>
        /* ── Reset ─────────────────────────────────────────────────── */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #000;
            background: #fff;
        }

        /* ── Pantalla: botones de acción ────────────────────────────── */
        .toolbar {
            display: flex;
            gap: 8px;
            padding: 10px 16px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            align-items: center;
        }
        .toolbar h6 {
            margin: 0;
            font-size: 13px;
            color: #1e293b;
            flex: 1;
        }
        .toolbar button, .toolbar a {
            padding: 5px 14px;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #374151;
        }
        .toolbar button.primary {
            background: #1d4ed8;
            color: #fff;
            border-color: #1d4ed8;
        }

        /* ── Página impresa ─────────────────────────────────────────── */
        .pagina {
            padding: 18px 22px;
            max-width: 1100px;
            margin: 0 auto;
        }

        /* ── Tabla ──────────────────────────────────────────────────── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        thead th {
            background: #FFFF00;
            border: 1px solid #555;
            padding: 4px 6px;
            font-weight: 700;
            text-align: center;
            font-size: 9.5px;
            white-space: nowrap;
        }
        tbody td {
            border: 1px solid #bbb;
            padding: 3px 6px;
            font-size: 9.5px;
            vertical-align: middle;
        }
        tbody tr:nth-child(even) td { background: #f9f9f9; }
        tfoot td {
            border: 1px solid #999;
            padding: 4px 6px;
            font-weight: 700;
            font-size: 9.5px;
            background: #e8e8e8;
        }
        .num { text-align: right; }
        .center { text-align: center; }

        /* ── Firmas ─────────────────────────────────────────────────── */
        .firmas {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            padding: 0 20px;
        }
        .firma-bloque {
            text-align: center;
            width: 220px;
        }
        .firma-linea {
            border-top: 1px solid #000;
            margin-bottom: 6px;
        }
        .firma-label {
            font-size: 10px;
            font-weight: 700;
        }
        .firma-sub {
            font-size: 9px;
            color: #555;
            margin-top: 2px;
        }

        /* ── Print ──────────────────────────────────────────────────── */
        @media print {
            .toolbar { display: none !important; }
            body { font-size: 9px; }
            .pagina { padding: 0; max-width: 100%; }
            thead th { font-size: 8.5px; }
            tbody td { font-size: 8.5px; }
        }
        @page { size: landscape; margin: 1cm 1.2cm; }
    </style>
</head>
<body>

    {{-- Barra de herramientas (no se imprime) --}}
    <div class="toolbar">
        <h6>
            <i>Entrega de Facturas</i> #{{ $entrega->id }} —
            {{ $entrega->created_at->format('d/m/Y') }}
        </h6>
        <a href="#" onclick="window.close(); return false;">← Cerrar</a>
        <button class="primary" onclick="window.print()">🖨 Imprimir</button>
    </div>

    <div class="pagina">

        <br><br><br>

        <table>
            <thead>
                <tr>
                    <th>Nro fact</th>
                    <th>Rut Prov</th>
                    <th>Nombre Prov</th>
                    <th>Producto</th>
                    <th>Cuenta Contable</th>
                    <th>Total</th>
                    <th>Sin IVA</th>
                    <th>OC</th>
                    <th>Entregada</th>
                </tr>
            </thead>
            <tbody>
                @foreach($entrega->items as $item)
                @php
                    $f             = $item->factura;
                    $tieneServicio = $f->id_servicio && $f->servicio;
                    if ($tieneServicio) {
                        $nombreProv = $f->servicio->compania->nombre ?? '';
                        $rutProv    = $f->servicio->compania->rut ?? '';
                    } else {
                        $nombreProv = $f->proveedor ?? '';
                        $rutProv    = $f->proveedor
                            ? ($companiasPorNombre->get($f->proveedor)?->rut ?? '')
                            : '';
                    }
                    $producto  = $tieneServicio
                        ? ($f->descripcion ?? $f->servicio->concepto ?? $f->servicio->servicio ?? '')
                        : ($f->descripcion ?? '');
                    $cc        = $f->cuentaContableEfectiva;
                    $cuentaStr = $cc
                        ? $cc->numero_cuenta . ' ' . $cc->nombre_cuenta
                        : '';
                @endphp
                <tr>
                    <td class="center"><strong>{{ $f->factura }}</strong></td>
                    <td class="center">{{ $rutProv }}</td>
                    <td>{{ $nombreProv }}</td>
                    <td>{{ $producto }}</td>
                    <td>{{ $cuentaStr }}</td>
                    <td class="num">$ {{ number_format($f->total, 0, ',', '.') }}</td>
                    <td class="num">$ {{ number_format($f->valor_neto, 0, ',', '.') }}</td>
                    <td class="center">{{ $f->oc ?? '' }}</td>
                    <td class="center">{{ $entrega->created_at->format('d-m-Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="firmas">
            <div class="firma-bloque">
                <div class="firma-linea" style="margin-top:50px"></div>
                <div class="firma-label">Entregado por {{ $entrega->usuario->name ?? '' }}</div>
            </div>
            <div class="firma-bloque">
                <div class="firma-linea" style="margin-top:50px"></div>
                <div class="firma-label">Recibido</div>
            </div>
        </div>

    </div>
</body>
</html>
