
<?php
// Start the customer session and connect to the database.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../database.php';

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);

$isLoggedIn = isset($_SESSION['customer_id']);
if ($isLoggedIn && empty($_SESSION['cancel_order_csrf'])) {
    $_SESSION['cancel_order_csrf'] = bin2hex(random_bytes(32));
}

$customerOrders = [];
$products = [];

// Load available menu products.
$sql = "SELECT product_code, name, price, category, variant_group,
               size_label, image_url, stock
        FROM menu_items
        WHERE is_available = 1
        ORDER BY id DESC";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'id' => $row['product_code'],
            'name' => $row['name'],
            'price' => (float) $row['price'],
            'category' => $row['category'],
            'variant_group' => $row['variant_group'],
            'size_label' => $row['size_label'],
            'image' => $row['image_url'] ?? '',
            'stock' => (int) $row['stock']
        ];
    }
}

// Load the signed-in customer's orders.
if ($isLoggedIn) {
    $customerId = (int) $_SESSION['customer_id'];

    $orderSql = "SELECT id, order_code, order_type, dining_option,
                        order_status, pickup_date, pickup_time,
                        subtotal, total_amount, created_at,
                        TIMESTAMPDIFF(SECOND, created_at, CURRENT_TIMESTAMP) AS order_age_seconds
                 FROM orders
                 WHERE customer_id = ?
                 ORDER BY created_at DESC";

    $stmt = $conn->prepare($orderSql);

    if ($stmt) {
        $stmt->bind_param("i", $customerId);
        $stmt->execute();

        $orderResult = $stmt->get_result();

        while ($row = $orderResult->fetch_assoc()) {
            $customerOrders[] = $row;
        }

        $stmt->close();
    }
}

// Format order statuses for display.
function formatOrderStatus($status) {
    return ucwords(str_replace('_', ' ', strtolower((string) $status)));
}

// Separate active orders from finished or cancelled orders.
$orderGroups = [
    'in-progress' => [],
    'completed' => []
];

$orderItemsByOrder = [];

$orderIds = array_map(
    static fn($order) => (int) $order['id'],
    $customerOrders
);

if ($orderIds) {
    $itemsSql = "SELECT order_id, product_name, quantity
                 FROM order_items
                 WHERE order_id IN (" . implode(',', $orderIds) . ")
                 ORDER BY order_id, product_name";

    $itemsResult = $conn->query($itemsSql);

    if ($itemsResult) {
        while ($item = $itemsResult->fetch_assoc()) {
            $orderItemsByOrder[(int) $item['order_id']][] = $item;
        }
    } else {
        error_log(
            'Purple Blend could not load My Orders items: ' . $conn->error
        );
    }
}

foreach ($customerOrders as $order) {
    $status = strtolower((string) $order['order_status']);

    $group = in_array(
        $status,
        ['completed', 'cancelled', 'canceled'],
        true
    ) ? 'completed' : 'in-progress';

    $orderGroups[$group][] = $order;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/Purple_Blend/Images/pic1.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>The Purple Blend | Campus Canteen</title>

    <link rel="stylesheet" href="css/index.css?v=59">

    <script>
        window.purpleBlendProducts = <?= json_encode(
            $products,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        ) ?>;
    </script>

    <script src="js/index.js?v=23" defer></script>
</head>

<body>

<?php if ($toast): ?>
    <div
        id="feedbackToast"
        class="toast <?= htmlspecialchars($toast['type'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        role="status"
        aria-live="polite"
    >
        <div class="toast-icon">
            <?= ($toast['type'] ?? '') === 'error' ? '!' : '✓' ?>
        </div>

        <div class="toast-content">
            <strong>
                <?= htmlspecialchars($toast['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </strong>

            <p>
                <?= htmlspecialchars($toast['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </p>

            <?php if (!empty($toast['order_code'])): ?>
                <p class="toast-order-code">
                    Order Code:
                    <strong>
                        <?= htmlspecialchars($toast['order_code'], ENT_QUOTES, 'UTF-8') ?>
                    </strong>
                </p>

                <a
                    class="toast-action-link"
                    href="#myOrders"
                    onclick="document.querySelector('[data-page-tab=&quot;myOrders&quot;]')?.click()"
                >
                    View My Orders
                </a>
            <?php endif; ?>
        </div>

        <button
            type="button"
            onclick="this.parentElement.remove()"
            aria-label="Close notification"
        >
            &times;
        </button>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/header.php'; ?>

<!-- Restaurant-style homepage hero. -->
<section class="hero" id="heroCarousel">
    <div class="hero-content">
        <span class="hero-label">YOUR CAMPUS FOOD STOP</span>

        <h1>
            Good food.
            <span>Good mood.</span>
        </h1>

        <p>
            Your campus favorites, all in one place.
            Browse the menu and plan your next meal with Purple Blend.
        </p>

        <div class="hero-actions">
            <button
                type="button"
                class="hero-primary-button"
                onclick="document.querySelector('[data-page-tab=&quot;menu&quot;]')?.click(); document.getElementById('menu')?.scrollIntoView({behavior:'smooth'});"
            >
                Explore Our Menu
                <span aria-hidden="true">&rarr;</span>
            </button>

            <button
                type="button"
                class="hero-secondary-button"
                onclick="document.querySelector('[data-page-tab=&quot;myOrders&quot;]')?.click(); document.getElementById('myOrders')?.scrollIntoView({behavior:'smooth'});"
            >
                Track My Orders
            </button>
        </div>

        <div class="hero-note">
            <span class="hero-note-icon" aria-hidden="true">✦</span>
            <span>Made for your campus days</span>
        </div>
    </div>

    <div class="hero-visual">
        <div class="hero-image-frame">
            <img
                class="hero-image"
                src="https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=1200&q=85"
                alt="A freshly prepared meal served with colorful ingredients"
                fetchpriority="high"
            >

            <div class="hero-image-overlay"></div>

            <div class="hero-image-caption">
                <span class="hero-caption-label">THE PURPLE BLEND</span>
                <strong>Your next favorite meal awaits.</strong>
            </div>
        </div>

        <div class="hero-floating-note">
            <span class="hero-floating-icon" aria-hidden="true">♡</span>
            <div>
                <strong>Craving something?</strong>
                <span>Find it on our menu.</span>
            </div>
        </div>

        <div class="hero-decoration hero-decoration-one"></div>
        <div class="hero-decoration hero-decoration-two"></div>
    </div>
</section>

<!-- Homepage navigation and menu search. -->
<div class="page-tabs" role="tablist" aria-label="Homepage sections">
    <button
        type="button"
        class="page-tab active"
        id="menuTab"
        role="tab"
        aria-selected="true"
        aria-controls="menu"
        data-page-tab="menu"
    >
        Menu
    </button>

    <button
        type="button"
        class="page-tab"
        id="ordersTab"
        role="tab"
        aria-selected="false"
        aria-controls="myOrders"
        data-page-tab="myOrders"
    >
        My Orders
    </button>

    <div class="search-box">
        <svg class="search-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" stroke-width="1.8"/>
            <path d="m16 16 4.2 4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
        </svg>

        <input
            type="search"
            id="searchInput"
            placeholder="Search food, drinks, and more..."
            aria-label="Search menu items"
        >
    </div>
</div>

<!-- Menu section. Existing category values are preserved for JavaScript. -->
<main
    id="menu"
    class="page-section"
    role="tabpanel"
    aria-labelledby="menuTab"
>
    <div class="section-heading">
        <div>
            <span class="menu-kicker">FRESH FROM OUR MENU</span>
            <h2>Find your favorites.</h2>
            <p>Something delicious for every campus day.</p>
        </div>
    </div>

    <div class="menu-toolbar">
        <div class="categories" aria-label="Filter menu by category">
            <button
                type="button"
                class="category-button active"
                data-category="All"
            >
                All Items
            </button>

            <button
                type="button"
                class="category-button"
                data-category="Ulam"
            >
                Ulam
            </button>

            <button
                type="button"
                class="category-button"
                data-category="Meryenda"
            >
                Meryenda
            </button>

            <button
                type="button"
                class="category-button"
                data-category="Beverage"
            >
                Beverage
            </button>

            <button
                type="button"
                class="category-button"
                data-category="Milk Tea"
            >
                Milk Tea
            </button>

            <button
                type="button"
                class="category-button"
                data-category="Fruity Soda"
            >
                Fruity Soda
            </button>

            <button
                type="button"
                class="category-button"
                data-category="Yakult Series"
            >
                Yakult Series
            </button>

            <button
                type="button"
                class="category-button"
                data-category="Coke Float"
            >
                Coke Float
            </button>

            <button
                type="button"
                class="category-button"
                data-category="Donut"
            >
                Donut
            </button>

            <button
                type="button"
                class="category-button"
                data-category="Snacks"
            >
                Snacks
            </button>

            <button
                type="button"
                class="category-button"
                data-category="Candy's"
            >
                Candy's
            </button>

            <button
                type="button"
                class="category-button"
                data-category="Biscuits"
            >
                Biscuits
            </button>
        </div>
    </div>

    <div class="product-grid" id="productGrid"></div>
</main>

<!-- Customer order history. -->
<main
    id="myOrders"
    class="page-section orders-section"
    role="tabpanel"
    aria-labelledby="ordersTab"
    hidden
>
    <div class="section-heading">
        <div>
            <span class="menu-kicker">YOUR ORDER HISTORY</span>
            <h2>My Orders</h2>
            <p>Check your order details and current status.</p>
        </div>
    </div>

    <?php if (!$isLoggedIn): ?>
        <div class="orders-empty">
            <h3>Log in to view your orders</h3>
            <p>Your order history will appear here after you log in.</p>
            <a href="login.php" class="orders-login-button">Log In</a>
        </div>

    <?php elseif (empty($customerOrders)): ?>
        <div class="orders-empty">
            <h3>No orders yet</h3>
            <p>Your placed orders will appear here.</p>

            <button
                type="button"
                class="orders-browse-button"
                data-page-tab="menu"
            >
                Browse Menu
            </button>
        </div>

    <?php else: ?>
        <div class="orders-columns">
            <?php foreach ($orderGroups as $groupKey => $groupOrders): ?>
                <?php
                    $groupTitle = $groupKey === 'completed'
                        ? 'Completed'
                        : 'In Progress';
                ?>

                <section class="orders-column orders-column--<?= htmlspecialchars($groupKey, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="orders-column-heading">
                        <h3><?= $groupTitle ?></h3>
                        <span><?= count($groupOrders) ?></span>
                    </div>

                    <?php if (empty($groupOrders)): ?>
                        <p class="orders-column-empty">
                            No <?= strtolower($groupTitle) ?> orders.
                        </p>
                    <?php else: ?>
                        <div class="orders-list">
                            <?php foreach ($groupOrders as $order): ?>
                                <?php
                                    $status = strtolower(
                                        (string) $order['order_status']
                                    );

                                    $knownStatuses = [
                                        'pending',
                                        'confirmed',
                                        'preparing',
                                        'ready',
                                        'completed',
                                        'cancelled',
                                        'canceled'
                                    ];

                                    $statusClass = in_array(
                                        $status,
                                        $knownStatuses,
                                        true
                                    ) ? $status : 'other';
                                    $orderAgeSeconds = (int) $order['order_age_seconds'];
                                    $cancelSecondsRemaining = 300 - $orderAgeSeconds;
                                    $canCancelOrder = $status === 'pending' &&
                                        $orderAgeSeconds >= 0 &&
                                        $cancelSecondsRemaining > 0;
                                ?>

                                <article class="order-card">
                                    <div class="order-card-header">
                                        <div>
                                            <span class="order-label">
                                                Order Code
                                            </span>

                                            <h3>
                                                <?= htmlspecialchars($order['order_code'], ENT_QUOTES, 'UTF-8') ?>
                                            </h3>
                                        </div>

                                        <span class="order-status <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars(formatOrderStatus($status), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </div>

                                    <?php if (!empty($orderItemsByOrder[(int) $order['id']])): ?>
                                        <ul class="order-items" aria-label="Items in this order">
                                            <?php foreach ($orderItemsByOrder[(int) $order['id']] as $item): ?>
                                                <li>
                                                    <span>
                                                        <?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?>
                                                    </span>

                                                    <strong>
                                                        &times;<?= (int) $item['quantity'] ?>
                                                    </strong>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>

                                    <div class="order-card-footer">
                                        <span>
                                            <?php if (!empty($order['pickup_date']) || !empty($order['pickup_time'])): ?>
                                                <small>
                                                    Pickup
                                                    <?= !empty($order['pickup_date'])
                                                        ? htmlspecialchars(date('M j', strtotime($order['pickup_date'])), ENT_QUOTES, 'UTF-8')
                                                        : '' ?>

                                                    <?= !empty($order['pickup_time'])
                                                        ? htmlspecialchars(date('g:i A', strtotime($order['pickup_time'])), ENT_QUOTES, 'UTF-8')
                                                        : '' ?>
                                                </small>
                                            <?php endif; ?>
                                        </span>

                                        <strong>
                                            ₱<?= number_format((float) $order['total_amount'], 2) ?>
                                        </strong>
                                    </div>

                                    <?php if ($canCancelOrder): ?>
                                        <form
                                            class="order-card-actions"
                                            action="cancel_order.php"
                                            method="POST"
                                            data-cancel-countdown
                                            data-cancel-seconds="<?= $cancelSecondsRemaining ?>"
                                            onsubmit="return confirm('Cancel this order? The items will be returned to stock.');"
                                        >
                                            <input
                                                type="hidden"
                                                name="order_id"
                                                value="<?= (int) $order['id'] ?>"
                                            >
                                            <input
                                                type="hidden"
                                                name="cancel_csrf"
                                                value="<?= htmlspecialchars($_SESSION['cancel_order_csrf'], ENT_QUOTES, 'UTF-8') ?>"
                                            >
                                            <button type="submit" class="cancel-order-button">
                                                Cancel Order
                                            </button>
                                            <small>Available for 5 minutes after ordering, while the order is pending.</small>
                                        </form>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <p class="orders-search-empty" aria-live="polite" hidden>
                            No orders or purchased items match your search.
                        </p>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Existing cart overlay and cart panel are preserved. -->
<div class="overlay" id="cartOverlay"></div>

<?php include __DIR__ . '/includes/cart_panel.php'; ?>

<footer>
    <p>
        <strong>The Purple Blend</strong>
        <span aria-hidden="true">·</span>
        Campus Canteen Web Ordering System
    </p>

    <p>Good food, good mood. See you on campus!</p>
</footer>

</body>
</html>
