// Add an accessible side drawer for admin navigation on small screens.
const adminSidebar = document.querySelector(".sidebar");
const adminMain = document.querySelector(".main");

if (adminSidebar && adminMain) {
    adminSidebar.classList.add("admin-mobile-nav-drawer");

    const navigationBar = document.createElement("div");
    navigationBar.className = "admin-mobile-nav-bar";
    navigationBar.innerHTML = `
        <button
            type="button"
            class="admin-mobile-nav-toggle"
            aria-label="Open admin navigation"
            aria-expanded="false"
            aria-controls="admin-navigation"
        >
            <span aria-hidden="true">☰</span>
        </button>
        <span class="admin-mobile-nav-title">Admin Menu</span>
    `;

    const sidebarNav = adminSidebar.querySelector("nav");
    if (sidebarNav) {
        sidebarNav.id = "admin-navigation";
    }

    const closeButton = document.createElement("button");
    closeButton.type = "button";
    closeButton.className = "admin-mobile-nav-close";
    closeButton.setAttribute("aria-label", "Close admin navigation");
    closeButton.innerHTML = "&times;";
    adminSidebar.prepend(closeButton);

    const backdrop = document.createElement("button");
    backdrop.type = "button";
    backdrop.className = "admin-mobile-nav-backdrop";
    backdrop.setAttribute("aria-label", "Close admin navigation");
    backdrop.hidden = true;
    document.body.append(backdrop);
    adminMain.prepend(navigationBar);

    const toggleButton = navigationBar.querySelector(
        ".admin-mobile-nav-toggle"
    );

    function updateSidebarAccessibility(isOpen) {
        if (window.innerWidth <= 650) {
            adminSidebar.inert = !isOpen;
            adminSidebar.setAttribute("aria-hidden", String(!isOpen));
            return;
        }

        adminSidebar.inert = false;
        adminSidebar.removeAttribute("aria-hidden");
    }

    function closeAdminNavigation() {
        adminSidebar.classList.remove("is-open");
        backdrop.classList.remove("is-visible");
        backdrop.hidden = true;
        document.body.classList.remove("admin-mobile-nav-open");
        toggleButton.setAttribute("aria-expanded", "false");
        toggleButton.setAttribute("aria-label", "Open admin navigation");
        updateSidebarAccessibility(false);
    }

    function openAdminNavigation() {
        adminSidebar.classList.add("is-open");
        backdrop.hidden = false;
        backdrop.classList.add("is-visible");
        document.body.classList.add("admin-mobile-nav-open");
        toggleButton.setAttribute("aria-expanded", "true");
        toggleButton.setAttribute("aria-label", "Close admin navigation");
        updateSidebarAccessibility(true);
        closeButton.focus();
    }

    toggleButton.addEventListener("click", openAdminNavigation);
    closeButton.addEventListener("click", closeAdminNavigation);
    backdrop.addEventListener("click", closeAdminNavigation);

    adminSidebar.querySelectorAll("nav a").forEach(link => {
        link.addEventListener("click", closeAdminNavigation);
    });

    document.addEventListener("keydown", event => {
        if (event.key === "Escape") {
            closeAdminNavigation();
        }
    });

    window.addEventListener("resize", () => {
        if (window.innerWidth > 650) {
            closeAdminNavigation();
        } else if (!adminSidebar.classList.contains("is-open")) {
            updateSidebarAccessibility(false);
        }
    });

    updateSidebarAccessibility(false);
}
