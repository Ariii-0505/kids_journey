<?php
require_once(__DIR__ . "/../../includes/db.php");
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/config.php';

/* =========================
   ADD / EDIT / DELETE STAFF
========================= */

// ADD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_staff') {
        // First, ensure users table has pending/denied status options
        $conn->query("ALTER TABLE users MODIFY COLUMN status ENUM('active','suspended','pending','denied') DEFAULT 'active'");
        
        $first   = trim($_POST['first_name']);
        $middle  = trim($_POST['middle_name'] ?? '');
        $last    = trim($_POST['last_name']);
        $pos     = $_POST['position'];
        $dept    = $_POST['department'];
        $dob     = $_POST['date_of_birth'] ?? null;
        $gender  = $_POST['gender'] ?? null;
        $contact = trim($_POST['contact_number'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $status  = $_POST['status'] ?? 'Active';
        $role    = $_POST['role'] ?? 'Educator';

        // Auto-generate staff_id — check BOTH tables so deleted records never cause duplicates
        $maxStaff = $conn->query("SELECT MAX(CAST(SUBSTRING(staff_id,5) AS UNSIGNED)) as n FROM staff WHERE staff_id LIKE 'STF-%'")->fetch_assoc()['n'] ?? 0;
        $maxUsers = $conn->query("SELECT MAX(CAST(SUBSTRING(staff_id,5) AS UNSIGNED)) as n FROM users WHERE staff_id LIKE 'STF-%'")->fetch_assoc()['n'] ?? 0;
        $nextNum  = max((int)$maxStaff, (int)$maxUsers) + 1;
        $staffId  = 'STF-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare("INSERT INTO staff (staff_id,first_name,middle_name,last_name,position,department,date_of_birth,gender,contact_number,email,address,status,role) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("sssssssssssss", $staffId,$first,$middle,$last,$pos,$dept,$dob,$gender,$contact,$email,$address,$status,$role);
        if ($stmt->execute()) {
            $newStaffDbId = $conn->insert_id;
            
            // Auto-generate credentials
            // Username: firstname + last 4 digits of contact number (e.g. juan1234)
            $last4Contact = preg_replace('/[^0-9]/', '', $contact);
            $last4Contact = substr($last4Contact, -4);
            $username = strtolower($first) . $last4Contact;
            
            // Password: lastname + birthdate YYYYMMDD (e.g. delacruz19900115)
            $dobForPassword = ($dob) ? date('Ymd', strtotime($dob)) : date('Ymd');
            $plainPassword = strtolower($last) . $dobForPassword;
            $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT);
            
            // Set status to 'pending' - Admin must approve before login
            $userStatus = 'pending';
            
            // Ensure users table has pending/denied status options (run once if needed)
            $conn->query("ALTER TABLE users MODIFY COLUMN status ENUM('active','suspended','pending','denied') DEFAULT 'active'");
            
            $conn->query("INSERT INTO users (staff_id,full_name,username,email,password,role,status,is_first_login) VALUES ('$staffId','" . $conn->real_escape_string("$first $last") . "','$username','" . $conn->real_escape_string($email) . "','$hashedPassword','$role','$userStatus',1)");
            
            // Check if user was created
            if ($conn->affected_rows > 0) {
                $userId = $conn->insert_id;
                $conn->query("UPDATE staff SET user_id=$userId WHERE id=$newStaffDbId");
                
                // Log the activity - Added Staff
                logActivity(
                    $conn,
                    $_SESSION['user_id'] ?? 0,
                    $_SESSION['role'] ?? 'Unknown',
                    'Added Staff',
                    'Staff Management',
                    'Success',
                    'New staff added: ' . $first . ' ' . $last . ' (' . $staffId . ') - Position: ' . $pos
                );
                
                $_SESSION['toast'] = "Staff added successfully! Username: $username - Waiting for Admin approval.";
            } else {
                $_SESSION['toast'] = "Staff created but user account failed. Error: " . $conn->error;
            }
        }
        header("Location: staff-management.php");
        exit;
    }

    if ($action === 'edit_staff') {
        $sid     = (int)$_POST['staff_db_id'];
        $first   = trim($_POST['first_name']);
        $middle  = trim($_POST['middle_name'] ?? '');
        $last    = trim($_POST['last_name']);
        $pos     = $_POST['position'];
        $dept    = $_POST['department'];
        $dob     = $_POST['date_of_birth'] ?? null;
        $gender  = $_POST['gender'] ?? null;
        $contact = trim($_POST['contact_number'] ?? '');
        $email   = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $status  = $_POST['status'] ?? 'Active';

        $stmt = $conn->prepare("UPDATE staff SET first_name=?,middle_name=?,last_name=?,position=?,department=?,date_of_birth=?,gender=?,contact_number=?,email=?,address=?,status=? WHERE id=?");
        $stmt->bind_param("sssssssssssi",$first,$middle,$last,$pos,$dept,$dob,$gender,$contact,$email,$address,$status,$sid);
        $stmt->execute();
        
        // Log the activity - Edited Staff
        logActivity(
            $conn,
            $_SESSION['user_id'] ?? 0,
            $_SESSION['role'] ?? 'Unknown',
            'Edited',
            'Staff Management',
            'Success',
            'Staff record updated: ' . $first . ' ' . $last . ' (ID: ' . $sid . ')'
        );
        
        $_SESSION['toast'] = "Staff updated successfully!";
        header("Location: staff-management.php");
        exit;
    }

    if ($action === 'delete_staff') {
        $sid = (int)$_POST['staff_db_id'];
        
        // Get staff details before deleting for logging
        $staffInfo = $conn->query("SELECT staff_id, first_name, last_name FROM staff WHERE id=$sid")->fetch_assoc();
        $staffName = $staffInfo ? ($staffInfo['first_name'] . ' ' . $staffInfo['last_name'] . ' (' . $staffInfo['staff_id'] . ')') : 'ID: ' . $sid;
        
        $conn->query("DELETE FROM staff WHERE id=$sid");
        
        // Log the activity - Deleted Staff
        logActivity(
            $conn,
            $_SESSION['user_id'] ?? 0,
            $_SESSION['role'] ?? 'Unknown',
            'Deleted',
            'Staff Management',
            'Success',
            'Staff removed: ' . $staffName
        );
        
        $_SESSION['toast'] = "Staff removed.";
        header("Location: staff-management.php");
        exit;
    }
}

/* =========================
   FETCH
========================= */
$search  = $_GET['search'] ?? '';
$sortBy  = $_GET['sort_by'] ?? 'Staff';
$sortDir = $_GET['sort_dir'] ?? 'Ascending';
$statusFilter = $_GET['status_filter'] ?? 'active';

// Ensure archived status exists in staff table
$conn->query("ALTER TABLE staff MODIFY COLUMN status ENUM('Active','Inactive','On Leave','Archived') DEFAULT 'Active'");

$orderMap = [
    'Staff'      => 'st.full_name',
    'Staff ID'   => 'st.staff_id',
    'Role'       => 'st.position',
    'Status'     => 'st.status',
];
$col = $orderMap[$sortBy] ?? 'st.staff_id';
$dir = $sortDir === 'Descending' ? 'DESC' : 'ASC';

$searchEsc = $conn->real_escape_string($search);
$statusWhere = "";
if ($statusFilter === 'active') {
    $statusWhere = "AND st.status = 'Active'";
} elseif ($statusFilter === 'inactive') {
    $statusWhere = "AND st.status = 'Inactive'";
} elseif ($statusFilter === 'on_leave') {
    $statusWhere = "AND st.status = 'On Leave'";
} elseif ($statusFilter === 'archived') {
    $statusWhere = "AND st.status = 'Archived'";
}

$sql = "SELECT st.*, u.email as user_email FROM staff st LEFT JOIN users u ON st.user_id=u.id
    WHERE (st.full_name LIKE '%$searchEsc%' OR st.staff_id LIKE '%$searchEsc%' OR st.email LIKE '%$searchEsc%')
    $statusWhere
    ORDER BY $col $dir";
$staffList = $conn->query($sql);

$toast = $_SESSION['toast'] ?? '';
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Staff Management – HRO</title>
  <link rel="stylesheet" href="../../assets/css/hro/hro-base.css">
  <link rel="stylesheet" href="../../assets/css/hro/staff-management.css">
  <script src="../../assets/js/sidebar.js" defer></script>
</head>
<body>

<?php include __DIR__ . '/../../includes/hro-sidebar.php'; ?>

<div class="main">

  <div class="page-hero">
    <div class="page-hero-text">
      <h1>Staff Management</h1>
      <p>Manage staff and positions</p>
    </div>
  </div>

  <div style="padding:24px 36px;display:flex;flex-direction:column;gap:20px;">

    <div class="dashboard-card">

      <!-- Toolbar -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
        <form method="GET" class="toolbar" style="margin:0;flex:1;">
          <input type="text" name="search" class="toolbar-search"
                 placeholder="Search by name, ID, email..."
                 value="<?= htmlspecialchars($search) ?>"
                 onkeydown="if(event.key==='Enter'){this.form.submit();}">
          <span class="toolbar-label">Status:</span>
          <select name="status_filter" class="toolbar-select" onchange="this.form.submit()">
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            <option value="on_leave" <?= $statusFilter === 'on_leave' ? 'selected' : '' ?>>On Leave</option>
            <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : '' ?>>Archived</option>
            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
          </select>
          <span class="toolbar-label">Sort By:</span>
          <select name="sort_by" class="toolbar-select" onchange="this.form.submit()">
            <?php foreach(['Staff','Staff ID','Role','Status'] as $opt): ?>
              <option <?= $sortBy==$opt?'selected':'' ?>><?= $opt ?></option>
            <?php endforeach; ?>
          </select>
          <select name="sort_dir" class="toolbar-select" onchange="this.form.submit()">
            <option <?= $sortDir=='Ascending'?'selected':'' ?>>Ascending</option>
            <option <?= $sortDir=='Descending'?'selected':'' ?>>Descending</option>
          </select>
        </form>
        <div style="display:flex;gap:8px;">
          <a href="export-staff.php" class="btn-outline" style="font-size:12px;padding:8px 14px;">⬇ Export</a>
          <button class="btn-primary" onclick="openAddModal()">+ Add Staff</button>
        </div>
      </div>

      <!-- Table -->
      <table class="data-table">
        <thead>
          <tr>
            <th>Staff ID</th>
            <th>Staff</th>
            <th>Role</th>
            <th>Contact</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($staffList && $staffList->num_rows > 0): ?>
            <?php while ($s = $staffList->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($s['staff_id']) ?></td>
              <td>
                <strong style="display:block;"><?= htmlspecialchars($s['full_name']) ?></strong>
                <small style="color:#9ca3af;"><?= htmlspecialchars($s['email'] ?? $s['user_email'] ?? '') ?></small>
              </td>
              <td><?= htmlspecialchars($s['position']) ?></td>
              <td><?= htmlspecialchars($s['contact_number'] ?? '-') ?></td>
              <td><span class="badge badge-<?= $s['status'] ?>"><?= $s['status'] ?></span></td>
              <td>
                <button class="btn-icon" title="Edit"
                  onclick="openEditModal(<?= htmlspecialchars(json_encode($s)) ?>)">
                  <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm17.71-10.21c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                </button>
                <button class="btn-icon danger" title="Delete"
                  onclick="openDeleteModal(<?= $s['id'] ?>, '<?= htmlspecialchars($s['full_name']) ?>')">
                  <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zm13-15h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                </button>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:30px;">No staff records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ======================== ADD STAFF MODAL ======================== -->
<div class="modal-overlay" id="addModal">
  <div class="modal modal-wide">
    <div class="modal-header">
      <h3>Add New Staff</h3>
      <button class="modal-close" onclick="closeModal('addModal')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_staff">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>First Name *</label>
            <input type="text" name="first_name" required placeholder="e.g. John">
          </div>
          <div class="form-group">
            <label>Last Name *</label>
            <input type="text" name="last_name" required placeholder="e.g. Doe">
          </div>
          <div class="form-group">
            <label>Middle Name</label>
            <input type="text" name="middle_name" placeholder="e.g. Miff">
          </div>
          <div class="form-group">
            <label>Position *</label>
            <select name="position" required>
              <option value="">Select Position</option>
              <option value="OTPR">OTPR – Occupational Therapist</option>
              <option value="RSLP">RSLP – Speech Language Pathologist</option>
              <option value="LPT">LPT – Licensed Professional Teacher</option>
              <option value="RSW">RSW – Registered Social Worker</option>
            </select>
          </div>
          <div class="form-group">
            <label>Department *</label>
            <select name="department" required>
              <option value="">Select Department</option>
              <option value="Education Department">Education Department</option>
              <option value="Therapy Department">Therapy Department</option>
            </select>
          </div>
          <div class="form-group">
            <label>Date of Birth</label>
            <input type="date" name="date_of_birth">
          </div>
          <div class="form-group">
            <label>Gender</label>
            <select name="gender">
              <option value="">Select Gender</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="form-group">
            <label>Contact Number</label>
            <input type="text" name="contact_number" placeholder="+63 0000000000">
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" placeholder="e.g. email@domain.com">
          </div>
          <div class="form-group form-full">
            <label>Address</label>
            <input type="text" name="address" placeholder="Home address">
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status">
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
              <option value="On Leave">On Leave</option>
            </select>
          </div>
          <div class="form-group">
            <label>Role *</label>
            <select name="role" required>
              <option value="Educator">Educator</option>
              <option value="Human Resources">Human Resources</option>
            </select>
          </div>
        </div>
        <p style="font-size:11px;color:#9ca3af;margin-top:12px;">
          ℹ A system account will be auto-created with pending status. <strong>Username:</strong> firstname + last 4 digits of contact (e.g. john1234). <strong>Password:</strong> lastname + birthdate YYYYMMDD (e.g. doe19900115). Admin must approve before login.
        </p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-outline" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn-primary">+ Add Staff</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================== EDIT STAFF MODAL ======================== -->
<div class="modal-overlay" id="editModal">
  <div class="modal modal-wide">
    <div class="modal-header">
      <h3>Edit Staff Information</h3>
      <button class="modal-close" onclick="closeModal('editModal')">×</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit_staff">
      <input type="hidden" name="staff_db_id" id="edit_staff_db_id">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group">
            <label>First Name *</label>
            <input type="text" name="first_name" id="edit_first_name" required>
          </div>
          <div class="form-group">
            <label>Last Name *</label>
            <input type="text" name="last_name" id="edit_last_name" required>
          </div>
          <div class="form-group">
            <label>Middle Name</label>
            <input type="text" name="middle_name" id="edit_middle_name">
          </div>
          <div class="form-group">
            <label>Position *</label>
            <select name="position" id="edit_position" required>
              <option value="OTPR">OTPR</option>
              <option value="RSLP">RSLP</option>
              <option value="LPT">LPT</option>
              <option value="RSW">RSW</option>
            </select>
          </div>
          <div class="form-group">
            <label>Department *</label>
            <select name="department" id="edit_department" required>
              <option value="Education Department">Education Department</option>
              <option value="Therapy Department">Therapy Department</option>
            </select>
          </div>
          <div class="form-group">
            <label>Date of Birth</label>
            <input type="date" name="date_of_birth" id="edit_dob">
          </div>
          <div class="form-group">
            <label>Gender</label>
            <select name="gender" id="edit_gender">
              <option value="">Select</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="form-group">
            <label>Contact Number</label>
            <input type="text" name="contact_number" id="edit_contact">
          </div>
          <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" id="edit_email">
          </div>
          <div class="form-group form-full">
            <label>Address</label>
            <input type="text" name="address" id="edit_address">
          </div>
          <div class="form-group">
            <label>Status</label>
            <select name="status" id="edit_status">
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
              <option value="On Leave">On Leave</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-outline" onclick="openCancelConfirm()">Cancel</button>
        <button type="submit" class="btn-primary">✓ Edit Staff</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================== DELETE CONFIRM ======================== -->
<div class="modal-overlay" id="deleteModal">
  <div class="confirm-modal">
    <div class="confirm-header">CONFIRMATION <button onclick="closeModal('deleteModal')" style="background:none;border:none;color:white;font-size:18px;cursor:pointer;">×</button></div>
    <div class="confirm-body">
      <p>Are you sure you want to proceed with deleting this staff?</p>
      <form method="POST" id="deleteForm">
        <input type="hidden" name="action" value="delete_staff">
        <input type="hidden" name="staff_db_id" id="delete_staff_id">
      </form>
    </div>
    <div class="confirm-footer">
      <button class="btn-cancel-sm" onclick="closeModal('deleteModal')">Cancel</button>
      <button class="btn-confirm-sm" onclick="document.getElementById('deleteForm').submit()">Confirm</button>
    </div>
  </div>
</div>

<!-- ======================== CANCEL CONFIRM (edit) ======================== -->
<div class="modal-overlay" id="cancelEditModal">
  <div class="confirm-modal">
    <div class="confirm-header" style="background:#374151;">CANCEL <button onclick="closeModal('cancelEditModal')" style="background:none;border:none;color:white;font-size:18px;cursor:pointer;">×</button></div>
    <div class="confirm-body">
      <p>Are you sure you want to cancel editing this information? All entered information will be lost.</p>
    </div>
    <div class="confirm-footer">
      <button class="btn-cancel-sm" onclick="closeModal('cancelEditModal')">Cancel</button>
      <button class="btn-confirm-sm dark" onclick="closeModal('cancelEditModal');closeModal('editModal')">Confirm</button>
    </div>
  </div>
</div>

<?php if ($toast): ?>
<div class="toast" id="toast"><?= htmlspecialchars($toast) ?></div>
<script>setTimeout(()=>{ let t=document.getElementById('toast'); if(t){t.style.opacity='0'; setTimeout(()=>t.remove(),500);} }, 3000);</script>
<?php endif; ?>

<script>
function openAddModal() {
  document.getElementById('addModal').classList.add('open');
}
function openEditModal(s) {
  document.getElementById('edit_staff_db_id').value = s.id;
  document.getElementById('edit_first_name').value  = s.first_name || '';
  document.getElementById('edit_last_name').value   = s.last_name || '';
  document.getElementById('edit_middle_name').value = s.middle_name || '';
  document.getElementById('edit_position').value    = s.position || '';
  document.getElementById('edit_department').value  = s.department || '';
  document.getElementById('edit_dob').value         = s.date_of_birth || '';
  document.getElementById('edit_gender').value      = s.gender || '';
  document.getElementById('edit_contact').value     = s.contact_number || '';
  document.getElementById('edit_email').value       = s.email || '';
  document.getElementById('edit_address').value     = s.address || '';
  document.getElementById('edit_status').value      = s.status || 'Active';
  document.getElementById('editModal').classList.add('open');
}
function openDeleteModal(id, name) {
  document.getElementById('delete_staff_id').value = id;
  document.getElementById('deleteModal').classList.add('open');
}
function openCancelConfirm() {
  document.getElementById('cancelEditModal').classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}
// Close on backdrop click
document.querySelectorAll('.modal-overlay').forEach(el => {
  el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
});
</script>

</body>
</html>
