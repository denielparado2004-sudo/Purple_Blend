<?php
session_start();
require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/includes/product_options.php';

// Verify the customer session before rendering checkout or accepting an order.
// Keep a CSRF token for the order-submission request.
if (empty($_SESSION['checkout_csrf'])) {
    $_SESSION['checkout_csrf'] = bin2hex(random_bytes(32));
}

$isOrderRequest = ($_GET['action'] ?? '') === 'place_order';

if (!isset($_SESSION['customer_id'])) {
    if ($isOrderRequest) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Your session expired. Please log in again.']);
        exit;
    }

    $redirect = ($_GET['mode'] ?? '') === 'buy-now'
        ? 'checkout.php?mode=buy-now'
        : 'checkout.php';
    header('Location: login.php?redirect=' . rawurlencode($redirect));
    exit;
}

$toast = $_SESSION['toast'] ?? null;
unset($_SESSION['toast']);
$diningCategoryByProductCode = [];
$stockByProductCode = [];

// Validate the submitted cart and create the order before sending HTML.
if ($isOrderRequest && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid checkout request. Please try again.');
        }

        if (!hash_equals($_SESSION['checkout_csrf'], (string)($payload['csrf_token'] ?? ''))) {
            http_response_code(403);
            throw new RuntimeException('For your security, we could not verify this checkout. Please refresh the page and try again.');
        }

        $items = $payload['items'] ?? null;
        $time = trim((string)($payload['schedule_time'] ?? ''));
        $dining = (string)($payload['dining_option'] ?? '');
        $notes = trim((string)($payload['notes'] ?? ''));
        $paymentMethod = (string)($payload['payment_method'] ?? 'cash');

        if (!is_array($items) || count($items) < 1 || count($items) > 100) {
            throw new RuntimeException('Your cart is empty or contains too many items.');
        }
        if (!in_array($paymentMethod, ['cash', 'instapay_qr'], true)) {
            throw new RuntimeException('Please choose a valid payment method.');
        }
        if ($paymentMethod === 'instapay_qr' && ($payload['payment_confirmed'] ?? false) !== true) {
            throw new RuntimeException('Please confirm that you have completed the QR payment before placing your order.');
        }
        if (strlen($notes) > 500) {
            throw new RuntimeException('Order notes must be 500 characters or fewer.');
        }
        $timezone = new DateTimeZone('Asia/Manila');
        $scheduledTime = DateTime::createFromFormat('!H:i', $time, $timezone);
        $timeErrors = DateTime::getLastErrors();
        if (
            !$scheduledTime ||
            ($timeErrors !== false && ($timeErrors['warning_count'] > 0 || $timeErrors['error_count'] > 0)) ||
            $scheduledTime->format('H:i') !== $time
        ) {
            throw new RuntimeException('Please choose a valid pickup or reservation time.');
        }

        $normalizedItems = [];
        $seenCodes = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new RuntimeException('One of your cart items is invalid. Please review your cart.');
            }
            $itemId = trim((string)($item['id'] ?? ''));
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
            $riceOption = null;
            $code = $itemId;

            foreach (['-with-rice' => 'with', '-without-rice' => 'without'] as $suffix => $option) {
                if (str_ends_with($itemId, $suffix)) {
                    $code = substr($itemId, 0, -strlen($suffix));
                    $riceOption = $option;
                    break;
                }
            }

            if ($code === '' || strlen($code) > 100 || $quantity === false || $quantity < 1 || $quantity > 99) {
                throw new RuntimeException('A cart item has an invalid product code or quantity.');
            }
            $itemKey = $code . "\0" . ($riceOption ?? 'standard');
            if (isset($seenCodes[$itemKey])) {
                throw new RuntimeException('Your cart contains a duplicate product. Please review your cart and try again.');
            }
            $seenCodes[$itemKey] = true;
            $normalizedItems[] = [
                'product_code' => $code,
                'rice_option' => $riceOption,
                'quantity' => $quantity
            ];
        }

        // Read customer contact details from the database, not from the browser.
        $customerId = (int)$_SESSION['customer_id'];
        $customerStmt = $conn->prepare('SELECT first_name, last_name, email, phone FROM customers WHERE id = ? LIMIT 1');
        if (!$customerStmt) {
            throw new RuntimeException('Unable to prepare your order. Please try again.');
        }
        $customerStmt->bind_param('i', $customerId);
        $customerStmt->execute();
        $customer = $customerStmt->get_result()->fetch_assoc();
        $customerStmt->close();
        if (!$customer) {
            http_response_code(401);
            throw new RuntimeException('Your customer account could not be found. Please log in again.');
        }
        $customerName = trim($customer['first_name'] . ' ' . $customer['last_name']);
        $customerEmail = $customer['email'];
        $customerPhone = $customer['phone'] ?? null;

        $conn->begin_transaction();
        $orderItemsToSave = [];
        $subtotal = 0.0;

        // Lock each product row so concurrent orders cannot oversell stock.
        $productStmt = $conn->prepare('SELECT id, product_code, name, price, stock, is_available, size_label, category FROM menu_items WHERE product_code = ? LIMIT 1 FOR UPDATE');
        if (!$productStmt) {
            throw new RuntimeException('Unable to validate product availability. Please try again.');
        }

        $requestedStockByProduct = [];
        $hasDiningEligibleItems = false;
        foreach ($normalizedItems as $requestedItem) {
            $code = $requestedItem['product_code'];
            $quantity = $requestedItem['quantity'];
            $productStmt->bind_param('s', $code);
            $productStmt->execute();
            $product = $productStmt->get_result()->fetch_assoc();

            if (!$product || (int)$product['is_available'] !== 1) {
                throw new RuntimeException('One of your selected products is no longer available. Please review your cart.');
            }

            $normalizedCategory = strtolower(trim((string)$product['category']));
            if (in_array(
                $normalizedCategory,
                ['ulam', 'meryenda', 'meryenda / dessert'],
                true
            )) {
                $hasDiningEligibleItems = true;
            }

            $requestedStockByProduct[$code] =
                ($requestedStockByProduct[$code] ?? 0) + $quantity;
            if ((int)$product['stock'] < $requestedStockByProduct[$code]) {
                $stockProductName = $product['name'];
                if (!empty($product['size_label'])) {
                    $stockProductName .= ' (' . $product['size_label'] . ')';
                }
                throw new RuntimeException('Not enough stock for ' . $stockProductName . '. Available quantity: ' . (int)$product['stock'] . '. Please update your cart.');
            }

            $productName = $product['name'];
            $riceOption = $requestedItem['rice_option'];
            $riceOptions = riceOptionsForProduct($product['name']);

            if ($riceOption !== null) {
                if (!isset($riceOptions[$riceOption])) {
                    throw new RuntimeException('A selected rice option is not available for this product.');
                }

                $baseName = preg_replace('/\s+with\s+rice$/i', '', $productName);
                $baseName = preg_replace('/\s+without\s+rice$/i', '', $baseName);
                $option = $riceOptions[$riceOption];
                $variantLabel = ' (' . $option['label'] . ')';
                $baseNameLimit = 150 - mb_strlen($variantLabel);
                $productName = mb_substr($baseName, 0, $baseNameLimit) .
                    $variantLabel;
                $unitPrice = (float)$option['price'];
            } else {
                $unitPrice = (float)$product['price'];
            }

            if (!empty($product['size_label'])) {
                $sizeLabel = ' (' . $product['size_label'] . ')';
                $productName = mb_substr(
                    $productName,
                    0,
                    150 - mb_strlen($sizeLabel)
                ) . $sizeLabel;
            }

            $lineTotal = round($unitPrice * $quantity, 2);
            $subtotal = round($subtotal + $lineTotal, 2);
            $orderItemsToSave[] = [
                'menu_item_id' => (int)$product['id'],
                'product_code' => $product['product_code'],
                'product_name' => $productName,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal
            ];
        }
        $productStmt->close();

        if ($hasDiningEligibleItems) {
            if ($dining === 'take-out' || $dining === 'takeout') {
                $dining = 'takeaway';
            }

            if (!in_array($dining, ['dine_in', 'takeaway'], true)) {
                throw new RuntimeException('Please choose dine-in or takeout.');
            }
        } else {
            $dining = 'takeaway';
        }

        $orderCode = 'PB-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $orderType = 'online';
        $orderStatus = 'pending';
        $pickupDate = null;
        $pickupTime = $scheduledTime->format('H:i:s');
        $totalAmount = $subtotal;

        $orderStmt = $conn->prepare('INSERT INTO orders (order_code, customer_id, order_type, dining_option, order_status, customer_name, customer_email, customer_phone, pickup_date, pickup_time, notes, subtotal, total_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$orderStmt) {
            throw new RuntimeException('Unable to save your order. Please try again.');
        }
        $orderStmt->bind_param('sisssssssssdd', $orderCode, $customerId, $orderType, $dining, $orderStatus, $customerName, $customerEmail, $customerPhone, $pickupDate, $pickupTime, $notes, $subtotal, $totalAmount);
        if (!$orderStmt->execute()) {
            $orderStmt->close();
            throw new RuntimeException('Unable to save your order. Please try again.');
        }
        $orderId = (int)$conn->insert_id;
        $orderStmt->close();

        $itemStmt = $conn->prepare('INSERT INTO order_items (order_id, menu_item_id, product_code, product_name, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stockStmt = $conn->prepare('UPDATE menu_items SET stock = stock - ? WHERE id = ? AND stock >= ? AND is_available = 1');
        if (!$itemStmt || !$stockStmt) {
            throw new RuntimeException('Unable to save order items. Please try again.');
        }

        foreach ($orderItemsToSave as $orderItem) {
            $menuItemId = $orderItem['menu_item_id'];
            $productCode = $orderItem['product_code'];
            $productName = $orderItem['product_name'];
            $quantity = $orderItem['quantity'];
            $unitPrice = $orderItem['unit_price'];
            $lineTotal = $orderItem['line_total'];

            $itemStmt->bind_param('iissidd', $orderId, $menuItemId, $productCode, $productName, $quantity, $unitPrice, $lineTotal);
            if (!$itemStmt->execute()) {
                throw new RuntimeException('Unable to save an item in your order. Please try again.');
            }

            $stockStmt->bind_param('iii', $quantity, $menuItemId, $quantity);
            if (!$stockStmt->execute() || $stockStmt->affected_rows !== 1) {
                throw new RuntimeException('Stock changed while placing your order. Please review your cart and try again.');
            }
        }
        $itemStmt->close();
        $stockStmt->close();

        $paymentStatus = 'pending';
        $storedPaymentMethod = $paymentMethod === 'instapay_qr'
            ? 'online'
            : $paymentMethod;
        $paymentStmt = $conn->prepare('INSERT INTO payments (order_id, payment_method, payment_status, amount) VALUES (?, ?, ?, ?)');
        if (!$paymentStmt) {
            throw new RuntimeException('Unable to create your payment record. Please try again.');
        }
        $paymentStmt->bind_param('issd', $orderId, $storedPaymentMethod, $paymentStatus, $totalAmount);
        if (!$paymentStmt->execute()) {
            $paymentStmt->close();
            throw new RuntimeException('Unable to create your payment record. Please try again.');
        }
        $paymentStmt->close();

        if (!$conn->commit()) {
            throw new RuntimeException('Unable to complete your order. Please try again.');
        }

        require_once __DIR__ . '/../includes/order_email.php';
        $orderEmailSent = sendPurpleBlendOrderEmail([
            'order_code' => $orderCode,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'total_amount' => $totalAmount,
            'dining_option' => $dining,
            'pickup_date' => $pickupDate,
            'pickup_time' => $pickupTime
        ], 'pending', $orderItemsToSave);

        echo json_encode([
            'success' => true,
            'message' => $orderEmailSent
                ? 'Your order has been sent to the canteen. A confirmation email was sent to your registered email address.'
                : 'Your order has been sent to the canteen and is pending confirmation. We could not send the confirmation email right now.',
            'order_code' => $orderCode,
            'total' => number_format($totalAmount, 2, '.', '')
        ]);
        exit;
    } catch (Throwable $error) {
        if (isset($conn) && $conn instanceof mysqli) {
            try { $conn->rollback(); } catch (Throwable $ignored) {}
        }
        if (http_response_code() < 400) {
            http_response_code(400);
        }
        // Log technical details for debugging; show only a safe message to the customer.
        error_log('Purple Blend checkout error: ' . $error->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $error instanceof RuntimeException
                ? $error->getMessage()
                : 'We could not place your order right now. Please try again.'
        ]);
        exit;
    }
}

$categoryResult = $conn->query(
    'SELECT product_code, category,
            CASE WHEN is_available = 1 THEN stock ELSE 0 END AS stock
     FROM menu_items'
);
if (!$categoryResult) {
    throw new RuntimeException('Unable to load product categories for checkout.');
}

while ($categoryRow = $categoryResult->fetch_assoc()) {
    $diningCategoryByProductCode[$categoryRow['product_code']] =
        $categoryRow['category'];
    $stockByProductCode[$categoryRow['product_code']] =
        (int) $categoryRow['stock'];
}
$categoryResult->free();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/Purple_Blend/Images/pic1.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Purple Blend</title>
    <link rel="stylesheet" href="css/index.css?v=55">
    <link rel="stylesheet" href="css/checkout.css?v=6">

    <style>
        /* Customer order notes */
        .order-notes-section {
            margin-top: 22px;
        }

        .order-notes-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .order-notes-heading h2 {
            margin: 0;
        }

        .order-notes-optional {
            color: var(--muted);
            font-size: 12px;
            font-weight: 500;
        }

        .order-notes-description {
            margin-bottom: 12px;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.6;
        }

        #orderNotes {
            display: block;
            box-sizing: border-box;
            width: 100%;
            min-height: 115px;
            padding: 14px;
            border: 1px solid #e4d5ec;
            border-radius: 12px;
            outline: none;
            background: #fff;
            color: var(--text);
            font: inherit;
            font-size: 13px;
            line-height: 1.6;
            resize: vertical;
            transition: border-color 0.2s ease,
                        box-shadow 0.2s ease;
        }

        #orderNotes:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(111, 43, 145, 0.10);
        }

        #orderNotes::placeholder {
            color: #a59aaa;
        }

        .order-notes-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-top: 8px;
        }

        .order-notes-hint {
            color: var(--muted);
            font-size: 11px;
            line-height: 1.5;
        }

        #orderNotesCount {
            flex-shrink: 0;
            color: var(--muted);
            font-size: 11px;
        }

        /* Successful order modal */
        .order-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(35, 20, 45, 0.58);
        }

        .order-modal-overlay.show {
            display: flex;
        }

        .order-success-modal {
            box-sizing: border-box;
            width: 100%;
            max-width: 440px;
            padding: 30px;
            border-radius: 20px;
            background: var(--white, #fff);
            color: var(--text, #30243A);
            text-align: center;
            box-shadow: 0 22px 70px rgba(28, 12, 38, 0.25);
            animation: orderModalIn 0.2s ease-out;
        }

        @keyframes orderModalIn {
            from { opacity: 0; transform: translateY(10px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .order-success-icon {
            display: grid;
            place-items: center;
            width: 58px;
            height: 58px;
            margin: 0 auto 14px;
            border-radius: 50%;
            background: #efe2f7;
            color: var(--purple, #6F2B91);
            font-size: 30px;
            font-weight: 800;
        }

        .order-success-modal h2 {
            margin: 0 0 10px;
            font-size: 23px;
        }

        .order-success-modal > p {
            margin: 0 0 14px;
            color: var(--muted, #776D80);
            font-size: 14px;
            line-height: 1.6;
        }

        .order-code-box {
            margin: 18px 0 14px;
            padding: 14px;
            border: 1px dashed #cbb3d8;
            border-radius: 13px;
            background: var(--light-purple, #F7F0FB);
        }

        .order-code-box span,
        .order-code-box strong {
            display: block;
        }

        .order-code-box span {
            margin-bottom: 6px;
            color: var(--muted, #776D80);
            font-size: 12px;
        }

        .order-code-box strong {
            overflow-wrap: anywhere;
            color: var(--dark-purple, #4E176B);
            font-size: 22px;
            letter-spacing: 1px;
        }

        .order-modal-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 20px;
        }

        .order-modal-primary,
        .order-modal-secondary {
            min-height: 44px;
            padding: 11px 14px;
            border: 1px solid var(--purple, #6F2B91);
            border-radius: 10px;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .order-modal-primary {
            background: var(--purple, #6F2B91);
            color: #fff;
        }

        .order-modal-primary:hover {
            background: var(--dark-purple, #4E176B);
        }

        .order-modal-secondary {
            background: #fff;
            color: var(--purple, #6F2B91);
        }

        .order-modal-secondary:hover {
            background: var(--light-purple, #F7F0FB);
        }

        @media (max-width: 420px) {
            .order-success-modal { padding: 24px 18px; }
            .order-modal-actions { grid-template-columns: 1fr; }
        }

        /* One clear checkout card: all sections stay together in one column. */
        .checkout-layout {
            display: block !important;
            width: 100%;
            max-width: 850px;
            margin: 24px auto 0;
            padding: clamp(18px, 3vw, 32px);
            box-sizing: border-box;
            background: #fff;
            border: 1px solid #eadff0;
            border-radius: 20px;
            box-shadow: 0 12px 36px rgba(78, 23, 107, 0.08);
        }
        .checkout-details,
        .checkout-payment {
            width: 100%;
            min-width: 0;
            padding: 0 !important;
            margin: 0 !important;
            border: 0 !important;
            border-radius: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
        }
        .checkout-payment { margin-top: 26px !important; padding-top: 24px !important; border-top: 1px solid #eee5f2 !important; }
        .checkout-details > h2, .checkout-payment > h2 { color: #4e176b; font-size: 19px; }
        .checkout-total { margin-top: 18px; padding-top: 16px; border-top: 1px solid #eee5f2; }
        .qr-payment-panel { display: none; margin: 14px 0; padding: 16px; border: 1px solid #e4d5ec; border-radius: 14px; background: #fbf7fd; }
        .qr-payment-panel.show { display: block; }
        .checkout-payment:has(input[name="payment"][value="instapay_qr"]:checked) #qrPaymentPanel { display: block; }
        .payment-qr-image { display: block; width: min(100%, 260px); max-height: 320px; object-fit: contain; margin: 12px auto; border-radius: 10px; background: #fff; }
        .qr-payment-warning { color: #70567d; font-size: 13px; line-height: 1.55; }
        #placeOrderButton { width: 100%; min-height: 48px; margin-top: 14px; }
        #placeOrderButton:disabled { opacity: .55; cursor: not-allowed; }
        .payment-option { display: flex; align-items: center; gap: 10px; margin: 10px 0; padding: 13px 14px; border: 1px solid #e8dcef; border-radius: 12px; background: #fff; }
        @media (max-width: 600px) {
            .checkout-layout { padding: 18px 15px; border-radius: 15px; }
            .checkout-payment { margin-top: 22px !important; padding-top: 20px !important; }
        }
    </style>
</head>

<body>
<?php if (is_array($toast)): ?>
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
        <strong><?= htmlspecialchars($toast['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
        <p><?= htmlspecialchars($toast['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <button type="button" id="dismissCheckoutToast" aria-label="Close notification">&times;</button>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/header.php'; ?>

<main class="checkout-page">
    <div class="checkout-container">
        <h1>Checkout</h1>

        <p class="checkout-subtitle">
            Review your order, schedule, and payment details.
        </p>

        <div class="checkout-layout">

            <section class="checkout-details">
                <h2>Order Summary</h2>

                <div id="checkoutItems">
                    <p>Loading your selected items...</p>
                </div>

                <div class="checkout-total">
                    <span>Total</span>
                    <strong id="checkoutTotal">₱0.00</strong>
                </div>
            </section>

            <section class="checkout-payment">
                <div id="diningOptionSection">
                <h2>Dining Option</h2>

                <div class="dining-options">

                    <label class="dining-card">
                        <input
                            type="radio"
                            name="dining"
                            value="dine_in"
                            checked
                        >

                        <span class="dining-card-icon">🍽️</span>

                        <strong>Dine-in</strong>

                        <p>Order ahead and choose when you plan to dine in.</p>
                    </label>

                    <label class="dining-card">
                        <input
                            type="radio"
                            name="dining"
                            value="takeout"
                        >

                        <span class="dining-card-icon">🥡</span>

                        <strong>Takeout</strong>

                        <p>Order ahead and choose when to pick up your food.</p>
                    </label>

                </div>
                </div>

                <div class="schedule-section">
                    <div class="schedule-heading-row" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                        <h2 id="scheduleHeading">Reservation Schedule</h2>
                        <span class="order-notes-optional">Required</span>
                    </div>

                    <p
                        class="schedule-description"
                        id="scheduleDescription"
                    >
                        Choose a time for your reservation or pickup.
                    </p>

                    <div class="schedule-fields">
                        <div class="schedule-field">
                            <label
                                for="scheduleTime"
                                id="scheduleTimeLabel"
                            >
                                Reservation Time
                            </label>

                            <p class="schedule-time-help" id="scheduleTimeHelp">
                                Choose the time you plan to pick up your order. Tap the field to open the time picker; the time format depends on your device.
                            </p>

                            <div class="schedule-input-wrap">
                                <span
                                    class="schedule-input-icon"
                                    aria-hidden="true"
                                >🕒</span>

                                <input
                                    type="time"
                                    id="scheduleTime"
                                    name="schedule_time"
                                    aria-describedby="scheduleTimeHelp"
                                    required
                                >
                            </div>
                        </div>
                    </div>

                    <p class="checkout-note" id="scheduleNote">
                        Your reservation or pickup time is required and subject to canteen availability and confirmation.
                    </p>
                </div>

                <!-- Customer notes -->
                <section class="order-notes-section">
                    <div class="order-notes-heading">
                        <h2>Order Notes</h2>
                        <span class="order-notes-optional">Optional</span>
                    </div>

                    <p class="order-notes-description">
                        Have special instructions or a message for the
                        Purple Blend vendor? Write them here.
                    </p>

                    <textarea
                        id="orderNotes"
                        name="order_notes"
                        maxlength="500"
                        placeholder="Example: Please prepare my order before 12:00 PM. No plastic spoon, please."
                        aria-describedby="orderNotesHint orderNotesCount"
                    ></textarea>

                    <div class="order-notes-footer">
                        <span
                            class="order-notes-hint"
                            id="orderNotesHint"
                        >
                            Please keep your instructions clear and specific.
                        </span>

                        <span id="orderNotesCount" aria-live="polite">
                            0/500
                        </span>
                    </div>
                </section>

                <h2 style="margin-top: 24px;">Payment Method</h2>

                <label class="payment-option">
                    <input type="radio" name="payment" value="cash" checked>
                    <span><strong>Cash</strong><br><small>Pay when you arrive or pick up your order.</small></span>
                </label>

                <label class="payment-option">
                    <input type="radio" name="payment" value="instapay_qr">
                    <span><strong>GCash / QR Payment</strong><br><small>Scan the QR code and pay before placing your order.</small></span>
                </label>

                <div class="qr-payment-panel" id="qrPaymentPanel" aria-live="polite">
                    <h3>Pay using GCash</h3>
                    <p class="qr-payment-warning">Scan this Purple Blend payment QR code and complete the payment in your GCash app.</p>
                    <img class="payment-qr-image" src="images/purple-blend-payment-qr.jpg" alt="Purple Blend GCash payment QR code">
                    <p class="qr-payment-warning">After paying, click “I've Paid” below. The button will then change to “Place Order.” Your payment will remain pending until Purple Blend staff verifies it.</p>
                    <p id="qrPaymentConfirmation" class="qr-payment-warning" role="status" aria-live="polite">Complete your payment, then confirm using the button below.</p>
                </div>

                <p class="checkout-note">
                    Review your items, dining option when applicable, schedule, and total before placing your order.
                </p>

                <button type="button" id="placeOrderButton">
                    Place Order
                </button>

                <p id="checkoutMessage" role="status" aria-live="polite"></p>

                <a href="cart.php" class="back-to-cart">
                    Back to Cart
                </a>
            </section>

        </div>
    </div>
</main>


<div class="order-modal-overlay" id="orderSuccessModal" role="dialog" aria-modal="true" aria-labelledby="orderSuccessTitle">
    <div class="order-success-modal">
        <div class="order-success-icon" aria-hidden="true">✓</div>
        <h2 id="orderSuccessTitle">Order Placed Successfully!</h2>
        <p>Your order has been submitted. Please wait for Purple Blend to confirm it.</p>

        <div class="order-code-box">
            <span>Order Code</span>
            <strong id="successOrderCode"></strong>
            <button type="button" id="copySuccessOrderCode" class="order-modal-secondary" style="margin-top: 10px;">Copy Order Code</button>
        </div>

        <p id="orderSuccessStatus" role="status">Status: Pending Confirmation</p>

        <div class="order-modal-actions">
            <button type="button" class="order-modal-primary" id="successMyOrders">My Orders</button>
            <button type="button" class="order-modal-secondary" id="successOrderAnother">Order Another</button>
        </div>
    </div>
</div>

<footer class="checkout-footer">
    <p>&copy; 2026 Purple Blend. All rights reserved.</p>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkoutToast = document.getElementById('feedbackToast');
    const dismissCheckoutToast = document.getElementById('dismissCheckoutToast');
    let checkoutToastTimer;

    function dismissToast() {
        if (!checkoutToast || checkoutToast.hidden) return;

        window.clearTimeout(checkoutToastTimer);
        checkoutToast.classList.add('hide');
        window.setTimeout(function () {
            checkoutToast.hidden = true;
            checkoutToast.classList.remove('hide');
        }, 260);
    }

    if (checkoutToast) {
        dismissCheckoutToast.addEventListener('click', dismissToast);
        checkoutToastTimer = window.setTimeout(dismissToast, 4500);
    }

    const CART_KEY = 'purpleBlendCart';
    const BUY_NOW_KEY = 'purpleBlendBuyNow';
    const isBuyNow = new URLSearchParams(window.location.search)
        .get('mode') === 'buy-now';
    const CSRF_TOKEN = <?= json_encode($_SESSION['checkout_csrf']) ?>;
    const DINING_CATEGORY_BY_PRODUCT_CODE = <?= json_encode(
        $diningCategoryByProductCode,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) ?>;
    const STOCK_BY_PRODUCT_CODE = <?= json_encode(
        $stockByProductCode,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) ?>;

    const checkoutItems = document.getElementById('checkoutItems');
    const checkoutTotal = document.getElementById('checkoutTotal');
    const placeOrderButton = document.getElementById('placeOrderButton');
    const checkoutMessage = document.getElementById('checkoutMessage');
    const qrPaymentPanel = document.getElementById('qrPaymentPanel');
    const qrPaymentConfirmation = document.getElementById('qrPaymentConfirmation');
    let qrPaymentConfirmed = false;

    const scheduleHeading = document.getElementById('scheduleHeading');
    const scheduleDescription = document.getElementById('scheduleDescription');
    const scheduleTimeLabel = document.getElementById('scheduleTimeLabel');
    const scheduleTimeHelp = document.getElementById('scheduleTimeHelp');
    const scheduleNote = document.getElementById('scheduleNote');
    const diningOptionSection = document.getElementById('diningOptionSection');

    const scheduleTime = document.getElementById('scheduleTime');

    const orderNotes = document.getElementById('orderNotes');
    const orderNotesCount = document.getElementById('orderNotesCount');

    function getCart() {
        try {
            const savedCart = JSON.parse(
                localStorage.getItem(CART_KEY) || '[]'
            );

            return Array.isArray(savedCart) ? savedCart : [];
        } catch (error) {
            return [];
        }
    }

    function escapeHTML(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function getSelectedItems() {
        let items = getCart();

        if (isBuyNow) {
            try {
                const buyNowItems = JSON.parse(
                    sessionStorage.getItem(BUY_NOW_KEY) || '[]'
                );
                items = Array.isArray(buyNowItems) ? buyNowItems : [];
            } catch (error) {
                items = [];
            }
        }

        const validItems = items.reduce(function (normalizedItems, item) {
            if (
                !item ||
                item.id === undefined ||
                !item.name ||
                !Number.isFinite(Number(item.price)) ||
                Number(item.price) < 0 ||
                !Number.isFinite(Number(item.quantity)) ||
                Number(item.quantity) < 1
            ) {
                return normalizedItems;
            }

            normalizedItems.push({
                ...item,
                quantity: Math.min(99, Math.floor(Number(item.quantity)))
            });

            return normalizedItems;
        }, []);

        if (JSON.stringify(validItems) !== JSON.stringify(items)) {
            saveCheckoutItems(validItems);
        }

        return validItems.filter(function (item) {
            return item.selected !== false;
        });
    }

    function saveCheckoutItems(items) {
        if (isBuyNow) {
            sessionStorage.setItem(BUY_NOW_KEY, JSON.stringify(items));
            return;
        }

        localStorage.setItem(CART_KEY, JSON.stringify(items));
    }

    function hasDiningEligibleItems(items) {
        return items.some(function (item) {
            const productCode = String(item.id)
                .replace(/-(?:with|without)-rice$/, '');
            const category = String(
                DINING_CATEGORY_BY_PRODUCT_CODE[productCode] || ''
            ).trim().toLocaleLowerCase().replace(/\s+/g, ' ');

            return ['ulam', 'meryenda', 'meryenda / dessert'].includes(category);
        });
    }

    function productCodeForItem(item) {
        return String(item.id).replace(/-(?:with|without)-rice$/, '');
    }

    function getStockForItem(item) {
        return Number(STOCK_BY_PRODUCT_CODE[productCodeForItem(item)]) || 0;
    }

    function hasInsufficientStock(items) {
        const requestedByProduct = new Map();

        items.forEach(function (item) {
            const productCode = productCodeForItem(item);
            requestedByProduct.set(
                productCode,
                (requestedByProduct.get(productCode) || 0) +
                    Number(item.quantity)
            );
        });

        return Array.from(requestedByProduct.entries()).some(
            ([productCode, quantity]) =>
                quantity > (Number(STOCK_BY_PRODUCT_CODE[productCode]) || 0)
        );
    }

    function updateDiningVisibility(items) {
        const diningIsAvailable = hasDiningEligibleItems(items);
        diningOptionSection.hidden = !diningIsAvailable;

        if (!diningIsAvailable) {
            document.querySelector('input[name="dining"][value="takeout"]')
                .checked = true;
        }

        updateDiningOption();
    }

    function updateDiningOption() {
        const selectedDining = document.querySelector(
            'input[name="dining"]:checked'
        );

        const isDineIn = selectedDining &&
            selectedDining.value === 'dine_in';

        scheduleHeading.textContent = isDineIn
            ? 'Reservation Schedule'
            : 'Pickup Schedule';

        scheduleDescription.textContent = isDineIn
            ? 'Choose when you plan to dine in.'
            : 'Choose when you plan to pick up your order.';

        scheduleTimeLabel.textContent = isDineIn
            ? 'Reservation Time'
            : 'Pickup Time';

        scheduleTimeHelp.textContent = isDineIn
            ? 'Choose the time you plan to arrive. Tap the field to open the time picker; the time format depends on your device.'
            : 'Choose the time you plan to pick up your order. Tap the field to open the time picker; the time format depends on your device.';

        scheduleNote.textContent = isDineIn
            ? 'A reservation time is required and subject to canteen availability and confirmation.'
            : 'A pickup time is required and subject to canteen availability and confirmation.';
    }

    function updateNotesCount() {
        orderNotesCount.textContent = `${orderNotes.value.length}/500`;
    }

    function updatePaymentUI(resetConfirmation = false) {
        const selectedPayment = document.querySelector('input[name="payment"]:checked');
        const isQrPayment = selectedPayment && selectedPayment.value === 'instapay_qr';

        qrPaymentPanel.classList.toggle('show', Boolean(isQrPayment));
        if (resetConfirmation) {
            qrPaymentConfirmed = false;
            qrPaymentConfirmation.textContent =
                'Complete your payment, then confirm using the button below.';
        }

        updatePlaceOrderAvailability();
    }

    let isSubmittingOrder = false;

    function updatePlaceOrderAvailability() {
        const hasItems = getSelectedItems().length > 0;
        const selectedPayment = document.querySelector('input[name="payment"]:checked');
        const isQrPayment = selectedPayment && selectedPayment.value === 'instapay_qr';

        placeOrderButton.disabled =
            isSubmittingOrder ||
            !hasItems ||
            hasInsufficientStock(getSelectedItems());

        if (isSubmittingOrder) {
            placeOrderButton.innerHTML =
                '<span class="checkout-loading-spinner" aria-hidden="true"></span>' +
                '<span>Processing order...</span>';
            placeOrderButton.setAttribute('aria-busy', 'true');
        } else {
            placeOrderButton.removeAttribute('aria-busy');
            placeOrderButton.textContent = isQrPayment && !qrPaymentConfirmed
                ? "I've Paid"
                : 'Place Order';
        }
    }

    function renderCheckout() {
        const selectedItems = getSelectedItems();

        if (selectedItems.length === 0) {
            updateDiningVisibility(selectedItems);
            checkoutItems.innerHTML = `
                <p>No items selected for checkout.</p>
                <p>Return to your cart and select the food you want to order.</p>
            `;

            checkoutTotal.textContent = '₱0.00';
            updatePlaceOrderAvailability();

            checkoutMessage.textContent =
                'Please select at least one item in your cart.';

            return;
        }

        updateDiningVisibility(selectedItems);

        let total = 0;

        checkoutItems.innerHTML = selectedItems.map(function (item) {
            const name = escapeHTML(item.name);
            const price = Number(item.price);
            const quantity = Math.floor(Number(item.quantity));
            const subtotal = price * quantity;
            const productCode = productCodeForItem(item);
            const otherRequested = selectedItems.reduce(function (sum, otherItem) {
                return String(otherItem.id) === String(item.id) ||
                    productCodeForItem(otherItem) !== productCode
                    ? sum
                    : sum + Math.floor(Number(otherItem.quantity));
            }, 0);
            const availableForItem = Math.max(
                0,
                getStockForItem(item) - otherRequested
            );

            total += subtotal;

            const image = String(item.image || '');

            const imageMarkup = image
                ? `<img src="${escapeHTML(image)}" alt="${name}"
                        loading="lazy" onerror="this.hidden=true;">`
                : `<span class="checkout-image-placeholder">No image</span>`;

            return `
                <div class="checkout-item">
                    <div class="checkout-item-product">
                        <div class="checkout-item-image">
                            ${imageMarkup}
                        </div>

                        <div class="checkout-item-info">
                            <strong>${name}</strong>
                            <p>₱${price.toFixed(2)} each</p>
                            <p class="checkout-item-stock${getStockForItem(item) === 0
                                ? ' checkout-item-out-of-stock'
                                : ''}" role="status">
                                ${getStockForItem(item) > 0
                                    ? `${getStockForItem(item)} available`
                                    : 'Out of stock'}
                            </p>
                            <div class="checkout-quantity-control"
                                 aria-label="Quantity for ${name}">
                                <button
                                    type="button"
                                    data-quantity-action="decrease"
                                    data-item-id="${escapeHTML(item.id)}"
                                    aria-label="Decrease ${name} quantity"
                                    ${quantity <= 1 ? 'disabled' : ''}
                                >−</button>
                                <span aria-live="polite">${quantity}</span>
                                <button
                                    type="button"
                                    data-quantity-action="increase"
                                    data-item-id="${escapeHTML(item.id)}"
                                    aria-label="Increase ${name} quantity"
                                    ${quantity >= Math.min(99, availableForItem) ? 'disabled' : ''}
                                >+</button>
                            </div>
                        </div>
                    </div>

                    <strong>₱${subtotal.toFixed(2)}</strong>
                </div>
            `;
        }).join('');

        checkoutTotal.textContent = '₱' + total.toFixed(2);
        updatePlaceOrderAvailability();
        const hasOutOfStockItem = selectedItems.some(function (item) {
            return getStockForItem(item) === 0;
        });
        checkoutMessage.textContent = hasOutOfStockItem
            ? 'Some selected items are out of stock. Return to your cart to remove them before placing your order.'
            : (hasInsufficientStock(selectedItems)
                ? 'Your cart quantity exceeds current stock. Reduce quantities before placing your order.'
                : '');
    }

    checkoutItems.addEventListener('click', function (event) {
        const button = event.target.closest('button[data-quantity-action]');

        if (!button || button.disabled) {
            return;
        }

        const items = isBuyNow
            ? getSelectedItems()
            : getCart();
        const item = items.find(function (cartItem) {
            return String(cartItem.id) === button.dataset.itemId;
        });

        if (!item) {
            renderCheckout();
            return;
        }

        const change = button.dataset.quantityAction === 'increase' ? 1 : -1;
        const quantity = Number(item.quantity) + change;

        if (quantity < 1 || quantity > 99) {
            return;
        }
        if (change > 0) {
            const productCode = productCodeForItem(item);
            const selectedItems = items.filter(function (cartItem) {
                return cartItem.selected !== false;
            });
            const otherRequested = selectedItems.reduce(function (sum, otherItem) {
                return String(otherItem.id) === String(item.id) ||
                    productCodeForItem(otherItem) !== productCode
                    ? sum
                    : sum + Number(otherItem.quantity);
            }, 0);
            const available = getStockForItem(item) - otherRequested;
            if (quantity > available) {
                checkoutMessage.textContent = available > 0
                    ? `Only ${available} more piece(s) of ${item.name} can be added.`
                    : `${item.name} has no remaining stock.`;
                return;
            }
        }

        item.quantity = quantity;
        saveCheckoutItems(items);
        renderCheckout();
    });

    document.querySelectorAll('input[name="dining"]').forEach(function (radio) {
        radio.addEventListener('change', updateDiningOption);
    });

    orderNotes.addEventListener('input', updateNotesCount);

    document.querySelectorAll('input[name="payment"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            updatePaymentUI(true);
        });
    });

    placeOrderButton.addEventListener('click', async function () {
        const selectedItems = getSelectedItems();
        if (selectedItems.length === 0) {
            renderCheckout();
            return;
        }

        const paymentInput = document.querySelector('input[name="payment"]:checked');
        if (!paymentInput) {
            checkoutMessage.textContent = 'Please choose your payment method.';
            return;
        }

        if (paymentInput.value === 'instapay_qr' && !qrPaymentConfirmed) {
            qrPaymentConfirmed = true;
            qrPaymentConfirmation.textContent =
                'Thanks. You can now place your order. Staff must still verify the payment.';
            updatePlaceOrderAvailability();
            return;
        }

        const hasTime = scheduleTime.value !== '';

        if (!hasTime) {
            checkoutMessage.textContent =
                'Please choose a reservation or pickup time.';
            return;
        }

        const diningInput = document.querySelector('input[name="dining"]:checked');
        if ((!diningInput && hasDiningEligibleItems(selectedItems)) || !paymentInput) {
            checkoutMessage.textContent = 'Please choose your dining option and payment method.';
            return;
        }
        const diningValue = diningInput
            ? (diningInput.value === 'takeout' ? 'takeaway' : diningInput.value)
            : '';

        isSubmittingOrder = true;
        updatePlaceOrderAvailability();
        checkoutMessage.textContent = 'Checking stock and saving your order...';

        try {
            const response = await fetch('checkout.php?action=place_order', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    csrf_token: CSRF_TOKEN,
                    items: selectedItems.map(item => ({
                        id: String(item.id),
                        quantity: Math.floor(Number(item.quantity))
                    })),
                    schedule_time: scheduleTime.value,
                    dining_option: diningValue,
                    payment_method: paymentInput.value,
                    payment_confirmed: paymentInput.value === 'instapay_qr' && qrPaymentConfirmed,
                    notes: orderNotes.value.trim()
                })
            });

            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Unable to place your order. Please try again.');
            }

            if (isBuyNow) {
                sessionStorage.removeItem(BUY_NOW_KEY);
            } else {
                // Remove only the items that were ordered; keep unchecked cart items.
                const orderedIds = new Set(selectedItems.map(item => String(item.id)));
                const remainingCart = getCart().filter(item => !orderedIds.has(String(item.id)));
                localStorage.setItem(CART_KEY, JSON.stringify(remainingCart));
                window.dispatchEvent(new StorageEvent('storage', {
                    key: CART_KEY,
                    newValue: JSON.stringify(remainingCart)
                }));
            }

            // Keep the order details for the menu notification, then return to the menu.
            sessionStorage.setItem('purpleBlendOrderSuccess', JSON.stringify({
                order_code: result.order_code || '',
                message: result.message || 'Your order has been submitted. Please wait for Purple Blend to confirm it.'
            }));
            window.location.href = 'index.php#menu';
            return;
        } catch (error) {
            checkoutMessage.textContent = error.message || 'Something went wrong. Please try again.';
            isSubmittingOrder = false;
            updatePaymentUI(false);
            updatePlaceOrderAvailability();
        }
    });

    window.addEventListener('storage', function (event) {
        if (event.key === CART_KEY) {
            renderCheckout();
        }
    });

    updateDiningOption();
    updateNotesCount();
    updatePaymentUI(false);
    renderCheckout();
});
</script>

</body>
</html>
