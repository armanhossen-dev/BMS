<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/language.php';
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['customer_id'])) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }

$errors = [];
$old = ['first_name'=>'','last_name'=>'','email'=>'','phone'=>'','dob'=>'','gender'=>'Male','address'=>'','city'=>'','nid'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($old as $k => $v) { $old[$k] = trim($_POST[$k] ?? ''); }
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($old['first_name'] === '' || $old['last_name'] === '') $errors[] = 'First and last name are required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($old['phone']) < 10) $errors[] = 'Enter a valid phone number.';
    if (strlen($old['nid']) < 5) $errors[] = 'National ID looks too short.';
    if (strlen($username) < 4) $errors[] = 'Username must be at least 4 characters.';
    if (strlen($password) < 6 || !preg_match('/[\W_]/', $password)) $errors[] = 'Password must be 6+ characters and include a special character.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $stmt = $pdo->prepare("SELECT 1 FROM CUSTOMER WHERE Email = ? OR NationalID = ?");
        $stmt->execute([$old['email'], $old['nid']]);
        if ($stmt->fetch()) $errors[] = 'An account with this email or National ID already exists.';

        $stmt = $pdo->prepare("SELECT 1 FROM DIGITALBANKINGUSER WHERE Username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) $errors[] = 'That username is taken. Please choose another.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO CUSTOMER
                (FirstName,LastName,DateOfBirth,Gender,Email,Phone,Address,City,NationalID,CustomerCategoryID,PrimaryBranchID,IsActive)
                VALUES (?,?,?,?,?,?,?,?,?,1,1,1)");
            $stmt->execute([$old['first_name'],$old['last_name'],$old['dob'] ?: null,$old['gender'],
                             $old['email'],$old['phone'],$old['address'],$old['city'],$old['nid']]);
            $customerId = (int)$pdo->lastInsertId();

            $refCode = 'REF' . $customerId . substr(md5((string)mt_rand()), 0, 6);
            $pdo->prepare("UPDATE CUSTOMER SET ReferralCode = ? WHERE CustomerID = ?")->execute([$refCode, $customerId]);

            // Auto-create Savings account
            $accNum = gen_account_number();
            $stmt = $pdo->prepare("INSERT INTO ACCOUNT (AccountNumber,ProductID,CustomerID,BranchID,OpeningDate,AvailableBalance,AccountStatus)
                                    VALUES (?,1,?,1,CURDATE(),0.00,'Active')");
            $stmt->execute([$accNum, $customerId]);

            // Auto-create debit card
            $cardNum = gen_card_number();
            $cvv = str_pad((string)mt_rand(0,999), 3, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("INSERT INTO CARDS (CardNumber,CustomerID,AccountNumber,ExpiryDate,CVV,CardType,CardStatus)
                                    VALUES (?,?,?,DATE_ADD(CURDATE(), INTERVAL 5 YEAR),?,'Debit','Active')");
            $stmt->execute([$cardNum, $customerId, $accNum, $cvv]);

            // Digital banking login
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO DIGITALBANKINGUSER (CustomerID,Username,PasswordHash,IsActive) VALUES (?,?,?,1)")
                ->execute([$customerId, $username, $hash]);

            // KYC placeholder
            $pdo->prepare("INSERT INTO KYC_VERIFICATIONS (customer_id,nid_number,status) VALUES (?,?, 'pending')")
                ->execute([$customerId, $old['nid']]);

            $pdo->prepare("INSERT INTO NOTIFICATIONS (customer_id,title,message,type) VALUES (?,?,?,?)")->execute([
                $customerId, 'Welcome to Asha Bank!',
                'Your account ' . $accNum . ' has been created. Complete your KYC to unlock full features.', 'success'
            ]);

            $pdo->commit();

            session_regenerate_id(true);
            $_SESSION['customer_id'] = $customerId;
            $_SESSION['show_kyc_popup'] = true;
            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account · Asha Bank</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-shell">
  <div class="auth-visual">
    <div class="brand" style="padding:0;position:relative;z-index:1;">
      <div class="brand-mark">A</div>
      <div class="brand-name" style="font-size:20px;">Asha<span>Bank</span></div>
    </div>
    <div style="position:relative;z-index:1;">
      <h2 style="font-size:30px;line-height:1.3;max-width:420px;">Open an account<br>in under <span style="color:var(--accent);">2 minutes</span>.</h2>
      <p class="text-muted mt-16" style="max-width:380px;">A Savings account and debit card are created automatically — no branch visit needed.</p>
    </div>
  </div>
  <div class="auth-form-col">
    <div class="auth-box" style="max-width:460px;">
      <h1>Create your account</h1>
      <p class="sub">Join Asha Bank — it takes less than two minutes.</p>

      <?php if ($errors): ?>
        <div class="badge badge-danger" style="display:block;width:100%;padding:12px 14px;margin-bottom:18px;line-height:1.6;">
          <?php foreach ($errors as $e) echo '⚠️ ' . clean($e) . '<br>'; ?>
        </div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <?= csrf_field() ?>
        <div class="grid grid-2" style="gap:14px;">
          <div class="field"><label>First name</label><input class="input" name="first_name" value="<?= clean($old['first_name']) ?>" required></div>
          <div class="field"><label>Last name</label><input class="input" name="last_name" value="<?= clean($old['last_name']) ?>" required></div>
        </div>
        <div class="field"><label>Email</label><input class="input" type="email" name="email" value="<?= clean($old['email']) ?>" required></div>
        <div class="grid grid-2" style="gap:14px;">
          <div class="field"><label>Phone</label><input class="input" name="phone" value="<?= clean($old['phone']) ?>" placeholder="017XXXXXXXX" required></div>
          <div class="field"><label>Date of birth</label><input class="input" type="date" name="dob" value="<?= clean($old['dob']) ?>"></div>
        </div>
        <div class="grid grid-2" style="gap:14px;">
          <div class="field">
            <label>Gender</label>
            <select class="input" name="gender">
              <option <?= $old['gender']==='Male'?'selected':'' ?>>Male</option>
              <option <?= $old['gender']==='Female'?'selected':'' ?>>Female</option>
              <option <?= $old['gender']==='Other'?'selected':'' ?>>Other</option>
            </select>
          </div>
          <div class="field"><label>City</label><input class="input" name="city" value="<?= clean($old['city']) ?>"></div>
        </div>
        <div class="field"><label>Address</label><input class="input" name="address" value="<?= clean($old['address']) ?>"></div>
        <div class="field"><label>National ID (NID)</label><input class="input" name="nid" value="<?= clean($old['nid']) ?>" required></div>
        <div class="field"><label>Choose a username</label><input class="input" name="username" required></div>
        <div class="grid grid-2" style="gap:14px;">
          <div class="field"><label>Password</label><input class="input" type="password" name="password" required></div>
          <div class="field"><label>Confirm password</label><input class="input" type="password" name="confirm_password" required></div>
        </div>
        <p class="text-dim text-xs mt-8" style="margin-bottom:16px;">Min 6 characters, at least one special character.</p>
        <button type="submit" class="btn btn-primary btn-block">Create Account</button>
      </form>

      <div class="auth-divider">Already have an account?</div>
      <a href="<?= BASE_URL ?>/login.php" class="btn btn-secondary btn-block">Sign in</a>
    </div>
  </div>
</div>
</body>
</html>
