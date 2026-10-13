@extends('layouts.app')

@section('title', 'Mantenimiento preventivo')
@section('section-title', 'Mantenimiento preventivo de equipos')

@section('content')
    <header class="maintenance-command">
        <div>
            <span class="eyebrow">RF-062 · RF-066</span>
            <h1>Programa preventivo</h1>
            <p>Planifica intervenciones, genera órdenes y documenta la condición de cada activo institucional.</p>
        </div>
        <div class="maintenance-command__actions">
            @can('generate', App\Models\MaintenanceOrder::class)
                <form method="POST" action="{{ route('maintenance.orders.generate') }}">@csrf<button class="button button--outline">Generar órdenes próximas</button></form>
            @endcan
            @can('create', App\Models\MaintenancePlan::class)
                <a href="{{ route('maintenance.plans.create') }}" class="button button--primary">＋ Crear plan</a>
            @endcan
        </div>
    </header>

    <section class="maintenance-metrics">
        <article><span>Planes activos</span><strong>{{ $metrics['plans'] }}</strong><small>Programas vigentes</small></article>
        <article class="is-blue"><span>Próximos 30 días</span><strong>{{ $metrics['dueSoon'] }}</strong><small>Equipos programados</small></article>
        <article class="is-teal"><span>Órdenes abiertas</span><strong>{{ $metrics['open'] }}</strong><small>Generadas o en ejecución</small></article>
        <article class="is-orange"><span>Atenciones vencidas</span><strong>{{ $metrics['overdue'] }}</strong><small>Requieren seguimiento</small></article>
    </section>

    <section class="panel maintenance-calendar">
        <div class="panel__header maintenance-calendar__heading">
            <div><span class="panel__eyebrow">RF-063 · RF-064</span><h2>Calendario de mantenimientos</h2><p>{{ $start->format('d/m/Y') }} — {{ $end->format('d/m/Y') }}</p></div>
            <span class="count-pill">{{ $groupedEntries->flatten(1)->count() }} programaciones</span>
        </div>

        <form method="GET" class="maintenance-filters">
            <div class="field"><label for="maintenance-scope">Período</label><select id="maintenance-scope" name="scope"><option value="month" @selected($filters['scope']==='month')>Mensual</option><option value="quarter" @selected($filters['scope']==='quarter')>Trimestral</option><option value="year" @selected($filters['scope']==='year')>Anual</option></select></div>
            <div class="field"><label for="maintenance-date">Fecha de referencia</label><input id="maintenance-date" type="date" name="date" value="{{ $filters['date'] ?? today()->toDateString() }}"></div>
            <div class="field"><label for="maintenance-group">Agrupar por</label><select id="maintenance-group" name="group_by"><option value="month" @selected($filters['group_by']==='month')>Mes</option><option value="department" @selected($filters['group_by']==='department')>Departamento</option><option value="location" @selected($filters['group_by']==='location')>Ubicación</option></select></div>
            <div class="field"><label for="maintenance-area">Área</label><select id="maintenance-area" name="area_id"><option value="">Todas</option>@foreach($areas as $area)<option value="{{ $area->id }}" @selected(($filters['area_id']??null)==$area->id)>{{ $area->name }}</option>@endforeach</select></div>
            <div class="field"><label for="maintenance-department">Departamento</label><select id="maintenance-department" name="department_id"><option value="">Todos</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(($filters['department_id']??null)==$department->id)>{{ $department->name }}</option>@endforeach</select></div>
            <div class="field"><label for="maintenance-location">Ubicación</label><select id="maintenance-location" name="location"><option value="">Todas</option>@foreach($locations as $location)<option value="{{ $location }}" @selected(($filters['location']??null)===$location)>{{ $location }}</option>@endforeach</select></div>
            <button class="button button--primary">Aplicar</button>
        </form>

        <div class="maintenance-schedule">
            @forelse($groupedEntries as $group => $entries)
                <section class="maintenance-schedule__group">
                    <header><span></span><h3>{{ str($group)->title() }}</h3><b>{{ $entries->count() }}</b></header>
                    <div class="maintenance-schedule__items">
                        @foreach($entries as $entry)
                            @php($late = $entry->date->isPast() && ($entry->type === 'planned' || !in_array($entry->order->status ?? '', ['completed','cancelled'])))
                            <article class="maintenance-entry {{ $late ? 'is-overdue' : '' }}">
                                <time datetime="{{ $entry->date->toDateString() }}"><strong>{{ $entry->date->format('d') }}</strong><span>{{ $entry->date->locale('es')->translatedFormat('M') }}</span></time>
                                <div class="maintenance-entry__asset"><span>{{ $entry->equipment->inventory_code }}</span><strong>{{ $entry->equipment->name }}</strong><small>{{ $entry->equipment->department?->name ?: $entry->equipment->area?->name }} · {{ $entry->equipment->location }}</small></div>
                                <div class="maintenance-entry__plan"><span>Plan</span><strong>{{ $entry->plan->name }}</strong><small>{{ $entry->plan->frequency_label }}</small></div>
                                @if($entry->type === 'order')
                                    <div class="maintenance-entry__state"><span class="maintenance-status maintenance-status--{{ $late ? 'overdue' : $entry->order->status }}">{{ $late ? 'Vencido' : $entry->order->status_label }}</span><small>{{ $entry->order->number }}</small></div>
                                    <a href="{{ route('maintenance.orders.show',$entry->order) }}" class="table-action">Abrir orden →</a>
                                @else
                                    <div class="maintenance-entry__state"><span class="maintenance-status maintenance-status--scheduled">Programado</span><small>Sin orden todavía</small></div>
                                    @can('generate', App\Models\MaintenanceOrder::class)<form method="POST" action="{{ route('maintenance.plans.assignments.generate',[$entry->plan,$entry->assignment]) }}">@csrf<button class="table-action">Generar orden →</button></form>@endcan
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="empty-state"><strong>No hay mantenimientos en este período</strong><p>Cambia la fecha o los filtros, o crea un plan preventivo.</p></div>
            @endforelse
        </div>
    </section>

    <section class="maintenance-plan-section">
        <div class="section-heading"><div><span class="eyebrow">RF-062</span><h2>Planes preventivos</h2></div><span>{{ $plans->count() }} registrados</span></div>
        <div class="maintenance-plan-grid">
            @forelse($plans as $plan)
                <a href="{{ route('maintenance.plans.show',$plan) }}" class="maintenance-plan-card {{ $plan->is_active ? '' : 'is-inactive' }}">
                    <header><span>{{ $plan->code }}</span><em>{{ $plan->is_active ? 'Activo' : 'Inactivo' }}</em></header>
                    <h3>{{ $plan->name }}</h3><p>{{ str($plan->description)->limit(115) }}</p>
                    <div><span><strong>{{ $plan->active_equipment_count }}</strong> equipos</span><span><strong>{{ $plan->open_orders_count }}</strong> órdenes abiertas</span><span><strong>{{ $plan->frequency_label }}</strong> frecuencia</span></div>
                    <footer><span>Anticipación: {{ $plan->lead_days }} días</span><b>Ver plan →</b></footer>
                </a>
            @empty
                <div class="empty-state"><strong>No hay planes preventivos</strong><p>Registra el primer programa formal para comenzar.</p></div>
            @endforelse
        </div>
    </section>
@endsection
