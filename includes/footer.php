</main>

<footer class="footer">
    <div class="footer-content">
        <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
    </div>
</footer>

<script src="js/script.js"></script>

<script>
let slides = document.querySelectorAll(".about-slideshow .slide");
let currentSlide = 0;

setInterval(() => {
    if(slides.length > 0){
        slides[currentSlide].classList.remove("active");
        currentSlide = (currentSlide + 1) % slides.length;
        slides[currentSlide].classList.add("active");
    }
}, 3000);

function openModal(id) {
    document.getElementById(id).classList.add("show");
}

function closeModal(id) {
    document.getElementById(id).classList.remove("show");
}

window.onclick = function(event) {
    let modal = document.getElementById("teamModal");
    if (event.target === modal) {
        modal.classList.remove("show");
    }
}

/* ✅ PASSWORD SHOW/HIDE */
function togglePassword(id){
    const input = document.getElementById(id);

    if(input.type === "password"){
        input.type = "text";
    } else {
        input.type = "password";
    }
}


</script>

<script>
document.addEventListener("DOMContentLoaded", function(){

    const links = document.querySelectorAll("a");

    links.forEach(link => {

        const url = link.getAttribute("href");

        if(
            !url ||
            url.startsWith("#") ||
            url.startsWith("mailto:") ||
            url.startsWith("tel:") ||
            link.target === "_blank"
        ){
            return;
        }

        link.addEventListener("click", function(e){

            e.preventDefault();

            const wrapper = document.getElementById("page-wrapper");

            if(wrapper){
                wrapper.classList.add("page-exit");
            }

            setTimeout(() => {
                window.location.href = url;
            }, 400);

        });

    });

});
</script>

</body>
</html>