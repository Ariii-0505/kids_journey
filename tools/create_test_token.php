<?php
require __DIR__ . '/../includes/config.php';

$userId = 3; // adjust as needed
$token = bin2hex(random_bytes(32));
$expiresAt = gmdate('Y-m-d H:i:s', time() + 3600);

$deleteQuery = "DELETE FROM password_reset_tokens WHERE user_id = ?";
$deleteStmt = $conn->prepare($deleteQuery);
$deleteStmt->bind_param('i', $userId);
$deleteStmt->execute();

$insertQuery = "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)";
$insertStmt = $conn->prepare($insertQuery);
$insertStmt->bind_param('iss', $userId, $token, $expiresAt);
$insertStmt->execute();

if ($insertStmt->affected_rows) {
    echo "Inserted token: $token\n";
    echo "Expires at (UTC): $expiresAt\n";
} else {
    echo "Insert failed: " . $conn->error . "\n";
}
