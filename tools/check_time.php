<?php
require __DIR__ . '/../includes/config.php';

echo "PHP now: " . date('Y-m-d H:i:s') . "\n";
$res = $conn->query('SELECT NOW() AS now');
if ($res) {
    $row = $res->fetch_assoc();
    echo "DB now: " . $row['now'] . "\n";
} else {
    echo "DB query failed: " . $conn->error . "\n";
}
