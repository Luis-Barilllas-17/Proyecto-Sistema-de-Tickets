<div class="ticket-supply-list">
    @forelse($ticket->supplyUsages as $usage)
        <article>
            <span class="supply-usage-state supply-usage-state--{{ $usage->status }}">{{ $usage->status_label }}</span>
            <div><strong>{{ $usage->supply->name }}</strong><small>{{ $usage->supply->code }}@if($usage->notes) · {{ $usage->notes }}@endif</small></div>
            <b>{{ rtrim(rtrim(number_format($usage->quantity_consumed??$usage->quantity_reserved,2,'.',','),'0'),'.') }} {{ $usage->supply->unit_label }}</b>
            @can('reserveSupply', $ticket)
                @if($usage->status==='reserved')
                    <form method="POST" action="{{ route('tickets.supplies.destroy',[$ticket,$usage]) }}">@csrf @method('DELETE')<button class="button button--danger-soft button--small">Liberar</button></form>
                @elseif($usage->status==='returned')
                    <button type="button" class="button button--outline button--small" data-reserve-returned data-supply-id="{{ $usage->supply_id }}" data-supply-code="{{ $usage->supply->code }}" data-quantity="{{ $usage->quantity_consumed??$usage->quantity_reserved }}" data-notes="{{ $usage->notes }}">Reservar de nuevo</button>
                @endif
            @endcan
        </article>
    @empty
        <div class="empty-state"><strong>Sin insumos reservados</strong><p>El personal técnico puede reservar materiales durante la atención.</p></div>
    @endforelse
</div>
@if($ticket->supplyUsages->contains('status','returned'))
    @can('reserveSupply',$ticket)<div class="ticket-returned-notice"><strong>Material devuelto: revisa qué se utilizará en esta intervención.</strong>Usa «Reservar de nuevo» y confirma la cantidad si volverás a utilizarlo. Actualizar la solución no descuenta existencias: al aceptar el nuevo cierre se consume únicamente lo reservado.</div>@endcan
@endif
