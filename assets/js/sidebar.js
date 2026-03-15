function toggleSidebar() {
  const sidebar = document.getElementById("sidebar");
  const main = document.querySelector(".main");
  const toggleBtn = document.querySelector(".toggle-btn svg");
  
  sidebar.classList.toggle("collapsed");
  
  // Toggle margin class on main content
  if (main) {
    main.classList.toggle("sidebar-collapsed");
  }
  
  // Change icon based on collapsed state
  if (sidebar.classList.contains("collapsed")) {
    toggleBtn.innerHTML = '<path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>';
  } else {
    toggleBtn.innerHTML = '<path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>';
  }
  
  // Save state to localStorage
  localStorage.setItem('sidebarCollapsed', sidebar.classList.contains("collapsed"));
}

// Restore sidebar state on page load
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.getElementById("sidebar");
  const main = document.querySelector(".main");
  
  const savedState = localStorage.getItem('sidebarCollapsed');
  if (savedState === 'true') {
    sidebar.classList.add("collapsed");
    if (main) {
      main.classList.add("sidebar-collapsed");
    }
  }
});
