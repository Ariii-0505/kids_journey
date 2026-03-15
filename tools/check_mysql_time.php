<?php
require __DIR__ . '/../includes/config.php';

$res = $conn->query('SELECT NOW() AS now, UTC_TIMESTAMP() AS utc');
if (!$res) {
    echo "Query error: " . $conn->error . "\n";
    exit(1);
}
$row = $res->fetch_assoc();
echo "NOW: " . $row['now'] . "\n";
echo "UTC: " . $row['utc'] . "\n";
