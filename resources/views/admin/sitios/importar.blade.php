@extends('layouts.app')

@section('content')
<style>
    .im-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:1.2rem 1.35rem; margin-bottom:1.1rem; max-width:820px; }
    .im-card h6 { font-size:.8rem; font-weight:700; color:#334155; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.9rem; }
    .im-paso { display:flex; gap:.9rem; align-items:flex-start; margin-bottom:1.1rem; }
    .im-num { width:26px; height:26px; border-radius:50%; background:#7c3aed; color:#fff; display:flex; align-items:center;
              justify-content:center; font-size:.78rem; font-weight:700; flex:0 0 auto; }
    .im-paso .tx { font-size:.83rem; color:#475569; line-height:1.6; }
    .im-cols { font-size:.74rem; color:#64748b; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:.7rem .85rem; }
    .im-cols code { background:#eef2ff; color:#4338ca; border-radius:4px; padding:0 4px; font-size:.72rem; }
</style>

<div class="container-fluid vti-page">

    <div class="vti-page-header">
        <h4><i class="bi bi-file-earmark-excel me-2" style="color:#16a34a"></i>Importar sitios desde Excel</h4>
        <a href="{{ route('admin.sitios.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Sitios</a>
    </div>

    <div class="im-card">
        <div class="im-paso">
            <div class="im-num">1</div>
            <div class="tx">
                <b>Descarga la plantilla</b> con las columnas correctas y una fila de ejemplo.
                <div class="mt-2">
                    <a href="{{ route('admin.sitios.importar.plantilla') }}" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-download me-1"></i>Descargar plantilla_sitios.xlsx
                    </a>
                </div>
            </div>
        </div>

        <div class="im-paso">
            <div class="im-num">2</div>
            <div class="tx">
                <b>Llena una fila por sitio.</b> Solo <code>Nombre</code> y <code>Tipo</code> son obligatorios —
                el resto lo puedes completar después en cada ficha o desde el celular en terreno.
                <div class="im-cols mt-2">
                    <b>Valores permitidos</b><br>
                    Tipo: <code>planta</code> <code>campo</code> <code>datacenter</code> <code>oficina</code><br>
                    Estado enlace: <code>sin_enlace</code> <code>en_gestion</code> <code>en_instalacion</code> <code>operativo</code><br>
                    Enlace: <code>fibra</code> <code>ptp</code> <code>starlink</code> <code>4g</code> <code>satelital</code> <code>ninguno</code>
                    <div class="mt-2 text-muted">
                        Si dejas el estado vacío queda como <code>sin_enlace</code>, que es lo correcto para los campos nuevos.
                    </div>
                </div>
            </div>
        </div>

        <div class="im-paso">
            <div class="im-num">3</div>
            <div class="tx" style="width:100%">
                <b>Sube el archivo.</b> Los sitios se identifican por <b>código</b> (o por nombre si no tiene código);
                los que ya existen se omiten, salvo que marques la casilla de actualizar.
                <form method="POST" action="{{ route('admin.sitios.importar.procesar') }}" enctype="multipart/form-data" class="mt-2">
                    @csrf
                    <input type="file" name="archivo" class="form-control form-control-sm mb-2" accept=".xlsx,.xls,.csv" required>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="actualizar" value="1" id="act">
                        <label class="form-check-label" for="act" style="font-size:.8rem">
                            Actualizar los sitios que ya existen con los datos del archivo
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-upload me-1"></i>Importar
                    </button>
                </form>
                @error('archivo')<div class="text-danger mt-2" style="font-size:.78rem">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>
@endsection
