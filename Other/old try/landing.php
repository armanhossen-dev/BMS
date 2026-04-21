<?php
require_once 'config/db.php';
// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apex Bank – Where Wealth Meets Wisdom</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400&family=Jost:wght@200;300;400;500;600&display=swap" rel="stylesheet">
    <style>
        /* ── RESET & BASE ─────────────────────────────────── */
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        html { scroll-behavior: smooth; font-size:16px; }

        :root {
            --gold:       #C9A84C;
            --gold-light: #E8C97A;
            --gold-dark:  #9A7B2F;
            --gold-dim:   rgba(201,168,76,0.15);
            --gold-line:  rgba(201,168,76,0.25);
            --black:      #080808;
            --black-2:    #0E0E0E;
            --black-3:    #141414;
            --black-4:    #1C1C1C;
            --black-5:    #242424;
            --white:      #F8F4EC;
            --white-dim:  rgba(248,244,236,0.7);
            --white-faint:rgba(248,244,236,0.12);
        }

        body {
            background: var(--black);
            color: var(--white);
            font-family: 'Jost', sans-serif;
            font-weight: 300;
            overflow-x: hidden;
        }

        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-track { background: var(--black); }
        ::-webkit-scrollbar-thumb { background: var(--gold-dark); }

        /* ── NAV ──────────────────────────────────────────── */
        nav {
            position: fixed; top:0; left:0; right:0; z-index:1000;
            padding: 20px 60px;
            display: flex; align-items:center; justify-content:space-between;
            transition: all 0.4s ease;
        }

        nav.scrolled {
            background: rgba(8,8,8,0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--gold-line);
            padding: 14px 60px;
        }

        .nav-logo {
            display: flex; align-items:center; gap:12px;
            text-decoration: none;
        }

        .nav-logo-mark {
            width:40px; height:40px;
            border: 1px solid var(--gold);
            border-radius:8px;
            display:flex; align-items:center; justify-content:center;
            font-family:'Cormorant Garamond', serif;
            font-size:1.3rem; font-weight:600;
            color: var(--gold);
            position:relative;
        }

        .nav-logo-mark::before {
            content:'';
            position:absolute; inset:3px;
            border:1px solid rgba(201,168,76,0.3);
            border-radius:5px;
        }

        .nav-logo-text {
            font-family:'Cormorant Garamond', serif;
            font-size:1.2rem; font-weight:600;
            letter-spacing:0.15em;
            color: var(--white);
        }

        .nav-logo-text span { color: var(--gold); }

        .nav-links {
            display:flex; gap:36px; list-style:none;
        }

        .nav-links a {
            text-decoration:none;
            font-size:0.78rem; font-weight:400;
            letter-spacing:0.12em; text-transform:uppercase;
            color: var(--white-dim);
            transition: color 0.3s;
        }

        .nav-links a:hover { color: var(--gold); }

        .nav-actions { display:flex; gap:12px; align-items:center; }

        .btn-nav-outline {
            padding:9px 22px;
            border:1px solid var(--gold-line);
            background:transparent;
            color: var(--gold);
            font-family:'Jost',sans-serif;
            font-size:0.75rem; font-weight:400;
            letter-spacing:0.12em; text-transform:uppercase;
            text-decoration:none;
            border-radius:3px;
            transition: all 0.3s;
            cursor:pointer;
        }

        .btn-nav-outline:hover {
            background: var(--gold-dim);
            border-color: var(--gold);
        }

        .btn-nav-solid {
            padding:9px 22px;
            border:1px solid var(--gold);
            background: linear-gradient(135deg, var(--gold-dark), var(--gold));
            color: var(--black);
            font-family:'Jost',sans-serif;
            font-size:0.75rem; font-weight:600;
            letter-spacing:0.12em; text-transform:uppercase;
            text-decoration:none;
            border-radius:3px;
            transition: all 0.3s;
        }

        .btn-nav-solid:hover {
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            box-shadow: 0 4px 20px rgba(201,168,76,0.3);
            transform:translateY(-1px);
        }

        .nav-hamburger { display:none; background:none; border:none; cursor:pointer; }
        .nav-hamburger span {
            display:block; width:22px; height:1px;
            background:var(--gold); margin:5px 0;
            transition:all 0.3s;
        }

        /* ── HERO ─────────────────────────────────────────── */
        .hero {
            min-height:100vh;
            display:flex; align-items:center; justify-content:center;
            position:relative; overflow:hidden;
            padding: 140px 60px 80px;
        }

        /* Geometric background */
        .hero-bg {
            position:absolute; inset:0;
            background: var(--black);
            overflow:hidden;
        }

        .hero-bg::before {
            content:'';
            position:absolute;
            width:800px; height:800px;
            border-radius:50%;
            background: radial-gradient(circle, rgba(201,168,76,0.06) 0%, transparent 65%);
            top:-200px; right:-200px;
        }

        .hero-bg::after {
            content:'';
            position:absolute;
            width:600px; height:600px;
            border-radius:50%;
            background: radial-gradient(circle, rgba(201,168,76,0.04) 0%, transparent 65%);
            bottom:-100px; left:-100px;
        }

        /* Diagonal gold line accents */
        .hero-lines {
            position:absolute; inset:0; overflow:hidden; pointer-events:none;
        }

        .hero-lines::before {
            content:'';
            position:absolute;
            top:-100px; left:50%;
            width:1px; height:120vh;
            background: linear-gradient(to bottom, transparent, rgba(201,168,76,0.15), transparent);
            transform: rotate(15deg) translateX(-300px);
        }

        .hero-lines::after {
            content:'';
            position:absolute;
            top:-100px; left:50%;
            width:1px; height:120vh;
            background: linear-gradient(to bottom, transparent, rgba(201,168,76,0.08), transparent);
            transform: rotate(15deg) translateX(200px);
        }

        /* Floating grid dots */
        .hero-dots {
            position:absolute; inset:0; pointer-events:none;
            background-image:
                radial-gradient(circle, rgba(201,168,76,0.12) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(ellipse 70% 70% at 50% 50%, black, transparent);
        }

        .hero-content {
            position:relative; z-index:1;
            max-width:1200px; width:100%;
            display:grid; grid-template-columns:1fr 1fr;
            gap:80px; align-items:center;
        }

        .hero-eyebrow {
            display:inline-flex; align-items:center; gap:10px;
            font-size:0.68rem; font-weight:500;
            letter-spacing:0.25em; text-transform:uppercase;
            color: var(--gold);
            margin-bottom:24px;
        }

        .hero-eyebrow::before {
            content:''; width:30px; height:1px; background:var(--gold);
        }

        .hero-title {
            font-family:'Cormorant Garamond', serif;
            font-size:clamp(3rem, 5vw, 5rem);
            font-weight:600; line-height:1.05;
            color: var(--white);
            margin-bottom:24px;
        }

        .hero-title em {
            font-style:italic;
            color: var(--gold);
        }

        .hero-subtitle {
            font-size:1rem; font-weight:300; line-height:1.8;
            color: var(--white-dim);
            max-width:480px;
            margin-bottom:44px;
        }

        .hero-cta {
            display:flex; gap:16px; align-items:center; flex-wrap:wrap;
        }

        .btn-primary {
            display:inline-flex; align-items:center; gap:10px;
            padding:15px 36px;
            background: linear-gradient(135deg, var(--gold-dark), var(--gold));
            color: var(--black);
            font-family:'Jost',sans-serif;
            font-size:0.8rem; font-weight:600;
            letter-spacing:0.15em; text-transform:uppercase;
            text-decoration:none; border:none;
            border-radius:3px; cursor:pointer;
            transition: all 0.3s;
            position:relative; overflow:hidden;
        }

        .btn-primary::after {
            content:''; position:absolute; inset:0;
            background: linear-gradient(135deg, transparent, rgba(255,255,255,0.15), transparent);
            transform:translateX(-100%);
            transition: transform 0.4s;
        }

        .btn-primary:hover::after { transform:translateX(100%); }

        .btn-primary:hover {
            box-shadow: 0 8px 30px rgba(201,168,76,0.4);
            transform:translateY(-2px);
        }

        .btn-secondary {
            display:inline-flex; align-items:center; gap:10px;
            padding:15px 36px;
            background: transparent;
            color: var(--white-dim);
            font-family:'Jost',sans-serif;
            font-size:0.8rem; font-weight:400;
            letter-spacing:0.12em; text-transform:uppercase;
            text-decoration:none; border:1px solid rgba(248,244,236,0.2);
            border-radius:3px; cursor:pointer;
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            color:var(--white);
            border-color:rgba(248,244,236,0.5);
            background:rgba(248,244,236,0.04);
        }

        /* HERO STATS BAR */
        .hero-stats {
            display:flex; gap:36px; margin-top:56px;
            padding-top:36px;
            border-top: 1px solid var(--gold-line);
        }

        .hero-stat-item { text-align:left; }

        .hero-stat-num {
            font-family:'Cormorant Garamond', serif;
            font-size:2rem; font-weight:600;
            color: var(--gold);
            line-height:1;
        }

        .hero-stat-label {
            font-size:0.7rem; font-weight:400;
            letter-spacing:0.1em; text-transform:uppercase;
            color: var(--white-dim);
            margin-top:6px;
        }

        /* HERO RIGHT: CARD VISUAL */
        .hero-visual {
            position:relative;
            animation: floatCard 6s ease-in-out infinite;
        }

        @keyframes floatCard {
            0%,100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-16px) rotate(0.5deg); }
        }

        .bank-card-3d {
            width:100%; max-width:420px;
            aspect-ratio: 1.586/1;
            background: linear-gradient(135deg, #1A1510 0%, #2C2210 40%, #1A1510 100%);
            border-radius:18px;
            border:1px solid rgba(201,168,76,0.35);
            padding:30px;
            position:relative; overflow:hidden;
            box-shadow:
                0 40px 80px rgba(0,0,0,0.7),
                0 0 0 1px rgba(201,168,76,0.1),
                inset 0 1px 0 rgba(201,168,76,0.2);
            margin:0 auto;
        }

        .bank-card-3d::before {
            content:'';
            position:absolute;
            width:300px; height:300px;
            border-radius:50%;
            background: radial-gradient(circle, rgba(201,168,76,0.12), transparent 70%);
            top:-80px; right:-80px;
        }

        .bank-card-3d::after {
            content:'◆';
            position:absolute;
            bottom:-20px; right:20px;
            font-size:120px;
            color:rgba(201,168,76,0.04);
            line-height:1;
        }

        .card-chip {
            width:44px; height:34px;
            background: linear-gradient(135deg, var(--gold-dark), var(--gold));
            border-radius:6px; margin-bottom:24px;
            position:relative; overflow:hidden;
        }

        .card-chip::before {
            content:''; position:absolute;
            top:50%; left:0; right:0; height:1px;
            background:rgba(0,0,0,0.3);
            transform:translateY(-50%);
        }

        .card-chip::after {
            content:''; position:absolute;
            left:50%; top:0; bottom:0; width:1px;
            background:rgba(0,0,0,0.3);
            transform:translateX(-50%);
        }

        .card-number {
            font-family:'Cormorant Garamond', serif;
            font-size:1.4rem; font-weight:300;
            letter-spacing:0.25em;
            color: rgba(248,244,236,0.9);
            margin-bottom:28px;
        }

        .card-bottom {
            display:flex; justify-content:space-between; align-items:flex-end;
        }

        .card-holder { font-size:0.7rem; letter-spacing:0.15em; text-transform:uppercase; color:rgba(248,244,236,0.5); margin-bottom:4px; }
        .card-name { font-size:0.9rem; letter-spacing:0.08em; color:rgba(248,244,236,0.9); }
        .card-logo {
            font-family:'Cormorant Garamond', serif;
            font-size:1.4rem; font-weight:600;
            color: var(--gold);
            letter-spacing:0.1em;
        }

        /* Floating mini cards behind */
        .card-ghost {
            position:absolute;
            width:85%; max-width:360px;
            aspect-ratio:1.586/1;
            border-radius:16px;
            border:1px solid rgba(201,168,76,0.15);
            background:linear-gradient(135deg, #12100A, #1A1510);
            left:50%; transform:translateX(-50%);
        }

        .card-ghost-1 { top:20px; rotate:-5deg; z-index:-1; }
        .card-ghost-2 { top:36px; rotate:-10deg; z-index:-2; opacity:0.5; }

        /* ── MARQUEE STRIP ───────────────────────────────── */
        .marquee-strip {
            padding:16px 0;
            background: var(--gold-dim);
            border-top:1px solid var(--gold-line);
            border-bottom:1px solid var(--gold-line);
            overflow:hidden; white-space:nowrap;
        }

        .marquee-inner {
            display:inline-block;
            animation: marquee 20s linear infinite;
        }

        .marquee-item {
            display:inline-flex; align-items:center; gap:10px;
            font-size:0.72rem; letter-spacing:0.15em;
            text-transform:uppercase; color:var(--gold);
            padding:0 32px;
        }

        .marquee-item::before { content:'◆'; font-size:0.5rem; }

        @keyframes marquee {
            0%   { transform:translateX(0); }
            100% { transform:translateX(-50%); }
        }

        /* ── FEATURES SECTION ────────────────────────────── */
        section { padding:100px 60px; }

        .section-eyebrow {
            display:inline-flex; align-items:center; gap:10px;
            font-size:0.68rem; font-weight:500;
            letter-spacing:0.25em; text-transform:uppercase;
            color: var(--gold); margin-bottom:16px;
        }

        .section-eyebrow::before { content:''; width:24px; height:1px; background:var(--gold); }

        .section-title {
            font-family:'Cormorant Garamond', serif;
            font-size:clamp(2rem,3.5vw,3.2rem);
            font-weight:600; line-height:1.2;
            color:var(--white); margin-bottom:16px;
        }

        .section-subtitle {
            font-size:0.95rem; font-weight:300; line-height:1.8;
            color:var(--white-dim); max-width:560px;
        }

        .features-grid {
            display:grid; grid-template-columns:repeat(3,1fr); gap:2px;
            margin-top:60px; border:1px solid var(--gold-line);
        }

        .feature-item {
            padding:44px 36px;
            background:var(--black-2);
            border:1px solid transparent;
            transition:all 0.4s;
            position:relative; overflow:hidden;
        }

        .feature-item:hover {
            background:var(--black-3);
            border-color:var(--gold-line);
            z-index:1;
        }

        .feature-item::before {
            content:'';
            position:absolute; top:0; left:0; right:0;
            height:2px;
            background:linear-gradient(90deg, transparent, var(--gold), transparent);
            transform:scaleX(0);
            transition:transform 0.4s;
        }

        .feature-item:hover::before { transform:scaleX(1); }

        .feature-icon {
            width:52px; height:52px;
            border:1px solid var(--gold-line);
            border-radius:10px;
            display:flex; align-items:center; justify-content:center;
            font-size:1.4rem; margin-bottom:24px;
            background:var(--gold-dim);
            transition:all 0.3s;
        }

        .feature-item:hover .feature-icon {
            background:rgba(201,168,76,0.2);
            border-color:var(--gold);
        }

        .feature-title {
            font-family:'Cormorant Garamond', serif;
            font-size:1.3rem; font-weight:600;
            color:var(--white); margin-bottom:12px;
        }

        .feature-desc {
            font-size:0.85rem; font-weight:300; line-height:1.8;
            color:var(--white-dim);
        }

        /* ── HOW IT WORKS ────────────────────────────────── */
        .hiw-section { background:var(--black-2); }

        .hiw-grid {
            display:grid; grid-template-columns:1fr 1fr; gap:80px;
            align-items:center; max-width:1200px; margin:0 auto;
        }

        .steps-list { margin-top:48px; }

        .step-item {
            display:flex; gap:24px; padding:24px 0;
            border-bottom:1px solid rgba(201,168,76,0.08);
        }

        .step-item:last-child { border-bottom:none; }

        .step-num {
            font-family:'Cormorant Garamond', serif;
            font-size:2.5rem; font-weight:600;
            color:var(--gold-dark); line-height:1;
            flex-shrink:0; width:48px;
            opacity:0.4;
        }

        .step-content h4 {
            font-family:'Cormorant Garamond', serif;
            font-size:1.2rem; font-weight:600;
            color:var(--white); margin-bottom:6px;
        }

        .step-content p { font-size:0.85rem; color:var(--white-dim); line-height:1.7; }

        /* Screen mock */
        .screen-mock {
            background:var(--black-3);
            border:1px solid var(--gold-line);
            border-radius:14px; overflow:hidden;
            box-shadow:0 30px 60px rgba(0,0,0,0.5);
        }

        .screen-topbar {
            background:var(--black-4);
            padding:14px 20px;
            display:flex; align-items:center; gap:10px;
            border-bottom:1px solid var(--gold-line);
        }

        .screen-dot { width:10px; height:10px; border-radius:50%; }

        .screen-body { padding:24px; }

        .screen-stat-row {
            display:grid; grid-template-columns:repeat(2,1fr); gap:12px;
            margin-bottom:16px;
        }

        .screen-stat {
            background:var(--black-4);
            border:1px solid var(--gold-line);
            border-radius:8px; padding:14px;
        }

        .screen-stat .label { font-size:0.65rem; color:#666; text-transform:uppercase; letter-spacing:0.1em; margin-bottom:4px; }
        .screen-stat .val { font-family:'Cormorant Garamond', serif; font-size:1.1rem; color:var(--gold); }

        .screen-bar-row { margin-top:8px; }
        .screen-bar { height:6px; background:var(--black-5); border-radius:6px; margin-bottom:8px; overflow:hidden; }
        .screen-bar-fill { height:100%; border-radius:6px; background:linear-gradient(90deg,var(--gold-dark),var(--gold)); }

        /* ── RATES TABLE ─────────────────────────────────── */
        .rates-section { text-align:center; }

        .rates-header { max-width:600px; margin:0 auto 60px; }

        .rates-grid {
            display:grid; grid-template-columns:repeat(4,1fr); gap:20px;
            max-width:1100px; margin:0 auto;
        }

        .rate-card {
            border:1px solid var(--gold-line);
            border-radius:10px; overflow:hidden;
            background:var(--black-2);
            transition:all 0.4s;
        }

        .rate-card:hover {
            border-color:var(--gold);
            box-shadow:0 8px 40px rgba(201,168,76,0.12);
            transform:translateY(-4px);
        }

        .rate-card-header {
            background:var(--gold-dim);
            padding:20px; text-align:left;
            border-bottom:1px solid var(--gold-line);
        }

        .rate-card-header .type {
            font-size:0.65rem; letter-spacing:0.2em; text-transform:uppercase;
            color:var(--gold); margin-bottom:4px;
        }

        .rate-card-header .name {
            font-family:'Cormorant Garamond', serif;
            font-size:1.2rem; font-weight:600; color:var(--white);
        }

        .rate-card-body { padding:20px; }

        .rate-big {
            font-family:'Cormorant Garamond', serif;
            font-size:2.8rem; font-weight:600;
            color:var(--gold); line-height:1;
        }

        .rate-big span { font-size:1.2rem; }

        .rate-label { font-size:0.72rem; color:var(--white-dim); margin-top:4px; margin-bottom:16px; }

        .rate-features { list-style:none; }

        .rate-features li {
            font-size:0.78rem; color:var(--white-dim);
            padding:7px 0; border-bottom:1px solid rgba(255,255,255,0.04);
            display:flex; align-items:center; gap:8px;
        }

        .rate-features li::before { content:'✓'; color:var(--gold); font-size:0.7rem; }
        .rate-features li:last-child { border:none; }

        /* ── TESTIMONIALS ────────────────────────────────── */
        .testimonials-section { background:var(--black-2); }

        .testimonials-grid {
            display:grid; grid-template-columns:repeat(3,1fr); gap:24px;
            max-width:1100px; margin:60px auto 0;
        }

        .testimonial-card {
            padding:32px;
            background:var(--black-3);
            border:1px solid var(--gold-line);
            border-radius:10px;
            transition:all 0.4s;
        }

        .testimonial-card:hover {
            border-color:var(--gold);
            box-shadow:0 8px 30px rgba(201,168,76,0.1);
        }

        .testimonial-stars {
            color:var(--gold); font-size:0.8rem; margin-bottom:16px; letter-spacing:3px;
        }

        .testimonial-text {
            font-size:0.88rem; font-weight:300; line-height:1.8;
            color:var(--white-dim); font-style:italic;
            margin-bottom:24px;
        }

        .testimonial-author { display:flex; align-items:center; gap:12px; }

        .author-avatar {
            width:40px; height:40px; border-radius:50%;
            background:linear-gradient(135deg,var(--gold-dark),var(--gold));
            display:flex; align-items:center; justify-content:center;
            font-family:'Cormorant Garamond', serif;
            font-weight:700; color:var(--black); font-size:1rem;
            flex-shrink:0;
        }

        .author-name { font-size:0.85rem; font-weight:500; color:var(--white); }
        .author-title { font-size:0.72rem; color:var(--gold); }

        /* ── CTA SECTION ─────────────────────────────────── */
        .cta-section {
            text-align:center;
            background:linear-gradient(135deg,#0E0C07,#1A1608,#0E0C07);
            border-top:1px solid var(--gold-line);
            border-bottom:1px solid var(--gold-line);
            position:relative; overflow:hidden;
        }

        .cta-section::before {
            content:'';
            position:absolute; inset:0;
            background:radial-gradient(ellipse 60% 80% at 50% 50%, rgba(201,168,76,0.07), transparent);
        }

        .cta-content { position:relative; z-index:1; max-width:700px; margin:0 auto; }

        .cta-title {
            font-family:'Cormorant Garamond', serif;
            font-size:clamp(2rem,4vw,3.5rem);
            font-weight:600; line-height:1.15;
            color:var(--white); margin-bottom:20px;
        }

        .cta-title em { color:var(--gold); font-style:italic; }

        .cta-sub {
            font-size:0.95rem; color:var(--white-dim); margin-bottom:40px; line-height:1.8;
        }

        .cta-btns { display:flex; gap:16px; justify-content:center; flex-wrap:wrap; }

        /* ── FOOTER ──────────────────────────────────────── */
        footer {
            padding:60px;
            background:var(--black);
            border-top:1px solid var(--gold-line);
        }

        .footer-grid {
            display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:48px;
            max-width:1200px; margin:0 auto 48px;
        }

        .footer-brand p {
            font-size:0.82rem; color:var(--white-dim); line-height:1.8;
            margin:16px 0 24px; max-width:280px;
        }

        .footer-title {
            font-size:0.68rem; letter-spacing:0.2em; text-transform:uppercase;
            color:var(--gold); margin-bottom:20px;
        }

        .footer-links { list-style:none; }

        .footer-links li { margin-bottom:10px; }

        .footer-links a {
            font-size:0.83rem; color:var(--white-dim);
            text-decoration:none; transition:color 0.3s;
        }

        .footer-links a:hover { color:var(--gold); }

        .footer-bottom {
            max-width:1200px; margin:0 auto;
            padding-top:24px; border-top:1px solid rgba(201,168,76,0.1);
            display:flex; justify-content:space-between; align-items:center;
            font-size:0.75rem; color:rgba(248,244,236,0.3);
        }

        .footer-bottom a { color:var(--gold); text-decoration:none; }

        /* ── MOBILE MENU ─────────────────────────────────── */
        .mobile-menu {
            display:none; position:fixed; inset:0; z-index:999;
            background:rgba(8,8,8,0.97);
            backdrop-filter:blur(20px);
            flex-direction:column; align-items:center; justify-content:center;
            gap:32px;
        }

        .mobile-menu.open { display:flex; }

        .mobile-menu a {
            font-family:'Cormorant Garamond', serif;
            font-size:2rem; font-weight:600;
            color:var(--white); text-decoration:none;
            letter-spacing:0.08em; transition:color 0.3s;
        }

        .mobile-menu a:hover { color:var(--gold); }

        .mobile-menu-close {
            position:absolute; top:24px; right:32px;
            background:none; border:none; color:var(--gold);
            font-size:1.5rem; cursor:pointer;
        }

        /* ── ANIMATIONS ──────────────────────────────────── */
        .fade-up {
            opacity:0; transform:translateY(30px);
            transition:all 0.7s cubic-bezier(0.22,1,0.36,1);
        }

        .fade-up.visible { opacity:1; transform:translateY(0); }

        .delay-1 { transition-delay:0.1s; }
        .delay-2 { transition-delay:0.2s; }
        .delay-3 { transition-delay:0.3s; }
        .delay-4 { transition-delay:0.4s; }

        /* ── RESPONSIVE ──────────────────────────────────── */
        @media (max-width:1024px) {
            nav { padding:18px 30px; }
            nav.scrolled { padding:12px 30px; }
            .nav-links { display:none; }
            .nav-hamburger { display:flex; flex-direction:column; }
            .hero { padding:120px 30px 60px; }
            .hero-content { grid-template-columns:1fr; gap:50px; }
            .hero-visual { order:-1; }
            .bank-card-3d { max-width:320px; }
            section { padding:70px 30px; }
            .features-grid { grid-template-columns:repeat(2,1fr); }
            .rates-grid { grid-template-columns:repeat(2,1fr); }
            .testimonials-grid { grid-template-columns:1fr; }
            .hiw-grid { grid-template-columns:1fr; }
            .footer-grid { grid-template-columns:1fr 1fr; gap:32px; }
            footer { padding:40px 30px; }
        }

        @media (max-width:640px) {
            .hero { padding:100px 20px 50px; }
            section { padding:60px 20px; }
            .features-grid { grid-template-columns:1fr; }
            .rates-grid { grid-template-columns:1fr; }
            .hero-stats { flex-direction:column; gap:20px; }
            .footer-grid { grid-template-columns:1fr; }
            .footer-bottom { flex-direction:column; gap:10px; text-align:center; }
            .cta-btns { flex-direction:column; align-items:center; }
            nav { padding:16px 20px; }
            .nav-actions { gap:8px; }
            .btn-nav-outline, .btn-nav-solid { padding:8px 16px; font-size:0.7rem; }
        }
    </style>
</head>
<body>

<!-- ── MOBILE MENU ── -->
<div class="mobile-menu" id="mobileMenu">
    <button class="mobile-menu-close" onclick="closeMobileMenu()">✕</button>
    <a href="#features" onclick="closeMobileMenu()">Features</a>
    <a href="#how-it-works" onclick="closeMobileMenu()">How It Works</a>
    <a href="#rates" onclick="closeMobileMenu()">Rates</a>
    <a href="login.php">Sign In</a>
    <a href="register.php" style="color:var(--gold)">Open Account →</a>
</div>

<!-- ── NAVIGATION ── -->
<nav id="mainNav">
    <a href="landing.php" class="nav-logo">
        <div class="nav-logo-mark">A</div>
        <span class="nav-logo-text">APEX <span>BANK</span></span>
    </a>

    <ul class="nav-links">
        <li><a href="#features">Features</a></li>
        <li><a href="#how-it-works">How It Works</a></li>
        <li><a href="#rates">Rates</a></li>
        <li><a href="#testimonials">Reviews</a></li>
    </ul>

    <div class="nav-actions">
        <a href="login.php" class="btn-nav-outline">Sign In</a>
        <a href="register.php" class="btn-nav-solid">Open Account</a>
        <button class="nav-hamburger" onclick="openMobileMenu()" aria-label="Menu">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>

<!-- ── HERO ── -->
<section class="hero">
    <div class="hero-bg">
        <div class="hero-dots"></div>
    </div>
    <div class="hero-lines"></div>

    <div class="hero-content">
        <div>
            <div class="hero-eyebrow fade-up visible">Established Excellence Since 2020</div>
            <h1 class="hero-title fade-up visible delay-1">
                Where <em>Wealth</em><br>Meets<br>Wisdom.
            </h1>
            <p class="hero-subtitle fade-up visible delay-2">
                Experience premium banking crafted for those who expect more.
                Secure accounts, intelligent transactions, and world-class service —
                all in one elegant platform.
            </p>
            <div class="hero-cta fade-up visible delay-3">
                <a href="register.php" class="btn-primary">
                    Open an Account
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
                <a href="login.php" class="btn-secondary">Sign In →</a>
            </div>
            <div class="hero-stats fade-up visible delay-4">
                <div class="hero-stat-item">
                    <div class="hero-stat-num">12K+</div>
                    <div class="hero-stat-label">Active Clients</div>
                </div>
                <div class="hero-stat-item">
                    <div class="hero-stat-num">৳500M+</div>
                    <div class="hero-stat-label">Deposits Managed</div>
                </div>
                <div class="hero-stat-item">
                    <div class="hero-stat-num">99.9%</div>
                    <div class="hero-stat-label">Uptime Guarantee</div>
                </div>
            </div>
        </div>

        <div class="hero-visual fade-up visible delay-2">
            <div style="position:relative;padding:20px;">
                <div class="card-ghost card-ghost-1"></div>
                <div class="card-ghost card-ghost-2"></div>
                <div class="bank-card-3d">
                    <div class="card-chip"></div>
                    <div class="card-number">4729 •••• •••• 8841</div>
                    <div class="card-bottom">
                        <div>
                            <div class="card-holder">Account Holder</div>
                            <div class="card-name">APEX PREMIUM</div>
                        </div>
                        <div class="card-logo">⬡ APEX</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── MARQUEE ── -->
<div class="marquee-strip">
    <div class="marquee-inner">
        <?php
        $items = ['Savings Account','Fixed Deposit','Personal Loan','Home Loan','Business Account','Fund Transfer','24/7 Support','Secure Banking','Zero Hidden Fees','Instant Transfers'];
        $html = '';
        foreach ($items as $item) $html .= "<span class='marquee-item'>$item</span>";
        echo str_repeat($html, 3);
        ?>
    </div>
</div>

<!-- ── FEATURES ── -->
<section id="features" style="max-width:1200px;margin:0 auto;padding:100px 60px;">
    <div class="fade-up">
        <div class="section-eyebrow">Everything You Need</div>
        <h2 class="section-title">Banking Redefined<br>for the Modern Age</h2>
        <p class="section-subtitle">A complete suite of financial tools built with security, speed, and simplicity at its core.</p>
    </div>

    <div class="features-grid fade-up">
        <div class="feature-item">
            <div class="feature-icon">🏦</div>
            <div class="feature-title">Multi-Account Support</div>
            <p class="feature-desc">Open Savings, Current, or Fixed Deposit accounts. Each with competitive interest rates tailored to your goals.</p>
        </div>
        <div class="feature-item">
            <div class="feature-icon">⚡</div>
            <div class="feature-title">Instant Transfers</div>
            <p class="feature-desc">Send funds between any Apex Bank accounts in seconds. Real-time balance updates and full transaction history.</p>
        </div>
        <div class="feature-item">
            <div class="feature-icon">🔐</div>
            <div class="feature-title">Bank-Grade Security</div>
            <p class="feature-desc">Role-based access, encrypted credentials, and session-protected operations keep your money safe 24/7.</p>
        </div>
        <div class="feature-item">
            <div class="feature-icon">📊</div>
            <div class="feature-title">Live Analytics</div>
            <p class="feature-desc">Visualize your financial health with real-time charts, monthly summaries, and downloadable reports.</p>
        </div>
        <div class="feature-item">
            <div class="feature-icon">💳</div>
            <div class="feature-title">Loan Management</div>
            <p class="feature-desc">Apply for personal, home, car, or business loans. Transparent EMI calculator and instant approval tracking.</p>
        </div>
        <div class="feature-item">
            <div class="feature-icon">🌙</div>
            <div class="feature-title">Always Available</div>
            <p class="feature-desc">Access your accounts, statements, and services around the clock from any device, anywhere in the world.</p>
        </div>
    </div>
</section>

<!-- ── HOW IT WORKS ── -->
<section class="hiw-section" id="how-it-works">
    <div class="hiw-grid">
        <div class="fade-up">
            <div class="section-eyebrow">Simple Process</div>
            <h2 class="section-title">Up and Running<br>in Minutes</h2>
            <p class="section-subtitle">No paperwork, no queues. Open your account entirely online and start banking immediately.</p>

            <div class="steps-list">
                <div class="step-item">
                    <div class="step-num">01</div>
                    <div class="step-content">
                        <h4>Register Your Profile</h4>
                        <p>Fill in your personal details and create your secure login credentials. Takes under 2 minutes.</p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-num">02</div>
                    <div class="step-content">
                        <h4>Account Activation</h4>
                        <p>Our team verifies your information and activates your account — usually within the same business day.</p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-num">03</div>
                    <div class="step-content">
                        <h4>Start Banking</h4>
                        <p>Deposit funds, make transfers, apply for loans, and monitor everything from your personal dashboard.</p>
                    </div>
                </div>
            </div>

            <div style="margin-top:40px;">
                <a href="register.php" class="btn-primary">Begin Your Journey →</a>
            </div>
        </div>

        <div class="fade-up delay-2">
            <div class="screen-mock">
                <div class="screen-topbar">
                    <div class="screen-dot" style="background:#E74C3C;"></div>
                    <div class="screen-dot" style="background:#F39C12;"></div>
                    <div class="screen-dot" style="background:#2ECC71;"></div>
                    <span style="font-size:0.7rem;color:#555;margin-left:8px;letter-spacing:0.1em;">APEX BANK · Dashboard</span>
                </div>
                <div class="screen-body">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
                        <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#9A7B2F,#C9A84C);display:flex;align-items:center;justify-content:center;color:#000;font-weight:700;font-size:0.8rem;">A</div>
                        <div>
                            <div style="font-size:0.7rem;color:#555;letter-spacing:0.1em;">WELCOME BACK</div>
                            <div style="font-size:0.85rem;color:#F8F4EC;font-weight:500;">Alice Johnson</div>
                        </div>
                    </div>
                    <div class="screen-stat-row">
                        <div class="screen-stat">
                            <div class="label">Total Balance</div>
                            <div class="val">৳ 2,25,000</div>
                        </div>
                        <div class="screen-stat">
                            <div class="label">Active Accounts</div>
                            <div class="val">2</div>
                        </div>
                        <div class="screen-stat">
                            <div class="label">This Month</div>
                            <div class="val" style="color:#2ECC71;">+৳ 12,500</div>
                        </div>
                        <div class="screen-stat">
                            <div class="label">Loan Status</div>
                            <div class="val" style="color:#F39C12;">Active</div>
                        </div>
                    </div>
                    <div class="screen-bar-row">
                        <div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="font-size:0.65rem;color:#555;">Savings Goal</span><span style="font-size:0.65rem;color:#C9A84C;">72%</span></div>
                        <div class="screen-bar"><div class="screen-bar-fill" style="width:72%;"></div></div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:4px;margin-top:8px;"><span style="font-size:0.65rem;color:#555;">Loan Repayment</span><span style="font-size:0.65rem;color:#C9A84C;">45%</span></div>
                        <div class="screen-bar"><div class="screen-bar-fill" style="width:45%;background:linear-gradient(90deg,#c0392b,#E74C3C);"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── RATES ── -->
<section id="rates" style="max-width:1200px;margin:0 auto;padding:100px 60px;">
    <div class="rates-header fade-up">
        <div class="section-eyebrow">Transparent Pricing</div>
        <h2 class="section-title">Competitive Rates,<br>Zero Surprises</h2>
        <p class="section-subtitle" style="margin:0 auto;">Our interest rates are designed to grow your wealth, not our profits.</p>
    </div>

    <div class="rates-grid fade-up">
        <div class="rate-card">
            <div class="rate-card-header">
                <div class="type">Savings</div>
                <div class="name">Savings Account</div>
            </div>
            <div class="rate-card-body">
                <div class="rate-big">5.5<span>%</span></div>
                <div class="rate-label">Annual Interest Rate</div>
                <ul class="rate-features">
                    <li>Minimum balance ৳500</li>
                    <li>Free transfers</li>
                    <li>Monthly statements</li>
                    <li>Debit card included</li>
                </ul>
            </div>
        </div>
        <div class="rate-card">
            <div class="rate-card-header">
                <div class="type">Current</div>
                <div class="name">Current Account</div>
            </div>
            <div class="rate-card-body">
                <div class="rate-big">0<span>%</span></div>
                <div class="rate-label">No interest, unlimited txns</div>
                <ul class="rate-features">
                    <li>Unlimited transactions</li>
                    <li>Overdraft facility</li>
                    <li>Business cheque book</li>
                    <li>Priority support</li>
                </ul>
            </div>
        </div>
        <div class="rate-card" style="border-color:var(--gold);position:relative;">
            <div style="position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,var(--gold-dark),var(--gold));color:var(--black);font-size:0.65rem;font-weight:600;letter-spacing:0.15em;text-transform:uppercase;padding:4px 14px;border-radius:20px;">Most Popular</div>
            <div class="rate-card-header" style="background:rgba(201,168,76,0.15);">
                <div class="type">Fixed</div>
                <div class="name">Fixed Deposit</div>
            </div>
            <div class="rate-card-body">
                <div class="rate-big">9<span>%</span></div>
                <div class="rate-label">Per annum, 1-year lock-in</div>
                <ul class="rate-features">
                    <li>Highest returns</li>
                    <li>Guaranteed interest</li>
                    <li>Auto-renewal option</li>
                    <li>Premature withdrawal</li>
                </ul>
            </div>
        </div>
        <div class="rate-card">
            <div class="rate-card-header">
                <div class="type">Loan</div>
                <div class="name">Personal Loan</div>
            </div>
            <div class="rate-card-body">
                <div class="rate-big">12<span>%</span></div>
                <div class="rate-label">Starting rate per annum</div>
                <ul class="rate-features">
                    <li>Up to ৳50 Lakh</li>
                    <li>Flexible tenures</li>
                    <li>Fast approval</li>
                    <li>No collateral needed</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<!-- ── TESTIMONIALS ── -->
<section class="testimonials-section" id="testimonials">
    <div style="max-width:1100px;margin:0 auto;text-align:center;">
        <div class="section-eyebrow" style="justify-content:center;">Client Stories</div>
        <h2 class="section-title">Trusted by Thousands</h2>
        <div class="testimonials-grid fade-up">
            <div class="testimonial-card">
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-text">"The fixed deposit rates are unmatched. My savings grew significantly within the first year. The dashboard makes tracking everything effortless."</p>
                <div class="testimonial-author">
                    <div class="author-avatar">R</div>
                    <div>
                        <div class="author-name">Rahim Chowdhury</div>
                        <div class="author-title">Fixed Deposit Customer</div>
                    </div>
                </div>
            </div>
            <div class="testimonial-card">
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-text">"Running my business finances through Apex Bank has been seamless. Instant transfers, clear reports, and the staff are incredibly responsive."</p>
                <div class="testimonial-author">
                    <div class="author-avatar">S</div>
                    <div>
                        <div class="author-name">Salma Begum</div>
                        <div class="author-title">Business Account Holder</div>
                    </div>
                </div>
            </div>
            <div class="testimonial-card">
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-text">"Got my home loan approved in 24 hours. The EMI calculator helped me plan perfectly. Apex Bank genuinely understands modern customers."</p>
                <div class="testimonial-author">
                    <div class="author-avatar">K</div>
                    <div>
                        <div class="author-name">Kamal Hossain</div>
                        <div class="author-title">Home Loan Customer</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── CTA ── -->
<section class="cta-section">
    <div class="cta-content fade-up">
        <div class="section-eyebrow" style="justify-content:center;">Start Today</div>
        <h2 class="cta-title">Your <em>Financial Future</em><br>Starts Here</h2>
        <p class="cta-sub">Join thousands of satisfied customers who trust Apex Bank with their financial journey. Open your account in minutes.</p>
        <div class="cta-btns">
            <a href="register.php" class="btn-primary" style="font-size:0.85rem;padding:16px 40px;">
                Open Account — It's Free
            </a>
            <a href="login.php" class="btn-secondary" style="font-size:0.85rem;padding:16px 40px;">
                Already a Member? Sign In
            </a>
        </div>
    </div>
</section>

<!-- ── FOOTER ── -->
<footer>
    <div class="footer-grid">
        <div class="footer-brand">
            <a href="landing.php" class="nav-logo" style="text-decoration:none;">
                <div class="nav-logo-mark">A</div>
                <span class="nav-logo-text">APEX <span>BANK</span></span>
            </a>
            <p>A modern, secure, and intelligent banking management system built for the digital generation. Excellence in every transaction.</p>
            <div style="display:flex;gap:12px;">
                <a href="#" style="width:34px;height:34px;border:1px solid var(--gold-line);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:0.8rem;color:var(--gold);text-decoration:none;transition:all 0.3s;" onmouseover="this.style.background='var(--gold-dim)'" onmouseout="this.style.background='transparent'">f</a>
                <a href="#" style="width:34px;height:34px;border:1px solid var(--gold-line);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:0.8rem;color:var(--gold);text-decoration:none;transition:all 0.3s;" onmouseover="this.style.background='var(--gold-dim)'" onmouseout="this.style.background='transparent'">in</a>
                <a href="#" style="width:34px;height:34px;border:1px solid var(--gold-line);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:0.8rem;color:var(--gold);text-decoration:none;transition:all 0.3s;" onmouseover="this.style.background='var(--gold-dim)'" onmouseout="this.style.background='transparent'">𝕏</a>
            </div>
        </div>
        <div>
            <div class="footer-title">Services</div>
            <ul class="footer-links">
                <li><a href="#">Savings Account</a></li>
                <li><a href="#">Current Account</a></li>
                <li><a href="#">Fixed Deposit</a></li>
                <li><a href="#">Personal Loans</a></li>
                <li><a href="#">Fund Transfer</a></li>
            </ul>
        </div>
        <div>
            <div class="footer-title">Company</div>
            <ul class="footer-links">
                <li><a href="#">About Us</a></li>
                <li><a href="#">Careers</a></li>
                <li><a href="#">Press</a></li>
                <li><a href="#">Blog</a></li>
                <li><a href="#">Contact</a></li>
            </ul>
        </div>
        <div>
            <div class="footer-title">Support</div>
            <ul class="footer-links">
                <li><a href="#">Help Center</a></li>
                <li><a href="#">Privacy Policy</a></li>
                <li><a href="#">Terms of Service</a></li>
                <li><a href="#">Security</a></li>
                <li><a href="login.php">Portal Login</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <span>© <?php echo date('Y'); ?> Apex Bank. All rights reserved.</span>
        <span>Built with precision for your <a href="#">Database Management Course</a></span>
    </div>
</footer>

<script>
    // Sticky nav
    window.addEventListener('scroll', () => {
        document.getElementById('mainNav').classList.toggle('scrolled', window.scrollY > 60);
    });

    // Mobile menu
    function openMobileMenu() { document.getElementById('mobileMenu').classList.add('open'); }
    function closeMobileMenu() { document.getElementById('mobileMenu').classList.remove('open'); }

    // Scroll reveal
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
    }, { threshold: 0.12 });

    document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));

    // Smooth anchor scroll
    document.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', e => {
            const target = document.querySelector(a.getAttribute('href'));
            if (target) { e.preventDefault(); target.scrollIntoView({ behavior:'smooth' }); }
        });
    });
</script>
</body>
</html>