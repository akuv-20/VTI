<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Compania;
use App\Models\Empresa;
use App\Models\MapaRed;
use App\Models\Sitio;
use App\Models\SitioEquipo;
use App\Models\SitioFoto;
use App\Models\SitioHost;
use App\Models\User;
use App\Models\Zona;
use App\Services\CheckMkClient;
use App\Services\GaleriaFotos;
use App\Services\SitiosCheckMk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SitioController extends Controller
{
    public function __construct(
        private SitiosCheckMk $puente = new SitiosCheckMk(),
        private GaleriaFotos $galeria = new GaleriaFotos(),
    ) {}

    /* ── Listado ─────────────────────────────────────────────────────────── */

    public function index(Request $request)
    {
        $sitios = Sitio::activos()
            ->tipo($request->input('tipo'))
            ->estadoEnlace($request->input('estado'))
            ->zona($request->input('zona'))
            ->buscar($request->input('q'))
            ->with(['fotos', 'tecnico:id,name', 'zona:id,nombre'])
            ->withCount('equipos')
            ->ordenados()
            ->get();

        $todos = Sitio::activos()->get(['tipo', 'estado_enlace', 'zona_id']);

        return view('admin.sitios.index', [
            'sitios'  => $sitios,
            'tipo'    => $request->input('tipo'),
            'estado'  => $request->input('estado'),
            'zona'    => $request->input('zona'),
            'q'       => $request->input('q'),
            'zonas'   => Zona::withCount('sitios')->ordenadas()->get(),
            'conteos' => [
                'total'  => $todos->count(),
                'tipos'  => collect(Sitio::TIPOS)->map(fn($_, $k) => $todos->where('tipo', $k)->count()),
                'estados' => collect(Sitio::ESTADOS_ENLACE)->map(fn($_, $k) => $todos->where('estado_enlace', $k)->count()),
                'sin_zona' => $todos->whereNull('zona_id')->count(),
            ],
        ]);
    }

    /* ── Ficha ───────────────────────────────────────────────────────────── */

    public function show(Sitio $sitio)
    {
        $sitio->load(['equipos.fotos', 'hosts', 'fotos', 'tecnico', 'levantadoPor', 'mapa', 'empresa', 'isp', 'zona']);

        return view('admin.sitios.show', [
            'sitio'        => $sitio,
            'estadoHosts'  => $this->puente->estadoDeSitio($sitio),
            'usuarios'     => User::where('activo', true)->orderBy('name')->get(['id', 'name']),
            'mapas'        => MapaRed::activos()->ordenados()->get(),
            'hostsCheckMk' => $this->listaHosts(),
            'empresas'     => Empresa::orderBy('nombre')->get(['id', 'nombre']),
            'companias'    => Compania::orderBy('nombre')->get(['id', 'nombre']),
            'zonas'        => Zona::withCount('sitios')->ordenadas()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'  => ['required', 'string', 'max:255'],
            'tipo'    => ['required', 'in:' . implode(',', array_keys(Sitio::TIPOS))],
            'codigo'  => ['nullable', 'string', 'max:30'],
            'zona_id' => ['nullable', 'integer', 'exists:zonas,id'],
        ]);

        $sitio = Sitio::create($data);

        return redirect()
            ->route('admin.sitios.show', $sitio)
            ->with('success', "Ficha de «{$sitio->nombre}» creada. Completa los datos cuando los tengas.");
    }

    public function update(Request $request, Sitio $sitio)
    {
        $data = $request->validate([
            'codigo'            => ['nullable', 'string', 'max:30'],
            'nombre'            => ['required', 'string', 'max:255'],
            'tipo'              => ['required', 'in:' . implode(',', array_keys(Sitio::TIPOS))],
            'estado_enlace'     => ['required', 'in:' . implode(',', array_keys(Sitio::ESTADOS_ENLACE))],
            'empresa_id'        => ['nullable', 'integer', 'exists:empresas,id'],
            'zona_id'           => ['nullable', 'integer', 'exists:zonas,id'],

            // Evaluación de factibilidad. Vive acá y no en el formulario móvil:
            // son datos que no se contestan parado en el campo. Sin estas reglas
            // los campos se pintarían pero `update()` los descartaría en silencio.
            'eval_internet_particular' => ['nullable', 'in:0,1'],
            'eval_internet_detalle'    => ['nullable', 'string', 'max:255'],
            'eval_infra_existente'     => ['nullable', 'string', 'max:2000'],
            'eval_linea_vista'         => ['nullable', 'in:' . implode(',', array_keys(Sitio::EVAL_LINEA_VISTA))],
            'eval_linea_vista_hacia'   => ['nullable', 'string', 'max:255'],
            'eval_distancia_km'        => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'eval_necesita_camaras'    => ['nullable', 'in:0,1'],
            'eval_necesita_wifi'       => ['nullable', 'in:0,1'],
            'eval_uso_previsto'        => ['nullable', 'string', 'max:2000'],
            'solucion_propuesta'       => ['nullable', 'in:' . implode(',', array_keys(Sitio::SOLUCIONES))],
            'orden_ejecucion'          => ['nullable', 'integer', 'min:0', 'max:999'],
            'costo_estimado'           => ['nullable', 'integer', 'min:0', 'max:999999999'],
            'acciones'                 => ['nullable', 'string', 'max:2000'],
            'region'            => ['nullable', 'string', 'max:255'],
            'comuna'            => ['nullable', 'string', 'max:255'],
            'maps_url'          => ['nullable', 'string', 'max:2000'],
            'acceso'            => ['nullable', 'string', 'max:2000'],
            'latitud'           => ['nullable', 'numeric', 'between:-90,90'],
            'longitud'          => ['nullable', 'numeric', 'between:-180,180'],
            'enlace_tipo'       => ['nullable', 'in:' . implode(',', array_keys(Sitio::ENLACE_TIPOS))],
            'isp_id'            => ['nullable', 'integer', 'exists:companias,id'],
            'ancho_banda'       => ['nullable', 'string', 'max:60'],
            'ancho_banda_mpls'  => ['nullable', 'string', 'max:60'],
            'ip_publica'        => ['nullable', 'string', 'max:60'],
            'num_servicio'      => ['nullable', 'string', 'max:80'],
            'fecha_instalacion' => ['nullable', 'date'],
            'subred'            => ['nullable', 'string', 'max:60'],
            'vlan'              => ['nullable', 'string', 'max:60'],
            'gateway'           => ['nullable', 'string', 'max:60'],
            'vpn'               => ['nullable', 'in:0,1'],
            'vpn_detalle'       => ['nullable', 'string', 'max:255'],
            'gabinete'          => ['nullable', 'string', 'max:255'],
            'ups_modelo'        => ['nullable', 'string', 'max:255'],
            'ups_kva'           => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'superficie_ha'     => ['nullable', 'numeric', 'min:0'],
            'especies'          => ['nullable', 'string', 'max:255'],
            'usuarios_cant'     => ['nullable', 'integer', 'min:0', 'max:9999'],
            'pcs_cant'          => ['nullable', 'integer', 'min:0', 'max:9999'],
            'temporada'         => ['nullable', 'string', 'max:80'],
            'racks_cant'        => ['nullable', 'integer', 'min:0', 'max:999'],
            'racks_u_usados'    => ['nullable', 'integer', 'min:0', 'max:9999'],
            'racks_u_totales'   => ['nullable', 'integer', 'min:0', 'max:9999'],
            'encargado_nombre'  => ['nullable', 'string', 'max:255'],
            'encargado_telefono' => ['nullable', 'string', 'max:60'],
            'encargado_email'   => ['nullable', 'email', 'max:255'],
            'tecnico_id'        => ['nullable', 'integer', 'exists:users,id'],
            'mapa_id'           => ['nullable', 'integer', 'exists:mapas_red,id'],
            'notas'             => ['nullable', 'string', 'max:5000'],
        ]);

        // `vpn` queda fuera a propósito: es tri-estado. Pasarlo por `boolean()`
        // convertiría «no lo he revisado» en «no tiene», que es justo la
        // diferencia que el informe necesita conservar.
        foreach (['generador', 'puesta_tierra', 'climatizacion', 'estacional'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        $urlCambio = ($data['maps_url'] ?? null) !== $sitio->maps_url;
        $data = $this->conCoordenadasDelLink($data, $urlCambio || !$sitio->latitud);

        $sitio->update($data);

        $aviso = 'Ficha actualizada.';
        if ($urlCambio && ($data['latitud'] ?? null) && !$request->filled('latitud')) {
            $aviso .= ' Se tomaron las coordenadas del link de Maps.';
        }

        return back()->with('success', $aviso);
    }

    public function destroy(Request $request, Sitio $sitio)
    {
        abort_unless($request->user()->can('admin'), 403);

        foreach ($sitio->fotos as $foto) {
            $this->galeria->eliminar($foto);
        }
        foreach ($sitio->equipos as $equipo) {
            foreach ($equipo->fotos as $foto) $this->galeria->eliminar($foto);
        }

        $nombre = $sitio->nombre;
        $sitio->delete();

        return redirect()->route('admin.sitios.index')->with('success', "Ficha de «{$nombre}» eliminada.");
    }

    /** Duplica una ficha con su equipamiento (sin fotos ni hosts). */
    public function clonar(Request $request, Sitio $sitio)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'codigo' => ['nullable', 'string', 'max:30'],
        ]);

        $nuevo = $sitio->replicate([
            'codigo', 'nombre', 'latitud', 'longitud', 'maps_url', 'ip_publica', 'num_servicio',
            'levantado_at', 'levantado_por', 'mapa_id', 'fecha_instalacion',
        ]);
        $nuevo->fill($data + ['estado_enlace' => 'sin_enlace']);
        $nuevo->save();

        // Copia el equipamiento como plantilla, sin datos únicos.
        foreach ($sitio->equipos as $equipo) {
            $copia = $equipo->replicate(['serie', 'mac', 'ip_gestion', 'host_name', 'fecha_compra', 'garantia_hasta']);
            $copia->sitio_id = $nuevo->id;
            $copia->save();
        }

        return redirect()
            ->route('admin.sitios.show', $nuevo)
            ->with('success', "Ficha clonada desde «{$sitio->nombre}» con {$sitio->equipos->count()} equipos como plantilla.");
    }

    /* ── Hosts de CheckMK ────────────────────────────────────────────────── */

    public function hostStore(Request $request, Sitio $sitio)
    {
        $data = $request->validate([
            'host_name' => ['required', 'string', 'max:255'],
            'rol'       => ['required', 'in:' . implode(',', array_keys(SitioHost::ROLES))],
        ]);

        $sitio->hosts()->firstOrCreate(['host_name' => $data['host_name']], ['rol' => $data['rol']]);

        return back()->with('success', "Host {$data['host_name']} enlazado a la ficha.");
    }

    public function hostDestroy(SitioHost $host)
    {
        $host->delete();

        return back()->with('success', 'Host desenlazado.');
    }

    /* ── Equipos ─────────────────────────────────────────────────────────── */

    public function equipoStore(Request $request, Sitio $sitio)
    {
        $data = $this->validarEquipo($request);
        $sitio->equipos()->create($data);

        return back()->with('success', 'Equipo agregado.');
    }

    public function equipoUpdate(Request $request, SitioEquipo $equipo)
    {
        $data = $this->validarEquipo($request);
        $equipo->update($data);

        return back()->with('success', 'Equipo actualizado.');
    }

    public function equipoDestroy(SitioEquipo $equipo)
    {
        foreach ($equipo->fotos as $foto) $this->galeria->eliminar($foto);
        $equipo->delete();

        return back()->with('success', 'Equipo eliminado.');
    }

    private function validarEquipo(Request $request): array
    {
        $data = $request->validate([
            'tipo'             => ['required', 'in:' . implode(',', array_keys(SitioEquipo::TIPOS))],
            'nombre'           => ['required', 'string', 'max:255'],
            'marca'            => ['nullable', 'string', 'max:255'],
            'modelo'           => ['nullable', 'string', 'max:255'],
            'serie'            => ['nullable', 'string', 'max:255'],
            'mac'              => ['nullable', 'string', 'max:40'],
            'estado'           => ['required', 'in:' . implode(',', array_keys(SitioEquipo::ESTADOS))],
            'ip_gestion'       => ['nullable', 'string', 'max:60'],
            'vlan'             => ['nullable', 'string', 'max:60'],
            'firmware'         => ['nullable', 'string', 'max:80'],
            'puertos_totales'  => ['nullable', 'integer', 'min:0', 'max:999'],
            'puertos_usados'   => ['nullable', 'integer', 'min:0', 'max:999'],
            'uplink'           => ['nullable', 'string', 'max:255'],
            'zona'             => ['nullable', 'string', 'max:255'],
            'rack_u'           => ['nullable', 'string', 'max:30'],
            'ptp_frecuencia'   => ['nullable', 'string', 'max:40'],
            'ptp_azimut'       => ['nullable', 'integer', 'min:0', 'max:360'],
            'ptp_distancia_km' => ['nullable', 'numeric', 'min:0'],
            'ptp_par_remoto'   => ['nullable', 'string', 'max:255'],
            'ptp_altura_m'     => ['nullable', 'numeric', 'min:0'],
            'ap_ssids'         => ['nullable', 'string', 'max:255'],
            'ap_banda'         => ['nullable', 'string', 'max:30'],
            'ap_canal'         => ['nullable', 'string', 'max:30'],
            'host_name'        => ['nullable', 'string', 'max:255'],
            'proveedor'        => ['nullable', 'string', 'max:255'],
            'fecha_compra'     => ['nullable', 'date'],
            'garantia_hasta'   => ['nullable', 'date'],
            'notas'            => ['nullable', 'string', 'max:2000'],
        ]);

        $data['poe'] = $request->boolean('poe');

        return $data;
    }

    /* ── Fotos ───────────────────────────────────────────────────────────── */

    public function fotoStore(Request $request)
    {
        $request->validate([
            'fotos'     => ['required', 'array', 'max:12'],
            'fotos.*'   => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:12288'],
            'sitio_id'  => ['nullable', 'integer', 'exists:sitios,id'],
            'equipo_id' => ['nullable', 'integer', 'exists:sitio_equipos,id'],
            'categoria' => ['nullable', 'in:' . implode(',', array_keys(SitioFoto::CATEGORIAS))],
        ]);

        $modelo = $request->filled('equipo_id')
            ? SitioEquipo::findOrFail($request->input('equipo_id'))
            : Sitio::findOrFail($request->input('sitio_id'));

        @set_time_limit(0); // redimensionar varias fotos de celular toma tiempo

        $n = 0;
        foreach ($request->file('fotos') as $archivo) {
            $this->galeria->guardar($modelo, $archivo, ['categoria' => $request->input('categoria')]);
            $n++;
        }

        return back()->with('success', $n === 1 ? 'Foto agregada.' : "{$n} fotos agregadas.");
    }

    public function fotoUpdate(Request $request, SitioFoto $foto)
    {
        $data = $request->validate([
            'titulo'    => ['nullable', 'string', 'max:255'],
            'categoria' => ['nullable', 'in:' . implode(',', array_keys(SitioFoto::CATEGORIAS))],
        ]);

        if ($request->boolean('portada')) {
            SitioFoto::where('fotable_type', $foto->fotable_type)
                ->where('fotable_id', $foto->fotable_id)
                ->update(['portada' => false]);
            $data['portada'] = true;
        }

        $foto->update($data);

        return back()->with('success', 'Foto actualizada.');
    }

    public function fotoDestroy(SitioFoto $foto)
    {
        $this->galeria->eliminar($foto);

        return back()->with('success', 'Foto eliminada.');
    }

    /* ── Helpers ─────────────────────────────────────────────────────────── */

    /**
     * Rellena latitud/longitud a partir del link de Maps cuando vienen vacías.
     * Si el usuario escribió coordenadas a mano, esas mandan.
     */
    private function conCoordenadasDelLink(array $data, bool $permitirSobrescribir): array
    {
        if (!empty($data['latitud']) && !empty($data['longitud'])) return $data;
        if (!$permitirSobrescribir) return $data;

        $coords = Sitio::coordenadasDesdeUrl($data['maps_url'] ?? null);
        if ($coords) {
            [$data['latitud'], $data['longitud']] = $coords;
        }

        return $data;
    }

    /** Lista de hosts de CheckMK para los selectores (cacheada). */
    private function listaHosts(): array
    {
        try {
            return Cache::remember('monitoreo_hosts_checkmk', 300, function () {
                return (new CheckMkClient())->listarHosts()->pluck('host_name')->values()->all();
            });
        } catch (\Throwable) {
            return [];
        }
    }
}
