<div class="vti-table-wrapper">
    <table class="vti-table" style="font-size:.85rem">
        <thead>
            <tr style="background:#FFFF00">
                <th style="min-width:140px">Empresa</th>
                <th style="min-width:120px">Centro de Costo</th>
                <th style="min-width:140px">Ubicación</th>
                <th style="min-width:200px">Usuario</th>
                <th class="text-end" style="min-width:110px">Total</th>
            </tr>
        </thead>
        <tbody>
        @php
            // Pre-calcular rowspans
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
                            <td rowspan="{{ $rows[$empresa]['__empresa'] }}"
                                class="fw-semibold align-middle"
                                style="border-right:2px solid #dee2e6;vertical-align:middle">
                                {{ $empresa }}
                            </td>
                            @php $primeraFilaEmpresa = false; @endphp
                        @endif

                        @if($primeraFilaCC)
                            <td rowspan="{{ $rows[$empresa][$cc]['__cc'] }}"
                                class="align-middle text-muted"
                                style="border-right:1px solid #dee2e6;vertical-align:middle">
                                {{ $cc }}
                            </td>
                            @php $primeraFilaCC = false; @endphp
                        @endif

                        @if($primeraFilaUbi)
                            <td rowspan="{{ $rows[$empresa][$cc][$ubi] }}"
                                class="align-middle"
                                style="border-right:1px solid #dee2e6;vertical-align:middle">
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
            <tr style="border-top:2px solid #333;background:#f8fafc">
                <td colspan="4" class="fw-bold text-end pe-3">Total</td>
                <td class="text-end fw-bold fs-6">$ {{ number_format($totalGeneral, 0, ',', '.') }}</td>
            </tr>
        </tfoot>
    </table>
</div>
