@extends('layouts.app')

@php
    $editing = isset($group) && $group;
    $selectedMembers = collect(old('member_ids', $editing ? $group->members->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
@endphp

@section('title', $editing ? 'Configurar grupo técnico' : 'Nuevo grupo técnico')
@section('section-title', 'Grupos técnicos')

@section('content')
    <div class="page-heading page-heading--compact"><div><a href="{{ route('administration.groups.index') }}" class="back-link"><span>←</span> Volver a grupos</a><span class="eyebrow">Equipo de atención IT</span><h1>{{ $editing ? 'Configurar '.$group->name : 'Crear grupo técnico' }}</h1><p>Define el área responsable, la coordinación y los integrantes autorizados.</p></div></div>

    <form action="{{ $editing ? route('administration.groups.update', $group) : route('administration.groups.store') }}" method="POST" class="entity-form-layout group-form-layout">
        @csrf @if ($editing) @method('PUT') @endif
        <div class="entity-form-main">
            <section class="panel entity-section">
                <div class="entity-section__header"><span class="section-number">01</span><div><h2>Información del grupo</h2><p>Identificación y ubicación dentro de la estructura de IT.</p></div></div>
                <div class="form-grid form-grid--two">
                    <div class="field"><label for="code">Código <strong>*</strong></label><div class="input-shell"><input id="code" name="code" value="{{ old('code', $group?->code) }}" placeholder="Ej. GRP-DEV" required></div></div>
                    <div class="field"><label for="area_id">Área interna de IT <strong>*</strong></label><div class="select-shell"><select id="area_id" name="area_id" required><option value="">Seleccionar área</option>@foreach ($itAreas as $area)<option value="{{ $area->id }}" @selected((string) old('area_id', $group?->area_id) === (string) $area->id)>{{ $area->name }}</option>@endforeach</select></div></div>
                    <div class="field field--wide"><label for="name">Nombre del grupo <strong>*</strong></label><div class="input-shell"><input id="name" name="name" value="{{ old('name', $group?->name) }}" required></div></div>
                    <div class="field field--wide"><label for="description">Descripción</label><div class="textarea-shell"><textarea id="description" name="description" rows="3">{{ old('description', $group?->description) }}</textarea></div></div>
                    <div class="field field--wide"><label for="coordinator_user_id">Coordinador responsable</label><div class="select-shell"><select id="coordinator_user_id" name="coordinator_user_id"><option value="">Sin coordinador</option>@foreach ($candidates as $candidate)<option value="{{ $candidate->id }}" @selected((string) old('coordinator_user_id', $group?->coordinator_user_id) === (string) $candidate->id)>{{ $candidate->name }} · {{ $candidate->primary_role_name }}</option>@endforeach</select></div></div>
                </div>
            </section>

            <section class="panel entity-section">
                <div class="entity-section__header"><span class="section-number">02</span><div><h2>Integrantes del grupo</h2><p>Asocia técnicos, encargados y programadores según el área de trabajo.</p></div></div>
                <div class="member-selection-grid">
                    @foreach ($candidates as $candidate)
                        <label class="member-selection-card"><input type="checkbox" name="member_ids[]" value="{{ $candidate->id }}" @checked($selectedMembers->contains($candidate->id))><span class="avatar avatar--table">{{ str($candidate->name)->substr(0, 1)->upper() }}</span><span><strong>{{ $candidate->name }}</strong><small>{{ $candidate->primary_role_name }}</small><em>{{ $candidate->specialties->pluck('name')->take(2)->join(' · ') ?: 'Sin especialidad asignada' }}</em></span><i>✓</i></label>
                    @endforeach
                </div>
            </section>
        </div>

        <aside class="entity-form-sidebar">
            <section class="panel form-summary-card form-summary-card--group"><span class="group-summary-mark">IT</span><span class="panel__eyebrow">Grupo de atención</span><h2>{{ $editing ? $group->name : 'Nuevo equipo técnico' }}</h2><p>Los integrantes podrán asociarse posteriormente con categorías y tipos de tickets.</p><div class="form-summary-list"><span><i></i> Coordinación definida</span><span><i></i> Integrantes autorizados</span><span><i></i> Área interna asociada</span></div></section>
            <label class="switch-field panel status-switch-panel"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $group?->is_active ?? true))><span></span><div><strong>Grupo activo</strong><small>Disponible para asignaciones</small></div></label>
            <div class="sticky-form-actions"><a href="{{ route('administration.groups.index') }}" class="button button--ghost">Cancelar</a><button class="button button--primary" type="submit">{{ $editing ? 'Guardar grupo' : 'Crear grupo' }} <span>→</span></button></div>
        </aside>
    </form>
@endsection
