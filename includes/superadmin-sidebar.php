<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" id="sidebar">

  <div>
    <div class="brand">
      <button class="toggle-btn" onclick="toggleSidebar()">☰</button>

      <div class="logo">SA</div>

      <div class="brand-text">
        <strong>Super Admin</strong>
        <span>Dashboard</span>
      </div>
    </div>

    <nav>
      <a class="<?= $currentPage == 'overview.php' ? 'active' : '' ?>" 
   href="overview.php" data-tooltip="Overview">
        <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
        <span>Overview</span>
      </a>

      <a class="<?= $currentPage == 'user-access.php' ? 'active' : '' ?>" 
   href="user-access.php" data-tooltip="User Access">

        <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v3h20v-3c0-3.3-6.7-5-10-5z"/></svg>
        <span>User Access</span>
      </a>

      <a class="<?= $currentPage == 'activity-logs.php' ? 'active' : '' ?>" 
   href="activity-logs.php" data-tooltip="Activity Logs">

        <svg viewBox="0 0 24 24"><path d="M3 3h18v2H3V3zm0 14h18v2H3v-2zm0-7h18v2H3v-2z"/></svg>
        <span>Activity Logs</span>
      </a>

            <a class="<?= $currentPage == 'finance.php' ? 'active' : '' ?>" 
   href="finance.php" data-tooltip="Finance">

        <svg viewBox="0 0 24 24"><path d="M3 3h18v2H3V3zm0 14h18v2H3v-2zm0-7h18v2H3v-2z"/></svg>
        <span>Finance</span>
      </a>

      <a class="<?= $currentPage == 'reports.php' ? 'active' : '' ?>" 
   href="reports.php" data-tooltip="Reports">

        <svg viewBox="0 0 24 24"><path d="M3 13h2v-2H3v2zm4 6h2V5H7v14zm4-4h2V9h-2v6zm4 4h2V3h-2v16z"/></svg>
        <span>Reports</span>
      </a>

    </nav>
  </div>

  <!-- ✅ Profile Section aligned with DB -->
  <div class="profile">
    <div class="avatar">
      <?= strtoupper(substr($loggedUser['full_name'], 0, 1)) ?>
    </div>

    <div class="profile-text">
      <strong><?= htmlspecialchars($loggedUser['full_name']) ?></strong>
      <span><?= htmlspecialchars($loggedUser['email']) ?></span>
    </div>
  </div>


</div>
