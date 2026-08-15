<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/language.php';
require_once __DIR__ . '/includes/functions.php';
require_customer();

$customerId = $_SESSION['customer_id'];

$stmt = $pdo->prepare("SELECT * FROM FEEDBACK WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->execute([$customerId]);
$feedbackList = $stmt->fetchAll();

$pageTitle = 'Feedback';
$activeNav = 'feedback';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div><h1>Feedback & Support</h1><div class="sub">Tell us what's working — or what's not.</div></div>
</div>

<div class="grid grid-main">
  <div class="card">
    <div class="card-head"><span class="card-title">Send us a message</span></div>
    <form id="feedbackForm" onsubmit="return submitFeedbackForm(this)">
      <?= csrf_field() ?>
      <div class="field">
        <label>Type</label>
        <select class="input" name="type">
          <option value="feedback">Feedback</option>
          <option value="complaint">Complaint</option>
          <option value="suggestion">Suggestion</option>
          <option value="issue">Issue</option>
        </select>
      </div>
      <div class="field">
        <label>Subject</label>
        <input class="input" name="subject" maxlength="200" required>
      </div>
      <div class="field">
        <label>Message</label>
        <textarea class="input" name="message" maxlength="500" data-maxlength="500" data-counter-target="charCount" required></textarea>
        <div class="char-count" id="charCount">0 / 500</div>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Submit</button>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><span class="card-title">Your History</span></div>
    <?php if (empty($feedbackList)): ?>
      <div class="empty-state">No feedback submitted yet.</div>
    <?php else: foreach ($feedbackList as $f): ?>
      <div style="padding:14px 0;border-bottom:1px solid var(--border-soft);">
        <div class="flex justify-between items-center">
          <span class="text-sm" style="font-weight:600;"><?= clean($f['subject']) ?></span>
          <span class="badge <?= $f['status']==='resolved'?'badge-success':($f['status']==='pending'?'badge-warning':'badge-info') ?>"><?= ucfirst($f['status']) ?></span>
        </div>
        <p class="text-dim text-xs mt-8"><?= clean($f['message']) ?></p>
        <?php if ($f['staff_reply']): ?>
          <div class="card" style="background:var(--surface-2);padding:10px 12px;margin-top:10px;">
            <div class="text-xs text-dim" style="margin-bottom:4px;">Staff reply · <?= time_ago($f['replied_at']) ?></div>
            <div class="text-sm"><?= clean($f['staff_reply']) ?></div>
          </div>
        <?php endif; ?>
        <div class="text-dim text-xs mt-8"><?= time_ago($f['created_at']) ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
