@extends('layouts.app')

@php
    use App\Models\Sitio;
    use App\Models\SitioEquipo;
    use App\Models\SitioFoto;
    use App\Models\SitioHost;
    $esCampo = $sitio->tipo === 'campo';
    $esPlanta = $sitio->tipo === 'planta';
    $esDc = $sitio->tipo === 'datacenter';
    $ta = fn(string $r) => auth()->user()->tieneAcceso($r);

    // Qué campos mueven el porcentaje de ESTA ficha. Depende del tipo, del estado
    // del enlace y de si declaró enlace, así que se calcula una vez acá y lo lee
    // el parcial `_req` heredando el scope de la vista.
    $req    = $sitio->requisitos();
    $faltan = $sitio->faltantes();
@endphp

@section('content')
<style>
    .sf-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; margin-bottom:1.1rem; }
    .sf-card > h6 { font-size:.78rem; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:.05em;
                    margin:0; padding:.75rem 1.1rem; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; gap:.4rem; }
    .sf-card > h6 .rt { margin-left:auto; font-size:.7rem; font-weight:400; text-transform:none; letter-spacing:0; color:#94a3b8; }
    .sf-body { padding:1rem 1.1rem; }
    .sf-hero { display:flex; gap:1.1rem; align-items:flex-start; flex-wrap:wrap; }
    .sf-hero .portada { width:190px; height:130px; border-radius:9px; overflow:hidden; background:#f1f5f9; flex:0 0 auto;
                        display:flex; align-items:center; justify-content:center; }
    .sf-hero .portada img { width:100%; height:100%; object-fit:cover; }
    .sf-hero .portada i { font-size:2.6rem; color:#cbd5e1; }
    .sf-badge { display:inline-block; font-size:.7rem; font-weight:700; padding:2px 10px; border-radius:20px; color:#fff; }
    .sf-tipo { display:inline-block; font-size:.7rem; font-weight:600; padding:2px 10px; border-radius:6px; background:#eef2ff; color:#4338ca; }
    .sf-comp { height:7px; background:#f1f5f9; border-radius:4px; overflow:hidden; max-width:280px; }
    .sf-comp span { display:block; height:100%; }
    .sf-falta { font-size:.7rem; color:#b45309; background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:.4rem .6rem; margin-top:.5rem; }
    /* Marca de "esto mueve el porcentaje". Discreta a propósito: acompaña a la
       etiqueta sin competir con el asterisco de los campos obligatorios. */
    .sf-req { display:inline-block; width:6px; height:6px; border-radius:50%; margin-left:5px;
              vertical-align:middle; }
    .sf-req.ok    { background:#86efac; }
    .sf-req.falta { background:#fff; border:1.5px solid #fbbf24; }
    .sf-leyenda { font-size:.68rem; color:#94a3b8; margin-top:.45rem; display:flex; align-items:center; gap:.35rem; flex-wrap:wrap; }
    .sf-leyenda .sf-req { margin-left:0; }
    .sf-terreno { border:1px dashed #e2e8f0; border-radius:8px; padding:.6rem .7rem; margin-bottom:1rem; background:#fcfcfd; }
    .sf-terreno .tit { font-size:.66rem; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.03em; margin-bottom:.45rem; }
    .sf-terreno .fila { display:flex; align-items:center; gap:.4rem; font-size:.76rem; padding:.15rem 0; }
    .sf-terreno .fila .sf-req { margin-left:0; flex:0 0 auto; }
    .sf-terreno .rot { color:#64748b; }
    .sf-terreno .val { margin-left:auto; color:#1e293b; font-weight:600; }
    .sf-terreno .val.vacio { color:#cbd5e1; font-weight:400; font-style:italic; }
    .sf-terreno .ir { display:inline-block; margin-top:.5rem; font-size:.72rem; text-decoration:none; color:#7c3aed; }
    .sf-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:.75rem 1rem; }
    .sf-f label { font-size:.7rem; color:#94a3b8; display:block; margin-bottom:2px; font-weight:600; text-transform:uppercase; letter-spacing:.03em; }
    .sf-chk { display:flex; align-items:center; gap:1.2rem; flex-wrap:wrap; }
    .sf-host { display:flex; align-items:center; gap:.6rem; padding:.5rem .7rem; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:.45rem; }
    .sf-host.roto { border-color:#d8b4fe; background:#faf5ff; }
    .sf-dot { width:10px; height:10px; border-radius:50%; flex:0 0 auto; }
    .sf-eq { border:1px solid #e2e8f0; border-radius:9px; padding:.7rem .85rem; margin-bottom:.6rem; }
    .sf-eq .cab { display:flex; align-items:center; gap:.5rem; }
    .sf-eq .cab .ic { width:32px; height:32px; border-radius:8px; background:#f8fafc; display:flex; align-items:center; justify-content:center; color:#64748b; }
    .sf-eq .datos { font-size:.74rem; color:#64748b; display:flex; gap:.9rem; flex-wrap:wrap; margin-top:.35rem; }
    .sf-fotos { display:grid; grid-template-columns:repeat(auto-fill,minmax(124px,1fr)); gap:.6rem; }
    .sf-foto { position:relative; border-radius:8px; overflow:hidden; background:#f1f5f9; aspect-ratio:4/3; }
    .sf-foto img { width:100%; height:100%; object-fit:cover; cursor:zoom-in; }
    .sf-foto .cap { position:absolute; left:0; right:0; bottom:0; background:rgba(15,23,42,.72); color:#fff; font-size:.62rem; padding:2px 5px; }
    .sf-foto .acc { position:absolute; top:3px; right:3px; display:flex; gap:2px; }
    .sf-foto .acc button { border:none; background:rgba(15,23,42,.72); color:#fff; border-radius:5px; font-size:.62rem; padding:1px 5px; }
    .sf-foto.es-portada { outline:2px solid #7c3aed; outline-offset:-2px; }
    .sf-vis { position:fixed; inset:0; background:rgba(15,23,42,.88); z-index:2000; display:none; align-items:center; justify-content:center; padding:2rem; }
    .sf-vis img { max-width:100%; max-height:100%; border-radius:8px; }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi {{ $sitio->icono }} me-2" style="color:#7c3aed"></i>{{ $sitio->titulo }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.sitios.terreno.ficha', $sitio) }}" class="btn btn-outline-secondary btn-sm" title="Vista para celular">
                <i class="bi bi-phone"></i>
            </a>
            @if($sitio->mapa)
            <a href="{{ route('admin.monitoreo.mapas.show', $sitio->mapa) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-diagram-3 me-1"></i>Ver mapa
            </a>
            @endif
            <a href="{{ route('admin.sitios.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Sitios</a>
        </div>
    </div>

    {{-- ── Resumen ────────────────────────────────────────────────────────── --}}
    <div class="sf-card">
        <div class="sf-body sf-hero">
            <div class="portada">
                @if($sitio->portada && $sitio->portada->url)
                    <img src="{{ $sitio->portada->url }}" alt="" class="ver-foto" style="cursor:zoom-in">
                @else
                    <i class="bi {{ $sitio->icono }}"></i>
                @endif
            </div>
            <div style="flex:1 1 260px">
                <div class="mb-2">
                    <span class="sf-tipo"><i class="bi {{ $sitio->icono }} me-1"></i>{{ $sitio->tipo_label }}</span>
                    <span class="sf-badge" style="background:{{ $sitio->estado_enlace_color }}">{{ $sitio->estado_enlace_label }}</span>
                    @if($sitio->estacional)<span class="sf-tipo" style="background:#fef3c7;color:#92400e"><i class="bi bi-calendar-range me-1"></i>Estacional</span>@endif
                </div>
                <div style="font-size:.8rem;color:#475569;line-height:1.7">
                    @if($sitio->empresa)<div><i class="bi bi-building me-1 text-muted"></i>{{ $sitio->empresa->nombre }}</div>@endif
                    @if($sitio->comuna || $sitio->region)
                        <div><i class="bi bi-geo-alt me-1 text-muted"></i>{{ collect([$sitio->comuna, $sitio->region])->filter()->implode(', ') }}</div>
                    @endif
                    @if($sitio->encargado_nombre)
                        <div><i class="bi bi-person me-1 text-muted"></i>{{ $sitio->encargado_nombre }}
                        @if($sitio->encargado_telefono)<span class="text-muted">· {{ $sitio->encargado_telefono }}</span>@endif</div>
                    @endif
                    @if($sitio->maps_link)
                        <div>
                            <i class="bi bi-pin-map me-1 text-muted"></i>
                            <a href="{{ $sitio->maps_link }}" target="_blank" rel="noopener">
                                @if($sitio->latitud && $sitio->longitud)
                                    {{ number_format($sitio->latitud, 5) }}, {{ number_format($sitio->longitud, 5) }}
                                @else
                                    Abrir ubicación en Maps
                                @endif
                            </a>
                        </div>
                    @endif
                    @if($sitio->levantado_at)
                        <div class="text-muted" style="font-size:.74rem">
                            <i class="bi bi-clock-history me-1"></i>Levantado {{ $sitio->levantado_at->format('d/m/Y H:i') }}
                            @if($sitio->levantadoPor) por {{ $sitio->levantadoPor->name }}@endif
                        </div>
                    @endif
                </div>
            </div>
            <div style="flex:0 0 220px">
                <label style="font-size:.7rem;color:#94a3b8;font-weight:600;text-transform:uppercase">Completitud de la ficha</label>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <div class="sf-comp flex-grow-1">
                        <span style="width:{{ $sitio->completitud }}%;background:{{ $sitio->completitud >= 80 ? '#16a34a' : ($sitio->completitud >= 40 ? '#d97706' : '#dc2626') }}"></span>
                    </div>
                    <b style="font-size:.85rem">{{ $sitio->completitud }}%</b>
                </div>
                @if(count($sitio->faltantes()))
                <div class="sf-falta">
                    <b>Falta:</b> {{ $sitio->faltantesEnPalabras() }}
                </div>
                @endif
                <div class="sf-leyenda">
                    <span class="sf-req ok"></span> respondido
                    <span class="sf-req falta ms-2"></span> falta
                    <span class="ms-1">— {{ count($req) }} campos cuentan para este {{ $sitio->tipo_label }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-0" style="gap:0">
        <div class="col-lg-7 pe-lg-3">

            {{-- ── Datos de la ficha ──────────────────────────────────────── --}}
            <form method="POST" action="{{ route('admin.sitios.update', $sitio) }}">
                @csrf @method('PUT')

                <div class="sf-card">
                    <h6><i class="bi bi-card-text"></i>Identificación y ubicación</h6>
                    <div class="sf-body">
                        <div class="sf-grid">
                            <div class="sf-f"><label>Código @include('admin.sitios._req', ['campo' => 'codigo'])</label><input type="text" name="codigo" class="form-control form-control-sm" value="{{ $sitio->codigo }}"></div>
                            <div class="sf-f" style="grid-column:span 2"><label>Nombre * @include('admin.sitios._req', ['campo' => 'nombre'])</label><input type="text" name="nombre" class="form-control form-control-sm" value="{{ $sitio->nombre }}" required></div>
                            <div class="sf-f"><label>Tipo *</label>
                                <select name="tipo" class="form-select form-select-sm">
                                    @foreach(Sitio::TIPOS as $k => $l)<option value="{{ $k }}" @selected($sitio->tipo === $k)>{{ $l }}</option>@endforeach
                                </select>
                            </div>
                            <div class="sf-f"><label>Estado de enlace *</label>
                                <select name="estado_enlace" class="form-select form-select-sm">
                                    @foreach(Sitio::ESTADOS_ENLACE as $k => $l)<option value="{{ $k }}" @selected($sitio->estado_enlace === $k)>{{ $l }}</option>@endforeach
                                </select>
                            </div>
                            <div class="sf-f"><label>Empresa</label>
                                <div class="input-group input-group-sm">
                                    <select name="empresa_id" class="form-select form-select-sm">
                                        <option value="">—</option>
                                        @foreach($empresas as $e)<option value="{{ $e->id }}" @selected($sitio->empresa_id === $e->id)>{{ $e->nombre }}</option>@endforeach
                                    </select>
                                    @if($ta('empresas.index'))
                                    <a href="{{ route('empresas.index') }}" target="_blank" class="btn btn-outline-secondary" title="Ver o agregar empresas"><i class="bi bi-box-arrow-up-right"></i></a>
                                    @endif
                                </div>
                            </div>
                            {{-- El botón abre el mantenedor sin salir de la ficha: la zona
                                 que falta se crea y queda elegida acá mismo, sin perder lo
                                 que ya se lleve escrito en el formulario. --}}
                            <div class="sf-f"><label>Zona</label>
                                <div class="input-group input-group-sm">
                                    <select name="zona_id" class="form-select form-select-sm">
                                        <option value="">— Sin zona —</option>
                                        @foreach($zonas as $z)<option value="{{ $z->id }}" @selected($sitio->zona_id === $z->id)>{{ $z->nombre }}</option>@endforeach
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#modalZonas"
                                            title="Crear o editar zonas"><i class="bi bi-plus-lg"></i></button>
                                </div>
                            </div>
                            <div class="sf-f"><label>Región @include('admin.sitios._req', ['campo' => 'region'])</label>
                                <select name="region" id="selRegion" class="form-select form-select-sm" data-actual="{{ $sitio->region }}">
                                    <option value="">—</option>
                                </select>
                            </div>
                            <div class="sf-f"><label>Comuna @include('admin.sitios._req', ['campo' => 'comuna'])</label>
                                <select name="comuna" id="selComuna" class="form-select form-select-sm" data-actual="{{ $sitio->comuna }}">
                                    <option value="">—</option>
                                </select>
                            </div>
                            <div class="sf-f" style="grid-column:span 2">
                                <label>Ubicación en Maps @include('admin.sitios._req', ['campo' => 'maps_url'])
                                    <span class="text-muted" style="text-transform:none;font-weight:400">— pega el link y las coordenadas se completan solas</span></label>
                                <div class="input-group input-group-sm">
                                    <input type="text" name="maps_url" class="form-control form-control-sm" value="{{ $sitio->maps_url }}" placeholder="https://maps.app.goo.gl/… o -34.39751,-71.17403">
                                    @if($sitio->maps_link)
                                    <a href="{{ $sitio->maps_link }}" target="_blank" rel="noopener" class="btn btn-outline-secondary" title="Abrir en Maps"><i class="bi bi-geo-alt"></i></a>
                                    @endif
                                </div>
                            </div>
                            <div class="sf-f"><label>Latitud</label><input type="text" name="latitud" class="form-control form-control-sm" value="{{ $sitio->latitud }}" placeholder="-34.12345"></div>
                            <div class="sf-f"><label>Longitud</label><input type="text" name="longitud" class="form-control form-control-sm" value="{{ $sitio->longitud }}" placeholder="-71.12345"></div>
                        </div>
                        <div class="sf-f mt-3">
                            <label>Cómo llegar / acceso @include('admin.sitios._req', ['campo' => 'acceso'])
                                <span class="text-muted" style="text-transform:none;font-weight:400">— portón, referencias, ruta</span></label>
                            <textarea name="acceso" class="form-control form-control-sm" rows="2">{{ $sitio->acceso }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="sf-card">
                    <h6><i class="bi bi-ethernet"></i>Enlace y red</h6>
                    <div class="sf-body">
                        <div class="sf-grid">
                            <div class="sf-f"><label>Tipo de enlace @include('admin.sitios._req', ['campo' => 'enlace_tipo'])</label>
                                <select name="enlace_tipo" class="form-select form-select-sm">
                                    <option value="">—</option>
                                    @foreach(Sitio::ENLACE_TIPOS as $k => $l)<option value="{{ $k }}" @selected($sitio->enlace_tipo === $k)>{{ $l }}</option>@endforeach
                                </select>
                            </div>
                            <div class="sf-f"><label>ISP / proveedor @include('admin.sitios._req', ['campo' => 'isp_id'])</label>
                                <div class="input-group input-group-sm">
                                    <select name="isp_id" class="form-select form-select-sm">
                                        <option value="">—</option>
                                        @foreach($companias as $c)<option value="{{ $c->id }}" @selected($sitio->isp_id === $c->id)>{{ $c->nombre }}</option>@endforeach
                                    </select>
                                    @if($ta('companias.index'))
                                    <a href="{{ route('companias.index') }}" target="_blank" class="btn btn-outline-secondary" title="Ver o agregar compañías"><i class="bi bi-box-arrow-up-right"></i></a>
                                    @endif
                                </div>
                            </div>
                            <div class="sf-f"><label>Ancho de banda (Internet) @include('admin.sitios._req', ['campo' => 'ancho_banda'])</label><input type="text" name="ancho_banda" class="form-control form-control-sm" value="{{ $sitio->ancho_banda }}" placeholder="100/100 Mbps"></div>
                            <div class="sf-f"><label>Ancho de banda (MPLS)</label><input type="text" name="ancho_banda_mpls" class="form-control form-control-sm" value="{{ $sitio->ancho_banda_mpls }}" placeholder="20/20 Mbps"></div>
                            <div class="sf-f"><label>IP pública</label><input type="text" name="ip_publica" class="form-control form-control-sm" value="{{ $sitio->ip_publica }}"></div>
                            <div class="sf-f"><label>N° de servicio</label><input type="text" name="num_servicio" class="form-control form-control-sm" value="{{ $sitio->num_servicio }}"></div>
                            <div class="sf-f"><label>Fecha instalación</label><input type="date" name="fecha_instalacion" class="form-control form-control-sm" value="{{ $sitio->fecha_instalacion?->format('Y-m-d') }}"></div>
                            <div class="sf-f"><label>Subred @include('admin.sitios._req', ['campo' => 'subred'])</label><input type="text" name="subred" class="form-control form-control-sm" value="{{ $sitio->subred }}" placeholder="192.168.34.0/24"></div>
                            <div class="sf-f"><label>VLAN</label><input type="text" name="vlan" class="form-control form-control-sm" value="{{ $sitio->vlan }}"></div>
                            <div class="sf-f"><label>Gateway</label><input type="text" name="gateway" class="form-control form-control-sm" value="{{ $sitio->gateway }}"></div>
                            {{-- Desplegable y no casilla: una casilla sin marcar no
                                 distingue «no tiene VPN» de «nadie lo ha revisado», y
                                 esa diferencia es la que se necesita en el informe. --}}
                            <div class="sf-f"><label>VPN al datacenter @include('admin.sitios._req', ['campo' => 'vpn'])</label>
                                <select name="vpn" class="form-select form-select-sm">
                                    <option value="" @selected($sitio->vpn === null)>— Sin revisar —</option>
                                    <option value="1" @selected($sitio->vpn === true)>Sí tiene</option>
                                    <option value="0" @selected($sitio->vpn === false)>No tiene</option>
                                </select>
                            </div>
                            <div class="sf-f" style="grid-column:span 2"><label>Detalle VPN</label><input type="text" name="vpn_detalle" class="form-control form-control-sm" value="{{ $sitio->vpn_detalle }}" placeholder="Túnel IPSec al datacenter"></div>
                        </div>
                    </div>
                </div>

                <div class="sf-card">
                    <h6><i class="bi bi-lightning-charge"></i>Infraestructura física</h6>
                    <div class="sf-body">
                        <div class="sf-grid">
                            <div class="sf-f" style="grid-column:span 2"><label>Gabinete / rack</label><input type="text" name="gabinete" class="form-control form-control-sm" value="{{ $sitio->gabinete }}"></div>
                            <div class="sf-f"><label>UPS modelo @include('admin.sitios._req', ['campo' => 'ups_modelo'])</label><input type="text" name="ups_modelo" class="form-control form-control-sm" value="{{ $sitio->ups_modelo }}"></div>
                            <div class="sf-f"><label>Capacidad UPS (kVA)</label><input type="text" name="ups_kva" class="form-control form-control-sm" value="{{ $sitio->ups_kva }}" placeholder="3.0"></div>
                            @if($esDc)
                            <div class="sf-f"><label>Racks @include('admin.sitios._req', ['campo' => 'racks_cant'])</label><input type="number" name="racks_cant" class="form-control form-control-sm" value="{{ $sitio->racks_cant }}"></div>
                            <div class="sf-f"><label>U usados</label><input type="number" name="racks_u_usados" class="form-control form-control-sm" value="{{ $sitio->racks_u_usados }}"></div>
                            <div class="sf-f"><label>U totales</label><input type="number" name="racks_u_totales" class="form-control form-control-sm" value="{{ $sitio->racks_u_totales }}"></div>
                            @endif
                        </div>
                        <div class="sf-chk mt-3">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="generador" value="1" id="cGen" @checked($sitio->generador)>
                                <label class="form-check-label" for="cGen" style="font-size:.78rem">Generador</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="puesta_tierra" value="1" id="cPt" @checked($sitio->puesta_tierra)>
                                <label class="form-check-label" for="cPt" style="font-size:.78rem">Puesta a tierra</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="climatizacion" value="1" id="cCli" @checked($sitio->climatizacion)>
                                <label class="form-check-label" for="cCli" style="font-size:.78rem">Climatización @include('admin.sitios._req', ['campo' => 'climatizacion'])</label></div>
                        </div>
                    </div>
                </div>

                <div class="sf-card">
                    <h6><i class="bi bi-people"></i>Operación y contactos</h6>
                    <div class="sf-body">
                        <div class="sf-grid">
                            @if($esCampo || $esPlanta)
                            <div class="sf-f"><label>Superficie (ha) @include('admin.sitios._req', ['campo' => 'superficie_ha'])</label><input type="text" name="superficie_ha" class="form-control form-control-sm" value="{{ $sitio->superficie_ha }}"></div>
                            <div class="sf-f"><label>Especies / cultivos</label><input type="text" name="especies" class="form-control form-control-sm" value="{{ $sitio->especies }}" placeholder="Cereza, uva"></div>
                            @endif
                            <div class="sf-f"><label>Usuarios @include('admin.sitios._req', ['campo' => 'usuarios_cant'])</label><input type="number" name="usuarios_cant" class="form-control form-control-sm" value="{{ $sitio->usuarios_cant }}"></div>
                            <div class="sf-f"><label>PCs / equipos @include('admin.sitios._req', ['campo' => 'pcs_cant'])</label><input type="number" name="pcs_cant" class="form-control form-control-sm" value="{{ $sitio->pcs_cant }}"></div>
                            <div class="sf-f"><label>Temporada</label><input type="text" name="temporada" class="form-control form-control-sm" value="{{ $sitio->temporada }}" placeholder="octubre a marzo"></div>
                            <div class="sf-f"><label>Encargado @include('admin.sitios._req', ['campo' => 'encargado_nombre'])</label><input type="text" name="encargado_nombre" class="form-control form-control-sm" value="{{ $sitio->encargado_nombre }}"></div>
                            <div class="sf-f"><label>Teléfono @include('admin.sitios._req', ['campo' => 'encargado_telefono'])</label><input type="text" name="encargado_telefono" class="form-control form-control-sm" value="{{ $sitio->encargado_telefono }}"></div>
                            <div class="sf-f"><label>Email</label><input type="email" name="encargado_email" class="form-control form-control-sm" value="{{ $sitio->encargado_email }}"></div>
                            <div class="sf-f"><label>Técnico TI asignado</label>
                                <select name="tecnico_id" class="form-select form-select-sm">
                                    <option value="">—</option>
                                    @foreach($usuarios as $u)<option value="{{ $u->id }}" @selected($sitio->tecnico_id === $u->id)>{{ $u->name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="sf-f"><label>Mapa de red</label>
                                <select name="mapa_id" class="form-select form-select-sm">
                                    <option value="">—</option>
                                    @foreach($mapas as $m)<option value="{{ $m->id }}" @selected($sitio->mapa_id === $m->id)>{{ $m->nombre }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        <div class="sf-chk mt-3">
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="estacional" value="1" id="cEst" @checked($sitio->estacional)>
                                <label class="form-check-label" for="cEst" style="font-size:.78rem">Opera solo en temporada</label></div>
                        </div>
                        <div class="sf-f mt-3"><label>Notas</label>
                            <textarea name="notas" class="form-control form-control-sm" rows="3">{{ $sitio->notas }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- ── Evaluación de factibilidad ─────────────────────────────
                     Todo lo que el levantamiento en terreno NO pregunta, más lo
                     que sí pregunta, para poder corregirlo desde el escritorio.
                     Antes estos campos solo existían en el formulario móvil: si
                     algo quedaba mal escrito, no había dónde arreglarlo.

                     Se muestra abierta mientras el sitio esté en evaluación; en
                     un sitio operativo queda plegada para no estorbar. --}}
                @php
                    $tieneEval = $sitio->solucion_propuesta || $sitio->eval_linea_vista
                        || $sitio->acciones || $sitio->eval_uso_previsto || $sitio->eval_infra_existente;
                @endphp
                <details class="sf-card" @if($sitio->enEvaluacion() || $tieneEval) open @endif>
                    <summary style="cursor:pointer;list-style:none">
                        <h6 class="mb-0"><i class="bi bi-clipboard-check"></i>Evaluación de factibilidad
                            <span class="rt">lo que no se pregunta en terreno</span>
                        </h6>
                    </summary>
                    <div class="sf-body">

                        {{-- Estos requisitos se contestan en el celular, parado en el
                             campo, y no tienen control acá: se muestran igual porque si
                             no, el porcentaje diría "12 campos" y en pantalla solo se
                             verían 5. Van de solo lectura para no invitar a llenarlos
                             desde el escritorio. --}}
                        @php
                            $enTerreno = [
                                'cobertura_medida'     => ['Cobertura móvil', $sitio->cobertura_medida ? 'medida' : null],
                                'eval_energia'         => ['Energía', Sitio::EVAL_ENERGIA[$sitio->eval_energia] ?? null],
                                'eval_energia_estable' => ['Energía estable', is_null($sitio->eval_energia_estable) ? null : ($sitio->eval_energia_estable ? 'Sí' : 'No')],
                                'eval_cielo_despejado' => ['Cielo despejado', is_null($sitio->eval_cielo_despejado) ? null : ($sitio->eval_cielo_despejado ? 'Sí' : 'No')],
                                'eval_fibra_zona'      => ['Fibra en la zona', Sitio::EVAL_FIBRA_ZONA[$sitio->eval_fibra_zona] ?? null],
                                'eval_punto_montaje'   => ['Punto de montaje', Sitio::EVAL_PUNTO_MONTAJE[$sitio->eval_punto_montaje] ?? null],
                                'tiene_fotos'          => ['Fotos del sitio', $sitio->fotos->count() ? $sitio->fotos->count() . ' foto(s)' : null],
                            ];
                            $enTerreno = array_filter($enTerreno, fn($_, $c) => in_array($c, $req, true), ARRAY_FILTER_USE_BOTH);
                        @endphp
                        @if($enTerreno)
                        <div class="sf-terreno">
                            <div class="tit">Se toman en terreno, desde el celular</div>
                            @foreach($enTerreno as $campo => [$rotulo, $valor])
                                <div class="fila">
                                    @include('admin.sitios._req', ['campo' => $campo])
                                    <span class="rot">{{ $rotulo }}</span>
                                    <span class="val {{ $valor ? '' : 'vacio' }}">{{ $valor ?? 'sin responder' }}</span>
                                </div>
                            @endforeach
                            <a href="{{ route('admin.sitios.terreno.ficha', $sitio) }}" class="ir">
                                <i class="bi bi-phone me-1"></i>Abrir el levantamiento
                            </a>
                        </div>
                        @endif

                        <div class="sf-grid">
                            <div class="sf-f"><label>¿Tienen internet hoy?</label>
                                <select name="eval_internet_particular" class="form-select form-select-sm">
                                    <option value="">—</option>
                                    <option value="1" @selected($sitio->eval_internet_particular === true)>Sí</option>
                                    <option value="0" @selected($sitio->eval_internet_particular === false)>No</option>
                                </select>
                            </div>
                            <div class="sf-f"><label>¿De quién / qué tipo?</label>
                                <input type="text" name="eval_internet_detalle" class="form-control form-control-sm"
                                       value="{{ $sitio->eval_internet_detalle }}" placeholder="Starlink del administrador, 4G…">
                            </div>
                            <div class="sf-f"><label>Línea de vista</label>
                                <select name="eval_linea_vista" class="form-select form-select-sm">
                                    <option value="">—</option>
                                    @foreach(Sitio::EVAL_LINEA_VISTA as $k => $l)<option value="{{ $k }}" @selected($sitio->eval_linea_vista === $k)>{{ $l }}</option>@endforeach
                                </select>
                            </div>
                            <div class="sf-f"><label>¿Hacia dónde?</label>
                                <input type="text" name="eval_linea_vista_hacia" class="form-control form-control-sm"
                                       value="{{ $sitio->eval_linea_vista_hacia }}" placeholder="Planta Rapel, cerro…">
                            </div>
                            <div class="sf-f"><label>Distancia (km)</label>
                                <input type="text" name="eval_distancia_km" class="form-control form-control-sm" value="{{ $sitio->eval_distancia_km }}">
                            </div>
                            <div class="sf-f"><label>¿Necesita cámaras?</label>
                                <select name="eval_necesita_camaras" class="form-select form-select-sm">
                                    <option value="">—</option>
                                    <option value="1" @selected($sitio->eval_necesita_camaras === true)>Sí</option>
                                    <option value="0" @selected($sitio->eval_necesita_camaras === false)>No</option>
                                </select>
                            </div>
                            <div class="sf-f"><label>¿Necesita WiFi?</label>
                                <select name="eval_necesita_wifi" class="form-select form-select-sm">
                                    <option value="">—</option>
                                    <option value="1" @selected($sitio->eval_necesita_wifi === true)>Sí</option>
                                    <option value="0" @selected($sitio->eval_necesita_wifi === false)>No</option>
                                </select>
                            </div>
                            <div class="sf-f"><label>Solución propuesta</label>
                                <select name="solucion_propuesta" class="form-select form-select-sm">
                                    <option value="">— Por definir —</option>
                                    @foreach(Sitio::SOLUCIONES as $k => $l)<option value="{{ $k }}" @selected($sitio->solucion_propuesta === $k)>{{ $l }}</option>@endforeach
                                </select>
                            </div>
                            <div class="sf-f"><label>Orden de ejecución</label>
                                <input type="number" name="orden_ejecucion" class="form-control form-control-sm" value="{{ $sitio->orden_ejecucion }}" placeholder="1, 2, 3…">
                            </div>
                            <div class="sf-f"><label>Costo estimado (CLP)</label>
                                <input type="number" name="costo_estimado" class="form-control form-control-sm" value="{{ $sitio->costo_estimado }}">
                            </div>
                        </div>

                        <div class="sf-f mt-3"><label>Infraestructura aprovechable</label>
                            <textarea name="eval_infra_existente" class="form-control form-control-sm" rows="2"
                                      placeholder="Postes, ductos, torre, cableado, gabinete…">{{ $sitio->eval_infra_existente }}</textarea>
                        </div>
                        <div class="sf-f mt-2"><label>Uso previsto</label>
                            <textarea name="eval_uso_previsto" class="form-control form-control-sm" rows="2"
                                      placeholder="Balanza, oficina, packing, control de riego…">{{ $sitio->eval_uso_previsto }}</textarea>
                        </div>
                        <div class="sf-f mt-2 mb-0"><label>Qué hay que hacer</label>
                            <textarea name="acciones" class="form-control form-control-sm" rows="2"
                                      placeholder="Cotizar PTP a Planta Rapel, pedir factibilidad de fibra…">{{ $sitio->acciones }}</textarea>
                        </div>
                    </div>
                </details>

                <div class="d-flex gap-2 mb-3">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Guardar ficha</button>
                </div>
            </form>
        </div>

        <div class="col-lg-5">

            {{-- ── Hosts de CheckMK ───────────────────────────────────────── --}}
            <div class="sf-card">
                <h6><i class="bi bi-server"></i>Hosts en CheckMK
                    <span class="rt">{{ count($sitio->todosLosHosts()) }} enlazado(s)</span>
                </h6>
                <div class="sf-body">
                    @forelse($sitio->hosts as $h)
                        @php
                            $e = $estadoHosts[$h->host_name] ?? ['estado' => 'na'];
                            $ausente = $e['estado'] === 'ausente';
                        @endphp
                        <div class="sf-host {{ $ausente ? 'roto' : '' }}">
                            <span class="sf-dot" style="background:{{ ['up'=>'#16a34a','down'=>'#dc2626','downtime'=>'#d97706','ausente'=>'#a855f7'][$e['estado']] ?? '#94a3b8' }}"></span>
                            <div style="flex:1 1 auto;min-width:0">
                                <div style="font-family:ui-monospace,monospace;font-size:.76rem">{{ $h->host_name }}</div>
                                <div style="font-size:.68rem;color:{{ $ausente ? '#7e22ce' : '#94a3b8' }}">
                                    {{ $h->rol_label }}
                                    @if($e['estado'] === 'up') · en línea @elseif($e['estado'] === 'down') · caído @elseif($e['estado'] === 'downtime') · mantención @elseif($ausente) · <b>ya no existe en CheckMK</b> @else · sin datos @endif
                                    @if(!empty($e['desde'])) · {{ $e['desde'] }}@endif
                                </div>
                                @if($ausente)
                                <a href="{{ route('admin.sitios.enlaces') }}" style="font-size:.68rem">
                                    <i class="bi bi-arrow-left-right me-1"></i>remapear a otro host
                                </a>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('admin.sitios.hosts.destroy', $h) }}" onsubmit="return confirm('¿Desenlazar este host?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm py-0 px-1" title="Desenlazar"><i class="bi bi-x-lg"></i></button>
                            </form>
                        </div>
                    @empty
                        <div class="text-muted mb-2" style="font-size:.78rem">
                            Sin hosts enlazados. Un sitio sin enlace todavía es válido — así queda registrado su avance.
                        </div>
                    @endforelse

                    <form method="POST" action="{{ route('admin.sitios.hosts.store', $sitio) }}" class="row g-2 align-items-end mt-2">
                        @csrf
                        <div class="col-7">
                            <label class="form-label" style="font-size:.72rem">Host de CheckMK</label>
                            <input type="text" name="host_name" class="form-control form-control-sm" list="dlHostsSitio" required placeholder="19.CAMPO_PORVENIR">
                            <datalist id="dlHostsSitio">
                                @foreach($hostsCheckMk as $h)<option value="{{ $h }}">@endforeach
                            </datalist>
                        </div>
                        <div class="col-3">
                            <label class="form-label" style="font-size:.72rem">Rol</label>
                            <select name="rol" class="form-select form-select-sm">
                                @foreach(SitioHost::ROLES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-2">
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-link-45deg"></i></button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ── Equipos ────────────────────────────────────────────────── --}}
            <div class="sf-card">
                <h6><i class="bi bi-hdd-network"></i>Equipamiento de red <span class="rt">{{ $sitio->equipos->count() }} equipo(s)</span></h6>
                <div class="sf-body">
                    @foreach($sitio->equipos as $eq)
                    <div class="sf-eq">
                        <div class="cab">
                            <div class="ic"><i class="bi {{ $eq->icono }}"></i></div>
                            <div style="flex:1 1 auto;min-width:0">
                                <div style="font-size:.84rem;font-weight:600">{{ $eq->nombre }}</div>
                                <div style="font-size:.7rem;color:#94a3b8">
                                    {{ $eq->tipo_label }}@if($eq->marca_modelo) · {{ $eq->marca_modelo }}@endif
                                </div>
                            </div>
                            <span class="sf-badge" style="background:{{ $eq->estado_color }};font-size:.62rem">{{ $eq->estado_label }}</span>
                            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1 btn-eq-edit" data-id="{{ $eq->id }}"><i class="bi bi-pencil"></i></button>
                        </div>
                        <div class="datos">
                            @if($eq->ip_gestion)<span><i class="bi bi-hdd-network me-1"></i>{{ $eq->ip_gestion }}</span>@endif
                            @if($eq->zona)<span><i class="bi bi-geo me-1"></i>{{ $eq->zona }}</span>@endif
                            @if($eq->uso_puertos !== null)<span><i class="bi bi-ethernet me-1"></i>{{ $eq->puertos_usados }}/{{ $eq->puertos_totales }} puertos</span>@endif
                            @if($eq->host_name)<span><i class="bi bi-server me-1"></i>{{ $eq->host_name }}</span>@endif
                            @if($eq->tipo === 'ptp' && $eq->ptp_frecuencia)<span><i class="bi bi-broadcast me-1"></i>{{ $eq->ptp_frecuencia }}@if($eq->ptp_distancia_km) · {{ $eq->ptp_distancia_km }} km @endif</span>@endif
                            @if($eq->garantia_hasta)<span class="{{ $eq->garantia_hasta->isPast() ? 'text-danger' : '' }}"><i class="bi bi-shield-check me-1"></i>{{ $eq->garantia_hasta->format('m/Y') }}</span>@endif
                        </div>

                        {{-- Fotos del equipo --}}
                        @if($eq->fotos->isNotEmpty())
                        <div class="sf-fotos mt-2" style="grid-template-columns:repeat(auto-fill,minmax(84px,1fr))">
                            @foreach($eq->fotos as $f)
                            <div class="sf-foto" style="aspect-ratio:1">
                                <img src="{{ $f->thumb_url }}" data-full="{{ $f->url }}" class="ver-foto" alt="">
                                <div class="acc">
                                    <form method="POST" action="{{ route('admin.sitios.fotos.destroy', $f) }}" onsubmit="return confirm('¿Eliminar foto?')">
                                        @csrf @method('DELETE')<button title="Eliminar"><i class="bi bi-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @endif

                        {{-- Edición del equipo --}}
                        <div id="eq-{{ $eq->id }}" style="display:none" class="mt-2 pt-2 border-top">
                            <form method="POST" action="{{ route('admin.sitios.equipos.update', $eq) }}">
                                @csrf @method('PUT')
                                @include('admin.sitios._equipo_campos', ['eq' => $eq])
                                <div class="d-flex gap-2 mt-2">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Guardar</button>
                                </div>
                            </form>
                            <div class="d-flex gap-2 mt-2">
                                <form method="POST" action="{{ route('admin.sitios.fotos.store') }}" enctype="multipart/form-data" class="d-flex gap-1 flex-grow-1">
                                    @csrf
                                    <input type="hidden" name="equipo_id" value="{{ $eq->id }}">
                                    <input type="hidden" name="categoria" value="equipo">
                                    <input type="file" name="fotos[]" class="form-control form-control-sm" accept="image/*" multiple required>
                                    <button type="submit" class="btn btn-outline-secondary btn-sm" title="Subir fotos"><i class="bi bi-camera"></i></button>
                                </form>
                                <form method="POST" action="{{ route('admin.sitios.equipos.destroy', $eq) }}" onsubmit="return confirm('¿Eliminar este equipo y sus fotos?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endforeach

                    <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-1" id="btnNuevoEq">
                        <i class="bi bi-plus-lg me-1"></i>Agregar equipo
                    </button>
                    <div id="nuevoEq" style="display:none" class="mt-2 pt-2 border-top">
                        <form method="POST" action="{{ route('admin.sitios.equipos.store', $sitio) }}">
                            @csrf
                            @include('admin.sitios._equipo_campos', ['eq' => null])
                            <button type="submit" class="btn btn-success btn-sm mt-2 w-100"><i class="bi bi-plus-lg me-1"></i>Agregar</button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ── Fotos del sitio ────────────────────────────────────────── --}}
            <div class="sf-card">
                <h6><i class="bi bi-images"></i>Fotos del sitio <span class="rt">{{ $sitio->fotos->count() }}</span></h6>
                <div class="sf-body">
                    @if($sitio->fotos->isNotEmpty())
                    <div class="sf-fotos mb-3">
                        @foreach($sitio->fotos as $f)
                        <div class="sf-foto {{ $f->portada ? 'es-portada' : '' }}">
                            <img src="{{ $f->thumb_url }}" data-full="{{ $f->url }}" class="ver-foto" alt="">
                            @if($f->categoria_label)<div class="cap">{{ $f->categoria_label }}</div>@endif
                            <div class="acc">
                                @unless($f->portada)
                                <form method="POST" action="{{ route('admin.sitios.fotos.update', $f) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="portada" value="1">
                                    <input type="hidden" name="categoria" value="{{ $f->categoria }}">
                                    <button title="Usar como portada"><i class="bi bi-star"></i></button>
                                </form>
                                @endunless
                                <form method="POST" action="{{ route('admin.sitios.fotos.destroy', $f) }}" onsubmit="return confirm('¿Eliminar foto?')">
                                    @csrf @method('DELETE')<button title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    <form method="POST" action="{{ route('admin.sitios.fotos.store') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                        @csrf
                        <input type="hidden" name="sitio_id" value="{{ $sitio->id }}">
                        <div class="col-5">
                            <label class="form-label" style="font-size:.72rem">Categoría</label>
                            <select name="categoria" class="form-select form-select-sm">
                                @foreach(SitioFoto::CATEGORIAS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label" style="font-size:.72rem">Fotos <span class="text-muted">(varias)</span></label>
                            <input type="file" name="fotos[]" class="form-control form-control-sm" accept="image/*" multiple required>
                        </div>
                        <div class="col-2">
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-upload"></i></button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ── Acciones ───────────────────────────────────────────────── --}}
            <div class="sf-card">
                <h6><i class="bi bi-three-dots"></i>Acciones</h6>
                <div class="sf-body">
                    <form method="POST" action="{{ route('admin.sitios.clonar', $sitio) }}" class="row g-2 align-items-end mb-2">
                        @csrf
                        <div class="col-4"><label class="form-label" style="font-size:.72rem">Código</label>
                            <input type="text" name="codigo" class="form-control form-control-sm"></div>
                        <div class="col-5"><label class="form-label" style="font-size:.72rem">Nombre del nuevo sitio</label>
                            <input type="text" name="nombre" class="form-control form-control-sm" required></div>
                        <div class="col-3"><button type="submit" class="btn btn-outline-secondary btn-sm w-100" title="Crea una ficha nueva con este equipamiento como plantilla">
                            <i class="bi bi-copy me-1"></i>Clonar</button></div>
                    </form>
                    @can('admin')
                    <form method="POST" action="{{ route('admin.sitios.destroy', $sitio) }}" onsubmit="return confirm('¿Eliminar esta ficha con sus equipos y fotos? No se puede deshacer.')">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm w-100"><i class="bi bi-trash me-1"></i>Eliminar ficha</button>
                    </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</div>

<div class="sf-vis" id="visor"><img src="" alt=""></div>

{{-- Sin recargar al cerrar: acá botaría los cambios sin guardar de la ficha. --}}
@include('admin.sitios._modal_zonas')

@push('scripts')
<script>
document.querySelectorAll('.btn-eq-edit').forEach(b => b.addEventListener('click', () => {
    const box = document.getElementById('eq-' + b.dataset.id);
    box.style.display = box.style.display === 'none' ? '' : 'none';
}));
document.getElementById('btnNuevoEq').addEventListener('click', () => {
    const box = document.getElementById('nuevoEq');
    box.style.display = box.style.display === 'none' ? '' : 'none';
});

// Visor de fotos a pantalla completa
const visor = document.getElementById('visor');
document.querySelectorAll('.ver-foto').forEach(img => img.addEventListener('click', () => {
    visor.querySelector('img').src = img.dataset.full || img.src;
    visor.style.display = 'flex';
}));
visor.addEventListener('click', () => visor.style.display = 'none');
document.addEventListener('keydown', e => { if (e.key === 'Escape') visor.style.display = 'none'; });

// Región y comuna: se llenan desde la API interna de división política
(function () {
    const selRegion = document.getElementById('selRegion');
    const selComuna = document.getElementById('selComuna');
    const opcion = (valor, texto) => new Option(texto ?? valor, valor);

    // Deja seleccionado un valor aunque no esté en la lista (fichas antiguas).
    const elegir = (sel, valor) => {
        if (valor && ![...sel.options].some(o => o.value === valor)) sel.add(opcion(valor));
        sel.value = valor || '';
    };

    fetch(@json(route('admin.sitios.dpa')))
        .then(r => r.json())
        .then(dpa => {
            Object.keys(dpa).forEach(r => selRegion.add(opcion(r)));
            elegir(selRegion, selRegion.dataset.actual);

            const pintarComunas = (region, elegida) => {
                selComuna.innerHTML = '';
                selComuna.add(opcion('', '—'));
                (dpa[region] || []).forEach(c => selComuna.add(opcion(c)));
                elegir(selComuna, elegida);
            };

            pintarComunas(selRegion.value, selComuna.dataset.actual);
            selRegion.addEventListener('change', () => pintarComunas(selRegion.value, ''));
        })
        .catch(() => {
            // Sin API, al menos conserva lo ya guardado.
            elegir(selRegion, selRegion.dataset.actual);
            elegir(selComuna, selComuna.dataset.actual);
        });
})();

// Mostrar solo los campos que aplican al tipo de equipo elegido
function aplicarTipo(sel) {
    const form = sel.closest('form');
    form.querySelectorAll('[data-solo]').forEach(el => {
        el.style.display = el.dataset.solo.split(',').includes(sel.value) ? '' : 'none';
    });
}
document.querySelectorAll('select.eq-tipo').forEach(sel => {
    aplicarTipo(sel);
    sel.addEventListener('change', () => aplicarTipo(sel));
});
</script>
@endpush
@endsection
