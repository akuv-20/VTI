<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appNombre ?? config('app.name') }} — Iniciar Sesión</title>
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        html, body {
            height: 100%;
            margin: 0;
            font-family: 'Nunito', sans-serif;
        }

        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Fondo: imagen o gradiente azul */
        .login-bg {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            filter: brightness(.55) saturate(1.1);
            transform: scale(1.03);
            transition: opacity .3s;
        }

        /* Overlay oscuro sutil sobre el fondo */
        .login-overlay {
            position: absolute;
            inset: 0;
            background: rgba(10, 20, 40, 0.35);
        }

        /* Tarjeta del formulario */
        .login-card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 420px;
            margin: 1.5rem;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 24px 80px rgba(0,0,0,.35), 0 4px 20px rgba(0,0,0,.15);
            padding: 2.8rem 2.5rem 2.2rem;
            animation: slideUp .4s ease;
        }

        @keyframes slideUp {
            from { opacity:0; transform: translateY(24px); }
            to   { opacity:1; transform: translateY(0); }
        }

        .login-logo {
            text-align: center;
            margin-bottom: 1.4rem;
        }

        .login-logo .app-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #1e3a5f, #2563eb);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: .6rem;
            box-shadow: 0 8px 24px rgba(37,99,235,.35);
        }

        .login-logo .app-icon i {
            font-size: 1.6rem;
            color: #fff;
        }

        .login-logo h5 {
            font-size: 1.3rem;
            font-weight: 800;
            color: #1e293b;
            margin: 0;
            letter-spacing: -.02em;
        }

        .login-logo small {
            color: #94a3b8;
            font-size: .8rem;
        }

        .login-card .form-label {
            font-weight: 600;
            font-size: .82rem;
            color: #475569;
            margin-bottom: .3rem;
        }

        .login-card .form-control {
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            padding: .65rem 1rem;
            font-size: .9rem;
            transition: border-color .15s, box-shadow .15s;
        }

        .login-card .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,.15);
        }

        .login-card .form-control.is-invalid {
            border-color: #ef4444;
        }

        .btn-login {
            width: 100%;
            padding: .75rem;
            border-radius: 10px;
            background: linear-gradient(135deg, #1e40af, #2563eb);
            border: none;
            color: #fff;
            font-weight: 700;
            font-size: .95rem;
            letter-spacing: .02em;
            cursor: pointer;
            transition: transform .1s, box-shadow .15s, opacity .15s;
            box-shadow: 0 4px 16px rgba(37,99,235,.4);
        }

        .btn-login:hover {
            opacity: .93;
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(37,99,235,.45);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .login-footer {
            text-align: center;
            margin-top: 1.2rem;
            font-size: .78rem;
            color: #94a3b8;
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper .toggle-pw {
            position: absolute;
            right: .9rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 0;
            font-size: 1rem;
            line-height: 1;
        }

        .password-wrapper .toggle-pw:hover { color: #64748b; }

        /* Botón Microsoft */
        .btn-microsoft {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .65rem;
            padding: .68rem 1rem;
            border-radius: 10px;
            border: 1.5px solid #e2e8f0;
            background: #fff;
            color: #1e293b;
            font-weight: 600;
            font-size: .9rem;
            cursor: pointer;
            text-decoration: none;
            transition: background .15s, border-color .15s, box-shadow .15s, transform .1s;
        }
        .btn-microsoft:hover {
            background: #f8fafc;
            border-color: #2563eb;
            box-shadow: 0 4px 14px rgba(37,99,235,.12);
            transform: translateY(-1px);
            color: #1e293b;
        }
        .btn-microsoft:active { transform: translateY(0); }

        .login-divider {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin: 1.1rem 0;
            color: #cbd5e1;
            font-size: .78rem;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .login-divider::before,
        .login-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }
    </style>
</head>
<body>
<div class="login-page">

    {{-- Fondo dinámico --}}
    <div class="login-bg" id="loginBg"></div>
    <div class="login-overlay"></div>

    {{-- Tarjeta --}}
    <div class="login-card">
        @yield('content')
    </div>

</div>

<script>
    // Inyectar fondo desde PHP sin exponer lógica en el layout
    (function() {
        const bg = @json($loginBackground ?? null);
        const el = document.getElementById('loginBg');
        if (bg) {
            el.style.backgroundImage = 'url(' + bg + ')';
        } else {
            el.style.background = 'linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #1d4ed8 100%)';
        }
    })();
</script>
</body>
</html>
