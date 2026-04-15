// ============================================
// ASHA BANK - Main JavaScript
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    initDarkMode();
    initCardModal();
    initDepositMethods();
    initWithdrawMethods();
    initToast();
});

function initDarkMode() {
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark');
            localStorage.setItem('theme', document.body.classList.contains('dark') ? 'dark' : 'light');
        });
    }
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark');
    }
}

function initCardModal() {
    const balanceCard = document.getElementById('balanceCard');
    const modal = document.getElementById('cardModal');
    const closeBtn = document.querySelector('.close-modal');
    
    if (balanceCard && modal) {
        balanceCard.addEventListener('click', () => {
            modal.style.display = 'flex';
        });
        
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                modal.style.display = 'none';
            });
        }
        
        window.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
}

function initDepositMethods() {
    const methodSelect = document.getElementById('deposit_method');
    const mobileFields = document.getElementById('mobile_fields');
    const branchFields = document.getElementById('branch_fields');
    
    if (methodSelect) {
        methodSelect.addEventListener('change', function() {
            if (mobileFields) mobileFields.style.display = 'none';
            if (branchFields) branchFields.style.display = 'none';
            
            if (this.value === 'bkash' || this.value === 'nagad' || this.value === 'rocket' || this.value === 'upai') {
                if (mobileFields) mobileFields.style.display = 'block';
            } else if (this.value === 'branch') {
                if (branchFields) branchFields.style.display = 'block';
            }
        });
    }
}

function initWithdrawMethods() {
    const methodSelect = document.getElementById('withdraw_method');
    const atmFields = document.getElementById('atm_fields');
    const branchWithdrawFields = document.getElementById('branch_withdraw_fields');
    
    if (methodSelect) {
        methodSelect.addEventListener('change', function() {
            if (atmFields) atmFields.style.display = 'none';
            if (branchWithdrawFields) branchWithdrawFields.style.display = 'none';
            
            if (this.value === 'atm') {
                if (atmFields) atmFields.style.display = 'block';
            } else if (this.value === 'branch_withdraw') {
                if (branchWithdrawFields) branchWithdrawFields.style.display = 'block';
            }
        });
    }
}

function showToast(message, type) {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.innerHTML = `<i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
    container.appendChild(toast);
    
    setTimeout(() => toast.remove(), 5000);
}

function initToast() {
    const existingToasts = document.querySelectorAll('.toast-notification');
    existingToasts.forEach(toast => {
        setTimeout(() => toast.remove(), 5000);
    });
}

window.showToast = showToast;
window.formatCurrency = function(amount) {
    return '৳ ' + parseFloat(amount).toFixed(2);
};