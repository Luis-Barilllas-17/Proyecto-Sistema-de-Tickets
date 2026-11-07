@can('reopen', $ticket)
    <details class="panel ticket-action-panel"><summary><span>↺</span><strong>Reabrir ticket</strong></summary>
        <form method="POST" action="{{ route('tickets.reopen', $ticket) }}" data-confirm="¿Reabrir este ticket para continuar la atención?" data-confirm-title="Reabrir ticket" data-reopen-consumed="{{ $ticket->supplyUsages->where('status','consumed')->count() }}" data-can-return="{{ auth()->user()->hasPermission('inventory.consume') ? 'true' : 'false' }}">
            @csrf @method('PATCH')<input type="hidden" name="stock_action" value="keep">
            <div class="field"><label for="ticket-reopen-reason">Motivo</label><textarea id="ticket-reopen-reason" name="reason" rows="3" minlength="10" maxlength="1500" required></textarea></div>
            <button class="button button--primary button--full">Reabrir atención</button>
        </form>
    </details>
@endcan
