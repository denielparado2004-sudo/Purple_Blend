
<?php


session_start();

require_once __DIR__ . '/../database.php';
require_once __DIR__ . '/../includes/order_email.php';

function redirectToOrders(string $message, string $type = 'error'): never
{
    $_SESSION['toast'] = [
        'type' => $type,
        'title' => $type === 'success' ? 'Order cancelled' : 'Unable to cancel order',
        'message' => $message
    ];
    header('Location: index.php#myOrders');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectToOrders('Invalid cancellation request.');
}

if (empty($_SESSION['customer_id'])) {
    redirectToOrders('Please log in to cancel an order.');
}

$customerId = (int) $_SESSION['customer_id'];
$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);

if (!$orderId || $orderId < 1) {
    redirectToOrders('Invalid order.');
}

$submittedToken = (string) ($_POST['cancel_csrf'] ?? '');
$sessionToken = (string) ($_SESSION['cancel_order_csrf'] ?? '');

if (
    $submittedToken === '' ||
    $sessionToken === '' ||
    !hash_equals($sessionToken, $submittedToken)
) {
    redirectToOrders('Invalid or expired request. Please try again.');
}

try {
    $conn->begin_transaction();

    // Verify that this order belongs to the logged-in customer.
    $stmt = $conn->prepare(
        "SELECT id, order_code, customer_id, order_status,
                customer_name, customer_email, total_amount,
                order_type, dining_option, pickup_date, pickup_time,
                TIMESTAMPDIFF(SECOND, created_at, CURRENT_TIMESTAMP) AS order_age_seconds
         FROM orders
         WHERE id = ? AND customer_id = ?
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bind_param('ii', $orderId, $customerId);
    $stmt->execute();

    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        $conn->rollback();
        redirectToOrders('Order not found.');
    }

    // Customers may cancel only pending orders.
    if (strtolower((string) $order['order_status']) !== 'pending') {
        $conn->rollback();
        redirectToOrders(
            'Only pending orders can be cancelled. Please contact Purple Blend for assistance.'
        );
    }

    if ((int) $order['order_age_seconds'] < 0 || (int) $order['order_age_seconds'] >= 300) {
        $conn->rollback();
        redirectToOrders(
            'Orders can only be cancelled within 5 minutes of being placed, while they are still pending.'
        );
    }

    $paymentStmt = $conn->prepare(
        'SELECT payment_status
         FROM payments
         WHERE order_id = ?
         ORDER BY id DESC
         LIMIT 1
         FOR UPDATE'
    );
    $paymentStmt->bind_param('i', $orderId);
    $paymentStmt->execute();
    $payment = $paymentStmt->get_result()->fetch_assoc();
    $paymentStmt->close();

    if ($payment && $payment['payment_status'] === 'paid') {
        $conn->rollback();
        redirectToOrders(
            'This order is already paid. Please contact the canteen vendor for help with a cancellation or refund.'
        );
    }

    // Retrieve actual item names and quantities, and lock their rows.
    $stmt = $conn->prepare(
        "SELECT menu_item_id, product_name, quantity, line_total
         FROM order_items
         WHERE order_id = ?
         FOR UPDATE"
    );

    $stmt->bind_param('i', $orderId);
    $stmt->execute();

    $result = $stmt->get_result();
    $items = [];

    while ($item = $result->fetch_assoc()) {
        $items[] = $item;
    }

    $stmt->close();

    if (!$items) {
        throw new RuntimeException('No order items were found.');
    }

    // Restore stock for the cancelled order.
    $restoreStock = $conn->prepare(
        "UPDATE menu_items
         SET stock = stock + ?
         WHERE id = ?"
    );

    foreach ($items as $item) {
        $quantity = (int) $item['quantity'];
        $menuItemId = (int) $item['menu_item_id'];

        if ($quantity < 1 || $menuItemId < 1) {
            throw new RuntimeException('Invalid order item data.');
        }

        $restoreStock->bind_param('ii', $quantity, $menuItemId);
        $restoreStock->execute();

        if ($restoreStock->affected_rows !== 1) {
            throw new RuntimeException('Could not restore item stock.');
        }
    }

    $restoreStock->close();

    // Mark the order as cancelled without requiring admin approval.
    $newStatus = 'cancelled';

    $stmt = $conn->prepare(
        "UPDATE orders
         SET order_status = ?
         WHERE id = ?
           AND customer_id = ?
           AND order_status = 'pending'
           AND created_at > DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 5 MINUTE)
           AND created_at <= CURRENT_TIMESTAMP"
    );

    $stmt->bind_param('sii', $newStatus, $orderId, $customerId);
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        $stmt->close();
        throw new RuntimeException('The order status could not be updated.');
    }

    $stmt->close();

    // Commit the cancellation and stock restoration together.
    $conn->commit();

    // Send the cancellation email with the actual order items.
    sendPurpleBlendOrderEmail($order, 'cancelled', $items);

    redirectToOrders('Your order has been cancelled successfully.', 'success');

} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
        error_log(
            'Purple Blend cancellation rollback failed: '
            . $rollbackError->getMessage()
        );
    }

    error_log('Purple Blend cancellation failed: ' . $e->getMessage());

    redirectToOrders('Cancellation failed. Please try again.');
}
