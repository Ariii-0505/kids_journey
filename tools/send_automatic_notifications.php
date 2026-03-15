<?php
/**
 * Automatic Payment Notification Script
 * 
 * This script should be run periodically (e.g., daily) via cron job
 * to send automatic payment reminders to guardians.
 * 
 * Usage: php send_automatic_notifications.php
 */

require_once(__DIR__ . "/../includes/db.php");
require_once(__DIR__ . "/../includes/EmailHelper.php");

// Get current date
$currentDate = new DateTime();
$currentDate->setTime(0, 0, 0); // Start of day

echo "Starting automatic payment notifications - " . $currentDate->format('Y-m-d H:i:s') . "\n";

// 1. Pending Payments - 1 day before due date
echo "Checking pending payments...\n";
$pendingQuery = $conn->query("
    SELECT p.payment_id, p.student_id, p.payment_amount, p.payment_status,
           s.full_name as student_name, s.student_id as student_code,
           g.guardian_name, g.email as guardian_email,
           svc.program_name, svc.service_name
    FROM payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN guardians g ON g.student_id = s.id
    LEFT JOIN enrollments e ON p.enrollment_id = e.id
    LEFT JOIN services svc ON e.service_id = svc.service_id
    WHERE p.payment_status = 'pending' 
    AND p.archived = 0
    AND g.email IS NOT NULL AND g.email != ''
");

$pendingNotifications = 0;
while ($payment = $pendingQuery->fetch_assoc()) {
    if (empty($payment['payment_date'])) continue;
    
    // For pending payments, due date is 3 days from payment date
    $paymentDate = new DateTime($payment['payment_date']);
    $dueDate = clone $paymentDate;
    $dueDate->modify('+3 days');
    $dueDate->setTime(0, 0, 0);
    
    $reminderDate = clone $dueDate;
    $reminderDate->modify('-1 day');
    
    if ($currentDate == $reminderDate) {
        // Send reminder
        if (sendAutomaticNotification($payment, 'pending')) {
            $pendingNotifications++;
        }
    }
}

// 2. Installment Payments - 7 days before next due date
echo "Checking installment payments...\n";
$installmentQuery = $conn->query("
    SELECT p.payment_id, p.student_id, p.payment_amount, p.payment_status,
           p.installment_next_due, p.remaining_balance,
           s.full_name as student_name, s.student_id as student_code,
           g.guardian_name, g.email as guardian_email,
           svc.program_name, svc.service_name
    FROM payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN guardians g ON g.student_id = s.id
    LEFT JOIN enrollments e ON p.enrollment_id = e.id
    LEFT JOIN services svc ON e.service_id = svc.service_id
    WHERE p.payment_status = 'installment' 
    AND p.archived = 0
    AND p.installment_next_due IS NOT NULL
    AND g.email IS NOT NULL AND g.email != ''
");

$installmentNotifications = 0;
while ($payment = $installmentQuery->fetch_assoc()) {
    $nextDueDate = new DateTime($payment['installment_next_due']);
    $nextDueDate->setTime(0, 0, 0);
    
    $reminderDate = clone $nextDueDate;
    $reminderDate->modify('-7 days');
    
    if ($currentDate == $reminderDate) {
        // Send reminder
        if (sendAutomaticNotification($payment, 'installment')) {
            $installmentNotifications++;
        }
    }
}

// 3. Overdue Payments - when status becomes overdue
echo "Checking for overdue payments...\n";
$overdueQuery = $conn->query("
    SELECT p.payment_id, p.student_id, p.payment_amount, p.payment_status,
           s.full_name as student_name, s.student_id as student_code,
           g.guardian_name, g.email as guardian_email,
           svc.program_name, svc.service_name
    FROM payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN guardians g ON g.student_id = s.id
    LEFT JOIN enrollments e ON p.enrollment_id = e.id
    LEFT JOIN services svc ON e.service_id = svc.service_id
    WHERE p.payment_status = 'overdue' 
    AND p.archived = 0
    AND g.email IS NOT NULL AND g.email != ''
");

$overdueNotifications = 0;
while ($payment = $overdueQuery->fetch_assoc()) {
    // Check if we already sent an overdue notification today
    $checkQuery = $conn->prepare("
        SELECT id FROM payment_notifications 
        WHERE payment_id = ? AND notification_type = 'automatic' 
        AND DATE(sent_at) = CURDATE()
    ");
    $checkQuery->bind_param("i", $payment['payment_id']);
    $checkQuery->execute();
    $checkResult = $checkQuery->get_result();
    
    if ($checkResult->num_rows == 0) {
        // Send overdue notification
        if (sendAutomaticNotification($payment, 'overdue')) {
            $overdueNotifications++;
        }
    }
}

echo "Automatic notifications completed:\n";
echo "- Pending payment reminders: $pendingNotifications\n";
echo "- Installment payment reminders: $installmentNotifications\n";
echo "- Overdue payment notifications: $overdueNotifications\n";
echo "Total notifications sent: " . ($pendingNotifications + $installmentNotifications + $overdueNotifications) . "\n";

function sendAutomaticNotification($payment, $type) {
    global $conn;
    
    $amount_due = 0;
    $due_date = '';
    
    if ($type === 'installment') {
        $amount_due = (float)$payment['remaining_balance'];
        $due_date = date('M d, Y', strtotime($payment['installment_next_due']));
    } elseif ($type === 'pending') {
        $amount_due = (float)$payment['payment_amount'];
        // For pending, due in 3 days from payment date
        $paymentDate = new DateTime($payment['payment_date']);
        $dueDate = clone $paymentDate;
        $dueDate->modify('+3 days');
        $due_date = $dueDate->format('M d, Y');
    } else { // overdue
        $amount_due = (float)$payment['payment_amount'];
        $due_date = date('M d, Y', strtotime($payment['payment_date']));
    }
    
    $student_name = $payment['student_name'];
    $program_name = $payment['program_name'] ?: 'N/A';
    $package_name = $payment['service_name'] ?: 'N/A';
    $payment_status = ucfirst($type);
    
    if ($type === 'overdue') {
        $payment_status = 'Overdue';
    }
    
    $subject = "Payment Reminder – Student Service Payment Due";
    
    $body = "
    <html>
    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #2563eb;'>Payment Reminder</h2>
            
            <p>Dear Guardian,</p>
            
            <p>This is an automatic reminder that a payment is required for the student:</p>
            
            <div style='background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                <p><strong>Student Name:</strong> " . htmlspecialchars($student_name) . "</p>
                <p><strong>Service / Program:</strong> " . htmlspecialchars($program_name) . "</p>
                <p><strong>Package / Category:</strong> " . htmlspecialchars($package_name) . "</p>
                <p><strong>Amount Due:</strong> ₱" . number_format($amount_due, 2) . "</p>
                <p><strong>Due Date:</strong> " . htmlspecialchars($due_date) . "</p>
                <p><strong>Payment Status:</strong> " . htmlspecialchars($payment_status) . "</p>
            </div>
            
            <p>Please settle this payment before the due date to avoid overdue status.</p>
            " . ($type === 'overdue' ? "<p><strong>This payment is now overdue. Please settle the payment as soon as possible.</strong></p>" : "") . "
            
            <p>Thank you.</p>
            
            <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;'>
            
            <p style='font-size: 12px; color: #6b7280;'>
                This is an automated message from " . SITE_NAME . ". Please do not reply to this email.
            </p>
        </div>
    </body>
    </html>
    ";
    
    $altBody = "
Payment Reminder – Student Service Payment Due

Dear Guardian,

This is an automatic reminder that a payment is required for the student:

Student Name: {$student_name}
Service / Program: {$program_name}
Package / Category: {$package_name}
Amount Due: ₱" . number_format($amount_due, 2) . "
Due Date: {$due_date}
Payment Status: {$payment_status}

Please settle this payment before the due date to avoid overdue status.
" . ($type === 'overdue' ? "\n\nThis payment is now overdue. Please settle the payment as soon as possible." : "") . "

Thank you.

This is an automated message from " . SITE_NAME . ". Please do not reply to this email.
    ";
    
    // Send email
    $emailSent = sendEmail($payment['guardian_email'], $subject, $body, $altBody);
    
    // Log notification
    $notification_type = 'automatic';
    $status = $emailSent ? 'sent' : 'failed';
    
    $conn->query("INSERT INTO payment_notifications 
        (payment_id, student_id, guardian_email, notification_type, status, sent_at) 
        VALUES ({$payment['payment_id']}, {$payment['student_id']}, '" . $conn->real_escape_string($payment['guardian_email']) . "', 
        '$notification_type', '$status', NOW())");
    
    if ($emailSent) {
        echo "✓ Sent {$type} notification to {$payment['guardian_email']} for payment {$payment['payment_id']}\n";
        return true;
    } else {
        echo "✗ Failed to send {$type} notification to {$payment['guardian_email']} for payment {$payment['payment_id']}\n";
        return false;
    }
}
?>