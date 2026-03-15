<?php
/**
 * Email Helper Class using PHPMailer
 * 
 * This class handles sending emails via Gmail SMTP for the school management system.
 * 
 * @requires PHPMailer - Ensure PHPMailer is available
 */

// Load PHPMailer
require_once __DIR__ . '/phpmailer_loader.php';

class EmailHelper
{
    private $mailer;
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $fromEmail;
    private $fromName;
    private $smtpSecure;
    private $smtpAuth;

    /**
     * Constructor - Initialize SMTP settings
     */
    public function __construct()
    {
        // Load SMTP configuration from database (with config.php as fallback)
        $smtpSettings = getSMTPSettings();
        
        $this->smtpHost = $smtpSettings['host'];
        $this->smtpPort = $smtpSettings['port'];
        $this->smtpUsername = $smtpSettings['username'];
        $this->smtpPassword = $smtpSettings['password'];
        $this->fromEmail = $smtpSettings['from_email'];
        $this->fromName = $smtpSettings['from_name'];
        $this->smtpSecure = $smtpSettings['secure'];
        $this->smtpAuth = (bool)$smtpSettings['auth'];

        // Create PHPMailer instance
        // Use the namespaced class directly since PHPMailer is loaded manually
        $this->mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
    }

    /**
     * Initialize PHPMailer with SMTP settings
     * 
     * @throws \Exception
     */
    private function initMailer()
    {
        // Enable SMTP debugging (0 = off, 1 = client messages, 2 = client and server messages)
        $this->mailer->SMTPDebug = 0; // SMTP::DEBUG_OFF

        // Set mailer to use SMTP
        $this->mailer->isSMTP();

        // Enable SMTP authentication
        $this->mailer->SMTPAuth = $this->smtpAuth;

        // Set SMTP server settings
        $this->mailer->Host = $this->smtpHost;
        $this->mailer->Port = $this->smtpPort;
        $this->mailer->Username = $this->smtpUsername;
        $this->mailer->Password = $this->smtpPassword;

        // Set encryption type
        if ($this->smtpSecure === 'tls') {
            $this->mailer->SMTPSecure = 'tls'; // PHPMailer::ENCRYPTION_STARTTLS
        } elseif ($this->smtpSecure === 'ssl') {
            $this->mailer->SMTPSecure = 'ssl'; // PHPMailer::ENCRYPTION_SMTPS
        }

        // Ensure a valid From address
        $fromEmail = $this->fromEmail;
        if (empty($fromEmail) || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            // Prefer SMTP username as From if valid
            if (!empty($this->smtpUsername) && filter_var($this->smtpUsername, FILTER_VALIDATE_EMAIL)) {
                $fromEmail = $this->smtpUsername;
            } else {
                // Fallback for dev/testing environments
                $fromEmail = 'no-reply@example.com';
            }
        }

        // Set sender information
        $this->mailer->setFrom($fromEmail, $this->fromName);
    }

    /**
     * Send a password reset email
     * 
     * @param string $email Recipient email address
     * @param string $username Recipient username
     * @param string $resetLink Password reset link
     * @return bool True if email sent successfully, false otherwise
     */
    public function sendPasswordResetEmail($email, $username, $resetLink)
    {
        try {
            $this->initMailer();

            // Set recipient
            $this->mailer->addAddress($email, $username);

            // Set email subject
            $this->mailer->Subject = 'Password Reset Request - ' . $this->fromName;

            // Set email body (HTML)
            $this->mailer->isHTML(true);
            $this->mailer->Body = $this->getPasswordResetEmailTemplate($username, $resetLink);
            
            // Plain text alternative
            $this->mailer->AltBody = $this->getPasswordResetEmailTextTemplate($username, $resetLink);

            // Send the email
            $this->mailer->send();
            
            return true;
        } catch (\Exception $e) {
            // Log the full error for debugging
            $errorMsg = "Email sending failed: " . $this->mailer->ErrorInfo;
            error_log($errorMsg);
            
            // Store error in session for display (for debugging)
            if (!isset($_SESSION['email_error'])) {
                $_SESSION['email_error'] = $errorMsg;
            }
            
            return false;
        }
    }

    /**
     * Get HTML email template for password reset
     * 
     * @param string $username
     * @param string $resetLink
     * @return string
     */
    private function getPasswordResetEmailTemplate($username, $resetLink)
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Password Reset</title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="color: white; margin: 0;">Password Reset Request</h1>
            </div>
            
            <div style="background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;">
                <p>Hello <strong>' . htmlspecialchars($username) . '</strong>,</p>
                
                <p>We received a request to reset your password. Click the button below to create a new password:</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . htmlspecialchars($resetLink) . '" style="background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">Reset Your Password</a>
                </div>
                
                <p style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; border-radius: 4px;">
                    <strong>⚠️ Security Notice:</strong> This link will expire in 1 hour for your security.
                </p>
                
                <p>If you did not request a password reset, please ignore this email. Your password will remain unchanged.</p>
                
                <hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">
                
                <p style="font-size: 12px; color: #666;">
                    If the button above does not work, copy and paste this link into your browser:<br>
                    <a href="' . htmlspecialchars($resetLink) . '" style="color: #667eea;">' . htmlspecialchars($resetLink) . '</a>
                </p>
            </div>
            
            <div style="text-align: center; padding: 20px; color: #666; font-size: 12px;">
                <p>&copy; ' . date('Y') . ' ' . htmlspecialchars($this->fromName) . '. All rights reserved.</p>
            </div>
        </body>
        </html>
        ';
    }

    /**
     * Get plain text email template for password reset
     * 
     * @param string $username
     * @param string $resetLink
     * @return string
     */
    private function getPasswordResetEmailTextTemplate($username, $resetLink)
    {
        return "
Password Reset Request
======================

Hello {$username},

We received a request to reset your password. Copy and paste the link below into your browser to create a new password:

{$resetLink}

This link will expire in 1 hour for your security.

If you did not request a password reset, please ignore this email. Your password will remain unchanged.

© " . date('Y') . " " . $this->fromName . ". All rights reserved.
";
    }

    /**
     * Send a general email
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body HTML email body
     * @param string $altBody Plain text alternative (optional)
     * @return bool True if email sent successfully, false otherwise
     */
    public function sendEmail($to, $subject, $body, $altBody = '')
    {
        try {
            $this->initMailer();

            // Set recipient
            $this->mailer->addAddress($to);

            // Set email subject
            $this->mailer->Subject = $subject;

            // Set email body (HTML)
            $this->mailer->isHTML(true);
            $this->mailer->Body = $body;
            
            // Plain text alternative
            if (!empty($altBody)) {
                $this->mailer->AltBody = $altBody;
            }

            // Send the email
            $this->mailer->send();
            
            return true;
        } catch (\Exception $e) {
            // Log the full error for debugging
            $errorMsg = "Email sending failed: " . $this->mailer->ErrorInfo;
            error_log($errorMsg);
            
            // Store error in session for display (for debugging)
            if (!isset($_SESSION['email_error'])) {
                $_SESSION['email_error'] = $errorMsg;
            }
            
            return false;
        }
    }
}

/**
 * Helper function to send general email
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $body HTML body
 * @param string $altBody Plain text body (optional)
 * @return bool True if sent successfully
 */
function sendEmail($to, $subject, $body, $altBody = '')
{
    $emailHelper = new EmailHelper();
    return $emailHelper->sendEmail($to, $subject, $body, $altBody);
}
