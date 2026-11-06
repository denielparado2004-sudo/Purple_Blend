
// Open and close the customer navigation drawer on small screens.
const menuToggle = document.querySelector(".mobile-menu-toggle");
const primaryNavigation = document.getElementById("primary-navigation");
const navigationBackdrop = document.querySelector(".mobile-nav-backdrop");
const navigationClose = document.querySelector(".mobile-nav-close");

if (menuToggle && primaryNavigation) {
    function closeNavigation() {
        menuToggle.setAttribute("aria-expanded", "false");
        menuToggle.setAttribute("aria-label", "Open navigation menu");
        primaryNavigation.classList.remove("is-open");
        if (navigationBackdrop) {
            navigationBackdrop.hidden = true;
        }
    }

    menuToggle.addEventListener("click", function () {
        const isExpanded =
            menuToggle.getAttribute("aria-expanded") === "true";

        menuToggle.setAttribute("aria-expanded", String(!isExpanded));
        menuToggle.setAttribute(
            "aria-label",
            isExpanded ? "Open navigation menu" : "Close navigation menu"
        );

        primaryNavigation.classList.toggle("is-open", !isExpanded);
        if (navigationBackdrop) {
            navigationBackdrop.hidden = isExpanded;
        }
    });

    if (navigationClose) {
        navigationClose.addEventListener("click", closeNavigation);
    }

    if (navigationBackdrop) {
        navigationBackdrop.addEventListener("click", closeNavigation);
    }

    primaryNavigation.querySelectorAll("a").forEach(function (link) {
        link.addEventListener("click", closeNavigation);
    });

    document.addEventListener("click", function (event) {
        const clickedInsideNavigation =
            primaryNavigation.contains(event.target) ||
            menuToggle.contains(event.target);

        if (!clickedInsideNavigation) {
            closeNavigation();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeNavigation();
        }
    });
}
