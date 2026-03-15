<?php
require_once(__DIR__ . "/../../includes/db.php");
require_once __DIR__ . '/../../includes/auth.php';

/* =========================
   STATS
========================= */
$totalStudents = $conn->query("SELECT COUNT(*) as c FROM students")->fetch_assoc()['c'];
$totalStaff    = $conn->query("SELECT COUNT(*) as c FROM staff WHERE status='Active'")->fetch_assoc()['c'];

// Staff by position
$staffByPosition = $conn->query("
    SELECT position, COUNT(*) as count 
    FROM staff 
    WHERE status='Active' 
    GROUP BY position
");

$positionLabels = [
    'OTPR' => 'Occupational Therapist',
    'RSLP' => 'Speech Language Pathologist',
    'LPT' => 'Licensed Professional Teacher',
    'RSW' => 'Registered Social Worker'
];

// Enrollment status counts
$activeEnroll   = $conn->query("SELECT COUNT(*) as c FROM enrollments WHERE status='Active'")->fetch_assoc()['c'];
$pendingEnroll  = $conn->query("SELECT COUNT(*) as c FROM enrollments WHERE status='Pending'")->fetch_assoc()['c'];
$inactiveEnroll = $conn->query("SELECT COUNT(*) as c FROM enrollments WHERE status='Inactive'")->fetch_assoc()['c'];
$totalEnroll    = max(1, $activeEnroll + $pendingEnroll + $inactiveEnroll);

// Recently enrolled students
$recentEnrollments = $conn->query("
    SELECT s.full_name, g.guardian_name, e.status, e.created_at
    FROM enrollments e
    JOIN students s ON e.student_id = s.id
    LEFT JOIN guardians g ON s.id = g.student_id
    ORDER BY e.created_at DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Human Resource Dashboard</title>
  <link rel="stylesheet" href="../../assets/css/hro/hro-base.css">
  <link rel="stylesheet" href="../../assets/css/hro/hr-overview-dashboard.css">
  <script src="../../assets/js/sidebar.js" defer></script>
</head>
<body>

<?php include __DIR__ . '/../../includes/hro-sidebar.php'; ?>

<div class="main">

  <div class="page-hero">
    <div class="page-hero-text">
      <h1>Human Resource Dashboard</h1>
    </div>
  </div>

  <!-- STAT CARDS -->
  <div class="stats-row">

    <div class="stat-card">
      <div class="stat-info">
        <span class="stat-label">Total Students</span>
        <h2 class="stat-number"><?= $totalStudents ?></h2>
        <span class="stat-sub">enrolled</span>
      </div>
      <div class="stat-icon stat-blue">
        <svg viewBox="0 0 24 24" width="26" height="26" fill="#2563eb">
          <path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/>
        </svg>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-info">
        <span class="stat-label">Total Staff</span>
        <h2 class="stat-number"><?= $totalStaff ?></h2>
        <span class="stat-sub">active</span>
      </div>
      <div class="stat-icon stat-green">
        <svg viewBox="0 0 24 24" width="26" height="26" fill="#16a34a">
          <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
        </svg>
      </div>
    </div>

  </div>

  <!-- ENROLLMENT STATUS CARD -->
  <div class="dashboard-card">
    <h2 class="card-title">Student Enrollment Status</h2>

    <div class="enroll-status-list">

      <div class="enroll-status-item">
        <div class="enroll-status-top">
          <span class="enroll-status-label green">Active Sessions</span>
          <span class="enroll-count"><?= $activeEnroll ?>/<?= $totalEnroll ?></span>
        </div>
        <div class="progress-bar-track">
          <div class="progress-bar-fill green" style="width:<?= round($activeEnroll/$totalEnroll*100) ?>%"></div>
        </div>
      </div>

      <div class="enroll-status-item">
        <div class="enroll-status-top">
          <span class="enroll-status-label yellow">Pending</span>
          <span class="enroll-count"><?= $pendingEnroll ?>/<?= $totalEnroll ?></span>
        </div>
        <div class="progress-bar-track">
          <div class="progress-bar-fill yellow" style="width:<?= round($pendingEnroll/$totalEnroll*100) ?>%"></div>
        </div>
      </div>

      <div class="enroll-status-item">
        <div class="enroll-status-top">
          <span class="enroll-status-label red">Inactive/Terminated</span>
          <span class="enroll-count"><?= $inactiveEnroll ?>/<?= $totalEnroll ?></span>
        </div>
        <div class="progress-bar-track">
          <div class="progress-bar-fill red" style="width:<?= round($inactiveEnroll/$totalEnroll*100) ?>%"></div>
        </div>
      </div>

    </div>
  </div>

  <!-- STAFF POSITIONS CARD -->
  <div class="dashboard-card">
    <h2 class="card-title">Staff Positions</h2>
    <div class="enroll-status-list">
      <?php if ($staffByPosition && $staffByPosition->num_rows > 0): ?>
        <?php while ($pos = $staffByPosition->fetch_assoc()): ?>
          <?php 
            $posName = $positionLabels[$pos['position']] ?? $pos['position'];
            $posPercent = round($pos['count'] / max(1, $totalStaff) * 100);
          ?>
          <div class="enroll-status-item">
            <div class="enroll-status-top">
              <span class="enroll-status-label green"><?= htmlspecialchars($posName) ?></span>
              <span class="enroll-count"><?= $pos['count'] ?>/<?= $totalStaff ?></span>
            </div>
            <div class="progress-bar-track">
              <div class="progress-bar-fill green" style="width:<?= $posPercent ?>%"></div>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <p style="color:#9ca3af;text-align:center;padding:20px 0;">No staff data available</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- RECENTLY ENROLLED -->
  <div class="dashboard-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
      <h2 class="card-title" style="margin:0;">Recently Enrolled</h2>
      <a href="student-enrollment.php" class="btn-primary" style="font-size:12px;padding:7px 14px;">+ Enroll Student</a>
    </div>

    <?php if ($recentEnrollments && $recentEnrollments->num_rows > 0): ?>
      <?php while ($row = $recentEnrollments->fetch_assoc()): ?>
        <div class="recent-enroll-item">
          <div class="recent-avatar"><?= strtoupper(substr($row['full_name'], 0, 1)) ?></div>
          <div class="recent-info">
            <strong><?= htmlspecialchars($row['full_name']) ?></strong>
            <span>Guardian: <?= htmlspecialchars($row['guardian_name'] ?? 'N/A') ?></span>
          </div>
          <span class="badge badge-<?= $row['status'] ?>"><?= $row['status'] ?></span>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p style="color:#9ca3af;text-align:center;padding:20px 0;">No enrollments yet.</p>
    <?php endif; ?>
  </div>

</div>

</body>
</html>
