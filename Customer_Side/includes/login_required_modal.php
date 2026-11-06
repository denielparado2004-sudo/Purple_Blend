<!-- Prompt guests to log in before starting checkout. -->
<div class="login-required-overlay" id="loginRequiredModal" hidden>
    <section
        class="login-required-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="loginRequiredTitle"
        aria-describedby="loginRequiredMessage"
    >
        <button
            type="button"
            class="login-required-close"
            id="loginRequiredClose"
            aria-label="Close login reminder"
        >&times;</button>

        <div class="login-required-icon" aria-hidden="true">👋</div>
        <h2 id="loginRequiredTitle">Almost there!</h2>
        <p id="loginRequiredMessage">
            Please log in before placing your order. Your items will stay safely in your cart.
        </p>

        <div class="login-required-actions">
            <a href="login.php?redirect=checkout.php" class="login-required-primary">
                Log In to Continue
            </a>
            <button type="button" class="login-required-secondary" id="loginRequiredCancel">
                Not Now
            </button>
        </div>
    </section>
</div>
