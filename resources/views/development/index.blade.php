@extends('layouts.app')

@section('title', 'Desarrollo seguro')
@section('section-title', 'Tickets de desarrollo y seguridad')

@section('content')
    <section class="development-hero">
        <div class="development-hero__copy">
            <span class="eyebrow eyebrow--light">Ciclo de desarrollo seguro</span>
            <h1>De la necesidad institucional<br>a un despliegue controlado.</h1>
            <p>Documenta alcance, requerimientos, seguridad y pruebas antes de solicitar el paso a producción.</p>
            <div class="development-hero__actions">
                @can('create', App\Models\DevelopmentRequest::class)
                    <a href="{{ route('development.create') }}" class="button button--light">Nueva solicitud <span>＋</span></a>
                @endcan
                <a href="{{ route('development.index', ['scope' => 'mine']) }}" class="button button--glass">Mis solicitudes</a>
            </div>
        </div>
        <div class="development-hero__visual" aria-hidden="true">
            <span class="development-code-card"><b>01</b><i></i><small>Requerimientos</small></span>
            <span class="development-code-card"><b>02</b><i></i><small>Seguridad</small></span>
            <span class="development-code-card"><b>03</b><i></i><small>Pruebas</small></span>
            <span class="development-code-card"><b>04</b><small>Producción</small></span>
        </div>
    </section>

    <section class="development-metrics" aria-label="Indicadores de desarrollo seguro">
        <article><span class="development-metric-icon development-metric-icon--blue">{ }</span><div><small>Solicitudes visibles</small><strong>{{ $metrics['total'] }}</strong><em>alcance autorizado</em></div></article>
        <article><span class="development-metric-icon development-metric-icon--orange">◫</span><div><small>En definición o desarrollo</small><strong>{{ $metrics['development'] }}</strong><em>requerimientos activos</em></div></article>
        <article><span class="development-metric-icon development-metric-icon--teal">✓</span><div><small>En pruebas</small><strong>{{ $metrics['testing'] }}</strong><em>validación de evidencias</em></div></article>
        <article><span class="development-metric-icon development-metric-icon--yellow">↗</span><div><small>Por autorizar</small><strong>{{ $metrics['deployment'] }}</strong><em>despliegues a producción</em></div></article>
    </section>

    <section class="panel development-catalog">
        <div class="panel__header development-catalog__header">
            <div><span class="panel__eyebrow">Portafolio</span><h2>Solicitudes de desarrollo</h2></div>
            <nav class="development-scopes" aria-label="Vistas de desarrollo">
                <a class="{{ ($filters['scope'] ?? 'all') === 'all' ? 'is-active' : '' }}" href="{{ route('development.index') }}">Todas</a>
                <a class="{{ ($filters['scope'] ?? '') === 'mine' ? 'is-active' : '' }}" href="{{ route('development.index', ['scope' => 'mine']) }}">Mis solicitudes</a>
                @if (auth()->user()->hasPermission('development.deploy_authorize'))
                    <a class="{{ ($filters['scope'] ?? '') === 'deployment' ? 'is-active' : '' }}" href="{{ route('development.index', ['scope' => 'deployment']) }}">Por autorizar</a>
                @endif
            </nav>
        </div>

        <form method="GET" action="{{ route('development.index') }}" class="development-filter">
            <input type="hidden" name="scope" value="{{ $filters['scope'] ?? 'all' }}">
            <label><span>⌕</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar por número o proyecto"></label>
            <select name="type" aria-label="Tipo de solicitud"><option value="">Todos los tipos</option>@foreach ($requestTypes as $value => $label)<option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
            <select name="stage" aria-label="Etapa"><option value="">Todas las etapas</option>@foreach ($stages as $value => $label)<option value="{{ $value }}" @selected(($filters['stage'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
            <button class="button button--primary button--small">Filtrar</button>
        </form>

        <div class="development-card-list">
            @forelse ($developmentRequests as $item)
                <a href="{{ route('development.show', $item) }}" class="development-project-card">
                    <div class="development-project-card__code"><span>{{ $item->ticket->number }}</span><b>{{ str($item->request_type_label)->substr(0, 2)->upper() }}</b></div>
                    <div class="development-project-card__main">
                        <div><span>{{ $item->request_type_label }}</span><em class="development-stage development-stage--{{ $item->lifecycle_status }}">{{ $item->lifecycle_status_label }}</em></div>
                        <h3>{{ $item->project_name ?: 'Borrador sin nombre' }}</h3>
                        <p>{{ str($item->objective ?: $item->business_need ?: 'Solicitud pendiente de completar.')->limit(125) }}</p>
                        <footer><span>Solicitante <strong>{{ $item->ticket->requester->name }}</strong></span><span>Equipo <strong>{{ $item->ticket->currentGroup?->name ?: 'Pendiente' }}</strong></span><span>Estado <strong>{{ $item->ticket->status?->name }}</strong></span></footer>
                    </div>
                    <div class="development-project-card__side">
                        @if ($item->ticket->priority)<span class="ticket-priority" style="--ticket-color: {{ $item->ticket->priority->color }}">{{ $item->ticket->priority->code }}</span>@endif
                        <small>Actualizado</small><strong>{{ $item->updated_at->diffForHumans() }}</strong><b>→</b>
                    </div>
                </a>
            @empty
                <div class="empty-state"><strong>No hay solicitudes en esta vista</strong><p>Cambia los filtros o registra una nueva iniciativa de desarrollo.</p></div>
            @endforelse
        </div>
        <div class="pagination-wrap">{{ $developmentRequests->links() }}</div>
    </section>
@endsection
