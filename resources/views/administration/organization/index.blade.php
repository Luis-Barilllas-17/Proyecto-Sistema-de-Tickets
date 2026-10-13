@extends('layouts.app')

@section('title', 'Estructura organizacional')
@section('section-title', 'Estructura organizacional')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">Estructura institucional</span><h1>Áreas y departamentos</h1><p>Registra la estructura institucional, sus jefaturas y las subdivisiones internas del Departamento de IT.</p></div>
        <div class="page-heading__meta"><span class="security-pulse"></span><div><strong>{{ $areas->where('is_active', true)->count() }} áreas activas</strong><span>{{ $departments->where('is_active', true)->count() }} departamentos configurados</span></div></div>
    </div>

    @if (auth()->user()->hasPermission($editingArea ? 'organization.update' : 'organization.create') || auth()->user()->hasPermission($editingDepartment ? 'organization.update' : 'organization.create'))
        <div class="organization-forms">
            <section class="panel quick-entity-form {{ $editingArea ? 'is-editing' : '' }}">
                <div class="quick-entity-form__header"><span class="section-number">A</span><div><span class="panel__eyebrow">{{ $editingArea ? 'Edición' : 'Nuevo registro' }}</span><h2>{{ $editingArea ? 'Editar área' : 'Registrar área' }}</h2></div>@if ($editingArea)<a href="{{ route('administration.organization.index') }}">Cancelar</a>@endif</div>
                <form action="{{ $editingArea ? route('administration.areas.update', $editingArea) : route('administration.areas.store') }}" method="POST" class="stack-form stack-form--compact">
                    @csrf @if ($editingArea) @method('PUT') @endif
                    <div class="form-grid form-grid--two">
                        <div class="field"><label for="area-code">Código <strong>*</strong></label><div class="input-shell"><input id="area-code" name="code" value="{{ old('code', $editingArea?->code) }}" placeholder="Ej. IT-INF" required></div></div>
                        <div class="field"><label for="area-type">Tipo <strong>*</strong></label><div class="select-shell"><select id="area-type" name="type" required><option value="organizational" @selected(old('type', $editingArea?->type) === 'organizational')>Organizacional</option><option value="it" @selected(old('type', $editingArea?->type) === 'it')>Área interna de IT</option></select></div></div>
                        <div class="field field--wide"><label for="area-name">Nombre del área <strong>*</strong></label><div class="input-shell"><input id="area-name" name="name" value="{{ old('name', $editingArea?->name) }}" required></div></div>
                        <div class="field"><label for="area-parent">Área superior</label><div class="select-shell"><select id="area-parent" name="parent_id"><option value="">Sin área superior</option>@foreach ($parentAreas as $parent)@if (! $editingArea || $parent->id !== $editingArea->id)<option value="{{ $parent->id }}" @selected((string) old('parent_id', $editingArea?->parent_id) === (string) $parent->id)>{{ $parent->name }}</option>@endif @endforeach</select></div></div>
                        <div class="field"><label for="area-head">Jefatura responsable</label><div class="select-shell"><select id="area-head" name="head_user_id"><option value="">Sin jefatura asignada</option>@foreach ($heads as $head)<option value="{{ $head->id }}" @selected((string) old('head_user_id', $editingArea?->head_user_id) === (string) $head->id)>{{ $head->name }}</option>@endforeach</select></div></div>
                        <div class="field field--wide"><label for="area-description">Descripción</label><div class="textarea-shell"><textarea id="area-description" name="description" rows="2">{{ old('description', $editingArea?->description) }}</textarea></div></div>
                    </div>
                    <div class="quick-form-footer"><label class="switch-field switch-field--small"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $editingArea?->is_active ?? true))><span></span><div><strong>Área activa</strong></div></label><button class="button button--primary button--small" type="submit">{{ $editingArea ? 'Guardar área' : 'Registrar área' }}</button></div>
                </form>
            </section>

            <section class="panel quick-entity-form {{ $editingDepartment ? 'is-editing' : '' }}">
                <div class="quick-entity-form__header"><span class="section-number">D</span><div><span class="panel__eyebrow">{{ $editingDepartment ? 'Edición' : 'Nuevo registro' }}</span><h2>{{ $editingDepartment ? 'Editar departamento' : 'Registrar departamento' }}</h2></div>@if ($editingDepartment)<a href="{{ route('administration.organization.index') }}">Cancelar</a>@endif</div>
                <form action="{{ $editingDepartment ? route('administration.departments.update', $editingDepartment) : route('administration.departments.store') }}" method="POST" class="stack-form stack-form--compact">
                    @csrf @if ($editingDepartment) @method('PUT') @endif
                    <div class="form-grid form-grid--two">
                        <div class="field"><label for="department-code">Código <strong>*</strong></label><div class="input-shell"><input id="department-code" name="code" value="{{ old('code', $editingDepartment?->code) }}" placeholder="Ej. INF-RED" required></div></div>
                        <div class="field"><label for="department-area">Área <strong>*</strong></label><div class="select-shell"><select id="department-area" name="area_id" required><option value="">Seleccionar área</option>@foreach ($parentAreas as $area)<option value="{{ $area->id }}" @selected((string) old('area_id', $editingDepartment?->area_id) === (string) $area->id)>{{ $area->name }}</option>@endforeach</select></div></div>
                        <div class="field field--wide"><label for="department-name">Nombre del departamento <strong>*</strong></label><div class="input-shell"><input id="department-name" name="name" value="{{ old('name', $editingDepartment?->name) }}" required></div></div>
                        <div class="field field--wide"><label for="department-head">Jefatura responsable</label><div class="select-shell"><select id="department-head" name="head_user_id"><option value="">Sin jefatura asignada</option>@foreach ($heads as $head)<option value="{{ $head->id }}" @selected((string) old('head_user_id', $editingDepartment?->head_user_id) === (string) $head->id)>{{ $head->name }}</option>@endforeach</select></div></div>
                        <div class="field field--wide"><label for="department-description">Descripción</label><div class="textarea-shell"><textarea id="department-description" name="description" rows="2">{{ old('description', $editingDepartment?->description) }}</textarea></div></div>
                    </div>
                    <div class="quick-form-footer"><label class="switch-field switch-field--small"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $editingDepartment?->is_active ?? true))><span></span><div><strong>Departamento activo</strong></div></label><button class="button button--primary button--small" type="submit">{{ $editingDepartment ? 'Guardar departamento' : 'Registrar departamento' }}</button></div>
                </form>
            </section>
        </div>
    @endif

    <section class="panel admin-panel">
        <div class="panel__header"><div><span class="panel__eyebrow">Directorio organizacional</span><h2>Áreas y subdivisiones de IT</h2></div><div class="legend"><span><i class="legend-dot legend-dot--green"></i> Activa</span><span><i class="legend-dot legend-dot--yellow"></i> Área IT</span></div></div>
        <div class="table-wrap"><table class="data-table organization-table"><thead><tr><th>Área</th><th>Tipo</th><th>Dependencia</th><th>Jefatura</th><th>Estructura</th><th>Estado</th><th class="align-right">Acción</th></tr></thead><tbody>
            @foreach ($areas as $area)
                <tr><td><div class="code-name-cell"><span>{{ $area->code }}</span><div><strong>{{ $area->name }}</strong><small>{{ str($area->description)->limit(65) }}</small></div></div></td><td><span class="type-badge type-badge--{{ $area->type }}">{{ $area->type === 'it' ? 'Interna de IT' : 'Organizacional' }}</span></td><td>{{ $area->parent?->name ?? 'Nivel principal' }}</td><td><div class="date-cell"><strong>{{ $area->head?->name ?? 'Sin asignar' }}</strong><span>{{ $area->head?->job_title ?? 'Jefatura pendiente' }}</span></div></td><td><div class="structure-counts"><span><strong>{{ $area->departments_count }}</strong> deptos.</span><span><strong>{{ $area->users_count }}</strong> usuarios</span></div></td><td><span class="status-badge status-badge--{{ $area->is_active ? 'active' : 'inactive' }}">{{ $area->is_active ? 'Activa' : 'Inactiva' }}</span></td><td class="align-right">@if (auth()->user()->hasPermission('organization.update'))<a href="{{ route('administration.organization.index', ['edit_area' => $area->id]) }}" class="button button--outline button--small">Editar</a>@endif</td></tr>
            @endforeach
        </tbody></table></div>
    </section>

    <section class="panel admin-panel">
        <div class="panel__header"><div><span class="panel__eyebrow">Departamentos y jefaturas</span><h2>Unidades registradas</h2></div><span class="panel-badge">{{ $departments->count() }} registros</span></div>
        <div class="table-wrap"><table class="data-table organization-table"><thead><tr><th>Departamento</th><th>Área</th><th>Jefatura</th><th>Usuarios</th><th>Estado</th><th class="align-right">Acción</th></tr></thead><tbody>
            @foreach ($departments as $department)
                <tr><td><div class="code-name-cell"><span>{{ $department->code }}</span><div><strong>{{ $department->name }}</strong><small>{{ str($department->description)->limit(65) }}</small></div></div></td><td>{{ $department->area->name }}</td><td><div class="date-cell"><strong>{{ $department->head?->name ?? 'Sin asignar' }}</strong><span>{{ $department->head?->email ?? 'Jefatura pendiente' }}</span></div></td><td><span class="count-pill">{{ $department->users_count }}</span></td><td><span class="status-badge status-badge--{{ $department->is_active ? 'active' : 'inactive' }}">{{ $department->is_active ? 'Activo' : 'Inactivo' }}</span></td><td class="align-right">@if (auth()->user()->hasPermission('organization.update'))<a href="{{ route('administration.organization.index', ['edit_department' => $department->id]) }}" class="button button--outline button--small">Editar</a>@endif</td></tr>
            @endforeach
        </tbody></table></div>
    </section>
@endsection
