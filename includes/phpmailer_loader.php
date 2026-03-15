<?php
/**
 * PHPMailer Loader
 * 
 * This file loads PHPMailer for email functionality.
 * Supports both Composer and manual installation.
 */

// Check if PHPMailer is already loaded via Composer
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    return; // PHPMailer already loaded via Composer
}

// If manually installed PHPMailer exists
$phpmailerPath = __DIR__ . '/PHPMailer/src';

if (file_exists($phpmailerPath . '/PHPMailer.php')) {
    // Load PHPMailer classes in the correct order
    require_once $phpmailerPath . '/Exception.php';
    require_once $phpmailerPath . '/SMTP.php';
    require_once $phpmailerPath . '/PHPMailer.php';
    
    // Register custom autoloader for PHPMailer namespace
    spl_autoload_register(function ($class) use ($phpmailerPath) {
        // Only handle PHPMailer namespace
        $prefix = 'PHPMailer\\PHPMailer\\';
        $len = strlen($prefix);
        
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        
        $relativeClass = substr($class, $len);
        $file = $phpmailerPath . '/' . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require $file;
        }
    }, true, true);
} else {
    // PHPMailer not found - show helpful error message
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>PHPMailer Not Found</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 50px auto; line-height: 1.6; }
            .error-box { border: 1px solid #ddd; border-radius: 8px; padding: 20px; background: #fff5f5; }
            h2 { color: #c00; margin-top: 0; }
            h3 { color: #333; }
            pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
            ol { line-height: 1.8; }
            a { color: #667eea; }
            .note { background: #fff3cd; padding: 15px; border-radius: 5px; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h2>⚠️ PHPMailer Not Found</h2>
            <p>To send password reset emails, PHPMailer needs to be installed.</p>
            
            <h3>Installation Instructions:</h3>
            
            <h4>Option 1: Using Composer (Recommended)</h4>
            <pre>composer require phpmailer/phpmailer</pre>
            
            <h4>Option 2: Manual Download (for XAMPP)</h4>
            <ol>
                <li>Download PHPMailer v6.8.0 from:<br>
                    <a href="https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.8.0.zip" target="_blank">https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.8.0.zip</a></li>
                <li>Extract the zip file</li>
                <li>Rename the extracted folder to "PHPMailer"</li>
                <li>Move the "PHPMailer" folder to: <code><?php echo __DIR__; ?>/PHPMailer/</code></li>
            </ol>
            
            <div class="note">
                <strong>📝 After installation, configure your Gmail settings in:</strong><br>
                <code>includes/config.php</code>
            </div>
            
            <h3>Gmail SMTP Setup:</h3>
            <ol>
                <li>Enable 2-Factor Authentication on your Google account</li>
                <li>Go to: <a href="https://myaccount.google.com/apppasswords" target="_blank">Google App Passwords</a></li>
                <li>Generate a new App Password (select "Mail" as the app)</li>
                <li>Update <code>includes/config.php</code> with your Gmail address and App Password</li>
            </ol>
        </div>
    </body>
    </html>
    <?php
    exit;
}
