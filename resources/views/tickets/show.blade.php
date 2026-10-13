@extends('layouts.app')

@section('title', $ticket->number)
@section('section-title', 'Detalle y seguimiento del ticket')

@section('content')
    <header class="ticket-detail-header">
        <div>
            <a href="{{ route('tickets.index') }}" class="back-link">← Volver a la bandeja</a>
            <div class="ticket-detail-header__meta">
                <span class="ticket-number ticket-number--large">{{ $ticket->number }}</span>
                @if ($ticket->is_draft)<span class="ticket-status ticket-status--draft">Borrador</span>@else<span class="ticket-status" style="--ticket-color: {{ $ticket->status?->color ?? '#888888' }}">{{ $ticket->status?->name }}</span>@endif
                @if ($ticket->priority)<span class="ticket-priority" style="--ticket-color: {{ $ticket->priority->color }}">{{ $ticket->priority->code }} · {{ $ticket->priority->name }}</span>@endif
            </div>
            <h1>{{ $ticket->title ?: 'Borrador sin asunto' }}</h1>
            <p>Registrado por {{ $ticket->creator->name }} · {{ $ticket->created_at->format('d/m/Y H:i') }} · {{ $ticket->source_label }}</p>
        </div>
        <div class="ticket-header-actions">
            @can('update', $ticket)<a href="{{ route('tickets.edit', $ticket) }}" class="button button--outline">Editar borrador</a>@endcan
            <a href="#conversation" class="button button--primary">Ir al seguimiento ↓</a>
        </div>
    </header>

    <section class="ticket-progress" aria-label="Progreso del ticket">
        @php
            $steps = ['Registrado', 'Aprobación', 'Asignación', 'Atención', 'Solución', 'Cierre'];
            $activeStep = $ticket->is_draft ? 0 : (match($ticket->status?->slug) {
                'registrado', 'pendiente-aprobacion' => 1, 'asignado' => 2,
                'en-analisis', 'en-proceso', 'pendiente-usuario', 'pendiente-tercero' => 3,
                'solucionado' => 4, 'cerrado' => 5, default => 0,
            });
        @endphp
        @foreach ($steps as $index => $step)<span class="{{ $index < $activeStep ? 'is-done' : ($index === $activeStep ? 'is-active' : '') }}"><b>{{ $index < $activeStep ? '✓' : $index + 1 }}</b><i></i><small>{{ $step }}</small></span>@endforeach
    </section>

    <div class="ticket-workspace">
        <div class="ticket-workspace__main">
            <section class="panel ticket-overview-card">
                <div class="panel__header"><div><span class="panel__eyebrow">Clasificación</span><h2>Información del caso</h2></div></div>
                <div class="ticket-overview-grid">
                    <div><span>Solicitante</span><strong>{{ $ticket->requester->name }}</strong><small>{{ $ticket->requester->email }}</small></div>
                    <div><span>Área / departamento</span><strong>{{ $ticket->requesterArea?->name ?: 'Sin área' }}</strong><small>{{ $ticket->requesterDepartment?->name ?: 'Sin departamento' }}</small></div>
                    <div><span>Categoría</span><strong>{{ $ticket->category->name }}</strong><small>{{ $ticket->subcategory?->name }}</small></div>
                    <div><span>Atención / ubicación</span><strong>{{ App\Models\Ticket::ATTENTION_TYPES[$ticket->attention_type] ?? 'Por definir' }}</strong><small>{{ $ticket->location }}</small></div>
                </div>
                <div class="ticket-description"><span>Descripción</span><p>{{ $ticket->description ?: 'El borrador aún no contiene una descripción.' }}</p></div>
                @if ($ticket->fieldValues->isNotEmpty())
                    <div class="ticket-custom-values"><h3>{{ $ticket->form?->name ?: 'Datos del formulario' }}</h3><div>@foreach ($ticket->fieldValues as $value)<span><small>{{ $value->field->label }}</small><strong>{{ $value->field->type === 'checkbox' ? ($value->value === '1' ? 'Sí' : 'No') : $value->value }}</strong></span>@endforeach</div></div>
                @endif
                @if ($ticket->equipmentLinks->isNotEmpty())
                    <div class="ticket-equipment"><h3>Equipos vinculados</h3>@foreach ($ticket->equipmentLinks as $equipment)@if($equipment->equipment && auth()->user()->can('view',$equipment->equipment))<a href="{{ route('inventory.equipment.show',$equipment->equipment) }}"><b>{{ $equipment->inventory_code }}</b>{{ $equipment->equipment_name }} →</a>@else<span><b>{{ $equipment->inventory_code }}</b>{{ $equipment->equipment_name }}</span>@endif @endforeach</div>
                @endif
            </section>

            @if($ticket->maintenanceOrder && auth()->user()->hasPermission('maintenance.view'))
                <section class="maintenance-ticket-link"><div><span>Orden preventiva asociada</span><strong>{{ $ticket->maintenanceOrder->number }} · {{ $ticket->maintenanceOrder->plan->name }}</strong><small>{{ $ticket->maintenanceOrder->equipment->inventory_code }} · Programado para {{ $ticket->maintenanceOrder->scheduled_for->format('d/m/Y') }}</small></div><a href="{{ route('maintenance.orders.show',$ticket->maintenanceOrder) }}" class="button button--outline">Abrir orden →</a></section>
            @endif

            @if($ticket->supplyUsages->isNotEmpty() || auth()->user()->can('reserveSupply',$ticket))
                <section class="panel ticket-supplies-card">
                    <div class="panel__header"><div><span class="panel__eyebrow">Inventario</span><h2>Insumos utilizados</h2></div><span class="count-pill">{{ $ticket->supplyUsages->count() }}</span></div>
                    <div class="ticket-supply-list">@forelse($ticket->supplyUsages as $usage)<article><span class="supply-usage-state supply-usage-state--{{ $usage->status }}">{{ $usage->status_label }}</span><div><strong>{{ $usage->supply->name }}</strong><small>{{ $usage->supply->code }}@if($usage->notes) · {{ $usage->notes }}@endif</small></div><b>{{ number_format($usage->quantity_consumed??$usage->quantity_reserved,2) }} {{ $usage->supply->unit_label }}</b>@if($usage->status==='reserved')@can('reserveSupply',$ticket)<form method="POST" action="{{ route('tickets.supplies.destroy',[$ticket,$usage]) }}">@csrf @method('DELETE')<button class="button button--danger-soft button--small">Liberar</button></form>@endcan @endif</article>@empty<div class="empty-state"><strong>Sin insumos reservados</strong><p>El personal técnico puede reservar materiales durante la atención.</p></div>@endforelse</div>
                    @can('reserveSupply',$ticket)<form method="POST" action="{{ route('tickets.supplies.store',$ticket) }}" class="ticket-supply-form">@csrf<div class="field"><label>Insumo disponible</label><select name="supply_id" required><option value="">Seleccionar</option>@foreach($availableSupplies as $supply)<option value="{{ $supply->id }}">{{ $supply->code }} · {{ $supply->name }} · {{ number_format($supply->availableQuantity(),2) }} disponibles</option>@endforeach</select></div><div class="field"><label>Cantidad</label><input type="number" step="0.01" min="0.01" name="quantity" required></div><div class="field"><label>Uso previsto</label><input name="notes" maxlength="2000" placeholder="Actividad donde será utilizado"></div><button class="button button--primary">Reservar insumo</button></form>@endcan
                    <p class="ticket-supply-note">La reserva no descuenta existencias. El consumo se aplica automáticamente cuando el solicitante confirma la solución.</p>
                </section>
            @endif

            @if ($ticket->approvals->isNotEmpty())
                <section class="panel ticket-approval-card">
                    <div class="panel__header"><div><span class="panel__eyebrow">Seguimiento</span><h2>Aprobaciones</h2></div><span class="ticket-approval-state ticket-approval-state--{{ $ticket->approval_status }}">{{ $ticket->approval_status_label }}</span></div>
                    <div class="ticket-approval-line">
                        @foreach ($ticket->approvals as $approval)
                            <article class="is-{{ $approval->status }}"><span>{{ $approval->status === 'approved' ? '✓' : $approval->level_number }}</span><div><strong>{{ $approval->level_name }}</strong><small>{{ $approval->approver?->name ?: $approval->approverRole?->name ?: 'Responsable según estructura' }}</small>@if ($approval->comment)<p>{{ $approval->comment }}</p>@endif</div><em>{{ $approval->status_label }}</em></article>
                        @endforeach
                    </div>
                    @can('approve', $ticket)
                        @php($pendingApproval = $ticket->approvals->firstWhere('status', 'pending'))
                        @if ($pendingApproval)
                            <form method="POST" action="{{ route('tickets.approvals.decide', [$ticket, $pendingApproval]) }}" class="ticket-inline-form">@csrf
                                <div class="field"><label for="approval_comment">Comentario de decisión</label><textarea id="approval_comment" name="comment" rows="3" maxlength="2000" placeholder="Obligatorio al rechazar o devolver"></textarea></div>
                                <div><button name="decision" value="changes_requested" class="button button--outline">Devolver</button><button name="decision" value="rejected" class="button button--danger-soft">Rechazar</button><button name="decision" value="approved" class="button button--primary">Aprobar nivel</button></div>
                            </form>
                        @endif
                    @endcan
                </section>
            @endif

            <section id="conversation" class="panel ticket-conversation">
                <div class="panel__header"><div><span class="panel__eyebrow">Colaboración</span><h2>Conversación y notas</h2></div><span class="count-pill">{{ $comments->count() }} entradas</span></div>
                <div class="ticket-timeline">
                    @forelse ($comments as $comment)
                        <article class="{{ $comment->is_internal ? 'is-internal' : '' }}">
                            <span class="avatar avatar--small">{{ str($comment->user->name)->substr(0, 1)->upper() }}</span>
                            <div><header><strong>{{ $comment->user->name }}</strong><small>{{ $comment->user->primary_role_name }} · {{ $comment->created_at->diffForHumans() }}</small>@if ($comment->is_internal)<em>Nota interna</em>@else<em>Visible</em>@endif</header><p>{{ $comment->body }}</p>
                                @if ($comment->attachments->isNotEmpty())<footer>@foreach ($comment->attachments as $attachment)<a href="{{ route('tickets.attachments.download', [$ticket, $attachment]) }}">▣ {{ $attachment->original_name }} <small>{{ $attachment->human_size }}</small></a>@endforeach</footer>@endif
                            </div>
                        </article>
                    @empty
                        <div class="empty-state"><strong>Aún no hay conversación</strong><p>Publica el primer comentario para iniciar el seguimiento.</p></div>
                    @endforelse
                </div>
                @can('comment', $ticket)
                    <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" enctype="multipart/form-data" class="ticket-message-form">@csrf
                        <div class="field"><label for="comment_body">Comentario visible</label><textarea id="comment_body" name="body" rows="4" maxlength="10000" placeholder="Escribe una actualización que podrá ver el solicitante y el equipo de IT"></textarea></div>
                        <div class="ticket-message-actions"><label class="ticket-attach-button">＋ Adjuntar<input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip"></label><button class="button button--primary">Publicar comentario</button></div>
                    </form>
                @endcan
            </section>

            @if ($canSeeInternal && $activities->isNotEmpty())
                <section class="panel ticket-activity-card">
                    <div class="panel__header"><div><span class="panel__eyebrow">Trabajo técnico</span><h2>Actividades registradas</h2></div><span class="count-pill">{{ $activities->sum('minutes_spent') }} min</span></div>
                    <div class="ticket-activity-list">@foreach ($activities as $activity)<article><time>{{ $activity->activity_date->format('d M') }}</time><span></span><div><header><strong>{{ $activity->type_label }}</strong><em>{{ $activity->minutes_spent }} min @if($activity->progress_percentage !== null)· {{ $activity->progress_percentage }}%@endif</em></header><p>{{ $activity->description }}</p>@if ($activity->diagnosis)<small><b>Diagnóstico:</b> {{ $activity->diagnosis }}</small>@endif</div></article>@endforeach</div>
                </section>
            @endif

            @if ($ticket->solution)
                <section class="ticket-solution-card">
                    <span>✓</span><div><small>Solución propuesta</small><h2>Solución aplicada</h2><p>{{ $ticket->solution }}</p><footer>Documentada por {{ $ticket->solutionBy?->name }} · {{ $ticket->solved_at?->format('d/m/Y H:i') }}</footer></div>
                </section>
            @endif

            @can('review', $ticket)
                <section class="panel ticket-review-card">
                    <div><span class="panel__eyebrow">Confirmación electrónica</span><h2>¿La solución resolvió tu solicitud?</h2><p>Tu confirmación cerrará el ticket. Si el problema continúa, regresará al equipo técnico.</p></div>
                    <form method="POST" action="{{ route('tickets.review.store', $ticket) }}">@csrf
                        <div class="field"><label for="rating">Calificación del servicio</label><select id="rating" name="rating"><option value="">Seleccionar</option>@for ($rating = 5; $rating >= 1; $rating--)<option value="{{ $rating }}">{{ str_repeat('★', $rating) }} · {{ $rating }}/5</option>@endfor</select></div>
                        <div class="field"><label for="review_comment">Comentario</label><textarea id="review_comment" name="comment" rows="3" maxlength="2000" placeholder="Obligatorio si rechazas la solución"></textarea></div>
                        <label class="ticket-check"><input type="checkbox" name="confirmation" value="1"><span>Confirmo electrónicamente la aceptación del servicio.</span></label>
                        <div><button class="button button--danger-soft" name="decision" value="rejected">No resolvió</button><button class="button button--primary" name="decision" value="accepted">Aceptar y cerrar</button></div>
                    </form>
                </section>
            @endcan
        </div>

        <aside class="ticket-workspace__side">
            <section class="panel ticket-side-card">
                <div class="panel__header"><div><span class="panel__eyebrow">Tiempos objetivo</span><h2>Prioridad y SLA</h2></div></div>
                <div class="ticket-priority-summary"><span style="--ticket-color: {{ $ticket->priority?->color ?? '#888888' }}">{{ $ticket->priority?->code ?? '—' }}</span><div><strong>{{ $ticket->priority?->name ?? 'Por calcular' }}</strong><small>Impacto {{ $ticket->impact?->name ?? '—' }} · Urgencia {{ $ticket->urgency?->name ?? '—' }}</small></div></div>
                <div class="ticket-sla-list">
                    <div><span>Primera respuesta</span><strong class="{{ $ticket->response_due_at?->isPast() && ! $ticket->first_response_at ? 'is-overdue' : '' }}">{{ $ticket->response_due_at?->format('d/m/Y H:i') ?: 'Sin objetivo' }}</strong></div>
                    <div><span>Escalamiento</span><strong>{{ $ticket->escalation_due_at?->format('d/m/Y H:i') ?: 'Sin objetivo' }}</strong></div>
                    <div><span>Resolución</span><strong class="{{ $ticket->resolution_due_at?->isPast() && ! $ticket->solved_at ? 'is-overdue' : '' }}">{{ $ticket->resolution_due_at?->format('d/m/Y H:i') ?: 'Sin objetivo' }}</strong></div>
                </div>
            </section>

            <section class="panel ticket-side-card">
                <div class="panel__header"><div><span class="panel__eyebrow">Gestión técnica</span><h2>Asignación actual</h2></div></div>
                <div class="ticket-assignee"><span class="avatar">{{ str($ticket->currentAssignee?->name ?: $ticket->currentGroup?->name ?: 'S')->substr(0, 1)->upper() }}</span><div><strong>{{ $ticket->currentAssignee?->name ?: $ticket->currentGroup?->name ?: 'Sin asignar' }}</strong><small>{{ $ticket->currentArea?->name ?: 'Pendiente de direccionamiento' }}</small></div></div>
                <dl class="ticket-compact-list"><div><dt>Grupo</dt><dd>{{ $ticket->currentGroup?->name ?: '—' }}</dd></div><div><dt>Respuesta</dt><dd>{{ str($ticket->assignment_status ?: 'sin asignar')->headline() }}</dd></div></dl>
                @php($pendingAssignment = $ticket->assignments->firstWhere('status', 'pending'))
                @if ($pendingAssignment)
                    @can('respondAssignment', $ticket)<form method="POST" action="{{ route('tickets.assignments.respond', [$ticket, $pendingAssignment]) }}" class="ticket-assignment-response">@csrf @method('PATCH')<div class="field"><label for="assignment_reason">Justificación si rechazas</label><textarea id="assignment_reason" name="reason" rows="2"></textarea></div><div><button name="decision" value="rejected" class="button button--danger-soft button--small">Rechazar</button><button name="decision" value="accepted" class="button button--primary button--small">Aceptar</button></div></form>@endcan
                @endif
            </section>

            @if($ticket->thirdPartyDependencies->isNotEmpty() || auth()->user()->can('manageThirdParty',$ticket))
                <section class="panel ticket-side-card third-party-card">
                    <div class="panel__header"><div><span class="panel__eyebrow">Coordinación externa</span><h2>Dependencias con terceros</h2></div><span class="count-pill">{{ $ticket->thirdPartyDependencies->count() }}</span></div>
                    <div class="third-party-list">
                        @foreach($ticket->thirdPartyDependencies as $dependency)
                            <article class="is-{{ $dependency->status }}"><div><span>{{ $dependency->status==='pending'?'Pendiente':'Resuelta' }}</span><strong>{{ $dependency->provider }}</strong><small>{{ $dependency->reference ?: 'Sin referencia' }} · {{ $dependency->requested_at->format('d/m/Y H:i') }}</small><p>{{ $dependency->description }}</p>@if($dependency->resolution)<em>{{ $dependency->resolution }}</em>@endif</div>
                            @if($dependency->status==='pending')@can('manageThirdParty',$ticket)<details><summary>Resolver</summary><form method="POST" action="{{ route('tickets.third-parties.resolve',[$ticket,$dependency]) }}">@csrf @method('PATCH')<textarea name="resolution" rows="3" required minlength="10" placeholder="Resultado recibido del proveedor"></textarea><button class="button button--primary button--small">Confirmar resolución</button></form></details>@endcan @endif</article>
                        @endforeach
                    </div>
                    @can('manageThirdParty',$ticket)<details class="ticket-card-form"><summary>＋ Registrar dependencia</summary><form method="POST" action="{{ route('tickets.third-parties.store',$ticket) }}">@csrf<div class="field"><label>Proveedor o tercero</label><input name="provider" required maxlength="180"></div><div class="field"><label>Referencia</label><input name="reference" maxlength="120"></div><div class="field"><label>Fecha compromiso</label><input type="datetime-local" name="due_at"></div><div class="field"><label>Gestión requerida</label><textarea name="description" rows="3" required minlength="10"></textarea></div><button class="button button--primary button--full">Registrar y pausar ticket</button></form></details>@endcan
                </section>
            @endif

            @can('escalate',$ticket)
                <details class="panel ticket-action-panel ticket-escalation-panel"><summary><span>↑</span><strong>Escalar atención</strong></summary><form method="POST" action="{{ route('tickets.escalations.store',$ticket) }}">@csrf<div class="field"><label>Nivel</label><select name="level" required>@foreach(App\Models\TicketEscalation::LEVELS as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div><div class="field"><label>Justificación</label><textarea name="reason" rows="3" required minlength="10"></textarea></div><button class="button button--primary button--full">Escalar y notificar</button></form></details>
            @endcan

            @if($ticket->escalations->isNotEmpty())
                <section class="panel ticket-side-card"><div class="panel__header"><div><span class="panel__eyebrow">Trazabilidad</span><h2>Escalamientos</h2></div></div><div class="escalation-list">@foreach($ticket->escalations as $escalation)<article><span>{{ $escalation->level_label }}</span><strong>{{ $escalation->escalatedTo?->name }}</strong><p>{{ $escalation->reason }}</p><small>{{ $escalation->escalated_at->format('d/m/Y H:i') }} · {{ str($escalation->source)->headline() }}</small></article>@endforeach</div></section>
            @endif

            @canany(['assign', 'transfer'], $ticket)
                <details class="panel ticket-action-panel"><summary><span>↗</span><strong>{{ $ticket->current_it_area_id ? 'Reasignar o transferir' : 'Asignar ticket' }}</strong></summary>
                    <form method="POST" action="{{ $ticket->current_it_area_id ? route('tickets.assignments.transfer', $ticket) : route('tickets.assignments.store', $ticket) }}" data-assignment-form>@csrf
                        <div class="field"><label>Área de IT</label><select name="area_id" data-it-area-select><option value="">Seleccionar</option>@foreach ($itAreas as $area)<option value="{{ $area->id }}">{{ $area->name }}</option>@endforeach</select></div>
                        <div class="field"><label>Grupo técnico</label><select name="group_id" data-it-group-select><option value="">Sin grupo</option>@foreach ($groups as $group)<option value="{{ $group->id }}" data-area="{{ $group->area_id }}">{{ $group->name }}</option>@endforeach</select></div>
                        <div class="field"><label>Responsable</label><select name="assignee_id"><option value="">Asignar solo al grupo</option>@foreach ($technicians as $technician)<option value="{{ $technician->id }}">{{ $technician->name }} · {{ $technician->primary_role_name }}</option>@endforeach</select></div>
                        <div class="field"><label>Motivo</label><textarea name="reason" rows="3" required minlength="5"></textarea></div>
                        <button class="button button--primary button--full">Confirmar asignación</button>
                    </form>
                </details>
            @endcanany

            @can('changeStatus', $ticket)
                <details class="panel ticket-action-panel"><summary><span>↻</span><strong>Cambiar estado operativo</strong></summary><form method="POST" action="{{ route('tickets.status', $ticket) }}">@csrf @method('PATCH')<div class="field"><label>Nuevo estado</label><select name="ticket_status_id" required><option value="">Seleccionar</option>@foreach ($availableStatuses as $status)<option value="{{ $status->id }}">{{ $status->name }}</option>@endforeach</select></div><div class="field"><label>Motivo</label><textarea name="reason" rows="3" required minlength="5"></textarea></div><button class="button button--primary button--full">Actualizar estado</button></form></details>
            @endcan

            @can('internalNote', $ticket)
                <details class="panel ticket-action-panel ticket-action-panel--internal"><summary><span>◆</span><strong>Agregar nota interna</strong></summary><form method="POST" action="{{ route('tickets.notes.store', $ticket) }}" enctype="multipart/form-data">@csrf<div class="field"><label>Nota privada para IT</label><textarea name="body" rows="4" required maxlength="10000"></textarea></div><div class="field"><label>Evidencias internas</label><input type="file" name="attachments[]" multiple></div><button class="button button--primary button--full">Guardar nota privada</button></form></details>
            @endcan

            @can('logActivity', $ticket)
                <details class="panel ticket-action-panel"><summary><span>◷</span><strong>Registrar actividad</strong></summary><form method="POST" action="{{ route('tickets.activities.store', $ticket) }}" enctype="multipart/form-data">@csrf<div class="form-grid form-grid--two"><div class="field"><label>Fecha</label><input type="date" name="activity_date" value="{{ now()->format('Y-m-d') }}" required></div><div class="field"><label>Tipo</label><select name="activity_type" required>@foreach (App\Models\TicketActivity::TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div><div class="field"><label>Minutos</label><input type="number" name="minutes_spent" value="0" min="0" max="1440" required></div><div class="field"><label>Avance %</label><input type="number" name="progress_percentage" min="0" max="100"></div></div><div class="field"><label>Actividad realizada</label><textarea name="description" rows="3" required></textarea></div><div class="field"><label>Diagnóstico</label><textarea name="diagnosis" rows="3"></textarea></div><div class="field"><label>Evidencias</label><input type="file" name="attachments[]" multiple></div><button class="button button--primary button--full">Registrar actividad</button></form></details>
            @endcan

            @can('solve', $ticket)
                <details class="panel ticket-action-panel ticket-action-panel--solution"><summary><span>✓</span><strong>Registrar solución</strong></summary><form method="POST" action="{{ route('tickets.solution.store', $ticket) }}" enctype="multipart/form-data">@csrf<div class="field"><label>Solución aplicada</label><textarea name="solution" rows="5" required minlength="20" maxlength="15000">{{ $ticket->solution }}</textarea></div><div class="field"><label>Evidencias finales</label><input type="file" name="attachments[]" multiple></div><button class="button button--primary button--full">Enviar solución</button></form></details>
            @endcan

            @if ($attachments->isNotEmpty())
                <section class="panel ticket-side-card"><div class="panel__header"><div><span class="panel__eyebrow">Adjuntos privados</span><h2>Archivos</h2></div><span class="count-pill">{{ $attachments->count() }}</span></div><div class="ticket-files">@foreach ($attachments as $attachment)<a href="{{ route('tickets.attachments.download', [$ticket, $attachment]) }}"><span>▣</span><div><strong>{{ $attachment->original_name }}</strong><small>{{ $attachment->human_size }} · {{ $attachment->uploadedBy->name }}</small></div>@if($attachment->is_internal)<em>Interno</em>@endif</a>@endforeach</div></section>
            @endif

            @can('relate', $ticket)
                <details class="panel ticket-action-panel"><summary><span>∞</span><strong>Relacionar ticket</strong></summary><form method="POST" action="{{ route('tickets.relations.store', $ticket) }}">@csrf<div class="field"><label>Ticket</label><select name="related_ticket_id" required><option value="">Seleccionar</option>@foreach ($relatedCandidates as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->number }} · {{ str($candidate->title)->limit(45) }}</option>@endforeach</select></div><div class="field"><label>Relación</label><select name="type" required>@foreach (App\Models\TicketRelation::TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div><div class="field"><label>Nota</label><textarea name="note" rows="2"></textarea></div><button class="button button--primary button--full">Vincular</button></form></details>
            @endcan

            @if ($relations->isNotEmpty())
                <section class="panel ticket-side-card"><div class="panel__header"><div><span class="panel__eyebrow">Casos vinculados</span><h2>Tickets relacionados</h2></div></div><div class="ticket-relations">@foreach ($relations as $relation) @php($other = $relation->ticket_id === $ticket->id ? $relation->relatedTicket : $relation->ticket)<a href="{{ route('tickets.show', $other) }}"><span>{{ $relation->type_label }}</span><strong>{{ $other->number }}</strong><small>{{ str($other->title)->limit(45) }}</small></a>@endforeach</div></section>
            @endif

            @can('reopen', $ticket)<details class="panel ticket-action-panel"><summary><span>↺</span><strong>Reabrir ticket</strong></summary><form method="POST" action="{{ route('tickets.reopen', $ticket) }}" data-confirm="¿Reabrir este ticket?">@csrf @method('PATCH')<div class="field"><label>Motivo</label><textarea name="reason" rows="3" minlength="10" required></textarea></div><button class="button button--primary button--full">Reabrir atención</button></form></details>@endcan
            @can('cancel', $ticket)<details class="panel ticket-action-panel ticket-action-panel--danger"><summary><span>×</span><strong>Cancelar ticket</strong></summary><form method="POST" action="{{ route('tickets.cancel', $ticket) }}" data-confirm="¿Confirmas la cancelación de este ticket?">@csrf @method('PATCH')<div class="field"><label>Justificación</label><textarea name="reason" rows="3" minlength="10" required></textarea></div><button class="button button--danger-soft button--full">Cancelar ticket</button></form></details>@endcan
        </aside>
    </div>

    <section class="panel ticket-history-panel">
        <div class="panel__header"><div><span class="panel__eyebrow">Auditoría inmutable</span><h2>Historial del ticket</h2></div><span class="count-pill">Últimos {{ $histories->count() }}</span></div>
        <div class="ticket-history-list">@forelse ($histories as $entry)<article><time>{{ $entry->created_at->format('d/m/Y') }}<small>{{ $entry->created_at->format('H:i') }}</small></time><span></span><div><strong>{{ $entry->description }}</strong><small>{{ $entry->user?->name ?: 'Sistema' }} · {{ str($entry->event)->replace('_', ' ')->headline() }}</small></div></article>@empty<div class="empty-state"><strong>Sin movimientos registrados</strong></div>@endforelse</div>
    </section>
@endsection
