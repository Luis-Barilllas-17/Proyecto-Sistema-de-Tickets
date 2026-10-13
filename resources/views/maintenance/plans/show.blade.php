@extends('layouts.app')

@section('title', $plan->code.' · Plan preventivo')
@section('section-title', 'Mantenimiento preventivo')

@section('content')
    <header class="maintenance-detail-header">
        <div><a href="{{ route('maintenance.index') }}" class="back-link"><span>←</span> Calendario</a><span class="eyebrow">{{ $plan->code }}</span><h1>{{ $plan->name }}</h1><p>{{ $plan->description }}</p></div>
        <div><span class="maintenance-plan-state {{ $plan->is_active ? 'is-active':'' }}">{{ $plan->is_active ? 'Plan activo':'Plan inactivo' }}</span>@can('update',$plan)<a href="{{ route('maintenance.plans.edit',$plan) }}" class="button button--primary">Editar plan</a>@endcan</div>
    </header>

    <section class="maintenance-plan-summary">
        <article><span>Frecuencia</span><strong>{{ $plan->frequency_label }}</strong><small>Cada {{ $plan->frequency_months }} mes(es)</small></article>
        <article><span>Generación anticipada</span><strong>{{ $plan->lead_days }} días</strong><small>Antes del vencimiento</small></article>
        <article><span>Duración estimada</span><strong>{{ $plan->duration_minutes }} min</strong><small>Por equipo</small></article>
        <article><span>Responsable</span><strong>{{ $plan->assignedUser?->name ?: $plan->group?->name ?: 'Por direccionamiento' }}</strong><small>{{ $plan->area?->name }}</small></article>
    </section>

    <div class="maintenance-detail-grid">
        <section class="panel maintenance-assignment-panel">
            <div class="panel__header"><div><span class="panel__eyebrow">RF-064 · RF-065</span><h2>Equipos programados</h2></div><span class="count-pill">{{ $plan->assignments->where('is_active',true)->count() }}</span></div>
            <div class="maintenance-assignment-list">
                @foreach($plan->assignments as $assignment)
                    <article class="{{ $assignment->is_active ? '' : 'is-inactive' }}">
                        <div class="maintenance-assignment-list__asset"><span>{{ $assignment->equipment->inventory_code }}</span><strong>{{ $assignment->equipment->name }}</strong><small>{{ $assignment->equipment->department?->name ?: $assignment->equipment->area?->name }} · {{ $assignment->equipment->location }}</small></div>
                        <div><span>Próxima fecha</span><strong>{{ $assignment->next_due_date->format('d/m/Y') }}</strong><small>@if($assignment->last_completed_on)Último: {{ $assignment->last_completed_on->format('d/m/Y') }}@else Sin ejecución previa @endif</small></div>
                        @can('schedule',$plan)
                            <details><summary>Programar</summary><form method="POST" action="{{ route('maintenance.plans.assignments.update',[$plan,$assignment]) }}">@csrf @method('PATCH')<div class="field"><label for="assignment-{{ $assignment->id }}-date">Próxima fecha</label><input id="assignment-{{ $assignment->id }}-date" type="date" name="next_due_date" value="{{ $assignment->next_due_date->toDateString() }}" required></div><div class="field"><label for="assignment-{{ $assignment->id }}-notes">Nota</label><input id="assignment-{{ $assignment->id }}-notes" name="notes" value="{{ $assignment->notes }}" maxlength="1000"></div><label class="switch-field switch-field--small" for="assignment-{{ $assignment->id }}-active"><input type="hidden" name="is_active" value="0"><input id="assignment-{{ $assignment->id }}-active" type="checkbox" name="is_active" value="1" @checked($assignment->is_active)><span></span><div><strong>Programación activa</strong></div></label><button class="button button--primary button--small">Guardar fecha</button></form></details>
                        @endcan
                        @can('generate',App\Models\MaintenanceOrder::class)<form method="POST" action="{{ route('maintenance.plans.assignments.generate',[$plan,$assignment]) }}">@csrf<button class="table-action">Generar orden →</button></form>@endcan
                    </article>
                @endforeach
            </div>
        </section>

        <aside class="panel maintenance-checklist-preview">
            <div class="panel__header"><div><span class="panel__eyebrow">RF-066</span><h2>Lista maestra</h2></div></div>
            <ol>@foreach($plan->checklistItems as $item)<li><span>{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><p>{{ $item->label }}</p></li>@endforeach</ol>
        </aside>
    </div>

    <section class="panel maintenance-order-history">
        <div class="panel__header"><div><span class="panel__eyebrow">Trazabilidad</span><h2>Órdenes del plan</h2></div><span class="count-pill">{{ $plan->orders->count() }}</span></div>
        <div class="inventory-table-wrap"><table class="inventory-table"><thead><tr><th>Orden</th><th>Equipo</th><th>Fecha</th><th>Responsable</th><th>Estado</th><th>Ticket</th><th></th></tr></thead><tbody>
            @forelse($plan->orders as $order)<tr><td><strong>{{ $order->number }}</strong></td><td><strong>{{ $order->equipment->inventory_code }}</strong><small>{{ $order->equipment->name }}</small></td><td>{{ $order->scheduled_for->format('d/m/Y') }}</td><td>{{ $order->assignedTo?->name ?: 'Por asignar' }}</td><td><span class="maintenance-status maintenance-status--{{ $order->status }}">{{ $order->status_label }}</span></td><td>@if($order->ticket)<a href="{{ route('tickets.show',$order->ticket) }}">{{ $order->ticket->number }}</a>@endif</td><td><a class="table-action" href="{{ route('maintenance.orders.show',$order) }}">Abrir →</a></td></tr>@empty<tr><td colspan="7"><div class="empty-state"><strong>Sin órdenes generadas</strong><p>Las órdenes aparecerán según la anticipación configurada.</p></div></td></tr>@endforelse
        </tbody></table></div>
    </section>
@endsection
