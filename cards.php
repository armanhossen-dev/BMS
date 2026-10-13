<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/language.php';
require_once __DIR__ . '/includes/functions.php';
require_customer();

$customerId = (int)($_SESSION['customer_id'] ?? $_SESSION['user_id']);

// Fetch customer profile
$customer = get_customer($customerId);
if (!$customer) {
    $stmt = $pdo->prepare("SELECT * FROM CUSTOMER WHERE CustomerID = ?");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
}

// Fetch user accounts
$accounts = get_accounts($customerId);
if (empty($accounts)) {
    $stmt = $pdo->prepare("SELECT a.*, ap.ProductName, ap.AccountType 
                           FROM ACCOUNT a 
                           JOIN ACCOUNTPRODUCT ap ON a.ProductID = ap.ProductID 
                           WHERE a.CustomerID = ?");
    $stmt->execute([$customerId]);
    $accounts = $stmt->fetchAll();
}

$totalBalance = array_sum(array_column($accounts, 'AvailableBalance'));
$userTier = tier_for_balance($totalBalance);

// ── Action 1: Toggle Freeze / Unfreeze Card ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_card'])) {
    verify_csrf();
    $cardId = (int)$_POST['card_id'];
    $stmt = $pdo->prepare("SELECT * FROM CARDS WHERE CardID = ? AND CustomerID = ?");
    $stmt->execute([$cardId, $customerId]);
    if ($card = $stmt->fetch()) {
        $newStatus = $card['CardStatus'] === 'Active' ? 'Blocked' : 'Active';
        $pdo->prepare("UPDATE CARDS SET CardStatus = ? WHERE CardID = ?")->execute([$newStatus, $cardId]);
        
        $last4 = substr($card['CardNumber'], -4);
        $title = $newStatus === 'Active' ? 'Card Unblocked' : 'Card Frozen';
        $msg   = "Your card ending in {$last4} is now {$newStatus}.";
        notify($customerId, $title, $msg, $newStatus === 'Active' ? 'success' : 'warning');
        setToast($msg, $newStatus === 'Active' ? 'success' : 'warning', 3000, $title);
    }
    header('Location: ' . BASE_URL . '/cards.php');
    exit;
}

// ── Action 2: Update Daily Spending Limit ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_limit'])) {
    verify_csrf();
    $cardId   = (int)$_POST['card_id'];
    $newLimit = (float)$_POST['daily_limit'];
    if ($newLimit >= 5000 && $newLimit <= 1000000) {
        $stmt = $pdo->prepare("UPDATE CARDS SET DailyLimit = ? WHERE CardID = ? AND CustomerID = ?");
        $stmt->execute([$newLimit, $cardId, $customerId]);
        setToast("Daily transaction limit updated to " . currency($newLimit) . ".", 'success', 3000, 'Limit Updated');
    } else {
        setToast("Daily limit must be between ৳5,000 and ৳10,00,000.", 'danger', 3000, 'Invalid Limit');
    }
    header('Location: ' . BASE_URL . '/cards.php');
    exit;
}

// ── Action 3: Request New / Virtual Card ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_new_card'])) {
    verify_csrf();
    $accNum   = trim($_POST['account_number'] ?? '');
    $cardType = in_array($_POST['card_type'] ?? '', ['Debit', 'Prepaid']) ? $_POST['card_type'] : 'Debit';
    
    // Validate account ownership
    $stmt = $pdo->prepare("SELECT AccountNumber FROM ACCOUNT WHERE AccountNumber = ? AND CustomerID = ?");
    $stmt->execute([$accNum, $customerId]);
    if ($stmt->fetch()) {
        $cardNum    = gen_card_number();
        $cvv        = str_pad((string)mt_rand(100, 999), 3, '0', STR_PAD_LEFT);
        $expiry     = date('Y-m-d', strtotime('+5 years'));
        $holderName = strtoupper(trim(($customer['FirstName'] ?? '') . ' ' . ($customer['LastName'] ?? '')));
        
        $insert = $pdo->prepare("INSERT INTO CARDS (CardNumber, CustomerID, AccountNumber, CardHolderName, ExpiryDate, CVV, CardType, CardStatus, DailyLimit, CreatedAt)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, 'Active', 100000.00, NOW())");
        $insert->execute([$cardNum, $customerId, $accNum, $holderName, $expiry, $cvv, $cardType]);
        
        $last4 = substr($cardNum, -4);
        notify($customerId, 'New Card Issued', "Your new {$cardType} card ending in {$last4} has been generated.", 'success');
        setToast("New {$cardType} card ending in {$last4} issued successfully!", 'success', 3500, 'Card Created');
    } else {
        setToast("Invalid account selected for card issuance.", 'danger', 3000, 'Error');
    }
    header('Location: ' . BASE_URL . '/cards.php');
    exit;
}

// Fetch user's cards with linked account details
$stmt = $pdo->prepare("SELECT c.*, a.AvailableBalance, a.AccountStatus, ap.ProductName, ap.AccountType
                       FROM CARDS c 
                       LEFT JOIN ACCOUNT a ON c.AccountNumber = a.AccountNumber 
                       LEFT JOIN ACCOUNTPRODUCT ap ON a.ProductID = ap.ProductID 
                       WHERE c.CustomerID = ?
                       ORDER BY c.CardID DESC");
$stmt->execute([$customerId]);
$cards = $stmt->fetchAll();

$pageTitle  = 'My Cards';
$activePage = 'cards';
require __DIR__ . '/includes/header.php';
?>

<div class="page-content">

    <!-- Page Header -->
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div>
            <h1>Cards &amp; Virtual Wallets</h1>
            <p>Manage physical and virtual debit cards, modify spending limits, and reveal sensitive card details.</p>
        </div>
        <button type="button" class="btn btn-primary" data-modal-open="requestCardModal">
            <i class='bx bx-plus-circle'></i> Issue Virtual Card
        </button>
    </div>

    <?php if (empty($cards)): ?>
    <!-- Empty State -->
    <div class="card" style="padding:48px 24px;text-align:center;">
        <div style="width:64px;height:64px;border-radius:16px;background:var(--bg-muted);display:inline-flex;align-items:center;justify-content:center;font-size:32px;color:var(--text-muted);margin-bottom:16px;">
            <i class='bx bx-credit-card-front'></i>
        </div>
        <h3 style="font-size:18px;font-weight:600;margin-bottom:8px;">No Cards Issued Yet</h3>
        <p style="color:var(--text-muted);max-width:420px;margin:0 auto 20px;font-size:14px;">
            You do not currently have any active debit or virtual cards linked to your account.
        </p>
        <button type="button" class="btn btn-primary" data-modal-open="requestCardModal">
            <i class='bx bx-plus'></i> Issue Your First Card
        </button>
    </div>
    <?php else: ?>

    <!-- Cards List -->
    <div style="display:flex;flex-direction:column;gap:32px;">
        <?php foreach ($cards as $card): 
            $cardTier     = tier_for_balance($card['AvailableBalance'] ?? 0);
            $tierClass    = tier_class($cardTier);
            $isBlocked    = ($card['CardStatus'] === 'Blocked');
            $maskedNum    = mask_card($card['CardNumber']);
            $unmaskedNum  = chunk_split($card['CardNumber'], 4, ' ');
            $holderName   = !empty($card['CardHolderName']) ? $card['CardHolderName'] : strtoupper(($customer['FirstName'] ?? '') . ' ' . ($customer['LastName'] ?? ''));
            $expiryFormat = date('m/y', strtotime($card['ExpiryDate']));
            $dailyLimit   = (float)($card['DailyLimit'] ?? 100000.00);
            $accBal       = (float)($card['AvailableBalance'] ?? 0);
        ?>
        <div class="card reveal" style="padding:0;overflow:hidden;border:1px solid var(--border);">
            
            <!-- Card Meta Banner -->
            <div style="padding:16px 24px;background:var(--bg-subtle);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:var(--bg-base);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:20px;color:var(--text-primary);">
                        <i class='bx bx-credit-card'></i>
                    </div>
                    <div>
                        <div style="font-weight:600;font-size:15px;">
                            <?= e($card['CardType'] ?? 'Debit') ?> Card 
                            <span style="font-weight:400;color:var(--text-muted);font-size:13px;">(Ends in <?= substr($card['CardNumber'], -4) ?>)</span>
                        </div>
                        <div style="font-size:12px;color:var(--text-muted);">
                            Linked to Account <?= e($card['AccountNumber']) ?> · <?= e($card['ProductName'] ?? 'Asha Savings') ?>
                        </div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <span class="badge <?= $isBlocked ? 'badge-danger' : 'badge-success' ?>" style="font-size:12px;padding:4px 10px;">
                        <i class='bx <?= $isBlocked ? 'bx-lock-alt' : 'bx-check-circle' ?>'></i>
                        <?= e($card['CardStatus']) ?>
                    </span>
                    <span class="badge badge-outline" style="font-size:12px;padding:4px 10px;">
                        <?= e($cardTier) ?> Tier
                    </span>
                </div>
            </div>

            <div class="card-body" style="padding:28px 24px;">
                <div class="cards-layout-grid">
                    
                    <!-- Left Column: Visual Bank Card Mockup & Quick Actions -->
                    <div>
                        <!-- Interactive Bank Card -->
                        <div class="bank-card <?= e($tierClass) ?>" 
                             style="cursor:pointer;position:relative;margin:0 auto;"
                             data-modal-open="revealModal-<?= $card['CardID'] ?>"
                             title="Click to view full card details &amp; CVV">
                            
                            <!-- Specular Shine Overlays -->
                            <div style="position:absolute;top:-40%;right:-20%;width:220px;height:220px;background:radial-gradient(circle,rgba(255,255,255,0.14) 0%,transparent 70%);border-radius:50%;pointer-events:none;"></div>
                            <div style="position:absolute;bottom:-40%;left:-20%;width:180px;height:180px;background:radial-gradient(circle,rgba(255,255,255,0.06) 0%,transparent 70%);border-radius:50%;pointer-events:none;"></div>

                            <!-- Card Header: Brand & Contactless Icon -->
                            <div class="bank-card-header">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <div style="width:28px;height:28px;border-radius:8px;background:rgba(255,255,255,0.16);display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.2" stroke-linecap="round" width="16" height="16"><path d="M4 16a8 8 0 0 1 16 0"/><path d="M12 4v2M4.9 7.9l1.4 1.4M19.1 7.9l-1.4 1.4M2 20h20"/></svg>
                                    </div>
                                    <span class="bank-card-logo">Asha Bank</span>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <i class='bx bx-rfid' style="font-size:22px;color:rgba(255,255,255,0.85);transform:rotate(90deg);" title="Contactless Active"></i>
                                </div>
                            </div>

                            <!-- EMV Chip & Monospace Number -->
                            <div style="margin:6px 0 14px;">
                                <div class="bank-card-chip" style="width:40px;height:28px;border-radius:6px;background:linear-gradient(135deg,#FFE382 0%,#D4A843 50%,#9E751D 100%);border:1px solid rgba(0,0,0,0.25);position:relative;overflow:hidden;box-shadow:inset 0 1px 2px rgba(255,255,255,0.4);">
                                    <div style="position:absolute;inset:0;background:repeating-linear-gradient(90deg,transparent,transparent 10px,rgba(0,0,0,0.15) 10px,rgba(0,0,0,0.15) 12px);opacity:0.6;"></div>
                                    <div style="position:absolute;top:50%;left:0;right:0;height:1px;background:rgba(0,0,0,0.25);"></div>
                                </div>
                                <div class="bank-card-number" style="font-size:18px;letter-spacing:0.18em;margin-top:14px;color:#FFFFFF;text-shadow:0 1px 2px rgba(0,0,0,0.6);">
                                    <?= e($maskedNum) ?>
                                </div>
                            </div>

                            <!-- Card Footer: Cardholder, Expiry, Brand Network -->
                            <div class="bank-card-footer">
                                <div>
                                    <div class="bank-card-label">Card Holder</div>
                                    <div class="bank-card-value"><?= e($holderName) ?></div>
                                </div>
                                <div style="text-align:right;">
                                    <div class="bank-card-label">Expires</div>
                                    <div class="bank-card-value" style="font-family:var(--font-mono);font-size:12px;"><?= e($expiryFormat) ?></div>
                                </div>
                                <div class="bank-card-network" style="opacity:0.95;">
                                    <i class='bx bxl-visa' style="font-size:36px;line-height:1;"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Card Action Buttons -->
                        <div style="display:flex;gap:10px;margin-top:18px;justify-content:center;flex-wrap:wrap;">
                            <button type="button" class="btn btn-outline btn-sm" data-modal-open="revealModal-<?= $card['CardID'] ?>" style="flex:1;min-width:130px;justify-content:center;">
                                <i class='bx bx-show'></i> Reveal Details
                            </button>

                            <button type="button" class="btn btn-secondary btn-sm" data-modal-open="limitModal-<?= $card['CardID'] ?>" style="flex:1;min-width:110px;justify-content:center;">
                                <i class='bx bx-slider-alt'></i> Limits
                            </button>

                            <!-- Toggle Freeze Form -->
                            <form method="POST" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="card_id" value="<?= $card['CardID'] ?>">
                                <button type="submit" name="toggle_card" class="btn btn-sm <?= $isBlocked ? 'btn-primary' : 'btn-danger' ?>"
                                        data-confirm="<?= $isBlocked ? 'Are you sure you want to unblock and restore all transactions for this card?' : 'Are you sure you want to freeze this card? POS transactions, ATM withdrawals, and recurring payments will be instantly paused.' ?>"
                                        data-confirm-title="<?= $isBlocked ? 'Unblock Card' : 'Freeze Card' ?>"
                                        data-confirm-btn="<?= $isBlocked ? 'Yes, Unblock' : 'Yes, Freeze Card' ?>"
                                        data-confirm-class="<?= $isBlocked ? 'btn-primary' : 'btn-danger' ?>"
                                        data-confirm-icon="bx-credit-card"
                                        style="min-width:105px;justify-content:center;">
                                    <i class='bx <?= $isBlocked ? 'bx-lock-open-alt' : 'bx-lock-alt' ?>'></i>
                                    <?= $isBlocked ? 'Unblock' : 'Freeze' ?>
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Right Column: Card Details, Spending Limit & Security Safeguards -->
                    <div>
                        <!-- Overview Grid -->
                        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:24px;">
                            <div style="background:var(--bg-subtle);border:1px solid var(--border);border-radius:var(--r);padding:14px 16px;">
                                <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Available Linked Balance</div>
                                <div style="font-size:18px;font-weight:700;color:var(--text-primary);"><?= currency($accBal) ?></div>
                                <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">Account: <?= e($card['AccountNumber']) ?></div>
                            </div>

                            <div style="background:var(--bg-subtle);border:1px solid var(--border);border-radius:var(--r);padding:14px 16px;">
                                <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Daily Transaction Limit</div>
                                <div style="font-size:18px;font-weight:700;color:var(--text-primary);"><?= currency($dailyLimit) ?></div>
                                <div style="font-size:11px;color:var(--success);margin-top:2px;">
                                    <i class='bx bx-check-circle'></i> Configured &amp; Active
                                </div>
                            </div>
                        </div>

                        <!-- Daily Limit Progress Bar -->
                        <div style="background:var(--bg-base);border:1px solid var(--border);border-radius:var(--r);padding:16px;margin-bottom:24px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                                <span style="font-size:13px;font-weight:600;color:var(--text-primary);">Daily Spending Allocation</span>
                                <span style="font-size:12px;color:var(--text-muted);">Max <?= currency($dailyLimit) ?></span>
                            </div>
                            <div style="height:8px;background:var(--bg-muted);border-radius:999px;overflow:hidden;position:relative;">
                                <div style="width:15%;height:100%;background:linear-gradient(90deg, #10B981, #059669);border-radius:999px;"></div>
                            </div>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;font-size:11px;color:var(--text-muted);">
                                <span>Used today: ৳ 0.00</span>
                                <span>Remaining: <?= currency($dailyLimit) ?></span>
                            </div>
                        </div>

                        <!-- Security & Feature Controls -->
                        <div>
                            <div style="font-size:13px;font-weight:600;color:var(--text-primary);margin-bottom:12px;">Card Security Parameters</div>
                            
                            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(210px, 1fr));gap:12px;">
                                <!-- Item 1 -->
                                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--bg-subtle);border:1px solid var(--border);border-radius:var(--r);">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <i class='bx bx-globe' style="font-size:18px;color:var(--text-secondary);"></i>
                                        <span style="font-size:13px;color:var(--text-primary);">Online Purchases</span>
                                    </div>
                                    <span class="badge badge-success" style="font-size:10px;">Active</span>
                                </div>

                                <!-- Item 2 -->
                                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--bg-subtle);border:1px solid var(--border);border-radius:var(--r);">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <i class='bx bx-rfid' style="font-size:18px;color:var(--text-secondary);"></i>
                                        <span style="font-size:13px;color:var(--text-primary);">Contactless (NFC)</span>
                                    </div>
                                    <span class="badge badge-success" style="font-size:10px;">Active</span>
                                </div>

                                <!-- Item 3 -->
                                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--bg-subtle);border:1px solid var(--border);border-radius:var(--r);">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <i class='bx bx-money' style="font-size:18px;color:var(--text-secondary);"></i>
                                        <span style="font-size:13px;color:var(--text-primary);">ATM Cash Access</span>
                                    </div>
                                    <span class="badge badge-success" style="font-size:10px;">Active</span>
                                </div>

                                <!-- Item 4 -->
                                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--bg-subtle);border:1px solid var(--border);border-radius:var(--r);">
                                    <div style="display:flex;align-items:center;gap:10px;">
                                        <i class='bx bx-shield-quarter' style="font-size:18px;color:var(--text-secondary);"></i>
                                        <span style="font-size:13px;color:var(--text-primary);">Fraud Protection</span>
                                    </div>
                                    <span class="badge badge-success" style="font-size:10px;">24/7 Monitored</span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- ── Modal: Reveal Sensitive Card Details ───────────────── -->
        <div class="modal-overlay" id="revealModal-<?= $card['CardID'] ?>" style="display:none;">
            <div class="modal" style="max-width:480px;" role="dialog" aria-modal="true">
                <div class="modal-header">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:34px;height:34px;border-radius:8px;background:var(--bg-muted);display:flex;align-items:center;justify-content:center;font-size:18px;">
                            <i class='bx bx-shield-alt-2'></i>
                        </div>
                        <h3 style="margin:0;font-size:16px;">Card Credentials &amp; Details</h3>
                    </div>
                    <button class="modal-close" type="button" data-modal-close="revealModal-<?= $card['CardID'] ?>">&times;</button>
                </div>
                
                <div class="modal-body" style="padding:var(--sp-6);">
                    <!-- Mini Card Preview -->
                    <div class="bank-card <?= e($tierClass) ?>" style="margin-bottom:22px;">
                        <div class="bank-card-header">
                            <div class="bank-card-logo">Asha Bank</div>
                            <i class='bx bx-rfid' style="font-size:20px;transform:rotate(90deg);"></i>
                        </div>
                        <div class="bank-card-number" style="font-size:17px;letter-spacing:0.18em;margin-bottom:12px;">
                            <?= e($unmaskedNum) ?>
                        </div>
                        <div class="bank-card-footer">
                            <div>
                                <div class="bank-card-label">Card Holder</div>
                                <div class="bank-card-value"><?= e($holderName) ?></div>
                            </div>
                            <div style="text-align:right;">
                                <div class="bank-card-label">Expires</div>
                                <div class="bank-card-value" style="font-family:var(--font-mono);font-size:12px;"><?= e($expiryFormat) ?></div>
                            </div>
                            <div class="bank-card-network"><i class='bx bxl-visa' style="font-size:32px;"></i></div>
                        </div>
                    </div>

                    <!-- Sensitive Data Grid -->
                    <div style="display:flex;flex-direction:column;gap:14px;">
                        <!-- Full Card Number -->
                        <div style="background:var(--bg-subtle);border:1px solid var(--border);border-radius:var(--r);padding:12px 16px;display:flex;align-items:center;justify-content:space-between;">
                            <div>
                                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">16-Digit Card Number</div>
                                <div style="font-family:var(--font-mono);font-size:16px;font-weight:600;color:var(--text-primary);letter-spacing:0.08em;margin-top:2px;">
                                    <?= e($unmaskedNum) ?>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline btn-sm copy-btn" data-copy="<?= e($card['CardNumber']) ?>" title="Copy to clipboard">
                                <i class='bx bx-copy'></i> Copy
                            </button>
                        </div>

                        <!-- CVV & Expiry -->
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div style="background:var(--bg-subtle);border:1px solid var(--border);border-radius:var(--r);padding:12px 16px;display:flex;align-items:center;justify-content:space-between;">
                                <div>
                                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Security Code (CVV)</div>
                                    <div style="font-family:var(--font-mono);font-size:16px;font-weight:700;color:var(--text-primary);margin-top:2px;">
                                        <?= e($card['CVV']) ?>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline btn-sm copy-btn" data-copy="<?= e($card['CVV']) ?>" title="Copy CVV">
                                    <i class='bx bx-copy'></i>
                                </button>
                            </div>

                            <div style="background:var(--bg-subtle);border:1px solid var(--border);border-radius:var(--r);padding:12px 16px;">
                                <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Valid Thru</div>
                                <div style="font-family:var(--font-mono);font-size:16px;font-weight:600;color:var(--text-primary);margin-top:2px;">
                                    <?= date('m / Y', strtotime($card['ExpiryDate'])) ?>
                                </div>
                            </div>
                        </div>

                        <!-- Account & Security Info -->
                        <div style="background:var(--bg-subtle);border:1px solid var(--border);border-radius:var(--r);padding:12px 16px;font-size:13px;color:var(--text-secondary);">
                            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                                <span>Linked Account:</span>
                                <span class="mono" style="font-weight:600;color:var(--text-primary);"><?= e($card['AccountNumber']) ?></span>
                            </div>
                            <div style="display:flex;justify-content:space-between;">
                                <span>Billing Postal Address:</span>
                                <span style="font-weight:500;color:var(--text-primary);"><?= e($customer['City'] ?? 'Dhaka, Bangladesh') ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="padding:var(--sp-4) var(--sp-6);display:flex;justify-content:flex-end;">
                    <button type="button" class="btn btn-primary btn-sm" data-modal-close="revealModal-<?= $card['CardID'] ?>">Done</button>
                </div>
            </div>
        </div>

        <!-- ── Modal: Update Daily Spending Limit ─────────────────── -->
        <div class="modal-overlay" id="limitModal-<?= $card['CardID'] ?>" style="display:none;">
            <div class="modal" style="max-width:440px;" role="dialog" aria-modal="true">
                <div class="modal-header">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:34px;height:34px;border-radius:8px;background:var(--bg-muted);display:flex;align-items:center;justify-content:center;font-size:18px;">
                            <i class='bx bx-slider'></i>
                        </div>
                        <h3 style="margin:0;font-size:16px;">Adjust Daily Spending Limit</h3>
                    </div>
                    <button class="modal-close" type="button" data-modal-close="limitModal-<?= $card['CardID'] ?>">&times;</button>
                </div>

                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="card_id" value="<?= $card['CardID'] ?>">
                    
                    <div class="modal-body" style="padding:var(--sp-6);">
                        <p style="font-size:14px;color:var(--text-secondary);margin-bottom:16px;">
                            Modify the maximum expenditure limit for card ending in <strong><?= substr($card['CardNumber'], -4) ?></strong> across POS, ATMs, and online gateways.
                        </p>

                        <div class="form-group">
                            <label class="form-label" for="daily_limit_<?= $card['CardID'] ?>">New Limit (BDT ৳)</label>
                            <input type="number" id="daily_limit_<?= $card['CardID'] ?>" name="daily_limit" class="form-control"
                                   min="5000" max="1000000" step="5000" value="<?= (int)$dailyLimit ?>" required>
                            <div style="font-size:12px;color:var(--text-muted);margin-top:6px;">Allowable range: ৳5,000 to ৳10,00,000.</div>
                        </div>

                        <!-- Presets -->
                        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
                            <button type="button" class="btn btn-outline btn-xs limit-preset" data-target="daily_limit_<?= $card['CardID'] ?>" data-val="50000">৳50k</button>
                            <button type="button" class="btn btn-outline btn-xs limit-preset" data-target="daily_limit_<?= $card['CardID'] ?>" data-val="100000">৳100k</button>
                            <button type="button" class="btn btn-outline btn-xs limit-preset" data-target="daily_limit_<?= $card['CardID'] ?>" data-val="200000">৳200k</button>
                            <button type="button" class="btn btn-outline btn-xs limit-preset" data-target="daily_limit_<?= $card['CardID'] ?>" data-val="500000">৳500k</button>
                        </div>
                    </div>

                    <div class="modal-footer" style="padding:var(--sp-4) var(--sp-6);display:flex;justify-content:flex-end;gap:10px;">
                        <button type="button" class="btn btn-outline btn-sm" data-modal-close="limitModal-<?= $card['CardID'] ?>">Cancel</button>
                        <button type="submit" name="update_limit" class="btn btn-primary btn-sm">Save Limit</button>
                    </div>
                </form>
            </div>
        </div>

        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<!-- ── Modal: Request New / Virtual Card ──────────────────────── -->
<div class="modal-overlay" id="requestCardModal" style="display:none;">
    <div class="modal" style="max-width:480px;" role="dialog" aria-modal="true">
        <div class="modal-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:34px;height:34px;border-radius:8px;background:var(--bg-muted);display:flex;align-items:center;justify-content:center;font-size:18px;">
                    <i class='bx bx-credit-card-front'></i>
                </div>
                <h3 style="margin:0;font-size:16px;">Issue New Debit Card</h3>
            </div>
            <button class="modal-close" type="button" data-modal-close="requestCardModal">&times;</button>
        </div>

        <form method="POST">
            <?= csrf_field() ?>
            <div class="modal-body" style="padding:var(--sp-6);">
                <p style="font-size:14px;color:var(--text-secondary);margin-bottom:18px;">
                    Generate an instant Virtual Visa Debit Card linked directly to one of your active savings or current accounts.
                </p>

                <div class="form-group">
                    <label class="form-label" for="account_number">Select Linked Account <span class="required">*</span></label>
                    <select id="account_number" name="account_number" class="form-control" required>
                        <?php foreach ($accounts as $a): ?>
                        <option value="<?= e($a['AccountNumber']) ?>">
                            <?= e($a['AccountNumber']) ?> — <?= e($a['ProductName'] ?? 'Account') ?> (<?= currency($a['AvailableBalance']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="card_type">Card Type</label>
                    <select id="card_type" name="card_type" class="form-control">
                        <option value="Debit">Virtual Visa Debit (Instant Activation)</option>
                        <option value="Prepaid">Virtual Prepaid Wallet</option>
                    </select>
                </div>

                <div style="background:var(--bg-subtle);border:1px solid var(--border);border-radius:var(--r);padding:14px;margin-top:16px;font-size:12px;color:var(--text-muted);display:flex;gap:10px;align-items:flex-start;">
                    <i class='bx bx-info-circle' style="font-size:18px;color:var(--blue);flex-shrink:0;margin-top:1px;"></i>
                    <div>
                        Virtual cards are generated instantly with a unique 16-digit card number, CVV, and 5-year expiration date. No physical issuance charge applied.
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="padding:var(--sp-4) var(--sp-6);display:flex;justify-content:flex-end;gap:10px;">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close="requestCardModal">Cancel</button>
                <button type="submit" name="request_new_card" class="btn btn-primary btn-sm">
                    <i class='bx bx-check'></i> Generate Card
                </button>
            </div>
        </form>
    </div>
</div>

<?php 
$extraJS = <<<'JS'
// Handle preset limit clicks
document.querySelectorAll('.limit-preset').forEach(btn => {
    btn.addEventListener('click', () => {
        const targetId = btn.dataset.target;
        const input = document.getElementById(targetId);
        if (input) {
            input.value = btn.dataset.val;
            input.focus();
        }
    });
});

// Handle clipboard copy
document.querySelectorAll('.copy-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const text = btn.dataset.copy;
        if (!text) return;
        
        navigator.clipboard.writeText(text).then(() => {
            if (typeof Toast !== 'undefined' && Toast.show) {
                Toast.show('Copied ' + text + ' to clipboard!', 'success', 2000, 'Copied');
            } else {
                alert('Copied to clipboard!');
            }
        }).catch(() => {
            prompt('Copy to clipboard: Ctrl+C, Enter', text);
        });
    });
});
JS;
require __DIR__ . '/includes/footer.php'; 
?>
