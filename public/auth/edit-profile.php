<?php
require_once __DIR__ . '/../../includes/config.php';

if (!isLoggedIn()) {
    redirect(BASE_URL . "/auth/login.php");
}

// Fetch current user
$stmt = $conn->prepare("SELECT id, full_name, username, email, role FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$loggedUser = $stmt->get_result()->fetch_assoc();

if (!$loggedUser) {
    session_unset(); session_destroy();
    redirect(BASE_URL . "/auth/login.php");
}

// Determine back URL based on role
$backUrls = [
    'Admin'           => BASE_URL . '/administrator/dashboard.php',
    'Human Resources' => BASE_URL . '/hro/dashboard.php',
    'Educator'        => BASE_URL . '/educator/educator-dashboard.php',
];
$backUrl = $_GET['back'] ?? ($backUrls[$loggedUser['role']] ?? BASE_URL . '/auth/login.php');

$success = '';
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName   = trim(sanitize($_POST['full_name'] ?? ''));
    $username   = trim(sanitize($_POST['username']  ?? ''));
    $curPass    = $_POST['current_password']  ?? '';
    $newPass    = $_POST['new_password']       ?? '';
    $confirmPass= $_POST['confirm_password']   ?? '';

    if (empty($fullName)) $errors[] = 'Full name is required.';
    if (empty($username))  $errors[] = 'Username is required.';

    // Check username uniqueness
    if ($username !== $loggedUser['username']) {
        $chk = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $chk->bind_param("si", $username, $loggedUser['id']);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) $errors[] = 'Username is already taken.';
    }

    $changingPassword = !empty($curPass) || !empty($newPass) || !empty($confirmPass);
    $hashedNew = null;

    if ($changingPassword) {
        // Verify current password
        $ps = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $ps->bind_param("i", $loggedUser['id']);
        $ps->execute();
        $row = $ps->get_result()->fetch_assoc();
        if (!password_verify($curPass, $row['password'])) {
            $errors[] = 'Current password is incorrect.';
        }
        if (strlen($newPass) < 8 || strlen($newPass) > 16) {
            $errors[] = 'New password must be 8–16 characters.';
        } elseif (!preg_match('/[A-Z]/', $newPass)) {
            $errors[] = 'New password must include an uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $newPass)) {
            $errors[] = 'New password must include a lowercase letter.';
        } elseif (!preg_match('/[0-9]/', $newPass)) {
            $errors[] = 'New password must include a number.';
        } elseif (!preg_match('/[!@#$%^&*]/', $newPass)) {
            $errors[] = 'New password must include a symbol (!@#$%^&*).';
        } elseif ($newPass !== $confirmPass) {
            $errors[] = 'Passwords do not match.';
        } else {
            $hashedNew = password_hash($newPass, PASSWORD_DEFAULT);
        }
    }

    if (empty($errors)) {
        if ($hashedNew) {
            $upd = $conn->prepare("UPDATE users SET full_name=?, username=?, password=? WHERE id=?");
            $upd->bind_param("sssi", $fullName, $username, $hashedNew, $loggedUser['id']);
        } else {
            $upd = $conn->prepare("UPDATE users SET full_name=?, username=? WHERE id=?");
            $upd->bind_param("ssi", $fullName, $username, $loggedUser['id']);
        }
        $upd->execute();
        $success = 'Profile updated successfully.';
        // Refresh user data
        $stmt->execute();
        $loggedUser = $stmt->get_result()->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Profile – <?= SITE_NAME ?></title>
  <link rel="stylesheet" href="../../assets/css/edit-profile.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="ep-wrapper">

  <!-- Back button -->
  <div class="ep-topbar">
    <a href="<?= htmlspecialchars($backUrl) ?>" class="btn-back">
      <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      Back
    </a>
  </div>

  <!-- Page heading -->
  <h1 class="ep-title">Edit Profile</h1>

  <!-- Card -->
  <div class="ep-card">

    <!-- Avatar section -->
    <div class="ep-avatar-section">
      <div class="ep-avatar">
        <span><?= strtoupper(substr($loggedUser['full_name'], 0, 1)) ?></span>
      </div>
      <div class="ep-avatar-info">
        <strong><?= htmlspecialchars($loggedUser['full_name']) ?></strong>
        <span><?= htmlspecialchars($loggedUser['email']) ?></span>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $e): ?>
          <div>• <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="POST" id="editProfileForm">
      <input type="hidden" name="back" value="<?= htmlspecialchars($backUrl) ?>">

      <!-- Name -->
      <div class="ep-field">
        <label for="full_name">Name</label>
        <input type="text" id="full_name" name="full_name"
               value="<?= htmlspecialchars($loggedUser['full_name']) ?>" required>
      </div>

      <!-- Username -->
      <div class="ep-field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username"
               value="<?= htmlspecialchars($loggedUser['username']) ?>" required>
      </div>

      <!-- Change Password section -->
      <div class="ep-section-title">Change Own Password</div>

      <div class="ep-field">
        <label for="current_password">Current Password</label>
        <div class="pw-wrap">
          <input type="password" id="current_password" name="current_password"
                 placeholder="Enter current password" autocomplete="current-password">
          <button type="button" class="pw-toggle" onclick="togglePw('current_password', this)">
            <i class="fa-solid fa-eye"></i>
          </button>
        </div>
      </div>

      <div class="ep-field">
        <label for="new_password">New Password</label>
        <div class="pw-wrap">
          <input type="password" id="new_password" name="new_password"
                 placeholder="Enter new password" autocomplete="new-password"
                 oninput="checkPwRequirements(this.value)">
          <button type="button" class="pw-toggle" onclick="togglePw('new_password', this)">
            <i class="fa-solid fa-eye"></i>
          </button>
        </div>
        <!-- Requirements list (always visible when field has focus/value) -->
        <ul class="pw-requirements" id="pwReqs">
          <li id="req-length">Password must be at least 8–16 characters</li>
          <li id="req-upper">Including a mix of uppercase letters</li>
          <li id="req-lower">Lowercase letters</li>
          <li id="req-number">Numbers</li>
          <li id="req-symbol">Symbols (!@#$%^&*)</li>
        </ul>
      </div>

      <div class="ep-field">
        <label for="confirm_password">Confirm Password</label>
        <div class="pw-wrap">
          <input type="password" id="confirm_password" name="confirm_password"
                 placeholder="Re-enter new password" autocomplete="new-password"
                 oninput="checkPwMatch()">
          <button type="button" class="pw-toggle" onclick="togglePw('confirm_password', this)">
            <i class="fa-solid fa-eye"></i>
          </button>
        </div>
        <div class="pw-mismatch" id="pwMismatch" style="display:none;">
          <i class="fa-solid fa-triangle-exclamation"></i> Password is not matching. Please try again.
        </div>
      </div>

      <!-- Action buttons -->
      <div class="ep-actions">
        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn-cancel">Cancel</a>
        <button type="submit" class="btn-save">Save</button>
      </div>
    </form>

  </div><!-- /ep-card -->

</div><!-- /ep-wrapper -->

<script>
function togglePw(fieldId, btn) {
  const input = document.getElementById(fieldId);
  const icon  = btn.querySelector('i');
  if (input.type === 'password') {
    input.type = 'text';
    icon.classList.replace('fa-eye', 'fa-eye-slash');
  } else {
    input.type = 'password';
    icon.classList.replace('fa-eye-slash', 'fa-eye');
  }
}

const reqRules = {
  'req-length' : v => v.length >= 8 && v.length <= 16,
  'req-upper'  : v => /[A-Z]/.test(v),
  'req-lower'  : v => /[a-z]/.test(v),
  'req-number' : v => /[0-9]/.test(v),
  'req-symbol' : v => /[!@#$%^&*]/.test(v),
};

function checkPwRequirements(val) {
  const reqs = document.getElementById('pwReqs');
  if (!val) { reqs.style.display = 'none'; return; }
  reqs.style.display = 'block';
  Object.entries(reqRules).forEach(([id, fn]) => {
    const li = document.getElementById(id);
    li.classList.toggle('req-met', fn(val));
    li.classList.toggle('req-fail', !fn(val));
  });
  checkPwMatch();
}

function checkPwMatch() {
  const np = document.getElementById('new_password').value;
  const cp = document.getElementById('confirm_password').value;
  const msg = document.getElementById('pwMismatch');
  if (!cp) { msg.style.display = 'none'; return; }
  msg.style.display = (np !== cp) ? 'flex' : 'none';
}

// Hide requirements list initially
document.getElementById('pwReqs').style.display = 'none';
</script>

</body>
</html>
