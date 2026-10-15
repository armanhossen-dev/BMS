# 🏦 Asha Bank — Banking Management System

A full-stack PHP/MySQL banking web application with a dark, gold-accented
dashboard UI across three portals — **Customer**, **Staff**, and **Admin** —
built responsive from desktop down to mobile.

> Asha means *"hope"* — the product's visual identity (warm gold on near-black)
> and tagline ("Banking that grows with your hope") are built around that.

---

## Table of Contents

1. [Screenshots](#-screenshots)
2. [Features](#-features)
3. [File Structure](#-file-structure)
4. [Requirements](#-requirements)
5. [Setup](#-setup)
6. [Demo Credentials](#-demo-credentials)
7. [Database Overview](#-database-overview)
8. [Design System](#-design-system)
9. [Security](#-security-notes)
10. [Known Limitations](#-known-limitations--next-steps)

---

## 📸 Screenshots

### 📊 Core Banking Dashboards

#### 1. Customer Banking Portal

> **The Client Journey:** From the moment an account is opened, customers are welcomed into an intuitive wealth-management cockpit engineered for speed, transparency, and elegance. The interface prioritizes real-time financial situational awareness—combining total balance aggregation, 7-day transaction velocity analytics, and frictionless access to every banking service without paperwork or wait times.

[![Customer Dashboard](ss/client-dash.png)](ss/client-dash.png)

##### 💎 Key Client Capabilities:
- **Financial Command Center:** Real-time balance aggregation across accounts, monthly inflow/outflow delta metrics, and an in-house Canvas-drawn 7-day transaction volume chart.
- **Dynamic Tiered Debit Card:** Virtual debit card with automatic tier-based gradient styling (Classic → Silver → Gold → Platinum → Black Edition) calibrated to customer liquidity, complete with star ratings and instant freeze/unfreeze controls.
- **Frictionless Money Movement:** Quick-action hubs for instant peer-to-peer bank transfers, multi-channel MFS deposits (bKash, Nagad, Rocket, Upay, Branch cash), and cash withdrawals.
- **Integrated Digital Services:** Seamless access to personal and commercial loans with a live EMI calculator, utility bill payments (Electricity, Water, Gas, Mobile) with auto-pay support, and cheque book management.

<br>

#### 2. Staff & Admin Portals

> **The Operational Story:** Behind every effortless customer transaction lies a synchronized two-tier governance backbone. Frontline **Staff Officers** act as the operational shield—evaluating citizen compliance, verifying identity submissions, and resolving client inquiries. Simultaneously, the **Executive Administrator** commands institutional governance—monitoring bank-wide liquidity reserves, tracking capital velocity, enforcing compliance holds, and broadcasting urgent bank-wide directives.

| 🛡️ Staff Operations Portal | 👑 Administrator Command Center |
| :---: | :---: |
| **Pending KYC Queue, Feedback Inbox & Admin Escalations** | **Liquidity Reserves, 6-Month Flow, User Controls & Audits** |
| [![Staff Dashboard](ss/staff-dash.png)](ss/staff-dash.png) | [![Admin Dashboard](ss/admin-dash.png)](ss/admin-dash.png) |

##### 🛡️ Staff Operational Capabilities:
- **KYC Compliance Pipeline:** Real-time queue to inspect National ID (NID) and citizen documents. Staff can approve or reject verification applications with audit-trailed reasons and automated customer alerts.
- **Customer Feedback & Support Inbox:** Centralized desk to triage, review, and reply to client grievances, automatically advancing ticket lifecycles (`Pending` → `Replied` → `Resolved`).
- **Internal Administrative Escalation:** Secure escalation channel allowing frontline staff to transmit field reports, flagged anomalies, and branch memos directly to Executive Administration.
- **Daily Operations Snapshot:** Live status counters tracking pending KYC dossiers, unread client messages, and daily branch transaction volumes.

##### 👑 Administrator Command & Control:
- **Institutional Liquidity & Trend Analytics:** Real-time visibility into bank-wide reserve balances, customer counts, 7-day transaction velocity bars, and 6-month deposits-vs-withdrawals cashflow curves.
- **Account Governance & Risk Controls:** Full executive authority to activate, freeze, or terminate customer accounts with automated push notifications.
- **Reactivation Adjudication:** Compliance tribunal interface to review reactivation petitions submitted by frozen account holders, complete with resolution ETAs and administrative notes.
- **Workforce Management:** Complete administration over bank staff credentials, department assignments, and operational permissions.
- **System-Wide Broadcast Hub:** Emergency broadcast engine capable of delivering high-priority notices and policy advisories to all active customer dashboards simultaneously.

---

### 🔐 Authentication & Onboarding

> **The First 3 Minutes:** Opening a bank account shouldn't feel like navigating a bureaucracy. Asha Bank's onboarding flow eliminates physical paperwork, instantly provisioning an account number, initial deposit credit, and a virtual debit card ready for use.

| Unified Portal Login (Client / Staff / Admin) | Customer Registration & Instant Card Issuance |
| :---: | :---: |
| [![Login Portal](ss/login.png)](ss/login.png) | [![Registration Page](ss/Register.png)](ss/Register.png) |

- **Intelligent 3-in-1 Authentication:** Single secure gateway routing Customers, Staff, and Administrators to their dedicated portals based on verified database roles, fortified with BCRYPT hashing and 5-attempt brute-force lockout safeguards.
- **Instant Self-Service Onboarding:** 3-step registration gathering identity and contact information, automatically generating a unique account number and tiered virtual debit card immediately upon submission.

---

### 🌐 Landing Page & Brand Showcase

> **The Public Face:** An elegant, responsive introduction to Asha Bank crafted with high-contrast typography, banking service highlights, currency calculators, branch locator, and seamless English / বাংলা dual-language switching.

[![Landing Page - Light Mode](ss/white-ban.png)](ss/white-ban.png)

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
BMS/
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
├── ss/                          Application UI screenshots & preview assets
│   ├── client-dash.png          Customer portal & financial overview
│   ├── staff-dash.png           Staff operational workflow & KYC queue
│   ├── admin-dash.png           System analytics & administrative controls
│   ├── login.png                Unified 3-in-1 secure login interface
│   ├── Register.png             Self-service customer onboarding
│   └── white-ban.png            Landing page showcase (Light theme)
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
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'asha_bank');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Set `BASE_URL`

Also in `config/db.php`:

```php
define('BASE_URL', '/BMS');
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
