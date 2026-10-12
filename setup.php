<?php
/**
 * Asha Bank — Database Setup & Diagnostic Helper
 * Allows automatic database schema creation, demo data initialization, and diagnostics.
 */

$host = '127.0.0.1'; 
$port = 3306;
$user = 'root'; 
$pass = '';
$dbName = 'asha_bank';

$msg = ''; 
$success = false;
$existingTables = [];
$dbConnected = false;

try {
    $testPdo = new PDO("mysql:host=$host;port=$port", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $dbConnected = true;
    
    // Check if database exists
    $dbs = $testPdo->query("SHOW DATABASES LIKE '$dbName'")->fetchAll();
    if (!empty($dbs)) {
        $appPdo = new PDO("mysql:host=$host;port=$port;dbname=$dbName", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $existingTables = $appPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Exception $e) {
    $dbConnected = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sqlPath = __DIR__ . '/database.sql';
        if (!file_exists($sqlPath)) {
            throw new Exception("database.sql not found at $sqlPath");
        }

        // Try CLI import first if shell_exec is available
        $imported = false;
        if (function_exists('exec')) {
            $passParam = !empty($pass) ? "-p" . escapeshellarg($pass) : "";
            $cmd = sprintf('mysql -h %s -P %d -u %s %s < %s 2>&1',
                escapeshellarg($host),
                $port,
                escapeshellarg($user),
                $passParam,
                escapeshellarg($sqlPath)
            );
            $output = [];
            $returnVar = 0;
            @exec($cmd, $output, $returnVar);
            if ($returnVar === 0) {
                $imported = true;
            }
        }

        // Fallback: Custom SQL parser for DELIMITER $$
        if (!$imported) {
            $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $rawSql = file_get_contents($sqlPath);
            
            // Normalize line breaks
            $rawSql = str_replace("\r\n", "\n", $rawSql);
            $lines = explode("\n", $rawSql);
            
            $currentDelimiter = ';';
            $buffer = '';
            
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (str_starts_with(strtoupper($trimmed), 'DELIMITER ')) {
                    $currentDelimiter = trim(substr($trimmed, 10));
                    continue;
                }
                
                $buffer .= $line . "\n";
                
                if ($currentDelimiter === ';' && str_ends_with($trimmed, ';')) {
                    $stmt = trim($buffer);
                    if (!empty($stmt)) {
                        $pdo->exec($stmt);
                    }
                    $buffer = '';
                } elseif ($currentDelimiter === '$$' && str_ends_with($trimmed, '$$')) {
                    $stmt = trim(substr(trim($buffer), 0, -2));
                    if (!empty($stmt)) {
                        $pdo->exec($stmt);
                    }
                    $buffer = '';
                }
            }
            if (!empty(trim($buffer))) {
                $pdo->exec(trim($buffer));
            }
        }

        $success = true;
        $msg = 'Database imported and initialized successfully! All tables, views, and seed data are ready.';
        
        // Refresh tables count
        $appPdo = new PDO("mysql:host=$host;port=$port;dbname=$dbName", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $existingTables = $appPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    } catch (Exception $e) {
        $msg = 'Setup error: ' . $e->getMessage() . '. You can also run: `mysql -u root < database.sql` from the terminal.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Asha Bank Database Setup & Verification</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Inter', -apple-system, sans-serif; background: #0f172a; color: #f8fafc; padding: 40px 20px; line-height: 1.5; }
    .container { max-width: 640px; margin: 0 auto; background: #1e293b; padding: 32px; border-radius: 16px; border: 1px solid #334155; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5); }
    h1 { font-size: 24px; margin-bottom: 8px; color: #38bdf8; display: flex; align-items: center; gap: 10px; }
    p { color: #94a3b8; font-size: 14px; margin-bottom: 20px; }
    .badge { padding: 12px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 20px; line-height: 1.5; }
    .badge-success { background: #064e3b; color: #6ee7b7; border: 1px solid #059669; }
    .badge-danger { background: #7f1d1d; color: #fca5a5; border: 1px solid #b91c1c; }
    .card { background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 16px; margin-bottom: 20px; font-family: monospace; font-size: 13px; color: #38bdf8; }
    .btn { display: inline-flex; align-items: center; justify-content: center; width: 100%; padding: 14px; border-radius: 8px; font-weight: 600; font-size: 14px; border: none; cursor: pointer; text-decoration: none; transition: 0.2s; }
    .btn-primary { background: #0284c7; color: white; margin-bottom: 12px; }
    .btn-primary:hover { background: #0369a1; }
    .btn-secondary { background: #334155; color: #f8fafc; }
    .btn-secondary:hover { background: #475569; }
    .creds { background: #0f172a; border-radius: 8px; padding: 16px; margin-top: 24px; border: 1px solid #334155; }
    .creds h3 { font-size: 14px; color: #f1f5f9; margin-bottom: 10px; }
    .creds table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .creds td { padding: 6px 8px; border-bottom: 1px solid #1e293b; color: #94a3b8; }
    .creds td strong { color: #f8fafc; }
</style>
</head>
<body>
<div class="container">
    <h1>🏛️ Asha Bank Database Setup</h1>
    <p>Manage and initialize the production-grade relational database for Asha Bank Management System.</p>

    <?php if ($msg): ?>
        <div class="badge <?= $success ? 'badge-success' : 'badge-danger' ?>">
            <?= htmlspecialchars($msg) ?>
        </div>
    <?php endif; ?>

    <div style="margin-bottom: 20px;">
        <span style="font-size: 13px; color: <?= $dbConnected ? '#34d399' : '#f87171' ?>;">
            ● MySQL Connection: <?= $dbConnected ? "Connected ($host:$port)" : "Cannot connect to MySQL ($host:$port)" ?>
        </span>
        <br>
        <span style="font-size: 13px; color: #94a3b8;">
            ● Database `asha_bank`: <?= count($existingTables) > 0 ? count($existingTables) . " tables & views installed" : "Not yet initialized" ?>
        </span>
    </div>

    <form method="POST">
        <button type="submit" class="btn btn-primary">
            <?= count($existingTables) > 0 ? "⚡ Re-initialize / Reset Database" : "🚀 Install & Initialize Database" ?>
        </button>
    </form>

    <a href="index.php" class="btn btn-secondary">Go to Homepage →</a>

    <div class="creds">
        <h3>🔑 Seed Demo Login Accounts (Password for all: <code>password</code>)</h3>
        <table>
            <tr><td><strong>Role</strong></td><td><strong>Login Username / Email</strong></td><td><strong>Access / Features</strong></td></tr>
            <tr><td>Admin</td><td><code>admin</code></td><td>Full management console, audit, customers</td></tr>
            <tr><td>Staff / Manager</td><td><code>rajesh</code></td><td>Customer KYC, ticket desk, branch ops</td></tr>
            <tr><td>Customer 1</td><td><code>arjun.kapoor</code></td><td>Classic Savings (৳ 78,450.50), Debit Card</td></tr>
            <tr><td>Customer 2</td><td><code>sanya.malhotra</code></td><td>Classic Savings (৳ 145,200.00), Verified KYC</td></tr>
            <tr><td>Customer 3</td><td><code>kiran.bose</code></td><td>Platinum High-Yield (৳ 485,000.00), Credit Card</td></tr>
        </table>
    </div>
</div>
</body>
</html>
