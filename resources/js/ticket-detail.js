document.querySelectorAll('[data-message-composer]').forEach((form) => {
    const toggle = form.querySelector('[data-message-private]');
    const render = () => {
        const internal = Boolean(toggle?.checked);
        form.classList.toggle('is-internal', internal);
        form.querySelector('[data-message-label]').textContent = internal ? 'Nota interna para IT' : 'Comentario visible';
        form.querySelector('[data-message-submit]').textContent = internal ? 'Guardar nota interna' : 'Publicar comentario';
        form.querySelector('[data-message-help]').textContent = internal
            ? 'Solo el personal de IT autorizado podrá ver esta nota y sus adjuntos.'
            : 'El solicitante y el equipo de IT podrán ver este comentario y sus adjuntos.';
    };
    toggle?.addEventListener('change', render);
    render();
});

const solutionEditor = document.querySelector('[data-solution-editor]');
const editSolution = document.querySelector('[data-edit-solution]');
if (solutionEditor) {
    const setOpen = (open) => {
        solutionEditor.hidden = !open;
        editSolution?.setAttribute('aria-expanded', String(open));
        if (open) solutionEditor.querySelector('textarea').focus();
        else editSolution?.focus();
    };
    editSolution?.addEventListener('click', () => setOpen(solutionEditor.hidden));
    solutionEditor.querySelector('[data-cancel-solution]')?.addEventListener('click', () => setOpen(false));
    editSolution?.setAttribute('aria-expanded', String(!solutionEditor.hidden));
    if (solutionEditor.dataset.autoEdit === 'true') {
        window.requestAnimationFrame(() => {
            setOpen(true);
            solutionEditor.scrollIntoView({ block: 'start' });
        });
    }
}

document.querySelectorAll('[data-star-rating]').forEach((root) => {
    const radios = [...root.querySelectorAll('input[type="radio"]')];
    const status = root.querySelector('[data-rating-status]');
    const render = (value) => radios.forEach((radio) => radio.closest('label').classList.toggle('is-filled', Number(radio.value) <= value));
    const current = () => Number(radios.find((radio) => radio.checked)?.value || 0);
    radios.forEach((radio) => {
        radio.addEventListener('change', () => {
            render(current());
            status.textContent = `${current()} de 5 estrellas`;
        });
        radio.closest('label').addEventListener('pointerenter', () => render(Number(radio.value)));
    });
    root.addEventListener('pointerleave', () => render(current()));
    render(current());
});

const supplyForm = document.querySelector('.ticket-supply-form');
if (supplyForm) {
    const quantity = supplyForm.querySelector('[name="quantity"]');
    const setMax = (item) => { quantity.max = item?.available ?? 9999999999; };
    const picker = supplyForm.querySelector('[data-search-picker]');
    setMax(JSON.parse(picker.dataset.selected || '[]')[0]);
    picker.addEventListener('search-picker:change', (event) => setMax(event.detail.item));
    document.querySelectorAll('[data-reserve-returned]').forEach((button) => {
        button.addEventListener('click', async () => {
            button.disabled = true;
            const status = picker.querySelector('[data-picker-status]');
            status.textContent = 'Consultando la disponibilidad actual…';
            try {
                const url = new URL(picker.dataset.endpoint, window.location.origin);
                url.searchParams.set('q', button.dataset.supplyCode);
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('lookup');
                const data = await response.json();
                const item = data.results.find((entry) => String(entry.value) === button.dataset.supplyId);
                if (!item) throw new Error('unavailable');
                picker.dispatchEvent(new CustomEvent('search-picker:select', { detail: { item } }));
                const previous = Number(button.dataset.quantity);
                quantity.value = Number.isInteger(previous) ? String(previous) : '';
                supplyForm.querySelector('[name="notes"]').value = button.dataset.notes || '';
                supplyForm.scrollIntoView({ block: 'center' });
                quantity.focus({ preventScroll: true });
                status.textContent = 'Revisa la cantidad y pulsa Reservar insumo. No se reserva automáticamente.';
            } catch {
                status.textContent = 'Este insumo no está disponible para reservar ahora. Revisa el inventario.';
                supplyForm.scrollIntoView({ block: 'center' });
            } finally {
                button.disabled = false;
            }
        });
    });
}

const dialog = document.querySelector('[data-attachment-dialog]');
if (dialog) {
    const body = dialog.querySelector('[data-preview-body]');
    const title = dialog.querySelector('h2');
    const download = dialog.querySelector('[data-preview-download]');
    let request;
    let trigger;
    let generation = 0;
    const message = (text) => {
        const paragraph = document.createElement('p');
        paragraph.className = 'ticket-preview__notice';
        paragraph.textContent = text;
        body.append(paragraph);
    };
    const render = (data) => {
        body.replaceChildren();
        if (data.notice) message(data.notice);
        if (data.type === 'image') {
            const image = document.createElement('img');
            image.src = data.url;
            image.alt = title.textContent;
            image.addEventListener('error', () => message('No se pudo cargar la imagen. Puedes descargar el original.'));
            body.append(image);
        } else if (data.type === 'pdf') {
            const frame = document.createElement('iframe');
            frame.src = data.url;
            frame.title = `Vista previa de ${title.textContent}`;
            body.append(frame);
            message('Si tu navegador no muestra el PDF, utiliza Descargar original.');
        } else if (data.type === 'text') {
            const text = document.createElement('pre');
            text.textContent = data.text;
            body.append(text);
        } else if (data.type === 'spreadsheet') {
            data.sheets.forEach((sheet) => {
                const heading = document.createElement('h3');
                heading.textContent = sheet.name;
                const scroll = document.createElement('div');
                scroll.className = 'ticket-preview__table-scroll';
                scroll.tabIndex = 0;
                scroll.setAttribute('role', 'region');
                scroll.setAttribute('aria-label', sheet.name);
                const table = document.createElement('table');
                const caption = document.createElement('caption');
                caption.textContent = `${sheet.name} · Vista de datos`;
                table.append(caption);
                const columns = Math.max(0, ...sheet.rows.map((row) => row.length));
                const head = table.createTHead().insertRow();
                for (let column = 0; column <= columns; column += 1) {
                    const cell = document.createElement('th');
                    cell.scope = 'col';
                    cell.textContent = column === 0 ? 'Fila' : column > 26 ? `A${String.fromCharCode(64 + column - 26)}` : String.fromCharCode(64 + column);
                    head.append(cell);
                }
                const rows = table.createTBody();
                sheet.rows.forEach((row, index) => {
                    const tr = rows.insertRow();
                    const number = document.createElement('th');
                    number.scope = 'row';
                    number.textContent = String(sheet.row_numbers?.[index] || index + 1);
                    tr.append(number);
                    for (let column = 0; column < columns; column += 1) tr.insertCell().textContent = row[column] || '';
                });
                scroll.append(table);
                body.append(heading, scroll);
            });
        }
    };
    document.querySelectorAll('[data-attachment-preview]').forEach((link) => {
        link.addEventListener('click', async (event) => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            request?.abort();
            request = new AbortController();
            const current = ++generation;
            trigger = link;
            title.textContent = link.dataset.attachmentName;
            download.href = link.href;
            body.replaceChildren();
            message('Cargando vista previa…');
            body.setAttribute('aria-busy', 'true');
            dialog.showModal();
            try {
                const response = await fetch(link.dataset.attachmentPreview, { signal: request.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error(String(response.status));
                const data = await response.json();
                if (current === generation && dialog.open) render(data);
            } catch (error) {
                if (error.name !== 'AbortError' && current === generation) {
                    body.replaceChildren();
                    message('No fue posible abrir el archivo. Verifica tu sesión y tus permisos e inténtalo de nuevo.');
                }
            } finally {
                if (current === generation) body.setAttribute('aria-busy', 'false');
            }
        });
    });
    dialog.querySelector('[data-preview-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        const box = dialog.getBoundingClientRect();
        if (event.target === dialog && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) dialog.close();
    });
    dialog.addEventListener('close', () => {
        generation += 1;
        request?.abort();
        body.replaceChildren();
        trigger?.focus();
    });
}
