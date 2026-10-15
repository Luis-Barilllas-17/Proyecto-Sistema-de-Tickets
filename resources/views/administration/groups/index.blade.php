@extends('layouts.app')

@section('title', 'Grupos técnicos')
@section('section-title', 'Grupos técnicos y especialidades')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">Equipos de atención IT</span><h1>Grupos técnicos</h1><p>Organiza los equipos de atención de IT, sus coordinadores, integrantes y especialidades.</p></div>
        @if (auth()->user()->hasPermission('groups.create'))<a href="{{ route('administration.groups.create') }}" class="button button--primary">Nuevo grupo <span>＋</span></a>@endif
    </div>

    <section class="people-metrics people-metrics--technical" aria-label="Resumen de la estructura técnica">
        <article class="people-metric people-metric--navy"><span class="people-metric__mark" aria-hidden="true">01</span><div><small>Grupos activos</small><strong>{{ $metrics['active_groups'] }}</strong><span>Equipos disponibles</span></div></article>
        <article class="people-metric people-metric--teal"><span class="people-metric__mark" aria-hidden="true">02</span><div><small>Personal técnico</small><strong>{{ $metrics['members'] }}</strong><span>Integrantes únicos</span></div></article>
        <article class="people-metric people-metric--blue"><span class="people-metric__mark" aria-hidden="true">03</span><div><small>Áreas de IT</small><strong>{{ $metrics['areas'] }}</strong><span>Con grupos asignados</span></div></article>
        <article class="people-metric people-metric--orange"><span class="people-metric__mark" aria-hidden="true">04</span><div><small>Especialidades</small><strong>{{ $metrics['specialties'] }}</strong><span>Competencias activas</span></div></article>
    </section>

    <section class="technical-groups-section" aria-labelledby="technical-groups-title">
        <header class="section-heading section-heading--formal"><div><span class="panel__eyebrow">Estructura de atención</span><h2 id="technical-groups-title">Equipos configurados</h2><p>Coordinación, capacidad y perfiles responsables de cada grupo.</p></div><span class="panel-badge">{{ $groups->count() }} grupos</span></header>
        <div class="technical-group-grid">
        @forelse ($groups as $group)
            <article class="technical-group-card {{ ! $group->is_active ? 'is-muted' : '' }}">
                <div class="technical-group-card__top"><span class="technical-group-card__code">{{ $group->code }}</span><span class="status-badge status-badge--{{ $group->is_active ? 'active' : 'inactive' }}">{{ $group->is_active ? 'Activo' : 'Inactivo' }}</span></div>
                <div class="technical-group-card__identity"><span class="technical-group-card__area">{{ $group->area?->name ?? 'Sin área de IT' }}</span><h3>{{ $group->name }}</h3><p>{{ $group->description ?: 'Grupo técnico sin descripción registrada.' }}</p></div>
                <div class="coordinator-card"><span class="avatar avatar--small">{{ str($group->coordinator?->name ?? 'S')->substr(0, 1)->upper() }}</span><div><span>Coordinación</span><strong>{{ $group->coordinator?->name ?? 'Sin asignar' }}</strong></div></div>
                <div class="member-preview"><div><span class="member-preview__label">Integrantes</span><div class="avatar-stack">@foreach ($group->members->take(4) as $member)<span title="{{ $member->name }}">{{ str($member->name)->substr(0, 1)->upper() }}</span>@endforeach @if ($group->members_count > 4)<span>+{{ $group->members_count - 4 }}</span>@endif</div></div><strong>{{ $group->members_count }} <small>{{ $group->members_count === 1 ? 'persona' : 'personas' }}</small></strong></div>
                <div class="technical-group-card__footer"><span>{{ $group->members->pluck('primary_role_name')->unique()->join(' · ') ?: 'Sin perfiles' }}</span>@if (auth()->user()->hasPermission('groups.update'))<a href="{{ route('administration.groups.edit', $group) }}">Configurar <span>→</span></a>@endif</div>
            </article>
        @empty
            <div class="empty-state technical-group-empty"><strong>No hay grupos técnicos registrados</strong><p>Crea el primer grupo para organizar responsables y asignaciones de IT.</p></div>
        @endforelse
        </div>
    </section>

    <section class="panel specialties-panel" id="especialidades">
        <div class="panel__header specialties-panel__header"><div><span class="panel__eyebrow">Conocimiento técnico</span><h2>Especialidades registradas</h2><p>Catálogo de competencias disponibles para técnicos y programadores.</p></div><span class="panel-badge">{{ $specialties->count() }} especialidades</span></div>
        <div class="specialties-layout">
            <div class="specialty-list">
                @forelse ($specialties as $specialty)
                    <article><span class="specialty-type specialty-type--{{ $specialty->type }}">{{ $specialty->type === 'programming' ? 'Desarrollo' : 'Técnica' }}</span><div><strong>{{ $specialty->name }}</strong><p>{{ $specialty->description }}</p></div><span class="specialty-users"><strong>{{ $specialty->users_count }}</strong> personas</span>@if (auth()->user()->hasPermission('specialties.manage'))<a href="{{ route('administration.groups.index', ['edit_specialty' => $specialty->id]) }}#especialidades">Editar</a>@endif</article>
                @empty
                    <div class="empty-state"><strong>Sin especialidades registradas</strong><p>Agrega competencias para completar el perfil del personal técnico.</p></div>
                @endforelse
            </div>

            @if (auth()->user()->hasPermission('specialties.manage'))
                <aside class="specialty-form-card">
                    <div><span class="panel__eyebrow">{{ $editingSpecialty ? 'Editar registro' : 'Nueva especialidad' }}</span><h3>{{ $editingSpecialty ? $editingSpecialty->name : 'Registrar especialidad' }}</h3><p>Disponible para asignar en el perfil de técnicos y programadores.</p></div>
                    <form action="{{ $editingSpecialty ? route('administration.specialties.update', $editingSpecialty) : route('administration.specialties.store') }}" method="POST" class="stack-form stack-form--compact">
                        @csrf @if ($editingSpecialty) @method('PUT') @endif
                        <div class="field"><label for="specialty-name">Nombre <strong>*</strong></label><div class="input-shell"><input id="specialty-name" name="name" value="{{ old('name', $editingSpecialty?->name) }}" required></div></div>
                        <div class="field"><label for="specialty-type">Tipo <strong>*</strong></label><div class="select-shell"><select id="specialty-type" name="type" required><option value="technical" @selected(old('type', $editingSpecialty?->type) === 'technical')>Técnica</option><option value="programming" @selected(old('type', $editingSpecialty?->type) === 'programming')>Desarrollo</option></select></div></div>
                        <div class="field"><label for="specialty-description">Descripción</label><div class="textarea-shell"><textarea id="specialty-description" name="description" rows="3">{{ old('description', $editingSpecialty?->description) }}</textarea></div></div>
                        <label class="switch-field switch-field--small"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $editingSpecialty?->is_active ?? true))><span></span><div><strong>Especialidad activa</strong></div></label>
                        <div class="specialty-form-card__actions">@if ($editingSpecialty)<a href="{{ route('administration.groups.index') }}#especialidades" class="button button--ghost button--small">Cancelar</a>@endif<button class="button button--primary button--small" type="submit">{{ $editingSpecialty ? 'Guardar' : 'Registrar' }}</button></div>
                    </form>
                </aside>
            @endif
        </div>
    </section>
@endsection
