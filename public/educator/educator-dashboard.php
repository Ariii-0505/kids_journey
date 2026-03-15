<?php
require_once(__DIR__ . "/../../includes/db.php");
require_once __DIR__ . '/../../includes/auth.php';

/* =========================
   GET EDUCATOR'S STAFF RECORD
========================= */
$staffRecord = $conn->query("SELECT * FROM staff WHERE user_id = " . (int)$_SESSION['user_id'])->fetch_assoc();
$staffDbId   = $staffRecord['id'] ?? null;

/* =========================
   RECORD ATTENDANCE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_attendance'])) {
    $schedId    = (int)$_POST['schedule_id'];
    $studentId  = (int)$_POST['student_id'];
    $attendStatus = $_POST['attend_status']; // Present or Absent
    $today      = date('Y-m-d');

    // Check if already recorded
    $exists = $conn->query("SELECT id FROM attendance WHERE schedule_id=$schedId AND student_id=$studentId AND session_date='$today'")->num_rows;
    if (!$exists) {
        $stmt = $conn->prepare("INSERT INTO attendance (schedule_id, student_id, staff_id, session_date, status, recorded_by) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("iiissi", $schedId, $studentId, $staffDbId, $today, $attendStatus, $_SESSION['user_id']);
        $stmt->execute();
    }
    $_SESSION['toast'] = "Attendance recorded: $attendStatus";
    header("Location: educator-dashboard.php");
    exit;
}

/* =========================
   WEEK RANGE
========================= */
$weekOffset = (int)($_GET['week'] ?? 0);
$today      = new DateTime();
$today->modify("$weekOffset weeks");
$dayOfWeek  = (int)$today->format('N'); // 1=Mon, 7=Sun
$weekStart  = clone $today;
$weekStart->modify('-' . ($dayOfWeek - 1) . ' days');
$weekEnd    = clone $weekStart;
$weekEnd->modify('+4 days'); // Mon–Fri

$weekLabel = $weekStart->format('M j') . '–' . $weekEnd->format('j, Y');

$days = [];
for ($i = 0; $i < 5; $i++) {
    $d = clone $weekStart;
    $d->modify("+$i days");
    $days[] = $d;
}

$todayStr = date('Y-m-d');

/* =========================
   FETCH SCHEDULES FOR WEEK
========================= */
$dayNames = ['Monday','Tuesday','Wednesday','Thursday','Friday'];
$schedByDay = [];
$totalStudentsSet = [];
$totalSessions = 0;

// Initialize all days with empty arrays to avoid undefined key warnings
foreach ($dayNames as $d) {
    $schedByDay[$d] = [];
}

if ($staffDbId && !empty($days)) {
    foreach ($days as $idx => $dayObj) {
        $dayName = $dayNames[$idx] ?? '';
        if (empty($dayName)) continue;
        
        $res = $conn->query("
            SELECT sch.*, st.full_name AS student_name, st.id AS student_db_id,
                   st.student_id AS student_no, svc.service_name, svc.program_name,
                   g.guardian_name
            FROM schedules sch
            LEFT JOIN students st  ON sch.student_id = st.id
            LEFT JOIN services svc ON sch.service_id = svc.service_id
            LEFT JOIN guardians g  ON st.id = g.student_id
            WHERE sch.staff_id = $staffDbId
              AND FIND_IN_SET('$dayName', sch.day_of_week) > 0
              AND sch.status = 'Active'
            ORDER BY sch.start_time ASC
        ");
        $schedByDay[$dayName] = [];
        while ($row = $res->fetch_assoc()) {
            $schedByDay[$dayName][] = $row;
            if (!empty($row['student_db_id'])) {
                $totalStudentsSet[$row['student_db_id']] = true;
            }
            $totalSessions++;
        }
    }
}

$totalStudentsCount = count($totalStudentsSet);

/* =========================
   TODAY'S SESSIONS + ATTENDANCE
========================= */
$todayDayName = date('l'); // Monday, Tuesday...
$todaySchedules = $schedByDay[$todayDayName] ?? [];

// Get already-recorded attendance for today
$attendanceToday = [];
if ($staffDbId) {
    $res = $conn->query("SELECT schedule_id, student_id, status FROM attendance WHERE staff_id=$staffDbId AND session_date='$todayStr'");
    while ($row = $res->fetch_assoc()) {
        $attendanceToday[$row['schedule_id'].'_'.$row['student_id']] = $row['status'];
    }
}

/* =========================
   ALL MY STUDENTS
========================= */
$myStudents = [];
if ($staffDbId) {
    $res = $conn->query("
        SELECT DISTINCT st.id, st.full_name, st.student_id,
               svc.program_name, svc.service_name, g.guardian_name,
               sch.room
        FROM schedules sch
        JOIN students st  ON sch.student_id = st.id
        LEFT JOIN services svc ON sch.service_id = svc.service_id
        LEFT JOIN guardians g  ON st.id = g.student_id
        WHERE sch.staff_id = $staffDbId AND sch.status = 'Active'
        ORDER BY st.full_name
    ");
    while ($row = $res->fetch_assoc()) {
        $myStudents[$row['id']] = $row;
    }
}

/* =========================
   WEEKLY SUMMARY
========================= */
$weeklySummary = [];
foreach ($dayNames as $d) {
    $weeklySummary[$d] = count($schedByDay[$d] ?? []);
}

// Session block colors (cycling)
$blockColors = ['#5C6BC0','#26A69A','#EF5350','#FFA726','#AB47BC','#42A5F5','#66BB6A','#FF7043'];
$colorIdx = 0;

function getInitials($name) {
    $parts = explode(' ', trim($name));
    $i = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) $i .= strtoupper(substr($parts[1], 0, 1));
    return $i;
}

$avatarColors = ['#EF5350','#AB47BC','#5C6BC0','#26A69A','#FFA726','#66BB6A','#42A5F5','#FF7043'];
$avatarIdx = 0;
$studentAvatarColor = [];
foreach ($myStudents as $sid => $st) {
    $studentAvatarColor[$sid] = $avatarColors[$avatarIdx % count($avatarColors)];
    $avatarIdx++;
}

$toast = $_SESSION['toast'] ?? '';
unset($_SESSION['toast']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Schedule – Educator</title>
  <link rel="stylesheet" href="../../assets/css/hro/hro-base.css">
  <link rel="stylesheet" href="../../assets/css/educator/educator-dashboard.css">
  <script src="../../assets/js/sidebar.js" defer></script>
</head>
<body>

<?php include __DIR__ . '/../../includes/educator-sidebar.php'; ?>

<div class="main" style="overflow:hidden;flex-direction:row;height:100vh;">

  <!-- ====== CALENDAR SECTION ====== -->
  <div class="calendar-section">

    <div class="cal-header">
      <h2 class="cal-title">Weekly Schedule</h2>
      <div class="cal-controls">
        <a href="?week=<?= $weekOffset - 1 ?>" class="cal-nav">‹</a>
        <span class="cal-range"><?= $weekLabel ?></span>
        <a href="?week=<?= $weekOffset + 1 ?>" class="cal-nav">›</a>
      </div>
      <div class="cal-stats">
        <div class="cal-stat-box">
          <strong><?= $totalStudentsCount ?></strong>
          <span>Students</span>
        </div>
        <div class="cal-stat-box">
          <strong><?= $totalSessions ?></strong>
          <span>Sessions</span>
        </div>
      </div>
    </div>

    <!-- Day column headers -->
    <div class="cal-grid">
      <div class="cal-time-col"></div>
      <?php foreach ($days as $idx => $dayObj): ?>
        <div class="cal-day-header <?= $dayObj->format('Y-m-d') === $todayStr ? 'today' : '' ?>">
          <span class="day-name"><?= strtoupper($dayNames[$idx]) ?></span>
          <span class="day-num <?= $dayObj->format('Y-m-d') === $todayStr ? 'today-circle' : '' ?>">
            <?= $dayObj->format('j') ?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Time slots -->
    <div class="cal-body">
      <div class="cal-time-slots">
        <?php for ($h = 7; $h <= 18; $h++): ?>
          <div class="time-slot"><?= date('g A', mktime($h, 0, 0)) ?></div>
        <?php endfor; ?>
      </div>

      <?php foreach ($days as $idx => $dayObj): ?>
        <?php $dayName = $dayNames[$idx]; ?>
        <div class="cal-day-col">
          <?php foreach ($schedByDay[$dayName] as $sched):
            $start = strtotime($sched['start_time']);
            $end   = strtotime($sched['end_time']);
            $startMins = (date('G', $start) - 7) * 60 + date('i', $start);
            $durationMins = ($end - $start) / 60;
            $top  = ($startMins / 60) * 56; // 56px per hour
            $height = max(40, ($durationMins / 60) * 56);
            $color = $blockColors[$colorIdx++ % count($blockColors)];
          ?>
            <div class="cal-event" style="top:<?= $top ?>px;height:<?= $height ?>px;background:<?= $color ?>;"
                 title="<?= htmlspecialchars($sched['student_name'] ?? '') ?> | <?= htmlspecialchars($sched['service_name'] ?? $sched['title'] ?? '') ?>">
              <div class="cal-event-title"><?= htmlspecialchars($sched['program_name'] ?? $sched['title'] ?? '') ?></div>
              <div class="cal-event-sub"><?= htmlspecialchars($sched['service_name'] ?? '') ?></div>
              <?php if ($sched['room']): ?>
                <div class="cal-event-room">Room <?= str_replace(['SML ','SLL ','OT ','SLP ','ABA ','TIL '], '', $sched['room']) ?></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Weekly Summary -->
    <div class="weekly-summary">
      <strong>WEEKLY SUMMARY</strong>
      <?php foreach ($weeklySummary as $d => $count): ?>
        <div class="summary-row">
          <span><?= $d ?></span>
          <span><?= $count ?> Session<?= $count !== 1 ? 's' : '' ?></span>
        </div>
      <?php endforeach; ?>
    </div>

  </div>

  <!-- ====== RIGHT PANEL ====== -->
  <div class="right-panel">

    <div class="panel-header">
      <h3>Today's Sessions</h3>
      <button class="btn-primary" style="font-size:12px;padding:7px 12px;" onclick="openStudentsModal()">My Students</button>
    </div>

    <?php if (count($todaySchedules) > 0): ?>
      <?php foreach ($todaySchedules as $sched):
        $key = $sched['id'] . '_' . $sched['student_db_id'];
        $recorded = $attendanceToday[$key] ?? null;
        $sid = $sched['student_db_id'];
        $initials = getInitials($sched['student_name'] ?? 'S');
        $avatarColor = $studentAvatarColor[$sid] ?? '#c0392b';
      ?>
        <div class="session-card">
          <div class="session-avatar" style="background:<?= $avatarColor ?>;">
            <?= htmlspecialchars($initials) ?>
          </div>
          <div class="session-info">
            <strong><?= htmlspecialchars($sched['student_name'] ?? '-') ?></strong>
            <span><?= htmlspecialchars($sched['program_name'] ?? '') ?><?= $sched['service_name'] ? ' – ' . $sched['service_name'] : '' ?></span>
            <span style="font-size:11px;color:#9ca3af;">Guardian: <?= htmlspecialchars($sched['guardian_name'] ?? '-') ?></span>
          </div>
          <div class="session-right">
            <?php if ($sched['room']): ?>
              <span class="room-tag"><?= htmlspecialchars($sched['room']) ?></span>
            <?php endif; ?>
            <span style="font-size:11px;color:#9ca3af;">
              <?= date('g:i A', strtotime($sched['start_time'])) ?> – <?= date('g:i A', strtotime($sched['end_time'])) ?>
            </span>
            <?php if ($recorded): ?>
              <span class="badge badge-<?= $recorded === 'Present' ? 'Active' : 'inactive' ?>"><?= $recorded ?></span>
            <?php else: ?>
              <div class="attend-btns">
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="record_attendance" value="1">
                  <input type="hidden" name="schedule_id" value="<?= $sched['id'] ?>">
                  <input type="hidden" name="student_id"  value="<?= $sched['student_db_id'] ?>">
                  <input type="hidden" name="attend_status" value="Present">
                  <button type="submit" class="attend-btn present" onclick="return confirmAttendance(this.form)">Present</button>
                </form>
                <form method="POST" style="display:inline;">
                  <input type="hidden" name="record_attendance" value="1">
                  <input type="hidden" name="schedule_id" value="<?= $sched['id'] ?>">
                  <input type="hidden" name="student_id"  value="<?= $sched['student_db_id'] ?>">
                  <input type="hidden" name="attend_status" value="Absent">
                  <button type="submit" class="attend-btn absent">Absent</button>
                </form>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div style="text-align:center;padding:40px 20px;color:#9ca3af;">
        <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#d1d5db" stroke-width="1.5" style="margin:0 auto 12px;display:block;">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p style="margin:0;font-size:14px;font-weight:500;">No scheduled class yet</p>
      </div>
    <?php endif; ?>

  </div>
</div>

<!-- ======================== MY STUDENTS MODAL ======================== -->
<div class="modal-overlay" id="studentsModal">
  <div class="modal" style="width:560px;max-height:80vh;overflow-y:auto;">
    <div class="modal-header">
      <h3>My Students</h3>
      <button class="modal-close" onclick="closeModal('studentsModal')">×</button>
    </div>
    <div class="modal-body" style="padding:16px 20px;">
      <?php foreach ($myStudents as $sid => $st):
        $initials = getInitials($st['full_name']);
        $avColor  = $studentAvatarColor[$sid] ?? '#c0392b';
        // Get latest attendance for this student
        $latestAtt = $conn->query("SELECT status FROM attendance WHERE student_id=$sid AND staff_id=$staffDbId ORDER BY session_date DESC LIMIT 1")->fetch_assoc();
        $attStatus = $latestAtt['status'] ?? null;
      ?>
        <div class="my-student-row">
          <div class="student-avatar-lg" style="background:<?= $avColor ?>;">
            <?= htmlspecialchars($initials) ?>
          </div>
          <div class="student-row-info">
            <strong><?= htmlspecialchars($st['full_name']) ?></strong>
            <span><?= htmlspecialchars($st['program_name'] ?? '') ?><?= $st['service_name'] ? ' – ' . $st['service_name'] : '' ?></span>
            <span style="font-size:11px;color:#6b7280;">Guardian: <?= htmlspecialchars($st['guardian_name'] ?? '-') ?></span>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0;">
            <?php if ($st['room']): ?>
              <span class="room-tag"><?= htmlspecialchars($st['room']) ?></span>
            <?php endif; ?>
            <?php if ($attStatus): ?>
              <span class="badge badge-<?= $attStatus === 'Present' ? 'Active' : 'inactive' ?>" style="font-size:10px;">
                <?= $attStatus ?>
              </span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($myStudents)): ?>
        <div style="text-align:center;padding:40px 20px;color:#9ca3af;">
          <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#d1d5db" stroke-width="1.5" style="margin:0 auto 12px;display:block;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p style="margin:0;font-size:14px;font-weight:500;">No scheduled class yet</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ATTENDANCE CONFIRM MODAL -->
<div class="modal-overlay" id="attendConfirmModal">
  <div class="confirm-modal">
    <div class="confirm-header">CONFIRMATION <button onclick="closeModal('attendConfirmModal')" style="background:none;border:none;color:white;font-size:18px;cursor:pointer;">×</button></div>
    <div class="confirm-body"><p>Are you sure you want to record the attendance of this student?</p></div>
    <div class="confirm-footer">
      <button class="btn-cancel-sm" onclick="closeModal('attendConfirmModal')">Cancel</button>
      <button class="btn-confirm-sm" id="attendConfirmBtn">Confirm</button>
    </div>
  </div>
</div>

<?php if ($toast): ?>
<div class="toast" id="toast"><?= htmlspecialchars($toast) ?></div>
<script>setTimeout(()=>{let t=document.getElementById('toast');if(t){t.style.opacity='0';setTimeout(()=>t.remove(),500);}},3000);</script>
<?php endif; ?>

<script>
let pendingAttendForm = null;

function confirmAttendance(form) {
  pendingAttendForm = form;
  document.getElementById('attendConfirmModal').classList.add('open');
  return false;
}

document.getElementById('attendConfirmBtn').addEventListener('click', function() {
  if (pendingAttendForm) {
    pendingAttendForm.submit();
  }
});

function openStudentsModal() {
  document.getElementById('studentsModal').classList.add('open');
}

function closeModal(id) {
  document.getElementById(id).classList.remove('open');
}

document.querySelectorAll('.modal-overlay').forEach(el => {
  el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
});
</script>

</body>
</html>
