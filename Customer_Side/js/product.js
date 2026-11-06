
// Handle product options, quantity controls, and add-to-cart behavior.
document.addEventListener("DOMContentLoaded", () => {
    const CART_KEY = "purpleBlendCart";
    const BUY_NOW_KEY = "purpleBlendBuyNow";
    const MAX_QUANTITY = 99;
    const inventoryById = new Map(
        (Array.isArray(window.purpleBlendProducts)
            ? window.purpleBlendProducts
            : []
        ).map(product => [String(product.id), Number(product.stock) || 0])
    );

    const cartPanel = document.getElementById("cartPanel");
    const cartOverlay = document.getElementById("cartOverlay");
    const cartItems = document.getElementById("cartItems");
    const cartCount = document.getElementById("cartCount");
    const cartTotal = document.getElementById("cartTotal");

    const openCartButton = document.getElementById("openCart");
    const closeCartButton = document.getElementById("closeCart");
    const checkoutButton = document.getElementById("checkoutButton");
    const clearCartButton = document.getElementById("clearCartButton");

    const quantityInput = document.getElementById("quantity");
    const decreaseQuantity = document.getElementById("decrease-quantity");
    const increaseQuantity = document.getElementById("increase-quantity");

    const productPrice = document.getElementById("product-price");
    const productStockLabel = document.getElementById("product-stock-label");
    const sizeVariantSelect = document.getElementById("size-variant-select");
    const subtotalElement = document.getElementById("subtotal");
    const addToCartButton = document.getElementById("add-to-cart");
    const cartMessage = document.getElementById("cart-message");

    let highlightedProductId = null;

    function formatPrice(price) {
        return `₱${Number(price).toFixed(2)}`;
    }

    function getCart() {
        try {
            const savedCart = JSON.parse(localStorage.getItem(CART_KEY));

            if (!Array.isArray(savedCart)) return [];

            return savedCart
                .filter(item =>
                    item &&
                    item.id !== undefined &&
                    item.name &&
                    Number.isFinite(Number(item.price)) &&
                    Number(item.price) >= 0
                )
                .map(item => ({
                    ...item,
                    id: String(item.id),
                    price: Number(item.price),
                    quantity: Math.min(
                        MAX_QUANTITY,
                        Math.max(1, Math.floor(Number(item.quantity) || 1))
                    )
                }));
        } catch (error) {
            return [];
        }
    }

    let cart = getCart();

    function saveCart() {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
    }

    function getAvailableStock(productId) {
        const baseId = String(productId)
            .replace(/-(?:with|without)-rice$/, "");
        return inventoryById.get(baseId) || 0;
    }

    function getOtherRequestedQuantity(item) {
        const baseId = String(item.id).replace(/-(?:with|without)-rice$/, "");
        return cart.reduce((sum, otherItem) => {
            return otherItem === item ||
                String(otherItem.id).replace(/-(?:with|without)-rice$/, "") !== baseId
                ? sum
                : sum + Number(otherItem.quantity);
        }, 0);
    }

    function escapeHTML(value) {
        return String(value).replace(/[&<>"']/g, character => ({
            "&": "&amp;",
            "<": "&lt;",
            ">": "&gt;",
            '"': "&quot;",
            "'": "&#039;"
        })[character]);
    }

    function clearProductHighlight() {
        highlightedProductId = null;

        if (!cartItems) return;

        cartItems.querySelectorAll(".cart-item-highlight").forEach(item => {
            item.classList.remove("cart-item-highlight");
        });
    }

    function addHighlightStyles() {
        if (document.getElementById("cartHighlightStyles")) return;

        const style = document.createElement("style");
        style.id = "cartHighlightStyles";

        style.textContent = `
            .cart-item-highlight {
                background: #F0DDFB !important;
                box-shadow: inset 0 0 0 2px #6F2B91;
                border-radius: 10px;
                animation: purpleBlendCartPulse 0.7s ease-in-out 2;
                scroll-margin-block: 100px;
            }

            @keyframes purpleBlendCartPulse {
                0%, 100% {
                    box-shadow: inset 0 0 0 2px rgba(111, 43, 145, 0.75);
                }
                50% {
                    box-shadow:
                        inset 0 0 0 2px #6F2B91,
                        inset 0 0 0 6px rgba(111, 43, 145, 0.25);
                }
            }
        `;

        document.head.appendChild(style);
    }

    function highlightCartProduct(productId) {
        if (!cartItems) return;

        clearProductHighlight();
        highlightedProductId = String(productId);

        const targetItem = Array.from(
            cartItems.querySelectorAll("[data-cart-item-id]")
        ).find(item => item.dataset.cartItemId === highlightedProductId);

        if (!targetItem) return;

        targetItem.classList.add("cart-item-highlight");

        targetItem.scrollIntoView({
            behavior: "smooth",
            block: "center"
        });
    }

    function updateCartDisplay() {
        const remainingStockByProduct = new Map();
        cart = cart.reduce((availableItems, item) => {
            const baseId = String(item.id).replace(/-(?:with|without)-rice$/, "");
            const stock = getAvailableStock(item.id);
            const remainingStock = remainingStockByProduct.has(baseId)
                ? remainingStockByProduct.get(baseId)
                : stock;
            const quantity = Math.min(Number(item.quantity), remainingStock);

            if (quantity > 0) {
                availableItems.push({
                    ...item,
                    quantity: quantity,
                    stock: stock
                });
                remainingStockByProduct.set(baseId, remainingStock - quantity);
            }

            return availableItems;
        }, []);
        saveCart();

        const requestedByProduct = new Map();
        cart.forEach(item => {
            const baseId = String(item.id).replace(/-(?:with|without)-rice$/, "");
            requestedByProduct.set(
                baseId,
                (requestedByProduct.get(baseId) || 0) + Number(item.quantity)
            );
        });
        const hasInsufficientStock = Array.from(requestedByProduct.entries())
            .some(([baseId, quantity]) => quantity > (inventoryById.get(baseId) || 0));

        if (checkoutButton) {
            checkoutButton.disabled = cart.length === 0 || hasInsufficientStock;
        }

        if (cartCount) {
            // Count distinct products, not total units.
            cartCount.textContent = cart.length;
        }

        if (cartTotal) {
            const total = cart.reduce(
                (sum, item) => sum + item.price * item.quantity,
                0
            );

            cartTotal.textContent = formatPrice(total);
        }

        if (!cartItems) return;

        if (cart.length === 0) {
            highlightedProductId = null;
            cartItems.innerHTML = "<p>Your cart is empty.</p>";
            return;
        }

        cartItems.innerHTML = cart.map(item => {
            const id = escapeHTML(item.id);
            const name = escapeHTML(item.name);
            const quantity = item.quantity;
            const subtotal = item.price * quantity;
            const isHighlighted = String(item.id) === highlightedProductId;
            const stock = getAvailableStock(item.id);
            const availableForItem = Math.max(
                0,
                stock - getOtherRequestedQuantity(item)
            );

            return `
                <div
                    class="cart-item${isHighlighted ? " cart-item-highlight" : ""}"
                    data-cart-item-id="${id}"
                >
                    <div>
                        <h4>${name}</h4>
                        <p>${formatPrice(subtotal)}</p>
                        <button
                            type="button"
                            class="cart-item-remove"
                            data-remove-cart-id="${id}"
                            aria-label="Remove ${name} from cart"
                        >Remove</button>
                    </div>

                    <div class="quantity-controls">
                        <button
                            type="button"
                            aria-label="Decrease ${name} quantity"
                            data-cart-id="${id}"
                            data-change="-1"
                        >−</button>

                        <span>${quantity}</span>

                        <button
                            type="button"
                            aria-label="Increase ${name} quantity"
                            data-cart-id="${id}"
                            data-change="1"
                            ${quantity >= Math.min(MAX_QUANTITY, availableForItem) ? "disabled" : ""}
                        >+</button>
                    </div>
                </div>
            `;
        }).join("");
    }

    // Show a notice when the product is already in the cart.
    function showDuplicateNotice(productName, productId) {
        let notice = document.getElementById("duplicateCartNotice");

        if (!notice) {
            notice = document.createElement("section");
            notice.id = "duplicateCartNotice";
            notice.setAttribute("role", "alert");
            notice.setAttribute("aria-live", "assertive");

            Object.assign(notice.style, {
                position: "fixed",
                top: "20px",
                left: "50%",
                transform: "translateX(-50%)",
                width: "calc(100% - 32px)",
                maxWidth: "440px",
                padding: "20px",
                background: "#F7F0FB",
                color: "#3B164D",
                border: "2px solid #6F2B91",
                borderRadius: "14px",
                boxShadow: "0 8px 30px rgba(40, 15, 55, 0.25)",
                zIndex: "10000",
                boxSizing: "border-box",
                fontFamily: "inherit"
            });

            document.body.appendChild(notice);
        }

        notice.innerHTML = `
            <div style="display:flex; gap:12px; align-items:flex-start;">
                <div style="
                    flex-shrink:0;
                    width:36px;
                    height:36px;
                    border-radius:50%;
                    background:#6F2B91;
                    color:white;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    font-size:22px;
                    font-weight:bold;
                ">!</div>

                <div style="flex:1; min-width:0;">
                    <h3 style="margin:0 0 8px; font-size:18px;">
                        Already in your cart
                    </h3>

                    <p style="margin:0; line-height:1.5;">
                        <strong>${escapeHTML(productName)}</strong>
                        is already in your cart. To change how many you want,
                        adjust its quantity in the cart.
                    </p>
                </div>
            </div>

            <div style="
                display:flex;
                gap:10px;
                margin-top:18px;
                flex-wrap:wrap;
            ">
                <button
                    type="button"
                    id="duplicateViewCart"
                    style="
                        flex:1;
                        min-width:120px;
                        padding:11px 14px;
                        border:0;
                        border-radius:8px;
                        background:#6F2B91;
                        color:white;
                        font-weight:600;
                        cursor:pointer;
                    "
                >View Cart</button>

                <button
                    type="button"
                    id="duplicateDismiss"
                    style="
                        flex:1;
                        min-width:100px;
                        padding:11px 14px;
                        border:1px solid #6F2B91;
                        border-radius:8px;
                        background:white;
                        color:#4E176B;
                        font-weight:600;
                        cursor:pointer;
                    "
                >Got it</button>
            </div>
        `;

        notice.querySelector("#duplicateViewCart").addEventListener("click", () => {
            notice.remove();
            openCart();

            // Wait briefly for the cart panel to open before highlighting.
            setTimeout(() => {
                highlightCartProduct(productId);
            }, 350);
        });

        notice.querySelector("#duplicateDismiss").addEventListener("click", () => {
            notice.remove();
        });
    }

    function showMessage(message) {
        if (cartMessage) {
            cartMessage.textContent = message;
        }
    }

    function addItemToCart(item, quantity = 1) {
        quantity = Number(quantity);
        const stock = getAvailableStock(item.id);

        if (
            !Number.isInteger(quantity) ||
            quantity < 1 ||
            quantity > MAX_QUANTITY ||
            quantity + cart.reduce((sum, cartItem) => {
                const sameProduct = String(cartItem.id)
                    .replace(/-(?:with|without)-rice$/, "") ===
                    String(item.id).replace(/-(?:with|without)-rice$/, "");
                return sameProduct ? sum + Number(cartItem.quantity) : sum;
            }, 0) > stock
        ) {
            showMessage(stock > 0
                ? `Only ${stock} pieces are available. Choose a quantity from 1 to ${Math.min(MAX_QUANTITY, stock)}.`
                : "This product is currently out of stock.");
            return false;
        }

        const existingItem = cart.find(
            cartItem => String(cartItem.id) === String(item.id)
        );

        if (existingItem) {
            showDuplicateNotice(item.name, item.id);
            return false;
        }

        cart.push({
            id: String(item.id),
            name: item.name,
            price: Number(item.price),
            image: item.image,
            quantity: quantity,
            stock: stock
        });

        saveCart();
        updateCartDisplay();
        return true;
    }

    function changeCartQuantity(productId, change) {
        const item = cart.find(
            cartItem => String(cartItem.id) === String(productId)
        );

        if (!item) return;

        const newQuantity = item.quantity + Number(change);
        const stock = getAvailableStock(item.id);
        const availableForItem = stock - getOtherRequestedQuantity(item);

        if (newQuantity > MAX_QUANTITY) {
            alert(`Maximum quantity per item is ${MAX_QUANTITY}.`);
            return;
        }
        if (change > 0 && newQuantity > availableForItem) {
            alert(stock > 0
                ? `Only ${Math.max(0, availableForItem)} pieces of ${item.name} are available.`
                : `${item.name} is currently out of stock.`);
            return;
        }

        clearProductHighlight();

        if (newQuantity <= 0) {
            cart = cart.filter(
                cartItem => String(cartItem.id) !== String(productId)
            );
        } else {
            item.quantity = newQuantity;
        }

        saveCart();
        updateCartDisplay();
    }

    if (cartItems) {
        cartItems.addEventListener("click", event => {
            const removeButton = event.target.closest("[data-remove-cart-id]");
            if (removeButton) {
                const productId = removeButton.dataset.removeCartId;
                cart = cart.filter(
                    item => String(item.id) !== String(productId)
                );
                if (highlightedProductId === String(productId)) {
                    clearProductHighlight();
                }
                saveCart();
                updateCartDisplay();
                return;
            }

            const button = event.target.closest("[data-cart-id]");
            if (!button || button.disabled) return;

            changeCartQuantity(
                button.dataset.cartId,
                Number(button.dataset.change)
            );
        });
    }

    function openCart() {
        if (cartPanel) cartPanel.classList.add("open");
        if (cartOverlay) cartOverlay.classList.add("show");
    }

    function closeCart() {
        if (cartPanel) cartPanel.classList.remove("open");
        if (cartOverlay) cartOverlay.classList.remove("show");

        clearProductHighlight();
    }

    if (openCartButton) {
        openCartButton.addEventListener("click", openCart);
    }

    if (closeCartButton) {
        closeCartButton.addEventListener("click", closeCart);
    }

    if (cartOverlay) {
        cartOverlay.addEventListener("click", closeCart);
    }

    if (clearCartButton) {
        clearCartButton.addEventListener("click", () => {
            if (cart.length === 0) {
                showMessage("Your cart is already empty.");
                return;
            }

            if (!confirm("Are you sure you want to clear your cart?")) {
                return;
            }

            clearProductHighlight();
            cart = [];
            saveCart();
            updateCartDisplay();
        });
    }

    if (checkoutButton) {
        checkoutButton.addEventListener("click", () => {
            if (cart.length === 0) {
                alert("Your cart is empty. Please add an item first.");
                return;
            }

            window.location.href = "cart.php";
        });
    }

    function getSelectedRiceOption() {
        return document.querySelector(
            'input[name="rice-option"]:checked'
        );
    }

    function getProductQuantity() {
        if (!quantityInput) return 1;

        const quantity = Math.floor(Number(quantityInput.value));
        const stock = Number(addToCartButton?.dataset.stock) || 0;

        if (!Number.isFinite(quantity) || quantity < 1) {
            return 1;
        }

        return Math.min(MAX_QUANTITY, stock, quantity);
    }

    function updateQuantityLimit(stock) {
        if (!quantityInput) return;

        const limit = Math.min(MAX_QUANTITY, Math.max(0, stock));
        quantityInput.max = String(Math.max(1, limit));
        quantityInput.disabled = limit === 0;
        if (decreaseQuantity) decreaseQuantity.disabled = limit === 0;
        if (increaseQuantity) increaseQuantity.disabled = limit === 0;

        if (limit > 0 && Number(quantityInput.value) > limit) {
            quantityInput.value = String(limit);
        } else if (limit === 0) {
            quantityInput.value = "1";
        }
    }

    function updateProductPrice() {
        if (!addToCartButton) return;

        const riceOption = getSelectedRiceOption();
        const hasRiceOptions =
            addToCartButton.dataset.riceOption !== undefined &&
            document.querySelector('input[name="rice-option"]');

        let price = Number(addToCartButton.dataset.price);

        if (hasRiceOptions && riceOption) {
            price = Number(riceOption.dataset.price);
            addToCartButton.dataset.riceOption = riceOption.value;
        }

        const quantity = getProductQuantity();

        if (productPrice) {
            productPrice.textContent = formatPrice(price);
        }

        if (subtotalElement) {
            subtotalElement.textContent = formatPrice(price * quantity);
        }

        addToCartButton.dataset.price = String(price);
    }

    if (sizeVariantSelect && addToCartButton) {
        sizeVariantSelect.addEventListener("change", () => {
            const option = sizeVariantSelect.selectedOptions[0];
            if (!option) return;

            const stock = Number(option.dataset.stock);
            addToCartButton.dataset.id = option.value;
            addToCartButton.dataset.size = option.dataset.size;
            addToCartButton.dataset.price = option.dataset.price;
            addToCartButton.dataset.stock = String(stock);
            addToCartButton.disabled = stock < 1;
            addToCartButton.textContent = stock < 1
                ? "Out of Stock"
                : (addToCartButton.dataset.buyNow === "true"
                    ? "Continue to Checkout"
                    : "Add to Cart");
            updateQuantityLimit(stock);
            quantityInput.value = 1;

            if (productStockLabel) {
                productStockLabel.textContent = stock > 0
                    ? `In stock: ${stock}`
                    : "Currently out of stock";
            }

            updateProductPrice();
        });
    }

    if (quantityInput) {
        updateQuantityLimit(Number(addToCartButton?.dataset.stock) || 0);

        quantityInput.addEventListener("input", () => {
            let quantity = Number(quantityInput.value);
            const stock = Number(addToCartButton?.dataset.stock) || 0;

            if (quantity > Math.min(MAX_QUANTITY, stock)) {
                quantityInput.value = Math.min(MAX_QUANTITY, stock);
            } else if (quantity < 1 && quantityInput.value !== "") {
                quantityInput.value = 1;
            }

            updateProductPrice();
        });

        quantityInput.addEventListener("change", () => {
            quantityInput.value = getProductQuantity();
            updateProductPrice();
        });
    }

    if (decreaseQuantity) {
        decreaseQuantity.addEventListener("click", () => {
            if (!quantityInput) return;

            quantityInput.value = Math.max(
                1,
                getProductQuantity() - 1
            );

            updateProductPrice();
        });
    }

    if (increaseQuantity) {
        increaseQuantity.addEventListener("click", () => {
            const quantity = getProductQuantity();
            const stock = Number(addToCartButton?.dataset.stock) || 0;

            if (quantity >= Math.min(MAX_QUANTITY, stock)) {
                showMessage(stock > 0
                    ? `Only ${stock} pieces are available.`
                    : "This product is currently out of stock.");
                return;
            }

            if (quantityInput) {
                quantityInput.value = quantity + 1;
                updateProductPrice();
            }
        });
    }

    document.querySelectorAll('input[name="rice-option"]').forEach(option => {
        option.addEventListener("change", updateProductPrice);
    });

    if (addToCartButton) {
        addToCartButton.addEventListener("click", () => {
            const baseId = addToCartButton.dataset.id;
            const baseName = addToCartButton.dataset.name;
            const image = addToCartButton.dataset.image;
            const riceOption = getSelectedRiceOption();

            const hasRiceOptions =
                document.querySelector('input[name="rice-option"]') !== null;

            let itemId = baseId;
            let itemName = baseName;
            let price = Number(addToCartButton.dataset.price);
            const sizeLabel = addToCartButton.dataset.size;

            if (sizeLabel) {
                itemName += ` (${sizeLabel})`;
            }

            if (hasRiceOptions && riceOption) {
                const choice = riceOption.value;

                price = Number(riceOption.dataset.price);
                itemId = `${baseId}-${choice}-rice`;

                itemName = choice === "with"
                    ? `${baseName} (With Rice)`
                    : `${baseName} (Without Rice)`;
            }

            const isBuyNow = addToCartButton.dataset.buyNow === "true";

            const selectedItem = {
                id: itemId,
                name: itemName,
                price: price,
                image: image
            };

            const quantity = getProductQuantity();

            if (isBuyNow) {
                try {
                    sessionStorage.setItem(BUY_NOW_KEY, JSON.stringify([{
                        ...selectedItem,
                        quantity: quantity,
                        selected: true
                    }]));
                } catch (error) {
                    showMessage("Unable to prepare checkout. Please try again.");
                    return;
                }

                window.location.href = "checkout.php?mode=buy-now";
                return;
            }

            const added = addItemToCart(selectedItem, quantity);

            if (added) {
                showMessage(`${itemName} added to your cart.`);
                if (typeof window.animateProductToCart === "function") {
                    window.animateProductToCart(
                        document.querySelector(".product-image-container .product-image"),
                        image
                    );
                }
            }
        });
    }

    document.querySelectorAll(".related-add-button").forEach(button => {
        button.addEventListener("click", () => {
            const added = addItemToCart({
                id: button.dataset.id,
                name: button.dataset.name,
                price: Number(button.dataset.price),
                image: button.dataset.image
            });

            if (added) {
                showMessage(`${button.dataset.name} added to your cart.`);
                if (typeof window.animateProductToCart === "function") {
                    window.animateProductToCart(
                        button.closest(".related-card")?.querySelector(".related-image"),
                        button.dataset.image
                    );
                }
            }
        });
    });

    window.addEventListener("storage", event => {
        if (event.key === CART_KEY) {
            clearProductHighlight();
            cart = getCart();
            updateCartDisplay();
        }
    });

    addHighlightStyles();
    saveCart();
    updateProductPrice();
    updateCartDisplay();
});
