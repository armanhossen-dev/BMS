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
            $pdo->prepare(
                "INSERT INTO CUSTOMER (FirstName, LastName, Email, Phone, DateOfBirth, Gender, NationalID, Address, City, IsActive, JoinDate)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())"
            )->execute([
                $form['first_name'], $form['last_name'], $form['email'],
                $form['phone'],      $form['dob'],       $form['gender'],
                $form['nid'],        $form['address'],   $form['city'],
            ]);
            $customerId = (int)$pdo->lastInsertId();

            // 2. Create account
            $accountNumber = generateAccountNumber($pdo);
            $minBal = $pdo->query("SELECT MinBalance FROM ACCOUNTPRODUCT WHERE ProductID={$form['product_id']}")->fetchColumn() ?: 500;

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
                password_hash($form['password'], PASSWORD_ARGON2ID),
                password_hash($form['pin'],      PASSWORD_ARGON2ID),
            ]);

            // 4. Welcome notification
            sendNotification($pdo, $customerId, 'Welcome to Asha Bank!',
                'Your account has been created. Account Number: ' . $accountNumber, 'success');

            $pdo->commit();

            setToast('Account created successfully! Please sign in.', 'success');
            redirect('login.php');

        } catch (\Exception $e) {
            $pdo->inTransaction() && $pdo->rollBack();
            $errors['_'] = 'Registration failed. Please try again.';
            error_log('[REGISTER] ' . $e->getMessage());
        }
    }
}

// Load branches and products
$branches = $pdo->query("SELECT BranchID, BranchName, City FROM BRANCH WHERE IsActive = 1 ORDER BY BranchName")->fetchAll();
$products = $pdo->query("SELECT ProductID, ProductName, AccountType, InterestRate, MinBalance FROM ACCOUNTPRODUCT ORDER BY ProductID")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Open Account — Asha Bank</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --cream:     #F5F4EF; --cream-2: #EDECEA; --cream-3: #E0DED8;
            --ink:       #0E0E0E; --ink-muted: #5A5A5A; --ink-faint: #999;
            --surface:   #FFFFFF; --red: #C62828; --red-dark: #9B1B1B; --red-light: #FDEAEA;
            --green:     #2E7D32; --green-light: #E8F5E9; --sans: 'Inter', sans-serif; --serif: 'DM Serif Display', serif;
        }
        html { scroll-behavior: smooth; }
        body { font-family: var(--sans); background: var(--cream); color: var(--ink); min-height: 100vh; -webkit-font-smoothing: antialiased; }

        nav { background: var(--ink); padding: 0 40px; height: 60px; display: flex; align-items: center; justify-content: space-between; }
        .nav-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .nav-logo-mark { width: 30px; height: 30px; background: var(--red); border-radius: 8px; display: flex; align-items: center; justify-content: center; }
        .nav-logo-mark svg { width: 16px; height: 16px; }
        .nav-logo-name { font-family: var(--serif); font-size: 17px; color: white; }
        .nav-right { display: flex; align-items: center; gap: 16px; }
        .nav-right a { font-size: 13px; color: rgba(255,255,255,0.55); text-decoration: none; }
        .nav-right a:hover { color: white; }
        .btn-sign-in { background: var(--red) !important; color: white !important; padding: 7px 16px; border-radius: 100px; }

        main { max-width: 860px; margin: 0 auto; padding: 48px 24px 80px; }
        .page-hero { margin-bottom: 36px; }
        .page-hero h1 { font-size: 32px; font-weight: 700; letter-spacing: -0.5px; margin-bottom: 8px; }
        .page-hero p { color: var(--ink-muted); }

        /* Step indicator */
        .steps { display: flex; gap: 0; margin-bottom: 36px; border: 1px solid var(--cream-3); border-radius: 12px; overflow: hidden; background: var(--surface); }
        .step-indicator { flex: 1; display: flex; align-items: center; gap: 10px; padding: 14px 20px; border-right: 1px solid var(--cream-3); cursor: pointer; transition: background .2s; }
        .step-indicator:last-child { border-right: none; }
        .step-indicator.active { background: var(--ink); color: white; }
        .step-indicator.done   { background: var(--green-light); }
        .step-num { width: 26px; height: 26px; border-radius: 50%; background: var(--cream-2); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; }
        .step-indicator.active .step-num { background: var(--red); color: white; }
        .step-indicator.done   .step-num { background: var(--green); color: white; }
        .step-info { min-width: 0; }
        .step-label { font-size: 12px; font-weight: 500; }
        .step-indicator.active .step-label, .step-indicator.done .step-label { color: inherit; }
        .step-sub   { font-size: 11px; opacity: .55; }

        .card { background: var(--surface); border: 1px solid var(--cream-3); border-radius: 16px; overflow: hidden; margin-bottom: 20px; }
        .card-header { padding: 18px 24px; border-bottom: 1px solid var(--cream-3); }
        .card-header h3 { font-size: 16px; font-weight: 600; }
        .card-body { padding: 24px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 0; }
        label { display: block; font-size: 13px; font-weight: 500; color: var(--ink-muted); margin-bottom: 6px; }
        .required { color: var(--red); margin-left: 2px; }
        input, select, textarea {
            width: 100%; padding: 10px 14px;
            border: 1.5px solid var(--cream-3); border-radius: 8px;
            font-family: var(--sans); font-size: 14px; color: var(--ink);
            background: var(--surface); appearance: none;
            transition: border-color .2s, box-shadow .2s;
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--ink); box-shadow: 0 0 0 3px rgba(14,14,14,.08); }
        .is-error { border-color: var(--red) !important; }
        .field-error { font-size: 12px; color: var(--red); margin-top: 4px; }
        select { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%235A5A5A' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 40px; }

        .section-sep { border: none; border-top: 1px solid var(--cream-3); margin: 24px 0; }

        .product-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 4px; }
        .product-card { border: 2px solid var(--cream-3); border-radius: 12px; padding: 16px; cursor: pointer; transition: all .2s; position: relative; }
        .product-card:hover { border-color: var(--ink-muted); }
        .product-card.selected { border-color: var(--ink); background: var(--cream); }
        .product-card input[type="radio"] { position: absolute; opacity: 0; }
        .product-name { font-size: 14px; font-weight: 600; margin-bottom: 4px; }
        .product-rate { font-size: 22px; font-weight: 700; color: var(--ink); }
        .product-rate-label { font-size: 10px; color: var(--ink-faint); }
        .product-min { font-size: 12px; color: var(--ink-muted); margin-top: 6px; }
        .product-card.selected::after { content: '✓'; position: absolute; top: 10px; right: 12px; width: 20px; height: 20px; background: var(--ink); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; }

        .error-summary { background: var(--red-light); border: 1px solid #ffcdd2; border-radius: 12px; padding: 16px; margin-bottom: 24px; }
        .error-summary h4 { color: var(--red); font-size: 14px; margin-bottom: 8px; }
        .error-summary ul { padding-left: 18px; }
        .error-summary ul li { color: var(--red); font-size: 13px; margin-bottom: 3px; }

        .agree-box { display: flex; align-items: flex-start; gap: 12px; padding: 16px; background: var(--cream-2); border-radius: 12px; }
        .agree-box input[type="checkbox"] { width: 18px; height: 18px; flex-shrink: 0; margin-top: 1px; cursor: pointer; }
        .agree-box label { font-size: 13px; color: var(--ink-muted); cursor: pointer; }
        .agree-box label a { color: var(--red); }

        .pw-strength { margin-top: 6px; display: flex; gap: 4px; }
        .pw-bar { flex: 1; height: 3px; background: var(--cream-3); border-radius: 4px; transition: background .3s; }
        .pw-bar.active-1 { background: var(--red); }
        .pw-bar.active-2 { background: var(--amber, #B45309); }
        .pw-bar.active-3 { background: var(--green); }
        .pw-text { font-size: 11px; color: var(--ink-faint); margin-top: 4px; }

        .btn-submit { width: 100%; padding: 14px; background: var(--ink); color: white; border: none; border-radius: 100px; font-family: var(--sans); font-size: 15px; font-weight: 600; cursor: pointer; transition: background .2s, transform .15s; margin-top: 24px; }
        .btn-submit:hover { background: var(--red); transform: translateY(-1px); }

        .back-link { display: inline-flex; align-items: center; gap: 6px; font-size: 14px; color: var(--ink-muted); text-decoration: none; margin-bottom: 24px; }
        .back-link:hover { color: var(--red); }
        .back-link svg { width: 16px; height: 16px; }

        @media (max-width: 640px) {
            .form-grid { grid-template-columns: 1fr; }
            .product-grid { grid-template-columns: 1fr; }
            .steps { flex-direction: column; }
            .step-indicator { border-right: none; border-bottom: 1px solid var(--cream-3); }
        }
    </style>
</head>
<body>

<nav role="navigation">
    <a href="index.php" class="nav-logo">
        <div class="nav-logo-mark">
            <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="9" width="20" height="13" rx="2"/>
                <path d="M6 9V7a6 6 0 0 1 12 0v2"/>
                <circle cx="12" cy="15" r="2" fill="white" stroke="none"/>
            </svg>
        </div>
        <span class="nav-logo-name">Asha Bank</span>
    </a>
    <div class="nav-right">
        <a href="login.php">Sign In</a>
        <a href="login.php" class="btn-sign-in">Already have an account</a>
    </div>
</nav>

<main role="main">
    <a href="index.php" class="back-link">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Back to home
    </a>

    <div class="page-hero">
        <h1>Open your account</h1>
        <p>Takes about 3 minutes. No paperwork, no queues.</p>
    </div>

    <?php if (!empty($errors['_'])): ?>
    <div class="error-summary" role="alert">
        <h4>Registration failed</h4>
        <p style="font-size:13px;color:var(--red);"><?= e($errors['_']) ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors) && empty($errors['_'])): ?>
    <div class="error-summary" role="alert">
        <h4>Please fix the following errors:</h4>
        <ul>
            <?php foreach ($errors as $field => $msg): if ($field === '_') continue; ?>
            <li><?= e($msg) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="register.php" id="registerForm">
        <?= csrfField() ?>

        <!-- Personal Info -->
        <div class="card">
            <div class="card-header">
                <h3>Personal Information</h3>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name"
                               value="<?= e($form['first_name'] ?? '') ?>"
                               class="<?= isset($errors['first_name']) ? 'is-error' : '' ?>"
                               placeholder="Rahman" required>
                        <?php if (isset($errors['first_name'])): ?><div class="field-error"><?= e($errors['first_name']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name"
                               value="<?= e($form['last_name'] ?? '') ?>"
                               class="<?= isset($errors['last_name']) ? 'is-error' : '' ?>"
                               placeholder="Chowdhury" required>
                        <?php if (isset($errors['last_name'])): ?><div class="field-error"><?= e($errors['last_name']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" id="email" name="email"
                               value="<?= e($form['email'] ?? '') ?>"
                               class="<?= isset($errors['email']) ? 'is-error' : '' ?>"
                               placeholder="rahman@example.com" required>
                        <?php if (isset($errors['email'])): ?><div class="field-error"><?= e($errors['email']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" id="phone" name="phone"
                               value="<?= e($form['phone'] ?? '') ?>"
                               class="<?= isset($errors['phone']) ? 'is-error' : '' ?>"
                               placeholder="01712345678" required>
                        <?php if (isset($errors['phone'])): ?><div class="field-error"><?= e($errors['phone']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="dob">Date of Birth <span class="required">*</span></label>
                        <input type="date" id="dob" name="dob"
                               value="<?= e($form['dob'] ?? '') ?>"
                               class="<?= isset($errors['dob']) ? 'is-error' : '' ?>"
                               max="<?= date('Y-m-d', strtotime('-18 years')) ?>" required>
                        <?php if (isset($errors['dob'])): ?><div class="field-error"><?= e($errors['dob']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender <span class="required">*</span></label>
                        <select id="gender" name="gender" class="<?= isset($errors['gender']) ? 'is-error' : '' ?>">
                            <option value="">Select gender</option>
                            <option value="Male"   <?= ($form['gender'] ?? '') === 'Male'   ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($form['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Other"  <?= ($form['gender'] ?? '') === 'Other'  ? 'selected' : '' ?>>Other</option>
                        </select>
                        <?php if (isset($errors['gender'])): ?><div class="field-error"><?= e($errors['gender']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="nid">National ID / Passport <span class="required">*</span></label>
                        <input type="text" id="nid" name="nid"
                               value="<?= e($form['nid'] ?? '') ?>"
                               class="<?= isset($errors['nid']) ? 'is-error' : '' ?>"
                               placeholder="NID number" required>
                        <?php if (isset($errors['nid'])): ?><div class="field-error"><?= e($errors['nid']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="city">City <span class="required">*</span></label>
                        <input type="text" id="city" name="city"
                               value="<?= e($form['city'] ?? '') ?>"
                               class="<?= isset($errors['city']) ? 'is-error' : '' ?>"
                               placeholder="Dhaka" required>
                        <?php if (isset($errors['city'])): ?><div class="field-error"><?= e($errors['city']) ?></div><?php endif; ?>
                    </div>
                </div>
                <div class="form-group" style="margin-top:16px;">
                    <label for="address">Full Address <span class="required">*</span></label>
                    <textarea id="address" name="address" rows="2"
                              class="<?= isset($errors['address']) ? 'is-error' : '' ?>"
                              placeholder="House, Road, Area"><?= e($form['address'] ?? '') ?></textarea>
                    <?php if (isset($errors['address'])): ?><div class="field-error"><?= e($errors['address']) ?></div><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Account Setup -->
        <div class="card">
            <div class="card-header"><h3>Account Setup</h3></div>
            <div class="card-body">
                <div style="margin-bottom:20px;">
                    <label>Account Type <span class="required">*</span></label>
                    <?php if (isset($errors['product_id'])): ?><div class="field-error" style="margin-bottom:8px;"><?= e($errors['product_id']) ?></div><?php endif; ?>
                    <div class="product-grid">
                        <?php foreach ($products as $prod): ?>
                        <label class="product-card <?= (int)($form['product_id'] ?? 0) === (int)$prod['ProductID'] ? 'selected' : '' ?>"
                               for="prod_<?= $prod['ProductID'] ?>">
                            <input type="radio" name="product_id" id="prod_<?= $prod['ProductID'] ?>"
                                   value="<?= $prod['ProductID'] ?>"
                                   <?= (int)($form['product_id'] ?? 0) === (int)$prod['ProductID'] ? 'checked' : '' ?>>
                            <div class="product-name"><?= e($prod['ProductName']) ?></div>
                            <div class="product-rate"><?= $prod['InterestRate'] ?>%<span class="product-rate-label"> p.a.</span></div>
                            <div class="product-min">Min: ৳<?= number_format($prod['MinBalance']) ?></div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="branch_id">Preferred Branch <span class="required">*</span></label>
                    <select id="branch_id" name="branch_id" class="<?= isset($errors['branch_id']) ? 'is-error' : '' ?>">
                        <option value="">Select a branch</option>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['BranchID'] ?>" <?= (int)($form['branch_id'] ?? 0) === (int)$b['BranchID'] ? 'selected' : '' ?>>
                            <?= e($b['BranchName']) ?> — <?= e($b['City']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['branch_id'])): ?><div class="field-error"><?= e($errors['branch_id']) ?></div><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Credentials -->
        <div class="card">
            <div class="card-header"><h3>Login Credentials</h3></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="username">Username <span class="required">*</span></label>
                        <input type="text" id="username" name="username"
                               value="<?= e($form['username'] ?? '') ?>"
                               class="<?= isset($errors['username']) ? 'is-error' : '' ?>"
                               placeholder="min 4 characters" autocomplete="username" required>
                        <?php if (isset($errors['username'])): ?><div class="field-error"><?= e($errors['username']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="pin">Transaction PIN <span class="required">*</span></label>
                        <input type="password" id="pin" name="pin"
                               class="<?= isset($errors['pin']) ? 'is-error' : '' ?>"
                               placeholder="4-6 digits" maxlength="6" inputmode="numeric"
                               autocomplete="new-password" required>
                        <?php if (isset($errors['pin'])): ?><div class="field-error"><?= e($errors['pin']) ?></div><?php endif; ?>
                        <div style="font-size:11px;color:var(--ink-faint);margin-top:4px;">Used to authorize transactions</div>
                    </div>
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <input type="password" id="password" name="password"
                               class="<?= isset($errors['password']) ? 'is-error' : '' ?>"
                               placeholder="Min 8 chars" autocomplete="new-password" required>
                        <div class="pw-strength">
                            <div class="pw-bar" id="bar1"></div>
                            <div class="pw-bar" id="bar2"></div>
                            <div class="pw-bar" id="bar3"></div>
                            <div class="pw-bar" id="bar4"></div>
                        </div>
                        <div class="pw-text" id="pwText">Enter a password</div>
                        <?php if (isset($errors['password'])): ?><div class="field-error"><?= e($errors['password']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="confirm_pw">Confirm Password <span class="required">*</span></label>
                        <input type="password" id="confirm_pw" name="confirm_pw"
                               class="<?= isset($errors['confirm_pw']) ? 'is-error' : '' ?>"
                               placeholder="Repeat password" autocomplete="new-password" required>
                        <?php if (isset($errors['confirm_pw'])): ?><div class="field-error"><?= e($errors['confirm_pw']) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Terms -->
        <div class="agree-box <?= isset($errors['agree']) ? 'is-error' : '' ?>" style="border:<?= isset($errors['agree']) ? '2px solid var(--red)' : '1px solid transparent' ?>;margin-bottom:4px;">
            <input type="checkbox" id="agree" name="agree" <?= !empty($form['agree']) ? 'checked' : '' ?>>
            <label for="agree">
                I agree to the <a href="#">Terms & Conditions</a> and <a href="#">Privacy Policy</a> of Asha Bank.
                I confirm that all information provided is accurate and complete.
            </label>
        </div>
        <?php if (isset($errors['agree'])): ?><div class="field-error" style="margin-bottom:16px;"><?= e($errors['agree']) ?></div><?php endif; ?>

        <button type="submit" class="btn-submit" id="submitBtn">
            Create My Account →
        </button>

        <p style="text-align:center;font-size:13px;color:var(--ink-faint);margin-top:16px;">
            Already have an account? <a href="login.php" style="color:var(--red);font-weight:500;">Sign in</a>
        </p>
    </form>
</main>

<script>
// Product card selection
document.querySelectorAll('.product-card').forEach(card => {
    card.addEventListener('click', function() {
        document.querySelectorAll('.product-card').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
    });
    const radio = card.querySelector('input[type="radio"]');
    if (radio.checked) card.classList.add('selected');
});

// Password strength
const pwInput = document.getElementById('password');
const bars    = [document.getElementById('bar1'), document.getElementById('bar2'), document.getElementById('bar3'), document.getElementById('bar4')];
const pwText  = document.getElementById('pwText');

pwInput.addEventListener('input', () => {
    const pw = pwInput.value;
    let score = 0;
    if (pw.length >= 8) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    const labels  = ['Too short', 'Weak', 'Fair', 'Strong', 'Very strong'];
    const classes = ['', 'active-1', 'active-1', 'active-2', 'active-3'];
    bars.forEach((bar, i) => {
        bar.className = 'pw-bar ' + (i < score ? classes[score] : '');
    });
    pwText.textContent = labels[score] || 'Enter a password';
    pwText.style.color = score <= 1 ? 'var(--red)' : score === 2 ? 'var(--amber, #B45309)' : 'var(--green)';
});

// Submit loading
document.getElementById('registerForm').addEventListener('submit', () => {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'Creating your account…';
    setTimeout(() => { btn.disabled = false; btn.textContent = 'Create My Account →'; }, 15000);
});
</script>
</body>
</html>
