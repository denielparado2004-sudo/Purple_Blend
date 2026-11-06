<?php
declare(strict_types=1);

/** Send a customer order email. Email failure is logged and never rolls back an order. */
function sendPurpleBlendOrderEmail(array $order, string $status, array $items = []): bool
{
    $email = trim((string)($order['customer_email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        error_log('Purple Blend order email skipped: invalid or missing customer email.');
        return false;
    }
    $root = dirname(__DIR__);
    $configPath = $root . '/email_config.php';
    $autoloadPath = $root . '/vendor/autoload.php';
    if (!is_file($configPath) || !is_file($autoloadPath)) {
        error_log('Purple Blend order email not sent: email_config.php or vendor/autoload.php is missing.');
        return false;
    }
    $config = require $configPath;
    if (!is_array($config) || empty($config['smtp_host']) || empty($config['smtp_username']) || empty($config['smtp_password']) || empty($config['from_email'])) {
        error_log('Purple Blend order email not sent: SMTP configuration is incomplete.');
        return false;
    }
    require_once $autoloadPath;
    $labels = ['pending' => 'Pending', 'preparing' => 'Preparing', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
    if (!isset($labels[$status])) return false;
    $orderCode = (string)($order['order_code'] ?? '');
    $name = (string)($order['customer_name'] ?? 'Customer');
    $total = number_format((float)($order['total_amount'] ?? 0), 2);
    $pickupDate = trim((string)($order['pickup_date'] ?? ''));
    $pickupTime = trim((string)($order['pickup_time'] ?? ''));
    $dining = (string)($order['dining_option'] ?? 'take-out') === 'dine_in' ? 'Dine-in' : 'Takeout';
    $schedule = $pickupTime !== ''
        ? ($pickupDate !== '' ? $pickupDate . ' ' : '') . substr($pickupTime, 0, 5)
        : 'Not specified';
    $scheduleLabel = $dining === 'Dine-in' ? 'Reservation time' : 'Pickup time';
    $statusLabel = $labels[$status];
    $safe = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $itemHtml = ''; $itemText = '';
    foreach ($items as $item) {
        $itemName = (string)($item['product_name'] ?? $item['name'] ?? 'Item');
        $quantity = (int)($item['quantity'] ?? 1);
        $lineTotal = number_format((float)($item['line_total'] ?? 0), 2);
        $itemHtml .= '<li>' . $safe($itemName) . ' × ' . $quantity . ' — ₱' . $lineTotal . '</li>';
        $itemText .= "- {$itemName} x{$quantity} - PHP {$lineTotal}\n";
    }
    $messages = [
        'pending' => 'We received your order. Please wait while Purple Blend reviews it.',
        'preparing' => 'Purple Blend has accepted your order and is preparing it.',
        'completed' => 'Your order has been completed. Thank you for ordering from Purple Blend!',
        'cancelled' => 'Your order has been cancelled. Please contact Purple Blend if you need more information.'
    ];
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP(); $mail->Host = (string)$config['smtp_host']; $mail->SMTPAuth = true;
        $mail->Username = (string)$config['smtp_username']; $mail->Password = (string)$config['smtp_password'];
        $mail->SMTPSecure = $config['smtp_secure'] ?? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)($config['smtp_port'] ?? 587); $mail->CharSet = 'UTF-8';
        $mail->setFrom((string)$config['from_email'], (string)($config['from_name'] ?? 'The Purple Blend'));
        $mail->addAddress($email, $name); $mail->isHTML(true);
        $mail->Subject = 'Purple Blend Order ' . $orderCode . ' — ' . $statusLabel;
        $mail->Body = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;color:#30253a">'
            . '<h2 style="color:#6f2b91">The Purple Blend</h2><p>Hello ' . $safe($name) . ',</p><p>' . $safe($messages[$status]) . '</p>'
            . '<p><strong>Order code:</strong> ' . $safe($orderCode) . '</p><p><strong>Status:</strong> ' . $safe($statusLabel) . '</p>'
            . ($itemHtml !== '' ? '<p><strong>Items</strong></p><ul>' . $itemHtml . '</ul>' : '')
            . '<p><strong>Total:</strong> ₱' . $total . '</p><p><strong>Order type:</strong> ' . $safe($dining) . '</p>'
            . '<p><strong>' . $safe($scheduleLabel) . ':</strong> ' . $safe($schedule) . '</p>'
            . '<hr><p style="font-size:12px;color:#665a70">Automated message from The Purple Blend. Please do not reply.</p></div>';
        $mail->AltBody = "The Purple Blend\nHello {$name},\n{$messages[$status]}\nOrder: {$orderCode}\nStatus: {$statusLabel}\n" . ($itemText !== '' ? "Items:\n{$itemText}" : '') . "Total: PHP {$total}\nType: {$dining}\n{$scheduleLabel}: {$schedule}\n";
        $mail->send(); return true;
    } catch (Throwable $e) {
        error_log('Purple Blend order email failed: ' . $e->getMessage()); return false;
    }
}
