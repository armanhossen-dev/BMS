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
    
    // ========== NUMBER FORMATTING ==========
    initNumberFormatting();
    
    // ========== TOOLTIPS ==========
    initTooltips();
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
    if (localStorage.getItem('theme') === 'dark') {
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
        // Auto remove after 5 seconds
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

// ========== NUMBER FORMATTING ==========
function initNumberFormatting() {
    const balanceElements = document.querySelectorAll('.format-balance');
    balanceElements.forEach(el => {
        const value = parseFloat(el.textContent.replace(/[^0-9.-]/g, ''));
        if (!isNaN(value)) {
            el.textContent = formatCurrency(value);
        }
    });
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2
    }).format(amount);
}

// ========== TOOLTIPS ==========
function initTooltips() {
    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(el => {
        el.addEventListener('mouseenter', function(e) {
            const tooltipText = this.getAttribute('data-tooltip');
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            tooltip.textContent = tooltipText;
            tooltip.style.cssText = `
                position: absolute;
                background: var(--glass-bg-solid);
                color: var(--text-primary);
                padding: 0.5rem 1rem;
                border-radius: var(--radius-md);
                font-size: 0.8rem;
                z-index: 1000;
                white-space: nowrap;
                box-shadow: var(--card-shadow);
                pointer-events: none;
            `;
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.top = (rect.top - tooltip.offsetHeight - 10) + 'px';
            tooltip.style.left = (rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2)) + 'px';
            
            this.addEventListener('mouseleave', function() {
                tooltip.remove();
            }, { once: true });
        });
    });
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

// ========== ACCOUNT NUMBER SEARCH (For Transfer) ==========
function initAccountSearch() {
    const searchInput = document.getElementById('accountSearch');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(function() {
            const query = this.value;
            if (query.length >= 4) {
                fetch(`/api/search-account.php?q=${query}`)
                    .then(res => res.json())
                    .then(data => {
                        const suggestions = document.getElementById('accountSuggestions');
                        if (suggestions) {
                            suggestions.innerHTML = data.map(acc => 
                                `<div class="suggestion-item" data-account="${acc.AccountNumber}">${acc.AccountNumber} - ${acc.CustomerName}</div>`
                            ).join('');
                        }
                    });
            }
        }, 300));
    }
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// ========== CONFIRMATION DIALOGS ==========
window.confirmAction = function(message, callback) {
    if (confirm(message)) {
        callback();
    }
};

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