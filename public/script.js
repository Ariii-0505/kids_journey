/* ================================
   PAGE TRANSITION (FADE IN/OUT)
================================ */

document.addEventListener("DOMContentLoaded", function () {

    // Fade in when page loads
    const wrapper = document.getElementById("page-wrapper");
if (wrapper) wrapper.classList.add("fade-in");

    // Handle internal navigation links
    document.querySelectorAll("a").forEach(link => {
        const href = link.getAttribute("href");

        if (
            href &&
            !href.startsWith("#") &&
            !href.startsWith("mailto:") &&
            !href.startsWith("tel:") &&
            !link.hasAttribute("target")
        ) {
            link.addEventListener("click", function (e) {
                e.preventDefault();

                document.body.classList.remove("fade-in");
                if (wrapper) wrapper.classList.add("fade-out");

                setTimeout(() => {
                    window.location.href = href;
                }, 400); // Must match CSS transition time
            });
        }
    });

});


/* ================================
   MODAL FUNCTIONALITY
================================ */

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = 'auto';
    }
}

// Close modal when clicking outside
window.addEventListener("click", function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
        document.body.style.overflow = 'auto';
    }
});


/* ================================
   TOGGLE PASSWORD (FIXED VERSION)
================================ */

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;

    const wrapper = input.closest('.password-input-wrapper');
    const icon = wrapper ? wrapper.querySelector('.toggle-password') : null;

    if (input.type === "password") {
        input.type = "text";
        if (icon) {
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
        }
    } else {
        input.type = "password";
        if (icon) {
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        }
    }
}


  
/* ================================
   FORM VALIDATION
================================ */

function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    const inputs = form.querySelectorAll('input[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            isValid = false;
            input.style.borderColor = '#E74C3C';
        } else {
            input.style.borderColor = '#e0e0e0';
        }
    });
    
    return isValid;
}



/* ================================
   AUTO HIDE ALERTS
================================ */

window.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});



/* ================================
   SMOOTH SCROLL
================================ */

document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});

/* ================================
    PASSWORD MATCH VALIDATION
================================ */

const password = document.getElementById("password");
const confirmPassword = document.getElementById("confirm_password");
const message = document.getElementById("password-message");

if(password && confirmPassword){
    confirmPassword.addEventListener("keyup", function(){

        if(confirmPassword.value === ""){
            message.textContent = "";
        }
        else if(password.value === confirmPassword.value){
            message.textContent = "✔ Passwords match";
            message.style.color = "green";
        } 
        else {
            message.textContent = "❌ Passwords do not match";
            message.style.color = "red";
        }

    });
}

const faders = document.querySelectorAll(".fade-up");

const observer = new IntersectionObserver(entries => {
    entries.forEach(entry=>{
        if(entry.isIntersecting){
            entry.target.classList.add("show");
        }
    });
});

faders.forEach(el=>{
    observer.observe(el);
});
