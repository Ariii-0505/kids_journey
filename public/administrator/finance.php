<?php
require_once(__DIR__ . "/../../includes/db.php");
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/EmailHelper.php';
 
$activeTab = $_GET['tab'] ?? 'payments';
 
/* =========================
   DB COLUMN GUARDS
========================== */
$colCheck = $conn->query("SHOW COLUMNS FROM payments LIKE 'archived'");
if (!$colCheck || $colCheck->num_rows == 0) {
    $conn->query("ALTER TABLE payments ADD COLUMN archived TINYINT(1) DEFAULT 0");
}
$serviceColCheck = $conn->query("SHOW COLUMNS FROM services LIKE 'archived'");
if (!$serviceColCheck || $serviceColCheck->num_rows == 0) {
    $conn->query("ALTER TABLE services ADD COLUMN archived TINYINT(1) DEFAULT 0");
}
$svcStatusCheck = $conn->query("SHOW COLUMNS FROM services LIKE 'status'");
if ($svcStatusCheck && $svcStatusCheck->num_rows > 0) {
    $conn->query("ALTER TABLE services MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
}
// Ensure notification columns exist on payments
$notifColCheck = $conn->query("SHOW COLUMNS FROM payments LIKE 'last_notified_at'");
if (!$notifColCheck || $notifColCheck->num_rows == 0) {
    $conn->query("ALTER TABLE payments ADD COLUMN last_notified_at DATETIME NULL DEFAULT NULL");
}

// Ensure payment_notifications table exists
$conn->query("CREATE TABLE IF NOT EXISTS payment_notifications (
    id INT(11) NOT NULL AUTO_INCREMENT,
    payment_id INT(11) NOT NULL,
    student_id INT(11) NOT NULL,
    guardian_email VARCHAR(255) NOT NULL,
    notification_type ENUM('manual','automatic') NOT NULL DEFAULT 'manual',
    status ENUM('sent','failed') NOT NULL DEFAULT 'sent',
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_payment_id (payment_id),
    KEY idx_student_id (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure payment_due_date column exists
$dueDateCheck = $conn->query("SHOW COLUMNS FROM payments LIKE 'payment_due_date'");
if (!$dueDateCheck || $dueDateCheck->num_rows == 0) {
    $conn->query("ALTER TABLE payments ADD COLUMN payment_due_date DATE NULL DEFAULT NULL");
}

// Back-fill due dates for existing pending payments that have none
$conn->query("
    UPDATE payments
    SET payment_due_date = DATE_ADD(DATE(payment_date), INTERVAL 3 DAY)
    WHERE payment_status = 'pending'
      AND (payment_due_date IS NULL OR payment_due_date = '0000-00-00')
      AND payment_date IS NOT NULL
");

// Auto-overdue: mark pending payments past their due date as overdue
$conn->query("
    UPDATE payments
    SET payment_status = 'overdue'
    WHERE payment_status = 'pending'
      AND payment_due_date IS NOT NULL
      AND payment_due_date < CURDATE()
");

// Ensure all new payment columns exist
$newCols = [
    "notes TEXT NULL",
    "installment_plan VARCHAR(20) NULL",
    "installment_pay_every VARCHAR(30) NULL",
    "installment_payment_num INT NULL DEFAULT 1",
    "installment_total_paid INT NULL DEFAULT 0",
    "installment_paid_count INT NULL DEFAULT 0",
    "installment_amount_per INT NULL DEFAULT 0",
    "amount_paid DECIMAL(10,2) NULL DEFAULT 0.00",
    "remaining_balance DECIMAL(10,2) NULL DEFAULT 0.00",
    "total_service_amount DECIMAL(10,2) NULL DEFAULT 0.00",
    "installment_next_due DATE NULL",
    "gcash_account_name VARCHAR(100) NULL",
    "gcash_number VARCHAR(20) NULL",
];
foreach ($newCols as $colDef) {
    $colName = explode(' ', $colDef)[0];
    $chk = $conn->query("SHOW COLUMNS FROM payments LIKE '$colName'");
    if (!$chk || $chk->num_rows == 0) {
        $conn->query("ALTER TABLE payments ADD COLUMN $colDef");
    }
}
 
/* =========================
   HANDLE ACTIONS
========================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 
    if (isset($_POST['action']) && $_POST['action'] === 'edit_service') {
        $sid          = (int)$_POST['service_id'];
        $program_name = $conn->real_escape_string($_POST['program_name']);
        $service_name = $conn->real_escape_string($_POST['service_name']);
        $description  = $conn->real_escape_string($_POST['description'] ?? '');
        $price        = (float)$_POST['price'];
        $frequency    = $conn->real_escape_string($_POST['frequency']);
        $status       = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';
        $conn->query("UPDATE services SET
            program_name='$program_name', service_name='$service_name',
            description='$description', price=$price,
            frequency='$frequency', status='$status'
            WHERE service_id=$sid");
        header("Location: finance.php?tab=services&svc_updated=1");
        exit;
    }
 
    if (isset($_POST['action']) && $_POST['action'] === 'archive_service') {
        $sid = (int)$_POST['service_id'];
        $conn->query("UPDATE services SET archived=1, status='archived' WHERE service_id=$sid");
        header("Location: finance.php?tab=services&svc_archived=1");
        exit;
    }
 
    if (isset($_POST['action']) && $_POST['action'] === 'restore_service') {
        $sid = (int)$_POST['service_id'];
        $conn->query("UPDATE services SET archived=0, status='active' WHERE service_id=$sid");
        header("Location: finance.php?tab=services&service_status=archived&svc_restored=1");
        exit;
    }
 
    if (isset($_POST['action']) && $_POST['action'] === 'edit_payment') {
        // Block submission if amount is zero or not provided
        $payment_amount_check = (float)($_POST['payment_amount'] ?? 0);
        if ($payment_amount_check <= 0) {
            header("Location: finance.php?tab=payments&error=no_amount");
            exit;
        }

        $payment_id          = (int)$_POST['payment_id'];
        $payment_status      = $conn->real_escape_string($_POST['payment_status']);
        $enrollment_status   = $conn->real_escape_string($_POST['enrollment_status'] ?? '');
        $payment_method      = $conn->real_escape_string($_POST['payment_method'] ?? 'cash');
        $payment_amount      = (float)($_POST['payment_amount'] ?? 0);
        $notes               = $conn->real_escape_string($_POST['notes'] ?? '');
        $gcash_account_name  = $conn->real_escape_string($_POST['gcash_account_name'] ?? '');
        $gcash_number        = $conn->real_escape_string($_POST['gcash_number'] ?? '');
        $installment_plan    = $conn->real_escape_string($_POST['installment_plan'] ?? '');
        $installment_pay_every   = $conn->real_escape_string($_POST['installment_pay_every'] ?? '');
        $installment_payment_num = (int)($_POST['installment_payment_num'] ?? 1);

        // Flag: 0 = first-time plan setup, 1 = recording a payment (even the 1st one)
        $record_first_payment = (int)($_POST['record_first_payment'] ?? 0);
 
        // Reference number
        $reference_no = ($payment_method === 'gcash')
            ? $conn->real_escape_string(preg_replace('/\s+/', '', $_POST['gcash_ref'] ?? ''))
            : '';
 
        // Lock check — cannot edit already-paid record
        $currentRow = $conn->query("SELECT payment_status, total_service_amount, payment_amount FROM payments WHERE payment_id=$payment_id")->fetch_assoc();
        if ($currentRow && $currentRow['payment_status'] === 'paid') {
            header("Location: finance.php?tab=payments&error=locked");
            exit;
        }
 
        // ── Installment logic ───────────────────────────────────────
        $planMap = ['1 Month' => 1, '2 Months' => 2, '3 Months' => 3];
        $installment_total_steps = isset($planMap[$installment_plan]) ? $planMap[$installment_plan] : 0;
 
        if ($payment_status === 'installment' && $installment_total_steps > 0) {
 
            // Always read current state from DB — never trust POST for plan/schedule
            $existingRow    = $conn->query("SELECT installment_paid_count, total_service_amount, installment_plan, installment_pay_every FROM payments WHERE payment_id=$payment_id")->fetch_assoc();
            $existing_paid  = (int)($existingRow['installment_paid_count'] ?? 0);
            $existing_plan  = $existingRow['installment_plan'] ?? '';

            // Lock plan + schedule from DB once set — never allow POST to overwrite them
            if (!empty($existing_plan)) {
                $installment_plan      = $existing_plan;
                $installment_pay_every = $existingRow['installment_pay_every'] ?? $installment_pay_every;
                // Recalculate steps from locked plan
                $installment_total_steps = isset($planMap[$installment_plan]) ? $planMap[$installment_plan] : $installment_total_steps;
            }

            // Lock in total service amount on very first setup
            $total_service_amount = (float)($existingRow['total_service_amount'] ?? 0);
            if ($total_service_amount <= 0) {
                $total_service_amount = $payment_amount;
            }
 
            $amount_per_installment = round($total_service_amount / $installment_total_steps, 2);
            $due_day = ($installment_pay_every === '14th of the Month') ? 14 : 30;
 
            $inst_plan_sql  = "'" . $conn->real_escape_string($installment_plan) . "'";
            $inst_every_sql = $installment_pay_every ? "'" . $conn->real_escape_string($installment_pay_every) . "'" : 'NULL';

            // ── FIX: is_plan_setup is TRUE only when:
            //    - paid_count is 0 AND
            //    - no plan was previously saved (first-ever open of the modal)
            //    OR the user explicitly hasn't clicked "Record Payment" yet.
            //    record_first_payment=1 means the user IS recording the 1st payment.
            $is_plan_setup = ($existing_paid === 0 && $record_first_payment === 0 && empty($existing_plan));
 
            if ($is_plan_setup) {
                // ── SETUP ONLY: save plan, keep paid_count = 0 ──────────────
                // First due date = next month from today on the Pay Every day
                $first_due_obj = new DateTime();
                $first_due_obj->setDate((int)$first_due_obj->format('Y'), (int)$first_due_obj->format('m'), $due_day);
                $first_due_obj->modify('+1 month');
                $first_due_date = $first_due_obj->format('Y-m-d');
 
                $conn->query("UPDATE payments SET
                    payment_status          = 'installment',
                    payment_method          = '$payment_method',
                    payment_amount          = $payment_amount,
                    notes                   = '$notes',
                    gcash_account_name      = '$gcash_account_name',
                    gcash_number            = '$gcash_number',
                    installment_plan        = $inst_plan_sql,
                    installment_pay_every   = $inst_every_sql,
                    installment_payment_num = 1,
                    installment_total_paid  = $installment_total_steps,
                    installment_paid_count  = 0,
                    installment_amount_per  = $amount_per_installment,
                    total_service_amount    = $total_service_amount,
                    amount_paid             = 0.00,
                    remaining_balance       = $total_service_amount,
                    installment_next_due    = '$first_due_date'
                    WHERE payment_id = $payment_id");
 
            } else {
                // ── RECORD PAYMENT: increment paid_count by 1 ───────────────
                // Double-check amount — must be > 0 to record a real payment
                if ($payment_amount <= 0) {
                    header("Location: finance.php?tab=payments&error=no_amount");
                    exit;
                }
                $new_paid_count    = $existing_paid + 1;
                $amount_paid       = round($amount_per_installment * $new_paid_count, 2);
                $remaining_balance = round($total_service_amount - $amount_paid, 2);
 
                // Next due date advances one more month
                $next_due_obj = new DateTime();
                $next_due_obj->setDate((int)$next_due_obj->format('Y'), (int)$next_due_obj->format('m'), $due_day);
                $next_due_obj->modify('+' . ($new_paid_count + 1) . ' months');
                $next_due_date = $next_due_obj->format('Y-m-d');
                $next_inst_sql = "'$next_due_date'";
 
                $record_status = 'installment';
 
                // Last installment — auto-close as paid
                if ($new_paid_count >= $installment_total_steps) {
                    $record_status     = 'paid';
                    $remaining_balance = 0.00;
                    $amount_paid       = $total_service_amount;
                    $next_inst_sql     = 'NULL';
                }
 
                $conn->query("UPDATE payments SET
                    payment_status          = '$record_status',
                    payment_method          = '$payment_method',
                    payment_amount          = $amount_per_installment,
                    reference_no            = '$reference_no',
                    notes                   = '$notes',
                    gcash_account_name      = '$gcash_account_name',
                    gcash_number            = '$gcash_number',
                    installment_plan        = $inst_plan_sql,
                    installment_pay_every   = $inst_every_sql,
                    installment_payment_num = $new_paid_count,
                    installment_total_paid  = $installment_total_steps,
                    installment_paid_count  = $new_paid_count,
                    installment_amount_per  = $amount_per_installment,
                    total_service_amount    = $total_service_amount,
                    amount_paid             = $amount_paid,
                    remaining_balance       = $remaining_balance,
                    installment_next_due    = $next_inst_sql
                    WHERE payment_id = $payment_id");
 
                $payment_status = $record_status; // used by archive flag below
            }
 
        } else {
            // Non-installment: pending / overdue / paid
            $inst_plan_sql  = $installment_plan      ? "'$installment_plan'"      : 'NULL';
            $inst_every_sql = $installment_pay_every ? "'$installment_pay_every'" : 'NULL';
 
            $conn->query("UPDATE payments SET
                payment_status          = '$payment_status',
                payment_method          = '$payment_method',
                payment_amount          = $payment_amount,
                reference_no            = '$reference_no',
                notes                   = '$notes',
                gcash_account_name      = '$gcash_account_name',
                gcash_number            = '$gcash_number',
                installment_plan        = $inst_plan_sql,
                installment_pay_every   = $inst_every_sql,
                installment_payment_num = $installment_payment_num,
                installment_total_paid  = 0,
                installment_paid_count  = 0,
                amount_paid             = $payment_amount,
                remaining_balance       = 0.00
                WHERE payment_id = $payment_id");
        }
 
        // Archive flag
        if ($payment_status === 'paid') {
            $conn->query("UPDATE payments SET archived=1 WHERE payment_id=$payment_id");
        } else {
            $conn->query("UPDATE payments SET archived=0 WHERE payment_id=$payment_id");
        }

        // Clear notification badge — payment has been acted on, no need to show "Notified" anymore
        $conn->query("UPDATE payments SET last_notified_at=NULL WHERE payment_id=$payment_id");
 
        // Sync enrollment + student
        $paymentData = $conn->query("SELECT enrollment_id, student_id FROM payments WHERE payment_id=$payment_id")->fetch_assoc();
        if ($paymentData && $paymentData['enrollment_id']) {
            $enrollment_id = (int)$paymentData['enrollment_id'];
            $student_id_db = (int)$paymentData['student_id'];
 
            if (!empty($enrollment_status) && in_array($enrollment_status, ['Pending','Active','Inactive'])) {
                $enroll_status_set = $enrollment_status;
            } elseif (in_array($payment_status, ['paid','installment'])) {
                $enroll_status_set = 'Active';
            } else {
                $enroll_status_set = 'Pending';
            }
 
            $esc_es   = $conn->real_escape_string($enroll_status_set);
            $conn->query("UPDATE enrollments SET status='$esc_es' WHERE id=$enrollment_id");
            $conn->query("UPDATE students SET status='$esc_es' WHERE id=$student_id_db");
 
            $log_uid  = (int)($_SESSION['user_id'] ?? 0);
            $log_sid  = (int)($_SESSION['staff_id'] ?? 0);
            $log_role = $conn->real_escape_string($_SESSION['role'] ?? 'Superadmin');
            $log_det    = $conn->real_escape_string("Payment #$payment_id: status=$payment_status, enrollment=$enroll_status_set, method=$payment_method");
            logActivity($conn,
                'Updated Payment Record',
                'Finance Management',
                'Success',
                "Payment #$payment_id: status=$payment_status, enrollment=$enroll_status_set, method=$payment_method",
                "Payment Record #$payment_id"
            );
        }
 
        header("Location: finance.php?tab=payments&success=1");
        exit;
    }
 
    if (isset($_POST['action']) && $_POST['action'] === 'send_notification') {
        $payment_id = (int)$_POST['payment_id'];

        $paymentQuery = $conn->query("
            SELECT p.*, s.full_name as student_name, s.student_id,
                   g.guardian_name, g.email as guardian_email,
                   svc.program_name, svc.service_name
            FROM payments p
            JOIN students s ON p.student_id = s.id
            LEFT JOIN guardians g ON g.student_id = s.id
            LEFT JOIN enrollments e ON p.enrollment_id = e.id
            LEFT JOIN services svc ON e.service_id = svc.service_id
            WHERE p.payment_id = $payment_id
        ");

        if ($paymentQuery && $payment = $paymentQuery->fetch_assoc()) {
            $guardian_email = $payment['guardian_email'];

            if (empty($guardian_email)) {
                header("Location: finance.php?tab=payments&error=no_guardian_email");
                exit;
            }

            // Build amount due and due date
            $amount_due = 0;
            $due_date   = '';

            if ($payment['payment_status'] === 'installment') {
                $amount_due = (float)$payment['remaining_balance'];
                if (!empty($payment['installment_next_due'])) {
                    $due_date = date('M d, Y', strtotime($payment['installment_next_due']));
                }
            } elseif ($payment['payment_status'] === 'pending') {
                $amount_due = (float)$payment['payment_amount'];
                if (!empty($payment['payment_due_date']) && $payment['payment_due_date'] !== '0000-00-00') {
                    $due_date = date('M d, Y', strtotime($payment['payment_due_date']));
                } else {
                    $due_date_obj = new DateTime($payment['payment_date'] ?? 'now');
                    $due_date_obj->modify('+3 days');
                    $due_date = $due_date_obj->format('M d, Y');
                }
            } else {
                $amount_due = (float)$payment['payment_amount'];
                $due_date   = date('M d, Y', strtotime($payment['payment_date']));
            }

            $student_name   = $payment['student_name'];
            $program_name   = $payment['program_name'] ?: 'N/A';
            $package_name   = $payment['service_name']  ?: 'N/A';
            $payment_status = ucfirst($payment['payment_status']);

            // Guardian salutation
            $guardian_name_parts = explode(' ', trim($payment['guardian_name'] ?? ''));
            $guardian_last_name  = end($guardian_name_parts) ?: 'Guardian';

            $subject = "Kid's Journey Learning Center: Payment Reminder";

            $body = "
            <html><head><meta charset='UTF-8'></head>
            <body style='font-family:Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:40px 20px;'>
                <div style='max-width:600px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 15px 40px rgba(0,0,0,0.12);'>
                    <div style='background:linear-gradient(135deg,#dc2626 0%,#b91c1c 100%);padding:30px;text-align:center;'>
                        <h1 style='color:white;margin:0;'>Payment Reminder</h1>
                    </div>
                    <div style='padding:30px;'>
                        <p>Dear Mr/Ms " . htmlspecialchars($guardian_last_name) . ",</p>
                        <p>This is a reminder that a payment is required for the student:</p>
                        <div style='background:#f9f9f9;padding:20px;border-radius:8px;margin:20px 0;border-left:4px solid #dc2626;'>
                            <p><strong>Student Name:</strong> " . htmlspecialchars($student_name) . "</p>
                            <p><strong>Service / Program:</strong> " . htmlspecialchars($program_name) . "</p>
                            <p><strong>Package / Category:</strong> " . htmlspecialchars($package_name) . "</p>
                            <p><strong>Amount Due:</strong> &#8369;" . number_format($amount_due, 2) . "</p>
                            <p><strong>Due Date:</strong> " . htmlspecialchars($due_date) . "</p>
                            <p><strong>Payment Status:</strong> " . htmlspecialchars($payment_status) . "</p>
                        </div>
                        <p>Please settle this payment before the due date to avoid overdue status.</p>
                        <p>Thank you.</p>
                        <hr style='border:none;border-top:1px solid #ddd;margin:20px 0;'>
                        <p style='font-size:12px;color:#666;text-align:center;'>&copy; " . date('Y') . " Kid's Journey Learning Center. All rights reserved.</p>
                    </div>
                </div>
            </body></html>";

            $altBody = "Kid's Journey Learning Center: Payment Reminder

"
                     . "Dear Mr/Ms {$guardian_last_name},

"
                     . "Student: {$student_name}
Program: {$program_name}
Package: {$package_name}
"
                     . "Amount Due: PHP " . number_format($amount_due, 2) . "
Due Date: {$due_date}
"
                     . "Status: {$payment_status}

Please settle before the due date.

Thank you.";

            $emailSent = sendEmail($guardian_email, $subject, $body, $altBody);

            // Log to payment_notifications table
            $notification_type = 'manual';
            $log_status_val    = $emailSent ? 'sent' : 'failed';
            $stmt = $conn->prepare("INSERT INTO payment_notifications
                (payment_id, student_id, guardian_email, notification_type, status, sent_at)
                VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iisss", $payment_id, $payment['student_id'], $guardian_email, $notification_type, $log_status_val);
            $stmt->execute();
            $stmt->close();

            // Update last_notified_at
            if ($emailSent) {
                $conn->query("UPDATE payments SET last_notified_at=NOW() WHERE payment_id=$payment_id");
            }

            logActivity($conn,
                'Sent Payment Notification',
                'Finance Management',
                $emailSent ? 'Success' : 'Failed',
                "Payment #{$payment_id} notification sent to {$guardian_email}",
                "Payment Record #{$payment_id}"
            );

            $redirect_param = $emailSent
                ? 'notified=1&email=' . urlencode($guardian_email)
                : 'error=email_failed';
            header("Location: finance.php?tab=payments&{$redirect_param}");
            exit;
        }

        header("Location: finance.php?tab=payments&error=invalid_payment");
        exit;
    }
 
    if (isset($_POST['action']) && $_POST['action'] === 'archive_payment') {
        $payment_id = (int)$_POST['payment_id'];
        $stmt = $conn->prepare("UPDATE payments SET archived=1 WHERE payment_id=?");
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        logActivity($conn,
            'Archived Payment Record',
            'Finance Management',
            'Success',
            "Payment ID: $payment_id has been archived",
            "Payment Record #$payment_id"
        );
        header("Location: finance.php?tab=payments&archived=1");
        exit;
    }
}
 
/* =========================
   DASHBOARD STATS
========================== */
$conn->query("UPDATE payments SET archived=0 WHERE payment_status IN ('pending','installment','overdue') AND archived=1");
$totalServices = $conn->query("SELECT COUNT(*) as total FROM services")->fetch_assoc()['total'];
$totalPayments = $conn->query("SELECT COUNT(*) as total FROM payments")->fetch_assoc()['total'];
$paidCount     = $conn->query("SELECT COUNT(*) as total FROM payments WHERE payment_status='paid'")->fetch_assoc()['total'];
$pendingCount  = $conn->query("SELECT COUNT(*) as total FROM payments WHERE payment_status='pending'")->fetch_assoc()['total'];
 
/* =========================
   RECENT PAYMENTS
========================== */
$paymentStatusFilter = $_GET['payment_status'] ?? 'all';
if ($paymentStatusFilter === 'all') {
    $paymentWhere = "WHERE p.payment_status IN ('pending','installment','overdue')";
    $paymentOrder = "ORDER BY FIELD(p.payment_status,'pending','installment','overdue'), p.payment_date ASC";
} elseif ($paymentStatusFilter === 'paid') {
    $paymentWhere = "WHERE p.payment_status='paid'";
    $paymentOrder = "ORDER BY p.payment_date DESC";
} else {
    $escaped      = $conn->real_escape_string($paymentStatusFilter);
    $paymentWhere = "WHERE p.payment_status='$escaped'";
    $paymentOrder = "ORDER BY p.payment_date ASC";
}
 
$recentPayments = $conn->query("
    SELECT p.payment_id, p.payment_amount, p.payment_method,
           p.reference_no, p.payment_status, p.payment_date,
           p.payment_due_date,
           p.notes, p.gcash_account_name, p.gcash_number,
           p.installment_plan, p.installment_pay_every,
           p.installment_payment_num, p.installment_total_paid,
           p.installment_paid_count, p.total_service_amount,
           p.amount_paid, p.remaining_balance,
           s.full_name, s.student_id, s.id as student_db_id,
           e.status as enrollment_status, e.id as enrollment_id,
           p.last_notified_at,
           svc.program_name, svc.service_name,
           COALESCE(svc.price, svc2.price, (SELECT sv3.price FROM enrollments ev3 JOIN services sv3 ON sv3.service_id=ev3.service_id WHERE ev3.student_id=p.student_id ORDER BY ev3.id DESC LIMIT 1), 0) as service_price
    FROM payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN enrollments e ON p.enrollment_id = e.id
    LEFT JOIN services svc ON e.service_id = svc.service_id
    LEFT JOIN enrollments e2  ON e2.id = (SELECT id FROM enrollments WHERE student_id = p.student_id ORDER BY id DESC LIMIT 1)
    LEFT JOIN services svc2   ON svc2.service_id = e2.service_id
    $paymentWhere $paymentOrder LIMIT 10
");
 
/* =========================
   SERVICES LIST
========================== */
$serviceStatusFilter  = $_GET['service_status']  ?? 'active';
$serviceProgramFilter = $_GET['service_program'] ?? '';
$svcConditions = [];
if ($serviceStatusFilter === 'active')       $svcConditions[] = "status='active'";
elseif ($serviceStatusFilter === 'inactive') $svcConditions[] = "status='inactive'";
elseif ($serviceStatusFilter === 'archived') $svcConditions[] = "status='archived'";
else                                          $svcConditions[] = "status!='archived'";
if ($serviceProgramFilter !== '') {
    $ep = $conn->real_escape_string($serviceProgramFilter);
    $svcConditions[] = "program_name='$ep'";
}
$serviceWhere = count($svcConditions) ? "WHERE ".implode(" AND ",$svcConditions) : "";
$services     = $conn->query("SELECT * FROM services $serviceWhere ORDER BY created_at DESC");
$programNames = $conn->query("SELECT DISTINCT program_name FROM services ORDER BY program_name ASC");
$programList  = [];
while ($p = $programNames->fetch_assoc()) { $programList[] = $p['program_name']; }
?>
<!DOCTYPE html>
<html>
<head>
  <title>Finance Management</title>
  <link rel="stylesheet" href="../../assets/css/hro/hro-base.css">
  <link rel="stylesheet" href="../../assets/css/finance/finance.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
  <script src="../../assets/js/sidebar.js" defer></script>
  <style>
    .student-banner{background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#0369a1;line-height:1.6;}
    .student-banner strong{color:#0c4a6e;font-size:14px;display:block;margin-bottom:4px;}
    .pill{display:inline-block;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;margin-right:6px;}
    .pill-program{background:#e0f2fe;color:#0369a1;}
    .pill-package{background:#dbeafe;color:#1e40af;}
    .installment-notice{background:#fefce8;border:1px solid #fde047;border-radius:8px;padding:10px 14px;margin:12px 0;font-size:13px;color:#713f12;line-height:1.5;}
    .installment-notice strong{color:#92400e;}
    .gcash-hint{font-size:11px;color:#6b7280;margin-top:3px;display:block;}
    .gcash-err{color:#ef4444;font-size:11px;display:none;}
    .dyn-section{display:none;}
    .dyn-section.on{display:block;}
    .req{color:#ef4444;margin-left:2px;}

    /* Guarantee modals never block page interaction when hidden */
    .fin-modal { display: none !important; pointer-events: none !important; }
    .fin-modal.is-open { display: flex !important; pointer-events: all !important; }

    /* Toast */
    .toast-notification {
      position: fixed; bottom: 20px; right: 20px;
      padding: 12px 20px; border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      font-size: 14px; font-weight: 500; z-index: 10000;
      opacity: 0; transform: translateY(20px);
      transition: all 0.3s ease; max-width: 400px; color: #fff;
    }
    .toast-notification.show { opacity: 1; transform: translateY(0); }
    .toast-notification.success { background:#10b981; border-left:4px solid #059669; }
    .toast-notification.error   { background:#ef4444; border-left:4px solid #dc2626; }

    /* 80% zoom for Payment Management tab only */
    #payments { zoom: 0.8; }
  </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/administrator-sidebar.php'; ?>
<div class="main">
  <div class="header">
    <h1>Finance Management</h1>
    <p>Track student's payment status and enrollment</p>
  </div>
  <div class="tabs-export-row">
    <div class="tabs">
      <span class="tab-pill <?= $activeTab==='payments' ? 'active' : '' ?>" data-tab="payments">Payment Management</span>
      <span class="tab-pill <?= $activeTab==='services' ? 'active' : '' ?>" data-tab="services">Service Management</span>
    </div>
    <div class="header-right-controls">
      <button id="exportBtn" class="btn-export"><span class="export-icon">&#8595;</span> Export</button>
      <div id="serviceControls" style="display:none;gap:10px;align-items:center;">
        <button id="openServiceModal" class="btn-export" style="float:none;height:42px;"><span class="export-icon">&#10133;</span> Add Service</button>
      </div>
    </div>
  </div>
 
  <!-- Payment Tab -->
  <div class="tab-content <?= $activeTab==='payments'?'active':'' ?>" id="payments">
    <div class="card">
      <h2 class="card-title">Overview</h2>
      <div class="stats-grid">
        <div class="stat-card">
          <div><span class="stat-label">Active Services</span><h2 class="stat-number"><?= $totalServices ?></h2><span class="stat-sub">Available packages</span></div>
          <div class="stat-icon green">&#128230;</div>
        </div>
        <div class="stat-card">
          <div><span class="stat-label">Payment Records</span><h2 class="stat-number"><?= $totalPayments ?></h2><span class="stat-sub"><?= $paidCount ?> Paid, <?= $pendingCount ?> Pending</span></div>
          <div class="stat-icon orange">&#128179;</div>
        </div>
      </div>
    </div>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'no_amount'): ?>
    <div style="background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;padding:10px 16px;border-radius:8px;margin-bottom:12px;font-size:13px;">
      &#9888;&#65039; Payment amount is required and must be greater than zero. No record was saved.
    </div>
    <?php endif; ?>

    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
        <h2 class="card-title" style="margin:0;">Payment Records</h2>
        <form method="GET" style="display:flex;gap:10px;align-items:center;">
          <input type="hidden" name="tab" value="payments">
          <select name="payment_status" onchange="this.form.submit()" style="padding:8px 12px;border-radius:6px;border:1px solid #d1d5db;">
            <option value="all" <?= $paymentStatusFilter==='all'?'selected':'' ?>>All</option>
            <option value="pending" <?= $paymentStatusFilter==='pending'?'selected':'' ?>>Pending</option>
            <option value="installment" <?= $paymentStatusFilter==='installment'?'selected':'' ?>>Installment</option>
            <option value="overdue" <?= $paymentStatusFilter==='overdue'?'selected':'' ?>>Overdue</option>
            <option value="paid" <?= $paymentStatusFilter==='paid'?'selected':'' ?>>Paid</option>
          </select>
        </form>
      </div>
      <table class="finance-table payments-header-table" style="min-width:900px;">
        <colgroup><col style="width:18%"><col style="width:10%"><col style="width:9%"><col style="width:10%"><col style="width:10%"><col style="width:12%"><col style="width:22%"><col style="width:9%"></colgroup>
        <thead><tr><th>Student</th><th>Amount</th><th>Method</th><th>Reference</th><th>Status</th><th>Due Date</th><th>Payment Plan</th><th>Actions</th></tr></thead>
      </table>
      <div class="payments-scroll" style="overflow-x:auto;">
        <table class="finance-table payments-body-table" id="paymentsTable" style="min-width:900px;">
          <colgroup><col style="width:18%"><col style="width:10%"><col style="width:9%"><col style="width:10%"><col style="width:10%"><col style="width:12%"><col style="width:22%"><col style="width:9%"></colgroup>
          <tbody>
            <?php if ($recentPayments && $recentPayments->num_rows > 0): ?>
              <?php while ($row = $recentPayments->fetch_assoc()):
                $md = [
                  'payment_id'=>$row['payment_id'],'payment_status'=>$row['payment_status'],
                  'payment_method'=>$row['payment_method'],'payment_amount'=>$row['payment_amount'],
                  'reference_no'=>$row['reference_no'],'notes'=>$row['notes']??'',
                  'gcash_account_name'=>$row['gcash_account_name']??'','gcash_number'=>$row['gcash_number']??'',
                  'full_name'=>$row['full_name'],'student_id'=>$row['student_id'],
                  'enrollment_status'=>$row['enrollment_status']??'Pending',
                  'program_name'=>$row['program_name']??'','service_name'=>$row['service_name']??'',
                  'installment_plan'=>$row['installment_plan']??'',
                  'installment_pay_every'=>$row['installment_pay_every']??'',
                  'installment_payment_num'=>$row['installment_payment_num']??1,
                  'installment_total_paid'=>$row['installment_total_paid']??0,
                  'installment_paid_count'=>$row['installment_paid_count']??0,
                  'total_service_amount'=>$row['total_service_amount']??$row['payment_amount']??0,
                  'service_price'=>(float)($row['service_price']??0),
                  'principal_amount'=>(float)(
                      $row['total_service_amount'] > 0 ? $row['total_service_amount'] :
                      ($row['payment_amount'] > 0     ? $row['payment_amount'] :
                      ($row['service_price'] > 0      ? $row['service_price']  : 0))
                  ),
                  'amount_paid'=>$row['amount_paid']??0,
                  'remaining_balance'=>$row['remaining_balance']??0,
                  'last_notified_at'=>$row['last_notified_at']??'',
                ];
              ?>
              <tr>
                <td>
                  <div style="font-size:14px;font-weight:500;color:#111827;"><?= htmlspecialchars($row['full_name']) ?></div>
                  <div style="font-size:11px;color:#6b7280;margin-top:2px;"><?= htmlspecialchars($row['student_id']) ?></div>
                </td>
                <td>&#8369;<?= number_format($row['payment_amount'],2) ?></td>
                <td><?= ucfirst($row['payment_method']) ?></td>
                <td><?= htmlspecialchars($row['reference_no']) ?></td>
                <td><span class="badge <?= $row['payment_status'] ?>"><?= ucfirst($row['payment_status']) ?></span></td>
                <td>
                  <?php
                  if ($row['payment_status'] === 'installment' && !empty($row['installment_pay_every'])) {
                    $day    = ($row['installment_pay_every'] === '14th of the Month') ? 14 : 30;
                    // Next due = the NEXT unpaid payment number (paid_count + 1)
                    $nextPayNum = (int)($row['installment_paid_count'] ?? 0) + 1;
                    $due    = new DateTime();
                    $due->setDate((int)$due->format('Y'), (int)$due->format('m'), $day);
                    $due->modify('+'.$nextPayNum.' months');
                    $today    = new DateTime();
                    $today->setTime(0,0,0);
                    $due->setTime(0,0,0);
                    $diff     = (int)$today->diff($due)->format('%r%a');
                    $daysAway = $diff;
                    echo '<span style="color:#dc2626;font-weight:600;">' . $due->format('M d, Y') . '</span>';
                    if ($daysAway > 0) {
                      echo '<div style="font-size:10px;color:#9ca3af;">due in '.$daysAway.' day'.($daysAway===1?'':'s').'</div>';
                    } elseif ($daysAway === 0) {
                      echo '<div style="font-size:10px;color:#f97316;">due today</div>';
                    } else {
                      echo '<div style="font-size:10px;color:#ef4444;">overdue by '.abs($daysAway).' day'.(abs($daysAway)===1?'':'s').'</div>';
                    }
                  } elseif ($row['payment_status'] === 'pending') {
                    // Use stored due date (payment_date + 3 days, starting day after creation)
                    if (!empty($row['payment_due_date']) && $row['payment_due_date'] !== '0000-00-00') {
                        $due = new DateTime($row['payment_due_date']);
                    } else {
                        // Fallback: 3 days from payment_date
                        $due = new DateTime($row['payment_date'] ?? 'now');
                        $due->modify('+3 days');
                    }
                    $today = new DateTime(); $today->setTime(0,0,0); $due->setTime(0,0,0);
                    $diff  = (int)$today->diff($due)->format('%r%a');
                    echo '<span style="color:#dc2626;font-weight:600;">' . $due->format('M d, Y') . '</span>';
                    if ($diff > 0) {
                        echo '<div style="font-size:10px;color:#9ca3af;">due in '.$diff.' day'.($diff===1?'':'s').'</div>';
                    } elseif ($diff === 0) {
                        echo '<div style="font-size:10px;color:#f97316;">due today</div>';
                    } else {
                        echo '<div style="font-size:10px;color:#ef4444;">overdue by '.abs($diff).' day'.(abs($diff)===1?'':'s').'</div>';
                    }
                  } else {
                    echo date("M d, Y", strtotime($row['payment_date']));
                  }
                  ?>
                </td>
                <td>
                  <?php
                  if ($row['payment_status'] === 'installment' && !empty($row['installment_plan'])) {
                    $paid = (int)($row['installment_paid_count'] ?? 0);
                    $tot  = (int)($row['installment_total_paid'] ?? 0);
                    $rem  = (float)($row['remaining_balance'] ?? 0);
                    // Show the NEXT payment number due (paid_count + 1), not the last recorded one
                    $n    = $paid + 1;
                    $v    = $n % 100;
                    $s    = ['th','st','nd','rd'];
                    $suf  = $s[($v-20)%10] ?? ($s[$v] ?? $s[0]);
                    echo '<span style="font-size:12px;color:#374151;line-height:1.6;display:block;">';
                    echo htmlspecialchars($row['installment_plan']);
                    if (!empty($row['installment_pay_every'])) {
                      echo ' &bull; ' . htmlspecialchars($row['installment_pay_every']);
                    }
                    if ($paid >= $tot && $tot > 0) {
                      // All payments done
                      echo '<br><span style="color:#16a34a;font-size:11px;">Fully Paid</span>';
                    } else {
                      echo '<br>' . $n . $suf . ' Payment';
                      if ($rem > 0) {
                        echo '<br><span style="color:#dc2626;font-size:11px;">Remaining: &#8369;' . number_format($rem,2) . '</span>';
                      }
                    }
                    echo '</span>';
                  } else {
                    echo '<span style="font-size:12px;color:#9ca3af;">&#8212;</span>';
                  }
                  ?>
                </td>
                <td>
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;align-items:start;width:fit-content;">
                    <!-- Edit button -->
                    <?php if ($row['payment_status'] !== 'paid'): ?>
                    <button class="action-btn edit-payment-btn" title="Edit"
                      data-payment='<?= htmlspecialchars(json_encode($md, JSON_HEX_TAG|JSON_HEX_APOS|JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>'>&#9999;&#65039;</button>
                    <?php else: ?>
                    <span></span>
                    <?php endif; ?>
                    <!-- Notify button -->
                    <div style="display:flex;flex-direction:column;align-items:center;">
                      <button class="action-btn notify-btn" title="Send Notification"
                        data-payment-id="<?= $row['payment_id'] ?>"
                        data-student="<?= htmlspecialchars($row['full_name']) ?>"
                        data-status="<?= $row['payment_status'] ?>"
                        data-program="<?= htmlspecialchars($row['program_name'] ?? '') ?>"
                        data-service="<?= htmlspecialchars($row['service_name'] ?? '') ?>"
                        data-amount="<?= number_format($row['payment_amount'], 2) ?>"
                        data-notified="<?= !empty($row['last_notified_at']) ? date('M d, Y g:i A', strtotime($row['last_notified_at'])) : '' ?>">&#128233;</button>
                      <?php if (!empty($row['last_notified_at']) && strtotime($row['last_notified_at']) >= strtotime('-24 hours')): ?>
                      <span style="font-size:10px;color:#6b7280;margin-top:2px;">Notified</span>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="8" style="text-align:center;">No recent payments found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($recentPayments && $recentPayments->num_rows >= 10 && (!isset($_GET['show']) || $_GET['show'] !== 'all')): ?>
        <div style="text-align:center;margin-top:10px;"><a href="finance.php?tab=payments&show=all" class="btn">See More</a></div>
      <?php endif; ?>
    </div>
  </div>
 
  <!-- Services Tab -->
  <div class="tab-content <?= $activeTab==='services'?'active':'' ?>" id="services">
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
        <h2 class="card-title" style="margin:0;">Service Management</h2>
        <form method="GET" style="display:flex;gap:10px;align-items:center;">
          <input type="hidden" name="tab" value="services">
          <select name="service_status" onchange="this.form.submit()" style="padding:8px 12px;border-radius:6px;border:1px solid #d1d5db;">
            <option value="active" <?= $serviceStatusFilter==='active'?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $serviceStatusFilter==='inactive'?'selected':'' ?>>Inactive</option>
            <option value="archived" <?= $serviceStatusFilter==='archived'?'selected':'' ?>>Archived</option>
            <option value="all" <?= $serviceStatusFilter==='all'?'selected':'' ?>>All</option>
          </select>
          <select name="service_program" onchange="this.form.submit()" style="padding:8px 12px;border-radius:6px;border:1px solid #d1d5db;">
            <option value="" <?= $serviceProgramFilter===''?'selected':'' ?>>All Services</option>
            <?php foreach($programList as $prog): ?>
              <option value="<?= htmlspecialchars($prog) ?>" <?= $serviceProgramFilter===$prog?'selected':'' ?>><?= htmlspecialchars($prog) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      <table class="finance-table svc-header-table">
        <colgroup><col style="width:22%"><col style="width:20%"><col style="width:12%"><col style="width:16%"><col style="width:14%"><col style="width:16%"></colgroup>
        <thead><tr><th>Service/Program</th><th>Package/Category</th><th>Rate</th><th>Frequency</th><th>Status</th><th>Actions</th></tr></thead>
      </table>
      <div class="svc-scroll">
        <table class="finance-table svc-body-table" id="servicesTable">
          <colgroup><col style="width:22%"><col style="width:20%"><col style="width:12%"><col style="width:16%"><col style="width:14%"><col style="width:16%"></colgroup>
          <tbody>
            <?php if ($services && $services->num_rows > 0): ?>
              <?php while ($svc = $services->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($svc['program_name']) ?></td>
                <td><?= htmlspecialchars($svc['service_name']) ?></td>
                <td>&#8369;<?= number_format($svc['price'],2) ?></td>
                <td><?= htmlspecialchars($svc['frequency']) ?></td>
                <td><span class="badge <?= htmlspecialchars($svc['status']??'active') ?>"><?= ucfirst($svc['status']??'active') ?></span></td>
                <td>
                  <?php if (($svc['status']??'') !== 'archived'): ?>
                  <button class="action-btn edit-svc-btn"
                    data-id="<?= $svc['service_id'] ?>" data-program="<?= htmlspecialchars($svc['program_name']) ?>"
                    data-service="<?= htmlspecialchars($svc['service_name']) ?>" data-desc="<?= htmlspecialchars($svc['description']??'') ?>"
                    data-price="<?= $svc['price'] ?>" data-frequency="<?= htmlspecialchars($svc['frequency']) ?>"
                    data-status="<?= htmlspecialchars($svc['status']??'active') ?>" title="Edit">&#9999;&#65039;</button>
                  <button class="action-btn archive-svc-btn"
                    data-id="<?= $svc['service_id'] ?>"
                    data-name="<?= htmlspecialchars($svc['program_name'].' - '.$svc['service_name']) ?>"
                    title="Archive">&#128436;&#65039;</button>
                  <?php else: ?>
                  <button class="action-btn restore-svc-btn"
                    data-id="<?= $svc['service_id'] ?>"
                    data-name="<?= htmlspecialchars($svc['program_name'].' - '.$svc['service_name']) ?>"
                    title="Restore" style="color:#166534;">&#9851;&#65039;</button>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="6" style="text-align:center;">No services found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
 
<!-- NOTIFY CONFIRM MODAL -->
<div class="fin-modal" id="notifyConfirmModal" style="display:none;">
  <div class="fin-modal-content" style="width:420px;">
    <div class="fin-modal-header" style="background:#1d4ed8;color:#fff;padding:12px 16px;border-radius:8px 8px 0 0;">
      <h2 style="margin:0;font-size:16px;">Send Payment Notification</h2>
      <span class="close-btn" style="color:#fff;" onclick="closeNotifyModal()">&times;</span>
    </div>
    <div style="padding:20px 24px 8px;">
      <p id="notifyConfirmMessage" style="font-size:15px;color:#374151;margin:0 0 12px;"></p>
      <div id="notifyStudentInfo" style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:10px 14px;font-size:13px;color:#0369a1;"></div>
    </div>
    <div class="fin-modal-footer">
      <button type="button" class="btn cancel-btn" onclick="closeNotifyModal()">Cancel</button>
      <button type="button" id="notifyConfirmBtn" class="btn submit-btn" style="background:#1d4ed8;">Send Notification</button>
    </div>
  </div>
</div>

<!-- ADD SERVICE MODAL -->
<div class="fin-modal" id="addServiceModal" style="display:none;">
  <div class="fin-modal-content">
    <div class="fin-modal-header"><h2>Add New Service</h2><span class="close-btn" id="closeServiceModal">&times;</span></div>
    <form method="POST" action="add-service.php">
      <div class="form-group"><label>Service/Program Name *</label><input type="text" name="program" required placeholder="e.g. Sensory Motor Learning"></div>
      <div class="form-group"><label>Package/Category *</label><input type="text" name="service" required placeholder="e.g. SML Package A"></div>
      <div class="form-group"><label>Description</label><textarea name="description"></textarea></div>
      <div class="form-group"><label>Rate (&#8369;) *</label><input type="number" name="rate" step="0.01" required></div>
      <div class="form-group"><label>Frequency</label>
        <select name="frequency" required>
          <option value="One Time">One Time</option><option value="Per Hour">Per Hour</option>
          <option value="Per 2 Hours">Per 2 Hours</option><option value="1x a Week">1x a Week</option>
          <option value="2x a Week">2x a Week</option><option value="3x a Week">3x a Week</option>
          <option value="4x a Week">4x a Week</option><option value="Per Session">Per Session</option>
        </select></div>
      <div class="fin-modal-footer">
        <button type="button" class="btn cancel-btn" id="cancelService">Cancel</button>
        <button type="submit" class="btn submit-btn">Create</button>
      </div>
    </form>
  </div>
</div>
 
<!-- CONFIRM MODAL -->
<div class="fin-modal" id="confirmModal" style="display:none;">
  <div class="fin-modal-content">
    <div class="fin-modal-header" style="background:#ef4444;color:#fff;padding:10px;border-radius:8px 8px 0 0;"><h2>CONFIRMATION</h2></div>
    <div style="padding:20px;"><p>Are you sure you want to create this service?</p></div>
    <div class="fin-modal-footer">
      <button type="button" class="btn cancel-btn" id="cancelConfirm">Cancel</button>
      <button type="button" class="btn submit-btn" id="confirmCreate">Confirm</button>
    </div>
  </div>
</div>
 
<!-- PAYMENT STATUS CONFIRM MODAL -->
<div class="fin-modal" id="paymentConfirmModal" style="display:none;">
  <div class="fin-modal-content" style="width:400px;">
    <div class="fin-modal-header" id="paymentConfirmHeader">
      <h2 id="paymentConfirmTitle">Confirm Status Change</h2>
      <span class="close-btn" onclick="closePaymentConfirmModal()">&times;</span>
    </div>
    <div style="padding:20px 24px 8px;"><p id="paymentConfirmMessage" style="font-size:15px;color:#374151;margin:0;"></p></div>
    <div class="fin-modal-footer">
      <button type="button" class="btn cancel-btn" onclick="closePaymentConfirmModal()">Cancel</button>
      <button type="button" class="submit-btn" id="paymentConfirmBtn" onclick="submitEditPayment()">Confirm</button>
    </div>
  </div>
</div>
 
<!-- EDIT PAYMENT MODAL -->
<div class="fin-modal" id="editPaymentModal" style="display:none;">
  <div class="fin-modal-content" style="width:500px;max-width:96vw;max-height:90vh;overflow-y:auto;">
    <div class="fin-modal-header">
      <h2>Edit Payment Record</h2>
      <span class="close-btn" onclick="closeEditPaymentModal()">&times;</span>
    </div>
    <form method="POST" id="editPaymentForm">
      <input type="hidden" name="action" value="edit_payment">
      <input type="hidden" name="payment_id" id="editPaymentId">
      <!-- FIX: flag to distinguish plan-setup from recording 1st payment -->
      <input type="hidden" name="record_first_payment" id="recordFirstPaymentFlag" value="0">
 
      <!-- Student Banner -->
      <div class="student-banner">
        <strong id="bannerStudentName"></strong>
        <span class="pill pill-program" id="bannerProgram"></span>
        <span class="pill pill-package" id="bannerPackage"></span>
      </div>
 
      <!-- Amount -->
      <div class="form-group">
        <label>Amount (&#8369;)<span class="req">*</span></label>
        <input type="number" name="payment_amount" id="editPaymentAmount" step="0.01" min="0.01" required placeholder="Enter amount received"
               style="background:#f9fafb;font-weight:600;color:#111827;">
        <span style="font-size:11px;color:#6b7280;margin-top:3px;display:block;" id="amountHint"></span>
      </div>
 
      <!-- Method + GCash — only shown when actually recording a payment -->
      <div id="paymentMethodSection" class="dyn-section">
      <div class="form-group">
        <label>Method<span class="req">*</span></label>
        <select name="payment_method" id="editPaymentMethod" onchange="handleMethodChange(this.value)">
          <option value="cash">Cash</option>
          <option value="gcash">GCash</option>
        </select>
      </div>

      <!-- GCASH FIELDS -->
      <div id="gcashSection" class="dyn-section">
        <div class="form-group">
          <label>GCash Reference Number<span class="req">*</span></label>
          <input type="text" name="gcash_ref" id="editGcashRef" maxlength="14" autocomplete="off"
                 placeholder="Twelve-digit reference number..."
                 oninput="formatGcashRef(this)">
          <span class="gcash-hint">Format: XXX XXX XXXXXX (12 digits, spaces added automatically)</span>
          <span id="gcashRefError" class="gcash-err">Reference number must be exactly 12 digits.</span>
        </div>
        <div class="form-group">
          <label>GCash Account Name</label>
          <input type="text" name="gcash_account_name" id="editGcashName" placeholder="Account holder name">
        </div>
        <div class="form-group">
          <label>GCash Number</label>
          <input type="text" name="gcash_number" id="editGcashNumber" placeholder="09XXXXXXXXX">
        </div>
      </div>
      </div><!-- /paymentMethodSection -->
 
      <!-- Payment Status -->
      <div class="form-group">
        <label>Payment Status<span class="req">*</span></label>
        <select name="payment_status" id="editPaymentStatus" onchange="handleStatusChange(this.value)" required>
          <option value="pending">Pending</option>
          <option value="paid">Paid</option>
          <option value="overdue">Overdue</option>
          <option value="installment">Installment</option>
        </select>
      </div>
 
      <!-- INSTALLMENT FIELDS -->
      <div id="installmentSection" class="dyn-section">
        <div class="form-group">
          <label>Installment Plan<span class="req">*</span></label>
          <select name="installment_plan" id="editInstallmentPlan" onchange="onPlanChange()">
            <option value="">&#8212; Select Plan &#8212;</option>
            <option value="1 Month">1 Month</option>
            <option value="2 Months">2 Months</option>
            <option value="3 Months">3 Months</option>
          </select>
        </div>
        <div class="form-group">
          <label>Pay Every<span class="req">*</span></label>
          <select name="installment_pay_every" id="editPayEvery" onchange="updateInstallmentNotice()">
            <option value="">&#8212; Select Schedule &#8212;</option>
            <option value="14th of the Month">14th of the Month</option>
            <option value="30th of the Month">30th of the Month</option>
          </select>
        </div>
        <!-- Payment number is auto-determined from paid_count — hidden, not shown to user -->
        <input type="hidden" name="installment_payment_num" id="editPaymentNum" value="1">
        <div class="installment-notice" id="installmentNotice" style="display:none;"></div>
      </div>
 
      <!-- Enrollment Status -->
      <div class="form-group">
        <label>Enrollment Status <span style="font-size:11px;color:#6b7280;font-weight:400;">(syncs with HR Student Records)</span></label>
        <select name="enrollment_status" id="editEnrollmentStatus">
          <option value="Pending">Pending</option>
          <option value="Active">Active</option>
          <option value="Inactive">Inactive</option>
        </select>
      </div>
 
      <!-- Notes -->
      <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" id="editNotes" rows="2" placeholder="Optional remarks..." style="resize:vertical;"></textarea>
      </div>
 
      <div class="fin-modal-footer">
        <button type="button" class="btn cancel-btn" onclick="closeEditPaymentModal()">Cancel</button>
        <button type="submit" class="btn submit-btn">Record Payment</button>
      </div>
    </form>
  </div>
</div>
 
<!-- EDIT SERVICE MODAL -->
<div class="fin-modal" id="editServiceModal" style="display:none;">
  <div class="fin-modal-content">
    <div class="fin-modal-header"><h2>Edit Service</h2><span class="close-btn" onclick="closeEditServiceModal()">&times;</span></div>
    <form method="POST" id="editServiceForm">
      <input type="hidden" name="action" value="edit_service">
      <input type="hidden" name="service_id" id="editSvcId">
      <div class="form-group"><label>Service/Program Name *</label><input type="text" name="program_name" id="editSvcProgram" required></div>
      <div class="form-group"><label>Package/Category *</label><input type="text" name="service_name" id="editSvcService" required></div>
      <div class="form-group"><label>Description</label><textarea name="description" id="editSvcDesc"></textarea></div>
      <div class="form-group"><label>Rate (&#8369;) *</label><input type="number" name="price" id="editSvcPrice" step="0.01" required></div>
      <div class="form-group"><label>Frequency</label>
        <select name="frequency" id="editSvcFrequency" required>
          <option value="One Time">One Time</option><option value="Per Hour">Per Hour</option>
          <option value="Per 2 Hours">Per 2 Hours</option><option value="1x a Week">1x a Week</option>
          <option value="2x a Week">2x a Week</option><option value="3x a Week">3x a Week</option>
          <option value="4x a Week">4x a Week</option><option value="Per Session">Per Session</option>
        </select></div>
      <div class="form-group"><label>Status</label>
        <select name="status" id="editSvcStatus" required>
          <option value="active">Active</option><option value="inactive">Inactive</option>
        </select></div>
      <div class="fin-modal-footer">
        <button type="button" class="btn cancel-btn" onclick="closeEditServiceModal()">Cancel</button>
        <button type="submit" class="btn submit-btn">Save Changes</button>
      </div>
    </form>
  </div>
</div>
 
<!-- ARCHIVE SERVICE MODAL -->
<div class="fin-modal" id="archiveServiceModal" style="display:none;">
  <div class="fin-modal-content" style="width:380px;">
    <div class="fin-modal-header" style="background:#f59e0b;color:#fff;padding:12px 16px;border-radius:8px 8px 0 0;">
      <h2 style="margin:0;font-size:16px;">Archive Service</h2>
      <span class="close-btn" style="color:#fff;" onclick="closeArchiveServiceModal()">&times;</span>
    </div>
    <div style="padding:20px 24px 8px;"><p id="archiveSvcMessage" style="font-size:15px;color:#374151;margin:0;"></p></div>
    <div class="fin-modal-footer">
      <button type="button" class="btn cancel-btn" onclick="closeArchiveServiceModal()">Cancel</button>
      <button type="button" id="archiveSvcConfirmBtn" class="btn submit-btn" style="background:#f59e0b;">Archive</button>
    </div>
  </div>
</div>
 
<!-- RESTORE SERVICE MODAL -->
<div class="fin-modal" id="restoreServiceModal" style="display:none;">
  <div class="fin-modal-content" style="width:380px;">
    <div class="fin-modal-header" style="background:#166534;color:#fff;padding:12px 16px;border-radius:8px 8px 0 0;">
      <h2 style="margin:0;font-size:16px;">Restore Service</h2>
      <span class="close-btn" style="color:#fff;" onclick="closeRestoreServiceModal()">&times;</span>
    </div>
    <div style="padding:20px 24px 8px;"><p id="restoreSvcMessage" style="font-size:15px;color:#374151;margin:0;"></p></div>
    <div class="fin-modal-footer">
      <button type="button" class="btn cancel-btn" onclick="closeRestoreServiceModal()">Cancel</button>
      <button type="button" id="restoreSvcConfirmBtn" class="btn submit-btn" style="background:#166534;">Restore</button>
    </div>
  </div>
</div>
 
<!-- SERVICE SUCCESS MODAL -->
<div class="fin-modal" id="svcSuccessModal" style="display:none;">
  <div class="fin-modal-content" style="width:360px;text-align:center;">
    <div class="fin-modal-header" style="background:#166534;color:#fff;padding:12px 16px;border-radius:8px 8px 0 0;justify-content:center;">
      <h2 style="margin:0;font-size:16px;" id="svcSuccessTitle">Success</h2>
    </div>
    <div style="padding:24px;"><div style="font-size:40px;margin-bottom:12px;">&#9989;</div>
      <p id="svcSuccessMessage" style="font-size:15px;color:#374151;margin:0;"></p>
    </div>
    <div class="fin-modal-footer" style="justify-content:center;">
      <button type="button" class="btn submit-btn" style="background:#166534;" onclick="closeSvcSuccessModal()">OK</button>
    </div>
  </div>
</div>
 
<script>
/* TAB SWITCHING */
function showTab(t){
  document.querySelectorAll('.tab-content').forEach(e=>e.classList.remove('active'));
  document.getElementById(t).classList.add('active');
  document.querySelectorAll('.tab-pill').forEach(e=>e.classList.remove('active'));
  document.querySelector('[data-tab="'+t+'"]').classList.add('active');
  var sc=document.getElementById('serviceControls');
  if(t==='payments'){sc.style.display='none';}
  else{sc.style.display='flex';sc.style.gap='10px';sc.style.alignItems='center';}
  localStorage.setItem('activeTab',t);
}
function setTab(t){showTab(t);}
window.addEventListener('DOMContentLoaded',function(){
  showTab(localStorage.getItem('activeTab')||'<?= $activeTab ?>');
  // Tab pill click listeners — avoids inline onclick timing issues
  document.querySelectorAll('.tab-pill').forEach(function(pill){
    pill.addEventListener('click', function(){ setTab(this.dataset.tab); });
  });

  /* PAYMENT ACTION BUTTONS — use event delegation to avoid inline onclick issues */
  document.addEventListener('click', function(e){
    var editBtn = e.target.closest('.edit-payment-btn');
    if(editBtn){
      var data = JSON.parse(editBtn.getAttribute('data-payment'));
      openEditPaymentModal(data);
      return;
    }
    var notifyBtn = e.target.closest('.notify-btn');
    if(notifyBtn){
      sendNotification(notifyBtn.getAttribute('data-payment-id'), notifyBtn);
      return;
    }
  });

  /* ADD SERVICE */
  var serviceModal=document.getElementById('addServiceModal');
  var confirmModal=document.getElementById('confirmModal');
  if(document.getElementById('openServiceModal'))
    document.getElementById('openServiceModal').onclick=()=>serviceModal.classList.add('is-open');
  if(document.getElementById('closeServiceModal'))
    document.getElementById('closeServiceModal').onclick=()=>serviceModal.classList.remove('is-open');
  if(document.getElementById('cancelService'))
    document.getElementById('cancelService').onclick=()=>serviceModal.classList.remove('is-open');
  if(document.querySelector('#addServiceModal form'))
    document.querySelector('#addServiceModal form').addEventListener('submit',function(e){
      e.preventDefault();serviceModal.classList.remove('is-open');confirmModal.classList.add('is-open');
    });
  if(document.getElementById('cancelConfirm'))
    document.getElementById('cancelConfirm').onclick=()=>{confirmModal.classList.remove('is-open');serviceModal.classList.add('is-open');};
  if(document.getElementById('confirmCreate'))
    document.getElementById('confirmCreate').onclick=()=>{confirmModal.classList.remove('is-open');document.querySelector('#addServiceModal form').submit();};
});
 
/* EXPORT + ALL DOM BINDINGS */
document.addEventListener('DOMContentLoaded', function(){


document.getElementById('exportBtn').addEventListener('click',function(){
  var at=localStorage.getItem('activeTab')||'payments';
  var {jsPDF}=window.jspdf;
  var now=new Date(),ts=now.toLocaleDateString('en-US',{month:'2-digit',day:'2-digit',year:'numeric'})+' '+now.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit',hour12:true});
  var doc=new jsPDF(),teal=[22,160,133];
  if(at==='payments'){
    var h=[],r=[];
    document.querySelectorAll('.payments-header-table thead th:not(:last-child)').forEach(th=>h.push(th.innerText.trim()));
    document.querySelectorAll('#paymentsTable tbody tr').forEach(tr=>{var row=[];tr.querySelectorAll('td').forEach((td,i)=>{if(i<tr.cells.length-1)row.push(td.innerText.trim().replace(/\u20b1/g,'PHP '));});r.push(row);});
    doc.setFontSize(14);doc.setTextColor(22,160,133);doc.text('Payment Records',14,16);
    doc.setFontSize(9);doc.setTextColor(100);doc.text('Generated: '+ts,14,23);
    doc.autoTable({head:[h],body:r,startY:28,theme:'grid',headStyles:{fillColor:teal,textColor:255,fontStyle:'bold'},styles:{fontSize:9,cellPadding:4},alternateRowStyles:{fillColor:[240,253,250]}});
    doc.save('payment-records.pdf');
  } else {
    var h=[],r=[];
    document.querySelectorAll('.svc-header-table thead th:not(:last-child)').forEach(th=>h.push(th.innerText.trim()));
    document.querySelectorAll('#servicesTable tbody tr').forEach(tr=>{var row=[];tr.querySelectorAll('td').forEach((td,i)=>{if(i<tr.cells.length-1)row.push(td.innerText.trim().replace(/\u20b1/g,'PHP '));});r.push(row);});
    doc.setFontSize(14);doc.setTextColor(22,160,133);doc.text('Service Management Report',14,16);
    doc.setFontSize(9);doc.setTextColor(100);doc.text('Generated: '+ts,14,23);
    doc.autoTable({head:[h],body:r,startY:28,theme:'grid',headStyles:{fillColor:teal,textColor:255,fontStyle:'bold'},styles:{fontSize:9,cellPadding:4},alternateRowStyles:{fillColor:[240,253,250]}});
    doc.save('service-management.pdf');
  }
});
 
/* EDIT SERVICE */
document.querySelectorAll('.edit-svc-btn').forEach(function(btn){
  btn.addEventListener('click',function(){
    document.getElementById('editSvcId').value=this.dataset.id;
    document.getElementById('editSvcProgram').value=this.dataset.program;
    document.getElementById('editSvcService').value=this.dataset.service;
    document.getElementById('editSvcDesc').value=this.dataset.desc;
    document.getElementById('editSvcPrice').value=this.dataset.price;
    document.getElementById('editSvcFrequency').value=this.dataset.frequency;
    document.getElementById('editSvcStatus').value=this.dataset.status||'active';
    document.getElementById('editServiceModal').classList.add('is-open');
  });
});
function closeEditServiceModal(){document.getElementById('editServiceModal').classList.remove('is-open');}
 
/* ARCHIVE/RESTORE SERVICE */
document.querySelectorAll('.archive-svc-btn').forEach(function(btn){
  btn.addEventListener('click',function(){
    var id=this.dataset.id,name=this.dataset.name;
    document.getElementById('archiveSvcMessage').textContent='Are you sure you want to archive "'+name+'"? It will be removed from the active list.';
    document.getElementById('archiveSvcConfirmBtn').onclick=function(){
      var f=document.createElement('form');f.method='POST';f.action='finance.php?tab=services';
      [['action','archive_service'],['service_id',id]].forEach(function(p){var i=document.createElement('input');i.type='hidden';i.name=p[0];i.value=p[1];f.appendChild(i);});
      document.body.appendChild(f);f.submit();
    };
    document.getElementById('archiveServiceModal').classList.add('is-open');
  });
});
function closeArchiveServiceModal(){document.getElementById('archiveServiceModal').classList.remove('is-open');}
 
document.querySelectorAll('.restore-svc-btn').forEach(function(btn){
  btn.addEventListener('click',function(){
    var id=this.dataset.id,name=this.dataset.name;
    document.getElementById('restoreSvcMessage').textContent='Restore "'+name+'" back to Active status?';
    document.getElementById('restoreSvcConfirmBtn').onclick=function(){
      var f=document.createElement('form');f.method='POST';f.action='finance.php?tab=services&service_status=archived';
      [['action','restore_service'],['service_id',id]].forEach(function(p){var i=document.createElement('input');i.type='hidden';i.name=p[0];i.value=p[1];f.appendChild(i);});
      document.body.appendChild(f);f.submit();
    };
    document.getElementById('restoreServiceModal').classList.add('is-open');
  });
});
function closeRestoreServiceModal(){document.getElementById('restoreServiceModal').classList.remove('is-open');}
function closeSvcSuccessModal(){document.getElementById('svcSuccessModal').classList.remove('is-open');}
 
}); // end DOMContentLoaded

<?php if(isset($_GET['svc_restored'])): ?>
window.addEventListener('load',function(){
  document.getElementById('svcSuccessTitle').textContent='Service Restored';
  document.getElementById('svcSuccessMessage').textContent='The service has been restored to Active status.';
  document.getElementById('svcSuccessModal').classList.add('is-open');
  history.replaceState(null,'','finance.php?tab=services&service_status=archived');
});
<?php endif; ?>
<?php if(isset($_GET['svc_updated'])): ?>
window.addEventListener('load',function(){
  document.getElementById('svcSuccessTitle').textContent='Service Updated';
  document.getElementById('svcSuccessMessage').textContent='The service has been successfully updated.';
  document.getElementById('svcSuccessModal').classList.add('is-open');
  history.replaceState(null,'','finance.php?tab=services');
});
<?php endif; ?>
<?php if(isset($_GET['svc_archived'])): ?>
window.addEventListener('load',function(){
  document.getElementById('svcSuccessTitle').textContent='Service Archived';
  document.getElementById('svcSuccessMessage').textContent='The service has been successfully archived.';
  document.getElementById('svcSuccessModal').classList.add('is-open');
  history.replaceState(null,'','finance.php?tab=services');
});
<?php endif; ?>

/* =============================================
   EDIT PAYMENT MODAL — dynamic
============================================= */
function openEditPaymentModal(data){
  document.getElementById('editPaymentId').value=data.payment_id;
  document.getElementById('bannerStudentName').textContent=data.full_name+' ('+data.student_id+')';
  document.getElementById('bannerProgram').textContent=data.program_name||'—';
  document.getElementById('bannerPackage').textContent=data.service_name||'—';
  var amtField = document.getElementById('editPaymentAmount');
  var amtHint  = document.getElementById('amountHint');

  // Amount is ALWAYS read-only — pulled from principal (total_service_amount).
  // For installment: divide by number of steps to show per-installment amount.
  // For all others: show the full principal amount.
  // principal_amount is resolved server-side — always correct
  var principalAmt = parseFloat(data.principal_amount || data.total_service_amount || data.payment_amount || data.service_price || 0);
  if(data.payment_status === 'installment' && data.installment_plan){
    var planMap2 = {'1 Month':1,'2 Months':2,'3 Months':3};
    var steps2   = planMap2[data.installment_plan] || 1;
    principalAmt = Math.round((principalAmt / steps2) * 100) / 100;
    amtHint.textContent = 'Amount per installment — fixed from service price.';
  } else {
    amtHint.textContent = 'Principal amount — fixed from service price.';
  }
  amtField.value             = principalAmt.toFixed(2);
  amtField.readOnly          = true;
  amtField.style.background  = '#f9fafb';
  amtField.style.cursor      = 'not-allowed';
  amtField.style.fontWeight  = '600';
 
  var method=(data.payment_method||'cash').toLowerCase();
  document.getElementById('editPaymentMethod').value=method;
  handleMethodChange(method);
 
  if(method==='gcash'){
    document.getElementById('editGcashRef').value=fmtRef((data.reference_no||'').replace(/\s/g,''));
    document.getElementById('editGcashName').value=data.gcash_account_name||'';
    document.getElementById('editGcashNumber').value=data.gcash_number||'';
  }
 
  var status=data.payment_status||'pending';
  document.getElementById('editPaymentStatus').value=status;
  handleStatusChange(status);
 
  if(status==='installment'){
    document.getElementById('editInstallmentPlan').value=data.installment_plan||'';
    document.getElementById('editPayEvery').value=data.installment_pay_every||'';
    var paidCount = parseInt(data.installment_paid_count||0);
    var nextPayNum = paidCount + 1;
    document.getElementById('editPaymentNum').value = nextPayNum;
    // Store balance data for notice box
    window._instData = {
      total_service_amount:   parseFloat(data.principal_amount||data.total_service_amount||data.payment_amount||data.service_price||0),
      amount_paid:            parseFloat(data.amount_paid||0),
      remaining_balance:      parseFloat(data.remaining_balance||0),
      installment_paid_count: paidCount,
      next_payment_num:       nextPayNum,
      installment_plan:       data.installment_plan||'',
      service_price:          parseFloat(data.service_price||0),
    };
    updateInstallmentNotice();
    refreshMethodSection(); // re-evaluate method visibility now that _instData is set
  }
 
  document.getElementById('editEnrollmentStatus').value=data.enrollment_status||'Pending';
  document.getElementById('editNotes').value=data.notes||'';
  document.getElementById('editPaymentModal').classList.add('is-open');
}
 
function handleMethodChange(m){
  document.getElementById('gcashSection').classList.toggle('on', m === 'gcash');
}
 
function handleStatusChange(s){
  var show = s === 'installment';
  document.getElementById('installmentSection').classList.toggle('on', show);

  // For installment: only show method section if plan is already saved (recording payment)
  // For paid: always show method section
  // For pending/overdue: never show method section
  var inst       = window._instData || {};
  var planSaved  = inst.installment_plan && inst.installment_plan !== '';
  var isRecording = (s === 'installment' && planSaved) || s === 'paid';

  document.getElementById('paymentMethodSection').classList.toggle('on', isRecording);
  if(!isRecording){
    document.getElementById('gcashSection').classList.remove('on');
  } else {
    handleMethodChange(document.getElementById('editPaymentMethod').value);
  }
  if(show) updateInstallmentNotice();
}

// Called after _instData is set to re-evaluate method section visibility
function refreshMethodSection(){
  handleStatusChange(document.getElementById('editPaymentStatus').value);
}
 
function fmtRef(d){
  d=d.replace(/\D/g,'').slice(0,12);
  var o='';
  if(d.length>0) o+=d.slice(0,3);
  if(d.length>3) o+=' '+d.slice(3,6);
  if(d.length>6) o+=' '+d.slice(6,12);
  return o;
}
 
function formatGcashRef(inp){
  inp.value=fmtRef(inp.value);
  var clean=inp.value.replace(/\s/g,'');
  var err=document.getElementById('gcashRefError');
  err.style.display=(clean.length>0&&clean.length<12)?'block':'none';
}
 
function onPlanChange(){
  var paid = parseInt((window._instData||{}).installment_paid_count||0);
  var nextNum = paid + 1;
  document.getElementById('editPaymentNum').value = nextNum;
  updateInstallmentNotice();
}
 
function getNextDueDate(payEvery,payNum){
  var day = payEvery==='14th of the Month' ? 14 : 30;
  var now = new Date();
  var d   = new Date(now.getFullYear(), now.getMonth() + payNum, day);
  return d.toLocaleDateString('en-US',{month:'long',day:'numeric',year:'numeric'});
}
 
function getDaysUntil(payEvery,payNum){
  var day = payEvery==='14th of the Month' ? 14 : 30;
  var now = new Date(); now.setHours(0,0,0,0);
  var due = new Date(now.getFullYear(), now.getMonth() + payNum, day);
  var diff = Math.round((due - now) / (1000*60*60*24));
  return diff;
}
 
function fmt(n){return '₱'+parseFloat(n).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});}
 
function updateInstallmentNotice(){
  var plan      = document.getElementById('editInstallmentPlan').value;
  var every     = document.getElementById('editPayEvery').value;
  var num       = parseInt(document.getElementById('editPaymentNum').value||0);
  var noticeEl  = document.getElementById('installmentNotice');
  var planMap   = {'1 Month':1,'2 Months':2,'3 Months':3};
  var totalSteps= planMap[plan]||0;
  if(!plan||!every||!num||!totalSteps){noticeEl.style.display='none';return;}
 
  var inst        = window._instData||{};
  var totalAmt    = inst.total_service_amount || inst.service_price || parseFloat(document.getElementById('editPaymentAmount').value||0);
  var perAmt      = totalSteps > 0 ? totalAmt / totalSteps : 0;
  var paidCount   = parseInt(inst.installment_paid_count||0);
  var planInDB    = inst.installment_plan || '';

  // FIX: isSetup = plan not yet saved to DB at all (brand new installment setup)
  // If a plan is already in the DB (planInDB is set) and paid_count=0,
  // that means setup was done but 1st payment not yet recorded — NOT a setup.
  var isSetup     = (paidCount === 0 && planInDB === '');

  var nextNum     = isSetup ? 1 : (paidCount + 1);
  document.getElementById('editPaymentNum').value = nextNum;

  // FIX: set the record_first_payment flag based on whether we are actually recording
  // (i.e. plan is already in DB and we're recording the next payment)
  document.getElementById('recordFirstPaymentFlag').value = isSetup ? '0' : '1';

  var amtPaid   = paidCount * perAmt;
  var remaining = totalAmt - (nextNum * perAmt);
  var due       = getNextDueDate(every, nextNum);
  var daysAway  = getDaysUntil(every, nextNum);
 
  var dueLine = 'Due on <strong>'+due+'</strong>';
  if(daysAway > 0)       dueLine += ' <span style="color:#9ca3af;font-size:12px;">(in '+daysAway+' day'+(daysAway===1?'':'s')+')</span>';
  else if(daysAway === 0) dueLine += ' <span style="color:#f97316;font-size:12px;">(today)</span>';
  else                   dueLine += ' <span style="color:#dc2626;font-size:12px;">('+Math.abs(daysAway)+' day'+(Math.abs(daysAway)===1?'':'s')+' overdue)</span>';
 
  var msg='';
  var ords2=['th','st','nd','rd'];
  var v2=nextNum%100, suf2=ords2[(v2-20)%10]||ords2[v2]||ords2[0];
  var payLabel = nextNum+suf2+' payment';
 
  if(nextNum > totalSteps){
    msg='All <strong>'+totalSteps+'</strong> installments have been recorded. Total paid: <strong>'+fmt(totalAmt)+'</strong>.';
    // No more payments to record — keep flag as 0 so nothing destructive happens
    document.getElementById('recordFirstPaymentFlag').value = '0';
  } else if(isSetup){
    msg='<strong>Installment plan set.</strong> '
      + totalSteps+' payments of <strong>'+fmt(perAmt)+'</strong> each. '
      + 'First payment due on <strong>'+due+'</strong> '
      + '<span style="color:#9ca3af;font-size:12px;">(in '+daysAway+' day'+(daysAway===1?'':'s')+')</span>.<br>'
      + '<span style="color:#6b7280;font-size:12px;">Clicking Record Payment will save this plan. '
      + 'The 1st payment will be recorded on the next visit.</span>';
  } else {
    msg='Recording <strong>'+payLabel+'</strong> of '+totalSteps+'. '
      + 'Already paid: <strong>'+fmt(amtPaid)+'</strong>. '
      + 'Remaining after this: <strong>'+fmt(remaining < 0 ? 0 : remaining)+'</strong>. '
      + (nextNum===totalSteps
          ? '<br>This is the <strong>final payment</strong>. '+dueLine+'.'
          : dueLine+'.');
  }
  noticeEl.innerHTML=msg;
  noticeEl.style.display='block';
}
 
function closeEditPaymentModal(){document.getElementById('editPaymentModal').classList.remove('is-open');}
 
function validatePaymentForm(){
  var method = document.getElementById('editPaymentMethod').value;
  var status = document.getElementById('editPaymentStatus').value;
  var methodSectionVisible = document.getElementById('paymentMethodSection').classList.contains('on');

  // Only validate GCash fields if method section is visible AND method is gcash
  if(methodSectionVisible && method === 'gcash'){
    var ref = document.getElementById('editGcashRef').value.replace(/\s/g,'');
    if(ref.length !== 12){
      document.getElementById('gcashRefError').style.display='block';
      document.getElementById('editGcashRef').focus();
      return false;
    }
  }

  // Only validate installment fields if status is installment
  if(status === 'installment'){
    if(!document.getElementById('editInstallmentPlan').value){alert('Please select an Installment Plan.');return false;}
    if(!document.getElementById('editPayEvery').value){alert('Please select a Pay Every schedule.');return false;}
  }
  return true;
}
 
var statusColors={
  paid:        {bg:'#166534',text:'Mark as Paid',       msg:'This payment will be marked as Paid and locked. This action cannot be undone.'},
  overdue:     {bg:'#991b1b',text:'Mark as Overdue',    msg:'Are you sure you want to mark this payment as Overdue?'},
  installment: {bg:'#1d4ed8',text:'Record Installment', msg:''},
  pending:     {bg:'#d97706',text:'Save Payment',       msg:''}
};
var _formConfirmed = false;

function buildConfirmMessage(sel){
  var name   = document.getElementById('bannerStudentName').textContent;
  var amt    = document.getElementById('editPaymentAmount').value;
  var method = document.getElementById('editPaymentMethod').value;
  var fmtAmt = '₱' + parseFloat(amt||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});

  if(sel === 'installment'){
    var plan    = document.getElementById('editInstallmentPlan').value;
    var every   = document.getElementById('editPayEvery').value;
    var payNum  = document.getElementById('editPaymentNum').value;
    var ords    = ['th','st','nd','rd'];
    var n       = parseInt(payNum||1);
    var v       = n % 100;
    var suf     = ords[(v-20)%10] || ords[v] || ords[0];
    var inst    = window._instData || {};
    var isSetup = (parseInt(inst.installment_paid_count||0) === 0 && (inst.installment_plan||'') === '');

    if(isSetup){
      // Just saving the plan — no money involved yet
      return '<strong>Save installment plan only.</strong>'
           + '<br><br>No payment will be recorded yet. The 1st payment of <strong>'+fmtAmt+'</strong> will be recorded on the <strong>next visit</strong>.'
           + '<br><br><span style="font-size:12px;color:#6b7280;">Plan: '+plan+' &bull; '+every+'</span>';
    } else {
      // Actually recording a payment — make it very explicit
      return '<span style="color:#dc2626;font-weight:700;font-size:15px;">&#9888; You are about to record a real payment.</span>'
           + '<br><br>This will mark the <strong>'+n+suf+' installment</strong> of <strong>'+fmtAmt+'</strong> as <strong>received</strong> from <strong>'+name+'</strong>.'
           + '<br><br><span style="background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;padding:6px 10px;display:block;font-size:12px;color:#991b1b;">Only confirm if you have physically received this payment. This action updates the student&#39;s balance.</span>'
           + '<br><span style="font-size:12px;color:#6b7280;">Plan: '+plan+' &bull; '+every+'</span>';
    }
  }
  if(sel === 'pending'){
    return 'Save payment of <strong>'+fmtAmt+'</strong> via <strong>'+method+'</strong> for <strong>'+name+'</strong> as <strong>Pending</strong>?';
  }
  if(sel === 'paid'){
    return 'Mark payment of <strong>'+fmtAmt+'</strong> for <strong>'+name+'</strong> as <strong>Paid</strong>? This will be locked and cannot be undone.';
  }
  if(sel === 'overdue'){
    return 'Mark payment for <strong>'+name+'</strong> as <strong>Overdue</strong>?';
  }
  return 'Confirm this payment action?';
}

document.getElementById('editPaymentForm').addEventListener('submit',function(e){
  if(_formConfirmed){ _formConfirmed=false; return; }
  var sel=document.getElementById('editPaymentStatus').value;
  if(!validatePaymentForm()){ e.preventDefault(); return; }
  // Show confirmation for ALL statuses
  e.preventDefault();
  var cfg = statusColors[sel] || {bg:'#374151',text:'Confirm',msg:''};
  var h   = document.getElementById('paymentConfirmHeader');
  h.style.background    = cfg.bg;
  h.style.color         = '#fff';
  h.style.padding       = '12px 16px';
  h.style.borderRadius  = '8px 8px 0 0';
  document.getElementById('paymentConfirmTitle').textContent   = cfg.text;
  document.getElementById('paymentConfirmMessage').innerHTML   = buildConfirmMessage(sel);
  document.getElementById('paymentConfirmBtn').style.background = cfg.bg;
  document.getElementById('editPaymentModal').classList.remove('is-open');
  document.getElementById('paymentConfirmModal').classList.add('is-open');
});
 
function closePaymentConfirmModal(){
  document.getElementById('paymentConfirmModal').classList.remove('is-open');
  document.getElementById('editPaymentModal').classList.add('is-open');
}
function submitEditPayment(){
  if(!validatePaymentForm()){
    document.getElementById('paymentConfirmModal').classList.remove('is-open');
    document.getElementById('editPaymentModal').classList.add('is-open');
    return;
  }
  document.getElementById('paymentConfirmModal').classList.remove('is-open');
  _formConfirmed = true;
  document.getElementById('editPaymentForm').submit();
}
 
function sendNotification(paymentId, btn){
  // Populate confirm modal
  var student  = btn ? btn.getAttribute('data-student')  : '';
  var status   = btn ? btn.getAttribute('data-status')   : '';
  var program  = btn ? btn.getAttribute('data-program')  : '';
  var service  = btn ? btn.getAttribute('data-service')  : '';
  var amount   = btn ? btn.getAttribute('data-amount')   : '';
  var notified = btn ? btn.getAttribute('data-notified') : '';

  document.getElementById('notifyConfirmMessage').textContent =
    'Are you sure you want to notify the guardian about this payment?';

  var info = '<strong>' + student + '</strong><br>'
    + program + (service ? ' &bull; ' + service : '') + '<br>'
    + 'Amount: <strong>&#8369;' + amount + '</strong> &bull; Status: <strong>' + capitalizeFirst(status) + '</strong>';
  if(notified){
    info += '<br><span style="color:#6b7280;font-size:11px;">Last notified: ' + notified + '</span>';
  }
  document.getElementById('notifyStudentInfo').innerHTML = info;

  document.getElementById('notifyConfirmBtn').onclick = function(){
    closeNotifyModal();
    var f = document.createElement('form');
    f.method = 'POST';
    f.action = 'finance.php?tab=payments';
    [['action','send_notification'],['payment_id', paymentId]].forEach(function(p){
      var i = document.createElement('input');
      i.type='hidden'; i.name=p[0]; i.value=p[1]; f.appendChild(i);
    });
    document.body.appendChild(f);
    f.submit();
  };

  document.getElementById('notifyConfirmModal').classList.add('is-open');
}

function closeNotifyModal(){
  document.getElementById('notifyConfirmModal').classList.remove('is-open');
}

function capitalizeFirst(str){
  return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
}
 


window.onclick=function(e){if(e.target.classList.contains('fin-modal'))e.target.classList.remove('is-open');};

function showToast(message, type) {
  type = type || 'success';
  var existing = document.querySelector('.toast-notification');
  if (existing) existing.remove();
  var toast = document.createElement('div');
  toast.className = 'toast-notification ' + type;
  toast.innerHTML = '<span>' + message + '</span>';
  document.body.appendChild(toast);
  setTimeout(function(){ toast.classList.add('show'); }, 10);
  setTimeout(function(){
    toast.classList.remove('show');
    setTimeout(function(){ if(toast.parentNode) toast.parentNode.removeChild(toast); }, 300);
  }, 4000);
}

// Show toast for notification results and clear URL so refresh won't retrigger
document.addEventListener('DOMContentLoaded', function(){
  var urlParams = new URLSearchParams(window.location.search);
  var changed = false;

  if (urlParams.has('notified')) {
    var email = urlParams.get('email') || 'guardian';
    showToast('Notification sent to ' + email, 'success');
    urlParams.delete('notified');
    urlParams.delete('email');
    changed = true;
  }

  if (urlParams.get('error') === 'email_failed') {
    showToast('Failed to send notification. Please check SMTP settings.', 'error');
    urlParams.delete('error');
    changed = true;
  }

  if (urlParams.get('error') === 'no_guardian_email') {
    showToast('Guardian email not found. Please update the student record first.', 'error');
    urlParams.delete('error');
    changed = true;
  }

  if (urlParams.get('error') === 'invalid_payment') {
    showToast('Invalid payment record. Please try again.', 'error');
    urlParams.delete('error');
    changed = true;
  }

  if (changed) {
    var newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
    history.replaceState(null, '', newUrl);
  }
});
</script>
</body>
</html>