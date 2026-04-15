// ============================================
// ASHA BANK - Main JavaScript
// Dark Mode, Toast Notifications, Form Validation
// ============================================

// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    
    // ========== DARK MODE TOGGLE ==========
    initDarkMode();
    
    // ========== TOAST NOTIFICATIONS ==========
    initToast();
    
    // ========== FORM VALIDATION ==========
    initFormValidation();
    
    // ========== CARD MODAL ==========
    initCardModal();
    
    // ========== DEPOSIT METHODS ==========
    initDepositMethods();
    
    // ========== WITHDRAW METHODS ==========
    initWithdrawMethods();
    
    // ========== LOGIN ROLE SELECT ==========
    initLoginRole();
});

// ========== DARK MODE ==========
function initDarkMode() {
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark');
            localStorage.setItem('theme', document.body.classList.contains('dark') ? 'dark' : 'light');
            updateThemeIcon(this);
        });
    }
    
    // Load saved theme
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark');
        updateThemeIcon(document.getElementById('themeToggle'));
    }
}

function updateThemeIcon(button) {
    if (!button) return;
    const isDark = document.body.classList.contains('dark');
    button.innerHTML = isDark ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
}

// ========== TOAST NOTIFICATIONS ==========
function initToast() {
    const toastContainer = document.getElementById('toastContainer');
    if (toastContainer && toastContainer.children.length > 0) {
        setTimeout(() => {
            const toasts = document.querySelectorAll('.toast-notification');
            toasts.forEach(toast => {
                setTimeout(() => {
                    toast.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => toast.remove(), 300);
                }, 4000);
            });
        }, 100);
    }
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast-notification ${type}`;
    toast.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
        <span>${message}</span>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// Add slideOutRight animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

// ========== FORM VALIDATION ==========
function initFormValidation() {
    const forms = document.querySelectorAll('form[data-validate="true"]');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            const inputs = form.querySelectorAll('input[required], select[required]');
            
            inputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = 'var(--danger)';
                    showErrorTooltip(input, 'This field is required');
                } else {
                    input.style.borderColor = '';
                    removeErrorTooltip(input);
                }
                
                // Amount validation
                if (input.type === 'number' && input.name === 'amount') {
                    const amount = parseFloat(input.value);
                    if (isNaN(amount) || amount <= 0) {
                        isValid = false;
                        showErrorTooltip(input, 'Please enter a valid amount greater than 0');
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showToast('Please fix the errors in the form', 'error');
            }
        });
    });
}

function showErrorTooltip(input, message) {
    let tooltip = input.parentElement.querySelector('.error-tooltip');
    if (!tooltip) {
        tooltip = document.createElement('small');
        tooltip.className = 'error-tooltip';
        tooltip.style.cssText = 'color: var(--danger); font-size: 0.75rem; margin-top: 0.25rem; display: block;';
        input.parentElement.appendChild(tooltip);
    }
    tooltip.textContent = message;
}

function removeErrorTooltip(input) {
    const tooltip = input.parentElement.querySelector('.error-tooltip');
    if (tooltip) tooltip.remove();
}

// ========== CARD MODAL ==========
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

// ========== DEPOSIT METHODS ==========
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

// ========== WITHDRAW METHODS ==========
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

// ========== LOGIN ROLE SELECT ==========
function initLoginRole() {
    const roleSelect = document.getElementById('role');
    if (roleSelect) {
        roleSelect.addEventListener('change', function() {
            const usernameField = document.getElementById('username');
            const usernameLabel = document.querySelector('label[for="username"]');
            
            if (this.value === 'admin') {
                if (usernameLabel) usernameLabel.innerHTML = '<i class="fas fa-user-shield"></i> Admin Username';
                if (usernameField) usernameField.placeholder = 'Enter admin username';
            } else if (this.value === 'staff') {
                if (usernameLabel) usernameLabel.innerHTML = '<i class="fas fa-user-tie"></i> Staff ID';
                if (usernameField) usernameField.placeholder = 'Enter staff ID';
            } else {
                if (usernameLabel) usernameLabel.innerHTML = '<i class="fas fa-user"></i> Username or Email';
                if (usernameField) usernameField.placeholder = 'Enter your username or email';
            }
        });
    }
}

// ========== NUMBER FORMATTING ==========
function formatCurrency(amount) {
    return '৳ ' + parseFloat(amount).toFixed(2);
}

// ========== AMOUNT INPUT RESTRICTION ==========
function restrictAmountInput(input) {
    input.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9.]/g, '');
        if ((this.value.match(/\./g) || []).length > 1) {
            this.value = this.value.slice(0, -1);
        }
    });
}

// Apply to all amount inputs
document.querySelectorAll('input[name="amount"]').forEach(restrictAmountInput);

// ========== LOADING STATES ==========
function showLoading(button) {
    const originalText = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    return function restore() {
        button.disabled = false;
        button.innerHTML = originalText;
    };
}

// ========== EXPORT FUNCTIONS ==========
window.showToast = showToast;
window.formatCurrency = formatCurrency;
window.showLoading = showLoading;