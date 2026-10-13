@extends('layouts.app')

@section('title', $ticket->number.' · Desarrollo')
@section('section-title', 'Expediente de desarrollo seguro')

@section('content')
    <header class="development-detail-heading">
        <div>
            <a href="{{ route('development.index') }}" class="back-link">← Volver al portafolio</a>
            <div class="development-detail-heading__meta"><span>{{ $ticket->number }}</span><em class="development-stage development-stage--{{ $developmentRequest->lifecycle_status }}">{{ $developmentRequest->lifecycle_status_label }}</em>@if($ticket->priority)<b class="ticket-priority" style="--ticket-color: {{ $ticket->priority->color }}">{{ $ticket->priority->code }} · {{ $ticket->priority->name }}</b>@endif</div>
            <h1>{{ $developmentRequest->project_name ?: 'Solicitud pendiente de completar' }}</h1>
            <p>{{ $developmentRequest->request_type_label }} · Solicitado por {{ $ticket->requester->name }} · {{ $ticket->created_at->format('d/m/Y H:i') }}</p>
        </div>
        <div class="development-detail-heading__actions">
            @can('update', $developmentRequest)<a href="{{ route('development.edit', $developmentRequest) }}" class="button button--outline">Editar ficha</a>@endcan
            <a href="{{ route('tickets.show', $ticket) }}" class="button button--primary">Ver ticket integral <span>→</span></a>
        </div>
    </header>

    <nav class="development-detail-nav" aria-label="Secciones del expediente">
        <a href="#overview" class="is-active"><span>01</span>Resumen</a>
        <a href="#requirements"><span>02</span>Requerimientos <b>{{ $developmentRequest->requirements->count() }}</b></a>
        <a href="#security-matrix"><span>03</span>Seguridad <b>{{ $developmentRequest->securityControls->where('status', 'verified')->count() }}/{{ $developmentRequest->securityControls->where('is_required', true)->count() }}</b></a>
        <a href="#testing"><span>04</span>Pruebas <b>{{ $developmentRequest->testEvidences->count() }}</b></a>
        <a href="#deployment"><span>05</span>Despliegue</a>
    </nav>

    <div class="development-workspace">
        <div class="development-workspace__main">
            <section id="overview" class="panel development-overview">
                <div class="panel__header"><div><span class="panel__eyebrow">Solicitud</span><h2>Resumen de la iniciativa</h2></div><span class="development-classification-badge development-classification-badge--{{ $developmentRequest->information_classification }}">{{ $developmentRequest->information_classification_label }}</span></div>
                <div class="development-overview-grid">
                    <div><span>Tipo</span><strong>{{ $developmentRequest->request_type_label }}</strong></div>
                    <div><span>Sistema afectado</span><strong>{{ $developmentRequest->affected_system ?: 'Nuevo desarrollo' }}</strong></div>
                    <div><span>Fecha esperada</span><strong>{{ $developmentRequest->expected_delivery_date?->format('d/m/Y') ?: 'Por definir' }}</strong></div>
                    <div><span>Equipo responsable</span><strong>{{ $ticket->currentGroup?->name ?: 'Pendiente de asignación' }}</strong></div>
                </div>
                <div class="development-overview-copy"><article><span>Necesidad institucional</span><p>{{ $developmentRequest->business_need ?: 'Pendiente de completar.' }}</p></article><article><span>Objetivo</span><p>{{ $developmentRequest->objective ?: 'Pendiente de completar.' }}</p></article><article class="is-wide"><span>Alcance</span><p>{{ $developmentRequest->scope ?: 'Pendiente de completar.' }}</p></article></div>
                @if ($developmentRequest->technical_restrictions || $developmentRequest->expected_benefits)
                    <div class="development-overview-notes">@if($developmentRequest->technical_restrictions)<div><span>△</span><p><strong>Restricciones técnicas</strong>{{ $developmentRequest->technical_restrictions }}</p></div>@endif @if($developmentRequest->expected_benefits)<div><span>↗</span><p><strong>Beneficios esperados</strong>{{ $developmentRequest->expected_benefits }}</p></div>@endif</div>
                @endif
            </section>

            <section id="requirements" class="panel development-requirements">
                <div class="panel__header"><div><span class="panel__eyebrow">Especificación</span><h2>Requerimientos e historias</h2></div><span class="count-pill">{{ $developmentRequest->requirements->count() }} elementos</span></div>
                <div class="development-requirement-list">
                    @forelse ($developmentRequest->requirements as $requirement)
                        <details class="development-requirement development-requirement--{{ $requirement->priority }}">
                            <summary><span>{{ $requirement->code }}</span><div><small>{{ $requirement->type_label }}</small><strong>{{ $requirement->title }}</strong></div><em>{{ $requirement->priority_label }}</em><b class="development-requirement-status development-requirement-status--{{ $requirement->status }}">{{ $requirement->status_label }}</b></summary>
                            <div class="development-requirement__body"><article><span>Descripción</span><p>{{ $requirement->description }}</p></article>@if($requirement->acceptance_criteria)<article><span>Criterios de aceptación</span><p>{{ $requirement->acceptance_criteria }}</p></article>@endif @if($requirement->technical_restrictions)<article><span>Restricciones técnicas</span><p>{{ $requirement->technical_restrictions }}</p></article>@endif</div>
                            @can('manageRequirements', $developmentRequest)
                                <form method="POST" action="{{ route('development.requirements.update', [$developmentRequest, $requirement]) }}" class="development-inline-editor">@csrf @method('PUT')
                                    <div class="form-grid form-grid--three"><div class="field"><label>Código</label><input name="code" value="{{ $requirement->code }}"></div><div class="field"><label>Tipo</label><select name="type">@foreach(App\Models\DevelopmentRequirement::TYPES as $value => $label)<option value="{{ $value }}" @selected($requirement->type === $value)>{{ $label }}</option>@endforeach</select></div><div class="field"><label>Prioridad</label><select name="priority">@foreach(App\Models\DevelopmentRequirement::PRIORITIES as $value => $label)<option value="{{ $value }}" @selected($requirement->priority === $value)>{{ $label }}</option>@endforeach</select></div><div class="field"><label>Estado</label><select name="status">@foreach(App\Models\DevelopmentRequirement::STATUSES as $value => $label)<option value="{{ $value }}" @selected($requirement->status === $value)>{{ $label }}</option>@endforeach</select></div><div class="field"><label>Orden</label><input type="number" name="sort_order" value="{{ $requirement->sort_order }}" min="0"></div><div class="field field--wide"><label>Título</label><input name="title" value="{{ $requirement->title }}" required></div><div class="field field--wide"><label>Descripción</label><textarea name="description" rows="3" required>{{ $requirement->description }}</textarea></div><div class="field"><label>Criterios de aceptación</label><textarea name="acceptance_criteria" rows="3">{{ $requirement->acceptance_criteria }}</textarea></div><div class="field"><label>Restricciones técnicas</label><textarea name="technical_restrictions" rows="3">{{ $requirement->technical_restrictions }}</textarea></div></div>
                                    <button class="button button--primary button--small">Guardar requerimiento</button>
                                </form>
                            @endcan
                        </details>
                    @empty
                        <div class="empty-state"><strong>Aún no hay requerimientos detallados</strong><p>Registra requisitos funcionales, historias, criterios y restricciones antes de iniciar el desarrollo.</p></div>
                    @endforelse
                </div>
                @can('manageRequirements', $developmentRequest)
                    <details class="development-add-panel"><summary>＋ Agregar requerimiento</summary><form method="POST" action="{{ route('development.requirements.store', $developmentRequest) }}">@csrf<div class="form-grid form-grid--three"><div class="field"><label>Código</label><input name="code" placeholder="Automático"></div><div class="field"><label>Tipo</label><select name="type" required>@foreach(App\Models\DevelopmentRequirement::TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div><div class="field"><label>Prioridad</label><select name="priority" required>@foreach(App\Models\DevelopmentRequirement::PRIORITIES as $value => $label)<option value="{{ $value }}" @selected($value === 'medium')>{{ $label }}</option>@endforeach</select></div><div class="field"><label>Estado</label><select name="status" required>@foreach(App\Models\DevelopmentRequirement::STATUSES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div><div class="field"><label>Orden</label><input type="number" name="sort_order" value="0" min="0"></div><div class="field field--wide"><label>Título</label><input name="title" maxlength="200" required></div><div class="field field--wide"><label>Descripción</label><textarea name="description" rows="4" required></textarea></div><div class="field"><label>Criterios de aceptación</label><textarea name="acceptance_criteria" rows="4"></textarea></div><div class="field"><label>Restricciones técnicas</label><textarea name="technical_restrictions" rows="4"></textarea></div></div><div class="development-add-panel__action"><button class="button button--primary">Registrar requerimiento</button></div></form></details>
                @endcan
            </section>

            <section id="security-matrix" class="panel development-security-matrix">
                <div class="panel__header"><div><span class="panel__eyebrow">Seguridad por diseño</span><h2>Matriz de controles</h2></div><div class="development-security-score"><strong>{{ $developmentRequest->securityControls->where('status', 'verified')->count() }}</strong><span>verificados de {{ $developmentRequest->securityControls->where('is_required', true)->count() }} requeridos</span></div></div>
                @can('manageSecurity', $developmentRequest)<form method="POST" action="{{ route('development.security.update', $developmentRequest) }}">@csrf @method('PUT')@endcan
                    <div class="development-security-list">
                        @foreach ($developmentRequest->securityControls as $control)
                            <article class="development-security-control development-security-control--{{ $control->status }}">
                                <header><span>{{ ['authentication' => 'ID', 'authorization' => 'RB', 'encryption' => 'CR', 'activity_logging' => 'LG', 'input_validation' => 'VL', 'error_handling' => 'ER'][$control->control_type] }}</span><div><strong>{{ $control->type_label }}</strong><small>{{ $control->is_required ? 'Control requerido' : 'Control opcional' }}</small></div><em>{{ $control->status_label }}</em></header>
                                @can('manageSecurity', $developmentRequest)
                                    <input type="hidden" name="controls[{{ $control->id }}][is_required]" value="0"><label class="development-control-required"><input type="checkbox" name="controls[{{ $control->id }}][is_required]" value="1" @checked($control->is_required)><span>Requerido para esta solución</span></label>
                                    <div class="field"><label>Requisito del control</label><textarea name="controls[{{ $control->id }}][requirement_details]" rows="3">{{ $control->requirement_details }}</textarea></div>
                                    <div class="field"><label>Notas de implementación</label><textarea name="controls[{{ $control->id }}][implementation_notes]" rows="3">{{ $control->implementation_notes }}</textarea></div>
                                    <div class="field"><label>Estado</label><select name="controls[{{ $control->id }}][status]">@foreach(App\Models\DevelopmentSecurityControl::STATUSES as $value => $label) @if($value !== 'verified' || auth()->user()->can('verifySecurity', $developmentRequest))<option value="{{ $value }}" @selected($control->status === $value)>{{ $label }}</option>@endif @endforeach</select></div>
                                @else
                                    <p>{{ $control->requirement_details ?: 'Sin detalle registrado.' }}</p>@if($control->verifiedBy)<footer>Verificado por {{ $control->verifiedBy->name }} · {{ $control->verified_at?->format('d/m/Y H:i') }}</footer>@endif
                                @endcan
                            </article>
                        @endforeach
                    </div>
                    @can('manageSecurity', $developmentRequest)<div class="development-security-actions"><span>Los controles marcados como requeridos deberán estar verificados antes de producción.</span><button class="button button--primary">Guardar matriz de seguridad</button></div>@endcan
                @can('manageSecurity', $developmentRequest)</form>@endcan
            </section>

            <section class="panel development-access-section">
                <div class="panel__header"><div><span class="panel__eyebrow">Mínimo privilegio</span><h2>Perfiles de acceso</h2></div><span class="count-pill">{{ $developmentRequest->accessProfiles->count() }}</span></div>
                <div class="development-access-grid">@forelse($developmentRequest->accessProfiles as $profile)<article><header><span>{{ str($profile->name)->substr(0, 1)->upper() }}</span><div><strong>{{ $profile->name }}</strong><small>{{ $profile->description }}</small></div></header><div><span>Permisos</span><p>{{ $profile->permissions }}</p></div><div><span>Alcance de datos</span><p>{{ $profile->data_scope ?: 'No especificado' }}</p></div><footer>@if($profile->includes_sensitive_data)<em>Datos sensibles</em>@endif @if($profile->third_party_access)<em>Acceso externo</em>@endif</footer></article>@empty<div class="empty-state"><strong>Sin perfiles detallados</strong><p>Registra los perfiles y su alcance para aplicar mínimo privilegio.</p></div>@endforelse</div>
                @can('manageSecurity', $developmentRequest)<details class="development-add-panel"><summary>＋ Agregar perfil de acceso</summary><form method="POST" action="{{ route('development.access-profiles.store', $developmentRequest) }}">@csrf<div class="form-grid form-grid--two"><div class="field"><label>Nombre del perfil</label><input name="name" required maxlength="140"></div><div class="field"><label>Descripción</label><input name="description" maxlength="3000"></div><div class="field"><label>Permisos permitidos</label><textarea name="permissions" rows="4" required></textarea></div><div class="field"><label>Alcance de datos</label><textarea name="data_scope" rows="4"></textarea></div><label class="ticket-check"><input type="checkbox" name="includes_sensitive_data" value="1"><span>Accede a datos sensibles</span></label><label class="ticket-check"><input type="checkbox" name="third_party_access" value="1"><span>Perfil para tercero</span></label></div><div class="development-add-panel__action"><button class="button button--primary">Registrar perfil</button></div></form></details>@endcan
            </section>

            <section id="testing" class="panel development-testing-section">
                <div class="panel__header"><div><span class="panel__eyebrow">Evidencia verificable</span><h2>Pruebas y aceptación</h2></div><span class="count-pill">{{ $developmentRequest->testEvidences->count() }} evidencias</span></div>
                <div class="development-test-summary">@foreach(App\Models\DevelopmentTestEvidence::TYPES as $value => $label) @php($passed = $developmentRequest->testEvidences->where('test_type', $value)->where('result', 'passed')->isNotEmpty())<div class="{{ $passed ? 'is-passed' : '' }}"><span>{{ $passed ? '✓' : '○' }}</span><p><strong>{{ $label }}</strong><small>{{ $passed ? 'Evidencia aprobada' : 'Pendiente' }}</small></p></div>@endforeach</div>
                <div class="development-test-list">@foreach($developmentRequest->testEvidences as $evidence)<article class="is-{{ $evidence->result }}"><div><span>{{ $evidence->type_label }}</span><strong>{{ $evidence->title }}</strong><small>{{ $evidence->environment }} · {{ $evidence->executed_at->format('d/m/Y H:i') }} · {{ $evidence->executedBy->name }}</small></div><em>{{ $evidence->result_label }}</em><footer>@foreach($evidence->attachments as $attachment)<a href="{{ route('tickets.attachments.download', [$ticket, $attachment]) }}">▣ {{ $attachment->original_name }} · {{ $attachment->human_size }}</a>@endforeach</footer>@if($evidence->notes)<p>{{ $evidence->notes }}</p>@endif</article>@endforeach</div>
                @can('manageTests', $developmentRequest)<details class="development-add-panel"><summary>＋ Registrar evidencia de prueba</summary><form method="POST" action="{{ route('development.tests.store', $developmentRequest) }}" enctype="multipart/form-data">@csrf<div class="form-grid form-grid--two"><div class="field"><label>Tipo de prueba</label><select name="test_type" required>@foreach(App\Models\DevelopmentTestEvidence::TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div><div class="field"><label>Resultado</label><select name="result" required>@foreach(App\Models\DevelopmentTestEvidence::RESULTS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div><div class="field"><label>Título</label><input name="title" required maxlength="180"></div><div class="field"><label>Ambiente</label><input name="environment" value="QA" required maxlength="80"></div><div class="field"><label>Fecha de ejecución</label><input type="datetime-local" name="executed_at" value="{{ now()->format('Y-m-d\TH:i') }}" required></div><div class="field"><label>Evidencia <strong>*</strong></label><input type="file" name="attachments[]" required multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip"></div><div class="field field--wide"><label>Observaciones</label><textarea name="notes" rows="4"></textarea></div></div><div class="development-add-panel__action"><button class="button button--primary">Guardar evidencia privada</button></div></form></details>@endcan
            </section>

            <section id="deployment" class="panel development-deployment-section">
                <div class="panel__header"><div><span class="panel__eyebrow">Puerta de producción</span><h2>Autorización de despliegue</h2></div></div>
                @foreach($developmentRequest->deploymentAuthorizations as $authorization)
                    <article class="development-deployment-card development-deployment-card--{{ $authorization->status }}"><header><div><span>Versión {{ $authorization->version }} · Producción</span><strong>{{ $authorization->status_label }}</strong><small>Solicitada por {{ $authorization->requestedBy->name }} · {{ $authorization->requested_at->format('d/m/Y H:i') }}</small></div><em>{{ $authorization->scheduled_at?->format('d/m/Y H:i') ?: 'Sin fecha programada' }}</em></header><div><section><span>Notas de versión</span><p>{{ $authorization->release_notes }}</p></section><section><span>Plan de despliegue</span><p>{{ $authorization->deployment_plan }}</p></section><section><span>Plan de reversión</span><p>{{ $authorization->rollback_plan }}</p></section></div>@if($authorization->decision_comment)<footer><strong>Decisión de {{ $authorization->decidedBy?->name }}</strong><p>{{ $authorization->decision_comment }}</p></footer>@endif
                        @if($authorization->status === 'pending') @can('authorizeDeployment', $developmentRequest)<form method="POST" action="{{ route('development.deployments.decide', [$developmentRequest, $authorization]) }}">@csrf @method('PATCH')<div class="field"><label>Fundamento de la decisión</label><textarea name="decision_comment" rows="3" minlength="10" required></textarea></div><div><button name="decision" value="rejected" class="button button--danger-soft">Rechazar</button><button name="decision" value="authorized" class="button button--primary">Autorizar producción y cierre</button></div></form>@endcan @endif
                    </article>
                @endforeach
                @can('requestDeployment', $developmentRequest)
                    @if($readinessIssues->isEmpty())
                        <details class="development-add-panel development-add-panel--deployment"><summary>↗ Solicitar autorización de producción</summary><form method="POST" action="{{ route('development.deployments.store', $developmentRequest) }}">@csrf<div class="form-grid form-grid--two"><div class="field"><label>Versión</label><input name="version" placeholder="1.0.0" required></div><div class="field"><label>Fecha programada</label><input type="datetime-local" name="scheduled_at"></div><div class="field field--wide"><label>Notas de versión</label><textarea name="release_notes" rows="4" minlength="20" required></textarea></div><div class="field"><label>Plan de despliegue</label><textarea name="deployment_plan" rows="5" minlength="20" required></textarea></div><div class="field"><label>Plan de reversión</label><textarea name="rollback_plan" rows="5" minlength="20" required></textarea></div></div><div class="development-add-panel__action"><button class="button button--primary">Enviar a Jefatura de IT</button></div></form></details>
                    @endif
                @endcan
            </section>
        </div>

        <aside class="development-workspace__side">
            <section class="panel development-readiness-card">
                <div class="panel__header"><div><span class="panel__eyebrow">Control de salida</span><h2>Preparación para producción</h2></div></div>
                @if($readinessIssues->isEmpty())<div class="development-ready"><span>✓</span><strong>Expediente completo</strong><p>La solicitud cumple los controles mínimos para pedir autorización.</p></div>@else<div class="development-readiness-issues"><span>{{ $readinessIssues->count() }} pendientes</span>@foreach($readinessIssues as $issue)<p><b>!</b>{{ $issue }}</p>@endforeach</div>@endif
            </section>

            <section class="panel development-info-security-card">
                <div class="panel__header"><div><span class="panel__eyebrow">Clasificación</span><h2>Información y exposición</h2></div></div>
                <dl><div><dt>Clasificación</dt><dd>{{ $developmentRequest->information_classification_label }}</dd></div><div><dt>Datos sensibles</dt><dd>{{ $developmentRequest->contains_sensitive_data ? 'Sí' : 'No' }}</dd></div><div><dt>Terceros</dt><dd>{{ $developmentRequest->involves_third_parties ? 'Sí' : 'No' }}</dd></div><div><dt>Perfiles definidos</dt><dd>{{ $developmentRequest->accessProfiles->count() }}</dd></div></dl>
                @if($developmentRequest->sensitive_data_description)<p><strong>Datos identificados</strong>{{ $developmentRequest->sensitive_data_description }}</p>@endif
                @if($developmentRequest->third_party_details)<p><strong>Terceros identificados</strong>{{ $developmentRequest->third_party_details }}</p>@endif
            </section>

            <section class="panel development-ticket-card">
                <div class="panel__header"><div><span class="panel__eyebrow">Ticket integral</span><h2>Servicio y responsables</h2></div></div>
                <div class="development-ticket-status"><span style="--ticket-color: {{ $ticket->status?->color ?? '#888' }}"></span><div><strong>{{ $ticket->status?->name }}</strong><small>{{ $ticket->approval_status_label }}</small></div></div>
                <dl><div><dt>Solicitante</dt><dd>{{ $ticket->requester->name }}</dd></div><div><dt>Responsable</dt><dd>{{ $ticket->currentAssignee?->name ?: 'Sin responsable' }}</dd></div><div><dt>Grupo</dt><dd>{{ $ticket->currentGroup?->name ?: 'Sin grupo' }}</dd></div><div><dt>Resolución SLA</dt><dd>{{ $ticket->resolution_due_at?->format('d/m/Y H:i') ?: 'Sin objetivo' }}</dd></div></dl>
                <a href="{{ route('tickets.show', $ticket) }}" class="button button--outline button--full">Abrir seguimiento integral</a>
            </section>

            @if($ticket->attachments->where('context', 'development_request')->isNotEmpty())<section class="panel development-document-card"><div class="panel__header"><div><span class="panel__eyebrow">Documentación inicial</span><h2>Archivos de la solicitud</h2></div></div><div>@foreach($ticket->attachments->where('context', 'development_request') as $attachment)<a href="{{ route('tickets.attachments.download', [$ticket, $attachment]) }}"><span>▣</span><p><strong>{{ $attachment->original_name }}</strong><small>{{ $attachment->human_size }} · SHA-256 registrado</small></p></a>@endforeach</div></section>@endif

            <section class="panel development-audit-card"><div class="panel__header"><div><span class="panel__eyebrow">Auditoría</span><h2>Actividad reciente</h2></div></div><div>@foreach($ticket->histories->take(8) as $entry)<article><span></span><p><strong>{{ $entry->description }}</strong><small>{{ $entry->user?->name ?: 'Sistema' }} · {{ $entry->created_at->diffForHumans() }}</small></p></article>@endforeach</div></section>
        </aside>
    </div>
@endsection
