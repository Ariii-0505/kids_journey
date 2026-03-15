<?php
require __DIR__ . '/../includes/config.php';

$res = $conn->query("SELECT id,user_id,token,expires_at FROM password_reset_tokens ORDER BY id DESC LIMIT 10");
if (!$res) {
    echo "Query error: " . $conn->error . "\n";
    exit(1);
}

while ($row = $res->fetch_assoc()) {
    var_dump($row);
}
