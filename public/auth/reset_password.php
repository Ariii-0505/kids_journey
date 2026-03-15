<?php
require_once __DIR__ . '/../../includes/config.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';
$success = false;
$error = '';

if (empty($token)) {
    redirect('forgot_password.php');
}

// Verify token (compare using UTC to avoid timezone mismatch)
$query = "SELECT * FROM password_reset_tokens WHERE token = ? AND expires_at > UTC_TIMESTAMP()";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Debug info: show the token that was received to help diagnose URL issues.
    $error = 'Invalid or expired reset token. (token: ' . htmlspecialchars($token) . ')';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($error)) {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if (empty($newPassword) || empty($confirmPassword)) {
        $error = 'Please fill in all fields.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $tokenData = $result->fetch_assoc();
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update user password
        $updateQuery = "UPDATE users SET password = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param('si', $hashedPassword, $tokenData['user_id']);
        
        if ($updateStmt->execute()) {
            // Delete used token
            $deleteQuery = "DELETE FROM password_reset_tokens WHERE token = ?";
            $deleteStmt = $conn->prepare($deleteQuery);
            $deleteStmt->bind_param('s', $token);
            $deleteStmt->execute();
            
            $success = true;
        } else {
            $error = 'Something went wrong. Please try again.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="signin-section">
    <div class="signin-container">
        <h1 data-testid="reset-password-title">Reset Password</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success" data-testid="success-message">
                Your password has been reset successfully! <a href="signin.php" style="color: #155724; font-weight: 600;">Click here to sign in</a>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="alert alert-error" data-testid="error-message">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" data-testid="reset-password-form">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="new_password" name="new_password" required data-testid="input-new-password">
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('new_password')" data-testid="toggle-new-password"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-input-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" required data-testid="input-confirm-password">
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password')" data-testid="toggle-confirm-password"></i>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit" data-testid="btn-submit">Reset Password</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>