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
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const notify = document.querySelector('[data-notify]');
    if (notify) {
        const toggle = notify.querySelector('[data-notify-toggle]');
        const panel = notify.querySelector('[data-notify-panel]');
        const list = notify.querySelector('[data-notify-list]');
        const count = notify.querySelector('[data-notify-count]');
        const paint = (payload) => {
            const unread = Number(payload.unread || 0);
            if (count) {
                count.textContent = String(unread);
                count.hidden = unread < 1;
            }
            if (!list) return;
            if (!payload.items || payload.items.length === 0) {
                list.innerHTML = '<p class="notify-empty">No notifications yet.</p>';
                return;
            }
            list.innerHTML = payload.items.map((item) => {
                const href = item.link || '#';
                return '<a class="notify-item' + (item.read ? '' : ' is-unread') + '" href="' + href + '" data-id="' + item.id + '"><span><strong></strong><p></p></span><time></time></a>';
            }).join('');
            list.querySelectorAll('.notify-item').forEach((link, index) => {
                const item = payload.items[index];
                link.querySelector('strong').textContent = item.title || '';
                link.querySelector('p').textContent = item.body || '';
                link.querySelector('time').textContent = item.time || '';
                link.addEventListener('click', () => {
                    if (item.read) return;
                    fetch(notify.getAttribute('data-read') + '/' + item.id + '/read', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    }).catch(() => {});
                });
            });
        };
        toggle?.addEventListener('click', async () => {
            const open = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
            if (panel) panel.hidden = open;
            if (open) return;
            try {
                const response = await fetch(notify.getAttribute('data-feed'), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                paint(await response.json());
            } catch (error) {
                if (list) list.innerHTML = '<p class="notify-empty">Unable to load notifications.</p>';
            }
        });
        document.addEventListener('click', (event) => {
            if (!notify.contains(event.target) && panel) {
                panel.hidden = true;
                toggle?.setAttribute('aria-expanded', 'false');
            }
        });
    }
    const dialog = document.getElementById('profile-dialog');
    if (dialog) {
        document.querySelector('[data-profile-open]')?.addEventListener('click', () => dialog.showModal());
        dialog.querySelectorAll('[data-profile-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
        const profileForm = dialog.querySelector('[data-profile-form]');
        profileForm?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const error = dialog.querySelector('[data-profile-error]');
            const response = await fetch(profileForm.action, {
                method: 'POST',
                body: new FormData(profileForm),
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            const payload = await response.json();
            if (!response.ok) {
                error.hidden = false;
                error.textContent = payload.message || 'Please check the form.';
                return;
            }
            error.hidden = true;
            const profile = payload.profile || {};
            const set = (selector, value) => { const node = document.querySelector(selector); if (node) node.textContent = value; };
            set('[data-profile-name]', profile.name || '');
            set('[data-profile-phone]', profile.phone || '—');
            set('[data-profile-initials]', profile.initials || '');
            const welcome = document.querySelector('[data-profile-welcome]');
            if (welcome && profile.name) {
                const template = welcome.getAttribute('data-welcome-template') || 'Welcome back, __NAME__.';
                welcome.textContent = template.replace('__NAME__', profile.name.split(' ')[0]);
            }
            dialog.close();
        });
        dialog.querySelector('[data-password-form]')?.addEventListener('submit', async (event) => {
            const form = event.currentTarget;
            event.preventDefault();
            const error = dialog.querySelector('[data-password-error]');
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            const payload = await response.json();
            error.hidden = response.ok;
            if (!response.ok) {
                error.textContent = payload.message || 'Password was not updated.';
                return;
            }
            form.reset();
            dialog.close();
        });
    }
    const trackDialog = document.querySelector('[data-track-dialog]');
    if (trackDialog) {
        try { trackDialog.showModal(); } catch (error) { trackDialog.setAttribute('open', ''); }
        trackDialog.querySelector('[data-track-close]')?.addEventListener('click', () => trackDialog.close());
        trackDialog.querySelector('[data-copy-track]')?.addEventListener('click', async (event) => {
            const button = event.currentTarget;
            const code = document.getElementById('track-code-value')?.textContent?.trim() || '';
            try {
                await navigator.clipboard.writeText(code);
                button.textContent = button.getAttribute('data-copied-label') || 'Copied';
            } catch (error) {
                const field = document.getElementById('track-code-value');
                if (field) {
                    const range = document.createRange();
                    range.selectNodeContents(field);
                    const selection = window.getSelection();
                    selection?.removeAllRanges();
                    selection?.addRange(range);
                }
            }
        });
    }
    document.querySelectorAll('[data-copy-code]').forEach((button) => {
        button.addEventListener('click', async () => {
            const code = button.getAttribute('data-copy-code') || '';
            try {
                await navigator.clipboard.writeText(code);
                const original = button.textContent;
                button.textContent = button.getAttribute('data-copied-label') || 'Copied';
                setTimeout(() => { button.textContent = original; }, 1600);
            } catch (error) {
                window.prompt('Copy tracking code', code);
            }
        });
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
