<?php
require_once(__DIR__ . "/../includes/db.php");

echo "Starting database fix...\n";

// Fix users table - add pending and denied statuses
$result = $conn->query("ALTER TABLE users MODIFY COLUMN status ENUM('active','suspended','pending','denied') DEFAULT 'active'");

if ($result) {
    echo "✅ Successfully updated users table!\n";
} else {
    echo "❌ Error: " . $conn->error . "\n";
}

// Check current users and their statuses
echo "\nCurrent users in database:\n";
$users = $conn->query("SELECT id, full_name, email, status FROM users");
while ($u = $users->fetch_assoc()) {
    echo "- ID: " . $u['id'] . ", Name: " . $u['full_name'] . ", Status: " . ($u['status'] ?? 'NULL/empty') . "\n";
}
?>
