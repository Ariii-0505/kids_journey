<?php
// =====================================================
// PRODUCTION CONFIG - Upload to Hostinger
// =====================================================
// Copy this content to config.php after updating values

// ✅ Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Base URL - UPDATE THIS to your domain
// Example: define('BASE_URL', '');  (for root domain)
// Example: define('BASE_URL', '/subfolder'); (if in subfolder)
define('BASE_URL', '');

// ✅ Database configuration - GET THESE FROM HOSTINGER
// Login to Hostinger → Websites → Advanced → PHP Database
define('DB_HOST', 'YOUR_DB_HOST');        // Usually: localhost or mysql.hostinger.com
define('DB_USER', 'YOUR_DB_USERNAME');    // Your Hostinger database username
define('DB_PASS', 'YOUR_DB_PASSWORD');    // Your Hostinger database password
define('DB_NAME', 'YOUR_DB_NAME');        // Your Hostinger database name

// ✅ SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_AUTH', true);
define('SMTP_USERNAME', 'kidsjourney20@gmail.com');
define('SMTP_PASSWORD', 'YOUR_GMAIL_APP_PASSWORD'); // Use Gmail App Password, not regular password
define('SMTP_FROM_EMAIL', 'kidsjourney20@gmail.com');
define('SITE_NAME', "Kid's Journey Learning Center");
define('SITE_URL', 'https://kidsjourneyrizal.com'); // UPDATE TO YOUR DOMAIN

// ✅ Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// =====================================================
// INCLUDE THE REST OF THE FUNCTIONS FROM config.php
// =====================================================
// Copy all the helper functions from your original config.php below this line
// (logActivity, isLoggedIn, hasRole, redirect, sanitize, getSetting, saveSetting, etc.)
