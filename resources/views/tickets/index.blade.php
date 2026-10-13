@extends('layouts.app')

@section('title', 'Gestión de tickets')
@section('section-title', 'Gestión integral de tickets')

@section('content')
    <section class="ticket-hero" style="--ticket-hero-image: url('{{ asset('images/brand/zolic-port.jpg') }}')">
        <div>
            <span class="eyebrow eyebrow--light">Centro de servicio</span>
            <h1>Atención trazable,<br>de principio a fin.</h1>
            <p>Registra, prioriza y acompaña cada solicitud con responsables, tiempos objetivo e historial verificable.</p>
        </div>
        @can('create', App\Models\Ticket::class)
            <a href="{{ route('tickets.create') }}" class="button button--light">Nuevo ticket <span>＋</span></a>
        @endcan
    </section>

    <section class="ticket-metrics" aria-label="Indicadores de tickets">
        <a href="{{ route('tickets.index') }}" class="ticket-metric ticket-metric--blue"><span>Total visible</span><strong>{{ $metrics['total'] }}</strong><small>casos bajo tu alcance</small></a>
        <a href="{{ route('tickets.index', ['scope' => 'all']) }}" class="ticket-metric ticket-metric--teal"><span>En atención</span><strong>{{ $metrics['open'] }}</strong><small>sin cerrar ni cancelar</small></a>
        <a href="{{ route('tickets.index', ['scope' => 'drafts']) }}" class="ticket-metric ticket-metric--gray"><span>Borradores</span><strong>{{ $metrics['drafts'] }}</strong><small>pendientes de envío</small></a>
        <a href="{{ route('tickets.index', ['priority' => $priorities->firstWhere('weight', 4)?->id]) }}" class="ticket-metric ticket-metric--orange"><span>Alta prioridad</span><strong>{{ $metrics['urgent'] }}</strong><small>requieren atención prioritaria</small></a>
    </section>

    <section class="panel ticket-list-panel">
        <div class="panel__header ticket-list-heading">
            <div><span class="panel__eyebrow">Bandeja operativa</span><h2>Tickets</h2></div>
            <nav class="ticket-scopes" aria-label="Vistas de la bandeja">
                <a class="{{ ($filters['scope'] ?? 'all') === 'all' ? 'is-active' : '' }}" href="{{ route('tickets.index') }}">Todos</a>
                <a class="{{ ($filters['scope'] ?? '') === 'mine' ? 'is-active' : '' }}" href="{{ route('tickets.index', ['scope' => 'mine']) }}">Mis tickets</a>
                @if (auth()->user()->hasPermission('tickets.view_assigned'))
                    <a class="{{ ($filters['scope'] ?? '') === 'assigned' ? 'is-active' : '' }}" href="{{ route('tickets.index', ['scope' => 'assigned']) }}">Asignados</a>
                @endif
                @if (auth()->user()->hasPermission('tickets.approve'))
                    <a class="{{ ($filters['scope'] ?? '') === 'approvals' ? 'is-active' : '' }}" href="{{ route('tickets.index', ['scope' => 'approvals']) }}">Por aprobar</a>
                @endif
            </nav>
        </div>

        <form method="GET" action="{{ route('tickets.index') }}" class="ticket-filter-bar">
            <input type="hidden" name="scope" value="{{ $filters['scope'] ?? 'all' }}">
            <label class="ticket-search"><span aria-hidden="true">⌕</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar por número o asunto"></label>
            <select name="status" aria-label="Filtrar por estado"><option value="">Todos los estados</option>@foreach ($statuses as $status)<option value="{{ $status->id }}" @selected(($filters['status'] ?? null) == $status->id)>{{ $status->name }}</option>@endforeach</select>
            <select name="priority" aria-label="Filtrar por prioridad"><option value="">Todas las prioridades</option>@foreach ($priorities as $priority)<option value="{{ $priority->id }}" @selected(($filters['priority'] ?? null) == $priority->id)>{{ $priority->code }} · {{ $priority->name }}</option>@endforeach</select>
            <select name="category" aria-label="Filtrar por categoría"><option value="">Todas las categorías</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected(($filters['category'] ?? null) == $category->id)>{{ $category->name }}</option>@endforeach</select>
            <button class="button button--primary button--small" type="submit">Aplicar</button>
        </form>

        <div class="ticket-table-wrap">
            <table class="ticket-table">
                <thead><tr><th>Ticket</th><th>Solicitante</th><th>Clasificación</th><th>Prioridad</th><th>Estado</th><th>Responsable</th><th>Actualizado</th><th></th></tr></thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td><a class="ticket-number" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->number }}</a><strong>{{ $ticket->title ?: 'Borrador sin asunto' }}</strong><small>{{ $ticket->source_label }}</small></td>
                            <td><strong>{{ $ticket->requester->name }}</strong><small>{{ $ticket->requester->employee_code ?: 'Sin código' }}</small></td>
                            <td><strong>{{ $ticket->category->name }}</strong><small>{{ $ticket->subcategory?->name ?: 'Sin subcategoría' }}</small></td>
                            <td>@if ($ticket->priority)<span class="ticket-priority" style="--ticket-color: {{ $ticket->priority->color }}">{{ $ticket->priority->code }} · {{ $ticket->priority->name }}</span>@else<span class="ticket-muted">Por calcular</span>@endif</td>
                            <td>@if ($ticket->is_draft)<span class="ticket-status ticket-status--draft">Borrador</span>@else<span class="ticket-status" style="--ticket-color: {{ $ticket->status?->color ?? '#888888' }}">{{ $ticket->status?->name ?? 'Sin estado' }}</span>@endif</td>
                            <td><strong>{{ $ticket->currentAssignee?->name ?: $ticket->currentGroup?->name ?: 'Sin asignar' }}</strong><small>{{ $ticket->currentArea?->name }}</small></td>
                            <td><strong>{{ $ticket->last_activity_at?->diffForHumans() }}</strong><small>{{ $ticket->last_activity_at?->format('d/m/Y H:i') }}</small></td>
                            <td><a class="ticket-open" href="{{ route('tickets.show', $ticket) }}" aria-label="Abrir {{ $ticket->number }}">→</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty-state"><strong>No hay tickets en esta vista</strong><p>Ajusta los filtros o registra una nueva solicitud.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $tickets->links() }}</div>
    </section>
@endsection
