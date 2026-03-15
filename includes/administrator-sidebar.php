<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" id="sidebar">

  <div class="sidebar-top">
    <div class="brand">
      <button class="toggle-btn" onclick="toggleSidebar()">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
      </button>

      <div class="logo">A</div>

      <div class="brand-text">
        <strong>Administrator</strong>
        <span>Dashboard</span>
      </div>
    </div>

    <nav>
      <a class="<?= $currentPage == 'dashboard.php' ? 'active' : '' ?>"
         href="dashboard.php" data-tooltip="Dashboard">
        <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
        <span>Dashboard</span>
      </a>

      <a class="<?= $currentPage == 'user-access.php' ? 'active' : '' ?>"
         href="user-access.php" data-tooltip="User Access">
        <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v3h20v-3c0-3.3-6.7-5-10-5z"/></svg>
        <span>User Access Management</span>
      </a>

      <a class="<?= $currentPage == 'finance.php' ? 'active' : '' ?>"
         href="finance.php" data-tooltip="Finance Management">
        <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
        <span>Finance Management</span>
      </a>

      <a class="<?= $currentPage == 'reports.php' ? 'active' : '' ?>"
         href="reports.php" data-tooltip="Reports">
        <svg viewBox="0 0 24 24"><path d="M3 13h2v-2H3v2zm4 6h2V5H7v14zm4-4h2V9h-2v6zm4 4h2V3h-2v16z"/></svg>
        <span>Reports Management</span>
      </a>
    </nav>
  </div>

  <div class="profile profile-trigger" onclick="toggleProfileMenu(this)">
    <div class="avatar">
      <?= strtoupper(substr($loggedUser['full_name'], 0, 1)) ?>
    </div>
    <div class="profile-text">
      <strong><?= htmlspecialchars($loggedUser['full_name']) ?></strong>
      <span><?= htmlspecialchars($loggedUser['email']) ?></span>
    </div>
    <div class="profile-dropdown">
      <a href="<?= BASE_URL ?>/auth/edit-profile.php?back=<?= urlencode(BASE_URL . '/administrator/dashboard.php') ?>">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v3h20v-3c0-3.3-6.7-5-10-5z"/></svg>
        Profile
      </a>
      <a href="<?= BASE_URL ?>/auth/logout.php">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5-5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
        Logout
      </a>
    </div>
  </div>

</div>

<script>
function toggleProfileMenu(el) {
  el.classList.toggle('open');
  document.addEventListener('click', function handler(e) {
    if (!el.contains(e.target)) {
      el.classList.remove('open');
      document.removeEventListener('click', handler);
    }
  });
}
</script>
