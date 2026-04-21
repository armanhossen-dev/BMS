// ================================================
// BANK MANAGEMENT SYSTEM - MAIN JS
// ================================================

document.addEventListener('DOMContentLoaded', function () {

    // ── SIDEBAR TOGGLE (Mobile) ──
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        });
    }

    // ── ACTIVE NAV ITEM ──
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-item').forEach(link => {
        if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
        }
    });

    // ── TOPBAR CLOCK ──
    const clockEl = document.getElementById('topbarClock');
    if (clockEl) {
        const updateClock = () => {
            const now = new Date();
            clockEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        };
        updateClock();
        setInterval(updateClock, 1000);
    }

    // ── AUTO-DISMISS ALERTS ──
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s, transform 0.5s';
            alert.style.opacity = '0';
            alert.style.transform = 'translateX(20px)';
            setTimeout(() => alert.remove(), 500);
        }, 4000);
    });

    // ── MODALS ──
    document.querySelectorAll('[data-modal]').forEach(trigger => {
        trigger.addEventListener('click', () => {
            const modal = document.getElementById(trigger.dataset.modal);
            if (modal) modal.classList.add('open');
        });
    });

    document.querySelectorAll('.modal-close, [data-dismiss="modal"]').forEach(btn => {
        btn.addEventListener('click', () => {
            btn.closest('.modal-overlay').classList.remove('open');
        });
    });

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) overlay.classList.remove('open');
        });
    });

    // ── TABLE SEARCH ──
    const tableSearch = document.getElementById('tableSearch');
    if (tableSearch) {
        tableSearch.addEventListener('input', function () {
            const query = this.value.toLowerCase();
            document.querySelectorAll('tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
            });
        });
    }

    // ── CONFIRM DELETE ──
    document.querySelectorAll('.btn-delete-confirm').forEach(btn => {
        btn.addEventListener('click', function (e) {
            if (!confirm('Are you sure you want to delete this record? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

    // ── LOAN EMI CALCULATOR ──
    const calcBtn = document.getElementById('calcEMI');
    if (calcBtn) {
        calcBtn.addEventListener('click', calculateEMI);
    }

    function calculateEMI() {
        const principal = parseFloat(document.getElementById('loan_amount')?.value || 0);
        const rate = parseFloat(document.getElementById('interest_rate')?.value || 0);
        const months = parseInt(document.getElementById('tenure_months')?.value || 0);

        if (principal && rate && months) {
            const monthlyRate = rate / (12 * 100);
            const emi = principal * monthlyRate * Math.pow(1 + monthlyRate, months) / (Math.pow(1 + monthlyRate, months) - 1);
            const total = emi * months;
            const interest = total - principal;

            const emiResult = document.getElementById('emiResult');
            if (emiResult) {
                emiResult.innerHTML = `
                    <div style="background:rgba(212,175,55,0.08);border:1px solid rgba(212,175,55,0.3);border-radius:8px;padding:16px;margin-top:14px;">
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;text-align:center;">
                            <div>
                                <div style="font-size:0.7rem;color:#888;text-transform:uppercase;margin-bottom:4px;">Monthly EMI</div>
                                <div style="font-size:1.1rem;color:#D4AF37;font-family:'Cinzel',serif;">৳ ${emi.toFixed(2)}</div>
                            </div>
                            <div>
                                <div style="font-size:0.7rem;color:#888;text-transform:uppercase;margin-bottom:4px;">Total Interest</div>
                                <div style="font-size:1.1rem;color:#E74C3C;font-family:'Cinzel',serif;">৳ ${interest.toFixed(2)}</div>
                            </div>
                            <div>
                                <div style="font-size:0.7rem;color:#888;text-transform:uppercase;margin-bottom:4px;">Total Payable</div>
                                <div style="font-size:1.1rem;color:#2ECC71;font-family:'Cinzel',serif;">৳ ${total.toFixed(2)}</div>
                            </div>
                        </div>
                    </div>`;
                document.getElementById('monthly_payment').value = emi.toFixed(2);
            }
        }
    }

    // ── NUMBER FORMATTING INPUT ──
    document.querySelectorAll('.amount-input').forEach(input => {
        input.addEventListener('blur', function () {
            const val = parseFloat(this.value.replace(/,/g, ''));
            if (!isNaN(val)) this.value = val.toFixed(2);
        });
    });

    // ── PRINT REPORT ──
    const printBtn = document.getElementById('printReport');
    if (printBtn) {
        printBtn.addEventListener('click', () => window.print());
    }

});

// ── OPEN/CLOSE MODAL HELPERS ──
function openModal(id) {
    document.getElementById(id)?.classList.add('open');
}

function closeModal(id) {
    document.getElementById(id)?.classList.remove('open');
}