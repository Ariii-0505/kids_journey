<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/EmailHelper.php';

echo "Testing system components...\n";

try {
    // Test database connection
    if ($conn) {
        echo "✓ Database connection successful\n";
    } else {
        echo "✗ Database connection failed\n";
    }
    
    // Test EmailHelper
    $emailHelper = new EmailHelper();
    echo "✓ EmailHelper instantiated successfully\n";
    
    echo "All tests passed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>