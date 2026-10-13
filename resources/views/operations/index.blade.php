@extends('layouts.app')

@section('title', 'Operación de IT')
@section('section-title', 'Asignación y atención de tickets')

@section('content')
    <header class="operations-hero">
        <div><span class="eyebrow eyebrow--light">Centro de operaciones</span><h1>Bandeja técnica de IT</h1><p>Distribuye la carga, controla compromisos y atiende cada solicitud desde una vista unificada.</p></div>
        <div class="operations-hero__clock"><span>{{ now()->locale('es')->isoFormat('dddd') }}</span><strong>{{ now()->format('H:i') }}</strong><small>{{ now()->format('d/m/Y') }}</small></div>
    </header>

    <section class="operations-metrics">
        <a href="{{ route('operations.index') }}"><span>Activos</span><strong>{{ $metrics['active'] }}</strong><small>tickets en operación</small></a>
        <a href="{{ route('operations.index',['scope'=>'unassigned']) }}" class="is-blue"><span>Sin responsable</span><strong>{{ $metrics['unassigned'] }}</strong><small>requieren distribución</small></a>
        <a href="{{ route('operations.index',['scope'=>'general','sla'=>'overdue']) }}" class="is-danger"><span>SLA vencido</span><strong>{{ $metrics['overdue'] }}</strong><small>requieren escalamiento</small></a>
        <a href="{{ route('operations.index',['scope'=>'general','priority_id'=>$priorities->firstWhere('weight',4)?->id]) }}" class="is-warning"><span>Alta prioridad</span><strong>{{ $metrics['urgent'] }}</strong><small>impacto relevante</small></a>
        <a href="{{ route('operations.index',['scope'=>'general']) }}"><span>Por aceptar</span><strong>{{ $metrics['pending_acceptance'] }}</strong><small>asignaciones pendientes</small></a>
    </section>

    <section class="panel operations-board">
        <div class="panel__header operations-board__header">
            <div><span class="panel__eyebrow">Distribución y seguimiento</span><h2>Bandeja operativa</h2></div>
            <nav class="operations-scopes">
                @if(auth()->user()->hasPermission('tickets.view_all'))<a class="{{ $filters['scope']==='general'?'is-active':'' }}" href="{{ route('operations.index',['scope'=>'general']) }}">General IT</a>@endif
                <a class="{{ $filters['scope']==='mine'?'is-active':'' }}" href="{{ route('operations.index',['scope'=>'mine']) }}">Mis asignaciones</a>
                <a class="{{ $filters['scope']==='unassigned'?'is-active':'' }}" href="{{ route('operations.index',['scope'=>'unassigned']) }}">Sin asignar</a>
            </nav>
        </div>

        <form method="GET" class="operations-filters">
            <input type="hidden" name="scope" value="{{ $filters['scope'] }}">
            <label><span>Buscar</span><input type="search" name="search" value="{{ $filters['search']??'' }}" placeholder="Código o asunto"></label>
            <label><span>Grupo técnico</span><select name="group_id" onchange="if(this.value){this.form.scope.value='group'}"><option value="">Todos</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected(($filters['group_id']??null)==$group->id)>{{ $group->name }}</option>@endforeach</select></label>
            <label><span>Técnico</span><select name="technician_id" onchange="if(this.value){this.form.scope.value='technician'}"><option value="">Todos</option>@foreach($technicians as $technician)<option value="{{ $technician->id }}" @selected(($filters['technician_id']??null)==$technician->id)>{{ $technician->name }}</option>@endforeach</select></label>
            <label><span>Estado</span><select name="status_id"><option value="">Todos</option>@foreach($statuses as $status)<option value="{{ $status->id }}" @selected(($filters['status_id']??null)==$status->id)>{{ $status->name }}</option>@endforeach</select></label>
            <label><span>SLA</span><select name="sla"><option value="">Cualquier condición</option><option value="on_track" @selected(($filters['sla']??null)==='on_track')>En objetivo</option><option value="at_risk" @selected(($filters['sla']??null)==='at_risk')>Próximo a vencer</option><option value="overdue" @selected(($filters['sla']??null)==='overdue')>Vencido</option></select></label>
            <button class="button button--primary button--small">Aplicar</button>
        </form>

        <div class="operations-ticket-list">
            @forelse($tickets as $ticket)
                @php($slaTone = !$ticket->solved_at && $ticket->resolution_due_at?->isPast() ? 'critical' : (!$ticket->solved_at && $ticket->resolution_due_at?->lte(now()->addHours(4)) ? 'warning' : 'normal'))
                <article class="operations-ticket is-{{ $slaTone }}">
                    <div class="operations-ticket__priority" style="--priority-color:{{ $ticket->priority?->color??'#888' }}"><span>{{ $ticket->priority?->code??'—' }}</span></div>
                    <div class="operations-ticket__identity"><a href="{{ route('tickets.show',$ticket) }}">{{ $ticket->number }}</a><strong>{{ $ticket->title }}</strong><small>{{ $ticket->category?->name }} · {{ $ticket->requester->name }}</small></div>
                    <div><span class="operations-label">Estado</span><strong>{{ $ticket->status?->name }}</strong><small>{{ str($ticket->assignment_status??'sin asignar')->headline() }}</small></div>
                    <div><span class="operations-label">Responsable</span><strong>{{ $ticket->currentAssignee?->name ?: $ticket->currentGroup?->name ?: 'Sin asignar' }}</strong><small>{{ $ticket->currentArea?->name }}</small></div>
                    <div><span class="operations-label">Objetivo</span><strong>{{ $ticket->resolution_due_at?->format('d/m/Y H:i') ?: 'Sin SLA' }}</strong><small>{{ $ticket->resolution_due_at?->diffForHumans() }}</small></div>
                    <a class="operations-ticket__open" href="{{ route('tickets.show',$ticket) }}">Atender →</a>
                </article>
            @empty
                <div class="empty-state"><strong>No hay tickets en esta bandeja</strong><p>Cambia el alcance o los filtros para consultar otros casos.</p></div>
            @endforelse
        </div>
        <div class="pagination-wrap">{{ $tickets->links() }}</div>
    </section>

    <section class="panel workload-panel">
        <div class="panel__header"><div><span class="panel__eyebrow">Capacidad operativa</span><h2>Carga del equipo</h2></div><span class="count-pill">{{ $technicians->count() }}</span></div>
        <div class="workload-grid">
            @foreach($technicians as $technician)
                @php($load = min(100,$technician->open_tickets_count*12))
                <article><div class="workload-person"><span class="avatar">{{ str($technician->name)->substr(0,1)->upper() }}</span><div><strong>{{ $technician->name }}</strong><small>{{ $technician->primary_role_name }}</small></div></div><div class="workload-stats"><span><b>{{ $technician->open_tickets_count }}</b> abiertos</span><span><b>{{ $technician->overdue_tickets_count }}</b> vencidos</span><span><b>{{ number_format(($technician->minutes_last_30_days??0)/60,1) }}</b> h/30 días</span></div><div class="workload-bar"><i style="width:{{ $load }}%"></i></div><a href="{{ route('operations.index',['scope'=>'technician','technician_id'=>$technician->id]) }}">Ver bandeja →</a></article>
            @endforeach
        </div>
    </section>
@endsection
