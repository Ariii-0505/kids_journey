<?php
require_once(__DIR__ . "/../../includes/db.php");

// Query the column definition to extract ENUM values
$result = $conn->query("SHOW COLUMNS FROM payments LIKE 'payment_method'");
$row = $result->fetch_assoc();

$enumStr = $row['Type']; // e.g. enum('Cash','GCash','Card')

// Extract values between quotes
preg_match_all("/'([^']+)'/", $enumStr, $matches);
$methods = $matches[1];

// Remove 'Card' from payment methods
$methods = array_filter($methods, function($method) {
    return $method !== 'Card';
});

header('Content-Type: application/json');
echo json_encode(array_values($methods));

