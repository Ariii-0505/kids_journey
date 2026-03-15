<?php
require_once(__DIR__ . "/../../includes/db.php");
require_once __DIR__ . '/../../includes/auth.php';

// Ensure archived column exists in students table
$colCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'archived'");
if (!$colCheck || $colCheck->num_rows == 0) {
    $conn->query("ALTER TABLE students ADD COLUMN archived TINYINT(1) DEFAULT 0");
}

// Ensure archived column exists in enrollments table
$colCheckEnroll = $conn->query("SHOW COLUMNS FROM enrollments LIKE 'archived'");
if (!$colCheckEnroll || $colCheckEnroll->num_rows == 0) {
    $conn->query("ALTER TABLE enrollments ADD COLUMN archived TINYINT(1) DEFAULT 0");
}

/* =========================
   UPDATE STUDENT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    $sid     = (int)$_POST['student_id'];
    $first   = $_POST['first_name'];
    $middle  = $_POST['middle_name'];
    $last    = $_POST['last_name'];
    $suffix  = $_POST['suffix'];
    $dob     = $_POST['date_of_birth'];
    $gender  = $_POST['gender'];
    $address = $_POST['address'];
    $status  = $_POST['student_status'];
    $gname   = $_POST['guardian_name'];
    $gcontact= $_POST['contact_number'];
    $gemail  = $_POST['email'];

    $stmt = $conn->prepare("UPDATE students SET first_name=?,middle_name=?,last_name=?,suffix=?,date_of_birth=?,gender=?,address=?,status=? WHERE id=?");
    $stmt->bind_param("ssssssssi",$first,$middle,$last,$suffix,$dob,$gender,$address,$status,$sid);
    $stmt->execute();

    $stmt2 = $conn->prepare("UPDATE guardians SET guardian_name=?,contact_number=?,email=? WHERE student_id=?");
    $stmt2->bind_param("sssi",$gname,$gcontact,$gemail,$sid);
    $stmt2->execute();

    $_SESSION['toast'] = "Student record updated.";
    header("Location: student-records.php");
    exit;
}

/* =========================
   ARCHIVE STUDENT
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive_student_id'])) {
    $sid = (int)$_POST['archive_student_id'];
    
    // Get student name for logging
    $studentName = $conn->query("SELECT full_name FROM students WHERE id = $sid")->fetch_assoc()['full_name'] ?? 'Unknown';
    
    // Archive the student record
    $stmt = $conn->prepare("UPDATE students SET archived = 1 WHERE id = ?");
    $stmt->bind_param("i", $sid);
    $stmt->execute();
    
    // Also archive related enrollments
    $conn->query("UPDATE enrollments SET archived = 1 WHERE student_id = $sid");
    
    // Also archive related payments
    $conn->query("UPDATE payments SET archived = 1 WHERE student_id = $sid");
    
    // Activity log
    $log_user_id  = $_SESSION['user_id'] ?? null;
    $log_staff_id = $_SESSION['staff_id'] ?? null;
    $log_role     = $_SESSION['role']     ?? 'Human Resources';
    $log_action   = 'Archived Student';
    $log_module   = 'Student Records';
    $log_status   = 'Success';
    $log_details  = 'Archived student record: ' . $studentName . ' (ID: ' . $sid . ')';
    $log_ip       = $_SERVER['REMOTE_ADDR'];

    $logStmt = $conn->prepare("INSERT INTO activity_logs 
        (user_id, staff_id, role, action, module, status, details, ip_address)
        VALUES (?,?,?,?,?,?,?,?)");
    $logStmt->bind_param("isssssss",
        $log_user_id, $log_staff_id, $log_role,
        $log_action, $log_module, $log_status,
        $log_details, $log_ip
    );
    $logStmt->execute();

    $_SESSION['toast'] = "Student archived successfully.";
    header("Location: student-records.php");
    exit;
}

/* =========================
   FETCH DATA
========================= */
$search  = $_GET['search'] ?? '';
$sortBy  = $_GET['sort_by'] ?? 'Student';
$sortDir = $_GET['sort_dir'] ?? 'Ascending';

$orderMap = ['Student'=>'s.full_name','Date'=>'e.created_at','Status'=>'e.status'];
$col = $orderMap[$sortBy] ?? 's.full_name';
$dir = $sortDir === 'Descending' ? 'DESC' : 'ASC';
$se  = $conn->real_escape_string($search);

$sql = "SELECT s.id as student_db_id, s.student_id, s.full_name as student_name,
               s.first_name, s.middle_name, s.last_name, s.suffix,
               s.date_of_birth, s.gender, s.address, s.status as student_status,
               g.guardian_name, g.contact_number, g.email,
               e.status as enrollment_status, e.created_at as enrolled_date, e.id as enrollment_id
        FROM students s
        LEFT JOIN enrollments e ON e.student_id = s.id
        LEFT JOIN guardians g ON s.id = g.student_id
        WHERE (s.archived IS NULL OR s.archived = 0)
        AND (s.full_name LIKE '%$se%' OR s.student_id LIKE '%$se%' OR g.guardian_name LIKE '%$se%')
        ORDER BY $col $dir";

$result   = $conn->query($sql);
$students = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Stats
$activeCount  = $conn->query("SELECT COUNT(*) as c FROM enrollments e LEFT JOIN students s ON e.student_id = s.id WHERE e.status='Active' AND (s.archived IS NULL OR s.archived = 0)")->fetch_assoc()['c'];
$pendingCount = $conn->query("SELECT COUNT(*) as c FROM enrollments e LEFT JOIN students s ON e.student_id = s.id WHERE e.status='Pending' AND (s.archived IS NULL OR s.archived = 0)")->fetch_assoc()['c'];
$totalCount   = $conn->query("SELECT COUNT(*) as c FROM students WHERE (archived IS NULL OR archived = 0)")->fetch_assoc()['c'];

$toast = $_SESSION['toast'] ?? '';
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Records – HRO</title>
  <link rel="stylesheet" href="../../assets/css/hro/hro-base.css">
  <link rel="stylesheet" href="../../assets/css/hro/student-records.css">
  <script src="../../assets/js/sidebar.js" defer></script>
</head>
<body>

<?php include __DIR__ . '/../../includes/hro-sidebar.php'; ?>

<div class="main">

  <div class="page-hero">
    <div class="page-hero-text">
      <h1>Student Records</h1>
      <p>Manage all student informations</p>
    </div>
  </div>

  <div style="padding:24px 36px;display:flex;flex-direction:column;gap:20px;">

    <!-- Stat Cards -->
    <div class="stats-row" style="padding:0;">
      <div class="stat-card">
        <div class="stat-info">
          <span class="stat-label">Active Sessions</span>
          <h2 class="stat-number"><?= $activeCount ?></h2>
          <span class="stat-sub">enrolled</span>
        </div>
        <div class="stat-icon stat-green">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="#16a34a"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-info">
          <span class="stat-label">Pending Enrollment</span>
          <h2 class="stat-number" style="color:#f59e0b;"><?= $pendingCount ?></h2>
        </div>
        <div class="stat-icon stat-orange">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="#d97706"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-info">
          <span class="stat-label">Total Students</span>
          <h2 class="stat-number"><?= $totalCount ?></h2>
        </div>
        <div class="stat-icon stat-blue">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="#2563eb"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v3h20v-3c0-3.3-6.7-5-10-5z"/></svg>
        </div>
      </div>
    </div>

    <!-- Table Card -->
    <div class="dashboard-card">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
        <form method="GET" class="toolbar" style="margin:0;flex:1;">
          <input type="text" name="search" class="toolbar-search"
                 placeholder="Search student..."
                 value="<?= htmlspecialchars($search) ?>"
                 onkeydown="if(event.key==='Enter'){this.form.submit();}">
          <span class="toolbar-label">Sort By:</span>
          <select name="sort_by" class="toolbar-select" onchange="this.form.submit()">
            <?php foreach(['Student','Date','Status'] as $o): ?>
              <option <?= $sortBy==$o?'selected':'' ?>><?= $o ?></option>
            <?php endforeach; ?>
          </select>
          <select name="sort_dir" class="toolbar-select" onchange="this.form.submit()">
            <option <?= $sortDir=='Ascending'?'selected':'' ?>>Ascending</option>
            <option <?= $sortDir=='Descending'?'selected':'' ?>>Descending</option>
          </select>
        </form>
        <a href="export-students.php" class="btn-outline" style="font-size:12px;padding:8px 14px;">⬇ Export</a>
      </div>

      <table class="data-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>Guardian</th>
            <th>Contact</th>
            <th>Status</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($students) > 0): ?>
            <?php foreach ($students as $s): ?>
            <tr>
              <td>
                <strong style="display:block;"><?= htmlspecialchars($s['student_name']) ?></strong>
                <small style="color:#9ca3af;"><?= htmlspecialchars($s['student_id']) ?></small>
              </td>
              <td><?= htmlspecialchars($s['guardian_name'] ?? '-') ?></td>
              <td><?= htmlspecialchars($s['contact_number'] ?? '-') ?></td>
              <td>
                <span class="badge badge-<?= $s['enrollment_status'] ?? $s['student_status'] ?>">
                  <?= $s['enrollment_status'] ?? $s['student_status'] ?>
                </span>
              </td>
              <td style="font-size:12px;color:#6b7280;">
                <?= $s['enrolled_date'] ? date('M j', strtotime($s['enrolled_date'])) : '-' ?>
              </td>
              <td>
                <button class="btn-icon" title="Edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($s)) ?>)">
                  <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm17.71-10.21c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                </button>
                <button class="btn-icon danger" title="Archive" onclick="openArchiveModal(<?= $s['student_db_id'] ?>, '<?= htmlspecialchars($s['student_name']) ?>')">
                  <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M20.54 5.23l-1.39-1.68C18.88 3.21 18.47 3 18 3H6c-.47 0-.88.21-1.16.55L3.46 5.23C3.17 5.57 3 6.02 3 6.5V19c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6.5c0-.48-.17-.93-.46-1.27zM12 17.5L6.5 12H10v-2h4v2h3.5L12 17.5zM5.12 5l.81-1h12l.94 1H5.12z"/></svg>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:30px;">No student records found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ======================== EDIT STUDENT MODAL ======================== -->
<div class="modal-overlay" id="editModal">
  <div class="modal modal-wide">
    <div class="modal-header">
      <h3>Edit Student Information</h3>
      <div style="display:flex;gap:8px;align-items:center;">
        <button class="btn-outline" style="font-size:12px;padding:6px 12px;" onclick="window.print()">🖨 Print</button>
        <button class="modal-close" onclick="closeModal('editModal')">×</button>
      </div>
    </div>
    <form method="POST" id="editForm">
      <input type="hidden" name="update_student" value="1">
      <input type="hidden" name="student_id" id="edit_student_id">
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
            <label>Suffix</label>
            <input type="text" name="suffix" id="edit_suffix">
          </div>
          <div class="form-group">
            <label>Date of Birth</label>
            <input type="date" name="date_of_birth" id="edit_dob">
          </div>
          <div class="form-group">
            <label>Gender</label>
            <select name="gender" id="edit_gender">
              <option value="">Select Gender</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="form-group form-full">
            <label>Address</label>
            <textarea name="address" id="edit_address"></textarea>
          </div>
          <div class="form-section">Guardian Information</div>
          <div class="form-group form-full">
            <label>Guardian Name</label>
            <input type="text" name="guardian_name" id="edit_guardian_name" placeholder="Full name of guardian/parent">
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
            <label>Student Status</label>
            <select name="student_status" id="edit_status">
              <option value="Active">Active</option>
              <option value="Pending">Pending</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-outline" onclick="openCancelEditConfirm()">Cancel</button>
        <button type="submit" class="btn-primary">✓ Edit Information</button>
      </div>
    </form>
  </div>
</div>

<!-- ARCHIVE CONFIRM -->
<div class="modal-overlay" id="archiveModal">
  <div class="confirm-modal">
    <div class="confirm-header" style="background:#f59e0b;">ARCHIVE STUDENT <button onclick="closeModal('archiveModal')" style="background:none;border:none;color:white;font-size:18px;cursor:pointer;">×</button></div>
    <div class="confirm-body"><p id="archive_msg">Are you sure you want to archive this student record?</p></div>
    <div class="confirm-footer">
      <button class="btn-cancel-sm" onclick="closeModal('archiveModal')">Cancel</button>
      <form method="POST" id="archiveStudentForm" style="display:inline;">
        <input type="hidden" name="archive_student_id" id="archive_student_id">
        <button type="submit" class="btn-confirm-sm">Archive</button>
      </form>
    </div>
  </div>
</div>

<!-- CANCEL EDIT CONFIRM -->
<div class="modal-overlay" id="cancelEditModal">
  <div class="confirm-modal">
    <div class="confirm-header" style="background:#374151;">CANCEL <button onclick="closeModal('cancelEditModal')" style="background:none;border:none;color:white;font-size:18px;cursor:pointer;">×</button></div>
    <div class="confirm-body"><p>Are you sure you want to cancel editing this information? Unsaved changes will be lost.</p></div>
    <div class="confirm-footer">
      <button class="btn-cancel-sm" onclick="closeModal('cancelEditModal')">Cancel</button>
      <button class="btn-confirm-sm dark" onclick="closeModal('cancelEditModal');closeModal('editModal')">Confirm</button>
    </div>
  </div>
</div>

<?php if ($toast): ?>
<div class="toast" id="toast"><?= htmlspecialchars($toast) ?></div>
<script>setTimeout(()=>{let t=document.getElementById('toast');if(t){t.style.opacity='0';setTimeout(()=>t.remove(),500);}},3000);</script>
<?php endif; ?>

<script>
function openEditModal(s) {
  document.getElementById('edit_student_id').value    = s.student_db_id;
  document.getElementById('edit_first_name').value    = s.first_name || '';
  document.getElementById('edit_last_name').value     = s.last_name  || '';
  document.getElementById('edit_middle_name').value   = s.middle_name || '';
  document.getElementById('edit_suffix').value        = s.suffix || '';
  document.getElementById('edit_dob').value           = s.date_of_birth || '';
  document.getElementById('edit_gender').value        = s.gender || '';
  document.getElementById('edit_address').value       = s.address || '';
  document.getElementById('edit_guardian_name').value = s.guardian_name || '';
  document.getElementById('edit_contact').value       = s.contact_number || '';
  document.getElementById('edit_email').value         = s.email || '';
  document.getElementById('edit_status').value        = s.student_status || 'Active';
  document.getElementById('editModal').classList.add('open');
}
function openArchiveModal(id, name) {
  document.getElementById('archive_student_id').value = id;
  document.getElementById('archive_msg').textContent = 'Are you sure you want to archive ' + name + '? This will also archive their enrollment and payment records.';
  document.getElementById('archiveModal').classList.add('open');
}
function openCancelEditConfirm() {
  document.getElementById('cancelEditModal').classList.add('open');
}
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(el => {
  el.addEventListener('click', e => { if(e.target===el) el.classList.remove('open'); });
});
</script>
</body>
</html>
