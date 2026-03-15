<?php
require_once(__DIR__ . "/../includes/header.php");
?>

<main>
<section class="services-section" id="page1">

<div class="shape circle"></div>
<div class="shape circle small"></div>
<div class="shape square"></div>
<div class="shape square small"></div>
<div class="shape triangle"></div>
<div class="shape triangle small"></div>

<h1 class="section-title"><span class="highlight">Services</span> Provided</h1>
    <div class="title-underline"></div>

    <div class="services-grid">
        <!-- Card 1 -->
        <div class="service-card" onclick="openService('sensory')">
            <div class="service-icon"><i class="fas fa-brain"></i></div>
            <h3>Sensory Motor Learning</h3>
            <p>A learning process where the senses and body work together to improve movement through practice and experience.</p>
            <p class = "C1"><i class="	fas fa-caret-right"></i> Click for more information</p>
        </div>

        <!-- Card 2 -->
        <div class="service-card" onclick="openService('speech')">
            <div class="service-icon"><i class="fas fa-comments"></i></div>
            <h3>Speech Language Learning</h3>
            <p>The process of developing communication abilities by understanding, producing, and organizing spoken language through listening, practice, and interaction.</p>
            <p class = "C2"><i class="	fas fa-caret-right"></i> Click for more information</p>
        </div>

        <!-- Card 3 -->
        <div class="service-card" onclick="openService('aba')">
            <div class="service-icon"><i class="fas fa-puzzle-piece"></i></div>
            <h3>Applied Behavioral Analysis</h3>
            <p>A method that focuses on improving behavior by encouraging positive actions and reducing challenging ones through guided learning.</p>
            <p class = "C3"><i class="	fas fa-caret-right"></i> Click for more information</p>
        </div>
    </div>

    <div class="pagination">
        <button onclick="showPage(1)" disabled>&lt; Previous</button>
        <span class="current-page">1</span>
        <button onclick="showPage(2)">Next &gt;</button>
    </div>
</section>

<!-- PAGE 2 -->
<section class="services-section" id="page2" style="display:none;">

<div class="shape circle"></div>
<div class="shape circle small"></div>
<div class="shape square"></div>
<div class="shape square small"></div>
<div class="shape triangle"></div>
<div class="shape triangle small"></div>

<h1 class="section-title"><span class="highlight">Services</span> Provided</h1>
    <div class="title-underline"></div>

    <div class="services-grid">
        <!-- Card 4 -->
        <div class="service-card" onclick="openService('transition')">
            <div class="service-icon"><i class="fas fa-user-graduate"></i></div>
            <h3>Transition & Independent Living</h3>
            <p>Supporting individuals to develop life skills and independence for adulthood.</p>
            <p class = "C4"><i class="	fas fa-caret-right"></i> Click for more information</p>
        </div>

        <!-- Card 5 -->
        <div class="service-card" onclick="openService('academic')">
            <div class="service-icon"><i class="fas fa-book"></i></div>
            <h3>Academic Tutorial</h3>
            <p>A guided learning session that helps learners strengthen understanding of school subjects, improve skills, and build confidence through personalized instruction and practice.</p>
            <p class = "C5"><i class="	fas fa-caret-right"></i> Click for more information</p>
        </div>

        <!-- Card 6 -->
        <div class="service-card" onclick="openService('playgroup')">
            <div class="service-icon"><i class="fas fa-children"></i></div>
            <h3>Playgroup</h3>
            <p>Group activities that build social skills, cooperation, and confidence through play.</p>
            <p class = "C6"><i class="	fas fa-caret-right"></i> Click for more information</p>
        </div>
    </div>

    <div class="pagination">
        <button onclick="showPage(1)">&lt; Previous</button>
        <span class="current-page">2</span>
        <button disabled>Next &gt;</button>
    </div>

</section>
</main>

<!-- MODAL -->
<div id="serviceModal" class="service-modal">
    <div class="service-modal-content">
        <span class="close-modal" onclick="closeService()">&times;</span>
        <h2 id="modalTitle"></h2>
        <ul id="modalList"></ul>
    </div>
</div>

<script>
// Open Modal
/* ================================
SERVICES PAGE
================================ */

function showPage(page){

document.getElementById('page1').style.display = (page === 1) ? 'block' : 'none';
document.getElementById('page2').style.display = (page === 2) ? 'block' : 'none';

window.scrollTo({
top:0,
behavior:"smooth"
});

}

/* OPEN SERVICE MODAL */

function openService(service){

const modal = document.getElementById("serviceModal");
const title = document.getElementById("modalTitle");
const list = document.getElementById("modalList");

list.innerHTML = "";

let items = [];

if(service === "sensory"){
title.innerText = "Sensory Motor Learning";

items = [
"OT Assessment / One time payment",
"Package A / 3x a week: 2x with OT",
"Package B / 2x a week: 2x with OT",
"Package C / 4x a week: 2x with OT",
"Package D / Per Session with OT"
];
}

else if(service === "speech"){
title.innerText = "Speech Language Learning";

items = [
"SLP Assessment / One time payment",
"Package A / 1x a week: 2x with SLP",
"Package B / 2x a week: 2x with SLP"
];
}

else if(service === "aba"){
title.innerText = "Applied Behavioral Analysis";

items = [
"ABA Assessment / One time payment",
"Package / Once a week",
"Per session / Per 2 hours"
];
}

else if(service === "transition"){
title.innerText = "Transition & Independent Living";

items = [
"Package A / 1x a week: 2x with T.A",
"Package B / 2x a week: 2x with T.A"
];
}

else if(service === "academic"){
title.innerText = "Academic Tutorial";

items = [
"Personalized academic support"
];
}

else if(service === "playgroup"){
title.innerText = "Playgroup";

items = [
"4 times a week"
];
}

items.forEach(item => {

const li = document.createElement("li");
li.innerText = item;
list.appendChild(li);

});

modal.classList.add("show");

document.body.style.overflow = "hidden";

}

/* CLOSE MODAL */

function closeService(){

const modal = document.getElementById("serviceModal");

modal.classList.remove("show");

document.body.style.overflow = "auto";

}

/* CLICK OUTSIDE MODAL */

window.addEventListener("click", function(event){

const modal = document.getElementById("serviceModal");

if(event.target === modal){

closeService();

}

});
</script>

<?php require_once(__DIR__ . "/../includes/footer.php"); ?>