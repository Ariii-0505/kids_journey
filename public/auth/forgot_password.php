<?php
require_once __DIR__ . '/../../includes/config.php';

// Include PHPMailer classes (assuming using Composer autoload)
// If not using Composer, include PHPMailer files manually
require_once __DIR__ . '/../../includes/EmailHelper.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email']);
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        // Check if email exists
        $query = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            // Store token expiry in UTC to avoid timezone mismatches between PHP and MySQL
            $expiresAt = gmdate('Y-m-d H:i:s', time() + 3600);
            
            // Delete old tokens for this user
            $deleteQuery = "DELETE FROM password_reset_tokens WHERE user_id = ?";
            $deleteStmt = $conn->prepare($deleteQuery);
            $deleteStmt->bind_param('i', $user['id']);
            $deleteStmt->execute();
            
            // Insert new token
            $insertQuery = "INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)";
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bind_param('iss', $user['id'], $token, $expiresAt);
            $insertStmt->execute();
            
            // Generate reset link
            $resetLink = SITE_URL . BASE_URL . '/auth/reset_password.php?token=' . $token;
            
            // Get username from user data
            $username = !empty($user['username']) ? $user['username'] : 
                        (!empty($user['first_name']) ? $user['first_name'] : 'User');
            
            // Send password reset email via SMTP
            $emailSent = sendPasswordResetEmail($email, $username, $resetLink);
            
            if ($emailSent) {
                $success = true;
                // Clear any sensitive data
                unset($token, $resetLink);
            } else {
                $error = 'Failed to send reset email. Please try again later or contact support.';
                // Show the actual error for debugging
                if (isset($_SESSION['email_error'])) {
                    $error .= '<br><small style="color: #666;">Error: ' . htmlspecialchars($_SESSION['email_error']) . '</small>';
                    unset($_SESSION['email_error']);
                }
            }
        } else {
            $error = 'Email address not found.';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="signin-section">
    <div class="signin-container">
        <h1 data-testid="forgot-password-title">Forgot Password</h1>
        <p style="text-align: center; color: #666; margin-bottom: 2rem;">Enter your email to reset your password</p>
        
        <?php if ($success): ?>
            <div class="alert alert-success" data-testid="success-message">
                <p style="margin-bottom: 10px;"><strong>✅ Password reset link sent!</strong></p>
                <p style="font-size: 14px;">We've sent a password reset link to your email address. Please check your inbox (and spam folder) for the reset link.</p>
                <p style="font-size: 14px; margin-top: 10px;">The link will expire in 1 hour.</p>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error" data-testid="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" data-testid="forgot-password-form">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required data-testid="input-email">
            </div>
            
            <button type="submit" class="btn-submit" data-testid="btn-submit">Send Reset Link</button>
            
            <div style="text-align: center; margin-top: 1rem;">
                <a href="signin.php" style="color: #8B1A1A; text-decoration: none;" data-testid="link-back">Back to Sign In</a>
            </div>
        </form>
    </div>
</section>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>