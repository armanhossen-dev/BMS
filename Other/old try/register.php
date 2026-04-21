<?php
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = $success = '';
$step  = 1; // multi-step feel but single form

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect & sanitize
    $full_name  = trim($conn->real_escape_string($_POST['full_name']));
    $email      = trim($conn->real_escape_string($_POST['email']));
    $phone      = trim($conn->real_escape_string($_POST['phone']));
    $dob        = $conn->real_escape_string($_POST['dob']);
    $gender     = $conn->real_escape_string($_POST['gender']);
    $id_type    = $conn->real_escape_string($_POST['id_type']);
    $id_number  = trim($conn->real_escape_string($_POST['id_number']));
    $address    = trim($conn->real_escape_string($_POST['address']));
    $acc_type   = $conn->real_escape_string($_POST['account_type']);
    $password   = $_POST['password'];
    $confirm_pw = $_POST['confirm_password'];

    // Validate
    if (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_pw) {
        $error = 'Passwords do not match.';
    } else {
        // Check duplicate email
        $check = $conn->query("SELECT id FROM users WHERE email='$email'");
        if ($check->num_rows > 0) {
            $error = 'An account with this email already exists. Please sign in.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $conn->begin_transaction();
            try {
                // Insert user
                $conn->query("INSERT INTO users (full_name, email, password, role)
                              VALUES ('$full_name','$email','$hashed','customer')");
                $user_id = $conn->insert_id;

                // Insert customer
                $cust_id = generateCustomerId($conn);
                $conn->query("INSERT INTO customers (customer_id, user_id, full_name, email, phone, address, dob, gender, id_type, id_number)
                              VALUES ('$cust_id', $user_id, '$full_name', '$email', '$phone', '$address', '$dob', '$gender', '$id_type', '$id_number')");
                $cust_db_id = $conn->insert_id;

                // Open initial account
                $acc_num = generateAccountNumber($conn);
                $rates   = ['savings'=>5.5,'current'=>0,'fixed_deposit'=>9];
                $rate    = $rates[$acc_type] ?? 0;
                $conn->query("INSERT INTO accounts (account_number, customer_id, account_type, balance, interest_rate, opened_date)
                              VALUES ('$acc_num', $cust_db_id, '$acc_type', 0.00, $rate, CURDATE())");

                $conn->commit();
                $success = "Welcome to Apex Bank, $full_name! Your Customer ID is <strong>$cust_id</strong> and account number is <strong>$acc_num</strong>. <a href='login.php' style='color:#C9A84C;'>Sign in now →</a>";
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Registration failed. Please try again. ('.$e->getMessage().')';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Open Account – Apex Bank</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,400&family=Jost:wght@200;300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        :root {
            --gold:#C9A84C; --gold-light:#E8C97A; --gold-dark:#9A7B2F;
            --gold-dim:rgba(201,168,76,0.1); --gold-line:rgba(201,168,76,0.2);
            --black:#080808; --black-2:#0E0E0E; --black-3:#141414; --black-4:#1C1C1C;
            --white:#F8F4EC; --white-dim:rgba(248,244,236,0.65);
            --success:#2ECC71; --danger:#E74C3C;
        }
        html,body { min-height:100%; }
        body {
            background:var(--black); font-family:'Jost',sans-serif;
            font-weight:300; color:var(--white);
        }

        /* TOPBAR */
        .auth-topbar {
            padding:18px 40px; display:flex; align-items:center; justify-content:space-between;
            border-bottom:1px solid var(--gold-line); background:var(--black-2);
            position:sticky; top:0; z-index:100;
        }

        .auth-logo { display:flex; align-items:center; gap:10px; text-decoration:none; }
        .auth-logo-mark {
            width:36px; height:36px; border:1px solid var(--gold); border-radius:7px;
            display:flex; align-items:center; justify-content:center;
            font-family:'Cormorant Garamond',serif; font-size:1.1rem;
            font-weight:600; color:var(--gold); position:relative;
        }
        .auth-logo-mark::before { content:''; position:absolute; inset:3px; border:1px solid rgba(201,168,76,0.25); border-radius:4px; }
        .auth-logo-text { font-family:'Cormorant Garamond',serif; font-size:1.1rem; font-weight:600; letter-spacing:0.12em; color:var(--white); }
        .auth-logo-text span { color:var(--gold); }
        .auth-topbar-right { font-size:0.78rem; color:var(--white-dim); }
        .auth-topbar-right a { color:var(--gold); text-decoration:none; font-weight:500; }

        /* LAYOUT */
        .reg-wrap {
            display:grid; grid-template-columns:340px 1fr;
            min-height:calc(100vh - 65px);
        }

        /* LEFT SIDEBAR */
        .reg-sidebar {
            background:var(--black-2); border-right:1px solid var(--gold-line);
            padding:48px 36px; position:sticky; top:65px; height:calc(100vh - 65px);
            overflow-y:auto;
        }

        .sidebar-heading {
            font-family:'Cormorant Garamond',serif;
            font-size:1.6rem; font-weight:600;
            color:var(--white); line-height:1.2;
            margin-bottom:8px;
        }

        .sidebar-heading em { color:var(--gold); font-style:italic; }

        .sidebar-desc { font-size:0.82rem; color:var(--white-dim); line-height:1.7; margin-bottom:36px; }

        .sidebar-steps { margin-bottom:36px; }

        .sidebar-step {
            display:flex; gap:14px; padding:14px 0;
            border-bottom:1px solid rgba(201,168,76,0.07);
        }

        .sidebar-step:last-child { border:none; }

        .step-circle {
            width:30px; height:30px; border-radius:50%; flex-shrink:0;
            border:1px solid var(--gold-line);
            display:flex; align-items:center; justify-content:center;
            font-size:0.72rem; font-weight:500; color:var(--white-dim);
            transition:all 0.3s;
        }

        .step-circle.active {
            background:linear-gradient(135deg,var(--gold-dark),var(--gold));
            border-color:var(--gold); color:var(--black); font-weight:700;
        }

        .step-circle.done {
            background:var(--gold-dim); border-color:var(--gold); color:var(--gold);
        }

        .step-text h5 { font-size:0.82rem; font-weight:500; color:var(--white); margin-bottom:2px; }
        .step-text p  { font-size:0.72rem; color:var(--white-dim); }

        .sidebar-perks { margin-top:8px; }
        .perk-item {
            display:flex; align-items:center; gap:10px;
            padding:8px 0; font-size:0.78rem; color:var(--white-dim);
        }
        .perk-item::before { content:'✓'; color:var(--gold); font-size:0.7rem; flex-shrink:0; }

        /* FORM AREA */
        .reg-form-area {
            padding:48px 56px;
            background:var(--black);
        }

        .reg-form-area .section-divider {
            display:flex; align-items:center; gap:12px; margin:32px 0 24px;
        }

        .reg-form-area .section-divider span {
            font-size:0.65rem; font-weight:500; letter-spacing:0.2em;
            text-transform:uppercase; color:var(--gold); white-space:nowrap;
        }

        .reg-form-area .section-divider::before,
        .reg-form-area .section-divider::after {
            content:''; flex:1; height:1px; background:var(--gold-line);
        }

        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
        .form-grid .full { grid-column:1/-1; }

        .form-field { margin-bottom:0; }
        .form-field label {
            display:block; font-size:0.67rem; font-weight:500;
            letter-spacing:0.15em; text-transform:uppercase;
            color:var(--gold); margin-bottom:7px;
        }

        .form-field input, .form-field select, .form-field textarea {
            width:100%; padding:12px 15px;
            background:var(--black-3); border:1px solid rgba(201,168,76,0.18);
            color:var(--white); font-family:'Jost',sans-serif;
            font-size:0.88rem; font-weight:300; border-radius:6px; outline:none;
            transition:all 0.3s;
        }

        .form-field select option { background:var(--black-3); }
        .form-field textarea { resize:vertical; min-height:80px; }

        .form-field input:focus, .form-field select:focus, .form-field textarea:focus {
            border-color:var(--gold); background:var(--black-4);
            box-shadow:0 0 0 3px rgba(201,168,76,0.07);
        }

        .form-field input::placeholder { color:rgba(248,244,236,0.2); }

        /* Account type cards */
        .acc-type-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:0; }

        .acc-type-card {
            border:1px solid var(--gold-line);
            border-radius:8px; padding:16px 14px;
            cursor:pointer; transition:all 0.3s;
            position:relative;
        }

        .acc-type-card:hover { border-color:var(--gold); background:var(--gold-dim); }

        .acc-type-card input[type=radio] {
            position:absolute; opacity:0; width:0; height:0;
        }

        .acc-type-card.selected {
            border-color:var(--gold); background:var(--gold-dim);
        }

        .acc-type-card.selected::before {
            content:'✓'; position:absolute; top:8px; right:10px;
            color:var(--gold); font-size:0.75rem;
        }

        .acc-type-icon { font-size:1.4rem; margin-bottom:8px; }
        .acc-type-name { font-size:0.8rem; font-weight:500; color:var(--white); margin-bottom:4px; }
        .acc-type-rate { font-size:0.68rem; color:var(--gold); letter-spacing:0.05em; }

        /* Password strength */
        .pw-strength { margin-top:8px; }
        .pw-bar { height:3px; background:var(--black-4); border-radius:4px; overflow:hidden; }
        .pw-bar-fill { height:100%; width:0; transition:all 0.4s; border-radius:4px; }
        .pw-label { font-size:0.65rem; color:var(--white-dim); margin-top:4px; }

        /* Terms */
        .terms-row {
            display:flex; align-items:flex-start; gap:10px;
            margin-bottom:24px; font-size:0.78rem; color:var(--white-dim); line-height:1.5;
        }

        .terms-row input { accent-color:var(--gold); margin-top:3px; flex-shrink:0; }
        .terms-row a { color:var(--gold); text-decoration:none; }

        /* Submit */
        .btn-submit {
            width:100%; padding:15px;
            background:linear-gradient(135deg,var(--gold-dark),var(--gold));
            color:var(--black); font-family:'Jost',sans-serif;
            font-size:0.82rem; font-weight:600;
            letter-spacing:0.15em; text-transform:uppercase;
            border:none; border-radius:6px; cursor:pointer;
            transition:all 0.3s; position:relative; overflow:hidden;
        }

        .btn-submit::after {
            content:''; position:absolute; inset:0;
            background:linear-gradient(135deg,transparent,rgba(255,255,255,0.15),transparent);
            transform:translateX(-100%); transition:transform 0.4s;
        }

        .btn-submit:hover::after { transform:translateX(100%); }
        .btn-submit:hover { box-shadow:0 6px 28px rgba(201,168,76,0.35); transform:translateY(-1px); }

        /* Alerts */
        .auth-error {
            padding:14px 16px; margin-bottom:24px;
            background:rgba(231,76,60,0.08); border:1px solid rgba(231,76,60,0.3);
            border-left:3px solid var(--danger); border-radius:6px;
            font-size:0.82rem; color:var(--danger);
            display:flex; align-items:center; gap:10px;
        }

        .auth-success {
            padding:16px 18px; margin-bottom:24px;
            background:rgba(46,204,113,0.08); border:1px solid rgba(46,204,113,0.3);
            border-left:3px solid var(--success); border-radius:8px;
            font-size:0.85rem; color:var(--success); line-height:1.7;
        }

        /* RESPONSIVE */
        @media (max-width:960px) {
            .reg-wrap { grid-template-columns:1fr; }
            .reg-sidebar { display:none; }
            .reg-form-area { padding:30px 24px; }
            .form-grid { grid-template-columns:1fr; }
            .acc-type-grid { grid-template-columns:1fr; }
        }

        @media (max-width:480px) {
            .auth-topbar { padding:14px 18px; }
        }

        @keyframes slideUp {
            from { opacity:0; transform:translateY(20px); }
            to   { opacity:1; transform:translateY(0); }
        }

        .reg-form-area form { animation:slideUp 0.5s ease both; }
    </style>
</head>
<body>

<!-- TOPBAR -->
<div class="auth-topbar">
    <a href="landing.php" class="auth-logo">
        <div class="auth-logo-mark">A</div>
        <span class="auth-logo-text">APEX <span>BANK</span></span>
    </a>
    <div class="auth-topbar-right">
        Already a member? <a href="login.php">Sign in →</a>
    </div>
</div>

<div class="reg-wrap">
    <!-- SIDEBAR -->
    <div class="reg-sidebar">
        <h2 class="sidebar-heading">Open Your<br><em>Account Today</em></h2>
        <p class="sidebar-desc">Join thousands of customers who trust Apex Bank. It takes less than 5 minutes.</p>

        <div class="sidebar-steps">
            <div class="sidebar-step">
                <div class="step-circle active">1</div>
                <div class="step-text">
                    <h5>Personal Information</h5>
                    <p>Your name, contact & identity</p>
                </div>
            </div>
            <div class="sidebar-step">
                <div class="step-circle active">2</div>
                <div class="step-text">
                    <h5>Choose Account Type</h5>
                    <p>Savings, Current or Fixed Deposit</p>
                </div>
            </div>
            <div class="sidebar-step">
                <div class="step-circle active">3</div>
                <div class="step-text">
                    <h5>Set Secure Password</h5>
                    <p>Protect your account</p>
                </div>
            </div>
            <div class="sidebar-step">
                <div class="step-circle">4</div>
                <div class="step-text">
                    <h5>Start Banking</h5>
                    <p>Login and explore your dashboard</p>
                </div>
            </div>
        </div>

        <div class="sidebar-perks">
            <div style="font-size:0.65rem;letter-spacing:0.2em;text-transform:uppercase;color:var(--gold);margin-bottom:12px;">What You Get</div>
            <div class="perk-item">Free account opening</div>
            <div class="perk-item">Up to 9% annual interest</div>
            <div class="perk-item">Instant fund transfers</div>
            <div class="perk-item">Real-time dashboard</div>
            <div class="perk-item">Loan eligibility from Day 1</div>
            <div class="perk-item">24/7 account access</div>
        </div>
    </div>

    <!-- FORM AREA -->
    <div class="reg-form-area">
        <?php if ($success): ?>
        <div class="auth-success">
            🎉 <?php echo $success; ?>
        </div>
        <?php else: ?>

        <?php if ($error): ?>
        <div class="auth-error">⚠ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" id="regForm">

            <!-- SECTION 1: Personal Info -->
            <div class="section-divider"><span>Personal Information</span></div>
            <div class="form-grid">
                <div class="form-field full">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" placeholder="As per your NID / Passport"
                           value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                </div>
                <div class="form-field">
                    <label>Email Address *</label>
                    <input type="email" name="email" placeholder="you@example.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>
                <div class="form-field">
                    <label>Phone Number *</label>
                    <input type="tel" name="phone" placeholder="01700-000000"
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                </div>
                <div class="form-field">
                    <label>Date of Birth</label>
                    <input type="date" name="dob"
                           value="<?php echo htmlspecialchars($_POST['dob'] ?? ''); ?>"
                           max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>">
                </div>
                <div class="form-field">
                    <label>Gender</label>
                    <select name="gender">
                        <option value="male"   <?php echo ($_POST['gender']??'')==='male'?'selected':''; ?>>Male</option>
                        <option value="female" <?php echo ($_POST['gender']??'')==='female'?'selected':''; ?>>Female</option>
                        <option value="other"  <?php echo ($_POST['gender']??'')==='other'?'selected':''; ?>>Other</option>
                    </select>
                </div>
            </div>

            <!-- SECTION 2: Identity -->
            <div class="section-divider"><span>Identity Verification</span></div>
            <div class="form-grid">
                <div class="form-field">
                    <label>ID Type *</label>
                    <select name="id_type" required>
                        <option value="NID"              <?php echo ($_POST['id_type']??'')==='NID'?'selected':''; ?>>National ID (NID)</option>
                        <option value="Passport"         <?php echo ($_POST['id_type']??'')==='Passport'?'selected':''; ?>>Passport</option>
                        <option value="Birth Certificate"<?php echo ($_POST['id_type']??'')==='Birth Certificate'?'selected':''; ?>>Birth Certificate</option>
                        <option value="Driving License"  <?php echo ($_POST['id_type']??'')==='Driving License'?'selected':''; ?>>Driving License</option>
                    </select>
                </div>
                <div class="form-field">
                    <label>ID Number *</label>
                    <input type="text" name="id_number" placeholder="Enter your ID number"
                           value="<?php echo htmlspecialchars($_POST['id_number'] ?? ''); ?>" required>
                </div>
                <div class="form-field full">
                    <label>Residential Address</label>
                    <textarea name="address" placeholder="Full address..."><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- SECTION 3: Account Type -->
            <div class="section-divider"><span>Choose Account Type</span></div>
            <div class="acc-type-grid" id="accTypeGrid">
                <?php
                $selType = $_POST['account_type'] ?? 'savings';
                $types = [
                    ['value'=>'savings',       'icon'=>'💰', 'name'=>'Savings Account', 'rate'=>'5.5% p.a. interest'],
                    ['value'=>'current',       'icon'=>'💳', 'name'=>'Current Account', 'rate'=>'Unlimited transactions'],
                    ['value'=>'fixed_deposit', 'icon'=>'🏆', 'name'=>'Fixed Deposit',   'rate'=>'9% p.a. — highest rate'],
                ];
                foreach ($types as $t):
                ?>
                <label class="acc-type-card <?php echo $selType === $t['value'] ? 'selected' : ''; ?>"
                       onclick="selectAccType(this, '<?php echo $t['value']; ?>')">
                    <input type="radio" name="account_type" value="<?php echo $t['value']; ?>"
                           <?php echo $selType === $t['value'] ? 'checked' : ''; ?>>
                    <div class="acc-type-icon"><?php echo $t['icon']; ?></div>
                    <div class="acc-type-name"><?php echo $t['name']; ?></div>
                    <div class="acc-type-rate"><?php echo $t['rate']; ?></div>
                </label>
                <?php endforeach; ?>
            </div>

            <!-- SECTION 4: Security -->
            <div class="section-divider" style="margin-top:32px;"><span>Create Password</span></div>
            <div class="form-grid">
                <div class="form-field">
                    <label>Password *</label>
                    <input type="password" id="pw1" name="password"
                           placeholder="Min. 6 characters" required oninput="checkStrength(this.value)">
                    <div class="pw-strength">
                        <div class="pw-bar"><div class="pw-bar-fill" id="pwBar"></div></div>
                        <div class="pw-label" id="pwLabel">Enter a password</div>
                    </div>
                </div>
                <div class="form-field">
                    <label>Confirm Password *</label>
                    <input type="password" id="pw2" name="confirm_password"
                           placeholder="Re-enter your password" required oninput="checkMatch()">
                    <div class="pw-label" id="pwMatch" style="margin-top:8px;font-size:0.65rem;"></div>
                </div>
            </div>

            <!-- TERMS -->
            <div class="terms-row" style="margin-top:28px;">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a> of Apex Bank. I confirm that all information provided is accurate and true.</label>
            </div>

            <button type="submit" class="btn-submit">
                Create My Account & Start Banking →
            </button>

            <p style="text-align:center;margin-top:20px;font-size:0.78rem;color:var(--white-dim);">
                Already have an account? <a href="login.php" style="color:var(--gold);">Sign in here</a>
            </p>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
function selectAccType(el, val) {
    document.querySelectorAll('.acc-type-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    el.querySelector('input[type=radio]').checked = true;
}

function checkStrength(pw) {
    let score = 0;
    if (pw.length >= 6) score++;
    if (pw.length >= 10) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    const bar = document.getElementById('pwBar');
    const lbl = document.getElementById('pwLabel');
    const pct = (score / 5) * 100;
    bar.style.width = pct + '%';

    if (score <= 1) { bar.style.background='#E74C3C'; lbl.textContent='Weak — add more characters'; lbl.style.color='#E74C3C'; }
    else if (score <= 3) { bar.style.background='#F39C12'; lbl.textContent='Fair — try adding numbers or symbols'; lbl.style.color='#F39C12'; }
    else { bar.style.background='#2ECC71'; lbl.textContent='Strong password ✓'; lbl.style.color='#2ECC71'; }
}

function checkMatch() {
    const p1 = document.getElementById('pw1').value;
    const p2 = document.getElementById('pw2').value;
    const el = document.getElementById('pwMatch');
    if (!p2) { el.textContent=''; return; }
    if (p1 === p2) { el.textContent='✓ Passwords match'; el.style.color='#2ECC71'; }
    else           { el.textContent='✗ Passwords do not match'; el.style.color='#E74C3C'; }
}
</script>
</body>
</html>