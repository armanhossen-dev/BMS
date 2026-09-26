<?php
/**
 * Asha Bank — Database setup helper.
 * Visiting this file in a browser will import database.sql if the DB doesn't exist yet.
 * Delete or protect this file in production.
 */
$host = 'localhost'; $user = 'root'; $pass = '';

$msg = ''; $success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $mysqli = new mysqli($host, $user, $pass);
        if ($mysqli->connect_error) throw new Exception($mysqli->connect_error);

        $sql = file_get_contents(__DIR__ . '/database.sql');
        if ($sql === false) throw new Exception('Could not read database.sql');

        // multi_query executes statements separated by ; including DELIMITER blocks improperly,
        // so we run it via the mysqli multi-statement API which handles DELIMITER-free scripts.
        // NOTE: the stored procedures in database.sql use DELIMITER $$, which mysqli doesn't parse.
        // For full compatibility, import via CLI: mysql -u root -p < database.sql
        if ($mysqli->multi_query($sql)) {
            do {
                if ($result = $mysqli->store_result()) $result->free();
            } while ($mysqli->more_results() && $mysqli->next_result());
        }
        if ($mysqli->error) throw new Exception($mysqli->error);

        $success = true;
        $msg = 'Database imported successfully! You can now log in.';
    } catch (Exception $e) {
        $msg = 'Import failed: ' . $e->getMessage() . '. Please import database.sql manually via phpMyAdmin or the mysql CLI instead.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup · Asha Bank</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;">
<div class="card" style="max-width:520px;width:100%;">
  <div class="brand" style="padding:0 0 20px;"><div class="brand-mark">A</div><div class="brand-name" style="font-size:19px;">Asha<span>Bank</span> Setup</div></div>

  <?php if ($msg): ?>
    <div class="badge <?= $success?'badge-success':'badge-danger' ?>" style="display:block;padding:12px 14px;margin-bottom:18px;line-height:1.5;"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <p class="text-muted text-sm" style="margin-bottom:18px;line-height:1.6;">
    This will attempt to import <code>database.sql</code> into a local MySQL server.
    <strong>Recommended:</strong> because the schema includes stored procedures and triggers
    (which use <code>DELIMITER</code>), the most reliable method is the command line:
  </p>
  <div class="card mono text-xs" style="background:var(--surface-2);padding:14px;margin-bottom:18px;">
    mysql -u root -p &lt; database.sql
  </div>
  <p class="text-muted text-sm" style="margin-bottom:18px;">Or import <code>database.sql</code> directly via phpMyAdmin's Import tab.</p>

  <form method="POST">
    <button type="submit" class="btn btn-primary btn-block">Try Automatic Import (basic)</button>
  </form>
  <a href="login.php" class="btn btn-secondary btn-block mt-16">Go to Login</a>
</div>
</body>
</html>
