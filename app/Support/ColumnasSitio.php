<?php

namespace App\Support;

use App\Models\Sitio;

/**
 * Definición de las columnas de un sitio para los informes.
 *
 * Fuente única: la tabla en pantalla y el Excel salen de acá, así no se
 * desincronizan cuando se agrega un campo a la ficha.
 *
 * Cada columna sabe cómo sacar su valor legible. Lo que se guarda como clave
 * —`starlink`, `poste`, un id de compañía— no le sirve a nadie en un informe.
 */
class ColumnasSitio
{
    /**
     * Columnas en el orden en que se muestran. Nombre y zona primero, el resto
     * agrupado como en la ficha.
     *
     * @return array<string,array{label:string,grupo:string,valor:callable}>
     */
    public static function todas(): array
    {
        $si = fn($v) => is_null($v) ? null : ($v ? 'Sí' : 'No');

        return [
            /* ── Identificación ───────────────────────────────────────────── */
            'nombre'        => ['Nombre',   'Identificación', fn(Sitio $s) => $s->nombre],
            'zona'          => ['Zona',     'Identificación', fn(Sitio $s) => $s->zona?->nombre],
            'codigo'        => ['Código',   'Identificación', fn(Sitio $s) => $s->codigo],
            'tipo'          => ['Tipo',     'Identificación', fn(Sitio $s) => $s->tipo_label],
            'estado_enlace' => ['Estado del enlace', 'Identificación', fn(Sitio $s) => $s->estado_enlace_label],
            'empresa'       => ['Empresa',  'Identificación', fn(Sitio $s) => $s->empresa?->nombre],

            /* ── Ubicación ────────────────────────────────────────────────── */
            'region'    => ['Región',    'Ubicación', fn(Sitio $s) => $s->region],
            'comuna'    => ['Comuna',    'Ubicación', fn(Sitio $s) => $s->comuna],
            'latitud'   => ['Latitud',   'Ubicación', fn(Sitio $s) => $s->latitud],
            'longitud'  => ['Longitud',  'Ubicación', fn(Sitio $s) => $s->longitud],
            'maps_url'  => ['Link de Maps', 'Ubicación', fn(Sitio $s) => $s->maps_url],
            'acceso'    => ['Cómo llegar',  'Ubicación', fn(Sitio $s) => $s->acceso],

            /* ── Enlace y red ─────────────────────────────────────────────── */
            'enlace_tipo'      => ['Tipo de enlace', 'Enlace y red', fn(Sitio $s) => Sitio::ENLACE_TIPOS[$s->enlace_tipo] ?? $s->enlace_tipo],
            'isp'              => ['ISP',            'Enlace y red', fn(Sitio $s) => $s->isp?->nombre],
            'ancho_banda'      => ['Ancho de banda', 'Enlace y red', fn(Sitio $s) => $s->ancho_banda],
            'ancho_banda_mpls' => ['Ancho de banda MPLS', 'Enlace y red', fn(Sitio $s) => $s->ancho_banda_mpls],
            'ip_publica'       => ['IP pública',     'Enlace y red', fn(Sitio $s) => $s->ip_publica],
            'num_servicio'     => ['N° de servicio', 'Enlace y red', fn(Sitio $s) => $s->num_servicio],
            'fecha_instalacion'=> ['Fecha instalación', 'Enlace y red', fn(Sitio $s) => $s->fecha_instalacion?->format('d-m-Y')],
            'subred'           => ['Subred',  'Enlace y red', fn(Sitio $s) => $s->subred],
            'vlan'             => ['VLAN',    'Enlace y red', fn(Sitio $s) => $s->vlan],
            'gateway'          => ['Gateway', 'Enlace y red', fn(Sitio $s) => $s->gateway],
            'vpn'              => ['VPN al datacenter', 'Enlace y red', fn(Sitio $s) => $si($s->vpn)],
            'vpn_detalle'      => ['Detalle VPN', 'Enlace y red', fn(Sitio $s) => $s->vpn_detalle],

            /* ── Cobertura móvil ──────────────────────────────────────────── */
            'cob_entel'    => ['Señal Entel',    'Cobertura móvil', fn(Sitio $s) => Sitio::COBERTURA[$s->cob_entel] ?? $s->cob_entel],
            'cob_movistar' => ['Señal Movistar', 'Cobertura móvil', fn(Sitio $s) => Sitio::COBERTURA[$s->cob_movistar] ?? $s->cob_movistar],
            'cob_wom'      => ['Señal WOM',      'Cobertura móvil', fn(Sitio $s) => Sitio::COBERTURA[$s->cob_wom] ?? $s->cob_wom],
            'cob_claro'    => ['Señal Claro',    'Cobertura móvil', fn(Sitio $s) => Sitio::COBERTURA[$s->cob_claro] ?? $s->cob_claro],
            'cob_notas'    => ['Notas de señal', 'Cobertura móvil', fn(Sitio $s) => $s->cob_notas],

            /* ── Energía e infraestructura ────────────────────────────────── */
            'eval_energia'         => ['Energía',            'Energía e infraestructura', fn(Sitio $s) => Sitio::EVAL_ENERGIA[$s->eval_energia] ?? $s->eval_energia],
            'eval_energia_estable' => ['Energía estable',    'Energía e infraestructura', fn(Sitio $s) => $si($s->eval_energia_estable)],
            'generador'            => ['Generador',          'Energía e infraestructura', fn(Sitio $s) => $si($s->generador)],
            'puesta_tierra'        => ['Puesta a tierra',    'Energía e infraestructura', fn(Sitio $s) => $si($s->puesta_tierra)],
            'climatizacion'        => ['Climatización',      'Energía e infraestructura', fn(Sitio $s) => $si($s->climatizacion)],
            'gabinete'             => ['Gabinete / rack',    'Energía e infraestructura', fn(Sitio $s) => $s->gabinete],
            'ups_modelo'           => ['UPS',                'Energía e infraestructura', fn(Sitio $s) => $s->ups_modelo],
            'ups_kva'              => ['UPS kVA',            'Energía e infraestructura', fn(Sitio $s) => $s->ups_kva],
            'racks_cant'           => ['Racks',              'Energía e infraestructura', fn(Sitio $s) => $s->racks_cant],
            'racks_u_usados'       => ['U usadas',           'Energía e infraestructura', fn(Sitio $s) => $s->racks_u_usados],
            'racks_u_totales'      => ['U totales',          'Energía e infraestructura', fn(Sitio $s) => $s->racks_u_totales],
            'eval_sala_equipos'    => ['Sala de equipos',    'Energía e infraestructura', fn(Sitio $s) => Sitio::EVAL_SALA_EQUIPOS[$s->eval_sala_equipos] ?? $s->eval_sala_equipos],
            'eval_infra_existente' => ['Infraestructura aprovechable', 'Energía e infraestructura', fn(Sitio $s) => $s->eval_infra_existente],

            /* ── Viabilidad del enlace ────────────────────────────────────── */
            'eval_internet_particular' => ['Tiene internet hoy', 'Viabilidad', fn(Sitio $s) => $si($s->eval_internet_particular)],
            'eval_internet_detalle'    => ['Internet de quién',  'Viabilidad', fn(Sitio $s) => $s->eval_internet_detalle],
            'eval_linea_vista'         => ['Línea de vista',     'Viabilidad', fn(Sitio $s) => Sitio::EVAL_LINEA_VISTA[$s->eval_linea_vista] ?? $s->eval_linea_vista],
            'eval_linea_vista_hacia'   => ['Línea de vista hacia', 'Viabilidad', fn(Sitio $s) => $s->eval_linea_vista_hacia],
            'eval_distancia_km'        => ['Distancia (km)',     'Viabilidad', fn(Sitio $s) => $s->eval_distancia_km],
            'eval_cielo_despejado'     => ['Cielo despejado',    'Viabilidad', fn(Sitio $s) => $si($s->eval_cielo_despejado)],
            'eval_fibra_zona'          => ['Fibra en la zona',   'Viabilidad', fn(Sitio $s) => Sitio::EVAL_FIBRA_ZONA[$s->eval_fibra_zona] ?? $s->eval_fibra_zona],
            'eval_punto_montaje'       => ['Punto de montaje',   'Viabilidad', fn(Sitio $s) => Sitio::EVAL_PUNTO_MONTAJE[$s->eval_punto_montaje] ?? $s->eval_punto_montaje],
            'eval_altura_m'            => ['Altura (m)',         'Viabilidad', fn(Sitio $s) => $s->eval_altura_m],

            /* ── Dimensión ────────────────────────────────────────────────── */
            'superficie_ha' => ['Superficie (ha)', 'Dimensión', fn(Sitio $s) => $s->superficie_ha],
            'especies'      => ['Especies',        'Dimensión', fn(Sitio $s) => $s->especies],
            'usuarios_cant' => ['Usuarios',        'Dimensión', fn(Sitio $s) => $s->usuarios_cant],
            'pcs_cant'      => ['PCs / equipos',   'Dimensión', fn(Sitio $s) => $s->pcs_cant],
            'estacional'    => ['Solo temporada',  'Dimensión', fn(Sitio $s) => $si($s->estacional)],
            'temporada'     => ['Temporada',       'Dimensión', fn(Sitio $s) => $s->temporada],

            /* ── Uso previsto y conclusión ────────────────────────────────── */
            'eval_necesita_camaras' => ['Necesita cámaras', 'Conclusión', fn(Sitio $s) => $si($s->eval_necesita_camaras)],
            'eval_necesita_wifi'    => ['Necesita WiFi',    'Conclusión', fn(Sitio $s) => $si($s->eval_necesita_wifi)],
            'eval_uso_previsto'     => ['Uso previsto',     'Conclusión', fn(Sitio $s) => $s->eval_uso_previsto],
            'solucion_propuesta'    => ['Solución propuesta', 'Conclusión', fn(Sitio $s) => Sitio::SOLUCIONES[$s->solucion_propuesta] ?? $s->solucion_propuesta],
            'orden_ejecucion'       => ['Orden de ejecución', 'Conclusión', fn(Sitio $s) => $s->orden_ejecucion],
            'costo_estimado'        => ['Costo estimado',     'Conclusión', fn(Sitio $s) => $s->costo_estimado],
            'acciones'              => ['Qué hay que hacer',  'Conclusión', fn(Sitio $s) => $s->acciones],

            /* ── Contacto y gestión ───────────────────────────────────────── */
            'encargado_nombre'   => ['Encargado',      'Contacto y gestión', fn(Sitio $s) => $s->encargado_nombre],
            'encargado_telefono' => ['Teléfono',       'Contacto y gestión', fn(Sitio $s) => $s->encargado_telefono],
            'encargado_email'    => ['Email',          'Contacto y gestión', fn(Sitio $s) => $s->encargado_email],
            'tecnico'            => ['Técnico TI',     'Contacto y gestión', fn(Sitio $s) => $s->tecnico?->name],
            'mapa'               => ['Mapa de red',    'Contacto y gestión', fn(Sitio $s) => $s->mapa?->nombre],
            'completitud'        => ['Completitud',    'Contacto y gestión', fn(Sitio $s) => $s->completitud . '%'],
            'levantado_at'       => ['Levantado el',   'Contacto y gestión', fn(Sitio $s) => $s->levantado_at?->format('d-m-Y H:i')],
            'levantado_por'      => ['Levantado por',  'Contacto y gestión', fn(Sitio $s) => $s->levantadoPor?->name],
            'notas'              => ['Notas',          'Contacto y gestión', fn(Sitio $s) => $s->notas],
        ];
    }

    /**
     * Las columnas que tienen al menos un dato en esta lista de sitios.
     *
     * Ojo con qué cuenta como «vacío»: solo `null` y la cadena vacía. Un `false`
     * o un `0` son respuestas —«no tiene VPN», «cero usuarios»— y su columna
     * tiene que salir. Por eso el filtro se hace acá en PHP y no con un
     * `WHERE columna != ''` en SQL, donde MySQL compara 0 con '' y los descarta.
     *
     * @return array<string,array{label:string,grupo:string,valor:callable}>
     */
    public static function conDatos($sitios): array
    {
        $vacio = fn($v) => $v === null || $v === '';

        return array_filter(
            self::todas(),
            fn(array $col) => $sitios->contains(fn(Sitio $s) => !$vacio($col[2]($s)))
        );
    }

    /** Los sitios con todas sus relaciones, para no consultar de a una por fila. */
    public static function consulta()
    {
        return Sitio::activos()->with(['zona', 'empresa', 'isp', 'tecnico', 'mapa', 'levantadoPor', 'fotos']);
    }
}
