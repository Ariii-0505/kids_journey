<?php
require_once(__DIR__ . "/../../includes/db.php");
require_once __DIR__ . '/../../includes/auth.php';

$activeTab = $_GET['tab'] ?? 'payments'; // default to payments

/* =========================
   DASHBOARD STATS
========================= */
$totalServices = $conn->query("SELECT COUNT(*) as total FROM services")->fetch_assoc()['total'];
$totalPayments = $conn->query("SELECT COUNT(*) as total FROM payments")->fetch_assoc()['total'];
$paidCount     = $conn->query("SELECT COUNT(*) as total FROM payments WHERE status='paid'")->fetch_assoc()['total'];
$pendingCount  = $conn->query("SELECT COUNT(*) as total FROM payments WHERE status='pending'")->fetch_assoc()['total'];
$overdueCount  = $conn->query("SELECT COUNT(*) as total FROM payments WHERE status='overdue'")->fetch_assoc()['total'];
$installCount  = $conn->query("SELECT COUNT(*) as total FROM payments WHERE status='installment'")->fetch_assoc()['total'];

/* =========================
   RECENT PAYMENTS
========================= */
$recentPayments = $conn->query("
    SELECT p.*, s.full_name, s.student_id
    FROM payments p
    JOIN enrollments e ON p.enrollment_id = e.id
    JOIN students s ON e.student_id = s.id
    ORDER BY p.payment_date DESC
    LIMIT 10

");

/* =========================
   SERVICES LIST
========================= */
$services = $conn->query("SELECT * FROM services");
?>
<!DOCTYPE html>
<html>
<head>
  <title>Finance Management</title>
  <link rel="stylesheet" href="../../assets/css/finance/finance.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
  <script src="../../assets/js/sidebar.js" defer></script>
  <script>
          function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.getElementById(tab).classList.add('active');
            document.querySelectorAll('.tab-pill').forEach(el => el.classList.remove('active'));
            document.querySelector('[data-tab="'+tab+'"]').classList.add('active');

            // Toggle Export vs Add Service controls
            const exportBtn = document.getElementById('exportBtn');
            const serviceControls = document.getElementById('serviceControls');

            if (tab === 'payments') {
              exportBtn.style.display = 'flex';
              serviceControls.style.display = 'none';
            } else {
              exportBtn.style.display = 'none';
              serviceControls.style.display = 'flex';
            }
          }

          // Ensure correct initial state on page load
          window.addEventListener('DOMContentLoaded', () => {
            showTab("<?= $activeTab ?>");
          });
  </script>
</head>
<body>

<?php include __DIR__ . '/../../includes/superadmin-sidebar.php'; ?>

<div class="main">

  <!-- HEADER -->
<div class="header">
  <h1>Finance Management</h1>
  <p>Track student's payment status and enrollment</p>

  <!-- Export button (Payment Management only) -->
  <button id="exportBtn" class="btn-export">
    <span class="export-icon">⬇</span>
    Export
  </button>

  <!-- Add Service button + Sort dropdown (Service Management only) -->
  <div id="serviceControls" style="display:none; float:right; display:flex; gap:10px; align-items:center;">
    <select id="sortBy" class="sort-dropdown">
      <option value="date_created">Date Created</option>
      <option value="active">Active</option>
      <option value="inactive">Inactive</option>
    </select>
      <button id="openServiceModal" class="btn-export">
        <span class="export-icon">➕</span>
        Add Service
      </button>
  </div>
</div>

<!-- Tabs -->
<div class="tabs">
    <span class="tab-pill <?= $activeTab === 'payments' ? 'active' : '' ?>"  
          data-tab="payments" onclick="setTab('payments')">
      Payment Management
    </span>
    <span class="tab-pill <?= $activeTab === 'services' ? 'active' : '' ?>"  
          data-tab="services" onclick="setTab('services')">
      Service Management
    </span>
</div>



  <!-- ✅ Payment Management Tab -->
  <div class="tab-content <?= $activeTab === 'payments' ? 'active' : '' ?>" id="payments">
    <div class="card">
      <h2 class="card-title">Overview</h2>
      <div class="stats-grid">
        <div class="stat-card">
          <div>
            <span class="stat-label">Active Services</span>
            <h2 class="stat-number"><?= $totalServices ?></h2>
            <span class="stat-sub">Available packages</span>
          </div>
          <div class="stat-icon green">📦</div>
        </div>

        <div class="stat-card">
          <div>
            <span class="stat-label">Payment Records</span>
            <h2 class="stat-number"><?= $totalPayments ?></h2>
            <span class="stat-sub"><?= $paidCount ?> Paid, <?= $pendingCount ?> Pending</span>
          </div>
          <div class="stat-icon orange">💳</div>
        </div>
      </div>

      <!-- Payment Status Breakdown -->
      <div class="breakdown" style="margin-top:30px;">
        <h2 class="card-title">Payment Status Breakdown</h2>
        <div class="progress-row">
          <span class="stat-label">Paid: <?= $paidCount ?> (<?= $totalPayments ? round(($paidCount/$totalPayments)*100) : 0 ?>%)</span>
          <div class="progress-bar">
            <div class="progress-fill green" style="width:<?= $totalPayments ? ($paidCount/$totalPayments)*100 : 0 ?>%"></div>
          </div>
        </div>
        <div class="progress-row">
          <span class="stat-label">Pending: <?= $pendingCount ?> (<?= $totalPayments ? round(($pendingCount/$totalPayments)*100) : 0 ?>%)</span>
          <div class="progress-bar">
            <div class="progress-fill orange" style="width:<?= $totalPayments ? ($pendingCount/$totalPayments)*100 : 0 ?>%"></div>
          </div>
        </div>
        <div class="progress-row">
          <span class="stat-label">Overdue: <?= $overdueCount ?> (<?= $totalPayments ? round(($overdueCount/$totalPayments)*100) : 0 ?>%)</span>
          <div class="progress-bar">
            <div class="progress-fill red" style="width:<?= $totalPayments ? ($overdueCount/$totalPayments)*100 : 0 ?>%"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Payments Table -->
    <div class="card">
      <h2 class="card-title">Recent Payments</h2>
      <table class="finance-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>Amount</th>
            <th>Method</th>
            <th>Reference</th>
            <th>Status</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
              <tbody>
                <?php if ($recentPayments->num_rows > 0): ?>
                  <?php while ($row = $recentPayments->fetch_assoc()): ?>
                    <tr>
                     <td><?= htmlspecialchars($row['full_name']) ?> (<?= htmlspecialchars($row['student_id']) ?>)</td>
                      <td>₱<?= number_format($row['amount'], 2) ?></td>
                      <td><?= ucfirst($row['method']) ?></td>
                      <td><?= htmlspecialchars($row['reference_number']) ?></td>
                      <td><span class="badge <?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                      <td><?= date("M d, Y", strtotime($row['created_at'])) ?></td>
                      <td>
                        <button class="action-btn">✏️</button>
                        <button class="action-btn">📩</button>
                        <button class="action-btn">🗄️</button>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7" style="text-align:center;">No recent payments found.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
      </table>
            <?php if ($recentPayments->num_rows >= 10 && (!isset($_GET['show']) || $_GET['show'] !== 'all')): ?>
              <div style="text-align:center; margin-top:10px;">
                <a href="finance.php?tab=payments&show=all" class="btn">See More</a>
              </div>
            <?php endif; ?>
    </div>
  </div>

  <!-- ✅ Service Management Tab -->
  <div class="tab-content <?= $activeTab === 'services' ? 'active' : '' ?>" id="services">
    <div class="card">
      <h2 class="card-title">Service Management</h2>
      <table class="finance-table" id="servicesTable">
        <thead>
          <tr>
            <th>Service/Program</th>
            <th>Package/Category</th>
            <th>Rate</th>
            <th>Frequency</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
            <?php while ($svc = $services->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($svc['program_name']) ?></td>
                  <td><?= htmlspecialchars($svc['service_name']) ?></td>
                  <td>₱<?= number_format($svc['price'], 2) ?></td>
                  <td><?= htmlspecialchars($svc['description']) ?></td>
                  <td><span class="badge <?= $svc['status'] === 'active' ? 'active' : 'inactive' ?>">
                      <?= ucfirst($svc['status']) ?>
                  </span></td>
                  <td>
                      <button class="action-btn edit-btn" data-id="<?= $svc['service_id'] ?>">✏️</button>
                      <button class="action-btn delete-btn" data-id="<?= $svc['service_id'] ?>">🗑️</button>
                  </td>
                </tr>
            <?php endwhile; ?>
      </tbody>
      </table>
    </div>
  </div>



<!-- ADD SERVICE MODAL -->
        <div class="modal" id="addServiceModal">
          <div class="modal-content">
            <div class="modal-header">
              <h2>Add New Service</h2>
              <span class="close-btn" id="closeServiceModal">&times;</span>
            </div>

            <form method="POST" action="add-service.php">
              <div class="form-group">
                <label>Service Name *</label>
                <input type="text" name="title" required>
              </div>

              <div class="form-group">
                <label>Description</label>
                <textarea name="description"></textarea>
              </div>

              <div class="form-group">
                <label>Category</label>
                <input type="text" name="category">
              </div>

              <div class="form-group">
                <label>Rate (₱) *</label>
                <input type="number" name="rate" step="0.01" required>
              </div>

              <div class="form-group">
                <label>Frequency</label>
                <select name="frequency" required>
                  <option value="One Time">One Time</option>
                  <option value="Per Hour">Per Hour</option>
                  <option value="Per 2 Hours">Per 2 Hours</option>
                  <option value="1x a Week">1x a Week</option>
                  <option value="2x a Week">2x a Week</option>
                  <option value="3x a Week">3x a Week</option>
                  <option value="4x a Week">4x a Week</option>
                  <option value="Per Session">Per Session</option>
                </select>
              </div>

              <div class="modal-footer">
                <button type="button" class="btn cancel-btn" id="cancelService">Cancel</button>
                <button type="submit" class="btn submit-btn">Create</button>
              </div>
            </form>
          </div>
        </div>

    <!-- CONFIRMATION MODAL -->
        <div class="modal" id="confirmModal">
          <div class="modal-content">
            <div class="modal-header" style="background:#ef4444; color:#fff; padding:10px; border-radius:8px 8px 0 0;">
              <h2>CONFIRMATION</h2>
            </div>
            <div style="padding:20px;">
              <p>Are you sure you want to create this service?</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn cancel-btn" id="cancelConfirm">Cancel</button>
              <button type="button" class="btn submit-btn" id="confirmCreate">Confirm</button>
            </div>
          </div>
        </div>


</div>
<script>

const serviceModal = document.getElementById("addServiceModal");
const confirmModal = document.getElementById("confirmModal");

const openServiceBtn = document.getElementById("openServiceModal");
const closeServiceBtn = document.getElementById("closeServiceModal");
const cancelServiceBtn = document.getElementById("cancelService");

const confirmBtn = document.getElementById("confirmCreate");
const cancelConfirmBtn = document.getElementById("cancelConfirm");

// Open Add Service modal
openServiceBtn.onclick = () => serviceModal.style.display = "flex";
closeServiceBtn.onclick = () => serviceModal.style.display = "none";
cancelServiceBtn.onclick = () => serviceModal.style.display = "none";

// Intercept Create button in Add Service form
document.querySelector("#addServiceModal form").addEventListener("submit", function(e) {
  e.preventDefault(); // stop immediate submit
  serviceModal.style.display = "none";
  confirmModal.style.display = "flex"; // show confirmation
});

// Cancel confirmation → back to Add Service form
cancelConfirmBtn.onclick = () => {
  confirmModal.style.display = "none";
  serviceModal.style.display = "flex";
};

// Confirm → actually submit form
confirmBtn.onclick = () => {
  confirmModal.style.display = "none";
  document.querySelector("#addServiceModal form").submit();
};

// Close modal when clicking outside
window.onclick = e => {
  if (e.target == serviceModal) serviceModal.style.display = "none";
  if (e.target == confirmModal) confirmModal.style.display = "none";
};

document.getElementById("exportBtn").addEventListener("click", function() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();

  doc.text("Service Management Report", 14, 16);

  doc.autoTable({
    html: "#servicesTable", // target the Service Management table
    startY: 25,
    theme: "grid",
    headStyles: { fillColor: [22, 160, 133] },
    styles: { fontSize: 10 }
  });

  doc.save("service-management.pdf");
});

// Handle Edit button
document.querySelectorAll(".edit-btn").forEach(btn => {
  btn.addEventListener("click", function() {
    const serviceId = this.dataset.id;
    // Redirect to edit page or open edit modal
    window.location.href = "edit-service.php?id=" + serviceId;
  });
});

// Handle Delete button
document.querySelectorAll(".delete-btn").forEach(btn => {
  btn.addEventListener("click", function() {
    const serviceId = this.dataset.id;

    // Show confirmation before deleting
    if (confirm("Are you sure you want to delete this service?")) {
      window.location.href = "delete-service.php?id=" + serviceId;
    }
  });
});

document.querySelectorAll(".delete-btn").forEach(btn => {
  btn.addEventListener("click", function() {
    const serviceId = this.dataset.id;
    if (confirm("Are you sure you want to delete this service?")) {
      window.location.href = "delete-service.php?id=" + serviceId;
    }
  });
});

function showTab(tab) {
  document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
  document.getElementById(tab).classList.add('active');
  document.querySelectorAll('.tab-pill').forEach(el => el.classList.remove('active'));
  document.querySelector('[data-tab="'+tab+'"]').classList.add('active');

  // Toggle Export vs Add Service controls
  const exportBtn = document.getElementById('exportBtn');
  const serviceControls = document.getElementById('serviceControls');

  if (tab === 'payments') {
    exportBtn.style.display = 'flex';
    serviceControls.style.display = 'none';
  } else {
    exportBtn.style.display = 'none';
    serviceControls.style.display = 'flex';
  }

  // Save tab state
  localStorage.setItem("activeTab", tab);
}

// On page load
window.addEventListener('DOMContentLoaded', () => {
  const savedTab = localStorage.getItem("activeTab") || "payments";
  showTab(savedTab);
});

function setTab(tab) {
  window.location.href = "finance.php?tab=" + tab;
}




</script>

</body>
</html>

