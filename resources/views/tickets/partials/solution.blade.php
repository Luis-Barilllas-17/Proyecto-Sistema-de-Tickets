@php($pendingSolution = $ticket->solutionNeedsUpdate())
<section class="panel ticket-resolution" id="ticket-resolution">
    <div class="panel__header"><div><span class="panel__eyebrow">Resolución del caso</span><h2>{{ $pendingSolution ? 'Actualizar solución tras reapertura' : ($ticket->solution ? 'Solución registrada' : 'Registrar solución') }}</h2></div>
        @if($ticket->solution)
            <div class="ticket-solution-actions">
                @can('solve', $ticket)<button type="button" class="button button--outline button--small" data-edit-solution aria-controls="ticket-solution-editor" aria-expanded="false">Editar solución</button>@endcan
                @can('deleteSolution', $ticket)<form method="POST" action="{{ route('tickets.solution.destroy', $ticket) }}" data-delete-solution data-confirm-title="Eliminar solución" data-confirm="¿Eliminar la solución actual y devolver el ticket a atención? Se conservarán el historial, los adjuntos y los movimientos de inventario.">@csrf @method('DELETE')<button class="button button--danger-soft button--small">Eliminar solución</button></form>@endcan
            </div>
        @endif
    </div>
    @if($ticket->solution)
        <div class="ticket-resolution__summary {{ $pendingSolution ? 'ticket-resolution__summary--pending' : '' }}"><span aria-hidden="true">{{ $pendingSolution ? '!' : '✓' }}</span><div>@if($pendingSolution)<strong>Solución anterior: pendiente de actualizar y enviar nuevamente.</strong>@endif<p>{{ $ticket->solution }}</p><small>{{ $pendingSolution ? 'Autor de la versión anterior:' : 'Documentada por' }} {{ $ticket->solutionBy?->name }}@if($ticket->solved_at) · {{ $ticket->solved_at->format('d/m/Y H:i') }}@endif</small></div></div>
    @endif
    @can('solve', $ticket)
        <form id="ticket-solution-editor" data-solution-editor data-auto-edit="{{ session('edit_solution') ? 'true' : 'false' }}" method="POST" action="{{ route('tickets.solution.store', $ticket) }}" enctype="multipart/form-data" @if($ticket->solution && ! $pendingSolution && ! session('edit_solution') && ! $errors->has('solution')) hidden @endif>@csrf
            <div class="field"><label for="ticket-solution">Solución aplicada</label><textarea id="ticket-solution" name="solution" rows="4" required minlength="20" maxlength="15000">{{ old('solution', $ticket->solution) }}</textarea></div>
            <div class="field"><label for="ticket-solution-files">Evidencias finales</label><input id="ticket-solution-files" type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip"></div>
            <div class="ticket-resolution__actions">@if($ticket->solution)<button type="button" class="button button--outline" data-cancel-solution>{{ $pendingSolution ? 'Ocultar editor' : 'Cancelar edición' }}</button>@endif<button class="button button--primary">{{ $pendingSolution ? 'Enviar solución actualizada' : ($ticket->solution ? 'Guardar solución' : 'Enviar solución') }}</button></div>
        </form>
    @else
        @if(! $ticket->solution || $pendingSolution)<p class="ticket-resolution__waiting">El equipo técnico debe {{ $pendingSolution ? 'actualizar y enviar nuevamente' : 'registrar' }} la solución antes de que puedas calificar el servicio.</p>@endif
    @endcan
</section>
