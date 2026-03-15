<?php
require_once(__DIR__ . "/../../includes/db.php");
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/config.php';

// Ensure archived column exists in services table
$colCheck = $conn->query("SHOW COLUMNS FROM services LIKE 'archived'");
if (!$colCheck || $colCheck->num_rows == 0) {
    $conn->query("ALTER TABLE services ADD COLUMN archived TINYINT(1) DEFAULT 0");
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    // Get service details before archiving for logging
    $service = $conn->query("SELECT service_name, program_name FROM services WHERE service_id = $id")->fetch_assoc();
    $serviceName = $service ? ($service['service_name'] . ' - ' . $service['program_name']) : 'Service ID: ' . $id;

    // Update service to archived status instead of deleting
    $stmt = $conn->prepare("UPDATE services SET archived = 1 WHERE service_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // Log the activity using existing logActivity function
    logActivity(
        $conn,
        $_SESSION['user_id'],
        $_SESSION['role'],
        'Archived',
        'Service Management',
        'Success',
        'Service "' . $serviceName . '" has been archived'
    );

    // Redirect back to Service Management tab
    header("Location: finance.php?tab=services&success=archived");
    exit();
} else {
    // Log failed attempt
    logActivity(
        $conn,
        $_SESSION['user_id'] ?? 0,
        $_SESSION['role'] ?? 'Unknown',
        'Archive Failed',
        'Service Management',
        'Failed',
        'Missing service ID for archive operation'
    );
    
    header("Location: finance.php?tab=services&error=missing_id");
    exit();
}
?>
