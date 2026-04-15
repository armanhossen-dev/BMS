<?php require_once 'config/db.php'; 
if(!isLoggedIn()) redirect('login.php');

$userId = $_SESSION['user_id'];
$message = '';
$error = '';

// Get existing nominee
$stmt = $pdo->prepare("SELECT * FROM NOMINEE WHERE CustomerID = ?");
$stmt->execute([$userId]);
$nominee = $stmt->fetch();

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['nominee_name']);
    $relation = trim($_POST['relation']);
    $phone = trim($_POST['phone']);
    
    if(empty($name) || empty($relation)) {
        $error = "Name and relation are required";
    } else {
        try {
            // Delete existing if any
            $pdo->prepare("DELETE FROM NOMINEE WHERE CustomerID = ?")->execute([$userId]);
            
            // Insert new nominee
            $stmt = $pdo->prepare("INSERT INTO NOMINEE (CustomerID, NomineeName, NomineeRelation, NomineePhone) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $name, $relation, $phone]);
            
            setToast("Nominee updated successfully!", "success");
            redirect('profile.php');
            
        } catch(Exception $e) {
            $error = "Failed to update nominee: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Nominee - Asha Bank</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-university"></i>
            Asha <span>Bank</span>
        </div>
        <div class="navbar-menu">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <button id="themeToggle" class="btn-outline" style="background: transparent; padding: 0.5rem 1rem;">
                <i class="fas fa-moon"></i>
            </button>
            <a href="logout.php" class="btn-danger" style="padding: 0.5rem 1rem;">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="glass-card fade-in" style="max-width: 550px; margin: 0 auto;">
            <div class="text-center">
                <i class="fas fa-user-friends" style="font-size: 3rem; color: var(--accent);"></i>
                <h2>Manage Nominee</h2>
                <p class="text-muted">Add or update your nominee details</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-danger" style="background: rgba(192,57,43,0.1); padding: 0.75rem; border-radius: 12px; margin-bottom: 1rem;">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" data-validate="true">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Nominee Full Name</label>
                    <input type="text" name="nominee_name" placeholder="Enter nominee's full name" value="<?= htmlspecialchars($nominee['NomineeName'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-heart"></i> Relationship</label>
                    <input type="text" name="relation" placeholder="e.g., Spouse, Child, Parent" value="<?= htmlspecialchars($nominee['NomineeRelation'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Phone Number</label>
                    <input type="tel" name="phone" placeholder="Nominee's phone number" value="<?= htmlspecialchars($nominee['NomineePhone'] ?? '') ?>">
                </div>
                <button type="submit" class="btn btn-block">Save Nominee <i class="fas fa-save"></i></button>
            </form>
            
            <div class="text-center mt-3">
                <a href="dashboard.php">← Back to Dashboard</a>
            </div>
        </div>
    </div>
    
    <div id="toastContainer">
        <?php $toast = getToast(); if($toast): ?>
            <div class="toast-notification <?= $toast['type'] ?>">
                <i class="fas <?= $toast['type'] == 'success' ? 'fa-check-circle' : 'fa-info-circle' ?>"></i>
                <span><?= htmlspecialchars($toast['message']) ?></span>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="assets/js/main.js"></script>
</body>
</html>