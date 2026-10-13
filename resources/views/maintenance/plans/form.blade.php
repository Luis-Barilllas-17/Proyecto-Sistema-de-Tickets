@extends('layouts.app')

@php
    $editing = (bool) $plan;
    $selectedEquipment = collect(old('equipment_ids', $editing ? $plan->assignments->where('is_active',true)->pluck('equipment_id')->all() : []))->map(fn($id)=>(int)$id);
    $checklist = old('checklist_text', $editing ? $plan->checklistItems->pluck('label')->implode("\n") : "Inspeccionar el estado físico del equipo.\nRealizar limpieza preventiva.\nEjecutar diagnóstico técnico.\nValidar funcionamiento y documentar resultados.");
    $firstDue = old('first_due_date', $editing ? $plan->assignments->where('is_active',true)->first()?->next_due_date?->toDateString() : today()->addWeek()->toDateString());
@endphp

@section('title', $editing ? 'Editar plan preventivo' : 'Nuevo plan preventivo')
@section('section-title', 'Mantenimiento preventivo')

@section('content')
    <div class="page-heading page-heading--compact maintenance-form-heading">
        <div><a href="{{ $editing ? route('maintenance.plans.show',$plan) : route('maintenance.index') }}" class="back-link"><span>←</span> Volver</a><span class="eyebrow">RF-062 · RF-064</span><h1>{{ $editing ? 'Editar plan preventivo' : 'Crear plan preventivo' }}</h1><p>Define la frecuencia, los equipos, el responsable y la lista de actividades.</p></div>
    </div>

    <form method="POST" action="{{ $editing ? route('maintenance.plans.update',$plan) : route('maintenance.plans.store') }}" class="maintenance-plan-form">
        @csrf @if($editing) @method('PUT') @endif
        <section class="panel maintenance-form-section">
            <div class="maintenance-form-section__number">01</div>
            <div class="maintenance-form-section__body">
                <header><span>Identificación</span><h2>Datos del programa</h2></header>
                <div class="form-grid form-grid--two">
                    <div class="field"><label for="plan-code">Código <strong>*</strong></label><input id="plan-code" name="code" maxlength="40" value="{{ old('code',$plan?->code) }}" placeholder="PM-PC-TRI" required></div>
                    <div class="field"><label for="plan-name">Nombre <strong>*</strong></label><input id="plan-name" name="name" maxlength="180" value="{{ old('name',$plan?->name) }}" required></div>
                    <div class="field field--wide"><label for="plan-description">Objetivo y alcance <strong>*</strong></label><textarea id="plan-description" name="description" rows="4" required>{{ old('description',$plan?->description) }}</textarea></div>
                </div>
            </div>
        </section>

        <section class="panel maintenance-form-section">
            <div class="maintenance-form-section__number">02</div>
            <div class="maintenance-form-section__body">
                <header><span>Periodicidad</span><h2>Programación y generación</h2></header>
                <div class="form-grid form-grid--four">
                    <div class="field"><label for="plan-frequency">Frecuencia <strong>*</strong></label><select id="plan-frequency" name="frequency_months" required>@foreach(App\Models\MaintenancePlan::FREQUENCIES as $value=>$label)<option value="{{ $value }}" @selected((int)old('frequency_months',$plan?->frequency_months??3)===$value)>{{ $label }}</option>@endforeach</select></div>
                    <div class="field"><label for="plan-first-due-date">Primera fecha <strong>*</strong></label><input id="plan-first-due-date" type="date" name="first_due_date" value="{{ $firstDue }}" required><span class="field__hint">Se aplica a equipos nuevos del plan.</span></div>
                    <div class="field"><label for="plan-lead-days">Anticipación de orden <strong>*</strong></label><input id="plan-lead-days" type="number" name="lead_days" min="0" max="90" value="{{ old('lead_days',$plan?->lead_days??7) }}" required><span class="field__hint">Días antes de la fecha.</span></div>
                    <div class="field"><label for="plan-duration">Duración estimada <strong>*</strong></label><input id="plan-duration" type="number" name="duration_minutes" min="15" max="1440" value="{{ old('duration_minutes',$plan?->duration_minutes??60) }}" required><span class="field__hint">Minutos por equipo.</span></div>
                    <div class="field"><label for="plan-start-time">Hora sugerida</label><input id="plan-start-time" type="time" name="default_start_time" value="{{ old('default_start_time',$plan?->default_start_time ? substr($plan->default_start_time,0,5) : '') }}"></div>
                    <label class="switch-field" for="plan-active"><input type="hidden" name="is_active" value="0"><input id="plan-active" type="checkbox" name="is_active" value="1" @checked((bool)old('is_active',$plan?->is_active??true))><span></span><div><strong>Plan activo</strong><small>Permite generar nuevas órdenes.</small></div></label>
                </div>
            </div>
        </section>

        <section class="panel maintenance-form-section">
            <div class="maintenance-form-section__number">03</div>
            <div class="maintenance-form-section__body">
                <header><span>Responsabilidad</span><h2>Equipo técnico asignado</h2></header>
                <div class="form-grid form-grid--three">
                    <div class="field"><label for="plan-it-area">Área de IT</label><select id="plan-it-area" name="assigned_it_area_id"><option value="">Sin asignación específica</option>@foreach($itAreas as $area)<option value="{{ $area->id }}" @selected(old('assigned_it_area_id',$plan?->assigned_it_area_id)==$area->id)>{{ $area->name }}</option>@endforeach</select></div>
                    <div class="field"><label for="plan-it-group">Grupo técnico</label><select id="plan-it-group" name="assigned_it_group_id"><option value="">Sin grupo específico</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected(old('assigned_it_group_id',$plan?->assigned_it_group_id)==$group->id)>{{ $group->name }}</option>@endforeach</select></div>
                    <div class="field"><label for="plan-technician">Técnico responsable</label><select id="plan-technician" name="assigned_user_id"><option value="">Asignación por grupo</option>@foreach($technicians as $technician)<option value="{{ $technician->id }}" @selected(old('assigned_user_id',$plan?->assigned_user_id)==$technician->id)>{{ $technician->name }}</option>@endforeach</select></div>
                </div>
            </div>
        </section>

        <section class="panel maintenance-form-section">
            <div class="maintenance-form-section__number">04</div>
            <div class="maintenance-form-section__body">
                <header><span>Equipos</span><h2>Activos incluidos</h2><p>Selecciona los activos que seguirán esta programación.</p></header>
                <div class="maintenance-equipment-picker">
                    @foreach($equipment as $asset)
                        <label for="plan-equipment-{{ $asset->id }}"><input id="plan-equipment-{{ $asset->id }}" type="checkbox" name="equipment_ids[]" value="{{ $asset->id }}" @checked($selectedEquipment->contains($asset->id))><span class="maintenance-equipment-picker__check">✓</span><div><strong>{{ $asset->inventory_code }} · {{ $asset->name }}</strong><small>{{ $asset->department?->name ?: $asset->area?->name }} · {{ $asset->location }}</small></div><em>{{ $asset->type_label }}</em></label>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="panel maintenance-form-section">
            <div class="maintenance-form-section__number">05</div>
            <div class="maintenance-form-section__body">
                <header><span>RF-066</span><h2>Lista de verificación</h2><p>Escribe una actividad obligatoria por línea. La orden conservará una copia histórica.</p></header>
                <div class="field"><label for="checklist-text">Actividades <strong>*</strong></label><textarea id="checklist-text" name="checklist_text" rows="9" required>{{ $checklist }}</textarea></div>
            </div>
        </section>

        <div class="form-submit-bar"><span>Los cambios de programación quedan protegidos por permisos administrativos.</span><div><a href="{{ route('maintenance.index') }}" class="button button--ghost">Cancelar</a><button class="button button--primary">{{ $editing ? 'Guardar cambios' : 'Crear plan preventivo' }}</button></div></div>
    </form>
@endsection
