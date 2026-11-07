import './bootstrap';
import './search-picker';
import './ticket-detail';
import './confirmation-dialog';
import '../css/ticket-detail.css';

const pageLoader = document.querySelector('[data-page-loader]');

if (pageLoader) {
    const storageKey = pageLoader.dataset.loaderKey || 'zolic-page-loader';
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let wasShown = false;

    try {
        wasShown = window.sessionStorage.getItem(storageKey) === 'shown';
        window.sessionStorage.setItem(storageKey, 'shown');
    } catch {
        wasShown = false;
    }

    const displayTime = reduceMotion ? 150 : (wasShown ? 350 : 4000);

    window.setTimeout(() => {
        pageLoader.classList.add('is-leaving');
        document.body.classList.remove('has-page-loader');
        pageLoader.setAttribute('aria-hidden', 'true');

        window.setTimeout(() => pageLoader.remove(), reduceMotion ? 20 : 700);
    }, displayTime);
}

document.querySelectorAll('[data-reset-page-loader]').forEach((form) => {
    form.addEventListener('submit', () => {
        try {
            window.sessionStorage.removeItem('zolic-dashboard-loader');
        } catch {
            // El cierre de sesión no debe depender del almacenamiento del navegador.
        }
    });
});

document.querySelectorAll('[data-logout-submit]').forEach((button) => {
    button.addEventListener('click', (event) => {
        const form = button.form;

        if (!form) return;

        event.preventDefault();
        form.setAttribute('method', 'post');
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    });
});

const sidebar = document.querySelector('#sidebar');
const overlay = document.querySelector('.sidebar-overlay');

const setSidebar = (open) => {
    if (!sidebar || !overlay) return;

    sidebar.classList.toggle('is-open', open);
    overlay.classList.toggle('is-visible', open);
    document.body.style.overflow = open ? 'hidden' : '';
};

document.querySelectorAll('[data-sidebar-open]').forEach((button) => {
    button.addEventListener('click', () => setSidebar(true));
});

document.querySelectorAll('[data-sidebar-close]').forEach((button) => {
    button.addEventListener('click', () => setSidebar(false));
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setSidebar(false);
});

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const shell = button.closest('.input-shell');
        const input = shell?.querySelector('[data-password-input]');

        if (!input) return;

        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.querySelector('span').textContent = show ? 'Ocultar' : 'Ver';
        button.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
});

document.querySelectorAll('.section-tabs a').forEach((tab) => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.section-tabs a').forEach((item) => item.classList.remove('is-active'));
        tab.classList.add('is-active');
    });
});

document.querySelectorAll('[data-horizontal-scroll]').forEach((container) => {
    const viewport = container.querySelector('[data-scroll-body]');
    const top = container.querySelector('[data-scroll-top]');
    const spacer = container.querySelector('[data-scroll-spacer]');
    const controls = container.querySelector('[data-scroll-controls]');
    const table = viewport?.querySelector('table');
    const buttons = [...container.querySelectorAll('[data-scroll-direction]')];
    if (!viewport || !top || !spacer || !controls || !table) return;

    const updateButtons = () => {
        const maximum = viewport.scrollWidth - viewport.clientWidth;
        buttons.forEach((button) => {
            button.disabled = Number(button.dataset.scrollDirection) < 0
                ? viewport.scrollLeft <= 1 : viewport.scrollLeft >= maximum - 1;
        });
    };

    const sync = (source, target) => {
        if (Math.abs(source.scrollLeft - target.scrollLeft) > 1) target.scrollLeft = source.scrollLeft;
        updateButtons();
    };
    const resize = () => {
        controls.hidden = viewport.scrollWidth <= viewport.clientWidth + 1;
        spacer.style.width = `${viewport.scrollWidth}px`;
        top.scrollLeft = viewport.scrollLeft;
        updateButtons();
    };

    viewport.addEventListener('scroll', () => sync(viewport, top), { passive: true });
    top.addEventListener('scroll', () => sync(top, viewport), { passive: true });
    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const distance = Math.max(180, viewport.clientWidth * 0.7) * Number(button.dataset.scrollDirection);
            viewport.scrollBy({ left: distance, behavior: 'instant' });
        });
    });

    const observer = new ResizeObserver(resize);
    observer.observe(viewport);
    observer.observe(table);
    document.fonts?.ready.then(resize);
    resize();
});

document.querySelectorAll('[data-auto-dismiss]').forEach((alert) => {
    window.setTimeout(() => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-8px)';
        window.setTimeout(() => alert.remove(), 180);
    }, 5000);
});

const areaSelect = document.querySelector('[data-area-select]');
const departmentSelect = document.querySelector('[data-department-select]');

const filterDepartments = () => {
    if (!areaSelect || !departmentSelect) return;

    const areaId = areaSelect.value;
    [...departmentSelect.options].forEach((option) => {
        const matches = !option.dataset.area || (Boolean(areaId) && option.dataset.area === areaId);
        option.hidden = !matches;
        option.disabled = !matches;
    });

    const selected = departmentSelect.selectedOptions[0];
    if (selected?.disabled) departmentSelect.value = '';
    departmentSelect.disabled = !areaId;
};

if (areaSelect && departmentSelect) {
    filterDepartments();
    areaSelect.addEventListener('change', filterDepartments);
}

document.addEventListener('search-picker:change', (event) => {
    if (event.detail.name !== 'requester_id' || !event.detail.item) return;
    const form = event.target.closest('[data-ticket-form]');
    if (!form) return;
    const area = form.querySelector('[data-area-select]');
    const department = form.querySelector('[data-department-select]');
    if (!area || !department) return;
    area.value = event.detail.item.area_id || '';
    department.value = event.detail.item.department_id || '';
    filterDepartments();
});

document.querySelectorAll('[data-check-group]').forEach((button) => {
    button.addEventListener('click', () => {
        const group = document.querySelector(`#${button.dataset.checkGroup}`);
        const checkboxes = [...(group?.querySelectorAll('input[type="checkbox"]') ?? [])];
        const shouldCheck = checkboxes.some((checkbox) => !checkbox.checked);

        checkboxes.forEach((checkbox) => {
            checkbox.checked = shouldCheck;
        });
        button.textContent = shouldCheck ? 'Quitar selección' : 'Seleccionar todo';
    });
});

document.querySelectorAll('[data-classification-form]').forEach((form) => {
    const category = form.querySelector('[data-category-select]');
    const categoryRadios = [...form.querySelectorAll('[data-category-radio]')];
    const subcategory = form.querySelector('[data-subcategory-select]');

    if ((!category && categoryRadios.length === 0) || !subcategory) return;

    const filterSubcategories = () => {
        const categoryId = category?.value ?? categoryRadios.find((radio) => radio.checked)?.value ?? '';
        [...subcategory.options].forEach((option) => {
            if (!option.dataset.category) return;
            const matches = Boolean(categoryId) && option.dataset.category === categoryId;
            option.hidden = !matches;
            option.disabled = !matches;
        });

        if (subcategory.selectedOptions[0]?.disabled) subcategory.value = '';
    };

    filterSubcategories();
    category?.addEventListener('change', filterSubcategories);
    categoryRadios.forEach((radio) => radio.addEventListener('change', filterSubcategories));
});

document.querySelectorAll('[data-approver-form]').forEach((form) => {
    const type = form.querySelector('[data-approver-type]');
    const userField = form.querySelector('[data-approver-user]');
    const roleField = form.querySelector('[data-approver-role]');

    if (!type || !userField || !roleField) return;

    const updateApproverFields = () => {
        const showUser = type.value === 'specific_user';
        const showRole = type.value === 'role';
        userField.hidden = !showUser;
        roleField.hidden = !showRole;
        userField.querySelector('select').disabled = !showUser;
        roleField.querySelector('select').disabled = !showRole;
    };

    updateApproverFields();
    type.addEventListener('change', updateApproverFields);
});

document.querySelectorAll('.color-input input[type="color"]').forEach((input) => {
    const label = input.closest('.color-input')?.querySelector('span');
    input.addEventListener('input', () => {
        if (label) label.textContent = input.value.toUpperCase();
    });
});

document.querySelectorAll('[data-file-input]').forEach((input) => {
    const list = input.parentElement?.querySelector('[data-file-list]');
    input.addEventListener('change', () => {
        if (!list) return;
        list.replaceChildren(...[...input.files].map((file) => {
            const item = document.createElement('span');
            const size = file.size < 1024 ? `${file.size} bytes`
                : file.size < 1024 * 1024 ? `${(file.size / 1024).toFixed(1)} KB`
                    : `${(file.size / 1024 / 1024).toFixed(1)} MB`;
            item.textContent = `${file.name} · ${size}`;
            return item;
        }));
    });
});

document.querySelectorAll('[data-assignment-form]').forEach((form) => {
    const area = form.querySelector('[data-it-area-select]');
    const group = form.querySelector('[data-it-group-select]');
    const technician = form.querySelector('[data-technician-select]');
    if (!area || !group) return;

    const filterGroups = () => {
        [...group.options].forEach((option) => {
            if (!option.dataset.area) return;
            const matches = !area.value || option.dataset.area === area.value;
            option.hidden = !matches;
            option.disabled = !matches;
        });
        if (group.selectedOptions[0]?.disabled) group.value = '';
        if (technician) {
            [...technician.options].forEach((option) => {
                if (!option.value) return;
                const memberships = JSON.parse(option.dataset.groups || '[]').map(String);
                const matches = (!area.value || option.dataset.area === area.value) && (!group.value || memberships.includes(group.value));
                option.hidden = !matches;
                option.disabled = !matches;
            });
            if (technician.selectedOptions[0]?.disabled) technician.value = '';
        }
    };

    filterGroups();
    area.addEventListener('change', filterGroups);
    group.addEventListener('change', filterGroups);
});
