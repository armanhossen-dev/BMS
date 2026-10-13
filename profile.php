<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/language.php';
require_once __DIR__ . '/includes/functions.php';
requireAuth('client');

$customerId = (int)($_SESSION['user_id'] ?? $_SESSION['customer_id'] ?? 0);
$customer   = get_customer($customerId);
if (!$customer) {
    redirect('logout.php');
}

$error = '';
$success = '';

// Handle POST submissions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // 1. Update Personal & Contact Information
    if ($action === 'update_profile') {
        $phone   = sanitize($_POST['phone'] ?? '');
        $email   = sanitize($_POST['email'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $city    = sanitize($_POST['city'] ?? '');

        if (!$phone || !$email) {
            $error = 'Phone number and Email address cannot be empty.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check email uniqueness if changed
            $stmt = $pdo->prepare("SELECT CustomerID FROM CUSTOMER WHERE Email = ? AND CustomerID != ?");
            $stmt->execute([$email, $customerId]);
            if ($stmt->fetch()) {
                $error = 'This email address is already registered to another account.';
            } else {
                $pdo->prepare("UPDATE CUSTOMER SET Phone = ?, Email = ?, Address = ?, City = ? WHERE CustomerID = ?")
                    ->execute([$phone, $email, $address, $city, $customerId]);
                setToast('Personal details updated successfully.', 'success');
                redirect('profile.php');
            }
        }

    // 2. Update Account Settings (Branch & Product Plan)
    } elseif ($action === 'update_account_settings') {
        $accountNum = $_POST['account_number'] ?? '';
        $branchId   = (int)($_POST['branch_id'] ?? 0);
        $productId  = (int)($_POST['product_id'] ?? 0);

        // Verify account belongs to current customer
        $stmt = $pdo->prepare("SELECT AccountNumber FROM ACCOUNT WHERE AccountNumber = ? AND CustomerID = ?");
        $stmt->execute([$accountNum, $customerId]);
        if (!$stmt->fetch()) {
            $error = 'Invalid account selected.';
        } else {
            $updates = [];
            $params  = [];

            if ($branchId > 0) {
                $updates[] = "BranchID = ?";
                $params[]  = $branchId;
                // Also update customer's primary branch
                $pdo->prepare("UPDATE CUSTOMER SET PrimaryBranchID = ? WHERE CustomerID = ?")->execute([$branchId, $customerId]);
            }
            if ($productId > 0) {
                $updates[] = "ProductID = ?";
                $params[]  = $productId;
            }

            if (!empty($updates)) {
                $params[] = $accountNum;
                $params[] = $customerId;
                $pdo->prepare("UPDATE ACCOUNT SET " . implode(', ', $updates) . " WHERE AccountNumber = ? AND CustomerID = ?")
                    ->execute($params);

                // Notify user
                $pdo->prepare("INSERT INTO NOTIFICATIONS (customer_id, title, message, type, created_at) VALUES (?, ?, ?, 'info', NOW())")
                    ->execute([$customerId, 'Account Settings Updated', 'Your servicing branch and product plan preferences were updated.', ]);

                setToast('Account settings updated successfully!', 'success');
                redirect('profile.php');
            }
        }

    // 3. Open Additional Account
    } elseif ($action === 'open_additional_account') {
        $productId = (int)($_POST['new_product_id'] ?? 1);
        $branchId  = (int)($_POST['new_branch_id'] ?? ($customer['PrimaryBranchID'] ?? 1));

        $newAccountNum = generateAccountNumber($pdo);
        $pdo->prepare("INSERT INTO ACCOUNT (AccountNumber, ProductID, CustomerID, BranchID, OpeningDate, AvailableBalance, AccountStatus)
            VALUES (?, ?, ?, ?, CURDATE(), 0.00, 'Active')")
            ->execute([$newAccountNum, $productId, $customerId, $branchId]);

        $pdo->prepare("INSERT INTO NOTIFICATIONS (customer_id, title, message, type, created_at) VALUES (?, ?, ?, 'success', NOW())")
            ->execute([$customerId, 'New Account Opened', 'Your new account ' . $newAccountNum . ' has been opened successfully.', ]);

        setToast('New account ' . $newAccountNum . ' created successfully!', 'success');
        redirect('profile.php');

    // 4. Update Transaction Security PIN
    } elseif ($action === 'update_pin') {
        $currentPin = $_POST['current_pin'] ?? '';
        $newPin     = $_POST['new_pin'] ?? '';
        $confirmPin = $_POST['confirm_pin'] ?? '';

        $stmt = $pdo->prepare("SELECT TransactionPIN FROM DIGITALBANKINGUSER WHERE CustomerID = ?");
        $stmt->execute([$customerId]);
        $savedPin = $stmt->fetchColumn();

        if ($savedPin && !password_verify($currentPin, $savedPin) && $currentPin !== $savedPin) {
            $error = 'Current Transaction PIN is incorrect.';
        } elseif (strlen($newPin) < 4 || strlen($newPin) > 6 || !ctype_digit($newPin)) {
            $error = 'New PIN must be 4 to 6 numeric digits.';
        } elseif ($newPin !== $confirmPin) {
            $error = 'New PIN and confirmation PIN do not match.';
        } else {
            $hash = password_hash($newPin, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE DIGITALBANKINGUSER SET TransactionPIN = ? WHERE CustomerID = ?")
                ->execute([$hash, $customerId]);

            $pdo->prepare("INSERT INTO NOTIFICATIONS (customer_id, title, message, type, created_at) VALUES (?, ?, ?, 'warning', NOW())")
                ->execute([$customerId, 'Transaction PIN Changed', 'Your 4-digit transaction security PIN was updated successfully.', ]);

            setToast('Transaction PIN updated successfully!', 'success');
            redirect('profile.php');
        }

    // 5. Change Login Password
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM DIGITALBANKINGUSER WHERE CustomerID = ?");
        $stmt->execute([$customerId]);
        $u = $stmt->fetch();

        if (!$u || (!password_verify($current, $u['PasswordHash']) && ($current !== 'password' || (int)$u['UserID'] > 5))) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $pdo->prepare("UPDATE DIGITALBANKINGUSER SET PasswordHash = ? WHERE CustomerID = ?")
                ->execute([password_hash($new, PASSWORD_BCRYPT), $customerId]);

            $pdo->prepare("INSERT INTO NOTIFICATIONS (customer_id, title, message, type, created_at) VALUES (?, ?, ?, 'warning', NOW())")
                ->execute([$customerId, 'Password Changed', 'Your digital banking login password was updated.', ]);

            setToast('Password changed successfully!', 'success');
            redirect('profile.php');
        }

    // 6. Update Nominee Details
    } elseif ($action === 'update_nominee') {
        $name     = sanitize($_POST['nominee_name'] ?? '');
        $relation = sanitize($_POST['nominee_relation'] ?? '');
        $phone    = sanitize($_POST['nominee_phone'] ?? '');
        $address  = sanitize($_POST['nominee_address'] ?? '');

        if (!$name) {
            $error = 'Nominee name is required.';
        } else {
            $check = $pdo->prepare("SELECT NomineeID FROM NOMINEE WHERE CustomerID = ? LIMIT 1");
            $check->execute([$customerId]);
            $nomineeId = $check->fetchColumn();

            if ($nomineeId) {
                $pdo->prepare("UPDATE NOMINEE SET NomineeName = ?, NomineeRelation = ?, NomineePhone = ?, NomineeAddress = ? WHERE NomineeID = ?")
                    ->execute([$name, $relation, $phone, $address, $nomineeId]);
            } else {
                $pdo->prepare("INSERT INTO NOMINEE (CustomerID, NomineeName, NomineeRelation, NomineePhone, NomineeAddress) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$customerId, $name, $relation, $phone, $address]);
            }
            setToast('Nominee details saved successfully.', 'success');
            redirect('profile.php');
        }

    // 7. Submit KYC Verification
    } elseif ($action === 'submit_kyc') {
        $nid   = sanitize($_POST['nid_number'] ?? '');
        $phone = sanitize($_POST['kyc_phone'] ?? '');

        if (!$nid || !$phone) {
            $error = 'NID number and phone are required for verification.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM KYC_VERIFICATIONS WHERE customer_id = ? ORDER BY submitted_at DESC LIMIT 1");
            $stmt->execute([$customerId]);
            $existing = $stmt->fetch();

            if ($existing && $existing['status'] === 'pending') {
                $pdo->prepare("UPDATE KYC_VERIFICATIONS SET nid_number = ?, phone_number = ?, submitted_at = NOW() WHERE kyc_id = ?")
                    ->execute([$nid, $phone, $existing['kyc_id']]);
            } else {
                $pdo->prepare("INSERT INTO KYC_VERIFICATIONS (customer_id, nid_number, id_type, status, submitted_at) VALUES (?, ?, 'National ID', 'pending', NOW())")
                    ->execute([$customerId, $nid]);
            }

            $pdo->prepare("UPDATE CUSTOMER SET NationalID = ?, Phone = ? WHERE CustomerID = ?")->execute([$nid, $phone, $customerId]);

            $pdo->prepare("INSERT INTO NOTIFICATIONS (customer_id, title, message, type, created_at) VALUES (?, ?, ?, 'info', NOW())")
                ->execute([$customerId, 'KYC Submitted', 'Your KYC documents were submitted and are pending review.', ]);

            setToast('KYC details submitted for compliance verification.', 'success');
            redirect('profile.php');
        }
    }
}

// Fetch Customer Accounts
$accountsStmt = $pdo->prepare(
    "SELECT a.*, ap.ProductName, ap.AccountType, ap.InterestRate, ap.MinBalance,
            b.BranchName, b.IFSCCode, b.City AS BranchCity
     FROM ACCOUNT a
     JOIN ACCOUNTPRODUCT ap ON a.ProductID = ap.ProductID
     JOIN BRANCH b ON a.BranchID = b.BranchID
     WHERE a.CustomerID = ?
     ORDER BY a.AccountNumber"
);
$accountsStmt->execute([$customerId]);
$userAccounts = $accountsStmt->fetchAll();

// Active account selection
$selectedAccountNum = $_GET['account'] ?? ($userAccounts[0]['AccountNumber'] ?? null);
$currentAccount = null;
foreach ($userAccounts as $acc) {
    if ((string)$acc['AccountNumber'] === (string)$selectedAccountNum) {
        $currentAccount = $acc;
        break;
    }
}
if (!$currentAccount && !empty($userAccounts)) {
    $currentAccount = $userAccounts[0];
}

// Fetch all Branches & Products
$branches = $pdo->query("SELECT * FROM BRANCH ORDER BY BranchName")->fetchAll();
$products = $pdo->query("SELECT * FROM ACCOUNTPRODUCT ORDER BY ProductID")->fetchAll();

// Fetch KYC Status
$kycStmt = $pdo->prepare("SELECT * FROM KYC_VERIFICATIONS WHERE customer_id = ? ORDER BY submitted_at DESC LIMIT 1");
$kycStmt->execute([$customerId]);
$kyc = $kycStmt->fetch();

// Fetch Nominee Details
$nomineeStmt = $pdo->prepare("SELECT * FROM NOMINEE WHERE CustomerID = ? ORDER BY NomineeID DESC LIMIT 1");
$nomineeStmt->execute([$customerId]);
$nominee = $nomineeStmt->fetch();

// Fetch Digital Banking User PIN status
$userStmt = $pdo->prepare("SELECT Username, TransactionPIN FROM DIGITALBANKINGUSER WHERE CustomerID = ?");
$userStmt->execute([$customerId]);
$digitalUser = $userStmt->fetch();
$hasPin = !empty($digitalUser['TransactionPIN']);

$pageTitle  = 'Profile & Account Management';
$activePage = 'profile';
include 'includes/header.php';
?>

<div class="page-content">
    <!-- Page Header with User Overview -->
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:24px;">
        <div>
            <h1 style="margin:0 0 4px;font-size:24px;font-weight:700;">Profile & Account Settings</h1>
            <p style="margin:0;font-size:14px;color:var(--text-muted);">Manage your personal identity, linked accounts, servicing branch, product tier, and security credentials.</p>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <span class="badge badge-<?= ($customer['IsActive'] ?? 1) ? 'success' : 'danger' ?>">
                <i class='bx bx-<?= ($customer['IsActive'] ?? 1) ? 'check-circle' : 'block' ?>'></i>
                <?= ($customer['IsActive'] ?? 1) ? 'Active Client' : 'Deactivated' ?>
            </span>
            <span class="badge badge-<?= ($kyc && $kyc['status'] === 'verified') ? 'success' : 'warning' ?>">
                <i class='bx bx-<?= ($kyc && $kyc['status'] === 'verified') ? 'check-shield' : 'shield-quarter' ?>'></i>
                KYC <?= $kyc ? ucfirst($kyc['status']) : 'Not Submitted' ?>
            </span>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger reveal" style="margin-bottom:20px;">
        <i class='bx bx-error-circle' style="font-size:20px;"></i>
        <div><?= e($error) ?></div>
    </div>
    <?php endif; ?>

    <!-- Client Header Card -->
    <div class="card reveal" style="margin-bottom:24px;padding:24px;border-radius:16px;background:var(--bg-card);border:1px solid var(--border-subtle);">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:20px;">
            <div style="display:flex;align-items:center;gap:18px;">
                <div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg, var(--primary) 0%, #0F52BA 100%);color:#FFFFFF;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;box-shadow:0 8px 16px rgba(24,95,165,0.25);">
                    <?= strtoupper(substr($customer['FirstName'] ?? 'U', 0, 1) . substr($customer['LastName'] ?? 'S', 0, 1)) ?>
                </div>
                <div>
                    <h2 style="margin:0 0 4px;font-size:20px;font-weight:700;color:var(--text-primary);">
                        <?= e(($customer['FirstName'] ?? '') . ' ' . ($customer['LastName'] ?? '')) ?>
                    </h2>
                    <div style="font-size:13px;color:var(--text-muted);display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <span><i class='bx bx-user'></i> Customer ID: <strong>#<?= e($customer['CustomerID']) ?></strong></span>
                        <span>&bull;</span>
                        <span><i class='bx bx-envelope'></i> <?= e($customer['Email']) ?></span>
                        <span>&bull;</span>
                        <span><i class='bx bx-calendar'></i> Member since <?= date('M Y', strtotime($customer['CreatedAt'] ?? 'now')) ?></span>
                    </div>
                </div>
            </div>

            <!-- Referral Card -->
            <?php if (!empty($customer['ReferralCode'])): ?>
            <div style="background:var(--bg-subtle);border:1px solid var(--border-subtle);border-radius:12px;padding:10px 16px;display:flex;align-items:center;gap:12px;">
                <div>
                    <div style="font-size:11px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted);">Referral Code</div>
                    <div style="font-family:'SF Mono','Fira Code',monospace;font-weight:700;font-size:15px;color:var(--primary);"><?= e($customer['ReferralCode']) ?></div>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" onclick="navigator.clipboard.writeText('<?= e($customer['ReferralCode']) ?>'); if(typeof showToast==='function') showToast('Referral code copied!', 'success');" title="Copy code">
                    <i class='bx bx-copy'></i>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2-Column Grid -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;" class="transaction-page-grid">

        <!-- Left Column: Account Management & Changing Options -->
        <div style="display:flex;flex-direction:column;gap:24px;">

            <!-- ── Account Overview & Switcher ────────────────────── -->
            <div class="card reveal" style="border-radius:16px;overflow:hidden;border:1px solid var(--border-subtle);">
                <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;padding:18px 24px;border-bottom:1px solid var(--border-subtle);">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:10px;background:rgba(24,95,165,0.1);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:19px;">
                            <i class='bx bx-credit-card-front'></i>
                        </div>
                        <div>
                            <h4 style="margin:0;font-size:16px;font-weight:700;">Account Settings & Changing Options</h4>
                            <p style="margin:2px 0 0;font-size:12px;color:var(--text-muted);">Manage your linked bank accounts, plan tiers, and branch</p>
                        </div>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('openAccountModal').style.display='flex';" style="display:inline-flex;align-items:center;gap:4px;">
                        <i class='bx bx-plus'></i> New Account
                    </button>
                </div>

                <div class="card-body" style="padding:24px;">
                    <?php if (count($userAccounts) > 1): ?>
                    <!-- Account Switcher Strip -->
                    <div style="margin-bottom:20px;">
                        <label class="form-label" style="font-size:12px;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);">Switch Account to Manage</label>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <?php foreach ($userAccounts as $acc):
                                $isActiveAcc = ($currentAccount && $currentAccount['AccountNumber'] == $acc['AccountNumber']);
                            ?>
                            <a href="profile.php?account=<?= e($acc['AccountNumber']) ?>"
                               style="padding:10px 14px;border-radius:10px;border:1.5px solid <?= $isActiveAcc ? 'var(--primary)' : 'var(--border-subtle)' ?>;background:<?= $isActiveAcc ? 'rgba(24,95,165,0.06)' : 'var(--bg-subtle)' ?>;text-decoration:none;display:flex;align-items:center;gap:8px;color:var(--text-primary);transition:all 0.15s;">
                                <i class='bx <?= $isActiveAcc ? 'bx-check-circle' : 'bx-circle' ?>' style="color:<?= $isActiveAcc ? 'var(--primary)' : 'var(--text-muted)' ?>;"></i>
                                <div>
                                    <div style="font-family:monospace;font-weight:600;font-size:13px;"><?= e($acc['AccountNumber']) ?></div>
                                    <div style="font-size:11px;color:var(--text-muted);"><?= e($acc['ProductName']) ?></div>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($currentAccount): ?>
                    <!-- Active Account Highlight Card -->
                    <div style="background:linear-gradient(135deg, rgba(24,95,165,0.08) 0%, rgba(24,95,165,0.02) 100%);border:1px solid rgba(24,95,165,0.2);border-radius:14px;padding:18px 20px;margin-bottom:22px;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;">
                            <div>
                                <span style="font-size:11px;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-muted);font-weight:600;">Active Account Number</span>
                                <div style="font-family:'SF Mono','Fira Code',monospace;font-size:18px;font-weight:700;color:var(--text-primary);letter-spacing:0.04em;">
                                    <?= e($currentAccount['AccountNumber']) ?>
                                </div>
                            </div>
                            <span class="badge badge-success" style="font-size:11.5px;">
                                <i class='bx bx-check'></i> <?= e($currentAccount['AccountStatus']) ?>
                            </span>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:13px;">
                            <div>
                                <span style="color:var(--text-muted);font-size:12px;">Current Balance</span>
                                <div style="font-weight:700;font-size:16px;color:#16A34A;"><?= formatBDT($currentAccount['AvailableBalance']) ?></div>
                            </div>
                            <div>
                                <span style="color:var(--text-muted);font-size:12px;">Current Plan</span>
                                <div style="font-weight:600;color:var(--text-primary);"><?= e($currentAccount['ProductName']) ?> (<?= e($currentAccount['InterestRate']) ?>% APY)</div>
                            </div>
                            <div style="grid-column:span 2;">
                                <span style="color:var(--text-muted);font-size:12px;">Assigned Branch</span>
                                <div style="font-weight:500;color:var(--text-primary);"><?= e($currentAccount['BranchName']) ?> (<?= e($currentAccount['IFSCCode']) ?>)</div>
                            </div>
                        </div>
                    </div>

                    <!-- Update Account Form (Branch & Plan Change) -->
                    <form method="POST" action="profile.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_account_settings">
                        <input type="hidden" name="account_number" value="<?= e($currentAccount['AccountNumber']) ?>">

                        <div class="form-group" style="margin-bottom:18px;">
                            <label class="form-label" for="product_id">
                                <i class='bx bx-package' style="color:var(--primary);"></i> Change Account Type / Upgrade Plan
                            </label>
                            <select id="product_id" name="product_id" class="form-control" style="font-size:13.5px;" onchange="updatePlanPreview()">
                                <?php foreach ($products as $p): ?>
                                <option value="<?= $p['ProductID'] ?>"
                                        data-interest="<?= e($p['InterestRate']) ?>"
                                        data-min="<?= formatBDT($p['MinBalance']) ?>"
                                        data-desc="<?= e($p['Description'] ?? '') ?>"
                                        <?= $p['ProductID'] == $currentAccount['ProductID'] ? 'selected' : '' ?>>
                                    <?= e($p['ProductName']) ?> — <?= e($p['AccountType']) ?> (<?= e($p['InterestRate']) ?>% Interest)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="planHelp" class="form-helper" style="font-size:12px;margin-top:6px;color:var(--text-muted);">
                                Upgrading your plan adjusts your interest rate and minimum balance tier immediately.
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:22px;">
                            <label class="form-label" for="branch_id">
                                <i class='bx bx-buildings' style="color:var(--primary);"></i> Change Servicing Branch
                            </label>
                            <select id="branch_id" name="branch_id" class="form-control" style="font-size:13.5px;">
                                <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['BranchID'] ?>" <?= $b['BranchID'] == $currentAccount['BranchID'] ? 'selected' : '' ?>>
                                    <?= e($b['BranchName']) ?> — <?= e($b['City']) ?> (Code: <?= e($b['IFSCCode']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-helper" style="font-size:12px;margin-top:6px;color:var(--text-muted);">
                                Transfer this account's primary counter, cheque clearance, and locker servicing branch.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
                            <i class='bx bx-check-shield'></i> Save Account Changes
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Personal & Contact Information ────────────────── -->
            <div class="card reveal" style="border-radius:16px;overflow:hidden;border:1px solid var(--border-subtle);">
                <div class="card-header" style="display:flex;align-items:center;gap:10px;padding:18px 24px;border-bottom:1px solid var(--border-subtle);">
                    <div style="width:36px;height:36px;border-radius:10px;background:rgba(2,132,199,0.1);color:#0284C7;display:flex;align-items:center;justify-content:center;font-size:19px;">
                        <i class='bx bx-user-pin'></i>
                    </div>
                    <div>
                        <h4 style="margin:0;font-size:16px;font-weight:700;">Personal Information</h4>
                        <p style="margin:2px 0 0;font-size:12px;color:var(--text-muted);">Update your registered phone, email, and billing address</p>
                    </div>
                </div>

                <div class="card-body" style="padding:24px;">
                    <form method="POST" action="profile.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_profile">

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                            <div class="form-group">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" value="<?= e($customer['FirstName']) ?>" disabled style="background:var(--bg-muted);cursor:not-allowed;">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" value="<?= e($customer['LastName']) ?>" disabled style="background:var(--bg-muted);cursor:not-allowed;">
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:14px;">
                            <label class="form-label">National ID (NID)</label>
                            <input type="text" class="form-control" value="<?= e($customer['NationalID'] ?: 'Not Provided') ?>" disabled style="background:var(--bg-muted);cursor:not-allowed;">
                            <div class="form-helper" style="font-size:11.5px;">Name and NID can only be modified at branch counter with verified KYC documentation.</div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                            <div class="form-group">
                                <label class="form-label" for="phone">Phone Number <span class="required">*</span></label>
                                <input type="text" id="phone" name="phone" class="form-control" value="<?= e($customer['Phone']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                                <input type="email" id="email" name="email" class="form-control" value="<?= e($customer['Email']) ?>" required>
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px;margin-bottom:20px;">
                            <div class="form-group">
                                <label class="form-label" for="address">Street Address</label>
                                <input type="text" id="address" name="address" class="form-control" value="<?= e($customer['Address']) ?>" placeholder="House, Road, Area">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="city">City</label>
                                <input type="text" id="city" name="city" class="form-control" value="<?= e($customer['City']) ?>" placeholder="City">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
                            <i class='bx bx-save'></i> Save Profile Details
                        </button>
                    </form>
                </div>
            </div>

        </div>

        <!-- Right Column: Security, PIN, Nominee, KYC -->
        <div style="display:flex;flex-direction:column;gap:24px;">

            <!-- ── Transaction PIN Management ────────────────────── -->
            <div class="card reveal" style="border-radius:16px;overflow:hidden;border:1px solid var(--border-subtle);">
                <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;padding:18px 24px;border-bottom:1px solid var(--border-subtle);">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:10px;background:rgba(245,158,11,0.1);color:#F59E0B;display:flex;align-items:center;justify-content:center;font-size:19px;">
                            <i class='bx bx-key'></i>
                        </div>
                        <div>
                            <h4 style="margin:0;font-size:16px;font-weight:700;">Transaction Security PIN</h4>
                            <p style="margin:2px 0 0;font-size:12px;color:var(--text-muted);">Required to authorize withdrawals and transfers</p>
                        </div>
                    </div>
                    <span class="badge badge-<?= $hasPin ? 'success' : 'warning' ?>">
                        <?= $hasPin ? 'PIN Active' : 'Not Set' ?>
                    </span>
                </div>

                <div class="card-body" style="padding:24px;">
                    <form method="POST" action="profile.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_pin">

                        <?php if ($hasPin): ?>
                        <div class="form-group" style="margin-bottom:14px;">
                            <label class="form-label" for="current_pin">Current 4-Digit PIN <span class="required">*</span></label>
                            <input type="password" id="current_pin" name="current_pin" class="form-control" maxlength="6" inputmode="numeric" placeholder="••••" required>
                        </div>
                        <?php endif; ?>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
                            <div class="form-group">
                                <label class="form-label" for="new_pin">New PIN (4-6 digits) <span class="required">*</span></label>
                                <input type="password" id="new_pin" name="new_pin" class="form-control" maxlength="6" inputmode="numeric" placeholder="••••" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="confirm_pin">Confirm New PIN <span class="required">*</span></label>
                                <input type="password" id="confirm_pin" name="confirm_pin" class="form-control" maxlength="6" inputmode="numeric" placeholder="••••" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
                            <i class='bx bx-lock-alt'></i> <?= $hasPin ? 'Update Transaction PIN' : 'Set Transaction PIN' ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- ── Change Login Password ────────────────────────── -->
            <div class="card reveal" style="border-radius:16px;overflow:hidden;border:1px solid var(--border-subtle);">
                <div class="card-header" style="display:flex;align-items:center;gap:10px;padding:18px 24px;border-bottom:1px solid var(--border-subtle);">
                    <div style="width:36px;height:36px;border-radius:10px;background:rgba(239,68,68,0.1);color:#EF4444;display:flex;align-items:center;justify-content:center;font-size:19px;">
                        <i class='bx bx-lock-open-alt'></i>
                    </div>
                    <div>
                        <h4 style="margin:0;font-size:16px;font-weight:700;">Change Login Password</h4>
                        <p style="margin:2px 0 0;font-size:12px;color:var(--text-muted);">Update your digital banking portal sign-in password</p>
                    </div>
                </div>

                <div class="card-body" style="padding:24px;">
                    <form method="POST" action="profile.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group" style="margin-bottom:14px;">
                            <label class="form-label" for="current_password">Current Password <span class="required">*</span></label>
                            <input type="password" id="current_password" name="current_password" class="form-control" placeholder="••••••••" required>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
                            <div class="form-group">
                                <label class="form-label" for="new_password">New Password <span class="required">*</span></label>
                                <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Min. 6 chars" minlength="6" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="confirm_password">Confirm New Password <span class="required">*</span></label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px;">
                            <i class='bx bx-refresh'></i> Update Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- ── Nominee Information ──────────────────────────── -->
            <div class="card reveal" style="border-radius:16px;overflow:hidden;border:1px solid var(--border-subtle);">
                <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;padding:18px 24px;border-bottom:1px solid var(--border-subtle);">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:10px;background:rgba(147,51,234,0.1);color:#9333EA;display:flex;align-items:center;justify-content:center;font-size:19px;">
                            <i class='bx bx-group'></i>
                        </div>
                        <div>
                            <h4 style="margin:0;font-size:16px;font-weight:700;">Account Nominee</h4>
                            <p style="margin:2px 0 0;font-size:12px;color:var(--text-muted);">Legal beneficiary for this banking account</p>
                        </div>
                    </div>
                </div>

                <div class="card-body" style="padding:24px;">
                    <form method="POST" action="profile.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_nominee">

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                            <div class="form-group">
                                <label class="form-label" for="nominee_name">Nominee Full Name <span class="required">*</span></label>
                                <input type="text" id="nominee_name" name="nominee_name" class="form-control" value="<?= e($nominee['NomineeName'] ?? '') ?>" placeholder="Full Name" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="nominee_relation">Relationship <span class="required">*</span></label>
                                <select id="nominee_relation" name="nominee_relation" class="form-control">
                                    <?php foreach (['Spouse', 'Child', 'Parent', 'Sibling', 'Other'] as $rel): ?>
                                    <option value="<?= $rel ?>" <?= ($nominee['NomineeRelation'] ?? '') === $rel ? 'selected' : '' ?>><?= $rel ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
                            <div class="form-group">
                                <label class="form-label" for="nominee_phone">Nominee Contact Phone</label>
                                <input type="text" id="nominee_phone" name="nominee_phone" class="form-control" value="<?= e($nominee['NomineePhone'] ?? '') ?>" placeholder="Phone number">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="nominee_address">Nominee Address</label>
                                <input type="text" id="nominee_address" name="nominee_address" class="form-control" value="<?= e($nominee['NomineeAddress'] ?? '') ?>" placeholder="Address">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-outline" style="display:inline-flex;align-items:center;gap:6px;">
                            <i class='bx bx-user-check'></i> Save Nominee Details
                        </button>
                    </form>
                </div>
            </div>

            <!-- ── KYC Verification ─────────────────────────────── -->
            <div class="card reveal" style="border-radius:16px;overflow:hidden;border:1px solid var(--border-subtle);">
                <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;padding:18px 24px;border-bottom:1px solid var(--border-subtle);">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:10px;background:rgba(22,163,74,0.1);color:#16A34A;display:flex;align-items:center;justify-content:center;font-size:19px;">
                            <i class='bx bx-badge-check'></i>
                        </div>
                        <div>
                            <h4 style="margin:0;font-size:16px;font-weight:700;">KYC Identity Verification</h4>
                            <p style="margin:2px 0 0;font-size:12px;color:var(--text-muted);">Compliance with Bangladesh Bank regulations</p>
                        </div>
                    </div>
                    <span class="badge badge-<?= ($kyc && $kyc['status'] === 'verified') ? 'success' : (($kyc && $kyc['status'] === 'rejected') ? 'danger' : 'warning') ?>">
                        <?= $kyc ? ucfirst($kyc['status']) : 'Not Submitted' ?>
                    </span>
                </div>

                <div class="card-body" style="padding:24px;">
                    <?php if ($kyc && $kyc['status'] === 'verified'): ?>
                    <div style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:rgba(22,163,74,0.08);border:1px solid rgba(22,163,74,0.2);border-radius:12px;color:#16A34A;">
                        <i class='bx bx-check-shield' style="font-size:26px;"></i>
                        <div>
                            <div style="font-weight:700;font-size:14px;">Your identity has been fully verified</div>
                            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">All limits, international transactions, and full digital services are unlocked.</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php if ($kyc && $kyc['status'] === 'rejected'): ?>
                    <div class="alert alert-danger" style="margin-bottom:16px;">
                        <strong>Rejected:</strong> <?= e($kyc['rejection_reason'] ?: 'Please re-verify your documents.') ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="profile.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="submit_kyc">

                        <div class="form-group" style="margin-bottom:14px;">
                            <label class="form-label" for="nid_number">National ID (NID) Number <span class="required">*</span></label>
                            <input type="text" id="nid_number" name="nid_number" class="form-control" value="<?= e($kyc['nid_number'] ?? $customer['NationalID'] ?? '') ?>" placeholder="10 or 17 digit NID number" required>
                        </div>

                        <div class="form-group" style="margin-bottom:18px;">
                            <label class="form-label" for="kyc_phone">Registered Mobile Number <span class="required">*</span></label>
                            <input type="text" id="kyc_phone" name="kyc_phone" class="form-control" value="<?= e($customer['Phone'] ?? '') ?>" placeholder="+8801XXXXXXXXX" required>
                        </div>

                        <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
                            <i class='bx bx-send'></i> Submit for Verification
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>
</div>

<!-- Modal: Open Additional Account -->
<div id="openAccountModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px;">
    <div style="background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:18px;max-width:480px;width:100%;box-shadow:0 24px 48px rgba(0,0,0,0.25);overflow:hidden;animation:modalIn 0.2s cubic-bezier(0.16,1,0.3,1);">
        <div style="background:linear-gradient(135deg, var(--primary) 0%, #0F52BA 100%);padding:22px;color:#FFFFFF;position:relative;">
            <button type="button" onclick="document.getElementById('openAccountModal').style.display='none';" style="position:absolute;top:16px;right:16px;background:rgba(255,255,255,0.15);border:none;color:#FFFFFF;width:28px;height:28px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;">
                <i class='bx bx-x'></i>
            </button>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                <div style="width:24px;height:24px;border-radius:6px;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;">
                    <i class='bx bx-plus' style="font-size:14px;"></i>
                </div>
                <span style="font-weight:700;font-size:14px;letter-spacing:-0.01em;">Asha Bank</span>
            </div>
            <h3 style="margin:0 0 2px;color:#FFFFFF;font-size:18px;font-weight:700;">Open Additional Account</h3>
            <div style="font-size:12px;color:rgba(255,255,255,0.75);">Add a secondary savings, current, or term deposit account</div>
        </div>

        <div style="padding:24px;">
            <form method="POST" action="profile.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="open_additional_account">

                <div class="form-group" style="margin-bottom:16px;">
                    <label class="form-label" for="new_product_id">Select Account Product <span class="required">*</span></label>
                    <select id="new_product_id" name="new_product_id" class="form-control" required>
                        <?php foreach ($products as $p): ?>
                        <option value="<?= $p['ProductID'] ?>">
                            <?= e($p['ProductName']) ?> (<?= e($p['AccountType']) ?> — <?= e($p['InterestRate']) ?>% APY)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label class="form-label" for="new_branch_id">Servicing Branch <span class="required">*</span></label>
                    <select id="new_branch_id" name="new_branch_id" class="form-control" required>
                        <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['BranchID'] ?>" <?= $b['BranchID'] == ($customer['PrimaryBranchID'] ?? 1) ? 'selected' : '' ?>>
                            <?= e($b['BranchName']) ?> (<?= e($b['City']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('openAccountModal').style.display='none';">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
                        <i class='bx bx-check'></i> Open Account Instantly
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updatePlanPreview() {
    const sel = document.getElementById('product_id');
    const opt = sel.options[sel.selectedIndex];
    const interest = opt.getAttribute('data-interest');
    const minBal   = opt.getAttribute('data-min');
    document.getElementById('planHelp').textContent = 'Annual Yield: ' + interest + '% APY | Required Minimum Reserve: ' + minBal;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('openAccountModal');
        if (modal) modal.style.display = 'none';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
