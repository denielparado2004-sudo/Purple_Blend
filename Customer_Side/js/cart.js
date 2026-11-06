
// Render and manage the standalone cart page using the saved browser cart.
document.addEventListener("DOMContentLoaded", function () {
    const cartItemsContainer = document.getElementById("cart-items");
    const emptyCart = document.getElementById("empty-cart");
    const itemCount = document.getElementById("cart-item-count");
    const subtotalElement = document.getElementById("cart-subtotal");
    const totalElement = document.getElementById("cart-total");
    const checkoutButton = document.getElementById("checkout-button");

    const CART_KEY = "purpleBlendCart";

    // Get current product information supplied by the homepage.
    const products = Array.isArray(window.purpleBlendProducts)
        ? window.purpleBlendProducts.map(function (product) {
            return {
                id: String(product.id),
                name: String(product.name),
                price: Number(product.price),
                stock: Number(product.stock) || 0
            };
        })
        : [];

    function getCart() {
        try {
            const cart = JSON.parse(localStorage.getItem(CART_KEY));
            return Array.isArray(cart) ? cart : [];
        } catch (error) {
            return [];
        }
    }

    function saveCart(cart) {
        localStorage.setItem(CART_KEY, JSON.stringify(cart));
    }

    function formatPrice(price) {
        return "₱" + Number(price).toFixed(2);
    }

    function escapeHTML(value) {
        return String(value ?? "").replace(/[&<>"']/g, function (character) {
            return {
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#039;"
            }[character];
        });
    }

    function getProduct(item) {
        const productId = String(item.id).replace(/-(?:with|without)-rice$/, "");
        return products.find(function (product) {
            return product.id === productId;
        });
    }

    function getBaseProductId(item) {
        return String(item.id).replace(/-(?:with|without)-rice$/, "");
    }

    function renderCart() {
        let cart = getCart().filter(function (item) {
            return item &&
                item.id !== undefined &&
                item.name &&
                Number.isFinite(Number(item.price)) &&
                Number(item.price) >= 0 &&
                Number.isFinite(Number(item.quantity)) &&
                Number(item.quantity) >= 1;
        });

        cart = cart.map(function (item) {
            const product = getProduct(item);
            const stock = product ? product.stock : 0;
            return {
                ...item,
                selected: item.selected !== false,
                quantity: Math.min(99, Math.floor(Number(item.quantity))),
                stock: stock
            };
        });

        saveCart(cart);

        const selectedItems = cart.filter(function (item) {
            return item.selected;
        });

        const allSelected = cart.length > 0 &&
            selectedItems.length === cart.length;
        const requestedByProduct = new Map();
        selectedItems.forEach(function (item) {
            const productId = getBaseProductId(item);
            requestedByProduct.set(
                productId,
                (requestedByProduct.get(productId) || 0) + item.quantity
            );
        });
        const hasInsufficientStock = selectedItems.some(function (item) {
            return requestedByProduct.get(getBaseProductId(item)) > item.stock;
        });

        let totalQuantity = 0;
        let subtotal = 0;

        cartItemsContainer.innerHTML = "";

        emptyCart.hidden = cart.length > 0;
        cartItemsContainer.hidden = cart.length === 0;
        checkoutButton.disabled =
            selectedItems.length === 0 || hasInsufficientStock;
        const stockMessage = document.getElementById("cart-stock-message");
        stockMessage.hidden = !hasInsufficientStock;
        stockMessage.textContent = hasInsufficientStock
            ? "Some selected items are no longer available in the requested quantity. Update or remove them before checkout."
            : "";

        if (cart.length > 0) {
            const selectAllContainer = document.createElement("div");
            selectAllContainer.className = "cart-select-all";

            selectAllContainer.innerHTML = `
                <label class="cart-select-all-label">
                    <input
                        type="checkbox"
                        id="selectAllCartItems"
                        class="cart-item-checkbox"
                        ${allSelected ? "checked" : ""}
                        aria-label="Select all cart items"
                    >
                    <span>${allSelected ? "Deselect all" : "Select all"}</span>
                </label>
            `;

            cartItemsContainer.appendChild(selectAllContainer);
        }

        cart.forEach(function (item) {
            const price = Number(item.price);
            const quantity = Number(item.quantity);
            const itemTotal = price * quantity;

            if (item.selected) {
                totalQuantity += quantity;
                subtotal += itemTotal;
            }

            const cartItem = document.createElement("div");
            cartItem.className = "cart-item";

            if (!item.selected) {
                cartItem.classList.add("cart-item-unselected");
            }

            const image = escapeHTML(item.image || "");
            const name = escapeHTML(item.name);
            const id = escapeHTML(item.id);
            const product = getProduct(item);
            const stock = product ? product.stock : 0;
            const otherRequested = cart.reduce(function (sum, otherItem) {
                return otherItem === item ||
                    getBaseProductId(otherItem) !== getBaseProductId(item)
                    ? sum
                    : sum + Number(otherItem.quantity);
            }, 0);
            const itemStockLimit = Math.max(0, stock - otherRequested);

            cartItem.innerHTML = `
                <label class="cart-item-select">
                    <input
                        type="checkbox"
                        class="cart-item-checkbox"
                        data-action="select"
                        data-id="${id}"
                        ${item.selected ? "checked" : ""}
                        aria-label="Select ${name} for checkout"
                    >
                </label>

                <img
                    src="${image}"
                    alt="${name}"
                    class="cart-item-image"
                    onerror="this.onerror=null;this.style.display='none';"
                >

                <div class="cart-item-info">
                    <h3>${name}</h3>
                    <p class="cart-item-price">${formatPrice(price)}</p>
                    <p class="cart-item-stock${stock < 1 ? " cart-item-out-of-stock" : ""}">
                        ${stock > 0 ? `${stock} available` : "Out of stock"}
                        ${quantity > stock
                            ? "<span>Reduce the quantity or remove this item to continue.</span>"
                            : ""}
                    </p>

                    <div class="cart-item-controls">
                        <div class="quantity-control">
                            <button
                                type="button"
                                data-action="decrease"
                                data-id="${id}"
                                aria-label="Decrease ${name} quantity"
                            >−</button>

                            <span>${quantity}</span>

                            <button
                                type="button"
                                data-action="increase"
                                data-id="${id}"
                                aria-label="Increase ${name} quantity"
                                ${quantity >= itemStockLimit || stock <= 0 ? "disabled" : ""}
                            >+</button>
                        </div>

                        <button
                            type="button"
                            class="remove-item-button"
                            data-action="remove"
                            data-id="${id}"
                        >Remove</button>
                    </div>
                </div>

                <div class="cart-item-total">${formatPrice(itemTotal)}</div>
            `;

            cartItemsContainer.appendChild(cartItem);
        });

        itemCount.textContent = totalQuantity;
        subtotalElement.textContent = formatPrice(subtotal);
        totalElement.textContent = formatPrice(subtotal);
    }

    cartItemsContainer.addEventListener("change", function (event) {
        const selectAllCheckbox = event.target.closest("#selectAllCartItems");

        if (selectAllCheckbox) {
            const cart = getCart();

            cart.forEach(function (item) {
                item.selected = selectAllCheckbox.checked;
            });

            saveCart(cart);
            renderCart();
            return;
        }

        const checkbox = event.target.closest('input[data-action="select"]');
        if (!checkbox) return;

        const cart = getCart();
        const item = cart.find(function (product) {
            return String(product.id) === checkbox.dataset.id;
        });

        if (!item) return;

        item.selected = checkbox.checked;
        saveCart(cart);
        renderCart();
    });

    cartItemsContainer.addEventListener("click", function (event) {
        const button = event.target.closest("button[data-action]");
        if (!button) return;

        const action = button.dataset.action;
        const id = button.dataset.id;
        let cart = getCart();

        if (action === "remove") {
            cart = cart.filter(function (item) {
                return String(item.id) !== id;
            });
        } else {
            const item = cart.find(function (product) {
                return String(product.id) === id;
            });

            if (!item) return;

            if (action === "increase") {
                const product = getProduct(item);
                const stock = product ? product.stock : 0;
                const otherRequested = cart.reduce(function (sum, otherItem) {
                    return otherItem === item ||
                        getBaseProductId(otherItem) !== getBaseProductId(item)
                        ? sum
                        : sum + Number(otherItem.quantity);
                }, 0);

                if (!Number.isFinite(stock) || stock <= 0) {
                    alert("Stock information is unavailable or this product is out of stock. Please return to the menu and refresh.");
                    return;
                }

                if (Number(item.quantity) + otherRequested >= stock) {
                    alert(`Only ${Math.max(0, stock - otherRequested)} pieces of ${item.name} are available.`);
                    return;
                }

                item.stock = stock;
                item.quantity = Number(item.quantity) + 1;
            }

            if (action === "decrease") {
                item.quantity = Math.max(1, Number(item.quantity) - 1);
            }
        }

        saveCart(cart);
        renderCart();
    });

    checkoutButton.addEventListener("click", function () {
        const selectedItems = getCart().filter(function (item) {
            return item && item.selected !== false;
        });

        if (selectedItems.length === 0) {
            alert("Please select at least one item for checkout.");
            return;
        }

        window.location.href = "checkout.php";
    });

    window.addEventListener("storage", function (event) {
        if (event.key === CART_KEY) {
            renderCart();
        }
    });

    renderCart();
});
