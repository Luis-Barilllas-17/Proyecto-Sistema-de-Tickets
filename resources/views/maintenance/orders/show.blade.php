@extends('layouts.app')

@section('title', $order->number.' · Orden preventiva')
@section('section-title', 'Mantenimiento preventivo')

@section('content')
    @php($isLate = $order->scheduled_for->isPast() && !in_array($order->status,['completed','cancelled']))
    <header class="maintenance-order-header">
        <div><a href="{{ route('maintenance.index') }}" class="back-link"><span>←</span> Calendario</a><span class="eyebrow">Orden de trabajo</span><h1>{{ $order->number }}</h1><p>{{ $order->plan->name }}</p></div>
        <div><span class="maintenance-status maintenance-status--{{ $isLate ? 'overdue' : $order->status }}">{{ $isLate ? 'Vencido' : $order->status_label }}</span>@if($order->ticket)<a href="{{ route('tickets.show',$order->ticket) }}" class="button button--outline">Ticket {{ $order->ticket->number }}</a>@endif</div>
    </header>

    <section class="maintenance-order-overview">
        <article class="maintenance-order-asset"><span>{{ $order->equipment->inventory_code }}</span><div><strong>{{ $order->equipment->name }}</strong><small>{{ $order->equipment->brand }} {{ $order->equipment->model }}</small></div><a href="{{ route('inventory.equipment.show',$order->equipment) }}">Ver activo →</a></article>
        <article><span>Fecha programada</span><strong>{{ $order->scheduled_for->format('d/m/Y') }}</strong><small>{{ $order->scheduled_start_at?->format('H:i') ?: 'Horario por coordinar' }}</small></article>
        <article><span>Ubicación</span><strong>{{ $order->equipment->location }}</strong><small>{{ $order->equipment->department?->name ?: $order->equipment->area?->name }}</small></article>
        <article><span>Técnico responsable</span><strong>{{ $order->assignedTo?->name ?: 'Por asignar' }}</strong><small>{{ $order->assignedTo?->job_title }}</small></article>
    </section>

    @if(in_array($order->status,['scheduled','work_order_generated','overdue']))
        @can('execute',$order)<section class="maintenance-start-callout"><div><span>Listo para ejecución</span><h2>Iniciar mantenimiento preventivo</h2><p>El equipo cambiará temporalmente a estado “En mantenimiento” y se registrará el inicio en su historial.</p></div><form method="POST" action="{{ route('maintenance.orders.start',$order) }}" data-confirm="¿Iniciar esta orden preventiva?">@csrf<button class="button button--primary">Iniciar orden →</button></form></section>@endcan
    @endif

    <div class="maintenance-order-layout">
        <section class="panel maintenance-execution">
            <div class="panel__header"><div><span class="panel__eyebrow">RF-066</span><h2>Lista de verificación</h2></div><span class="count-pill">{{ $order->items->where('status','completed')->count() }}/{{ $order->items->count() }}</span></div>

            @if($order->status === 'in_progress')
                @can('execute',$order)
                    <form method="POST" action="{{ route('maintenance.orders.complete',$order) }}" enctype="multipart/form-data" class="maintenance-execution-form">@csrf
                        <div class="maintenance-checklist-run">
                            @foreach($order->items as $item)
                                <article><span class="maintenance-checklist-run__number">{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><div><strong>{{ $item->label }}</strong><div class="maintenance-checklist-run__controls"><select name="items[{{ $item->id }}][status]" required><option value="">Seleccionar resultado</option><option value="completed" @selected(old("items.$item->id.status",$item->status)==='completed')>Realizado</option><option value="not_applicable" @selected(old("items.$item->id.status",$item->status)==='not_applicable')>No aplica</option></select><input name="items[{{ $item->id }}][observation]" value="{{ old("items.$item->id.observation",$item->observation) }}" maxlength="2000" placeholder="Observación o justificación"></div></div></article>
                            @endforeach
                        </div>
                        <div class="maintenance-completion-grid">
                            <div class="field"><label>Resultado final <strong>*</strong></label><select name="result" required><option value="">Seleccionar</option>@foreach(App\Models\MaintenanceOrder::RESULTS as $value=>$label)<option value="{{ $value }}" @selected(old('result')===$value)>{{ $label }}</option>@endforeach</select></div>
                            <div class="field field--wide"><label>Observaciones técnicas <strong>*</strong></label><textarea name="observations" rows="4" minlength="10" maxlength="10000" required>{{ old('observations') }}</textarea></div>
                            <div class="field"><label for="maintenance-evidences">Evidencias <strong>*</strong></label><input id="maintenance-evidences" type="file" name="evidences[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple><span class="field__hint">Hasta 5 archivos, 5 MB cada uno.</span></div>
                            <div class="field"><label>Descripción de evidencias</label><input name="evidence_description" maxlength="1000" value="{{ old('evidence_description') }}"></div>
                        </div>
                        <button class="button button--primary button--full">Completar y documentar mantenimiento</button>
                    </form>
                @endcan
            @else
                <div class="maintenance-checklist-readonly">@foreach($order->items as $item)<article><span class="check-state check-state--{{ $item->status }}">{{ $item->status==='completed'?'✓':($item->status==='not_applicable'?'—':'○') }}</span><div><strong>{{ $item->label }}</strong>@if($item->observation)<p>{{ $item->observation }}</p>@endif</div><small>{{ App\Models\MaintenanceOrderItem::STATUSES[$item->status] }}</small></article>@endforeach</div>
            @endif
        </section>

        <aside class="maintenance-order-side">
            <section class="panel maintenance-order-card"><div class="panel__header"><div><span class="panel__eyebrow">Plan</span><h2>Programación</h2></div></div><dl><div><dt>Código</dt><dd><a href="{{ route('maintenance.plans.show',$order->plan) }}">{{ $order->plan->code }}</a></dd></div><div><dt>Frecuencia</dt><dd>{{ $order->plan->frequency_label }}</dd></div><div><dt>Generada</dt><dd>{{ $order->generated_at?->format('d/m/Y H:i') }}</dd></div><div><dt>Inicio</dt><dd>{{ $order->started_at?->format('d/m/Y H:i') ?: 'Pendiente' }}</dd></div><div><dt>Finalización</dt><dd>{{ $order->completed_at?->format('d/m/Y H:i') ?: 'Pendiente' }}</dd></div></dl></section>

            <section class="panel maintenance-order-card"><div class="panel__header"><div><span class="panel__eyebrow">Evidencias privadas</span><h2>Archivos</h2></div><span class="count-pill">{{ $order->evidences->count() }}</span></div><div class="maintenance-evidence-list">@forelse($order->evidences as $evidence)<a href="{{ route('maintenance.orders.evidences.download',[$order,$evidence]) }}"><span>▣</span><div><strong>{{ $evidence->original_name }}</strong><small>{{ $evidence->human_size }} · {{ $evidence->uploadedBy->name }}</small>@if($evidence->description)<p>{{ $evidence->description }}</p>@endif</div></a>@empty<div class="empty-state empty-state--compact"><strong>Sin evidencias</strong><p>Se agregarán al completar la orden.</p></div>@endforelse</div></section>

            @if($order->status==='completed')<section class="panel maintenance-result-card maintenance-result-card--{{ $order->result }}"><span>Resultado final</span><h2>{{ $order->result_label }}</h2><p>{{ $order->observations }}</p><small>Documentado por {{ $order->completedBy?->name }} · {{ $order->completed_at?->format('d/m/Y H:i') }}</small></section>@endif
        </aside>
    </div>
@endsection
