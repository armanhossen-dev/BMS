<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/language.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in? Redirect to the right dashboard.
if (!empty($_SESSION['customer_id'])) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }
if (!empty($_SESSION['staff_id']))    { header('Location: ' . BASE_URL . '/staff/dashboard.php'); exit; }
if (!empty($_SESSION['admin_id']))    { header('Location: ' . BASE_URL . '/admin/index.php'); exit; }

$error = '';
$role = $_POST['role'] ?? 'client';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        if ($role === 'admin') {
            $stmt = $pdo->prepare("SELECT * FROM ADMIN_USER WHERE Username = ?");
            $stmt->execute([$username]);
            $u = $stmt->fetch();
            if ($u && $u['IsActive'] && password_verify($password, $u['PasswordHash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $u['AdminID'];
                $_SESSION['admin_role'] = $u['Role'];
                $pdo->prepare("UPDATE ADMIN_USER SET LastLogin = NOW() WHERE AdminID = ?")->execute([$u['AdminID']]);
                header('Location: ' . BASE_URL . '/admin/index.php'); exit;
            }
            $error = 'Invalid admin credentials.';
        } elseif ($role === 'staff') {
            $stmt = $pdo->prepare("SELECT * FROM STAFF WHERE username = ?");
            $stmt->execute([$username]);
            $u = $stmt->fetch();
            if ($u && $u['locked_until'] && strtotime($u['locked_until']) > time()) {
                $error = 'Account temporarily locked due to too many failed attempts. Try again later.';
            } elseif ($u && $u['is_active'] && password_verify($password, $u['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['staff_id'] = $u['staff_id'];
                $_SESSION['staff_role'] = $u['role'];
                $pdo->prepare("UPDATE STAFF SET last_login = NOW(), login_attempts = 0 WHERE staff_id = ?")->execute([$u['staff_id']]);
                header('Location: ' . BASE_URL . '/staff/dashboard.php'); exit;
            } else {
                if ($u) {
                    $attempts = $u['login_attempts'] + 1;
                    $locked = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
                    $pdo->prepare("UPDATE STAFF SET login_attempts = ?, locked_until = ? WHERE staff_id = ?")
                        ->execute([$attempts, $locked, $u['staff_id']]);
                }
                $error = 'Invalid staff credentials.';
            }
        } else {
            $stmt = $pdo->prepare("SELECT d.*, c.FirstName, c.LastName, c.IsActive AS CustomerActive
                                    FROM DIGITALBANKINGUSER d JOIN CUSTOMER c ON d.CustomerID = c.CustomerID
                                    WHERE d.Username = ?");
            $stmt->execute([$username]);
            $u = $stmt->fetch();
            if ($u && $u['LockedUntil'] && strtotime($u['LockedUntil']) > time()) {
                $error = 'Account temporarily locked due to too many failed attempts. Try again later.';
            } elseif ($u && !$u['CustomerActive']) {
                $error = 'Your account is inactive. Please contact support or request reactivation.';
            } elseif ($u && $u['IsActive'] && password_verify($password, $u['PasswordHash'])) {
                session_regenerate_id(true);
                $_SESSION['customer_id'] = $u['CustomerID'];
                $pdo->prepare("UPDATE DIGITALBANKINGUSER SET LastLogin = NOW(), LoginAttempts = 0 WHERE UserID = ?")->execute([$u['UserID']]);
                header('Location: ' . BASE_URL . '/dashboard.php'); exit;
            } else {
                if ($u) {
                    $attempts = $u['LoginAttempts'] + 1;
                    $locked = $attempts >= 5 ? date('Y-m-d H:i:s', time() + 900) : null;
                    $pdo->prepare("UPDATE DIGITALBANKINGUSER SET LoginAttempts = ?, LockedUntil = ? WHERE UserID = ?")
                        ->execute([$attempts, $locked, $u['UserID']]);
                }
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
<title>Login · Asha Bank</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-shell">
  <div class="auth-visual">
    <div style="position:relative;z-index:1;">
      <div class="brand" style="padding:0;">
        <div class="brand-mark">A</div>
        <div class="brand-name" style="font-size:20px;">Asha<span>Bank</span></div>
      </div>
    </div>
    <div style="position:relative;z-index:1;">
      <h2 style="font-size:32px;line-height:1.25;max-width:420px;">Banking that grows<br>with your <span style="color:var(--accent);">hope</span>.</h2>
      <p class="text-muted mt-16" style="max-width:380px;">Savings, transfers, loans, and bill pay — all in one secure place, wherever you are.</p>
      <div class="flex gap-16 mt-24">
        <div class="card" style="background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.08);padding:16px;flex:1;">
          <div class="stat-value" style="font-size:20px;">120K+</div>
          <div class="text-dim text-xs mt-8">Active customers</div>
        </div>
        <div class="card" style="background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.08);padding:16px;flex:1;">
          <div class="stat-value" style="font-size:20px;">99.9%</div>
          <div class="text-dim text-xs mt-8">Uptime, always on</div>
        </div>
      </div>
    </div>
  </div>

  <div class="auth-form-col">
    <div class="auth-box">
      <h1>Welcome back</h1>
      <p class="sub">Sign in to manage your money.</p>

      <?php if ($error): ?>
        <div class="badge badge-danger" style="display:flex;width:100%;padding:10px 14px;margin-bottom:18px;">⚠️ <?= clean($error) ?></div>
      <?php endif; ?>

      <div class="flex gap-8 mt-8" style="margin-bottom:22px;background:var(--surface-2);padding:4px;border-radius:10px;">
        <?php foreach (['client'=>'Client','staff'=>'Staff','admin'=>'Admin'] as $val=>$label): ?>
          <button type="button" class="btn btn-sm role-tab <?= $role===$val?'btn-primary':'btn-ghost' ?>" style="flex:1;" data-role="<?= $val ?>"><?= $label ?></button>
        <?php endforeach; ?>
      </div>

      <form method="POST" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="role" id="roleInput" value="<?= clean($role) ?>">
        <div class="field">
          <label>Username</label>
          <input class="input" type="text" name="username" placeholder="e.g. arjun.kapoor" required autofocus>
        </div>
        <div class="field">
          <label>Password</label>
          <input class="input" type="password" name="password" placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Sign In</button>
      </form>

      <div class="auth-divider">New to Asha Bank?</div>
      <a href="<?= BASE_URL ?>/register.php" class="btn btn-secondary btn-block">Create an account</a>

      <div class="demo-creds">
        <strong>Demo credentials</strong><br>
        Admin: <span class="mono">admin</span> / <span class="mono">Admin@123</span><br>
        Staff: <span class="mono">rajesh</span> / <span class="mono">password</span><br>
        Client: <span class="mono">arjun.kapoor</span> / <span class="mono">password</span>
      </div>
    </div>
  </div>
</div>
<script>
document.querySelectorAll('.role-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.role-tab').forEach(b => { b.classList.remove('btn-primary'); b.classList.add('btn-ghost'); });
    btn.classList.remove('btn-ghost'); btn.classList.add('btn-primary');
    document.getElementById('roleInput').value = btn.getAttribute('data-role');
  });
});
</script>
</body>
</html>
