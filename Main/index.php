<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/language.php';
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['customer_id'])) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }
if (!empty($_SESSION['staff_id']))    { header('Location: ' . BASE_URL . '/staff/dashboard.php'); exit; }
if (!empty($_SESSION['admin_id']))    { header('Location: ' . BASE_URL . '/admin/index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Asha Bank — Banking that grows with hope</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div style="max-width:1200px;margin:0 auto;padding:26px 24px;">
  <div class="flex items-center justify-between">
    <div class="brand" style="padding:0;">
      <div class="brand-mark">A</div>
      <div class="brand-name" style="font-size:19px;">Asha<span>Bank</span></div>
    </div>
    <div class="flex gap-12">
      <a href="login.php" class="btn btn-secondary">Sign In</a>
      <a href="register.php" class="btn btn-primary">Open Account</a>
    </div>
  </div>

  <div style="text-align:center;padding:90px 20px 50px;max-width:760px;margin:0 auto;">
    <span class="badge badge-warning" style="margin-bottom:20px;">✨ Now with Loans & Bill Pay</span>
    <h1 style="font-size:52px;line-height:1.15;letter-spacing:-0.02em;">Banking that grows<br>with your <span style="color:var(--accent);">hope</span>.</h1>
    <p class="text-muted mt-16" style="font-size:16px;max-width:520px;margin:16px auto 0;">Savings, transfers, loans, cards, and bill payments — one secure account for everything money.</p>
    <div class="flex gap-12" style="justify-content:center;margin-top:32px;">
      <a href="register.php" class="btn btn-primary" style="padding:13px 28px;">Get Started Free</a>
      <a href="login.php" class="btn btn-secondary" style="padding:13px 28px;">I have an account</a>
    </div>
  </div>

  <div class="grid grid-4" style="margin-top:40px;">
    <?php
    $features = [
        ['Transfers','Send money instantly between Asha accounts, 24/7.','send'],
        ['Tiered Rewards','Classic to Black Edition — unlock perks as your balance grows.','award'],
        ['Loans & EMIs','Home, personal, car and education loans with clear schedules.','landmark'],
        ['Bill Payments','Pay electricity, gas, mobile and more in a few taps.','file-text'],
    ];
    foreach ($features as $f):
    ?>
    <div class="card">
      <div class="icon-wrap" style="margin-bottom:14px;width:38px;height:38px;border-radius:10px;background:var(--surface-2);display:flex;align-items:center;justify-content:center;color:var(--accent);">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg>
      </div>
      <h3 style="font-size:15px;margin-bottom:6px;"><?= clean($f[0]) ?></h3>
      <p class="text-dim text-sm"><?= clean($f[1]) ?></p>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="text-center text-dim text-xs" style="text-align:center;margin-top:80px;padding-top:24px;border-top:1px solid var(--border-soft);">
    © <?= date('Y') ?> Asha Bank. All rights reserved.
  </div>
</div>
</body>
</html>
