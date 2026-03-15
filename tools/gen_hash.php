<?php
// Generate a proper bcrypt hash for admin123
$password = 'admin123';
$hash = password_hash($password, PASSWORD_BCRYPT);

echo "Password: " . $password . "\n";
echo "Generated hash: " . $hash . "\n\n";

// Verify it works
if (password_verify($password, $hash)) {
    echo "✅ Hash verification: SUCCESS\n\n";
    echo "SQL Update statement:\n";
    echo "UPDATE users SET password='" . $hash . "' WHERE username='aj123';\n";
} else {
    echo "❌ Hash verification: FAILED\n";
}
?>