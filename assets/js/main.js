/**
 * Asha Bank — Global JavaScript v2.0
 * Toast, sidebar, modals, theme toggle, scroll reveal, etc.
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
            success: `<i class='bx bx-check-circle'></i>`,
            danger:  `<i class='bx bx-x-circle'></i>`,
            warning: `<i class='bx bx-error-circle'></i>`,
            info:    `<i class='bx bx-info-circle'></i>`,
        };
        const el = document.createElement('div');
        el.className = `toast toast-${type}`;
        el.innerHTML = `
            ${icons[type] || icons.info}
            <span>${message}</span>
            <button class="toast-close" aria-label="Dismiss">
                <i class='bx bx-x'></i>
            </button>`;
        el.querySelector('.toast-close').addEventListener('click', () => this.dismiss(el));
        this.container.appendChild(el);
        if (duration > 0) setTimeout(() => this.dismiss(el), duration);
        return el;
    },

    dismiss(el) {
        if (!el || !el.parentNode) return;
        el.style.transition = 'opacity 0.25s, transform 0.25s';
        el.style.opacity = '0';
        el.style.transform = 'translateX(16px)';
        setTimeout(() => el.parentNode && el.parentNode.removeChild(el), 260);
    }
};

// ── Theme Manager ─────────────────────────────────────────────
const Theme = {
    KEY: 'asha-theme',

    init() {
        const saved = localStorage.getItem(this.KEY) || 'light';
        this.apply(saved);

        const btn = document.getElementById('themeToggle');
        if (btn) {
            btn.addEventListener('click', () => this.toggle());
        }
    },

    apply(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(this.KEY, theme);
        this.updateIcon(theme);
    },

    toggle() {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
        this.apply(current === 'dark' ? 'light' : 'dark');
    },

    updateIcon(theme) {
        const icon = document.getElementById('themeIcon');
        if (!icon) return;
        icon.className = theme === 'dark' ? 'bx bx-sun' : 'bx bx-moon';
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
        this.overlay?.classList.add('open');
        document.body.style.overflow = 'hidden';
    },

    close() {
        this.sidebar?.classList.remove('open');
        this.overlay?.classList.remove('open');
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
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) this.close();
            });
        });
        document.querySelectorAll('[data-modal-close]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.modalClose;
                this.close(id);
            });
        });
        document.querySelectorAll('[data-modal-open]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.modalOpen;
                this.open(id);
            });
        });
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
        }, { threshold: 0.08 });

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

// ── Form utilities ─────────────────────────────────────────────
const Forms = {
    init() {
        document.querySelectorAll('input[data-format="currency"]').forEach(input => {
            input.addEventListener('blur', () => {
                const val = parseFloat(input.value);
                if (!isNaN(val)) input.value = val.toFixed(2);
            });
        });

        document.querySelectorAll('input[data-format="phone"]').forEach(input => {
            input.addEventListener('input', () => {
                input.value = input.value.replace(/[^0-9+]/g, '');
            });
        });

        document.querySelectorAll('[data-maxlength]').forEach(el => {
            const max = parseInt(el.dataset.maxlength);
            const counter = document.getElementById(el.id + '_counter');
            if (!counter) return;
            el.addEventListener('input', () => {
                const len = el.value.length;
                counter.textContent = `${len}/${max}`;
                counter.style.color = len >= max ? 'var(--danger)' : 'var(--text-muted)';
            });
        });

        document.querySelectorAll('form[data-loading]').forEach(form => {
            form.addEventListener('submit', () => {
                const btn = form.querySelector('[type="submit"]');
                if (btn) {
                    btn.disabled = true;
                    const original = btn.innerHTML;
                    btn.innerHTML = `<i class='bx bx-loader-alt spin'></i> Processing…`;
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

// ── Spin animation ────────────────────────────────────────────
const spinStyle = document.createElement('style');
spinStyle.textContent = `.spin { animation: _spin 0.7s linear infinite; } @keyframes _spin { to { transform: rotate(360deg); } }`;
document.head.appendChild(spinStyle);

// ── Number counter animation ──────────────────────────────────
function animateCounter(el, target, duration = 1200) {
    const start = 0;
    const startTime = performance.now();
    const format = el.dataset.format || '';

    function update(now) {
        const elapsed = now - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const ease = 1 - Math.pow(1 - progress, 3);
        const value = Math.floor(start + (target - start) * ease);

        if (format === 'currency') {
            el.textContent = '৳' + value.toLocaleString('en-BD');
        } else {
            el.textContent = value.toLocaleString();
        }

        if (progress < 1) requestAnimationFrame(update);
    }
    requestAnimationFrame(update);
}

// ── Init on DOM ready ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    Toast.init();
    Theme.init();
    Sidebar.init();
    Modal.init();
    ScrollReveal.init();
    TableSearch.init();
    Forms.init();
    Dropdown.init();

    // Animate counters
    document.querySelectorAll('[data-counter]').forEach(el => {
        const target = parseInt(el.dataset.counter.replace(/[^0-9]/g, ''), 10);
        if (!isNaN(target)) animateCounter(el, target);
    });

    // Auto-refresh notifications every 60s
    if (document.querySelector('.notif-count')) {
        setInterval(() => Notifications.refresh(), 60000);
    }
});

// Expose globals
window.Toast   = Toast;
window.Modal   = Modal;
window.Sidebar = Sidebar;
window.Theme   = Theme;
