<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Sitio extends Model
{
    protected $table = 'sitios';

    protected $guarded = ['id'];

    protected $casts = [
        'vpn'               => 'boolean',
        'generador'         => 'boolean',
        'puesta_tierra'     => 'boolean',
        'climatizacion'     => 'boolean',
        'estacional'        => 'boolean',
        'activo'            => 'boolean',
        'fecha_instalacion' => 'date',
        'levantado_at'      => 'datetime',
        'latitud'           => 'float',
        'longitud'          => 'float',
        'superficie_ha'     => 'float',
        'ups_kva'           => 'float',

        // Evaluación en terreno. Estos booleanos admiten null porque
        // "no lo evalué" no es lo mismo que "no hay".
        'eval_energia_estable'     => 'boolean',
        'eval_internet_particular' => 'boolean',
        'eval_cielo_despejado'     => 'boolean',
        'eval_necesita_camaras'    => 'boolean',
        'eval_necesita_wifi'       => 'boolean',
        'eval_distancia_km'        => 'float',
        'eval_altura_m'            => 'float',
    ];

    public const TIPOS = [
        'planta'     => 'Planta',
        'campo'      => 'Campo',
        'datacenter' => 'Datacenter',
        'oficina'    => 'Oficina',
    ];

    public const ICONOS_TIPO = [
        'planta'     => 'bi-buildings',
        'campo'      => 'bi-pin-map-fill',
        'datacenter' => 'bi-hdd-rack-fill',
        'oficina'    => 'bi-building-fill',
    ];

    /**
     * Etapas del enlazamiento (estado de proyecto, distinto del estado en vivo).
     *
     * «Solo Internet» es el campo que hoy tiene un internet cualquiera —del dueño,
     * un 4G, un Starlink— pero todavía no nuestro enlace. Reemplaza a la pregunta
     * «¿tienen algún internet hoy?» del levantamiento: se responde eligiendo este
     * estado y el tipo de enlace, en vez de con una casilla aparte.
     */
    public const ESTADOS_ENLACE = [
        'sin_enlace'      => 'Sin enlace',
        'solo_internet'   => 'Solo Internet',
        'en_gestion'      => 'En gestión',
        'en_instalacion'  => 'En instalación',
        'operativo'       => 'Operativo',
        'intermitente'    => 'Intermitente',
    ];

    public const COLORES_ENLACE = [
        'sin_enlace'     => '#94a3b8',
        'solo_internet'  => '#0d9488',
        'en_gestion'     => '#d97706',
        'en_instalacion' => '#0284c7',
        'operativo'      => '#16a34a',
        'intermitente'   => '#dc2626',
    ];

    public const ENLACE_TIPOS = [
        'fibra'     => 'Fibra óptica',
        'ptp'       => 'Punto a punto',
        'starlink'  => 'Starlink',
        '4g'        => '4G / LTE',
        'satelital' => 'Satelital',
        'ninguno'   => 'Sin enlace',
    ];

    /* ── Evaluación en terreno de sitios sin enlace ───────────────────────── */

    /**
     * Energía en el sitio, sí o no.
     *
     * Antes distinguía monofásica, trifásica y generador. En terreno eso no se
     * usó —de 12 levantamientos, 9 quedaron sin responder— y para decidir un
     * enlace da lo mismo: lo que importa es si hay de dónde alimentar el equipo.
     */
    public const EVAL_ENERGIA = [
        'si' => 'Sí hay',
        'no' => 'No hay',
    ];

    /** Operadores que se miden en terreno, en el orden en que se muestran. */
    public const OPERADORES = [
        'cob_entel'    => 'Entel',
        'cob_movistar' => 'Movistar',
        'cob_wom'      => 'WOM',
        'cob_claro'    => 'Claro',
    ];

    public const COBERTURA = [
        'sin'     => 'Sin señal',
        'mala'    => 'Mala',
        'regular' => 'Regular',
        'buena'   => 'Buena',
    ];

    /**
     * Etiquetas cortas para los botones del celular: con "Sin señal" completa,
     * los cuatro operadores no caben en una fila de 375 px y se parten en dos.
     */
    public const COBERTURA_CORTA = [
        'sin'     => 'Sin',
        'mala'    => 'Mala',
        'regular' => 'Reg.',
        'buena'   => 'Buena',
    ];

    public const EVAL_LINEA_VISTA = [
        'si'      => 'Sí, despejada',
        'parcial' => 'Parcial (obstáculos)',
        'no'      => 'No hay',
    ];

    public const EVAL_FIBRA_ZONA = [
        'si'    => 'Sí, hay fibra en la zona',
        'no'    => 'No hay',
        'no_se' => 'No se pudo averiguar',
    ];

    public const EVAL_PUNTO_MONTAJE = [
        'poste'  => 'Poste',
        'torre'  => 'Torre',
        'techo'  => 'Techo / galpón',
        'silo'   => 'Silo / estructura alta',
        'no_hay' => 'No hay dónde montar',
    ];

    public const EVAL_SALA_EQUIPOS = [
        'si'     => 'Sí, hay sala',
        'caseta' => 'Caseta / gabinete',
        'no'     => 'No hay',
    ];

    public const SOLUCIONES = [
        'fibra'         => 'Fibra óptica',
        'ptp'           => 'Enlace punto a punto',
        'starlink'      => 'Starlink',
        '4g'            => '4G / LTE',
        'satelital'     => 'Satelital',
        'sin_solucion'  => 'Sin solución viable por ahora',
    ];

    /**
     * Campos exigidos por tipo para considerar la ficha "completa".
     * Alimenta el % de completitud y el estándar de levantamiento.
     */
    public const REQUISITOS = [
        'comun' => ['codigo', 'nombre', 'region', 'comuna', 'encargado_nombre', 'encargado_telefono'],
        'campo' => ['acceso', 'maps_url', 'enlace_tipo', 'superficie_ha', 'usuarios_cant'],
        'planta' => ['acceso', 'maps_url', 'enlace_tipo', 'isp_id', 'subred', 'usuarios_cant', 'pcs_cant'],
        'datacenter' => ['enlace_tipo', 'isp_id', 'subred', 'racks_cant', 'ups_modelo', 'climatizacion'],
        'oficina' => ['maps_url', 'enlace_tipo', 'usuarios_cant'],
    ];

    /**
     * Requisitos de un sitio que todavía se está evaluando (sin enlace).
     *
     * Reemplaza a los de arriba —no se suman— porque miden cosas distintas: los
     * de un sitio operativo preguntan por ISP, subred o superficie, que en un
     * campo sin nada no existen todavía y no dicen nada del avance del
     * levantamiento.
     *
     * Acá va SOLO lo que hay que estar parado en el sitio para saber. Todo lo
     * que se puede completar después desde el escritorio (código, comuna,
     * superficie, costo, orden de ejecución) queda fuera a propósito: si contara,
     * el porcentaje mediría trabajo de oficina en vez de decir si la visita
     * quedó bien hecha.
     *
     * La lista se ajustó el 2026-08-12 con los 12 levantamientos reales de los
     * campos de Linderos y Maipú. Salieron cinco requisitos que la visita casi
     * nunca dejaba resueltos, cada uno por un motivo distinto:
     *
     *   - `acceso` (0/12): describir el portón y la ruta es texto largo, y en el
     *     celular no se escribe. Lo cubre la foto de categoría «Entorno / acceso».
     *   - `encargado_nombre` (1/12) y `encargado_telefono` (0/12): dependen de que
     *     haya alguien en el campo ese día. Se piden en el escritorio.
     *   - `eval_linea_vista` (8/12): rara vez aplica; pasó al escritorio.
     *   - `solucion_propuesta` (2/12): es una decisión que se toma después, no
     *     parado en el campo.
     *
     * Y entraron los que la visita sí resuelve siempre: tipo de enlace, energía
     * estable, cielo despejado, fibra en la zona y haber sacado alguna foto.
     */
    public const REQUISITOS_EVALUACION = [
        'maps_url',            // el GPS solo se puede tomar estando ahí
        'cobertura_medida',    // atributo calculado: al menos un operador medido
        'enlace_tipo',
        'eval_energia',
        'eval_energia_estable',
        'eval_cielo_despejado',
        'eval_fibra_zona',
        'eval_punto_montaje',
        'vpn',                 // sí/no explícito: «no tiene» es un dato del informe
        'tiene_fotos',         // atributo calculado: la visita dejó registro visual
    ];

    /**
     * Requisitos que solo aplican si el sitio tiene algún enlace.
     *
     * Preguntar el ancho de banda y el proveedor de un campo donde no llega nada
     * no tiene sentido, y contarlos como faltantes castigaría una visita que
     * quedó bien hecha.
     */
    public const REQUISITOS_CON_ENLACE = ['ancho_banda', 'isp_id'];

    /** Nombre legible de los campos, para la lista de «lo que falta». */
    public const ETIQUETAS = [
        'codigo'             => 'código',
        'maps_url'           => 'ubicación en Maps',
        'isp_id'             => 'ISP',
        'empresa_id'         => 'empresa',
        'enlace_tipo'        => 'tipo de enlace',
        'superficie_ha'      => 'superficie',
        'usuarios_cant'      => 'usuarios',
        'pcs_cant'           => 'PCs',
        'racks_cant'         => 'racks',
        'ups_modelo'         => 'UPS',
        'ups_kva'            => 'capacidad UPS',
        'encargado_nombre'   => 'encargado',
        'encargado_telefono' => 'teléfono del encargado',
        'acceso'             => 'cómo llegar',

        // Levantamiento de factibilidad
        'cobertura_medida'     => 'cobertura móvil',
        'eval_energia'         => 'energía',
        'eval_energia_estable' => 'si la energía es estable',
        'eval_cielo_despejado' => 'cielo despejado',
        'eval_fibra_zona'      => 'fibra en la zona',
        'eval_linea_vista'     => 'línea de vista',
        'eval_punto_montaje'   => 'punto de montaje',
        'solucion_propuesta'   => 'solución propuesta',
        'tiene_fotos'          => 'fotos del sitio',
        'vpn'                  => 'VPN al datacenter',
        'ancho_banda'          => 'ancho de banda',
    ];

    /* ── Relaciones ──────────────────────────────────────────────────────── */

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /** Agrupación de operación, por encima de comuna y región. */
    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'zona_id');
    }

    /** Proveedor del enlace: sale del mantenedor de compañías de facturación. */
    public function isp(): BelongsTo
    {
        return $this->belongsTo(Compania::class, 'isp_id');
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(SitioEquipo::class, 'sitio_id');
    }

    public function hosts(): HasMany
    {
        return $this->hasMany(SitioHost::class, 'sitio_id');
    }

    public function fotos(): MorphMany
    {
        return $this->morphMany(SitioFoto::class, 'fotable')->orderBy('orden')->orderBy('id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function levantadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'levantado_por');
    }

    public function mapa(): BelongsTo
    {
        return $this->belongsTo(MapaRed::class, 'mapa_id');
    }

    /* ── Scopes ──────────────────────────────────────────────────────────── */

    public function scopeActivos($q)
    {
        return $q->where('activo', true);
    }

    public function scopeTipo($q, ?string $tipo)
    {
        return $tipo ? $q->where('tipo', $tipo) : $q;
    }

    public function scopeEstadoEnlace($q, ?string $estado)
    {
        return $estado ? $q->where('estado_enlace', $estado) : $q;
    }

    /**
     * Filtra por zona. El valor 'sin' aísla los sitios que todavía no la tienen
     * asignada, que es lo que hay que ir cerrando después de crear una zona.
     */
    public function scopeZona($q, $zona)
    {
        if ($zona === null || $zona === '') return $q;

        return $zona === 'sin' ? $q->whereNull('zona_id') : $q->where('zona_id', $zona);
    }

    public function scopeBuscar($q, ?string $texto)
    {
        if (!$texto) return $q;

        return $q->where(function ($sub) use ($texto) {
            $like = '%' . $texto . '%';
            $sub->where('nombre', 'like', $like)
                ->orWhere('codigo', 'like', $like)
                ->orWhere('comuna', 'like', $like)
                ->orWhere('encargado_nombre', 'like', $like);
        });
    }

    public function scopeOrdenados($q)
    {
        // Código numérico primero (2, 3, 12…), luego el resto por nombre.
        return $q->orderByRaw('CAST(codigo AS UNSIGNED) = 0, CAST(codigo AS UNSIGNED)')->orderBy('nombre');
    }

    /* ── Atributos derivados ─────────────────────────────────────────────── */

    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    public function getIconoAttribute(): string
    {
        return self::ICONOS_TIPO[$this->tipo] ?? 'bi-geo-alt';
    }

    public function getEstadoEnlaceLabelAttribute(): string
    {
        return self::ESTADOS_ENLACE[$this->estado_enlace] ?? $this->estado_enlace;
    }

    public function getEstadoEnlaceColorAttribute(): string
    {
        return self::COLORES_ENLACE[$this->estado_enlace] ?? '#94a3b8';
    }

    public function getTituloAttribute(): string
    {
        return $this->codigo ? "{$this->codigo}. {$this->nombre}" : $this->nombre;
    }

    public function getPortadaAttribute(): ?SitioFoto
    {
        return $this->fotos->firstWhere('portada', true) ?? $this->fotos->first();
    }

    /** Link para abrir el sitio en Maps: el guardado, o uno armado con las coordenadas. */
    public function getMapsLinkAttribute(): ?string
    {
        if ($this->maps_url) return $this->maps_url;

        return ($this->latitud && $this->longitud)
            ? "https://www.google.com/maps?q={$this->latitud},{$this->longitud}"
            : null;
    }

    /**
     * Extrae las coordenadas de un link de Google Maps.
     * Cubre las formas que devuelven «Compartir», la app móvil y el navegador.
     */
    public static function coordenadasDesdeUrl(?string $url): ?array
    {
        if (!$url) return null;

        $patrones = [
            '/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/',                          // .../data=…!3dLAT!4dLNG
            '/@(-?\d+\.\d+),\s*(-?\d+\.\d+)/',                           // .../@LAT,LNG,17z
            '/[?&](?:q|query|ll|sll|daddr|center)=(-?\d+\.\d+),\s*(-?\d+\.\d+)/', // ?q=LAT,LNG
            '/^\s*(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)\s*$/',                 // pegar solo las coordenadas
        ];

        foreach ($patrones as $patron) {
            if (preg_match($patron, $url, $m)) {
                $lat = (float) $m[1];
                $lng = (float) $m[2];
                if (abs($lat) <= 90 && abs($lng) <= 180) return [$lat, $lng];
            }
        }

        return null;
    }

    /** Campos requeridos para este tipo de sitio. */
    /**
     * ¿El sitio está en etapa de evaluación?
     *
     * Un campo sin enlace o recién en gestión todavía se está levantando; en
     * cuanto pasa a instalación u operativo se le exigen los datos de un sitio
     * en funcionamiento.
     */
    public function enEvaluacion(): bool
    {
        // «En instalación» y «Solo Internet» siguen siendo etapas de evaluación:
        // el enlace nuestro todavía no está. Si no estuvieran, la ficha cambiaría
        // sola de vara al avanzar de estado y su porcentaje pasaría a medir otra
        // cosa —a mitad del proyecto y sin avisar—, que fue justo lo que pasó con
        // «Linderos - 1V Don Miguel».
        return $this->tipo === 'campo'
            && in_array($this->estado_enlace, ['sin_enlace', 'solo_internet', 'en_gestion', 'en_instalacion'], true);
    }

    /** Atributo calculado: ¿se midió la señal de al menos un operador? */
    public function getCoberturaMedidaAttribute(): ?bool
    {
        foreach (array_keys(self::OPERADORES) as $campo) {
            if (!empty($this->getAttribute($campo))) return true;
        }
        return null;   // null = no medido, para que cuente como faltante
    }

    /**
     * Atributo calculado: ¿la visita dejó alguna foto?
     *
     * Sustituye a `acceso` escrito a mano: en los 12 levantamientos reales nadie
     * escribió la ruta pero los 12 trajeron fotos, y una foto del portón dice más
     * que un párrafo tecleado con una mano en el celular.
     */
    public function getTieneFotosAttribute(): ?bool
    {
        // Si la relación ya viene cargada se usa esa, sin volver a la base: el
        // listado calcula la completitud de cada tarjeta y con 67 sitios esto
        // serían 67 consultas de más por cada carga de pantalla.
        $hay = $this->relationLoaded('fotos')
            ? $this->fotos->isNotEmpty()
            : $this->fotos()->exists();

        return $hay ?: null;   // null = falta, para el conteo
    }

    public function requisitos(): array
    {
        if ($this->enEvaluacion()) {
            return array_merge(
                self::REQUISITOS_EVALUACION,
                $this->tieneEnlace() ? self::REQUISITOS_CON_ENLACE : []
            );
        }

        return array_merge(self::REQUISITOS['comun'], self::REQUISITOS[$this->tipo] ?? []);
    }

    /** ¿Se declaró algún enlace? «Sin enlace» y sin responder no cuentan. */
    public function tieneEnlace(): bool
    {
        return $this->enlace_tipo !== null && $this->enlace_tipo !== '' && $this->enlace_tipo !== 'ninguno';
    }

    /** Lista de campos requeridos que están vacíos. */
    public function faltantes(): array
    {
        return array_values(array_filter($this->requisitos(), function ($campo) {
            $v = $this->getAttribute($campo);
            return $v === null || $v === '' || $v === 0;
        }));
    }

    /** Lo que falta, en palabras («ubicación en Maps, ISP, subred»). */
    public function faltantesEnPalabras(): string
    {
        return collect($this->faltantes())
            ->map(fn($c) => self::ETIQUETAS[$c] ?? str_replace('_', ' ', $c))
            ->implode(', ');
    }

    /** % de completitud de la ficha (0-100). */
    public function getCompletitudAttribute(): int
    {
        $req = $this->requisitos();
        if (empty($req)) return 100;

        return (int) round((count($req) - count($this->faltantes())) / count($req) * 100);
    }

    /** Nombres de host de CheckMK asociados al sitio (incluye los de sus equipos). */
    public function todosLosHosts(): array
    {
        return collect($this->hosts->pluck('host_name'))
            ->merge($this->equipos->pluck('host_name'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
