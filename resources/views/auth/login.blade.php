@extends('layouts.login')

@section('content')

<div class="login-logo">
    @if(!empty($appLogo))
        <img src="{{ $appLogo }}" alt="Logo"
             style="height:56px;object-fit:contain;margin-bottom:.6rem;display:block;margin-left:auto;margin-right:auto">
    @else
        <div class="app-icon">
            <i class="bi bi-building-check"></i>
        </div>
    @endif
    <h5>{{ $appNombre ?? config('app.name') }}</h5>
    <small>Sistema de Gestión</small>
</div>

@if($errors->any())
    <div class="alert alert-danger py-2 px-3 mb-3 rounded-3" style="font-size:.83rem">
        <i class="bi bi-exclamation-triangle-fill me-1"></i>
        {{ $errors->first() }}
    </div>
@endif

@if(session('azure_pendiente'))
    <div class="alert py-2 px-3 mb-3 rounded-3" style="font-size:.83rem;background:#fef9c3;color:#854d0e;border:1px solid #fde68a">
        <i class="bi bi-clock-fill me-1"></i>{{ session('azure_pendiente') }}
    </div>
@endif

@if(!empty($azureEnabled))
    <a href="{{ route('azure.redirect') }}" class="btn-microsoft mb-1">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 23 23">
            <path fill="#f3f3f3" d="M0 0h23v23H0z"/>
            <path fill="#f35325" d="M1 1h10v10H1z"/>
            <path fill="#81bc06" d="M12 1h10v10H12z"/>
            <path fill="#05a6f0" d="M1 12h10v10H1z"/>
            <path fill="#ffba08" d="M12 12h10v10H12z"/>
        </svg>
        Continuar con Microsoft 365
    </a>
    <div class="login-divider">o ingresa con tu cuenta</div>
@endif

<form method="POST" action="{{ route('login') }}">
    @csrf

    <div class="mb-3">
        <label for="email" class="form-label">Correo electrónico</label>
        <input id="email" type="email" name="email"
               class="form-control @error('email') is-invalid @enderror"
               value="{{ old('email') }}"
               placeholder="usuario@empresa.cl"
               required autocomplete="email" autofocus>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Contraseña</label>
        <div class="password-wrapper">
            <input id="password" type="password" name="password"
                   class="form-control @error('password') is-invalid @enderror"
                   placeholder="••••••••"
                   required autocomplete="current-password"
                   style="padding-right:2.8rem">
            <button type="button" class="toggle-pw" onclick="togglePw()" tabindex="-1">
                <i class="bi bi-eye-fill" id="pw-icon"></i>
            </button>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="form-check mb-0">
            <input class="form-check-input" type="checkbox" name="remember" id="remember"
                   {{ old('remember') ? 'checked' : '' }}>
            <label class="form-check-label" for="remember"
                   style="font-size:.82rem;color:#64748b">Recordarme</label>
        </div>
        @if (Route::has('password.request'))
            <a href="{{ route('password.request') }}"
               style="font-size:.82rem;color:#2563eb;text-decoration:none;font-weight:600">
                ¿Olvidaste tu contraseña?
            </a>
        @endif
    </div>

    <button type="submit" class="btn-login">
        Iniciar sesión
    </button>
</form>

<div class="login-footer">
    {{ $appNombre ?? config('app.name') }} &copy; {{ date('Y') }}
</div>

<script>
function togglePw() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('pw-icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash-fill';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye-fill';
    }
}
</script>

@endsection
