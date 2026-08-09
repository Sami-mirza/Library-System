<?php
require_once 'Config/DB.php';

try {
    $pdo->exec("ALTER TABLE books ADD COLUMN location VARCHAR(100) DEFAULT NULL");
    echo "✅ Location column added successfully! You can delete this file now.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "✅ Location column already exists. Nothing to fix.";
    } else {
        echo "❌ Error: " . $e->getMessage();
    }
}
?>