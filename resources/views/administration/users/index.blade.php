@extends('layouts.app')

@section('title', 'Administración de usuarios')
@section('section-title', 'Usuarios institucionales')

@section('content')
    <div class="page-heading">
        <div>
            <span class="eyebrow">Directorio institucional</span>
            <h1>Usuarios institucionales</h1>
            <p>Crea, consulta y actualiza las cuentas, perfiles y ubicaciones dentro de la organización.</p>
        </div>
        @if (auth()->user()->hasPermission('users.create'))
            <a href="{{ route('administration.users.create') }}" class="button button--primary">Nuevo usuario <span>＋</span></a>
        @endif
    </div>

    <section class="people-metrics" aria-label="Resumen del directorio institucional">
        <article class="people-metric people-metric--navy">
            <span class="people-metric__mark" aria-hidden="true">01</span>
            <div><small>Directorio total</small><strong>{{ $metrics['total'] }}</strong><span>Cuentas registradas</span></div>
        </article>
        <article class="people-metric people-metric--teal">
            <span class="people-metric__mark" aria-hidden="true">02</span>
            <div><small>Usuarios activos</small><strong>{{ $metrics['active'] }}</strong><span>Con acceso habilitado</span></div>
        </article>
        <article class="people-metric people-metric--blue">
            <span class="people-metric__mark" aria-hidden="true">03</span>
            <div><small>Personal de IT</small><strong>{{ $metrics['technical'] }}</strong><span>Jefaturas y equipo técnico</span></div>
        </article>
        <article class="people-metric people-metric--orange">
            <span class="people-metric__mark" aria-hidden="true">04</span>
            <div><small>Acceso restringido</small><strong>{{ $metrics['restricted'] }}</strong><span>Suspendidos o inactivos</span></div>
        </article>
    </section>

    <section class="panel directory-panel">
        <header class="directory-panel__header">
            <div><span class="panel__eyebrow">Consulta de cuentas</span><h2>Directorio de usuarios</h2></div>
            <span class="directory-panel__hint">Utiliza los filtros para localizar una cuenta institucional</span>
        </header>
        <form action="{{ route('administration.users.index') }}" method="GET" class="directory-filters">
            <label class="search-control">
                <span class="sr-only">Buscar usuario</span>
                <span aria-hidden="true">⌕</span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Buscar por nombre, correo o código...">
            </label>
            <label>
                <span class="sr-only">Filtrar por estado</span>
                <select name="status">
                    <option value="">Todos los estados</option>
                    <option value="active" @selected(request('status') === 'active')>Activos</option>
                    <option value="suspended" @selected(request('status') === 'suspended')>Suspendidos</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option>
                </select>
            </label>
            <label>
                <span class="sr-only">Filtrar por perfil</span>
                <select name="role">
                    <option value="">Todos los perfiles</option>
                    @foreach ($roles as $role)<option value="{{ $role->id }}" @selected((string) request('role') === (string) $role->id)>{{ $role->name }}</option>@endforeach
                </select>
            </label>
            <label>
                <span class="sr-only">Filtrar por área</span>
                <select name="area">
                    <option value="">Todas las áreas</option>
                    @foreach ($areas as $area)<option value="{{ $area->id }}" @selected((string) request('area') === (string) $area->id)>{{ $area->name }}</option>@endforeach
                </select>
            </label>
            <button class="button button--outline button--small" type="submit">Filtrar</button>
            @if (request()->hasAny(['search', 'status', 'role', 'area']))
                <a href="{{ route('administration.users.index') }}" class="clear-filter">Limpiar</a>
            @endif
        </form>

        <div class="directory-summary">
            <span><strong>{{ $users->total() }}</strong> usuario(s) encontrados</span>
            <span>Directorio actualizado {{ now()->locale('es')->diffForHumans() }}</span>
        </div>

        <div class="table-wrap">
            <table class="data-table users-table">
                <thead><tr><th>Usuario</th><th>Perfil</th><th>Ubicación organizacional</th><th>Estado</th><th>Último acceso</th><th class="align-right">Acciones</th></tr></thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <span class="avatar avatar--table">{{ str($user->name)->substr(0, 1)->upper() }}</span>
                                    <div><strong>{{ $user->name }}</strong><span>{{ $user->email }}</span><small>{{ $user->employee_code ?? 'Sin código institucional' }}{{ $user->job_title ? ' · '.$user->job_title : '' }}</small></div>
                                </div>
                            </td>
                            <td><div class="role-chip-list">@forelse ($user->roles as $role)<span>{{ $role->name }}</span>@empty<span class="role-chip-list__empty">Sin perfil</span>@endforelse</div></td>
                            <td><div class="date-cell"><strong>{{ $user->area?->name ?? 'Sin área asignada' }}</strong><span>{{ $user->department?->name ?? $user->job_title ?? 'Sin departamento' }}</span></div></td>
                            <td><span class="status-badge status-badge--{{ $user->status }}"><i></i>{{ $user->status === 'active' ? 'Activo' : ($user->status === 'suspended' ? 'Suspendido' : 'Inactivo') }}</span></td>
                            <td><div class="date-cell"><strong>{{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Sin accesos' }}</strong><span>{{ $user->last_login_ip ?? '—' }}</span></div></td>
                            <td>
                                <div class="table-actions">
                                    @if (auth()->user()->hasPermission('users.update'))
                                        <a href="{{ route('administration.users.edit', $user) }}" class="button button--outline button--small">Editar perfil</a>
                                    @endif
                                    @if (auth()->user()->hasPermission('users.reset_password'))
                                        <a href="{{ route('security.password.edit', $user) }}" class="table-more-link" title="Administrar el acceso de {{ $user->name }}">Acceso</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty-state"><strong>No se encontraron usuarios</strong><p>Modifica los filtros para ampliar la búsqueda.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())<div class="pagination-wrap">{{ $users->links() }}</div>@endif
    </section>
@endsection
