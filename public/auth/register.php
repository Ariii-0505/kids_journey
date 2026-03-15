<?php
require_once __DIR__ . '/../../includes/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $username  = sanitize($_POST['username']);
    $email     = sanitize($_POST['email']);
    $password  = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role      = $_POST['role'];

    if (empty($full_name) || empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
    $error = "All fields are required.";
        }
        elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Username or Email already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            // Set status to 'pending' so Admin must approve before login
            $status = 'pending';
            $stmt = $conn->prepare("INSERT INTO users (full_name, username, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $full_name, $username, $email, $hashed_password, $role, $status);

            if ($stmt->execute()) {
                $success = "Please contact the Administrator to approve the request.";
            } else {
                $error = "Something went wrong.";
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="login-section register-section">
    <!-- Left Side -->
    <div class="login-left">
        <div class="login-left-content">
            <h1>Join <span style="color:#E74C3C;">The Kid's Journey</span></h1>
        </div>
    </div>

    <!-- Right Side (Form) -->
    <div class="login-right">
        <div class="login-form-container">
            <h2>Create Account</h2>

            <?php if($error) echo "<div class='alert alert-error'>$error</div>"; ?>
            <?php if($success) echo "<div class='alert alert-success'>$success</div>"; ?>

            <form method="POST">
                <div class="form-group">
                    <input type="text" name="full_name" placeholder="Full Name" required>
                </div>

                <div class="form-group">
                    <input type="text" name="username" placeholder="Username" required>
                </div>

                <div class="form-group">
                    <input type="email" name="email" placeholder="Email" required>
                </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-input-wrapper">
                    <input type="password" id="password" name="password" placeholder="Password" required>
                    <i class="fa fa-eye toggle-password" onclick="togglePassword('password')"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-input-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
                    <i class="fa fa-eye toggle-password" onclick="togglePassword('confirm_password')"></i>
                </div>
            </div>

                <!-- ✅ Updated Role Dropdown -->
                <div class="form-group">
                    <select name="role" required>
                        <option value="">Select Role</option>
                        <option value="Admin">Admin</option>
                        <option value="Human Resources">Human Resources</option>
                        <option value="Educator">Educator</option>
                    </select>
                </div>

                <button type="submit" class="btn-primary">Create Account</button>
            </form>
        </div>
    </div>
</div>

<?php
require_once(__DIR__ . "/../../includes/footer.php");
?>
