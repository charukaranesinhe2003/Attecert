document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('[data-portal-nav]')?.addEventListener('click', () => {
        document.getElementById('portal-nav')?.classList.toggle('is-open');
    });

    document.querySelectorAll('[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm');
            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('form[data-busy]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[type="submit"]');
            if (button) {
                button.classList.add('is-busy');
                button.disabled = true;
                if (!button.dataset.originalLabel) {
                    button.dataset.originalLabel = button.textContent || '';
                }
                const busy = form.getAttribute('data-busy');
                if (busy) button.textContent = busy;
            }
        });
    });

    const quick = document.querySelector('[data-admin-quick]');
    if (quick) {
        const toggle = quick.querySelector('[data-admin-quick-toggle]');
        const menu = quick.querySelector('[data-admin-quick-menu]');
        const closeQuick = () => {
            quick.classList.remove('is-open');
            menu?.setAttribute('hidden', '');
            toggle?.setAttribute('aria-expanded', 'false');
        };
        const openQuick = () => {
            quick.classList.add('is-open');
            menu?.removeAttribute('hidden');
            toggle?.setAttribute('aria-expanded', 'true');
        };
        toggle?.addEventListener('click', (event) => {
            event.stopPropagation();
            if (quick.classList.contains('is-open')) closeQuick();
            else openQuick();
        });
        document.addEventListener('click', (event) => {
            if (!quick.contains(event.target)) closeQuick();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeQuick();
        });
        menu?.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => closeQuick());
        });
    }

    const searchForm = document.querySelector('[data-admin-search]');
    const searchInput = document.querySelector('[data-admin-search-input]');
    const searchResults = document.querySelector('[data-admin-search-results]');
    let searchTimer = null;
    let activeIndex = -1;

    const hideSearch = () => {
        searchResults?.setAttribute('hidden', '');
        activeIndex = -1;
    };

    const renderSearch = (groups) => {
        if (!searchResults) return;
        if (!groups.length) {
            searchResults.innerHTML = '<div class="admin-search-group"><strong>No matches</strong></div>';
            searchResults.removeAttribute('hidden');
            return;
        }
        searchResults.innerHTML = groups.map((group) => `
            <div class="admin-search-group">
                <strong>${escapeHtml(group.label)}</strong>
                ${group.items.map((item) => `
                    <a class="admin-search-item" href="${escapeAttr(item.href)}">
                        ${escapeHtml(item.title)}
                        <span>${escapeHtml(item.meta || '')}</span>
                    </a>
                `).join('')}
            </div>
        `).join('');
        searchResults.removeAttribute('hidden');
        activeIndex = -1;
    };

    const runSearch = async (query) => {
        if (!window.AtteCertAdmin?.searchUrl) return;
        const url = new URL(window.AtteCertAdmin.searchUrl, window.location.origin);
        url.searchParams.set('q', query);
        const response = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        if (!response.ok) return;
        const data = await response.json();
        renderSearch(data.results || []);
    };

    searchInput?.addEventListener('input', () => {
        const query = searchInput.value.trim();
        clearTimeout(searchTimer);
        if (query.length < 2) {
            hideSearch();
            return;
        }
        searchTimer = setTimeout(() => {
            runSearch(query).catch(() => hideSearch());
        }, 220);
    });

    searchInput?.addEventListener('keydown', (event) => {
        if (!searchResults || searchResults.hasAttribute('hidden')) return;
        const items = [...searchResults.querySelectorAll('.admin-search-item')];
        if (!items.length) return;
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            activeIndex = Math.min(items.length - 1, activeIndex + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            activeIndex = Math.max(0, activeIndex - 1);
        } else if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            window.location.href = items[activeIndex].href;
            return;
        } else if (event.key === 'Escape') {
            hideSearch();
            return;
        } else {
            return;
        }
        items.forEach((item, index) => item.classList.toggle('is-active', index === activeIndex));
        items[activeIndex]?.scrollIntoView({ block: 'nearest' });
    });

    document.addEventListener('click', (event) => {
        if (searchForm && !searchForm.contains(event.target)) hideSearch();
    });

    const tabRoot = document.querySelector('[data-case-tabs]');
    if (tabRoot) {
        const buttons = [...tabRoot.querySelectorAll('[data-tab]')];
        const panels = [...document.querySelectorAll('[data-panel]')];
        const activate = (name) => {
            buttons.forEach((button) => button.classList.toggle('is-active', button.dataset.tab === name));
            panels.forEach((panel) => {
                if (panel.dataset.panel === name) panel.removeAttribute('hidden');
                else panel.setAttribute('hidden', '');
            });
            if (window.history.replaceState) {
                window.history.replaceState(null, '', '#' + name);
            }
        };
        buttons.forEach((button) => {
            button.addEventListener('click', () => activate(button.dataset.tab));
        });
        const hash = (window.location.hash || '#overview').replace('#', '');
        const initial = buttons.some((button) => button.dataset.tab === hash) ? hash : 'overview';
        activate(initial);
        window.addEventListener('hashchange', () => {
            const next = (window.location.hash || '#overview').replace('#', '');
            if (buttons.some((button) => button.dataset.tab === next)) activate(next);
        });
    }

    const builder = document.querySelector('[data-quote-builder]');
    if (builder) {
        const total = builder.querySelector('[data-total]');
        const recalc = () => {
            let sum = 0;
            builder.querySelectorAll('[data-line]').forEach((row) => {
                const checked = row.querySelector('[data-selected]');
                if (checked && !checked.checked) {
                    const out = row.querySelector('[data-line-total]');
                    if (out) out.textContent = '0.00';
                    return;
                }
                const qty = parseFloat(row.querySelector('[data-qty]')?.value || '0');
                const price = parseFloat(row.querySelector('[data-price]')?.value || '0');
                const line = Math.round(qty * price * 100) / 100;
                const out = row.querySelector('[data-line-total]');
                if (out) out.textContent = line.toFixed(2);
                sum += line;
            });
            const discount = parseFloat(builder.querySelector('[name="discount_amount"]')?.value || '0');
            const taxRate = parseFloat(builder.querySelector('[name="tax_rate"]')?.value || '0');
            const extra = parseFloat(builder.querySelector('[name="additional_amount"]')?.value || '0');
            const taxable = Math.max(0, sum - discount);
            const grand = taxable + (taxable * taxRate / 100) + extra;
            if (total) total.textContent = grand.toFixed(2);
        };
        builder.addEventListener('input', recalc);
        builder.addEventListener('change', recalc);
        recalc();
    }

    const category = document.querySelector('[data-doc-category]');
    const visible = document.querySelector('[data-doc-visible]');
    if (category && visible) {
        const syncVisibility = () => {
            if (visible.dataset.touched === '1') return;
            visible.checked = category.value === 'customer' || category.value === 'completed';
        };
        category.addEventListener('change', syncVisibility);
        visible.addEventListener('change', () => { visible.dataset.touched = '1'; });
        syncVisibility();
    }
});

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function escapeAttr(value) {
    return escapeHtml(value).replace(/'/g, '&#39;');
}
