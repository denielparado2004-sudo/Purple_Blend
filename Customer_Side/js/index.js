
/* =========================================
   THE PURPLE BLEND - HOMEPAGE
   Products are loaded from MySQL through PHP.
========================================= */

// Base paths for the deployed customer-side pages and images.
// Clean customer page URLs and actual asset paths.
const PRODUCT_URL = "/product.php";
const CART_URL = "/cart.php";
const IMAGE_BASE_URL = "/Purple_Blend/Images";

// Menu products supplied by Customer_Side/index.php.
// Filter menu cards and coordinate the shared mini-cart and order search.
const products = Array.isArray(window.purpleBlendProducts)
    ? window.purpleBlendProducts.map(product => ({
        id: String(product.id),
        name: String(product.name),
        displayName: product.size_label
            ? `${String(product.name)} (${String(product.size_label)})`
            : String(product.name),
        size: String(product.size_label || ""),
        variantGroup: String(product.variant_group || ""),
        price: Number(product.price),
        category: String(product.category).trim(),
        image: product.image || "",
        stock: Number(product.stock) || 0
    }))
    : [];

let selectedCategory = "All";
const CART_KEY = "purpleBlendCart";

const productGrid = document.getElementById("productGrid");
const searchInput = document.getElementById("searchInput");
const cartPanel = document.getElementById("cartPanel");
const cartOverlay = document.getElementById("cartOverlay");
const cartCount = document.getElementById("cartCount");
const cartTotal = document.getElementById("cartTotal");
const cartItems = document.getElementById("cartItems");

// Load the shared cart.
function getSavedCart() {
    try {
        const savedCart = JSON.parse(localStorage.getItem(CART_KEY));
        return Array.isArray(savedCart) ? savedCart : [];
    } catch (error) {
        return [];
    }
}

let cart = getSavedCart();

function saveCart() {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
}

function getProductForCartItem(item) {
    const productId = String(item.id).replace(/-(?:with|without)-rice$/, "");
    return products.find(product => product.id === productId);
}

// Normalize category labels for reliable matching.
function normalizeCategory(value) {
    return String(value || "")
        .trim()
        .toLowerCase()
        .replace(/\s+/g, " ");
}

function equivalentCategory(left, right) {
    const leftValue = normalizeCategory(left);
    const rightValue = normalizeCategory(right);
    const donutLabels = ["donut", "donuts"];
    const beverageLabels = ["beverage", "drinks"];

    return leftValue === rightValue ||
        (donutLabels.includes(leftValue) && donutLabels.includes(rightValue)) ||
        (beverageLabels.includes(leftValue) && beverageLabels.includes(rightValue));
}

function displayCategoryLabel(category) {
    return equivalentCategory(category, "Beverage")
        ? "Beverage"
        : category;
}

// Match the menu's category names.
function categoryMatches(productCategory, selected) {
    if (normalizeCategory(selected) === "all") return true;

    const productValue = normalizeCategory(productCategory);
    const selectedValue = normalizeCategory(selected);

    // Support the old Meryenda / Dessert category name.
    if (selectedValue === "meryenda") {
        return productValue === "meryenda" ||
            productValue === "meryenda / dessert";
    }

    return equivalentCategory(productValue, selectedValue);
}

// Display products using the existing product-card design.
function displayProducts() {
    if (!productGrid || !searchInput) return;

    const searchText = searchInput.value.trim().toLowerCase();

    const filteredProducts = [];
    const seenVariantGroups = new Set();

    products.forEach(product => {
        if (product.variantGroup) {
            if (seenVariantGroups.has(product.variantGroup)) return;
            seenVariantGroups.add(product.variantGroup);

            const variants = products.filter(
                item => item.variantGroup === product.variantGroup
            ).sort((left, right) =>
                ["12 oz", "16 oz", "22 oz"].indexOf(left.size) -
                ["12 oz", "16 oz", "22 oz"].indexOf(right.size)
            );
            const matchesSearch = product.name.toLowerCase().includes(searchText) ||
                variants.some(variant =>
                    variant.size.toLowerCase().includes(searchText)
                );

            if (
                categoryMatches(product.category, selectedCategory) &&
                matchesSearch
            ) {
                filteredProducts.push({ ...product, variants });
            }
            return;
        }

        if (
            categoryMatches(product.category, selectedCategory) &&
            product.name.toLowerCase().includes(searchText)
        ) {
            filteredProducts.push(product);
        }
    });

    if (filteredProducts.length === 0) {
        if (products.length === 0) {
            productGrid.innerHTML =
                '<p class="empty-message">No products are available yet. Please check back later.</p>';
        } else if (selectedCategory !== "All" && !searchText) {
            productGrid.innerHTML =
                `<p class="empty-message">No items available in ${escapeHTML(selectedCategory)} yet.</p>`;
        } else {
            productGrid.innerHTML =
                '<p class="empty-message">No items found. Try another search.</p>';
        }

        return;
    }

    productGrid.innerHTML = filteredProducts.map(product => {
        const selectedVariant = product.variants
            ? (product.variants.find(variant => variant.stock > 0) || product.variants[0])
            : product;
        const productId = encodeURIComponent(selectedVariant.id);
        const productName = escapeHTML(product.name);
        const productCategory = escapeHTML(
            displayCategoryLabel(product.category)
        );
        const productImage = escapeHTML(product.image);
        const productPrice = selectedVariant.price.toFixed(2);
        const isInStock = selectedVariant.stock > 0;

        // Always point to the actual customer-side product page.
        const detailUrl = `${PRODUCT_URL}?id=${productId}`;

        const productImageMarkup = `
            <img class="product-image"
                 src="${productImage || 'https://placehold.co/500x350/F7F0FB/6F2B91?text=The+Purple+Blend'}"
                 alt="${productName}"
                 loading="lazy"
                 onerror="this.onerror=null;this.src='https://placehold.co/500x350/F7F0FB/6F2B91?text=The+Purple+Blend';">
        `;
        const variantPicker = product.variants
            ? `
                <label class="variant-picker-label">
                    Size
                    <select class="variant-size-select" aria-label="Choose ${productName} size">
                        ${product.variants.map(variant => `
                            <option
                                value="${escapeHTML(variant.id)}"
                                data-price="${variant.price}"
                                data-stock="${variant.stock}"
                                ${variant.id === selectedVariant.id ? "selected" : ""}
                            >${escapeHTML(variant.size)}</option>
                        `).join("")}
                    </select>
                </label>
            `
            : "";
        const initialStockNote = selectedVariant.stock > 0
            ? `Stock: ${selectedVariant.stock} available`
            : "Out of stock";

        return `
            <article class="product-card${isInStock ? "" : " is-out-of-stock"}"
                     data-detail-url="${isInStock ? detailUrl : ""}"
                     tabindex="${isInStock ? "0" : "-1"}"
                     aria-disabled="${isInStock ? "false" : "true"}"
                     aria-label="${isInStock ? `Open details for ${productName}` : `${productName} is out of stock`}">
                <a ${isInStock ? `href="${detailUrl}"` : 'aria-disabled="true" tabindex="-1"'}
                   class="product-detail-link"
                   aria-label="View details for ${productName}">${productImageMarkup}</a>

                <div class="product-info">
                    <span class="product-category">${productCategory}</span>

                    <h3>
                        <a ${isInStock ? `href="${detailUrl}"` : 'aria-disabled="true" tabindex="-1"'}
                           class="product-name-link">${productName}</a>
                    </h3>

                    ${variantPicker}

                    <p class="product-price">₱${productPrice}</p>

                    <p class="stock-note">${initialStockNote}</p>

                    <div class="product-actions">
                        <button class="buy-now-button"
                                type="button"
                                data-product-id="${escapeHTML(selectedVariant.id)}"
                                data-buy-now-product
                                ${isInStock ? "" : "disabled"}>
                            Buy Now
                        </button>

                        <button class="add-button"
                                type="button"
                                data-product-id="${escapeHTML(selectedVariant.id)}"
                                ${isInStock ? "" : "disabled"}
                                onclick="addToCart(this.dataset.productId, this)">
                            ${isInStock ? "+ Add to Cart" : "Out of Stock"}
                        </button>
                    </div>
                </div>
            </article>
        `;
    }).join("");
}

if (productGrid) {
    productGrid.addEventListener("change", event => {
        const sizeSelect = event.target.closest(".variant-size-select");
        if (!sizeSelect) return;

        const card = sizeSelect.closest(".product-card");
        const selectedOption = sizeSelect.selectedOptions[0];
        const variant = products.find(item => item.id === sizeSelect.value);
        if (!card || !selectedOption || !variant) return;

        const stock = Number(selectedOption.dataset.stock);
        const price = Number(selectedOption.dataset.price);
        const buyNowButton = card.querySelector("[data-buy-now-product]");
        const addButton = card.querySelector(".add-button");
        const productLink = card.querySelector(".product-name-link");
        const imageLink = card.querySelector(".product-detail-link");

        // Update the link whenever a customer changes the product size.
        const productUrl = `${PRODUCT_URL}?id=${encodeURIComponent(variant.id)}`;
        const isInStock = stock > 0;

        card.dataset.detailUrl = isInStock ? productUrl : "";
        card.classList.toggle("is-out-of-stock", !isInStock);
        card.tabIndex = isInStock ? 0 : -1;
        card.setAttribute("aria-disabled", String(!isInStock));
        card.setAttribute(
            "aria-label",
            isInStock
                ? `Open details for ${variant.name}`
                : `${variant.name} is out of stock`
        );
        card.querySelector(".product-price").textContent =
            `₱${price.toFixed(2)}`;
        card.querySelector(".stock-note").textContent = isInStock
            ? `Stock: ${stock} available`
            : "Out of stock";

        [buyNowButton, addButton].forEach(button => {
            button.dataset.productId = variant.id;
            button.disabled = !isInStock;
        });

        addButton.textContent = isInStock ? "+ Add to Cart" : "Out of Stock";
        [productLink, imageLink].forEach(link => {
            if (isInStock) {
                link.href = productUrl;
                link.removeAttribute("aria-disabled");
                link.removeAttribute("tabindex");
            } else {
                link.removeAttribute("href");
                link.setAttribute("aria-disabled", "true");
                link.tabIndex = -1;
            }
        });
    });

    productGrid.addEventListener("click", event => {
        const buyNowButton = event.target.closest("[data-buy-now-product]");
        if (buyNowButton) {
            if (buyNowButton.disabled) return;

            window.location.href =
                `${PRODUCT_URL}?id=${encodeURIComponent(buyNowButton.dataset.productId)}&action=buy`;
            return;
        }

        const card = event.target.closest(".product-card");
        if (
            !card ||
            card.getAttribute("aria-disabled") === "true" ||
            event.target.closest("a, button, select, input, [role='button']")
        ) {
            return;
        }

        window.location.href = card.dataset.detailUrl;
    });

    productGrid.addEventListener("keydown", event => {
        const card = event.target.closest(".product-card");
        if (
            card &&
            card.getAttribute("aria-disabled") !== "true" &&
            event.target === card &&
            event.key === "Enter"
        ) {
            event.preventDefault();
            window.location.href = card.dataset.detailUrl;
        }
    });
}

// Safely display database text in HTML.
function escapeHTML(value) {
    return String(value).replace(/[&<>"']/g, character => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;"
    })[character]);
}

// Escape product IDs used inside JavaScript event attributes.
function escapeJS(value) {
    return String(value)
        .replace(/\\/g, "\\\\")
        .replace(/'/g, "\\'")
        .replace(/"/g, '\\"')
        .replace(/</g, "\\x3C")
        .replace(/>/g, "\\x3E")
        .replace(/&/g, "\\x26")
        .replace(/\r/g, "\\r")
        .replace(/\n/g, "\\n");
}

// Activate a category button and refresh the products.
function selectCategory(category) {
    const matchingButton = Array.from(
        document.querySelectorAll(".category-button")
    ).find(button =>
        equivalentCategory(button.dataset.category, category)
    );

    if (!matchingButton) return false;

    document.querySelectorAll(".category-button").forEach(button => {
        button.classList.toggle("active", button === matchingButton);
    });

    selectedCategory = matchingButton.dataset.category || "All";
    document.querySelectorAll("[data-category-link]").forEach(link => {
        const isActive = equivalentCategory(
            link.dataset.categoryLink,
            selectedCategory
        );
        link.classList.toggle("active", isActive);

        if (isActive) {
            link.setAttribute("aria-current", "true");
        } else {
            link.removeAttribute("aria-current");
        }
    });

    displayProducts();

    return true;
}

// Category filters.
document.querySelectorAll(".category-button").forEach(button => {
    button.addEventListener("click", () => {
        selectCategory(button.dataset.category || "All");
    });
});

function filterOrders(searchText) {
    document.querySelectorAll(".orders-column").forEach(column => {
        const cards = Array.from(column.querySelectorAll(".order-card"));

        cards.forEach(card => {
            const matchesSearch = card.textContent
                .toLowerCase()
                .includes(searchText);
            let matchesPurchasedItem = false;

            card.querySelectorAll(".order-items li").forEach(item => {
                const itemName = item.querySelector("span");
                if (!itemName) {
                    return;
                }

                const name = itemName.textContent;
                const matchIndex = searchText
                    ? name.toLowerCase().indexOf(searchText)
                    : -1;
                const itemMatches = matchIndex !== -1;

                item.classList.toggle("order-item--search-match", itemMatches);
                matchesPurchasedItem = matchesPurchasedItem || itemMatches;

                if (!itemMatches) {
                    itemName.textContent = name;
                    return;
                }

                const fragment = document.createDocumentFragment();
                fragment.append(
                    document.createTextNode(name.slice(0, matchIndex))
                );

                const highlight = document.createElement("mark");
                highlight.className = "order-item-search-highlight";
                highlight.textContent = name.slice(
                    matchIndex,
                    matchIndex + searchText.length
                );
                fragment.append(highlight);
                fragment.append(
                    document.createTextNode(
                        name.slice(matchIndex + searchText.length)
                    )
                );
                itemName.replaceChildren(fragment);
            });

            card.hidden = !matchesSearch;
            card.classList.toggle(
                "order-card--search-match",
                matchesPurchasedItem
            );
        });

        const emptyMessage = column.querySelector(".orders-search-empty");
        if (emptyMessage) {
            emptyMessage.hidden = !searchText || cards.some(card => !card.hidden);
        }
    });

    sizeInProgressOrderList();
}

function updateActiveSearch() {
    if (!searchInput) {
        return;
    }

    const ordersSection = document.getElementById("myOrders");
    const searchingOrders = Boolean(ordersSection && !ordersSection.hidden);
    const searchText = searchInput.value.trim().toLowerCase();

    searchInput.placeholder = searchingOrders
        ? "Search purchased..."
        : "Search Ulam, Meryenda, Donut...";
    searchInput.setAttribute(
        "aria-label",
        searchingOrders ? "Search purchased..." : "Search menu items"
    );

    if (searchingOrders) {
        filterOrders(searchText);
    } else {
        displayProducts();
    }
}

// Search menu products or past orders depending on the active tab.
if (searchInput) {
    searchInput.addEventListener("input", updateActiveSearch);
}

// Add a product to the cart.
// Existing items must use the cart quantity controls.
function showCartFeedback(title, message) {
    document.getElementById("cartFeedbackToast")?.remove();

    const toast = document.createElement("div");
    toast.id = "cartFeedbackToast";
    toast.className = "toast error";
    toast.setAttribute("role", "alert");
    toast.setAttribute("aria-live", "assertive");

    const icon = document.createElement("div");
    icon.className = "toast-icon";
    icon.setAttribute("aria-hidden", "true");
    icon.textContent = "!";

    const content = document.createElement("div");
    content.className = "toast-content";

    const heading = document.createElement("strong");
    heading.textContent = title;

    const detail = document.createElement("p");
    detail.textContent = message;
    content.append(heading, detail);

    const closeButton = document.createElement("button");
    closeButton.type = "button";
    closeButton.setAttribute("aria-label", "Dismiss notification");
    closeButton.textContent = "×";
    closeButton.addEventListener("click", () => toast.remove());

    toast.append(icon, content, closeButton);
    document.body.appendChild(toast);
    window.setTimeout(() => {
        if (toast.isConnected) {
            toast.classList.add("hide");
            window.setTimeout(() => toast.remove(), 260);
        }
    }, 5000);
}

function addToCart(productId, button) {
    const product = products.find(
        item => item.id === String(productId)
    );

    if (!product) return;

    const existingItem = cart.find(
        item => String(item.id) === String(productId)
    );
    if (existingItem) {
        showCartFeedback(
            "Already in your cart",
            `${product.displayName} is already in your cart. Open your cart to change the quantity.`
        );
        return;
    }

    if (product.stock <= 0) {
        showCartFeedback("Out of stock", "This product is currently unavailable.");
        return;
    }

    const requestedForProduct = cart.reduce((sum, item) => {
        return String(item.id).replace(/-(?:with|without)-rice$/, "") ===
            String(product.id)
            ? sum + Number(item.quantity)
            : sum;
    }, 0);
    if (requestedForProduct >= product.stock) {
        showCartFeedback(
            "Stock limit reached",
            `Only ${product.stock} pieces of ${product.name} are available. Change the quantity in your cart.`
        );
        return;
    }

    cart.push({
        id: product.id,
        name: product.displayName,
        price: product.price,
        image: product.image,
        quantity: 1,
        stock: product.stock
    });

    saveCart();
    updateCart();

    // Briefly show one-time feedback such as the successful-login welcome message.
    const feedbackToast = document.getElementById("feedbackToast");
    if (feedbackToast) {
        window.setTimeout(() => {
            if (!feedbackToast.isConnected) return;

            feedbackToast.classList.add("hide");
            window.setTimeout(() => feedbackToast.remove(), 260);
        }, 4500);
    }

    const productImage = button
        ? button.closest(".product-card")?.querySelector(".product-image")
        : null;
    if (typeof window.animateProductToCart === "function") {
        window.animateProductToCart(productImage, product.image);
    }
}

// Update cart count, items, and total.
function updateCart() {
    cart = cart.filter(item =>
        item &&
        Number(item.quantity) > 0 &&
        Number.isFinite(Number(item.price))
    );

    const remainingStockByProduct = new Map();
    cart = cart.reduce((availableItems, item) => {
        const product = getProductForCartItem(item);
        const productId = String(item.id).replace(/-(?:with|without)-rice$/, "");
        const remainingStock = remainingStockByProduct.has(productId)
            ? remainingStockByProduct.get(productId)
            : (product ? product.stock : 0);
        const quantity = Math.min(
            Math.floor(Number(item.quantity)),
            remainingStock
        );

        if (quantity > 0 && product) {
            availableItems.push({
                ...item,
                quantity: quantity,
                stock: product.stock
            });
            remainingStockByProduct.set(productId, remainingStock - quantity);
        }

        return availableItems;
    }, []);
    saveCart();

    // Count unique products, not their quantities.
    const count = cart.length;

    const total = cart.reduce(
        (sum, item) =>
            sum + Number(item.price) * Number(item.quantity),
        0
    );
    const requestedByProduct = new Map();
    cart.forEach(item => {
        const productId = String(item.id).replace(/-(?:with|without)-rice$/, "");
        requestedByProduct.set(
            productId,
            (requestedByProduct.get(productId) || 0) + Number(item.quantity)
        );
    });
    const hasInsufficientStock = cart.some(item => {
        const product = getProductForCartItem(item);
        const productId = String(item.id).replace(/-(?:with|without)-rice$/, "");
        return !product ||
            requestedByProduct.get(productId) > product.stock;
    });

    if (cartCount) cartCount.textContent = count;
    if (cartTotal) cartTotal.textContent = `₱${total.toFixed(2)}`;
    if (checkoutButton) {
        checkoutButton.disabled = cart.length === 0 || hasInsufficientStock;
    }

    if (!cartItems) return;

    if (cart.length === 0) {
        cartItems.innerHTML = "<p>Your cart is empty.</p>";
        return;
    }

    cartItems.innerHTML = cart.map(item => {
        const product = getProductForCartItem(item);
        const productId = String(item.id).replace(/-(?:with|without)-rice$/, "");
        const otherRequested = cart.reduce((sum, otherItem) => {
            return otherItem === item ||
                String(otherItem.id).replace(/-(?:with|without)-rice$/, "") !== productId
                ? sum
                : sum + Number(otherItem.quantity);
        }, 0);
        const availableForItem = product
            ? Math.max(0, product.stock - otherRequested)
            : 0;

        return `
        <div class="cart-item">
            <div>
                <h4>${escapeHTML(item.name)}</h4>
                <p>₱${(
                    Number(item.price) * Number(item.quantity)
                ).toFixed(2)}</p>
                <button type="button"
                        class="cart-item-remove"
                        data-remove-cart-id="${escapeHTML(item.id)}"
                        aria-label="Remove ${escapeHTML(item.name)} from cart">
                    Remove
                </button>
            </div>

            <div class="quantity-controls">
                <button type="button"
                        aria-label="Decrease ${escapeHTML(item.name)} quantity"
                        onclick="changeQuantity('${escapeJS(item.id)}', -1)">−</button>

                <span>${Number(item.quantity)}</span>

                <button type="button"
                        aria-label="Increase ${escapeHTML(item.name)} quantity"
                        onclick="changeQuantity('${escapeJS(item.id)}', 1)"
                        ${Number(item.quantity) >= availableForItem ? "disabled" : ""}>+</button>
            </div>
        </div>
    `;
    }).join("");
}

// Change quantity or remove an item.
function changeQuantity(productId, change) {
    const item = cart.find(
        product => String(product.id) === String(productId)
    );

    if (!item) return;

    const product = getProductForCartItem(item);

    const newQuantity = Number(item.quantity) + change;
    const otherRequested = cart.reduce((sum, otherItem) => {
        return otherItem === item ||
            String(otherItem.id).replace(/-(?:with|without)-rice$/, "") !==
                String(item.id).replace(/-(?:with|without)-rice$/, "")
            ? sum
            : sum + Number(otherItem.quantity);
    }, 0);

    if (change > 0 && (!product || newQuantity + otherRequested > product.stock)) {
        alert(
            product
                ? `Only ${Math.max(0, product.stock - otherRequested)} pieces of ${product.name} are available.`
                : "This product is currently unavailable. Please remove it from your cart."
        );
        return;
    }
    if (product) item.stock = product.stock;

    if (newQuantity > 0) {
        item.quantity = newQuantity;
    } else {
        cart = cart.filter(
            product => String(product.id) !== String(productId)
        );
    }

    saveCart();
    updateCart();
}

if (cartItems) {
    cartItems.addEventListener("click", event => {
        const button = event.target.closest("[data-remove-cart-id]");
        if (!button) return;

        const productId = button.dataset.removeCartId;
        cart = cart.filter(
            item => String(item.id) !== String(productId)
        );
        saveCart();
        updateCart();
    });
}

// Open and close the mini-cart.
function openCart() {
    if (!cartPanel || !cartOverlay) return;

    updateCart();
    cartPanel.classList.add("open");
    cartOverlay.classList.add("show");
}

function closeCart() {
    if (cartPanel) cartPanel.classList.remove("open");
    if (cartOverlay) cartOverlay.classList.remove("show");
}

const openCartButton = document.getElementById("openCart");
const closeCartButton = document.getElementById("closeCart");
const checkoutButton = document.getElementById("checkoutButton");
const clearCartButton = document.getElementById("clearCartButton");

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
            alert("Your cart is already empty.");
            return;
        }

        if (!confirm("Are you sure you want to clear your cart?")) {
            return;
        }

        cart = [];
        saveCart();
        updateCart();
    });
}

if (checkoutButton) {
    checkoutButton.addEventListener("click", () => {
        if (cart.length === 0) {
            alert("Your cart is empty. Please add an item first.");
            return;
        }

        // Open the actual customer-side cart page.
        window.location.href = CART_URL;
    });
}

// Header category links.
// These activate the matching homepage category button.
document.querySelectorAll("[data-category-link]").forEach(link => {
    link.addEventListener("click", event => {
        const category = link.dataset.categoryLink;

        function navigateToCategory(selected) {
            if (!selectCategory(selected)) return false;

            switchPageTab("menu");
            event.preventDefault();
            history.replaceState(
                null,
                "",
                `?category=${encodeURIComponent(selectedCategory)}#menu`
            );
            document.getElementById("menu").scrollIntoView({
                behavior: "smooth",
                block: "start"
            });
            return true;
        }

        if (navigateToCategory(category)) return;

        // Support the old navbar category labels.
        const aliases = {
            "Food": "Ulam",
            "Desserts": "Meryenda"
        };

        const alternative = aliases[category];

        if (alternative && navigateToCategory(alternative)) {
            return;
        }
    });
});

// Sync cart changes from other tabs.
window.addEventListener("storage", event => {
    if (event.key === CART_KEY) {
        cart = getSavedCart();
        updateCart();
    }
});

/* =========================================
   HERO IMAGE CAROUSEL
========================================= */

const heroImages = [
    `${IMAGE_BASE_URL}/pic2.jpg`,
    `${IMAGE_BASE_URL}/pic3.jpg`,
    `${IMAGE_BASE_URL}/pic4.jpg`
];

let heroIndex = 0;
let heroTimer;
let heroAnimating = false;

const hero = document.getElementById("heroCarousel");
const heroDots = document.getElementById("heroDots");

if (hero && heroDots) {
    const firstSlide = document.createElement("div");
    firstSlide.className = "hero-slide-image";
    firstSlide.style.backgroundImage = `
        linear-gradient(90deg, rgba(45,15,50,.88), rgba(45,15,50,.25)),
        url("${heroImages[0]}")
    `;
    hero.prepend(firstSlide);

    function showHero(index, direction = 1) {
        if (heroAnimating) return;

        const nextIndex =
            (index + heroImages.length) % heroImages.length;

        if (nextIndex === heroIndex) return;

        const currentSlide = hero.querySelector(".hero-slide-image");
        if (!currentSlide) return;

        heroAnimating = true;

        const nextSlide = document.createElement("div");
        nextSlide.className = "hero-slide-image " +
            (direction > 0 ? "enter-next" : "enter-prev");

        nextSlide.style.backgroundImage = `
            linear-gradient(90deg, rgba(45,15,50,.88), rgba(45,15,50,.25)),
            url("${heroImages[nextIndex]}")
        `;

        hero.prepend(nextSlide);
        nextSlide.offsetWidth;

        requestAnimationFrame(() => {
            nextSlide.classList.remove("enter-next", "enter-prev");

            currentSlide.style.transform =
                direction > 0 ? "translateX(-100%)" : "translateX(100%)";

            nextSlide.style.transform = "translateX(0)";
        });

        setTimeout(() => {
            currentSlide.remove();
            nextSlide.style.transform = "";
            heroIndex = nextIndex;
            updateHeroDots();
            heroAnimating = false;
        }, 600);
    }

    function updateHeroDots() {
        heroDots.innerHTML = heroImages.map((_, index) => `
            <button type="button"
                    class="hero-dot ${index === heroIndex ? "active" : ""}"
                    aria-label="Show image ${index + 1}"
                    onclick="changeHeroTo(${index})"></button>
        `).join("");
    }

    function changeHero(direction) {
        if (heroAnimating) return;

        showHero(heroIndex + direction, direction);
        resetHeroTimer();
    }

    function changeHeroTo(index) {
        if (heroAnimating || index === heroIndex) return;

        showHero(index, index > heroIndex ? 1 : -1);
        resetHeroTimer();
    }

    function resetHeroTimer() {
        clearInterval(heroTimer);

        heroTimer = setInterval(() => {
            if (!heroAnimating) {
                showHero(heroIndex + 1, 1);
            }
        }, 3000);
    }

    window.changeHero = changeHero;
    window.changeHeroTo = changeHeroTo;

    updateHeroDots();
    resetHeroTimer();
}

/* =========================================
   HOMEPAGE TAB SWITCHING
========================================= */

function switchPageTab(tabName) {
    const sections = document.querySelectorAll(".page-section");
    const tabs = document.querySelectorAll(".page-tab");

    document.body.classList.toggle("orders-view", tabName === "myOrders");
    if (tabName === "myOrders") {
        window.scrollTo(0, 0);
    }

    sections.forEach(section => {
        const isActive = section.id === tabName;
        section.hidden = !isActive;
    });

    tabs.forEach(tab => {
        const isActive = tab.dataset.pageTab === tabName;

        tab.classList.toggle("active", isActive);
        tab.setAttribute("aria-selected", String(isActive));
    });

    if (tabName === "myOrders") {
        window.requestAnimationFrame(sizeInProgressOrderList);
    }

    updateActiveSearch();
}

function sizeInProgressOrderList() {
    const list = document.querySelector(
        ".orders-column--in-progress .orders-list"
    );

    if (!list) {
        return;
    }

    const cards = Array.from(list.querySelectorAll(".order-card"))
        .filter(card => !card.hidden)
        .slice(0, 3);
    const styles = window.getComputedStyle(list);
    const gap = parseFloat(styles.rowGap) || 0;
    const padding =
        (parseFloat(styles.paddingTop) || 0) +
        (parseFloat(styles.paddingBottom) || 0);
    const cardsHeight = cards.reduce(
        (height, card) => height + card.getBoundingClientRect().height,
        0
    );

    list.style.setProperty(
        "--orders-list-content-height",
        `${cardsHeight + Math.max(0, cards.length - 1) * gap + padding}px`
    );
}

window.addEventListener("resize", sizeInProgressOrderList);

// Switch between Menu and My Orders without leaving the homepage.
document.querySelectorAll(".page-tab").forEach(tab => {
    tab.addEventListener("click", () => {
        switchPageTab(tab.dataset.pageTab);
    });
});

function activatePageTabFromHash() {
    const hash = window.location.hash.toLowerCase();

    if (hash === "#myorders" || hash === "#my-orders") {
        switchPageTab("myOrders");
    } else {
        switchPageTab("menu");
    }
}

window.addEventListener("hashchange", activatePageTabFromHash);

// Let the Browse Menu button return to the Menu tab.
document.querySelectorAll(".orders-browse-button").forEach(button => {
    button.addEventListener("click", () => {
        switchPageTab("menu");
    });
});

// Initialize the homepage.
const requestedCategory = new URLSearchParams(window.location.search)
    .get("category");

if (!selectCategory(requestedCategory || "All")) {
    selectCategory("All");
}

activatePageTabFromHash();
displayProducts();
updateCart();

document.querySelectorAll("[data-cancel-countdown]").forEach(form => {
    let remainingSeconds = Number.parseInt(form.dataset.cancelSeconds, 10);
    if (!Number.isFinite(remainingSeconds) || remainingSeconds <= 0) {
        form.hidden = true;
        return;
    }

    const countdown = window.setInterval(() => {
        remainingSeconds -= 1;
        if (remainingSeconds <= 0) {
            window.clearInterval(countdown);
            form.hidden = true;
        }
    }, 1000);
});


/*=============================================================================================*/


/* =========================================
   CHECKOUT SUCCESS NOTIFICATION
========================================= */

document.addEventListener("DOMContentLoaded", function () {
    const storedOrder = sessionStorage.getItem("purpleBlendOrderSuccess");

    if (!storedOrder) return;

    sessionStorage.removeItem("purpleBlendOrderSuccess");

    try {
        const order = JSON.parse(storedOrder);

        const container = document.createElement("div");
        container.className = "toast-container";
        container.setAttribute("aria-live", "polite");
        container.setAttribute("aria-atomic", "true");

        const toast = document.createElement("div");
        toast.className = "toast";
        toast.id = "checkoutSuccessToast";
        toast.setAttribute("role", "status");

        const icon = document.createElement("div");
        icon.className = "toast-icon";
        icon.setAttribute("aria-hidden", "true");
        icon.textContent = "✓";

        const content = document.createElement("div");
        content.className = "toast-content";

        const title = document.createElement("p");
        title.className = "toast-title";
        title.textContent = "Order Submitted";

        const message = document.createElement("p");
        message.className = "toast-message";
        message.textContent = order.message ||
            "Your order has been submitted. Please wait for Purple Blend to confirm it.";

        content.append(title, message);

        if (order.order_code) {
            const code = document.createElement("p");
            code.className = "toast-message";
            code.style.marginTop = "6px";
            code.textContent = "Order Code: " + order.order_code;
            content.appendChild(code);
        }

        const track = document.createElement("div");
        track.className = "toast-progress-track";
        track.setAttribute("aria-hidden", "true");

        const progress = document.createElement("div");
        progress.className = "toast-progress";
        track.appendChild(progress);
        content.appendChild(track);

        const close = document.createElement("button");
        close.type = "button";
        close.className = "toast-close";
        close.setAttribute("aria-label", "Dismiss notification");
        close.innerHTML = "&times;";

        toast.append(icon, content, close);
        container.appendChild(toast);
        document.body.appendChild(container);

        const duration = 4500;
        const startedAt = Date.now();
        let progressTimer;
        let hideTimer;
        let dismissed = false;

        function dismissToast() {
            if (dismissed) return;
            dismissed = true;

            clearInterval(progressTimer);
            clearTimeout(hideTimer);

            toast.classList.add("hide");

            window.setTimeout(() => container.remove(), 260);
        }

        progressTimer = window.setInterval(() => {
            const remaining = Math.max(
                0,
                100 - ((Date.now() - startedAt) / duration) * 100
            );

            progress.style.width = remaining + "%";

            if (remaining <= 0) {
                dismissToast();
            }
        }, 40);

        hideTimer = window.setTimeout(dismissToast, duration);
        close.addEventListener("click", dismissToast);

    } catch (error) {
        console.error("Could not display order notification:", error);
    }
});
