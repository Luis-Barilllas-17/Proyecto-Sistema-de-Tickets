document.querySelectorAll('[data-search-picker]').forEach((root) => {
    const input = root.querySelector('[data-picker-search]');
    const results = root.querySelector('[data-picker-results]');
    const chosen = root.querySelector('[data-picker-selected]');
    const inputs = root.querySelector('[data-picker-inputs]');
    const status = root.querySelector('[data-picker-status]');
    const clear = root.querySelector('[data-picker-clear]');
    const multiple = root.dataset.multiple === 'true';
    const disabled = root.dataset.disabled === 'true';
    const limit = Number(root.dataset.limit) || 10;
    const selection = new Map(JSON.parse(root.dataset.selected || '[]').map((item) => [String(item.value), item]));
    let items = [];
    let highlighted = -1;
    let timer;
    let request;
    let generation = 0;

    const notify = (item) => root.dispatchEvent(new CustomEvent('search-picker:change', {
        bubbles: true, detail: { name: root.dataset.name, item },
    }));

    const cancel = () => {
        window.clearTimeout(timer);
        request?.abort();
        generation += 1;
    };

    const close = () => {
        cancel();
        results.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        highlighted = -1;
    };

    const renderSelection = (updateText = true) => {
        inputs.replaceChildren();
        chosen.replaceChildren();
        if (!multiple && !disabled) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.id = root.dataset.name;
            hidden.name = root.dataset.name;
            hidden.value = selection.values().next().value?.value || '';
            inputs.append(hidden);
        }
        selection.forEach((item, key) => {
            if (multiple) {
                if (!disabled) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = item.input_name || root.dataset.name;
                    hidden.value = item.input_value || item.value;
                    inputs.append(hidden);
                }
                const chip = document.createElement('span');
                chip.className = 'search-picker__chip';
                const label = document.createElement('span');
                label.textContent = item.label;
                chip.append(label);
                if (!disabled) {
                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.textContent = '×';
                    remove.setAttribute('aria-label', `Quitar ${item.label}`);
                    remove.addEventListener('click', () => {
                        selection.delete(key);
                        renderSelection();
                        status.textContent = `${selection.size} de ${limit} equipos seleccionados.`;
                        notify(null);
                        input.focus();
                    });
                    chip.append(remove);
                }
                if (item.description?.startsWith('Referencia anterior')) chip.title = item.description;
                chosen.append(chip);
            } else {
                if (updateText) input.value = item.label;
                const description = document.createElement('span');
                description.className = 'search-picker__identity';
                description.textContent = item.description || '';
                chosen.append(description);
            }
        });
        if (clear) clear.hidden = selection.size === 0;
    };

    const choose = (item) => {
        if (multiple && (selection.has(String(item.value)) || selection.size >= limit)) return;
        if (!multiple) selection.clear();
        selection.set(String(item.value), item);
        renderSelection();
        if (multiple) input.value = '';
        status.textContent = multiple ? `${selection.size} de ${limit} equipos seleccionados.` : `Selección confirmada: ${item.label}.`;
        close();
        notify(item);
    };

    const highlight = (index) => {
        const options = [...results.querySelectorAll('[role="option"]')];
        options.forEach((option, position) => option.classList.toggle('is-highlighted', position === index));
        highlighted = index;
        if (options[index]) {
            input.setAttribute('aria-activedescendant', options[index].id);
            options[index].scrollIntoView({ block: 'nearest' });
        } else input.removeAttribute('aria-activedescendant');
    };

    const renderResults = (data) => {
        items = data.results || [];
        results.replaceChildren();
        items.forEach((item, index) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.className = 'search-picker__option';
            option.id = `${input.id}-option-${index}`;
            option.tabIndex = -1;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', String(selection.has(String(item.value))));
            option.disabled = multiple && (selection.has(String(item.value)) || selection.size >= limit);
            const label = document.createElement('strong');
            label.textContent = item.label;
            const description = document.createElement('span');
            description.textContent = item.description || '';
            option.append(label, description);
            option.addEventListener('mousedown', (event) => event.preventDefault());
            option.addEventListener('click', () => choose(item));
            results.append(option);
        });
        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'search-picker__empty';
            empty.textContent = 'No se encontraron coincidencias.';
            results.append(empty);
        }
        results.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        status.textContent = multiple && selection.size >= limit ? `Límite de ${limit} equipos. Quita una selección para agregar otro.`
            : data.has_more ? 'Hay más coincidencias. Escribe más letras para afinar la búsqueda.' : `${items.length} resultado(s).`;
        highlight([...results.querySelectorAll('[role="option"]')].findIndex((option) => !option.disabled));
    };

    const load = async (query) => {
        cancel();
        const current = generation;
        request = new AbortController();
        status.textContent = 'Buscando…';
        const url = new URL(root.dataset.endpoint, window.location.origin);
        url.searchParams.set('q', query.trim().slice(0, 100));
        try {
            const response = await fetch(url, { signal: request.signal, headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('lookup');
            const data = await response.json();
            if (current === generation) renderResults(data);
        } catch (error) {
            if (error.name !== 'AbortError' && current === generation) {
                close();
                status.textContent = 'No fue posible cargar el listado. Vuelve a intentarlo.';
            }
        }
    };

    renderSelection();
    if (disabled) return;

    root.addEventListener('search-picker:select', (event) => {
        const item = event.detail?.item;
        if (item?.value && item?.label) choose(item);
    });

    input.addEventListener('focus', () => {
        if (!multiple && selection.size) input.select();
        load(!multiple && selection.size ? '' : input.value);
    });
    input.addEventListener('input', () => {
        cancel();
        results.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        if (!multiple) {
            selection.clear();
            renderSelection(false);
            notify(null);
        }
        timer = window.setTimeout(() => load(input.value), 250);
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
            event.stopPropagation();
        } else if (['ArrowDown', 'ArrowUp'].includes(event.key)) {
            event.preventDefault();
            if (results.hidden) { load(input.value); return; }
            const options = [...results.querySelectorAll('[role="option"]')];
            const step = event.key === 'ArrowDown' ? 1 : -1;
            for (let offset = 1; offset <= options.length; offset += 1) {
                const index = (highlighted + step * offset + options.length) % options.length;
                if (!options[index].disabled) { highlight(index); break; }
            }
        } else if (event.key === 'Enter' && !results.hidden) {
            event.preventDefault();
            if (highlighted >= 0) choose(items[highlighted]);
        }
    });
    clear?.addEventListener('click', () => {
        selection.clear();
        input.value = '';
        renderSelection();
        notify(null);
        input.focus();
        load('');
    });
    root.addEventListener('focusout', (event) => { if (!root.contains(event.relatedTarget)) close(); });
    document.addEventListener('pointerdown', (event) => { if (!root.contains(event.target)) close(); });
    root.closest('form')?.addEventListener('submit', (event) => {
        if (!multiple && selection.size === 0) {
            event.preventDefault();
            input.focus();
            status.textContent = `Selecciona una opción del listado de ${root.dataset.label} antes de guardar.`;
        }
    });
});
