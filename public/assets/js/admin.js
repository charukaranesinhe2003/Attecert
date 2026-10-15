document.addEventListener('DOMContentLoaded', () => {
    const navToggle = document.querySelector('[data-portal-nav]');
    const nav = document.getElementById('portal-nav');
    const backdrop = document.querySelector('[data-portal-backdrop]');
    const closeNav = () => {
        const wasOpen = nav?.classList.contains('is-open');
        nav?.classList.remove('is-open');
        navToggle?.setAttribute('aria-expanded', 'false');
        if (backdrop) backdrop.hidden = true;
        if (wasOpen) navToggle?.focus();
    };
    navToggle?.addEventListener('click', () => {
        if (nav?.classList.contains('is-open')) return closeNav();
        nav?.classList.add('is-open');
        navToggle.setAttribute('aria-expanded', 'true');
        if (backdrop) backdrop.hidden = false;
        nav?.querySelector('[data-portal-close]')?.focus();
    });
    document.querySelector('[data-portal-close]')?.addEventListener('click', closeNav);
    backdrop?.addEventListener('click', closeNav);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeNav();
        if (event.key === 'Tab' && nav?.classList.contains('is-open')) {
            const focusable = [...nav.querySelectorAll('a[href], button:not([disabled])')];
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        }
    });
    window.matchMedia('(min-width: 901px)').addEventListener('change', (event) => {
        if (event.matches) closeNav();
    });

    document.querySelectorAll('form[data-busy]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            // public.js owns confirmations and runs first. A cancelled form stays usable.
            if (event.defaultPrevented) return;
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
    const searchStatus = document.querySelector('[data-admin-search-status]');
    let searchTimer = null;
    let activeIndex = -1;
    let searchRequest = null;
    let searchSequence = 0;

    const hideSearch = () => {
        clearTimeout(searchTimer);
        searchSequence += 1;
        searchRequest?.abort();
        searchResults?.setAttribute('hidden', '');
        searchInput?.setAttribute('aria-expanded', 'false');
        searchInput?.removeAttribute('aria-activedescendant');
        searchForm?.removeAttribute('aria-busy');
        if (searchStatus) searchStatus.textContent = '';
        activeIndex = -1;
    };

    const showSearchMessage = (message) => {
        if (!searchResults) return;
        const node = document.createElement('p');
        node.className = 'admin-search-message';
        node.textContent = message;
        searchResults.replaceChildren(node);
        searchResults.removeAttribute('hidden');
        searchInput?.setAttribute('aria-expanded', 'true');
        searchInput?.removeAttribute('aria-activedescendant');
        if (searchStatus) searchStatus.textContent = message;
        activeIndex = -1;
    };

    const renderSearch = (groups) => {
        if (!searchResults) return;
        if (!groups.length) {
            showSearchMessage(searchForm.dataset.searchEmpty);
            return;
        }
        searchResults.innerHTML = groups.map((group, groupIndex) => `
            <div class="admin-search-group" role="group" aria-label="${escapeAttr(group.label)}">
                <strong aria-hidden="true">${escapeHtml(group.label)}</strong>
                ${group.items.map((item, itemIndex) => `
                    <a class="admin-search-item" id="admin-result-${groupIndex}-${itemIndex}" role="option" aria-selected="false" href="${escapeAttr(item.href)}">
                        ${escapeHtml(item.title)}
                        <span>${escapeHtml(item.meta || '')}</span>
                    </a>
                `).join('')}
            </div>
        `).join('');
        searchResults.removeAttribute('hidden');
        searchInput?.setAttribute('aria-expanded', 'true');
        searchInput?.removeAttribute('aria-activedescendant');
        if (searchStatus) searchStatus.textContent = '';
        activeIndex = -1;
    };

    const runSearch = async (query, sequence) => {
        if (!searchForm || sequence !== searchSequence) return;
        searchRequest = new AbortController();
        searchForm.setAttribute('aria-busy', 'true');
        showSearchMessage(searchForm.dataset.searchLoading);
        // The form action works under the existing CSP and includes subdirectory installs.
        const url = new URL(searchForm.action, window.location.origin);
        url.searchParams.set('q', query);
        const response = await fetch(url.toString(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: searchRequest.signal,
        });
        if (!response.ok) throw new Error('Search request failed');
        const data = await response.json();
        if (sequence !== searchSequence || searchInput.value.trim() !== query) return;
        searchForm.removeAttribute('aria-busy');
        renderSearch(data.results || []);
    };

    searchInput?.addEventListener('input', () => {
        const query = searchInput.value.trim();
        hideSearch();
        if (query.length < 2) {
            return;
        }
        const sequence = searchSequence;
        searchTimer = setTimeout(() => {
            runSearch(query, sequence).catch((error) => {
                if (error.name === 'AbortError' || sequence !== searchSequence) return;
                searchForm?.removeAttribute('aria-busy');
                showSearchMessage(searchForm.dataset.searchError);
            });
        }, 220);
    });

    searchInput?.addEventListener('keydown', (event) => {
        if (!searchResults || searchResults.hasAttribute('hidden')) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            hideSearch();
            return;
        }
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
        } else {
            return;
        }
        items.forEach((item, index) => {
            item.classList.toggle('is-active', index === activeIndex);
            item.setAttribute('aria-selected', index === activeIndex ? 'true' : 'false');
        });
        searchInput.setAttribute('aria-activedescendant', items[activeIndex].id);
        items[activeIndex]?.scrollIntoView({ block: 'nearest' });
    });

    document.addEventListener('click', (event) => {
        if (searchForm && !searchForm.contains(event.target)) hideSearch();
    });
    document.addEventListener('focusin', (event) => {
        if (searchForm && !searchForm.contains(event.target)) hideSearch();
    });
    searchForm?.addEventListener('submit', hideSearch);

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
        document.querySelectorAll('[data-jump-tab]').forEach((link) => {
            link.addEventListener('click', (event) => {
                const button = buttons.find((item) => item.dataset.tab === link.dataset.jumpTab);
                if (!button) return;
                event.preventDefault();
                activate(button.dataset.tab);
                button.focus();
                button.scrollIntoView({ block: 'nearest' });
            });
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
