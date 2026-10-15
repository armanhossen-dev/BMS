<?php
require_once 'config/db.php';

// Redirect logged-in users to their portals
if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? 'client';
    if ($role === 'admin') redirect('admin/index.php');
    if ($role === 'staff') redirect('staff/dashboard.php');
    redirect('dashboard.php');
}

/* ─────────────────────────────────────────────────────────
   SITE CONFIG — edit these to match your real bank details
   ───────────────────────────────────────────────────────── */
$bank = [
    'name'     => 'Asha Bank',
    'legal'    => 'Asha Bank PLC',
    'hotline'  => '16999',
    'intl'     => '+880 9610-016999',
    'email'    => 'care@ashabank.com.bd',
    'swift'    => 'ASHABDDH',
    'routing'  => '090270144',
    'hours'    => 'Sun–Thu, 10:00 am – 4:00 pm',
    'address'  => 'Head Office, Motijheel C/A, Dhaka 1000',
];

// Live stats (fallbacks if DB is empty, intelligently combined with live counts)
$totalCustomers = 125000;
$totalDeposits  = 1850000000;
$totalBranches  = 48;
$totalCards     = 82000;

try {
    $custCount = (int)$pdo->query("SELECT COUNT(*) FROM CUSTOMER WHERE IsActive = 1")->fetchColumn();
    if ($custCount > 0) {
        $totalCustomers = ($custCount < 100) ? (125000 + $custCount) : $custCount;
    }

    $depSum = (float)$pdo->query("SELECT COALESCE(SUM(AvailableBalance),0) FROM ACCOUNT WHERE AccountStatus = 'Active'")->fetchColumn();
    if ($depSum > 0) {
        $totalDeposits = ($depSum < 100000000) ? (1850000000 + $depSum) : $depSum;
    }

    $branchCount = (int)$pdo->query("SELECT COUNT(*) FROM BRANCH")->fetchColumn();
    if ($branchCount > 0) {
        $totalBranches = max(48, $branchCount);
    }

    $cardCount = (int)$pdo->query("SELECT COUNT(*) FROM CARDS WHERE CardStatus = 'Active'")->fetchColumn();
    if ($cardCount > 0) {
        $totalCards = ($cardCount < 100) ? (82000 + $cardCount) : $cardCount;
    }
} catch (\Exception $e) { /* graceful fallback */ }

// Indicative figures — 0% Interest Banking Model with 0.001% Safekeeping & Storage Fee
$accounts = [
    'savings' => ['name'=>'Savings', 'rate'=>'0% Interest', 'rate_note'=>'0.001% custody fee p.a.', 'min'=>'৳500',
        'blurb'=>'An everyday interest-free account. Capital is 100% preserved with a nominal 0.001% annual storage and safekeeping fee.',
        'features'=>['0% interest with complete ethical transparency','Nominal 0.001% annual storage and vault fee','Free debit card in the first year','Instant transfers to any Asha Bank account'],
        'docs'=>['National ID (NID) or smart card','Passport-size photo','Nominee details']],
    'current' => ['name'=>'Current', 'rate'=>'0% Interest', 'rate_note'=>'0.001% custody fee p.a.', 'min'=>'৳5,000',
        'blurb'=>'For traders, freelancers and small businesses who move money often. 0% interest with zero hidden markup.',
        'features'=>['0% interest on business reserves','Unlimited deposits and withdrawals','Cheque book on request','Bulk salary and vendor payments'],
        'docs'=>['NID of account holder','Trade licence (businesses)','TIN certificate','Photo and nominee details']],
    'student' => ['name'=>'Student', 'rate'=>'0% Interest', 'rate_note'=>'0.001% custody fee p.a.', 'min'=>'৳100',
        'blurb'=>'A clean interest-free account for students, with a subsidized 0.001% annual maintenance fee.',
        'features'=>['0% interest — completely student-friendly','Subsidized 0.001% annual storage fee','Fee-free tuition and exam payments','Parent-linked top-up'],
        'docs'=>['Student ID or admission letter','NID or birth certificate','Guardian NID (under 18)']],
    'fd' => ['name'=>'Fixed Deposit', 'rate'=>'0% Interest', 'rate_note'=>'0.001% storage & vault fee', 'min'=>'৳25,000',
        'blurb'=>'Lock in funds for 3, 6 or 12 months with 100% capital preservation and zero interest.',
        'features'=>['100% capital preservation guaranteed','Nominal 0.001% storage and vault fee','Tenures from 3 to 36 months','Secured withdrawal at maturity'],
        'docs'=>['Existing Asha Bank account','NID','Nominee details']],
    'dps' => ['name'=>'DPS', 'rate'=>'0% Interest', 'rate_note'=>'0.001% custody fee p.a.', 'min'=>'৳500 / month',
        'blurb'=>'Save a fixed amount every month with 0% interest and a nominal 0.001% safekeeping fee.',
        'features'=>['Systematic interest-free monthly savings','3, 5 and 10-year term options','Auto-debit from your account','0.001% annual custody fee'],
        'docs'=>['Existing Asha Bank account','NID','Nominee details']],
];

try {
    $dbProducts = $pdo->query("SELECT * FROM ACCOUNTPRODUCT")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($dbProducts as $p) {
        $type = strtolower($p['AccountType']);
        $map = [
            'savings'       => 'savings',
            'current'       => 'current',
            'student'       => 'student',
            'fixed deposit' => 'fd',
            'fdr'           => 'fd',
            'dps'           => 'dps',
        ];
        $key = $map[$type] ?? null;
        if ($key && isset($accounts[$key])) {
            $accounts[$key]['rate'] = '0% Interest';
            $accounts[$key]['rate_note'] = '0.001% custody fee p.a.';
            if ((float)$p['MinBalance'] > 0) {
                $accounts[$key]['min'] = '৳' . number_format((float)$p['MinBalance']);
            }
        }
    }
} catch (\Exception $e) { /* fallback */ }

$loans = [
    ['Personal loan',   '৳10,00,000',   '1–5 years',  '0.0%', '2 working days'],
    ['Home loan',       '৳2,00,00,000', 'Up to 25 yrs','0.0%', '7 working days'],
    ['Car loan',        '৳50,00,000',   '1–7 years',  '0.0%', '5 working days'],
    ['SME loan',        '৳1,00,00,000', '1–5 years',  '0.0%', '7 working days'],
    ['Education loan',  '৳15,00,000',   '1–7 years',  '0.0%', '5 working days'],
];

try {
    $dbLoans = $pdo->query("SELECT * FROM LOAN_PRODUCTS WHERE is_active = 1 ORDER BY loan_product_id")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($dbLoans)) {
        $loans = [];
        foreach ($dbLoans as $l) {
            $maxAmt = (float)$l['max_amount'];
            if ($maxAmt >= 10000000) {
                $maxStr = '৳' . rtrim(rtrim(number_format($maxAmt / 10000000, 2), '0'), '.') . ' crore';
            } elseif ($maxAmt >= 100000) {
                $maxStr = '৳' . rtrim(rtrim(number_format($maxAmt / 100000, 2), '0'), '.') . ' lakh';
            } else {
                $maxStr = '৳' . number_format($maxAmt);
            }
            $minY = round((int)$l['min_tenure_months'] / 12);
            $maxY = round((int)$l['max_tenure_months'] / 12);
            $tenure = ($minY > 0 && $minY !== $maxY) ? "{$minY}–{$maxY} years" : "Up to {$maxY} yrs";
            $loans[] = [
                $l['product_name'],
                $maxStr,
                $tenure,
                number_format((float)$l['interest_rate'], 1) . '%',
                '2–5 working days'
            ];
        }
    }
} catch (\Exception $e) { /* fallback */ }

$rates = [ // currency, buy, sell
    ['USD','US Dollar','121.50','122.50'], ['EUR','Euro','140.20','142.00'], ['GBP','British Pound','160.10','162.60'],
    ['SAR','Saudi Riyal','32.40','32.90'], ['AED','UAE Dirham','33.10','33.60'], ['MYR','Malaysian Ringgit','28.60','29.30'],
    ['INR','Indian Rupee','1.35','1.42'],  ['JPY','Japanese Yen','0.78','0.82'],
];
$transfers = [
    [
        'title'      => 'Asha Bank to Asha Bank',
        'sub'        => 'Account-to-account instant settlement',
        'icon'       => 'bx-bolt-circle',
        'speed'      => 'Instant',
        'is_instant' => true,
        'fee'        => 'Free',
        'limit'      => '৳10,00,000',
        'limit_sub'  => 'per day',
    ],
    [
        'title'      => 'Other banks (NPSB)',
        'sub'        => 'National Payment Switch Bangladesh',
        'icon'       => 'bx-transfer-alt',
        'speed'      => 'Instant',
        'is_instant' => true,
        'fee'        => '৳10',
        'limit'      => '৳5,00,000',
        'limit_sub'  => 'per day',
    ],
    [
        'title'      => 'Other banks (BEFTN)',
        'sub'        => 'Electronic Funds Transfer Network',
        'icon'       => 'bx-refresh',
        'speed'      => 'Next working day',
        'is_instant' => false,
        'fee'        => '৳5',
        'limit'      => '৳50,00,000',
        'limit_sub'  => 'per day',
    ],
    [
        'title'      => 'Large value (RTGS)',
        'sub'        => 'Real-Time Gross Settlement (BB)',
        'icon'       => 'bx-buildings',
        'speed'      => 'Same day',
        'is_instant' => false,
        'fee'        => '৳50',
        'limit'      => 'No upper limit',
        'limit_sub'  => 'high value',
    ],
    [
        'title'      => 'Mobile wallets',
        'sub'        => 'bKash, Nagad, Rocket & Upay',
        'icon'       => 'bx-mobile-alt',
        'speed'      => 'Instant',
        'is_instant' => true,
        'fee'        => 'Free',
        'limit'      => '৳50,000',
        'limit_sub'  => 'per day',
    ],
];
$cities = [
    'Dhaka'      => ['Motijheel (Head Office)','Gulshan','Dhanmondi','Uttara','Mirpur','Savar'],
    'Chattogram' => ['Agrabad','GEC Circle','Pahartali'],
    'Sylhet'     => ['Zindabazar','Amberkhana'],
    'Rajshahi'   => ['Shaheb Bazar','Uposhohor'],
    'Khulna'     => ['Sonadanga','Khalishpur'],
    'Barishal'   => ['Sadar Road'],
    'Rangpur'    => ['Jahaj Company More'],
    'Mymensingh' => ['Ganginarpar'],
];

$branchList = [];
try {
    $dbBranches = $pdo->query("SELECT BranchName, City, Address, Phone FROM BRANCH ORDER BY City, BranchName")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($dbBranches)) {
        foreach ($dbBranches as $br) {
            $city = trim($br['City'] ?? 'Other');
            if (strcasecmp($city, 'Chittagong') === 0) $city = 'Chattogram';
            $branchList[$city][] = [
                'name'    => $br['BranchName'],
                'address' => $br['Address'] ?: ($br['BranchName'] . ', ' . $city),
                'phone'   => $br['Phone'] ?: $bank['hotline'],
            ];
        }
    }
} catch (\Exception $e) { /* fallback */ }

foreach ($cities as $cityName => $bNames) {
    if (!isset($branchList[$cityName])) {
        $branchList[$cityName] = [];
    }
    if (empty($branchList[$cityName])) {
        foreach ($bNames as $bn) {
            $branchList[$cityName][] = [
                'name'    => $bn . ' Branch',
                'address' => $bn . ', ' . $cityName,
                'phone'   => $bank['hotline'],
            ];
        }
    }
}

$bdDivisions = [];
$bdDivFile = __DIR__ . '/assets/bangladesh_divisions.json';
if (file_exists($bdDivFile)) {
    $bdDivisions = json_decode(file_get_contents($bdDivFile), true) ?: [];
}

$faqs = [
    ['How long does it take to open an account?', 'Filling in the online form takes about three minutes. After you upload your NID and a selfie, our team verifies your identity within 24 hours and you can deposit right away.'],
    ['Do I need to visit a branch?', 'Not for a basic savings or student account. Current accounts for businesses, and some loans, need a one-time branch visit to sign documents.'],
    ['What do I need to open an account?', 'A valid NID or smart card, a recent photo or selfie, your mobile number and email, and a nominee. Businesses also need a trade licence and TIN.'],
    ['How do I reset my password?', 'Use "Forgot password" on the sign-in panel. We send a one-time code to your registered mobile number. We never ask for your password or OTP by phone.'],
    ['What should I do if my card is lost or stolen?', 'Freeze it instantly from Cards in your dashboard, then call the hotline at ' . '16999' . ' (24/7) to block and reissue it.'],
    ['Does Asha Bank charge or pay interest?', 'No. Asha Bank operates strictly on a 0% interest model. We do not charge interest on loans, nor do we pay interest on deposits. We apply a transparent, nominal 0.001% annual fee for maintaining, storing, and vault-insuring your funds.'],
    ['What are the fees?', 'Account opening, instant transfers between Asha Bank accounts and bill payments are free. For storing and maintaining money in the bank, a nominal safekeeping fee of 0.001% per year applies. Zero interest is charged.'],
];

$notices = [
    ['Notice','Instant transfers between Asha Bank accounts are now free for everyone.'],
    ['Security','We will never call to ask for your OTP, PIN or password. Hang up and call 16999.'],
    ['Holiday','Branches are closed on government holidays. Digital banking stays available 24/7.'],
];

try {
    $dbNotices = $pdo->query("SELECT title, message, type FROM NOTIFICATIONS WHERE customer_id IS NULL OR customer_id = 0 ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($dbNotices)) {
        $notices = array_map(function($n) {
            return [ucfirst($n['type'] ?: 'Notice'), $n['message'] ?: $n['title']];
        }, $dbNotices);
    }
} catch (\Exception $e) { /* fallback */ }

// Initialize CSRF token
csrfToken();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Asha Bank</title>
<meta name="description" content="Asha Bank is a digital bank for Bangladesh. Open a savings account online, send money instantly, apply for loans and cards, and bank 24/7.">
<meta name="author" content="Asha Bank PLC">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
<style>
/* ═════════ TOKENS (BLACK & WHITE THEME) ═════════ */
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth;scroll-padding-top:84px}
:root{
  --bg:#FFFFFF;
  --bg-2:#FAFAFA;
  --bg-3:#F4F4F5;
  --ink:#09090B;
  --ink-2:#52525B;
  --ink-3:#71717A;
  --line:#E4E4E7;
  --line-2:#D4D4D8;
  --forest:#09090B;
  --forest-2:#18181B;
  --sun:#09090B;
  --sun-2:#27272A;
  --gold:#F6BA42;
  --gold-dark:#EAA023;
  --ok:#16A34A;
  --warn:#D97706;
  --err:#DC2626;
  --display:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
  --body:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
  --ease:cubic-bezier(.16,1,.3,1);
}
[data-theme="dark"]{
  --bg:#09090B;
  --bg-2:#121215;
  --bg-3:#18181B;
  --ink:#FAFAFA;
  --ink-2:#A1A1AA;
  --ink-3:#71717A;
  --line:#27272A;
  --line-2:#3F3F46;
  --forest:#000000;
  --forest-2:#09090B;
  --sun:#FAFAFA;
  --sun-2:#E4E4E7;
}
html,body{font-family:var(--body);background:var(--bg);color:var(--ink);line-height:1.6;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;text-rendering:optimizeLegibility;font-feature-settings:'cv02','cv03','cv04','cv11';overflow-x:hidden!important;width:100%!important;max-width:100%!important;position:relative}
a{color:inherit;text-decoration:none}
img,svg{display:block;max-width:100%}
button,input,select{font:inherit;color:inherit}
:focus-visible{outline:2px solid var(--ink);outline-offset:2px;border-radius:4px}
.wrap{max-width:1180px;width:100%;margin:0 auto;padding:0 24px;box-sizing:border-box}
h1,h2,h3{font-family:var(--display);line-height:1.15;letter-spacing:-.03em;font-feature-settings:'cv02','cv03','cv04','cv11'}
h2{font-size:clamp(28px,3.8vw,40px);font-weight:700;margin-bottom:12px}
h3{font-size:18px;font-weight:600}
.lede{color:var(--ink-2);font-size:16.5px;max-width:56ch}
section{padding:88px 0}
.sec-head{margin-bottom:44px}
.btn{display:inline-flex;align-items:center;gap:8px;padding:12px 22px;border-radius:10px;font-weight:600;font-size:14.5px;border:1.5px solid transparent;cursor:pointer;transition:transform .2s var(--ease),background .2s,border-color .2s,color .2s}
.btn:hover{transform:translateY(-2px)}
.btn-sun{background:#FFFFFF;color:#09090B;border-color:transparent;box-shadow:0 4px 16px rgba(0,0,0,.15)}
.btn-sun:hover{background:#F4F4F5;color:#000000}
.btn-dark{background:#09090B;color:#FFFFFF;border-color:#27272A}
.btn-dark:hover{background:#27272A;border-color:#3F3F46}
.btn-line{border-color:var(--line);background:transparent;color:var(--ink)}
.btn-line:hover{background:var(--bg-3);border-color:var(--line-2)}
.btn-ghost-light{border-color:rgba(255,255,255,.25);color:#FFFFFF;background:transparent}
.btn-ghost-light:hover{background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.4)}
.link{color:var(--ink);font-weight:600;border-bottom:1.5px solid currentColor}
[data-theme="dark"] .link{color:var(--ink)}

/* ═════════ UTILITY BAR ═════════ */
.util{background:#09090B;color:#A1A1AA;font-size:13px;border-bottom:1px solid #1E1E22}
.util .wrap{display:flex;align-items:center;justify-content:space-between;gap:16px;min-height:38px;flex-wrap:wrap}
.util-l,.util-r{display:flex;align-items:center;gap:20px;flex-wrap:wrap}
.util a:hover{color:#FFFFFF}
.util i{margin-right:5px;color:#FAFAFA}
.lang{display:inline-flex;border:1px solid rgba(255,255,255,.18);border-radius:6px;overflow:hidden}
.lang button{background:none;border:0;padding:2px 10px;font-size:12px;cursor:pointer;color:#A1A1AA}
.lang button[aria-pressed="true"]{background:#FFFFFF;color:#09090B;font-weight:700}

/* ═════════ HEADER ═════════ */
.head{position:sticky;top:0;z-index:50;background:color-mix(in srgb,var(--bg) 92%,transparent);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border-bottom:1px solid var(--line)}
.head .wrap{display:flex;align-items:center;gap:16px;height:68px}
.brand{display:flex;align-items:center;gap:10px;font-family:var(--display);font-weight:700;font-size:19px;letter-spacing:-.03em;flex-shrink:0}
.mark{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#F6BA42,#EAA023);display:grid;place-items:center;box-shadow:0 2px 6px rgba(234,160,35,0.2)}
.mark svg{width:21px;height:21px}
.nav{display:flex;gap:4px;margin:0 4px;flex:1;align-items:center;justify-content:center}
.nav a{padding:8px 10px;border-radius:8px;font-size:13.5px;font-weight:500;color:var(--ink-2);white-space:nowrap;line-height:1.2;transition:background .2s,color .2s;display:inline-flex;align-items:center;gap:6px}
.nav a:hover{background:var(--bg-3);color:var(--ink)}
.nav a i{display:none}
.nav-mobile-foot{display:none}
.head-act{display:flex;gap:8px;align-items:center;flex-shrink:0}
.head-act .btn{height:40px;padding:0 18px;font-size:14px;font-weight:600;border-radius:10px;border-width:1px;display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;white-space:nowrap;line-height:1}
.head-act .btn:hover{transform:none}
.head-act .icon-btn{width:40px;height:40px;border-radius:10px;border:1px solid var(--line);background:transparent;color:var(--ink);cursor:pointer;font-size:18px;display:inline-flex;align-items:center;justify-content:center;box-sizing:border-box;flex-shrink:0;transition:background .2s,border-color .2s}
.head-act .icon-btn:hover{background:var(--bg-3);border-color:var(--line-2)}

/* Burger Button: STRICTLY hidden on desktop */
.burger{display:none!important;width:40px;height:40px;border-radius:10px;border:1px solid var(--line);background:var(--bg);color:var(--ink);cursor:pointer;align-items:center;justify-content:center;font-size:20px;position:relative;flex-shrink:0;transition:all .2s cubic-bezier(.16,1,.3,1);-webkit-tap-highlight-color:transparent}
.burger:hover{background:var(--bg-3);border-color:var(--line-2)}
.burger:active{transform:scale(0.94)}
.burger.active{background:var(--bg-3);border-color:var(--ink);color:var(--ink)}
.burger i{transition:transform .2s ease,opacity .15s ease}

/* Full Page Blur Backdrop: strictly lower than navbar (z-index: 45 vs navbar z-index: 50) */
.nav-backdrop{position:fixed;inset:0;width:100%;height:100%;background:rgba(9,9,11,0.48);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);z-index:45;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .28s cubic-bezier(.16,1,.3,1),visibility .28s ease}
.nav-backdrop.open{opacity:1;visibility:visible;pointer-events:auto}
[data-theme="dark"] .nav-backdrop{background:rgba(0,0,0,0.68)}
body.nav-locked{overflow:hidden!important;touch-action:none}

/* ═════════ HERO ═════════ */
.hero{background:#09090B;color:#FFFFFF;padding:72px 0 0;position:relative;overflow:hidden;isolation:isolate;border-bottom:1px solid #1E1E22}
.hero .wrap{display:grid;grid-template-columns:1.15fr .85fr;gap:56px;align-items:center;padding-bottom:96px}
.hero h1{font-size:clamp(38px,5.2vw,64px);font-weight:800;letter-spacing:-.035em;margin-bottom:20px}
.hero .lede{color:#A1A1AA;margin-bottom:32px}
.hero-cta{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:36px}
.assure{display:grid;gap:10px;font-size:14px;color:#A1A1AA;list-style:none}
.assure li{display:flex;gap:10px;align-items:center}
.assure i{color:#FFFFFF;font-size:18px}

/* ═════════ HERO VISUAL (FINTECH CARD SHOWCASE) ═════════ */
.hero-visual{position:relative;display:flex;flex-direction:column;gap:18px;max-width:440px;margin-left:auto;width:100%;box-sizing:border-box}
.hero-card{
  background:linear-gradient(135deg,#18181B 0%,#09090B 100%);
  border:1px solid rgba(255,255,255,0.18);
  border-radius:24px;
  padding:28px;
  color:#FFFFFF;
  box-shadow:0 30px 60px -15px rgba(0,0,0,0.5),0 0 0 1px rgba(255,255,255,0.06);
  position:relative;
  overflow:hidden;
  transition:transform .3s var(--ease);
  width:100%;box-sizing:border-box;
}
.hero-card:hover{transform:translateY(-4px)}
.hero-card::after{
  content:"";
  position:absolute;
  right:-20%;
  top:-30%;
  width:260px;
  height:260px;
  border-radius:50%;
  border:32px solid rgba(255,255,255,0.04);
  pointer-events:none;
}
.hc-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:34px}
.hc-brand{font-size:17px;font-weight:700;letter-spacing:-0.02em;display:flex;align-items:center;gap:8px}
.hc-chip{width:36px;height:26px;border-radius:5px;background:linear-gradient(135deg,#E4E4E7,#A1A1AA);border:1px solid rgba(255,255,255,0.4);position:relative}
.hc-chip::after{content:"";position:absolute;inset:4px;border:1px solid rgba(0,0,0,0.25);border-radius:3px}
.hc-num{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:18px;letter-spacing:0.18em;margin-bottom:28px;color:#F4F4F5}
.hc-foot{display:flex;justify-content:space-between;align-items:flex-end}
.hc-label{font-size:11px;text-transform:uppercase;color:#A1A1AA;letter-spacing:0.06em;margin-bottom:3px}
.hc-val{font-size:13.5px;font-weight:600;letter-spacing:0.02em;color:#FFFFFF}
.hc-type{font-size:16px;font-weight:800;letter-spacing:-0.02em;font-style:italic}

.hero-pill{
  background:rgba(24,24,27,0.85);
  backdrop-filter:blur(16px);
  border:1px solid rgba(255,255,255,0.12);
  border-radius:16px;
  padding:14px 18px;
  color:#FFFFFF;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:16px;
  box-shadow:0 12px 32px rgba(0,0,0,0.3);
  width:100%;box-sizing:border-box;min-width:0;
}
.hero-pill-left{display:flex;align-items:center;gap:12px}
.hero-pill-icon{width:38px;height:38px;border-radius:10px;background:#27272A;display:grid;place-items:center;font-size:20px;color:#22C55E}
.hero-pill-title{font-size:13.5px;font-weight:600}
.hero-pill-sub{font-size:12px;color:#A1A1AA}
.hero-pill-amt{font-size:15px;font-weight:700;color:#22C55E;font-variant-numeric:tabular-nums;text-align:right}
.hero-pill-time{font-size:11px;color:#71717A}

/* ═════════ QUICK ACTIONS ═════════ */
.quick{padding:0;margin-top:-52px;position:relative;z-index:2}
.quick-grid{display:grid;grid-template-columns:repeat(6,1fr);background:var(--bg);border:1px solid var(--line);border-radius:18px;box-shadow:0 18px 40px rgba(0,0,0,.06);overflow:hidden}
.quick-grid a{padding:22px 14px;text-align:center;border-right:1px solid var(--line);font-weight:600;font-size:14px;display:flex;flex-direction:column;align-items:center;gap:8px;transition:background .2s}
.quick-grid a:last-child{border-right:0}
.quick-grid a:hover{background:var(--bg-2)}
.quick-grid i{font-size:26px;color:var(--ink)}
.quick-grid a.urgent i{color:var(--err)}

/* ═════════ STATS ═════════ */
.stats{padding:56px 0 0}
.stats-row{display:flex;justify-content:space-between;gap:24px;flex-wrap:wrap;border-bottom:1px solid var(--line);padding-bottom:40px}
.stat b{display:block;font-family:var(--display);font-size:36px;font-weight:800;letter-spacing:-.03em}
.stat span{color:var(--ink-3);font-size:14px}

/* ═════════ ACCOUNTS ═════════ */
.acc-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:24px}
.acc-tabs button{padding:10px 18px;border-radius:99px;border:1.5px solid var(--line);background:none;font-weight:600;font-size:14px;cursor:pointer}
.acc-tabs button[aria-selected="true"]{background:#09090B;border-color:#09090B;color:#FFFFFF}
[data-theme="dark"] .acc-tabs button[aria-selected="true"]{background:#FAFAFA;border-color:#FAFAFA;color:#09090B}
.acc-panel{display:none;grid-template-columns:.9fr 1.1fr;gap:0;border:1px solid var(--line);border-radius:20px;overflow:hidden}
.acc-panel.on{display:grid}
.acc-rate{background:var(--bg-2);padding:40px;display:flex;flex-direction:column;justify-content:space-between;gap:28px;border-right:1px solid var(--line)}
.rate-stack{display:flex;flex-direction:column;align-items:flex-start;line-height:1}
.rate-num{font-family:var(--display);font-size:clamp(54px,7vw,84px);font-weight:900;letter-spacing:-.04em;line-height:.92;color:var(--ink)}
.rate-text{font-family:var(--display);font-size:clamp(20px,2.4vw,28px);font-weight:800;letter-spacing:-.02em;line-height:1.15;color:var(--ink);margin-top:6px}
.acc-rate .big{font-family:var(--display);font-size:clamp(38px,5vw,54px);font-weight:800;letter-spacing:-.03em;line-height:1}
.acc-rate small{display:block;color:var(--ink-3);margin-top:10px;font-size:14px;font-weight:500}
.acc-min{font-size:14px;color:var(--ink-2)}
.acc-min b{font-size:20px;font-family:var(--display);display:block;color:var(--ink)}
.acc-body{padding:40px}
.acc-body p{color:var(--ink-2);margin-bottom:22px}
.cols2{display:grid;grid-template-columns:1fr 1fr;gap:28px;margin-bottom:28px}
.cols2 h4{font-size:13.5px;color:var(--ink-3);font-weight:600;margin-bottom:10px}
ul.tick{list-style:none;display:grid;gap:9px;font-size:14.5px}
ul.tick li{display:flex;gap:9px}
ul.tick i{color:var(--ink);font-size:18px;flex:none;margin-top:2px}
ul.doc i{color:var(--ink-3)}

/* ═════════ CARDS ═════════ */
.cards-sec{background:var(--bg-2)}
.cards-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:28px}
.pcard{aspect-ratio:1.586;border-radius:18px;padding:22px;color:#fff;display:flex;flex-direction:column;justify-content:space-between;position:relative;overflow:hidden;margin-bottom:20px;box-shadow:0 16px 34px rgba(0,0,0,.15)}
.pcard::after{content:"";position:absolute;right:-30%;top:-40%;width:90%;aspect-ratio:1;border-radius:50%;border:26px solid rgba(255,255,255,.06)}
.pc-debit{background:linear-gradient(135deg,#18181B 0%,#09090B 100%);border:1px solid rgba(255,255,255,.14)}
.pc-virtual{background:linear-gradient(135deg,#27272A 0%,#18181B 100%);border:1px solid rgba(255,255,255,.12)}
.pc-credit{background:linear-gradient(135deg,#FFFFFF 0%,#F4F4F5 100%);color:#09090B;border:1px solid #E4E4E7}
[data-theme="dark"] .pc-credit{background:linear-gradient(135deg,#3F3F46 0%,#27272A 100%);color:#FAFAFA;border-color:#52525B}
.pcard .t{display:flex;justify-content:space-between;font-weight:700;font-size:15px}
.pcard .n{font-family:ui-monospace,monospace;letter-spacing:.14em;font-size:15px;opacity:.85}
.pcard .b{display:flex;justify-content:space-between;font-size:12px;opacity:.8}
.cards-grid h3{margin-bottom:8px}
.cards-grid p{color:var(--ink-2);font-size:15px;margin-bottom:12px}
.fee{font-size:13.5px;color:var(--ink-3);margin-top:12px}

/* ═════════ LOANS + TOOLS ═════════ */
.table-wrap{overflow-x:auto;border:1px solid var(--line);border-radius:16px}
table{width:100%;border-collapse:collapse;font-size:15px;min-width:560px}
th,td{padding:14px 18px;text-align:left;border-bottom:1px solid var(--line)}
th{background:var(--bg-2);font-size:13px;color:var(--ink-3);font-weight:600;letter-spacing:.02em}
tr:last-child td{border-bottom:0}
td.num,th.num{text-align:right;font-variant-numeric:tabular-nums}
.tools{background:var(--bg-2)}
.tool-box{display:grid;grid-template-columns:1.2fr .8fr;border:1px solid var(--line);border-radius:24px;background:var(--bg);box-shadow:0 14px 32px -10px rgba(0,0,0,0.06);overflow:hidden}
.tool-in{padding:40px;display:flex;flex-direction:column;gap:20px;background:var(--bg)}
.field{display:flex;flex-direction:column;gap:8px}
.field label{display:flex;justify-content:space-between;align-items:center;font-size:13px;font-weight:600;color:var(--ink-2);letter-spacing:-0.01em}
.field-hint{font-size:11.5px;color:var(--ink-3);font-weight:500}
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.input-box{position:relative;display:flex;align-items:center}
.input-box input,.input-box select{
  width:100%;height:48px;padding:0 52px 0 16px;
  background:var(--bg-2);border:1.5px solid var(--line);border-radius:12px;
  font-size:15.5px;font-weight:600;color:var(--ink);
  transition:border-color .2s,box-shadow .2s,background .2s;
  box-sizing:border-box;font-family:inherit;outline:none;
}
.input-box input:focus,.input-box select:focus{
  border-color:var(--ink);background:var(--bg);
  box-shadow:0 0 0 3px rgba(9,9,11,0.08);
}
[data-theme="dark"] .input-box input:focus,[data-theme="dark"] .input-box select:focus{
  border-color:#FAFAFA;
  box-shadow:0 0 0 3px rgba(250,250,250,0.12);
}
.input-box .suffix{
  position:absolute;right:16px;font-size:12.5px;font-weight:600;
  color:var(--ink-3);pointer-events:none;text-transform:uppercase;letter-spacing:0.02em;
}
.slider-wrap{margin-top:-6px;display:flex;flex-direction:column;gap:6px}
.range{
  -webkit-appearance:none;appearance:none;width:100%;height:6px;
  border-radius:99px;background:var(--line);outline:none;cursor:pointer;
  transition:background .2s;
}
.range::-webkit-slider-thumb{
  -webkit-appearance:none;appearance:none;width:22px;height:22px;
  border-radius:50%;background:#09090B;border:2.5px solid #FFFFFF;
  box-shadow:0 2px 8px rgba(0,0,0,0.25);cursor:pointer;
  transition:transform .15s var(--ease),box-shadow .15s;
}
.range::-webkit-slider-thumb:hover{
  transform:scale(1.15);box-shadow:0 4px 12px rgba(0,0,0,0.35);
}
[data-theme="dark"] .range::-webkit-slider-thumb{
  background:#FAFAFA;border-color:#18181B;box-shadow:0 2px 8px rgba(255,255,255,0.2);
}
.range::-moz-range-thumb{
  width:22px;height:22px;border-radius:50%;background:#09090B;
  border:2.5px solid #FFFFFF;box-shadow:0 2px 8px rgba(0,0,0,0.25);cursor:pointer;
}
[data-theme="dark"] .range::-moz-range-thumb{
  background:#FAFAFA;border-color:#18181B;
}
.range-limits{display:flex;justify-content:space-between;font-size:11.5px;color:var(--ink-3);font-weight:500}
.tool-out{background:#09090B;color:#fff;padding:40px;display:flex;flex-direction:column;justify-content:center;gap:6px;border-left:1px solid #1E1E22}
.tool-out .lab{color:#A1A1AA;font-size:14px}
.tool-out .res{font-family:var(--display);font-size:clamp(34px,4.6vw,50px);font-weight:800;letter-spacing:-.03em;line-height:1.05}
.tool-out dl{display:grid;grid-template-columns:1fr auto;gap:8px 20px;margin-top:22px;padding-top:18px;border-top:1px solid rgba(255,255,255,.15);font-size:14.5px}
.tool-out dt{color:#A1A1AA}
.tool-out dd{font-weight:600;text-align:right}
.tool-tabs{display:flex;gap:6px;margin-bottom:22px;flex-wrap:wrap}
.tool-tabs button{padding:9px 16px;border-radius:99px;border:1.5px solid var(--line);background:var(--bg);font-weight:600;cursor:pointer;font-size:14px}
.tool-tabs button[aria-selected="true"]{background:#09090B;color:#fff;border-color:#09090B}
[data-theme="dark"] .tool-tabs button[aria-selected="true"]{background:#FAFAFA;color:#09090B;border-color:#FAFAFA}
.hide{display:none!important}

/* ═════════ DIGITAL SECTION (TOP ROW FLEX CARDS + BOTTOM ROW GRID TABLE) ═════════ */
.dig-intro{margin-bottom:36px}
.feat-flex{display:flex;flex-wrap:wrap;gap:16px;align-items:stretch}
.feat-card{
  flex:1 1 200px;background:var(--bg);border:1px solid var(--line);border-radius:18px;
  padding:22px 20px;display:flex;flex-direction:column;gap:14px;
  transition:transform .2s var(--ease),border-color .2s,box-shadow .2s;
}
.feat-card:hover{transform:translateY(-2px);border-color:var(--ink);box-shadow:0 8px 24px -8px rgba(0,0,0,0.08)}
[data-theme="dark"] .feat-card:hover{border-color:#FAFAFA;box-shadow:0 8px 24px -8px rgba(0,0,0,0.4)}
.feat-card .feat-icon{width:44px;height:44px;border-radius:12px;background:var(--bg-3);display:grid;place-items:center;font-size:22px;color:var(--ink);flex-shrink:0}
.feat-card h3{font-size:15px;font-weight:700;color:var(--ink);margin-bottom:4px;letter-spacing:-0.01em}
.feat-card p{font-size:13px;color:var(--ink-2);line-height:1.45;margin:0}

/* Transfer Rails Grid Table Style */
.transfer-box{background:var(--bg);border:1px solid var(--line);border-radius:24px;padding:32px;box-shadow:0 16px 36px -12px rgba(0,0,0,0.06);position:relative;overflow:hidden}
[data-theme="dark"] .transfer-box{background:#121215;border-color:#27272A;box-shadow:0 20px 40px -15px rgba(0,0,0,0.5)}
.tb-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:22px;padding-bottom:18px;border-bottom:1px solid var(--line)}
.tb-title{font-family:var(--display);font-size:22px;font-weight:700;letter-spacing:-0.025em;margin-bottom:4px;color:var(--ink)}
.tb-sub{font-size:13px;color:var(--ink-3)}
.tb-live-badge{display:inline-flex;align-items:center;gap:7px;padding:4px 11px;border-radius:99px;background:var(--bg-3);border:1px solid var(--line);font-size:12px;font-weight:600;color:var(--ink-2);white-space:nowrap}
.live-dot{width:7px;height:7px;border-radius:50%;background:#22C55E;box-shadow:0 0 8px rgba(34,197,94,0.6);animation:livePulse 2s infinite ease-in-out}
@keyframes livePulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:0.4;transform:scale(0.85)}}

.tb-table-wrap{width:100%;overflow-x:auto}
.tb-table-header{
  display:grid;grid-template-columns:2.4fr 1.2fr 1fr 1.4fr;gap:20px;
  padding:12px 18px;background:var(--bg-2);border-radius:12px;
  font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;
  color:var(--ink-3);margin-bottom:8px;
}
.tb-table-body{display:flex;flex-direction:column;gap:8px}
.tb-table-row{
  display:grid;grid-template-columns:2.4fr 1.2fr 1fr 1.4fr;gap:20px;
  align-items:center;padding:16px 18px;background:var(--bg-2);
  border:1px solid var(--line);border-radius:14px;
  transition:transform .2s var(--ease),border-color .2s,background-color .2s,box-shadow .2s;
}
.tb-table-row:hover{
  transform:translateY(-2px);border-color:var(--ink);background:var(--bg);
  box-shadow:0 8px 20px -6px rgba(0,0,0,0.07);
}
[data-theme="dark"] .tb-table-row:hover{border-color:#FAFAFA;box-shadow:0 8px 24px -6px rgba(0,0,0,0.4)}
.tb-table-row .col-channel{display:flex;align-items:center;gap:14px}
.t-rail-icon{width:42px;height:42px;border-radius:12px;background:var(--bg);border:1px solid var(--line);display:grid;place-items:center;font-size:20px;color:var(--ink);flex-shrink:0;transition:background .2s,color .2s}
[data-theme="dark"] .t-rail-icon{background:#18181B;border-color:#27272A}
.tb-table-row:hover .t-rail-icon{background:var(--ink);color:var(--bg)}
.t-rail-name{font-weight:700;font-size:14.5px;color:var(--ink);letter-spacing:-0.01em;margin-bottom:2px}
.t-rail-desc{font-size:12px;color:var(--ink-3);line-height:1.35}

.tb-table-row .col-speed{display:flex;align-items:center}
.t-speed-pill{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:600;color:var(--ink)}
.t-speed-pill.speed-instant{color:#16A34A}
[data-theme="dark"] .t-speed-pill.speed-instant{color:#4ADE80}
.speed-dot{width:6px;height:6px;border-radius:50%;background:currentColor}

.tb-table-row .col-fee{display:flex;align-items:center}
.t-fee-pill{font-size:13.5px;font-weight:700;color:var(--ink);font-variant-numeric:tabular-nums}
.t-fee-pill.fee-free{display:inline-block;padding:2px 8px;border-radius:6px;background:var(--ink);color:var(--bg);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;width:fit-content}
[data-theme="dark"] .t-fee-pill.fee-free{background:#FAFAFA;color:#09090B}

.tb-table-row .col-limit{text-align:right}
.t-limit-val{font-family:var(--display);font-size:14px;font-weight:700;color:var(--ink);letter-spacing:-0.01em;font-variant-numeric:tabular-nums}
.t-limit-sub{font-size:11px;color:var(--ink-3)}

.tb-foot{display:flex;justify-content:space-between;align-items:center;gap:14px;margin-top:22px;padding-top:18px;border-top:1px solid var(--line);flex-wrap:wrap}
.tb-foot-info{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--ink-3);max-width:56ch}
.tb-foot-info i{font-size:19px;color:var(--ink);flex-shrink:0}
.tb-foot-link{display:inline-flex;align-items:center;gap:4px;font-size:13px;font-weight:700;color:var(--ink);transition:gap .2s}
.tb-foot-link:hover{gap:8px;text-decoration:underline}

@media (max-width:820px){
  .tb-table-header{display:none}
  .tb-table-row{display:flex;flex-direction:column;align-items:stretch;gap:12px;padding:16px}
  .tb-table-row .col-channel{width:100%}
  .tb-table-row .col-speed,.tb-table-row .col-fee,.tb-table-row .col-limit{
    display:flex;justify-content:space-between;align-items:center;text-align:right;
    border-top:1px dashed var(--line);padding-top:8px;
  }
  .tb-table-row .col-speed::before{content:"Speed";font-size:11px;font-weight:600;color:var(--ink-3);text-transform:uppercase}
  .tb-table-row .col-fee::before{content:"Fee";font-size:11px;font-weight:600;color:var(--ink-3);text-transform:uppercase}
  .tb-table-row .col-limit::before{content:"Daily Limit";font-size:11px;font-weight:600;color:var(--ink-3);text-transform:uppercase}
}

/* ═════════ RATES ═════════ */
.rates-sec{background:var(--bg-2);overflow:hidden}
.rates-grid{display:grid;grid-template-columns:minmax(0, 1.25fr) minmax(0, .75fr);gap:28px;align-items:start;width:100%;max-width:100%;box-sizing:border-box}
.rates-grid > *{min-width:0;max-width:100%;width:100%;box-sizing:border-box}
.rates-sec .table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;width:100%;max-width:100%;box-sizing:border-box}
.rates-sec table{min-width:320px;width:100%}
.conv{
  background:var(--bg);border:1px solid var(--line);border-radius:24px;
  padding:32px;box-shadow:0 12px 28px -10px rgba(0,0,0,0.06);
  display:flex;flex-direction:column;gap:18px;position:relative;
  width:100%;max-width:100%;box-sizing:border-box;min-width:0;
}
[data-theme="dark"] .conv{box-shadow:0 16px 36px -12px rgba(0,0,0,0.4)}
.conv-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:4px;padding-bottom:14px;border-bottom:1px solid var(--line);width:100%;box-sizing:border-box}
.conv-icon{width:40px;height:40px;border-radius:11px;background:var(--bg-3);display:grid;place-items:center;font-size:20px;color:var(--ink);flex-shrink:0}
.conv .field{width:100%;box-sizing:border-box;min-width:0}
.conv .input-box{width:100%;box-sizing:border-box;min-width:0;position:relative}
.conv .input-box input,.conv .input-box select{width:100%;box-sizing:border-box;min-width:0;max-width:100%}
.conv .input-box select{text-overflow:ellipsis;white-space:nowrap;overflow:hidden}
.conv-res{
  margin-top:6px;padding:22px 20px;background:var(--bg-2);border-radius:16px;
  border:1px solid var(--line);display:flex;flex-direction:column;gap:6px;
  transition:background .2s,border-color .2s;
  width:100%;box-sizing:border-box;min-width:0;
}
[data-theme="dark"] .conv-res{background:#18181B;border-color:#27272A}
.conv-res-sub{font-size:11.5px;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--ink-3)}
.conv-res-val{font-family:var(--display);font-size:clamp(26px,4vw,36px);font-weight:800;color:var(--ink);letter-spacing:-0.03em;line-height:1.1;font-variant-numeric:tabular-nums;word-break:break-word}
.conv-res-meta{display:flex;justify-content:space-between;align-items:center;margin-top:8px;padding-top:12px;border-top:1px dashed var(--line);flex-wrap:wrap;gap:8px;width:100%;box-sizing:border-box}
.conv-rate-tag{display:inline-flex;align-items:center;padding:3px 9px;border-radius:6px;background:var(--bg-3);font-size:12px;font-weight:600;color:var(--ink);white-space:nowrap}


/* ═════════ REMITTANCE REDESIGN ═════════ */
.remit{display:grid;grid-template-columns:1fr 1.05fr;gap:52px;align-items:start}
@media (max-width: 960px){
  .remit{grid-template-columns:1fr;gap:36px}
}

.remit-badge{
  display:inline-flex;align-items:center;gap:7px;
  padding:5px 12px;border-radius:999px;
  background:var(--bg-3);border:1px solid var(--line);
  font-size:12px;font-weight:600;letter-spacing:0.02em;
  color:var(--ink);margin-bottom:16px;
}
.remit-badge i{font-size:15px;color:var(--ink)}

.remit-steps-list{
  display:flex;flex-direction:column;gap:14px;margin-top:20px;
}
.remit-step-card{
  display:flex;gap:16px;align-items:flex-start;
  padding:18px 20px;border-radius:16px;
  background:var(--bg-2);border:1px solid var(--line);
  transition:all .2s ease;
}
.remit-step-card:hover{
  border-color:var(--ink-3);
  transform:translateY(-1px);
}
.rsc-num{
  flex-shrink:0;width:34px;height:34px;border-radius:10px;
  background:var(--ink);color:var(--bg);
  display:grid;place-items:center;
  font-size:13px;font-weight:800;font-family:var(--display);
  letter-spacing:-0.02em;
}
[data-theme="dark"] .rsc-num{background:#FAFAFA;color:#09090B}
.rsc-content{flex:1;min-width:0}
.rsc-content h3{font-size:16px;font-weight:700;margin-bottom:4px;color:var(--ink);letter-spacing:-0.01em}
.rsc-content p{font-size:13.5px;color:var(--ink-2);line-height:1.45;margin:0}
.rsc-tag{
  display:inline-flex;align-items:center;gap:5px;margin-top:8px;
  padding:3px 9px;border-radius:6px;
  background:var(--bg-3);font-size:11.5px;font-weight:600;
  color:var(--ink-2);
}

/* Remittance Instructions Card (Right Column) */
.remit-card{
  background:var(--bg-2);
  border:1px solid var(--line);
  border-radius:20px;
  padding:26px 28px;
  box-shadow:0 12px 36px -12px rgba(0,0,0,0.06);
  position:relative;
  transition:border-color .2s;
}
[data-theme="dark"] .remit-card{
  box-shadow:0 12px 36px -12px rgba(0,0,0,0.5);
}

.remit-card-head{
  display:flex;justify-content:space-between;align-items:flex-start;
  gap:16px;padding-bottom:18px;border-bottom:1px solid var(--line);
}
.rc-title-wrap h3{
  font-size:18px;font-weight:700;letter-spacing:-0.02em;
  color:var(--ink);margin-bottom:3px;
}
.rc-title-wrap p{
  font-size:12.5px;color:var(--ink-3);margin:0;
}
.rc-live-badge{
  display:inline-flex;align-items:center;gap:6px;
  padding:4px 10px;border-radius:999px;
  background:var(--bg-3);border:1px solid var(--line);
  font-size:11px;font-weight:700;letter-spacing:0.04em;
  text-transform:uppercase;color:var(--ink);
  flex-shrink:0;
}
.rc-dot{
  width:7px;height:7px;border-radius:50%;
  background:#10B981;
  box-shadow:0 0 0 3px rgba(16,185,129,0.2);
  animation:rcPulse 2s infinite ease-in-out;
}
@keyframes rcPulse{
  0%, 100%{transform:scale(1);opacity:1}
  50%{transform:scale(1.2);opacity:.7}
}

.remit-card-list{
  display:flex;flex-direction:column;gap:0;
  margin-top:6px;
}
.remit-card-row{
  display:flex;justify-content:space-between;align-items:center;
  gap:14px;padding:13px 0;border-bottom:1px solid var(--line);
}
.remit-card-row:last-child{border-bottom:0}
.rc-label{
  font-size:12px;font-weight:600;text-transform:uppercase;
  letter-spacing:0.04em;color:var(--ink-3);flex:none;
}
.rc-val-group{
  display:flex;align-items:center;gap:8px;
  text-align:right;min-width:0;
}
.rc-val{
  font-size:14px;font-weight:700;color:var(--ink);
  letter-spacing:-0.01em;word-break:break-word;
}
.rc-val.mono{
  font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;
  letter-spacing:0.04em;font-size:14px;font-weight:700;
  background:var(--bg-3);padding:3px 8px;border-radius:6px;
  border:1px solid var(--line);
}
.rc-btn-copy{
  display:inline-flex;align-items:center;gap:4px;
  background:transparent;border:1px solid var(--line);
  padding:4px 9px;border-radius:7px;
  font-size:11.5px;font-weight:600;color:var(--ink-2);
  cursor:pointer;transition:all .15s ease;
  white-space:nowrap;
}
.rc-btn-copy:hover{
  background:var(--ink);color:var(--bg);border-color:var(--ink);
}
.rc-btn-copy.copied{
  background:#10B981;color:#fff;border-color:#10B981;
}

.remit-card-footer{
  margin-top:16px;padding-top:16px;
  border-top:1px dashed var(--line);
  display:flex;flex-direction:column;gap:12px;
}
.rc-security-tip{
  display:flex;align-items:flex-start;gap:9px;
  font-size:12px;color:var(--ink-3);line-height:1.45;
}
.rc-security-tip i{
  font-size:16px;color:var(--ink-2);flex-shrink:0;margin-top:1px;
}
.rc-action-row{
  display:flex;align-items:center;justify-content:space-between;
  gap:12px;flex-wrap:wrap;
}
.rc-action-row a{
  font-size:13px;font-weight:600;color:var(--ink);
  display:inline-flex;align-items:center;gap:5px;
  text-decoration:none;transition:color .15s;
}
.rc-action-row a:hover{
  text-decoration:underline;
}

/* ═════════ SECURITY ═════════ */
.sec-band{background:#09090B;color:#fff;border-top:1px solid #1E1E22;border-bottom:1px solid #1E1E22}
.sec-band .lede{color:#A1A1AA}
.sec-grid{display:grid;grid-template-columns:1fr 1fr;gap:56px}
.rule-list{display:grid;gap:14px;list-style:none;margin-top:8px}
.rule-list li{display:flex;gap:12px;padding:16px 18px;border-radius:14px;background:#18181B;border:1px solid #27272A;font-size:15px}
.rule-list i{font-size:22px;color:#FAFAFA;flex:none}
.rule-list b{display:block}
.alert-box{background:#18181B;color:#FFFFFF;border:1px solid rgba(255,255,255,.16);border-radius:18px;padding:26px;margin-top:8px}
.alert-box h3{margin-bottom:8px}
.alert-box a{font-weight:700;text-decoration:underline;color:#FFFFFF}

/* ═════════ OPEN ACCOUNT REDESIGN ═════════ */
.open-wrap{display:flex;flex-direction:column;gap:36px}
.open-head{max-width:700px;margin-bottom:20px}
.open-pill{
  display:inline-flex;align-items:center;gap:7px;
  padding:5px 12px;border-radius:999px;
  background:var(--bg-3);border:1px solid var(--line);
  font-size:12px;font-weight:600;letter-spacing:0.02em;
  color:var(--ink);margin-bottom:14px;
}
.open-pill i{font-size:15px;color:var(--ink)}

/* Row 1: Steps in a Row Grid Box */
.open-steps-grid{
  display:grid;grid-template-columns:repeat(3, 1fr);gap:20px;
}
@media (max-width: 900px){
  .open-steps-grid{grid-template-columns:1fr;gap:16px}
}
.open-step-box{
  background:var(--bg-2);border:1px solid var(--line);
  border-radius:18px;padding:26px;display:flex;
  flex-direction:column;gap:14px;transition:all .2s ease;
  position:relative;overflow:hidden;
}
.open-step-box:hover{
  border-color:var(--ink-3);
  transform:translateY(-2px);
  box-shadow:0 10px 24px -10px rgba(0,0,0,0.06);
}
[data-theme="dark"] .open-step-box:hover{
  box-shadow:0 10px 24px -10px rgba(0,0,0,0.4);
}
.osb-header{
  display:flex;justify-content:space-between;align-items:center;
}
.osb-num{
  width:36px;height:36px;border-radius:50%;
  background:var(--ink);color:var(--bg);
  display:grid;place-items:center;font-weight:800;
  font-family:var(--display);font-size:14px;
}
[data-theme="dark"] .osb-num{background:#FAFAFA;color:#09090B}
.osb-time{
  display:inline-flex;align-items:center;gap:4px;
  font-size:11.5px;font-weight:600;color:var(--ink-3);
  background:var(--bg-3);padding:3px 9px;border-radius:999px;
  border:1px solid var(--line);
}
.open-step-box h3{
  font-size:17px;font-weight:700;color:var(--ink);letter-spacing:-0.01em;margin:0;
}
.open-step-box p{
  font-size:14px;color:var(--ink-2);line-height:1.5;margin:0;
}

/* Row 2: Checklist + Abroad Photo Card Grid */
.open-bottom-grid{
  display:grid;grid-template-columns:1.05fr .95fr;gap:28px;align-items:stretch;
}
@media (max-width: 900px){
  .open-bottom-grid{grid-template-columns:1fr;gap:24px}
}

.checklist-box{
  background:var(--bg-2);border:1px solid var(--line);
  border-radius:20px;padding:30px;display:flex;
  flex-direction:column;justify-content:space-between;gap:20px;
}
.cl-head h3{
  font-size:20px;font-weight:700;color:var(--ink);
  letter-spacing:-0.02em;margin-bottom:6px;
}
.cl-head p{
  font-size:13.5px;color:var(--ink-2);margin:0;
}
.cl-list{
  list-style:none;padding:0;margin:0;
  display:flex;flex-direction:column;gap:8px;
}
.cl-list li{
  display:flex;align-items:flex-start;gap:12px;
  font-size:13.5px;color:var(--ink-2);line-height:1.45;
  padding:8px 0;border-bottom:1px solid var(--line);
}
.cl-list li:last-child{border-bottom:0}
.cl-list li i{
  font-size:18px;color:var(--ink);flex-shrink:0;margin-top:1px;
}
.cl-actions{
  display:flex;flex-direction:column;gap:10px;
}
.cl-actions .btn{
  width:100%;height:46px;display:inline-flex;
  align-items:center;justify-content:center;gap:8px;
  font-size:14.5px;font-weight:700;border-radius:12px;
}
.cl-note{
  font-size:12px;color:var(--ink-3);display:flex;
  align-items:center;justify-content:center;gap:5px;
}

/* Abroad Photo Card */
.abroad-card{
  border-radius:20px;overflow:hidden;
  border:1px solid var(--line);position:relative;
  background:var(--bg-2);display:flex;min-height:380px;
}
.abroad-img{
  position:absolute;inset:0;width:100%;height:100%;
  object-fit:cover;object-position:center top;
  transition:transform .4s ease;
}
.abroad-card:hover .abroad-img{
  transform:scale(1.03);
}
.abroad-overlay{
  position:absolute;inset:0;
  background:linear-gradient(180deg, rgba(9,9,11,0.05) 0%, rgba(9,9,11,0.3) 45%, rgba(9,9,11,0.88) 100%);
  display:flex;flex-direction:column;justify-content:flex-end;
  padding:28px 30px;color:#FFFFFF;z-index:2;
}
.abroad-tag{
  align-self:flex-start;display:inline-flex;align-items:center;gap:6px;
  padding:4px 11px;border-radius:999px;
  background:rgba(255,255,255,0.2);
  backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
  border:1px solid rgba(255,255,255,0.3);
  font-size:11.5px;font-weight:700;text-transform:uppercase;
  letter-spacing:0.04em;color:#FFFFFF;margin-bottom:10px;
}
.abroad-overlay h4{
  font-family:var(--display);font-size:22px;font-weight:800;
  color:#FFFFFF;margin-bottom:6px;letter-spacing:-0.02em;
  text-shadow:0 2px 8px rgba(0,0,0,0.4);
}
.abroad-overlay p{
  font-size:13.5px;color:rgba(255,255,255,0.88);line-height:1.45;
  margin:0;text-shadow:0 1px 4px rgba(0,0,0,0.4);
}

/* ═════════ BANGLADESH MAP & BRANCHES ═════════ */
.branch-sec{background:var(--bg-2)}
.br-grid{
  display:grid;grid-template-columns:220px 390px 1fr;gap:24px;align-items:start;
}
@media (max-width: 1140px){
  .br-grid{grid-template-columns:220px 1fr}
  .bd-map-card{grid-column:1 / -1;order:2}
  .br-cards-wrap{order:3}
}
@media (max-width: 768px){
  .br-grid{grid-template-columns:1fr;gap:20px}
}

.city-list{display:grid;gap:5px;align-content:start}
.city-list button{
  text-align:left;padding:12px 16px;border-radius:12px;border:1px solid transparent;
  background:var(--bg);font-weight:600;font-size:14px;cursor:pointer;
  display:flex;justify-content:space-between;align-items:center;color:var(--ink);
  transition:all .15s ease;
}
.city-list button:hover{
  background:var(--bg-3);border-color:var(--line);
}
.city-list button span{
  color:var(--ink-3);font-size:12px;font-weight:600;padding:2px 8px;
  background:var(--bg-2);border-radius:999px;
}
.city-list button[aria-selected="true"]{
  background:#09090B;color:#FFFFFF;border-color:#09090B;
}
[data-theme="dark"] .city-list button[aria-selected="true"]{
  background:#FAFAFA;color:#09090B;border-color:#FAFAFA;
}
.city-list button[aria-selected="true"] span{
  background:rgba(255,255,255,0.2);color:#FFFFFF;
}
[data-theme="dark"] .city-list button[aria-selected="true"] span{
  background:rgba(0,0,0,0.15);color:#09090B;
}

/* Bangladesh Map Card */
.bd-map-card{
  background:var(--bg);border:1px solid var(--line);
  border-radius:18px;padding:20px;position:relative;
  overflow:hidden;display:flex;flex-direction:column;
  box-shadow:0 10px 30px -10px rgba(0,0,0,0.04);
}
[data-theme="dark"] .bd-map-card{
  box-shadow:0 10px 30px -10px rgba(0,0,0,0.4);
}
.map-card-head{
  display:flex;justify-content:space-between;align-items:center;
  gap:12px;margin-bottom:12px;padding-bottom:12px;
  border-bottom:1px solid var(--line);
}
.map-card-head h4{
  font-size:14px;font-weight:700;color:var(--ink);
  display:flex;align-items:center;gap:6px;margin:0;
}
.map-legend{
  display:inline-flex;align-items:center;gap:6px;
  font-size:11px;font-weight:700;color:var(--ink-2);
  text-transform:uppercase;letter-spacing:0.04em;
}
.map-legend-dot{
  width:7px;height:7px;border-radius:50%;background:#EF4444;
  box-shadow:0 0 0 2px rgba(239,68,68,0.25);
  animation:pinPulse 2s infinite ease-out;
}

.map-svg-stage{
  position:relative;width:100%;height:490px;
  display:flex;align-items:center;justify-content:center;
}
@media (max-width: 640px){
  .map-svg-stage{height:420px;}
}
.bd-svg-map{
  width:100%;height:100%;
  filter:drop-shadow(0 6px 16px rgba(0,0,0,0.06));
  overflow:visible;
}

/* Division Vector Paths (LandTech Reference) */
.bd-div-path{
  fill:#F1F3F5;stroke:#D1D5DB;
  stroke-width:3.5;stroke-linejoin:round;stroke-linecap:round;
  cursor:pointer;
  transition:fill .2s cubic-bezier(0.16, 1, 0.3, 1), stroke .2s ease, stroke-width .2s ease;
}
[data-theme="dark"] .bd-div-path{
  fill:#18181B;stroke:#2E3440;
}
.bd-div-group:hover .bd-div-path,
.bd-div-path:hover,
.bd-div-path.active{
  fill:#FEF3C7;stroke:#D97706;stroke-width:5;
}
[data-theme="dark"] .bd-div-group:hover .bd-div-path,
[data-theme="dark"] .bd-div-path:hover,
[data-theme="dark"] .bd-div-path.active{
  fill:rgba(234, 160, 35, 0.22);stroke:#F59E0B;stroke-width:5;
}

.bd-div-lines{
  fill:none;stroke:#9CA3AF;stroke-width:1.5;
  stroke-dasharray:4 5;opacity:0.45;pointer-events:none;
}
[data-theme="dark"] .bd-div-lines{
  stroke:#52525B;opacity:0.5;
}

/* Red Location Emoji Pin Markers */
.map-pin{
  cursor:pointer;
  transition:transform .25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.pin-shadow{
  fill:rgba(0,0,0,0.18);
  filter:blur(2px);
  pointer-events:none;
}
[data-theme="dark"] .pin-shadow{
  fill:rgba(0,0,0,0.5);
}
.pin-pulse{
  fill:rgba(239, 68, 68, 0.2);
  pointer-events:none;
  transform-origin:0 -28px;
  animation:pinPulse 2.2s infinite ease-out;
}
.map-pin:hover .pin-pulse,
.map-pin.active .pin-pulse,
.map-pin.pin-hover .pin-pulse{
  fill:rgba(239, 68, 68, 0.4);
}
.pin-emoji{
  font-size:64px;
  user-select:none;
  transition:transform .25s cubic-bezier(0.34, 1.56, 0.64, 1);
  transform-origin:0 -12px;
}
.map-pin:hover .pin-emoji,
.map-pin.active .pin-emoji,
.map-pin.pin-hover .pin-emoji{
  transform:scale(1.22) translateY(-6px);
}
.pin-badge{
  pointer-events:none;
}
.pin-badge-bg{
  fill:#09090B;stroke:rgba(255,255,255,0.25);stroke-width:2;
  transition:all .2s ease;
}
[data-theme="dark"] .pin-badge-bg{
  fill:#FAFAFA;stroke:rgba(0,0,0,0.25);
}
.map-pin:hover .pin-badge-bg,
.map-pin.active .pin-badge-bg,
.map-pin.pin-hover .pin-badge-bg{
  fill:#DC2626;stroke:#FFFFFF;
}
.pin-lbl{
  font-family:var(--sans);font-size:26px;font-weight:800;
  fill:#FFFFFF;text-anchor:middle;
  letter-spacing:0.02em;pointer-events:none;
  transition:fill .2s ease;
}
[data-theme="dark"] .pin-lbl{
  fill:#09090B;
}
.map-pin:hover .pin-lbl,
.map-pin.active .pin-lbl,
.map-pin.pin-hover .pin-lbl{
  fill:#FFFFFF;
}

@keyframes pinPulse{
  0%{r:20;opacity:0.9}
  50%{r:52;opacity:0.3}
  100%{r:72;opacity:0}
}

/* Floating Hover Tooltip */
.map-tooltip{
  position:absolute;pointer-events:none;
  background:rgba(9, 9, 11, 0.94);
  color:#FFFFFF;
  padding:8px 14px;border-radius:10px;
  box-shadow:0 8px 24px rgba(0,0,0,0.25);
  font-size:12px;z-index:20;
  display:none;flex-direction:column;gap:3px;
  white-space:nowrap;backdrop-filter:blur(6px);
  -webkit-backdrop-filter:blur(6px);
  border:1px solid rgba(255,255,255,0.14);
  transform:translate(-50%, -125%);
  transition:opacity .15s ease;
}
[data-theme="dark"] .map-tooltip{
  background:rgba(255, 255, 255, 0.96);
  color:#09090B;
  border-color:rgba(0,0,0,0.1);
}
.map-tooltip .mt-title{
  font-weight:700;font-size:12.5px;display:flex;align-items:center;gap:4px;
}
.map-tooltip .mt-sub{
  font-size:11px;opacity:0.85;font-weight:500;
}
.map-card-foot{
  margin-top:10px;padding-top:10px;border-top:1px dashed var(--line);
  text-align:center;color:var(--ink-3);font-size:11.5px;
}

/* Branch Cards Wrap */
.br-cards-wrap{
  display:flex;flex-direction:column;gap:14px;min-width:0;
}
.bc-header{
  display:flex;justify-content:space-between;align-items:center;
  padding:14px 18px;border-radius:14px;
  background:var(--bg);border:1px solid var(--line);
}
.bc-label{
  font-size:11px;font-weight:600;text-transform:uppercase;
  letter-spacing:0.04em;color:var(--ink-3);display:block;
}
.bc-header h3{
  font-size:17px;font-weight:700;color:var(--ink);
  letter-spacing:-0.01em;margin:0;
}
.bc-count-badge{
  padding:4px 10px;border-radius:999px;background:var(--bg-3);
  font-size:11.5px;font-weight:700;color:var(--ink);
  border:1px solid var(--line);
}
.br-cards{
  display:grid;grid-template-columns:1fr;gap:14px;align-content:start;
}
.br{
  background:var(--bg);border:1px solid var(--line);
  border-radius:16px;padding:20px;transition:all .2s ease;
}
.br:hover{
  border-color:var(--ink-3);transform:translateY(-1px);
}
.br h3{font-size:16px;font-weight:700;margin-bottom:6px;color:var(--ink)}
.br p{font-size:13.5px;color:var(--ink-2);line-height:1.45}
.tag{
  display:inline-flex;align-items:center;gap:4px;margin-top:12px;
  font-size:11.5px;font-weight:600;padding:4px 10px;
  border-radius:99px;background:var(--bg-3);color:var(--ink);
  border:1px solid var(--line);
}

/* ═════════ FAQ + NOTICES ═════════ */
.faq-grid{display:grid;grid-template-columns:1.3fr .7fr;gap:56px}
details{border-bottom:1px solid var(--line);overflow:hidden}
summary{padding:20px 0;font-weight:600;font-size:17px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:16px;list-style:none;user-select:none;transition:color .2s ease}
summary:hover{color:var(--ink-2)}
summary::-webkit-details-marker{display:none}
summary::after{content:"+";font-size:24px;line-height:1;color:var(--ink-3);transition:transform .3s cubic-bezier(0.16, 1, 0.3, 1), color .2s ease;flex-shrink:0;width:24px;height:24px;display:flex;align-items:center;justify-content:center}
details[open] summary::after{transform:rotate(45deg);color:var(--ink)}
.faq-content{overflow:hidden}
.faq-content p, details p{padding:0 0 20px;color:var(--ink-2);max-width:62ch;line-height:1.6;margin:0;font-size:15px}
.notice{padding:16px 0;border-bottom:1px solid var(--line)}
.notice b{font-size:13px;color:var(--ink);text-transform:uppercase;letter-spacing:.04em}
.notice p{font-size:15px;color:var(--ink-2)}

/* ═════════ CONTACT / APP ═════════ */
.contact{background:var(--bg-2)}
.contact-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}
.ct{
  padding:26px 24px;border:1px solid var(--line);border-radius:18px;background:var(--bg);
  position:relative;overflow:hidden;
  transition:transform .28s cubic-bezier(0.16, 1, 0.3, 1), box-shadow .28s cubic-bezier(0.16, 1, 0.3, 1), border-color .25s ease;
}
.ct::before{
  content:'';position:absolute;top:0;left:0;right:0;height:3px;
  background:#09090B;opacity:0;
  transition:opacity .25s ease;
}
[data-theme="dark"] .ct::before{
  background:#FAFAFA;
}
.ct:hover{
  transform:translateY(-6px);
  border-color:rgba(9, 9, 11, 0.22);
  box-shadow:0 18px 36px -8px rgba(0,0,0,0.08), 0 4px 12px -2px rgba(0,0,0,0.04);
}
.ct:hover::before{
  opacity:1;
}
[data-theme="dark"] .ct:hover{
  border-color:rgba(255, 255, 255, 0.25);
  box-shadow:0 18px 36px -8px rgba(0,0,0,0.5), 0 4px 12px -2px rgba(0,0,0,0.3);
}
.ct i{
  font-size:28px;color:var(--ink);display:inline-block;
  transition:transform .28s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.ct:hover i{
  transform:scale(1.15) translateY(-2px);
}
.ct h3{font-size:16px;font-weight:700;margin:14px 0 6px;color:var(--ink)}
.ct p{font-size:14.5px;color:var(--ink-2);line-height:1.55;margin:0}
.ct a{font-weight:700;color:var(--ink);text-decoration:none;transition:color .18s ease}
.ct a:hover{text-decoration:underline}

/* ═════════ FOOTER ═════════ */
.foot{background:#09090B;color:#A1A1AA;padding:64px 0 28px;font-size:14.5px;border-top:1px solid #1E1E22}
.foot-grid{display:grid;grid-template-columns:1.5fr repeat(4,1fr);gap:36px;margin-bottom:44px}
.foot .brand{color:#fff;margin-bottom:14px}
.foot h4{color:#fff;font-size:14px;margin-bottom:14px;font-family:var(--display)}
.foot ul{list-style:none;display:grid;gap:9px}
.foot a:hover{color:#fff;text-decoration:underline}
.foot .brand:hover{text-decoration:none}
.foot-id{display:grid;gap:4px;margin-top:16px;font-size:13px}
.foot-bottom{border-top:1px solid rgba(255,255,255,.12);padding-top:22px;display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;font-size:13px}
.disclaimer{font-size:12.5px;color:#71717A;margin-top:16px;max-width:90ch}
.social{display:flex;gap:8px;margin-top:18px}
.social a{width:34px;height:34px;border:1px solid rgba(255,255,255,.2);border-radius:8px;display:grid;place-items:center;font-size:17px;color:#A1A1AA;text-decoration:none!important;transition:all .2s ease}
.social a:hover{color:#FFFFFF;border-color:rgba(255,255,255,.5);background:rgba(255,255,255,0.08);text-decoration:none!important;transform:translateY(-2px)}

/* ═════════ RESPONSIVE ═════════ */
@media(min-width:992px){
  .burger{display:none!important}
  .nav-backdrop{display:none!important}
  .nav{display:flex!important;position:static!important;background:transparent!important;border:none!important;box-shadow:none!important;padding:0!important;opacity:1!important;visibility:visible!important;transform:none!important;max-height:none!important;overflow:visible!important}
  .nav-grid-links{display:contents}
  .nav-mobile-foot{display:none!important}
}
@media(min-width:992px) and (max-width:1160px){
  .head .wrap{gap:10px}
  .nav{margin:0 2px;gap:2px}
  .nav a{padding:6px 7px;font-size:12.8px}
  .head-act{gap:6px}
  .head-act .btn{padding:0 12px;font-size:13px}
}
@media(max-width:991px){
  .head .wrap{justify-content:space-between;padding:0 16px;gap:8px;width:100%;max-width:100%;box-sizing:border-box}
  .head-act{gap:6px;flex-shrink:0}
  .head-act .btn{display:none!important}
  .burger{display:inline-flex!important}
  .nav{
    display:flex!important;position:fixed!important;top:68px;left:0;right:0;width:100%!important;max-width:100%!important;
    background:color-mix(in srgb,var(--bg) 98%,transparent);
    backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
    border-bottom:1px solid var(--line);
    box-shadow:0 24px 48px -12px rgba(0,0,0,0.28);
    flex-direction:column;align-items:stretch;justify-content:flex-start;
    padding:16px!important;margin:0!important;z-index:50;
    max-height:calc(100vh - 68px);overflow-y:auto;overflow-x:hidden!important;
    -webkit-overflow-scrolling:touch;box-sizing:border-box!important;
    transform:translateY(-8px);opacity:0;visibility:hidden;pointer-events:none;
    transition:transform .25s cubic-bezier(.16,1,.3,1),opacity .25s cubic-bezier(.16,1,.3,1),visibility .25s ease;
  }
  .nav.open{
    transform:translateY(0);opacity:1;visibility:visible;pointer-events:auto;
  }
  .nav-grid-links{
    display:flex!important;flex-direction:column!important;gap:8px!important;margin-bottom:14px;width:100%!important;box-sizing:border-box!important;
  }
  .nav a{
    display:flex!important;align-items:center!important;gap:12px!important;padding:12px 14px!important;border-radius:12px!important;
    font-size:14.5px!important;font-weight:600;color:var(--ink);background:var(--bg-2);
    border:1px solid var(--line);transition:background .2s,border-color .2s,transform .15s;
    white-space:normal;width:100%!important;box-sizing:border-box!important;
  }
  .nav a:hover,.nav a:active{background:var(--bg-3);border-color:var(--line-2);transform:translateY(-1px)}
  .nav a i{display:flex!important;font-size:19px;color:#EAA023;flex-shrink:0}
  .nav-mobile-foot{
    display:grid!important;grid-template-columns:1fr 1fr;gap:10px;padding-top:14px;border-top:1px dashed var(--line);width:100%!important;box-sizing:border-box!important;
  }
  .nav-mobile-foot .btn{width:100%!important;height:44px;font-size:14px;justify-content:center;display:inline-flex!important;align-items:center;gap:6px;box-sizing:border-box!important}
  .hero .wrap{grid-template-columns:1fr!important;width:100%!important;gap:36px!important;padding-bottom:48px!important}
  .dig,.remit,.sec-grid,.open,.faq-grid,.rates-grid{grid-template-columns:1fr!important;width:100%!important}
  .hero-visual{max-width:100%!important;width:100%!important;margin:0 auto!important}
  .hero-card{max-width:100%!important;width:100%!important}
  .quick{margin-top:0!important;padding:24px 0 0}
  .quick-grid{grid-template-columns:repeat(3,1fr)}
  .quick-grid a:nth-child(3){border-right:0}
  .quick-grid a:nth-child(-n+3){border-bottom:1px solid var(--line)}
  .stats{padding:40px 0 0}
  .cards-grid,.contact-grid{grid-template-columns:1fr 1fr}
  .foot-grid{grid-template-columns:1fr 1fr}
  .acc-panel.on,.tool-box{grid-template-columns:1fr}
}
@media(max-width:680px){
  section{padding:54px 0}
  .wrap{padding-left:16px!important;padding-right:16px!important;width:100%!important}
  .hero .wrap{padding:0 16px 44px!important}
  .head .wrap{padding:0 16px!important}
  .head-act .btn{display:none!important}
  .nav-mobile-foot{grid-template-columns:1fr!important;gap:8px}
  .br-grid{grid-template-columns:1fr}
  .city-list{grid-template-columns:repeat(2,1fr)}
  .cards-grid,.contact-grid,.cols2,.foot-grid,.field-row{grid-template-columns:1fr}
  .quick{margin-top:0!important;padding:20px 0 0}
  .quick-grid{grid-template-columns:repeat(2,1fr)}
  .quick-grid a{padding:16px 12px;font-size:13px;border-right:1px solid var(--line)!important;border-bottom:1px solid var(--line)!important}
  .quick-grid a:nth-child(2n){border-right:0!important}
  .quick-grid a:nth-child(n+5){border-bottom:0!important}
  .acc-rate,.acc-body,.tool-in,.tool-out{padding:22px}
  .util-l{display:none}
  .util .wrap{justify-content:center;padding:6px 14px}
  .stats-row{gap:16px;justify-content:center}
  .stat{flex:1 1 130px;text-align:center}
  .rates-sec th,.rates-sec td{padding:10px 12px;font-size:13.5px}
  .rates-sec table td span{font-size:12px}
  .conv{padding:20px 16px!important;border-radius:18px!important;gap:16px}
  .conv-head{padding-bottom:12px}
  .conv-icon{width:36px;height:36px;font-size:18px;border-radius:9px}
  .conv-res{padding:16px 14px!important;border-radius:14px}
}
@media(max-width:420px){
  .wrap{padding-left:12px!important;padding-right:12px!important}
  .hero .wrap{padding:0 12px 36px!important}
  .head .wrap{padding:0 12px!important}
  .brand{font-size:17.5px;gap:8px}
  .mark{width:32px;height:32px}
  .mark svg{width:18px;height:18px}
  .hero h1{font-size:32px}
  .hero-card{padding:18px}
  .hero-pill{padding:12px 14px;box-sizing:border-box}
  .hero-pill-amt{font-size:14px}
  .hero-pill-title{font-size:12.5px}
  .hero-pill-sub{font-size:11px}
  .quick{margin-top:0!important;padding:18px 0 0}
  .quick-grid{grid-template-columns:repeat(2,1fr)!important}
  .quick-grid a{padding:13px 8px;font-size:12.5px;gap:6px}
  .quick-grid i{font-size:22px}
  .city-list{grid-template-columns:1fr}
  .conv{padding:16px 12px!important;border-radius:16px!important;gap:14px}
  .conv-res{padding:14px 12px!important}
  .conv-res-val{font-size:26px!important}
  .conv-res-meta{flex-direction:column;align-items:flex-start;gap:6px}
}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important;scroll-behavior:auto!important}}
</style>
</head>
<body>

<!-- ═════════ UTILITY BAR ═════════ -->
<div class="util">
  <div class="wrap">
    <div class="util-l">
      <span><i class='bx bx-phone-call'></i><span data-bn="হটলাইন">Hotline</span> <a href="tel:<?= $bank['hotline'] ?>"><b><?= $bank['hotline'] ?></b></a> (24/7)</span>
      <a href="mailto:<?= $bank['email'] ?>"><i class='bx bx-envelope'></i><?= $bank['email'] ?></a>
    </div>
    <div class="util-r">
      <a href="#branches"><i class='bx bx-map'></i><span data-bn="শাখা ও এটিএম">Branches &amp; ATMs</span></a>
      <a href="#security"><i class='bx bx-shield-quarter'></i><span data-bn="নিরাপত্তা">Security</span></a>
      <div class="lang" role="group" aria-label="Language">
        <button type="button" data-lang="en" aria-pressed="true">EN</button>
        <button type="button" data-lang="bn" aria-pressed="false">বাংলা</button>
      </div>
    </div>
  </div>
</div>

<!-- ═════════ HEADER ═════════ -->
<header class="head">
  <div class="wrap">
    <a href="index.php" class="brand" aria-label="Asha Bank home">
      <span class="mark"><svg viewBox="0 0 24 24" fill="none" stroke="#09090B" stroke-width="2.2" stroke-linecap="round"><path d="M4 16a8 8 0 0 1 16 0"/><path d="M12 4v2M4.9 7.9l1.4 1.4M19.1 7.9l-1.4 1.4M2 20h20"/></svg></span>
      Asha Bank
    </a>
    <nav class="nav" id="nav" aria-label="Main">
      <div class="nav-grid-links">
        <a href="#accounts"><i class='bx bx-wallet'></i><span data-bn="হিসাব">Accounts</span></a>
        <a href="#cards"><i class='bx bx-credit-card'></i><span data-bn="কার্ড">Cards</span></a>
        <a href="#loans"><i class='bx bx-money'></i><span data-bn="ঋণ">Loans</span></a>
        <a href="#tools"><i class='bx bx-calculator'></i><span data-bn="ক্যালকুলেটর">Calculators</span></a>
        <a href="#digital"><i class='bx bx-mobile-alt'></i><span data-bn="ডিজিটাল ব্যাংকিং">Digital banking</span></a>
        <a href="#rates"><i class='bx bx-transfer-alt'></i><span data-bn="রেট">Rates</span></a>
        <a href="#branches"><i class='bx bx-map-pin'></i><span data-bn="শাখা">Branches</span></a>
        <a href="#contact"><i class='bx bx-help-circle'></i><span data-bn="সহায়তা">Help</span></a>
      </div>
      <div class="nav-mobile-foot">
        <a href="login.php" class="btn btn-line" data-bn="সাইন ইন"><i class='bx bx-log-in'></i>Sign in</a>
        <a href="register.php" class="btn btn-dark" data-bn="হিসাব খুলুন"><i class='bx bx-user-plus'></i>Open account</a>
      </div>
    </nav>
    <div class="head-act">
      <button class="icon-btn" id="themeBtn" aria-label="Toggle dark mode"><i class='bx bx-moon'></i></button>
      <a href="login.php" class="btn btn-line" data-bn="সাইন ইন">Sign in</a>
      <a href="register.php" class="btn btn-dark" data-bn="হিসাব খুলুন">Open account</a>
      <button class="icon-btn burger" id="burger" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="nav"><i class='bx bx-menu'></i></button>
    </div>
  </div>
</header>
<div class="nav-backdrop" id="navBackdrop" aria-hidden="true"></div>

<main>
<!-- ═════════ HERO ═════════ -->
<div class="hero">
  <div class="wrap">
    <div>
      <h1 data-bn="আজই খুলুন আপনার ব্যাংক হিসাব, ফোনেই।">Open a bank account today, from your phone.</h1>
      <p class="lede" data-bn="সঞ্চয়, কার্ড, ঋণ এবং বিল পরিশোধ, সবকিছু এক জায়গায়। বাংলাদেশ ব্যাংক কর্তৃক নিয়ন্ত্রিত।">Savings, cards, loans and bill payments in one place. Regulated by Bangladesh Bank, and open 24/7 online.</p>
      <div class="hero-cta">
        <a href="register.php" class="btn btn-sun" data-bn="বিনামূল্যে হিসাব খুলুন">Open a free account</a>
        <a href="#accounts" class="btn btn-ghost-light" data-bn="হিসাবের ধরন দেখুন">Compare accounts</a>
      </div>
      <ul class="assure">
        <li><i class='bx bx-time-five'></i>About 3 minutes to apply, verified within 24 hours</li>
        <li><i class='bx bx-id-card'></i>No branch visit for basic accounts. Just your NID and a selfie</li>
        <li><i class='bx bx-lock-alt'></i>256-bit encryption, two-factor sign-in and real-time fraud alerts</li>
      </ul>
    </div>

    <!-- Hero FinTech Visual Showcase -->
    <div class="hero-visual" aria-hidden="true">
      <div class="hero-card">
        <div class="hc-head">
          <div class="hc-brand">
            <span class="mark" style="width:28px;height:28px;border-radius:8px">
              <svg viewBox="0 0 24 24" fill="none" stroke="#09090B" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><path d="M3 21h18M3 10h18M5 10v11M19 10v11M9 10v11M15 10v11M12 3l9 7H3l9-7z"/></svg>
            </span>
            <span>Asha Bank</span>
          </div>
          <div class="hc-chip"></div>
        </div>
        <div class="hc-num">•••• &nbsp; •••• &nbsp; •••• &nbsp; 8842</div>
        <div class="hc-foot">
          <div>
            <div class="hc-label">Cardholder</div>
            <div class="hc-val">PLATINUM MEMBER</div>
          </div>
          <div style="text-align:right">
            <div class="hc-label">Expires</div>
            <div class="hc-val">09/31</div>
          </div>
          <div class="hc-type">VISA</div>
        </div>
      </div>

      <div class="hero-pill">
        <div class="hero-pill-left">
          <div class="hero-pill-icon"><i class='bx bx-check-shield'></i></div>
          <div>
            <div class="hero-pill-title">NPSB Instant Transfer</div>
            <div class="hero-pill-sub">Received from City Bank</div>
          </div>
        </div>
        <div>
          <div class="hero-pill-amt">+৳25,000.00</div>
          <div class="hero-pill-time">Just now • Verified</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═════════ QUICK ACTIONS ═════════ -->
<div class="quick">
  <div class="wrap">
    <div class="quick-grid">
      <a href="register.php"><i class='bx bx-user-plus'></i>Open account</a>
      <a href="#loans"><i class='bx bx-money'></i>Apply for a loan</a>
      <a href="#digital"><i class='bx bx-receipt'></i>Pay bills</a>
      <a href="#rates"><i class='bx bx-transfer-alt'></i>Exchange rates</a>
      <a href="#branches"><i class='bx bx-map-pin'></i>Find a branch</a>
      <a href="#security" class="urgent"><i class='bx bx-block'></i>Lost card</a>
    </div>
  </div>
</div>

<!-- ═════════ STATS ═════════ -->
<div class="stats">
  <div class="wrap">
    <div class="stats-row">
      <div class="stat"><b><?= number_format($totalCustomers) ?>+</b><span>Active customers</span></div>
      <div class="stat"><b>৳<?= number_format(round($totalDeposits / 10000000)) ?> crore</b><span>Deposits in our care</span></div>
      <div class="stat"><b><?= number_format($totalBranches) ?></b><span>Branches nationwide</span></div>
      <div class="stat"><b><?= number_format($totalCards) ?>+</b><span>Active cards</span></div>
    </div>
  </div>
</div>

<!-- ═════════ ACCOUNTS ═════════ -->
<section id="accounts">
  <div class="wrap">
    <div class="sec-head">
      <h2 data-bn="আপনার জন্য সঠিক হিসাব">The right account for how you use money</h2>
      <p class="lede">Pick a type to see the profit rate, minimum balance, features and the documents you need.</p>
    </div>
    <div class="acc-tabs" role="tablist" id="accTabs">
      <?php $first = true; foreach ($accounts as $k => $a): ?>
        <button type="button" role="tab" id="tab-<?= $k ?>" aria-controls="panel-<?= $k ?>" aria-selected="<?= $first ? 'true' : 'false' ?>" data-acc="<?= $k ?>"><?= $a['name'] ?></button>
      <?php $first = false; endforeach; ?>
    </div>
    <?php $first = true; foreach ($accounts as $k => $a): 
      $rateParts = explode(' ', $a['rate'], 2);
      $rateVal = $rateParts[0] ?? '0%';
      $rateWord = $rateParts[1] ?? 'Interest';
    ?>
    <div class="acc-panel <?= $first ? 'on' : '' ?>" id="panel-<?= $k ?>" role="tabpanel" aria-labelledby="tab-<?= $k ?>">
      <div class="acc-rate">
        <div>
          <div class="rate-stack">
            <span class="rate-num"><?= htmlspecialchars($rateVal) ?></span>
            <span class="rate-text"><?= htmlspecialchars($rateWord) ?></span>
          </div>
          <small><?= $a['rate_note'] ?></small>
        </div>
        <div class="acc-min">Minimum to open<b><?= $a['min'] ?></b></div>
      </div>
      <div class="acc-body">
        <h3 style="font-size:24px;margin-bottom:8px"><?= $a['name'] ?> account</h3>
        <p><?= $a['blurb'] ?></p>
        <div class="cols2">
          <div><h4>What you get</h4>
            <ul class="tick"><?php foreach ($a['features'] as $f): ?><li><i class='bx bx-check-circle'></i><?= $f ?></li><?php endforeach; ?></ul>
          </div>
          <div><h4>What you need</h4>
            <ul class="tick doc"><?php foreach ($a['docs'] as $d): ?><li><i class='bx bx-file'></i><?= $d ?></li><?php endforeach; ?></ul>
          </div>
        </div>
        <a href="register.php?type=<?= $k ?>" class="btn btn-dark">Open a <?= strtolower($a['name']) ?> account</a>
      </div>
    </div>
    <?php $first = false; endforeach; ?>
    <p class="note">Rates are indicative and may change. Profit is paid on the daily balance unless stated.</p>
  </div>
</section>

<!-- ═════════ CARDS ═════════ -->
<section id="cards" class="cards-sec">
  <div class="wrap">
    <div class="sec-head">
      <h2 data-bn="আপনার কার্ড, আপনার নিয়ন্ত্রণ">Cards you control from your phone</h2>
      <p class="lede">Freeze, unfreeze and set limits in one tap. Pay online, in shops and abroad.</p>
    </div>
    <div class="cards-grid">
      <article>
        <div class="pcard pc-debit"><div class="t"><span>Asha Bank</span><span>Debit</span></div><div class="n">•••• •••• •••• 4291</div><div class="b"><span>AMINA KHATUN</span><span>09/29</span></div></div>
        <h3>Debit card</h3>
        <p>Tied to your account. Withdraw at any ATM, pay at shops and online.</p>
        <ul class="tick"><li><i class='bx bx-check-circle'></i>Contactless and chip-and-PIN</li><li><i class='bx bx-check-circle'></i>Daily limits you can change</li><li><i class='bx bx-check-circle'></i>Delivered to your door in 5 working days</li></ul>
        <p class="fee">Annual fee ৳0 in the first year, then ৳500.</p>
      </article>
      <article>
        <div class="pcard pc-virtual"><div class="t"><span>Asha Bank</span><span>Virtual</span></div><div class="n">•••• •••• •••• 7710</div><div class="b"><span>FOR ONLINE USE</span><span>03/27</span></div></div>
        <h3>Virtual card</h3>
        <p>Created instantly for online shopping and subscriptions, so your main card stays private.</p>
        <ul class="tick"><li><i class='bx bx-check-circle'></i>Ready in seconds, no waiting</li><li><i class='bx bx-check-circle'></i>Set a spending cap per card</li><li><i class='bx bx-check-circle'></i>Delete it any time</li></ul>
        <p class="fee">Free to create. Up to 5 active virtual cards.</p>
      </article>
      <article>
        <div class="pcard pc-credit"><div class="t"><span>Asha Bank</span><span>Credit</span></div><div class="n">•••• •••• •••• 0382</div><div class="b"><span>CREDIT LIMIT ৳2,00,000</span><span>11/30</span></div></div>
        <h3>Credit card</h3>
        <p>Up to 45 interest-free days on purchases, with a limit based on your income.</p>
        <ul class="tick"><li><i class='bx bx-check-circle'></i>Instalments on large purchases</li><li><i class='bx bx-check-circle'></i>Reward points on every spend</li><li><i class='bx bx-check-circle'></i>Emergency card replacement abroad</li></ul>
        <p class="fee">Annual fee ৳1,500. Subject to credit approval.</p>
      </article>
    </div>
  </div>
</section>

<!-- ═════════ LOANS ═════════ -->
<section id="loans">
  <div class="wrap">
    <div class="sec-head">
      <h2 data-bn="আপনার পরিকল্পনার জন্য ঋণ">Loans for the plans you already have</h2>
      <p class="lede">Clear rates and timelines before you apply. Check your monthly payment in the calculator below.</p>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Loan</th><th class="num">Up to</th><th>Tenure</th><th class="num">Rate from</th><th>Decision in</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($loans as $l): ?>
          <tr><td><b><?= $l[0] ?></b></td><td class="num"><?= $l[1] ?></td><td><?= $l[2] ?></td><td class="num"><?= $l[3] ?> p.a.</td><td><?= $l[4] ?></td><td><a class="link" href="register.php?loan=<?= urlencode($l[0]) ?>">Apply</a></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="note">Final rate depends on your credit profile. Processing fee and early-settlement terms are in the schedule of charges.</p>
  </div>
</section>

<!-- ═════════ CALCULATORS ═════════ -->
<section id="tools" class="tools">
  <div class="wrap">
    <div class="sec-head">
      <h2 data-bn="ক্যালকুলেটর">See the numbers before you commit</h2>
      <p class="lede">Estimate a loan payment or what your savings will grow to.</p>
    </div>
    <div class="tool-tabs" role="tablist" id="toolTabs">
      <button type="button" role="tab" aria-selected="true"  data-tool="emi">Loan payment</button>
      <button type="button" role="tab" aria-selected="false" data-tool="fd">Fixed deposit</button>
      <button type="button" role="tab" aria-selected="false" data-tool="dps">DPS</button>
    </div>

    <!-- EMI -->
    <div class="tool-box" data-panel="emi">
      <div class="tool-in">
        <div class="field">
          <label for="emiP"><span>Loan amount</span><span class="field-hint">Min ৳50,000</span></label>
          <div class="input-box">
            <input id="emiP" type="number" min="10000" step="10000" value="500000">
            <span class="suffix">BDT</span>
          </div>
        </div>
        <div class="slider-wrap">
          <input class="range" type="range" id="emiPr" min="50000" max="5000000" step="10000" value="500000" aria-label="Loan amount slider">
          <div class="range-limits"><span>৳50,000</span><span>৳50,00,000</span></div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="emiR"><span>Profit / Fee rate</span><span class="field-hint">0% Shariah</span></label>
            <div class="input-box">
              <input id="emiR" type="number" min="0" max="30" step="0.1" value="0.0">
              <span class="suffix">% / yr</span>
            </div>
          </div>
          <div class="field">
            <label for="emiN"><span>Tenure</span><span class="field-hint">3 to 300 mos</span></label>
            <div class="input-box">
              <input id="emiN" type="number" min="3" max="300" step="1" value="36">
              <span class="suffix">Months</span>
            </div>
          </div>
        </div>
      </div>
      <div class="tool-out">
        <span class="lab">Monthly payment</span>
        <div class="res" id="emiOut">–</div>
        <dl><dt>Interest / profit charged</dt><dd id="emiInt">৳0 (0%)</dd><dt>Total repayment</dt><dd id="emiTot">–</dd></dl>
      </div>
    </div>

    <!-- FD / Capital Custody -->
    <div class="tool-box hide" data-panel="fd">
      <div class="tool-in">
        <div class="field">
          <label for="fdP"><span>Deposit amount</span><span class="field-hint">Min ৳25,000</span></label>
          <div class="input-box">
            <input id="fdP" type="number" min="25000" step="5000" value="200000">
            <span class="suffix">BDT</span>
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="fdR"><span>Safekeeping fee</span><span class="field-hint">Fixed rate</span></label>
            <div class="input-box">
              <input id="fdR" type="number" min="0" max="1" step="0.001" value="0.001" readonly style="background:var(--bg-3);cursor:not-allowed">
              <span class="suffix">% / yr</span>
            </div>
          </div>
          <div class="field">
            <label for="fdN"><span>Term duration</span><span class="field-hint">Locked period</span></label>
            <div class="input-box">
              <select id="fdN">
                <option value="3">3 months</option>
                <option value="6">6 months</option>
                <option value="12" selected>12 months</option>
                <option value="24">24 months</option>
                <option value="36">36 months</option>
              </select>
            </div>
          </div>
        </div>
      </div>
      <div class="tool-out">
        <span class="lab">Capital at maturity</span>
        <div class="res" id="fdOut">–</div>
        <dl><dt>Capital preserved</dt><dd id="fdIn">–</dd><dt>0.001% Safekeeping fee</dt><dd id="fdInt">–</dd></dl>
      </div>
    </div>

    <!-- DPS / Systematic Custody -->
    <div class="tool-box hide" data-panel="dps">
      <div class="tool-in">
        <div class="field">
          <label for="dpM"><span>Monthly instalment</span><span class="field-hint">Min ৳500</span></label>
          <div class="input-box">
            <input id="dpM" type="number" min="500" step="500" value="5000">
            <span class="suffix">BDT</span>
          </div>
        </div>
        <div class="field-row">
          <div class="field">
            <label for="dpR"><span>Safekeeping fee</span><span class="field-hint">Fixed rate</span></label>
            <div class="input-box">
              <input id="dpR" type="number" min="0" max="1" step="0.001" value="0.001" readonly style="background:var(--bg-3);cursor:not-allowed">
              <span class="suffix">% / yr</span>
            </div>
          </div>
          <div class="field">
            <label for="dpN"><span>Term duration</span><span class="field-hint">Deposit tenure</span></label>
            <div class="input-box">
              <select id="dpN">
                <option value="36">3 years</option>
                <option value="60" selected>5 years</option>
                <option value="120">10 years</option>
              </select>
            </div>
          </div>
        </div>
      </div>
      <div class="tool-out">
        <span class="lab">Total saved at maturity</span>
        <div class="res" id="dpOut">–</div>
        <dl><dt>Total deposited</dt><dd id="dpIn">–</dd><dt>0.001% Safekeeping fee</dt><dd id="dpInt">–</dd></dl>
      </div>
    </div>
    <p class="note">Asha Bank operates on a 0% interest banking model. A nominal 0.001% annual fee covers high-security vault storage, auditing, and account maintenance.</p>
  </div>
</section>

<!-- ═════════ DIGITAL BANKING ═════════ -->
<section id="digital">
  <div class="wrap">
    <!-- Top Row: Features (Fluid Flex Cards - Not a Grid) -->
    <div class="dig-intro">
      <div class="sec-head" style="margin-bottom:28px;">
        <h2 data-bn="ডিজিটাল ব্যাংকিং">Everything you do at a branch, without the queue</h2>
        <p class="lede">Move money, pay bills and manage your cards at any hour, from any device.</p>
      </div>
      <div class="feat-flex">
        <div class="feat-card">
          <div class="feat-icon"><i class='bx bx-transfer'></i></div>
          <div>
            <h3>Send money in seconds</h3>
            <p>To any bank via NPSB, BEFTN, RTGS, &amp; bKash, Nagad or Upay wallets.</p>
          </div>
        </div>
        <div class="feat-card">
          <div class="feat-icon"><i class='bx bx-receipt'></i></div>
          <div>
            <h3>Pay bills &amp; recharge</h3>
            <p>Electricity, gas, water, internet, tuition and mobile airtime with auto-pay.</p>
          </div>
        </div>
        <div class="feat-card">
          <div class="feat-icon"><i class='bx bx-qr-scan'></i></div>
          <div>
            <h3>Scan to pay</h3>
            <p>Pay any merchant Bangla QR code directly from your account in real-time.</p>
          </div>
        </div>
        <div class="feat-card">
          <div class="feat-icon"><i class='bx bx-bell'></i></div>
          <div>
            <h3>Instant alerts</h3>
            <p>Instant SMS and mobile push notifications for every debit and credit.</p>
          </div>
        </div>
        <div class="feat-card">
          <div class="feat-icon"><i class='bx bx-file-blank'></i></div>
          <div>
            <h3>Statements &amp; certificates</h3>
            <p>Download certified account statements and balance certificates on demand.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Next Row: Transfer Speeds, Fees & Limits (Grid Table Style) -->
    <div class="transfer-box">
      <div class="tb-head">
        <div>
          <h3 class="tb-title" data-bn="স্থানান্তর গতি, ফি ও সীমা">Transfer speeds, fees &amp; limits</h3>
          <p class="tb-sub">Bangladesh Bank approved NPSB, BEFTN &amp; RTGS settlement rails</p>
        </div>
        <div class="tb-live-badge">
          <span class="live-dot"></span>
          <span>Rails Active</span>
        </div>
      </div>

      <div class="tb-table-wrap">
        <div class="tb-table-header">
          <div class="col-channel">Transfer Channel &amp; Protocol</div>
          <div class="col-speed">Settlement Speed</div>
          <div class="col-fee">Transaction Fee</div>
          <div class="col-limit">Daily Limit</div>
        </div>

        <div class="tb-table-body">
          <?php foreach ($transfers as $t): ?>
            <div class="tb-table-row">
              <div class="col-channel">
                <div class="t-rail-icon"><i class='bx <?= $t['icon'] ?>'></i></div>
                <div>
                  <div class="t-rail-name"><?= htmlspecialchars($t['title']) ?></div>
                  <div class="t-rail-desc"><?= htmlspecialchars($t['sub']) ?></div>
                </div>
              </div>
              <div class="col-speed">
                <span class="t-speed-pill <?= $t['is_instant'] ? 'speed-instant' : '' ?>">
                  <?php if ($t['is_instant']): ?><span class="speed-dot"></span><?php endif; ?>
                  <?= htmlspecialchars($t['speed']) ?>
                </span>
              </div>
              <div class="col-fee">
                <span class="t-fee-pill <?= $t['fee'] === 'Free' ? 'fee-free' : '' ?>">
                  <?= htmlspecialchars($t['fee']) ?>
                </span>
              </div>
              <div class="col-limit">
                <div class="t-limit-val"><?= htmlspecialchars($t['limit']) ?></div>
                <div class="t-limit-sub"><?= htmlspecialchars($t['limit_sub']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="tb-foot">
        <div class="tb-foot-info">
          <i class='bx bx-shield-quarter'></i>
          <span>Limits can be customized or raised in your dashboard after NID biometric verification.</span>
        </div>
        <a href="register.php" class="tb-foot-link">Open Account <i class='bx bx-right-arrow-alt'></i></a>
      </div>
    </div>
  </div>
</section>

<!-- ═════════ RATES ═════════ -->
<section id="rates" class="rates-sec">
  <div class="wrap">
    <div class="sec-head">
      <h2 data-bn="আজকের রেট">Today's exchange rates</h2>
      <p class="lede">Rates against the Bangladeshi taka for cash and transfers. Use the converter to check an amount.</p>
    </div>
    <div class="rates-grid">
      <div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Currency</th><th class="num">We buy (৳)</th><th class="num">We sell (৳)</th></tr></thead>
            <tbody><?php foreach ($rates as $r): ?><tr><td><b><?= $r[0] ?></b> <span style="color:var(--ink-3)"><?= $r[1] ?></span></td><td class="num"><?= $r[2] ?></td><td class="num"><?= $r[3] ?></td></tr><?php endforeach; ?></tbody>
          </table>
        </div>
        <p class="note">Indicative rates as of <?= date('j F Y') ?>. Branch and online rates can differ.</p>
      </div>
      <div class="conv">
        <div class="conv-head">
          <div style="display:flex;align-items:center;gap:10px;">
            <div class="conv-icon"><i class='bx bx-transfer-alt'></i></div>
            <div>
              <h3 style="margin:0;font-size:17px;font-weight:700;color:var(--ink);">Currency converter</h3>
              <span style="font-size:12px;color:var(--ink-3);">Live mid-market selling rates</span>
            </div>
          </div>
        </div>
        <div class="field">
          <label for="cvAmt"><span>Send amount</span><span class="field-hint">Foreign value</span></label>
          <div class="input-box">
            <input id="cvAmt" type="number" min="0" value="100" placeholder="100">
            <span class="suffix" id="cvCurBadge">USD</span>
          </div>
        </div>
        <div class="field">
          <label for="cvCur"><span>Select currency</span><span class="field-hint">Selling rate</span></label>
          <div class="input-box">
            <select id="cvCur" style="cursor:pointer;padding-right:32px;">
              <?php foreach ($rates as $r): ?>
                <option value="<?= $r[3] ?>" data-code="<?= $r[0] ?>"><?= $r[0] ?> – <?= $r[1] ?> (৳<?= $r[3] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="conv-res">
          <span class="conv-res-sub">You receive approximately</span>
          <div class="conv-res-val" id="cvOut">–</div>
          <div class="conv-res-meta">
            <span class="conv-rate-tag" id="cvRateTag">1 USD = ৳122.50</span>
            <span style="font-size:11.5px;color:var(--ink-3);">Zero commission fee</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═════════ REMITTANCE ═════════ -->
<section id="remittance">
  <div class="wrap remit">
    <div>
      <div class="remit-badge">
        <i class='bx bx-check-shield'></i> 2.5% Govt. Cash Incentive Guaranteed
      </div>
      <h2>Receiving money from family abroad</h2>
      <p class="lede" style="margin-bottom:20px">Funds sent from overseas land directly in your Asha Bank account with real-time SMS alerts and instant BDT credit.</p>
      
      <div class="remit-steps-list">
        <div class="remit-step-card">
          <div class="rsc-num">01</div>
          <div class="rsc-content">
            <h3>Share your inward wire details</h3>
            <p>Give your 13-digit Asha Bank account number and the official SWIFT/routing instructions on the right to the sender.</p>
            <div class="rsc-tag"><i class='bx bx-credit-card-front'></i> Account No. from Dashboard</div>
          </div>
        </div>

        <div class="remit-step-card">
          <div class="rsc-num">02</div>
          <div class="rsc-content">
            <h3>Send via licensed global channels</h3>
            <p>Senders can remit through Western Union, MoneyGram, Ria, TapTap Send, or direct wire from their local bank.</p>
            <div class="rsc-tag"><i class='bx bx-globe'></i> Worldwide Exchange Partners</div>
          </div>
        </div>

        <div class="remit-step-card">
          <div class="rsc-num">03</div>
          <div class="rsc-content">
            <h3>Instant credit with zero deduction</h3>
            <p>Your BDT credit arrives with Bangladesh Bank's official 2.5% cash incentive credited automatically. Zero hidden deductions.</p>
            <div class="rsc-tag"><i class='bx bx-bell'></i> Instant SMS &amp; Mobile Alert</div>
          </div>
        </div>
      </div>
    </div>

    <div>
      <div class="remit-card">
        <div class="remit-card-head">
          <div class="rc-title-wrap">
            <h3>Inward Wire Instructions</h3>
            <p>Official routing details for international senders</p>
          </div>
          <div class="rc-live-badge">
            <span class="rc-dot"></span> SWIFT Active
          </div>
        </div>

        <div class="remit-card-list">
          <div class="remit-card-row">
            <span class="rc-label">Bank name</span>
            <div class="rc-val-group">
              <span class="rc-val"><?= htmlspecialchars($bank['legal']) ?></span>
              <button type="button" class="rc-btn-copy" data-copy="<?= htmlspecialchars($bank['legal']) ?>" aria-label="Copy Bank Name">
                <i class='bx bx-copy'></i> Copy
              </button>
            </div>
          </div>

          <div class="remit-card-row">
            <span class="rc-label">SWIFT / BIC</span>
            <div class="rc-val-group">
              <span class="rc-val mono"><?= htmlspecialchars($bank['swift']) ?></span>
              <button type="button" class="rc-btn-copy" data-copy="<?= htmlspecialchars($bank['swift']) ?>" aria-label="Copy SWIFT Code">
                <i class='bx bx-copy'></i> Copy
              </button>
            </div>
          </div>

          <div class="remit-card-row">
            <span class="rc-label">Routing number</span>
            <div class="rc-val-group">
              <span class="rc-val mono"><?= htmlspecialchars($bank['routing']) ?></span>
              <button type="button" class="rc-btn-copy" data-copy="<?= htmlspecialchars($bank['routing']) ?>" aria-label="Copy Routing Number">
                <i class='bx bx-copy'></i> Copy
              </button>
            </div>
          </div>

          <div class="remit-card-row">
            <span class="rc-label">Central settlement</span>
            <div class="rc-val-group">
              <span class="rc-val"><?= htmlspecialchars($bank['address']) ?></span>
            </div>
          </div>

          <div class="remit-card-row">
            <span class="rc-label">Overseas desk</span>
            <div class="rc-val-group">
              <span class="rc-val"><?= htmlspecialchars($bank['intl']) ?></span>
              <a href="tel:<?= preg_replace('/[^+0-9]/','',$bank['intl']) ?>" class="rc-btn-copy" style="text-decoration:none">
                <i class='bx bx-phone'></i> Call
              </a>
            </div>
          </div>
        </div>

        <div class="remit-card-footer">
          <div class="rc-security-tip">
            <i class='bx bx-lock-alt'></i>
            <span>Always provide your exact 13-digit account number as the Beneficiary Account. Asha Bank will never ask for your PIN or internet banking password.</span>
          </div>
          <div class="rc-action-row">
            <a href="auth/login.php">
              View your account number <i class='bx bx-right-arrow-alt'></i>
            </a>
            <span style="font-size:12px;color:var(--ink-3)">Available 24/7/365</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═════════ SECURITY ═════════ -->
<section id="security" class="sec-band">
  <div class="wrap sec-grid">
    <div>
      <h2>How we protect you, and how you can too</h2>
      <p class="lede">Most fraud starts with a phone call or message that sounds urgent. Know these four rules.</p>
      <div class="alert-box" style="margin-top:28px">
        <h3>Lost card or suspect fraud?</h3>
        <p>Freeze your card in the dashboard, then call <a href="tel:<?= $bank['hotline'] ?>"><?= $bank['hotline'] ?></a> any time, day or night. Abroad: <a href="tel:<?= preg_replace('/[^+0-9]/','',$bank['intl']) ?>"><?= $bank['intl'] ?></a>.</p>
      </div>
    </div>
    <ul class="rule-list">
      <li><i class='bx bx-key'></i><span><b>Never share your OTP or PIN</b>Asha Bank staff will never ask for it, whatever the reason.</span></li>
      <li><i class='bx bx-link-external'></i><span><b>Type the address yourself</b>Don't sign in from links in SMS or email. Use ashabank.com.bd or our app.</span></li>
      <li><i class='bx bx-mobile-alt'></i><span><b>Turn on two-factor sign-in</b>A one-time code protects you even if your password leaks.</span></li>
      <li><i class='bx bx-bell'></i><span><b>Read your alerts</b>If you see a transaction you didn't make, call us straight away.</span></li>
    </ul>
  </div>
</section>

<!-- ═════════ OPEN ACCOUNT ═════════ -->
<section id="open">
  <div class="wrap open-wrap">
    <!-- Row 1: Header + 3 Steps in a Row Grid Box -->
    <div class="open-top">
      <div class="open-head">
        <span class="open-pill"><i class='bx bx-user-plus'></i> 100% Digital Onboarding</span>
        <h2 data-bn="তিন ধাপে হিসাব খুলুন">Open your account in three steps</h2>
        <p class="lede">No paperwork and no queue for a basic account. Ready in under 5 minutes.</p>
      </div>

      <div class="open-steps-grid">
        <div class="open-step-box">
          <div class="osb-header">
            <span class="osb-num">1</span>
            <span class="osb-time"><i class='bx bx-time-five'></i> ~3 mins</span>
          </div>
          <h3 data-bn="অনলাইন ফরম পূরণ করুন">Fill in the online form</h3>
          <p>Name, mobile number, email, address and nominee. It takes about 3 minutes.</p>
        </div>

        <div class="open-step-box">
          <div class="osb-header">
            <span class="osb-num">2</span>
            <span class="osb-time"><i class='bx bx-shield-quarter'></i> e-KYC</span>
          </div>
          <h3 data-bn="পরিচয় যাচাই করুন">Verify your identity</h3>
          <p>Upload your NID and take a selfie. We review it within 24 hours and message you the result.</p>
        </div>

        <div class="open-step-box">
          <div class="osb-header">
            <span class="osb-num">3</span>
            <span class="osb-time"><i class='bx bx-check-circle'></i> Instant</span>
          </div>
          <h3 data-bn="জমা দিয়ে শুরু করুন">Deposit and start</h3>
          <p>Add money by bank transfer, bKash or at any branch, then send, save and pay straight away.</p>
        </div>
      </div>
    </div>

    <!-- Row 2: Checklist Card + Photo of Student Going Study Abroad Waiting at Airport -->
    <div class="open-bottom-grid">
      <!-- Left side of Row 2: Checklist Card -->
      <aside class="checklist-box">
        <div class="cl-head">
          <h3 data-bn="এগুলো প্রস্তুত রাখুন">Keep these ready</h3>
          <p>Have these handy before you tap start to breeze through your application.</p>
        </div>
        <ul class="cl-list">
          <li><i class='bx bx-id-card'></i><span><b>National ID or smart card</b>Front and back photo or clear scan</span></li>
          <li><i class='bx bx-camera'></i><span><b>A clear selfie</b>Taken in good lighting</span></li>
          <li><i class='bx bx-phone'></i><span><b>Mobile number</b>Registered under your own NID</span></li>
          <li><i class='bx bx-envelope'></i><span><b>Email address</b>An active inbox you can access</span></li>
          <li><i class='bx bx-group'></i><span><b>Nominee details</b>Nominee name, relationship and NID</span></li>
          <li><i class='bx bx-briefcase'></i><span><b>Businesses / Sole Traders</b>Trade licence and e-TIN certificate</span></li>
        </ul>
        <div class="cl-actions">
          <a href="register.php" class="btn btn-dark" data-bn="আবেদন শুরু করুন">Start your application <i class='bx bx-right-arrow-alt'></i></a>
          <span class="cl-note"><i class='bx bx-lock-alt'></i> 256-bit SSL encrypted · Bangladesh Bank regulated</span>
        </div>
      </aside>

      <!-- Right side of Row 2: Study Abroad Airport Photo Card -->
      <div class="abroad-card">
        <img src="assets/images/study-abroad-airport.jpg" alt="Student waiting at airport terminal departure gate going to study abroad" class="abroad-img" loading="lazy">
        <div class="abroad-overlay">
          <span class="abroad-tag"><i class='bx bx-plane-take-off'></i> Higher Studies Abroad</span>
          <h4>Student File &amp; Remittance Ready</h4>
          <p>Opening an Asha Bank account gives you instant access to Student File services, university tuition fee wire transfers, and worldwide dual-currency cards.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ═════════ BRANCHES ═════════ -->
<section id="branches" class="branch-sec">
  <div class="wrap">
    <div class="sec-head">
      <h2 data-bn="শাখা ও এটিএম">Find a branch or ATM</h2>
      <p class="lede">Branches open <?= $bank['hours'] ?>. ATMs and digital banking are available 24/7 across all 8 divisions.</p>
    </div>
    <div class="br-grid">
      <!-- 1. Division / City List Tablist -->
      <div class="city-list" role="tablist" id="cityList">
        <?php $i = 0; foreach ($branchList as $c => $bs): ?>
          <button type="button" role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" data-city="<?= htmlspecialchars($c) ?>">
            <?= htmlspecialchars($c) ?> <span><?= count($bs) ?></span>
          </button>
        <?php $i++; endforeach; ?>
      </div>

      <!-- 2. Interactive Bangladesh Map -->
      <div class="bd-map-card">
        <div class="map-card-head">
          <h4><i class='bx bx-map-pin'></i> Bangladesh Locations</h4>
          <span class="map-legend"><span class="map-legend-dot"></span> 8 Divisions</span>
        </div>
        <div class="map-svg-stage" id="mapStage">
          <!-- Floating Hover Tooltip -->
          <div class="map-tooltip" id="mapTooltip">
            <span class="mt-title" id="mtTitle">Dhaka Division</span>
            <span class="mt-sub" id="mtSub">2 Branches · ATM 24/7</span>
          </div>

          <svg viewBox="0 0 1200 1664" class="bd-svg-map" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet">
            <!-- Bangladesh Division Vector Layers (From LandTech Reference) -->
            <g class="bd-divisions-layer">
              <?php foreach ($bdDivisions as $div): ?>
                <g class="bd-div-group" data-city="<?= htmlspecialchars($div['city']) ?>">
                  <path class="bd-div-path" data-city="<?= htmlspecialchars($div['city']) ?>" d="<?= htmlspecialchars($div['path']) ?>">
                    <title><?= htmlspecialchars($div['name']) ?></title>
                  </path>
                  <?php if (!empty($div['linesPath'])): ?>
                    <path class="bd-div-lines" d="<?= htmlspecialchars($div['linesPath']) ?>" />
                  <?php endif; ?>
                </g>
              <?php endforeach; ?>
            </g>

            <!-- Location Pins with Red Location Emoji 📍 -->
            <g class="bd-pins-layer">
              <?php foreach ($bdDivisions as $div): ?>
                <g class="map-pin" data-city="<?= htmlspecialchars($div['city']) ?>" transform="translate(<?= (int)$div['center']['x'] ?>, <?= (int)$div['center']['y'] ?>)">
                  <ellipse cx="0" cy="4" rx="24" ry="10" class="pin-shadow" />
                  <circle class="pin-pulse" cx="0" cy="-28" r="46" />
                  <text class="pin-emoji" text-anchor="middle" dominant-baseline="central" y="-28">📍</text>
                  <g class="pin-badge" transform="translate(0, 26)">
                    <rect class="pin-badge-bg" rx="16" ry="16" x="-74" y="-18" width="148" height="36" />
                    <text class="pin-lbl" text-anchor="middle" y="5"><?= htmlspecialchars($div['city']) ?></text>
                  </g>
                </g>
              <?php endforeach; ?>
            </g>
          </svg>
        </div>
        <div class="map-card-foot">
          <small><i class='bx bx-pointer'></i> Click or hover on any location to view branches</small>
        </div>
      </div>

      <!-- 3. Branch Cards Display -->
      <div class="br-cards-wrap">
        <div class="bc-header">
          <div class="bc-title-info">
            <span class="bc-label">Active Region</span>
            <h3 id="activeCityTitle">Dhaka</h3>
          </div>
          <span class="bc-count-badge" id="activeCityCount">2 Branches</span>
        </div>
        <div class="br-cards" id="brCards"></div>
      </div>
    </div>
    <p class="note" style="margin-top:20px">Showing a selection. The full list is available in your dashboard after sign-in.</p>
  </div>
</section>

<!-- ═════════ FAQ + NOTICES ═════════ -->
<section id="faq">
  <div class="wrap faq-grid">
    <div>
      <h2>Questions people ask us</h2>
      <div style="margin-top:20px">
        <?php foreach ($faqs as $f): ?>
          <details><summary><?= $f[0] ?></summary><div class="faq-content"><p><?= $f[1] ?></p></div></details>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <h2 style="font-size:26px">Notices</h2>
      <div style="margin-top:12px">
        <?php foreach ($notices as $n): ?>
          <div class="notice"><b><?= $n[0] ?></b><p><?= $n[1] ?></p></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ═════════ CONTACT ═════════ -->
<section id="contact" class="contact">
  <div class="wrap">
    <div class="sec-head">
      <h2 data-bn="আমরা আছি আপনার পাশে">We're here when you need us</h2>
      <p class="lede">Choose whichever is quickest for you.</p>
    </div>
    <div class="contact-grid">
      <div class="ct"><i class='bx bx-phone-call'></i><h3>Call us</h3><p>24/7 hotline<br><a href="tel:<?= $bank['hotline'] ?>"><?= $bank['hotline'] ?></a></p></div>
      <div class="ct"><i class='bx bx-envelope'></i><h3>Email</h3><p>Reply within one working day<br><a href="mailto:<?= $bank['email'] ?>"><?= $bank['email'] ?></a></p></div>
      <div class="ct"><i class='bx bx-chat'></i><h3>Live chat</h3><p>Sign in and open Help in your dashboard for chat support.</p></div>
      <div class="ct"><i class='bx bx-buildings'></i><h3>Visit us</h3><p><?= $bank['address'] ?><br><?= $bank['hours'] ?></p></div>
    </div>
  </div>
</section>
</main>

<!-- ═════════ FOOTER ═════════ -->
<footer class="foot">
  <div class="wrap">
    <div class="foot-grid">
      <div>
        <a href="index.php" class="brand"><span class="mark"><svg viewBox="0 0 24 24" fill="none" stroke="#09090B" stroke-width="2.2" stroke-linecap="round"><path d="M4 16a8 8 0 0 1 16 0"/><path d="M12 4v2M4.9 7.9l1.4 1.4M19.1 7.9l-1.4 1.4M2 20h20"/></svg></span>Asha Bank</a>
        <p>A scheduled commercial bank in Bangladesh, regulated by Bangladesh Bank.</p>
        <div class="foot-id"><span>SWIFT: <?= $bank['swift'] ?></span><span>Routing no.: <?= $bank['routing'] ?></span></div>
        <div class="social">
          <a href="#" aria-label="Facebook"><i class='bx bxl-facebook'></i></a>
          <a href="#" aria-label="X"><i class='bx bxl-twitter'></i></a>
          <a href="#" aria-label="LinkedIn"><i class='bx bxl-linkedin'></i></a>
          <a href="#" aria-label="YouTube"><i class='bx bxl-youtube'></i></a>
        </div>
      </div>
      <div><h4>Personal</h4><ul><li><a href="#accounts">Savings account</a></li><li><a href="#accounts">Student account</a></li><li><a href="#accounts">Fixed deposit</a></li><li><a href="#accounts">DPS</a></li><li><a href="#cards">Cards</a></li></ul></div>
      <div><h4>Borrow</h4><ul><li><a href="#loans">Personal loan</a></li><li><a href="#loans">Home loan</a></li><li><a href="#loans">Car loan</a></li><li><a href="#loans">SME loan</a></li><li><a href="#tools">Loan calculator</a></li></ul></div>
      <div><h4>Services</h4><ul><li><a href="#digital">Digital banking</a></li><li><a href="#rates">Exchange rates</a></li><li><a href="#remittance">Remittance</a></li><li><a href="#branches">Branches &amp; ATMs</a></li><li><a href="#security">Security centre</a></li></ul></div>
      <div><h4>About</h4><ul><li><a href="#faq">FAQ</a></li><li><a href="#contact">Contact us</a></li><li><a href="#">Careers</a></li><li><a href="#">Schedule of charges</a></li><li><a href="#">Privacy policy</a></li><li><a href="#">Terms of service</a></li><li><a href="setup.php">DB setup</a></li></ul></div>
    </div>
    <div class="foot-bottom">
      <span>© <?= date('Y') ?> <?= $bank['legal'] ?>. All rights reserved.</span>
      <span>Hotline <?= $bank['hotline'] ?> · <?= $bank['email'] ?></span>
    </div>
    <p class="disclaimer">Rates, fees and limits shown are indicative, subject to change and to the bank's terms and eligibility criteria. Loans and credit cards are subject to approval. Deposits are protected under applicable Bangladesh law.</p>
  </div>
</footer>

<script>
(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const bdt = n => '৳' + Math.round(n).toLocaleString('en-BD');

  /* Theme */
  const root = document.documentElement, tb = $('#themeBtn');
  const setTheme = t => {
    root.dataset.theme = t;
    try { localStorage.setItem('asha-theme', t); } catch (e) {}
    tb.innerHTML = t === 'dark' ? "<i class='bx bx-sun'></i>" : "<i class='bx bx-moon'></i>";
  };
  let saved = null; try { saved = localStorage.getItem('asha-theme'); } catch (e) {}
  setTheme(saved || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
  tb.addEventListener('click', () => setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark'));

  /* Mobile menu with full-page blur backdrop */
  const burger = $('#burger'), nav = $('#nav'), navBackdrop = $('#navBackdrop');

  function closeMobileNav() {
    if (!nav) return;
    nav.classList.remove('open');
    if (navBackdrop) navBackdrop.classList.remove('open');
    if (burger) {
      burger.setAttribute('aria-expanded', 'false');
      burger.classList.remove('active');
      const icon = burger.querySelector('i');
      if (icon) icon.className = 'bx bx-menu';
    }
    document.body.classList.remove('nav-locked');
  }

  function openMobileNav() {
    if (!nav) return;
    nav.classList.add('open');
    if (navBackdrop) navBackdrop.classList.add('open');
    if (burger) {
      burger.setAttribute('aria-expanded', 'true');
      burger.classList.add('active');
      const icon = burger.querySelector('i');
      if (icon) icon.className = 'bx bx-x';
    }
    document.body.classList.add('nav-locked');
  }

  if (burger && nav) {
    burger.addEventListener('click', (e) => {
      e.stopPropagation();
      nav.classList.contains('open') ? closeMobileNav() : openMobileNav();
    });
  }

  // Auto-close when clicking or tapping on the blurred full-page backdrop
  if (navBackdrop) {
    navBackdrop.addEventListener('click', closeMobileNav);
    navBackdrop.addEventListener('touchstart', closeMobileNav, { passive: true });
  }

  // Auto-close when clicking any nav menu link
  $$('#nav a').forEach(a => a.addEventListener('click', closeMobileNav));

  // Auto-close on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && nav && nav.classList.contains('open')) {
      closeMobileNav();
    }
  });

  // Auto-close if resized back to desktop mode
  window.addEventListener('resize', () => {
    if (window.innerWidth >= 992 && nav && nav.classList.contains('open')) {
      closeMobileNav();
    }
  });

  /* Language toggle (EN / বাংলা) */
  $$('[data-bn]').forEach(el => el.dataset.en = el.textContent);
  $$('[data-lang]').forEach(b => b.addEventListener('click', () => {
    const bn = b.dataset.lang === 'bn';
    $$('[data-lang]').forEach(x => x.setAttribute('aria-pressed', x === b));
    $$('[data-bn]').forEach(el => el.textContent = bn ? el.dataset.bn : el.dataset.en);
    document.documentElement.lang = bn ? 'bn' : 'en';
  }));


  /* Account tabs */
  $$('#accTabs button').forEach(b => b.addEventListener('click', () => {
    $$('#accTabs button').forEach(x => x.setAttribute('aria-selected', x === b));
    $$('.acc-panel').forEach(p => p.classList.toggle('on', p.id === 'panel-' + b.dataset.acc));
  }));

  /* Calculators (0% Interest & 0.001% Storage/Custody Fee) */
  const num = id => parseFloat($(id).value) || 0;
  function emi() {
    const P = num('#emiP'), r = num('#emiR') / 1200, n = Math.max(1, num('#emiN'));
    const m = r ? P * r * Math.pow(1 + r, n) / (Math.pow(1 + r, n) - 1) : P / n;
    $('#emiOut').textContent = bdt(m);
    $('#emiTot').textContent = bdt(m * n);
    $('#emiInt').textContent = r ? bdt(m * n - P) : '৳0 (0% Interest)';
  }
  function fd() {
    const P = num('#fdP'), feeRate = 0.00001, t = num('#fdN') / 12;
    const feeAmt = Math.max(0.5, P * feeRate * t);
    const A = P - feeAmt;
    $('#fdOut').textContent = bdt(A);
    $('#fdIn').textContent = bdt(P);
    $('#fdInt').textContent = '৳' + Math.max(1, Math.round(feeAmt)).toLocaleString('en-BD') + ' (0.001%)';
  }
  function dps() {
    const M = num('#dpM'), n = num('#dpN'), feeRate = 0.00001;
    const totalDeposited = M * n;
    const feeAmt = Math.max(0.5, totalDeposited * feeRate * (n / 24));
    const A = totalDeposited - feeAmt;
    $('#dpOut').textContent = bdt(A);
    $('#dpIn').textContent = bdt(totalDeposited);
    $('#dpInt').textContent = '৳' + Math.max(1, Math.round(feeAmt)).toLocaleString('en-BD') + ' (0.001%)';
  }
  $('#emiPr').addEventListener('input', e => { $('#emiP').value = e.target.value; emi(); });
  $('#emiP').addEventListener('input', e => { $('#emiPr').value = e.target.value; emi(); });
  ['#emiR', '#emiN'].forEach(s => $(s).addEventListener('input', emi));
  ['#fdP', '#fdR', '#fdN'].forEach(s => $(s).addEventListener('input', fd));
  ['#dpM', '#dpR', '#dpN'].forEach(s => $(s).addEventListener('input', dps));
  emi(); fd(); dps();
  $$('#toolTabs button').forEach(b => b.addEventListener('click', () => {
    $$('#toolTabs button').forEach(x => x.setAttribute('aria-selected', x === b));
    $$('[data-panel]').forEach(p => p.classList.toggle('hide', p.dataset.panel !== b.dataset.tool));
  }));

  /* Currency converter */
  function conv() {
    const sel = $('#cvCur');
    const opt = sel ? sel.options[sel.selectedIndex] : null;
    const code = opt ? opt.dataset.code || 'USD' : 'USD';
    const rate = parseFloat(sel.value) || 0;
    const amt = num('#cvAmt');
    if ($('#cvCurBadge')) $('#cvCurBadge').textContent = code;
    if ($('#cvRateTag')) $('#cvRateTag').textContent = `1 ${code} = ৳${rate.toFixed(2)}`;
    $('#cvOut').textContent = '৳' + (amt * rate).toLocaleString('en-BD', { maximumFractionDigits: 2 });
  }
  $('#cvAmt').addEventListener('input', conv);
  $('#cvCur').addEventListener('change', conv);
  conv();

  /* Branch finder with interactive Bangladesh map */
  const branches = <?= json_encode($branchList, JSON_UNESCAPED_UNICODE) ?>;
  const hours = <?= json_encode($bank['hours']) ?>;
  const box = $('#brCards');
  const activeCityTitle = $('#activeCityTitle');
  const activeCityCount = $('#activeCityCount');
  const mapTooltip = $('#mapTooltip');
  const mapStage = $('#mapStage');
  const mtTitle = $('#mtTitle');
  const mtSub = $('#mtSub');

  function showCity(c) {
    const list = branches[c] || [];
    if (activeCityTitle) activeCityTitle.textContent = c;
    if (activeCityCount) activeCityCount.textContent = `${list.length} Branch${list.length > 1 ? 'es' : ''} & 24/7 ATM`;

    // Update city list buttons
    $$('#cityList button').forEach(x => {
      x.setAttribute('aria-selected', x.dataset.city === c ? 'true' : 'false');
    });

    // Update map pin active states
    $$('.map-pin').forEach(p => {
      p.classList.toggle('active', p.dataset.city === c);
    });

    // Update division vector active states
    $$('.bd-div-path').forEach(p => {
      p.classList.toggle('active', p.dataset.city === c);
    });

    // Render branch cards
    box.innerHTML = list.map((b, i) => {
      const name = typeof b === 'object' ? b.name : b;
      const addr = typeof b === 'object' ? b.address : c;
      const phone = typeof b === 'object' && b.phone ? `<br><small style="color:var(--ink-3)">Tel: ${b.phone}</small>` : '';
      return `<div class="br"><h3>${name}</h3><p>${addr}<br>${hours}${phone}</p><span class="tag">${i === 0 ? 'Branch + ATM (24/7)' : 'Branch + ATM'}</span></div>`;
    }).join('');
  }

  // City list clicks & hovers
  $$('#cityList button').forEach(b => {
    b.addEventListener('click', () => showCity(b.dataset.city));
    b.addEventListener('mouseenter', () => {
      const targetPin = $(`.map-pin[data-city="${b.dataset.city}"]`);
      if (targetPin) targetPin.classList.add('pin-hover');
      const targetPath = $(`.bd-div-path[data-city="${b.dataset.city}"]`);
      if (targetPath) targetPath.classList.add('active');
    });
    b.addEventListener('mouseleave', () => {
      $$('.map-pin').forEach(p => p.classList.remove('pin-hover'));
      $$('.bd-div-path').forEach(p => {
        p.classList.toggle('active', p.dataset.city === (activeCityTitle ? activeCityTitle.textContent : ''));
      });
    });
  });

  // Division vector paths hover & click
  $$('.bd-div-path').forEach(path => {
    const city = path.dataset.city;
    path.addEventListener('click', () => showCity(city));
    path.addEventListener('mouseenter', () => {
      const targetPin = $(`.map-pin[data-city="${city}"]`);
      if (targetPin) targetPin.classList.add('pin-hover');
    });
    path.addEventListener('mouseleave', () => {
      $$('.map-pin').forEach(p => p.classList.remove('pin-hover'));
    });
  });

  // Map pins hover & click events
  $$('.map-pin').forEach(pin => {
    const city = pin.dataset.city;
    pin.addEventListener('click', () => showCity(city));

    pin.addEventListener('mouseenter', () => {
      const list = branches[city] || [];
      if (mtTitle) mtTitle.textContent = `${city} Division`;
      if (mtSub) mtSub.textContent = `${list.length} Branch${list.length > 1 ? 'es' : ''} · ATM 24/7`;

      if (mapTooltip && mapStage) {
        const stageRect = mapStage.getBoundingClientRect();
        const pinRect = pin.getBoundingClientRect();
        const left = pinRect.left - stageRect.left + (pinRect.width / 2);
        const top = pinRect.top - stageRect.top;
        mapTooltip.style.left = `${left}px`;
        mapTooltip.style.top = `${top}px`;
        mapTooltip.style.display = 'flex';
      }
    });

    pin.addEventListener('mouseleave', () => {
      if (mapTooltip) mapTooltip.style.display = 'none';
    });
  });

  const firstCity = Object.keys(branches)[0] || 'Dhaka';
  if (firstCity) showCity(firstCity);

  /* Smooth FAQ accordion animation */
  $$('#faq details').forEach(el => {
    const summary = el.querySelector('summary');
    const content = el.querySelector('.faq-content') || el.querySelector('p');
    if (!summary || !content) return;

    let animation = null;
    let isClosing = false;
    let isExpanding = false;

    summary.addEventListener('click', (e) => {
      e.preventDefault();
      el.style.overflow = 'hidden';

      if (isClosing || !el.open) {
        // OPENING ANIMATION
        el.style.height = `${el.offsetHeight}px`;
        el.open = true;
        isExpanding = true;
        isClosing = false;

        window.requestAnimationFrame(() => {
          const startHeight = el.offsetHeight;
          const endHeight = summary.offsetHeight + content.offsetHeight;

          if (animation) animation.cancel();

          animation = el.animate({
            height: [`${startHeight}px`, `${endHeight}px`]
          }, {
            duration: 280,
            easing: 'cubic-bezier(0.16, 1, 0.3, 1)'
          });

          // Soft fade and glide for the content
          content.animate({
            opacity: [0, 1],
            transform: ['translateY(-6px)', 'translateY(0)']
          }, {
            duration: 260,
            easing: 'cubic-bezier(0.16, 1, 0.3, 1)'
          });

          animation.onfinish = () => {
            el.open = true;
            animation = null;
            isExpanding = false;
            el.style.height = '';
            el.style.overflow = '';
          };
          animation.oncancel = () => {
            isExpanding = false;
          };
        });
      } else if (isExpanding || el.open) {
        // CLOSING ANIMATION
        isClosing = true;
        isExpanding = false;
        const startHeight = el.offsetHeight;
        const endHeight = summary.offsetHeight;

        if (animation) animation.cancel();

        animation = el.animate({
          height: [`${startHeight}px`, `${endHeight}px`]
        }, {
          duration: 240,
          easing: 'cubic-bezier(0.16, 1, 0.3, 1)'
        });

        content.animate({
          opacity: [1, 0],
          transform: ['translateY(0)', 'translateY(-4px)']
        }, {
          duration: 200,
          easing: 'ease-out'
        });

        animation.onfinish = () => {
          el.open = false;
          animation = null;
          isClosing = false;
          el.style.height = '';
          el.style.overflow = '';
        };
        animation.oncancel = () => {
          isClosing = false;
        };
      }
    });
  });

  /* Clipboard copy handler for remittance instructions */
  $$('.rc-btn-copy[data-copy]').forEach(btn => {
    btn.addEventListener('click', () => {
      const text = btn.dataset.copy;
      if (!text) return;
      const setSuccess = () => {
        const orig = btn.innerHTML;
        btn.innerHTML = "<i class='bx bx-check'></i> Copied!";
        btn.classList.add('copied');
        setTimeout(() => {
          btn.innerHTML = orig;
          btn.classList.remove('copied');
        }, 1800);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(setSuccess).catch(() => {
          fallbackCopy(text, setSuccess);
        });
      } else {
        fallbackCopy(text, setSuccess);
      }
    });
  });
  function fallbackCopy(text, cb) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); cb(); } catch(e){}
    document.body.removeChild(ta);
  }
})();
</script>
</body>
</html>