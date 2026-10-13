<?php
require_once 'config/db.php';

if (isLoggedIn()) redirect('dashboard.php');

$errors = [];
$form   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $form = [
        'first_name'   => sanitize($_POST['first_name'] ?? ''),
        'last_name'    => sanitize($_POST['last_name']  ?? ''),
        'email'        => sanitize($_POST['email']      ?? ''),
        'phone'        => sanitize($_POST['phone']      ?? ''),
        'dob'          => sanitize($_POST['dob']        ?? ''),
        'gender'       => sanitize($_POST['gender']     ?? ''),
        'nid'          => sanitize($_POST['nid']        ?? ''),
        'address'      => sanitize($_POST['address']    ?? ''),
        'city'         => sanitize($_POST['city']       ?? ''),
        'username'     => sanitize($_POST['username']   ?? ''),
        'password'     => $_POST['password']            ?? '',
        'confirm_pw'   => $_POST['confirm_pw']          ?? '',
        'pin'          => $_POST['pin']                 ?? '',
        'branch_id'    => (int)($_POST['branch_id']     ?? 0),
        'product_id'   => (int)($_POST['product_id']    ?? 0),
        'agree'        => isset($_POST['agree']),
    ];

    // Validation
    if (empty($form['first_name'])) $errors['first_name'] = 'First name is required.';
    if (empty($form['last_name']))  $errors['last_name']  = 'Last name is required.';
    if (empty($form['email']) || !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Valid email is required.';
    }
    if (empty($form['phone']) || !preg_match('/^[0-9+]{10,14}$/', $form['phone'])) {
        $errors['phone'] = 'Valid phone number is required.';
    }
    if (empty($form['dob'])) {
        $errors['dob'] = 'Date of birth is required.';
    } elseif (date_diff(date_create($form['dob']), date_create())->y < 18) {
        $errors['dob'] = 'You must be at least 18 years old.';
    }
    if (empty($form['gender']))  $errors['gender']  = 'Please select gender.';
    if (empty($form['nid']))     $errors['nid']     = 'National ID is required.';
    if (empty($form['address'])) $errors['address'] = 'Address is required.';
    if (empty($form['city']))    $errors['city']    = 'City is required.';
    if (empty($form['username']) || strlen($form['username']) < 4) {
        $errors['username'] = 'Username must be at least 4 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9._]+$/', $form['username'])) {
        $errors['username'] = 'Username can only contain letters, numbers, dots and underscores.';
    }
    if (strlen($form['password']) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $form['password']) || !preg_match('/[0-9]/', $form['password'])) {
        $errors['password'] = 'Password must include at least one uppercase letter and one number.';
    }
    if ($form['password'] !== $form['confirm_pw']) {
        $errors['confirm_pw'] = 'Passwords do not match.';
    }
    if (!preg_match('/^[0-9]{4,6}$/', $form['pin'])) {
        $errors['pin'] = 'Transaction PIN must be 4-6 digits.';
    }
    if (!$form['branch_id'])  $errors['branch_id']  = 'Please select a branch.';
    if (!$form['product_id']) $errors['product_id'] = 'Please select an account type.';
    if (!$form['agree'])      $errors['agree']      = 'You must accept the terms.';

    // Uniqueness checks
    if (empty($errors)) {
        $check = $pdo->prepare("SELECT CustomerID FROM CUSTOMER WHERE Email = ? LIMIT 1");
        $check->execute([$form['email']]);
        if ($check->fetch()) $errors['email'] = 'This email is already registered.';

        $check2 = $pdo->prepare("SELECT UserID FROM DIGITALBANKINGUSER WHERE Username = ? LIMIT 1");
        $check2->execute([$form['username']]);
        if ($check2->fetch()) $errors['username'] = 'This username is already taken.';

        $check3 = $pdo->prepare("SELECT CustomerID FROM CUSTOMER WHERE NationalID = ? LIMIT 1");
        $check3->execute([$form['nid']]);
        if ($check3->fetch()) $errors['nid'] = 'This National ID is already registered.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Insert customer
            $referralCode = 'REF' . rand(100000, 999999);
            $pdo->prepare(
                "INSERT INTO CUSTOMER (FirstName, LastName, Email, Phone, DateOfBirth, Gender, NationalID, Address, City, PrimaryBranchID, ReferralCode, IsActive, CreatedAt)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())"
            )->execute([
                $form['first_name'], $form['last_name'], $form['email'],
                $form['phone'],      $form['dob'],       $form['gender'],
                $form['nid'],        $form['address'],   $form['city'],
                $form['branch_id'] ?: 1, $referralCode
            ]);
            $customerId = (int)$pdo->lastInsertId();

            // 2. Create account
            $accountNumber = generateAccountNumber($pdo);
            $minBal = (float)($pdo->query("SELECT MinBalance FROM ACCOUNTPRODUCT WHERE ProductID=" . (int)$form['product_id'])->fetchColumn() ?: 500);

            $pdo->prepare(
                "INSERT INTO ACCOUNT (CustomerID, BranchID, ProductID, AccountNumber, AvailableBalance, AccountStatus, OpeningDate)
                 VALUES (?, ?, ?, ?, ?, 'Active', NOW())"
            )->execute([$customerId, $form['branch_id'], $form['product_id'], $accountNumber, $minBal]);

            // 3. Create digital banking user
            $pdo->prepare(
                "INSERT INTO DIGITALBANKINGUSER (CustomerID, Username, PasswordHash, TransactionPIN, IsActive, CreatedAt)
                 VALUES (?, ?, ?, ?, 1, NOW())"
            )->execute([
                $customerId, $form['username'],
                password_hash($form['password'], PASSWORD_DEFAULT),
                password_hash($form['pin'],      PASSWORD_DEFAULT),
            ]);

            // 4. Auto issue virtual debit card (16 continuous digits)
            $cardNum = '4' . rand(100, 999) . rand(1000, 9999) . rand(1000, 9999) . rand(1000, 9999);
            $cvv = (string)rand(100, 999);
            $expiry = date('Y-m-d', strtotime('+4 years'));
            $cardHolder = strtoupper(trim($form['first_name'] . ' ' . $form['last_name']));
            $pdo->prepare(
                "INSERT INTO CARDS (CardNumber, CustomerID, AccountNumber, CardHolderName, ExpiryDate, CVV, CardType, CardStatus, DailyLimit, CreatedAt)
                 VALUES (?, ?, ?, ?, ?, ?, 'Debit', 'Active', 100000, NOW())"
            )->execute([$cardNum, $customerId, $accountNumber, $cardHolder, $expiry, $cvv]);

            // 5. Initial opening deposit transaction
            if ($minBal > 0) {
                $refNo = generateReference('DEP');
                $pdo->prepare(
                    "INSERT INTO TRANSACTION (TransactionTypeID, TransactionAmount, TransactionDate, ToAccountNumber, ToCustomerID, Description, TransactionStatus, ReferenceNumber)
                     VALUES (1, ?, NOW(), ?, ?, 'Account Opening Initial Deposit', 'Completed', ?)"
                )->execute([$minBal, $accountNumber, $customerId, $refNo]);
            }

            // 6. Welcome notification
            sendNotification($pdo, $customerId, 'Welcome to Asha Bank!',
                'Your account has been created. Account Number: ' . $accountNumber, 'success');

            $pdo->commit();

            setToast('Account created successfully! Welcome to Asha Bank. Please sign in.', 'success');
            redirect('login.php?registered=1&user=' . urlencode($form['username']));

        } catch (\Exception $e) {
            $pdo->inTransaction() && $pdo->rollBack();
            $errors['_'] = 'Registration failed: ' . $e->getMessage();
            error_log('[REGISTER] ' . $e->getMessage());
        }
    }
}

// Load branches and products
$branches = $pdo->query("SELECT BranchID, BranchName, City FROM BRANCH ORDER BY BranchName")->fetchAll();
$products = $pdo->query("SELECT ProductID, ProductName, AccountType, InterestRate, MinBalance FROM ACCOUNTPRODUCT ORDER BY ProductID")->fetchAll();

// Which step should open first? (the earliest step that has a server error)
$stepOf = [
    'first_name'=>1,'last_name'=>1,'email'=>1,'phone'=>1,'dob'=>1,'gender'=>1,'nid'=>1,'city'=>1,'address'=>1,
    'product_id'=>2,'branch_id'=>2,
    'username'=>3,'password'=>3,'confirm_pw'=>3,'pin'=>3,'agree'=>3,'_'=>3,
];
$startStep = 1;
if ($errors) {
    $startStep = 3;
    foreach ($errors as $f => $m) $startStep = min($startStep, $stepOf[$f] ?? 3);
}
$selProduct = (int)($form['product_id'] ?? ($products[0]['ProductID'] ?? 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Open an account — Asha Bank</title>
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <link rel="alternate icon" href="favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=Hind+Siliguri:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg:       #EEF3F0;
            --surface:  #FFFFFF;
            --ink:      #0C2A20;
            --muted:    #4F645A;
            --faint:    #8A9B92;
            --line:     #D5E0DA;
            --green:    #0B3D2E;
            --green-2:  #12573F;
            --mint:     #CFE6DA;
            --mint-bg:  #E6F2EB;
            --marigold: #F2B33D;
            --danger:   #A82A22;
            --danger-bg:#FBECEA;
            --display:  'Bricolage Grotesque', 'Hind Siliguri', sans-serif;
            --body:     'Hind Siliguri', system-ui, sans-serif;
        }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--body); font-size: 16px; line-height: 1.5;
            background: var(--bg); color: var(--ink); min-height: 100vh; min-height: 100dvh;
            display: grid; grid-template-columns: minmax(360px, 5fr) 7fr;
            -webkit-font-smoothing: antialiased;
        }

        /* ---------- Brand panel (sticky, holds the live card) ---------- */
        .brand {
            background: var(--green); color: #fff; padding: 40px 48px;
            position: sticky; top: 0; height: 100vh; height: 100dvh;
            display: flex; flex-direction: column; justify-content: space-between; overflow: hidden;
        }
        .brand::before {
            content: ''; position: absolute; right: -240px; top: 22%; width: 560px; height: 560px; border-radius: 50%;
            box-shadow: 0 0 0 60px rgba(255,255,255,.035), 0 0 0 120px rgba(255,255,255,.025), 0 0 0 180px rgba(255,255,255,.015);
        }
        .logo { display: inline-flex; align-items: center; gap: 12px; text-decoration: none; color: #fff; position: relative; z-index: 1; }
        .logo-mark { width: 38px; height: 38px; border-radius: 12px; background: var(--marigold); display: grid; place-items: center; }
        .logo-mark svg { width: 22px; height: 22px; }
        .logo-name { font-family: var(--display); font-weight: 700; font-size: 21px; letter-spacing: -.01em; }
        .brand-main { position: relative; z-index: 1; }
        .brand h2 { font-family: var(--display); font-weight: 700; font-size: clamp(30px, 3vw, 44px); line-height: 1.06; letter-spacing: -.025em; max-width: 13ch; margin-bottom: 14px; }
        .brand p { color: rgba(255,255,255,.7); max-width: 34ch; font-size: 16.5px; }

        .card-visual {
            margin-top: 40px; width: min(100%, 340px); aspect-ratio: 1.586; border-radius: 20px; padding: 22px 24px;
            background: linear-gradient(135deg, var(--marigold) 0%, #E39A1F 100%); color: #2A1B00;
            transform: rotate(-4deg); box-shadow: 0 30px 50px -20px rgba(0,0,0,.5);
            display: flex; flex-direction: column; justify-content: space-between;
            transition: transform .4s cubic-bezier(.3,.9,.3,1);
        }
        .card-visual.bump { transform: rotate(-1.5deg) scale(1.02); }
        .card-top { display: flex; justify-content: space-between; align-items: center; font-family: var(--display); font-weight: 700; font-size: 15px; }
        .chip { width: 38px; height: 28px; border-radius: 6px; background: linear-gradient(135deg, #fff6, #fff2); border: 1.5px solid #2A1B0033; }
        .card-num { font-family: var(--display); font-size: 19px; letter-spacing: .12em; font-weight: 500; }
        .card-bottom { display: flex; justify-content: space-between; gap: 12px; font-size: 13.5px; font-weight: 500; }
        .card-bottom span:first-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .card-type { margin-top: 18px; font-size: 14px; color: rgba(255,255,255,.65); min-height: 22px; }
        .brand-foot { position: relative; z-index: 1; font-size: 13px; color: rgba(255,255,255,.5); }

        /* ---------- Form side ---------- */
        .panel { padding: 40px 40px 72px; display: flex; justify-content: center; }
        .wrap { width: 100%; max-width: 600px; }
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 36px; font-size: 15px; color: var(--muted); }
        .topbar a { color: var(--green-2); font-weight: 600; text-underline-offset: 3px; }
        .mobile-logo { display: none; align-items: center; gap: 10px; text-decoration: none; color: var(--ink); }
        .mobile-logo .logo-mark { width: 32px; height: 32px; background: var(--marigold); border-radius: 10px; display: grid; place-items: center; }
        .mobile-logo .logo-mark svg { width: 18px; height: 18px; }
        .mobile-logo .logo-mark svg * { stroke: #0B3D2E; }
        .mobile-logo .logo-name { font-family: var(--display); font-weight: 700; font-size: 18px; }

        h1 { font-family: var(--display); font-weight: 700; font-size: 34px; letter-spacing: -.025em; line-height: 1.1; margin-bottom: 8px; }
        .lede { color: var(--muted); margin-bottom: 28px; }

        /* progress */
        .progress { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 8px; }
        .progress i { height: 5px; border-radius: 5px; background: var(--mint); transition: background .3s; }
        .progress i.on { background: var(--green-2); }
        .progress-label { display: flex; justify-content: space-between; font-size: 14px; color: var(--muted); margin-bottom: 28px; }
        .progress-label strong { color: var(--ink); font-weight: 600; }

        .alert { background: var(--danger-bg); color: var(--danger); border: 1px solid #F0C9C5; border-radius: 12px; padding: 14px 16px; font-size: 14.5px; margin-bottom: 24px; }
        .alert strong { display: block; margin-bottom: 4px; }
        .alert ul { padding-left: 18px; }

        .step { display: none; }
        .step.current { display: block; animation: swap .3s ease both; }
        @keyframes swap { from { opacity: 0; transform: translateX(14px); } to { opacity: 1; transform: none; } }
        .step h2 { font-family: var(--display); font-size: 22px; font-weight: 700; letter-spacing: -.015em; margin-bottom: 4px; }
        .step .hint { color: var(--muted); font-size: 15px; margin-bottom: 22px; }

        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 14px; }
        .span-2 { grid-column: 1 / -1; }
        .field label, .legend { display: block; font-size: 14.5px; font-weight: 500; margin-bottom: 6px; }
        .opt { color: var(--faint); font-weight: 400; }
        input[type="text"], input[type="email"], input[type="tel"], input[type="date"], input[type="password"], select, textarea {
            width: 100%; height: 48px; padding: 0 14px; font: 400 16px var(--body); color: var(--ink);
            background: var(--surface); border: 1.5px solid var(--line); border-radius: 12px; appearance: none;
            transition: border-color .15s, box-shadow .15s;
        }
        textarea { height: auto; padding: 12px 14px; resize: vertical; min-height: 84px; }
        select { padding-right: 40px; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%234F645A' stroke-width='2.4' stroke-linecap='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; }
        input:hover, select:hover, textarea:hover { border-color: #B9CBC1; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--green-2); box-shadow: 0 0 0 4px rgba(18,87,63,.14); }
        .is-error { border-color: var(--danger) !important; }
        .msg { font-size: 13.5px; color: var(--danger); margin-top: 5px; }
        .note { font-size: 13.5px; color: var(--faint); margin-top: 5px; }
        .pw-wrap { position: relative; }
        .pw-wrap input { padding-right: 48px; }
        .eye { position: absolute; right: 6px; top: 6px; width: 36px; height: 36px; border: 0; background: none; border-radius: 8px; color: var(--faint); cursor: pointer; display: grid; place-items: center; }
        .eye:hover { color: var(--ink); background: var(--bg); }
        .eye svg { width: 18px; height: 18px; }

        /* gender pills */
        .pills { display: flex; gap: 8px; }
        .pill { flex: 1; position: relative; }
        .pill input { position: absolute; opacity: 0; inset: 0; cursor: pointer; }
        .pill span { display: grid; place-items: center; height: 48px; border: 1.5px solid var(--line); border-radius: 12px; background: #fff; font-size: 15px; color: var(--muted); transition: all .15s; }
        .pill input:checked + span { border-color: var(--green-2); background: var(--mint-bg); color: var(--green); font-weight: 600; }
        .pill input:focus-visible + span { box-shadow: 0 0 0 4px rgba(18,87,63,.18); }
        .pills.is-error span { border-color: var(--danger); }

        /* account type */
        .products { display: grid; gap: 10px; margin-bottom: 22px; }
        .product { position: relative; display: grid; grid-template-columns: 1fr auto; align-items: center; gap: 4px 16px; padding: 16px 18px; background: #fff; border: 1.5px solid var(--line); border-radius: 14px; cursor: pointer; transition: border-color .15s, background .15s; }
        .product:hover { border-color: #B9CBC1; }
        .product input { position: absolute; opacity: 0; inset: 0; cursor: pointer; }
        .product:has(input:checked) { border-color: var(--green-2); background: var(--mint-bg); box-shadow: 0 0 0 1px var(--green-2); }
        .product:has(input:focus-visible) { box-shadow: 0 0 0 4px rgba(18,87,63,.18); }
        .p-name { font-weight: 600; font-size: 16px; }
        .p-min  { font-size: 14px; color: var(--muted); }
        .p-rate { grid-row: 1 / span 2; grid-column: 2; text-align: right; font-family: var(--display); font-weight: 700; font-size: 26px; line-height: 1; }
        .p-rate small { display: block; font: 400 12.5px var(--body); color: var(--muted); margin-top: 4px; }
        .products.is-error .product { border-color: var(--danger); }

        /* terms */
        .agree { display: flex; gap: 12px; align-items: flex-start; padding: 14px 16px; background: #fff; border: 1.5px solid var(--line); border-radius: 12px; margin-top: 22px; }
        .agree.is-error { border-color: var(--danger); }
        .agree input { width: 20px; height: 20px; margin-top: 3px; flex-shrink: 0; accent-color: var(--green-2); cursor: pointer; }
        .agree label { font-size: 14.5px; color: var(--muted); cursor: pointer; }
        .agree a { color: var(--green-2); font-weight: 500; }

        /* strength */
        .meter { display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; margin-top: 8px; }
        .meter i { height: 4px; border-radius: 4px; background: var(--line); transition: background .25s; }
        .meter[data-s="1"] i:nth-child(-n+1) { background: var(--danger); }
        .meter[data-s="2"] i:nth-child(-n+2) { background: #C77B12; }
        .meter[data-s="3"] i:nth-child(-n+3) { background: #7FA52F; }
        .meter[data-s="4"] i:nth-child(-n+4) { background: var(--green-2); }
        .meter-text { font-size: 13.5px; color: var(--muted); margin-top: 5px; min-height: 20px; }

        /* nav buttons */
        .actions { display: flex; gap: 12px; margin-top: 30px; }
        .btn { height: 50px; padding: 0 28px; border-radius: 12px; font: 600 16px var(--body); cursor: pointer; border: 0; transition: background .15s, transform .1s; }
        .btn:active { transform: scale(.985); }
        .btn:focus-visible { outline: 3px solid var(--marigold); outline-offset: 2px; }
        .btn-primary { flex: 1; background: var(--green); color: #fff; }
        .btn-primary:hover { background: var(--green-2); }
        .btn-primary:disabled { opacity: .75; cursor: progress; }
        .btn-ghost { background: transparent; color: var(--muted); border: 1.5px solid var(--line); }
        .btn-ghost:hover { background: #fff; color: var(--ink); }
        .foot { text-align: center; margin-top: 22px; color: var(--muted); font-size: 15px; }
        .foot a { color: var(--green-2); font-weight: 600; text-underline-offset: 3px; }

        @media (max-width: 960px) {
            body { grid-template-columns: 1fr; }
            .brand { display: none; }
            .panel { padding: 24px 20px 56px; }
            .mobile-logo { display: inline-flex; }
        }
        @media (max-width: 560px) {
            .grid { grid-template-columns: 1fr; }
            h1 { font-size: 29px; }
        }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; animation: none !important; } }
    </style>
</head>
<body>

<!-- Brand panel with live card -->
<aside class="brand" aria-hidden="true">
    <a href="index.php" class="logo" tabindex="-1">
        <span class="logo-mark"><svg viewBox="0 0 24 24" fill="none" stroke="#0B3D2E" stroke-width="2.2" stroke-linecap="round"><path d="M4 16a8 8 0 0 1 16 0"/><path d="M12 4v2M4.9 7.9l1.4 1.4M19.1 7.9l-1.4 1.4M2 20h20"/></svg></span>
        <span class="logo-name">Asha Bank</span>
    </a>

    <div class="brand-main">
        <h2>Open an account in about three minutes.</h2>
        <p>No paperwork and no queue. Your virtual debit card is ready the moment you finish.</p>

        <div class="card-visual" id="card">
            <div class="card-top"><span>Asha Bank</span><span class="chip"></span></div>
            <div class="card-num">4••• &nbsp;•••• &nbsp;•••• &nbsp;••••</div>
            <div class="card-bottom"><span id="cardName">Your name here</span><span id="cardExp"><?= date('m/y', strtotime('+4 years')) ?></span></div>
        </div>
        <div class="card-type" id="cardType"></div>
    </div>

    <p class="brand-foot">&copy; <?= date('Y') ?> Asha Bank. Regulated by Bangladesh Bank.</p>
</aside>

<main class="panel">
    <div class="wrap">
        <div class="topbar">
            <a href="index.php" class="mobile-logo" style="color:var(--ink);font-weight:400">
                <span class="logo-mark"><svg viewBox="0 0 24 24" fill="none" stroke-width="2.2" stroke-linecap="round"><path d="M4 16a8 8 0 0 1 16 0"/><path d="M12 4v2M4.9 7.9l1.4 1.4M19.1 7.9l-1.4 1.4M2 20h20"/></svg></span>
                <span class="logo-name">Asha Bank</span>
            </a>
            <span>Already a customer? <a href="login.php">Sign in</a></span>
        </div>

        <h1>Open your account</h1>
        <p class="lede">Three short steps. You can go back and change anything before you submit.</p>

        <div class="progress" id="progress" aria-hidden="true"><i></i><i></i><i></i></div>
        <div class="progress-label"><span id="stepName"><strong>About you</strong></span><span id="stepCount">Step 1 of 3</span></div>

        <?php if (!empty($errors['_'])): ?>
        <div class="alert" role="alert"><strong>We couldn't create your account</strong><?= e($errors['_']) ?></div>
        <?php elseif (!empty($errors)): ?>
        <div class="alert" role="alert">
            <strong>Fix these and submit again:</strong>
            <ul><?php foreach ($errors as $f => $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <form method="POST" action="register.php" id="registerForm" novalidate>
            <?= csrfField() ?>

            <!-- Step 1: About you -->
            <section class="step" data-step="1" data-name="About you">
                <h2>About you</h2>
                <p class="hint">We use these details to verify your identity.</p>
                <div class="grid">
                    <div class="field">
                        <label for="first_name">First name</label>
                        <input type="text" id="first_name" name="first_name" value="<?= e($form['first_name'] ?? '') ?>" autocomplete="given-name" required class="<?= isset($errors['first_name']) ? 'is-error' : '' ?>">
                        <?php if (isset($errors['first_name'])): ?><div class="msg"><?= e($errors['first_name']) ?></div><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="last_name">Last name</label>
                        <input type="text" id="last_name" name="last_name" value="<?= e($form['last_name'] ?? '') ?>" autocomplete="family-name" required class="<?= isset($errors['last_name']) ? 'is-error' : '' ?>">
                        <?php if (isset($errors['last_name'])): ?><div class="msg"><?= e($errors['last_name']) ?></div><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?= e($form['email'] ?? '') ?>" autocomplete="email" placeholder="you@example.com" required class="<?= isset($errors['email']) ? 'is-error' : '' ?>">
                        <?php if (isset($errors['email'])): ?><div class="msg"><?= e($errors['email']) ?></div><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="phone">Mobile number</label>
                        <input type="tel" id="phone" name="phone" value="<?= e($form['phone'] ?? '') ?>" autocomplete="tel" placeholder="01712345678" required class="<?= isset($errors['phone']) ? 'is-error' : '' ?>">
                        <?php if (isset($errors['phone'])): ?><div class="msg"><?= e($errors['phone']) ?></div><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="dob">Date of birth</label>
                        <input type="date" id="dob" name="dob" value="<?= e($form['dob'] ?? '') ?>" max="<?= date('Y-m-d', strtotime('-18 years')) ?>" autocomplete="bday" required class="<?= isset($errors['dob']) ? 'is-error' : '' ?>">
                        <?php if (isset($errors['dob'])): ?><div class="msg"><?= e($errors['dob']) ?></div><?php endif; ?>
                    </div>
                    <div class="field">
                        <span class="legend" id="genderLegend">Gender</span>
                        <div class="pills <?= isset($errors['gender']) ? 'is-error' : '' ?>" role="radiogroup" aria-labelledby="genderLegend">
                            <?php foreach (['Male','Female','Other'] as $g): ?>
                            <label class="pill"><input type="radio" name="gender" value="<?= $g ?>" <?= ($form['gender'] ?? '') === $g ? 'checked' : '' ?>><span><?= $g ?></span></label>
                            <?php endforeach; ?>
                        </div>
                        <?php if (isset($errors['gender'])): ?><div class="msg"><?= e($errors['gender']) ?></div><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="nid">National ID or passport number</label>
                        <input type="text" id="nid" name="nid" value="<?= e($form['nid'] ?? '') ?>" inputmode="numeric" required class="<?= isset($errors['nid']) ? 'is-error' : '' ?>">
                        <?php if (isset($errors['nid'])): ?><div class="msg"><?= e($errors['nid']) ?></div><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" value="<?= e($form['city'] ?? '') ?>" autocomplete="address-level2" placeholder="Dhaka" required class="<?= isset($errors['city']) ? 'is-error' : '' ?>">
                        <?php if (isset($errors['city'])): ?><div class="msg"><?= e($errors['city']) ?></div><?php endif; ?>
                    </div>
                    <div class="field span-2">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="2" autocomplete="street-address" placeholder="House, road, area" required class="<?= isset($errors['address']) ? 'is-error' : '' ?>"><?= e($form['address'] ?? '') ?></textarea>
                        <?php if (isset($errors['address'])): ?><div class="msg"><?= e($errors['address']) ?></div><?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Step 2: Account -->
            <section class="step" data-step="2" data-name="Your account">
                <h2>Choose your account</h2>
                <p class="hint">Your opening balance is the minimum for the account you pick.</p>

                <div id="productsBox" class="products <?= isset($errors['product_id']) ? 'is-error' : '' ?>" role="radiogroup" aria-label="Account type">
                    <?php foreach ($products as $prod): ?>
                    <label class="product">
                        <input type="radio" name="product_id" value="<?= (int)$prod['ProductID'] ?>" data-label="<?= e($prod['ProductName']) ?>" <?= $selProduct === (int)$prod['ProductID'] ? 'checked' : '' ?>>
                        <span class="p-name"><?= e($prod['ProductName']) ?></span>
                        <span class="p-min">Minimum balance ৳<?= number_format($prod['MinBalance']) ?></span>
                        <span class="p-rate"><?= e($prod['InterestRate']) ?>%<small>interest a year</small></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($errors['product_id'])): ?><div class="msg" style="margin:-12px 0 18px"><?= e($errors['product_id']) ?></div><?php endif; ?>

                <div class="field">
                    <label for="branch_id">Home branch</label>
                    <select id="branch_id" name="branch_id" required class="<?= isset($errors['branch_id']) ? 'is-error' : '' ?>">
                        <option value="">Select a branch</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= (int)$b['BranchID'] ?>" <?= (int)($form['branch_id'] ?? 0) === (int)$b['BranchID'] ? 'selected' : '' ?>><?= e($b['BranchName']) ?>, <?= e($b['City']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['branch_id'])): ?><div class="msg"><?= e($errors['branch_id']) ?></div><?php endif; ?>
                </div>
            </section>

            <!-- Step 3: Security -->
            <section class="step" data-step="3" data-name="Sign-in details">
                <h2>Set up your sign-in</h2>
                <p class="hint">You'll use the username and password to sign in, and the PIN to approve transactions.</p>
                <div class="grid">
                    <div class="field span-2">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?= e($form['username'] ?? '') ?>" autocomplete="username" autocapitalize="none" spellcheck="false" required class="<?= isset($errors['username']) ? 'is-error' : '' ?>">
                        <?php if (isset($errors['username'])): ?><div class="msg"><?= e($errors['username']) ?></div><?php else: ?><div class="note">At least 4 characters. Letters, numbers, dots and underscores.</div><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="password">Password</label>
                        <div class="pw-wrap">
                            <input type="password" id="password" name="password" autocomplete="new-password" required class="<?= isset($errors['password']) ? 'is-error' : '' ?>">
                            <button type="button" class="eye" data-for="password" aria-label="Show password"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                        </div>
                        <div class="meter" id="meter" data-s="0"><i></i><i></i><i></i><i></i></div>
                        <div class="meter-text" id="meterText">8 or more characters, with a capital letter and a number.</div>
                        <?php if (isset($errors['password'])): ?><div class="msg"><?= e($errors['password']) ?></div><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="confirm_pw">Confirm password</label>
                        <div class="pw-wrap">
                            <input type="password" id="confirm_pw" name="confirm_pw" autocomplete="new-password" required class="<?= isset($errors['confirm_pw']) ? 'is-error' : '' ?>">
                            <button type="button" class="eye" data-for="confirm_pw" aria-label="Show password"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
                        </div>
                        <div class="msg" id="matchMsg" style="display:none">Passwords don't match yet.</div>
                        <?php if (isset($errors['confirm_pw'])): ?><div class="msg"><?= e($errors['confirm_pw']) ?></div><?php endif; ?>
                    </div>
                    <div class="field span-2">
                        <label for="pin">Transaction PIN</label>
                        <input type="password" id="pin" name="pin" maxlength="6" inputmode="numeric" pattern="[0-9]*" autocomplete="new-password" required style="max-width:200px;letter-spacing:.3em" class="<?= isset($errors['pin']) ? 'is-error' : '' ?>">
                        <?php if (isset($errors['pin'])): ?><div class="msg"><?= e($errors['pin']) ?></div><?php else: ?><div class="note">4 to 6 digits. Keep it different from your password.</div><?php endif; ?>
                    </div>
                </div>

                <div class="agree <?= isset($errors['agree']) ? 'is-error' : '' ?>">
                    <input type="checkbox" id="agree" name="agree" <?= !empty($form['agree']) ? 'checked' : '' ?>>
                    <label for="agree">I agree to the <a href="#">Terms and Conditions</a> and <a href="#">Privacy Policy</a>, and confirm the details I gave are accurate.</label>
                </div>
                <?php if (isset($errors['agree'])): ?><div class="msg"><?= e($errors['agree']) ?></div><?php endif; ?>
            </section>

            <div class="actions">
                <button type="button" class="btn btn-ghost" id="backBtn" hidden>Back</button>
                <button type="button" class="btn btn-primary" id="nextBtn">Continue</button>
                <button type="submit" class="btn btn-primary" id="submitBtn" hidden>Create account</button>
            </div>
        </form>

        <p class="foot">Already have an account? <a href="login.php">Sign in</a></p>
    </div>
</main>

<script>
(function () {
    const form   = document.getElementById('registerForm');
    const steps  = [...form.querySelectorAll('.step')];
    const bars   = [...document.querySelectorAll('#progress i')];
    const back   = document.getElementById('backBtn');
    const next   = document.getElementById('nextBtn');
    const submit = document.getElementById('submitBtn');
    const stepName  = document.getElementById('stepName');
    const stepCount = document.getElementById('stepCount');
    let current = <?= (int)$startStep ?>;

    function show(n, focus) {
        current = n;
        steps.forEach((s, i) => s.classList.toggle('current', i === n - 1));
        bars.forEach((b, i) => b.classList.toggle('on', i < n));
        back.hidden   = n === 1;
        next.hidden   = n === steps.length;
        submit.hidden = n !== steps.length;
        stepName.innerHTML  = '<strong>' + steps[n - 1].dataset.name + '</strong>';
        stepCount.textContent = 'Step ' + n + ' of ' + steps.length;
        if (focus) {
            const first = steps[n - 1].querySelector('.is-error, input:not([type=radio]):not([type=checkbox]), select, textarea');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            first && setTimeout(() => first.focus({ preventScroll: true }), 50);
        }
    }

    function flag(el, message) {
        el.classList.add('is-error');
        const target = el.closest('.pw-wrap') || el;
        let m = target.parentElement.querySelector('.js-msg');
        if (!m) { m = document.createElement('div'); m.className = 'msg js-msg'; target.insertAdjacentElement('afterend', m); }
        m.textContent = message;
    }
    function clearFlag(el) {
        el.classList.remove('is-error');
        const target = el.closest('.pw-wrap') || el;
        const m = target.parentElement.querySelector('.js-msg');
        if (m) m.remove();
    }

    // Returns the first invalid element in a step, flagging all of them
    function validate(n) {
        const sec = steps[n - 1];
        let firstBad = null;
        const bad = (el, msg) => { flag(el, msg); firstBad = firstBad || el; };

        sec.querySelectorAll('input[required]:not([type=radio]), select[required], textarea[required]').forEach(el => {
            clearFlag(el);
            if (!el.value.trim()) return bad(el, 'Fill in this field.');
            if (el.type === 'email' && !el.checkValidity()) return bad(el, 'Enter a valid email address.');
            if (el.id === 'phone' && !/^[0-9+]{10,14}$/.test(el.value)) return bad(el, 'Enter 10 to 14 digits, for example 01712345678.');
            if (el.id === 'username' && !/^[A-Za-z0-9._]{4,}$/.test(el.value)) return bad(el, 'Use at least 4 letters, numbers, dots or underscores.');
            if (el.id === 'password' && !(el.value.length >= 8 && /[A-Z]/.test(el.value) && /[0-9]/.test(el.value))) return bad(el, 'Use 8 or more characters with a capital letter and a number.');
            if (el.id === 'confirm_pw' && el.value !== document.getElementById('password').value) return bad(el, 'Passwords don\'t match.');
            if (el.id === 'pin' && !/^[0-9]{4,6}$/.test(el.value)) return bad(el, 'Enter 4 to 6 digits.');
            if (el.id === 'dob') {
                if (!el.value || el.value > el.max) return bad(el, 'You must be at least 18 years old.');
            }
        });

        const pills = sec.querySelector('.pills');
        if (pills) { clearFlag(pills); if (!pills.querySelector('input:checked')) bad(pills, 'Select a gender.'); }
        const prods = sec.querySelector('.products');
        if (prods) { clearFlag(prods); if (!prods.querySelector('input:checked')) bad(prods, 'Select an account type.'); }
        const agree = sec.querySelector('#agree');
        if (agree) { const box = agree.closest('.agree'); clearFlag(box); if (!agree.checked) bad(box, 'Accept the terms to continue.'); }

        return firstBad;
    }

    function goNext() {
        const bad = validate(current);
        if (bad) { (bad.querySelector('input') || bad).focus({ preventScroll: false }); return; }
        show(current + 1, true);
    }
    next.addEventListener('click', goNext);
    back.addEventListener('click', () => show(current - 1, true));

    // Enter moves forward on steps 1 and 2 instead of submitting a partial form
    form.addEventListener('keydown', e => {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && current < steps.length) { e.preventDefault(); goNext(); }
    });

    form.addEventListener('submit', e => {
        for (let n = 1; n <= steps.length; n++) {
            const bad = validate(n);
            if (bad) { e.preventDefault(); show(n, true); return; }
        }
        setTimeout(() => {
            submit.disabled = true;
            submit.textContent = 'Creating your account...';
        }, 10);
    });

    // Clear a field's error as soon as the person edits it
    form.addEventListener('input', e => { if (e.target.classList.contains('is-error')) clearFlag(e.target); });
    form.addEventListener('change', e => {
        const g = e.target.closest('.pills, .products'); if (g) clearFlag(g);
        const a = e.target.closest('.agree'); if (a) clearFlag(a);
    });

    // Live card preview
    const card = document.getElementById('card'), cardName = document.getElementById('cardName'), cardType = document.getElementById('cardType');
    const fn = document.getElementById('first_name'), ln = document.getElementById('last_name');
    function updateName() {
        const n = (fn.value + ' ' + ln.value).trim();
        cardName.textContent = n || 'Your name here';
    }
    fn.addEventListener('input', updateName); ln.addEventListener('input', updateName); updateName();
    function updateType() {
        const sel = form.querySelector('input[name=product_id]:checked');
        cardType.textContent = sel ? sel.dataset.label + ' account' : '';
        card.classList.add('bump'); setTimeout(() => card.classList.remove('bump'), 350);
    }
    form.querySelectorAll('input[name=product_id]').forEach(r => r.addEventListener('change', updateType));
    (function () { const sel = form.querySelector('input[name=product_id]:checked'); if (sel) cardType.textContent = sel.dataset.label + ' account'; })();

    // Password strength + match hint
    const pw = document.getElementById('password'), cpw = document.getElementById('confirm_pw');
    const meter = document.getElementById('meter'), meterText = document.getElementById('meterText'), matchMsg = document.getElementById('matchMsg');
    const labels = ['8 or more characters, with a capital letter and a number.', 'Weak', 'Fair', 'Good', 'Strong'];
    pw.addEventListener('input', () => {
        const v = pw.value; let s = 0;
        if (v.length >= 8) s++; if (/[A-Z]/.test(v)) s++; if (/[0-9]/.test(v)) s++; if (/[^A-Za-z0-9]/.test(v)) s++;
        if (!v) s = 0;
        meter.dataset.s = s; meterText.textContent = labels[s]; checkMatch();
    });
    function checkMatch() { matchMsg.style.display = cpw.value && cpw.value !== pw.value ? 'block' : 'none'; }
    cpw.addEventListener('input', checkMatch);

    // PIN: digits only
    document.getElementById('pin').addEventListener('input', e => { e.target.value = e.target.value.replace(/\D/g, ''); });

    // Show / hide password buttons
    const EYE = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    const EYE_OFF = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/>';
    document.querySelectorAll('.eye').forEach(btn => btn.addEventListener('click', () => {
        const input = document.getElementById(btn.dataset.for), show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.querySelector('svg').innerHTML = show ? EYE_OFF : EYE;
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    }));

    show(current, false);
})();
</script>
</body>
</html>