import './bootstrap';

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

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('.section-tabs a').forEach((tab) => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.section-tabs a').forEach((item) => item.classList.remove('is-active'));
        tab.classList.add('is-active');
    });
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
        if (!option.dataset.area) return;
        option.hidden = Boolean(areaId) && option.dataset.area !== areaId;
        option.disabled = Boolean(areaId) && option.dataset.area !== areaId;
    });

    const selected = departmentSelect.selectedOptions[0];
    if (selected?.disabled) departmentSelect.value = '';
};

if (areaSelect && departmentSelect) {
    filterDepartments();
    areaSelect.addEventListener('change', filterDepartments);
}

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
            item.textContent = `${file.name} · ${(file.size / 1024 / 1024).toFixed(1)} MB`;
            return item;
        }));
    });
});

document.querySelectorAll('[data-assignment-form]').forEach((form) => {
    const area = form.querySelector('[data-it-area-select]');
    const group = form.querySelector('[data-it-group-select]');
    if (!area || !group) return;

    const filterGroups = () => {
        [...group.options].forEach((option) => {
            if (!option.dataset.area) return;
            const matches = !area.value || option.dataset.area === area.value;
            option.hidden = !matches;
            option.disabled = !matches;
        });
        if (group.selectedOptions[0]?.disabled) group.value = '';
    };

    filterGroups();
    area.addEventListener('change', filterGroups);
});
