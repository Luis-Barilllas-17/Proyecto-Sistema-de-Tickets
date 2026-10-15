<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Sistema institucional de gestión de tickets ZOLIC">
    <title>@yield('title', 'Panel') | Sistema de Tickets ZOLIC</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-page {{ request()->routeIs('dashboard') ? 'has-page-loader' : '' }}">
    @if (request()->routeIs('dashboard'))
        <div class="page-loader" data-page-loader data-loader-key="zolic-dashboard-loader" role="status" aria-live="polite" aria-label="Cargando el panel principal">
            <div class="page-loader__stage">
                <span class="page-loader__orbit" aria-hidden="true"></span>
                <span class="page-loader__ray page-loader__ray--one" aria-hidden="true"></span>
                <span class="page-loader__ray page-loader__ray--two" aria-hidden="true"></span>
                <span class="page-loader__ray page-loader__ray--three" aria-hidden="true"></span>
                <span class="page-loader__ray page-loader__ray--four" aria-hidden="true"></span>
                <div class="page-loader__logo">
                    <img src="{{ asset('images/brand/logo-zolic-white.png') }}" alt="ZOLIC">
                </div>
                <span class="page-loader__progress" aria-hidden="true"><i></i></span>
            </div>
        </div>
    @endif

    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar__top">
                <a href="{{ route('dashboard') }}" class="sidebar-logo">
                    <img src="{{ asset('images/brand/logo-zolic-white.png') }}" alt="ZOLIC">
                    <span>Sistema de Gestión de Tickets</span>
                </a>

                <button class="sidebar-close" type="button" data-sidebar-close aria-label="Cerrar menú">×</button>
            </div>

            <div class="sidebar__profile">
                <span class="avatar">{{ str(auth()->user()->name)->substr(0, 1)->upper() }}</span>
                <div>
                    <strong>{{ auth()->user()->name }}</strong>
                    <span>{{ auth()->user()->primary_role_name }}</span>
                </div>
            </div>

            <nav class="sidebar__nav" aria-label="Navegación principal">
                @php
                    $requesterNavigation = auth()->user()->hasRole('usuario-solicitante');
                    $personalNavigationOpen = request()->routeIs('dashboard', 'security.*', 'password.change');
                @endphp

                <details name="sidebar-navigation" class="nav-group {{ $personalNavigationOpen ? 'is-current' : '' }}">
                    <summary class="nav-group__toggle">
                        <span>Mi espacio</span>
                        <span class="nav-group__indicator" aria-hidden="true">/</span>
                    </summary>
                    <div class="nav-group__items">
                        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                            <span class="nav-link__icon" aria-hidden="true">01</span>
                            <span>{{ $requesterNavigation ? 'Mi inicio' : 'Resumen de seguridad' }}</span>
                        </a>

                        @if (auth()->user()->hasPermission('security.view'))
                            <a href="{{ route('security.index') }}" class="nav-link {{ request()->routeIs('security.*') ? 'is-active' : '' }}">
                                <span class="nav-link__icon" aria-hidden="true">02</span>
                                <span>Control de acceso</span>
                            </a>
                        @endif

                        <a href="{{ route('password.change') }}" class="nav-link {{ request()->routeIs('password.change') ? 'is-active' : '' }}">
                            <span class="nav-link__icon" aria-hidden="true">03</span>
                            <span>Cambiar contraseña</span>
                        </a>
                    </div>
                </details>

                @if (auth()->user()->hasPermission('users.view'))
                    <details name="sidebar-navigation" class="nav-group {{ request()->routeIs('administration.*') ? 'is-current' : '' }}">
                        <summary class="nav-group__toggle"><span>Administración</span><span class="nav-group__indicator" aria-hidden="true">/</span></summary>
                        <div class="nav-group__items">
                            <a href="{{ route('administration.dashboard') }}" class="nav-link {{ request()->routeIs('administration.dashboard') ? 'is-active' : '' }}">
                                <span class="nav-link__icon" aria-hidden="true">04</span><span>Resumen organizacional</span>
                            </a>
                            <a href="{{ route('administration.users.index') }}" class="nav-link {{ request()->routeIs('administration.users.*') ? 'is-active' : '' }}">
                                <span class="nav-link__icon" aria-hidden="true">05</span><span>Usuarios</span>
                            </a>
                            @if (auth()->user()->hasPermission('roles.view'))
                                <a href="{{ route('administration.roles.index') }}" class="nav-link {{ request()->routeIs('administration.roles.*') ? 'is-active' : '' }}">
                                    <span class="nav-link__icon" aria-hidden="true">06</span><span>Roles y permisos</span>
                                </a>
                            @endif
                            @if (auth()->user()->hasPermission('organization.view'))
                                <a href="{{ route('administration.organization.index') }}" class="nav-link {{ request()->routeIs('administration.organization.*') ? 'is-active' : '' }}">
                                    <span class="nav-link__icon" aria-hidden="true">07</span><span>Áreas y departamentos</span>
                                </a>
                            @endif
                            @if (auth()->user()->hasPermission('groups.view'))
                                <a href="{{ route('administration.groups.index') }}" class="nav-link {{ request()->routeIs('administration.groups.*') || request()->routeIs('administration.specialties.*') ? 'is-active' : '' }}">
                                    <span class="nav-link__icon" aria-hidden="true">08</span><span>Grupos técnicos</span>
                                </a>
                            @endif
                        </div>
                    </details>
                @endif

                @if (auth()->user()->hasPermission('configuration.view'))
                    <details name="sidebar-navigation" class="nav-group {{ request()->routeIs('configuration.*') ? 'is-current' : '' }}">
                        <summary class="nav-group__toggle"><span>Configuración</span><span class="nav-group__indicator" aria-hidden="true">/</span></summary>
                        <div class="nav-group__items">
                            <a href="{{ route('configuration.dashboard') }}" class="nav-link {{ request()->routeIs('configuration.dashboard') ? 'is-active' : '' }}">
                                <span class="nav-link__icon" aria-hidden="true">09</span><span>Parametrización</span>
                            </a>
                            <a href="{{ route('configuration.categories.index') }}" class="nav-link {{ request()->routeIs('configuration.categories.*') || request()->routeIs('configuration.subcategories.*') ? 'is-active' : '' }}">
                                <span class="nav-link__icon" aria-hidden="true">10</span><span>Categorías</span>
                            </a>
                            <a href="{{ route('configuration.forms.index') }}" class="nav-link {{ request()->routeIs('configuration.forms.*') || request()->routeIs('configuration.fields.*') ? 'is-active' : '' }}">
                                <span class="nav-link__icon" aria-hidden="true">11</span><span>Formularios</span>
                            </a>
                            <a href="{{ route('configuration.approvals.index') }}" class="nav-link {{ request()->routeIs('configuration.approvals.*') || request()->routeIs('configuration.approval-levels.*') || request()->routeIs('configuration.delegations.*') ? 'is-active' : '' }}">
                                <span class="nav-link__icon" aria-hidden="true">12</span><span>Aprobaciones</span>
                            </a>
                            <a href="{{ route('configuration.routing.index') }}" class="nav-link {{ request()->routeIs('configuration.routing.*') ? 'is-active' : '' }}">
                                <span class="nav-link__icon" aria-hidden="true">13</span><span>Direccionamiento</span>
                            </a>
                            <a href="{{ route('configuration.service-levels.index') }}" class="nav-link {{ request()->routeIs('configuration.service-levels.*') || request()->routeIs('configuration.statuses.*') || request()->routeIs('configuration.impacts.*') || request()->routeIs('configuration.urgencies.*') || request()->routeIs('configuration.priorities.*') || request()->routeIs('configuration.priority-matrix.*') || request()->routeIs('configuration.sla-targets.*') ? 'is-active' : '' }}">
                                <span class="nav-link__icon" aria-hidden="true">14</span><span>Estados y SLA</span>
                            </a>
                        </div>
                    </details>
                @endif

                @if (auth()->user()->hasPermission('tickets.view'))
                    <details name="sidebar-navigation" class="nav-group {{ request()->routeIs('tickets.*') ? 'is-current' : '' }}">
                        <summary class="nav-group__toggle"><span>Tickets</span><span class="nav-group__indicator" aria-hidden="true">/</span></summary>
                        <div class="nav-group__items">
                            <a href="{{ route('tickets.index') }}" class="nav-link {{ request()->routeIs('tickets.index', 'tickets.show') && ! request('scope') ? 'is-active' : '' }}">
                                <span class="nav-link__icon" aria-hidden="true">15</span><span>Centro de tickets</span>
                            </a>
                            @if (auth()->user()->hasPermission('tickets.create'))
                                <a href="{{ route('tickets.create') }}" class="nav-link {{ request()->routeIs('tickets.create', 'tickets.edit') ? 'is-active' : '' }}">
                                    <span class="nav-link__icon" aria-hidden="true">16</span><span>Nuevo ticket</span>
                                </a>
                            @endif
                            @if (auth()->user()->hasPermission('tickets.view_assigned'))
                                <a href="{{ route('tickets.index', ['scope' => 'assigned']) }}" class="nav-link {{ request()->routeIs('tickets.index') && request('scope') === 'assigned' ? 'is-active' : '' }}">
                                    <span class="nav-link__icon" aria-hidden="true">17</span><span>Mi bandeja técnica</span>
                                </a>
                            @endif
                            @if (auth()->user()->hasPermission('tickets.approve'))
                                <a href="{{ route('tickets.index', ['scope' => 'approvals']) }}" class="nav-link {{ request()->routeIs('tickets.index') && request('scope') === 'approvals' ? 'is-active' : '' }}">
                                    <span class="nav-link__icon" aria-hidden="true">18</span><span>Aprobaciones pendientes</span>
                                </a>
                            @endif
                        </div>
                    </details>
                @endif

                @if (auth()->user()->hasPermission('operations.workbench') || auth()->user()->hasPermission('notifications.view') || auth()->user()->hasPermission('reports.view'))
                    <details name="sidebar-navigation" class="nav-group {{ request()->routeIs('operations.*', 'notifications.*', 'reports.*') ? 'is-current' : '' }}">
                        <summary class="nav-group__toggle"><span>Operación y control</span><span class="nav-group__indicator" aria-hidden="true">/</span></summary>
                        <div class="nav-group__items">
                            @if(auth()->user()->hasPermission('operations.workbench'))
                                <a href="{{ route('operations.index') }}" class="nav-link {{ request()->routeIs('operations.*') ? 'is-active' : '' }}"><span class="nav-link__icon" aria-hidden="true">27</span><span>Bandeja de IT</span></a>
                            @endif
                            @if(auth()->user()->hasPermission('notifications.view'))
                                <a href="{{ route('notifications.index') }}" class="nav-link {{ request()->routeIs('notifications.*') ? 'is-active' : '' }}"><span class="nav-link__icon" aria-hidden="true">28</span><span>Notificaciones</span>@if(auth()->user()->unreadNotifications()->count())<b class="nav-count">{{ min(99,auth()->user()->unreadNotifications()->count()) }}</b>@endif</a>
                            @endif
                            @if(auth()->user()->hasPermission('reports.view'))
                                <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'is-active' : '' }}"><span class="nav-link__icon" aria-hidden="true">29</span><span>Indicadores y reportes</span></a>
                            @endif
                        </div>
                    </details>
                @endif

                @if (auth()->user()->hasPermission('development.view'))
                    <details name="sidebar-navigation" class="nav-group {{ request()->routeIs('development.*') ? 'is-current' : '' }}">
                        <summary class="nav-group__toggle"><span>Desarrollo</span><span class="nav-group__indicator" aria-hidden="true">/</span></summary>
                        <div class="nav-group__items">
                            <a href="{{ route('development.index') }}" class="nav-link {{ request()->routeIs('development.index', 'development.show') && request('scope') !== 'deployment' ? 'is-active' : '' }}">
                                <span class="nav-link__icon" aria-hidden="true">19</span><span>Desarrollo seguro</span>
                            </a>
                            @if (auth()->user()->hasPermission('development.create'))
                                <a href="{{ route('development.create') }}" class="nav-link {{ request()->routeIs('development.create', 'development.edit') ? 'is-active' : '' }}">
                                    <span class="nav-link__icon" aria-hidden="true">20</span><span>Nueva solicitud</span>
                                </a>
                            @endif
                            @if (auth()->user()->hasPermission('development.deploy_authorize'))
                                <a href="{{ route('development.index', ['scope' => 'deployment']) }}" class="nav-link {{ request()->routeIs('development.index') && request('scope') === 'deployment' ? 'is-active' : '' }}">
                                    <span class="nav-link__icon" aria-hidden="true">21</span><span>Autorizar despliegues</span>
                                </a>
                            @endif
                        </div>
                    </details>
                @endif

                @if (auth()->user()->hasPermission('inventory.view'))
                    <details name="sidebar-navigation" class="nav-group {{ request()->routeIs('inventory.*') ? 'is-current' : '' }}">
                        <summary class="nav-group__toggle"><span>Inventario</span><span class="nav-group__indicator" aria-hidden="true">/</span></summary>
                        <div class="nav-group__items">
                            <a href="{{ route('inventory.index') }}" class="nav-link {{ request()->routeIs('inventory.index', 'inventory.equipment.*') ? 'is-active' : '' }}">
                                <span class="nav-link__icon" aria-hidden="true">22</span><span>Equipos tecnológicos</span>
                            </a>
                            <a href="{{ route('inventory.supplies.index') }}" class="nav-link {{ request()->routeIs('inventory.supplies.*') ? 'is-active' : '' }}">
                                <span class="nav-link__icon" aria-hidden="true">23</span><span>Insumos técnicos</span>
                            </a>
                            @if (auth()->user()->hasPermission('inventory.stock_manage'))
                                <a href="{{ route('inventory.supplies.index', ['stock' => 'low']) }}" class="nav-link {{ request()->routeIs('inventory.supplies.index') && request('stock') === 'low' ? 'is-active' : '' }}">
                                    <span class="nav-link__icon" aria-hidden="true">24</span><span>Alertas de existencias</span>
                                </a>
                            @endif
                        </div>
                    </details>
                @endif

                @if (auth()->user()->hasPermission('maintenance.view'))
                    <details name="sidebar-navigation" class="nav-group {{ request()->routeIs('maintenance.*') ? 'is-current' : '' }}">
                        <summary class="nav-group__toggle"><span>Mantenimiento</span><span class="nav-group__indicator" aria-hidden="true">/</span></summary>
                        <div class="nav-group__items">
                            <a href="{{ route('maintenance.index') }}" class="nav-link {{ request()->routeIs('maintenance.index', 'maintenance.orders.*') ? 'is-active' : '' }}">
                                <span class="nav-link__icon" aria-hidden="true">25</span><span>Calendario preventivo</span>
                            </a>
                            @if (auth()->user()->hasPermission('maintenance.manage_plans'))
                                <a href="{{ route('maintenance.plans.create') }}" class="nav-link {{ request()->routeIs('maintenance.plans.create', 'maintenance.plans.edit') ? 'is-active' : '' }}">
                                    <span class="nav-link__icon" aria-hidden="true">26</span><span>Nuevo plan</span>
                                </a>
                            @endif
                        </div>
                    </details>
                @endif
            </nav>

            <div class="sidebar__security">
                <span class="security-pulse" aria-hidden="true"></span>
                <div>
                    <strong>Sesión protegida</strong>
                    <span>Cierre automático en {{ config('security.session_timeout_minutes') }} min</span>
                </div>
            </div>

            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="sidebar__logout" data-logout-form data-reset-page-loader>
                @csrf
                <button type="submit" form="logout-form" formmethod="post" formaction="{{ route('logout') }}" data-logout-submit>
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
                    @if(auth()->user()->hasPermission('notifications.view'))
                        <a href="{{ route('notifications.index',['scope'=>'unread']) }}" class="topbar-notifications" aria-label="Notificaciones sin leer"><span>●</span>@if(auth()->user()->unreadNotifications()->count())<b>{{ min(99,auth()->user()->unreadNotifications()->count()) }}</b>@endif</a>
                    @endif
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
