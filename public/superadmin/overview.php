<?php
require_once(__DIR__ . "/../../includes/db.php");
require_once __DIR__ . '/../../includes/auth.php';

/* =========================
   DASHBOARD STATS
========================= */

// Count active users
$totalUsers = $conn->query("
    SELECT COUNT(*) as total 
    FROM users 
    WHERE status='active'
")->fetch_assoc()['total'];

// Count pending access requests
$pendingRequests = $conn->query("
    SELECT COUNT(*) as total 
    FROM users 
    WHERE status='pending'
")->fetch_assoc()['total'];

/* =========================
   RECENT SYSTEM ACTIVITIES
========================= */

$activities = $conn->query("
    SELECT a.*, u.full_name, u.email
    FROM activity_logs a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 6
");

?>

<!DOCTYPE html>
<html>
<head>
  <title>Super Administrator Dashboard</title>
  <link rel="stylesheet" href="../../assets/css/superadmincss/overview.css">
  <script src="../../assets/js/sidebar.js" defer></script>
</head>
<body>

<?php include __DIR__ . '/../../includes/superadmin-sidebar.php'; ?>

<div class="main">

  <div class="page-header">
    <h1>Super Administrator Dashboard</h1>
    <p>System overview and summary</p>
  </div>

  <!-- OVERVIEW CARD -->
        <div class="dashboard-card">

          <h2 class="card-title">Overview</h2>

                  <div class="stats-grid">

                    <!-- Total Users card -->
                    <div class="stat-card clickable" onclick="window.location.href='user-access.php?tab=management'">
                      <div>
                        <span class="stat-label">Total Users</span>
                        <h2 class="stat-number"><?= $totalUsers ?></h2>
                        <span class="stat-sub">Active accounts</span>
                      </div>
                      <div class="stat-icon purple">👥</div>
                    </div>

                    <!-- Pending Requests card -->
                    <div class="stat-card clickable" onclick="window.location.href='user-access.php?tab=requests'">
                      <div>
                        <span class="stat-label">Pending Access Requests</span>
                        <h2 class="stat-number"><?= $pendingRequests ?></h2>
                        <span class="stat-sub">Requires review</span>
                      </div>
                      <div class="stat-icon orange">!</div>
                    </div>

                  </div>

          </div>

  <!-- ACTIVITY CARD -->
  <div class="dashboard-card">

    <h2 class="card-title">Recent System Activities</h2>

    <table class="activity-table">
      <thead>
        <tr>
          <th>Action</th>
          <th>User</th>
          <th>Module</th>
          <th>Time</th>
          <th>Status</th>
        </tr>
      </thead>

                  <tbody>
                  <?php while ($row = $activities->fetch_assoc()): ?>
                        <tr>
                          <!-- Action -->
                          <td>
                            <span class="action-badge">
                              <?= htmlspecialchars($row['action']) ?>
                            </span>
                          </td>

                          <!-- User -->
                          <td>
                            <?= htmlspecialchars($row['full_name']) ?><br>
                            <small><?= htmlspecialchars($row['email']) ?></small>
                          </td>

                          <!-- Module -->
                          <td><?= htmlspecialchars($row['module']) ?></td>

                          <!-- Time -->
                          <td><?= date("m/d/Y, h:i:s A", strtotime($row['created_at'])) ?></td>

                          <!-- Status -->
                          <td>
                            <span class="status-badge <?= htmlspecialchars($row['status']) ?>">
                              <?= ucfirst($row['status']) ?>
                            </span>
                          </td>
                        </tr>
                  <?php endwhile; ?>
                  </tbody>

    </table>

  </div>

</div>

</body>
</html>