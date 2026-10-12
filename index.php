<?php
require_once 'config/db.php';

// Redirect logged-in users to their respective portals
if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? 'client';
    if ($role === 'admin') redirect('admin/index.php');
    if ($role === 'staff') redirect('staff/dashboard.php');
    redirect('dashboard.php');
}

// Live stats from database
$totalCustomers = 125000;
$totalDeposits  = 1850000000;
$totalBranches  = 48;
$totalCards     = 82000;

try {
    $custCount = (int)$pdo->query("SELECT COUNT(*) FROM CUSTOMER WHERE IsActive = 1")->fetchColumn();
    if ($custCount > 0) $totalCustomers = $custCount;
    
    $depSum = (float)$pdo->query("SELECT COALESCE(SUM(AvailableBalance),0) FROM ACCOUNT WHERE AccountStatus = 'Active'")->fetchColumn();
    if ($depSum > 0) $totalDeposits = $depSum;

    $branchCount = (int)$pdo->query("SELECT COUNT(*) FROM BRANCH")->fetchColumn();
    if ($branchCount > 0) $totalBranches = $branchCount;

    $cardCount = (int)$pdo->query("SELECT COUNT(*) FROM CARDS WHERE CardStatus = 'Active'")->fetchColumn();
    if ($cardCount > 0) $totalCards = $cardCount;
} catch (\Exception $e) {
    // Graceful fallback
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Asha Bank — Bangladesh's modern digital bank. Smart banking, instant transfers, high-yield savings, and more.">
    <meta name="keywords" content="Asha Bank, Bangladesh Bank, Digital Banking, Online Banking, Savings Account">
    <meta name="author" content="Asha Bank PLC">
    <title>Asha Bank | Modern Banking for Bangladesh</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        /* ══════════════════════════════════════════════════
           ASHA BANK HOMEPAGE — MINIMAL SAAS DESIGN
           Inspired by VengenceUI, Cosmos.so, Unicorn.studio
           ══════════════════════════════════════════════════ */
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; font-size: 16px; }

        :root {
            /* Light theme */
            --bg:        #FFFFFF;
            --bg-2:      #FAFAFA;
            --bg-3:      #F5F5F5;
            --ink:       #0A0A0A;
            --ink-2:     #171717;
            --ink-muted: #737373;
            --ink-faint: #A3A3A3;
            --border:    #E5E5E5;
            --border-2:  #D4D4D4;
            --accent:    #000000;

            --font: 'Inter', -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
            --r:    8px;
            --r-md: 12px;
            --r-lg: 16px;
            --r-xl: 20px;
            --r-2xl:24px;

            --ease: cubic-bezier(0.16, 1, 0.3, 1);
            --dur:  200ms;
        }

        [data-theme="dark"] {
            --bg:        #0A0A0A;
            --bg-2:      #111111;
            --bg-3:      #1A1A1A;
            --ink:       #FAFAFA;
            --ink-2:     #E5E5E5;
            --ink-muted: #A3A3A3;
            --ink-faint: #737373;
            --border:    #262626;
            --border-2:  #404040;
            --accent:    #FFFFFF;
        }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--ink);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            font-feature-settings: "cv02","cv03","cv04","cv11";
        }

        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; display: block; }

        .container {
            max-width: 1160px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ── Announcement bar ─────────────────────────── */
        .announcement {
            background: var(--ink);
            color: rgba(255,255,255,0.8);
            text-align: center;
            padding: 10px 24px;
            font-size: 13px;
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .announcement a {
            color: #fff;
            font-weight: 600;
            text-decoration: underline;
            text-underline-offset: 2px;
        }
        .announcement i { font-size: 14px; opacity: 0.6; }
        .announcement .close-ann {
            position: absolute;
            right: 20px;
            cursor: pointer;
            opacity: 0.5;
            background: none;
            border: none;
            color: white;
            font-size: 18px;
        }

        /* ── Navigation ───────────────────────────────── */
        .nav {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(20px) saturate(1.8);
            -webkit-backdrop-filter: blur(20px) saturate(1.8);
            border-bottom: 1px solid var(--border);
            transition: background var(--dur) var(--ease);
        }
        [data-theme="dark"] .nav {
            background: rgba(10,10,10,0.85);
        }
        .nav-inner {
            display: flex;
            align-items: center;
            height: 60px;
            gap: 8px;
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-right: 8px;
        }
        .nav-logo {
            width: 32px; height: 32px;
            background: var(--ink);
            border-radius: var(--r);
            display: flex; align-items: center; justify-content: center;
            color: var(--bg);
            font-size: 16px;
            flex-shrink: 0;
        }
        .nav-logo-text {
            font-size: 16px;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: -0.04em;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 2px;
            flex: 1;
        }
        .nav-link {
            padding: 6px 12px;
            font-size: 14px;
            font-weight: 500;
            color: var(--ink-muted);
            border-radius: var(--r);
            transition: all var(--dur) var(--ease);
            letter-spacing: -0.01em;
        }
        .nav-link:hover {
            color: var(--ink);
            background: var(--bg-3);
        }
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: auto;
        }
        .btn-nav {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            font-size: 14px;
            font-weight: 500;
            border-radius: var(--r);
            cursor: pointer;
            transition: all var(--dur) var(--ease);
            border: 1px solid transparent;
            letter-spacing: -0.01em;
            font-family: var(--font);
        }
        .btn-ghost-nav {
            background: transparent;
            color: var(--ink-muted);
            border-color: transparent;
        }
        .btn-ghost-nav:hover { color: var(--ink); background: var(--bg-3); }
        .btn-solid-nav {
            background: var(--ink);
            color: var(--bg);
            border-color: var(--ink);
        }
        .btn-solid-nav:hover { opacity: 0.85; transform: translateY(-1px); }
        .nav-mobile-menu {
            display: none;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--ink);
            font-size: 22px;
            padding: 6px;
            margin-left: auto;
        }
        .theme-btn {
            width: 34px; height: 34px;
            display: flex; align-items: center; justify-content: center;
            border-radius: var(--r);
            border: 1px solid var(--border);
            background: none;
            cursor: pointer;
            color: var(--ink-muted);
            font-size: 16px;
            transition: all var(--dur);
        }
        .theme-btn:hover { background: var(--bg-3); color: var(--ink); border-color: var(--border-2); }

        /* ── Hero Section ─────────────────────────────── */
        .hero {
            padding: 80px 0 100px;
            position: relative;
            overflow: hidden;
        }

        /* Animated background mesh */
        .hero-bg {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
        }
        .hero-grid-lines {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(var(--border) 1px, transparent 1px),
                linear-gradient(90deg, var(--border) 1px, transparent 1px);
            background-size: 60px 60px;
            opacity: 0.4;
            mask-image: radial-gradient(ellipse 80% 70% at 50% 50%, black 40%, transparent 100%);
        }
        .hero-glow {
            position: absolute;
            top: -100px;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            height: 400px;
            background: radial-gradient(ellipse, rgba(0,0,0,0.04) 0%, transparent 70%);
            border-radius: 50%;
        }
        [data-theme="dark"] .hero-glow {
            background: radial-gradient(ellipse, rgba(255,255,255,0.05) 0%, transparent 70%);
        }

        .hero-content {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 780px;
            margin: 0 auto;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--bg-2);
            border: 1px solid var(--border);
            padding: 5px 12px 5px 8px;
            border-radius: 100px;
            font-size: 12.5px;
            font-weight: 500;
            color: var(--ink-muted);
            margin-bottom: 28px;
            letter-spacing: -0.01em;
            transition: all var(--dur) var(--ease);
        }
        .hero-badge:hover { border-color: var(--border-2); color: var(--ink); }
        .hero-badge-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: #16A34A;
            flex-shrink: 0;
            box-shadow: 0 0 0 2px rgba(22,163,74,0.2);
            animation: pulse-dot 2s ease-in-out infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { box-shadow: 0 0 0 2px rgba(22,163,74,0.2); }
            50% { box-shadow: 0 0 0 4px rgba(22,163,74,0.15); }
        }

        .hero h1 {
            font-size: clamp(38px, 6vw, 68px);
            font-weight: 700;
            color: var(--ink);
            letter-spacing: -0.04em;
            line-height: 1.08;
            margin-bottom: 20px;
        }
        .hero h1 em {
            font-style: normal;
            color: var(--ink-muted);
        }

        .hero-desc {
            font-size: clamp(15px, 2vw, 17px);
            color: var(--ink-muted);
            line-height: 1.65;
            margin-bottom: 36px;
            max-width: 560px;
            margin-left: auto;
            margin-right: auto;
            letter-spacing: -0.01em;
        }

        .hero-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 60px;
        }
        .btn-hero-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: var(--ink);
            color: var(--bg);
            border-radius: var(--r-md);
            font-size: 15px;
            font-weight: 600;
            letter-spacing: -0.02em;
            transition: all var(--dur) var(--ease);
            border: 1px solid var(--ink);
        }
        .btn-hero-primary:hover { opacity: 0.85; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
        .btn-hero-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: var(--bg);
            color: var(--ink);
            border-radius: var(--r-md);
            font-size: 15px;
            font-weight: 500;
            letter-spacing: -0.02em;
            transition: all var(--dur) var(--ease);
            border: 1px solid var(--border-2);
        }
        .btn-hero-secondary:hover { background: var(--bg-3); transform: translateY(-1px); }

        /* Hero stats strip */
        .hero-stats {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            border: 1px solid var(--border);
            border-radius: var(--r-xl);
            background: var(--bg-2);
            overflow: hidden;
            max-width: 600px;
            margin: 0 auto;
        }
        .hero-stat {
            flex: 1;
            padding: 20px 16px;
            text-align: center;
            border-right: 1px solid var(--border);
            transition: background var(--dur);
        }
        .hero-stat:last-child { border-right: none; }
        .hero-stat:hover { background: var(--bg-3); }
        .hero-stat-value {
            font-size: 22px;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: -0.04em;
            line-height: 1;
            margin-bottom: 4px;
        }
        .hero-stat-label {
            font-size: 11.5px;
            color: var(--ink-faint);
            letter-spacing: -0.01em;
        }

        /* ── Dashboard Preview ────────────────────────── */
        .preview-section {
            padding: 80px 0;
            background: var(--bg-2);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            overflow: hidden;
        }
        .preview-label {
            text-align: center;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--ink-faint);
            margin-bottom: 48px;
        }
        .preview-window {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--r-2xl);
            box-shadow: 0 32px 64px rgba(0,0,0,0.08), 0 0 0 1px var(--border);
            overflow: hidden;
            max-width: 920px;
            margin: 0 auto;
        }
        .preview-titlebar {
            background: var(--bg-2);
            border-bottom: 1px solid var(--border);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .preview-dot {
            width: 12px; height: 12px;
            border-radius: 50%;
            background: var(--border-2);
        }
        .preview-dot:nth-child(1) { background: #FF5F57; }
        .preview-dot:nth-child(2) { background: #FEBC2E; }
        .preview-dot:nth-child(3) { background: #28C840; }
        .preview-url {
            flex: 1;
            text-align: center;
            font-size: 12px;
            color: var(--ink-faint);
            background: var(--bg-3);
            border-radius: 6px;
            padding: 4px 12px;
            max-width: 300px;
            margin: 0 auto;
        }
        .preview-body {
            padding: 24px;
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 16px;
            min-height: 340px;
        }
        /* Fake sidebar */
        .fake-sidebar {
            background: var(--ink);
            border-radius: var(--r-lg);
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .fake-sidebar-logo {
            width: 28px; height: 28px;
            background: white;
            border-radius: 6px;
            margin-bottom: 16px;
        }
        .fake-nav-item {
            height: 32px;
            background: rgba(255,255,255,0.08);
            border-radius: 6px;
            display: flex;
            align-items: center;
            padding: 0 10px;
            gap: 8px;
        }
        .fake-nav-item.active { background: rgba(255,255,255,0.18); }
        .fake-nav-dot { width: 14px; height: 14px; background: rgba(255,255,255,0.2); border-radius: 3px; }
        .fake-nav-line { height: 8px; background: rgba(255,255,255,0.15); border-radius: 4px; flex: 1; }
        .fake-nav-item.active .fake-nav-line { background: rgba(255,255,255,0.6); }
        /* Fake main */
        .fake-main { display: flex; flex-direction: column; gap: 12px; }
        .fake-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .fake-stat-card {
            background: var(--bg-2);
            border: 1px solid var(--border);
            border-radius: var(--r-md);
            padding: 14px;
        }
        .fake-stat-label { height: 8px; background: var(--border); border-radius: 4px; width: 60%; margin-bottom: 8px; }
        .fake-stat-value { height: 18px; background: var(--border-2); border-radius: 4px; width: 80%; }
        .fake-card-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .fake-card {
            background: var(--bg-2);
            border: 1px solid var(--border);
            border-radius: var(--r-md);
            padding: 14px;
            height: 120px;
        }
        .fake-line { height: 8px; background: var(--border); border-radius: 4px; margin-bottom: 6px; }
        .fake-line.w-3 { width: 30%; }
        .fake-line.w-5 { width: 50%; }
        .fake-line.w-7 { width: 70%; }
        .fake-line.w-full { width: 100%; }

        /* ── Features Section ─────────────────────────── */
        .section {
            padding: 96px 0;
        }
        .section-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--bg-3);
            border: 1px solid var(--border);
            padding: 4px 12px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
            color: var(--ink-muted);
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 20px;
        }
        .section-title {
            font-size: clamp(26px, 4vw, 40px);
            font-weight: 700;
            color: var(--ink);
            letter-spacing: -0.04em;
            line-height: 1.15;
            margin-bottom: 16px;
        }
        .section-desc {
            font-size: 16px;
            color: var(--ink-muted);
            line-height: 1.65;
            max-width: 500px;
            letter-spacing: -0.01em;
        }

        /* Features bento grid */
        .bento-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            grid-template-rows: auto auto;
            gap: 16px;
            margin-top: 60px;
        }
        .bento-card {
            background: var(--bg-2);
            border: 1px solid var(--border);
            border-radius: var(--r-2xl);
            padding: 28px;
            transition: all 0.3s var(--ease);
            overflow: hidden;
            position: relative;
        }
        .bento-card:hover {
            border-color: var(--border-2);
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.06);
        }
        .bento-card.large {
            grid-column: span 2;
        }
        .bento-card.tall {
            grid-row: span 2;
        }
        .bento-icon {
            width: 44px; height: 44px;
            border-radius: var(--r-md);
            background: var(--bg);
            border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            color: var(--ink);
            margin-bottom: 20px;
        }
        .bento-title {
            font-size: 17px;
            font-weight: 600;
            color: var(--ink);
            letter-spacing: -0.03em;
            margin-bottom: 8px;
        }
        .bento-desc {
            font-size: 14px;
            color: var(--ink-muted);
            line-height: 1.6;
            letter-spacing: -0.01em;
        }
        /* Mini card inside bento */
        .mini-bank-card {
            background: var(--ink);
            border-radius: var(--r-lg);
            padding: 20px;
            color: white;
            margin-top: 20px;
            aspect-ratio: 16/9;
            max-width: 240px;
            position: relative;
            overflow: hidden;
        }
        .mini-bank-card::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -20%;
            width: 140px; height: 140px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            border-radius: 50%;
        }
        .mini-card-num { font-size: 12px; letter-spacing: 0.12em; color: rgba(255,255,255,0.5); margin-bottom: 16px; font-family: monospace; }
        .mini-card-balance { font-size: 20px; font-weight: 700; letter-spacing: -0.04em; }
        .mini-card-label  { font-size: 10px; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.06em; }

        /* Transfer mini UI */
        .transfer-ui {
            margin-top: 20px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--r-lg);
            padding: 16px;
        }
        .transfer-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            background: var(--bg-2);
            border-radius: var(--r);
            margin-bottom: 8px;
            font-size: 13px;
        }
        .transfer-row:last-child { margin-bottom: 0; }
        .t-avatar {
            width: 28px; height: 28px;
            border-radius: 50%;
            background: var(--border-2);
            font-size: 11px;
            font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            color: var(--ink);
        }
        .t-name { flex: 1; font-weight: 500; color: var(--ink); letter-spacing: -0.01em; }
        .t-amount { font-weight: 600; color: var(--ink); letter-spacing: -0.02em; font-size: 13px; }
        .t-amount.in { color: #16A34A; }
        .t-amount.out { color: var(--ink-muted); }

        /* ── How It Works ─────────────────────────────── */
        .steps-section {
            padding: 96px 0;
            background: var(--bg-2);
            border-top: 1px solid var(--border);
        }
        .steps-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
            margin-top: 60px;
        }
        .step-item {
            padding: 32px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--r-2xl);
            transition: all 0.3s var(--ease);
            position: relative;
        }
        .step-item:hover {
            border-color: var(--border-2);
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            transform: translateY(-2px);
        }
        .step-num {
            font-size: 13px;
            font-weight: 700;
            color: var(--ink-faint);
            letter-spacing: -0.01em;
            margin-bottom: 20px;
        }
        .step-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--ink);
            letter-spacing: -0.03em;
            margin-bottom: 10px;
        }
        .step-desc {
            font-size: 14px;
            color: var(--ink-muted);
            line-height: 1.65;
            letter-spacing: -0.01em;
        }

        /* ── Testimonials ─────────────────────────────── */
        .testimonials-section {
            padding: 96px 0;
            overflow: hidden;
        }
        .testimonials-track {
            display: flex;
            gap: 16px;
            margin-top: 48px;
            overflow-x: auto;
            scrollbar-width: none;
            padding-bottom: 4px;
        }
        .testimonials-track::-webkit-scrollbar { display: none; }
        .testimonial-card {
            flex-shrink: 0;
            width: 320px;
            background: var(--bg-2);
            border: 1px solid var(--border);
            border-radius: var(--r-xl);
            padding: 24px;
            transition: all 0.3s var(--ease);
        }
        .testimonial-card:hover {
            border-color: var(--border-2);
            transform: translateY(-2px);
        }
        .t-stars {
            display: flex; gap: 2px; margin-bottom: 14px;
        }
        .t-stars i { color: #F59E0B; font-size: 14px; }
        .t-quote {
            font-size: 14px;
            color: var(--ink-2);
            line-height: 1.65;
            margin-bottom: 18px;
            letter-spacing: -0.01em;
        }
        .t-author {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .t-avatar-img {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: var(--border-2);
            display: flex; align-items: center; justify-content: center;
            font-size: 12px;
            font-weight: 700;
            color: var(--ink);
            flex-shrink: 0;
        }
        .t-author-name { font-size: 13px; font-weight: 600; color: var(--ink); letter-spacing: -0.02em; }
        .t-author-role { font-size: 12px; color: var(--ink-faint); }

        /* ── CTA Section ──────────────────────────────── */
        .cta-section {
            padding: 96px 0;
            background: var(--ink);
            position: relative;
            overflow: hidden;
        }
        [data-theme="dark"] .cta-section { background: var(--bg-2); border-top: 1px solid var(--border); }
        .cta-bg {
            position: absolute;
            inset: 0;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black 40%, transparent 100%);
        }
        .cta-content {
            position: relative;
            z-index: 1;
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
        }
        .cta-title {
            font-size: clamp(30px, 5vw, 52px);
            font-weight: 700;
            color: #FFFFFF;
            letter-spacing: -0.04em;
            line-height: 1.1;
            margin-bottom: 16px;
        }
        [data-theme="dark"] .cta-title { color: var(--ink); }
        .cta-desc {
            font-size: 16px;
            color: rgba(255,255,255,0.6);
            margin-bottom: 36px;
            letter-spacing: -0.01em;
        }
        [data-theme="dark"] .cta-desc { color: var(--ink-muted); }
        .btn-cta-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 13px 28px;
            background: #FFFFFF;
            color: #000000;
            border-radius: var(--r-md);
            font-size: 15px;
            font-weight: 600;
            letter-spacing: -0.02em;
            transition: all var(--dur) var(--ease);
        }
        .btn-cta-primary:hover { opacity: 0.9; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.3); }
        [data-theme="dark"] .btn-cta-primary { background: var(--ink); color: var(--bg); }

        /* ── Footer ───────────────────────────────────── */
        .footer {
            background: var(--bg-2);
            border-top: 1px solid var(--border);
            padding: 64px 0 32px;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 48px;
        }
        .footer-brand-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }
        .footer-logo-mark {
            width: 30px; height: 30px;
            background: var(--ink);
            border-radius: var(--r);
            display: flex; align-items: center; justify-content: center;
            color: var(--bg);
            font-size: 15px;
        }
        .footer-logo-text {
            font-size: 15px;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: -0.04em;
        }
        .footer-brand-desc {
            font-size: 13.5px;
            color: var(--ink-faint);
            line-height: 1.65;
            margin-bottom: 20px;
            letter-spacing: -0.01em;
        }
        .footer-social {
            display: flex; gap: 8px;
        }
        .footer-social a {
            width: 32px; height: 32px;
            border-radius: var(--r);
            border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            color: var(--ink-muted);
            font-size: 16px;
            transition: all var(--dur);
        }
        .footer-social a:hover { border-color: var(--border-2); color: var(--ink); background: var(--bg-3); }
        .footer-col-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--ink);
            letter-spacing: -0.02em;
            margin-bottom: 16px;
        }
        .footer-links { list-style: none; }
        .footer-links li { margin-bottom: 10px; }
        .footer-links a {
            font-size: 13.5px;
            color: var(--ink-faint);
            transition: color var(--dur);
            letter-spacing: -0.01em;
        }
        .footer-links a:hover { color: var(--ink); }
        .footer-bottom {
            border-top: 1px solid var(--border);
            padding-top: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .footer-copy {
            font-size: 13px;
            color: var(--ink-faint);
            letter-spacing: -0.01em;
        }
        .footer-bottom-links {
            display: flex;
            gap: 20px;
        }
        .footer-bottom-links a {
            font-size: 13px;
            color: var(--ink-faint);
            transition: color var(--dur);
            letter-spacing: -0.01em;
        }
        .footer-bottom-links a:hover { color: var(--ink); }

        /* ── Login Modal ─────────────────────────────── */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(8px);
            z-index: 999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-backdrop.open { display: flex; }
        .login-modal {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--r-2xl);
            padding: 36px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 32px 64px rgba(0,0,0,0.25);
            animation: modalIn 0.25s var(--ease) forwards;
            position: relative;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.96) translateY(8px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal-close {
            position: absolute;
            top: 16px; right: 16px;
            width: 30px; height: 30px;
            border-radius: 50%;
            border: 1px solid var(--border);
            background: none;
            cursor: pointer;
            color: var(--ink-muted);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            transition: all var(--dur);
        }
        .modal-close:hover { background: var(--bg-3); color: var(--ink); }
        .modal-logo {
            width: 40px; height: 40px;
            background: var(--ink);
            border-radius: var(--r-md);
            display: flex; align-items: center; justify-content: center;
            color: var(--bg);
            font-size: 20px;
            margin-bottom: 20px;
        }
        .modal-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: -0.04em;
            margin-bottom: 6px;
        }
        .modal-sub {
            font-size: 14px;
            color: var(--ink-muted);
            margin-bottom: 28px;
            letter-spacing: -0.01em;
        }
        .role-tabs {
            display: flex;
            background: var(--bg-3);
            border-radius: var(--r);
            padding: 3px;
            gap: 3px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
        }
        .role-tab {
            flex: 1;
            padding: 7px;
            background: transparent;
            border: none;
            color: var(--ink-muted);
            font-size: 13px;
            font-weight: 500;
            border-radius: var(--r-sm, 6px);
            cursor: pointer;
            transition: all var(--dur) var(--ease);
            font-family: var(--font);
            letter-spacing: -0.01em;
        }
        .role-tab.active {
            background: var(--bg);
            color: var(--ink);
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        .form-field {
            margin-bottom: 16px;
        }
        .form-field label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--ink-2);
            margin-bottom: 6px;
            letter-spacing: -0.01em;
        }
        .form-field input {
            display: block;
            width: 100%;
            padding: 9px 13px;
            background: var(--bg);
            border: 1px solid var(--border-2);
            border-radius: var(--r);
            font-size: 14px;
            color: var(--ink);
            font-family: var(--font);
            outline: none;
            transition: border-color var(--dur), box-shadow var(--dur);
            letter-spacing: -0.01em;
        }
        .form-field input:focus {
            border-color: var(--ink);
            box-shadow: 0 0 0 3px rgba(0,0,0,0.06);
        }
        .btn-login {
            width: 100%;
            padding: 11px;
            background: var(--ink);
            color: var(--bg);
            border: none;
            border-radius: var(--r);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--dur) var(--ease);
            font-family: var(--font);
            letter-spacing: -0.02em;
            margin-top: 8px;
        }
        .btn-login:hover { opacity: 0.85; }
        .modal-footer-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13.5px;
            color: var(--ink-muted);
            letter-spacing: -0.01em;
        }
        .modal-footer-link a { color: var(--ink); font-weight: 600; }

        /* ── Reveal animations ────────────────────────── */
        .reveal {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s var(--ease), transform 0.6s var(--ease);
        }
        .reveal.visible { opacity: 1; transform: translateY(0); }
        .reveal-delay-1 { transition-delay: 0.1s; }
        .reveal-delay-2 { transition-delay: 0.2s; }
        .reveal-delay-3 { transition-delay: 0.3s; }

        /* ── Horizontal scroll marquee ────────────────── */
        .marquee-wrapper {
            overflow: hidden;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            background: var(--bg-2);
            padding: 14px 0;
        }
        .marquee-track {
            display: flex;
            gap: 60px;
            animation: marquee 30s linear infinite;
            white-space: nowrap;
        }
        .marquee-track:hover { animation-play-state: paused; }
        .marquee-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: var(--ink-faint);
            letter-spacing: -0.01em;
            flex-shrink: 0;
        }
        .marquee-item i { font-size: 16px; }
        @keyframes marquee {
            from { transform: translateX(0); }
            to   { transform: translateX(-50%); }
        }

        /* ── Responsive ───────────────────────────────── */
        @media (max-width: 1024px) {
            .bento-grid { grid-template-columns: repeat(2, 1fr); }
            .bento-card.large { grid-column: span 1; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .nav-links, .btn-ghost-nav { display: none; }
            .nav-mobile-menu { display: flex; }
            .hero { padding: 60px 0 80px; }
            .hero h1 { font-size: 38px; }
            .steps-grid { grid-template-columns: 1fr; }
            .bento-grid { grid-template-columns: 1fr; }
            .bento-card.large, .bento-card.tall { grid-column: span 1; grid-row: span 1; }
            .footer-grid { grid-template-columns: 1fr; }
            .preview-body { grid-template-columns: 1fr; }
            .fake-sidebar { display: none; }
            .hero-stats { flex-direction: column; border-radius: var(--r-lg); }
            .hero-stat { border-right: none; border-bottom: 1px solid var(--border); }
            .hero-stat:last-child { border-bottom: none; }
        }
    </style>
</head>
<body>

<!-- Announcement Bar -->
<div class="announcement" id="announcementBar">
    <i class='bx bx-info-circle'></i>
    New: Instant transfers are now free for all accounts. <a href="register.php">Open an account →</a>
    <button class="close-ann" onclick="document.getElementById('announcementBar').style.display='none';" aria-label="Close">
        <i class='bx bx-x'></i>
    </button>
</div>

<!-- Navigation -->
<nav class="nav">
    <div class="container nav-inner">
        <a href="index.php" class="nav-brand">
            <div class="nav-logo"><i class='bx bxs-bank'></i></div>
            <span class="nav-logo-text">Asha Bank</span>
        </a>

        <div class="nav-links">
            <a href="#features" class="nav-link">Features</a>
            <a href="#how-it-works" class="nav-link">How it works</a>
            <a href="#about" class="nav-link">About</a>
            <a href="setup.php" class="nav-link">Setup</a>
        </div>

        <div class="nav-actions">
            <button class="theme-btn" id="themeToggle" aria-label="Toggle theme">
                <i class='bx bx-moon' id="themeIcon"></i>
            </button>
            <button class="btn-nav btn-ghost-nav" onclick="openModal('login-modal')" id="signInBtn">Sign in</button>
            <a href="register.php" class="btn-nav btn-solid-nav">
                Get started <i class='bx bx-right-arrow-alt'></i>
            </a>
        </div>

        <button class="nav-mobile-menu" onclick="openModal('login-modal')" aria-label="Menu">
            <i class='bx bx-menu'></i>
        </button>
    </div>
</nav>

<!-- ═══════════════════════════════════ -->
<!-- HERO SECTION -->
<!-- ═══════════════════════════════════ -->
<section class="hero">
    <div class="hero-bg">
        <div class="hero-grid-lines"></div>
        <div class="hero-glow"></div>
    </div>
    <div class="container">
        <div class="hero-content">
            <a href="register.php" class="hero-badge reveal">
                <span class="hero-badge-dot"></span>
                Trusted by <?= number_format($totalCustomers) ?>+ customers
                <i class='bx bx-right-arrow-alt'></i>
            </a>
            <h1 class="reveal reveal-delay-1">
                Banking that<br>
                <em>actually works</em>
            </h1>
            <p class="hero-desc reveal reveal-delay-2">
                Modern digital banking for Bangladesh. Open an account in minutes, send money instantly, and manage everything from one clean dashboard.
            </p>
            <div class="hero-cta reveal reveal-delay-3">
                <a href="register.php" class="btn-hero-primary">
                    Open free account <i class='bx bx-right-arrow-alt'></i>
                </a>
                <button class="btn-hero-secondary" onclick="openModal('login-modal')">
                    Sign in <i class='bx bx-log-in'></i>
                </button>
            </div>

            <!-- Stats -->
            <div class="hero-stats reveal">
                <div class="hero-stat">
                    <div class="hero-stat-value"><?= number_format($totalCustomers) ?>+</div>
                    <div class="hero-stat-label">Customers</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-value">৳<?= round($totalDeposits / 1000000, 0) ?>M+</div>
                    <div class="hero-stat-label">In deposits</div>
                </div>
                <div class="hero-stat">
                    <div class="hero-stat-value"><?= $totalBranches ?>+</div>
                    <div class="hero-stat-label">Branches</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Marquee strip -->
<div class="marquee-wrapper">
    <div class="marquee-track">
        <!-- duplicated for seamless loop -->
        <?php $items = [
            ['bx bx-check-shield', 'Bank-grade security'],
            ['bx bx-transfer', 'Instant transfers'],
            ['bx bx-credit-card', 'Virtual cards'],
            ['bx bx-bar-chart-alt-2', 'Spending analytics'],
            ['bx bx-bell', 'Real-time alerts'],
            ['bx bx-mobile', 'Mobile banking'],
            ['bx bx-lock-open-alt', 'Zero hidden fees'],
            ['bx bx-support', '24/7 support'],
        ]; foreach(array_merge($items, $items) as $i): ?>
        <span class="marquee-item">
            <i class='<?= $i[0] ?>'></i>
            <?= $i[1] ?>
        </span>
        <?php endforeach; ?>
    </div>
</div>

<!-- ═══════════════════════════════════ -->
<!-- PREVIEW SECTION -->
<!-- ═══════════════════════════════════ -->
<section class="preview-section">
    <div class="container">
        <p class="preview-label">Your banking dashboard</p>
        <div class="preview-window reveal">
            <div class="preview-titlebar">
                <div class="preview-dot"></div>
                <div class="preview-dot"></div>
                <div class="preview-dot"></div>
                <div class="preview-url">app.ashabank.com.bd/dashboard</div>
            </div>
            <div class="preview-body">
                <!-- Fake sidebar -->
                <div class="fake-sidebar">
                    <div class="fake-sidebar-logo"></div>
                    <div class="fake-nav-item active">
                        <div class="fake-nav-dot"></div>
                        <div class="fake-nav-line"></div>
                    </div>
                    <div class="fake-nav-item">
                        <div class="fake-nav-dot"></div>
                        <div class="fake-nav-line"></div>
                    </div>
                    <div class="fake-nav-item">
                        <div class="fake-nav-dot"></div>
                        <div class="fake-nav-line"></div>
                    </div>
                    <div class="fake-nav-item">
                        <div class="fake-nav-dot"></div>
                        <div class="fake-nav-line"></div>
                    </div>
                </div>
                <!-- Fake main -->
                <div class="fake-main">
                    <div class="fake-stats">
                        <div class="fake-stat-card">
                            <div class="fake-stat-label"></div>
                            <div class="fake-stat-value"></div>
                        </div>
                        <div class="fake-stat-card">
                            <div class="fake-stat-label"></div>
                            <div class="fake-stat-value"></div>
                        </div>
                        <div class="fake-stat-card">
                            <div class="fake-stat-label"></div>
                            <div class="fake-stat-value"></div>
                        </div>
                    </div>
                    <div class="fake-card-row">
                        <div class="fake-card">
                            <div class="fake-line w-5" style="margin-bottom:12px;"></div>
                            <div class="fake-line w-7"></div>
                            <div class="fake-line w-3" style="margin-top:6px;"></div>
                        </div>
                        <div class="fake-card">
                            <div class="fake-line w-5" style="margin-bottom:12px;"></div>
                            <div class="fake-line w-full"></div>
                            <div class="fake-line w-full" style="margin-top:6px;"></div>
                            <div class="fake-line w-full" style="margin-top:6px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════ -->
<!-- FEATURES BENTO GRID -->
<!-- ═══════════════════════════════════ -->
<section class="section" id="features">
    <div class="container">
        <div class="reveal">
            <div class="section-tag"><i class='bx bx-sparkles'></i> Features</div>
            <h2 class="section-title">Everything you need,<br>nothing you don't.</h2>
            <p class="section-desc">A clean, powerful banking platform built for modern Bangladesh.</p>
        </div>

        <div class="bento-grid">
            <!-- Card 1: Large - Virtual card -->
            <div class="bento-card large reveal">
                <div class="bento-icon"><i class='bx bx-credit-card'></i></div>
                <h3 class="bento-title">Virtual & Physical Cards</h3>
                <p class="bento-desc">Get your card instantly. Freeze, unfreeze, and set spending limits from your dashboard.</p>
                <div class="mini-bank-card">
                    <div class="mini-card-label">Available Balance</div>
                    <div class="mini-card-balance">৳ 42,850.00</div>
                    <div class="mini-card-num" style="margin-top:auto;padding-top:16px;">•••• •••• •••• 4291</div>
                </div>
            </div>

            <!-- Card 2: Instant transfer -->
            <div class="bento-card reveal reveal-delay-1">
                <div class="bento-icon"><i class='bx bx-transfer'></i></div>
                <h3 class="bento-title">Instant Transfers</h3>
                <p class="bento-desc">Send money to anyone, anywhere in Bangladesh in seconds.</p>
                <div class="transfer-ui">
                    <div class="transfer-row">
                        <div class="t-avatar">R</div>
                        <div class="t-name">Rahim Ahmed</div>
                        <div class="t-amount out">−৳500</div>
                    </div>
                    <div class="transfer-row">
                        <div class="t-avatar">S</div>
                        <div class="t-name">Salary Credit</div>
                        <div class="t-amount in">+৳45,000</div>
                    </div>
                </div>
            </div>

            <!-- Card 3: Security -->
            <div class="bento-card reveal reveal-delay-2">
                <div class="bento-icon"><i class='bx bx-shield-quarter'></i></div>
                <h3 class="bento-title">Bank-grade Security</h3>
                <p class="bento-desc">256-bit encryption, 2FA, and real-time fraud detection protecting your account 24/7.</p>
            </div>

            <!-- Card 4: Analytics -->
            <div class="bento-card reveal reveal-delay-1">
                <div class="bento-icon"><i class='bx bx-bar-chart-alt-2'></i></div>
                <h3 class="bento-title">Spending Insights</h3>
                <p class="bento-desc">See where your money goes with automatic categorization and visual reports.</p>
            </div>

            <!-- Card 5: Notifications -->
            <div class="bento-card reveal reveal-delay-2">
                <div class="bento-icon"><i class='bx bx-bell'></i></div>
                <h3 class="bento-title">Real-time Alerts</h3>
                <p class="bento-desc">Get notified instantly for every transaction. Never miss a payment or suspicious activity.</p>
            </div>

            <!-- Card 6: Support -->
            <div class="bento-card reveal reveal-delay-3">
                <div class="bento-icon"><i class='bx bx-support'></i></div>
                <h3 class="bento-title">24/7 Support</h3>
                <p class="bento-desc">Our team is always available to help. Live chat, phone, and email support around the clock.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════ -->
<!-- HOW IT WORKS -->
<!-- ═══════════════════════════════════ -->
<section class="steps-section" id="how-it-works">
    <div class="container">
        <div class="reveal" style="text-align:center;">
            <div class="section-tag" style="margin-bottom:16px;"><i class='bx bx-info-circle'></i> How it works</div>
            <h2 class="section-title">Open your account<br>in three steps</h2>
        </div>

        <div class="steps-grid">
            <div class="step-item reveal">
                <div class="step-num">01</div>
                <h3 class="step-title">Create an account</h3>
                <p class="step-desc">Fill in your details online in under 3 minutes. No branch visit required for basic accounts.</p>
            </div>
            <div class="step-item reveal reveal-delay-1">
                <div class="step-num">02</div>
                <h3 class="step-title">Verify your identity</h3>
                <p class="step-desc">Upload your NID and a selfie. Our team reviews it within 24 hours to keep you secure.</p>
            </div>
            <div class="step-item reveal reveal-delay-2">
                <div class="step-num">03</div>
                <h3 class="step-title">Start banking</h3>
                <p class="step-desc">Deposit funds and start sending money, paying bills, and managing your finances instantly.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════ -->
<!-- TESTIMONIALS -->
<!-- ═══════════════════════════════════ -->
<section class="testimonials-section" id="about">
    <div class="container">
        <div class="reveal">
            <div class="section-tag"><i class='bx bx-user-voice'></i> Testimonials</div>
            <h2 class="section-title">Loved by customers<br>across Bangladesh</h2>
        </div>
    </div>
    <div class="container">
        <div class="testimonials-track">
            <?php
            $testimonials = [
                ['name' => 'Farhan Islam', 'role' => 'Entrepreneur, Dhaka', 'quote' => 'Switching to Asha Bank was the best financial decision I made. The dashboard is beautiful and transfers are truly instant.'],
                ['name' => 'Nasrin Akter', 'role' => 'Teacher, Chittagong', 'quote' => 'I love how clean the interface is. No clutter, just what I need. Customer support is also excellent.'],
                ['name' => 'Rafiqul Hasan', 'role' => 'Software Engineer', 'quote' => 'The spending analytics feature helped me save 20% more every month. Absolutely love this bank.'],
                ['name' => 'Mitu Begum', 'role' => 'Small Business Owner', 'quote' => 'Finally a bank that feels modern. The mobile experience is seamless and the fees are transparent.'],
                ['name' => 'Jahangir Alam', 'role' => 'Freelancer, Sylhet', 'quote' => 'Receiving international payments and managing my finances has never been this easy. Highly recommend.'],
            ];
            foreach($testimonials as $t): ?>
            <div class="testimonial-card">
                <div class="t-stars">
                    <i class='bx bxs-star'></i>
                    <i class='bx bxs-star'></i>
                    <i class='bx bxs-star'></i>
                    <i class='bx bxs-star'></i>
                    <i class='bx bxs-star'></i>
                </div>
                <p class="t-quote">"<?= htmlspecialchars($t['quote']) ?>"</p>
                <div class="t-author">
                    <div class="t-avatar-img"><?= strtoupper(substr($t['name'], 0, 1)) ?></div>
                    <div>
                        <div class="t-author-name"><?= htmlspecialchars($t['name']) ?></div>
                        <div class="t-author-role"><?= htmlspecialchars($t['role']) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════ -->
<!-- CTA SECTION -->
<!-- ═══════════════════════════════════ -->
<section class="cta-section">
    <div class="cta-bg"></div>
    <div class="container">
        <div class="cta-content reveal">
            <h2 class="cta-title">Ready to bank<br>smarter?</h2>
            <p class="cta-desc">Join <?= number_format($totalCustomers) ?>+ customers who trust Asha Bank for their daily banking needs.</p>
            <a href="register.php" class="btn-cta-primary">
                Open free account <i class='bx bx-right-arrow-alt'></i>
            </a>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════ -->
<!-- FOOTER -->
<!-- ═══════════════════════════════════ -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <div class="footer-brand-logo">
                    <div class="footer-logo-mark"><i class='bx bxs-bank'></i></div>
                    <span class="footer-logo-text">Asha Bank</span>
                </div>
                <p class="footer-brand-desc">
                    A premier scheduled commercial bank in Bangladesh. Regulated by Bangladesh Bank.
                </p>
                <div class="footer-social">
                    <a href="#" aria-label="Facebook"><i class='bx bxl-facebook'></i></a>
                    <a href="#" aria-label="Twitter"><i class='bx bxl-twitter'></i></a>
                    <a href="#" aria-label="LinkedIn"><i class='bx bxl-linkedin'></i></a>
                    <a href="#" aria-label="Instagram"><i class='bx bxl-instagram'></i></a>
                </div>
            </div>
            <div>
                <div class="footer-col-title">Products</div>
                <ul class="footer-links">
                    <li><a href="register.php">Savings Account</a></li>
                    <li><a href="register.php">Current Account</a></li>
                    <li><a href="register.php">Fixed Deposits</a></li>
                    <li><a href="register.php">Cards</a></li>
                    <li><a href="register.php">Loans</a></li>
                </ul>
            </div>
            <div>
                <div class="footer-col-title">Company</div>
                <ul class="footer-links">
                    <li><a href="#about">About</a></li>
                    <li><a href="#how-it-works">How it works</a></li>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Press</a></li>
                </ul>
            </div>
            <div>
                <div class="footer-col-title">Support</div>
                <ul class="footer-links">
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Contact Us</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                    <li><a href="setup.php">DB Setup</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <span class="footer-copy">© <?= date('Y') ?> Asha Bank PLC. All rights reserved. SWIFT: ASHABDDH</span>
            <div class="footer-bottom-links">
                <a href="#">Privacy</a>
                <a href="#">Terms</a>
                <a href="#">Cookies</a>
            </div>
        </div>
    </div>
</footer>

<!-- ═══════════════════════════════════ -->
<!-- LOGIN MODAL -->
<!-- ═══════════════════════════════════ -->
<div class="modal-backdrop" id="login-modal" onclick="closeModal(event)">
    <div class="login-modal">
        <button class="modal-close" onclick="closeModalDirect()" aria-label="Close">
            <i class='bx bx-x'></i>
        </button>

        <div class="modal-logo"><i class='bx bxs-bank'></i></div>
        <h2 class="modal-title">Welcome back</h2>
        <p class="modal-sub">Sign in to your Asha Bank account</p>

        <!-- Role tabs -->
        <div class="role-tabs" id="roleTabs">
            <button class="role-tab active" onclick="setRole(this, 'client')">Customer</button>
            <button class="role-tab" onclick="setRole(this, 'staff')">Staff</button>
            <button class="role-tab" onclick="setRole(this, 'admin')">Admin</button>
        </div>

        <form method="POST" action="login.php" data-loading>
            <input type="hidden" name="<?= csrf_field() ?>" value="<?= csrf_token() ?>">
            <input type="hidden" name="role" id="modalRole" value="client">

            <div class="form-field">
                <label>Username or Email</label>
                <input type="text" name="login" placeholder="your@email.com" required autocomplete="username">
            </div>
            <div class="form-field">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-login">
                Sign in <i class='bx bx-right-arrow-alt'></i>
            </button>
        </form>

        <div class="modal-footer-link">
            Don't have an account? <a href="register.php">Create one →</a>
        </div>
    </div>
</div>

<script>
// ── Theme ──────────────────────────────────────────────────────
const THEME_KEY = 'asha-theme';
const html = document.documentElement;
const icon = document.getElementById('themeIcon');

function applyTheme(t) {
    html.setAttribute('data-theme', t);
    localStorage.setItem(THEME_KEY, t);
    if (icon) icon.className = t === 'dark' ? 'bx bx-sun' : 'bx bx-moon';
}

// Load saved
applyTheme(localStorage.getItem(THEME_KEY) || 'light');

document.getElementById('themeToggle')?.addEventListener('click', () => {
    applyTheme(html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
});

// ── Modal ──────────────────────────────────────────────────────
function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(e) {
    if (e.target === e.currentTarget) closeModalDirect();
}
function closeModalDirect() {
    document.querySelectorAll('.modal-backdrop').forEach(m => m.classList.remove('open'));
    document.body.style.overflow = '';
}
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModalDirect(); });

// ── Role tabs ──────────────────────────────────────────────────
function setRole(btn, role) {
    document.querySelectorAll('#roleTabs .role-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('modalRole').value = role;
}

// ── Scroll reveal ──────────────────────────────────────────────
const reveals = document.querySelectorAll('.reveal');
const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) { e.target.classList.add('visible'); observer.unobserve(e.target); }
    });
}, { threshold: 0.1 });
reveals.forEach(el => observer.observe(el));

// ── Helper for CSRF ────────────────────────────────────────────
</script>

<?php
// Helper functions for CSRF token in this page
function csrf_field() { return 'csrf_token'; }
function csrf_token() { return $_SESSION['csrf_token'] ?? ''; }
?>

</body>
</html>
