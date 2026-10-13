<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/language.php';
require_once __DIR__ . '/includes/functions.php';
require_customer();

$customerId = (int)($_SESSION['customer_id'] ?? $_SESSION['user_id']);

// ── Direct POST Handler (Works seamlessly with or without JS) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    verify_csrf();
    $rawType = $_POST['type'] ?? 'feedback';
    $map     = ['feedback' => 'feedback', 'suggestion' => 'feedback', 'complaint' => 'complaint', 'issue' => 'complaint', 'inquiry' => 'inquiry'];
    $type    = $map[$rawType] ?? 'feedback';
    $subject = trim($_POST['subject'] ?? '');
    $message = trim(substr($_POST['message'] ?? '', 0, 500));

    if ($subject === '' || $message === '') {
        setToast('Please provide both a subject and a message.', 'danger', 3000, 'Required Fields');
    } else {
        $stmt = $pdo->prepare("INSERT INTO FEEDBACK (customer_id, subject, message, type, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$customerId, $subject, $message, $type]);
        
        notify($customerId, 'Support Ticket Created', "Your ticket regarding '{$subject}' has been registered.", 'info');
        setToast("Thank you! Your ticket has been submitted to support.", 'success', 3500, 'Ticket Submitted');
        header('Location: ' . BASE_URL . '/feedback.php');
        exit;
    }
}

// Fetch feedback history for this customer
$stmt = $pdo->prepare("SELECT * FROM FEEDBACK WHERE customer_id = ? ORDER BY created_at DESC");
$stmt->execute([$customerId]);
$feedbackList = $stmt->fetchAll();

$pageTitle  = 'Feedback & Support';
$activePage = 'feedback';
require __DIR__ . '/includes/header.php';
?>

<div class="page-content">

    <!-- Page Header -->
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div>
            <h1>Feedback &amp; Customer Support</h1>
            <p>We value your experience. Submit an inquiry, suggestion, or complaint and our support team will respond promptly.</p>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
            <span class="badge badge-outline" style="font-size:12px;padding:6px 12px;">
                <i class='bx bx-support'></i> 24/7 Digital Desk
            </span>
        </div>
    </div>

    <!-- Layout Grid -->
    <div class="feedback-layout-grid" style="display:grid;grid-template-columns:minmax(320px, 440px) 1fr;gap:28px;align-items:start;">

        <!-- Left Column: Submit Ticket Form -->
        <div style="display:flex;flex-direction:column;gap:20px;">
            <div class="card reveal" style="padding:0;overflow:hidden;border:1px solid var(--border);">
                <div class="card-header" style="background:var(--bg-subtle);padding:16px 20px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:32px;height:32px;border-radius:8px;background:var(--bg-base);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:18px;color:var(--text-primary);">
                            <i class='bx bx-message-square-edit'></i>
                        </div>
                        <div>
                            <h3 style="margin:0;font-size:15px;font-weight:600;">Submit a Support Ticket</h3>
                        </div>
                    </div>
                </div>

                <div class="card-body" style="padding:22px 20px;">
                    <form id="feedbackForm" method="POST" action="feedback.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="submit_feedback" value="1">

                        <!-- Ticket Type -->
                        <div class="form-group">
                            <label class="form-label" for="feedbackType">Category <span class="required">*</span></label>
                            <select class="form-control" id="feedbackType" name="type" required>
                                <option value="feedback">General Feedback &amp; Suggestion</option>
                                <option value="complaint">Complaint &amp; Transaction Issue</option>
                                <option value="inquiry">Account &amp; Service Inquiry</option>
                            </select>
                        </div>

                        <!-- Subject -->
                        <div class="form-group">
                            <label class="form-label" for="feedbackSubject">Subject <span class="required">*</span></label>
                            <input type="text" class="form-control" id="feedbackSubject" name="subject" 
                                   placeholder="Brief summary of your question or issue" 
                                   maxlength="200" required>
                        </div>

                        <!-- Message -->
                        <div class="form-group">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--sp-2);">
                                <label class="form-label" for="feedbackMessage" style="margin:0;">Description <span class="required">*</span></label>
                                <span class="char-count" id="charCount" style="font-size:11px;color:var(--text-muted);">0 / 500</span>
                            </div>
                            <textarea class="form-control" id="feedbackMessage" name="message" rows="5" 
                                      placeholder="Please explain the details of what happened, what you need assistance with, or how we can improve..." 
                                      maxlength="500" required style="resize:vertical;min-height:110px;"></textarea>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" id="feedbackSubmitBtn" class="btn btn-primary w-full" style="justify-content:center;margin-top:12px;">
                            <i class='bx bx-send'></i> Submit Ticket
                        </button>
                    </form>
                </div>
            </div>

            <!-- Help Channels Box -->
            <div class="card" style="padding:18px 20px;background:var(--bg-subtle);border:1px solid var(--border);">
                <div style="font-weight:600;font-size:13px;margin-bottom:10px;color:var(--text-primary);display:flex;align-items:center;gap:8px;">
                    <i class='bx bx-phone-call' style="font-size:16px;color:var(--blue);"></i> Direct Support Helplines
                </div>
                <div style="font-size:12px;color:var(--text-secondary);display:flex;flex-direction:column;gap:8px;">
                    <div style="display:flex;justify-content:space-between;">
                        <span>Customer Care:</span>
                        <span class="mono" style="font-weight:600;color:var(--text-primary);">16216 / 09612-000000</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span>Official Support Email:</span>
                        <span style="font-weight:500;color:var(--text-primary);">help@ashabank.com</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span>Card Blocking Desk:</span>
                        <span class="mono" style="font-weight:600;color:var(--danger);">09612-999999 (24/7)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Support Ticket History -->
        <div>
            <div class="card reveal" style="padding:0;overflow:hidden;border:1px solid var(--border);">
                <div class="card-header" style="background:var(--bg-subtle);padding:16px 22px;display:flex;justify-content:space-between;align-items:center;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:32px;height:32px;border-radius:8px;background:var(--bg-base);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:18px;color:var(--text-primary);">
                            <i class='bx bx-history'></i>
                        </div>
                        <h3 style="margin:0;font-size:15px;font-weight:600;">Your Ticket History</h3>
                    </div>
                    <span class="badge badge-outline" style="font-size:11px;padding:3px 8px;">
                        <?= count($feedbackList) ?> <?= count($feedbackList) === 1 ? 'Record' : 'Records' ?>
                    </span>
                </div>

                <div class="card-body" style="padding:22px;">
                    <?php if (empty($feedbackList)): ?>
                    <!-- Empty State -->
                    <div style="padding:48px 16px;text-align:center;">
                        <div style="width:56px;height:56px;border-radius:14px;background:var(--bg-muted);display:inline-flex;align-items:center;justify-content:center;font-size:28px;color:var(--text-muted);margin-bottom:14px;">
                            <i class='bx bx-chat'></i>
                        </div>
                        <h4 style="font-size:16px;font-weight:600;margin-bottom:6px;">No Tickets Submitted Yet</h4>
                        <p style="font-size:13px;color:var(--text-muted);max-width:340px;margin:0 auto;">
                            Any inquiries, feedback, or complaints you submit will appear here along with responses from our support officers.
                        </p>
                    </div>
                    <?php else: ?>

                    <!-- Ticket List -->
                    <div style="display:flex;flex-direction:column;gap:18px;">
                        <?php foreach ($feedbackList as $f): 
                            $status = $f['status'] ?? 'pending';
                            $statusBadges = [
                                'pending'     => ['badge-warning', 'bx-time', 'Pending Review'],
                                'in_progress' => ['badge-info', 'bx-loader-alt', 'In Progress'],
                                'resolved'    => ['badge-success', 'bx-check-circle', 'Resolved'],
                                'closed'      => ['badge-outline', 'bx-check-double', 'Closed'],
                            ];
                            $b = $statusBadges[$status] ?? ['badge-outline', 'bx-help-circle', ucfirst($status)];

                            $typeLabels = [
                                'feedback'  => 'Suggestion',
                                'complaint' => 'Complaint',
                                'inquiry'   => 'Inquiry',
                            ];
                            $typeText = $typeLabels[$f['type']] ?? ucfirst($f['type'] ?? 'Ticket');
                        ?>
                        <div style="background:var(--bg-base);border:1px solid var(--border);border-radius:var(--r);padding:18px;transition:box-shadow var(--dur);">
                            
                            <!-- Header Row -->
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px;flex-wrap:wrap;">
                                <div>
                                    <div style="font-size:15px;font-weight:600;color:var(--text-primary);margin-bottom:4px;">
                                        <?= clean($f['subject']) ?>
                                    </div>
                                    <div style="font-size:12px;color:var(--text-muted);display:flex;align-items:center;gap:8px;">
                                        <span class="badge badge-outline" style="font-size:10px;padding:1px 6px;"><?= e($typeText) ?></span>
                                        <span><?= time_ago($f['created_at']) ?> · <?= date('M d, Y', strtotime($f['created_at'])) ?></span>
                                    </div>
                                </div>
                                <span class="badge <?= $b[0] ?>" style="font-size:11px;padding:3px 8px;">
                                    <i class='bx <?= $b[1] ?>'></i> <?= $b[2] ?>
                                </span>
                            </div>

                            <!-- Customer Message -->
                            <div style="font-size:13.5px;color:var(--text-secondary);line-height:1.55;background:var(--bg-subtle);border-radius:var(--r-sm);padding:12px 14px;border:1px solid var(--border-soft);">
                                <?= nl2br(clean($f['message'])) ?>
                            </div>

                            <!-- Staff / Admin Response Box -->
                            <?php if (!empty($f['admin_reply'])): ?>
                            <div style="margin-top:14px;background:var(--bg-muted);border-left:3px solid var(--success);border-radius:0 var(--r-sm) var(--r-sm) 0;padding:12px 14px;">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                                    <div style="font-size:12px;font-weight:600;color:var(--text-primary);display:flex;align-items:center;gap:6px;">
                                        <i class='bx bx-headphone' style="color:var(--success);"></i>
                                        Asha Bank Support Officer
                                    </div>
                                    <?php if (!empty($f['resolved_at'])): ?>
                                    <span style="font-size:11px;color:var(--text-muted);"><?= time_ago($f['resolved_at']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:13px;color:var(--text-primary);line-height:1.5;">
                                    <?= nl2br(clean($f['admin_reply'])) ?>
                                </div>
                            </div>
                            <?php endif; ?>

                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

</div>

<?php 
$extraJS = <<<'JS'
// Live character counter
const msgInput = document.getElementById('feedbackMessage');
const charCounter = document.getElementById('charCount');
if (msgInput && charCounter) {
    const updateCount = () => {
        const len = msgInput.value.length;
        charCounter.textContent = len + ' / 500';
        if (len > 450) {
            charCounter.style.color = 'var(--danger)';
        } else {
            charCounter.style.color = 'var(--text-muted)';
        }
    };
    msgInput.addEventListener('input', updateCount);
    updateCount();
}

// Seamless AJAX submission enhancement with graceful fallback
const form = document.getElementById('feedbackForm');
const btn = document.getElementById('feedbackSubmitBtn');
if (form) {
    form.addEventListener('submit', function(e) {
        // Let standard POST run if user has JS fetch restrictions or on command
        if (!window.fetch) return true;

        e.preventDefault();
        const originalText = btn.innerHTML;
        btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Submitting...";
        btn.disabled = true;

        const formData = new FormData(form);

        fetch('api/send_feedback.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (typeof Toast !== 'undefined' && Toast.show) {
                    Toast.show(data.message || 'Ticket submitted successfully!', 'success', 2500, 'Success');
                }
                form.reset();
                if (charCounter) charCounter.textContent = '0 / 500';
                setTimeout(() => window.location.reload(), 800);
            } else {
                if (typeof Toast !== 'undefined' && Toast.show) {
                    Toast.show(data.message || 'Failed to submit feedback.', 'danger', 3500, 'Error');
                } else {
                    alert(data.message || 'Failed to submit feedback.');
                }
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        })
        .catch(err => {
            // If AJAX failed, submit normally
            form.submit();
        });
    });
}
JS;
require __DIR__ . '/includes/footer.php'; 
?>
