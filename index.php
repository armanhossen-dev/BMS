<?php require_once 'config/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asha Bank | Premium Digital Banking</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-university"></i>
            Asha <span>Bank</span>
        </div>
        <div class="navbar-menu">
            <button id="themeToggle" class="btn-outline" style="background: transparent; padding: 0.5rem 1rem;">
                <i class="fas fa-moon"></i>
            </button>
            <a href="login.php" class="btn">Login</a>
            <a href="register.php" class="btn-outline">Open Account</a>
        </div>
    </nav>

    <div class="container" style="margin-top: 3rem;">
        <div class="glass-card text-center fade-in">
            <i class="fas fa-shield-alt" style="font-size: 4rem; color: var(--accent); margin-bottom: 1rem;"></i>
            <h1>Welcome to <span style="color: var(--accent);">Asha Bank</span></h1>
            <p style="font-size: 1.2rem; margin: 1rem 0;">Experience banking with modern glassmorphism design,<br>secure transactions, and 24/7 digital access.</p>
            <div class="d-flex justify-center gap-2" style="margin-top: 2rem;">
                <a href="register.php" class="btn btn-lg">Open Account <i class="fas fa-arrow-right"></i></a>
                <a href="login.php" class="btn-outline btn-lg">Access Dashboard</a>
            </div>
        </div>

        <div class="dashboard-grid" style="margin-top: 3rem;">
            <div class="stat-card text-center">
                <i class="fas fa-lock stat-icon"></i>
                <div class="stat-value">100% Secure</div>
                <div class="stat-label">Banking encryption</div>
            </div>
            <div class="stat-card text-center">
                <i class="fas fa-clock stat-icon"></i>
                <div class="stat-value">24/7 Access</div>
                <div class="stat-label">Online banking</div>
            </div>
            <div class="stat-card text-center">
                <i class="fas fa-exchange-alt stat-icon"></i>
                <div class="stat-value">Instant Transfers</div>
                <div class="stat-label">Send money instantly</div>
            </div>
        </div>
    </div>

    <div id="toastContainer"></div>
    <script src="assets/js/main.js"></script>
</body>
</html>