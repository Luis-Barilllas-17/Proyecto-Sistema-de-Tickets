const confirmation = document.querySelector('[data-confirm-dialog]');
if (confirmation) {
    let pending;
    const approved = new WeakSet();
    const stock = confirmation.querySelector('[data-confirm-stock]');
    const returnOption = stock.querySelector('[value="return"]');
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!form.matches('form[data-confirm]') || event.defaultPrevented) return;
        if (approved.has(form)) { approved.delete(form); return; }
        event.preventDefault();
        pending = { form, submitter: event.submitter, focus: document.activeElement };
        confirmation.querySelector('h2').textContent = form.dataset.confirmTitle || 'Confirmar acción';
        confirmation.querySelector('[data-confirm-description]').textContent = form.dataset.confirm;
        stock.hidden = !(Number(form.dataset.reopenConsumed) > 0);
        stock.querySelector('[value="keep"]').checked = true;
        returnOption.disabled = form.dataset.canReturn !== 'true';
        confirmation.querySelector('[data-confirm-stock-help]').textContent = returnOption.disabled
            ? 'Solo el personal autorizado de inventario puede devolver insumos. Puedes reabrir manteniéndolos consumidos.'
            : 'La decisión quedará registrada en el historial y las devoluciones en el kardex.';
        confirmation.showModal();
    });
    confirmation.querySelector('[data-confirm-cancel]').addEventListener('click', () => confirmation.close());
    confirmation.querySelector('[data-confirm-accept]').addEventListener('click', () => {
        if (!pending) return;
        const { form, submitter } = pending;
        const action = form.querySelector('[name="stock_action"]');
        if (action) action.value = stock.hidden ? 'keep' : stock.querySelector('input:checked').value;
        approved.add(form);
        confirmation.close();
        form.requestSubmit(submitter?.isConnected ? submitter : undefined);
    });
    confirmation.addEventListener('close', () => {
        pending?.focus?.focus();
        pending = null;
    });
}
