@extends('layouts.app')

@section('title', 'Reglas de aprobación')
@section('section-title', 'Aprobaciones y suplencias')

@section('content')
    <div class="page-heading">
        <div><span class="eyebrow">Gobierno de aprobaciones</span><h1>Reglas de aprobación</h1><p>Determina cuándo un ticket requiere autorización, el orden de los niveles y quién cubre una ausencia temporal.</p></div>
        @if(auth()->user()->hasPermission('approvals.manage'))<a href="{{ route('configuration.approvals.create') }}" class="button button--primary">Nueva regla <span>→</span></a>@endif
    </div>

    <section class="workflow-grid">
        @forelse($workflows as $workflow)
            <article class="panel workflow-card">
                <header><span class="config-code">{{ $workflow->code }}</span><span class="status-badge status-badge--{{ $workflow->is_active ? 'active' : 'inactive' }}">{{ $workflow->is_active ? 'Activa' : 'Inactiva' }}</span></header>
                <h2>{{ $workflow->name }}</h2><p>{{ $workflow->description }}</p>
                <div class="workflow-scope"><span><small>Clasificación</small><strong>{{ $workflow->category->name }}</strong></span><span><small>Subcategoría</small><strong>{{ $workflow->subcategory?->name ?? 'Todas' }}</strong></span><span><small>Área solicitante</small><strong>{{ $workflow->requesterArea?->name ?? 'Todas' }}</strong></span></div>
                <div class="approval-path">
                    @forelse($workflow->levels as $level)
                        <div><span>{{ $level->level_number }}</span><div><strong>{{ $level->name }}</strong><small>{{ $level->approver_type_label }}@if($level->approver) · {{ $level->approver->name }}@elseif($level->approverRole) · {{ $level->approverRole->name }}@endif</small></div></div>
                    @empty
                        <div class="empty-inline">Sin niveles configurados</div>
                    @endforelse
                </div>
                <footer><span>{{ $workflow->levels->count() }} niveles · {{ $workflow->minimumPriority?->name ?? 'Cualquier prioridad' }}</span>@if(auth()->user()->hasPermission('approvals.manage'))<a href="{{ route('configuration.approvals.edit', $workflow) }}" class="button button--outline button--small">Configurar</a>@endif</footer>
            </article>
        @empty
            <div class="panel empty-state empty-state--wide"><strong>No hay reglas de aprobación</strong><p>Crea una regla para proteger solicitudes que requieran autorización previa.</p></div>
        @endforelse
    </section>

    <section class="panel admin-panel" id="delegaciones">
        <div class="panel__header"><div><span class="panel__eyebrow">Continuidad operativa</span><h2>Suplencias y delegaciones temporales</h2></div><span class="panel-badge">{{ $delegations->where('is_active', true)->count() }} activas</span></div>
        @if(auth()->user()->hasPermission('approvals.manage'))
            <form action="{{ $editingDelegation ? route('configuration.delegations.update', $editingDelegation) : route('configuration.delegations.store') }}" method="POST" class="delegation-form">
                @csrf @if($editingDelegation) @method('PUT') @endif
                <div class="form-grid form-grid--three">
                    <div class="field"><label for="delegator">Titular <strong>*</strong></label><div class="select-shell"><select id="delegator" name="delegator_user_id" required><option value="">Seleccionar</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string)old('delegator_user_id', $editingDelegation?->delegator_user_id) === (string)$user->id)>{{ $user->name }}</option>@endforeach</select></div></div>
                    <div class="field"><label for="delegate">Suplente <strong>*</strong></label><div class="select-shell"><select id="delegate" name="delegate_user_id" required><option value="">Seleccionar</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string)old('delegate_user_id', $editingDelegation?->delegate_user_id) === (string)$user->id)>{{ $user->name }}</option>@endforeach</select></div></div>
                    <div class="field field--wide"><label for="delegation-reason">Motivo <strong>*</strong></label><div class="input-shell"><input id="delegation-reason" name="reason" value="{{ old('reason', $editingDelegation?->reason) }}" maxlength="500" placeholder="Ej. Vacaciones programadas" required></div></div>
                    <div class="field"><label for="starts-at">Inicio <strong>*</strong></label><div class="input-shell"><input id="starts-at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $editingDelegation?->starts_at?->format('Y-m-d\TH:i')) }}" required></div></div>
                    <div class="field"><label for="ends-at">Finalización <strong>*</strong></label><div class="input-shell"><input id="ends-at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $editingDelegation?->ends_at?->format('Y-m-d\TH:i')) }}" required></div></div>
                </div>
                <div class="quick-form-footer"><label class="switch-field switch-field--small"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool)old('is_active', $editingDelegation?->is_active ?? true))><span></span><div><strong>Delegación activa</strong></div></label><div>@if($editingDelegation)<a href="{{ route('configuration.approvals.index') }}#delegaciones" class="button button--ghost button--small">Cancelar</a>@endif<button type="submit" class="button button--primary button--small">{{ $editingDelegation ? 'Guardar suplencia' : 'Registrar suplencia' }}</button></div></div>
            </form>
        @endif
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Titular</th><th>Suplente</th><th>Vigencia</th><th>Motivo</th><th>Estado</th><th class="align-right">Acción</th></tr></thead><tbody>
            @forelse($delegations as $delegation)
                @php $isCurrent = $delegation->is_active && $delegation->starts_at->isPast() && $delegation->ends_at->isFuture(); @endphp
                <tr><td><strong>{{ $delegation->delegator->name }}</strong></td><td>{{ $delegation->delegate->name }}</td><td><div class="date-cell"><strong>{{ $delegation->starts_at->format('d/m/Y H:i') }}</strong><span>hasta {{ $delegation->ends_at->format('d/m/Y H:i') }}</span></div></td><td>{{ str($delegation->reason)->limit(70) }}</td><td><span class="status-badge status-badge--{{ $isCurrent ? 'active' : ($delegation->is_active ? 'suspended' : 'inactive') }}">{{ $isCurrent ? 'Vigente' : ($delegation->is_active ? 'Programada/expirada' : 'Inactiva') }}</span></td><td class="align-right">@if(auth()->user()->hasPermission('approvals.manage'))<a href="{{ route('configuration.approvals.index', ['edit_delegation' => $delegation->id]) }}#delegaciones" class="button button--outline button--small">Editar</a>@endif</td></tr>
            @empty
                <tr><td colspan="6"><div class="empty-state"><strong>Sin suplencias registradas</strong><p>No existen delegaciones temporales.</p></div></td></tr>
            @endforelse
        </tbody></table></div>
    </section>
@endsection
