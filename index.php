<?php require_once 'config/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asha Bank - Modern Digital Banking in Bangladesh</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/theme-switch.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --font-sans: 'Inter', sans-serif;
            --bg-primary: #FFFFFF;
            --bg-secondary: #F8FAFC;
            --bg-tertiary: #F1F5F9;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-tertiary: #94A3B8;
            --border-color: #E2E8F0;
            --accent: #185FA5;
            --accent-dark: #0C447C;
            --accent-bg: #E6F1FB;
            --accent-text: #0C447C;
            --success: #3B6D11;
            --success-bg: #EAF3DE;
            --danger: #A32D2D;
            --warning: #BA7517;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        body.dark {
            --bg-primary: #0F172A;
            --bg-secondary: #1E293B;
            --bg-tertiary: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #CBD5E1;
            --text-tertiary: #94A3B8;
            --border-color: #334155;
            --accent: #3B82F6;
            --accent-dark: #2563EB;
            --accent-bg: #1E3A5F;
            --accent-text: #93C5FD;
        }
        
        body {
            font-family: var(--font-sans);
            background: var(--bg-secondary);
            color: var(--text-primary);
            overflow-x: hidden;
        }
        
        /* Theme Switch Position */
        .theme-switch-wrapper {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 1000;
        }
        
        /* Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-color);
            padding: 16px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
            transition: all 0.3s ease;
        }
        
        body.dark .navbar {
            background: rgba(15, 23, 42, 0.95);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--accent-bg);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo-icon svg {
            width: 24px;
            height: 24px;
        }
        
        .logo-text h1 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .logo-text p {
            font-size: 11px;
            color: var(--text-tertiary);
        }
        
        .nav-links {
            display: flex;
            gap: 32px;
            align-items: center;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 500;
            font-size: 14px;
            transition: color 0.2s;
        }
        
        .nav-links a:hover {
            color: var(--accent);
        }
        
        .nav-buttons {
            display: flex;
            gap: 12px;
        }
        
        .btn-outline-nav {
            padding: 8px 20px;
            border-radius: 40px;
            border: 1.5px solid var(--accent);
            background: transparent;
            color: var(--accent);
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-outline-nav:hover {
            background: var(--accent);
            color: white;
        }
        
        .btn-primary-nav {
            padding: 8px 20px;
            border-radius: 40px;
            background: var(--accent);
            color: white;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-primary-nav:hover {
            background: var(--accent-dark);
            transform: translateY(-2px);
        }
        
        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 120px 5% 80px;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
        }
        
        .hero-content {
            flex: 1;
            max-width: 600px;
        }
        
        .hero-badge {
            display: inline-block;
            padding: 6px 14px;
            background: var(--accent-bg);
            color: var(--accent-text);
            border-radius: 40px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 24px;
        }
        
        .hero-content h1 {
            font-size: 56px;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 24px;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero-content p {
            font-size: 18px;
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 32px;
        }
        
        .hero-buttons {
            display: flex;
            gap: 16px;
            margin-bottom: 48px;
        }
        
        .btn-hero-primary {
            padding: 14px 32px;
            border-radius: 48px;
            background: var(--accent);
            color: white;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-hero-primary:hover {
            background: var(--accent-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-hero-secondary {
            padding: 14px 32px;
            border-radius: 48px;
            border: 1.5px solid var(--border-color);
            background: transparent;
            color: var(--text-primary);
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-hero-secondary:hover {
            border-color: var(--accent);
            color: var(--accent);
        }
        
        .hero-stats {
            display: flex;
            gap: 48px;
        }
        
        .stat-item h3 {
            font-size: 28px;
            font-weight: 800;
            color: var(--accent);
        }
        
        .stat-item p {
            font-size: 13px;
            color: var(--text-tertiary);
            margin: 0;
        }
        
        .hero-image {
            flex: 1;
            display: flex;
            justify-content: center;
        }
        
        .hero-image img {
            max-width: 100%;
            height: auto;
        }
        
        /* Features Section */
        .features {
            padding: 80px 5%;
            background: var(--bg-primary);
        }
        
        .section-header {
            text-align: center;
            max-width: 600px;
            margin: 0 auto 48px;
        }
        
        .section-header h2 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 16px;
        }
        
        .section-header p {
            color: var(--text-secondary);
            font-size: 16px;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 32px;
        }
        
        .feature-card {
            background: var(--bg-secondary);
            border-radius: 24px;
            padding: 32px;
            text-align: center;
            transition: all 0.3s;
            border: 1px solid var(--border-color);
        }
        
        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
        }
        
        .feature-icon {
            width: 64px;
            height: 64px;
            background: var(--accent-bg);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .feature-icon i {
            font-size: 28px;
            color: var(--accent);
        }
        
        .feature-card h3 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .feature-card p {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.6;
        }
        
        /* Products Section */
        .products {
            padding: 80px 5%;
            background: var(--bg-secondary);
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 32px;
        }
        
        .product-card {
            background: var(--bg-primary);
            border-radius: 24px;
            padding: 32px;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }
        
        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }
        
        .product-icon {
            width: 56px;
            height: 56px;
            background: var(--success-bg);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .product-icon i {
            font-size: 24px;
            color: var(--success);
        }
        
        .product-card h3 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .product-card p {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .product-rate {
            font-size: 24px;
            font-weight: 700;
            color: var(--accent);
        }
        
        /* CTA Section */
        .cta {
            padding: 80px 5%;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            text-align: center;
        }
        
        .cta h2 {
            font-size: 36px;
            font-weight: 700;
            color: white;
            margin-bottom: 16px;
        }
        
        .cta p {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 32px;
        }
        
        .btn-cta {
            display: inline-block;
            padding: 14px 40px;
            border-radius: 48px;
            background: white;
            color: var(--accent);
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-cta:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        /* Footer */
        .footer {
            background: var(--bg-primary);
            padding: 60px 5% 30px;
            border-top: 1px solid var(--border-color);
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 48px;
            margin-bottom: 48px;
        }
        
        .footer-col h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .footer-col a {
            display: block;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 13px;
            margin-bottom: 12px;
            transition: color 0.2s;
        }
        
        .footer-col a:hover {
            color: var(--accent);
        }
        
        .social-links {
            display: flex;
            gap: 16px;
        }
        
        .social-links a {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--bg-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .social-links a:hover {
            background: var(--accent);
            color: white;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid var(--border-color);
            font-size: 12px;
            color: var(--text-tertiary);
        }
        
        /* Mobile Menu */
        .mobile-menu-btn {
            display: none;
            font-size: 24px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .hero {
                flex-direction: column;
                text-align: center;
                padding-top: 100px;
            }
            
            .hero-content h1 {
                font-size: 36px;
            }
            
            .hero-buttons {
                justify-content: center;
            }
            
            .hero-stats {
                justify-content: center;
            }
            
            .section-header h2 {
                font-size: 28px;
            }
            
            .features-grid, .products-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Mobile Menu Active */
        .nav-links.active {
            display: flex;
            flex-direction: column;
            position: absolute;
            top: 70px;
            left: 0;
            right: 0;
            background: var(--bg-primary);
            padding: 20px;
            gap: 16px;
            border-bottom: 1px solid var(--border-color);
        }
    </style>
</head>
<body>
    <div class="theme-switch-wrapper">
        <label class="theme-switch">
            <input type="checkbox" class="theme-switch__checkbox" id="themeCheckbox">
            <div class="theme-switch__container">
                <div class="theme-switch__clouds"></div>
                <div class="theme-switch__stars-container">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 144 55" fill="none">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M135.831 3.00688C135.055 3.85027 134.111 4.29946 133 4.35447C134.111 4.40947 135.055 4.85867 135.831 5.71123C136.607 6.55462 136.996 7.56303 136.996 8.72727C136.996 7.95722 137.172 7.25134 137.525 6.59129C137.886 5.93124 138.372 5.39954 138.98 5.00535C139.598 4.60199 140.268 4.39114 141 4.35447C139.88 4.2903 138.936 3.85027 138.16 3.00688C137.384 2.16348 136.996 1.16425 136.996 0C136.996 1.16425 136.607 2.16348 135.831 3.00688ZM31 23.3545C32.1114 23.2995 33.0551 22.8503 33.8313 22.0069C34.6075 21.1635 34.9956 20.1642 34.9956 19C34.9956 20.1642 35.3837 21.1635 36.1599 22.0069C36.9361 22.8503 37.8798 23.2903 39 23.3545C38.2679 23.3911 37.5976 23.602 36.9802 24.0053C36.3716 24.3995 35.8864 24.9312 35.5248 25.5913C35.172 26.2513 34.9956 26.9572 34.9956 27.7273C34.9956 26.563 34.6075 25.5546 33.8313 24.7112C33.0551 23.8587 32.1114 23.4095 31 23.3545ZM0 36.3545C1.11136 36.2995 2.05513 35.8503 2.83131 35.0069C3.6075 34.1635 3.99559 33.1642 3.99559 32C3.99559 33.1642 4.38368 34.1635 5.15987 35.0069C5.93605 35.8503 6.87982 36.2903 8 36.3545C7.26792 36.3911 6.59757 36.602 5.98015 37.0053C5.37155 37.3995 4.88644 37.9312 4.52481 38.5913C4.172 39.2513 3.99559 39.9572 3.99559 40.7273C3.99559 39.563 3.6075 38.5546 2.83131 37.7112C2.05513 36.8587 1.11136 36.4095 0 36.3545ZM56.8313 24.0069C56.0551 24.8503 55.1114 25.2995 54 25.3545C55.1114 25.4095 56.0551 25.8587 56.8313 26.7112C57.6075 27.5546 57.9956 28.563 57.9956 29.7273C57.9956 28.9572 58.172 28.2513 58.5248 27.5913C58.8864 26.9312 59.3716 26.3995 59.9802 26.0053C60.5976 25.602 61.2679 25.3911 62 25.3545C60.8798 25.2903 59.9361 24.8503 59.1599 24.0069C58.3837 23.1635 57.9956 22.1642 57.9956 21C57.9956 22.1642 57.6075 23.1635 56.8313 24.0069ZM81 25.3545C82.1114 25.2995 83.0551 24.8503 83.8313 24.0069C84.6075 23.1635 84.9956 22.1642 84.9956 21C84.9956 22.1642 85.3837 23.1635 86.1599 24.0069C86.9361 24.8503 87.8798 25.2903 89 25.3545C88.2679 25.3911 87.5976 25.602 86.9802 26.0053C86.3716 26.3995 85.8864 26.9312 85.5248 27.5913C85.172 28.2513 84.9956 28.9572 84.9956 29.7273C84.9956 28.563 84.6075 27.5546 83.8313 26.7112C83.0551 25.8587 82.1114 25.4095 81 25.3545ZM136 36.3545C137.111 36.2995 138.055 35.8503 138.831 35.0069C139.607 34.1635 139.996 33.1642 139.996 32C139.996 33.1642 140.384 34.1635 141.16 35.0069C141.936 35.8503 142.88 36.2903 144 36.3545C143.268 36.3911 142.598 36.602 141.98 37.0053C141.372 37.3995 140.886 37.9312 140.525 38.5913C140.172 39.2513 139.996 39.9572 139.996 40.7273C139.996 39.563 139.607 38.5546 138.831 37.7112C138.055 36.8587 137.111 36.4095 136 36.3545ZM101.831 49.0069C101.055 49.8503 100.111 50.2995 99 50.3545C100.111 50.4095 101.055 50.8587 101.831 51.7112C102.607 52.5546 102.996 53.563 102.996 54.7273C102.996 53.9572 103.172 53.2513 103.525 52.5913C103.886 51.9312 104.372 51.3995 104.98 51.0053C105.598 50.602 106.268 50.3911 107 50.3545C105.88 50.2903 104.936 49.8503 104.16 49.0069C103.384 48.1635 102.996 47.1642 102.996 46C102.996 47.1642 102.607 48.1635 101.831 49.0069Z" fill="currentColor"></path>
                    </svg>
                </div>
                <div class="theme-switch__circle-container">
                    <div class="theme-switch__sun-moon-container">
                        <div class="theme-switch__moon">
                            <div class="theme-switch__spot"></div>
                            <div class="theme-switch__spot"></div>
                            <div class="theme-switch__spot"></div>
                        </div>
                    </div>
                </div>
            </div>
        </label>
    </div>
    
    <nav class="navbar">
        <div class="logo">
            <div class="logo-icon">
                <svg width="24" height="24" viewBox="0 0 16 16" fill="none">
                    <rect x="1" y="6" width="14" height="9" rx="1.5" fill="none" stroke="var(--accent)" stroke-width="1.2"/>
                    <path d="M4 6V4a4 4 0 0 1 8 0v2" stroke="var(--accent)" stroke-width="1.2"/>
                    <circle cx="8" cy="10.5" r="1.5" fill="var(--accent)"/>
                </svg>
            </div>
            <div class="logo-text">
                <h1>Asha Bank</h1>
                <p>Bangladesh</p>
            </div>
        </div>
        <div class="mobile-menu-btn" id="mobileMenuBtn">
            <i class="fas fa-bars"></i>
        </div>
        <div class="nav-links" id="navLinks">
            <a href="#home">Home</a>
            <a href="#features">Features</a>
            <a href="#products">Products</a>
            <a href="#about">About</a>
            <a href="#contact">Contact</a>
        </div>
        <div class="nav-buttons">
            <a href="login.php" class="btn-outline-nav">Login</a>
            <a href="register.php" class="btn-primary-nav">Open Account</a>
        </div>
    </nav>
    
    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-content">
            <span class="hero-badge">
                <i class="fas fa-shield-alt"></i> Secure Digital Banking
            </span>
            <h1>Banking That<br>Works For You</h1>
            <p>Experience modern banking with Asha Bank. Open an account in minutes, send money instantly, and manage your finances 24/7 from anywhere in Bangladesh.</p>
            <div class="hero-buttons">
                <a href="register.php" class="btn-hero-primary">Open Account Free →</a>
                <a href="login.php" class="btn-hero-secondary">Login to Dashboard</a>
            </div>
            <div class="hero-stats">
                <div class="stat-item">
                    <h3>50K+</h3>
                    <p>Happy Customers</p>
                </div>
                <div class="stat-item">
                    <h3>৳500Cr+</h3>
                    <p>Total Deposits</p>
                </div>
                <div class="stat-item">
                    <h3>24/7</h3>
                    <p>Customer Support</p>
                </div>
            </div>
        </div>
        <div class="hero-image">
            <div style="width: 400px; height: 400px; background: linear-gradient(135deg, var(--accent-bg), transparent); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-mobile-alt" style="font-size: 120px; color: var(--accent); opacity: 0.8;"></i>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="features" id="features">
        <div class="section-header">
            <h2>Why Choose Asha Bank?</h2>
            <p>We provide modern banking solutions tailored for the people of Bangladesh</p>
        </div>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <h3>Mobile Banking</h3>
                <p>Access your account anytime, anywhere with our mobile app. Support for bKash, Nagad, Rocket, and Upai.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h3>Bank-Level Security</h3>
                <p>Your money and data are protected with 256-bit encryption and multi-factor authentication.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3>24/7 Support</h3>
                <p>Our customer support team is available round the clock to assist you with any issues.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-exchange-alt"></i>
                </div>
                <h3>Instant Transfers</h3>
                <p>Send money to any bank account in Bangladesh instantly with zero hidden fees.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-percent"></i>
                </div>
                <h3>Competitive Rates</h3>
                <p>Enjoy high-interest savings accounts and low-interest loan options.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-language"></i>
                </div>
                <h3>বাংলা সাপোর্ট</h3>
                <p>Full Bangla language support for our local customers across Bangladesh.</p>
            </div>
        </div>
    </section>
    
    <!-- Products Section -->
    <section class="products" id="products">
        <div class="section-header">
            <h2>Our Banking Products</h2>
            <p>Choose the account that fits your needs</p>
        </div>
        <div class="products-grid">
            <div class="product-card">
                <div class="product-icon">
                    <i class="fas fa-piggy-bank"></i>
                </div>
                <h3>Savings Account</h3>
                <p>Perfect for daily banking with competitive interest rates and zero maintenance fees.</p>
                <div class="product-rate">3.50% p.a.</div>
            </div>
            <div class="product-card">
                <div class="product-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>Premium Savings</h3>
                <p>Higher interest rates and exclusive benefits for premium customers.</p>
                <div class="product-rate">4.00% p.a.</div>
            </div>
            <div class="product-card">
                <div class="product-icon">
                    <i class="fas fa-briefcase"></i>
                </div>
                <h3>Current Account</h3>
                <p>Designed for businesses with unlimited transactions and overdraft facility.</p>
                <div class="product-rate">0% p.a.</div>
            </div>
            <div class="product-card">
                <div class="product-icon">
                    <i class="fas fa-home"></i>
                </div>
                <h3>Home Loan</h3>
                <p>Affordable home loans with flexible EMI options up to 25 years.</p>
                <div class="product-rate">8.50% p.a.</div>
            </div>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section class="cta">
        <h2>Ready to start banking with us?</h2>
        <p>Join thousands of satisfied customers who trust Asha Bank for their financial needs.</p>
        <a href="register.php" class="btn-cta">Open Account Now →</a>
    </section>
    
    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="footer-grid">
            <div class="footer-col">
                <h4>Asha Bank</h4>
                <a href="#home">About Us</a>
                <a href="#features">Careers</a>
                <a href="#contact">Contact Us</a>
                <a href="#products">Blog</a>
            </div>
            <div class="footer-col">
                <h4>Banking</h4>
                <a href="login.php">Internet Banking</a>
                <a href="register.php">Open Account</a>
                <a href="#">Loan Application</a>
                <a href="#">Credit Cards</a>
            </div>
            <div class="footer-col">
                <h4>Support</h4>
                <a href="#">Help Center</a>
                <a href="#">FAQs</a>
                <a href="#">Branch Locator</a>
                <a href="#">ATM Locations</a>
            </div>
            <div class="footer-col">
                <h4>Connect With Us</h4>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                </div>
                <p style="margin-top: 20px; font-size: 12px; color: var(--text-tertiary);">
                    <i class="fas fa-phone"></i> 16479 (24/7 Helpline)<br>
                    <i class="fas fa-envelope"></i> support@ashabank.bd
                </p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2024 Asha Bank. All rights reserved. A Scheduled Bank under Bangladesh Bank.</p>
        </div>
    </footer>
    
    <script>
        // Theme Toggle
        const themeCheckbox = document.getElementById('themeCheckbox');
        const savedTheme = localStorage.getItem('theme');
        
        if (savedTheme === 'dark') {
            document.body.classList.add('dark');
            themeCheckbox.checked = true;
        }
        
        themeCheckbox.addEventListener('change', function() {
            if (this.checked) {
                document.body.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            } else {
                document.body.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            }
        });
        
        // Mobile Menu
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const navLinks = document.getElementById('navLinks');
        
        mobileMenuBtn.addEventListener('click', () => {
            navLinks.classList.toggle('active');
        });
        
        // Smooth Scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener