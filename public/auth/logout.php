<?php
// config.php already starts the session
require_once __DIR__ . '/../../includes/config.php';

// Unset all session variables
$_SESSION = [];

// Destroy session
session_destroy();

// Redirect to home page
redirect(BASE_URL . "/index.php");
?>
