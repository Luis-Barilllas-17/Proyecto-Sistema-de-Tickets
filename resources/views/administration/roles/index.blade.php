@extends('layouts.app')

@section('title', 'Roles y permisos')
@section('section-title', 'Roles y permisos')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">Perfiles y autorización</span><h1>Roles y permisos</h1><p>Controla qué secciones, acciones y datos puede consultar o modificar cada perfil.</p></div>
        @if (auth()->user()->hasPermission('roles.create'))<a href="{{ route('administration.roles.create') }}" class="button button--primary">Nuevo rol <span>＋</span></a>@endif
    </div>

    <section class="roles-overview">
        <div class="roles-overview__metric"><span>Perfiles configurados</span><strong>{{ $roles->count() }}</strong><small>{{ $roles->where('is_active', true)->count() }} activos</small></div>
        <div class="roles-overview__metric"><span>Permisos disponibles</span><strong>{{ $permissionCount }}</strong><small>Organizados por acción</small></div>
        <div class="roles-overview__message"><span class="policy-check">✓</span><div><strong>Principio de mínimo privilegio</strong><p>Asigna únicamente los permisos necesarios para cada responsabilidad.</p></div></div>
    </section>

    <div class="role-card-grid">
        @foreach ($roles as $role)
            <article class="role-card {{ ! $role->is_active ? 'is-muted' : '' }}">
                <div class="role-card__top"><span class="role-card__mark">{{ str($role->name)->substr(0, 2)->upper() }}</span><div class="role-card__badges">@if ($role->is_system)<span>Sistema</span>@endif<span class="{{ $role->is_active ? 'is-active' : '' }}">{{ $role->is_active ? 'Activo' : 'Inactivo' }}</span></div></div>
                <h2>{{ $role->name }}</h2>
                <p>{{ $role->description ?: 'Perfil personalizado de acceso institucional.' }}</p>
                <div class="role-card__stats"><span><strong>{{ $role->users_count }}</strong> usuarios</span><span><strong>{{ $role->permissions_count }}</strong> permisos</span></div>
                <div class="role-card__footer">
                    <span>{{ $role->is_system ? 'Perfil inicial' : 'Perfil personalizado' }}</span>
                    @if (auth()->user()->hasPermission('roles.update'))<a href="{{ route('administration.roles.edit', $role) }}">Configurar <span>→</span></a>@endif
                </div>
            </article>
        @endforeach
    </div>
@endsection
