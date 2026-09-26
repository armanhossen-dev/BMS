/**
 * Asha Bank — Global JavaScript
 * Toast, sidebar, modals, tables, dark mode, etc.
 */

'use strict';

// ── Toast System ─────────────────────────────────────────────
const Toast = {
    container: null,

    init() {
        this.container = document.getElementById('toastContainer');
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'toastContainer';
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    },

    show(message, type = 'success', duration = 4000) {
        const icons = {
            success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>`,
            danger:  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`,
            warning: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
            info:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`,
        };
        const el = document.createElement('div');
        el.className = `toast toast-${type}`;
        el.innerHTML = `
            ${icons[type] || icons.info}
            <span>${message}</span>
            <button class="toast-close" aria-label="Dismiss">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>`;
        el.querySelector('.toast-close').addEventListener('click', () => this.dismiss(el));
        this.container.appendChild(el);
        if (duration > 0) setTimeout(() => this.dismiss(el), duration);
        return el;
    },

    dismiss(el) {
        if (!el || !el.parentNode) return;
        el.style.transition = 'opacity 0.3s, transform 0.3s';
        el.style.opacity = '0';
        el.style.transform = 'translateX(20px)';
        setTimeout(() => el.parentNode && el.parentNode.removeChild(el), 300);
    }
};

// ── Sidebar ──────────────────────────────────────────────────
const Sidebar = {
    sidebar: null,
    overlay: null,

    init() {
        this.sidebar = document.getElementById('sidebar');
        this.overlay = document.getElementById('sidebarOverlay');
        const toggle = document.getElementById('menuToggle');

        if (toggle) toggle.addEventListener('click', () => this.toggle());
        if (this.overlay) this.overlay.addEventListener('click', () => this.close());

        // Close on ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.close();
        });
    },

    toggle() {
        if (!this.sidebar) return;
        const isOpen = this.sidebar.classList.contains('open');
        isOpen ? this.close() : this.open();
    },

    open() {
        this.sidebar?.classList.add('open');
        this.overlay?.classList.add('active');
        document.body.style.overflow = 'hidden';
    },

    close() {
        this.sidebar?.classList.remove('open');
        this.overlay?.classList.remove('active');
        document.body.style.overflow = '';
    }
};

// ── Modal ─────────────────────────────────────────────────────
const Modal = {
    open(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('active');
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        modal.querySelector('[autofocus]')?.focus();
    },

    close(id) {
        const modal = id ? document.getElementById(id) : document.querySelector('.modal-overlay.active');
        if (!modal) return;
        modal.classList.remove('active');
        modal.style.display = '';
        document.body.style.overflow = '';
    },

    init() {
        // Close on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) this.close();
            });
        });
        // Close buttons
        document.querySelectorAll('[data-modal-close]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.modalClose;
                this.close(id);
            });
        });
        // Open buttons
        document.querySelectorAll('[data-modal-open]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.modalOpen;
                this.open(id);
            });
        });
        // ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.close();
        });
    }
};

// ── Scroll Reveal ─────────────────────────────────────────────
const ScrollReveal = {
    init() {
        const els = document.querySelectorAll('.reveal');
        if (!els.length) return;

        const io = new IntersectionObserver((entries) => {
            entries.forEach(e => {
                if (e.isIntersecting) {
                    e.target.classList.add('visible');
                    io.unobserve(e.target);
                }
            });
        }, { threshold: 0.1 });

        els.forEach(el => io.observe(el));
    }
};

// ── Table Search / Filter ─────────────────────────────────────
const TableSearch = {
    init() {
        document.querySelectorAll('[data-table-search]').forEach(input => {
            const tableId = input.dataset.tableSearch;
            const table = document.getElementById(tableId);
            if (!table) return;

            input.addEventListener('input', () => {
                const q = input.value.toLowerCase().trim();
                table.querySelectorAll('tbody tr').forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(q) ? '' : 'none';
                });
            });
        });
    }
};

// ── Confirm dialogs ───────────────────────────────────────────
function confirmAction(message, callback) {
    if (window.confirm(message)) {
        callback();
    }
}

// ── Form utilities ─────────────────────────────────────────────
const Forms = {
    init() {
        // Auto format currency inputs
        document.querySelectorAll('input[data-format="currency"]').forEach(input => {
            input.addEventListener('blur', () => {
                const val = parseFloat(input.value);
                if (!isNaN(val)) input.value = val.toFixed(2);
            });
        });

        // Phone number formatting (Bangladesh)
        document.querySelectorAll('input[data-format="phone"]').forEach(input => {
            input.addEventListener('input', () => {
                input.value = input.value.replace(/[^0-9+]/g, '');
            });
        });

        // Character counters
        document.querySelectorAll('[data-maxlength]').forEach(el => {
            const max = parseInt(el.dataset.maxlength);
            const counter = document.getElementById(el.id + '_counter');
            if (!counter) return;
            el.addEventListener('input', () => {
                const len = el.value.length;
                counter.textContent = `${len}/${max}`;
                counter.style.color = len >= max ? 'var(--red)' : 'var(--ink-faint)';
            });
        });

        // Submit loading state
        document.querySelectorAll('form[data-loading]').forEach(form => {
            form.addEventListener('submit', () => {
                const btn = form.querySelector('[type="submit"]');
                if (btn) {
                    btn.disabled = true;
                    const original = btn.innerHTML;
                    btn.innerHTML = `<svg class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Processing…`;
                    // Restore after 10s in case of error
                    setTimeout(() => {
                        btn.disabled = false;
                        btn.innerHTML = original;
                    }, 10000);
                }
            });
        });
    }
};

// ── Dropdown ──────────────────────────────────────────────────
const Dropdown = {
    init() {
        document.querySelectorAll('[data-dropdown-toggle]').forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const menuId = toggle.dataset.dropdownToggle;
                const menu = document.getElementById(menuId);
                if (!menu) return;
                const isOpen = menu.style.display === 'block';
                this.closeAll();
                if (!isOpen) {
                    menu.style.display = 'block';
                    toggle.setAttribute('aria-expanded', 'true');
                }
            });
        });

        document.addEventListener('click', () => this.closeAll());
    },

    closeAll() {
        document.querySelectorAll('[data-dropdown-toggle]').forEach(t => {
            const id = t.dataset.dropdownToggle;
            const m  = document.getElementById(id);
            if (m) m.style.display = '';
            t.removeAttribute('aria-expanded');
        });
    }
};

// ── Notification badge live update ────────────────────────────
const Notifications = {
    async refresh() {
        try {
            const r = await fetch('?ajax=notif_count', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!r.ok) return;
            const d = await r.json();
            const badge = document.querySelector('.notif-count');
            if (badge) {
                badge.textContent = d.count;
                badge.style.display = d.count > 0 ? 'flex' : 'none';
            }
        } catch (_) {}
    }
};

// ── Spin animation (used in loading states) ───────────────────
const style = document.createElement('style');
style.textContent = `.spin { animation: spin 0.8s linear infinite; } @keyframes spin { to { transform: rotate(360deg); } }`;
document.head.appendChild(style);

// ── Init on DOM ready ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    Toast.init();
    Sidebar.init();
    Modal.init();
    ScrollReveal.init();
    TableSearch.init();
    Forms.init();
    Dropdown.init();

    // Auto-refresh notifications every 60s
    if (document.querySelector('.notif-count')) {
        setInterval(() => Notifications.refresh(), 60000);
    }
});

// Expose globals
window.Toast  = Toast;
window.Modal  = Modal;
window.Sidebar = Sidebar;
