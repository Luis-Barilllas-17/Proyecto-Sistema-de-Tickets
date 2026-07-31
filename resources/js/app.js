import './bootstrap';

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
