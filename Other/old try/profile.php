<?php
require_once 'config/db.php';
requireLogin();
$pageTitle = 'Profile';

$success = $error = '';
$user = $conn->query("SELECT * FROM users WHERE id=" . $_SESSION['user_id'])->fetch_assoc();

// UPDATE PROFILE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'update') {
    $name  = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $conn->query("UPDATE users SET full_name='$name', email='$email' WHERE id=" . $_SESSION['user_id']);
    $_SESSION['full_name'] = $name;
    $_SESSION['email']     = $email;
    $success = "Profile updated successfully!";
    $user = $conn->query("SELECT * FROM users WHERE id=" . $_SESSION['user_id'])->fetch_assoc();
}

// CHANGE PASSWORD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'password') {
    $current = $_POST['current_password'];
    $new     = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if (!password_verify($current, $user['password'])) {
        $error = "Current password is incorrect.";
    } elseif ($new !== $confirm) {
        $error = "New passwords do not match.";
    } elseif (strlen($new) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password='$hash' WHERE id=" . $_SESSION['user_id']);
        $success = "Password changed successfully!";
    }
}

// ADD STAFF (admin only)
if ($_SESSION['role'] === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_staff') {
    $sname  = $conn->real_escape_string($_POST['staff_name']);
    $semail = $conn->real_escape_string($_POST['staff_email']);
    $spass  = password_hash($_POST['staff_password'], PASSWORD_DEFAULT);
    $srole  = $conn->real_escape_string($_POST['staff_role']);
    if ($conn->query("INSERT INTO users (full_name, email, password, role) VALUES ('$sname','$semail','$spass','$srole')")) {
        $success = "Staff member $sname added!";
    } else {
        $error = "Error: " . $conn->error;
    }
}

$staffList = $conn->query("SELECT * FROM users ORDER BY id");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile – Apex Bank</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="page-header">
    <h2>⚙ Profile & Settings</h2>
    <p>Manage your account settings and staff access.</p>
</div>

<?php if ($success): ?><div class="alert alert-success">✔ <?php echo $success; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger">⚠ <?php echo $error; ?></div><?php endif; ?>

<div class="grid-2">
    <!-- UPDATE PROFILE -->
    <div class="card">
        <div class="card-header"><h4>👤 My Profile</h4></div>
        <div class="card-body">
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;padding-bottom:20px;border-bottom:var(--border-gold);">
                <div class="user-avatar" style="width:60px;height:60px;font-size:1.4rem;">
                    <?php echo strtoupper(substr($user['full_name'],0,1)); ?>
                </div>
                <div>
                    <h4 style="color:#D4AF37;"><?php echo htmlspecialchars($user['full_name']); ?></h4>
                    <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                    <span class="badge badge-gold"><?php echo ucfirst($user['role']); ?></span>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" readonly style="opacity:0.5;">
                </div>
                <button type="submit" class="btn btn-gold">Update Profile</button>
            </form>
        </div>
    </div>

    <!-- CHANGE PASSWORD -->
    <div class="card">
        <div class="card-header"><h4>🔒 Change Password</h4></div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="password">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" class="form-control" minlength="6" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-gold">Change Password</button>
            </form>
        </div>
    </div>
</div>

<?php if ($_SESSION['role'] === 'admin'): ?>
<!-- STAFF MANAGEMENT -->
<div class="card">
    <div class="card-header">
        <h4>👥 Staff Management</h4>
        <button class="btn btn-gold btn-sm" onclick="openModal('addStaffModal')">+ Add Staff</button>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th></tr>
            </thead>
            <tbody>
                <?php $i=1; while ($s = $staffList->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="user-avatar" style="width:32px;height:32px;font-size:0.75rem;">
                                <?php echo strtoupper(substr($s['full_name'],0,1)); ?>
                            </div>
                            <?php echo htmlspecialchars($s['full_name']); ?>
                            <?php echo $s['id'] == $_SESSION['user_id'] ? '<span class="badge badge-gold" style="margin-left:6px;">You</span>' : ''; ?>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($s['email']); ?></td>
                    <td><span class="badge badge-gold"><?php echo ucfirst($s['role']); ?></span></td>
                    <td><span class="badge badge-<?php echo $s['status']==='active'?'success':'danger'; ?>"><?php echo $s['status']; ?></span></td>
                    <td><?php echo date('d M Y', strtotime($s['created_at'])); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD STAFF MODAL -->
<div class="modal-overlay" id="addStaffModal">
    <div class="modal">
        <div class="modal-header">
            <h4>➕ Add Staff Member</h4>
            <button class="modal-close" onclick="closeModal('addStaffModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_staff">
            <div class="modal-body">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="staff_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email Address *</label>
                    <input type="email" name="staff_email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="staff_password" class="form-control" minlength="6" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="staff_role" class="form-control">
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addStaffModal')">Cancel</button>
                <button type="submit" class="btn btn-gold">Add Staff</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

    </div>
</div>
</div>
<script src="js/main.js"></script>
</body>
</html>