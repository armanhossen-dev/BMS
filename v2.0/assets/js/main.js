/* ============================================================
   ASHA BANK — main.js
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
    initSidebarToggle();
    initDropdowns();
    initModals();
    initCharCounters();
    initMethodToggles();
    initConfirmActions();
    initReplyToggles();
    initCardFlip();
    initTierBars();
    autoDismissFlash();
});

/* ---------------- Mobile sidebar ---------------- */
function initSidebarToggle() {
    const btn = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (!btn || !sidebar) return;
    const close = () => { sidebar.classList.remove('open'); backdrop?.classList.remove('open'); };
    btn.addEventListener('click', () => { sidebar.classList.toggle('open'); backdrop?.classList.toggle('open'); });
    backdrop?.addEventListener('click', close);
}

/* ---------------- Dropdowns (notifications / user menu) ---------------- */
function initDropdowns() {
    document.querySelectorAll('[data-dropdown-toggle]').forEach(toggle => {
        const panelId = toggle.getAttribute('data-dropdown-toggle');
        const panel = document.getElementById(panelId);
        if (!panel) return;
        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            document.querySelectorAll('.dropdown-panel.open').forEach(p => { if (p !== panel) p.classList.remove('open'); });
            panel.classList.toggle('open');
        });
    });
    document.addEventListener('click', (e) => {
        document.querySelectorAll('.dropdown-panel.open').forEach(p => {
            if (!p.contains(e.target)) p.classList.remove('open');
        });
    });
}

/* ---------------- Modals ---------------- */
function initModals() {
    document.querySelectorAll('[data-modal-open]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById(btn.getAttribute('data-modal-open'));
            modal?.classList.add('open');
        });
    });
    document.querySelectorAll('[data-modal-close]').forEach(btn => {
        btn.addEventListener('click', () => btn.closest('.modal-overlay')?.classList.remove('open'));
    });
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.classList.remove('open'); });
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
    });
}

/* ---------------- Character counters ---------------- */
function initCharCounters() {
    document.querySelectorAll('[data-maxlength]').forEach(el => {
        const max = parseInt(el.getAttribute('data-maxlength'), 10);
        const counter = document.getElementById(el.getAttribute('data-counter-target'));
        const update = () => { if (counter) counter.textContent = `${el.value.length} / ${max}`; };
        el.addEventListener('input', update);
        update();
    });
}

/* ---------------- Deposit/Withdraw method field toggles ---------------- */
function initMethodToggles() {
    document.querySelectorAll('[data-method-select]').forEach(select => {
        const groups = document.querySelectorAll(`[data-method-group]`);
        const sync = () => {
            groups.forEach(g => {
                g.style.display = (g.getAttribute('data-method-group') === select.value) ? 'block' : 'none';
            });
        };
        select.addEventListener('change', sync);
        sync();
    });
}

/* ---------------- Confirm dialogs for destructive actions ---------------- */
function initConfirmActions() {
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', (e) => {
            if (!confirm(el.getAttribute('data-confirm'))) e.preventDefault();
        });
    });
}

/* ---------------- Reply form toggles ---------------- */
function initReplyToggles() {
    document.querySelectorAll('[data-reply-toggle]').forEach(btn => {
        btn.addEventListener('click', () => {
            const form = document.getElementById(btn.getAttribute('data-reply-toggle'));
            form?.classList.toggle('open-reply');
            if (form) form.style.display = form.style.display === 'block' ? 'none' : 'block';
        });
    });
}

/* ---------------- Card flip / preview ---------------- */
function initCardFlip() {
    document.querySelectorAll('.bank-card[data-card-modal]').forEach(card => {
        card.addEventListener('click', () => {
            const modal = document.getElementById(card.getAttribute('data-card-modal'));
            modal?.classList.add('open');
        });
    });
}

/* ---------------- Tier progress bar animation ---------------- */
function initTierBars() {
    document.querySelectorAll('.tier-bar-fill').forEach(bar => {
        const target = bar.getAttribute('data-width') || '0%';
        requestAnimationFrame(() => { bar.style.width = target; });
    });
}

/* ---------------- Toasts ---------------- */
function showToast(message, type = 'info') {
    let wrap = document.querySelector('.toast-wrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.className = 'toast-wrap';
        document.body.appendChild(wrap);
    }
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<div>${message}</div><div class="toast-progress"></div>`;
    wrap.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

function autoDismissFlash() {
    document.querySelectorAll('.js-flash').forEach(el => {
        showToast(el.getAttribute('data-msg'), el.getAttribute('data-type') || 'info');
        el.remove();
    });
}

/* ---------------- Mark notification read (AJAX) ---------------- */
function markNotificationRead(id, el) {
    fetch(BASE_URL + '/api/mark_notification_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(window.CSRF_TOKEN || '')
    }).then(r => r.json()).then(data => {
        if (data.success && el) {
            el.classList.remove('unread');
            const badge = document.getElementById('notifBadge');
            if (badge) {
                let count = parseInt(badge.textContent || '0', 10) - 1;
                if (count <= 0) { badge.style.display = 'none'; } else { badge.textContent = count; }
            }
        }
    }).catch(() => {});
}

function markAllNotificationsRead() {
    fetch(BASE_URL + '/api/mark_notification_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'all=1&csrf_token=' + encodeURIComponent(window.CSRF_TOKEN || '')
    }).then(r => r.json()).then(data => {
        if (data.success) {
            document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
            const badge = document.getElementById('notifBadge');
            if (badge) badge.style.display = 'none';
        }
    }).catch(() => {});
}

/* ---------------- Feedback AJAX submission ---------------- */
function submitFeedbackForm(form) {
    const formData = new FormData(form);
    fetch(BASE_URL + '/api/send_feedback.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            showToast(data.message, data.success ? 'success' : 'danger');
            if (data.success) {
                form.reset();
                setTimeout(() => window.location.reload(), 1200);
            }
        })
        .catch(() => showToast('Something went wrong. Please try again.', 'danger'));
    return false;
}

/* ---------------- Simple line chart (no dependency) ---------------- */
function drawLineChart(canvasId, labels, series) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * dpr; canvas.height = rect.height * dpr;
    ctx.scale(dpr, dpr);
    const w = rect.width, h = rect.height, pad = 30;
    ctx.clearRect(0, 0, w, h);

    const allVals = series.flatMap(s => s.data);
    const max = Math.max(...allVals, 1) * 1.15;
    const min = 0;
    const stepX = (w - pad * 2) / (labels.length - 1);

    // grid lines
    ctx.strokeStyle = 'rgba(255,255,255,0.06)';
    ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i++) {
        const y = pad + ((h - pad * 2) / 4) * i;
        ctx.beginPath(); ctx.moveTo(pad, y); ctx.lineTo(w - pad, y); ctx.stroke();
    }

    series.forEach(s => {
        ctx.beginPath();
        s.data.forEach((val, i) => {
            const x = pad + stepX * i;
            const y = h - pad - ((val - min) / (max - min)) * (h - pad * 2);
            if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
        });
        ctx.strokeStyle = s.color || '#d4a657';
        ctx.lineWidth = 2.5;
        ctx.lineJoin = 'round';
        ctx.stroke();

        // fill gradient
        const grad = ctx.createLinearGradient(0, pad, 0, h - pad);
        grad.addColorStop(0, (s.color || '#d4a657') + '33');
        grad.addColorStop(1, (s.color || '#d4a657') + '00');
        ctx.lineTo(w - pad, h - pad);
        ctx.lineTo(pad, h - pad);
        ctx.closePath();
        ctx.fillStyle = grad;
        ctx.fill();
    });

    // x labels
    ctx.fillStyle = '#6b6b72';
    ctx.font = '11px Inter, sans-serif';
    ctx.textAlign = 'center';
    labels.forEach((l, i) => {
        const x = pad + stepX * i;
        ctx.fillText(l, x, h - 8);
    });
}

/* Simple bar chart */
function drawBarChart(canvasId, labels, series) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * dpr; canvas.height = rect.height * dpr;
    ctx.scale(dpr, dpr);
    const w = rect.width, h = rect.height, pad = 30;
    ctx.clearRect(0, 0, w, h);

    const allVals = series.flatMap(s => s.data);
    const max = Math.max(...allVals, 1) * 1.2;
    const groupW = (w - pad * 2) / labels.length;
    const barW = Math.min(16, groupW / (series.length + 1.5));

    ctx.strokeStyle = 'rgba(255,255,255,0.06)';
    for (let i = 0; i <= 4; i++) {
        const y = pad + ((h - pad * 2) / 4) * i;
        ctx.beginPath(); ctx.moveTo(pad, y); ctx.lineTo(w - pad, y); ctx.stroke();
    }

    labels.forEach((label, i) => {
        const groupX = pad + groupW * i + groupW / 2;
        series.forEach((s, si) => {
            const val = s.data[i];
            const barH = ((val) / max) * (h - pad * 2);
            const x = groupX - (series.length * barW) / 2 + si * barW;
            const y = h - pad - barH;
            ctx.fillStyle = s.color || '#d4a657';
            ctx.beginPath();
            ctx.roundRect(x, y, barW - 4, barH, 4);
            ctx.fill();
        });
        ctx.fillStyle = '#6b6b72';
        ctx.font = '11px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(label, groupX, h - 8);
    });
}
