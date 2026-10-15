/**
 * Asha Bank — Interactive Standalone Demo Engine
 * Simulates complete banking platform lifecycle in localStorage
 * Zero PHP / Database dependencies
 */

const BMS_STORAGE_KEY = 'ASHA_BANK_DEMO_STATE_V1';

const INITIAL_DEMO_STATE = {
  user: {
    name: 'Arjun Kapoor',
    firstName: 'Arjun',
    lastName: 'Kapoor',
    username: 'arjun.kapoor',
    email: 'arjun.kapoor@example.com',
    phone: '+880 1712-345678',
    nid: '5912837461928',
    accountNumber: '0192837465',
    routingNumber: '090270144',
    swiftCode: 'ASHABDDH',
    accountType: 'Savings Account',
    balance: 125450.00,
    monthlyReceived: 45000.00,
    monthlySent: 18200.00,
    tier: 'Platinum',
    status: 'Active',
    kycStatus: 'Verified',
    address: 'House 42, Road 11, Banani, Dhaka 1213',
    gender: 'Male',
    dob: '1992-05-14',
    nominee: {
      name: 'Rohan Kapoor',
      relation: 'Brother',
      phone: '+880 1799-887766'
    }
  },
  card: {
    number: '4532 •••• •••• 8842',
    rawNumber: '4532 8920 1829 8842',
    name: 'ARJUN KAPOOR',
    expiry: '09/31',
    cvv: '842',
    pin: '4920',
    type: 'VISA Platinum Debit',
    status: 'Active',
    dailyAtmLimit: 50000,
    dailyPosLimit: 100000,
    onlineActive: true,
    intlActive: false
  },
  transactions: [
    { id: 'TXN-90218', type: 'NPSB Transfer', desc: 'Received from City Bank (A/C 20491)', date: 'Today, 02:45 PM', amount: 25000.00, direction: 'in', status: 'Completed', ref: 'NPSB-2910481' },
    { id: 'TXN-90217', type: 'Utility Bill', desc: 'DESCO Electricity Bill Payment', date: 'Yesterday, 11:15 AM', amount: 3200.00, direction: 'out', status: 'Completed', ref: 'BILL-4928174' },
    { id: 'TXN-90216', type: 'Peer Transfer', desc: 'Transfer to Sanya Malhotra (A/C 0184920194)', date: '14 Oct 2026', amount: 5000.00, direction: 'out', status: 'Completed', ref: 'P2P-9847291' },
    { id: 'TXN-90215', type: 'bKash Deposit', desc: 'MFS Deposit via bKash (TrxID: 9X82KD)', date: '12 Oct 2026', amount: 15000.00, direction: 'in', status: 'Completed', ref: 'MFS-7392018' },
    { id: 'TXN-90214', type: 'ATM Cash', desc: 'Fast Cash Motijheel ATM Terminal #04', date: '10 Oct 2026', amount: 10000.00, direction: 'out', status: 'Completed', ref: 'ATM-1092837' }
  ],
  notifications: [
    { id: 1, title: 'Transfer Received', message: '৳25,000.00 credited via NPSB from City Bank.', time: '10m ago', read: false, type: 'success' },
    { id: 2, title: 'Card Security Alert', message: 'Online transaction ৳3,200 approved for DESCO Bill.', time: '1d ago', read: false, type: 'info' },
    { id: 3, title: 'KYC Verified', message: 'Your National ID documents have been verified by Compliance.', time: '3d ago', read: true, type: 'success' }
  ],
  staffKycQueue: [
    { id: 'KYC-401', customer: 'Farhana Yasmin', nid: '7392019482710', phone: '+880 1812-334455', docType: 'Smart NID', date: '15 Oct 2026', status: 'Pending' },
    { id: 'KYC-402', customer: 'Tanvir Ahmed', nid: '6102938475829', phone: '+880 1711-223344', docType: 'National ID', date: '15 Oct 2026', status: 'Pending' },
    { id: 'KYC-403', customer: 'Mehedi Hasan', nid: '8920194820194', phone: '+880 1913-998877', docType: 'Passport', date: '14 Oct 2026', status: 'Pending' }
  ],
  feedbackTickets: [
    { id: 'TKT-108', customer: 'Arjun Kapoor', subject: 'ATM Cash Dispense Query', category: 'ATM', status: 'Replied', date: '14 Oct 2026', message: 'ATM cash dispensed ৳10,000 but receipt showed slip error.', reply: 'Our operations officer verified terminal log. Transaction is balanced and verified.' },
    { id: 'TKT-109', customer: 'Sanya Malhotra', subject: 'Debit Card Travel Quota', category: 'Card', status: 'Pending', date: '15 Oct 2026', message: 'Can I endorse $2000 on my Platinum card for travel?', reply: '' }
  ],
  adminStats: {
    totalReserves: 1850450000.00,
    totalCustomers: 125005,
    activeStaff: 34,
    dailyTransactions: 4210
  }
};

class BMSDemoEngine {
  constructor() {
    this.state = this.loadState();
  }

  loadState() {
    try {
      const stored = localStorage.getItem(BMS_STORAGE_KEY);
      if (stored) {
        return JSON.parse(stored);
      }
    } catch (e) {
      console.warn('Could not read localStorage', e);
    }
    const fresh = JSON.parse(JSON.stringify(INITIAL_DEMO_STATE));
    this.saveState(fresh);
    return fresh;
  }

  saveState(newState) {
    this.state = newState || this.state;
    try {
      localStorage.setItem(BMS_STORAGE_KEY, JSON.stringify(this.state));
    } catch (e) {
      console.warn('Could not save to localStorage', e);
    }
  }

  resetState() {
    const fresh = JSON.parse(JSON.stringify(INITIAL_DEMO_STATE));
    this.saveState(fresh);
    this.showToast('Demo State Reset', 'Default banking records restored.', 'info');
    setTimeout(() => window.location.reload(), 400);
  }

  formatBDT(num) {
    return '৳' + Number(num || 0).toLocaleString('en-BD', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  formatNumber(num) {
    return Number(num || 0).toLocaleString('en-BD');
  }

  getTier(balance) {
    if (balance >= 500000) return 'Black Edition';
    if (balance >= 100000) return 'Platinum';
    if (balance >= 50000) return 'Gold';
    if (balance >= 20000) return 'Silver';
    return 'Classic';
  }

  addTransaction({ type, desc, amount, direction, ref }) {
    const amt = parseFloat(amount);
    if (isNaN(amt) || amt <= 0) return false;

    if (direction === 'out' && this.state.user.balance < amt) {
      this.showToast('Insufficient Balance', 'You do not have enough funds to complete this transfer.', 'danger');
      return false;
    }

    if (direction === 'out') {
      this.state.user.balance -= amt;
      this.state.user.monthlySent += amt;
    } else {
      this.state.user.balance += amt;
      this.state.user.monthlyReceived += amt;
    }

    this.state.user.tier = this.getTier(this.state.user.balance);

    const now = new Date();
    const timeStr = 'Today, ' + now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const txnId = 'TXN-' + Math.floor(10000 + Math.random() * 90000);

    const newTxn = {
      id: txnId,
      type: type || 'Transfer',
      desc: desc || 'Banking Transaction',
      date: timeStr,
      amount: amt,
      direction: direction || 'out',
      status: 'Completed',
      ref: ref || ('REF-' + Math.floor(1000000 + Math.random() * 9000000))
    };

    this.state.transactions.unshift(newTxn);

    // Push notification
    this.addNotification({
      title: direction === 'out' ? 'Money Sent' : 'Money Received',
      message: `${direction === 'out' ? 'Debit of ' : 'Credit of '} ${this.formatBDT(amt)}: ${desc}`,
      type: direction === 'out' ? 'info' : 'success'
    });

    this.saveState();
    return newTxn;
  }

  addNotification({ title, message, type }) {
    const newNotif = {
      id: Date.now(),
      title,
      message,
      time: 'Just now',
      read: false,
      type: type || 'info'
    };
    this.state.notifications.unshift(newNotif);
    this.saveState();
  }

  toggleCardFreeze() {
    this.state.card.status = this.state.card.status === 'Active' ? 'Frozen' : 'Active';
    this.saveState();
    this.showToast(
      this.state.card.status === 'Frozen' ? 'Card Frozen' : 'Card Unfrozen',
      `Your card is now ${this.state.card.status.toLowerCase()}.`,
      this.state.card.status === 'Frozen' ? 'warning' : 'success'
    );
    return this.state.card.status;
  }

  showToast(title, message, type = 'info') {
    let container = document.getElementById('toastContainer');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toastContainer';
      container.className = 'toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.style.animation = 'fadeInUp 0.3s ease forwards';
    toast.innerHTML = `
      <div class="toast-icon">
        <i class='bx ${type === 'success' ? 'bx-check-circle' : type === 'danger' ? 'bx-error-circle' : type === 'warning' ? 'bx-error' : 'bx-info-circle'}'></i>
      </div>
      <div class="toast-body">
        <div class="toast-title">${title}</div>
        <div class="toast-msg">${message}</div>
      </div>
      <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    container.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(10px)';
      toast.style.transition = 'all 0.3s ease';
      setTimeout(() => toast.remove(), 300);
    }, 4500);
  }

  injectDemoHeader() {
    if (document.getElementById('bmsDemoTopBar')) return;
    const path = window.location.pathname.toLowerCase();
    if (path.includes('login') || path.includes('register')) {
      return; // Preserve clean 2-column auth layout
    }
    const bar = document.createElement('div');
    bar.id = 'bmsDemoTopBar';
    bar.className = 'bms-demo-topbar';
    bar.innerHTML = `
      <div class="bms-dt-left">
        <span class="bms-dt-badge">Demo Mode</span>
        <span class="bms-dt-title">Asha Bank Interactive Simulation (No DB / PHP)</span>
      </div>
      <div class="bms-dt-links">
        <a href="../index.html" class="bms-dt-link"><i class='bx bx-home-alt'></i> Public Site</a>
        <a href="dashboard.html" class="bms-dt-link ${window.location.pathname.includes('dashboard') && !window.location.pathname.includes('staff') && !window.location.pathname.includes('admin') ? 'active' : ''}"><i class='bx bx-user'></i> Customer Portal</a>
        <a href="staff-dashboard.html" class="bms-dt-link ${window.location.pathname.includes('staff') ? 'active' : ''}"><i class='bx bx-shield'></i> Staff Ops</a>
        <a href="admin-dashboard.html" class="bms-dt-link ${window.location.pathname.includes('admin') ? 'active' : ''}"><i class='bx bx-crown'></i> Admin Center</a>
        <button onclick="window.BMS.resetState()" class="bms-dt-btn" title="Reset all demo data to defaults"><i class='bx bx-reset'></i> Reset Data</button>
      </div>
    `;
    document.body.prepend(bar);

    // Style the bar
    const style = document.createElement('style');
    style.textContent = `
      .bms-demo-topbar {
        background: #111114;
        border-bottom: 1px solid rgba(212,166,87,0.3);
        color: #e4e4e7;
        font-family: 'Inter', -apple-system, sans-serif;
        font-size: 12px;
        padding: 8px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
        position: sticky;
        top: 0;
        z-index: 10000;
        box-shadow: 0 2px 10px rgba(0,0,0,0.4);
      }
      .bms-dt-left { display: flex; align-items: center; gap: 8px; font-weight: 500; }
      .bms-dt-badge {
        background: #d4a657;
        color: #09090b;
        font-weight: 800;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }
      .bms-dt-links { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
      .bms-dt-link {
        color: #a1a1aa;
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 8px;
        border-radius: 5px;
        transition: all 0.15s;
      }
      .bms-dt-link:hover, .bms-dt-link.active {
        color: #f6ba42;
        background: rgba(246,186,66,0.12);
      }
      .bms-dt-btn {
        background: #27272a;
        color: #f43f5e;
        border: 1px solid rgba(244,63,94,0.3);
        border-radius: 5px;
        padding: 3px 8px;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.15s;
      }
      .bms-dt-btn:hover {
        background: rgba(244,63,94,0.15);
      }
      @media(max-width: 768px) {
        .bms-demo-topbar { font-size: 11px; padding: 6px 10px; }
        .bms-dt-title { display: none; }
      }
    `;
    document.head.appendChild(style);
  }
}

// Global instance
window.BMS = new BMSDemoEngine();

document.addEventListener('DOMContentLoaded', () => {
  window.BMS.injectDemoHeader();
});
