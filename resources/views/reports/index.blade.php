@extends('layouts.app')

@section('title', 'Indicadores y reportes')
@section('section-title', 'Indicadores y reportes')

@section('content')
    <header class="reports-heading">
        <div><span class="eyebrow eyebrow--light">RF-069 · RF-070</span><h1>Inteligencia operativa</h1><p>Indicadores verificables para supervisar servicio, capacidad, activos y cumplimiento.</p></div>
        @if(auth()->user()->hasPermission('reports.export'))<a class="button button--light" href="{{ route('reports.export',request()->query()) }}">Exportar CSV ↓</a>@endif
    </header>

    <section class="report-metrics">
        <article><span>Tickets del período</span><strong>{{ $metrics['tickets'] }}</strong><small>{{ $metrics['open'] }} aún abiertos</small></article>
        <article><span>Respuesta dentro de SLA</span><strong>{{ $metrics['response_compliance']!==null?number_format($metrics['response_compliance'],1).'%':'—' }}</strong><small>sobre objetivos configurados</small></article>
        <article><span>Resolución dentro de SLA</span><strong>{{ $metrics['resolution_compliance']!==null?number_format($metrics['resolution_compliance'],1).'%':'—' }}</strong><small>cumplimiento del período</small></article>
        <article><span>Satisfacción</span><strong>{{ $metrics['rating']?number_format($metrics['rating'],1).'/5':'—' }}</strong><small>{{ $metrics['reopened'] }} reaperturas</small></article>
    </section>

    <section class="panel report-workspace">
        <form method="GET" class="report-filters">
            <label><span>Reporte</span><select name="report">@foreach($reportTypes as $value=>$label)@if(auth()->user()->hasPermission('tickets.view_all')||!in_array($value,['equipment','supplies','maintenance','audit']))<option value="{{ $value }}" @selected($filters['report']===$value)>{{ $label }}</option>@endif @endforeach</select></label>
            <label><span>Desde</span><input type="date" name="start_date" value="{{ $filters['start_date'] }}" required></label>
            <label><span>Hasta</span><input type="date" name="end_date" value="{{ $filters['end_date'] }}" required></label>
            <label><span>Área de IT</span><select name="area_id"><option value="">Todas</option>@foreach($areas as $item)<option value="{{ $item->id }}" @selected(($filters['area_id']??null)==$item->id)>{{ $item->name }}</option>@endforeach</select></label>
            <label><span>Grupo</span><select name="group_id"><option value="">Todos</option>@foreach($groups as $item)<option value="{{ $item->id }}" @selected(($filters['group_id']??null)==$item->id)>{{ $item->name }}</option>@endforeach</select></label>
            <label><span>Técnico</span><select name="technician_id"><option value="">Todos</option>@foreach($technicians as $item)<option value="{{ $item->id }}" @selected(($filters['technician_id']??null)==$item->id)>{{ $item->name }}</option>@endforeach</select></label>
            <label><span>Estado</span><select name="status_id"><option value="">Todos</option>@foreach($statuses as $item)<option value="{{ $item->id }}" @selected(($filters['status_id']??null)==$item->id)>{{ $item->name }}</option>@endforeach</select></label>
            <label><span>Prioridad</span><select name="priority_id"><option value="">Todas</option>@foreach($priorities as $item)<option value="{{ $item->id }}" @selected(($filters['priority_id']??null)==$item->id)>{{ $item->code }} · {{ $item->name }}</option>@endforeach</select></label>
            <label><span>Categoría</span><select name="category_id"><option value="">Todas</option>@foreach($categories as $item)<option value="{{ $item->id }}" @selected(($filters['category_id']??null)==$item->id)>{{ $item->name }}</option>@endforeach</select></label>
            <button class="button button--primary">Generar reporte</button>
        </form>

        <div class="report-result-heading"><div><span class="panel__eyebrow">Resultado</span><h2>{{ $reportTypes[$filters['report']] }}</h2></div><small>Máximo 250 registros en pantalla · hasta 10,000 al exportar</small></div>
        <div class="report-table-wrap"><table class="report-table"><thead><tr>@foreach($dataset['columns'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead><tbody>@forelse($dataset['rows'] as $row)<tr>@foreach(array_keys($dataset['columns']) as $key)<td>{{ $row[$key]??'—' }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($dataset['columns']) }}"><div class="empty-state"><strong>Sin datos para este reporte</strong><p>Ajusta el período o los filtros seleccionados.</p></div></td></tr>@endforelse</tbody></table></div>
    </section>
@endsection
