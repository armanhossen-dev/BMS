<?php
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($conn->real_escape_string($_POST['email']));
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = 'Please enter your email and password.';
    } else {
        $res  = $conn->query("SELECT * FROM users WHERE email='$email' AND status='active'");
        $user = $res->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email']     = $user['email'];
            $_SESSION['role']      = $user['role'];

            // If customer, also store their customer_id for account filtering
            if ($user['role'] === 'customer') {
                $cust = $conn->query("SELECT id FROM customers WHERE user_id={$user['id']}")->fetch_assoc();
                $_SESSION['customer_id'] = $cust['id'] ?? 0;
            }

            header("Location: dashboard.php");
            exit();
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In – Apex Bank</title>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,400&family=Jost:wght@200;300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        :root {
            --gold:#C9A84C; --gold-light:#E8C97A; --gold-dark:#9A7B2F;
            --gold-dim:rgba(201,168,76,0.1); --gold-line:rgba(201,168,76,0.2);
            --black:#080808; --black-2:#0E0E0E; --black-3:#141414; --black-4:#1C1C1C;
            --white:#F8F4EC; --white-dim:rgba(248,244,236,0.65);
        }
        html,body { height:100%; }
        body {
            background:var(--black);
            font-family:'Jost',sans-serif;
            font-weight:300;
            color:var(--white);
            display:flex; flex-direction:column;
        }

        /* ── TOP BAR ── */
        .auth-topbar {
            padding:20px 40px;
            display:flex; align-items:center; justify-content:space-between;
            border-bottom:1px solid var(--gold-line);
            background:var(--black-2);
        }

        .auth-logo {
            display:flex; align-items:center; gap:10px; text-decoration:none;
        }

        .auth-logo-mark {
            width:36px; height:36px; border:1px solid var(--gold);
            border-radius:7px; display:flex; align-items:center; justify-content:center;
            font-family:'Cormorant Garamond', serif; font-size:1.1rem;
            font-weight:600; color:var(--gold); position:relative;
        }

        .auth-logo-mark::before {
            content:''; position:absolute; inset:3px;
            border:1px solid rgba(201,168,76,0.25); border-radius:4px;
        }

        .auth-logo-text {
            font-family:'Cormorant Garamond', serif;
            font-size:1.1rem; font-weight:600;
            letter-spacing:0.12em; color:var(--white);
        }

        .auth-logo-text span { color:var(--gold); }

        .auth-topbar-right {
            font-size:0.78rem; color:var(--white-dim);
        }

        .auth-topbar-right a {
            color:var(--gold); text-decoration:none; font-weight:500;
        }

        /* ── MAIN LAYOUT ── */
        .auth-wrap {
            flex:1; display:grid; grid-template-columns:1fr 1fr;
            min-height:calc(100vh - 65px);
        }

        /* ── LEFT PANEL ── */
        .auth-left {
            background:var(--black-2);
            border-right:1px solid var(--gold-line);
            padding:60px 56px;
            display:flex; flex-direction:column; justify-content:center;
            position:relative; overflow:hidden;
        }

        .auth-left::before {
            content:'';
            position:absolute; inset:0;
            background:radial-gradient(ellipse 80% 60% at 20% 80%, rgba(201,168,76,0.05), transparent);
        }

        .auth-left-content { position:relative; z-index:1; max-width:400px; }

        .auth-tag {
            display:inline-flex; align-items:center; gap:8px;
            font-size:0.65rem; font-weight:500; letter-spacing:0.25em;
            text-transform:uppercase; color:var(--gold);
            margin-bottom:32px;
        }

        .auth-tag::before { content:''; width:24px; height:1px; background:var(--gold); }

        h1.auth-heading {
            font-family:'Cormorant Garamond', serif;
            font-size:2.8rem; font-weight:600; line-height:1.1;
            color:var(--white); margin-bottom:14px;
        }

        h1.auth-heading em { font-style:italic; color:var(--gold); }

        .auth-desc {
            font-size:0.88rem; font-weight:300; line-height:1.8;
            color:var(--white-dim); margin-bottom:44px;
        }

        /* Info boxes */
        .auth-info-list { display:flex; flex-direction:column; gap:14px; }

        .auth-info-item {
            display:flex; align-items:center; gap:14px;
            padding:14px 18px;
            background:var(--black-3);
            border:1px solid var(--gold-line);
            border-radius:8px;
            transition:border-color 0.3s;
        }

        .auth-info-item:hover { border-color:var(--gold); }

        .auth-info-icon {
            width:36px; height:36px; flex-shrink:0;
            background:var(--gold-dim); border-radius:8px;
            display:flex; align-items:center; justify-content:center; font-size:1rem;
        }

        .auth-info-item h5 { font-size:0.82rem; font-weight:500; color:var(--white); margin-bottom:2px; }
        .auth-info-item p  { font-size:0.73rem; color:var(--white-dim); }

        /* ── RIGHT PANEL (Form) ── */
        .auth-right {
            padding:60px 64px;
            display:flex; flex-direction:column; justify-content:center;
            background:var(--black);
            position:relative; overflow:hidden;
        }

        .auth-right::before {
            content:'';
            position:absolute; top:-100px; right:-100px;
            width:400px; height:400px; border-radius:50%;
            background:radial-gradient(circle, rgba(201,168,76,0.04), transparent 70%);
        }

        .auth-form-wrap { max-width:380px; width:100%; position:relative; z-index:1; }

        .auth-form-tag {
            font-size:0.65rem; font-weight:500; letter-spacing:0.2em;
            text-transform:uppercase; color:var(--gold); margin-bottom:10px;
        }

        h2.auth-form-title {
            font-family:'Cormorant Garamond', serif;
            font-size:2rem; font-weight:600;
            color:var(--white); margin-bottom:6px;
        }

        .auth-form-sub {
            font-size:0.82rem; color:var(--white-dim); margin-bottom:36px;
        }

        .auth-form-sub a { color:var(--gold); text-decoration:none; }

        /* FORM */
        .form-field { margin-bottom:20px; }

        .form-field label {
            display:block; font-size:0.7rem; font-weight:500;
            letter-spacing:0.15em; text-transform:uppercase;
            color:var(--gold); margin-bottom:8px;
        }

        .form-field input {
            width:100%; padding:13px 16px;
            background:var(--black-3);
            border:1px solid rgba(201,168,76,0.18);
            color:var(--white);
            font-family:'Jost',sans-serif; font-size:0.9rem; font-weight:300;
            border-radius:6px; outline:none;
            transition:all 0.3s;
        }

        .form-field input:focus {
            border-color:var(--gold);
            background:var(--black-4);
            box-shadow:0 0 0 3px rgba(201,168,76,0.08);
        }

        .form-field input::placeholder { color:rgba(248,244,236,0.2); }

        .form-row {
            display:flex; justify-content:space-between; align-items:center;
            margin-bottom:24px; font-size:0.78rem;
        }

        .form-row label {
            display:flex; align-items:center; gap:8px;
            color:var(--white-dim); cursor:pointer;
        }

        .form-row input[type=checkbox] { accent-color:var(--gold); width:14px; height:14px; }
        .form-row a { color:var(--gold); text-decoration:none; }

        .btn-submit {
            width:100%; padding:14px;
            background:linear-gradient(135deg, var(--gold-dark), var(--gold));
            color:var(--black);
            font-family:'Jost',sans-serif;
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

        .btn-submit:hover {
            box-shadow:0 6px 24px rgba(201,168,76,0.35);
            transform:translateY(-1px);
        }

        /* Divider */
        .auth-divider {
            display:flex; align-items:center; gap:12px; margin:24px 0;
        }

        .auth-divider::before, .auth-divider::after {
            content:''; flex:1; height:1px; background:var(--gold-line);
        }

        .auth-divider span { font-size:0.7rem; color:rgba(248,244,236,0.25); letter-spacing:0.1em; }

        /* Demo credentials */
        .demo-creds {
            background:var(--black-3);
            border:1px solid var(--gold-line);
            border-radius:8px; padding:16px;
            margin-top:28px;
        }

        .demo-creds-title {
            font-size:0.65rem; font-weight:500;
            letter-spacing:0.2em; text-transform:uppercase;
            color:var(--gold); margin-bottom:10px;
        }

        .demo-row {
            display:flex; justify-content:space-between;
            font-size:0.78rem; padding:6px 0;
            border-bottom:1px solid rgba(201,168,76,0.06);
        }

        .demo-row:last-child { border:none; }
        .demo-row .role { color:var(--white-dim); }
        .demo-row .cred { color:var(--gold); font-family:'Courier New', monospace; font-size:0.72rem; }

        /* Error */
        .auth-error {
            padding:12px 16px; margin-bottom:20px;
            background:rgba(231,76,60,0.08);
            border:1px solid rgba(231,76,60,0.3);
            border-left:3px solid #E74C3C;
            border-radius:6px;
            font-size:0.82rem; color:#E74C3C;
            display:flex; align-items:center; gap:10px;
        }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .auth-wrap { grid-template-columns:1fr; }
            .auth-left { display:none; }
            .auth-right { padding:40px 30px; }
            .auth-form-wrap { max-width:100%; }
        }

        @media (max-width:480px) {
            .auth-topbar { padding:16px 20px; }
            .auth-right { padding:30px 20px; }
        }

        /* Animate in */
        @keyframes slideUp {
            from { opacity:0; transform:translateY(20px); }
            to   { opacity:1; transform:translateY(0); }
        }

        .auth-form-wrap { animation:slideUp 0.5s ease both; }
        .auth-left-content { animation:slideUp 0.5s ease 0.1s both; }
    </style>
</head>
<body>

<!-- TOP BAR -->
<div class="auth-topbar">
    <a href="landing.php" class="auth-logo">
        <div class="auth-logo-mark">A</div>
        <span class="auth-logo-text">APEX <span>BANK</span></span>
    </a>
    <div class="auth-topbar-right">
        No account? <a href="register.php">Open one free →</a>
    </div>
</div>

<!-- MAIN -->
<div class="auth-wrap">
    <!-- LEFT PANEL -->
    <div class="auth-left">
        <div class="auth-left-content">
            <div class="auth-tag">Secure Portal</div>
            <h1 class="auth-heading">Welcome<br>Back to<br><em>Apex Bank</em></h1>
            <p class="auth-desc">Your financial dashboard awaits. Sign in to manage accounts, view transactions, and stay in control of your wealth.</p>
            <div class="auth-info-list">
                <div class="auth-info-item">
                    <div class="auth-info-icon">🔐</div>
                    <div>
                        <h5>Bank-Grade Security</h5>
                        <p>256-bit encrypted sessions protect every login</p>
                    </div>
                </div>
                <div class="auth-info-item">
                    <div class="auth-info-icon">⚡</div>
                    <div>
                        <h5>Real-Time Access</h5>
                        <p>Balances, transactions and analytics live</p>
                    </div>
                </div>
                <div class="auth-info-item">
                    <div class="auth-info-icon">🌍</div>
                    <div>
                        <h5>Available 24/7</h5>
                        <p>Access your account anytime, from anywhere</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL (FORM) -->
    <div class="auth-right">
        <div class="auth-form-wrap">
            <div class="auth-form-tag">Account Access</div>
            <h2 class="auth-form-title">Sign In</h2>
            <p class="auth-form-sub">New to Apex Bank? <a href="register.php">Create a free account →</a></p>

            <?php if ($error): ?>
            <div class="auth-error">⚠ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="on">
                <div class="form-field">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email"
                           placeholder="you@example.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                           required autofocus>
                </div>
                <div class="form-field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           placeholder="Enter your password" required>
                </div>
                <div class="form-row">
                    <label><input type="checkbox" name="remember"> Remember me</label>
                    <a href="#">Forgot password?</a>
                </div>
                <button type="submit" class="btn-submit">Sign In to Portal →</button>
            </form>

            <div class="auth-divider"><span>Demo Credentials</span></div>

            <div class="demo-creds">
                <div class="demo-creds-title">Use these to explore</div>
                <div class="demo-row">
                    <span class="role">👑 Admin</span>
                    <span class="cred">admin@apexbank.com / password</span>
                </div>
                <div class="demo-row">
                    <span class="role">👤 Staff</span>
                    <span class="cred">staff@apexbank.com / password</span>
                </div>
                <div class="demo-row">
                    <span class="role">🏦 Customer</span>
                    <span class="cred">alice@email.com / password</span>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>