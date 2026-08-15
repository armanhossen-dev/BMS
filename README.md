# Asha Bank — Banking Management System [v2.0](https://github.com/armanhossen-dev/BMS/tree/main/v2.0)

A full-stack PHP/MySQL banking web app with a dark, gold-accented dashboard UI
(Customer / Staff / Admin portals), responsive from desktop down to mobile.

## ✨ What's included

- **Database**: `database.sql` — 30+ tables, 5 stored procedures, 6 functions,
  5 triggers, 5 views, indexes (incl. full-text), and seed data.
- **Auth**: role-based login (Admin / Staff / Client), BCRYPT hashing, session
  security, 5-attempt lockout, CSRF protection on every form.
- **Customer portal**: dashboard, transfers, deposits, withdrawals, cards,
  profile + KYC, feedback, loans, bill pay, cheque book requests.
- **Staff portal**: KYC review, feedback replies, messages to admin.
- **Admin portal**: stats + charts, customer/staff management, broadcast
  notifications, staff message replies, reactivation approvals.
- **Design system**: dark near-black surfaces, warm gold accent, tiered debit
  card visuals (Classic → Black Edition), canvas-based charts (no external
  chart library needed), toasts, modals, notification dropdown — fully
  responsive with a collapsible sidebar on mobile.

## 📁 File structure

```
asha_bank/
├── index.php                  Landing page
├── login.php                  Role-based authentication
├── register.php               Customer registration (auto account + card)
├── dashboard.php               Customer dashboard
├── transfer.php                Fund transfer
├── deposit.php                 Deposit (bKash/Nagad/Rocket/Upay/Branch)
├── withdraw.php                Withdrawal
├── cards.php                   Card management (block/unblock)
├── profile.php                 Profile, KYC, password, nominees, reactivation
├── feedback.php                Customer feedback (AJAX submit)
├── logout.php
├── setup.php                   DB import helper
├── database.sql                Full schema + seed data
├── config/
│   ├── db.php                  PDO connection + BASE_URL
│   └── language.php             EN / বাংলা strings
├── includes/
│   ├── header.php               Customer sidebar + topbar + notifications
│   ├── footer.php                JS include + flash → toast bridge
│   └── functions.php             Helpers: currency, tiers, CSRF, auth guards…
├── admin/
│   └── index.php                Admin dashboard (tabs: overview, customers,
│                                  staff, staff_messages, reactivations, broadcast)
├── staff/
│   └── dashboard.php            Staff dashboard (tabs: overview, kyc, feedback, messages)
├── api/
│   ├── send_feedback.php        AJAX: submit feedback
│   ├── mark_notification_read.php  AJAX: mark single/all notifications read
│   └── send_verification.php    Demo verification code generator
├── modules/
│   ├── loans/
│   │   ├── apply.php            Loan application + live EMI calculator
│   │   └── manage.php           EMI schedule + pay EMI
│   ├── bills/
│   │   ├── pay.php               Pay utilities / mobile / DTH
│   │   └── history.php           Bill payment history
│   └── cheques/
│       ├── request.php           Cheque book request + issued cheques
│       └── status.php            Stop payment
└── assets/
    ├── css/style.css             Design tokens + full component library
    ├── js/main.js                Sidebar, dropdowns, modals, toasts, charts
    └── images/                    (empty — add your own assets)
```

## 🚀 Setup

1. **Import the database**
   ```bash
   mysql -u root -p < database.sql
   ```
   (The schema uses `DELIMITER` for procedures/triggers, so use the CLI or
   phpMyAdmin's Import tab — not a raw multi-query call.)

2. **Configure the connection**
   Edit `config/db.php` if your MySQL user/password differ from `root` / *(empty)*.

3. **Set `BASE_URL`**
   In `config/db.php`, `BASE_URL` is set to `/asha_bank`. Change this constant
   if you deploy the app under a different folder name or domain root.

4. **Serve the app**
   Point your web server (Apache/Nginx + PHP 8.x, or `php -S localhost:8000`)
   at the project root, then visit `index.php`.

## 🔑 Demo credentials

| Role   | Username        | Password    |
|--------|-----------------|-------------|
| Admin  | `admin`         | `Admin@123` |
| Staff  | `rajesh`        | `password`  |
| Client | `arjun.kapoor`  | `password`  |

## 🎨 Design tokens

- Surfaces: `#09090b` → `#1b1b1f` (near-black, layered)
- Accent: warm gold `#d4a657` (Asha = "hope")
- Display font: **Outfit** · Body: **Inter** · Numerals: **JetBrains Mono**
- Tiers: Classic (gray) → Silver → Gold → Platinum (blue) → Black Edition
  (black + gold trim), each with a distinct card gradient and star rating.

## 🔒 Security notes

- All queries use PDO prepared statements.
- All output is escaped via `clean()` (htmlspecialchars).
- Every POST form carries a CSRF token, verified server-side.
- Passwords are hashed with `password_hash()` (BCRYPT).
- This is a learning/demo project — before any real deployment, add rate
  limiting at the network layer, HTTPS, proper secrets management, and a
  security review of the stored procedures and triggers.
