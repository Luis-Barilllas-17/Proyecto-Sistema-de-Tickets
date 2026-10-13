<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Acceso seguro al Sistema de Gestión de Tickets ZOLIC">
    <title>@yield('title', 'Acceso') | Sistema de Tickets ZOLIC</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="guest-page">
    <main class="auth-shell">
        <section class="auth-brand auth-brand--photo" aria-label="Sistema de Gestión de Tickets ZOLIC">
            <div class="auth-brand__content">
                <a href="{{ route('login') }}" class="official-brand" aria-label="Ir al inicio de sesión">
                    <img src="{{ asset('images/brand/logo-zolic-white.png') }}" alt="ZOLIC · Zona Libre de Industria y Comercio Santo Tomás de Castilla">
                </a>
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-mobile-brand">
                <a href="{{ route('login') }}" class="official-brand official-brand--mobile">
                    <img src="{{ asset('images/brand/logo-zolic-white.png') }}" alt="ZOLIC">
                </a>
            </div>

            <div class="auth-card">
                @if (session('status'))
                    <div class="alert alert--success" role="status">
                        <span class="alert__icon" aria-hidden="true">✓</span>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert--danger" role="alert">
                        <span class="alert__icon" aria-hidden="true">!</span>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                @yield('content')
            </div>

            <p class="auth-panel__help">
                ¿Necesitas asistencia? <strong>Contacta a la Mesa de Ayuda de IT</strong>
            </p>
        </section>
    </main>
</body>
</html>
