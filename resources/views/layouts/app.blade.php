<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="dns-prefetch" href="//fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Nunito" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
            <div class="container-fluid">
                <a class="navbar-brand" href="{{ url('/home') }}">
                    {{ config('app.name', 'Laravel') }}
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    @guest
                        @if (Route::has('login'))
                            
                        @endif
                        @else
                        <!-- Left Side Of Navbar -->
                        <ul class="navbar-nav me-auto">
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('facturas.index') }}">{{ __('Facturas') }}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('servicios.index') }}">{{ __('Servicios') }}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('familias.index') }}">{{ __('Familias') }}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('empresas.index') }}">{{ __('Empresas') }}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('companias.index') }}">{{ __('Compañias') }}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('cuentas_contables.index') }}">{{ __('CC') }}</a>
                            </li>
                        </ul>
                    @endguest
                    
                    
                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Authentication Links -->
                        @guest
                            @if (Route::has('login'))
                                
                            @endif

                            @if (Route::has('register'))
                                
                            @endif
                        @else
                        {{-- <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                        </li> --}}
                        {{-- <li class="nav-item">
                            <b><a class="nav-link" href="{{ route('register') }}">{{ __('Registrar Usuario') }}</a></b>
                        </li> --}}
                            <li class="nav-item dropdown">
                                <a id="navbarDropdown" class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                    {{ Auth::user()->name }}
                                </a>

                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">

                                    <a class="dropdown-item" href="{{ route('register') }}"
                                       onclick="">
                                        {{ __('Registrar Usuario') }}
                                    </a>

                                    <a class="dropdown-item" href="{{ route('logout') }}"
                                       onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                        {{ __('Cerrar Sesión') }}
                                    </a>
                                    

                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
                
            </div>
        </nav>

        <main class="py-4">
            @yield('content')
        </main>
    </div>
</body>
</html>
