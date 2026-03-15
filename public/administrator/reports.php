<?php
require_once(__DIR__ . "/../../includes/db.php");
require_once __DIR__ . '/../../includes/auth.php';

/* =====================
   COLUMN GUARD
===================== */
$_colGuards = [
    'module'     => 'VARCHAR(100) NULL',
    'target'     => 'VARCHAR(255) NULL',
    'ip_address' => 'VARCHAR(45)  NULL',
    'details'    => 'TEXT         NULL',
];
foreach ($_colGuards as $_col => $_def) {
    $_chk = $conn->query("SHOW COLUMNS FROM activity_logs LIKE '$_col'");
    if ($_chk && $_chk->num_rows === 0) {
        $conn->query("ALTER TABLE activity_logs ADD COLUMN $_col $_def");
    }
}
unset($_colGuards, $_col, $_def, $_chk);

/* =====================
   AJAX HANDLER
===================== */
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    $type = $_GET['ajax'];

    // ── Activity Logs AJAX ──────────────────────────────────────────────────
    if ($type === 'logs') {
        $search       = trim($_GET['search']        ?? '');
        $filterAction = trim($_GET['action_filter'] ?? '');
        $filterModule = trim($_GET['module_filter'] ?? '');

        $query = "
            SELECT a.*, u.full_name, u.staff_id as user_staff_id
            FROM activity_logs a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE 1=1
        ";

        if (!empty($search)) {
            $s = $conn->real_escape_string($search);
            $query .= " AND (
                a.module    LIKE '%$s%' OR
                a.action    LIKE '%$s%' OR
                a.target    LIKE '%$s%' OR
                a.staff_id  LIKE '%$s%' OR
                u.full_name LIKE '%$s%' OR
                u.staff_id  LIKE '%$s%'
            )";
        }
        if (!empty($filterModule)) {
            $fm = $conn->real_escape_string($filterModule);
            $query .= " AND a.module = '$fm'";
        }
        if (!empty($filterAction)) {
            $fa = $conn->real_escape_string($filterAction);
            $query .= " AND a.action LIKE '%$fa%'";
        }

        $query .= " ORDER BY a.created_at DESC";
        $result = $conn->query($query);
        $rows   = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        echo json_encode(['total' => count($rows), 'rows' => $rows]);
        exit;
    }

    // ── Students AJAX ───────────────────────────────────────────────────────
    if ($type === 'students') {
        $ssf   = trim($_GET['student_status'] ?? '');
        $where = '';
        if (!empty($ssf)) {
            $ssf   = $conn->real_escape_string($ssf);
            $where = "WHERE s.status = '$ssf'";
        }
        $result = $conn->query("
            SELECT s.student_id, s.full_name, s.status, s.created_at, g.guardian_name
            FROM students s
            LEFT JOIN guardians g ON s.id = g.student_id
            $where
            ORDER BY s.created_at DESC
            LIMIT 50
        ");
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        echo json_encode(['rows' => $rows]);
        exit;
    }

    // ── Dropdown options AJAX ───────────────────────────────────────────────
    if ($type === 'options') {
        $modules = [];
        $mr = $conn->query("SELECT DISTINCT module FROM activity_logs WHERE module IS NOT NULL AND module != '' ORDER BY module ASC");
        while ($m = $mr->fetch_assoc()) $modules[] = $m['module'];

        $actions = [];
        $ar = $conn->query("SELECT DISTINCT action FROM activity_logs WHERE action IS NOT NULL AND action != '' ORDER BY action ASC");
        while ($a = $ar->fetch_assoc()) $actions[] = $a['action'];

        echo json_encode(['modules' => $modules, 'actions' => $actions]);
        exit;
    }

    echo json_encode(['error' => 'Unknown ajax type']);
    exit;
}

/* =====================
   INITIAL PAGE LOAD DATA
===================== */
$search              = trim($_GET['search']        ?? '');
$filterAction        = trim($_GET['action_filter'] ?? '');
$filterModule        = trim($_GET['module_filter'] ?? '');
$studentStatusFilter = trim($_GET['student_status'] ?? '');

// Modules & Actions for initial dropdowns
$modulesResult = $conn->query("SELECT DISTINCT module FROM activity_logs WHERE module IS NOT NULL AND module != '' ORDER BY module ASC");
$moduleList    = [];
while ($m = $modulesResult->fetch_assoc()) { $moduleList[] = $m['module']; }

$actionsResult = $conn->query("SELECT DISTINCT action FROM activity_logs WHERE action IS NOT NULL AND action != '' ORDER BY action ASC");
$actionList    = [];
while ($a = $actionsResult->fetch_assoc()) { $actionList[] = $a['action']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reports – Administrator</title>
  <link rel="stylesheet" href="../../assets/css/hro/hro-base.css">
  <link rel="stylesheet" href="../../assets/css/administratorcss/dashboard.css">
  <script src="../../assets/js/sidebar.js" defer></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
  <style>
    .activity-scroll-wrap {
      max-height: 490px;
      overflow-y: auto; overflow-x: hidden;
      display: block; width: 100%;
      border: 1px solid #e5e7eb; border-radius: 8px;
    }
    .students-scroll-wrap {
      max-height: 304px;
      overflow-y: auto; overflow-x: hidden;
      display: block; width: 100%;
      border: 1px solid #e5e7eb; border-radius: 8px;
    }
    .activity-scroll-wrap table thead th,
    .students-scroll-wrap table thead th {
      position: sticky; top: 0; background: #fff; z-index: 2;
      box-shadow: 0 1px 0 #e5e7eb;
    }
    .activity-scroll-wrap::-webkit-scrollbar,
    .students-scroll-wrap::-webkit-scrollbar { width: 6px; height: 6px; }
    .activity-scroll-wrap::-webkit-scrollbar-track,
    .students-scroll-wrap::-webkit-scrollbar-track { background: #f3f4f6; border-radius: 3px; }
    .activity-scroll-wrap::-webkit-scrollbar-thumb,
    .students-scroll-wrap::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }
    .activity-scroll-wrap::-webkit-scrollbar-thumb:hover,
    .students-scroll-wrap::-webkit-scrollbar-thumb:hover { background: #9ca3af; }

    .status-badge.action-red     { background:#fee2e2; color:#991b1b; }
    .status-badge.action-blue    { background:#dbeafe; color:#1e40af; }
    .status-badge.action-gray    { background:#f3f4f6; color:#374151; }
    .status-badge.action-green   { background:#dcfce7; color:#166534; }
    .status-badge.action-purple  { background:#ede9fe; color:#5b21b6; }
    .status-badge.action-teal    { background:#ccfbf1; color:#0f766e; }
    .status-badge.action-indigo  { background:#e0e7ff; color:#3730a3; }
    .status-badge.action-orange  { background:#ffedd5; color:#9a3412; }
    .status-badge.action-cyan    { background:#cffafe; color:#155e75; }
    .status-badge.action-amber   { background:#fef3c7; color:#92400e; }
    .status-badge.action-default { background:#f1f5f9; color:#475569; }

    .module-badge {
      display:inline-block; padding:2px 8px; border-radius:6px;
      font-size:11px; font-weight:600; background:#f1f5f9; color:#475569; white-space:nowrap;
    }
    .target-cell {
      font-size:13px; color:#374151; max-width:180px;
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .role-badge { display:inline-block; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:600; }
    .role-badge.administrator { background:#fce7f3; color:#9d174d; }
    .role-badge.hr            { background:#e0e7ff; color:#3730a3; }
    .role-badge.educator      { background:#dcfce7; color:#166534; }
    .role-badge.therapist     { background:#ccfbf1; color:#0f766e; }
    .role-badge.default       { background:#f3f4f6; color:#374151; }

    .activity-table { width:100%; min-width:unset; table-layout:fixed; }
    .activity-table th { font-size:12px; padding:8px 10px; text-align:center; }
    .activity-table td { font-size:12px; padding:8px 10px; word-wrap:break-word; overflow-wrap:break-word; white-space:normal; text-align:left; }
    .activity-table th:nth-child(1), .activity-table td:nth-child(1) { width:15%; } /* Module */
    .activity-table th:nth-child(2), .activity-table td:nth-child(2) { width:17%; } /* Action */
    .activity-table th:nth-child(3), .activity-table td:nth-child(3) { width:18%; } /* Target */
    .activity-table th:nth-child(4), .activity-table td:nth-child(4) { width:9%;  } /* Staff ID */
    .activity-table th:nth-child(5), .activity-table td:nth-child(5) { width:19%; } /* Name */
    .activity-table th:nth-child(6), .activity-table td:nth-child(6) { width:12%; } /* Role */
    .activity-table th:nth-child(7), .activity-table td:nth-child(7) { width:10%; } /* Timestamp */

    /* Spinner */
    .ajax-loading { opacity: 0.5; pointer-events: none; transition: opacity 0.2s; }


  </style>
</head>
<body class="page-reports">

<?php include __DIR__ . '/../../includes/administrator-sidebar.php'; ?>

<div class="main">

  <div class="header" style="padding: 24px 36px 16px;">
    <h1>Report Management</h1>
    <p>System activities and recent student enrollment records</p>
  </div>

  <div style="padding:0 36px 24px;display:flex;flex-direction:column;gap:20px;">

    <!-- FILTER CARD -->
    <div class="dashboard-card" style="padding:18px 24px;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
        <span style="font-weight:600;color:#374151;font-size:15px;">Filter Activity Logs</span>
        <a href="#" id="clearFilters" onclick="clearAllFilters(event)"
           style="font-size:13px;color:#ef4444;text-decoration:none;font-weight:500;display:none;">&#10005; Clear Filters</a>
      </div>
      <div style="display:flex;flex-direction:column;gap:12px;">

        <!-- Search bar -->
        <div style="position:relative;">
          <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:15px;">&#128269;</span>
          <input type="text" id="searchInput"
                 placeholder="Search by module, action, target, staff ID, or name..."
                 value="<?= htmlspecialchars($search) ?>"
                 style="width:100%;padding:9px 12px 9px 36px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box;">
        </div>

        <!-- Module + Action + Search button -->
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
          <div style="flex:1;min-width:180px;">
            <label style="font-size:12px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px;">MODULE</label>
            <select id="moduleFilter" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;">
              <option value="">All Modules</option>
              <?php foreach($moduleList as $mod): ?>
                <option value="<?= htmlspecialchars($mod) ?>" <?= $filterModule === $mod ? 'selected':'' ?>>
                  <?= htmlspecialchars($mod) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div style="flex:1;min-width:180px;">
            <label style="font-size:12px;font-weight:600;color:#6b7280;display:block;margin-bottom:4px;">ACTION</label>
            <select id="actionFilter" style="width:100%;padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;">
              <option value="">All Actions</option>
              <?php foreach($actionList as $act): ?>
                <option value="<?= htmlspecialchars($act) ?>" <?= $filterAction === $act ? 'selected':'' ?>>
                  <?= htmlspecialchars($act) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

        </div>

        <!-- Active filter pills -->
        <div id="filterPills" style="display:flex;gap:8px;flex-wrap:wrap;"></div>

      </div>
    </div>

    <!-- SYSTEM ACTIVITY LOGS -->
    <div class="dashboard-card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
        <h2 class="card-title" style="margin:0;">System Activity Logs (<span id="logsCount">0</span>)</h2>
        <button onclick="openExportModal()"
                style="display:flex;align-items:center;gap:6px;padding:8px 16px;background:#dc2626;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
          &#8595; Export PDF
        </button>
      </div>
      <div class="activity-scroll-wrap" id="logsScrollWrap">
        <table class="activity-table" id="activityLogsTable">
          <thead>
            <tr>
              <th>Module</th><th>Action</th><th>Activity (Target)</th>
              <th>Staff ID</th><th>Name</th><th>Role</th><th>Timestamp</th>
            </tr>
          </thead>
          <tbody id="logsTbody">
            <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:30px;">Loading...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- RECENT STUDENT ENROLLMENTS -->
    <div class="dashboard-card">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 class="card-title" style="margin:0;">Recent Student Enrollments</h2>
        <div style="position:relative;display:inline-block;">
          <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#374151;font-size:11px;pointer-events:none;">&#9660;</span>
          <select id="studentStatusFilter"
                  style="padding:7px 12px 7px 28px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;appearance:none;-webkit-appearance:none;background:#fff;cursor:pointer;">
            <option value="" <?= $studentStatusFilter==='' ? 'selected':'' ?>>All Status</option>
            <option value="Pending"  <?= $studentStatusFilter==='Pending'  ? 'selected':'' ?>>Pending</option>
            <option value="Active"   <?= $studentStatusFilter==='Active'   ? 'selected':'' ?>>Active</option>
            <option value="Inactive" <?= $studentStatusFilter==='Inactive' ? 'selected':'' ?>>Inactive</option>
          </select>
        </div>
      </div>
      <div class="students-scroll-wrap">
        <table class="activity-table">
          <thead>
            <tr>
              <th>Student ID</th><th>Name</th><th>Guardian</th><th>Status</th><th>Enrolled Date</th>
            </tr>
          </thead>
          <tbody id="studentsTbody">
            <tr><td colspan="5" style="text-align:center;color:#9ca3af;padding:30px;">Loading...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- EXPORT PDF MODAL -->
<div id="exportModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:12px;width:420px;max-width:95vw;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
    <div style="background:#1e40af;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;">
      <h3 style="color:#fff;margin:0;font-size:16px;">Export Activity Logs — PDF</h3>
      <span onclick="closeExportModal()" style="color:#fff;cursor:pointer;font-size:20px;line-height:1;">&times;</span>
    </div>
    <div style="padding:24px 20px;">
      <div style="display:flex;gap:8px;margin-bottom:20px;">
        <?php foreach(['Year','Month','Week','Day'] as $rt): ?>
        <button onclick="setRangeType('<?= strtolower($rt) ?>')"
                id="tab_<?= strtolower($rt) ?>"
                style="flex:1;padding:8px 4px;border-radius:8px;border:1px solid #d1d5db;font-size:13px;font-weight:600;cursor:pointer;background:#f9fafb;color:#374151;">
          <?= $rt ?>
        </button>
        <?php endforeach; ?>
      </div>
      <div id="range_year">
        <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Select Year</label>
        <select id="sel_year" style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;">
          <?php for($y=date('Y');$y>=2020;$y--): ?>
          <option value="<?= $y ?>" <?= $y==date('Y')?'selected':'' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div id="range_month" style="display:none;">
        <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Select Month</label>
        <div style="display:flex;gap:8px;">
          <select id="sel_month_y" style="flex:1;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;">
            <?php for($y=date('Y');$y>=2020;$y--): ?>
            <option value="<?= $y ?>" <?= $y==date('Y')?'selected':'' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
          <select id="sel_month_m" style="flex:1;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;">
            <?php for($m=1;$m<=12;$m++): ?>
            <option value="<?= str_pad($m,2,'0',STR_PAD_LEFT) ?>" <?= $m==date('n')?'selected':'' ?>>
              <?= date('F', mktime(0,0,0,$m,1)) ?>
            </option>
            <?php endfor; ?>
          </select>
        </div>
      </div>
      <div id="range_week" style="display:none;">
        <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Select Week Start Date</label>
        <input type="date" id="sel_week"
               style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box;"
               value="<?= date('Y-m-d', strtotime('monday this week')) ?>">
      </div>
      <div id="range_day" style="display:none;">
        <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Select Date</label>
        <input type="date" id="sel_day"
               style="width:100%;padding:9px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;box-sizing:border-box;"
               value="<?= date('Y-m-d') ?>">
      </div>
    </div>
    <div style="padding:0 20px 20px;display:flex;gap:10px;justify-content:flex-end;">
      <button onclick="closeExportModal()"
              style="padding:9px 20px;border:1px solid #d1d5db;border-radius:8px;background:#fff;color:#374151;font-size:13px;font-weight:600;cursor:pointer;">
        Cancel
      </button>
      <button onclick="generatePDF()"
              style="padding:9px 20px;background:#1e40af;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
        &#8595; Export PDF
      </button>
    </div>
  </div>
</div>

<script>
/* =============================================================
   STATE
============================================================= */
var _searchVal   = <?= json_encode($search) ?>;
var _moduleVal   = <?= json_encode($filterModule) ?>;
var _actionVal   = <?= json_encode($filterAction) ?>;
var _studentStat = <?= json_encode($studentStatusFilter) ?>;
var _searchTimer = null;
var _allLogRows  = []; // used by PDF export

/* =============================================================
   BADGE HELPERS
============================================================= */
function actionBadgeClass(action) {
  var a = action.toLowerCase();
  if (/disapprov|reject|delet|remov/.test(a)) return 'action-red';
  if (/login/.test(a))                         return 'action-blue';
  if (/logout/.test(a))                        return 'action-gray';
  if (/approv|accept|restor/.test(a))          return 'action-green';
  if (/notif/.test(a))                         return 'action-purple';
  if (/payment|paid|finance/.test(a))          return 'action-teal';
  if (/enroll|student/.test(a))                return 'action-indigo';
  if (/updat|edit|modif/.test(a))              return 'action-orange';
  if (/creat|add|new|register/.test(a))        return 'action-cyan';
  if (/archiv/.test(a))                        return 'action-amber';
  return 'action-default';
}
function roleBadgeClass(role) {
  var r = (role||'').toLowerCase();
  if (/admin/.test(r))   return 'administrator';
  if (/hr/.test(r))      return 'hr';
  if (/educat/.test(r))  return 'educator';
  if (/therap/.test(r))  return 'therapist';
  return 'default';
}
function esc(str) {
  return (str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* =============================================================
   ACTIVITY LOGS — AJAX LOAD
============================================================= */
function loadLogs() {
  var wrap = document.getElementById('logsScrollWrap');
  wrap.classList.add('ajax-loading');

  var params = new URLSearchParams({
    ajax:          'logs',
    search:        _searchVal,
    module_filter: _moduleVal,
    action_filter: _actionVal
  });

  fetch('reports.php?' + params.toString())
    .then(function(r){ return r.json(); })
    .then(function(data) {
      _allLogRows = data.rows || [];
      document.getElementById('logsCount').textContent = data.total;
      renderLogs(_allLogRows);
      wrap.classList.remove('ajax-loading');
      updateFilterPills();
      updateClearLink();
    })
    .catch(function() {
      wrap.classList.remove('ajax-loading');
    });
}

function renderLogs(rows) {
  var tbody = document.getElementById('logsTbody');
  if (!rows || rows.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:30px;">No activity logs found.</td></tr>';
    return;
  }
  var html = '';
  rows.forEach(function(row) {
    var staffId = row.user_staff_id || row.staff_id || '—';
    var target  = row.target || row.details || '—';
    var ac      = actionBadgeClass(row.action || '');
    var rc      = roleBadgeClass(row.role || '');
    html += '<tr>'
      + '<td><span class="module-badge">'  + esc(row.module || '—') + '</span></td>'
      + '<td style="text-align:center;"><span class="status-badge '  + ac + '">' + esc(row.action || '—') + '</span></td>'
      + '<td class="target-cell" style="text-align:center;max-width:none;" title="' + esc(target) + '">' + esc(target) + '</td>'
      + '<td style="font-size:13px;color:#6b7280;text-align:center;">'  + esc(staffId) + '</td>'
      + '<td style="font-size:13px;color:#111827;text-align:center;">'  + esc(row.full_name || 'System') + '</td>'
      + '<td style="text-align:center;"><span class="role-badge ' + rc + '">' + esc(row.role || '—') + '</span></td>'
      + '<td style="font-size:12px;color:#6b7280;">'  + esc(row.created_at ? row.created_at.replace('T',' ').substring(0,19) : '—') + '</td>'
      + '</tr>';
  });
  tbody.innerHTML = html;
}

/* =============================================================
   STUDENTS — AJAX LOAD
============================================================= */
function loadStudents() {
  var params = new URLSearchParams({ ajax: 'students', student_status: _studentStat });
  fetch('reports.php?' + params.toString())
    .then(function(r){ return r.json(); })
    .then(function(data) {
      renderStudents(data.rows || []);
    });
}

function renderStudents(rows) {
  var tbody = document.getElementById('studentsTbody');
  if (!rows || rows.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#9ca3af;padding:30px;">No students found.</td></tr>';
    return;
  }
  var html = '';
  rows.forEach(function(row) {
    var st = (row.status||'').toLowerCase();
    html += '<tr>'
      + '<td style="text-align:center;">' + esc(row.student_id) + '</td>'
      + '<td style="text-align:left;">'   + esc(row.full_name)  + '</td>'
      + '<td style="text-align:left;">'   + esc(row.guardian_name || '—') + '</td>'
      + '<td style="text-align:center;"><span class="status-badge ' + st + '">' + esc(row.status) + '</span></td>'
      + '<td style="text-align:center;">' + esc(row.created_at ? row.created_at.substring(0,19).replace('T',' ') : '—') + '</td>'
      + '</tr>';
  });
  tbody.innerHTML = html;
}

/* =============================================================
   FILTER CONTROLS
============================================================= */
function applyFilters() {
  _searchVal = document.getElementById('searchInput').value.trim();
  _moduleVal = document.getElementById('moduleFilter').value;
  _actionVal = document.getElementById('actionFilter').value;
  loadLogs();
}

function clearAllFilters(e) {
  e.preventDefault();
  _searchVal = _moduleVal = _actionVal = '';
  document.getElementById('searchInput').value   = '';
  document.getElementById('moduleFilter').value  = '';
  document.getElementById('actionFilter').value  = '';
  loadLogs();
}

function updateClearLink() {
  var show = _searchVal || _moduleVal || _actionVal;
  document.getElementById('clearFilters').style.display = show ? 'inline' : 'none';
}

function updateFilterPills() {
  var pills = document.getElementById('filterPills');
  var html  = '';
  if (_moduleVal) html += '<span style="background:#e0e7ff;color:#3730a3;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">Module: ' + esc(_moduleVal) + '</span>';
  if (_actionVal) html += '<span style="background:#dcfce7;color:#166534;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">Action: ' + esc(_actionVal) + '</span>';
  if (_searchVal) html += '<span style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">Search: &quot;' + esc(_searchVal) + '&quot;</span>';
  pills.innerHTML = html;
  pills.style.display = html ? 'flex' : 'none';
}

/* Search on Enter key */
document.addEventListener('DOMContentLoaded', function() {
  document.getElementById('searchInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') applyFilters();
  });

  /* Module / Action dropdowns fire AJAX on change */
  document.getElementById('moduleFilter').addEventListener('change', applyFilters);
  document.getElementById('actionFilter').addEventListener('change', applyFilters);

  /* Student status filter */
  document.getElementById('studentStatusFilter').addEventListener('change', function() {
    _studentStat = this.value;
    loadStudents();
  });

  /* Initial load */
  loadLogs();
  loadStudents();

  /* Auto-refresh logs every 30 seconds */
  setInterval(function() { loadLogs(); loadStudents(); }, 30000);
});

/* =============================================================
   EXPORT MODAL
============================================================= */
var _rangeType = 'year';

function openExportModal() {
  document.getElementById('exportModal').style.display = 'flex';
  setRangeType('year');
}
function closeExportModal() {
  document.getElementById('exportModal').style.display = 'none';
}
function setRangeType(type) {
  _rangeType = type;
  ['year','month','week','day'].forEach(function(t) {
    document.getElementById('range_' + t).style.display = t === type ? 'block' : 'none';
    var tab = document.getElementById('tab_' + t);
    tab.style.background  = t === type ? '#1e40af' : '#f9fafb';
    tab.style.color       = t === type ? '#fff'    : '#374151';
    tab.style.borderColor = t === type ? '#1e40af' : '#d1d5db';
  });
}

/* =============================================================
   PDF GENERATOR — uses _allLogRows (already fetched, no extra request)
============================================================= */
function generatePDF() {
  var rows = _allLogRows;

  // Filter by time range
  var filtered = rows.filter(function(r) {
    var d = new Date(r.created_at);
    if (isNaN(d)) return true;
    if (_rangeType === 'year') {
      return d.getFullYear() === parseInt(document.getElementById('sel_year').value);
    } else if (_rangeType === 'month') {
      return d.getFullYear() === parseInt(document.getElementById('sel_month_y').value)
          && (d.getMonth()+1)  === parseInt(document.getElementById('sel_month_m').value);
    } else if (_rangeType === 'week') {
      var ws = new Date(document.getElementById('sel_week').value);
      var we = new Date(ws); we.setDate(we.getDate()+6); we.setHours(23,59,59);
      return d >= ws && d <= we;
    } else {
      var day = document.getElementById('sel_day').value;
      return r.created_at && r.created_at.startsWith(day);
    }
  });

  if (filtered.length === 0) { alert('No logs found for the selected time range.'); return; }

  var rangeLabel = '';
  if (_rangeType === 'year') {
    rangeLabel = document.getElementById('sel_year').value;
  } else if (_rangeType === 'month') {
    rangeLabel = document.getElementById('sel_month_y').value + '-' + document.getElementById('sel_month_m').value;
  } else if (_rangeType === 'week') {
    var ws = document.getElementById('sel_week').value;
    var we = new Date(ws); we.setDate(we.getDate()+6);
    rangeLabel = ws + ' to ' + we.toISOString().slice(0,10);
  } else {
    rangeLabel = document.getElementById('sel_day').value;
  }

  var { jsPDF } = window.jspdf;
  var doc  = new jsPDF({ orientation:'landscape', unit:'mm', format:'a4' });
  var teal = [30,64,175];
  doc.setFontSize(14); doc.setTextColor(30,64,175);
  doc.text("Kid's Journey Learning Center — System Activity Logs", 14, 16);
  doc.setFontSize(10); doc.setTextColor(100);
  doc.text('Period: ' + rangeLabel + '   |   Generated: ' + new Date().toLocaleString(), 14, 23);
  doc.text('Total records: ' + filtered.length, 14, 29);

  doc.autoTable({
    head: [['Module','Action','Activity (Target)','Staff ID','Name','Role','Timestamp']],
    body: filtered.map(function(r) {
      var staffId = r.user_staff_id || r.staff_id || '—';
      var target  = r.target || r.details || '—';
      return [r.module||'—', r.action||'—', target, staffId, r.full_name||'System', r.role||'—', r.created_at||'—'];
    }),
    startY: 33,
    theme: 'grid',
    headStyles: { fillColor:teal, textColor:255, fontStyle:'bold', fontSize:9, halign:'center' },
    bodyStyles: { fontSize:8, cellPadding:3, halign:'center' },
    alternateRowStyles: { fillColor:[239,246,255] },
    columnStyles: { 0:{cellWidth:40},1:{cellWidth:44},2:{cellWidth:50},3:{cellWidth:24},4:{cellWidth:44},5:{cellWidth:32},6:{cellWidth:43} }
  });

  doc.save('activity-logs-' + rangeLabel + '.pdf');
  closeExportModal();
}
</script>

</body>
</html>