<?php
require_once(__DIR__ . "/../../includes/db.php");

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // Delete from services table
    $stmt = $conn->prepare("DELETE FROM services WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    // Redirect back to Service Management tab
    header("Location: finance.php?tab=services&success=deleted");
    exit();
} else {
    header("Location: finance.php?tab=services&error=missing_id");
    exit();
}
?>
