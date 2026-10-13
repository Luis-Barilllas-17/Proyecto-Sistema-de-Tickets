@extends('layouts.app')

@php $editing = isset($form) && $form; @endphp

@section('title', $editing ? 'Configurar formulario' : 'Nuevo formulario')
@section('section-title', 'Diseñador de formularios')

@section('content')
    <div class="page-heading page-heading--compact">
        <div><a href="{{ route('configuration.forms.index') }}" class="back-link"><span>←</span> Volver a formularios</a><span class="eyebrow">{{ $editing ? 'Edición y versionado' : 'Nuevo diseño' }}</span><h1>{{ $editing ? $form->name : 'Crear formulario' }}</h1><p>Asocia el diseño a una clasificación y controla cómo se capturará cada dato.</p></div>
        @if($editing)<div class="page-heading__meta"><span class="config-code">v{{ $form->version }}</span><div><strong>{{ $form->fields->count() }} campos</strong><span>{{ $form->is_active ? 'Formulario publicado' : 'Formulario inactivo' }}</span></div></div>@endif
    </div>

    <form action="{{ $editing ? route('configuration.forms.update', $form) : route('configuration.forms.store') }}" method="POST" class="panel entity-section configuration-main-form" data-classification-form>
        @csrf @if($editing) @method('PUT') @endif
        <div class="entity-section__header"><span class="section-number">01</span><div><h2>Identidad y asociación</h2><p>Un formulario puede aplicar a toda la categoría o solamente a una subcategoría.</p></div></div>
        <div class="form-grid form-grid--three">
            <div class="field"><label for="form-code">Código <strong>*</strong></label><div class="input-shell"><input id="form-code" name="code" value="{{ old('code', $form?->code) }}" maxlength="40" placeholder="Ej. FRM-SOP" required></div></div>
            <div class="field field--wide"><label for="form-name">Nombre <strong>*</strong></label><div class="input-shell"><input id="form-name" name="name" value="{{ old('name', $form?->name) }}" maxlength="180" required></div></div>
            <div class="field"><label for="form-category">Categoría <strong>*</strong></label><div class="select-shell"><select id="form-category" name="ticket_category_id" data-category-select required><option value="">Seleccionar</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('ticket_category_id', $form?->ticket_category_id) === (string)$category->id)>{{ $category->code }} · {{ $category->name }}</option>@endforeach</select></div></div>
            <div class="field"><label for="form-subcategory">Subcategoría</label><div class="select-shell"><select id="form-subcategory" name="ticket_subcategory_id" data-subcategory-select><option value="">Todas las subcategorías</option>@foreach($categories as $category)@foreach($category->subcategories as $subcategory)<option value="{{ $subcategory->id }}" data-category="{{ $category->id }}" @selected((string)old('ticket_subcategory_id', $form?->ticket_subcategory_id) === (string)$subcategory->id)>{{ $subcategory->name }}</option>@endforeach @endforeach</select></div></div>
            <div class="field field--wide"><label for="form-description">Descripción</label><div class="textarea-shell"><textarea id="form-description" name="description" rows="3" maxlength="2000">{{ old('description', $form?->description) }}</textarea></div></div>
        </div>
        <div class="form-submit-bar"><label class="switch-field switch-field--small"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool)old('is_active', $form?->is_active ?? true))><span></span><div><strong>Formulario publicado</strong><small>Disponible al crear tickets de esta clasificación.</small></div></label><div><a href="{{ route('configuration.forms.index') }}" class="button button--ghost">Cancelar</a><button type="submit" class="button button--primary">{{ $editing ? 'Guardar y crear versión' : 'Crear formulario' }}</button></div></div>
    </form>

    @if($editing)
        <div class="form-builder-layout">
            <section class="panel field-builder-panel">
                <div class="panel__header"><div><span class="panel__eyebrow">Constructor</span><h2>Agregar campo personalizado</h2></div><span class="panel-badge">8 tipos disponibles</span></div>
                <form action="{{ route('configuration.fields.store', $form) }}" method="POST" class="stack-form stack-form--padded">
                    @csrf
                    <div class="form-grid form-grid--three">
                        <div class="field"><label for="field-label">Etiqueta <strong>*</strong></label><div class="input-shell"><input id="field-label" name="label" maxlength="160" placeholder="Ej. Ubicación del equipo" required></div></div>
                        <div class="field"><label for="field-name">Clave técnica <strong>*</strong></label><div class="input-shell"><input id="field-name" name="name" maxlength="80" pattern="[a-z][a-z0-9_]*" placeholder="ubicacion_equipo" required></div><span class="field__hint">Minúsculas, números y guion bajo.</span></div>
                        <div class="field"><label for="field-type">Tipo <strong>*</strong></label><div class="select-shell"><select id="field-type" name="type" required>@foreach($fieldTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div></div>
                        <div class="field"><label for="field-order">Orden <strong>*</strong></label><div class="input-shell"><input id="field-order" name="sort_order" type="number" min="0" max="999" value="{{ max(0, (($form->fields->max('sort_order') ?? 0) + 10)) }}" required></div></div>
                        <div class="field field--wide"><label for="field-placeholder">Texto de ejemplo</label><div class="input-shell"><input id="field-placeholder" name="placeholder" maxlength="255"></div></div>
                        <div class="field field--wide"><label for="field-help">Ayuda para el usuario</label><div class="input-shell"><input id="field-help" name="help_text" maxlength="1000"></div></div>
                        <div class="field field--wide"><label for="field-options">Opciones</label><div class="textarea-shell"><textarea id="field-options" name="options_text" rows="3" placeholder="Una opción por línea. Obligatorio para listas y opciones múltiples."></textarea></div></div>
                        <div class="field"><label for="field-min">Mínimo</label><div class="input-shell"><input id="field-min" name="min_value" type="number" min="0" max="1000000"></div></div>
                        <div class="field"><label for="field-max">Máximo</label><div class="input-shell"><input id="field-max" name="max_value" type="number" min="0" max="1000000"></div></div>
                    </div>
                    <div class="field-policy-row">
                        <label><input type="checkbox" name="is_required" value="1"><span>Obligatorio</span></label>
                        <label><input type="hidden" name="is_visible" value="0"><input type="checkbox" name="is_visible" value="1" checked><span>Visible</span></label>
                        <label><input type="checkbox" name="is_readonly" value="1"><span>Solo lectura</span></label>
                        <label><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked><span>Activo</span></label>
                    </div>
                    <div class="form-actions"><span class="field__hint">Los valores se validan nuevamente en el servidor.</span><button type="submit" class="button button--primary button--small">Agregar campo</button></div>
                </form>
            </section>

            <aside class="panel form-preview-panel">
                <div class="panel__header"><div><span class="panel__eyebrow">Vista previa</span><h2>{{ $form->name }}</h2></div></div>
                <div class="form-preview">
                    @forelse($form->fields->where('is_active', true)->where('is_visible', true) as $field)
                        <div class="preview-field"><label>{{ $field->label }} @if($field->is_required)<strong>*</strong>@endif</label>
                            @if($field->type === 'textarea')<div class="preview-control preview-control--area">{{ $field->placeholder }}</div>
                            @elseif(in_array($field->type, ['select', 'radio']))<div class="preview-options">@foreach($field->options ?? [] as $option)<span><i></i>{{ $option }}</span>@endforeach</div>
                            @elseif($field->type === 'checkbox')<div class="preview-options"><span><i></i>Confirmar</span></div>
                            @else<div class="preview-control">{{ $field->placeholder }}</div>@endif
                            @if($field->help_text)<small>{{ $field->help_text }}</small>@endif
                        </div>
                    @empty
                        <div class="empty-state"><strong>Formulario sin campos</strong><p>Agrega el primer campo desde el constructor.</p></div>
                    @endforelse
                </div>
            </aside>
        </div>

        <section class="panel admin-panel">
            <div class="panel__header"><div><span class="panel__eyebrow">Campos configurados</span><h2>Estructura del formulario</h2></div><span class="panel-badge">{{ $form->fields->count() }} registros</span></div>
            <div class="configured-fields">
                @forelse($form->fields as $field)
                    <details class="configured-field" @if(request('field') == $field->id) open @endif>
                        <summary><span class="drag-order">{{ str_pad($field->sort_order, 2, '0', STR_PAD_LEFT) }}</span><div><strong>{{ $field->label }}</strong><span>{{ $field->name }} · {{ $field->type_label }}</span></div><div class="field-flags">@if($field->is_required)<span>Obligatorio</span>@endif @if(!$field->is_visible)<span>Oculto</span>@endif @if($field->is_readonly)<span>Solo lectura</span>@endif <i class="status-dot {{ $field->is_active ? 'is-active' : '' }}"></i></div></summary>
                        <form action="{{ route('configuration.fields.update', [$form, $field]) }}" method="POST" class="stack-form configured-field__form">
                            @csrf @method('PUT')
                            <div class="form-grid form-grid--three">
                                <div class="field"><label>Etiqueta <strong>*</strong></label><div class="input-shell"><input name="label" value="{{ $field->label }}" maxlength="160" required></div></div>
                                <div class="field"><label>Clave técnica <strong>*</strong></label><div class="input-shell"><input name="name" value="{{ $field->name }}" maxlength="80" pattern="[a-z][a-z0-9_]*" required></div></div>
                                <div class="field"><label>Tipo <strong>*</strong></label><div class="select-shell"><select name="type" required>@foreach($fieldTypes as $value => $label)<option value="{{ $value }}" @selected($field->type === $value)>{{ $label }}</option>@endforeach</select></div></div>
                                <div class="field"><label>Orden</label><div class="input-shell"><input name="sort_order" type="number" min="0" max="999" value="{{ $field->sort_order }}" required></div></div>
                                <div class="field field--wide"><label>Texto de ejemplo</label><div class="input-shell"><input name="placeholder" value="{{ $field->placeholder }}" maxlength="255"></div></div>
                                <div class="field field--wide"><label>Texto de ayuda</label><div class="input-shell"><input name="help_text" value="{{ $field->help_text }}" maxlength="1000"></div></div>
                                <div class="field field--wide"><label>Opciones</label><div class="textarea-shell"><textarea name="options_text" rows="2">{{ implode("\n", $field->options ?? []) }}</textarea></div></div>
                                <div class="field"><label>Mínimo</label><div class="input-shell"><input name="min_value" type="number" min="0" max="1000000" value="{{ $field->min_value }}"></div></div>
                                <div class="field"><label>Máximo</label><div class="input-shell"><input name="max_value" type="number" min="0" max="1000000" value="{{ $field->max_value }}"></div></div>
                            </div>
                            <div class="field-policy-row"><label><input type="checkbox" name="is_required" value="1" @checked($field->is_required)><span>Obligatorio</span></label><label><input type="hidden" name="is_visible" value="0"><input type="checkbox" name="is_visible" value="1" @checked($field->is_visible)><span>Visible</span></label><label><input type="checkbox" name="is_readonly" value="1" @checked($field->is_readonly)><span>Solo lectura</span></label><label><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($field->is_active)><span>Activo</span></label></div>
                            <div class="form-actions"><span></span><button type="submit" class="button button--primary button--small">Guardar campo</button></div>
                        </form>
                    </details>
                @empty
                    <div class="empty-state"><strong>No hay campos</strong><p>Usa el constructor para definir la información solicitada.</p></div>
                @endforelse
            </div>
        </section>
    @endif
@endsection
