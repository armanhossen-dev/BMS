// ============================================
// ASHA BANK - Main JavaScript
// Complete Rewrite - No Page Reloads
// ============================================

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initThemeToggle();
    initCardModal();
    initDepositMethods();
    initWithdrawMethods();
    initNotificationSystem();
    initFeedbackSystem();
    initToastCleanup();
});

// ============================================
// THEME TOGGLE - No Page Reload
// ============================================
function initThemeToggle() {
    const themeToggle = document.getElementById('themeToggle');
    if (!themeToggle) return;
    
    // Get saved theme
    const savedTheme = localStorage.getItem('theme');
    const icon = themeToggle.querySelector('i');
    const textSpan = themeToggle.querySelector('span');
    
    // Apply saved theme
    if (savedTheme === 'dark') {
        document.body.classList.add('dark');
        if (icon) icon.className = 'fas fa-sun';
        if (textSpan) textSpan.textContent = 'Light';
    } else {
        if (icon) icon.className = 'fas fa-moon';
        if (textSpan) textSpan.textContent = 'Dark';
    }
    
    // Toggle event
    themeToggle.addEventListener('click', function(e) {
        e.preventDefault();
        const isDark = document.body.classList.contains('dark');
        
        if (isDark) {
            document.body.classList.remove('dark');
            localStorage.setItem('theme', 'light');
            if (icon) icon.className = 'fas fa-moon';
            if (textSpan) textSpan.textContent = 'Dark';
        } else {
            document.body.classList.add('dark');
            localStorage.setItem('theme', 'dark');
            if (icon) icon.className = 'fas fa-sun';
            if (textSpan) textSpan.textContent = 'Light';
        }
    });
}

// ============================================
// CARD MODAL - Click Balance Card
// ============================================
function initCardModal() {
    const balanceCard = document.getElementById('balanceCard');
    const modal = document.getElementById('cardModal');
    
    if (!balanceCard || !modal) return;
    
    // Open modal on card click
    balanceCard.addEventListener('click', function(e) {
        e.preventDefault();
        modal.style.display = 'flex';
    });
    
    // Close on X button
    const closeBtn = modal.querySelector('.close-modal, .close-card-modal');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    }
    
    // Close on outside click
    window.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            modal.style.display = 'none';
        }
    });
}

// ============================================
// DEPOSIT METHODS - Show/Hide Fields
// ============================================
function initDepositMethods() {
    const methodSelect = document.getElementById('deposit_method');
    if (!methodSelect) return;
    
    const mobileFields = document.getElementById('mobile_fields');
    const branchFields = document.getElementById('branch_fields');
    
    methodSelect.addEventListener('change', function() {
        // Hide all fields first
        if (mobileFields) mobileFields.style.display = 'none';
        if (branchFields) branchFields.style.display = 'none';
        
        // Show relevant fields
        const value = this.value;
        if (value === 'bkash' || value === 'nagad' || value === 'rocket' || value === 'upai') {
            if (mobileFields) mobileFields.style.display = 'block';
        } else if (value === 'branch') {
            if (branchFields) branchFields.style.display = 'block';
        }
    });
}

// ============================================
// WITHDRAW METHODS - Show/Hide Fields
// ============================================
function initWithdrawMethods() {
    const methodSelect = document.getElementById('withdraw_method');
    if (!methodSelect) return;
    
    const atmFields = document.getElementById('atm_fields');
    const branchWithdrawFields = document.getElementById('branch_withdraw_fields');
    
    methodSelect.addEventListener('change', function() {
        // Hide all fields first
        if (atmFields) atmFields.style.display = 'none';
        if (branchWithdrawFields) branchWithdrawFields.style.display = 'none';
        
        // Show relevant fields
        const value = this.value;
        if (value === 'atm') {
            if (atmFields) atmFields.style.display = 'block';
        } else if (value === 'branch_withdraw') {
            if (branchWithdrawFields) branchWithdrawFields.style.display = 'block';
        }
    });
}

// ============================================
// NOTIFICATION SYSTEM - Clickable Popups
// ============================================
function initNotificationSystem() {
    // Notification dropdown toggle
    const notificationContainer = document.querySelector('.notification-container');
    const dropdown = document.getElementById('notificationDropdown');
    
    if (notificationContainer && dropdown) {
        notificationContainer.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            dropdown.classList.remove('show');
        });
    }
}

// Show notification modal with full message
function showNotificationModal(notification) {
    // Create modal if it doesn't exist
    let modal = document.getElementById('dynamicNotificationModal');
    
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'dynamicNotificationModal';
        modal.className = 'notification-modal';
        modal.innerHTML = `
            <div class="notification-modal-content">
                <div class="notification-modal-header">
                    <h3 id="dynamicNotifTitle"></h3>
                    <span class="close-notification-modal">&times;</span>
                </div>
                <div class="notification-modal-body">
                    <div id="dynamicNotifIcon" class="notification-type-icon"></div>
                    <p id="dynamicNotifMessage"></p>
                    <p id="dynamicNotifTime" style="font-size: 11px; color: var(--text-tertiary); margin-top: 12px;"></p>
                </div>
                <button class="btn-close-modal">Close</button>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Add close event listeners
        const closeBtn = modal.querySelector('.close-notification-modal');
        const closeButton = modal.querySelector('.btn-close-modal');
        
        closeBtn.addEventListener('click', () => modal.style.display = 'none');
        closeButton.addEventListener('click', () => modal.style.display = 'none');
        
        window.addEventListener('click', (e) => {
            if (e.target === modal) modal.style.display = 'none';
        });
    }
    
    // Set content
    document.getElementById('dynamicNotifTitle').innerHTML = notification.title;
    document.getElementById('dynamicNotifMessage').innerHTML = notification.message;
    document.getElementById('dynamicNotifTime').innerHTML = notification.created_at;
    
    const iconDiv = document.getElementById('dynamicNotifIcon');
    iconDiv.className = 'notification-type-icon ' + notification.type;
    
    if (notification.type === 'success') iconDiv.innerHTML = '<i class="fas fa-check-circle"></i>';
    else if (notification.type === 'warning') iconDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
    else if (notification.type === 'danger') iconDiv.innerHTML = '<i class="fas fa-times-circle"></i>';
    else iconDiv.innerHTML = '<i class="fas fa-info-circle"></i>';
    
    modal.style.display = 'flex';
    
    // Mark as read via AJAX (no page reload)
    if (!notification.is_read) {
        fetch(`?ajax_mark_read=1&id=${notification.notification_id}`, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).catch(err => console.error('Error marking notification as read:', err));
    }
}

// ============================================
// FEEDBACK SYSTEM - AJAX with Toast
// ============================================
function initFeedbackSystem() {
    const messageBubble = document.getElementById('messageBubble');
    const feedbackModal = document.getElementById('feedbackModal');
    
    if (messageBubble && feedbackModal) {
        messageBubble.addEventListener('click', function(e) {
            e.stopPropagation();
            feedbackModal.classList.toggle('show');
            if (feedbackModal.classList.contains('show')) {
                loadFeedbackHistory();
            }
        });
        
        // Close feedback modal when clicking outside
        document.addEventListener('click', function(e) {
            if (feedbackModal.classList.contains('show') && 
                !feedbackModal.contains(e.target) && 
                !messageBubble.contains(e.target)) {
                feedbackModal.classList.remove('show');
            }
        });
    }
    
    // Character counter
    const feedbackMessage = document.getElementById('feedbackMessage');
    const charCountSpan = document.getElementById('charCount');
    if (feedbackMessage && charCountSpan) {
        feedbackMessage.addEventListener('input', function() {
            charCountSpan.textContent = this.value.length;
        });
    }
}

// Send feedback via AJAX (no page reload)
function sendFeedback() {
    const type = document.getElementById('feedbackType').value;
    const subject = document.getElementById('feedbackSubject').value;
    const message = document.getElementById('feedbackMessage').value;
    
    if (!subject || !message) {
        showToastMessage('Please fill in both subject and message', 'error');
        return;
    }
    
    const formData = new URLSearchParams();
    formData.append('type', type);
    formData.append('subject', subject);
    formData.append('message', message);
    
    fetch('send_feedback.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToastMessage('✓ Feedback sent successfully! Our team will respond within 24 hours.', 'success');
            document.getElementById('feedbackSubject').value = '';
            document.getElementById('feedbackMessage').value = '';
            const charSpan = document.getElementById('charCount');
            if (charSpan) charSpan.textContent = '0';
            closeFeedbackModal();
        } else {
            showToastMessage('Error: ' + (data.error || 'Something went wrong'), 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToastMessage('Error sending feedback. Please try again.', 'error');
    });
}

// Load feedback history via AJAX
function loadFeedbackHistory() {
    fetch('get_feedback.php', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        const historyDiv = document.getElementById('feedbackHistoryList');
        if (!historyDiv) return;
        
        if (data.length === 0 || data.error) {
            historyDiv.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-tertiary);">No feedback yet</div>';
        } else {
            historyDiv.innerHTML = data.map(f => `
                <div class="feedback-item">
                    <div class="subject">${escapeHtml(f.subject)}</div>
                    <div class="message">${escapeHtml(f.message.substring(0, 80))}${f.message.length > 80 ? '...' : ''}</div>
                    <span class="status status-${f.status}">${f.status.toUpperCase()}</span>
                    ${f.staff_reply ? `<div class="reply"><strong>Reply:</strong> ${escapeHtml(f.staff_reply.substring(0, 100))}</div>` : ''}
                    <small style="font-size:9px; color:var(--text-tertiary);">${f.created_at}</small>
                </div>
            `).join('');
        }
    })
    .catch(error => {
        console.error('Error loading feedback:', error);
        const historyDiv = document.getElementById('feedbackHistoryList');
        if (historyDiv) {
            historyDiv.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-tertiary);">Error loading feedback</div>';
        }
    });
}

function toggleFeedbackHistory() {
    const historyDiv = document.getElementById('feedbackHistory');
    if (historyDiv) {
        if (historyDiv.style.display === 'none') {
            historyDiv.style.display = 'block';
            loadFeedbackHistory();
        } else {
            historyDiv.style.display = 'none';
        }
    }
}

function closeFeedbackModal() {
    const modal = document.getElementById('feedbackModal');
    if (modal) modal.classList.remove('show');
}

// ============================================
// TOAST NOTIFICATION SYSTEM
// ============================================
function showToastMessage(message, type = 'success') {
    // Remove existing toast
    const existingToast = document.querySelector('.custom-toast');
    if (existingToast) existingToast.remove();
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `custom-toast ${type}`;
    toast.innerHTML = `
        <div class="custom-toast-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            <span>${escapeHtml(message)}</span>
        </div>
        <div class="custom-toast-progress"></div>
    `;
    
    // Add styles
    const iconColor = type === 'success' ? 'var(--success)' : 'var(--danger)';
    toast.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        background: var(--bg-primary);
        border-radius: 12px;
        box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2);
        z-index: 10000;
        min-width: 280px;
        max-width: 400px;
        overflow: hidden;
        animation: slideInRight 0.3s ease;
        border-left: 4px solid ${iconColor};
    `;
    
    // Add keyframes if not exists
    if (!document.querySelector('#toastKeyframes')) {
        const style = document.createElement('style');
        style.id = 'toastKeyframes';
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            @keyframes progressShrink {
                from { width: 100%; }
                to { width: 0%; }
            }
            .custom-toast-progress {
                height: 3px;
                background: ${iconColor};
                width: 100%;
                animation: progressShrink 3s linear forwards;
            }
            .custom-toast-content {
                padding: 14px 20px;
                display: flex;
                align-items: center;
                gap: 12px;
                font-size: 14px;
                color: var(--text-primary);
            }
            .custom-toast-content i {
                font-size: 18px;
                color: ${iconColor};
            }
        `;
        document.head.appendChild(style);
    }
    
    document.body.appendChild(toast);
    
    // Auto remove after 3 seconds
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Legacy showToast function for compatibility
function showToast(message, type) {
    showToastMessage(message, type);
}

// Initialize toast cleanup
function initToastCleanup() {
    const existingToasts = document.querySelectorAll('.toast-notification, .custom-toast');
    existingToasts.forEach(toast => {
        setTimeout(() => toast.remove(), 5000);
    });
}

// ============================================
// STAFF DASHBOARD - Reply Functions
// ============================================
function toggleReplyForm(id) {
    // Close all other reply forms
    document.querySelectorAll('.reply-form').forEach(form => {
        if (form.id !== 'replyForm-' + id) {
            form.style.display = 'none';
        }
    });
    
    const form = document.getElementById('replyForm-' + id);
    if (form) {
        if (form.style.display === 'none' || form.style.display === '') {
            form.style.display = 'block';
            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            form.style.display = 'none';
        }
    }
}

function closeReplyForm(id) {
    const form = document.getElementById('replyForm-' + id);
    if (form) form.style.display = 'none';
}

// ============================================
// UTILITY FUNCTIONS
// ============================================
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatCurrency(amount) {
    return '৳ ' + parseFloat(amount).toFixed(2);
}

// Export to window for inline onclick handlers
window.showToastMessage = showToastMessage;
window.showToast = showToast;
window.sendFeedback = sendFeedback;
window.toggleFeedbackHistory = toggleFeedbackHistory;
window.closeFeedbackModal = closeFeedbackModal;
window.toggleReplyForm = toggleReplyForm;
window.closeReplyForm = closeReplyForm;
window.showNotificationModal = showNotificationModal;
window.formatCurrency = formatCurrency;