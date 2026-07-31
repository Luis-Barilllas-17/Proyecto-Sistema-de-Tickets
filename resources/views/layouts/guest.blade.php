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
        <section class="auth-brand" aria-label="Sistema de Gestión de Tickets ZOLIC">
            <div class="auth-brand__glow auth-brand__glow--one"></div>
            <div class="auth-brand__glow auth-brand__glow--two"></div>

            <div class="auth-brand__content">
                <a href="{{ route('login') }}" class="brand-lockup brand-lockup--light" aria-label="Ir al inicio de sesión">
                    <span class="brand-mark" aria-hidden="true">
                        <span class="brand-mark__line"></span>
                        <strong>Z</strong>
                    </span>
                    <span>
                        <strong>ZOLIC</strong>
                        <small>Gestión institucional</small>
                    </span>
                </a>

                <div class="auth-brand__message">
                    <span class="eyebrow eyebrow--light">Plataforma de servicio interno</span>
                    <h1>Atención técnica<br>con orden y trazabilidad.</h1>
                    <p>Un espacio seguro para centralizar solicitudes, proteger la información y dar seguimiento al servicio de tecnología.</p>
                </div>

                <div class="auth-trust">
                    <div class="auth-trust__item">
                        <span class="trust-icon" aria-hidden="true">01</span>
                        <div>
                            <strong>Acceso protegido</strong>
                            <span>Control de intentos y sesiones</span>
                        </div>
                    </div>
                    <div class="auth-trust__item">
                        <span class="trust-icon" aria-hidden="true">02</span>
                        <div>
                            <strong>Actividad registrada</strong>
                            <span>Trazabilidad de cada acceso</span>
                        </div>
                    </div>
                </div>

                <p class="auth-brand__footer">Zona Libre de Industria y Comercio Santo Tomás de Castilla</p>
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-mobile-brand">
                <a href="{{ route('login') }}" class="brand-lockup">
                    <span class="brand-mark" aria-hidden="true">
                        <span class="brand-mark__line"></span>
                        <strong>Z</strong>
                    </span>
                    <span>
                        <strong>ZOLIC</strong>
                        <small>Sistema de Tickets</small>
                    </span>
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
