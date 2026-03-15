<?php
require_once __DIR__ . '/../includes/db.php';

$result = $conn->query('SHOW TABLES LIKE "payment_notifications"');
if ($result->num_rows > 0) {
    echo "Table exists\n";
    $columns = $conn->query('DESCRIBE payment_notifications');
    while ($col = $columns->fetch_assoc()) {
        echo $col['Field'] . ' - ' . $col['Type'] . "\n";
    }
} else {
    echo "Table does not exist\n";
}
?>