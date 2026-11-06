// Show and dismiss the login reminder for unauthenticated customers.
document.addEventListener("DOMContentLoaded", function () {
    const header = document.querySelector("header[data-customer-authenticated]");
    const modal = document.getElementById("loginRequiredModal");
    const closeButton = document.getElementById("loginRequiredClose");
    const cancelButton = document.getElementById("loginRequiredCancel");

    if (
        !header ||
        header.dataset.customerAuthenticated === "true" ||
        !modal ||
        !closeButton ||
        !cancelButton
    ) {
        return;
    }

    let lastFocusedElement = null;

    function closeModal() {
        modal.hidden = true;
        if (lastFocusedElement instanceof HTMLElement) {
            lastFocusedElement.focus();
        }
    }

    document.addEventListener("click", function (event) {
        if (!(event.target instanceof Element)) return;

        const checkoutButton = event.target.closest("#checkout-button");
        if (!checkoutButton) return;

        let cart;
        try {
            cart = JSON.parse(localStorage.getItem("purpleBlendCart") || "[]");
        } catch (error) {
            console.error("Unable to read the cart before checkout.", error);
            return;
        }

        const hasSelectedItems = Array.isArray(cart) && cart.some(item =>
            item &&
            item.selected !== false &&
            Number(item.quantity) > 0
        );

        if (!hasSelectedItems) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        lastFocusedElement = checkoutButton;
        modal.hidden = false;
        closeButton.focus();
    }, true);

    closeButton.addEventListener("click", closeModal);
    cancelButton.addEventListener("click", closeModal);

    modal.addEventListener("click", function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && !modal.hidden) {
            closeModal();
        }
    });
});
