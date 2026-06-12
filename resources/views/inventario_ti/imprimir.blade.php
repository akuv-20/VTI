<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acta de Entrega — {{ $acta->nombre_equipo }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 12px;
            color: #1a1a2e;
            background: #e8ecf0;
        }

        .toolbar {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .6rem 1.2rem;
            background: #fff;
            border-bottom: 1px solid #dee2e6;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .toolbar a, .toolbar button {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid #ced4da;
            background: #fff;
            color: #495057;
        }
        .toolbar button.primary {
            background: #1e3a5f;
            color: #fff;
            border-color: #1e3a5f;
        }
        .toolbar .ms-auto { margin-left: auto; }

        .page-wrap {
            max-width: 794px;
            margin: 28px auto;
            background: #fff;
            box-shadow: 0 4px 24px rgba(0,0,0,.13);
            border-radius: 4px;
            overflow: hidden;
        }

        .acta-band {
            height: 6px;
            background: linear-gradient(90deg, #1e3a5f 0%, #2563eb 100%);
        }

        .acta { padding: 32px 44px 36px; }

        .acta-header {
            display: flex;
            align-items: center;
            gap: 20px;
            padding-bottom: 16px;
            margin-bottom: 20px;
            border-bottom: 2px solid #1e3a5f;
        }
        .acta-header img { height: 54px; width: auto; object-fit: contain; flex-shrink: 0; }
        .acta-logo-fallback {
            height: 54px; width: 90px;
            display: flex; align-items: center; justify-content: center;
            background: #1e3a5f; border-radius: 6px;
            color: #fff; font-weight: 700; font-size: 14px; flex-shrink: 0;
        }
        .acta-title-block { flex: 1; }
        .acta-title {
            font-size: 15px; font-weight: 700; letter-spacing: .04em;
            text-transform: uppercase; color: #1e3a5f; line-height: 1.3;
        }
        .acta-num { margin-top: 3px; font-size: 11px; color: #6c757d; letter-spacing: .02em; }

        .datos-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 0;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 18px;
        }
        .dato-cell {
            padding: 8px 14px;
            border-right: 1px solid #dee2e6;
            border-bottom: 1px solid #dee2e6;
        }
        .dato-cell:nth-child(3n) { border-right: none; }
        .dato-cell:nth-last-child(-n+3) { border-bottom: none; }
        .dato-cell.full { grid-column: 1 / -1; border-right: none; }
        .dato-label {
            font-size: 9.5px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .06em; color: #6c757d; margin-bottom: 2px;
        }
        .dato-value { font-size: 12.5px; font-weight: 600; color: #1a1a2e; }
        .dato-value.mono { font-family: 'Courier New', monospace; font-size: 11.5px; }

        .section-title {
            font-size: 10px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .1em; color: #fff; background: #1e3a5f;
            padding: 4px 10px; border-radius: 4px;
            margin-bottom: 10px; margin-top: 16px; display: inline-block;
        }

        .condicion-row { display: flex; gap: 24px; margin-bottom: 12px; }
        .condicion-item { display: flex; align-items: center; gap: 8px; }
        .check-square {
            width: 20px; height: 20px; border: 1.5px solid #495057;
            border-radius: 3px; display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 13px; background: #fff; flex-shrink: 0;
        }
        .check-square.marked { background: #1e3a5f; border-color: #1e3a5f; color: #fff; }

        .check-table {
            width: 100%; border-collapse: collapse; margin-bottom: 4px; font-size: 11.5px;
        }
        .check-table thead th {
            background: #f1f5f9; font-weight: 700; text-align: center;
            padding: 5px 8px; border: 1px solid #dee2e6;
            font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; color: #495057;
        }
        .check-table thead th:first-child { text-align: left; }
        .check-table tbody td { border: 1px solid #dee2e6; padding: 5px 8px; text-align: center; }
        .check-table tbody td:first-child { text-align: left; color: #374151; }
        .check-table tbody tr:nth-child(even) td { background: #fafbfc; }

        .check-dot {
            display: inline-block; width: 20px; height: 20px;
            line-height: 20px; text-align: center;
            border-radius: 3px; border: 1.5px solid #dee2e6;
            font-size: 13px; font-weight: 700; color: transparent;
        }
        .check-dot.si { background: #dcfce7; border-color: #166534; color: #166534; }
        .check-dot.no { background: #fee2e2; border-color: #dc3545; color: #dc3545; }

        .obs-box {
            border: 1px solid #dee2e6; border-radius: 6px; padding: 10px 14px;
            min-height: 48px; margin-bottom: 16px; font-size: 12px;
            color: #374151; background: #fafbfc;
        }

        .importante {
            background: #f8fafc; border-left: 3px solid #2563eb;
            padding: 9px 14px; font-size: 10.5px; color: #374151;
            line-height: 1.55; border-radius: 0 4px 4px 0; margin-bottom: 28px;
        }
        .importante strong { color: #1e3a5f; }

        .firmas {
            display: flex; gap: 60px; justify-content: center; margin-top: 60px;
        }
        .firma-bloque { flex: 1; max-width: 220px; text-align: center; }
        .firma-linea { border-top: 1.5px solid #1e3a5f; margin-bottom: 6px; padding-top: 4px; }
        .firma-nombre { font-size: 11.5px; font-weight: 700; color: #1a1a2e; }
        .firma-rol { font-size: 10px; color: #6c757d; margin-top: 1px; }

        @media print {
            @page { size: A4 portrait; margin: 0; }
            .toolbar { display: none !important; }
            body { background: #fff; }
            .page-wrap { box-shadow: none; border-radius: 0; margin: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <a href="{{ route('inventario_ti.show', $acta->glpi_computer_id) }}">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
    <a href="{{ route('inventario_ti.actas') }}">
        <i class="bi bi-list-ul"></i> Actas
    </a>
    <div class="ms-auto">
        <button class="primary" onclick="window.print()">
            <i class="bi bi-printer-fill"></i> Imprimir
        </button>
    </div>
</div>

<div class="page-wrap">
    <div class="acta-band"></div>
    <div class="acta">

        {{-- Header --}}
        <div class="acta-header">
            @if(!empty($appLogo))
                <img src="{{ $appLogo }}" alt="Logo">
            @else
                <div class="acta-logo-fallback">VTI</div>
            @endif
            <div class="acta-title-block">
                <div class="acta-title">Acta de Entrega<br>Equipo Computacional</div>
                <div class="acta-num">N° {{ str_pad($acta->id, 6, '0', STR_PAD_LEFT) }} &nbsp;·&nbsp; Emitida el {{ $acta->fecha_emision->format('d/m/Y') }}</div>
            </div>
        </div>

        {{-- Datos generales --}}
        <div class="datos-grid">
            <div class="dato-cell">
                <div class="dato-label">Nombre del Empleado</div>
                <div class="dato-value">{{ $acta->nombre_receptor ?? '—' }}</div>
            </div>
            <div class="dato-cell">
                <div class="dato-label">Fecha de Entrega</div>
                <div class="dato-value">{{ $acta->fecha_emision->format('d-m-Y') }}</div>
            </div>
            <div class="dato-cell">
                <div class="dato-label">Ubicación</div>
                <div class="dato-value">{{ $acta->ubicacion ?? '—' }}</div>
            </div>
            <div class="dato-cell">
                <div class="dato-label">Nombre del Equipo</div>
                <div class="dato-value">{{ $acta->nombre_equipo }}</div>
            </div>
            <div class="dato-cell">
                <div class="dato-label">Marca</div>
                <div class="dato-value">{{ $acta->marca ?? '—' }}</div>
            </div>
            <div class="dato-cell">
                <div class="dato-label">Modelo</div>
                <div class="dato-value">{{ $acta->modelo ?? '—' }}</div>
            </div>
            <div class="dato-cell">
                <div class="dato-label">N° de Serie</div>
                <div class="dato-value mono">{{ $acta->numero_serie ?? '—' }}</div>
            </div>
            <div class="dato-cell" style="grid-column:2/4;border-right:none">
                <div class="dato-label">Sistema Operativo</div>
                <div class="dato-value">{{ $acta->sistema_operativo ?? '—' }}</div>
            </div>
            <div class="dato-cell" style="border-bottom:none;border-right:1px solid #dee2e6">
                <div class="dato-label">Procesador</div>
                <div class="dato-value">{{ $acta->procesador ?? '—' }}</div>
            </div>
            <div class="dato-cell" style="border-bottom:none">
                <div class="dato-label">Memoria RAM</div>
                <div class="dato-value">{{ $acta->ram ?? '—' }}</div>
            </div>
            <div class="dato-cell" style="border-bottom:none;border-right:none">
                <div class="dato-label">Disco Principal</div>
                <div class="dato-value">{{ $acta->disco ?? '—' }}</div>
            </div>
        </div>

        {{-- Condición --}}
        <div class="section-title">Condición del Equipo</div>
        <div class="condicion-row">
            @foreach(['Nuevo','Usado'] as $cond)
            <div class="condicion-item">
                <div class="check-square {{ $acta->condicion === $cond ? 'marked' : '' }}">
                    {{ $acta->condicion === $cond ? '✓' : '' }}
                </div>
                <span>{{ $cond }}</span>
            </div>
            @endforeach
        </div>

        {{-- Accesorios --}}
        @php $acc = $acta->accesorios ?? []; @endphp
        <div class="section-title">Accesorios</div>
        <table class="check-table">
            <thead>
                <tr>
                    <th>Ítem</th>
                    <th style="width:80px">Sí</th>
                    <th style="width:80px">No</th>
                </tr>
            </thead>
            <tbody>
                @foreach(['monitor' => 'Monitor', 'mouse' => 'Mouse', 'teclado' => 'Teclado', 'mochila' => 'Mochila / Maletín'] as $key => $label)
                <tr>
                    <td>{{ $label }}</td>
                    <td><span class="check-dot {{ ($acc[$key] ?? '') === 'SI' ? 'si' : '' }}">{{ ($acc[$key] ?? '') === 'SI' ? '✓' : '' }}</span></td>
                    <td><span class="check-dot {{ ($acc[$key] ?? '') === 'NO' ? 'no' : '' }}">{{ ($acc[$key] ?? '') === 'NO' ? '✗' : '' }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Observación --}}
        <div class="section-title">Observación</div>
        <div class="obs-box">{{ $acta->observacion ?? '' }}</div>

        {{-- Importante --}}
        <div class="importante">
            <strong>Importante:</strong> El equipo entregado es de uso exclusivo laboral y debe ser
            tratado con el debido cuidado. Evite exponerlo a golpes, humedad o temperaturas extremas.
            No instale software no autorizado ni lo utilice para fines ajenos a la empresa.
            En caso de pérdida, robo o daño, informe de inmediato al área de TI. El empleado es responsable
            del buen estado y custodia del equipo durante toda su vigencia de uso.
        </div>

        {{-- Firmas --}}
        <div class="firmas">
            <div class="firma-bloque">
                <div class="firma-linea"></div>
                <div class="firma-nombre">{{ $acta->entregado_por }}</div>
                <div class="firma-rol">Entrega Conforme</div>
            </div>
            <div class="firma-bloque">
                <div class="firma-linea"></div>
                <div class="firma-nombre">{{ $acta->nombre_receptor ?? '—' }}</div>
                <div class="firma-rol">Recibe Conforme</div>
            </div>
        </div>

    </div>
</div>

<script>
    window.addEventListener('load', () => window.print());
</script>
</body>
</html>
