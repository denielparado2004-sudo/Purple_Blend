
<?php
// Render shared customer navigation and the cart controls.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$isCartPage = ($currentPage === 'cart.php');
$isCheckoutPage = ($currentPage === 'checkout.php');
$isLoggedIn = isset($_SESSION['customer_id']);

// Show the mini-cart only on pages other than Cart and Checkout.
$showMiniCart = !$isCartPage && !$isCheckoutPage;
?>

<div class="customer-site-header">
    <header data-customer-authenticated="<?= $isLoggedIn ? 'true' : 'false' ?>">
        <nav class="navbar" aria-label="Main navigation">

            <a href="index.php" class="brand" aria-label="The Purple Blend home">
                <img
                    class="brand-logo"
                    src="../Images/purple-blend-logo.jpg"
                    alt="The Purple Blend"
                >
            </a>

            <div class="header-actions">
                <?php if ($isLoggedIn): ?>
                    <a href="account.php" class="account-button">
                        👤 Account
                    </a>
                <?php else: ?>
                    <a href="login.php" class="login-button">
                        Log In
                    </a>
                <?php endif; ?>

                <?php if ($showMiniCart): ?>
                    <button
                        type="button"
                        class="cart-button"
                        id="openCart"
                    >
                        🛒 Cart (<span id="cartCount">0</span>)
                    </button>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <nav class="category-nav" aria-label="Menu categories">
        <div class="nav-links" id="primary-navigation">
            <a href="index.php?category=All#menu" data-category-link="All">All Items</a>
            <a href="index.php?category=Ulam#menu" data-category-link="Ulam">Ulam</a>
            <a href="index.php?category=Meryenda#menu" data-category-link="Meryenda">Meryenda</a>
            <a href="index.php?category=Beverage#menu" data-category-link="Beverage">Beverage</a>
            <a href="index.php?category=Milk%20Tea#menu" data-category-link="Milk Tea">Milk Tea</a>
            <a href="index.php?category=Fruity%20Soda#menu" data-category-link="Fruity Soda">Fruity Soda</a>
            <a href="index.php?category=Yakult%20Series#menu" data-category-link="Yakult Series">Yakult Series</a>
            <a href="index.php?category=Coke%20Float#menu" data-category-link="Coke Float">Coke Float</a>
            <a href="index.php?category=Donut#menu" data-category-link="Donut">Donut</a>
            <a href="index.php?category=Snacks#menu" data-category-link="Snacks">Snacks</a>
            <a href="index.php?category=Candy%27s#menu" data-category-link="Candy's">Candy's</a>
            <a href="index.php?category=Biscuits#menu" data-category-link="Biscuits">Biscuits</a>
        </div>
    </nav>
</div>

<script src="js/login-required.js" defer></script>
<script src="js/cart-fly.js?v=1" defer></script>
