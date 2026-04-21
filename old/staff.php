<?php
require_once 'config/db.php';
requireLogin();
requireAdmin(); // redirects if not admin/staff

// ONLY ADMIN can manage staff, not regular staff
if ($_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php?error=admin_only");
    exit();
}

$pageTitle = 'Staff Management';
$success = $error = '';

// ── ADD STAFF ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name   = trim($conn->real_escape_string($_POST['full_name']));
    $email  = trim($conn->real_escape_string($_POST['email']));
    $role   = in_array($_POST['role'], ['admin','staff']) ? $_POST['role'] : 'staff';
    $pw     = $_POST['password'];
    $cpw    = $_POST['confirm_password'];

    if (empty($name) || empty($email) || empty($pw)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($pw) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($pw !== $cpw) {
        $error = "Passwords do not match.";
    } else {
        $check = $conn->query("SELECT id FROM users WHERE email='$email'");
        if ($check->num_rows > 0) {
            $error = "A user with this email already exists.";
        } else {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            if ($conn->query("INSERT INTO users (full_name, email, password, role) VALUES ('$name','$email','$hash','$role')")) {
                $success = "Staff member <strong>$name</strong> added as <strong>" . ucfirst($role) . "</strong> successfully.";
            } else {
                $error = "Error: " . $conn->error;
            }
        }
    }
}

// ── EDIT STAFF ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $eid    = intval($_POST['edit_id']);
    $name   = trim($conn->real_escape_string($_POST['full_name']));
    $email  = trim($conn->real_escape_string($_POST['email']));
    $role   = in_array($_POST['role'], ['admin','staff']) ? $_POST['role'] : 'staff';
    $status = in_array($_POST['status'], ['active','inactive']) ? $_POST['status'] : 'active';

    // Prevent demoting yourself
    if ($eid === intval($_SESSION['user_id']) && $role !== 'admin') {
        $error = "You cannot change your own role.";
    } else {
        $conn->query("UPDATE users SET full_name='$name', email='$email', role='$role', status='$status' WHERE id=$eid AND role != 'customer'");
        $success = "Staff member updated successfully.";

        // Optional: reset password
        if (!empty($_POST['new_password'])) {
            if (strlen($_POST['new_password']) < 6) {
                $error = "New password must be at least 6 characters.";
            } else {
                $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $conn->query("UPDATE users SET password='$hash' WHERE id=$eid");
                $success .= " Password also reset.";
            }
        }
    }
}

// ── DELETE STAFF ─────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $del = intval($_GET['delete']);
    if ($del === intval($_SESSION['user_id'])) {
        $error = "You cannot delete your own account.";
    } else {
        $conn->query("DELETE FROM users WHERE id=$del AND role != 'customer'");
        $success = "Staff member removed.";
    }
}

// ── TOGGLE STATUS ─────────────────────────────────────────────────────────────
if (isset($_GET['toggle'])) {
    $tog = intval($_GET['toggle']);
    if ($tog === intval($_SESSION['user_id'])) {
        $error = "You cannot deactivate your own account.";
    } else {
        $cur = $conn->query("SELECT status FROM users WHERE id=$tog")->fetch_assoc()['status'] ?? 'active';
        $new = $cur === 'active' ? 'inactive' : 'active';
        $conn->query("UPDATE users SET status='$new' WHERE id=$tog AND role != 'customer'");
        $success = "Staff status updated to '$new'.";
    }
}

// ── FETCH STAFF ───────────────────────────────────────────────────────────────
$search = '';
$where  = "WHERE role IN ('admin','staff')";
if (!empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $where .= " AND (full_name LIKE '%$search%' OR email LIKE '%$search%')";
}

$staffList = $conn->query("SELECT * FROM users $where ORDER BY role ASC, created_at DESC");

// Stats
$totalAdmins = $conn->query("SELECT COUNT(*) c FROM users WHERE role='admin'")->fetch_assoc()['c'];
$totalStaff  = $conn->query("SELECT COUNT(*) c FROM users WHERE role='staff'")->fetch_assoc()['c'];
$activeStaff = $conn->query("SELECT COUNT(*) c FROM users WHERE role IN ('admin','staff') AND status='active'")->fetch_assoc()['c'];

// Edit fetch
$editUser = null;
if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    $editUser = $conn->query("SELECT * FROM users WHERE id=$eid AND role != 'customer'")->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management – Apex Bank</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Extra styles specific to this page */
        .role-tag {
            display:inline-flex; align-items:center; gap:5px;
            padding:4px 10px; border-radius:20px;
            font-size:0.68rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em;
        }

        .role-admin {
            background:rgba(201,168,76,0.18); color:var(--gold);
            border:1px solid rgba(201,168,76,0.3);
        }

        .role-staff {
            background:rgba(52,152,219,0.12); color:#3498DB;
            border:1px solid rgba(52,152,219,0.25);
        }

        .permission-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }

        .permission-card {
            background:var(--black-3);
            border:1px solid rgba(201,168,76,0.12);
            border-radius:10px; padding:18px;
        }

        .permission-card h5 {
            font-size:0.82rem; color:var(--white); margin-bottom:12px;
            display:flex; align-items:center; gap:8px;
        }

        .perm-item {
            display:flex; align-items:center; gap:8px;
            font-size:0.75rem; padding:5px 0;
            border-bottom:1px solid rgba(255,255,255,0.04);
        }

        .perm-item:last-child { border:none; }
        .perm-item .yes { color:var(--success); }
        .perm-item .no  { color:var(--danger); opacity:0.6; }
        .perm-item .label { color:var(--white-dim); }

        .staff-avatar {
            width:38px; height:38px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-weight:700; font-size:0.9rem; color:var(--black);
            flex-shrink:0;
        }

        .staff-avatar.admin-av { background:linear-gradient(135deg,var(--gold-dark),var(--gold)); }
        .staff-avatar.staff-av { background:linear-gradient(135deg,#2980b9,#3498DB); }
    </style>
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
    <div>
        <h2>👑 Staff Management</h2>
        <p>Create, assign roles and manage all staff members. <span style="color:rgba(231,76,60,0.8);font-size:0.8rem;">Admin only area.</span></p>
    </div>
    <button class="btn btn-gold" onclick="openModal('addStaffModal')">+ Add Staff Member</button>
</div>

<?php if ($success): ?><div class="alert alert-success">✔ <?php echo $success; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger">⚠ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<!-- STAT CARDS -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:28px;">
    <div class="stat-card">
        <div class="stat-icon gold">👑</div>
        <div class="stat-value"><?php echo $totalAdmins; ?></div>
        <div class="stat-label">Admins</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">👤</div>
        <div class="stat-value"><?php echo $totalStaff; ?></div>
        <div class="stat-label">Staff Members</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">✔</div>
        <div class="stat-value"><?php echo $activeStaff; ?></div>
        <div class="stat-label">Currently Active</div>
    </div>
</div>

<!-- PERMISSION REFERENCE -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <h4>🔐 Role Permissions Reference</h4>
        <span class="text-muted">What each role can do</span>
    </div>
    <div class="card-body">
        <div class="permission-grid">
            <div class="permission-card">
                <h5><span style="color:var(--gold);">👑</span> Admin Role</h5>
                <div class="perm-item"><span class="yes">✓</span><span class="label">Manage staff (add/edit/delete)</span></div>
                <div class="perm-item"><span class="yes">✓</span><span class="label">Manage all customers & accounts</span></div>
                <div class="perm-item"><span class="yes">✓</span><span class="label">Approve / reject loans</span></div>
                <div class="perm-item"><span class="yes">✓</span><span class="label">Freeze / unfreeze accounts</span></div>
                <div class="perm-item"><span class="yes">✓</span><span class="label">View all reports & analytics</span></div>
                <div class="perm-item"><span class="yes">✓</span><span class="label">Delete customers & accounts</span></div>
            </div>
            <div class="permission-card">
                <h5><span style="color:#3498DB;">👤</span> Staff Role</h5>
                <div class="perm-item"><span class="no">✗</span><span class="label">Cannot manage other staff</span></div>
                <div class="perm-item"><span class="yes">✓</span><span class="label">Add / view customers</span></div>
                <div class="perm-item"><span class="yes">✓</span><span class="label">Process deposits & withdrawals</span></div>
                <div class="perm-item"><span class="yes">✓</span><span class="label">Approve / reject loans</span></div>
                <div class="perm-item"><span class="yes">✓</span><span class="label">Open new accounts</span></div>
                <div class="perm-item"><span class="no">✗</span><span class="label">Cannot delete records</span></div>
            </div>
        </div>
    </div>
</div>

<!-- SEARCH + TABLE -->
<div class="filter-bar">
    <form method="GET" style="display:flex;gap:12px;flex:1;flex-wrap:wrap;">
        <div class="search-box" style="flex:1;">
            <span class="search-icon">🔍</span>
            <input type="text" name="search" placeholder="Search by name or email..."
                   value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button type="submit" class="btn btn-outline">Search</button>
        <?php if ($search): ?><a href="staff.php" class="btn btn-outline">✕ Clear</a><?php endif; ?>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h4>All Staff Members</h4>
        <span class="text-muted"><?php echo $staffList->num_rows; ?> member(s)</span>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Staff Member</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($staffList->num_rows > 0):
                    $i = 1;
                    while ($s = $staffList->fetch_assoc()):
                        $isMe = $s['id'] == $_SESSION['user_id'];
                ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div class="staff-avatar <?php echo $s['role']==='admin'?'admin-av':'staff-av'; ?>">
                                <?php echo strtoupper(substr($s['full_name'],0,1)); ?>
                            </div>
                            <div>
                                <div style="font-size:0.88rem;color:var(--white);">
                                    <?php echo htmlspecialchars($s['full_name']); ?>
                                    <?php if ($isMe): ?>
                                    <span class="badge badge-gold" style="margin-left:6px;font-size:0.62rem;">You</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:0.72rem;color:#666;">ID #<?php echo $s['id']; ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:0.83rem;"><?php echo htmlspecialchars($s['email']); ?></td>
                    <td>
                        <span class="role-tag <?php echo $s['role']==='admin'?'role-admin':'role-staff'; ?>">
                            <?php echo $s['role']==='admin'?'👑':'👤'; ?>
                            <?php echo ucfirst($s['role']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo $s['status']==='active'?'success':'danger'; ?>">
                            <?php echo $s['status']; ?>
                        </span>
                    </td>
                    <td style="font-size:0.8rem;"><?php echo date('d M Y', strtotime($s['created_at'])); ?></td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <a href="?edit=<?php echo $s['id']; ?>" class="btn btn-outline btn-sm" title="Edit">✏ Edit</a>
                            <?php if (!$isMe): ?>
                            <a href="?toggle=<?php echo $s['id']; ?>" class="btn btn-outline btn-sm"
                               title="Toggle status"
                               onclick="return confirm('Toggle status for this staff member?')">
                                <?php echo $s['status']==='active'?'🚫':'✔'; ?>
                            </a>
                            <a href="?delete=<?php echo $s['id']; ?>" class="btn btn-danger btn-sm btn-delete-confirm"
                               title="Remove staff">🗑</a>
                            <?php else: ?>
                            <span style="font-size:0.72rem;color:#444;padding:4px 8px;">Protected</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:50px;color:#555;">
                        No staff members found.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── ADD STAFF MODAL ── -->
<div class="modal-overlay" id="addStaffModal">
    <div class="modal" style="max-width:560px;">
        <div class="modal-header">
            <h4>➕ Add New Staff Member</h4>
            <button class="modal-close" onclick="closeModal('addStaffModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" class="form-control" placeholder="Enter full name" required>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Email Address *</label>
                        <input type="email" name="email" class="form-control" placeholder="staff@apexbank.com" required>
                    </div>
                    <div class="form-group">
                        <label>Assign Role *</label>
                        <select name="role" class="form-control" required>
                            <option value="staff">👤 Staff</option>
                            <option value="admin">👑 Admin</option>
                        </select>
                        <div style="font-size:0.72rem;color:#666;margin-top:6px;">
                            Admin can manage other staff. Staff can only manage customers & transactions.
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Initial Password *</label>
                        <input type="password" name="password" class="form-control" placeholder="Min. 6 characters" minlength="6" required>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Confirm Password *</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
                    </div>
                </div>
                <div style="background:rgba(201,168,76,0.06);border:1px solid rgba(201,168,76,0.15);border-radius:8px;padding:14px;margin-top:8px;font-size:0.78rem;color:var(--gray-light);line-height:1.7;">
                    ⚠ The new staff member should change their password on first login. Only admins can create other admin accounts.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addStaffModal')">Cancel</button>
                <button type="submit" class="btn btn-gold">✔ Create Staff Account</button>
            </div>
        </form>
    </div>
</div>

<!-- ── EDIT STAFF MODAL ── -->
<?php if ($editUser): ?>
<div class="modal-overlay open" id="editStaffModal">
    <div class="modal" style="max-width:560px;">
        <div class="modal-header">
            <h4>✏ Edit Staff: <?php echo htmlspecialchars($editUser['full_name']); ?></h4>
            <a href="staff.php" class="modal-close" style="text-decoration:none;">✕</a>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="edit_id" value="<?php echo $editUser['id']; ?>">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($editUser['full_name']); ?>" required>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Email Address *</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($editUser['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" class="form-control" <?php echo $editUser['id']==$_SESSION['user_id']?'disabled':''; ?>>
                            <option value="staff" <?php echo $editUser['role']==='staff'?'selected':''; ?>>👤 Staff</option>
                            <option value="admin" <?php echo $editUser['role']==='admin'?'selected':''; ?>>👑 Admin</option>
                        </select>
                        <?php if ($editUser['id']==$_SESSION['user_id']): ?>
                        <input type="hidden" name="role" value="admin">
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control" <?php echo $editUser['id']==$_SESSION['user_id']?'disabled':''; ?>>
                            <option value="active"   <?php echo $editUser['status']==='active'?'selected':''; ?>>Active</option>
                            <option value="inactive" <?php echo $editUser['status']==='inactive'?'selected':''; ?>>Inactive</option>
                        </select>
                        <?php if ($editUser['id']==$_SESSION['user_id']): ?>
                        <input type="hidden" name="status" value="active">
                        <?php endif; ?>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Reset Password <span style="color:#666;font-size:0.7rem;text-transform:none;letter-spacing:0;">(leave blank to keep current)</span></label>
                        <input type="password" name="new_password" class="form-control" placeholder="New password (optional)" minlength="6">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="staff.php" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-gold">✔ Save Changes</button>
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