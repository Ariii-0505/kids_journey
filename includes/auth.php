<?php
require_once __DIR__ . '/config.php'; // ✅ config.php already starts session + DB

// ✅ Check if user is logged in
if (!isLoggedIn()) {
    redirect(BASE_URL . "/auth/login.php");
}

// ✅ Fetch user from DB
$stmt = $conn->prepare("SELECT id, full_name, username, email, role FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $loggedUser = $result->fetch_assoc();
} else {
    session_unset();
    session_destroy();
    redirect(BASE_URL . "/auth/login.php");
}

// ✅ Role-based access control
$currentPath = $_SERVER['PHP_SELF'];

$roleAccess = [
    'Admin'   => BASE_URL . '/administrator/',
    'Human Resources'      => BASE_URL . '/hro/',
    'Educator'=> BASE_URL . '/educator/'
];

$allowedDir = $roleAccess[$loggedUser['role']] ?? null;

if ($allowedDir && strpos($currentPath, $allowedDir) === false) {
    // 🚫 Redirect to correct dashboard if user tries to access another role's area
    $homePage = [
        'Admin'           => 'dashboard.php',
        'Human Resources' => 'dashboard.php',
        'Educator'        => 'educator-dashboard.php',
    ];
    $home = $homePage[$loggedUser['role']] ?? 'dashboard.php';
    redirect($allowedDir . $home);
}
?>
