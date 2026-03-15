<?php
if (!defined('DB_HOST')) {
    require_once 'config.php';
}
$base_url = "/school-management-system/public"; /*BASE URL*/
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?= dirname($base_url) ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Gloria+Hallelujah&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="logo">
                <a href="index.php">
                    <img src="<?= $base_url ?>/images/LOGOkj2.png" alt="Kid's Journey Logo">
                </a>
            </div>

            <ul class="nav-links">
                <li>
                    <a href="<?= $base_url ?>/index.php"
                       class="<?= ($current_page == 'index.php') ? 'active' : ''; ?>">
                       Home
                    </a>
                </li>

                <li>
                    <a href="<?= $base_url ?>/services.php"
                       class="<?= ($current_page == 'services.php') ? 'active' : ''; ?>">
                       Services
                    </a>
                </li>

                <li>
                    <a href="<?= $base_url ?>/about.php"
                       class="<?= ($current_page == 'about.php') ? 'active' : ''; ?>">
                       About Us
                    </a>
                </li>

                <li>
                    <a href="<?= $base_url ?>/contact.php"
                       class="<?= ($current_page == 'contact.php') ? 'active' : ''; ?>">
                       Contact Us
                    </a>
                </li>
            </ul>

            <!-- Auth Buttons -->
            <div class="auth-buttons">
                <a href="<?= $base_url ?>/auth/signin.php" class="btn-signin">
                    Sign In
                </a>
            </div>
        </nav>
    </header>
    <main>