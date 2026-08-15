<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/language.php';
require_once __DIR__ . '/includes/functions.php';
require_customer();

$customerId = $_SESSION['customer_id'];
$customer = get_customer($customerId);
$msg = ''; $msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $pdo->prepare("UPDATE CUSTOMER SET Phone=?, Address=?, City=? WHERE CustomerID=?")
            ->execute([$phone, $address, $city, $customerId]);
        $msg = 'Profile updated successfully.';
        $customer = get_customer($customerId);

    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM DIGITALBANKINGUSER WHERE CustomerID = ?");
        $stmt->execute([$customerId]);
        $u = $stmt->fetch();
        if (!$u || !password_verify($current, $u['PasswordHash'])) {
            $msg = 'Current password is incorrect.'; $msgType = 'danger';
        } elseif (strlen($new) < 6 || !preg_match('/[\W_]/', $new)) {
            $msg = 'New password must be 6+ chars with a special character.'; $msgType = 'danger';
        } elseif ($new !== $confirm) {
            $msg = 'New passwords do not match.'; $msgType = 'danger';
        } else {
            $pdo->prepare("UPDATE DIGITALBANKINGUSER SET PasswordHash = ? WHERE CustomerID = ?")
                ->execute([password_hash($new, PASSWORD_BCRYPT), $customerId]);
            $msg = 'Password changed successfully.';
        }

    } elseif ($action === 'submit_kyc') {
        $nid = trim($_POST['nid_number'] ?? '');
        $phone = trim($_POST['kyc_phone'] ?? '');
        $stmt = $pdo->prepare("SELECT * FROM KYC_VERIFICATIONS WHERE customer_id = ? ORDER BY submitted_at DESC LIMIT 1");
        $stmt->execute([$customerId]);
        $existing = $stmt->fetch();
        if ($existing && $existing['status'] === 'pending') {
            $pdo->prepare("UPDATE KYC_VERIFICATIONS SET nid_number=?, phone_number=?, submitted_at=NOW() WHERE kyc_id=?")
                ->execute([$nid, $phone, $existing['kyc_id']]);
        } else {
            $pdo->prepare("INSERT INTO KYC_VERIFICATIONS (customer_id, nid_number, phone_number, status) VALUES (?,?,?, 'pending')")
                ->execute([$customerId, $nid, $phone]);
        }
        notify($customerId, 'KYC Submitted', 'Your KYC documents were submitted and are pending review.', 'info');
        $msg = 'KYC information submitted for review.';

    } elseif ($action === 'reactivation_request') {
        $reason = trim($_POST['reason'] ?? '');
        $stmt = $pdo->prepare("SELECT 1 FROM REACTIVATION_REQUESTS WHERE customer_id = ? AND status = 'pending'");
        $stmt->execute([$customerId]);
        if ($stmt->fetch()) {
            $msg = 'You already have a pending reactivation request.'; $msgType = 'warning';
        } else {
            $pdo->prepare("INSERT INTO REACTIVATION_REQUESTS (customer_id, reason) VALUES (?,?)")->execute([$customerId, $reason]);
            $msg = 'Reactivation request submitted.';
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM KYC_VERIFICATIONS WHERE customer_id = ? ORDER BY submitted_at DESC LIMIT 1");
$stmt->execute([$customerId]);
$kyc = $stmt->fetch();

$stmt = $pdo->prepare("SELECT * FROM NOMINEE WHERE CustomerID = ?");
$stmt->execute([$customerId]);
$nominees = $stmt->fetchAll();

$pageTitle = 'Profile';
$activeNav = 'profile';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div><h1>Profile & Settings</h1><div class="sub">Manage your personal details, security, and verification.</div></div>
</div>

<?php if ($msg): ?>
  <div class="badge badge-<?= $msgType ?>" style="display:block;padding:12px 14px;margin-bottom:18px;"><?= clean($msg) ?></div>
<?php endif; ?>

<div class="grid grid-main">
  <div class="flex" style="flex-direction:column;gap:18px;">

    <div class="card">
      <div class="card-head"><span class="card-title">Personal Information</span></div>
      <div class="flex items-center gap-16 mt-8" style="margin-bottom:20px;">
        <div class="avatar lg"><?= strtoupper(substr($customer['FirstName'],0,1).substr($customer['LastName'],0,1)) ?></div>
        <div>
          <div style="font-weight:600;"><?= clean($customer['FirstName'] . ' ' . $customer['LastName']) ?></div>
          <div class="text-dim text-xs">Customer since <?= date('M Y', strtotime($customer['CreatedAt'])) ?></div>
        </div>
      </div>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_profile">
        <div class="grid grid-2" style="gap:14px;">
          <div class="field"><label>First Name</label><input class="input" value="<?= clean($customer['FirstName']) ?>" disabled></div>
          <div class="field"><label>Last Name</label><input class="input" value="<?= clean($customer['LastName']) ?>" disabled></div>
        </div>
        <div class="field"><label>Email</label><input class="input" value="<?= clean($customer['Email']) ?>" disabled></div>
        <div class="field"><label>Phone</label><input class="input" name="phone" value="<?= clean($customer['Phone']) ?>"></div>
        <div class="grid grid-2" style="gap:14px;">
          <div class="field"><label>Address</label><input class="input" name="address" value="<?= clean($customer['Address']) ?>"></div>
          <div class="field"><label>City</label><input class="input" name="city" value="<?= clean($customer['City']) ?>"></div>
        </div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </form>
    </div>

    <div class="card" id="kyc">
      <div class="card-head">
        <span class="card-title">KYC Verification</span>
        <span class="badge <?= $kyc['status']==='verified'?'badge-success':($kyc['status']==='rejected'?'badge-danger':'badge-warning') ?>">
          <?= $kyc ? ucfirst($kyc['status']) : 'Not Submitted' ?>
        </span>
      </div>
      <?php if ($kyc && $kyc['status']==='rejected' && $kyc['rejection_reason']): ?>
        <div class="badge badge-danger" style="display:block;padding:10px 14px;margin-bottom:14px;">Reason: <?= clean($kyc['rejection_reason']) ?></div>
      <?php endif; ?>
      <?php if (!$kyc || $kyc['status'] !== 'verified'): ?>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="submit_kyc">
        <div class="field"><label>National ID (NID) Number</label><input class="input" name="nid_number" value="<?= clean($kyc['nid_number'] ?? $customer['NationalID']) ?>" required></div>
        <div class="field"><label>Registered Phone</label><input class="input" name="kyc_phone" value="<?= clean($kyc['phone_number'] ?? $customer['Phone']) ?>" required></div>
        <button type="submit" class="btn btn-primary">Submit for Verification</button>
      </form>
      <?php else: ?>
        <p class="text-muted text-sm">✅ Your identity has been verified. All features are unlocked.</p>
      <?php endif; ?>
    </div>

    <?php if (!$customer['IsActive']): ?>
    <div class="card">
      <div class="card-head"><span class="card-title">Account Reactivation</span></div>
      <p class="text-muted text-sm mt-8" style="margin-bottom:14px;">Your account is currently inactive. Submit a request to reactivate it.</p>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reactivation_request">
        <div class="field"><label>Reason</label><textarea class="input" name="reason" required></textarea></div>
        <button type="submit" class="btn btn-primary">Submit Request</button>
      </form>
    </div>
    <?php endif; ?>
  </div>

  <div class="flex" style="flex-direction:column;gap:18px;">
    <div class="card">
      <div class="card-head"><span class="card-title">Change Password</span></div>
      <form method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        <div class="field"><label>Current Password</label><input class="input" type="password" name="current_password" required></div>
        <div class="field"><label>New Password</label><input class="input" type="password" name="new_password" required></div>
        <div class="field"><label>Confirm New Password</label><input class="input" type="password" name="confirm_password" required></div>
        <button type="submit" class="btn btn-secondary btn-block">Update Password</button>
      </form>
    </div>

    <div class="card">
      <div class="card-head"><span class="card-title">Nominees</span></div>
      <?php if (empty($nominees)): ?>
        <div class="empty-state">No nominees added yet.</div>
      <?php else: foreach ($nominees as $n): ?>
        <div style="padding:10px 0;border-bottom:1px solid var(--border-soft);">
          <div class="text-sm" style="font-weight:600;"><?= clean($n['NomineeName']) ?></div>
          <div class="text-dim text-xs"><?= clean($n['NomineeRelation']) ?> · <?= clean($n['NomineePhone']) ?></div>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <div class="card">
      <div class="card-head"><span class="card-title">Referral Program</span></div>
      <p class="text-dim text-xs" style="margin-bottom:10px;">Share your code and earn rewards when friends join.</p>
      <div class="input mono" style="text-align:center;font-weight:700;letter-spacing:.05em;"><?= clean($customer['ReferralCode']) ?></div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
