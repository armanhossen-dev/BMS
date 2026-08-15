<?php
require_once 'config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Asha Bank | Modern Digital Banking in Bangladesh</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --ink: #0e1117;
            --ink-muted: #4a5162;
            --ink-faint: #8a8f9e;
            --paper: #fafaf8;
            --paper-2: #f2f1ed;
            --paper-3: #e8e7e1;
            --accent: #1a5c9e;
            --accent-light: #e8f0f9;
            --accent-mid: #3d7cc7;
            --gold: #b8913a;
            --gold-light: #f5edda;
            --sans: 'DM Sans', sans-serif;
            --serif: 'DM Serif Display', serif;
            --r: 12px;
            --r-lg: 20px;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: var(--sans);
            background: var(--paper);
            color: var(--ink);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Scroll Animations */
        .reveal {
            opacity: 0;
            transform: translateY(28px);
            transition: opacity 0.65s cubic-bezier(0.22, 1, 0.36, 1), transform 0.65s cubic-bezier(0.22, 1, 0.36, 1);
        }
        .reveal.visible { opacity: 1; transform: translateY(0); }
        .reveal-delay-1 { transition-delay: 0.1s; }
        .reveal-delay-2 { transition-delay: 0.2s; }
        .reveal-delay-3 { transition-delay: 0.3s; }
        .reveal-delay-4 { transition-delay: 0.4s; }

        /* Nav */
        nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            background: rgba(250, 250, 248, 0.88);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--paper-3);
            transition: background 0.3s;
        }
        .nav-inner {
            max-width: 1160px;
            margin: 0 auto;
            padding: 0 28px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .logo-mark {
            width: 34px; height: 34px;
            background: var(--accent);
            border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
        }
        .logo-mark svg { width: 18px; height: 18px; }
        .logo-name {
            font-family: var(--serif);
            font-size: 19px;
            color: var(--ink);
            letter-spacing: -0.3px;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 36px;
            list-style: none;
        }
        .nav-links a {
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            color: var(--ink-muted);
            transition: color 0.2s;
            letter-spacing: 0.01em;
        }
        .nav-links a:hover { color: var(--ink); }
        .nav-cta {
            background: var(--ink) !important;
            color: var(--paper) !important;
            padding: 9px 20px;
            border-radius: 100px;
            font-size: 13px !important;
            transition: background 0.2s, transform 0.15s !important;
        }
        .nav-cta:hover { background: var(--accent) !important; transform: translateY(-1px); }
        .mobile-btn {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
        }
        .mobile-btn svg { width: 22px; height: 22px; stroke: var(--ink); }

        /* Hero */
        .hero {
            padding: 140px 28px 100px;
            max-width: 1160px;
            margin: 0 auto;
        }
        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--accent);
            background: var(--accent-light);
            padding: 5px 14px;
            border-radius: 100px;
            margin-bottom: 28px;
        }
        .hero-eyebrow span { width: 5px; height: 5px; background: var(--accent-mid); border-radius: 50%; display: block; }
        .hero-title {
            font-family: var(--serif);
            font-size: clamp(44px, 6vw, 76px);
            line-height: 1.07;
            letter-spacing: -1.5px;
            color: var(--ink);
            max-width: 780px;
            margin-bottom: 24px;
        }
        .hero-title em { font-style: italic; color: var(--accent); }
        .hero-sub {
            font-size: 18px;
            color: var(--ink-muted);
            max-width: 500px;
            line-height: 1.65;
            margin-bottom: 40px;
            font-weight: 300;
        }
        .hero-actions {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 72px;
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            background: var(--ink);
            color: var(--paper);
            border-radius: 100px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            transition: background 0.2s, transform 0.15s;
        }
        .btn-primary:hover { background: var(--accent); transform: translateY(-2px); }
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            background: transparent;
            color: var(--ink);
            border: 1.5px solid var(--paper-3);
            border-radius: 100px;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            transition: border-color 0.2s, color 0.2s;
        }
        .btn-secondary:hover { border-color: var(--ink-muted); }

        /* Hero Stats */
        .hero-stats {
            display: flex;
            gap: 0;
            border-top: 1px solid var(--paper-3);
            padding-top: 36px;
        }
        .stat {
            flex: 1;
            padding-right: 36px;
        }
        .stat + .stat {
            padding-left: 36px;
            border-left: 1px solid var(--paper-3);
        }
        .stat-num {
            font-family: var(--serif);
            font-size: 42px;
            color: var(--ink);
            line-height: 1;
            margin-bottom: 6px;
        }
        .stat-label {
            font-size: 13px;
            color: var(--ink-faint);
            font-weight: 400;
            letter-spacing: 0.01em;
        }

        /* Section base */
        section { padding: 96px 28px; }
        .container { max-width: 1160px; margin: 0 auto; }

        .section-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 16px;
        }
        .section-title {
            font-family: var(--serif);
            font-size: clamp(30px, 3.5vw, 46px);
            letter-spacing: -0.8px;
            line-height: 1.12;
            color: var(--ink);
            margin-bottom: 16px;
        }
        .section-body {
            font-size: 17px;
            color: var(--ink-muted);
            font-weight: 300;
            line-height: 1.7;
            max-width: 540px;
        }

        /* Features */
        .features-bg { background: var(--paper-2); }
        .features-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 64px;
            align-items: center;
        }
        .features-text { }
        .features-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2px;
            background: var(--paper-3);
            border-radius: var(--r-lg);
            overflow: hidden;
        }
        .feat-card {
            background: var(--paper);
            padding: 28px 24px;
            transition: background 0.2s;
        }
        .feat-card:hover { background: var(--accent-light); }
        .feat-card:first-child { border-radius: var(--r-lg) 0 0 0; }
        .feat-card:nth-child(2) { border-radius: 0 var(--r-lg) 0 0; }
        .feat-card:nth-child(5) { border-radius: 0 0 0 var(--r-lg); }
        .feat-card:last-child { border-radius: 0 0 var(--r-lg) 0; }
        .feat-icon {
            width: 38px; height: 38px;
            background: var(--accent-light);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 14px;
        }
        .feat-icon svg { width: 18px; height: 18px; stroke: var(--accent); fill: none; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }
        .feat-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 6px;
        }
        .feat-desc {
            font-size: 13px;
            color: var(--ink-muted);
            line-height: 1.55;
            font-weight: 400;
        }

        /* Products */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2px;
            background: var(--paper-3);
            border-radius: var(--r-lg);
            overflow: hidden;
            margin-top: 52px;
        }
        .prod-card {
            background: var(--paper);
            padding: 36px 28px;
            transition: background 0.2s;
        }
        .prod-card:hover { background: var(--gold-light); }
        .prod-card:first-child { border-radius: var(--r-lg) 0 0 var(--r-lg); }
        .prod-card:last-child { border-radius: 0 var(--r-lg) var(--r-lg) 0; }
        .prod-icon {
            width: 44px; height: 44px;
            border-radius: 12px;
            background: var(--gold-light);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 20px;
        }
        .prod-icon svg { width: 22px; height: 22px; stroke: var(--gold); fill: none; stroke-width: 1.5; stroke-linecap: round; stroke-linejoin: round; }
        .prod-name { font-weight: 600; font-size: 15px; margin-bottom: 8px; }
        .prod-desc { font-size: 13px; color: var(--ink-muted); margin-bottom: 20px; line-height: 1.55; }
        .prod-rate {
            font-family: var(--serif);
            font-size: 26px;
            color: var(--gold);
            display: block;
        }
        .prod-rate-label { font-size: 11px; color: var(--ink-faint); letter-spacing: 0.05em; }

        /* Trust strip */
        .trust-strip {
            background: var(--ink);
            padding: 64px 28px;
            text-align: center;
        }
        .trust-strip .container {
            display: flex;
            align-items: center;
            gap: 48px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .trust-item {
            color: rgba(255,255,255,0.55);
            font-size: 13px;
            letter-spacing: 0.04em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .trust-item svg { width: 16px; height: 16px; stroke: rgba(255,255,255,0.4); fill: none; stroke-width: 1.5; }
        .trust-divider { width: 1px; height: 28px; background: rgba(255,255,255,0.15); }

        /* CTA */
        .cta-section { background: var(--paper-2); }
        .cta-inner {
            background: var(--ink);
            border-radius: 28px;
            padding: 72px 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
        }
        .cta-text .section-title { color: #fafaf8; margin-bottom: 10px; }
        .cta-text p { color: rgba(250,250,248,0.55); font-size: 16px; font-weight: 300; }
        .btn-cta {
            white-space: nowrap;
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 16px 32px;
            background: var(--paper);
            color: var(--ink);
            border-radius: 100px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: background 0.2s, transform 0.15s;
        }
        .btn-cta:hover { background: var(--accent-light); transform: translateY(-2px); }

        /* Footer */
        footer {
            background: var(--ink);
            color: rgba(250,250,248,0.45);
            padding: 72px 28px 36px;
        }
        .footer-top {
            max-width: 1160px;
            margin: 0 auto 56px;
            display: grid;
            grid-template-columns: 1.6fr 1fr 1fr 1fr;
            gap: 48px;
        }
        .footer-brand .logo-name { color: var(--paper); margin-top: 10px; display: block; margin-bottom: 14px; }
        .footer-brand p { font-size: 13px; line-height: 1.65; max-width: 240px; }
        .footer-col h5 {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(250,250,248,0.7);
            margin-bottom: 18px;
        }
        .footer-col a {
            display: block;
            font-size: 14px;
            color: rgba(250,250,248,0.45);
            text-decoration: none;
            margin-bottom: 10px;
            transition: color 0.2s;
        }
        .footer-col a:hover { color: rgba(250,250,248,0.85); }
        .footer-contact p {
            font-size: 13px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .footer-contact svg { width: 14px; height: 14px; stroke: rgba(250,250,248,0.3); fill: none; stroke-width: 1.5; flex-shrink: 0; }
        .footer-bottom {
            max-width: 1160px;
            margin: 0 auto;
            padding-top: 28px;
            border-top: 1px solid rgba(250,250,248,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }
        .footer-bottom p { font-size: 12px; }
        .social-row { display: flex; gap: 14px; }
        .social-row a {
            width: 32px; height: 32px;
            border-radius: 50%;
            border: 1px solid rgba(250,250,248,0.12);
            display: flex; align-items: center; justify-content: center;
            color: rgba(250,250,248,0.4);
            text-decoration: none;
            font-size: 11px;
            transition: border-color 0.2s, color 0.2s;
        }
        .social-row a:hover { border-color: rgba(250,250,248,0.35); color: rgba(250,250,248,0.8); }

        /* Mobile nav */
        @media (max-width: 860px) {
            .nav-links { display: none; }
            .mobile-btn { display: block; }
            .nav-links.open {
                display: flex;
                flex-direction: column;
                gap: 0;
                position: fixed;
                top: 64px; left: 0; right: 0;
                background: var(--paper);
                border-bottom: 1px solid var(--paper-3);
                padding: 16px 0;
            }
            .nav-links.open li { padding: 0 28px; }
            .nav-links.open a { padding: 12px 0; display: block; border-bottom: 1px solid var(--paper-2); }
            .nav-cta { display: inline-block; margin: 12px 28px 4px; }
            .features-layout { grid-template-columns: 1fr; gap: 40px; }
            .products-grid { grid-template-columns: 1fr 1fr; }
            .prod-card:first-child { border-radius: var(--r-lg) 0 0 0; }
            .prod-card:last-child { border-radius: 0 0 var(--r-lg) 0; }
            .prod-card:nth-child(2) { border-radius: 0 var(--r-lg) 0 0; }
            .prod-card:nth-child(3) { border-radius: 0 0 0 var(--r-lg); }
            .cta-inner { flex-direction: column; text-align: center; padding: 48px 32px; }
            .footer-top { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 600px) {
            .hero { padding: 110px 20px 80px; }
            section { padding: 72px 20px; }
            .hero-stats { flex-direction: column; gap: 28px; }
            .stat + .stat { padding-left: 0; border-left: none; border-top: 1px solid var(--paper-3); padding-top: 28px; }
            .features-grid { grid-template-columns: 1fr; }
            .products-grid { grid-template-columns: 1fr; }
            .prod-card { border-radius: 0 !important; }
            .prod-card:first-child { border-radius: var(--r-lg) var(--r-lg) 0 0 !important; }
            .prod-card:last-child { border-radius: 0 0 var(--r-lg) var(--r-lg) !important; }
            .footer-top { grid-template-columns: 1fr; gap: 36px; }
            .footer-bottom { flex-direction: column; text-align: center; }
            .trust-divider { display: none; }
            .hero-actions { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<!-- Navigation -->
<nav id="mainNav">
    <div class="nav-inner">
        <a href="#" class="logo">
            <div class="logo-mark">
                <svg viewBox="0 0 18 18" fill="none">
                    <rect x="1" y="7" width="16" height="10" rx="2" stroke="white" stroke-width="1.4"/>
                    <path d="M5 7V5a4 4 0 0 1 8 0v2" stroke="white" stroke-width="1.4"/>
                    <circle cx="9" cy="12" r="1.5" fill="white"/>
                </svg>
            </div>
            <span class="logo-name">Asha Bank</span>
        </a>
        <ul class="nav-links" id="navLinks">
            <li><a href="#features">Features</a></li>
            <li><a href="#products">Products</a></li>
            <li><a href="#footer">Contact</a></li>
            <li><a href="login.php">Sign In</a></li>
            <li><a href="register.php" class="nav-cta">Open Account</a></li>
        </ul>
        <button class="mobile-btn" id="mobileBtn" aria-label="Toggle menu">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round">
                <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>
    </div>
</nav>

<!-- Hero -->
<main>
<section class="hero">
    <div class="reveal">
        <div class="hero-eyebrow">
            <span></span> Regulated by Bangladesh Bank
        </div>
    </div>
    <h1 class="hero-title reveal reveal-delay-1">
        Banking built for<br><em>modern lives</em>
    </h1>
    <p class="hero-sub reveal reveal-delay-2">
        Open a full-service account in minutes. Send money, save smarter, and manage your finances entirely from your phone.
    </p>
    <div class="hero-actions reveal reveal-delay-3">
        <a href="register.php" class="btn-primary">Open Account Free →</a>
        <a href="login.php" class="btn-secondary">Sign Into Dashboard</a>
    </div>
    <div class="hero-stats reveal reveal-delay-4">
        <div class="stat">
            <div class="stat-num"><span id="statCustomers">0</span>K+</div>
            <div class="stat-label">Happy customers</div>
        </div>
        <div class="stat">
            <div class="stat-num">৳<span id="statDeposits">0</span>Cr+</div>
            <div class="stat-label">Total deposits held</div>
        </div>
        <div class="stat">
            <div class="stat-num"><span id="statSupport">0</span>/7</div>
            <div class="stat-label">Customer support</div>
        </div>
    </div>
</section>

<!-- Features -->
<section class="features-bg" id="features">
    <div class="container">
        <div class="features-layout">
            <div class="features-text">
                <div class="section-label reveal">Why Asha Bank</div>
                <h2 class="section-title reveal reveal-delay-1">Everything your money needs, in one place</h2>
                <p class="section-body reveal reveal-delay-2">Modern banking solutions built with the people of Bangladesh in mind. Secure, fast, and genuinely easy to use.</p>
            </div>
            <div class="features-grid reveal reveal-delay-1">
                <div class="feat-card">
                    <div class="feat-icon">
                        <svg viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="3"/><line x1="12" y1="18" x2="12" y2="18.01"/></svg>
                    </div>
                    <div class="feat-title">Mobile Banking</div>
                    <div class="feat-desc">Full account access from anywhere, at any time.</div>
                </div>
                <div class="feat-card">
                    <div class="feat-icon">
                        <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div class="feat-title">Bank-Grade Security</div>
                    <div class="feat-desc">256-bit encryption and multi-factor authentication.</div>
                </div>
                <div class="feat-card">
                    <div class="feat-icon">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div class="feat-title">24/7 Support</div>
                    <div class="feat-desc">Round-the-clock human assistance via call or chat.</div>
                </div>
                <div class="feat-card">
                    <div class="feat-icon">
                        <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </div>
                    <div class="feat-title">Instant Transfers</div>
                    <div class="feat-desc">Send money to any bank instantly. Zero hidden fees.</div>
                </div>
                <div class="feat-card">
                    <div class="feat-icon">
                        <svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <div class="feat-title">Competitive Rates</div>
                    <div class="feat-desc">High-interest savings and low-rate loan products.</div>
                </div>
                <div class="feat-card">
                    <div class="feat-icon">
                        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <div class="feat-title">বাংলা সাপোর্ট</div>
                    <div class="feat-desc">Full Bangla language support across all services.</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Trust strip -->
<div class="trust-strip">
    <div class="container">
        <div class="trust-item reveal">
            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            Scheduled Bank under Bangladesh Bank
        </div>
        <div class="trust-divider reveal reveal-delay-1"></div>
        <div class="trust-item reveal reveal-delay-1">
            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            256-bit SSL Encryption
        </div>
        <div class="trust-divider reveal reveal-delay-2"></div>
        <div class="trust-item reveal reveal-delay-2">
            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            BFIU Compliant KYC
        </div>
        <div class="trust-divider reveal reveal-delay-3"></div>
        <div class="trust-item reveal reveal-delay-3">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            16479 Helpline — 24/7
        </div>
    </div>
</div>

<!-- Products -->
<section id="products">
    <div class="container">
        <div class="section-label reveal">Our Products</div>
        <h2 class="section-title reveal reveal-delay-1">Accounts designed for every goal</h2>
        <div class="products-grid">
            <div class="prod-card reveal">
                <div class="prod-icon">
                    <svg viewBox="0 0 24 24"><path d="M19 11c0 5-7 10-7 10S5 16 5 11a7 7 0 0 1 14 0z"/><circle cx="12" cy="11" r="2.5"/></svg>
                </div>
                <div class="prod-name">Savings Account</div>
                <div class="prod-desc">Perfect for everyday banking with competitive interest returns on your balance.</div>
                <span class="prod-rate">3.50%</span>
                <span class="prod-rate-label">PER ANNUM</span>
            </div>
            <div class="prod-card reveal reveal-delay-1">
                <div class="prod-icon">
                    <svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                </div>
                <div class="prod-name">Premium Savings</div>
                <div class="prod-desc">Exclusive benefits and higher interest for members who save more.</div>
                <span class="prod-rate">4.00%</span>
                <span class="prod-rate-label">PER ANNUM</span>
            </div>
            <div class="prod-card reveal reveal-delay-2">
                <div class="prod-icon">
                    <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
                </div>
                <div class="prod-name">Current Account</div>
                <div class="prod-desc">Designed for businesses with unlimited daily transactions.</div>
                <span class="prod-rate">0.00%</span>
                <span class="prod-rate-label">TRANSACTION FEE</span>
            </div>
            <div class="prod-card reveal reveal-delay-3">
                <div class="prod-icon">
                    <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                </div>
                <div class="prod-name">Home Loan</div>
                <div class="prod-desc">Affordable home financing with flexible repayment plans.</div>
                <span class="prod-rate">8.50%</span>
                <span class="prod-rate-label">PER ANNUM</span>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="container">
        <div class="cta-inner reveal">
            <div class="cta-text">
                <h2 class="section-title">Start banking with Asha today</h2>
                <p>Join thousands who've already made the switch to modern digital banking.</p>
            </div>
            <a href="register.php" class="btn-cta">Open Account Free →</a>
        </div>
    </div>
</section>
</main>

<!-- Footer -->
<footer id="footer">
    <div class="footer-top">
        <div class="footer-brand reveal">
            <div class="logo">
                <div class="logo-mark">
                    <svg viewBox="0 0 18 18" fill="none">
                        <rect x="1" y="7" width="16" height="10" rx="2" stroke="white" stroke-width="1.4"/>
                        <path d="M5 7V5a4 4 0 0 1 8 0v2" stroke="white" stroke-width="1.4"/>
                        <circle cx="9" cy="12" r="1.5" fill="white"/>
                    </svg>
                </div>
                <span class="logo-name">Asha Bank</span>
            </div>
            <p>A fully digital, full-service scheduled bank under Bangladesh Bank. Serving individuals and businesses across Bangladesh.</p>
            <div class="footer-contact" style="margin-top: 20px;">
                <p>
                    <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.6 3.49a2 2 0 0 1 1.99-2.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.96a16 16 0 0 0 6.09 6.09l.95-.95a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    16479 (24/7 Helpline)
                </p>
                <p>
                    <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    support@ashabank.bd
                </p>
                <p>
                    <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    Dhaka, Bangladesh
                </p>
            </div>
        </div>
        <div class="footer-col reveal reveal-delay-1">
            <h5>Company</h5>
            <a href="#">About Us</a>
            <a href="#">Careers</a>
            <a href="#">Press</a>
            <a href="#">Blog</a>
        </div>
        <div class="footer-col reveal reveal-delay-2">
            <h5>Banking</h5>
            <a href="login.php">Internet Banking</a>
            <a href="register.php">Open Account</a>
            <a href="#">Loan Application</a>
            <a href="#">Credit Cards</a>
        </div>
        <div class="footer-col reveal reveal-delay-3">
            <h5>Support</h5>
            <a href="#">Help Center</a>
            <a href="#">FAQs</a>
            <a href="#">Branch Locator</a>
            <a href="#">ATM Locations</a>
        </div>
    </div>
    <div class="footer-bottom">
        <p>© 2026 Asha Bank. All rights reserved. A Scheduled Bank under Bangladesh Bank.</p>
        <div class="social-row">
            <a href="#" aria-label="Facebook">f</a>
            <a href="#" aria-label="Twitter">𝕏</a>
            <a href="#" aria-label="LinkedIn">in</a>
        </div>
    </div>
</footer>

<script>
    // Mobile nav
    const btn = document.getElementById('mobileBtn');
    const links = document.getElementById('navLinks');
    btn.addEventListener('click', () => links.classList.toggle('open'));
    links.querySelectorAll('a').forEach(a => a.addEventListener('click', () => links.classList.remove('open')));

    // Scroll reveal
    const reveals = document.querySelectorAll('.reveal');
    const io = new IntersectionObserver((entries) => {
        entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); } });
    }, { threshold: 0.12 });
    reveals.forEach(el => io.observe(el));

    // Counter animation
    function countUp(el, target, duration) {
        let start = 0, startTime = null;
        function step(ts) {
            if (!startTime) startTime = ts;
            const p = Math.min((ts - startTime) / duration, 1);
            el.textContent = Math.floor(p * target);
            if (p < 1) requestAnimationFrame(step);
            else el.textContent = target;
        }
        requestAnimationFrame(step);
    }

    const statsSection = document.querySelector('.hero-stats');
    let statsTriggered = false;
    const statsObserver = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && !statsTriggered) {
            statsTriggered = true;
            countUp(document.getElementById('statCustomers'), 50, 1800);
            countUp(document.getElementById('statDeposits'), 500, 2000);
            countUp(document.getElementById('statSupport'), 24, 1400);
        }
    }, { threshold: 0.4 });
    if (statsSection) statsObserver.observe(statsSection);

    // Smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', e => {
            const target = document.querySelector(a.getAttribute('href'));
            if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth' }); }
        });
    });
</script>
</body>
</html>