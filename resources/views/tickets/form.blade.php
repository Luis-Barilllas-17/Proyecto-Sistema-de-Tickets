@extends('layouts.app')

@php($editing = $ticket !== null)
@section('title', $editing ? 'Editar '.$ticket->number : 'Nuevo ticket')
@section('section-title', $editing ? 'Editar borrador' : 'Registrar ticket')

@section('content')
    <header class="ticket-page-heading">
        <div>
            <a href="{{ $editing ? route('tickets.show', $ticket) : route('tickets.index') }}" class="back-link">← Volver a tickets</a>
            <span class="eyebrow">{{ $editing ? $ticket->number : 'Nueva solicitud' }}</span>
            <h1>{{ $editing ? 'Completar y enviar ticket' : 'Crear una nueva solicitud' }}</h1>
            <p>La clasificación determina el formulario, la prioridad, la aprobación y el equipo que atenderá el caso.</p>
        </div>
        <div class="secure-form-note"><span>✓</span><div><strong>Registro protegido</strong><small>Validación, adjuntos privados y trazabilidad activa</small></div></div>
    </header>

    @if (! $selectedCategory)
        <section class="ticket-create-shell">
            <aside class="ticket-stepper">
                <span class="is-active"><b>1</b><i></i><strong>Clasificación</strong><small>Tipo de solicitud</small></span>
                <span><b>2</b><i></i><strong>Información</strong><small>Datos del caso</small></span>
                <span><b>3</b><strong>Confirmación</strong><small>Guardar o enviar</small></span>
            </aside>
            <form method="GET" action="{{ route('tickets.create') }}" class="panel ticket-classification-card" data-classification-form>
                <div class="ticket-form-section-heading"><span>01</span><div><h2>¿Qué necesitas reportar?</h2><p>Selecciona la categoría y subcategoría que mejor describen el requerimiento.</p></div></div>
                <div class="ticket-category-grid">
                    @foreach ($categories as $category)
                        <label class="ticket-category-option">
                            <input type="radio" name="category_id" value="{{ $category->id }}" required data-category-radio>
                            <span><b>{{ $category->code }}</b><strong>{{ $category->name }}</strong><small>{{ $category->description }}</small></span>
                        </label>
                    @endforeach
                </div>
                <div class="field">
                    <label for="ticket_subcategory_id">Subcategoría <strong>*</strong></label>
                    <select id="ticket_subcategory_id" name="subcategory_id" required data-subcategory-select>
                        <option value="">Selecciona primero una categoría</option>
                        @foreach ($categories as $category) @foreach ($category->subcategories as $subcategory)
                            <option value="{{ $subcategory->id }}" data-category="{{ $category->id }}" hidden disabled>{{ $subcategory->name }}</option>
                        @endforeach @endforeach
                    </select>
                </div>
                <div class="ticket-form-actions"><a href="{{ route('tickets.index') }}" class="button button--ghost">Cancelar</a><button type="submit" class="button button--primary">Continuar <span>→</span></button></div>
            </form>
        </section>
    @else
        <form method="POST" action="{{ $editing ? route('tickets.update', $ticket) : route('tickets.store') }}" enctype="multipart/form-data" class="ticket-editor-form">
            @csrf
            @if ($editing) @method('PUT') <input type="hidden" name="lock_version" value="{{ $ticket->lock_version }}"> @endif
            <input type="hidden" name="ticket_category_id" value="{{ $selectedCategory->id }}">
            <input type="hidden" name="ticket_subcategory_id" value="{{ $selectedSubcategory?->id }}">

            <aside class="ticket-stepper ticket-stepper--vertical">
                <span class="is-done"><b>✓</b><i></i><strong>Clasificación</strong><small>{{ $selectedCategory->name }}</small></span>
                <span class="is-active"><b>2</b><i></i><strong>Información</strong><small>Datos del caso</small></span>
                <span><b>3</b><strong>Confirmación</strong><small>Guardar o enviar</small></span>
            </aside>

            <div class="ticket-editor-content">
                <section class="panel ticket-form-card">
                    <div class="ticket-form-section-heading"><span>01</span><div><h2>Clasificación seleccionada</h2><p>{{ $selectedCategory->name }} · {{ $selectedSubcategory?->name }}</p></div>@unless ($editing)<a href="{{ route('tickets.create') }}" class="text-link">Cambiar</a>@endunless</div>
                    @if (auth()->user()->hasPermission('tickets.create_internal'))
                        <div class="ticket-internal-switch">
                            <label><input type="checkbox" name="is_internal" value="1" @checked(old('is_internal', $ticket?->source === 'it_internal'))><span></span><div><strong>Ticket interno de IT</strong><small>Registra una actividad, incidente detectado o trabajo técnico interno.</small></div></label>
                            <div class="field"><label for="requester_id">Registrar a nombre de</label><select id="requester_id" name="requester_id"><option value="">Mi usuario</option>@foreach ($requesters as $requester)<option value="{{ $requester->id }}" @selected(old('requester_id', $ticket?->requester_id) == $requester->id)>{{ $requester->name }} · {{ $requester->email }}</option>@endforeach</select></div>
                        </div>
                    @endif
                </section>

                <section class="panel ticket-form-card">
                    <div class="ticket-form-section-heading"><span>02</span><div><h2>Información del requerimiento</h2><p>Describe el caso de forma precisa para facilitar su atención.</p></div></div>
                    <div class="form-grid form-grid--two">
                        <div class="field field--wide"><label for="title">Asunto <strong>*</strong></label><input id="title" name="title" maxlength="200" value="{{ old('title', $ticket?->title) }}" placeholder="Resumen breve y específico del caso"></div>
                        <div class="field field--wide"><label for="description">Descripción detallada <strong>*</strong></label><textarea id="description" name="description" rows="6" maxlength="15000" placeholder="Indica qué sucede, desde cuándo y cómo afecta tu trabajo">{{ old('description', $ticket?->description) }}</textarea><span class="field__hint">No incluyas contraseñas, llaves privadas ni información innecesariamente sensible.</span></div>
                        <div class="field"><label for="requester_area_id">Área solicitante <strong>*</strong></label><select id="requester_area_id" name="requester_area_id" data-area-select><option value="">Seleccionar</option>@foreach ($areas as $area)<option value="{{ $area->id }}" @selected(old('requester_area_id', $ticket?->requester_area_id ?: auth()->user()->area_id) == $area->id)>{{ $area->name }}</option>@endforeach</select></div>
                        <div class="field"><label for="requester_department_id">Departamento</label><select id="requester_department_id" name="requester_department_id" data-department-select><option value="">Sin departamento</option>@foreach ($areas as $area) @foreach ($area->departments as $department)<option value="{{ $department->id }}" data-area="{{ $area->id }}" @selected(old('requester_department_id', $ticket?->requester_department_id ?: auth()->user()->department_id) == $department->id)>{{ $department->name }}</option>@endforeach @endforeach</select></div>
                        <div class="field"><label for="location">Ubicación <strong>*</strong></label><input id="location" name="location" maxlength="255" value="{{ old('location', $ticket?->location) }}" placeholder="Edificio, oficina o punto de atención"></div>
                        <div class="field"><label for="attention_type">Tipo de atención <strong>*</strong></label><select id="attention_type" name="attention_type"><option value="">Seleccionar</option>@foreach ($attentionTypes as $value => $label)<option value="{{ $value }}" @selected(old('attention_type', $ticket?->attention_type) === $value)>{{ $label }}</option>@endforeach</select></div>
                    </div>
                </section>

                <section class="panel ticket-form-card">
                    <div class="ticket-form-section-heading"><span>03</span><div><h2>Impacto y urgencia</h2><p>El sistema calculará la prioridad con la matriz institucional configurada.</p></div></div>
                    <div class="form-grid form-grid--two">
                        <div class="field"><label for="impact_level_id">Impacto <strong>*</strong></label><select id="impact_level_id" name="impact_level_id"><option value="">Seleccionar</option>@foreach ($impacts as $impact)<option value="{{ $impact->id }}" @selected(old('impact_level_id', $ticket?->impact_level_id) == $impact->id)>{{ $impact->name }} · {{ $impact->description }}</option>@endforeach</select></div>
                        <div class="field"><label for="urgency_level_id">Urgencia <strong>*</strong></label><select id="urgency_level_id" name="urgency_level_id"><option value="">Seleccionar</option>@foreach ($urgencies as $urgency)<option value="{{ $urgency->id }}" @selected(old('urgency_level_id', $ticket?->urgency_level_id) == $urgency->id)>{{ $urgency->name }} · {{ $urgency->description }}</option>@endforeach</select></div>
                    </div>
                </section>

                @if ($form)
                    <section class="panel ticket-form-card">
                        <div class="ticket-form-section-heading"><span>04</span><div><h2>{{ $form->name }}</h2><p>{{ $form->description ?: 'Información específica para esta clasificación.' }}</p></div></div>
                        <div class="form-grid form-grid--two">
                            @foreach ($form->fields->where('is_visible', true) as $field)
                                @php($current = old('fields.'.$field->id, $existingValues->get($field->id)?->value ?? $field->default_value))
                                <div class="field {{ in_array($field->type, ['textarea', 'radio', 'checkbox', 'file']) ? 'field--wide' : '' }}">
                                    <label for="field_{{ $field->id }}">{{ $field->label }} @if ($field->is_required)<strong>*</strong>@endif</label>
                                    @if ($field->type === 'textarea')
                                        <textarea id="field_{{ $field->id }}" name="fields[{{ $field->id }}]" rows="4" @readonly($field->is_readonly) placeholder="{{ $field->placeholder }}">{{ $current }}</textarea>
                                    @elseif ($field->type === 'select')
                                        <select id="field_{{ $field->id }}" name="fields[{{ $field->id }}]" @disabled($field->is_readonly)><option value="">Seleccionar</option>@foreach ($field->options ?? [] as $option)<option value="{{ $option }}" @selected($current === $option)>{{ $option }}</option>@endforeach</select>
                                    @elseif ($field->type === 'radio')
                                        <div class="ticket-radio-row">@foreach ($field->options ?? [] as $option)<label><input type="radio" name="fields[{{ $field->id }}]" value="{{ $option }}" @checked($current === $option) @disabled($field->is_readonly)><span>{{ $option }}</span></label>@endforeach</div>
                                    @elseif ($field->type === 'checkbox')
                                        <label class="ticket-check"><input id="field_{{ $field->id }}" type="checkbox" name="fields[{{ $field->id }}]" value="1" @checked((string) $current === '1') @disabled($field->is_readonly)><span>Confirmar</span></label>
                                    @elseif ($field->type === 'file')
                                        <div class="ticket-file-reference"><span>↥</span><p>Utiliza el apartado de evidencias al final del formulario.</p></div>
                                    @else
                                        <input id="field_{{ $field->id }}" name="fields[{{ $field->id }}]" type="{{ $field->type }}" value="{{ $current }}" @readonly($field->is_readonly) placeholder="{{ $field->placeholder }}" @if($field->min_value !== null) min="{{ $field->min_value }}" @endif @if($field->max_value !== null) max="{{ $field->max_value }}" @endif>
                                    @endif
                                    @if ($field->help_text)<span class="field__hint">{{ $field->help_text }}</span>@endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section class="panel ticket-form-card">
                    <div class="ticket-form-section-heading"><span>05</span><div><h2>Equipos y evidencias</h2><p>Vincula códigos de inventario y adjunta archivos relevantes.</p></div></div>
                    <div class="form-grid form-grid--two">
                        <div class="field"><label for="equipment_codes">Equipos relacionados</label><textarea id="equipment_codes" name="equipment_codes" rows="5" placeholder="ZOLIC-PC-001 | Computadora de recepción&#10;ZOLIC-MON-018 | Monitor">{{ old('equipment_codes', $equipmentText) }}</textarea><span class="field__hint">Máximo 10 equipos; un código por línea. El nombre después de “|” es opcional.</span></div>
                        <div class="field"><label for="attachments">Adjuntos</label><label class="ticket-upload" for="attachments"><span>↥</span><strong>Seleccionar evidencias</strong><small>JPG, PNG, WEBP, PDF, Office, TXT, CSV o ZIP · hasta 5 MB cada uno</small></label><input id="attachments" class="sr-file" type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip" data-file-input><div class="ticket-file-list" data-file-list></div></div>
                    </div>
                    @if ($editing && $ticket->attachments->isNotEmpty())
                        <div class="ticket-existing-files"><strong>Adjuntos del borrador</strong>@foreach ($ticket->attachments as $attachment)<div><span>▣ {{ $attachment->original_name }} · {{ $attachment->human_size }}</span><button type="submit" form="delete-attachment-{{ $attachment->id }}" class="text-button">Eliminar</button></div>@endforeach</div>
                    @endif
                </section>

                <footer class="ticket-submit-bar">
                    <div><strong>¿Aún falta información?</strong><span>Guarda el borrador o envíalo para calcular prioridad, SLA, aprobación y asignación.</span></div>
                    <div><button type="submit" name="action" value="draft" class="button button--outline">Guardar borrador</button><button type="submit" name="action" value="submit" class="button button--primary">Enviar ticket <span>→</span></button></div>
                </footer>
            </div>
        </form>

        @if ($editing)
            @foreach ($ticket->attachments as $attachment)<form id="delete-attachment-{{ $attachment->id }}" method="POST" action="{{ route('tickets.attachments.destroy', [$ticket, $attachment]) }}" data-confirm="¿Eliminar este adjunto del borrador?">@csrf @method('DELETE')</form>@endforeach
        @endif
    @endif
@endsection
