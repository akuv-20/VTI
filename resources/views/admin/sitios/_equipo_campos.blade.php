{{-- Campos de un equipo de red. Reutilizado por el alta y la edición. --}}
@php
    use App\Models\SitioEquipo;
    $v = fn($campo, $def = '') => old($campo, $eq?->{$campo} ?? $def);
@endphp

<div class="sf-grid">
    <div class="sf-f">
        <label>Tipo *</label>
        <select name="tipo" class="form-select form-select-sm eq-tipo" required>
            @foreach(SitioEquipo::TIPOS as $k => $l)
                <option value="{{ $k }}" @selected($v('tipo', 'switch') === $k)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div class="sf-f" style="grid-column:span 2">
        <label>Nombre *</label>
        <input type="text" name="nombre" class="form-control form-control-sm" value="{{ $v('nombre') }}" required placeholder="Switch rack central">
    </div>
    <div class="sf-f">
        <label>Estado *</label>
        <select name="estado" class="form-select form-select-sm">
            @foreach(SitioEquipo::ESTADOS as $k => $l)
                <option value="{{ $k }}" @selected($v('estado', 'operativo') === $k)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div class="sf-f"><label>Marca</label><input type="text" name="marca" class="form-control form-control-sm" value="{{ $v('marca') }}" list="dlMarcas"></div>
    <div class="sf-f"><label>Modelo</label><input type="text" name="modelo" class="form-control form-control-sm" value="{{ $v('modelo') }}"></div>
    <div class="sf-f"><label>N° de serie</label><input type="text" name="serie" class="form-control form-control-sm" value="{{ $v('serie') }}"></div>
    <div class="sf-f"><label>MAC</label><input type="text" name="mac" class="form-control form-control-sm" value="{{ $v('mac') }}"></div>
    <div class="sf-f"><label>IP de gestión</label><input type="text" name="ip_gestion" class="form-control form-control-sm" value="{{ $v('ip_gestion') }}"></div>
    <div class="sf-f"><label>VLAN</label><input type="text" name="vlan" class="form-control form-control-sm" value="{{ $v('vlan') }}"></div>
    <div class="sf-f"><label>Firmware</label><input type="text" name="firmware" class="form-control form-control-sm" value="{{ $v('firmware') }}"></div>
    <div class="sf-f"><label>Zona / sala</label><input type="text" name="zona" class="form-control form-control-sm" value="{{ $v('zona') }}" placeholder="Packing, altillo, rack central"></div>
    <div class="sf-f"><label>Rack / U</label><input type="text" name="rack_u" class="form-control form-control-sm" value="{{ $v('rack_u') }}"></div>
    <div class="sf-f"><label>Uplink a</label><input type="text" name="uplink" class="form-control form-control-sm" value="{{ $v('uplink') }}" placeholder="Switch core pto 24"></div>

    {{-- Solo switches --}}
    <div class="sf-f" data-solo="switch"><label>Puertos totales</label><input type="number" name="puertos_totales" class="form-control form-control-sm" value="{{ $v('puertos_totales') }}"></div>
    <div class="sf-f" data-solo="switch"><label>Puertos usados</label><input type="number" name="puertos_usados" class="form-control form-control-sm" value="{{ $v('puertos_usados') }}"></div>

    {{-- Solo enlaces punto a punto --}}
    <div class="sf-f" data-solo="ptp"><label>Frecuencia</label><input type="text" name="ptp_frecuencia" class="form-control form-control-sm" value="{{ $v('ptp_frecuencia') }}" placeholder="5.8 GHz"></div>
    <div class="sf-f" data-solo="ptp"><label>Azimut (°)</label><input type="number" name="ptp_azimut" class="form-control form-control-sm" value="{{ $v('ptp_azimut') }}"></div>
    <div class="sf-f" data-solo="ptp"><label>Distancia (km)</label><input type="text" name="ptp_distancia_km" class="form-control form-control-sm" value="{{ $v('ptp_distancia_km') }}"></div>
    <div class="sf-f" data-solo="ptp"><label>Altura (m)</label><input type="text" name="ptp_altura_m" class="form-control form-control-sm" value="{{ $v('ptp_altura_m') }}"></div>
    <div class="sf-f" data-solo="ptp" style="grid-column:span 2"><label>Equipo remoto pareado</label><input type="text" name="ptp_par_remoto" class="form-control form-control-sm" value="{{ $v('ptp_par_remoto') }}"></div>

    {{-- Solo access points --}}
    <div class="sf-f" data-solo="ap" style="grid-column:span 2"><label>SSIDs</label><input type="text" name="ap_ssids" class="form-control form-control-sm" value="{{ $v('ap_ssids') }}"></div>
    <div class="sf-f" data-solo="ap"><label>Banda</label><input type="text" name="ap_banda" class="form-control form-control-sm" value="{{ $v('ap_banda') }}" placeholder="2.4 / 5 GHz"></div>
    <div class="sf-f" data-solo="ap"><label>Canal</label><input type="text" name="ap_canal" class="form-control form-control-sm" value="{{ $v('ap_canal') }}"></div>

    <div class="sf-f" style="grid-column:span 2">
        <label>Host en CheckMK</label>
        <input type="text" name="host_name" class="form-control form-control-sm" value="{{ $v('host_name') }}" list="dlHostsSitio">
    </div>
    <div class="sf-f"><label>Proveedor</label><input type="text" name="proveedor" class="form-control form-control-sm" value="{{ $v('proveedor') }}"></div>
    <div class="sf-f"><label>Fecha compra</label><input type="date" name="fecha_compra" class="form-control form-control-sm" value="{{ old('fecha_compra', $eq?->fecha_compra?->format('Y-m-d')) }}"></div>
    <div class="sf-f"><label>Garantía hasta</label><input type="date" name="garantia_hasta" class="form-control form-control-sm" value="{{ old('garantia_hasta', $eq?->garantia_hasta?->format('Y-m-d')) }}"></div>
</div>

<div class="form-check mt-2">
    <input class="form-check-input" type="checkbox" name="poe" value="1" id="poe-{{ $eq?->id ?? 'new' }}" @checked($v('poe'))>
    <label class="form-check-label" for="poe-{{ $eq?->id ?? 'new' }}" style="font-size:.78rem">Tiene PoE</label>
</div>

<div class="sf-f mt-2">
    <label>Notas</label>
    <textarea name="notas" class="form-control form-control-sm" rows="2">{{ $v('notas') }}</textarea>
</div>

<datalist id="dlMarcas">
    <option value="Ubiquiti"><option value="Cisco"><option value="TP-Link"><option value="MikroTik">
    <option value="HPE / Aruba"><option value="Fortinet"><option value="Dahua"><option value="Hikvision">
    <option value="APC"><option value="Dell"><option value="Starlink">
</datalist>
