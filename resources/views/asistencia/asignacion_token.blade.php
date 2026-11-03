@extends('layouts.app')

@section('content')
<style>
    .as-pasos { counter-reset:paso; list-style:none; padding-left:0; }
    .as-pasos li { counter-increment:paso; position:relative; padding:0 0 .9rem 2.6rem; }
    .as-pasos li::before { content:counter(paso); position:absolute; left:0; top:-.1rem; width:1.8rem; height:1.8rem;
                           border-radius:50%; background:#dbeafe; color:#1d4ed8; font-weight:700;
                           display:flex; align-items:center; justify-content:center; font-size:.85rem; }
    .as-ruta { background:#f1f5f9; border-radius:.3rem; padding:.05rem .35rem; font-weight:600; white-space:nowrap; }
</style>

<div class="container-fluid vti-page">
    <div class="vti-page-header mb-3">
        <h4 class="mb-0"><i class="bi bi-person-plus me-2 text-primary"></i>Asignación de tickets</h4>
        <small class="text-muted">Hola, {{ $glpi['nombre'] }}. Antes de empezar necesitamos tu token personal de GLPI.</small>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white fw-semibold border-bottom py-2">
                    <i class="bi bi-list-check me-1 text-primary"></i> Cómo obtener tu token en GLPI
                </div>
                <div class="card-body">
                    <ol class="as-pasos mb-2">
                        <li>
                            Abre GLPI e inicia sesión con tu cuenta de Unifrutti:
                            <a href="{{ $urlGlpi }}" target="_blank" rel="noopener">{{ $urlGlpi }} <i class="bi bi-box-arrow-up-right small"></i></a>
                        </li>
                        <li>Arriba a la derecha, haz clic en <strong>tu nombre</strong> y entra a <span class="as-ruta">Mis ajustes</span>.</li>
                        <li>En la pestaña <span class="as-ruta">Principal</span>, baja hasta la sección <span class="as-ruta">Claves de acceso remoto</span>.</li>
                        <li>Junto a <span class="as-ruta">Token de API</span>, marca la casilla <span class="as-ruta">Regenerar</span> y presiona <span class="as-ruta">Guardar</span> al final de la página.</li>
                        <li>Vuelve a esa misma sección: ahora aparece el token (unos 40 caracteres). <strong>Cópialo completo</strong> y pégalo aquí.</li>
                    </ol>
                    <div class="alert alert-light border small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Si ya tenías un token y lo usas en otra herramienta, regenerarlo invalida el anterior: en ese caso
                        copia el que ya tienes en vez de marcar «Regenerar».
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-header bg-white fw-semibold border-bottom py-2">
                    <i class="bi bi-key-fill me-1 text-warning"></i> Tu token personal
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('asistencia.asignacion.token') }}">
                        @csrf
                        <label class="form-label small text-muted" for="as-token">Token de API de GLPI</label>
                        <div class="input-group">
                            <input type="password" name="token" id="as-token" class="form-control @error('token') is-invalid @enderror"
                                   autocomplete="off" spellcheck="false" required placeholder="Pega aquí tu token">
                            <button type="button" class="btn btn-outline-secondary" title="Mostrar u ocultar"
                                    onclick="const i=document.getElementById('as-token'); i.type = i.type === 'password' ? 'text' : 'password'">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        @error('token') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                        <button class="btn btn-primary w-100 mt-3"><i class="bi bi-check-lg me-1"></i>Validar y guardar</button>
                    </form>

                    <ul class="small text-muted mt-3 mb-0 ps-3">
                        <li>Antes de guardarlo lo probamos contra GLPI y comprobamos que sea <strong>tuyo</strong>.</li>
                        <li>Se guarda cifrado y solo se usa para lo que asignes desde esta pantalla.</li>
                        <li>En el historial del ticket en GLPI, la asignación queda a tu nombre.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
