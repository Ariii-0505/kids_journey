<?php
require_once(__DIR__ . "/../../includes/db.php");
require_once __DIR__ . '/../../includes/auth.php';

$id = null; // ✅ initialize to avoid "undefined variable"

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];

    /* =========================
       Activate User
    ========================== */
    if ($_POST['action'] === 'activate') {
        if ($id == $_SESSION['user_id']) {
            // Prevent self-activation (optional safeguard)
            logActivity(
                $conn,
                $_SESSION['user_id'],
                $_SESSION['role'],
                "attempted self-activation",
                "User Management",
                "denied",
                "User tried to activate their own account"
            );
        } else {
            $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=?");
            $status = 'active';
            $stmt->bind_param("si", $status, $id);
            $stmt->execute();

            logActivity(
                $conn,
                $_SESSION['user_id'],
                $_SESSION['role'],
                "activated user",
                "User Management",
                "approved",
                "User ID $id was activated"
            );
        }
    }

    /* =========================
       Suspend User
    ========================== */
    if ($_POST['action'] === 'suspend') {
        if ($id == $_SESSION['user_id']) {
            // Prevent self-suspension
            logActivity(
                $conn,
                $_SESSION['user_id'],
                $_SESSION['role'],
                "attempted self-suspension",
                "User Management",
                "denied",
                "User tried to suspend their own account"
            );
        } else {
            $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=?");
            $status = 'suspended';
            $stmt->bind_param("si", $status, $id);
            $stmt->execute();

            logActivity(
                $conn,
                $_SESSION['user_id'],
                $_SESSION['role'],
                "suspended user",
                "User Management",
                "approved",
                "User ID $id was suspended"
            );
        }
    }

    /* =========================
       Change Role
    ========================== */
    if ($_POST['action'] === 'role') {
        if ($id == $_SESSION['user_id']) {
            logActivity(
                $conn,
                $_SESSION['user_id'],
                $_SESSION['role'],
                "attempted self-role change",
                "User Management",
                "denied",
                "User tried to change their own role"
            );
        } else {
            $role = $conn->real_escape_string($_POST['role']);
            $conn->query("UPDATE users SET role='$role' WHERE id=$id");

            logActivity(
                $conn,
                $_SESSION['user_id'],
                $_SESSION['role'],
                "changed role",
                "User Management",
                "approved",
                "User ID $id role changed to $role"
            );
        }
    }

    /* =========================
       Approve Request
    ========================== */
    if ($_POST['action'] === 'approve_request') {
        $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=?");
        $status = 'approved';
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();

        logActivity(
            $conn,
            $_SESSION['user_id'],
            $_SESSION['role'],
            "approved request",
            "User Access Management",
            "approved",
            "User ID $id request approved"
        );
    }

    /* =========================
       Deny Request
    ========================== */
    if ($_POST['action'] === 'deny_request') {
        $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=?");
        $status = 'denied';
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();

        logActivity(
            $conn,
            $_SESSION['user_id'],
            $_SESSION['role'],
            "denied request",
            "User Access Management",
            "denied",
            "User ID $id request denied"
        );
    }
}

// ✅ Fetch users with optional search
$search = '';
if (isset($_GET['search']) && $_GET['search'] !== '') {
    $search = $conn->real_escape_string($_GET['search']);
    $users = $conn->query("
        SELECT * FROM users 
        WHERE full_name LIKE '%$search%' 
           OR email LIKE '%$search%'
    ");
} else {
    $users = $conn->query("SELECT * FROM users");
}

// ✅ Helper function for activity logs
function logActivity($conn, $userId, $role, $action, $module, $status, $details) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("
        INSERT INTO activity_logs 
        (user_id, role, action, module, status, details, ip_address) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issssss", $userId, $role, $action, $module, $status, $details, $ip);
    $stmt->execute();
}

// ✅ Determine active tab (default: requests)
$activeTab = $_GET['tab'] ?? 'requests';
?>

<!DOCTYPE html>
<html>
<head>
  <title>User Access Management</title>
  <link rel="stylesheet" href="../../assets/css/superadmincss/user-access.css">
  <script src="../../assets/js/sidebar.js" defer></script>
  <script>
    // ✅ Simple tab switcher
    function showTab(tabId) {
      document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
      document.querySelectorAll('.tab-pill').forEach(el => el.classList.remove('active'));
      document.getElementById(tabId).classList.add('active');
      document.querySelector('[data-tab="'+tabId+'"]').classList.add('active');
    }
  </script>
</head>

<body>
<?php include __DIR__ . '/../../includes/superadmin-sidebar.php'; ?>

<div class="main">
  <div class="header">
    <h1>User Access Management</h1>
    <p>Manage user access requests, activate/deactivate accounts, and assign roles</p>
  </div>

  <!-- ✅ Tabs -->
  <div class="tabs">
    <span class="tab-pill <?= $activeTab === 'requests' ? 'active' : '' ?>"  
          data-tab="requests" onclick="showTab('requests')">
      Access Requests
    </span>
    <span class="tab-pill <?= $activeTab === 'management' ? 'active' : '' ?>"  
          data-tab="management" onclick="showTab('management')">
      User Management
    </span>
  </div>

  <!-- ✅ Access Requests Tab -->
  <div class="tab-content <?= $activeTab === 'requests' ? 'active' : '' ?>" id="requests">
    <div class="card">
      <h2>Pending Access Requests</h2>
      <table>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Requested Role</th>
          <th>Actions</th>
        </tr>
        <?php
        $pending = $conn->query("SELECT * FROM users WHERE status='pending'");
        while ($req = $pending->fetch_assoc()):
        ?>
        <tr>
          <td><?= htmlspecialchars($req['full_name']) ?></td>
          <td><?= htmlspecialchars($req['email']) ?></td>
          <td><?= htmlspecialchars($req['role']) ?></td>
          <td>
            <form method="POST" style="display:inline">
              <input type="hidden" name="id" value="<?= $req['id'] ?>">
              <input type="hidden" name="action" value="approve_request">
              <button class="btn btn-success">Approve</button>
            </form>
            <form method="POST" style="display:inline">
              <input type="hidden" name="id" value="<?= $req['id'] ?>">
              <input type="hidden" name="action" value="deny_request">
              <button class="btn btn-danger">Deny</button>
            </form>
          </td>
        </tr>
        <?php endwhile; ?>
      </table>
    </div>

    <div class="card">
      <h2>Previous Requests</h2>
      <table>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Requested Role</th>
          <th>Status</th>
        </tr>
        <?php
        $previous = $conn->query("SELECT * FROM users WHERE status IN ('approved','denied')");
        while ($req = $previous->fetch_assoc()):
        ?>
        <tr>
          <td><?= htmlspecialchars($req['full_name']) ?></td>
          <td><?= htmlspecialchars($req['email']) ?></td>
          <td><?= htmlspecialchars($req['role']) ?></td>
          <td><?= ucfirst($req['status']) ?></td>
        </tr>
        <?php endwhile; ?>
      </table>
    </div>
  </div>

<!-- ✅ User Management Tab -->
<div class="tab-content <?= $activeTab === 'management' ? 'active' : '' ?>" id="management">
  <!-- Search box -->
  <form method="GET" class="search-box">
    <input type="hidden" name="tab" value="management">
    <span class="search-icon">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none"
        viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M21 21l-4.35-4.35m1.85-5.4a7.25 7.25 0 11-14.5 0 7.25 7.25 0 0114.5 0z" />
      </svg>
    </span>
    <input type="text" name="search" placeholder="Search by name, email..."  
           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" />
  </form>

  <div class="card">
    <table>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>

      <?php while ($u = $users->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($u['full_name']) ?></td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td>
          <form method="POST">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <input type="hidden" name="action" value="role">
            <select name="role" onchange="this.form.submit()">
              <?php foreach (['Admin','Human Resources','Educator'] as $r): ?>
                <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>>
                  <?= $r ?>
                </option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <td>
          <span class="badge <?= $u['status'] ?>">
            <?= ucfirst($u['status']) ?>
          </span>
        </td>
        <td>
          <!-- ✅ Activate button -->
          <form method="POST" style="display:inline">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <input type="hidden" name="action" value="activate">
            <button class="btn btn-success">Activate</button>
          </form>

          <!-- ✅ Suspend button -->
          <form method="POST" style="display:inline">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <input type="hidden" name="action" value="suspend">
            <button class="btn btn-danger">Suspend</button>
          </form>
        </td>
      </tr>
      <?php endwhile; ?>
    </table>
  </div>
</div>


</div>
</body>
</html>

