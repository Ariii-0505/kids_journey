<?php
/**
 * Role-based access check helper.
 *
 * Include this file at the top of any page that requires a specific role.
 * Usage:
 *   define('REQUIRED_ROLE', 'Admin');          // single role
 *   define('REQUIRED_ROLE', 'Human Resources'); // HR pages
 *   define('REQUIRED_ROLE', 'Educator');        // Educator pages
 *   require_once __DIR__ . '/../includes/role-check.php';
 *
 * auth.php must already be included (which ensures $loggedUser is set).
 */

if (!isset($loggedUser)) {
    // auth.php was not included before this file — include it now
    require_once __DIR__ . '/auth.php';
}

// Role → default dashboard mapping
$_roleDashboards = [
    'Admin'           => BASE_URL . '/administrator/dashboard.php',
    'Human Resources' => BASE_URL . '/hro/dashboard.php',
    'Educator'        => BASE_URL . '/educator/educator-dashboard.php',
];

// If a page defines REQUIRED_ROLE, enforce it
if (defined('REQUIRED_ROLE') && $loggedUser['role'] !== REQUIRED_ROLE) {
    $home = $_roleDashboards[$loggedUser['role']] ?? BASE_URL . '/index.php';
    redirect($home);
}

/**
 * requireRole(string $role)
 * Inline helper — redirect immediately if the logged-in user does not have $role.
 */
function requireRole(string $role): void {
    global $loggedUser;
    $dashboards = [
        'Admin'           => BASE_URL . '/administrator/dashboard.php',
        'Human Resources' => BASE_URL . '/hro/dashboard.php',
        'Educator'        => BASE_URL . '/educator/educator-dashboard.php',
    ];
    if (!isset($loggedUser) || $loggedUser['role'] !== $role) {
        $home = $dashboards[$loggedUser['role'] ?? ''] ?? BASE_URL . '/index.php';
        redirect($home);
    }
}
