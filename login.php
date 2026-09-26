<?php
require_once 'config/db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $r = $_SESSION['role'];
    if ($r === 'admin') redirect('admin/index.php');
    if ($r === 'staff') redirect('staff/dashboard.php');
    redirect('dashboard.php');
}

$error  = '';
$field  = ''; // which field has error
$login  = '';
$role   = 'client';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'client';

    if (empty($login) || empty($password)) {
        $error = 'Please enter your credentials.';
    } else {
        if ($role === 'admin') {
            $stmt = $pdo->prepare("SELECT * FROM ADMIN_USER WHERE Username = ? AND IsActive = 1");
            $stmt->execute([$login]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['PasswordHash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']  = $admin['AdminID'];
                $_SESSION['username'] = $admin['Username'];
                $_SESSION['role']     = 'admin';
                // Update last login
                $pdo->prepare("UPDATE ADMIN_USER SET LastLogin = NOW() WHERE AdminID = ?")->execute([$admin['AdminID']]);
                setToast('Welcome back, ' . $admin['Username'] . '!', 'success');
                redirect('admin/index.php');
            } else {
                $error = 'Invalid admin credentials.';
            }

        } elseif ($role === 'staff') {
            $stmt = $pdo->prepare("SELECT * FROM STAFF WHERE (email = ? OR username = ?) AND is_active = 1");
            $stmt->execute([$login, $login]);
            $staff = $stmt->fetch();

            if ($staff && password_verify($password, $staff['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id']  = $staff['staff_id'];
                $_SESSION['username'] = $staff['first_name'] . ' ' . $staff['last_name'];
                $_SESSION['role']     = 'staff';
                $_SESSION['staff_data'] = [
                    'role'       => $staff['role'],
                    'department' => $staff['department'],
                ];
                setToast('Welcome, ' . $staff['first_name'] . '!', 'success');
                redirect('staff/dashboard.php');
            } else {
                $error = 'Invalid staff credentials.';
            }

        } else { // client
            $stmt = $pdo->prepare(
                "SELECT d.UserID, d.CustomerID, d.PasswordHash, c.FirstName, c.LastName, c.IsActive
                 FROM DIGITALBANKINGUSER d
                 JOIN CUSTOMER c ON d.CustomerID = c.CustomerID
                 WHERE (d.Username = ? OR c.Email = ?) AND d.IsActive = 1"
            );
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['PasswordHash'])) {
                if (!$user['IsActive']) {
                    $error = 'Your account has been deactivated. Please contact support.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id']  = $user['CustomerID'];
                    $_SESSION['username'] = $user['FirstName'] . ' ' . $user['LastName'];
                    $_SESSION['role']     = 'client';
                    // Update last login
                    $pdo->prepare("UPDATE DIGITALBANKINGUSER SET LastLogin = NOW() WHERE CustomerID = ?")->execute([$user['CustomerID']]);
                    setToast('Welcome back, ' . $user['FirstName'] . '!', 'success');
                    redirect('dashboard.php');
                }
            } else {
                $error = 'Invalid username or password.';
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
    <title>Sign In — Asha Bank</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --cream:      #F5F4EF;
            --cream-2:    #EDECEA;
            --cream-3:    #E0DED8;
            --ink:        #0E0E0E;
            --ink-muted:  #5A5A5A;
            --ink-faint:  #999;
            --surface:    #FFFFFF;
            --red:        #C62828;
            --red-dark:   #9B1B1B;
            --red-light:  #FDEAEA;
            --green:      #2E7D32;
            --green-light:#E8F5E9;
            --sans:       'Inter', sans-serif;
            --serif:      'DM Serif Display', serif;
            --r:          10px;
            --r-lg:       20px;
        }
        html { height: 100%; }
        body {
            font-family: var(--sans);
            background: var(--cream);
            color: var(--ink);
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
            -webkit-font-smoothing: antialiased;
        }
        /* Left panel */
        .left-panel {
            background: var(--ink);
            display: flex; flex-direction: column;
            justify-content: space-between;
            padding: 40px 48px;
            position: relative;
            overflow: hidden;
        }
        .left-panel::before {
            content: '';
            position: absolute; top: -100px; right: -100px;
            width: 400px; height: 400px;
            background: var(--red);
            border-radius: 50%;
            opacity: 0.08;
        }
        .left-panel::after {
            content: '';
            position: absolute; bottom: -80px; left: -80px;
            width: 300px; height: 300px;
            background: var(--red);
            border-radius: 50%;
            opacity: 0.05;
        }
        .left-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; position: relative; z-index: 1; }
        .left-logo-mark { width: 34px; height: 34px; background: var(--red); border-radius: 9px; display: flex; align-items: center; justify-content: center; }
        .left-logo-mark svg { width: 18px; height: 18px; }
        .left-logo-name { font-family: var(--serif); font-size: 20px; color: white; }
        .left-main { position: relative; z-index: 1; }
        .left-label { font-size: 11px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: rgba(255,255,255,0.3); margin-bottom: 16px; }
        .left-title { font-family: var(--serif); font-size: clamp(32px, 3vw, 48px); color: white; line-height: 1.1; letter-spacing: -1px; margin-bottom: 20px; }
        .left-title em { font-style: italic; color: rgba(255,255,255,0.35); }
        .left-body { font-size: 15px; color: rgba(255,255,255,0.45); line-height: 1.65; max-width: 340px; }
        .features-list { margin-top: 32px; display: flex; flex-direction: column; gap: 12px; }
        .feature-item { display: flex; align-items: center; gap: 12px; }
        .feature-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--red); flex-shrink: 0; }
        .feature-text { font-size: 14px; color: rgba(255,255,255,0.5); }
        .left-footer { font-size: 12px; color: rgba(255,255,255,0.2); position: relative; z-index: 1; }

        /* Right panel */
        .right-panel {
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            padding: 40px 48px;
            background: var(--cream);
        }
        .auth-card { width: 100%; max-width: 420px; }
        .auth-card-header { margin-bottom: 32px; }
        .auth-card-header h1 { font-size: 26px; font-weight: 700; letter-spacing: -0.5px; margin-bottom: 6px; }
        .auth-card-header p { font-size: 14px; color: var(--ink-muted); }

        /* Role tabs */
        .role-tabs { display: flex; background: var(--cream-2); border-radius: var(--r); padding: 4px; margin-bottom: 28px; }
        .role-tab {
            flex: 1; padding: 8px; font-size: 13px; font-weight: 500; text-align: center;
            border: none; background: transparent; color: var(--ink-muted);
            border-radius: 7px; cursor: pointer; transition: all 0.2s;
        }
        .role-tab.active { background: var(--surface); color: var(--ink); box-shadow: 0 1px 4px rgba(0,0,0,0.1); }

        /* Form */
        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 13px; font-weight: 500; color: var(--ink-muted); margin-bottom: 7px; }
        .input-wrap { position: relative; }
        .input-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--ink-faint); display: flex; pointer-events: none; }
        .input-icon svg { width: 16px; height: 16px; }
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%; padding: 11px 14px 11px 42px;
            border: 1.5px solid var(--cream-3); border-radius: var(--r);
            font-family: var(--sans); font-size: 14px; color: var(--ink);
            background: var(--surface);
            transition: border-color 0.2s, box-shadow 0.2s;
            appearance: none;
        }
        input:focus { outline: none; border-color: var(--ink); box-shadow: 0 0 0 3px rgba(14,14,14,0.08); }
        input.error-field { border-color: var(--red); }
        input.error-field:focus { box-shadow: 0 0 0 3px rgba(198,40,40,0.10); }
        .password-toggle { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--ink-faint); padding: 4px; }
        .password-toggle:hover { color: var(--ink); }
        .password-toggle svg { width: 16px; height: 16px; }

        .error-alert {
            display: flex; align-items: center; gap: 10px;
            background: var(--red-light); color: var(--red);
            border: 1px solid #ffcdd2; border-radius: var(--r);
            padding: 12px 14px; font-size: 13px; margin-bottom: 20px;
        }
        .error-alert svg { width: 16px; height: 16px; flex-shrink: 0; }

        .btn-submit {
            width: 100%; padding: 13px;
            background: var(--ink); color: white; border: none;
            border-radius: 100px; font-family: var(--sans); font-size: 15px; font-weight: 600;
            cursor: pointer; transition: background 0.2s, transform 0.15s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .btn-submit:hover { background: var(--red); transform: translateY(-1px); }
        .btn-submit:focus-visible { outline: 3px solid var(--red); outline-offset: 3px; }
        .btn-submit svg { width: 16px; height: 16px; }

        .auth-footer { margin-top: 24px; text-align: center; font-size: 14px; color: var(--ink-muted); }
        .auth-footer a { color: var(--red); font-weight: 500; text-decoration: none; }
        .auth-footer a:hover { text-decoration: underline; }

        .divider { display: flex; align-items: center; gap: 12px; margin: 24px 0; }
        .divider-line { flex: 1; height: 1px; background: var(--cream-3); }
        .divider-text { font-size: 12px; color: var(--ink-faint); }

        .demo-box { background: var(--surface); border: 1px solid var(--cream-3); border-radius: var(--r-lg); padding: 16px; }
        .demo-box h6 { font-size: 11px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--ink-muted); margin-bottom: 12px; }
        .demo-creds { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
        .demo-cred-btn {
            background: var(--cream-2); border: 1px solid var(--cream-3); border-radius: var(--r);
            padding: 10px 8px; cursor: pointer; transition: all 0.2s;
            font-family: var(--sans); font-size: 12px; font-weight: 500; color: var(--ink-muted);
            text-align: center;
        }
        .demo-cred-btn:hover { background: var(--ink); color: white; border-color: var(--ink); }
        .demo-cred-btn .role-badge { display: block; font-size: 10px; color: var(--red); font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 4px; }
        .demo-cred-btn:hover .role-badge { color: rgba(255,255,255,0.6); }

        @media (max-width: 900px) {
            body { grid-template-columns: 1fr; }
            .left-panel { display: none; }
            .right-panel { padding: 40px 24px; }
        }
    </style>
</head>
<body>

<!-- Left branding panel -->
<div class="left-panel" aria-hidden="true">
    <a href="index.php" class="left-logo">
        <div class="left-logo-mark">
            <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="9" width="20" height="13" rx="2"/>
                <path d="M6 9V7a6 6 0 0 1 12 0v2"/>
                <circle cx="12" cy="15" r="2" fill="white" stroke="none"/>
            </svg>
        </div>
        <span class="left-logo-name">Asha Bank</span>
    </a>

    <div class="left-main">
        <div class="left-label">Secure · Reliable · Modern</div>
        <h2 class="left-title">Your money,<br>your <em>control</em></h2>
        <p class="left-body">Full-service digital banking built for Bangladesh. Manage all your finances in one secure place.</p>
        <div class="features-list">
            <div class="feature-item"><div class="feature-dot"></div><span class="feature-text">Instant deposits & transfers</span></div>
            <div class="feature-item"><div class="feature-dot"></div><span class="feature-text">Real-time transaction history</span></div>
            <div class="feature-item"><div class="feature-dot"></div><span class="feature-text">24/7 account monitoring</span></div>
            <div class="feature-item"><div class="feature-dot"></div><span class="feature-text">Regulated by Bangladesh Bank</span></div>
        </div>
    </div>

    <p class="left-footer">© <?= date('Y') ?> Asha Bank. All rights reserved.</p>
</div>

<!-- Right login form panel -->
<main class="right-panel" role="main">
    <div class="auth-card">
        <div class="auth-card-header">
            <h1>Sign in</h1>
            <p>Enter your credentials to access your account</p>
        </div>

        <!-- Role selector -->
        <div class="role-tabs" role="tablist" aria-label="Select account type">
            <button class="role-tab <?= $role==='client' ? 'active' : '' ?>" data-role="client" role="tab" aria-selected="<?= $role==='client' ? 'true':'false' ?>">Customer</button>
            <button class="role-tab <?= $role==='staff'  ? 'active' : '' ?>" data-role="staff"  role="tab" aria-selected="<?= $role==='staff'  ? 'true':'false' ?>">Staff</button>
            <button class="role-tab <?= $role==='admin'  ? 'active' : '' ?>" data-role="admin"  role="tab" aria-selected="<?= $role==='admin'  ? 'true':'false' ?>">Admin</button>
        </div>

        <?php if ($error): ?>
        <div class="error-alert" role="alert">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <?= e($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="role" id="roleInput" value="<?= e($role) ?>">

            <div class="form-group">
                <label for="login">Username or Email</label>
                <div class="input-wrap">
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </span>
                    <input type="text" id="login" name="login"
                           value="<?= e($login) ?>"
                           placeholder="Enter username or email"
                           autocomplete="username"
                           required
                           <?= $error ? 'class="error-field"' : '' ?>>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <input type="password" id="password" name="password"
                           placeholder="Enter your password"
                           autocomplete="current-password"
                           required
                           <?= $error ? 'class="error-field"' : '' ?>>
                    <button type="button" class="password-toggle" id="pwToggle" aria-label="Toggle password visibility">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" id="pwIcon">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                Sign In
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
        </form>

        <div class="auth-footer" style="margin-top: 20px;">
            <p>Don't have an account? <a href="register.php">Open one free</a></p>
        </div>

        <div class="divider">
            <div class="divider-line"></div>
            <span class="divider-text">Demo credentials</span>
            <div class="divider-line"></div>
        </div>

        <div class="demo-box" aria-label="Demo login credentials">
            <h6>Click to fill credentials</h6>
            <div class="demo-creds">
                <button class="demo-cred-btn" onclick="fillDemo('arjun.kapoor','password','client')">
                    <span class="role-badge">Customer</span>
                    arjun.kapoor
                </button>
                <button class="demo-cred-btn" onclick="fillDemo('rajesh','password','staff')">
                    <span class="role-badge">Staff</span>
                    rajesh
                </button>
                <button class="demo-cred-btn" onclick="fillDemo('admin','Admin@123','admin')">
                    <span class="role-badge">Admin</span>
                    admin
                </button>
            </div>
        </div>
    </div>
</main>

<script>
// Role tabs
document.querySelectorAll('.role-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.role-tab').forEach(t => {
            t.classList.remove('active');
            t.setAttribute('aria-selected', 'false');
        });
        tab.classList.add('active');
        tab.setAttribute('aria-selected', 'true');
        document.getElementById('roleInput').value = tab.dataset.role;
    });
});

// Password toggle
const pwInput  = document.getElementById('password');
const pwToggle = document.getElementById('pwToggle');
const pwIcon   = document.getElementById('pwIcon');
pwToggle.addEventListener('click', () => {
    const show = pwInput.type === 'password';
    pwInput.type = show ? 'text' : 'password';
    pwIcon.innerHTML = show
        ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
        : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
    pwToggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
});

// Demo credentials auto-fill
function fillDemo(user, pass, role) {
    document.getElementById('login').value    = user;
    document.getElementById('password').value = pass;
    document.getElementById('roleInput').value = role;
    document.querySelectorAll('.role-tab').forEach(t => {
        const active = t.dataset.role === role;
        t.classList.toggle('active', active);
        t.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.getElementById('login').focus();
}

// Form submit loading state
document.getElementById('loginForm').addEventListener('submit', () => {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<svg class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Signing in…';
    setTimeout(() => {
        btn.disabled = false;
        btn.innerHTML = 'Sign In <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
    }, 8000);
});
</script>
<style>.spin { animation: spin 0.8s linear infinite; } @keyframes spin { to { transform: rotate(360deg); } }</style>
</body>
</html>
