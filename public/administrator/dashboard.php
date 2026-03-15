<?php
require_once(__DIR__ . "/../../includes/db.php");
require_once __DIR__ . '/../../includes/auth.php';
 
/* =========================
   GET FILTERS
========================== */
if (isset($_GET['enroll_start_month']) && isset($_GET['enroll_start_year'])) {
    $enrollStartMonth = $_GET['enroll_start_year'] . '-' . $_GET['enroll_start_month'];
} else {
    $enrollStartMonth = $_GET['enroll_start'] ?? date('Y-m', strtotime('-5 months'));
}
 
if (isset($_GET['enroll_end_month']) && isset($_GET['enroll_end_year'])) {
    $enrollEndMonth = $_GET['enroll_end_year'] . '-' . $_GET['enroll_end_month'];
} else {
    $enrollEndMonth = $_GET['enroll_end'] ?? date('Y-m');
}
 
if (isset($_GET['sales_start_month']) && isset($_GET['sales_start_year'])) {
    $salesStartMonth = $_GET['sales_start_year'] . '-' . $_GET['sales_start_month'];
} else {
    $salesStartMonth = $_GET['sales_start'] ?? date('Y-m', strtotime('-5 months'));
}
 
if (isset($_GET['sales_end_month']) && isset($_GET['sales_end_year'])) {
    $salesEndMonth = $_GET['sales_end_year'] . '-' . $_GET['sales_end_month'];
} else {
    $salesEndMonth = $_GET['sales_end'] ?? date('Y-m');
}
 
if (isset($_GET['services_month_num']) && isset($_GET['services_year'])) {
    $servicesMonth = $_GET['services_year'] . '-' . $_GET['services_month_num'];
} else {
    $servicesMonth = $_GET['services_month'] ?? date('Y-m');
}
 
if (isset($_GET['status_month_num']) && isset($_GET['status_year'])) {
    $statusMonth = $_GET['status_year'] . '-' . $_GET['status_month_num'];
} else {
    $statusMonth = $_GET['status_month'] ?? date('Y-m');
}
 
/* =========================
   DASHBOARD STATS
========================== */
$totalUsers = $conn->query("SELECT COUNT(*) as total FROM users WHERE status='active'")->fetch_assoc()['total'];
$pendingRequests = $conn->query("SELECT COUNT(*) as total FROM users WHERE status='pending'")->fetch_assoc()['total'];
 
/* =========================
   SUMMARY STATS
   Total income = fully paid payments + amount_paid so far on installments
========================== */
$totalIncome = $conn->query("
    SELECT IFNULL(SUM(
        CASE
            WHEN payment_status = 'paid'        THEN payment_amount
            WHEN payment_status = 'installment' THEN amount_paid
            ELSE 0
        END
    ), 0) as total
    FROM payments
")->fetch_assoc()['total'];
 
$currentMonth  = date('Y-m');
$previousMonth = date('Y-m', strtotime('-1 month'));

$currentMonthIncome = $conn->query("
    SELECT IFNULL(SUM(
        CASE
            WHEN payment_status = 'paid'        THEN payment_amount
            WHEN payment_status = 'installment' THEN amount_paid
            ELSE 0
        END
    ), 0) as total
    FROM payments
    WHERE DATE_FORMAT(payment_date,'%Y-%m') = '$currentMonth'
")->fetch_assoc()['total'];

$previousMonthIncome = $conn->query("
    SELECT IFNULL(SUM(
        CASE
            WHEN payment_status = 'paid'        THEN payment_amount
            WHEN payment_status = 'installment' THEN amount_paid
            ELSE 0
        END
    ), 0) as total
    FROM payments
    WHERE DATE_FORMAT(payment_date,'%Y-%m') = '$previousMonth'
")->fetch_assoc()['total'];
 
$growthPercent = 0;
if ($previousMonthIncome > 0) {
    $growthPercent = round((($currentMonthIncome - $previousMonthIncome) / $previousMonthIncome) * 100, 2);
}
 
$activeServices   = $conn->query("SELECT COUNT(*) as c FROM services WHERE status='active'")->fetch_assoc()['c'];
$totalStudents    = $conn->query("SELECT COUNT(*) as c FROM students")->fetch_assoc()['c'];
$activeStudents   = $conn->query("SELECT COUNT(*) as c FROM students WHERE status='Active'")->fetch_assoc()['c'];
$inactiveStudents = $conn->query("SELECT COUNT(*) as c FROM students WHERE status='Inactive'")->fetch_assoc()['c'];
$totalStaff   = $conn->query("SELECT COUNT(*) as c FROM staff")->fetch_assoc()['c'];
$activeStaff  = $conn->query("SELECT COUNT(*) as c FROM staff WHERE status='Active'")->fetch_assoc()['c'];
$onLeaveStaff = $conn->query("SELECT COUNT(*) as c FROM staff WHERE status='On Leave'")->fetch_assoc()['c'];
 
/* =========================
   PAYMENT STATUS BREAKDOWN
========================== */
$conn->query("UPDATE payments SET archived = 0 WHERE payment_status IN ('pending','installment','overdue') AND archived = 1");
 
$totalPayments = $conn->query("SELECT COUNT(*) as total FROM payments")->fetch_assoc()['total'];
$paidCount     = $conn->query("SELECT COUNT(*) as total FROM payments WHERE payment_status='paid'")->fetch_assoc()['total'];
$pendingCount  = $conn->query("SELECT COUNT(*) as total FROM payments WHERE payment_status='pending'")->fetch_assoc()['total'];
$overdueCount  = $conn->query("SELECT COUNT(*) as total FROM payments WHERE payment_status='overdue'")->fetch_assoc()['total'];
$installCount  = $conn->query("SELECT COUNT(*) as total FROM payments WHERE payment_status='installment'")->fetch_assoc()['total'];
 
/* =========================
   INCOME GRAPH DATA (Last 6 months)
   Each bar = paid payments + installment amount_paid for that month
========================== */
$incomeGraphData   = [];
$incomeGraphLabels = [];
$startIncome = date('Y-m', strtotime('-5 months'));
for ($i = 0; $i <= 5; $i++) {
    $month = date('Y-m', strtotime("$startIncome +$i months"));
    $incomeGraphLabels[] = date('M', strtotime($month));
    $res = $conn->query("
        SELECT IFNULL(SUM(
            CASE
                WHEN payment_status = 'paid'        THEN payment_amount
                WHEN payment_status = 'installment' THEN amount_paid
                ELSE 0
            END
        ), 0) as total
        FROM payments
        WHERE DATE_FORMAT(payment_date,'%Y-%m') = '$month'
    ");
    $incomeGraphData[] = (float)$res->fetch_assoc()['total'];
}
 
/* =========================
   ENROLLMENT DATA
========================== */
$monthlyEnrollment = [];
$startEnroll = $enrollStartMonth;
for ($i = 0; $i <= 12; $i++) {
    $month = date('Y-m', strtotime("$startEnroll +$i months"));
    if ($month > $enrollEndMonth) break;
    $res = $conn->query("SELECT COUNT(*) as c FROM enrollments WHERE DATE_FORMAT(created_at,'%Y-%m')='$month'");
    $monthlyEnrollment[] = ['label' => date('M Y', strtotime($month)), 'count' => (int)$res->fetch_assoc()['c']];
}
 
/* =========================
   SALES DATA
   Count paid + installment amount_paid per program per month
========================== */
// Pull distinct program names dynamically from services table
$programs = [];
$progResult = $conn->query("SELECT DISTINCT program_name FROM services WHERE status != 'archived' AND program_name != '' ORDER BY program_name ASC");
while ($progRow = $progResult->fetch_assoc()) {
    $programs[] = $progRow['program_name'];
}
$salesByProgram = [];
$salesLabels = [];
for ($i = 0; $i <= 6; $i++) {
    $month = date('Y-m', strtotime("$salesStartMonth +$i months"));
    if ($month > $salesEndMonth) break;
    $salesLabels[] = date('M Y', strtotime($month));
}
foreach ($programs as $prog) {
    $data = [];
    $startSales = $salesStartMonth;
    for ($i = 0; $i <= 6; $i++) {
        $month = date('Y-m', strtotime("$startSales +$i months"));
        if ($month > $salesEndMonth) break;
        $res = $conn->query("
            SELECT IFNULL(SUM(
                CASE
                    WHEN p.payment_status = 'paid'        THEN p.payment_amount
                    WHEN p.payment_status = 'installment' THEN p.amount_paid
                    ELSE 0
                END
            ), 0) as total
            FROM payments p
            JOIN enrollments e ON p.enrollment_id = e.id
            JOIN services s    ON e.service_id = s.service_id
            WHERE s.program_name = '" . $conn->real_escape_string($prog) . "'
              AND DATE_FORMAT(p.payment_date,'%Y-%m') = '$month'
        ");
        $data[] = (float)$res->fetch_assoc()['total'];
    }
    $salesByProgram[] = ['name' => $prog, 'data' => $data];
}
 
/* =========================
   SERVICES DATA
========================== */
$servicesMonthEsc = $conn->real_escape_string($servicesMonth);
$freqServices = $conn->query("SELECT s.service_name, COUNT(*) as count FROM enrollments e JOIN services s ON e.service_id = s.service_id WHERE DATE_FORMAT(e.created_at,'%Y-%m')='$servicesMonthEsc' GROUP BY s.service_name ORDER BY count DESC LIMIT 6");
$freqLabels = [];
$freqData   = [];
if ($freqServices && $freqServices->num_rows > 0) {
    while ($row = $freqServices->fetch_assoc()) {
        $freqLabels[] = $row['service_name'];
        $freqData[]   = (int)$row['count'];
    }
}
 
/* =========================
   STATUS DATA
========================== */
$studentStatusLabels = ['Active', 'Pending', 'Inactive'];
$studentStatusColors = ['#0c4d24', '#c08609', '#a13737'];
 
$statusMonthEsc = $conn->real_escape_string($statusMonth);
$activeFilter   = $conn->query("SELECT COUNT(*) as c FROM students WHERE status='Active'  AND DATE_FORMAT(COALESCE(updated_at, created_at),'%Y-%m')='$statusMonthEsc'")->fetch_assoc()['c'];
$pendingFilter  = $conn->query("SELECT COUNT(*) as c FROM students WHERE status='Pending' AND DATE_FORMAT(COALESCE(updated_at, created_at),'%Y-%m')='$statusMonthEsc'")->fetch_assoc()['c'];
$inactiveFilter = $conn->query("SELECT COUNT(*) as c FROM students WHERE status='Inactive' AND DATE_FORMAT(COALESCE(updated_at, created_at),'%Y-%m')='$statusMonthEsc'")->fetch_assoc()['c'];
if ($activeFilter + $pendingFilter + $inactiveFilter == 0) {
    $activeFilter   = $activeStudents;
    $pendingFilter  = $conn->query("SELECT COUNT(*) as c FROM students WHERE status='Pending'")->fetch_assoc()['c'];
    $inactiveFilter = $inactiveStudents;
}
 
/* =========================
   MONTHS FOR FILTERS
========================== */
$rangeMonths = [];
for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $rangeMonths[] = ['value' => $m, 'label' => date('F Y', strtotime($m))];
}
 
$allMonths = [];
for ($i = 0; $i < 24; $i++) {
    $m = date('Y-m', strtotime("-$i months"));
    $allMonths[] = ['value' => $m, 'label' => date('F Y', strtotime($m))];
}
 
$enrollStartParts    = explode('-', $enrollStartMonth);
$enrollStartYear     = $enrollStartParts[0];
$enrollStartMonthNum = $enrollStartParts[1];
 
$enrollEndParts    = explode('-', $enrollEndMonth);
$enrollEndYear     = $enrollEndParts[0];
$enrollEndMonthNum = $enrollEndParts[1];
 
$salesStartParts    = explode('-', $salesStartMonth);
$salesStartYear     = $salesStartParts[0];
$salesStartMonthNum = $salesStartParts[1];
 
$salesEndParts    = explode('-', $salesEndMonth);
$salesEndYear     = $salesEndParts[0];
$salesEndMonthNum = $salesEndParts[1];
 
$servicesParts    = explode('-', $servicesMonth);
$servicesYear     = $servicesParts[0];
$servicesMonthNum = $servicesParts[1];
 
$statusParts    = explode('-', $statusMonth);
$statusYear     = $statusParts[0];
$statusMonthNum = $statusParts[1];
 
$years = [];
for ($i = date('Y'); $i >= date('Y') - 5; $i--) {
    $years[] = $i;
}
 
$months = [
    '01' => 'January',  '02' => 'February', '03' => 'March',    '04' => 'April',
    '05' => 'May',      '06' => 'June',      '07' => 'July',     '08' => 'August',
    '09' => 'September','10' => 'October',   '11' => 'November', '12' => 'December'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Administrator Dashboard</title>
  <link rel="stylesheet" href="../../assets/css/administratorcss/dashboard.css">
  <link rel="stylesheet" href="../../assets/css/hro/hro-base.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="../../assets/js/sidebar.js" defer></script>
  <style>
    .income-breakdown-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
      padding: 24px 36px 0;
    }
    .income-dash-card,
    .breakdown-dash-card {
      background: #fff;
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    .income-dash-card   { border-left: 4px solid #22c55e; }
    .breakdown-dash-card{ border-left: 4px solid #3b82f6; }
    .income-card-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 16px;
    }
    .income-mini-chart { width: 100%; height: 110px; position: relative; }
    .breakdown-dash-card .card-title { font-size: 16px; font-weight: 700; color: #111827; margin-bottom: 16px; }
    .progress-row { margin-bottom: 12px; }
    .progress-row .stat-label { font-size: 13px; color: #374151; display: block; margin-bottom: 4px; }
    .progress-bar { height: 10px; border-radius: 6px; background: #e5e7eb; overflow: hidden; }
    .progress-fill        { height: 100%; border-radius: 6px; }
    .progress-fill.green  { background: #22c55e; }
    .progress-fill.orange { background: #f97316; }
    .progress-fill.red    { background: #ef4444; }
    .progress-fill.blue   { background: #3b82f6; }
    .dashboard-mid-section { display: flex; gap: 24px; padding: 24px 36px 0; }
    .dashboard-left-col { flex: 0 0 300px; display: flex; flex-direction: column; gap: 16px; }
    .dashboard-right-col { flex: 1; }
    .dashboard-left-col .stat-card {
      background: #fff; border-radius: 16px; padding: 20px 24px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      display: flex; justify-content: space-between; align-items: center;
      cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;
    }
    .dashboard-left-col .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(0,0,0,0.12); }
    .enrollment-right-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); height: 100%; }
    .enrollment-right-card .chart-body { height: 300px; position: relative; }
    .sales-section, .bottom-section { padding: 24px 36px 0; }
    @media (max-width: 1100px) {
      .income-breakdown-row  { grid-template-columns: 1fr; }
      .dashboard-mid-section { flex-direction: column; }
      .dashboard-left-col    { flex: unset; }
    }
  </style>
</head>
<body>
 
<?php include __DIR__ . '/../../includes/administrator-sidebar.php'; ?>
 
<div class="main" style="display:block !important; min-height:100vh; background:#f3f4f6;">
 
  <div class="page-hero">
    <div class="page-hero-text"><h1>Administrator Dashboard</h1></div>
  </div>
 
  <!-- ROW 1 : Total Income | Payment Status Breakdown -->
  <div class="income-breakdown-row">
 
    <div class="income-dash-card">
      <div class="income-card-top">
        <div class="stat-info">
          <span class="stat-label">Total Income</span>
          <h2 class="stat-number">₱<?= number_format($totalIncome, 0) ?></h2>
          <span class="stat-sub <?= $growthPercent >= 0 ? 'positive' : 'negative' ?>">
            <?php if ($growthPercent >= 0): ?>
              <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M7 14l5-5 5 5z"/></svg>
            <?php else: ?>
              <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor"><path d="M7 10l5 5 5-5z"/></svg>
            <?php endif; ?>
            <?= $growthPercent >= 0 ? '+' : '' ?><?= $growthPercent ?>% vs last month
          </span>
        </div>
        <div class="stat-icon stat-icon-income">
          <svg viewBox="0 0 24 24" width="28" height="28" fill="#16a34a"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
        </div>
      </div>
      <div class="income-mini-chart">
        <canvas id="incomeChart"></canvas>
      </div>
    </div>
 
    <div class="breakdown-dash-card">
      <div class="card-title">Payment Status Breakdown</div>
      <div class="progress-row">
        <span class="stat-label">Paid: <?= $paidCount ?> (<?= $totalPayments ? round(($paidCount/$totalPayments)*100) : 0 ?>%)</span>
        <div class="progress-bar"><div class="progress-fill green" style="width:<?= $totalPayments ? ($paidCount/$totalPayments)*100 : 0 ?>%"></div></div>
      </div>
      <div class="progress-row">
        <span class="stat-label">Pending: <?= $pendingCount ?> (<?= $totalPayments ? round(($pendingCount/$totalPayments)*100) : 0 ?>%)</span>
        <div class="progress-bar"><div class="progress-fill orange" style="width:<?= $totalPayments ? ($pendingCount/$totalPayments)*100 : 0 ?>%"></div></div>
      </div>
      <div class="progress-row">
        <span class="stat-label">Overdue: <?= $overdueCount ?> (<?= $totalPayments ? round(($overdueCount/$totalPayments)*100) : 0 ?>%)</span>
        <div class="progress-bar"><div class="progress-fill red" style="width:<?= $totalPayments ? ($overdueCount/$totalPayments)*100 : 0 ?>%"></div></div>
      </div>
      <div class="progress-row">
        <span class="stat-label">Installment: <?= $installCount ?> (<?= $totalPayments ? round(($installCount/$totalPayments)*100) : 0 ?>%)</span>
        <div class="progress-bar"><div class="progress-fill blue" style="width:<?= $totalPayments ? ($installCount/$totalPayments)*100 : 0 ?>%"></div></div>
      </div>
    </div>
 
  </div>
 
  <!-- ROW 2 : 4 Stacked Cards | Enrollment Chart -->
  <div class="dashboard-mid-section">
    <div class="dashboard-left-col">
      <div class="stat-card stat-pending" onclick="window.location.href='user-access.php?tab=requests'">
        <div class="stat-info">
          <span class="stat-label">Pending Access Requests</span>
          <h2 class="stat-number"><?= $pendingRequests ?></h2>
          <span class="stat-sub">Requires Review</span>
        </div>
        <div class="stat-icon stat-icon-pending">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="#d97706"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
        </div>
      </div>
      <div class="stat-card" onclick="window.location.href='finance.php'">
        <div class="stat-info">
          <span class="stat-label">Active Services</span>
          <h2 class="stat-number"><?= $activeServices ?></h2>
          <span class="stat-sub">Available Packages</span>
        </div>
        <div class="stat-icon stat-icon-blue">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="#2563eb"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17.93V18h-2v1.93C7.06 19.48 4.52 16.94 4.07 13H6v-2H4.07C4.52 7.06 7.06 4.52 11 4.07V6h2V4.07c3.94.45 6.48 2.99 6.93 6.93H18v2h1.93c-.45 3.94-2.99 6.48-6.93 6.93z"/></svg>
        </div>
      </div>
      <div class="stat-card" onclick="window.location.href='../hro/student-records.php'">
        <div class="stat-info">
          <span class="stat-label">Total Students</span>
          <h2 class="stat-number"><?= $totalStudents ?></h2>
          <span class="stat-sub">
            <span class="status-green">●</span> <?= $activeStudents ?> Active
            <span class="status-red">●</span> <?= $inactiveStudents ?> Inactive
          </span>
        </div>
        <div class="stat-icon stat-icon-purple">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="#7c3aed"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v3h20v-3c0-3.3-6.7-5-10-5z"/></svg>
        </div>
      </div>
      <div class="stat-card" onclick="window.location.href='../hro/staff-management.php'">
        <div class="stat-info">
          <span class="stat-label">Total Staff</span>
          <h2 class="stat-number"><?= $totalStaff ?></h2>
          <span class="stat-sub">
            <span class="status-green">●</span> <?= $activeStaff ?> Active
            <span class="status-yellow">●</span> <?= $onLeaveStaff ?> On Leave
          </span>
        </div>
        <div class="stat-icon stat-icon-orange">
          <svg viewBox="0 0 24 24" width="26" height="26" fill="#d97706"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
        </div>
      </div>
    </div>
 
    <div class="dashboard-right-col">
      <div class="enrollment-right-card">
        <div class="chart-header">
          <h3 class="chart-title">Total Enrollment of Students</h3>
        </div>
        <div class="enrollment-filters">
          <div class="filter-group">
            <span class="filter-label">Start of Report</span>
            <form method="GET" class="filter-form">
              <select name="enroll_start_month" onchange="this.form.submit()" class="filter-select">
                <?php foreach($months as $num => $name): ?>
                  <option value="<?= $num ?>" <?= $enrollStartMonthNum == $num ? 'selected' : '' ?>><?= $name ?></option>
                <?php endforeach; ?>
              </select>
              <select name="enroll_start_year" onchange="this.form.submit()" class="filter-select">
                <?php foreach($years as $year): ?>
                  <option value="<?= $year ?>" <?= $enrollStartYear == $year ? 'selected' : '' ?>><?= $year ?></option>
                <?php endforeach; ?>
              </select>
              <input type="hidden" name="enroll_end_month"   value="<?= $enrollEndMonthNum ?>">
              <input type="hidden" name="enroll_end_year"    value="<?= $enrollEndYear ?>">
              <input type="hidden" name="sales_start"        value="<?= htmlspecialchars($salesStartMonth) ?>">
              <input type="hidden" name="sales_end"          value="<?= htmlspecialchars($salesEndMonth) ?>">
              <input type="hidden" name="services_month"     value="<?= htmlspecialchars($servicesMonth) ?>">
              <input type="hidden" name="status_month"       value="<?= htmlspecialchars($statusMonth) ?>">
            </form>
          </div>
          <div class="filter-group">
            <span class="filter-label">End of Report</span>
            <form method="GET" class="filter-form">
              <select name="enroll_end_month" onchange="this.form.submit()" class="filter-select">
                <?php foreach($months as $num => $name): ?>
                  <option value="<?= $num ?>" <?= $enrollEndMonthNum == $num ? 'selected' : '' ?>><?= $name ?></option>
                <?php endforeach; ?>
              </select>
              <select name="enroll_end_year" onchange="this.form.submit()" class="filter-select">
                <?php foreach($years as $year): ?>
                  <option value="<?= $year ?>" <?= $enrollEndYear == $year ? 'selected' : '' ?>><?= $year ?></option>
                <?php endforeach; ?>
              </select>
              <input type="hidden" name="enroll_start_month" value="<?= $enrollStartMonthNum ?>">
              <input type="hidden" name="enroll_start_year"  value="<?= $enrollStartYear ?>">
              <input type="hidden" name="sales_start"        value="<?= htmlspecialchars($salesStartMonth) ?>">
              <input type="hidden" name="sales_end"          value="<?= htmlspecialchars($salesEndMonth) ?>">
              <input type="hidden" name="services_month"     value="<?= htmlspecialchars($servicesMonth) ?>">
              <input type="hidden" name="status_month"       value="<?= htmlspecialchars($statusMonth) ?>">
            </form>
          </div>
        </div>
        <div class="chart-body"><canvas id="enrollChart"></canvas></div>
      </div>
    </div>
  </div>
 
  <!-- ROW 3 : Monthly Sales for Services -->
  <div class="sales-section">
    <div class="chart-card sales-chart-card">
      <div class="chart-header-main">
        <h3 class="chart-title-main">Monthly Sales for Services</h3>
        <div class="header-actions">
          <div class="sales-filters">
            <div class="filter-group-inline">
              <span class="filter-label">Start of Report</span>
              <form method="GET" class="filter-form-inline">
                <select name="sales_start_month" onchange="this.form.submit()" class="filter-select">
                  <?php foreach($months as $num => $name): ?>
                    <option value="<?= $num ?>" <?= $salesStartMonthNum == $num ? 'selected' : '' ?>><?= $name ?></option>
                  <?php endforeach; ?>
                </select>
                <select name="sales_start_year" onchange="this.form.submit()" class="filter-select">
                  <?php foreach($years as $year): ?>
                    <option value="<?= $year ?>" <?= $salesStartYear == $year ? 'selected' : '' ?>><?= $year ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" name="sales_end_month"     value="<?= $salesEndMonthNum ?>">
                <input type="hidden" name="sales_end_year"      value="<?= $salesEndYear ?>">
                <input type="hidden" name="enroll_start_month"  value="<?= $enrollStartMonthNum ?>">
                <input type="hidden" name="enroll_start_year"   value="<?= $enrollStartYear ?>">
                <input type="hidden" name="enroll_end_month"    value="<?= $enrollEndMonthNum ?>">
                <input type="hidden" name="enroll_end_year"     value="<?= $enrollEndYear ?>">
                <input type="hidden" name="services_month_num"  value="<?= $servicesMonthNum ?>">
                <input type="hidden" name="services_year"       value="<?= $servicesYear ?>">
                <input type="hidden" name="status_month_num"    value="<?= $statusMonthNum ?>">
                <input type="hidden" name="status_year"         value="<?= $statusYear ?>">
              </form>
            </div>
            <div class="filter-group-inline">
              <span class="filter-label">End of Report</span>
              <form method="GET" class="filter-form-inline">
                <select name="sales_end_month" onchange="this.form.submit()" class="filter-select">
                  <?php foreach($months as $num => $name): ?>
                    <option value="<?= $num ?>" <?= $salesEndMonthNum == $num ? 'selected' : '' ?>><?= $name ?></option>
                  <?php endforeach; ?>
                </select>
                <select name="sales_end_year" onchange="this.form.submit()" class="filter-select">
                  <?php foreach($years as $year): ?>
                    <option value="<?= $year ?>" <?= $salesEndYear == $year ? 'selected' : '' ?>><?= $year ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="hidden" name="sales_start_month"   value="<?= $salesStartMonthNum ?>">
                <input type="hidden" name="sales_start_year"    value="<?= $salesStartYear ?>">
                <input type="hidden" name="enroll_start_month"  value="<?= $enrollStartMonthNum ?>">
                <input type="hidden" name="enroll_start_year"   value="<?= $enrollStartYear ?>">
                <input type="hidden" name="enroll_end_month"    value="<?= $enrollEndMonthNum ?>">
                <input type="hidden" name="enroll_end_year"     value="<?= $enrollEndYear ?>">
                <input type="hidden" name="services_month_num"  value="<?= $servicesMonthNum ?>">
                <input type="hidden" name="services_year"       value="<?= $servicesYear ?>">
                <input type="hidden" name="status_month_num"    value="<?= $statusMonthNum ?>">
                <input type="hidden" name="status_year"         value="<?= $statusYear ?>">
              </form>
            </div>
          </div>
          <button class="export-btn" onclick="exportSalesData()">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
            Export
          </button>
        </div>
      </div>
      <div class="chart-body-main"><canvas id="salesChart"></canvas></div>
      <div class="chart-legend" id="salesLegend"></div>
    </div>
  </div>
 
  <!-- ROW 4 : Frequent Services + Status of Students -->
  <div class="bottom-section" style="padding-bottom:36px;">
    <div class="charts-row">
      <div class="chart-card-small">
        <div class="chart-header">
          <h3 class="chart-title">Frequent Services Availed</h3>
          <form method="GET" class="chart-filter">
            <select name="services_month_num" onchange="this.form.submit()" class="filter-select">
              <?php foreach($months as $num => $name): ?>
                <option value="<?= $num ?>" <?= $servicesMonthNum == $num ? 'selected' : '' ?>><?= $name ?></option>
              <?php endforeach; ?>
            </select>
            <select name="services_year" onchange="this.form.submit()" class="filter-select">
              <?php foreach($years as $year): ?>
                <option value="<?= $year ?>" <?= $servicesYear == $year ? 'selected' : '' ?>><?= $year ?></option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" name="status_month_num"   value="<?= $statusMonthNum ?>">
            <input type="hidden" name="status_year"        value="<?= $statusYear ?>">
            <input type="hidden" name="enroll_start_month" value="<?= $enrollStartMonthNum ?>">
            <input type="hidden" name="enroll_start_year"  value="<?= $enrollStartYear ?>">
            <input type="hidden" name="enroll_end_month"   value="<?= $enrollEndMonthNum ?>">
            <input type="hidden" name="enroll_end_year"    value="<?= $enrollEndYear ?>">
            <input type="hidden" name="sales_start_month"  value="<?= $salesStartMonthNum ?>">
            <input type="hidden" name="sales_start_year"   value="<?= $salesStartYear ?>">
            <input type="hidden" name="sales_end_month"    value="<?= $salesEndMonthNum ?>">
            <input type="hidden" name="sales_end_year"     value="<?= $salesEndYear ?>">
          </form>
        </div>
        <div class="chart-body-small"><canvas id="servicesChart"></canvas></div>
      </div>
      <div class="chart-card-small">
        <div class="chart-header">
          <h3 class="chart-title">Status of Students</h3>
          <form method="GET" class="chart-filter">
            <select name="status_month_num" onchange="this.form.submit()" class="filter-select">
              <?php foreach($months as $num => $name): ?>
                <option value="<?= $num ?>" <?= $statusMonthNum == $num ? 'selected' : '' ?>><?= $name ?></option>
              <?php endforeach; ?>
            </select>
            <select name="status_year" onchange="this.form.submit()" class="filter-select">
              <?php foreach($years as $year): ?>
                <option value="<?= $year ?>" <?= $statusYear == $year ? 'selected' : '' ?>><?= $year ?></option>
              <?php endforeach; ?>
            </select>
            <input type="hidden" name="services_month_num" value="<?= $servicesMonthNum ?>">
            <input type="hidden" name="services_year"      value="<?= $servicesYear ?>">
            <input type="hidden" name="enroll_start_month" value="<?= $enrollStartMonthNum ?>">
            <input type="hidden" name="enroll_start_year"  value="<?= $enrollStartYear ?>">
            <input type="hidden" name="enroll_end_month"   value="<?= $enrollEndMonthNum ?>">
            <input type="hidden" name="enroll_end_year"    value="<?= $enrollEndYear ?>">
            <input type="hidden" name="sales_start_month"  value="<?= $salesStartMonthNum ?>">
            <input type="hidden" name="sales_start_year"   value="<?= $salesStartYear ?>">
            <input type="hidden" name="sales_end_month"    value="<?= $salesEndMonthNum ?>">
            <input type="hidden" name="sales_end_year"     value="<?= $salesEndYear ?>">
          </form>
        </div>
        <div class="chart-body-small donut-wrap">
          <canvas id="studentsChart"></canvas>
          <div class="donut-center"><strong><?= $totalStudents ?></strong><span>Students</span></div>
        </div>
        <div class="donut-legend">
          <span class="dot green"></span> <?= $studentStatusLabels[0] ?> (<?= $activeFilter ?>)
          <span class="dot yellow"></span> <?= $studentStatusLabels[1] ?> (<?= $pendingFilter ?>)
          <span class="dot red"></span> <?= $studentStatusLabels[2] ?> (<?= $inactiveFilter ?>)
        </div>
      </div>
    </div>
  </div>
 
</div>
 
<script>
const programColors = ['#ef4444','#f97316','#eab308','#22c55e','#3b82f6','#8b5cf6'];
 
function exportSalesData() {
  const salesChart = Chart.getChart('salesChart');
  if (!salesChart) return;
  const labels   = salesChart.data.labels;
  const datasets = salesChart.data.datasets;
  let csv = 'Month,' + datasets.map(d => d.label).join(',') + '\n';
  labels.forEach((label, i) => {
    const row = [label];
    datasets.forEach(ds => { row.push(ds.data[i] || 0); });
    csv += row.join(',') + '\n';
  });
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = 'monthly_sales_report.csv';
  link.style.visibility = 'hidden';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}
 
document.addEventListener('DOMContentLoaded', function () {
 
  try {
    const incomeCtx = document.getElementById('incomeChart');
    if (incomeCtx) {
      new Chart(incomeCtx, {
        type: 'line',
        data: {
          labels: <?= json_encode($incomeGraphLabels) ?>,
          datasets: [{
            data: <?= json_encode($incomeGraphData) ?>,
            borderColor: '#22c55e',
            backgroundColor: 'rgba(34,197,94,0.1)',
            fill: true, tension: 0.4,
            pointBackgroundColor: '#22c55e',
            pointRadius: 3, borderWidth: 2
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { display: false } },
            y: { beginAtZero: true, ticks: { callback: v => '₱' + v.toLocaleString() } }
          }
        }
      });
    }
  } catch(e) { console.error('Income chart:', e); }
 
  try {
    const enrollCtx = document.getElementById('enrollChart');
    if (enrollCtx) {
      new Chart(enrollCtx, {
        type: 'bar',
        data: {
          labels: <?= json_encode(array_column($monthlyEnrollment,'label')) ?>,
          datasets: [{ data: <?= json_encode(array_column($monthlyEnrollment,'count')) ?>, backgroundColor: '#fca5a5', borderRadius: 6 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true } } }
      });
    }
  } catch(e) { console.error('Enrollment chart:', e); }
 
  try {
    const salesCtx = document.getElementById('salesChart');
    if (salesCtx) {
      new Chart(salesCtx, {
        type: 'bar',
        data: {
          labels: <?= json_encode($salesLabels) ?>,
          datasets: <?php
            $colors = ['#ef4444','#f97316','#eab308','#22c55e','#3b82f6','#8b5cf6'];
            $ds = [];
            foreach($salesByProgram as $i => $p) {
              $ds[] = ['label'=>$p['name'],'data'=>$p['data'],'backgroundColor'=>$colors[$i%6],'borderRadius'=>4];
            }
            echo json_encode($ds);
          ?>
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true } } }
      });
    }
  } catch(e) { console.error('Sales chart:', e); }
 
  <?php
  $lc = ['#ef4444','#f97316','#eab308','#22c55e','#3b82f6','#8b5cf6'];
  foreach($salesByProgram as $i=>$p): ?>
  if(document.getElementById('salesLegend')) {
    document.getElementById('salesLegend').innerHTML += '<span class="legend-item"><span class="legend-dot" style="background:<?= $lc[$i%6] ?>"></span><?= htmlspecialchars($p['name']) ?></span>';
  }
  <?php endforeach; ?>
 
  try {
    const servicesCtx = document.getElementById('servicesChart');
    if (servicesCtx) {
      new Chart(servicesCtx, {
        type: 'pie',
        data: { labels: <?= json_encode($freqLabels) ?>, datasets: [{ data: <?= json_encode($freqData) ?>, backgroundColor: programColors }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } } }
      });
    }
  } catch(e) { console.error('Services chart:', e); }
 
  try {
    const studentsCtx = document.getElementById('studentsChart');
    if (studentsCtx) {
      new Chart(studentsCtx, {
        type: 'doughnut',
        data: {
          labels: <?= json_encode($studentStatusLabels) ?>,
          datasets: [{ data: [<?= $activeFilter ?>,<?= $pendingFilter ?>,<?= $inactiveFilter ?>], backgroundColor: <?= json_encode($studentStatusColors) ?>, borderWidth: 2 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false } } }
      });
    }
  } catch(e) { console.error('Students chart:', e); }
 
});
</script>
</body>
</html>