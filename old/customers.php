<?php
require_once 'config/db.php';
requireLogin();
$pageTitle = 'Customers';

$success = $error = '';

// ADD CUSTOMER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $cid    = generateCustomerId($conn);
    $name   = $conn->real_escape_string($_POST['full_name']);
    $email  = $conn->real_escape_string($_POST['email']);
    $phone  = $conn->real_escape_string($_POST['phone']);
    $addr   = $conn->real_escape_string($_POST['address']);
    $dob    = $conn->real_escape_string($_POST['dob']);
    $gender = $conn->real_escape_string($_POST['gender']);
    $idtype = $conn->real_escape_string($_POST['id_type']);
    $idnum  = $conn->real_escape_string($_POST['id_number']);

    $sql = "INSERT INTO customers (customer_id, full_name, email, phone, address, dob, gender, id_type, id_number)
            VALUES ('$cid','$name','$email','$phone','$addr','$dob','$gender','$idtype','$idnum')";

    if ($conn->query($sql)) {
        $success = "Customer '$name' added successfully! ID: $cid";
    } else {
        $error = "Error: " . $conn->error;
    }
}

// DELETE CUSTOMER
if (isset($_GET['delete'])) {
    $did = intval($_GET['delete']);
    $conn->query("DELETE FROM customers WHERE id = $did");
    $success = "Customer deleted successfully.";
}

// STATUS TOGGLE
if (isset($_GET['toggle'])) {
    $tid = intval($_GET['toggle']);
    $cur = $conn->query("SELECT status FROM customers WHERE id=$tid")->fetch_assoc()['status'];
    $new = $cur === 'active' ? 'inactive' : 'active';
    $conn->query("UPDATE customers SET status='$new' WHERE id=$tid");
    $success = "Customer status updated.";
}

// SEARCH
$search = '';
$where  = '';
if (!empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $where  = "WHERE full_name LIKE '%$search%' OR email LIKE '%$search%' OR customer_id LIKE '%$search%' OR phone LIKE '%$search%'";
}

$customers = $conn->query("SELECT * FROM customers $where ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers – Apex Bank</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="page-header">
    <h2>👤 Customer Management</h2>
    <p>Register, view and manage all bank customers.</p>
</div>

<?php if ($success): ?><div class="alert alert-success">✔ <?php echo $success; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger">⚠ <?php echo $error; ?></div><?php endif; ?>

<!-- FILTER BAR -->
<div class="filter-bar">
    <form method="GET" style="display:flex;gap:12px;flex:1;flex-wrap:wrap;">
        <div class="search-box" style="flex:1;">
            <span class="search-icon">🔍</span>
            <input type="text" name="search" id="tableSearch" placeholder="Search by name, email, ID..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button type="submit" class="btn btn-outline">Search</button>
        <?php if ($search): ?><a href="customers.php" class="btn btn-outline">✕ Clear</a><?php endif; ?>
    </form>
    <button class="btn btn-gold" onclick="openModal('addCustomerModal')">+ Add Customer</button>
</div>

<!-- CUSTOMERS TABLE -->
<div class="card">
    <div class="card-header">
        <h4>All Customers</h4>
        <span class="text-muted"><?php echo $customers->num_rows; ?> record(s)</span>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Gender</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($customers->num_rows > 0):
                    $i = 1;
                    while ($c = $customers->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td><span style="color:#D4AF37;"><?php echo $c['customer_id']; ?></span></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="user-avatar" style="width:32px;height:32px;font-size:0.75rem;flex-shrink:0;">
                                <?php echo strtoupper(substr($c['full_name'],0,1)); ?>
                            </div>
                            <?php echo htmlspecialchars($c['full_name']); ?>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($c['email']); ?></td>
                    <td><?php echo htmlspecialchars($c['phone']); ?></td>
                    <td><?php echo ucfirst($c['gender'] ?? '—'); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $c['status'] === 'active' ? 'success' : ($c['status'] === 'blocked' ? 'danger' : 'warning'); ?>">
                            <?php echo $c['status']; ?>
                        </span>
                    </td>
                    <td><?php echo date('d M Y', strtotime($c['created_at'])); ?></td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <a href="accounts.php?customer=<?php echo $c['id']; ?>" class="btn btn-outline btn-sm" title="View Accounts">💳</a>
                            <a href="customers.php?toggle=<?php echo $c['id']; ?>" class="btn btn-outline btn-sm" title="Toggle Status">⇄</a>
                            <a href="customers.php?delete=<?php echo $c['id']; ?>" class="btn btn-danger btn-sm btn-delete-confirm" title="Delete">🗑</a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="9" style="text-align:center;padding:40px;color:#888;">No customers found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ADD CUSTOMER MODAL -->
<div class="modal-overlay" id="addCustomerModal">
    <div class="modal" style="max-width:680px;">
        <div class="modal-header">
            <h4>➕ Register New Customer</h4>
            <button class="modal-close" onclick="closeModal('addCustomerModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" class="form-control" placeholder="Enter full name" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address *</label>
                        <input type="email" name="email" class="form-control" placeholder="email@example.com" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number *</label>
                        <input type="text" name="phone" class="form-control" placeholder="01700-000000" required>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="dob" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" class="form-control">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>ID Type</label>
                        <select name="id_type" class="form-control">
                            <option value="NID">National ID (NID)</option>
                            <option value="Passport">Passport</option>
                            <option value="Birth Certificate">Birth Certificate</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>ID Number</label>
                        <input type="text" name="id_number" class="form-control" placeholder="Enter ID number">
                    </div>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" class="form-control" placeholder="Full address..." rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addCustomerModal')">Cancel</button>
                <button type="submit" class="btn btn-gold">✔ Register Customer</button>
            </div>
        </form>
    </div>
</div>

    </div>
</div>
</div>
<script src="js/main.js"></script>
</body>
</html>