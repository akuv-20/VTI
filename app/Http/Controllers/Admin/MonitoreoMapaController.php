<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Configuracion;
use App\Models\MapaEnlace;
use App\Models\MapaNodo;
use App\Models\MapaRed;
use App\Services\CheckMkClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Mapa de red en vivo (módulo Monitoreo).
 *
 * Mapas con nodos (hosts CheckMK) posicionables por drag & drop y enlaces
 * entre ellos. El estado (verde/rojo/naranja) se consulta en vivo a CheckMK
 * con caché corto, de modo que varias pantallas abiertas generan una sola
 * consulta al servidor de monitoreo.
 */
class MonitoreoMapaController extends Controller
{
    /** Segundos de caché del estado global de hosts (absorbe el polling). */
    private const CACHE_ESTADO = 8;

    /* ── Mapas ───────────────────────────────────────────────────────────── */

    public function index(Request $request)
    {
        $user  = $request->user();
        $mapas = MapaRed::visiblesPara($user)->ordenados()->withCount('nodos')->with('tecnicos:id,name')->get();

        // Técnico con un solo mapa visible: directo a su mapa.
        if (!$user->can('admin') && $mapas->count() === 1) {
            return redirect()->route('admin.monitoreo.mapas.show', $mapas->first());
        }

        return view('admin.monitoreo.mapas.index', [
            'mapas'    => $mapas,
            'tvToken'  => Configuracion::get('monitoreo_tv_token'),
            'usuarios' => $user->can('admin')
                ? \App\Models\User::where('activo', true)->orderBy('name')->get(['id', 'name'])
                : collect(),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('admin'), 403);

        $data = $request->validate([
            'nombre'      => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        $mapa = MapaRed::create($data + [
            'orden'    => (int) MapaRed::max('orden') + 1,
            'tv_token' => Str::random(48),
        ]);

        return redirect()
            ->route('admin.monitoreo.mapas.show', $mapa)
            ->with('success', "Mapa «{$mapa->nombre}» creado. Agrega hosts desde el panel de edición.");
    }

    public function update(Request $request, MapaRed $mapa)
    {
        $user = $request->user();
        abort_unless($mapa->puedeEditar($user), 403);
        $esAdmin = $user->can('admin');

        $data = $request->validate([
            'nombre'         => [$esAdmin ? 'required' : 'nullable', 'string', 'max:255'],
            'descripcion'    => ['nullable', 'string', 'max:255'],
            'orden'          => ['nullable', 'integer', 'min:0', 'max:9999'],
            'en_tv'          => ['nullable', 'boolean'],
            'imagen_fondo'   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
            'fondo_opacidad' => ['nullable', 'integer', 'min:10', 'max:100'],
            'quitar_fondo'   => ['nullable', 'boolean'],
        ]);

        // Los técnicos solo mantienen contenido: nombre, orden y TV son de admin.
        if ($esAdmin) {
            $data['en_tv'] = $request->boolean('en_tv');
            $data['orden'] = $data['orden'] ?? $mapa->orden;
        } else {
            unset($data['nombre'], $data['descripcion'], $data['orden'], $data['en_tv']);
        }
        unset($data['imagen_fondo'], $data['quitar_fondo']);

        // Imagen de fondo (plano de la planta): subir nueva o quitar la actual.
        if ($request->boolean('quitar_fondo')) {
            $this->borrarFondo($mapa);
            $data['imagen_fondo'] = null;
        } elseif ($request->hasFile('imagen_fondo')) {
            $this->borrarFondo($mapa);
            $data['imagen_fondo'] = $request->file('imagen_fondo')->store('mapas_red', 'public');
        }

        $mapa->update($data);

        return back()->with('success', 'Mapa actualizado.');
    }

    public function destroy(Request $request, MapaRed $mapa)
    {
        abort_unless($request->user()->can('admin'), 403);

        $nombre = $mapa->nombre;
        $this->borrarFondo($mapa);
        $mapa->delete();

        return redirect()
            ->route('admin.monitoreo.mapas.index')
            ->with('success', "Mapa «{$nombre}» eliminado.");
    }

    /** Asignación de técnicos y visibilidad pública (solo admin). */
    public function tecnicos(Request $request, MapaRed $mapa)
    {
        abort_unless($request->user()->can('admin'), 403);

        $data = $request->validate([
            'user_ids'        => ['nullable', 'array'],
            'user_ids.*'      => ['integer', 'exists:users,id'],
            'publico_lectura' => ['nullable', 'boolean'],
        ]);

        $mapa->tecnicos()->sync($data['user_ids'] ?? []);
        $mapa->update(['publico_lectura' => $request->boolean('publico_lectura')]);

        return back()->with('success', "Asignación de «{$mapa->nombre}» actualizada.");
    }

    private function borrarFondo(MapaRed $mapa): void
    {
        if ($mapa->imagen_fondo) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($mapa->imagen_fondo);
        }
    }

    /** Vista principal del mapa: edición + en vivo en la misma página. */
    public function show(Request $request, MapaRed $mapa)
    {
        $user = $request->user();
        abort_unless($mapa->puedeVer($user), 403);

        $mapa->load(['nodos', 'enlaces']);

        return view('admin.monitoreo.mapas.show', [
            'mapa'          => $mapa,
            'puedeEditar'   => $mapa->puedeEditar($user),
            'esAdmin'       => $user->can('admin'),
            'nodosData'     => $mapa->nodos->map(self::nodoArray(...))->values(),
            'enlacesData'   => $mapa->enlaces->map(self::enlaceArray(...))->values(),
            'otrosMapas'    => MapaRed::activos()->visiblesPara($user)->ordenados()->where('id', '!=', $mapa->id)->get(),
            'nombresMapas'  => MapaRed::pluck('nombre', 'id'),
            'mapasVisibles' => MapaRed::visiblesPara($user)->pluck('id'),
            'iconos'        => MapaNodo::ICONOS,
            'tipos'         => MapaEnlace::TIPOS,
            'tvUrlMapa'     => $mapa->tv_token ? route('monitoreo.tv', $mapa->tv_token) : null,
        ]);
    }

    /**
     * Miniatura en vivo de un mapa (para el hover de los nodos portal):
     * nodos, enlaces y estado actual en un solo payload liviano.
     */
    public function preview(Request $request, MapaRed $mapa)
    {
        abort_unless($mapa->puedeVer($request->user()), 403);

        $mapa->load(['nodos', 'enlaces']);
        $est = $this->estadoMapa($mapa);

        return response()->json([
            'ok'      => true,
            'id'      => $mapa->id,
            'nombre'  => $mapa->nombre,
            'nodos'   => $mapa->nodos->map(self::nodoArray(...))->values(),
            'enlaces' => $mapa->enlaces->map(self::enlaceArray(...))->values(),
            'estados' => ($est['ok'] ?? false) ? $est['nodos'] : new \stdClass(),
        ]);
    }

    /** Payload JSON de un nodo para el front (Blade no soporta closures en @json). */
    private static function nodoArray(MapaNodo $n): array
    {
        return [
            'id'              => $n->id,
            'host_name'       => $n->host_name,
            'etiqueta'        => $n->etiqueta,
            'icono'           => $n->icono,
            'icono_px'        => $n->icono_px,
            'letra_px'        => $n->letra_px,
            'x'               => $n->x,
            'y'               => $n->y,
            'mapa_destino_id' => $n->mapa_destino_id,
        ];
    }

    private static function enlaceArray(MapaEnlace $e): array
    {
        return [
            'id'        => $e->id,
            'nodo_a_id' => $e->nodo_a_id,
            'nodo_b_id' => $e->nodo_b_id,
            'tipo'      => $e->tipo,
            'etiqueta'  => $e->etiqueta,
        ];
    }

    /* ── Datos para el editor ────────────────────────────────────────────── */

    /** Hosts disponibles en CheckMK (para el buscador del panel de edición). */
    public function hosts()
    {
        try {
            $hosts = Cache::remember('monitoreo_hosts_checkmk', 300, function () {
                return (new CheckMkClient())->listarHosts()->pluck('host_name')->values();
            });
            return response()->json(['ok' => true, 'hosts' => $hosts]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /* ── Estado en vivo ──────────────────────────────────────────────────── */

    /** Estado por nodo del mapa (endpoint de polling de la vista autenticada). */
    public function estado(Request $request, MapaRed $mapa)
    {
        abort_unless($mapa->puedeVer($request->user()), 403);

        return response()->json($this->estadoMapa($mapa));
    }

    /**
     * Calcula el estado de cada nodo del mapa a partir del estado global de
     * hosts (cacheado). Nodos con host → estado directo; nodos sin host pero
     * con mapa destino → estado agregado de ese mapa (drill-down); el resto
     * quedan neutros.
     */
    private function estadoMapa(MapaRed $mapa): array
    {
        try {
            $estados = Cache::remember('monitoreo_estado_hosts', self::CACHE_ESTADO, function () {
                return (new CheckMkClient())->estadoHosts();
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'CheckMK no responde: ' . $e->getMessage()];
        }

        $nodos  = $mapa->nodos()->get();
        $out    = [];
        $caidos = 0;

        foreach ($nodos as $nodo) {
            $info = $this->estadoNodo($nodo, $estados);
            if ($info['estado'] === 'down') $caidos++;
            $out[$nodo->id] = $info;
        }

        return [
            'ok'     => true,
            'nodos'  => $out,
            'caidos' => $caidos,
            'total'  => count($out),
            'ts'     => now()->format('H:i:s'),
        ];
    }

    private function estadoNodo(MapaNodo $nodo, $estados): array
    {
        // Nodo con host CheckMK: estado directo.
        if ($nodo->host_name) {
            $h = $estados->get($nodo->host_name);
            if (!$h) {
                return ['estado' => 'na', 'detalle' => 'Host no encontrado en CheckMK'];
            }
            $estado = $h['downtime'] ? 'downtime' : ($h['state'] === 0 ? 'up' : 'down');
            return [
                'estado'  => $estado,
                'detalle' => Str::limit($h['output'], 140),
                'desde'   => $h['since'] ? \Carbon\Carbon::createFromTimestamp($h['since'])->locale('es')->diffForHumans() : null,
            ];
        }

        // Nodo agrupador con drill-down: agrega los hosts del mapa destino.
        if ($nodo->mapa_destino_id) {
            $hostsDestino = MapaNodo::where('mapa_id', $nodo->mapa_destino_id)
                ->whereNotNull('host_name')->pluck('host_name');

            if ($hostsDestino->isEmpty()) {
                return ['estado' => 'na', 'detalle' => 'Mapa destino sin hosts'];
            }

            $down = 0; $downtime = 0; $up = 0;
            foreach ($hostsDestino as $hn) {
                $h = $estados->get($hn);
                if (!$h) continue;
                if ($h['downtime'])          $downtime++;
                elseif ($h['state'] === 0)   $up++;
                else                         $down++;
            }

            if ($down > 0)     return ['estado' => 'down', 'detalle' => "{$down} de {$hostsDestino->count()} hosts caídos en este mapa"];
            if ($up === 0 && $downtime > 0) return ['estado' => 'downtime', 'detalle' => 'Hosts en mantención programada'];
            if ($up === 0)     return ['estado' => 'na', 'detalle' => 'Sin datos de los hosts del mapa'];
            return ['estado' => 'up', 'detalle' => "{$up} hosts operativos" . ($downtime ? " · {$downtime} en mantención" : '')];
        }

        return ['estado' => 'na', 'detalle' => 'Nodo decorativo (sin host)'];
    }

    /* ── Nodos ───────────────────────────────────────────────────────────── */

    public function nodoStore(Request $request, MapaRed $mapa)
    {
        abort_unless($mapa->puedeEditar($request->user()), 403);

        $data = $request->validate([
            'host_name'       => ['nullable', 'string', 'max:255'],
            'etiqueta'        => ['required', 'string', 'max:255'],
            'icono'           => ['required', 'string', 'in:' . implode(',', array_keys(MapaNodo::ICONOS))],
            'icono_px'        => ['nullable', 'integer', 'min:24', 'max:128'],
            'letra_px'        => ['nullable', 'integer', 'min:8', 'max:28'],
            'x'               => ['required', 'numeric', 'min:0', 'max:1600'],
            'y'               => ['required', 'numeric', 'min:0', 'max:900'],
            'mapa_destino_id' => ['nullable', 'integer', 'exists:mapas_red,id'],
        ]);

        $data['host_name'] = $data['host_name'] ?: null;
        $data['icono_px']  = $data['icono_px'] ?? 48;
        $data['letra_px']  = $data['letra_px'] ?? 11;
        $nodo = $mapa->nodos()->create($data);

        return response()->json(['ok' => true, 'nodo' => $nodo->fresh('mapaDestino')]);
    }

    public function nodoUpdate(Request $request, MapaNodo $nodo)
    {
        abort_unless($nodo->mapa->puedeEditar($request->user()), 403);

        $data = $request->validate([
            'host_name'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'etiqueta'        => ['sometimes', 'required', 'string', 'max:255'],
            'icono'           => ['sometimes', 'required', 'string', 'in:' . implode(',', array_keys(MapaNodo::ICONOS))],
            'icono_px'        => ['sometimes', 'required', 'integer', 'min:24', 'max:128'],
            'letra_px'        => ['sometimes', 'required', 'integer', 'min:8', 'max:28'],
            'x'               => ['sometimes', 'required', 'numeric', 'min:0', 'max:1600'],
            'y'               => ['sometimes', 'required', 'numeric', 'min:0', 'max:900'],
            'mapa_destino_id' => ['sometimes', 'nullable', 'integer', 'exists:mapas_red,id'],
        ]);

        if (array_key_exists('host_name', $data)) {
            $data['host_name'] = $data['host_name'] ?: null;
        }

        $nodo->update($data);

        return response()->json(['ok' => true, 'nodo' => $nodo->fresh('mapaDestino')]);
    }

    public function nodoDestroy(Request $request, MapaNodo $nodo)
    {
        abort_unless($nodo->mapa->puedeEditar($request->user()), 403);

        $nodo->delete();
        return response()->json(['ok' => true]);
    }

    /* ── Enlaces ─────────────────────────────────────────────────────────── */

    public function enlaceStore(Request $request, MapaRed $mapa)
    {
        abort_unless($mapa->puedeEditar($request->user()), 403);

        $data = $request->validate([
            'nodo_a_id' => ['required', 'integer', 'exists:mapa_nodos,id'],
            'nodo_b_id' => ['required', 'integer', 'different:nodo_a_id', 'exists:mapa_nodos,id'],
            'tipo'      => ['required', 'in:' . implode(',', array_keys(MapaEnlace::TIPOS))],
            'etiqueta'  => ['nullable', 'string', 'max:255'],
        ]);

        // Evitar enlaces duplicados (en cualquier dirección).
        $existe = $mapa->enlaces()
            ->where(function ($q) use ($data) {
                $q->where(['nodo_a_id' => $data['nodo_a_id'], 'nodo_b_id' => $data['nodo_b_id']])
                  ->orWhere(function ($q2) use ($data) {
                      $q2->where(['nodo_a_id' => $data['nodo_b_id'], 'nodo_b_id' => $data['nodo_a_id']]);
                  });
            })->exists();

        if ($existe) {
            return response()->json(['ok' => false, 'error' => 'Esos nodos ya están enlazados.'], 422);
        }

        $enlace = $mapa->enlaces()->create($data);

        return response()->json(['ok' => true, 'enlace' => $enlace]);
    }

    public function enlaceUpdate(Request $request, MapaEnlace $enlace)
    {
        abort_unless($enlace->mapa->puedeEditar($request->user()), 403);

        $data = $request->validate([
            'tipo'     => ['required', 'in:' . implode(',', array_keys(MapaEnlace::TIPOS))],
            'etiqueta' => ['nullable', 'string', 'max:255'],
        ]);

        $enlace->update($data);

        return response()->json(['ok' => true, 'enlace' => $enlace]);
    }

    public function enlaceDestroy(Request $request, MapaEnlace $enlace)
    {
        abort_unless($enlace->mapa->puedeEditar($request->user()), 403);

        $enlace->delete();
        return response()->json(['ok' => true]);
    }

    /* ── Modo TV ─────────────────────────────────────────────────────────── */

    /** (Re)genera el token GLOBAL de la URL pública del modo TV (solo admin). */
    public function tvTokenRegenerar(Request $request)
    {
        abort_unless($request->user()->can('admin'), 403);

        Configuracion::set('monitoreo_tv_token', Str::random(48));

        return back()->with('success', 'Token del modo TV global regenerado. La URL anterior quedó inválida.');
    }

    /** (Re)genera el token TV propio de UN mapa (solo admin). */
    public function tvTokenMapa(Request $request, MapaRed $mapa)
    {
        abort_unless($request->user()->can('admin'), 403);

        $mapa->update(['tv_token' => Str::random(48)]);

        return back()->with('success', "URL TV de «{$mapa->nombre}» regenerada. La anterior quedó inválida.");
    }

    /**
     * Vista pública del modo TV (sin login, autenticada por token).
     * - Token global: rota entre los mapas marcados "en TV" (?mapa=ID fija uno).
     * - Token propio de un mapa: muestra solo ese mapa.
     */
    public function tv(Request $request, string $token)
    {
        $mapaPropio = $this->mapaPorTokenTv($token);

        if ($mapaPropio) {
            $mapas = collect([$mapaPropio->load(['nodos', 'enlaces'])]);
        } else {
            $this->validarTokenTv($token);
            $mapas = MapaRed::activos()->where('en_tv', true)->ordenados()
                ->with(['nodos', 'enlaces'])->get();

            if ($request->filled('mapa')) {
                $mapas = $mapas->where('id', (int) $request->input('mapa'))->values();
            }
        }

        abort_if($mapas->isEmpty(), 404, 'No hay mapas habilitados para el modo TV.');

        $mapasData = $mapas->map(fn(MapaRed $m) => [
            'id'       => $m->id,
            'nombre'   => $m->nombre,
            'fondo'    => $m->fondo_url,
            'opacidad' => $m->fondo_opacidad,
            'nodos'    => $m->nodos->map(self::nodoArray(...))->values(),
            'enlaces'  => $m->enlaces->map(self::enlaceArray(...))->values(),
        ])->values();

        return view('admin.monitoreo.tv', [
            'mapasData' => $mapasData,
            'token'     => $token,
            'rotacion'  => max(10, (int) $request->input('rotacion', 30)), // segundos por mapa
            'intervalo' => max(10, (int) $request->input('intervalo', 30)), // polling
        ]);
    }

    /** Endpoint de polling del modo TV (público, autenticado por token). */
    public function tvEstado(string $token, MapaRed $mapa)
    {
        // Token propio del mapa consultado, o token global.
        if (!($mapa->tv_token && hash_equals($mapa->tv_token, $token))) {
            $this->validarTokenTv($token);
        }

        return response()->json($this->estadoMapa($mapa));
    }

    private function validarTokenTv(string $token): void
    {
        $real = (string) Configuracion::get('monitoreo_tv_token', '');
        abort_if($real === '' || !hash_equals($real, $token), 404);
    }

    private function mapaPorTokenTv(string $token): ?MapaRed
    {
        return $token !== '' ? MapaRed::activos()->where('tv_token', $token)->first() : null;
    }
}
