<?php
/**
 * activity_logger.php
 * Place in: includes/activity_logger.php
 *
 * Updated logActivity() — now includes 'target' parameter.
 * The function signature is backward-compatible:
 * old calls with 5 params still work, target defaults to ''.
 */

// ── DB column guard — adds 'target' column if it doesn't exist ──────────────
function ensureActivityLogsColumns(mysqli $conn): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $cols = ['target VARCHAR(255) NULL', 'ip_address VARCHAR(45) NULL'];
    foreach ($cols as $colDef) {
        $colName = explode(' ', trim($colDef))[0];
        $chk = $conn->query("SHOW COLUMNS FROM activity_logs LIKE '$colName'");
        if ($chk && $chk->num_rows === 0) {
            $conn->query("ALTER TABLE activity_logs ADD COLUMN $colDef");
        }
    }
}

/**
 * Log a system activity.
 *
 * @param mysqli $conn
 * @param string $action   What was done  — e.g. "Approved Access Request"
 * @param string $module   Where it happened — e.g. "User Access Management"
 * @param string $status   'Success' | 'Failed' | 'Pending'
 * @param string $details  Extra detail text
 * @param string $target   Who/what was affected — e.g. "STF-001 – Maria Santos"
 */
function logActivity(
    mysqli $conn,
    string $action,
    string $module,
    string $status  = 'Success',
    string $details = '',
    string $target  = ''
): void {
    ensureActivityLogsColumns($conn);

    $user_id    = $_SESSION['user_id']   ?? null;
    $staff_id   = $_SESSION['staff_id']  ?? null;
    $role       = $_SESSION['role']      ?? 'Unknown';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    $a  = $conn->real_escape_string($action);
    $mo = $conn->real_escape_string($module);
    $s  = $conn->real_escape_string($status);
    $d  = $conn->real_escape_string($details);
    $t  = $conn->real_escape_string($target);
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
 * Shorthand — logs a Success with no target.
 * All old quickLog() calls still work unchanged.
 */
function quickLog(mysqli $conn, string $action, string $module, string $details = ''): void {
    logActivity($conn, $action, $module, 'Success', $details, '');
}