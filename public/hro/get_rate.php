<?php
require_once(__DIR__ . "/../../includes/db.php");

$service = $_GET['service'] ?? '';
$package = $_GET['package'] ?? '';

if (!$service) {
    echo json_encode(["rate" => 0]);
    exit;
}

if ($service === "Academic Tutorial") {

    // Academic tutorial uses hourly rate
    $stmt = $conn->prepare("
        SELECT price 
        FROM services 
        WHERE service_name = ? 
        LIMIT 1
    ");

    $stmt->bind_param("s", $service);

} else {

    // Other services use service + package
    $stmt = $conn->prepare("
        SELECT price 
        FROM services 
        WHERE service_name = ? 
        AND package = ? 
        LIMIT 1
    ");

    $stmt->bind_param("ss", $service, $package);

}

$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo json_encode([
    "rate" => $row['price'] ?? 0
]);