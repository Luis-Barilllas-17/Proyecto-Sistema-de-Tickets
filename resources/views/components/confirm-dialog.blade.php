<dialog class="system-confirm" data-confirm-dialog aria-labelledby="system-confirm-title" aria-describedby="system-confirm-description">
    <div class="system-confirm__heading"><span aria-hidden="true">!</span><div><small>Confirmación de la operación</small><h2 id="system-confirm-title">Confirmar acción</h2></div></div>
    <p id="system-confirm-description" data-confirm-description></p>
    <fieldset data-confirm-stock hidden><legend>Insumos consumidos al cerrar</legend>
        <label><input type="radio" name="confirm-stock-action" value="keep" checked><span><strong>Mantener consumidos</strong><small>No modificar las existencias del inventario.</small></span></label>
        <label><input type="radio" name="confirm-stock-action" value="return"><span><strong>Devolver al inventario</strong><small>Restituir los insumos consumidos de este ticket. Confirma que fueron recibidos físicamente y no se utilizaron.</small></span></label>
        <p data-confirm-stock-help></p>
    </fieldset>
    <div class="system-confirm__actions"><button type="button" class="button button--outline" data-confirm-cancel autofocus>Cancelar</button><button type="button" class="button button--primary" data-confirm-accept>Confirmar</button></div>
</dialog>
