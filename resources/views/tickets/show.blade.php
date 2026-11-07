@extends('layouts.app')

@section('title', $ticket->number)
@section('section-title', 'Detalle y seguimiento del ticket')

@section('content')
    @if($ticket->isTerminal())<div class="ticket-readonly" role="status"><strong>{{ $ticket->status->name }} · Solo lectura</strong><span>{{ $ticket->status->slug === 'cerrado' ? 'Para realizar cambios, primero debes reabrir el ticket con autorización.' : 'Este ticket ya no admite modificaciones.' }}</span></div>@endif
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
                    @include('tickets.partials.supply-usages')
                    @can('reserveSupply',$ticket)
                        <form method="POST" action="{{ route('tickets.supplies.store',$ticket) }}" class="ticket-supply-form">@csrf
                            <x-search-picker id="ticket-supply" name="supply_id" label="Insumo disponible" :endpoint="route('tickets.lookups.supplies', $ticket)" :selected="$selectedSupplies" placeholder="Buscar código o nombre…" />
                            <div class="field"><label for="ticket-supply-quantity">Cantidad</label><input id="ticket-supply-quantity" type="number" step="1" min="1" max="9999999999" inputmode="numeric" name="quantity" value="{{ old('quantity') }}" required aria-label="Cantidad de unidades enteras"></div>
                            <div class="field ticket-supply-purpose"><label class="sr-only" for="ticket-supply-notes">Uso previsto</label><input id="ticket-supply-notes" name="notes" value="{{ old('notes') }}" maxlength="2000" placeholder="Uso previsto (opcional)"></div>
                            <button class="button button--primary">Reservar insumo</button>
                        </form>
                    @endcan
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
                                @if ($comment->attachments->isNotEmpty())<footer>@foreach ($comment->attachments as $attachment)<a href="{{ route('tickets.attachments.download', [$ticket, $attachment]) }}" data-attachment-preview="{{ route('tickets.attachments.preview', [$ticket, $attachment]) }}" data-attachment-name="{{ $attachment->original_name }}" aria-haspopup="dialog">▣ {{ $attachment->original_name }} <small>{{ $attachment->human_size }} · Ver archivo</small></a>@endforeach</footer>@endif
                            </div>
                        </article>
                    @empty
                        <div class="empty-state"><strong>Aún no hay conversación</strong><p>Publica el primer comentario para iniciar el seguimiento.</p></div>
                    @endforelse
                </div>
                @canany(['comment', 'internalNote'], $ticket)
                    @php($privateMessage = old('is_internal') || ! auth()->user()->can('comment', $ticket))
                    <form method="POST" action="{{ auth()->user()->can('comment', $ticket) ? route('tickets.comments.store', $ticket) : route('tickets.notes.store', $ticket) }}" enctype="multipart/form-data" class="ticket-message-form" data-message-composer>
                        @csrf
                        <div class="field"><label for="comment_body" data-message-label>{{ $privateMessage ? 'Nota interna para IT' : 'Comentario visible' }}</label><textarea id="comment_body" name="body" rows="3" required minlength="2" maxlength="10000" aria-describedby="message-visibility-help" placeholder="Escribe una actualización del ticket">{{ old('body') }}</textarea></div>
                        <div class="ticket-message-actions">
                            <label class="ticket-attach-button" for="comment-attachments">＋ Adjuntar<input id="comment-attachments" type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip"></label>
                            @can('internalNote', $ticket)<label class="ticket-message-visibility"><input type="checkbox" name="is_internal" value="1" role="switch" @checked($privateMessage) @disabled(! auth()->user()->can('comment', $ticket)) data-message-private><span aria-hidden="true"></span>Nota interna</label>@endcan
                            <button class="button button--primary" data-message-submit>{{ $privateMessage ? 'Guardar nota interna' : 'Publicar comentario' }}</button>
                        </div>
                        <p id="message-visibility-help" data-message-help role="status">{{ $privateMessage ? 'Solo el personal de IT autorizado podrá ver esta nota y sus adjuntos.' : 'El solicitante y el equipo de IT podrán ver este comentario y sus adjuntos.' }}</p>
                    </form>
                @endcanany
            </section>

            @if ($canSeeInternal && $activities->isNotEmpty())
                <section class="panel ticket-activity-card">
                    <div class="panel__header"><div><span class="panel__eyebrow">Trabajo técnico</span><h2>Actividades registradas</h2></div><span class="count-pill">{{ $activities->sum('minutes_spent') }} min</span></div>
                    <div class="ticket-activity-list">@foreach ($activities as $activity)<article><time>{{ $activity->activity_date->format('d M') }}</time><span></span><div><header><strong>{{ $activity->type_label }}</strong><em>{{ $activity->minutes_spent }} min @if($activity->progress_percentage !== null)· {{ $activity->progress_percentage }}%@endif</em></header><p>{{ $activity->description }}</p>@if ($activity->diagnosis)<small><b>Diagnóstico:</b> {{ $activity->diagnosis }}</small>@endif</div></article>@endforeach</div>
                </section>
            @endif

            @include('tickets.partials.solution')

            @can('review', $ticket)
                <section class="panel ticket-review-card">
                    <div><span class="panel__eyebrow">Confirmación electrónica</span><h2>¿La solución resolvió tu solicitud?</h2><p>Tu confirmación cerrará el ticket. Si el problema continúa, regresará al equipo técnico.</p></div>
                    <form method="POST" action="{{ route('tickets.review.store', $ticket) }}">@csrf
                        <fieldset class="ticket-rating" data-star-rating><legend>Calificación del servicio</legend><div class="ticket-rating__stars">
                            @for ($rating = 1; $rating <= 5; $rating++)
                                <label class="ticket-rating__option"><input type="radio" name="rating" value="{{ $rating }}" @checked((string) old('rating') === (string) $rating)><span aria-hidden="true">★</span><span class="sr-only">{{ $rating }} {{ $rating === 1 ? 'estrella' : 'estrellas' }} de 5</span></label>
                            @endfor
                        </div><p data-rating-status aria-live="polite">{{ old('rating') ? old('rating').' de 5 estrellas' : 'Selecciona de 1 a 5 estrellas' }}</p></fieldset>
                        <div class="field"><label for="review_comment">Comentario</label><textarea id="review_comment" name="comment" rows="3" maxlength="2000" placeholder="Obligatorio si rechazas la solución">{{ old('comment') }}</textarea></div>
                        <label class="ticket-check" for="service-confirmation"><input id="service-confirmation" type="checkbox" name="confirmation" value="1" @checked(old('confirmation'))><span>Confirmo electrónicamente la aceptación del servicio.</span></label>
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

            @include('tickets.partials.assignment')

            @include('tickets.partials.files')

            @can('escalate',$ticket)
                <details class="panel ticket-action-panel ticket-escalation-panel"><summary><span>↑</span><strong>Escalar atención</strong></summary><form method="POST" action="{{ route('tickets.escalations.store',$ticket) }}">@csrf<div class="field"><label for="ticket-escalation-level">Nivel</label><select id="ticket-escalation-level" name="level" required>@foreach(App\Models\TicketEscalation::LEVELS as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div><div class="field"><label for="ticket-escalation-reason">Justificación</label><textarea id="ticket-escalation-reason" name="reason" rows="3" required minlength="10"></textarea></div><button class="button button--primary button--full">Escalar y notificar</button></form></details>
            @endcan

            @if($ticket->escalations->isNotEmpty())
                <section class="panel ticket-side-card"><div class="panel__header"><div><span class="panel__eyebrow">Trazabilidad</span><h2>Escalamientos</h2></div></div><div class="escalation-list">@foreach($ticket->escalations as $escalation)<article><span>{{ $escalation->level_label }}</span><strong>{{ $escalation->escalatedTo?->name }}</strong><p>{{ $escalation->reason }}</p><small>{{ $escalation->escalated_at->format('d/m/Y H:i') }} · {{ str($escalation->source)->headline() }}</small></article>@endforeach</div></section>
            @endif



            @can('changeStatus', $ticket)
                <details class="panel ticket-action-panel"><summary><span>↻</span><strong>Cambiar estado operativo</strong></summary><form method="POST" action="{{ route('tickets.status', $ticket) }}">@csrf @method('PATCH')<div class="field"><label for="ticket-new-status">Nuevo estado</label><select id="ticket-new-status" name="ticket_status_id" required><option value="">Seleccionar</option>@foreach ($availableStatuses as $status)<option value="{{ $status->id }}">{{ $status->name }}</option>@endforeach</select></div><div class="field"><label for="ticket-status-reason">Motivo</label><textarea id="ticket-status-reason" name="reason" rows="3" required minlength="5"></textarea></div><button class="button button--primary button--full">Actualizar estado</button></form></details>
            @endcan



            @can('logActivity', $ticket)
                <details class="panel ticket-action-panel"><summary><span>◷</span><strong>Registrar actividad</strong></summary><form method="POST" action="{{ route('tickets.activities.store', $ticket) }}" enctype="multipart/form-data">@csrf<div class="form-grid form-grid--two"><div class="field"><label for="activity-date">Fecha</label><input id="activity-date" type="date" name="activity_date" value="{{ now()->format('Y-m-d') }}" required></div><div class="field"><label for="activity-type">Tipo</label><select id="activity-type" name="activity_type" required>@foreach (App\Models\TicketActivity::TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div><div class="field"><label for="activity-minutes">Minutos</label><input id="activity-minutes" type="number" name="minutes_spent" value="0" min="0" max="1440" required></div><div class="field"><label for="activity-progress">Avance %</label><input id="activity-progress" type="number" name="progress_percentage" min="0" max="100"></div></div><div class="field"><label for="activity-description">Actividad realizada</label><textarea id="activity-description" name="description" rows="3" required></textarea></div><div class="field"><label for="activity-diagnosis">Diagnóstico</label><textarea id="activity-diagnosis" name="diagnosis" rows="3"></textarea></div><div class="field"><label for="activity-files">Evidencias</label><input id="activity-files" type="file" name="attachments[]" multiple></div><button class="button button--primary button--full">Registrar actividad</button></form></details>
            @endcan



            @include('tickets.partials.third-parties')

            @can('relate', $ticket)
                <details class="panel ticket-action-panel"><summary><span>∞</span><strong>Relacionar ticket</strong></summary><form method="POST" action="{{ route('tickets.relations.store', $ticket) }}">@csrf<x-search-picker id="related-ticket" name="related_ticket_id" label="Ticket relacionado" :endpoint="route('tickets.lookups.related', $ticket)" :selected="$selectedRelatedTickets" placeholder="Buscar por número o asunto…" /><div class="field"><label for="ticket-relation-type">Relación</label><select id="ticket-relation-type" name="type" required>@foreach (App\Models\TicketRelation::TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div><div class="field"><label for="ticket-relation-note">Nota</label><textarea id="ticket-relation-note" name="note" rows="2"></textarea></div><button class="button button--primary button--full">Vincular</button></form></details>
            @endcan

            @if ($relations->isNotEmpty())
                <section class="panel ticket-side-card"><div class="panel__header"><div><span class="panel__eyebrow">Casos vinculados</span><h2>Tickets relacionados</h2></div></div><div class="ticket-relations">@foreach ($relations as $relation) @php($other = $relation->ticket_id === $ticket->id ? $relation->relatedTicket : $relation->ticket)<a href="{{ route('tickets.show', $other) }}"><span>{{ $relation->type_label }}</span><strong>{{ $other->number }}</strong><small>{{ str($other->title)->limit(45) }}</small></a>@endforeach</div></section>
            @endif

            @include('tickets.partials.reopen')
            @can('cancel', $ticket)<details class="panel ticket-action-panel ticket-action-panel--danger"><summary><span>×</span><strong>Cancelar ticket</strong></summary><form method="POST" action="{{ route('tickets.cancel', $ticket) }}" data-confirm="¿Confirmas la cancelación de este ticket?">@csrf @method('PATCH')<div class="field"><label for="ticket-cancel-reason">Justificación</label><textarea id="ticket-cancel-reason" name="reason" rows="3" minlength="10" required></textarea></div><button class="button button--danger-soft button--full">Cancelar ticket</button></form></details>@endcan
        </aside>
    </div>

    <section class="panel ticket-history-panel">
        <div class="panel__header"><div><span class="panel__eyebrow">Auditoría inmutable</span><h2>Historial del ticket</h2></div><span class="count-pill">Últimos {{ $histories->count() }}</span></div>
        <div class="ticket-history-list">@forelse ($histories as $entry)<article><time>{{ $entry->created_at->format('d/m/Y') }}<small>{{ $entry->created_at->format('H:i') }}</small></time><span></span><div><strong>{{ $entry->description }}</strong><small>{{ $entry->user?->name ?: 'Sistema' }} · {{ str($entry->event)->replace('_', ' ')->headline() }}</small></div></article>@empty<div class="empty-state"><strong>Sin movimientos registrados</strong></div>@endforelse</div>
    </section>
    <dialog class="ticket-preview" data-attachment-dialog aria-labelledby="attachment-preview-title">
        <header class="ticket-preview__header"><div><span class="panel__eyebrow">Vista previa privada</span><h2 id="attachment-preview-title">Archivo adjunto</h2></div><button type="button" class="ticket-preview__close" data-preview-close aria-label="Cerrar vista previa">×</button></header>
        <div class="ticket-preview__body" data-preview-body aria-busy="false"></div>
        <footer><span>El archivo original no se modifica.</span><a class="button button--primary button--small" data-preview-download>Descargar original</a></footer>
    </dialog>
@endsection
