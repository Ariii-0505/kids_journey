<?php
require_once(__DIR__ . "/../../includes/db.php");
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $program     = $_POST['program']     ?? '';
    $service     = $_POST['service']     ?? '';
    $description = $_POST['description'] ?? '';
    $category    = $_POST['category']    ?? '';
    $price       = $_POST['rate']        ?? 0;
    $frequency   = $_POST['frequency']   ?? '';

    // Avoid inserting duplicate program+service combinations
    $check = $conn->prepare("SELECT service_id FROM services WHERE program_name = ? AND service_name = ?");
    $check->bind_param('ss', $program, $service);
    $check->execute();
    $check->store_result();

    if ($check->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO services (program_name, service_name, description, category, price, frequency, status) VALUES (?,?,?,?,?,?,'active')");
        $stmt->bind_param("ssssds", $program, $service, $description, $category, $price, $frequency);
        $stmt->execute();

        // Log with new signature: logActivity($conn, $action, $module, $status, $details, $target)
        logActivity(
            $conn,
            'Added Service',
            'Finance Management',
            'Success',
            'New service added: ' . $service . ' (' . $program . ') - ₱' . number_format((float)$price, 2),
            $program . ' – ' . $service
        );
    }

    header("Location: finance.php?tab=services&success=1");
    exit();
}
?>