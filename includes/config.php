<?php
// ✅ Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Base URL
define('BASE_URL', '/school-management-system/public');

// ✅ Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'kids_journey');

// ✅ SMTP Configuration for Gmail
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_AUTH', true);
define('SMTP_USERNAME', 'kidsjourney20@gmail.com');
define('SMTP_PASSWORD', 'uassonsbajcgyvou');
define('SMTP_FROM_EMAIL', 'kidsjourney20@gmail.com');
define('SITE_NAME', "Kid's Journey Learning Center");
define('SITE_URL', 'http://localhost');

// ✅ Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// =====================
//  HELPER FUNCTIONS
// =====================

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function redirect($url) {
    header("Location: " . $url);
    exit();
}

function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string(
        $conn,
        htmlspecialchars(strip_tags(trim($data)))
    );
}

/**
 * Log a system activity to the activity_logs table.
 *
 * New signature — reads session automatically, no need to pass user_id/role.
 * Also saves 'target' (who/what was affected) for the Reports module.
 *
 * Usage:
 *   logActivity($conn, 'Approved Access Request', 'User Access Management', 'Success', 'Details here', 'STF-001 – Maria Santos');
 *
 * @param mysqli $conn
 * @param string $action   What was done         e.g. "Approved Access Request"
 * @param string $module   Where it happened     e.g. "User Access Management"
 * @param string $status   Success | Failed | Pending
 * @param string $details  Extra detail text
 * @param string $target   Who/what was affected e.g. "STF-001 – Maria Santos"
 */
function logActivity($conn, $action, $module, $status = 'Success', $details = '', $target = '') {
    // Ensure target column exists (runs once per request)
    static $colChecked = false;
    if (!$colChecked) {
        $colChecked = true;
        $chk = $conn->query("SHOW COLUMNS FROM activity_logs LIKE 'target'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE activity_logs ADD COLUMN target VARCHAR(255) NULL");
        }
    }

    // Pull everything from session — callers don't need to pass these
    $user_id    = $_SESSION['user_id']    ?? null;
    $staff_id   = $_SESSION['staff_id']   ?? null;
    $role       = $_SESSION['role']       ?? 'Unknown';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    // If staff_id not in session, look it up from DB
    if (empty($staff_id) && $user_id) {
        $res = $conn->query("SELECT staff_id FROM users WHERE id=" . (int)$user_id);
        if ($res && $row = $res->fetch_assoc()) {
            $staff_id = $row['staff_id'];
        }
    }

    $a  = $conn->real_escape_string($action);
    $mo = $conn->real_escape_string($module);
    $s  = $conn->real_escape_string($status);
    $d  = $conn->real_escape_string($details ?? '');
    $t  = $conn->real_escape_string($target  ?? '');
    $r  = $conn->real_escape_string($role);
    $ip = $conn->real_escape_string($ip_address);
    $si = $staff_id ? $conn->real_escape_string($staff_id) : null;

    if ($user_id) {
        $stmt = $conn->prepare("
            INSERT INTO activity_logs
                (user_id, staff_id, role, action, module, target, status, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issssssss", $user_id, $si, $r, $a, $mo, $t, $s, $d, $ip);
    } else {
        $null_uid = null;
        $stmt = $conn->prepare("
            INSERT INTO activity_logs
                (user_id, staff_id, role, action, module, target, status, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issssssss", $null_uid, $si, $r, $a, $mo, $t, $s, $d, $ip);
    }

    $stmt->execute();
    $stmt->close();
}

/**
 * Shorthand log — always Success, no target needed.
 */
function quickLog($conn, $action, $module, $details = '') {
    logActivity($conn, $action, $module, 'Success', $details, '');
}

/**
 * Get a setting value from the database
 */
function getSetting($key, $default = null) {
    global $conn;
    $tableCheck = $conn->query("SHOW TABLES LIKE 'settings'");
    if ($tableCheck->num_rows == 0) {
        return $default;
    }
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

/**
 * Save a setting value to the database
 */
function saveSetting($key, $value) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param('sss', $key, $value, $value);
    return $stmt->execute();
}

/**
 * Get SMTP configuration (from database, with config.php as fallback)
 */
function getSMTPSettings() {
    $username  = getSetting('smtp_username',   defined('SMTP_USERNAME')   ? SMTP_USERNAME   : '');
    $fromEmail = getSetting('smtp_from_email', defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : '');

    if (empty($fromEmail) || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        if (!empty($username) && filter_var($username, FILTER_VALIDATE_EMAIL)) {
            $fromEmail = $username;
        } else {
            $fromEmail = 'no-reply@example.com';
        }
    }

    return [
        'host'       => getSetting('smtp_host',     defined('SMTP_HOST')     ? SMTP_HOST     : 'smtp.gmail.com'),
        'port'       => getSetting('smtp_port',     defined('SMTP_PORT')     ? SMTP_PORT     : '587'),
        'secure'     => getSetting('smtp_secure',   defined('SMTP_SECURE')   ? SMTP_SECURE   : 'tls'),
        'auth'       => getSetting('smtp_auth',     defined('SMTP_AUTH')     ? SMTP_AUTH     : true),
        'username'   => $username,
        'password'   => getSMTPPassword(),
        'from_email' => $fromEmail,
        'from_name'  => getSetting('smtp_from_name', defined('SITE_NAME') ? SITE_NAME : 'School Management System'),
    ];
}

/**
 * Get SMTP password (decrypted)
 */
function getSMTPPassword() {
    $hashed = getSetting('smtp_password_hash', '');
    if (!empty($hashed)) {
        $encrypted = getSetting('smtp_password_encrypted', '');
        if (!empty($encrypted)) {
            return openssl_decrypt($encrypted, 'AES-128-CBC', 'kj_smtp_key_2024');
        }
    }
    return getSetting('smtp_password', defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '');
}

/**
 * Save SMTP password (hashed and encrypted)
 */
function saveSMTPPassword($password) {
    if (!empty($password)) {
        $encrypted = openssl_encrypt($password, 'AES-128-CBC', 'kj_smtp_key_2024');
        saveSetting('smtp_password_encrypted', $encrypted);
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        saveSetting('smtp_password_hash', $hashed);
        return true;
    }
    return false;
}

/**
 * Verify SMTP password
 */
function verifySMTPPassword($password) {
    $hashed = getSetting('smtp_password_hash', '');
    if (!empty($hashed)) {
        return password_verify($password, $hashed);
    }
    return false;
}

/**
 * Record new user info to settings database
 */
function recordUserToSettings($userId, $staffId, $fullName, $username, $email, $role) {
    global $conn;
    $existingUsersJson = getSetting('registered_users', '[]');
    $users = json_decode($existingUsersJson, true);
    $users[] = [
        'id'         => $userId,
        'staff_id'   => $staffId,
        'full_name'  => $fullName,
        'username'   => $username,
        'email'      => $email,
        'role'       => $role,
        'created_at' => date('Y-m-d H:i:s'),
    ];
    saveSetting('registered_users', json_encode($users));
}