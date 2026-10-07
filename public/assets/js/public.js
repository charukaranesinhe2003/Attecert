document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('[data-menu-toggle]');
    const nav = document.querySelector('[data-nav]');
    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            const open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }
    document.querySelectorAll('[data-lang]').forEach((select) => {
        select.addEventListener('change', () => {
            if (select.value) {
                window.location.href = select.value;
            }
        });
    });
    document.querySelectorAll('[data-accordion]').forEach((accordion) => {
        accordion.querySelectorAll('[data-accordion-button]').forEach((button) => {
            button.addEventListener('click', () => {
                const panel = button.parentElement.nextElementSibling;
                const open = button.getAttribute('aria-expanded') === 'true';
                accordion.querySelectorAll('[data-accordion-button]').forEach((other) => {
                    other.setAttribute('aria-expanded', 'false');
                    const otherPanel = other.parentElement.nextElementSibling;
                    if (otherPanel) otherPanel.hidden = true;
                });
                button.setAttribute('aria-expanded', open ? 'false' : 'true');
                if (panel) panel.hidden = open;
            });
        });
    });
    document.querySelectorAll('.toast').forEach((toast) => {
        setTimeout(() => toast.remove(), 4200);
    });
    document.querySelectorAll('[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm') || 'Continue?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
});
