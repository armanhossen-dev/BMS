# 🏦 Asha Bank — Banking Management System

A full-stack PHP/MySQL banking web application with a dark, gold-accented
dashboard UI across three portals — **Customer**, **Staff**, and **Admin** —
built responsive from desktop down to mobile.

> Asha means *"hope"* — the product's visual identity (warm gold on near-black)
> and tagline ("Banking that grows with your hope") are built around that.

---

## Table of Contents

1. [Features](#-features)
2. [File Structure](#-file-structure)
3. [Requirements](#-requirements)
4. [Setup](#-setup)
5. [Demo Credentials](#-demo-credentials)
6. [Database Overview](#-database-overview)
7. [Design System](#-design-system)
8. [Security](#-security-notes)
9. [Known Limitations](#-known-limitations--next-steps)

---

## ✨ Features

### Authentication
- Role-based login — one form, three roles (Client / Staff / Admin)
- BCRYPT password hashing
- Session regeneration on login (session fixation protection)
- 5-attempt login lockout (15-minute cooldown) for both staff and customers
- CSRF token verification on every state-changing form

### Customer Portal
- **Dashboard** — total balance, monthly received/sent, tier badge with star
  rating, 7-day transaction volume chart, tiered debit card preview, account
  list, recent transactions
- **Send Money** — instant transfer between Asha Bank accounts with balance
  and status checks
- **Deposit** — bKash / Nagad / Rocket / Upay / Branch, with method-specific
  reference fields
- **Withdraw** — ATM / Branch / Mobile Banking
- **Cards** — auto-issued debit card on signup, tier-styled gradient design,
  block/unblock, full card detail modal
- **Profile** — edit contact info, change password, submit KYC (NID + phone),
  view nominees, referral code, request account reactivation
- **Feedback** — AJAX submission with live character counter and status
  history (pending → read → replied → resolved)
- **Loans** — apply for Home / Personal / Car / Education loans with a live
  EMI calculator, view application status, pay EMIs against a generated
  amortization schedule
- **Bill Pay** — Electricity / Water / Gas / Mobile / DTH / Insurance,
  optional recurring auto-pay flag, payment history
- **Cheques** — request a cheque book, track issued cheques, place stop
  payments

### Staff Portal
- Overview stats (pending KYC, today's transactions, pending feedback)
- KYC review queue — verify or reject with a reason (customer is notified
  either way)
- Feedback inbox — reply to customer feedback, status updates automatically
- Send messages/reports/requests up to Admin, see admin replies

### Admin Portal
- Overview — total customers, total reserve, total transactions, active
  staff, daily transaction bar chart (7 days), deposits-vs-withdrawals bar
  chart (6 months), top 5 customers by volume, recent transactions
- Customer management — activate/deactivate with automatic notification
- Staff management — add, toggle active/inactive, delete
- Staff messages — approve/reject with a reply
- Reactivation requests — approve (auto-reactivates the account) or reject
  with a reply and optional ETA
- Broadcast — send a notification to every active customer at once

### Platform-wide
- Notifications — dropdown with unread badge, mark one or all as read (AJAX),
  auto-generated on transfers, deposits, withdrawals, large transactions,
  KYC decisions, card status changes, and broadcasts
- Multi-language — English / বাংলা toggle, session-persisted
- Fully responsive — sidebar collapses behind a hamburger menu on mobile,
  all grids stack to a single column below 860px

---

## 📁 File Structure

```
asha_bank/
├── index.php                    Landing page
├── login.php                    Role-based authentication
├── register.php                 Customer registration (auto account + card)
├── dashboard.php                Customer dashboard
├── transfer.php                 Fund transfer
├── deposit.php                  Deposit (bKash/Nagad/Rocket/Upay/Branch)
├── withdraw.php                 Withdrawal
├── cards.php                    Card management (block/unblock)
├── profile.php                  Profile, KYC, password, nominees, reactivation
├── feedback.php                 Customer feedback (AJAX submit)
├── logout.php
├── setup.php                    DB import helper
├── database.sql                 Full schema + seed data
├── README.md                    This file
│
├── config/
│   ├── db.php                   PDO connection + BASE_URL constant
│   └── language.php             EN / বাংলা string tables
│
├── includes/
│   ├── header.php               Customer sidebar + topbar + notifications
│   ├── footer.php                JS include + flash → toast bridge
│   └── functions.php             Helpers: currency, tiers, CSRF, auth guards…
│
├── admin/
│   └── index.php                 Tabs: overview, customers, staff,
│                                   staff_messages, reactivations, broadcast
│
├── staff/
│   └── dashboard.php             Tabs: overview, kyc, feedback, messages
│
├── api/                          AJAX endpoints (JSON responses)
│   ├── send_feedback.php
│   ├── mark_notification_read.php
│   └── send_verification.php
│
├── modules/
│   ├── loans/
│   │   ├── apply.php             Application + live EMI calculator
│   │   └── manage.php            EMI schedule + pay EMI
│   ├── bills/
│   │   ├── pay.php                Category → provider cascading select
│   │   └── history.php
│   └── cheques/
│       ├── request.php            Cheque book request + issued cheques
│       └── status.php             Stop payment
│
└── assets/
    ├── css/style.css             Design tokens + full component library
    ├── js/main.js                 Sidebar, dropdowns, modals, toasts, charts
    └── images/                    Add your own logos/assets here
```

---

## 🧰 Requirements

- PHP **8.0+** with the `pdo_mysql` extension
- MySQL **5.7+** or MariaDB **10.3+** (needs `JSON` column support for audit
  logs, and `SIGNAL`/trigger support)
- Any web server (Apache, Nginx, or PHP's built-in server for local testing)

---

## 🚀 Setup

### 1. Import the database

```bash
mysql -u root -p < database.sql
```

The schema uses `DELIMITER $$` blocks for stored procedures, functions, and
triggers — import via the **CLI** or **phpMyAdmin's Import tab**. A raw
multi-statement API call (like `mysqli::multi_query`) will not parse the
`DELIMITER` syntax correctly, which is why `setup.php`'s one-click import is
labeled "basic" and CLI import is the recommended path.

### 2. Configure the connection

Edit `config/db.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'asha_bank');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Set `BASE_URL`

Also in `config/db.php`:

```php
define('BASE_URL', '/asha_bank');
```

Change this if you deploy under a different folder name, or to `''` if the
app lives at your domain root.

### 4. Serve the app

```bash
# Quick local test
php -S localhost:8000

# Or point Apache/Nginx's document root at the project folder
```

Visit `index.php` (or just `/`) in your browser.

---

## 🔑 Demo Credentials

| Role   | Username        | Password    | Portal          |
|--------|-----------------|-------------|------------------|
| Admin  | `admin`         | `Admin@123` | `/admin/index.php` |
| Staff  | `rajesh`        | `password`  | `/staff/dashboard.php` |
| Client | `arjun.kapoor`  | `password`  | `/dashboard.php` |

Two more seeded clients: `sanya.malhotra` and `kiran.bose`, both with password
`password`.

---

## 🗄 Database Overview

**37 tables**, organized into logical groups:

| Group | Tables |
|---|---|
| Geography | `ZONE`, `REGION`, `BRANCH` |
| Employees | `DEPARTMENT`, `DESIGNATION`, `EMPLOYEE` |
| System users | `ADMIN_USER`, `STAFF` |
| Customers | `CUSTOMERCATEGORY`, `CUSTOMER`, `NOMINEE` |
| Accounts | `ACCOUNTPRODUCT`, `ACCOUNT`, `JOINT_ACCOUNT_HOLDERS` |
| Cards | `CARDS` |
| Transactions | `TRANSACTIONTYPE`, `TRANSACTION`, `TRANSACTION_LIMITS` |
| Digital banking | `DIGITALBANKINGUSER` |
| Engagement | `NOTIFICATIONS`, `FEEDBACK` |
| Compliance | `KYC_VERIFICATIONS`, `REACTIVATION_REQUESTS` |
| Internal ops | `STAFF_MESSAGES` |
| Loans | `LOAN_PRODUCTS`, `LOANS`, `LOAN_EMI_SCHEDULE` |
| Bills | `BILL_CATEGORIES`, `BILL_PROVIDERS`, `BILL_PAYMENTS`, `BILL_AUTOPAY_SCHEDULES` |
| Cheques | `CHEQUE_BOOK_REQUESTS`, `CHEQUES` |
| Growth | `REFERRALS`, `REWARD_POINTS`, `REWARD_HISTORY` |
| Audit | `AUDIT_LOGS`, `TRANSACTION_AUDIT` |

**Also included:**
- 5 stored procedures — `sp_get_customer_statement`, `sp_transfer_money`,
  `sp_apply_monthly_interest`, `sp_calculate_loan_emi`, `sp_generate_emi_schedule`
- 6 functions — `fn_calculate_age`, `fn_get_customer_tier`,
  `fn_format_currency`, `fn_total_customer_transactions`, `fn_calculate_emi`,
  `fn_get_balance`
- 5 triggers — zero-balance auto-dormancy, balance-change audit log,
  large-transaction notification, negative-balance prevention, customer
  update audit log
- 5 views — `v_customer_balance_tier`, `v_daily_transaction_summary`,
  `v_account_summary`, `v_loan_summary`, `v_bill_payment_summary`
- Indexes — single, composite, and two full-text indexes (`CUSTOMER`,
  `TRANSACTION.Description`)

> **Note:** the PHP application layer performs transfers, deposits,
> withdrawals, and EMI payments directly with prepared statements inside
> `PDO` transactions (see `transfer.php`, `deposit.php`, etc.), rather than
> calling `sp_transfer_money`. Both paths are valid — the stored procedure is
> included in the schema for direct SQL/CLI use and as a reference
> implementation.

---

## 🎨 Design System

| Token | Value |
|---|---|
| Background | `#09090b` → `#1b1b1f` (layered near-black surfaces) |
| Accent | `#d4a657` warm gold (*Asha* = "hope") |
| Success / Danger / Warning / Info | `#4ade80` / `#f87171` / `#fbbf24` / `#60a5fa` |
| Display font | **Outfit** |
| Body font | **Inter** |
| Numerals | **JetBrains Mono** (account numbers, balances, card numbers) |

**Signature element:** tiered debit cards. Balance drives a customer's tier
(Classic → Silver → Gold → Platinum → Black Edition), and each tier renders
its own card gradient, a star rating, and a progress bar toward the next
tier — visible on the dashboard and the Cards page.

Charts are drawn with a small hand-rolled Canvas API (`drawLineChart` /
`drawBarChart` in `assets/js/main.js`) — no external chart library required.

---

## 🔒 Security Notes

- All queries use **PDO prepared statements** — no string-concatenated SQL.
- All dynamic output is escaped via `clean()` (`htmlspecialchars`).
- Every POST form carries a CSRF token, verified server-side with
  `hash_equals()`.
- Passwords are hashed with `password_hash()` (BCRYPT).
- Login attempts are rate-limited per account (5 attempts → 15-minute lock).
- Session ID is regenerated on every successful login.

This is a learning/demo project. Before any real deployment, add: HTTPS
everywhere, network-level rate limiting, a secrets manager (don't hardcode
`DB_PASS`), CSP headers, and an independent security review of the stored
procedures/triggers and the KYC/reactivation approval flows.

---

## ⚠️ Known Limitations / Next Steps

The core system (auth, accounts, transfers, cards, KYC, feedback, loans,
bills, cheques, and all three portals) is fully built and wired to the
database. Not yet implemented, and reasonable next additions:

- PDF/Excel statement and report export
- Tax certificates (TDS, interest certificates)
- Branch-wise performance and customer-acquisition reports
- Real SMS/Telegram/email delivery for KYC codes (currently demo-mode: the
  code is returned in the API response instead of sent)
- Bill auto-pay execution (the `BILL_AUTOPAY_SCHEDULES` table and the
  recurring checkbox exist; a scheduled job to actually run them does not)
- Reward points accrual logic (tables exist; no accrual trigger yet)
- Joint account holder management UI (table exists, no UI)
