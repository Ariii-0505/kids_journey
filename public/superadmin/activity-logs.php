<?php
require_once(__DIR__ . "/../../includes/db.php");
require_once __DIR__ . '/../../includes/auth.php';

/* =========================
   FILTER LOGIC
========================= */

$search = $_GET['search'] ?? '';
$action = $_GET['action'] ?? '';
$status = $_GET['status'] ?? '';

$query = "
    SELECT activity_logs.*, users.full_name, users.username, users.email
    FROM activity_logs
    LEFT JOIN users ON activity_logs.user_id = users.id
    WHERE 1=1
";

/* Search */
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $query .= " AND (
        users.full_name LIKE '%$search%' 
        OR users.username LIKE '%$search%' 
        OR users.email LIKE '%$search%' 
        OR activity_logs.action LIKE '%$search%' 
        OR activity_logs.details LIKE '%$search%'
    )";
}

/* Filter Action */
if (!empty($action)) {
    $action = $conn->real_escape_string($action);
    $query .= " AND activity_logs.action = '$action'";
}

/* Filter Status */
if (!empty($status)) {
    $status = $conn->real_escape_string($status);
    $query .= " AND activity_logs.status = '$status'";
}

$query .= " ORDER BY activity_logs.created_at DESC";

$logs = $conn->query($query);
$totalLogs = $logs->num_rows;

?>

<!DOCTYPE html>
<html>
<head>
  <title>User Access Management</title>
  <link rel="stylesheet" href="../../assets/css/superadmincss/activity-logs.css">
  <script src="../../assets/js/sidebar.js" defer></script>
</head>
<body>

<?php include __DIR__ . '/../../includes/superadmin-sidebar.php'; ?>

<div class="main">

  <!-- PAGE HEADER -->
  <div class="page-header">
    <h1>System Activity Logs</h1>
    <p>Monitor all system activities including logins, role changes, and security events</p>
  </div>

  <!-- FILTER CARD -->
  <div class="dashboard-card">

    <div class="filter-header">
      <h3>Filter and Export</h3>

      <a href="export-logs.php" class="export-btn">
        ⬇ Export CSV
      </a>
    </div>

    <form method="GET" class="filter-controls">
  <input type="text"  
         name="search"  
         placeholder="Search by user, action..." 
         value="<?= htmlspecialchars($search) ?>"
         onkeydown="if(event.key==='Enter'){this.form.submit();}" />

  <select name="action" onchange="this.form.submit()">
    <option value="">All Actions</option>
    <option value="approved" <?= $action == 'approved' ? 'selected' : '' ?>>Approved</option>
    <option value="denied" <?= $action == 'denied' ? 'selected' : '' ?>>Denied</option>
  </select>

  <select name="status" onchange="this.form.submit()">
    <option value="">All Status</option>
    <option value="approved" <?= $status == 'approved' ? 'selected' : '' ?>>Approved</option>
    <option value="denied" <?= $status == 'denied' ? 'selected' : '' ?>>Denied</option>
  </select>

  <button type="submit" class="filter-btn">Apply</button>
  <a href="activity-logs.php" class="filter-btn reset-btn">Reset</a>
</form>


  </div>

  <!-- LOG TABLE -->
  <div class="dashboard-card">

    <h2 class="card-title">Activity Logs (<?= $totalLogs ?>)</h2>

    <table class="activity-table">
      <thead>
        <tr>
          <th>Action</th>
          <th>User</th>
          <th>Timestamp</th>
          <th>Status</th>
          <th>Details</th>
        </tr>
      </thead>

              <tbody>
              <?php if ($totalLogs > 0): ?>
                <?php while ($row = $logs->fetch_assoc()): ?>
                <tr>
                  <td>
                    <span class="action-badge">
                      <?= htmlspecialchars($row['action']) ?>
                    </span>
                  </td>

                  <!-- ✅ User column -->
                  <td>
                    <?= htmlspecialchars($row['full_name']) ?><br>
                    <small><?= htmlspecialchars($row['email']) ?></small>
                  </td>

                  <td>
                    <?= date("m/d/Y, h:i:s A", strtotime($row['created_at'])) ?>
                  </td>

                  <td>
                    <span class="status-badge <?= htmlspecialchars($row['status']) ?>">
                      <?= ucfirst($row['status']) ?>
                    </span>
                  </td>

                  <td><?= htmlspecialchars($row['details']) ?></td>
                </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr>
                  <td colspan="5" style="text-align:center; padding:20px;">
                    No activity logs found.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>

    </table>

  </div>

</div>

</body>
</html>
