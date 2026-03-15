<?php
// simulate form submission by setting $_POST then including the enrollment script
$_POST = [
    'enroll_student' => '1',
    'first_name' => 'CLI',
    'last_name' => 'Tester',
    'middle_name' => '',
    'suffix' => '',
    'date_of_birth' => '2010-05-01',
    'gender' => 'Female',
    'address' => '123 CLI St',
    // status will be forced to Pending
    'email' => 'cli@guardian.com',
    'contact_number' => '09123456789',
    'guardian_name' => 'Guardian CLI',
    // choose service/program and package existing from DB
    'service' => [],
    'package' => [],
    'total_amount' => '0',
    'payment_method' => 'Cash'
];

// pick first service from database
require_once __DIR__.'/includes/db.php';
$res = $conn->query("SELECT program_name, service_name FROM services LIMIT 1");
if ($res && $row=$res->fetch_assoc()){
    $_POST['service'][] = $row['program_name'];
    $_POST['package'][] = $row['service_name'];
}

require __DIR__.'/public/hro/student-enrollment.php';
