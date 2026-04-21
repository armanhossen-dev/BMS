<?php
$currentPage = basename($_SERVER['PHP_SELF']);
function navActive($page) {
    global $currentPage;
    return $currentPage === $page ? 'active' : '';
}
$isAdmin    = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$isStaff    = isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin','staff']);
$isCustomer = isset($_SESSION['role']) && $_SESSION['role'] === 'customer';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="logo-icon">⬡</div>
        <div class="brand-text">
            <h3>APEX BANK</h3>
            <span><?php echo $isAdmin ? 'Admin Portal' : ($isStaff ? 'Staff Portal' : 'My Banking'); ?></span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Main</div>
        <a href="dashboard.php" class="nav-item <?php echo navActive('dashboard.php'); ?>">
            <span class="nav-icon">⊞</span> Dashboard
        </a>

        <?php if ($isStaff): ?>
        <div class="nav-section">Customers</div>
        <a href="customers.php" class="nav-item <?php echo navActive('customers.php'); ?>">
            <span class="nav-icon">👤</span> Customers
        </a>
        <?php endif; ?>

        <a href="accounts.php" class="nav-item <?php echo navActive('accounts.php'); ?>">
            <span class="nav-icon">💳</span> <?php echo $isCustomer ? 'My Accounts' : 'Accounts'; ?>
        </a>

        <div class="nav-section">Transactions</div>
        <a href="deposit.php" class="nav-item <?php echo navActive('deposit.php'); ?>">
            <span class="nav-icon">⬆</span> Deposit
        </a>
        <a href="withdraw.php" class="nav-item <?php echo navActive('withdraw.php'); ?>">
            <span class="nav-icon">⬇</span> Withdraw
        </a>
        <a href="transfer.php" class="nav-item <?php echo navActive('transfer.php'); ?>">
            <span class="nav-icon">⇄</span> Transfer
        </a>
        <a href="transactions.php" class="nav-item <?php echo navActive('transactions.php'); ?>">
            <span class="nav-icon">≡</span> <?php echo $isCustomer ? 'My Transactions' : 'All Transactions'; ?>
        </a>

        <div class="nav-section">Loans</div>
        <a href="loans.php" class="nav-item <?php echo navActive('loans.php'); ?>">
            <span class="nav-icon">🏦</span> <?php echo $isCustomer ? 'My Loans' : 'Loan Management'; ?>
        </a>

        <?php if ($isStaff): ?>
        <div class="nav-section">Reports</div>
        <a href="reports.php" class="nav-item <?php echo navActive('reports.php'); ?>">
            <span class="nav-icon">📊</span> Reports
        </a>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <div class="nav-section" style="color:rgba(231,76,60,0.7);">Admin Only</div>
        <a href="staff.php" class="nav-item <?php echo navActive('staff.php'); ?>" style="position:relative;">
            <span class="nav-icon">👑</span> Staff Management
            <span style="position:absolute;right:14px;top:50%;transform:translateY(-50%);background:rgba(201,168,76,0.15);color:var(--gold);font-size:0.58rem;padding:2px 7px;border-radius:10px;letter-spacing:0.08em;text-transform:uppercase;border:1px solid rgba(201,168,76,0.25);">Admin</span>
        </a>
        <?php endif; ?>

        <div class="nav-section">Account</div>
        <a href="profile.php" class="nav-item <?php echo navActive('profile.php'); ?>">
            <span class="nav-icon">⚙</span> Profile & Settings
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'A', 0, 1)); ?>
            </div>
            <div class="user-info">
                <h5><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></h5>
                <span style="color:<?php echo $isAdmin ? 'var(--gold)' : ($isStaff ? '#3498DB' : 'var(--success)'); ?>;">
                    <?php echo $isAdmin ? '👑 Admin' : ($isStaff ? '👤 Staff' : '🏦 Customer'); ?>
                </span>
            </div>
        </div>
        <a href="landing.php" style="display:block;margin-top:10px;font-size:0.72rem;color:#444;text-decoration:none;text-align:center;transition:color 0.3s;" onmouseover="this.style.color='var(--gold)'" onmouseout="this.style.color='#444'">
            ← Back to Website
        </a>
    </div>
</aside>