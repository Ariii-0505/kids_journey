<?php
require_once(__DIR__ . "/../../includes/db.php");
require_once __DIR__ . '/../../includes/auth.php';

/* =========================
   DELETE LOG
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del_id = (int)$_POST['delete_id'];
    $conn->query("DELETE FROM activity_logs WHERE id = $del_id");
    header("Location: activity-logs.php");
    exit;
}

/* =========================
   FILTER LOGIC
========================= */
$search = $_GET['search'] ?? '';
$filterAction = $_GET['action_filter'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$query = "
    SELECT a.*, u.full_name, u.email, u.staff_id as user_staff_id
    FROM activity_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE 1=1
";

if (!empty($search)) {
    $s = $conn->real_escape_string($search);
    $query .= " AND (u.full_name LIKE '%$s%' OR u.username LIKE '%$s%' OR a.action LIKE '%$s%' OR a.details LIKE '%$s%')";
}
if (!empty($filterAction)) {
    $fa = $conn->real_escape_string($filterAction);
    $query .= " AND a.action LIKE '%$fa%'";
}
if (!empty($filterStatus)) {
    $fs = $conn->real_escape_string($filterStatus);
    $query .= " AND a.status = '$fs'";
}

$query .= " ORDER BY a.created_at DESC";
$logs     = $conn->query($query);
$totalLogs = $logs->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Activity Logs – Administrator</title>
  <link rel="stylesheet" href="../../assets/css/hro/hro-base.css">
  <link rel="stylesheet" href="../../assets/css/administratorcss/dashboard.css">
  <link rel="stylesheet" href="../../assets/css/administratorcss/activity-logs.css">
  <script src="../../assets/js/sidebar.js" defer></script>
</head>
<body>

<?php include __DIR__ . '/../../includes/administrator-sidebar.php'; ?>

<div class="main">

  <div class="page-hero">
    <div class="page-hero-text">
      <h1>System Activity Logs</h1>
      <p style="font-size:14px;color:#6b7280;margin-top:4px;">Monitor all system activities including logins, role changes, and security events</p>
    </div>
  </div>

  <div style="padding:24px 36px;display:flex;flex-direction:column;gap:20px;">

    <!-- FILTER & EXPORT -->
    <div class="dashboard-card" style="padding:18px 24px;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <span style="font-weight:600;color:#374151;">Filter and Export</span>
        <a href="export-logs.php" class="export-btn" onclick="return confirmExport()">⬇ Export</a>
      </div>
      <form method="GET" class="filter-row">
        <input type="text" name="search" placeholder="Search by user, action..."
               value="<?= htmlspecialchars($search) ?>"
               onkeydown="if(event.key==='Enter'){this.form.submit();}">
        <select name="action_filter" onchange="this.form.submit()">
          <option value="">All Actions</option>
          <option value="Access Request Approved"  <?= $filterAction == 'Access Request Approved'  ? 'selected':'' ?>>Access Request Approved</option>
          <option value="Access Request Disapproved" <?= $filterAction == 'Access Request Disapproved' ? 'selected':'' ?>>Access Request Disapproved</option>
          <option value="Login" <?= $filterAction == 'Login' ? 'selected':'' ?>>Login</option>
          <option value="Logout" <?= $filterAction == 'Logout' ? 'selected':'' ?>>Logout</option>
        </select>
        <select name="status" onchange="this.form.submit()">
          <option value="">All Status</option>
          <option value="Success" <?= $filterStatus == 'Success' ? 'selected':'' ?>>Success</option>
          <option value="Failed"  <?= $filterStatus == 'Failed'  ? 'selected':'' ?>>Failed</option>
          <option value="Pending" <?= $filterStatus == 'Pending' ? 'selected':'' ?>>Pending</option>
        </select>
        <a href="activity-logs.php" class="filter-reset">Reset</a>
      </form>
    </div>

    <!-- LOG TABLE -->
    <div class="dashboard-card">
      <h2 class="card-title" style="margin-bottom:18px;">Activity Logs (<?= $totalLogs ?>)</h2>

      <table class="activity-table">
        <thead>
          <tr>
            <th>Action</th>
            <th>Staff ID</th>
            <th>User</th>
            <th>Timestamp</th>
            <th>Status</th>
            <th>Delete</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($totalLogs > 0): ?>
            <?php while ($row = $logs->fetch_assoc()): ?>
            <tr>
              <td>
                <a href="#" class="action-link <?= strpos(strtolower($row['action']),'disapprov') !== false || strpos(strtolower($row['action']),'reject') !== false ? 'red' : (strpos(strtolower($row['action']),'approv') !== false || strpos(strtolower($row['action']),'accept') !== false ? 'green' : '') ?>">
                  <?= htmlspecialchars($row['action']) ?>
                </a>
              </td>
              <td><?= $row['user_staff_id'] ? htmlspecialchars($row['user_staff_id']) : ($row['staff_id'] ?? '-') ?></td>
              <td><?= htmlspecialchars($row['full_name'] ?? 'System') ?></td>
              <td><?= date("m/d/Y, g:i:s A", strtotime($row['created_at'])) ?></td>
              <td>
                <span class="status-badge <?= $row['status'] ?>">
                  <?= htmlspecialchars($row['status']) ?>
                </span>
              </td>
              <td>
                <form method="POST" onsubmit="return confirm('Delete this log entry?')">
                  <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                  <button type="submit" class="delete-btn" title="Delete">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zm13-15h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                  </button>
                </form>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:30px;">No activity logs found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </div>
</div>

<!-- Export confirmation modal -->
<div id="exportModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:999;display:none;align-items:center;justify-content:center;">
  <div style="background:white;border-radius:14px;padding:32px;width:360px;text-align:center;">
    <div style="background:#c0392b;color:white;font-weight:700;padding:10px;border-radius:8px 8px 0 0;margin:-32px -32px 20px;">CONFIRMATION</div>
    <p style="font-size:15px;font-weight:600;">Are you sure you want to download this file?</p>
    <div style="display:flex;gap:12px;justify-content:center;margin-top:20px;">
      <button onclick="document.getElementById('exportModal').style.display='none'" style="padding:10px 24px;border-radius:8px;border:1px solid #e5e7eb;cursor:pointer;">Cancel</button>
      <a href="export-logs.php" style="padding:10px 24px;border-radius:8px;background:#c0392b;color:white;font-weight:600;text-decoration:none;">Confirm</a>
    </div>
  </div>
</div>

<script>
function confirmExport() {
  document.getElementById('exportModal').style.display = 'flex';
  return false;
}
</script>

</body>
</html>
