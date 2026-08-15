Full working project — 32 files, all PHP-linted clean. Here's the complete structure:

asha_bank/
├── database.sql              Full schema: 30+ tables, 5 procedures, 6 functions, 5 triggers, 5 views, seed data
├── index.php                 Landing page
├── login.php                 Role-based auth (Client/Staff/Admin tabs)
├── register.php              Signup → auto-creates Savings account + debit card
├── logout.php
├── dashboard.php             Customer dashboard (balance, tier card, chart, recent txns)
├── transfer.php / deposit.php / withdraw.php
├── cards.php                 Tiered debit card UI, block/unblock
├── profile.php                Personal info, KYC, password, nominees, reactivation
├── feedback.php               AJAX feedback form
├── setup.php                  DB import helper
├── README.md                  Full setup instructions + demo logins
│
├── config/          db.php (PDO + BASE_URL), language.php (EN/বাংলা)
├── includes/        header.php, footer.php, functions.php (helpers, auth guards, tiers)
├── admin/           index.php — overview/customers/staff/staff_messages/reactivations/broadcast
├── staff/           dashboard.php — overview/kyc/feedback/messages
├── api/              send_feedback.php, mark_notification_read.php, send_verification.php
├── modules/
│   ├── loans/       apply.php (live EMI calc), manage.php (schedule + pay)
│   ├── bills/       pay.php, history.php
│   └── cheques/     request.php, status.php (stop payment)
└── assets/
    ├── css/style.css   Full dark design system (gold accent, tiered cards, charts, modals, toasts)
    ├── js/main.js       Sidebar, dropdowns, canvas charts, AJAX, toasts
    └── images/

Design: near-black surfaces (
#09090b→
#1b1b1f), warm gold accent (
#d4a657), Outfit/Inter/JetBrains Mono — matching the sidebar-dashboard style from your screenshots, with a signature element: tiered debit cards (Classic→Black Edition) with gradient designs and progress bars.

Setup: mysql -u root -p < database.sql, then serve the folder with PHP. Demo logins are in the README (admin/Admin@123, rajesh/password, arjun.kapoor/password).

One honest note: I built the full core system (auth, accounts, transfers, cards, KYC, feedback, loans, bills, cheques, admin/staff portals) rather than every single sub-feature listed in your spec (e.g. PDF/Excel report export, tax certificates, branch performance reports, Telegram/SMS integration). Those are stubbable extensions on this foundation — let me know if you want me to build any of them out next.