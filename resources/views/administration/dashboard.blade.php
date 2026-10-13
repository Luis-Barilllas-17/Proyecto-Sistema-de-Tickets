@extends('layouts.app')

@section('title', 'Administración organizacional')
@section('section-title', 'Usuarios y estructura organizacional')

@section('content')
    <section class="module-hero module-hero--organization">
        <div class="module-hero__content">
            <span class="eyebrow eyebrow--light">Administración institucional</span>
            <h1>Personas, perfiles y estructura<br>en un solo lugar.</h1>
            <p>Administra quién participa en el sistema, qué puede hacer y cómo se integra dentro de la organización.</p>
            <div class="module-hero__actions">
                @if (auth()->user()->hasPermission('users.create'))
                    <a href="{{ route('administration.users.create') }}" class="button button--light">Crear usuario <span>→</span></a>
                @endif
                <a href="{{ route('administration.organization.index') }}" class="button button--glass">Ver estructura</a>
            </div>
        </div>
        <div class="module-hero__summary">
            <span>Directorio institucional</span>
            <strong>{{ $metrics['active_users'] }}</strong>
            <small>usuarios con acceso activo</small>
        </div>
    </section>

    <section class="metric-grid metric-grid--module-two" aria-label="Indicadores de administración">
        <article class="metric-card metric-card--blue">
            <div class="metric-card__icon"><span>{{ $metrics['users'] }}</span></div>
            <div><span>Usuarios registrados</span><strong>{{ $metrics['users'] }}</strong><small>{{ $metrics['active_users'] }} cuentas activas</small></div>
        </article>
        <article class="metric-card metric-card--teal">
            <div class="metric-card__icon"><span>{{ $metrics['roles'] }}</span></div>
            <div><span>Perfiles habilitados</span><strong>{{ $metrics['roles'] }}</strong><small>Permisos por sección y acción</small></div>
        </article>
        <article class="metric-card metric-card--yellow">
            <div class="metric-card__icon"><span>{{ $metrics['areas'] }}</span></div>
            <div><span>Áreas institucionales</span><strong>{{ $metrics['areas'] }}</strong><small>{{ $metrics['departments'] }} departamentos</small></div>
        </article>
        <article class="metric-card metric-card--orange">
            <div class="metric-card__icon"><span>{{ $metrics['groups'] }}</span></div>
            <div><span>Grupos técnicos</span><strong>{{ $metrics['groups'] }}</strong><small>Equipos internos de IT</small></div>
        </article>
    </section>

    <section class="module-navigation" aria-label="Opciones de administración">
        <a href="{{ route('administration.users.index') }}" class="module-link-card">
            <span class="module-link-card__number">01</span>
            <div><strong>Usuarios</strong><p>Creación, edición, perfiles, estado y ubicación organizacional.</p></div>
            <span class="module-link-card__arrow">→</span>
        </a>
        @if (auth()->user()->hasPermission('roles.view'))
            <a href="{{ route('administration.roles.index') }}" class="module-link-card">
                <span class="module-link-card__number">02</span>
                <div><strong>Roles y permisos</strong><p>Perfiles iniciales y acceso detallado por sección y acción.</p></div>
                <span class="module-link-card__arrow">→</span>
            </a>
        @endif
        <a href="{{ route('administration.organization.index') }}" class="module-link-card">
            <span class="module-link-card__number">03</span>
            <div><strong>Estructura organizacional</strong><p>Áreas, departamentos, jefaturas y subdivisiones internas de IT.</p></div>
            <span class="module-link-card__arrow">→</span>
        </a>
        @if (auth()->user()->hasPermission('groups.view'))
            <a href="{{ route('administration.groups.index') }}" class="module-link-card">
                <span class="module-link-card__number">04</span>
                <div><strong>Grupos técnicos</strong><p>Equipos, coordinadores, integrantes y especialidades.</p></div>
                <span class="module-link-card__arrow">→</span>
            </a>
        @endif
    </section>

    <div class="dashboard-grid administration-grid">
        <section class="panel">
            <div class="panel__header">
                <div><span class="panel__eyebrow">Directorio</span><h2>Usuarios incorporados recientemente</h2></div>
                <a href="{{ route('administration.users.index') }}" class="text-link">Ver directorio <span>→</span></a>
            </div>
            <div class="compact-user-list">
                @foreach ($recentUsers as $user)
                    <article>
                        <span class="avatar avatar--table">{{ str($user->name)->substr(0, 1)->upper() }}</span>
                        <div class="compact-user-list__identity"><strong>{{ $user->name }}</strong><span>{{ $user->email }}</span></div>
                        <div class="compact-user-list__role"><strong>{{ $user->primary_role_name }}</strong><span>{{ $user->area?->name ?? 'Sin área' }}</span></div>
                        <span class="status-badge status-badge--{{ $user->status }}">{{ $user->status === 'active' ? 'Activo' : ($user->status === 'suspended' ? 'Suspendido' : 'Inactivo') }}</span>
                    </article>
                @endforeach
            </div>
        </section>

        <aside class="panel role-overview">
            <div class="panel__header">
                <div><span class="panel__eyebrow">Perfiles</span><h2>Distribución de usuarios</h2></div>
            </div>
            <div class="role-bars">
                @foreach ($roles->take(7) as $role)
                    @php $percentage = $metrics['users'] ? min(100, round(($role->users_count / $metrics['users']) * 100)) : 0; @endphp
                    <div class="role-bar">
                        <div><strong>{{ $role->name }}</strong><span>{{ $role->users_count }}</span></div>
                        <span class="role-bar__track"><i style="width: {{ $percentage }}%"></i></span>
                    </div>
                @endforeach
            </div>
        </aside>
    </div>

    <section class="panel it-structure-panel">
        <div class="panel__header">
            <div><span class="panel__eyebrow">Estructura IT</span><h2>Áreas internas de atención</h2></div>
            <span class="panel-badge">{{ $itAreas->count() }} áreas configuradas</span>
        </div>
        <div class="it-area-grid">
            @foreach ($itAreas as $area)
                <article>
                    <span class="it-area-grid__code">{{ $area->code }}</span>
                    <div><strong>{{ $area->name }}</strong><p>{{ $area->description }}</p></div>
                    <div class="it-area-grid__stats"><span><strong>{{ $area->users_count }}</strong> personas</span><span><strong>{{ $area->technical_groups_count }}</strong> grupos</span></div>
                </article>
            @endforeach
        </div>
    </section>
@endsection
