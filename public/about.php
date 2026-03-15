<?php
require_once(__DIR__ . "/../includes/header.php");
?>
<div id="page-wrapper" class="page-about">
<main>
<section class="about-section">

<!-- Floating Background Icons -->
<i class="bg-icon apple fas fa-apple-alt"></i>
<i class="bg-icon football fas fa-football-ball"></i>
<i class="bg-icon star fas fa-star"></i>

<i class="bg-icon apple small fas fa-apple-alt"></i>
<i class="bg-icon star small fas fa-star"></i>
<i class="bg-icon football small fas fa-football-ball"></i>

    <div class="about-left">
        <div class="about-slideshow">
            <img src="<?= $base_url ?>/images/GroupPic.jpg" class="slide active">
            <img src="<?= $base_url ?>/images/GroupPic2.jpg" class="slide">
        </div>
    </div>
    
    <div class="about-right">

        <div class="about-text-slider">

            <!-- SLIDE 1 -->
            <div class="about-slide active">
                <h1><span class="highlight">About</span> Us</h1>

                <p>
                    Come and meet the exceptional leaders, professionals, and educators of 
                    Kid's Journey Angono/Binangonan — serving with passion, leading with heart!
                </p>
            </div>

            <!-- SLIDE 2 -->
            <div class="about-slide">
                <h1>Our Mission</h1>

                <p>
                    To develop the full potential and abilities of each learner in a caring and 
                    supportive environment through individualized learning programs.
                </p>
            </div>

            <!-- SLIDE 3 -->
            <div class="about-slide">
                <h1>Our Vision</h1>

                <p>
                    To provide and cater an affordable yet high-quality education for learners of all abilities.
                </p>
            </div>

    <span class="about-prev">&#10094;</span>
    <span class="about-next">&#10095;</span>

</div>

        <!-- DROPDOWN BUTTON -->
        <div class="meet-dropdown">
            <button type="button" class="btn-meet" id="meetBtn">
                Meet Us ▾
            </button>

            <div class="dropdown-content" id="meetDropdown">
                <a href="leaders.php">Leaders</a>
                <a href="professionals.php">Professionals</a>
                <a href="educators.php">Educators</a>
            </div>
        </div>

    </div>

</section>
</main>


<!-- IMAGE SLIDESHOW -->
<script>
let slides = document.querySelectorAll(".slide");
let index = 0;

setInterval(() => {
    slides[index].classList.remove("active");
    index = (index + 1) % slides.length;
    slides[index].classList.add("active");
}, 3000);
</script>


<!-- ABOUT TEXT SLIDER -->
<script>

document.addEventListener("DOMContentLoaded", function(){

    const aboutSlides = document.querySelectorAll(".about-slide");
    const nextBtn = document.querySelector(".about-next");
    const prevBtn = document.querySelector(".about-prev");

    let aboutIndex = 0;

    function showAboutSlide(i){

        aboutSlides.forEach(slide=>{
            slide.classList.remove("active");
        });

        aboutSlides[i].classList.add("active");
    }

    nextBtn.addEventListener("click", function(){

        aboutIndex++;

        if(aboutIndex >= aboutSlides.length){
            aboutIndex = 0;
        }

        showAboutSlide(aboutIndex);

    });

    prevBtn.addEventListener("click", function(){

        aboutIndex--;

        if(aboutIndex < 0){
            aboutIndex = aboutSlides.length - 1;
        }

        showAboutSlide(aboutIndex);

    });

});

</script>


<!-- DROPDOWN SCRIPT -->
<script>
const meetBtn = document.getElementById("meetBtn");
const dropdown = document.getElementById("meetDropdown");

meetBtn.addEventListener("click", function(e) {
    e.stopPropagation();
    dropdown.classList.toggle("show");
});

document.addEventListener("click", function(e) {
    if (!dropdown.contains(e.target)) {
        dropdown.classList.remove("show");
    }
});
</script>
</div>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>