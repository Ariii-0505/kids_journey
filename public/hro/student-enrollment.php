<?php
require_once(__DIR__ . "/../../includes/db.php");
require_once(__DIR__ . "/../../includes/auth.php");

if (isset($_POST['enroll_student'])) {

  $first_name     = $_POST['first_name'];
  $last_name      = $_POST['last_name'];
  $middle_name    = $_POST['middle_name'];
  $suffix         = $_POST['suffix'];
  $date_of_birth  = $_POST['date_of_birth'] ?? '';
  $gender         = $_POST['gender'];
  $address        = $_POST['address'];
  $status         = $_POST['status'];
  $email          = $_POST['email'];
  $contact_number = $_POST['contact_number'];

  if ($date_of_birth == '') {
      echo "Date of birth is required.";
      exit;
  }

  $conn->begin_transaction();

  try {
      // GENERATE STUDENT ID (e.g. STD-0001)
      $countResult = $conn->query("SELECT COUNT(*) AS total FROM students");
      $countRow    = $countResult->fetch_assoc();
      $student_id  = 'STD-' . str_pad($countRow['total'] + 1, 4, '0', STR_PAD_LEFT);

      // INSERT INTO STUDENTS
      $stmt4 = $conn->prepare("INSERT INTO students 
          (student_id, first_name, middle_name, last_name, suffix, date_of_birth, gender, address) 
          VALUES (?,?,?,?,?,?,?,?)");
      $stmt4->bind_param("ssssssss",
          $student_id, $first_name, $middle_name, $last_name,
          $suffix, $date_of_birth, $gender, $address
      );
      $stmt4->execute();
      $student_db_id = $stmt4->insert_id;

      // INSERT INTO GUARDIANS
      $stmt2 = $conn->prepare("INSERT INTO guardians
          (student_id, guardian_name, contact_number, email)
          VALUES (?,?,?,?)");
      $stmt2->bind_param("isss",
          $student_db_id, $_POST['guardian_name'], $contact_number, $email
      );
      $stmt2->execute();

      // GET FIRST SERVICE ID FROM FORM
      $first_service_name = $_POST['service'][0] ?? '';
      $first_package_name = $_POST['package'][0] ?? '';

      // LOOK UP service_id FROM services TABLE
      $svcStmt = $conn->prepare("SELECT service_id FROM services 
          WHERE program_name = ? AND service_name = ? LIMIT 1");
      $svcStmt->bind_param("ss", $first_service_name, $first_package_name);
      $svcStmt->execute();
      $svcRow     = $svcStmt->get_result()->fetch_assoc();
      $service_id = $svcRow['service_id'] ?? null;

      // INSERT INTO ENROLLMENTS
      $stmt = $conn->prepare("INSERT INTO enrollments 
          (student_id, service_id, status, enrolled_by) 
          VALUES (?,?,?,?)");
      $stmt->bind_param("iisi",
          $student_db_id, $service_id, $status, $_SESSION['user_id']
      );
      $stmt->execute();
      $enrollment_id = $stmt->insert_id;

      // CREATE TRANSACTION
      $transStmt = $conn->prepare("INSERT INTO transactions 
          (student_id, enrollment_id, total_amount, amount_paid) 
          VALUES (?,?,0,0)");
      $transStmt->bind_param("ii", $student_db_id, $enrollment_id);
      $transStmt->execute();
      $transaction_id = $transStmt->insert_id;

      // INSERT SERVICES AND COMPUTE TOTAL
      $total_amount = 0;

      if (!empty($_POST['service'])) {
          foreach ($_POST['service'] as $index => $service) {
              $package = $_POST['package'][$index];

              if ($service == "Academic Tutorial") {
                  $package = null;
              }

              $rateStmt = $conn->prepare("SELECT service_id, price FROM services 
                  WHERE program_name = ? AND service_name = ? LIMIT 1");
              $rateStmt->bind_param("ss", $service, $package);
              $rateStmt->execute();
              $rateRow = $rateStmt->get_result()->fetch_assoc();

              $service_id_item = $rateRow['service_id'] ?? null;
              $amount          = $rateRow['price'] ?? 0;

              $stmt3 = $conn->prepare("INSERT INTO transaction_services 
                  (transaction_id, service_id, price, quantity, subtotal) 
                  VALUES (?,?,?,1,?)");
              $stmt3->bind_param("iidd",
                  $transaction_id, $service_id_item, $amount, $amount
              );
              $stmt3->execute();

              $total_amount += $amount;
          }
      }

      // UPDATE TRANSACTION TOTAL
      $updateTrans = $conn->prepare("UPDATE transactions SET total_amount = ? WHERE transaction_id = ?");
      $updateTrans->bind_param("di", $total_amount, $transaction_id);
      $updateTrans->execute();

      // INSERT INTO PAYMENTS
      $payment_method = $_POST['payment_method'];
      $reference_no   = $_POST['reference_no'] ?? '';
      // If cash and no reference provided, set to NULL or empty string
      // If gcash, use the user-provided reference number
      $payment_status = 'pending';

      $stmt5 = $conn->prepare("INSERT INTO payments 
          (transaction_id, enrollment_id, student_id, payment_amount, 
          payment_method, reference_no, payment_status, recorded_by) 
          VALUES (?,?,?,?,?,?,?,?)");
      $stmt5->bind_param("iiidsssi",
          $transaction_id, $enrollment_id, $student_db_id, $total_amount,
          $payment_method, $reference_no, $payment_status, $_SESSION['user_id']
      );
      $stmt5->execute();

      // ── ACTIVITY LOG ──────────────────────────────────────
      $log_user_id  = $_SESSION['user_id'];
      $log_staff_id = $_SESSION['staff_id'] ?? null;
      $log_role     = $_SESSION['role']     ?? 'Human Resources';
      $log_action   = 'Enrolled Student';
      $log_module   = 'Student Enrollment';
      $log_status   = 'Success';
      $log_details  = 'Enrolled student: ' . $first_name . ' ' . $last_name . ' (' . $student_id . ')';
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
      // ──────────────────────────────────────────────────────

      $conn->commit();
      header("Location: student-enrollment.php?success=1");
      exit();

  } catch (Exception $e) {
      $conn->rollback();

      // Log failed attempt
      $log_user_id  = $_SESSION['user_id'];
      $log_staff_id = $_SESSION['staff_id'] ?? null;
      $log_role     = $_SESSION['role']     ?? 'Human Resources';
      $log_action   = 'Enrolled Student';
      $log_module   = 'Student Enrollment';
      $log_status   = 'Failed';
      $log_details  = 'Failed to enroll student: ' . $e->getMessage();
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

      echo "Error: " . $e->getMessage();
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Student Enrollment</title>
  <link rel="stylesheet" href="../../assets/css/hro/hro-base.css">
  <link rel="stylesheet" href="../../assets/css/hro/student-enrollment.css">
  <script src="../../assets/js/sidebar.js" defer></script>
</head>

<body>

<?php include __DIR__ . '/../../includes/hro-sidebar.php'; ?>

<div class="main">

  <div class="page-header">

    <?php if(isset($_GET['success'])): ?>
    <div id="toastSuccess">
      <span>&#10003; Student enrolled successfully!</span>
    </div>
    <style>
      #toastSuccess {
        position: fixed;
        bottom: 30px;
        right: 30px;
        background: #1abc9c;
        color: #fff;
        padding: 14px 22px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        z-index: 9999;
        opacity: 1;
        transition: opacity 0.6s ease;
      }
      #toastSuccess.hide { opacity: 0; }
    </style>
    <script>
      window.addEventListener('DOMContentLoaded', function() {
        const toast = document.getElementById('toastSuccess');
        if (toast) {
          setTimeout(() => toast.classList.add('hide'), 3000);
          setTimeout(() => toast.remove(), 3600);
        }
      });
    </script>
    <?php endif; ?>

    <div style="display:flex; justify-content:space-between; align-items:center;">
      <div>
        <h1>Student Enrollment</h1>
        <p>Enroll new students</p>
      </div>
      <a href="#" class="enroll-btn" id="openEnrollModal">+ Enroll Student</a>
    </div>
  </div>

  <div class="dashboard-card">
    <h3 class="card-title">Recently Enrolled</h3>

    <?php
    $limit = isset($_GET['show']) && $_GET['show'] === 'all' ? 100 : 20;

    $query = "
      SELECT s.full_name, g.guardian_name
      FROM enrollments e
      LEFT JOIN students s ON e.student_id = s.id
      LEFT JOIN guardians g ON g.student_id = s.id
      WHERE (s.archived IS NULL OR s.archived = 0)
      ORDER BY e.created_at DESC
      LIMIT $limit
    ";

    $result = $conn->query($query);

    if ($result && $result->num_rows > 0):
      while ($row = $result->fetch_assoc()):
    ?>
      <div class="enrollment-item">
        <div>
          <strong><?= htmlspecialchars($row['full_name']); ?></strong>
          <p>Guardian: <?= htmlspecialchars($row['guardian_name']); ?></p>
        </div>
        <span class="status-badge">Enrolled</span>
      </div>
    <?php endwhile; else: ?>
      <p>No recent enrollments found.</p>
    <?php endif; ?>

    <?php if ($result && $result->num_rows >= 20 && (!isset($_GET['show']) || $_GET['show'] !== 'all')): ?>
      <div style="text-align:center; margin-top:10px;">
        <a href="student-enrollment.php?show=all" class="btn">See More</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ENROLL MODAL -->
<div class="modal-overlay" id="enrollModal">
  <div class="modal-content">

    <div class="form-alert" id="formAlert">
      <span class="alert-icon">ⓘ</span>
      <span>Student information - fields marked * are required</span>
    </div>

    <div class="modal-header">
      <h2>Enroll New Student</h2>
      <span class="close-btn" id="closeEnrollModal">&times;</span>
    </div>

    <form method="POST" action="">

      <!-- STUDENT INFO -->
      <div class="form-grid">
        <div class="form-group">
          <label>First Name *</label>
          <input type="text" name="first_name" required data-required="true">
        </div>
        <div class="form-group">
          <label>Last Name *</label>
          <input type="text" name="last_name" required data-required="true">
        </div>
        <div class="form-group">
          <label>Middle Name *</label>
          <input type="text" name="middle_name" required data-required="true">
        </div>
        <div class="form-group">
          <label>Suffix</label>
          <input type="text" name="suffix">
        </div>
        <div class="form-group">
          <label>Date of Birth *</label>
          <input type="date" name="date_of_birth" required data-required="true">
        </div>
        <div class="form-group">
          <label>Gender *</label>
          <select name="gender" data-required="true">
            <option value="">Select Gender</option>
            <option>Male</option>
            <option>Female</option>
          </select>
        </div>
      </div>

      <div class="form-group full-width">
        <label>Address</label>
        <textarea name="address"></textarea>
      </div>

      <!-- GUARDIAN -->
      <h3 class="section-title">Guardian Information</h3>

      <div class="form-group full-width">
        <label>Guardian Name *</label>
        <input type="text" name="guardian_name" required data-required="true">
      </div>

      <div class="form-grid">
        <div class="form-group">
          <label>Contact Number *</label>
          <input type="text" name="contact_number" required data-required="true">
        </div>
        <div class="form-group">
          <label>Email *</label>
          <input type="email" name="email" required data-required="true">
        </div>
      </div>

      <!-- SERVICES -->
      <h3 class="section-title">Service/Package</h3>

      <div class="form-group service-buttons">
        <button type="button" id="addServiceBtn" class="btn service-btn">+ Add Service</button>
      </div>

      <div id="servicesContainer">
        <div class="service-row">
          <div class="form-grid">
            <div class="form-group">
              <label>Service</label>
              <select name="service[]" class="serviceSelect" required data-required="true">
                <option value="">Select Service</option>
              </select>
            </div>
            <div class="form-group package-container">
              <label>Package</label>
              <select name="package[]" class="packageSelect" required data-required="true">
                <option value="">Select package</option>
              </select>
            </div>
            <div class="form-group service-buttons">
              <button type="button" class="btn service-btn removeServiceBtn">− Remove Service</button>
            </div>
          </div>
        </div>
      </div>

      <div class="form-group full-width">
        <label>Enrollment Status</label>
        <select name="status">
          <option value="Pending">Pending</option>
          <option value="Active">Active</option>
        </select>
      </div>

      <div class="form-group full-width">
        <label>Payment Method</label>
        <select name="payment_method" id="paymentMethod" required onchange="toggleReferenceField()">
          <option value="">Select Payment</option>
        </select>
      </div>

      <div class="form-group full-width" id="referenceField" style="display:none;">
        <label>Reference Number *</label>
        <input type="text" name="reference_no" id="referenceNo" placeholder="Enter GCash reference number">
      </div>

      <div id="totalAmount" style="margin-top:10px;font-weight:bold;">Total: ₱</div>
      <input type="hidden" name="total_amount" id="hiddenTotal">

      <div class="modal-footer">
        <button type="button" class="btn cancel-btn" id="cancelEnroll">Cancel</button>
        <button type="submit" name="enroll_student" class="btn submit-btn">Enroll Student</button>
      </div>

    </form>
  </div>
</div>

<script>
function toggleReferenceField() {
  const paymentMethod = document.getElementById('paymentMethod').value;
  const referenceField = document.getElementById('referenceField');
  const referenceInput = document.getElementById('referenceNo');
  
  if (paymentMethod.toLowerCase() === 'gcash') {
    referenceField.style.display = 'block';
    referenceInput.setAttribute('required', 'true');
  } else {
    referenceField.style.display = 'none';
    referenceInput.removeAttribute('required');
    referenceInput.value = '';
  }
}

const modal = document.getElementById("enrollModal");
document.getElementById("openEnrollModal").onclick = () => modal.classList.add("open");
document.getElementById("closeEnrollModal").onclick = () => modal.classList.remove("open");
document.getElementById("cancelEnroll").onclick = () => modal.classList.remove("open");
window.onclick = e => { if (e.target == modal) modal.classList.remove("open"); };

let packages = {};

async function loadPackages() {
  try {
    const response = await fetch("get_packages.php");
    packages = await response.json();
    document.querySelectorAll(".serviceSelect").forEach(select => {
      select.innerHTML = '<option value="">Select Service</option>';
      Object.keys(packages).forEach(program => {
        let option = document.createElement("option");
        option.value = program;
        option.textContent = program;
        select.appendChild(option);
      });
    });
  } catch (err) {
    console.error("Error loading packages:", err);
  }
}

document.addEventListener("DOMContentLoaded", function() {
    loadPackages();
    loadPaymentMethods();
});

async function loadPaymentMethods() {
    try {
        const response = await fetch("get_payment_methods.php");
        const methods = await response.json();
        const select = document.getElementById("paymentMethod");
        select.innerHTML = '<option value="">Select Payment</option>';
        methods.forEach(method => {
            let option = document.createElement("option");
            option.value = method;
            option.textContent = method;
            select.appendChild(option);
        });
    } catch (err) {
        console.error("Error loading payment methods:", err);
    }
}

const container = document.getElementById("servicesContainer");
const addBtn    = document.getElementById("addServiceBtn");

addBtn.addEventListener("click", function () {
  const newRow = document.createElement("div");
  newRow.classList.add("service-row");
  newRow.innerHTML = `
    <div class="form-grid">
      <div class="form-group">
        <label>Service</label>
        <select name="service[]" class="serviceSelect" required>
          <option value="">Select Service</option>
        </select>
      </div>
      <div class="form-group package-container">
        <label>Package</label>
        <select name="package[]" class="packageSelect" required>
          <option value="">Select package</option>
        </select>
      </div>
      <div class="form-group service-buttons">
        <button type="button" class="btn service-btn removeServiceBtn">− Remove Service</button>
      </div>
    </div>
  `;
  container.appendChild(newRow);
  const serviceSelect = newRow.querySelector(".serviceSelect");
  Object.keys(packages).forEach(program => {
    let option = document.createElement("option");
    option.value = program;
    option.textContent = program;
    serviceSelect.appendChild(option);
  });
});

document.addEventListener("click", function(e){
  if(e.target.classList.contains("removeServiceBtn")){
    const allRows = document.querySelectorAll(".service-row");
    if(allRows.length > 1){
      e.target.closest(".service-row").remove();
      computeTotal();
    } else {
      alert("At least one service is required.");
    }
  }
});

// SERVICE CHANGE → populate packages
document.addEventListener("change", function (e) {
  if (e.target.classList.contains("serviceSelect")) {
    const selectedService = e.target.value;
    const row = e.target.closest(".service-row");
    const packageContainer = row.querySelector(".package-container");

    packageContainer.innerHTML = `<label>Package</label>`;

    if (selectedService === "Academic Tutorial") {
      packageContainer.innerHTML += `
        <input type="number"
               name="package[]"
               placeholder="Input how many hours"
               min="1"
               required>
      `;
    } else {
      const select = document.createElement("select");
      select.name = "package[]";
      select.classList.add("packageSelect");

      let defaultOption = document.createElement("option");
      defaultOption.value = "";
      defaultOption.textContent = "Select package";
      select.appendChild(defaultOption);

      if (packages[selectedService]) {
        packages[selectedService].forEach(pkg => {
          let option = document.createElement("option");
          option.value = pkg.name;
          option.textContent = pkg.name;
          option.dataset.price = pkg.price;
          select.appendChild(option);
        });
      }

      packageContainer.appendChild(select);
    }
  }
});

// PACKAGE CHANGE → compute total
document.addEventListener("change", function(e) {
  if (e.target.classList.contains("packageSelect") || e.target.type === "number") {
    computeTotal();
  }
});

// COMPUTE TOTAL FUNCTION
function computeTotal() {
  let total = 0;

  // Sum selected packages
  document.querySelectorAll(".packageSelect").forEach(sel => {
    if(sel.selectedIndex > 0){
      total += parseFloat(sel.options[sel.selectedIndex].dataset.price || 0);
    }
  });

  // Sum Academic Tutorial hours (if any)
  document.querySelectorAll("input[type='number'][name='package[]']").forEach(input => {
    const hours = parseInt(input.value, 10);
    if(hours > 0){
      const ratePerHour = parseFloat(input.dataset.price || 0);
      total += ratePerHour * hours;
    }
  });

  // Update display and hidden input
  document.getElementById("totalAmount").textContent = "Total: ₱" + total.toFixed(2);
  document.getElementById("hiddenTotal").value = total.toFixed(2);
}
</script>

</body>
</html>