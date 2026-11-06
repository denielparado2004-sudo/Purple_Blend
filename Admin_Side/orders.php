
<?php
// Process order status changes and load orders for admin management.
session_start();

require_once __DIR__ . '/../database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$adminStatusStmt = $conn->prepare("SELECT is_active FROM admins WHERE id = ? LIMIT 1");
$adminId = (int) $_SESSION['admin_id'];
$adminStatusStmt->bind_param('i', $adminId);
$adminStatusStmt->execute();
$adminStatus = $adminStatusStmt->get_result()->fetch_assoc();
$adminStatusStmt->close();

if (!$adminStatus || (int) $adminStatus['is_active'] !== 1) {
    session_unset();
    session_destroy();
    header('Location: admin_login.php');
    exit;
}

$message = '';
$messageType = 'success';

// Simplified order workflow.
$allowedStatuses = ['pending', 'preparing', 'completed', 'cancelled'];
$statusTransitions = [
    'pending' => ['preparing', 'cancelled'],
    'preparing' => ['completed', 'cancelled'],
    'completed' => ['cancelled'],
    'cancelled' => ['pending', 'preparing', 'completed']
];
if (empty($_SESSION['admin_orders_csrf'])) {
    $_SESSION['admin_orders_csrf'] = bin2hex(random_bytes(32));
}

// Send an email only when the status actually changes.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $newStatus = $_POST['order_status'] ?? '';
    $paymentAction = $_POST['payment_action'] ?? '';
    $submittedToken = (string)($_POST['admin_orders_csrf'] ?? '');

    if (
        $submittedToken === '' ||
        !hash_equals($_SESSION['admin_orders_csrf'], $submittedToken)
    ) {
        $message = 'This request expired. Refresh the page and try again.';
        $messageType = 'error';
    } elseif (!$orderId || (
        $paymentAction !== 'mark_paid' &&
        !in_array($newStatus, $allowedStatuses, true)
    )) {
        $message = 'Invalid order or status.'; $messageType = 'error';
    } elseif ($paymentAction === 'mark_paid') {
        $conn->begin_transaction();
        $orderCheck = $conn->prepare(
            'SELECT order_status FROM orders WHERE id = ? LIMIT 1 FOR UPDATE'
        );
        $paymentCheck = $conn->prepare(
            'SELECT id, payment_method, payment_status
             FROM payments
             WHERE order_id = ?
             ORDER BY id DESC
             LIMIT 1
             FOR UPDATE'
        );

        if (!$orderCheck || !$paymentCheck) {
            $conn->rollback();
            $message = 'Unable to check this order payment.';
            $messageType = 'error';
        } else {
            $orderCheck->bind_param('i', $orderId);
            $orderCheck->execute();
            $orderForPayment = $orderCheck->get_result()->fetch_assoc();
            $orderCheck->close();

            $paymentCheck->bind_param('i', $orderId);
            $paymentCheck->execute();
            $payment = $paymentCheck->get_result()->fetch_assoc();
            $paymentCheck->close();

            if (!$orderForPayment || !$payment) {
                $conn->rollback();
                $message = 'Order or payment record not found.';
                $messageType = 'error';
            } elseif (in_array($orderForPayment['order_status'], ['completed', 'cancelled'], true)) {
                $conn->rollback();
                $message = 'Payment cannot be changed for a completed or cancelled order.';
                $messageType = 'error';
            } elseif ($payment['payment_status'] === 'paid') {
                $conn->rollback();
                $message = 'This payment is already marked as paid.';
                $messageType = 'error';
            } elseif ($payment['payment_status'] !== 'pending') {
                $conn->rollback();
                $message = 'Only payments waiting for confirmation can be marked as paid.';
                $messageType = 'error';
            } else {
                $paymentId = (int)$payment['id'];
                $paymentUpdate = $conn->prepare(
                    "UPDATE payments
                     SET payment_status = 'paid',
                         paid_at = COALESCE(paid_at, CURRENT_TIMESTAMP)
                     WHERE id = ? AND payment_status = 'pending'"
                );
                if ($paymentUpdate) {
                    $paymentUpdate->bind_param('i', $paymentId);
                }

                if (!$paymentUpdate) {
                    $conn->rollback();
                    $message = 'Unable to prepare the payment update.';
                    $messageType = 'error';
                } else {
                    $updatedPayment = $paymentUpdate->execute() &&
                        $paymentUpdate->affected_rows === 1;
                    $paymentUpdate->close();

                    if (!$updatedPayment) {
                        $conn->rollback();
                        $message = 'Payment was not updated. Refresh and check the order again.';
                        $messageType = 'error';
                    } elseif (!$conn->commit()) {
                        $conn->rollback();
                        $message = 'Unable to save the payment confirmation.';
                        $messageType = 'error';
                    } else {
                        $message = 'Payment marked as paid. You can now complete the order after handing it to the customer.';
                    }
                }
            }
        }
    } else {
        $conn->begin_transaction();
        $orderCheck = $conn->prepare('SELECT id, order_code, customer_name, customer_email, order_status, total_amount, pickup_date, pickup_time, dining_option FROM orders WHERE id = ? LIMIT 1 FOR UPDATE');
        if (!$orderCheck) {
            $conn->rollback(); $message = 'Unable to prepare the order lookup.'; $messageType = 'error';
        } else {
            $orderCheck->bind_param('i', $orderId);
            $orderCheck->execute(); $order = $orderCheck->get_result()->fetch_assoc(); $orderCheck->close();
            if (!$order) {
                $conn->rollback(); $message = 'Order not found.'; $messageType = 'error';
            } elseif ($order['order_status'] === $newStatus) {
                $conn->rollback(); $message = 'The order already has that status. No email was sent.';
            } elseif (!in_array($newStatus, $statusTransitions[$order['order_status']] ?? [], true)) {
                $conn->rollback();
                $message = 'Choose one of the available next steps for this order.';
                $messageType = 'error';
            } else {
                $orderStmt = $conn->prepare('UPDATE orders SET order_status = ? WHERE id = ?');
                if (!$orderStmt) {
                    $conn->rollback(); $message = 'Unable to prepare the order update.'; $messageType = 'error';
                } else {
                    $orderStmt->bind_param('si', $newStatus, $orderId); $updated = $orderStmt->execute(); $orderStmt->close();
                    if (!$updated) {
                        $conn->rollback(); $message = 'Unable to update the order status.'; $messageType = 'error';
                    } elseif ($newStatus === 'completed') {
                        $paymentStmt = $conn->prepare("UPDATE payments SET payment_status = 'paid', paid_at = COALESCE(paid_at, CURRENT_TIMESTAMP) WHERE order_id = ? AND payment_method = 'cash' AND payment_status = 'pending'");
                        if (!$paymentStmt) {
                            $conn->rollback(); $message = 'Unable to prepare the payment update.'; $messageType = 'error';
                        } else {
                            $paymentStmt->bind_param('i', $orderId); $paymentUpdated = $paymentStmt->execute(); $paymentStmt->close();
                            $paidCheck = $conn->prepare("SELECT id FROM payments WHERE order_id = ? AND payment_status = 'paid' LIMIT 1");
                            if (!$paymentUpdated || !$paidCheck) {
                                $conn->rollback(); $message = 'Unable to confirm the order payment.'; $messageType = 'error';
                            } else {
                                $paidCheck->bind_param('i', $orderId); $paymentResult = $paidCheck->execute() ? $paidCheck->get_result() : false;
                                $hasPaidPayment = $paymentResult && $paymentResult->num_rows > 0; $paidCheck->close();
                                if (!$hasPaidPayment) {
                                    $conn->rollback(); $message = 'Payment is not marked as paid. Check the QR payment proof and mark it as paid before completing this order.'; $messageType = 'error';
                                } elseif (!$conn->commit()) {
                                    $conn->rollback(); $message = 'Unable to save the completed order.'; $messageType = 'error';
                                } else {
                                    require_once __DIR__ . '/../includes/order_email.php'; $order['order_status'] = $newStatus;
                                    sendPurpleBlendOrderEmail($order, $newStatus); $message = 'Order completed. Customer notification attempted.';
                                }
                            }
                        }
                    } elseif ($conn->commit()) {
                        require_once __DIR__ . '/../includes/order_email.php'; $order['order_status'] = $newStatus;
                        sendPurpleBlendOrderEmail($order, $newStatus); $message = 'Order status updated. Customer notification attempted.';
                    } else {
                        $conn->rollback(); $message = 'Unable to save the order status.'; $messageType = 'error';
                    }
                }
            }
        }
    }
}

// Search orders
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$sql = "
    SELECT
        id,
        order_code,
        customer_name,
        customer_email,
        customer_phone,
        order_type,
        dining_option,
        order_status,
        pickup_date,
        pickup_time,
        total_amount,
        created_at,
        (SELECT payment_method FROM payments WHERE payments.order_id = orders.id ORDER BY id DESC LIMIT 1) AS payment_method,
        (SELECT payment_status FROM payments WHERE payments.order_id = orders.id ORDER BY id DESC LIMIT 1) AS payment_status
    FROM orders
    WHERE 1 = 1
";

$params = [];
$types = '';

if (in_array($statusFilter, $allowedStatuses, true)) {
    $sql .= " AND order_status = ?";
    $params[] = $statusFilter;
    $types .= 's';
} else {
    $statusFilter = '';
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$orders = $stmt->get_result();

// Order summary
$summaryResult = $conn->query("
    SELECT
        COUNT(*) AS total_orders,
        COALESCE(SUM(order_status = 'pending'), 0) AS pending_orders,
        COALESCE(SUM(order_status = 'preparing'), 0) AS preparing_orders,
        COALESCE(SUM(order_status = 'completed'), 0) AS completed_orders
    FROM orders
");

$summary = $summaryResult->fetch_assoc();

function statusClass($status)
{
    switch ($status) {
        case 'pending':
            return 'pending';
        case 'confirmed':
            return 'confirmed';
        case 'preparing':
            return 'preparing';
        case 'ready':
            return 'ready';
        case 'completed':
            return 'completed';
        case 'cancelled':
            return 'cancelled';
        default:
            return 'pending';
    }
}

function displayOrderStatus($status)
{
    $labels = [
        'pending' => 'New - needs action',
        'confirmed' => 'Accepted',
        'preparing' => 'Being prepared',
        'ready' => 'Ready for pickup',
        'completed' => 'Picked up',
        'cancelled' => 'Cancelled'
    ];

    return $labels[strtolower((string)$status)] ?? ucfirst((string)$status);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/Purple_Blend/Images/pic1.jpg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Orders | Purple Blend</title>

<style>
:root {
    --purple: #6F2B91;
    --dark-purple: #4E176B;
    --light-purple: #F7F0FB;
    --background: #FCFAFD;
    --text: #30243A;
    --muted: #81758A;
    --border: #EAE2EF;
    --green: #218653;
    --orange: #B76B00;
    --red: #C73939;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: var(--background);
    color: var(--text);
}

.layout {
    display: flex;
    min-height: 100vh;
}

.sidebar {
    position: sticky;
    top: 0;
    align-self: flex-start;
    height: 100vh;
    overflow-y: auto;
    width: 245px;
    flex-shrink: 0;
    background: #441D56;
    color: white;
    padding: 28px 18px;
}

.brand {
    display: block;
    width: calc(100% + 36px);
    margin: -28px -18px 35px;
}

.brand-logo {
    display: block;
    width: 100%;
    height: auto;
}

.nav-link {
    display: block;
    padding: 12px 13px;
    margin-bottom: 6px;
    color: #F2E9F7;
    text-decoration: none;
    border-radius: 8px;
    font-size: 14px;
}

.nav-link:hover,
.nav-link.active {
    background: var(--purple);
    color: white;
}

.main {
    flex: 1;
    min-width: 0;
    padding: 32px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 28px;
}

h1 {
    margin: 0 0 8px;
    font-size: 28px;
}

.subtitle {
    margin: 0;
    color: var(--muted);
    font-size: 14px;
}

.admin-label {
    color: var(--purple);
    background: var(--light-purple);
    padding: 10px 14px;
    border-radius: 9px;
    font-size: 13px;
    white-space: nowrap;
}

.summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 26px;
}

.summary-card {
    background: white;
    padding: 20px;
    border: 1px solid var(--border);
    border-radius: 12px;
}

.summary-card p {
    color: var(--muted);
    font-size: 13px;
    margin: 0 0 12px;
}

.summary-card h2 {
    margin: 0;
    font-size: 27px;
}

.panel {
    background: white;
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    padding: 20px;
    border-bottom: 1px solid var(--border);
}

.panel-header h2 {
    margin: 0;
    font-size: 18px;
}

.filters {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.search-input,
.status-filter {
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font: inherit;
    font-size: 13px;
    outline-color: var(--purple);
}

.search-input {
    width: 230px;
}

.button {
    border: 0;
    background: var(--purple);
    color: white;
    padding: 10px 14px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
}

.button:hover {
    background: var(--dark-purple);
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

th,
td {
    padding: 15px 16px;
    text-align: left;
    border-bottom: 1px solid #F0EAF3;
    font-size: 13px;
    vertical-align: middle;
}

th {
    background: #FBF8FD;
    color: var(--muted);
    font-size: 12px;
    white-space: nowrap;
}

tbody tr:last-child td {
    border-bottom: 0;
}

.order-code {
    color: var(--purple);
    font-weight: bold;
}

.customer-name {
    font-weight: 600;
}

.small-text {
    display: block;
    color: var(--muted);
    font-size: 11px;
    margin-top: 5px;
}

.amount {
    font-weight: bold;
    white-space: nowrap;
}

.status {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
    white-space: nowrap;
    text-transform: capitalize;
}

.status.pending {
    background: #FFF3DC;
    color: #946000;
}

.status.confirmed {
    background: #E8F0FF;
    color: #2458A6;
}

.status.preparing {
    background: #F0E6FF;
    color: #7040A0;
}

.status.ready {
    background: #E0F7F5;
    color: #087E75;
}

.status.completed {
    background: #E8F7EE;
    color: var(--green);
}

.status.cancelled {
    background: #FDEAEA;
    color: var(--red);
}

.status-form {
    display: flex;
    align-items: center;
    gap: 6px;
}

.status-form select {
    max-width: 130px;
    padding: 8px;
    border: 1px solid var(--border);
    border-radius: 7px;
    background: white;
    font-size: 12px;
}

.save-button {
    padding: 9px 10px;
}

.payment-verify-form {
    display: grid;
    justify-items: start;
    gap: 5px;
    margin-top: 8px;
}

.payment-verify-button {
    padding: 7px 9px;
    background: #218653;
    font-size: 11px;
}

.payment-verify-button:hover {
    background: #176b40;
}

.message {
    margin-bottom: 20px;
    padding: 13px 16px;
    border-radius: 8px;
    font-size: 13px;
}

.message.success {
    background: #E8F7EE;
    color: #176A40;
}

.message.error {
    background: #FDEAEA;
    color: #A32626;
}

.empty {
    text-align: center;
    color: var(--muted);
    padding: 35px;
}

@media (max-width: 1050px) {
    .summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .main {
        padding: 24px;
    }
}

@media (max-width: 650px) {
    .layout {
        display: block;
    }

    .sidebar {
        width: 100%;
        padding: 20px;
    }

    .brand {
        width: calc(100% + 40px);
        margin: -20px -20px 20px;
    }

    .main {
        padding: 18px;
    }

    .topbar {
        align-items: flex-start;
        flex-direction: column;
    }

    .summary {
        gap: 10px;
    }

    .summary-card {
        padding: 15px;
    }

    .summary-card h2 {
        font-size: 23px;
    }

    .filters {
        width: 100%;
    }

    .search-input {
        width: 100%;
        min-width: 0;
    }
}
    </style>
    <link rel="stylesheet" href="css/mobile-navigation.css?v=1">
    <script src="js/live-search.js?v=3" defer></script>
    <script src="js/mobile-navigation.js?v=1" defer></script>
</head>

<body>
<div class="layout">

<aside class="sidebar">
    <a class="brand" href="dashboard.php" aria-label="Purple Blend admin dashboard">
        <img class="brand-logo" src="../Images/purple-blend-logo.jpg" alt="Purple Blend">
    </a>

    <nav>
        <a class="nav-link" href="dashboard.php">Home</a>
        <a class="nav-link" href="products.php">Menu</a>
        <a class="nav-link" href="inventory.php">Stock</a>
        <a class="nav-link active" href="orders.php">Orders</a>
        <a class="nav-link" href="customers.php">Customers</a>
        <a class="nav-link" href="sales.php">Sales</a>
        <a class="nav-link" href="account.php">Account</a>
        <a class="nav-link" href="logout.php">Log Out</a>
    </nav>
</aside>

<main class="main">

<header class="topbar">
    <div>
        <h1>Orders</h1>
        <p class="subtitle">
            Review new orders and update each order as you prepare it.
        </p>
    </div>

    <div class="admin-label">
        Signed in:
        <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?>
    </div>
</header>

<?php if ($message !== ''): ?>
    <div class="message <?= htmlspecialchars($messageType) ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<section class="summary">
    <div class="summary-card">
        <p>All Orders</p>
        <h2><?= number_format((int)$summary['total_orders']) ?></h2>
    </div>

    <div class="summary-card">
        <p>New - Needs Action</p>
        <h2><?= number_format((int)$summary['pending_orders']) ?></h2>
    </div>

    <div class="summary-card">
        <p>Being Prepared</p>
        <h2><?= number_format((int)$summary['preparing_orders']) ?></h2>
    </div>

    <div class="summary-card">
        <p>Finished Orders</p>
        <h2><?= number_format((int)$summary['completed_orders']) ?></h2>
    </div>
</section>

<section class="panel">

    <div class="panel-header">
        <h2>All Orders</h2>

        <form class="filters" method="GET" data-admin-live-search>
            <input
                class="search-input"
                type="search"
                name="search"
                maxlength="100"
                data-admin-live-search-input
                placeholder="Find an order or customer..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <select class="status-filter" name="status">
                <option value="">Show all orders</option>
                <?php foreach ($allowedStatuses as $status): ?>
                    <option
                        value="<?= htmlspecialchars($status) ?>"
                        <?= $statusFilter === $status ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars(displayOrderStatus($status)) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button class="button" type="submit">Show Orders</button>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Order Number / Time</th>
                <th>Customer</th>
                <th>Order Method</th>
                <th>Pickup Time</th>
                <th>Order Total</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Next Step</th>
            </tr>
            </thead>

            <tbody>
            <?php if ($orders && $orders->num_rows > 0): ?>
                <?php while ($order = $orders->fetch_assoc()): ?>
                    <?php
                    $paymentMethod = (string)($order['payment_method'] ?? '');
                    $paymentStatus = (string)($order['payment_status'] ?? '');
                    $canMarkPaymentPaid = $paymentStatus === 'pending' &&
                        !in_array($order['order_status'], ['completed', 'cancelled'], true);
                    $paymentMethodLabel = in_array($paymentMethod, ['online', 'instapay_qr'], true)
                        ? 'Online'
                        : ($paymentMethod === 'cash' ? 'Cash' : 'Unknown type');
                    ?>
                    <tr data-admin-search-row="<?= htmlspecialchars(
                        $order['order_code'] . ' ' .
                        $order['customer_name'] . ' ' .
                        ($order['customer_email'] ?? '') . ' ' .
                        ($order['customer_phone'] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>">
                        <td>
                            <span class="order-code">
                                <?= htmlspecialchars($order['order_code']) ?>
                            </span>
                            <span class="small-text">
                                <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?>
                            </span>
                        </td>

                        <td>
                            <span class="customer-name">
                                <?= htmlspecialchars($order['customer_name']) ?>
                            </span>

                            <?php if (!empty($order['customer_email'])): ?>
                                <span class="small-text">
                                    <?= htmlspecialchars($order['customer_email']) ?>
                                </span>
                            <?php endif; ?>

                            <?php if (!empty($order['customer_phone'])): ?>
                                <span class="small-text">
                                    <?= htmlspecialchars($order['customer_phone']) ?>
                                </span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= in_array(strtolower($order['order_type']), ['walk_in', 'walk-in'], true)
                                ? 'In person'
                                : 'Online' ?>
                            <span class="small-text">
                                <?= in_array(strtolower($order['dining_option']), ['takeaway', 'takeout', 'take-out'], true)
                                    ? 'Take-out'
                                    : ucfirst(str_replace('_', ' ', $order['dining_option'])) ?>
                            </span>
                        </td>

                        <td>
                            <?php if (!empty($order['pickup_date'])): ?>
                                <?= date('M j, Y', strtotime($order['pickup_date'])) ?>
                            <?php endif; ?>
                            <?php if (!empty($order['pickup_time'])): ?>
                                <span class="small-text">
                                    <?= date('g:i A', strtotime($order['pickup_time'])) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (empty($order['pickup_date']) && empty($order['pickup_time'])): ?>
                                <span class="small-text">Not scheduled</span>
                            <?php endif; ?>
                        </td>

                        <td class="amount">
                            ₱<?= number_format((float)$order['total_amount'], 2) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($paymentMethodLabel, ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($canMarkPaymentPaid): ?>
                                <form class="payment-verify-form" method="POST"
                                      onsubmit="return confirm('Have you received this payment? Mark it as paid?');">
                                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                    <input type="hidden" name="payment_action" value="mark_paid">
                                    <input type="hidden" name="admin_orders_csrf"
                                           value="<?= htmlspecialchars($_SESSION['admin_orders_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                                    <button class="button payment-verify-button" type="submit">
                                        Mark as Paid
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="status <?= statusClass($order['order_status']) ?>">
                                <?= htmlspecialchars(displayOrderStatus($order['order_status'])) ?>
                            </span>
                        </td>

                        <td>
                            <form class="status-form" method="POST">
                                <input
                                    type="hidden"
                                    name="order_id"
                                    value="<?= (int)$order['id'] ?>"
                                >
                                <input
                                    type="hidden"
                                    name="admin_orders_csrf"
                                    value="<?= htmlspecialchars($_SESSION['admin_orders_csrf'], ENT_QUOTES, 'UTF-8') ?>"
                                >

                                <select name="order_status" required>
                                    <option value="" selected disabled>Choose the next step</option>
                                    <?php foreach ($allowedStatuses as $status): ?>
                                        <option
                                            value="<?= htmlspecialchars($status) ?>"
                                            <?= in_array($status, $statusTransitions[$order['order_status']] ?? [], true) ? '' : 'disabled' ?>
                                        >
                                            <?= htmlspecialchars(displayOrderStatus($status)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <button class="button save-button" type="submit">
                                Update
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="empty">
                        No matching orders. Try another search or status.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</section>

</main>
</div>
</body>
</html>
