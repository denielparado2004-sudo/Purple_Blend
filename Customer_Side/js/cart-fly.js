// Animate a product image toward the cart after an item is added.
(() => {
    let cartPulseTimer;

    window.animateProductToCart = function (sourceElement, imageUrl) {
        const cartButton = document.getElementById("openCart");
        if (!cartButton) return;

        if (
            window.matchMedia("(prefers-reduced-motion: reduce)").matches
        ) {
            return;
        }

        cartButton.classList.remove("cart-button--added");
        window.clearTimeout(cartPulseTimer);

        requestAnimationFrame(() => {
            cartButton.classList.add("cart-button--added");
            cartPulseTimer = window.setTimeout(() => {
                cartButton.classList.remove("cart-button--added");
            }, 550);
        });

        if (!(sourceElement instanceof Element) || !imageUrl) return;

        const sourceRect = sourceElement.getBoundingClientRect();
        const targetRect = cartButton.getBoundingClientRect();
        if (!sourceRect.width || !sourceRect.height || !targetRect.width) return;

        const image = document.createElement("img");
        image.className = "cart-fly-image";
        image.src = imageUrl;
        image.alt = "";
        image.setAttribute("aria-hidden", "true");

        const size = Math.min(82, Math.max(54, sourceRect.width * 0.34));
        const startX = sourceRect.left + (sourceRect.width - size) / 2;
        const startY = sourceRect.top + (sourceRect.height - size) / 2;
        const deltaX = targetRect.left + targetRect.width / 2 - (startX + size / 2);
        const deltaY = targetRect.top + targetRect.height / 2 - (startY + size / 2);

        image.style.left = `${startX}px`;
        image.style.top = `${startY}px`;
        image.style.width = `${size}px`;
        image.style.height = `${size}px`;
        document.body.appendChild(image);

        const animation = image.animate(
            [
                {
                    transform: "translate3d(0, 0, 0) scale(1) rotate(0deg)",
                    opacity: 1,
                    offset: 0
                },
                {
                    transform: `translate3d(${deltaX * 0.48}px, ${deltaY * 0.48 - 72}px, 0) scale(0.78) rotate(8deg)`,
                    opacity: 1,
                    offset: 0.52
                },
                {
                    transform: `translate3d(${deltaX}px, ${deltaY}px, 0) scale(0.12) rotate(18deg)`,
                    opacity: 0.15,
                    offset: 1
                }
            ],
            {
                duration: 680,
                easing: "cubic-bezier(0.22, 0.75, 0.3, 1)",
                fill: "forwards"
            }
        );

        animation.onfinish = () => image.remove();
        animation.oncancel = () => image.remove();
    };
})();
