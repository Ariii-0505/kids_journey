<?php
/**
 * Automatic Payment Status Update Script
 * 
 * This script should be run periodically (e.g., daily) via cron job
 * to automatically update payment statuses based on due dates.
 * 
 * Usage: php update_payment_statuses.php
 */

require_once(__DIR__ . "/../includes/db.php");

// Get current date
$currentDate = new DateTime();
$currentDate->setTime(0, 0, 0); // Start of day

echo "Starting automatic payment status updates - " . $currentDate->format('Y-m-d H:i:s') . "\n";

// 1. Mark pending payments as overdue if past due date (3 days from payment date)
echo "Checking pending payments for overdue status...\n";
$pendingQuery = $conn->query("
    SELECT payment_id, payment_date 
    FROM payments 
    WHERE payment_status = 'pending' 
    AND archived = 0
");

$pendingOverdue = 0;
while ($payment = $pendingQuery->fetch_assoc()) {
    $paymentDate = new DateTime($payment['payment_date']);
    $dueDate = clone $paymentDate;
    $dueDate->modify('+3 days');
    $dueDate->setTime(0, 0, 0);
    
    if ($currentDate > $dueDate) {
        // Mark as overdue
        $conn->query("UPDATE payments SET payment_status = 'overdue' WHERE payment_id = {$payment['payment_id']}");
        $conn->query("UPDATE enrollments SET status = 'Pending' WHERE id = (SELECT enrollment_id FROM payments WHERE payment_id = {$payment['payment_id']})");
        $conn->query("UPDATE students SET status = 'Pending' WHERE id = (SELECT student_id FROM payments WHERE payment_id = {$payment['payment_id']})");
        
        // Log the status change
        $userId = 1; // System user
        $role = 'System';
        $action = 'Auto-marked as Overdue';
        $module = 'Payment Management';
        $details = "Payment ID {$payment['payment_id']} automatically marked as overdue (past due date)";
        logActivity($conn, $userId, $role, $action, $module, 'Success', $details);
        
        $pendingOverdue++;
    }
}

// 2. Mark installment payments as overdue if past next due date
echo "Checking installment payments for overdue status...\n";
$installmentQuery = $conn->query("
    SELECT payment_id, installment_next_due 
    FROM payments 
    WHERE payment_status = 'installment' 
    AND archived = 0
    AND installment_next_due IS NOT NULL
");

$installmentOverdue = 0;
while ($payment = $installmentQuery->fetch_assoc()) {
    $nextDueDate = new DateTime($payment['installment_next_due']);
    $nextDueDate->setTime(0, 0, 0);
    
    if ($currentDate > $nextDueDate) {
        // Mark as overdue
        $conn->query("UPDATE payments SET payment_status = 'overdue' WHERE payment_id = {$payment['payment_id']}");
        $conn->query("UPDATE enrollments SET status = 'Pending' WHERE id = (SELECT enrollment_id FROM payments WHERE payment_id = {$payment['payment_id']})");
        $conn->query("UPDATE students SET status = 'Pending' WHERE id = (SELECT student_id FROM payments WHERE payment_id = {$payment['payment_id']})");
        
        // Log the status change
        $userId = 1; // System user
        $role = 'System';
        $action = 'Auto-marked as Overdue';
        $module = 'Payment Management';
        $details = "Payment ID {$payment['payment_id']} automatically marked as overdue (past installment due date)";
        logActivity($conn, $userId, $role, $action, $module, 'Success', $details);
        
        $installmentOverdue++;
    }
}

echo "Automatic status updates completed:\n";
echo "- Pending payments marked overdue: $pendingOverdue\n";
echo "- Installment payments marked overdue: $installmentOverdue\n";
echo "Total payments updated: " . ($pendingOverdue + $installmentOverdue) . "\n";
?>