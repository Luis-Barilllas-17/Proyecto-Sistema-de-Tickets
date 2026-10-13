@extends('layouts.app')

@php
    $editing = isset($role) && $role;
    $selectedPermissions = collect(old('permission_ids', $editing ? $role->permissions->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
@endphp

@section('title', $editing ? 'Configurar rol' : 'Nuevo rol')
@section('section-title', 'Roles y permisos')

@section('content')
    <div class="page-heading page-heading--compact"><div><a href="{{ route('administration.roles.index') }}" class="back-link"><span>←</span> Volver a roles</a><span class="eyebrow">Matriz de autorización</span><h1>{{ $editing ? 'Configurar '.$role->name : 'Crear rol' }}</h1><p>Define el alcance del perfil por sección y acción.</p></div></div>

    <form action="{{ $editing ? route('administration.roles.update', $role) : route('administration.roles.store') }}" method="POST" class="permission-form">
        @csrf
        @if ($editing) @method('PUT') @endif

        <section class="panel role-identity-panel">
            <div class="form-grid form-grid--role">
                <div class="field"><label for="name">Nombre del rol <strong>*</strong></label><div class="input-shell"><input id="name" name="name" value="{{ old('name', $role?->name) }}" required maxlength="120"></div></div>
                <div class="field field--description"><label for="description">Descripción</label><div class="textarea-shell"><textarea id="description" name="description" rows="2" maxlength="1000">{{ old('description', $role?->description) }}</textarea></div></div>
                <label class="switch-field"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $role?->is_active ?? true))><span></span><div><strong>Rol activo</strong><small>Disponible para asignación</small></div></label>
            </div>
        </section>

        <div class="permission-heading"><div><span class="eyebrow">Permisos del perfil</span><h2>Acceso por sección y acción</h2></div><span>{{ $permissionGroups->flatten()->count() }} permisos disponibles</span></div>

        <div class="permission-module-list">
            @foreach ($permissionGroups as $module => $permissions)
                <section class="panel permission-module">
                    <div class="permission-module__header"><div><span class="permission-module__code">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><div><h3>{{ $module }}</h3><p>{{ $permissions->count() }} acciones disponibles</p></div></div><button type="button" data-check-group="permission-group-{{ $loop->iteration }}">Seleccionar todo</button></div>
                    <div class="permission-options" id="permission-group-{{ $loop->iteration }}">
                        @foreach ($permissions as $permission)
                            <label><input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked($selectedPermissions->contains($permission->id))><span class="permission-options__check">✓</span><span><strong>{{ $permission->action }}</strong><small>{{ $permission->name }}</small></span></label>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <div class="form-submit-bar"><div><span class="security-pulse"></span><p><strong>Autorización del lado del servidor</strong><small>Los cambios se aplican en el próximo acceso del usuario.</small></p></div><div><a href="{{ route('administration.roles.index') }}" class="button button--ghost">Cancelar</a><button type="submit" class="button button--primary">{{ $editing ? 'Guardar configuración' : 'Crear rol' }} <span>→</span></button></div></div>
    </form>
@endsection
