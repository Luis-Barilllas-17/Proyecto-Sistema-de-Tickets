<section class="panel ticket-origin-card" aria-labelledby="ticket-origin-title">
    <div>
        <span class="eyebrow">Formulario de administración y técnicos</span>
        <h2 id="ticket-origin-title">Registro de IT</h2>
        <p>El creador del registro queda identificado en el historial. La clasificación conserva las reglas de prioridad y aprobación; la asignación del técnico es manual.</p>
    </div>
    <div class="field">
        <label for="ticket_origin">Tipo de registro</label>
        <select id="ticket_origin" name="is_internal" @disabled($editing)>
            <option value="0" @selected(! old('is_internal', $ticket?->source === 'it_internal'))>Solicitud de atención</option>
            <option value="1" @selected(old('is_internal', $ticket?->source === 'it_internal'))>Actividad interna de IT</option>
        </select>
        <span class="field__hint">La solicitud de atención permite al solicitante aceptar y calificar la solución. La actividad interna conserva el flujo interno de IT.</span>
    </div>
    <x-search-picker id="requester" name="requester_id" label="Solicitante de la atención"
        :endpoint="route('tickets.lookups.requesters')" :selected="$requesterSelection" :disabled="$editing"
        placeholder="Buscar por nombre, correo o código…"
        :hint="$editing ? 'El solicitante se conserva al editar el borrador.' : 'Escribe para filtrar y selecciona una persona. Puedes cambiar su área y departamento para esta atención.'" />
</section>
