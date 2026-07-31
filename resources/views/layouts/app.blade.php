<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Módulo de autenticación y seguridad del Sistema de Tickets ZOLIC">
    <title>@yield('title', 'Panel') | Sistema de Tickets ZOLIC</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-page">
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar__top">
                <a href="{{ route('dashboard') }}" class="brand-lockup brand-lockup--light">
                    <span class="brand-mark" aria-hidden="true">
                        <span class="brand-mark__line"></span>
                        <strong>Z</strong>
                    </span>
                    <span>
                        <strong>ZOLIC</strong>
                        <small>Sistema de Tickets</small>
                    </span>
                </a>

                <button class="sidebar-close" type="button" data-sidebar-close aria-label="Cerrar menú">×</button>
            </div>

            <div class="sidebar__profile">
                <span class="avatar">{{ str(auth()->user()->name)->substr(0, 1)->upper() }}</span>
                <div>
                    <strong>{{ auth()->user()->name }}</strong>
                    <span>{{ auth()->user()->is_super_admin ? 'Super Administrador' : 'Usuario institucional' }}</span>
                </div>
            </div>

            <nav class="sidebar__nav" aria-label="Navegación principal">
                <span class="nav-section">Módulo 1</span>
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                    <span class="nav-link__icon" aria-hidden="true">01</span>
                    <span>Resumen de seguridad</span>
                </a>

                @if (auth()->user()->is_super_admin)
                    <a href="{{ route('security.index') }}" class="nav-link {{ request()->routeIs('security.*') ? 'is-active' : '' }}">
                        <span class="nav-link__icon" aria-hidden="true">02</span>
                        <span>Control de acceso</span>
                    </a>
                @endif

                <a href="{{ route('password.change') }}" class="nav-link {{ request()->routeIs('password.change') ? 'is-active' : '' }}">
                    <span class="nav-link__icon" aria-hidden="true">03</span>
                    <span>Cambiar contraseña</span>
                </a>
            </nav>

            <div class="sidebar__security">
                <span class="security-pulse" aria-hidden="true"></span>
                <div>
                    <strong>Sesión protegida</strong>
                    <span>Cierre automático en {{ config('security.session_timeout_minutes') }} min</span>
                </div>
            </div>

            <form action="{{ route('logout') }}" method="POST" class="sidebar__logout">
                @csrf
                <button type="submit">
                    <span aria-hidden="true">↗</span>
                    Cerrar sesión
                </button>
            </form>
        </aside>

        <div class="sidebar-overlay" data-sidebar-close></div>

        <div class="app-main">
            <header class="topbar">
                <button class="menu-trigger" type="button" data-sidebar-open aria-label="Abrir menú">
                    <span></span><span></span><span></span>
                </button>

                <div class="topbar__context">
                    <span class="topbar__module">Sistema de Gestión de Tickets</span>
                    <strong>@yield('section-title', 'Seguridad y acceso')</strong>
                </div>

                <div class="topbar__user">
                    <div class="topbar__date">
                        <span>{{ now()->locale('es')->isoFormat('dddd') }}</span>
                        <strong>{{ now()->locale('es')->isoFormat('D [de] MMMM') }}</strong>
                    </div>
                    <span class="avatar avatar--small">{{ str(auth()->user()->name)->substr(0, 1)->upper() }}</span>
                </div>
            </header>

            <main class="content">
                @if (session('success'))
                    <div class="alert alert--success alert--floating" role="status" data-auto-dismiss>
                        <span class="alert__icon" aria-hidden="true">✓</span>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                @if (session('warning'))
                    <div class="alert alert--warning alert--floating" role="status">
                        <span class="alert__icon" aria-hidden="true">!</span>
                        <span>{{ session('warning') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert--danger alert--floating" role="alert">
                        <span class="alert__icon" aria-hidden="true">!</span>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
