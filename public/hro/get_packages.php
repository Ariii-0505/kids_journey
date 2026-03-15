<?php
require_once(__DIR__ . "/../../includes/db.php");

// Fetch all active services
$result = $conn->query("SELECT program_name, service_name, price FROM services WHERE status='active'");

$services = [];

while ($row = $result->fetch_assoc()) {
    $program = $row['program_name'];
    $service = $row['service_name'];
    $price   = $row['price'];

    if (!isset($services[$program])) {
        $services[$program] = [];
    }

    $services[$program][] = [
        "name" => $service,
        "price" => $price
    ];
}

header('Content-Type: application/json');
echo json_encode($services);

