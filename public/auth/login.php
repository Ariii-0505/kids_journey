<?php
require_once __DIR__ . '/../../includes/config.php';

$role = isset($_GET['role']) ? $_GET['role'] : 'Admin'; // Default role
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username  = sanitize($_POST['username']);
    $password  = $_POST['password'];
    $loginRole = $_POST['role'];

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $query = "SELECT * FROM users WHERE username = ?";
        $stmt  = $conn->prepare($query);
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                // Check if account is pending approval
                if ($user['status'] === 'pending') {
                    $error = "Your account is pending approval. Please contact the Administrator.";
                } elseif ($user['status'] === 'suspended') {
                    $error = "Your account has been suspended. Please contact the Administrator.";
                } elseif ($user['role'] !== $loginRole) {
                    $error = "You are not authorized for this role.";
                } else {
                    // ✅ Session already active from config.php
                    $_SESSION['user_id']  = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role']     = $user['role'];

                    // ✅ Role-based dashboard mapping using BASE_URL
                    $dashboardMap = [
                        'Admin'           => BASE_URL . '/administrator/dashboard.php',
                        'Human Resources' => BASE_URL . '/hro/dashboard.php',
                        'Educator'        => BASE_URL . '/educator/educator-dashboard.php',
                    ];

                    if (isset($dashboardMap[$user['role']])) {
                        redirect($dashboardMap[$user['role']]);
                    } else {
                        $error = "Dashboard not configured for this role.";
                    }
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}


require_once(__DIR__ . "/../../includes/header.php");
?>


<div id="page-wrapper" class="page-login">
<section class="login-section">
<div class="login-left">

    <!-- Portrait image -->
   <img src="<?= $base_url ?>/images/LoginBG.png" class="login-portrait">

    <div class="login-left-content">
        <h2 class="chalky-title">Kid's Journey</h2>
        <p class="chalky-subtitle">Learning Center</p>
    </div>

</div>

    <div class="login-right">
        <div class="login-form-container">
    
            <!-- BACK ARROW -->
            <div class="back-arrow" onclick="history.back()">
                <i class="fa-solid 	fas fa-reply"></i>
            </div>
            <span class="role-badge" data-testid="role-badge"><?php echo htmlspecialchars($role); ?></span>
            <h2 data-testid="login-title">SIGN IN</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error" data-testid="error-message">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" data-testid="login-form">
                <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required data-testid="input-username">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="password" name="password" required data-testid="input-password">
                        <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('password')" data-testid="toggle-password"></i>
                    </div>
                </div>
                
                <div class="forgot-password">
                    <a href="forgot_password.php" data-testid="link-forgot-password">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn-submit" data-testid="btn-login">SIGN IN</button>
            </form>
        </div>
    </div>
</section>

<?php
require_once(__DIR__ . "/../../includes/footer.php");
?>