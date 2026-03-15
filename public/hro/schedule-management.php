<?php
require_once(__DIR__ . "/../../includes/db.php");
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/config.php';

/* =========================
   ADD / EDIT / DELETE SCHEDULE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_schedule') {
        $type       = $_POST['schedule_type'] ?? 'Student';
        $studentId  = !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;
        $staffId    = !empty($_POST['staff_id'])   ? (int)$_POST['staff_id']   : null;
        $serviceId  = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;
        $title      = trim($_POST['schedule_title'] ?? '');
        $days       = isset($_POST['days']) ? implode(',', $_POST['days']) : '';
        $startTime  = $_POST['start_time'];
        $endTime    = $_POST['end_time'];
        $room       = $_POST['room'] ?? null;
        $assignTo   = !empty($_POST['assign_staff_id']) ? (int)$_POST['assign_staff_id'] : null;
        $desc       = trim($_POST['description'] ?? '');

        $stmt = $conn->prepare("INSERT INTO schedules (schedule_type,title,student_id,staff_id,service_id,day_of_week,start_time,end_time,room,description,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssiiiissssi",$type,$title,$studentId,$staffId,$serviceId,$days,$startTime,$endTime,$room,$desc,$_SESSION['user_id']);
        $stmt->execute();
        
        // Log the activity - Added Schedule
        logActivity(
            $conn,
            $_SESSION['user_id'] ?? 0,
            $_SESSION['role'] ?? 'Unknown',
            'Added Schedule',
            'Schedule Management',
            'Success',
            'New ' . $type . ' schedule added: ' . $title . ' (' . $days . ' ' . $startTime . '-' . $endTime . ')'
        );
        
        $_SESSION['toast'] = "Schedule added!";
        header("Location: schedule-management.php");
        exit;
    }

    if ($action === 'edit_schedule') {
        $sid        = (int)$_POST['schedule_db_id'];
        $studentId  = !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;
        $staffId    = !empty($_POST['staff_id'])   ? (int)$_POST['staff_id']   : null;
        $serviceId  = !empty($_POST['service_id']) ? (int)$_POST['service_id'] : null;
        $title      = trim($_POST['schedule_title'] ?? '');
        $days       = isset($_POST['days']) ? implode(',', $_POST['days']) : '';
        $startTime  = $_POST['start_time'];
        $endTime    = $_POST['end_time'];
        $room       = $_POST['room'] ?? null;
        $desc       = trim($_POST['description'] ?? '');

        $stmt = $conn->prepare("UPDATE schedules SET title=?,student_id=?,staff_id=?,service_id=?,day_of_week=?,start_time=?,end_time=?,room=?,description=? WHERE id=?");
        $stmt->bind_param("siiiissss i",$title,$studentId,$staffId,$serviceId,$days,$startTime,$endTime,$room,$desc,$sid);
        $stmt->execute();
        
        // Log the activity - Edited Schedule
        logActivity(
            $conn,
            $_SESSION['user_id'] ?? 0,
            $_SESSION['role'] ?? 'Unknown',
            'Edited',
            'Schedule Management',
            'Success',
            'Schedule updated: ' . $title . ' (ID: ' . $sid . ')'
        );
        
        $_SESSION['toast'] = "Schedule updated!";
        header("Location: schedule-management.php");
        exit;
    }

    if ($action === 'delete_schedule') {
        $sid = (int)$_POST['schedule_db_id'];
        
        // Get schedule details before deleting for logging
        $schedInfo = $conn->query("SELECT title, schedule_type FROM schedules WHERE id=$sid")->fetch_assoc();
        $schedName = $schedInfo ? ($schedInfo['title'] . ' (' . $schedInfo['schedule_type'] . ')') : 'ID: ' . $sid;
        
        $conn->query("DELETE FROM schedules WHERE id=$sid");
        
        // Log the activity - Deleted Schedule
        logActivity(
            $conn,
            $_SESSION['user_id'] ?? 0,
            $_SESSION['role'] ?? 'Unknown',
            'Deleted',
            'Schedule Management',
            'Success',
            'Schedule removed: ' . $schedName
        );
        
        $_SESSION['toast'] = "Schedule deleted.";
        header("Location: schedule-management.php");
        exit;
    }
}

/* =========================
   FETCH DATA
========================= */
$tab = $_GET['tab'] ?? 'student';

$studentSchedules = $conn->query("
    SELECT sch.*, st.full_name AS student_name, st.student_id AS student_no,
           stf.full_name AS staff_name, stf.staff_id AS staff_no,
           svc.service_name, svc.program_name
    FROM schedules sch
    LEFT JOIN students st  ON sch.student_id = st.id
    LEFT JOIN staff stf    ON sch.staff_id   = stf.id
    LEFT JOIN services svc ON sch.service_id = svc.service_id
    WHERE sch.schedule_type = 'Student' AND sch.status != 'Cancelled'
    ORDER BY sch.created_at DESC
");

$staffSchedules = $conn->query("
    SELECT sch.*, stf.full_name AS staff_name, stf.staff_id AS staff_no,
           st.full_name AS student_name,
           svc.service_name, svc.program_name
    FROM schedules sch
    LEFT JOIN staff stf    ON sch.staff_id   = stf.id
    LEFT JOIN students st  ON sch.student_id = st.id
    LEFT JOIN services svc ON sch.service_id = svc.service_id
    WHERE sch.schedule_type = 'Staff' AND sch.status != 'Cancelled'
    ORDER BY sch.created_at DESC
");

$studentCount = $studentSchedules->num_rows;
$staffCount   = $staffSchedules->num_rows;
$totalCount   = $studentCount + $staffCount;

// For dropdowns
$studentList = $conn->query("SELECT id, student_id, full_name FROM students WHERE status='Active' ORDER BY full_name");
$staffList   = $conn->query("SELECT id, staff_id, full_name FROM staff WHERE status='Active' ORDER BY full_name");
$serviceList = $conn->query("SELECT service_id, program_name, service_name FROM services WHERE status='active' ORDER BY program_name, service_name");

$roomOptions = ['SML 1','SML 2','SLL 1','SLL 2','OT 1','SLP 1','ABA 1','TIL 1','Session Hall','Conference Room'];
$dayOptions  = ['Monday','Tuesday','Wednesday','Thursday','Friday'];

$dayChipClass = ['Monday'=>'day-mon','Tuesday'=>'day-tue','Wednesday'=>'day-wed','Thursday'=>'day-thu','Friday'=>'day-fri'];
$dayAbbr      = ['Monday'=>'Mon','Tuesday'=>'Tue','Wednesday'=>'Wed','Thursday'=>'Thu','Friday'=>'Fri'];

$toast = $_SESSION['toast'] ?? '';
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Schedule Management – HRO</title>
  <link rel="stylesheet" href="../../assets/css/hro/hro-base.css">
  <link rel="stylesheet" href="../../assets/css/hro/schedule-management.css">
  <script src="../../assets/js/sidebar.js" defer></script>
</head>
<body>

<?php include __DIR__ . '/../../includes/hro-sidebar.php'; ?>

<div class="main">

  <div class="page-hero">
    <div class="page-hero-text">
      <h1>Schedule Management</h1>
      <p>Manage and oversee student and staff schedules</p>
    </div>
  </div>

  <div style="padding:24px 36px;display:flex;flex-direction:column;gap:20px;">

    <!-- Stat Cards -->
    <div class="stats-row" style="padding:0;">
      <div class="stat-card">
        <div class="stat-info">
          <span class="stat-label">Student Schedules</span>
          <h2 class="stat-number"><?= $studentCount ?></h2>
        </div>
        <div class="stat-icon stat-blue">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="#2563eb"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3z"/></svg>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-info">
          <span class="stat-label">Staff Schedules</span>
          <h2 class="stat-number"><?= $staffCount ?></h2>
        </div>
        <div class="stat-icon stat-green">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="#16a34a"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v3h20v-3c0-3.3-6.7-5-10-5z"/></svg>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-info">
          <span class="stat-label">Total</span>
          <h2 class="stat-number"><?= $totalCount ?></h2>
        </div>
        <div class="stat-icon stat-orange">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="#d97706"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
        </div>
      </div>
    </div>

    <!-- Table Card -->
    <div class="dashboard-card">

      <!-- Tabs + Sort + Add Button -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
        <div class="sched-tabs">
          <a href="?tab=student" class="sched-tab <?= $tab==='student'?'active':'' ?>">Student Schedules (<?= $studentCount ?>)</a>
          <a href="?tab=staff"   class="sched-tab <?= $tab==='staff'  ?'active':'' ?>">Staff Schedules (<?= $staffCount ?>)</a>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
          <span style="font-size:12px;color:#6b7280;">Sort By:</span>
          <select class="toolbar-select" style="font-size:12px;">
            <option>Name</option><option>Day</option><option>Time</option>
          </select>
          <button class="btn-primary" onclick="openAddModal()">+ Add Schedule</button>
        </div>
      </div>

      <!-- Student Schedules Tab -->
      <?php if ($tab === 'student'): ?>
      <table class="data-table">
        <thead>
          <tr>
            <th>Service/Assessment</th>
            <th>Student ID</th>
            <th>Assign To</th>
            <th>Day</th>
            <th>Time</th>
            <th>Room</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $studentSchedules->data_seek(0);
          if ($studentSchedules->num_rows > 0):
            while ($row = $studentSchedules->fetch_assoc()):
              $days = explode(',', $row['day_of_week']);
          ?>
          <tr>
            <td><?= htmlspecialchars($row['service_name'] ?? $row['title'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['student_no'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['staff_name'] ?? '-') ?></td>
            <td>
              <?php foreach ($days as $d): $d=trim($d); ?>
                <span class="day-chip <?= $dayChipClass[$d] ?? '' ?>"><?= $dayAbbr[$d] ?? $d ?></span>
              <?php endforeach; ?>
            </td>
            <td style="font-size:12px;">
              <?= date('g:i A', strtotime($row['start_time'])) ?> – <?= date('g:i A', strtotime($row['end_time'])) ?>
            </td>
            <td><span class="room-badge"><?= htmlspecialchars($row['room'] ?? '-') ?></span></td>
            <td>
              <button class="btn-icon" title="Edit" onclick="openEditStudentModal(<?= htmlspecialchars(json_encode($row)) ?>)">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm17.71-10.21c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
              </button>
              <button class="btn-icon danger" title="Delete" onclick="openDeleteModal(<?= $row['id'] ?>)">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zm13-15h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
              </button>
            </td>
          </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:30px;">No student schedules.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <!-- Staff Schedules Tab -->
      <?php else: ?>
      <table class="data-table">
        <thead>
          <tr>
            <th>Appointment</th>
            <th>Assign To</th>
            <th>Day</th>
            <th>Time</th>
            <th>Room</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $staffSchedules->data_seek(0);
          if ($staffSchedules->num_rows > 0):
            while ($row = $staffSchedules->fetch_assoc()):
              $days = explode(',', $row['day_of_week']);
          ?>
          <tr>
            <td><?= htmlspecialchars($row['service_name'] ?? $row['title'] ?? '-') ?></td>
            <td><?= htmlspecialchars($row['staff_name'] ?? '-') ?></td>
            <td>
              <?php foreach ($days as $d): $d=trim($d); ?>
                <span class="day-chip <?= $dayChipClass[$d] ?? '' ?>"><?= $dayAbbr[$d] ?? $d ?></span>
              <?php endforeach; ?>
            </td>
            <td style="font-size:12px;">
              <?= date('g:i A', strtotime($row['start_time'])) ?> – <?= date('g:i A', strtotime($row['end_time'])) ?>
            </td>
            <td><span class="room-badge"><?= htmlspecialchars($row['room'] ?? '-') ?></span></td>
            <td>
              <button class="btn-icon" title="Edit" onclick="openEditStaffModal(<?= htmlspecialchars(json_encode($row)) ?>)">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm17.71-10.21c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
              </button>
              <button class="btn-icon danger" title="Delete" onclick="openDeleteModal(<?= $row['id'] ?>)">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zm13-15h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
              </button>
            </td>
          </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:30px;">No staff schedules.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      <?php endif; ?>

    </div>
  </div>
</div>

<!-- ======================== ADD SCHEDULE MODAL ======================== -->
<div class="modal-overlay" id="addModal">
  <div class="modal modal-wide">
    <div class="modal-header">
      <h3>Add Schedule</h3>
      <button class="modal-close" onclick="closeModal('addModal')">×</button>
    </div>
    <form method="POST" id="addForm">
      <input type="hidden" name="action" value="add_schedule">
      <div class="modal-body">
        <div class="form-grid">
          <div class="form-group form-full">
            <label>Schedule Type</label>
            <select name="schedule_type" id="add_type" onchange="toggleTypeFields(this.value)">
              <option value="Student">Student</option>
              <option value="Staff">Staff</option>
            </select>
          </div>
          <div class="form-group" id="add_student_field">
            <label>Student</label>
            <select name="student_id">
              <option value="">Select Student</option>
              <?php $studentList->data_seek(0); while($s=$studentList->fetch_assoc()): ?>
                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= $s['student_id'] ?>)</option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Schedule Title</label>
            <input type="text" name="schedule_title" placeholder="e.g. Therapy Session">
          </div>
          <div class="form-group">
            <label>Service</label>
            <select name="service_id">
              <option value="">Select Service</option>
              <?php $serviceList->data_seek(0); while($sv=$serviceList->fetch_assoc()): ?>
                <option value="<?= $sv['service_id'] ?>"><?= htmlspecialchars($sv['program_name']) ?> – <?= htmlspecialchars($sv['service_name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group form-full">
            <label>Day of Week</label>
            <div class="day-checkboxes">
              <?php foreach ($dayOptions as $d): ?>
                <label class="day-check-label">
                  <input type="checkbox" name="days[]" value="<?= $d ?>"> <?= $d ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="form-group">
            <label>Start Time *</label>
            <input type="time" name="start_time" required>
          </div>
          <div class="form-group">
            <label>End Time *</label>
            <input type="time" name="end_time" required>
          </div>
          <div class="form-group">
            <label>Room</label>
            <select name="room">
              <option value="">Select Room</option>
              <?php foreach ($roomOptions as $r): ?>
                <option value="<?= $r ?>"><?= $r ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Assign To (Staff)</label>
            <select name="assign_staff_id">
              <option value="">Select Staff</option>
              <?php $staffList->data_seek(0); while($stf=$staffList->fetch_assoc()): ?>
                <option value="<?= $stf['id'] ?>"><?= htmlspecialchars($stf['full_name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="form-group form-full">
            <label>Description</label>
            <textarea name="description" placeholder="Notes or description..."></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-outline" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn-primary">+ Add Schedule</button>
      </div>
    </form>
  </div>
</div>

<!-- ======================== DELETE CONFIRM ======================== -->
<div class="modal-overlay" id="deleteModal">
  <div class="confirm-modal">
    <div class="confirm-header">CONFIRMATION <button onclick="closeModal('deleteModal')" style="background:none;border:none;color:white;font-size:18px;cursor:pointer;">×</button></div>
    <form method="POST" id="deleteForm">
      <input type="hidden" name="action" value="delete_schedule">
      <input type="hidden" name="schedule_db_id" id="delete_sched_id">
      <div class="confirm-body"><p>Are you sure you want to delete this schedule?</p></div>
      <div class="confirm-footer">
        <button type="button" class="btn-cancel-sm" onclick="closeModal('deleteModal')">Cancel</button>
        <button type="submit" class="btn-confirm-sm">Confirm</button>
      </div>
    </form>
  </div>
</div>

<?php if ($toast): ?>
<div class="toast" id="toast"><?= htmlspecialchars($toast) ?></div>
<script>setTimeout(()=>{let t=document.getElementById('toast');if(t){t.style.opacity='0';setTimeout(()=>t.remove(),500);}},3000);</script>
<?php endif; ?>

<script>
function openAddModal() { document.getElementById('addModal').classList.add('open'); }
function openDeleteModal(id) {
  document.getElementById('delete_sched_id').value = id;
  document.getElementById('deleteModal').classList.add('open');
}
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.modal-overlay').forEach(el => {
  el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
});
</script>

</body>
</html>
