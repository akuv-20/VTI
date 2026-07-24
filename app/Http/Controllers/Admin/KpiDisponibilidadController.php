<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KpiDisponibilidadMensual;
use App\Models\KpiExcepcion;
use App\Models\KpiServicioCritico;
use App\Services\CapturaDisponibilidad;
use App\Services\CheckMkClient;
use App\Services\KpiDisponibilidad;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class KpiDisponibilidadController extends Controller
{
    /* ── Dashboard ───────────────────────────────────────────────────────── */

    public function dashboard(Request $request)
    {
        $anio   = (int) $request->input('anio', now()->year);
        $sector = $request->input('sector'); // '', 'planta', 'campo', 'sin_asignar'

        // Conteo por sector para las pestañas.
        $todos    = KpiServicioCritico::activos()->get();
        $conteos  = [
            ''            => $todos->count(),
            'planta'      => $todos->where('sector', 'planta')->count(),
            'campo'       => $todos->where('sector', 'campo')->count(),
            'sin_asignar' => $todos->whereNull('sector')->count(),
        ];

        $servicios = KpiServicioCritico::activos()->sector($sector)->ordenados()->get();

        // Snapshots del año, filtrados a los servicios del sector seleccionado.
        $ids = $servicios->pluck('id');
        $snapshots = KpiDisponibilidadMensual::anio($anio)->whereIn('servicio_id', $ids)->get();
        $porServicio = $snapshots->groupBy('servicio_id');

        // Fila por servicio: pct de cada mes, promedio anual y nivel.
        $filas = $servicios->map(function (KpiServicioCritico $s) use ($porServicio) {
            $meses = $porServicio->get($s->id, collect())->keyBy('mes');
            $anual = KpiDisponibilidad::promedioAnual($porServicio->get($s->id, collect()));

            return [
                'servicio' => $s,
                'meses'    => collect(range(1, 12))->mapWithKeys(function ($m) use ($meses) {
                    $snap = $meses->get($m);
                    return [$m => $snap ? ['pct' => $snap->pct, 'exc' => $snap->excepcion_seconds] : null];
                }),
                'anual'    => $anual,
                'nivel'    => $anual === null ? null : KpiDisponibilidad::nivel($anual),
            ];
        });

        // Total de horas justificadas (descontadas) en el año.
        $horasJustificadas = round($snapshots->sum('excepcion_seconds') / 3600, 1);

        // Resultado global del KPI: promedio anual ponderado de todos los snapshots.
        $pctGlobal   = KpiDisponibilidad::promedioAnual($snapshots);
        $nivelGlobal = $pctGlobal === null ? null : KpiDisponibilidad::nivel($pctGlobal);

        // Evolución mensual global (para el gráfico de línea).
        $evolucion = collect(range(1, 12))->map(function ($m) use ($snapshots) {
            $delMes = $snapshots->where('mes', $m);
            return [
                'mes' => $m,
                'pct' => KpiDisponibilidad::promedioAnual($delMes),
            ];
        });

        return view('admin.kpi.disponibilidad.dashboard', [
            'anio'        => $anio,
            'sector'      => $sector,
            'conteos'     => $conteos,
            'servicios'   => $servicios,
            'filas'       => $filas,
            'pctGlobal'   => $pctGlobal,
            'nivelGlobal' => $nivelGlobal,
            'evolucion'   => $evolucion,
            'meta'        => KpiDisponibilidad::META,
            'peso'        => KpiDisponibilidad::PESO,
            'horasJustificadas' => $horasJustificadas,
        ]);
    }

    /* ── Gestión de servicios críticos ───────────────────────────────────── */

    public function servicios()
    {
        return view('admin.kpi.disponibilidad.servicios', [
            'servicios'   => KpiServicioCritico::ordenados()->get(),
            'configurado' => (new CheckMkClient())->configurado(),
        ]);
    }

    public function servicioStore(Request $request)
    {
        $data = $request->validate([
            'host_name'           => ['required', 'string', 'max:255'],
            'service_description' => ['nullable', 'string', 'max:255'],
            'etiqueta'            => ['required', 'string', 'max:255'],
            'grupo'               => ['nullable', 'string', 'max:100'],
            'sector'              => ['nullable', 'in:' . implode(',', array_keys(KpiServicioCritico::SECTORES))],
            'orden'               => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $data['service_description'] = $data['service_description'] ?: null;
        $data['sector'] = $data['sector'] ?: null;
        $data['orden'] = $data['orden'] ?? 0;

        KpiServicioCritico::updateOrCreate(
            ['host_name' => $data['host_name'], 'service_description' => $data['service_description']],
            $data
        );

        return back()->with('success', 'Servicio crítico agregado.');
    }

    public function servicioUpdate(Request $request, KpiServicioCritico $servicio)
    {
        $data = $request->validate([
            'etiqueta' => ['required', 'string', 'max:255'],
            'grupo'    => ['nullable', 'string', 'max:100'],
            'sector'   => ['nullable', 'in:' . implode(',', array_keys(KpiServicioCritico::SECTORES))],
            'orden'    => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $data['sector'] = $data['sector'] ?: null;
        $data['orden'] = $data['orden'] ?? 0;

        $servicio->update($data);

        return back()->with('success', 'Servicio crítico actualizado.');
    }

    public function servicioToggle(KpiServicioCritico $servicio)
    {
        $servicio->update(['activo' => !$servicio->activo]);

        return back()->with('success', $servicio->activo ? 'Servicio activado.' : 'Servicio desactivado.');
    }

    public function servicioDestroy(KpiServicioCritico $servicio)
    {
        $servicio->delete();

        return back()->with('success', 'Servicio crítico eliminado.');
    }

    /**
     * Explorador de CheckMK: devuelve hosts y (opcionalmente) servicios de un
     * host para elegir cuáles marcar como críticos. Responde JSON.
     */
    public function explorar(Request $request)
    {
        $client = new CheckMkClient();

        if (!$client->configurado()) {
            return response()->json(['error' => 'CheckMK no está configurado. Ve a Admin → Configuración.'], 422);
        }

        try {
            $host = trim((string) $request->input('host', ''));

            if ($host !== '') {
                // Servicios de un host puntual (cacheados brevemente).
                $servicios = Cache::remember("checkmk_svcs_{$host}", 120, fn() => $client->listarServicios($host));
                return response()->json(['servicios' => $servicios->values()]);
            }

            $hosts = Cache::remember('checkmk_hosts', 300, fn() => $client->listarHosts());
            return response()->json(['hosts' => $hosts->values()]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /* ── Excepciones justificadas ────────────────────────────────────────── */

    public function excepciones()
    {
        return view('admin.kpi.disponibilidad.excepciones', [
            'excepciones' => KpiExcepcion::orderByDesc('desde')->get(),
            'servicios'   => KpiServicioCritico::ordenados()->get(),
        ]);
    }

    public function excepcionStore(Request $request, CapturaDisponibilidad $captura)
    {
        @set_time_limit(0); // la recaptura consulta CheckMK; puede superar los 30 s

        $data = $this->validarExcepcion($request);

        $exc = KpiExcepcion::create($data);

        $meses = $captura->recapturarRango($exc->desde, $exc->hasta, $exc->host_name);

        return back()->with('success', $this->mensajeRecaptura('Excepción registrada', $meses));
    }

    public function excepcionUpdate(Request $request, KpiExcepcion $excepcion, CapturaDisponibilidad $captura)
    {
        @set_time_limit(0);

        $data = $this->validarExcepcion($request);

        // Rango a recapturar = unión del rango anterior y el nuevo.
        $desde = $excepcion->desde->min(Carbon::parse($data['desde']));
        $hasta = $excepcion->hasta->max(Carbon::parse($data['hasta']));

        $excepcion->update($data);

        $meses = $captura->recapturarRango($desde, $hasta, $excepcion->host_name);

        return back()->with('success', $this->mensajeRecaptura('Excepción actualizada', $meses));
    }

    public function excepcionToggle(KpiExcepcion $excepcion, CapturaDisponibilidad $captura)
    {
        @set_time_limit(0);

        $excepcion->update(['activa' => !$excepcion->activa]);

        $meses = $captura->recapturarRango($excepcion->desde, $excepcion->hasta, $excepcion->host_name);

        $verbo = $excepcion->activa ? 'activada' : 'desactivada';
        return back()->with('success', $this->mensajeRecaptura("Excepción {$verbo}", $meses));
    }

    public function excepcionDestroy(KpiExcepcion $excepcion, CapturaDisponibilidad $captura)
    {
        @set_time_limit(0);

        $desde = $excepcion->desde->copy();
        $hasta = $excepcion->hasta->copy();
        $host  = $excepcion->host_name;

        $excepcion->delete();

        $meses = $captura->recapturarRango($desde, $hasta, $host);

        return back()->with('success', $this->mensajeRecaptura('Excepción eliminada', $meses));
    }

    private function validarExcepcion(Request $request): array
    {
        $data = $request->validate([
            'host_name'           => ['required', 'string', 'max:255'],
            'service_description' => ['nullable', 'string', 'max:255'],
            'desde'               => ['required', 'date'],
            'hasta'               => ['required', 'date', 'after:desde'],
            'categoria'           => ['nullable', 'string', 'max:100'],
            'justificacion'       => ['required', 'string', 'max:1000'],
        ], [
            'hasta.after' => 'La fecha "hasta" debe ser posterior a "desde".',
        ]);

        $data['service_description'] = $data['service_description'] ?: null;
        $data['activa'] = true;

        return $data;
    }

    private function mensajeRecaptura(string $base, $meses): string
    {
        if ($meses->isEmpty()) {
            return "{$base}. (Sin meses capturados aún que recalcular.)";
        }
        return "{$base}. Meses recalculados: " . $meses->implode(', ') . '.';
    }

    /* ── Captura de disponibilidad mensual ───────────────────────────────── */

    /**
     * Congela la disponibilidad de un mes para todos los servicios críticos
     * activos, consultando CheckMK. Si un servicio falla, se registra el error
     * y se continúa con el resto (el snapshot puede completarse manualmente).
     */
    public function capturar(Request $request, CapturaDisponibilidad $captura)
    {
        $data = $request->validate([
            'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mes'  => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $anio = (int) $data['anio'];
        $mes  = (int) $data['mes'];

        $r = $captura->capturarMes($anio, $mes);

        if ($r['total'] === 0) {
            return back()->withErrors(['No hay servicios críticos activos que capturar. Agrégalos primero.']);
        }

        $etiquetaMes = KpiDisponibilidad::MESES[$mes] ?? $mes;

        if ($r['errores']) {
            return back()
                ->with('success', "Captura de {$etiquetaMes} {$anio}: {$r['ok']} servicio(s) actualizados.")
                ->withErrors(array_slice($r['errores'], 0, 10));
        }

        return back()->with('success', "Captura de {$etiquetaMes} {$anio} completada: {$r['ok']} servicio(s).");
    }

    /**
     * Edición manual de un snapshot mensual. Sirve de respaldo mientras se
     * valida la API de disponibilidad, o para registrar ajustes justificados.
     */
    public function snapshotUpdate(Request $request, KpiDisponibilidadMensual $snapshot)
    {
        $data = $request->validate([
            'pct'  => ['required', 'numeric', 'min:0', 'max:100'],
            'nota' => ['nullable', 'string', 'max:500'],
        ]);

        $snapshot->update([
            'pct'          => round((float) $data['pct'], 3),
            'nota'         => $data['nota'] ?? null,
            'fuente'       => 'manual',
            'capturado_en' => now(),
        ]);

        return back()->with('success', 'Disponibilidad actualizada manualmente.');
    }

    /* ── Informe imprimible ──────────────────────────────────────────────── */

    public function informe(Request $request)
    {
        $anio      = (int) $request->input('anio', now()->year);
        $sector    = $request->input('sector');
        $servicios = KpiServicioCritico::activos()->sector($sector)->ordenados()->get();

        $ids       = $servicios->pluck('id');
        $snapshots = KpiDisponibilidadMensual::anio($anio)->whereIn('servicio_id', $ids)->get();
        $porServicio = $snapshots->groupBy('servicio_id');

        $filas = $servicios->map(function (KpiServicioCritico $s) use ($porServicio) {
            $delServicio = $porServicio->get($s->id, collect());
            $meses = $delServicio->keyBy('mes');
            $anual = KpiDisponibilidad::promedioAnual($delServicio);

            return [
                'servicio' => $s,
                'meses'    => collect(range(1, 12))->mapWithKeys(function ($m) use ($meses) {
                    $snap = $meses->get($m);
                    return [$m => $snap ? ['pct' => $snap->pct, 'exc' => $snap->excepcion_seconds] : null];
                }),
                'anual'    => $anual,
                'nivel'    => $anual === null ? null : KpiDisponibilidad::nivel($anual),
            ];
        });

        $pctGlobal   = KpiDisponibilidad::promedioAnual($snapshots);
        $nivelGlobal = $pctGlobal === null ? null : KpiDisponibilidad::nivel($pctGlobal);

        // Excepciones activas del año, para el anexo de justificaciones.
        $excepciones = KpiExcepcion::activas()
            ->enRango(Carbon::create($anio, 1, 1)->startOfDay(), Carbon::create($anio, 12, 31)->endOfDay())
            ->orderBy('desde')->get();

        $sectorLabel = $sector === 'sin_asignar' ? 'Sin asignar' : (KpiServicioCritico::SECTORES[$sector] ?? null);

        return view('admin.kpi.disponibilidad.informe', [
            'anio'        => $anio,
            'sector'      => $sector,
            'sectorLabel' => $sectorLabel,
            'filas'       => $filas,
            'pctGlobal'   => $pctGlobal,
            'nivelGlobal' => $nivelGlobal,
            'meta'        => KpiDisponibilidad::META,
            'peso'        => KpiDisponibilidad::PESO,
            'generado'    => now(),
            'excepciones' => $excepciones,
            'horasJustificadas' => round($snapshots->sum('excepcion_seconds') / 3600, 1),
        ]);
    }
}
