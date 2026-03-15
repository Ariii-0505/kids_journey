<?php
require_once(__DIR__ . "/../../includes/db.php");
require_once __DIR__ . '/../../includes/auth.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');

    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if (!$action) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }

    // search_users does not require an id
    if (!$id && $action !== 'search_users') {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }

    // Fetch target user info for logging
    $targetUser  = $conn->query("SELECT full_name, staff_id, role FROM users WHERE id=$id")->fetch_assoc();
    $targetName  = $targetUser['full_name'] ?? "User #$id";
    $targetSid   = $targetUser['staff_id']  ?? '';
    $targetRole  = $targetUser['role']       ?? '';
    $targetLabel = $targetSid ? "$targetSid – $targetName" : $targetName;

    $newStatus = null;

    switch ($action) {
        case 'activate':
            if ($id == $_SESSION['user_id']) { echo json_encode(['success'=>false,'message'=>'Cannot activate your own account']); exit; }
            $newStatus = 'active';
            logActivity($conn,'Activated User Account','User Access Management','Success',"Account activated for: $targetLabel ($targetRole)",$targetLabel);
            break;

        case 'deactivate':
            if ($id == $_SESSION['user_id']) { echo json_encode(['success'=>false,'message'=>'Cannot deactivate your own account']); exit; }
            $newStatus = 'inactive';
            logActivity($conn,'Deactivated User Account','User Access Management','Success',"Account deactivated for: $targetLabel ($targetRole)",$targetLabel);
            break;

        case 'suspend':
            if ($id == $_SESSION['user_id']) { echo json_encode(['success'=>false,'message'=>'Cannot suspend your own account']); exit; }
            $newStatus = 'suspended';
            logActivity($conn,'Suspended User Account','User Access Management','Success',"Account suspended for: $targetLabel ($targetRole)",$targetLabel);
            break;

        case 'approve_request':
            $newStatus = 'active';
            logActivity($conn,'Approved Access Request','User Access Management','Success',"Access request approved for: $targetLabel, Role: $targetRole",$targetLabel);
            $stmt2 = $conn->prepare("UPDATE users SET status='active' WHERE id=?");
            $stmt2->bind_param("i", $id);
            $stmt2->execute();
            $userData = $conn->query("SELECT * FROM users WHERE id=$id")->fetch_assoc();
            echo json_encode(['success' => true, 'newStatus' => 'active', 'user' => $userData]);
            exit;

        case 'deny_request':
            $userInfo = $conn->query("SELECT staff_id FROM users WHERE id=$id")->fetch_assoc();
            $staffIdToRemove = $userInfo['staff_id'] ?? null;
            if ($staffIdToRemove) {
                $conn->query("DELETE FROM staff WHERE staff_id='" . $conn->real_escape_string($staffIdToRemove) . "'");
            }
            $newStatus = 'denied';
            logActivity($conn,'Rejected Access Request','User Access Management','Failed',"Access request rejected for: $targetLabel, Role: $targetRole",$targetLabel);
            break;

        case 'change_role':
            $newRole = $conn->real_escape_string($_POST['role'] ?? '');
            if ($id != $_SESSION['user_id'] && !empty($newRole)) {
                $conn->query("UPDATE users SET role='$newRole' WHERE id=$id AND role != 'Admin'");
                logActivity($conn,'Changed User Role','User Access Management','Success',"Role changed to '$newRole' for: $targetLabel",$targetLabel);
                echo json_encode(['success' => true, 'newRole' => $newRole]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Cannot change own role or invalid role']);
            }
            exit;

        case 'search_users':
            $search       = $conn->real_escape_string($_POST['search'] ?? '');
            $statusFilter = $conn->real_escape_string($_POST['status_filter'] ?? '');
            $where   = ["status != 'pending'", "status != 'denied'"];
            if ($search !== '') $where[] = "(full_name LIKE '%$search%' OR email LIKE '%$search%')";
            if ($statusFilter !== '') {
                $where[] = "status = '$statusFilter'";
            } else {
                $where[] = "status != 'suspended'";
            }
            $whereSql = 'WHERE ' . implode(' AND ', $where);
            $result = $conn->query("SELECT * FROM users $whereSql ORDER BY created_at ASC");
            $users = [];
            while ($u = $result->fetch_assoc()) $users[] = $u;
            echo json_encode(['success' => true, 'users' => $users]);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            exit;
    }

    if ($newStatus) {
        $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=?");
        $stmt->bind_param("si", $newStatus, $id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'newStatus' => $newStatus]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No rows updated']);
        }
    }
    exit;
}

// Initial page load data
$search       = $conn->real_escape_string($_GET['search'] ?? '');
$statusFilter = $conn->real_escape_string($_GET['status_filter'] ?? '');
$where        = ["status != 'pending'", "status != 'denied'"];
if ($search !== '') $where[] = "(full_name LIKE '%$search%' OR email LIKE '%$search%')";
if ($statusFilter !== '') $where[] = "status = '$statusFilter'";
else $where[] = "status != 'suspended'";
$whereSql = 'WHERE ' . implode(' AND ', $where);
$users    = $conn->query("SELECT * FROM users $whereSql ORDER BY created_at ASC");
// Store current filter for JS
$currentStatusFilter = $statusFilter;

$pending  = $conn->query("SELECT * FROM users WHERE status='pending' ORDER BY created_at ASC");
$previous = $conn->query("SELECT * FROM users WHERE status IN ('active','denied') ORDER BY created_at DESC LIMIT 5");

$activeTab = $_GET['tab'] ?? 'requests';
?>
<!DOCTYPE html>
<html>
<head>
  <title>User Access Management</title>
  <link rel="stylesheet" href="../../assets/css/hro/hro-base.css">
  <link rel="stylesheet" href="../../assets/css/administratorcss/user-access.css">
  <script src="../../assets/js/sidebar.js" defer></script>
  <style>
    /* 80% zoom for User Management tab only */
    #management { zoom: 0.8; }
  </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/administrator-sidebar.php'; ?>

<!-- Confirmation Modal -->
<div class="modal-overlay" id="confirmModal">
  <div class="confirm-modal">
    <div class="confirm-header"><span id="confirmTitle">Confirm Action</span></div>
    <div class="confirm-body">
      <p id="confirmMessage"></p>
      <div class="confirm-warn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="#92400e" style="flex-shrink:0;margin-top:1px"><path d="M12 2L1 21h22L12 2zm0 3.5L20.5 19h-17L12 5.5zM11 10v4h2v-4h-2zm0 6v2h2v-2h-2z"/></svg>
        Please review carefully before confirming this action.
      </div>
    </div>
    <div class="confirm-footer">
      <button class="confirm-footer-btn btn-cancel-sm" onclick="cancelAction()">Cancel</button>
      <button class="confirm-footer-btn btn-confirm-sm" onclick="confirmAction()">Confirm</button>
    </div>
  </div>
</div>

<div class="main">
  <div class="header">
    <h1>User Access Management</h1>
    <p>Manage user access requests, activate/deactivate accounts, and assign roles</p>
  </div>

  <div class="tabs">
    <span class="tab-pill <?= $activeTab==='requests'   ? 'active':'' ?>" data-tab="requests"   onclick="showTab('requests')">Access Requests</span>
    <span class="tab-pill <?= $activeTab==='management' ? 'active':'' ?>" data-tab="management" onclick="showTab('management')">User Management</span>
  </div>

  <!-- Access Requests Tab -->
  <div class="tab-content <?= $activeTab==='requests' ? 'active':'' ?>" id="requests">
    <div class="card">
      <h2>Pending Access Requests</h2>
      <table class="pending-table">
        <thead><tr><th>Staff ID</th><th>Name</th><th>Email</th><th>Requested Role</th><th>Status</th><th>Actions</th></tr></thead>
      </table>
      <div class="pending-table-scroll">
        <table class="pending-table-body">
          <colgroup><col style="width:12%"><col style="width:18%"><col style="width:25%"><col style="width:16%"><col style="width:12%"><col></colgroup>
          <tbody id="pendingTbody">
          <?php while ($req = $pending->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($req['staff_id'] ?? '-') ?></td>
            <td><?= htmlspecialchars($req['full_name']) ?></td>
            <td><?= htmlspecialchars($req['email']) ?></td>
            <td><?= htmlspecialchars($req['role']) ?></td>
            <td><span class="badge pending">Pending</span></td>
            <td>
              <div class="action-buttons">
                <button class="btn-accept" onclick="showConfirm('approve_request','<?= $req['id'] ?>','Approve Request','Are you sure you want to approve this access request?',this.closest('tr'))">Accept</button>
                <button class="btn-reject" onclick="showConfirm('deny_request','<?= $req['id'] ?>','Reject Request','Are you sure you want to reject this access request?',this.closest('tr'))">Reject</button>
              </div>
            </td>
          </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card">
      <h2>Previous Requests</h2>
      <table>
        <thead><tr><th>Staff ID</th><th>Name</th><th>Email</th><th>Requested Role</th><th>Status</th><th>Date Requested</th></tr></thead>
        <tbody id="previousTbody">
        <?php while ($req = $previous->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($req['staff_id'] ?? '-') ?></td>
          <td><?= htmlspecialchars($req['full_name']) ?></td>
          <td><?= htmlspecialchars($req['email']) ?></td>
          <td><?= htmlspecialchars($req['role']) ?></td>
          <td><span class="badge <?= strtolower($req['status']) ?>"><?= ucfirst($req['status']) ?></span></td>
          <td><?= date('M d, Y', strtotime($req['created_at'] ?? 'now')) ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- User Management Tab -->
  <div class="tab-content <?= $activeTab==='management' ? 'active':'' ?>" id="management">

    <div style="display:flex;gap:10px;align-items:center;margin-bottom:16px;">
      <input type="text" id="searchInput" placeholder="Search by name, email..."
             value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
             style="flex:1;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;"
             onkeydown="if(event.key==='Enter') searchUsers()">
      <select id="statusFilterSelect" onchange="searchUsers()"
              style="padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
        <option value=""         <?= $statusFilter===''         ? 'selected':'' ?>>All Status</option>
        <option value="active"   <?= $statusFilter==='active'   ? 'selected':'' ?>>Active</option>
        <option value="inactive" <?= $statusFilter==='inactive' ? 'selected':'' ?>>Inactive</option>
        <option value="suspended"<?= $statusFilter==='suspended'? 'selected':'' ?>>Suspended (Archived)</option>
      </select>
      <button onclick="searchUsers()" class="search-submit-btn">Search</button>
    </div>

    <div class="card">
      <table class="mgmt-table-header">
        <colgroup><col style="width:11%"><col style="width:18%"><col style="width:24%"><col style="width:18%"><col style="width:11%"><col></colgroup>
        <thead><tr><th>Staff ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
      </table>
      <div class="mgmt-table-scroll">
        <table class="mgmt-table-body">
          <colgroup><col style="width:11%"><col style="width:18%"><col style="width:24%"><col style="width:18%"><col style="width:11%"><col></colgroup>
          <tbody id="managementTbody">
          <?php while ($u = $users->fetch_assoc()): ?>
            <?= renderUserRow($u) ?>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
function renderUserRow($u) {
    $uidInt  = (int)($u['id']);
    $sid     = htmlspecialchars($u['staff_id'] ?? '-');
    $name    = htmlspecialchars($u['full_name']);
    $email   = htmlspecialchars($u['email']);
    $role    = $u['role'];
    $status  = $u['status'] ?? '';
    $badge   = '<span class="badge ' . htmlspecialchars($status) . '">' . ucfirst($status) . '</span>';

    if ($role === 'Admin') {
        $roleCell = '<span class="role-admin-text">Admin</span>';
    } else {
        $disabled = $status === 'suspended' ? 'disabled' : '';
        $hrSel    = $role === 'Human Resources' ? 'selected' : '';
        $edSel    = $role === 'Educator'         ? 'selected' : '';
        $roleCell = "<select onchange=\"changeRole({$uidInt},this.value)\" {$disabled} style=\"padding:4px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;\">"
                  . "<option value=\"Human Resources\" {$hrSel}>Human Resources</option>"
                  . "<option value=\"Educator\" {$edSel}>Educator</option>"
                  . "</select>";
    }

    if ($role === 'Admin') {
        $actions = '<span class="protected-text">Protected</span>';
    } elseif ($status === 'active') {
        $actions = "<button class=\"btn btn-danger\" onclick=\"showConfirm('deactivate','{$uidInt}','Deactivate Account','Deactivate this account?',this.closest('tr'))\">Deactivate</button>"
                 . " <button class=\"btn btn-warning\" onclick=\"showConfirm('suspend','{$uidInt}','Suspend Account','Suspend this account?',this.closest('tr'))\">Suspend</button>";
    } elseif ($status === 'inactive') {
        $actions = "<button class=\"btn btn-success\" onclick=\"showConfirm('activate','{$uidInt}','Activate Account','Activate this account?',this.closest('tr'))\">Activate</button>";
    } elseif ($status === 'suspended') {
        $actions = '<span class="no-actions">Archived</span>';
    } else {
        $actions = '<span class="no-actions">&mdash;</span>';
    }

    return "<tr data-user-id=\"{$uidInt}\">"
         . "<td>{$sid}</td><td>{$name}</td><td>{$email}</td>"
         . "<td>{$roleCell}</td><td>{$badge}</td>"
         . "<td><div class=\"action-buttons\">{$actions}</div></td>"
         . "</tr>";
}
?>

<script>
function showTab(tabId) {
  document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.tab-pill').forEach(el => el.classList.remove('active'));
  document.getElementById(tabId).classList.add('active');
  document.querySelector('[data-tab="'+tabId+'"]').classList.add('active');
}

// On page load, apply the current filter to the table via AJAX
document.addEventListener('DOMContentLoaded', function() {
  searchUsers();
});

let pendingAction = null, pendingId = null, pendingRow = null;

function showConfirm(actionValue, userId, title, message, rowEl) {
  pendingAction = actionValue;
  pendingId     = userId;
  pendingRow    = rowEl || null;
  document.getElementById('confirmTitle').textContent   = title;
  document.getElementById('confirmMessage').textContent = message;
  document.getElementById('confirmModal').classList.add('open');
}

function confirmAction() {
  document.getElementById('confirmModal').classList.remove('open');
  const formData = new FormData();
  formData.append('ajax', '1');
  formData.append('action', pendingAction);
  formData.append('id', pendingId);

  fetch(window.location.pathname, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (!data.success) { alert('Error: ' + data.message); return; }

      if (pendingAction === 'approve_request' || pendingAction === 'deny_request') {
        const cells      = pendingRow.querySelectorAll('td');
        const staffId    = cells[0].textContent.trim();
        const name       = cells[1].textContent.trim();
        const email      = cells[2].textContent.trim();
        const role       = cells[3].textContent.trim();
        const status     = pendingAction === 'approve_request' ? 'active' : 'denied';
        const badgeLabel = status === 'active' ? 'Active' : 'Denied';
        const today      = new Date().toLocaleDateString('en-US',{month:'short',day:'2-digit',year:'numeric'});

        pendingRow.remove();

        const prevTbody = document.getElementById('previousTbody');
        const prevRow   = document.createElement('tr');
        prevRow.innerHTML = `<td>${staffId}</td><td>${name}</td><td>${email}</td><td>${role}</td>
          <td><span class="badge ${status}">${badgeLabel}</span></td><td>${today}</td>`;
        prevTbody.insertBefore(prevRow, prevTbody.firstChild);
        while (prevTbody.rows.length > 5) prevTbody.removeChild(prevTbody.lastChild);

        if (pendingAction === 'approve_request' && data.user) {
          const u = data.user;
          const mgmtTbody = document.getElementById('managementTbody');
          if (mgmtTbody) {
            const placeholder = mgmtTbody.querySelector('td[colspan]');
            if (placeholder) placeholder.closest('tr').remove();
            const tmp = document.createElement('tbody');
            tmp.innerHTML = buildUserRow(u);
            mgmtTbody.appendChild(tmp.firstElementChild);
          }
        }

      } else {
        updateRow(pendingId, data.newStatus);
      }
    })
    .catch(err => alert('Request failed: ' + err));
}

function cancelAction() {
  document.getElementById('confirmModal').classList.remove('open');
  pendingAction = pendingId = pendingRow = null;
}

function changeRole(userId, newRole) {
  const formData = new FormData();
  formData.append('ajax', '1');
  formData.append('action', 'change_role');
  formData.append('id', userId);
  formData.append('role', newRole);
  fetch(window.location.pathname, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => { if (!data.success) alert('Role change failed: ' + data.message); });
}

function searchUsers() {
  const search = document.getElementById('searchInput').value;
  const status = document.getElementById('statusFilterSelect').value;
  const formData = new FormData();
  formData.append('ajax', '1');
  formData.append('action', 'search_users');
  formData.append('search', search);
  formData.append('status_filter', status);

  fetch(window.location.pathname, { method: 'POST', body: formData })
    .then(function(r) { return r.text(); })
    .then(function(text) {
      var data;
      try { data = JSON.parse(text); }
      catch(e) {
        // Server returned non-JSON — likely a PHP error, log it
        console.error('searchUsers response:', text);
        return;
      }
      if (!data.success) {
        console.warn('searchUsers failed:', data.message);
        return;
      }
      const tbody = document.getElementById('managementTbody');
      if (!data.users || data.users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:20px;">No users found.</td></tr>';
        return;
      }
      tbody.innerHTML = data.users.map(u => buildUserRow(u)).join('');
    })
    .catch(function(err) { console.error('searchUsers fetch error:', err); });
}

function buildUserRow(u) {
  const uid    = u.id;
  const sid    = u.staff_id ?? '-';
  const status = u.status ?? '';
  const role   = u.role ?? '';

  let roleCell = '';
  if (role === 'Admin') {
    roleCell = '<span class="role-admin-text">Admin</span>';
  } else {
    const dis   = status === 'suspended' ? 'disabled' : '';
    const hrSel = role === 'Human Resources' ? 'selected' : '';
    const edSel = role === 'Educator' ? 'selected' : '';
    roleCell = `<select onchange="changeRole(${uid},this.value)" ${dis} style="padding:4px 8px;border:1px solid #d1d5db;border-radius:4px;font-size:13px;">
      <option value="Human Resources" ${hrSel}>Human Resources</option>
      <option value="Educator" ${edSel}>Educator</option>
    </select>`;
  }

  let actions = '';
  if (role === 'Admin') {
    actions = '<span class="protected-text">Protected</span>';
  } else if (status === 'active') {
    actions = `<button class="btn btn-danger" onclick="showConfirm('deactivate','${uid}','Deactivate Account','Deactivate this account?',this.closest('tr'))">Deactivate</button>
               <button class="btn btn-warning" onclick="showConfirm('suspend','${uid}','Suspend Account','Suspend this account?',this.closest('tr'))">Suspend</button>`;
  } else if (status === 'inactive') {
    actions = `<button class="btn btn-success" onclick="showConfirm('activate','${uid}','Activate Account','Activate this account?',this.closest('tr'))">Activate</button>`;
  } else if (status === 'suspended') {
    actions = '<span class="no-actions">Archived</span>';
  } else {
    actions = '<span class="no-actions">—</span>';
  }

  return `<tr data-user-id="${uid}">
    <td>${sid}</td><td>${u.full_name ?? ''}</td><td>${u.email ?? ''}</td>
    <td>${roleCell}</td>
    <td><span class="badge ${status}">${status.charAt(0).toUpperCase()+status.slice(1)}</span></td>
    <td><div class="action-buttons">${actions}</div></td>
  </tr>`;
}

function updateRow(userId, newStatus) {
  const row = document.querySelector('tr[data-user-id="' + userId + '"]');
  if (!row) return;

  const currentFilter = document.getElementById('statusFilterSelect').value;

  // Suspended — always remove from view unless viewing suspended list
  if (newStatus === 'suspended' && currentFilter !== 'suspended') {
    row.style.transition = 'opacity 0.3s';
    row.style.opacity = '0';
    setTimeout(() => row.remove(), 300);
    return;
  }

  // Deactivated while viewing active — remove
  if (newStatus === 'inactive' && currentFilter === 'active') {
    row.style.transition = 'opacity 0.3s';
    row.style.opacity = '0';
    setTimeout(() => row.remove(), 300);
    return;
  }

  // Activated while viewing inactive — remove
  if (newStatus === 'active' && currentFilter === 'inactive') {
    row.style.transition = 'opacity 0.3s';
    row.style.opacity = '0';
    setTimeout(() => row.remove(), 300);
    return;
  }

  // Otherwise update in place
  const badge = row.querySelector('.badge');
  if (badge) { badge.className = 'badge ' + newStatus; badge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1); }

  const actionCell = row.querySelector('.action-buttons');
  if (newStatus === 'active') {
    actionCell.innerHTML =
      `<button class="btn btn-danger" onclick="showConfirm('deactivate','${userId}','Deactivate Account','Deactivate this account?',this.closest('tr'))">Deactivate</button>
       <button class="btn btn-warning" onclick="showConfirm('suspend','${userId}','Suspend Account','Suspend this account?',this.closest('tr'))">Suspend</button>`;
  } else if (newStatus === 'inactive') {
    actionCell.innerHTML =
      `<button class="btn btn-success" onclick="showConfirm('activate','${userId}','Activate Account','Activate this account?',this.closest('tr'))">Activate</button>`;
  } else if (newStatus === 'suspended') {
    actionCell.innerHTML = '<span class="no-actions">Archived</span>';
    const sel = row.querySelector('select');
    if (sel) sel.disabled = true;
  }
}
</script>
</body>
</html>