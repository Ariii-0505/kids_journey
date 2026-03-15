<?php
require_once(__DIR__ . "/../../includes/db.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $category = $_POST['category'];
    $rate = $_POST['rate'];
    $frequency = $_POST['frequency'];

    $stmt = $conn->prepare("INSERT INTO services (title, description, category, rate, frequency) VALUES (?,?,?,?,?)");
    $stmt->bind_param("sssds", $title, $description, $category, $rate, $frequency);
    $stmt->execute();

    header("Location: finance.php?tab=services&success=1");
    exit();
}
?>

