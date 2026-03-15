<?php 
require_once(__DIR__ . "/../../includes/header.php");

$base_url = "/school-management-system/public"; // BASE URL
?>

<section class="signin-section">
    <div class="signin-container">
        <h1 data-testid="signin-title">Select Your Role</h1>
        <p style="text-align: center; color: #666; margin-bottom: 2rem;">
            Please select your role to sign in
        </p>
        
        <div class="role-buttons">
            <a href="<?= $base_url ?>/auth/login.php?role=Admin" 
               class="role-btn">
                <i class="fas fa-user-shield"></i> Admin
            </a>

            <a href="<?= $base_url ?>/auth/login.php?role=Human Resources" 
               class="role-btn">
                <i class="fas fa-users-cog"></i> Human Resources
            </a>

            <a href="<?= $base_url ?>/auth/login.php?role=Educator" 
               class="role-btn">
                <i class="fas fa-chalkboard-teacher"></i> Educator
            </a>
        </div>
    </div>
</section>

<?php
require_once(__DIR__ . "/../../includes/footer.php");
?>

