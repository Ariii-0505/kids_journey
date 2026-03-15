<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<div class="sidebar" id="sidebar">

  <div class="sidebar-top">
    <div class="brand">
      <button class="toggle-btn" onclick="toggleSidebar()">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
      </button>

      <div class="logo">E</div>

      <div class="brand-text">
        <strong>Educator</strong>
        <span>Dashboard</span>
      </div>
    </div>

    <nav>
      <a class="<?= $currentPage == 'educator-dashboard.php' ? 'active' : '' ?>"
         href="educator-dashboard.php" data-tooltip="My Schedule">
        <svg viewBox="0 0 24 24"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
        <span>My Schedule</span>
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
      <a href="<?= BASE_URL ?>/auth/edit-profile.php?back=<?= urlencode(BASE_URL . '/educator/educator-dashboard.php') ?>">
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
