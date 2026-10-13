@extends('layouts.app')

@section('title', 'Inventario tecnológico')
@section('section-title', 'Inventario de equipos e insumos')

@section('content')
    <header class="inventory-command">
        <div><span class="eyebrow">Control de activos</span><h1>Inventario tecnológico</h1><p>Control de activos, responsables, red, historial técnico y movimientos institucionales.</p></div>
        <div class="inventory-command__actions">
            <a href="{{ route('inventory.supplies.index') }}" class="button button--outline">Ver insumos</a>
            @can('create', App\Models\Equipment::class)<a href="{{ route('inventory.equipment.create') }}" class="button button--primary">＋ Registrar equipo</a>@endcan
        </div>
    </header>

    <section class="inventory-metrics">
        <article><span>Activos registrados</span><strong>{{ $metrics['total'] }}</strong><small>Inventario institucional</small></article>
        <article class="is-teal"><span>Equipos asignados</span><strong>{{ $metrics['assigned'] }}</strong><small>Con usuario responsable</small></article>
        <article class="is-orange"><span>Atención técnica</span><strong>{{ $metrics['attention'] }}</strong><small>Mantenimiento o reparación</small></article>
        <article class="is-red"><span>Alertas de insumos</span><strong>{{ $metrics['lowStock'] }}</strong><small>En nivel mínimo</small></article>
    </section>

    <div class="inventory-layout">
        <section class="panel inventory-catalog">
            <div class="panel__header"><div><span class="panel__eyebrow">Catálogo</span><h2>Equipos tecnológicos</h2></div><span class="count-pill">{{ $equipment->total() }} activos</span></div>
            <form method="GET" class="inventory-filter">
                <div class="input-shell"><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Código, nombre, serie o hostname"><span>⌕</span></div>
                <select name="type"><option value="">Todos los tipos</option>@foreach(App\Models\Equipment::TYPES as $value => $label)<option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
                <select name="status"><option value="">Todos los estados</option>@foreach(App\Models\Equipment::STATUSES as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
                <button class="button button--primary">Filtrar</button>
                @if(array_filter($filters))<a href="{{ route('inventory.index') }}" class="button button--ghost">Limpiar</a>@endif
            </form>

            <div class="inventory-table-wrap"><table class="inventory-table"><thead><tr><th>Activo</th><th>Identificación</th><th>Ubicación</th><th>Responsable</th><th>Estado</th><th></th></tr></thead><tbody>
                @forelse($equipment as $asset)
                    <tr>
                        <td><span class="asset-type-mark asset-type-mark--{{ $asset->asset_type }}">{{ ['computer'=>'PC','server'=>'SV','printer'=>'PR','network_device'=>'NW','phone'=>'TL','peripheral'=>'PF','other'=>'OT'][$asset->asset_type] }}</span><div><strong>{{ $asset->name }}</strong><small>{{ $asset->type_label }}</small></div></td>
                        <td><b>{{ $asset->inventory_code }}</b><small>{{ $asset->brand }} {{ $asset->model }}@if($asset->serial_number) · S/N {{ $asset->serial_number }}@endif</small></td>
                        <td><strong>{{ $asset->location }}</strong><small>{{ $asset->area?->name }}@if($asset->department) · {{ $asset->department->name }}@endif</small></td>
                        <td><strong>{{ $asset->responsible?->name ?: 'Sin responsable' }}</strong><small>{{ $asset->hostname ?: 'Sin hostname' }}</small></td>
                        <td><span class="asset-status asset-status--{{ $asset->status }}">{{ $asset->status_label }}</span></td>
                        <td><a href="{{ route('inventory.equipment.show', $asset) }}" class="table-action">Abrir →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty-state"><strong>No se encontraron equipos</strong><p>Ajusta los filtros o registra el primer activo tecnológico.</p></div></td></tr>
                @endforelse
            </tbody></table></div>
            @if($equipment->hasPages())<div class="pagination-wrap">{{ $equipment->links() }}</div>@endif
        </section>

        <aside class="panel inventory-alerts">
            <div class="panel__header"><div><span class="panel__eyebrow">Alertas de inventario</span><h2>Existencias mínimas</h2></div></div>
            <div class="inventory-alert-list">
                @forelse($lowStockSupplies as $supply)<a href="{{ route('inventory.supplies.show', $supply) }}"><span>!</span><div><strong>{{ $supply->name }}</strong><small>{{ $supply->code }} · mínimo {{ number_format($supply->minimum_quantity, 2) }}</small></div><b>{{ number_format($supply->stock_quantity, 2) }}</b></a>
                @empty<div class="inventory-ok"><span>✓</span><p>No hay insumos en nivel mínimo.</p></div>@endforelse
            </div>
            <a href="{{ route('inventory.supplies.index', ['stock' => 'low']) }}" class="button button--outline button--full">Revisar existencias</a>
        </aside>
    </div>
@endsection
