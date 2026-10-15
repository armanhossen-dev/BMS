/**
 * Asha Bank — Global JavaScript v2.0
 * Toast, sidebar, modals, theme toggle, scroll reveal, etc.
 */

'use strict';

// ── Toast System (Bottom-Right, 1s display + fadeaway) ────────
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

    show(message, type = 'success', duration = 1500, title = '') {
        this.init();
        const icons = {
            success: `<i class='bx bx-check-circle'></i>`,
            danger:  `<i class='bx bx-x-circle'></i>`,
            warning: `<i class='bx bx-error-circle'></i>`,
            info:    `<i class='bx bx-info-circle'></i>`,
        };
        const el = document.createElement('div');
        el.className = `toast toast-${type}`;

        const contentHtml = title
            ? `<div class="toast-content"><div class="toast-title">${title}</div><div class="toast-message">${message}</div></div>`
            : `<div class="toast-content"><div class="toast-message">${message}</div></div>`;

        el.innerHTML = `
            ${icons[type] || icons.info}
            ${contentHtml}
            <button class="toast-close" aria-label="Dismiss">
                <i class='bx bx-x'></i>
            </button>`;

        el.querySelector('.toast-close').addEventListener('click', () => this.dismiss(el));
        this.container.appendChild(el);

        // Animate in
        requestAnimationFrame(() => {
            el.classList.add('toast-visible');
        });

        if (duration > 0) {
            setTimeout(() => this.dismiss(el), duration);
        }
        return el;
    },

    dismiss(el) {
        if (!el || !el.parentNode) return;
        el.classList.remove('toast-visible');
        el.classList.add('toast-fadeaway');
        setTimeout(() => {
            if (el && el.parentNode) {
                el.parentNode.removeChild(el);
            }
        }, 360);
    },

    /**
     * Plays a sequence of notifications:
     * - Initial 800ms delay after login
     * - Each notification popup holds for 1.5s (1500ms)
     * - Then smoothly fades away before the next one appears
     */
    playSequence(items, initialDelay = 800, itemDuration = 1500) {
        if (!Array.isArray(items) || items.length === 0) return;
        this.init();

        setTimeout(() => {
            let index = 0;
            const showNext = () => {
                if (index >= items.length) return;
                const item = items[index++];
                const msg   = typeof item === 'string' ? item : (item.message || '');
                const title = typeof item === 'object' ? (item.title || '') : '';
                const type  = typeof item === 'object' ? (item.type || 'success') : 'success';

                this.show(msg, type, itemDuration, title);

                // Wait for display duration (1500ms) + fadeaway animation (360ms) + small gap (100ms)
                setTimeout(() => {
                    showNext();
                }, itemDuration + 460);
            };

            showNext();
        }, initialDelay);
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
        modal.style.display = 'none';
        document.body.style.overflow = '';
    },

    init() {
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) this.close(overlay.id);
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

// ── Universal Confirm Dialog ──────────────────────────────────
const ConfirmDialog = {
    overlay: null,

    init() {
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('[data-confirm]');
            if (!trigger) return;

            e.preventDefault();
            e.stopPropagation();

            const promptText = trigger.dataset.confirm || 'Are you sure you want to proceed?';
            const title = trigger.dataset.confirmTitle || 'Please Confirm';
            const confirmBtnText = trigger.dataset.confirmBtn || 'Confirm';
            const confirmBtnClass = trigger.dataset.confirmClass || 'btn-primary';
            const icon = trigger.dataset.confirmIcon || 'bx-help-circle';
            const subtext = trigger.dataset.confirmSub || 'This action cannot be undone.';

            this.show({
                title,
                message: promptText,
                subtext,
                confirmText: confirmBtnText,
                confirmBtnClass,
                icon,
                onConfirm: () => {
                    if (trigger.tagName === 'A') {
                        window.location.href = trigger.href;
                    } else if (trigger.tagName === 'BUTTON' || trigger.tagName === 'INPUT') {
                        const form = trigger.closest('form');
                        if (form) {
                            if (trigger.name) {
                                const hidden = document.createElement('input');
                                hidden.type = 'hidden';
                                hidden.name = trigger.name;
                                hidden.value = trigger.value || '1';
                                form.appendChild(hidden);
                            }
                            form.submit();
                        }
                    }
                }
            });
        });
    },

    show({
        title = 'Please Confirm',
        message = 'Are you sure you want to proceed?',
        subtext = 'This action cannot be undone.',
        confirmText = 'Confirm',
        confirmBtnClass = 'btn-primary',
        cancelText = 'Cancel',
        icon = 'bx-help-circle',
        previewHtml = '',
        onConfirm = () => {}
    }) {
        if (!this.overlay) {
            this.overlay = document.createElement('div');
            this.overlay.className = 'modal-overlay';
            this.overlay.id = 'appUniversalConfirmModal';
            document.body.appendChild(this.overlay);
        }

        this.overlay.innerHTML = `
            <div class="modal" style="max-width:440px;" role="dialog" aria-modal="true">
                <div class="modal-header">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <div style="width:36px;height:36px;border-radius:10px;background:var(--bg-muted);display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--text-primary);flex-shrink:0;">
                            <i class='bx ${icon}'></i>
                        </div>
                        <h3 style="margin:0;font-size:16px;font-weight:600;">${title}</h3>
                    </div>
                    <button class="modal-close" type="button" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body" style="padding:var(--sp-5) var(--sp-6);">
                    <p style="font-size:14px;color:var(--text-secondary);line-height:1.5;margin-bottom:${previewHtml ? '14px' : '0'};">${message}</p>
                    ${previewHtml}
                    ${subtext ? `<p style="font-size:12px;color:var(--text-muted);margin-top:12px;margin-bottom:0;">${subtext}</p>` : ''}
                </div>
                <div class="modal-footer" style="padding:var(--sp-4) var(--sp-6);">
                    <button type="button" class="btn btn-outline btn-sm confirm-cancel-btn">${cancelText}</button>
                    <button type="button" class="btn btn-sm ${confirmBtnClass} confirm-ok-btn">${confirmText}</button>
                </div>
            </div>
        `;

        this.overlay.classList.add('active');
        this.overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        const closeModal = () => {
            this.overlay.classList.remove('active');
            this.overlay.style.display = 'none';
            document.body.style.overflow = '';
        };

        this.overlay.querySelector('.modal-close').onclick = closeModal;
        this.overlay.querySelector('.confirm-cancel-btn').onclick = closeModal;
        this.overlay.onclick = (e) => {
            if (e.target === this.overlay) closeModal();
        };

        const okBtn = this.overlay.querySelector('.confirm-ok-btn');
        okBtn.onclick = () => {
            closeModal();
            onConfirm();
        };
        okBtn.focus();
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
    ConfirmDialog.init();
    ScrollReveal.init();
    TableSearch.init();
    Forms.init();
    Dropdown.init();

    // Trigger after-login notification sequence or flash toast (held for 1.5s)
    if (window.__LOGIN_NOTIFICATIONS__ && Array.isArray(window.__LOGIN_NOTIFICATIONS__) && window.__LOGIN_NOTIFICATIONS__.length > 0) {
        Toast.playSequence(window.__LOGIN_NOTIFICATIONS__, 800, 1500);
    } else if (window.__FLASH_TOAST__) {
        Toast.show(window.__FLASH_TOAST__.message, window.__FLASH_TOAST__.type || 'success', 1500, window.__FLASH_TOAST__.title || '');
    }

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
window.Toast         = Toast;
window.showToast     = (msg, type = 'success', duration = 1500, title = '') => Toast.show(msg, type, duration, title);
window.Modal         = Modal;
window.ConfirmDialog = ConfirmDialog;
window.Sidebar       = Sidebar;
window.Theme         = Theme;

// Ensure Desktop-Only Gate is rendered for mobile/responsive protection
(function ensureDesktopGate() {
    function injectGate() {
        if (document.querySelector('.desktop-only-gate')) return;
        const path = window.location.pathname;
        if (path.endsWith('index.html') || path.endsWith('index.php') || path.endsWith('/BMS/') || path.endsWith('/demo-web/') || path === '/' || path === '') {
            return;
        }
        const isDemo = path.includes('/demo-web/');
        const homeUrl = isDemo ? 'index.html' : ((window.__ROOT_URL__ || '') + 'index.php');
        
        const gate = document.createElement('div');
        gate.className = 'desktop-only-gate';
        gate.setAttribute('aria-hidden', 'true');
        gate.innerHTML = `
          <div class="dog-backdrop"></div>
          <div class="dog-card">
            <div class="dog-header">
              <div class="dog-logo">
                <span class="dog-logo-mark">
                  <svg viewBox="0 0 24 24" fill="none" stroke="#09090B" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 21h18M3 10h18M5 10v11M19 10v11M9 10v11M15 10v11M12 3l9 7H3l9-7z"/>
                  </svg>
                </span>
                <span class="dog-logo-text">Asha Bank</span>
              </div>
              <span class="dog-badge"><i class='bx bx-desktop'></i> Desktop Only</span>
            </div>

            <div class="dog-icon-circle">
              <div class="dog-icon-glow"></div>
              <svg class="dog-screen-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" y1="21" x2="16" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
              </svg>
            </div>

            <h2 class="dog-title">Desktop Mode Required</h2>
            <p class="dog-desc">Asha Bank's portal dashboards, secure operations, and account management are currently available in desktop mode only.</p>
            
            <div class="dog-hint">
              <i class='bx bx-info-circle'></i>
              <span>Please open this portal on a laptop or desktop computer (width 1024px or higher) to access your dashboard.</span>
            </div>

            <div class="dog-actions">
              <a href="${homeUrl}" class="dog-btn dog-btn-primary">
                <i class='bx bx-arrow-back'></i>
                <span>Return to Homepage</span>
              </a>
            </div>
          </div>
        `;
        if (document.body) {
            document.body.appendChild(gate);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectGate);
    } else {
        injectGate();
    }
})();

