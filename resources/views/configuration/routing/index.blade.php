@extends('layouts.app')

@section('title', 'Reglas de direccionamiento')
@section('section-title', 'Direccionamiento automático')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">Asignación automática</span><h1>Reglas de direccionamiento</h1><p>Define qué área de IT, grupo técnico o responsable recibirá cada tipo de ticket.</p></div>
        <div class="page-heading__meta"><span class="security-pulse"></span><div><strong>{{ $rules->where('is_active', true)->count() }} reglas activas</strong><span>Se evalúan por clasificación y precedencia</span></div></div>
    </div>

    @if(auth()->user()->hasPermission('routing.manage'))
        <section class="panel quick-entity-form routing-form-panel {{ $editingRule ? 'is-editing' : '' }}">
            <div class="quick-entity-form__header"><span class="section-number">R</span><div><span class="panel__eyebrow">{{ $editingRule ? 'Edición' : 'Nuevo registro' }}</span><h2>{{ $editingRule ? 'Editar regla' : 'Registrar regla de direccionamiento' }}</h2></div>@if($editingRule)<a href="{{ route('configuration.routing.index') }}">Cancelar</a>@endif</div>
            <form action="{{ $editingRule ? route('configuration.routing.update', $editingRule) : route('configuration.routing.store') }}" method="POST" class="stack-form stack-form--padded" data-classification-form>
                @csrf @if($editingRule) @method('PUT') @endif
                <div class="form-grid form-grid--three">
                    <div class="field field--wide"><label for="routing-name">Nombre de la regla <strong>*</strong></label><div class="input-shell"><input id="routing-name" name="name" value="{{ old('name', $editingRule?->name) }}" maxlength="180" placeholder="Ej. Incidentes de conectividad" required></div></div>
                    <div class="field"><label for="routing-precedence">Precedencia <strong>*</strong></label><div class="input-shell"><input id="routing-precedence" name="precedence" type="number" min="1" max="999" value="{{ old('precedence', $editingRule?->precedence ?? 100) }}" required></div><span class="field__hint">El número menor se evalúa primero.</span></div>
                    <div class="field"><label for="routing-category">Categoría <strong>*</strong></label><div class="select-shell"><select id="routing-category" name="ticket_category_id" data-category-select required><option value="">Seleccionar</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('ticket_category_id', $editingRule?->ticket_category_id) === (string)$category->id)>{{ $category->name }}</option>@endforeach</select></div></div>
                    <div class="field"><label for="routing-subcategory">Subcategoría</label><div class="select-shell"><select id="routing-subcategory" name="ticket_subcategory_id" data-subcategory-select><option value="">Todas</option>@foreach($categories as $category)@foreach($category->subcategories as $subcategory)<option value="{{ $subcategory->id }}" data-category="{{ $category->id }}" @selected((string)old('ticket_subcategory_id', $editingRule?->ticket_subcategory_id) === (string)$subcategory->id)>{{ $subcategory->name }}</option>@endforeach @endforeach</select></div></div>
                </div>
                <div class="routing-destination"><div><span class="panel__eyebrow">Destino</span><h3>Selecciona uno o varios responsables</h3></div><div class="form-grid form-grid--three">
                    <div class="field"><label for="routing-area">Área interna de IT</label><div class="select-shell"><select id="routing-area" name="it_area_id"><option value="">Sin área específica</option>@foreach($itAreas as $area)<option value="{{ $area->id }}" @selected((string)old('it_area_id', $editingRule?->it_area_id) === (string)$area->id)>{{ $area->name }}</option>@endforeach</select></div></div>
                    <div class="field"><label for="routing-group">Grupo técnico</label><div class="select-shell"><select id="routing-group" name="it_group_id"><option value="">Sin grupo específico</option>@foreach($groups as $group)<option value="{{ $group->id }}" data-area="{{ $group->area_id }}" @selected((string)old('it_group_id', $editingRule?->it_group_id) === (string)$group->id)>{{ $group->name }} · {{ $group->area?->name }}</option>@endforeach</select></div></div>
                    <div class="field"><label for="routing-assignee">Responsable directo</label><div class="select-shell"><select id="routing-assignee" name="assignee_user_id"><option value="">Asignación al grupo</option>@foreach($technicians as $technician)<option value="{{ $technician->id }}" @selected((string)old('assignee_user_id', $editingRule?->assignee_user_id) === (string)$technician->id)>{{ $technician->name }} · {{ $technician->primary_role_name }}</option>@endforeach</select></div></div>
                </div></div>
                <div class="form-submit-bar"><label class="switch-field switch-field--small"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool)old('is_active', $editingRule?->is_active ?? true))><span></span><div><strong>Regla activa</strong><small>Disponible para asignación automática.</small></div></label><div>@if($editingRule)<a href="{{ route('configuration.routing.index') }}" class="button button--ghost">Cancelar</a>@endif<button type="submit" class="button button--primary">{{ $editingRule ? 'Guardar regla' : 'Registrar regla' }}</button></div></div>
            </form>
        </section>
    @endif

    <section class="panel admin-panel">
        <div class="panel__header"><div><span class="panel__eyebrow">Mapa operativo</span><h2>Clasificación y destino de atención</h2></div><div class="legend"><span><i class="legend-dot legend-dot--green"></i> Activa</span><span><i class="legend-dot legend-dot--yellow"></i> Mayor precedencia</span></div></div>
        <div class="table-wrap"><table class="data-table routing-table"><thead><tr><th>Regla</th><th>Clasificación</th><th>Área de IT</th><th>Grupo técnico</th><th>Responsable</th><th>Orden</th><th>Estado</th><th class="align-right">Acción</th></tr></thead><tbody>
            @forelse($rules as $rule)
                <tr><td><div class="date-cell"><strong>{{ $rule->name }}</strong><span>Regla #{{ str_pad($rule->id, 3, '0', STR_PAD_LEFT) }}</span></div></td><td><div class="date-cell"><strong>{{ $rule->category->name }}</strong><span>{{ $rule->subcategory?->name ?? 'Todas las subcategorías' }}</span></div></td><td>{{ $rule->itArea?->name ?? '—' }}</td><td>{{ $rule->group?->name ?? '—' }}</td><td>{{ $rule->assignee?->name ?? 'Asignación al grupo' }}</td><td><span class="count-pill">{{ $rule->precedence }}</span></td><td><span class="status-badge status-badge--{{ $rule->is_active ? 'active' : 'inactive' }}">{{ $rule->is_active ? 'Activa' : 'Inactiva' }}</span></td><td class="align-right">@if(auth()->user()->hasPermission('routing.manage'))<a href="{{ route('configuration.routing.index', ['edit_rule' => $rule->id]) }}" class="button button--outline button--small">Editar</a>@endif</td></tr>
            @empty
                <tr><td colspan="8"><div class="empty-state"><strong>No hay reglas de direccionamiento</strong><p>Los tickets no podrán asignarse automáticamente hasta configurar una.</p></div></td></tr>
            @endforelse
        </tbody></table></div>
    </section>
@endsection
