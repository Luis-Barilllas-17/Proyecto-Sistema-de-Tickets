@extends('layouts.app')

@php
    $editing = $developmentRequest !== null;
    $selectedControls = old('security_controls', $editing ? $developmentRequest->securityControls()->where('is_required', true)->pluck('control_type')->all() : ['authentication', 'authorization', 'activity_logging', 'input_validation', 'error_handling']);
@endphp
@section('title', $editing ? 'Editar solicitud de desarrollo' : 'Nueva solicitud de desarrollo')
@section('section-title', 'Formulario especializado de desarrollo')

@section('content')
    <header class="development-form-heading">
        <div>
            <a href="{{ $editing ? route('development.show', $developmentRequest) : route('development.index') }}" class="back-link">← Volver a desarrollo</a>
            <span class="eyebrow">Desarrollo seguro</span>
            <h1>{{ $editing ? 'Actualizar solicitud de desarrollo' : 'Definir una iniciativa tecnológica' }}</h1>
            <p>Completa la necesidad, el alcance y los controles iniciales. Los requerimientos detallados y las pruebas se administrarán dentro del expediente.</p>
        </div>
        <div class="development-secure-badge"><span>◇</span><div><strong>Secure by design</strong><small>Controles OWASP desde el inicio</small></div></div>
    </header>

    <form method="POST" action="{{ $editing ? route('development.update', $developmentRequest) : route('development.store') }}" enctype="multipart/form-data" class="development-request-form">
        @csrf
        @if ($editing) @method('PUT') <input type="hidden" name="lock_version" value="{{ $ticket->lock_version }}"> @endif

        <aside class="development-form-nav">
            <span>Expediente</span>
            <a href="#initiative" class="is-active"><b>01</b><div><strong>Iniciativa</strong><small>Tipo y objetivo</small></div></a>
            <a href="#scope"><b>02</b><div><strong>Alcance</strong><small>Necesidad y restricciones</small></div></a>
            <a href="#information"><b>03</b><div><strong>Información</strong><small>Clasificación y accesos</small></div></a>
            <a href="#security"><b>04</b><div><strong>Seguridad</strong><small>Controles aplicables</small></div></a>
            <a href="#service"><b>05</b><div><strong>Servicio</strong><small>Impacto y evidencia</small></div></a>
        </aside>

        <div class="development-form-content">
            <section id="initiative" class="panel development-form-section">
                <div class="development-section-heading"><span>01</span><div><small>Solicitud de desarrollo</small><h2>Identificación de la iniciativa</h2><p>Sistemas, componentes, mejoras, correcciones o automatizaciones.</p></div></div>
                <div class="development-type-grid">
                    @foreach (App\Models\DevelopmentRequest::REQUEST_TYPES as $value => $label)
                        <label><input type="radio" name="request_type" value="{{ $value }}" @checked(old('request_type', $developmentRequest?->request_type ?: 'improvement') === $value) required><span><b>{{ str($label)->substr(0, 2)->upper() }}</b><strong>{{ $label }}</strong></span></label>
                    @endforeach
                </div>
                <div class="form-grid form-grid--two">
                    <div class="field field--wide"><label for="project_name">Nombre de la iniciativa <strong>*</strong></label><input id="project_name" name="project_name" maxlength="200" value="{{ old('project_name', $developmentRequest?->project_name) }}" placeholder="Ej. Automatización del control de accesos"></div>
                    <div class="field"><label for="ticket_subcategory_id">Clasificación <strong>*</strong></label><select id="ticket_subcategory_id" name="ticket_subcategory_id" required>@foreach ($category->subcategories as $subcategory)<option value="{{ $subcategory->id }}" @selected(old('ticket_subcategory_id', $ticket?->ticket_subcategory_id) == $subcategory->id)>{{ $subcategory->name }}</option>@endforeach</select></div>
                    <div class="field"><label for="affected_system">Sistema afectado</label><input id="affected_system" name="affected_system" maxlength="200" value="{{ old('affected_system', $developmentRequest?->affected_system) }}" placeholder="Nuevo desarrollo o nombre del sistema actual"></div>
                    <div class="field field--wide"><label for="objective">Objetivo <strong>*</strong></label><textarea id="objective" name="objective" rows="4" maxlength="15000" placeholder="Resultado institucional que se desea alcanzar">{{ old('objective', $developmentRequest?->objective) }}</textarea></div>
                </div>
            </section>

            <section id="scope" class="panel development-form-section">
                <div class="development-section-heading"><span>02</span><div><small>Especificación funcional</small><h2>Necesidad y alcance</h2><p>Base para construir requisitos, historias y criterios de aceptación verificables.</p></div></div>
                <div class="form-grid form-grid--two">
                    <div class="field field--wide"><label for="business_need">Necesidad institucional <strong>*</strong></label><textarea id="business_need" name="business_need" rows="5" maxlength="15000" placeholder="Problema actual, usuarios afectados y consecuencias">{{ old('business_need', $developmentRequest?->business_need) }}</textarea></div>
                    <div class="field field--wide"><label for="scope_text">Alcance esperado <strong>*</strong></label><textarea id="scope_text" name="scope" rows="5" maxlength="20000" placeholder="Qué incluye y qué queda fuera de esta solicitud">{{ old('scope', $developmentRequest?->scope) }}</textarea></div>
                    <div class="field"><label for="expected_benefits">Beneficios esperados</label><textarea id="expected_benefits" name="expected_benefits" rows="4" maxlength="5000">{{ old('expected_benefits', $developmentRequest?->expected_benefits) }}</textarea></div>
                    <div class="field"><label for="technical_restrictions">Restricciones técnicas</label><textarea id="technical_restrictions" name="technical_restrictions" rows="4" maxlength="10000" placeholder="Tecnologías, integraciones, licencias o infraestructura">{{ old('technical_restrictions', $developmentRequest?->technical_restrictions) }}</textarea></div>
                    <div class="field"><label for="expected_delivery_date">Fecha esperada</label><input id="expected_delivery_date" type="date" name="expected_delivery_date" value="{{ old('expected_delivery_date', $developmentRequest?->expected_delivery_date?->format('Y-m-d')) }}"></div>
                    <div class="field"><label for="repository_reference">Referencia técnica</label><input id="repository_reference" name="repository_reference" maxlength="255" value="{{ old('repository_reference', $developmentRequest?->repository_reference) }}" placeholder="Repositorio o documento relacionado; no incluyas credenciales"></div>
                </div>
            </section>

            <section id="information" class="panel development-form-section">
                <div class="development-section-heading"><span>03</span><div><small>Protección de información</small><h2>Clasificación de información y accesos</h2><p>Identifica exposición, datos sensibles y participación de terceros.</p></div></div>
                <div class="development-classification-grid">
                    @foreach (App\Models\DevelopmentRequest::INFORMATION_CLASSIFICATIONS as $value => $label)
                        <label class="development-classification development-classification--{{ $value }}"><input type="radio" name="information_classification" value="{{ $value }}" @checked(old('information_classification', $developmentRequest?->information_classification ?: 'internal') === $value)><span><b></b><strong>{{ $label }}</strong><small>{{ match($value) {'public' => 'Sin restricción institucional', 'internal' => 'Uso dentro de ZOLIC', 'confidential' => 'Acceso por necesidad funcional', default => 'Acceso estrictamente autorizado'} }}</small></span></label>
                    @endforeach
                </div>
                <div class="development-risk-options">
                    <label><input type="checkbox" name="contains_sensitive_data" value="1" @checked(old('contains_sensitive_data', $developmentRequest?->contains_sensitive_data))><span>!</span><div><strong>Procesa datos sensibles</strong><small>Personales, financieros, credenciales u otra información protegida.</small></div></label>
                    <label><input type="checkbox" name="involves_third_parties" value="1" @checked(old('involves_third_parties', $developmentRequest?->involves_third_parties))><span>↗</span><div><strong>Participan terceros</strong><small>Proveedores, integraciones o personal externo con acceso.</small></div></label>
                </div>
                <div class="form-grid form-grid--two">
                    <div class="field"><label for="sensitive_data_description">Datos sensibles involucrados</label><textarea id="sensitive_data_description" name="sensitive_data_description" rows="4" maxlength="5000">{{ old('sensitive_data_description', $developmentRequest?->sensitive_data_description) }}</textarea></div>
                    <div class="field"><label for="third_party_details">Participación de terceros</label><textarea id="third_party_details" name="third_party_details" rows="4" maxlength="5000">{{ old('third_party_details', $developmentRequest?->third_party_details) }}</textarea></div>
                    <div class="field field--wide"><label for="access_overview">Perfiles de acceso previstos</label><textarea id="access_overview" name="access_overview" rows="4" maxlength="5000" placeholder="Enumera perfiles principales y el alcance general de cada uno">{{ old('access_overview', $developmentRequest?->access_overview) }}</textarea></div>
                </div>
            </section>

            <section id="security" class="panel development-form-section">
                <div class="development-section-heading"><span>04</span><div><small>Seguridad por diseño</small><h2>Controles de seguridad aplicables</h2><p>Selecciona los controles que deberán documentarse, implementarse y verificarse.</p></div></div>
                <div class="development-security-selector">
                    @foreach (App\Models\DevelopmentSecurityControl::TYPES as $value => $label)
                        <label><input type="checkbox" name="security_controls[]" value="{{ $value }}" @checked(in_array($value, $selectedControls, true))><span><b>{{ ['authentication' => 'ID', 'authorization' => 'RB', 'encryption' => 'CR', 'activity_logging' => 'LG', 'input_validation' => 'VL', 'error_handling' => 'ER'][$value] }}</b><strong>{{ $label }}</strong><small>Requiere evidencia antes del despliegue</small></span></label>
                    @endforeach
                </div>
            </section>

            <section id="service" class="panel development-form-section">
                <div class="development-section-heading"><span>05</span><div><small>Clasificación del servicio</small><h2>Impacto, urgencia y documentos</h2><p>La prioridad y los tiempos objetivo serán calculados automáticamente.</p></div></div>
                <div class="form-grid form-grid--two">
                    <div class="field"><label for="requester_area_id">Área solicitante <strong>*</strong></label><select id="requester_area_id" name="requester_area_id" data-area-select><option value="">Seleccionar</option>@foreach ($areas as $area)<option value="{{ $area->id }}" @selected(old('requester_area_id', $ticket?->requester_area_id ?: auth()->user()->area_id) == $area->id)>{{ $area->name }}</option>@endforeach</select></div>
                    <div class="field"><label for="requester_department_id">Departamento</label><select id="requester_department_id" name="requester_department_id" data-department-select><option value="">Sin departamento</option>@foreach ($areas as $area) @foreach ($area->departments as $department)<option value="{{ $department->id }}" data-area="{{ $area->id }}" @selected(old('requester_department_id', $ticket?->requester_department_id ?: auth()->user()->department_id) == $department->id)>{{ $department->name }}</option>@endforeach @endforeach</select></div>
                    <div class="field"><label for="location">Ubicación <strong>*</strong></label><input id="location" name="location" maxlength="255" value="{{ old('location', $ticket?->location) }}" placeholder="Edificio, oficina o sede"></div>
                    <div class="field"><label for="impact_level_id">Impacto <strong>*</strong></label><select id="impact_level_id" name="impact_level_id"><option value="">Seleccionar</option>@foreach ($impacts as $impact)<option value="{{ $impact->id }}" @selected(old('impact_level_id', $ticket?->impact_level_id) == $impact->id)>{{ $impact->name }} · {{ $impact->description }}</option>@endforeach</select></div>
                    <div class="field"><label for="urgency_level_id">Urgencia <strong>*</strong></label><select id="urgency_level_id" name="urgency_level_id"><option value="">Seleccionar</option>@foreach ($urgencies as $urgency)<option value="{{ $urgency->id }}" @selected(old('urgency_level_id', $ticket?->urgency_level_id) == $urgency->id)>{{ $urgency->name }} · {{ $urgency->description }}</option>@endforeach</select></div>
                    <div class="field"><label for="development_attachments">Documentos iniciales</label><label class="development-upload" for="development_attachments"><span>↥</span><div><strong>Adjuntar documentación</strong><small>Diagramas, especificaciones o referencias · máximo 10 MB</small></div></label><input class="sr-file" id="development_attachments" type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip" data-file-input><div class="ticket-file-list" data-file-list></div></div>
                </div>
            </section>

            <footer class="development-submit-bar">
                <div><span>◇</span><p><strong>Validación segura activa</strong><small>El envío creará el flujo de aprobación, prioridad, SLA y direccionamiento técnico.</small></p></div>
                <div>
                    @if (! $editing || $ticket->is_draft)<button type="submit" name="action" value="draft" class="button button--outline">Guardar borrador</button><button type="submit" name="action" value="submit" class="button button--primary">Enviar a aprobación <span>→</span></button>@else<button type="submit" name="action" value="save" class="button button--primary">Guardar cambios</button>@endif
                </div>
            </footer>
        </div>
    </form>
@endsection
