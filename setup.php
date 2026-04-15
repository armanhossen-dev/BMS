<?php
// setup.php - Run this once to create all folders and verify setup
echo "<pre>";

// Create folders
$folders = ['assets/css', 'assets/js', 'config', 'admin', 'includes'];
foreach($folders as $folder) {
    if(!is_dir($folder)) {
        mkdir($folder, 0777, true);
        echo "✓ Created folder: $folder\n";
    }
}

echo "\n✅ Folder structure ready!\n";
echo "📁 Please ensure:\n";
echo "   1. database.sql is imported into MySQL\n";
echo "   2. Update config/db.php with your database credentials\n";
echo "   3. Then visit index.php\n";

// Check database connection
try {
    require_once 'config/db.php';
    echo "\n✓ Database connection successful!\n";
} catch(Exception $e) {
    echo "\n✗ Database connection failed. Please check config/db.php\n";
}
?>