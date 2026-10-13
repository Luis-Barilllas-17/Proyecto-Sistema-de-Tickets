@extends('layouts.app')

@php $editing = isset($workflow) && $workflow; @endphp

@section('title', $editing ? 'Configurar aprobación' : 'Nueva aprobación')
@section('section-title', 'Flujos de aprobación')

@section('content')
    <div class="page-heading page-heading--compact"><div><a href="{{ route('configuration.approvals.index') }}" class="back-link"><span>←</span> Volver a aprobaciones</a><span class="eyebrow">Flujo multinivel</span><h1>{{ $editing ? $workflow->name : 'Nueva regla de aprobación' }}</h1><p>Define el alcance de la regla y agrega los niveles en el orden exacto de autorización.</p></div></div>

    <form action="{{ $editing ? route('configuration.approvals.update', $workflow) : route('configuration.approvals.store') }}" method="POST" class="panel entity-section configuration-main-form" data-classification-form>
        @csrf @if($editing) @method('PUT') @endif
        <div class="entity-section__header"><span class="section-number">01</span><div><h2>Condiciones de la regla</h2><p>Los campos vacíos significan que la regla aplica a cualquier valor de ese criterio.</p></div></div>
        <div class="form-grid form-grid--three">
            <div class="field"><label for="workflow-code">Código <strong>*</strong></label><div class="input-shell"><input id="workflow-code" name="code" value="{{ old('code', $workflow?->code) }}" maxlength="40" placeholder="Ej. APR-DEV" required></div></div>
            <div class="field field--wide"><label for="workflow-name">Nombre <strong>*</strong></label><div class="input-shell"><input id="workflow-name" name="name" value="{{ old('name', $workflow?->name) }}" maxlength="180" required></div></div>
            <div class="field"><label for="workflow-category">Categoría <strong>*</strong></label><div class="select-shell"><select id="workflow-category" name="ticket_category_id" data-category-select required><option value="">Seleccionar</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('ticket_category_id', $workflow?->ticket_category_id) === (string)$category->id)>{{ $category->name }}</option>@endforeach</select></div></div>
            <div class="field"><label for="workflow-subcategory">Subcategoría</label><div class="select-shell"><select id="workflow-subcategory" name="ticket_subcategory_id" data-subcategory-select><option value="">Todas</option>@foreach($categories as $category)@foreach($category->subcategories as $subcategory)<option value="{{ $subcategory->id }}" data-category="{{ $category->id }}" @selected((string)old('ticket_subcategory_id', $workflow?->ticket_subcategory_id) === (string)$subcategory->id)>{{ $subcategory->name }}</option>@endforeach @endforeach</select></div></div>
            <div class="field"><label for="workflow-area">Área solicitante</label><div class="select-shell"><select id="workflow-area" name="requester_area_id"><option value="">Todas las áreas</option>@foreach($areas as $area)<option value="{{ $area->id }}" @selected((string)old('requester_area_id', $workflow?->requester_area_id) === (string)$area->id)>{{ $area->name }}</option>@endforeach</select></div></div>
            <div class="field"><label for="workflow-priority">Prioridad mínima</label><div class="select-shell"><select id="workflow-priority" name="minimum_priority_id"><option value="">Cualquier prioridad</option>@foreach($priorities as $priority)<option value="{{ $priority->id }}" @selected((string)old('minimum_priority_id', $workflow?->minimum_priority_id) === (string)$priority->id)>{{ $priority->code }} · {{ $priority->name }}</option>@endforeach</select></div></div>
            <div class="field field--wide"><label for="workflow-description">Descripción</label><div class="textarea-shell"><textarea id="workflow-description" name="description" rows="3" maxlength="2000">{{ old('description', $workflow?->description) }}</textarea></div></div>
        </div>
        <div class="form-submit-bar"><label class="switch-field switch-field--small"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool)old('is_active', $workflow?->is_active ?? true))><span></span><div><strong>Regla activa</strong><small>Evaluada al registrar un ticket coincidente.</small></div></label><div><a href="{{ route('configuration.approvals.index') }}" class="button button--ghost">Cancelar</a><button class="button button--primary" type="submit">{{ $editing ? 'Guardar regla' : 'Crear y agregar niveles' }}</button></div></div>
    </form>

    @if($editing)
        <section class="panel approval-level-builder">
            <div class="panel__header"><div><span class="panel__eyebrow">Secuencia</span><h2>Niveles de aprobación</h2></div><span class="panel-badge">{{ $workflow->levels->count() }} niveles</span></div>
            <form action="{{ route('configuration.approval-levels.store', $workflow) }}" method="POST" class="stack-form stack-form--padded" data-approver-form>
                @csrf
                <div class="form-grid form-grid--three">
                    <div class="field"><label for="level-number">Nivel <strong>*</strong></label><div class="input-shell"><input id="level-number" name="level_number" type="number" min="1" max="20" value="{{ ($workflow->levels->max('level_number') ?? 0) + 1 }}" required></div></div>
                    <div class="field field--wide"><label for="level-name">Nombre <strong>*</strong></label><div class="input-shell"><input id="level-name" name="name" maxlength="140" placeholder="Ej. Aprobación de jefatura" required></div></div>
                    <div class="field"><label for="approver-type">Responsable <strong>*</strong></label><div class="select-shell"><select id="approver-type" name="approver_type" data-approver-type required>@foreach($approverTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div></div>
                    <div class="field" data-approver-user><label for="approver-user">Usuario específico</label><div class="select-shell"><select id="approver-user" name="approver_user_id"><option value="">Seleccionar usuario</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div></div>
                    <div class="field" data-approver-role><label for="approver-role">Perfil institucional</label><div class="select-shell"><select id="approver-role" name="approver_role_id"><option value="">Seleccionar perfil</option>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select></div></div>
                </div>
                <div class="field-policy-row"><label><input type="hidden" name="is_required" value="0"><input type="checkbox" name="is_required" value="1" checked><span>Obligatorio</span></label><label><input type="hidden" name="allow_delegation" value="0"><input type="checkbox" name="allow_delegation" value="1" checked><span>Permite suplencia</span></label><label><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked><span>Activo</span></label></div>
                <div class="form-actions"><span></span><button class="button button--primary button--small" type="submit">Agregar nivel</button></div>
            </form>

            <div class="approval-levels">
                @forelse($workflow->levels as $level)
                    <details class="configured-field">
                        <summary><span class="workflow-mini-list__step">{{ $level->level_number }}</span><div><strong>{{ $level->name }}</strong><span>{{ $level->approver_type_label }}@if($level->approver) · {{ $level->approver->name }}@elseif($level->approverRole) · {{ $level->approverRole->name }}@endif</span></div><div class="field-flags">@if($level->is_required)<span>Obligatorio</span>@endif @if($level->allow_delegation)<span>Con suplencia</span>@endif<i class="status-dot {{ $level->is_active ? 'is-active' : '' }}"></i></div></summary>
                        <form action="{{ route('configuration.approval-levels.update', [$workflow, $level]) }}" method="POST" class="stack-form configured-field__form" data-approver-form>
                            @csrf @method('PUT')
                            <div class="form-grid form-grid--three">
                                <div class="field"><label for="approval-level-{{ $level->id }}-number">Nivel <strong>*</strong></label><div class="input-shell"><input id="approval-level-{{ $level->id }}-number" name="level_number" type="number" min="1" max="20" value="{{ $level->level_number }}" required></div></div>
                                <div class="field field--wide"><label for="approval-level-{{ $level->id }}-name">Nombre <strong>*</strong></label><div class="input-shell"><input id="approval-level-{{ $level->id }}-name" name="name" value="{{ $level->name }}" maxlength="140" required></div></div>
                                <div class="field"><label for="approval-level-{{ $level->id }}-type">Responsable <strong>*</strong></label><div class="select-shell"><select id="approval-level-{{ $level->id }}-type" name="approver_type" data-approver-type required>@foreach($approverTypes as $value => $label)<option value="{{ $value }}" @selected($level->approver_type === $value)>{{ $label }}</option>@endforeach</select></div></div>
                                <div class="field" data-approver-user><label for="approval-level-{{ $level->id }}-user">Usuario específico</label><div class="select-shell"><select id="approval-level-{{ $level->id }}-user" name="approver_user_id"><option value="">Seleccionar</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected($level->approver_user_id === $user->id)>{{ $user->name }}</option>@endforeach</select></div></div>
                                <div class="field" data-approver-role><label for="approval-level-{{ $level->id }}-role">Perfil institucional</label><div class="select-shell"><select id="approval-level-{{ $level->id }}-role" name="approver_role_id"><option value="">Seleccionar</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected($level->approver_role_id === $role->id)>{{ $role->name }}</option>@endforeach</select></div></div>
                            </div>
                            <div class="field-policy-row"><label><input type="hidden" name="is_required" value="0"><input type="checkbox" name="is_required" value="1" @checked($level->is_required)><span>Obligatorio</span></label><label><input type="hidden" name="allow_delegation" value="0"><input type="checkbox" name="allow_delegation" value="1" @checked($level->allow_delegation)><span>Permite suplencia</span></label><label><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($level->is_active)><span>Activo</span></label></div>
                            <div class="form-actions"><span></span><button class="button button--primary button--small" type="submit">Guardar nivel</button></div>
                        </form>
                    </details>
                @empty
                    <div class="empty-state"><strong>La regla aún no tiene niveles</strong><p>Agrega el primer responsable para completar el flujo.</p></div>
                @endforelse
            </div>
        </section>
    @endif
@endsection
