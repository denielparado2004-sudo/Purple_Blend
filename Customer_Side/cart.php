<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../database.php';

$cartProducts = [];
$productResult = $conn->query(
    'SELECT product_code, name, price,
            CASE WHEN is_available = 1 THEN stock ELSE 0 END AS stock
     FROM menu_items'
);

if (!$productResult) {
    error_log('Purple Blend could not load cart inventory: ' . $conn->error);
    http_response_code(500);
    exit('Unable to load current inventory. Please refresh the page.');
}

while ($product = $productResult->fetch_assoc()) {
    $cartProducts[] = [
        'id' => $product['product_code'],
        'name' => $product['name'],
        'price' => (float) $product['price'],
        'stock' => (int) $product['stock']
    ];
}
$productResult->free();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/Purple_Blend/Images/pic1.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart | Purple Blend</title>

    <link rel="stylesheet" href="css/index.css?v=55">
    <link rel="stylesheet" href="css/cart.css?v=2">
  <script>
    window.purpleBlendProducts = <?= json_encode(
        $cartProducts,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) ?>;
  </script>
  <script src="js/cart.js?v=4" defer></script>

</head>
<body>

  <?php include __DIR__ . '/includes/header.php'; ?>



    <main class="cart-page">

        <div class="cart-heading">
            <h1>My Cart</h1>
            <p>Review your selected food before checkout.</p>
        </div>

        <div class="cart-layout">

            <section class="cart-items-section">
                <div class="cart-section-heading">
                    <h2>Your Items</h2>
                  
                </div>

                <div id="cart-items">
                    <!-- Cart items will be displayed by cart.js -->
                </div>

                <div id="empty-cart" class="empty-cart" hidden>
                    <div class="empty-cart-icon">🛒</div>
                    <h3>Your cart is empty</h3>
                    <p>You haven't added any food yet.</p>
                    <a href="index.php#menu" class="browse-menu-button">
                        Browse Menu
                    </a>
                </div>
            </section>

            <aside class="cart-summary">
                <h2>Order Summary</h2>

                <div class="summary-row">
                    <span>Items</span>
                    <span id="cart-item-count">0</span>
                </div>

                <div class="summary-row">
                    <span>Subtotal</span>
                    <span id="cart-subtotal">₱0.00</span>
                </div>

                <div class="summary-divider"></div>

                <div class="summary-row summary-total">
                    <span>Total</span>
                    <span id="cart-total">₱0.00</span>
                </div>

                <button type="button" id="checkout-button">
                    Proceed to Checkout
                </button>

                <p id="cart-stock-message" class="cart-stock-message" role="status" hidden></p>

                <p class="cart-note">
                    Your order total will update when you change quantities.
                </p>
            </aside>

        </div>

    </main>

    <?php include __DIR__ . '/includes/login_required_modal.php'; ?>

    <footer class="cart-footer">
        <p>&copy; 2026 Purple Blend. All rights reserved.</p>
    </footer>


    
</body>
</html>