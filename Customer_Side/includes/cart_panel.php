
<!-- Shared mini-cart panel used by customer menu and product pages. -->
<aside class="cart-panel" id="cartPanel" aria-label="Shopping cart">
    <div class="cart-header">
        <h2>Your Cart</h2>
        <button class="close-cart" id="closeCart" type="button">✕ Close</button>
    </div>

    <div class="cart-items" id="cartItems">
        <p>Your cart is empty.</p>
    </div>

    <div class="cart-footer">
        <div class="cart-total">
            <span>Total</span>
            <span id="cartTotal">₱0.00</span>
        </div>


       <button class="checkout-button" id="checkoutButton" type="button">
    Continue to Checkout
</button>

<button class="clear-cart-button" id="clearCartButton" type="button">
    Clear Cart
</button>

    </div>
</aside>

<?php include __DIR__ . '/login_required_modal.php'; ?>
