
<?php
// Retrieve and display orders belonging to the signed-in customer.
session_start();

require_once "../database.php";

// Only logged-in customers can view their orders.
if (!isset($_SESSION["customer_id"])) {
    header("Location: login.php");
    exit;
}

$customerId = (int) $_SESSION["customer_id"];

// Retrieve only this customer's orders.
$sql = "SELECT
            id,
            order_code,
            order_type,
            dining_option,
            order_status,
            pickup_date,
            pickup_time,
            subtotal,
            total_amount,
            created_at
        FROM orders
        WHERE customer_id = ?
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customerId);
$stmt->execute();

$result = $stmt->get_result();

function escapeOutput($value) {
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}

function formatStatus($status) {
    $statuses = [
        "pending"   => "Pending Confirmation",
        "confirmed" => "Confirmed",
        "preparing" => "Preparing",
        "ready"     => "Ready for Pickup",
        "completed" => "Completed",
        "cancelled" => "Cancelled",
        "canceled"  => "Cancelled"
    ];

    return $statuses[strtolower($status)] ?? ucfirst($status);
}

function statusClass($status) {
    $status = strtolower($status);

    $classes = [
        "pending"   => "pending",
        "confirmed" => "confirmed",
        "preparing" => "preparing",
        "ready"     => "ready",
        "completed" => "completed",
        "cancelled" => "cancelled",
        "canceled"  => "cancelled"
    ];

    return $classes[$status] ?? "pending";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/Purple_Blend/Images/pic1.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | Purple Blend</title>

    <link rel="stylesheet" href="css/index.css?v=55">

    <style>
        :root {
            --purple: #6F2B91;
            --dark-purple: #4E176B;
            --light-purple: #F7F0FB;
            --page-bg: #FCFAFD;
            --text: #30263A;
            --muted: #766D7D;
            --border: #E9DFEF;
        }

        body {
            margin: 0;
            background: var(--page-bg);
            color: var(--text);
            font-family: Arial, sans-serif;
        }

        .orders-page {
            width: min(1050px, 92%);
            margin: 38px auto;
            min-height: 65vh;
        }

        .page-heading {
            margin-bottom: 26px;
        }

        .page-heading h1 {
            margin: 0 0 8px;
            color: var(--dark-purple);
            font-size: 30px;
        }

        .page-heading p {
            margin: 0;
            color: var(--muted);
            line-height: 1.5;
        }

        .orders-list {
            display: grid;
            gap: 18px;
        }

        .order-card {
            padding: 22px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 4px 16px rgba(78, 23, 107, 0.05);
        }

        .order-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            flex-wrap: wrap;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .order-code {
            margin: 0 0 6px;
            color: var(--dark-purple);
            font-size: 18px;
            overflow-wrap: anywhere;
        }

        .order-date {
            color: var(--muted);
            font-size: 13px;
        }

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
        }

        .status.pending {
            background: #FFF3D6;
            color: #8A5A00;
        }

        .status.confirmed {
            background: #E8F1FF;
            color: #2459A9;
        }

        .status.preparing {
            background: #F1E8FF;
            color: #6635A3;
        }

        .status.ready {
            background: #E2F7E9;
            color: #217443;
        }

        .status.completed {
            background: #E8F5F0;
            color: #216B55;
        }

        .status.cancelled {
            background: #FCE8E8;
            color: #A52828;
        }

        .order-details {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            padding: 18px 0;
        }

        .detail-label {
            display: block;
            margin-bottom: 6px;
            color: var(--muted);
            font-size: 12px;
        }

        .detail-value {
            font-size: 14px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .order-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }

        .total-label {
            color: var(--muted);
            font-size: 13px;
        }

        .total-amount {
            margin-top: 5px;
            color: var(--dark-purple);
            font-size: 22px;
            font-weight: bold;
        }

        .browse-button {
            display: inline-block;
            padding: 11px 17px;
            background: var(--purple);
            color: #fff;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }

        .browse-button:hover {
            background: var(--dark-purple);
        }

        .empty-state {
            padding: 45px 20px;
            background: #fff;
            border: 1px dashed #D9C6E5;
            border-radius: 14px;
            text-align: center;
        }

        .empty-state h2 {
            margin: 0 0 10px;
            color: var(--dark-purple);
        }

        .empty-state p {
            margin: 0 0 20px;
            color: var(--muted);
        }

        @media (max-width: 650px) {
            .orders-page {
                margin: 25px auto;
            }

            .page-heading h1 {
                font-size: 25px;
            }

            .order-card {
                padding: 16px;
            }

            .order-details {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 18px 12px;
            }
        }
    </style>
</head>

<body>
    <?php include "includes/header.php"; ?>

    <main class="orders-page">
        <div class="page-heading">
            <h1>My Orders</h1>
            <p>View your orders and check their latest status.</p>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <div class="orders-list">
                <?php while ($order = $result->fetch_assoc()): ?>
                    <article class="order-card">
                        <div class="order-top">
                            <div>
                                <h2 class="order-code">
                                    <?= escapeOutput($order["order_code"]) ?>
                                </h2>

                                <div class="order-date">
                                    Ordered on
                                    <?= escapeOutput(date(
                                        "M d, Y · h:i A",
                                        strtotime($order["created_at"])
                                    )) ?>
                                </div>
                            </div>

                            <span class="status <?= escapeOutput(statusClass($order["order_status"])) ?>">
                                <?= escapeOutput(formatStatus($order["order_status"])) ?>
                            </span>
                        </div>

                        <div class="order-details">
                            <div>
                                <span class="detail-label">Order Type</span>
                                <span class="detail-value">
                                    <?= escapeOutput(ucwords(str_replace("_", " ", $order["order_type"]))) ?>
                                </span>
                            </div>

                            <div>
                                <span class="detail-label">Dining Option</span>
                                <span class="detail-value">
                                    <?= escapeOutput(in_array(strtolower($order["dining_option"]), ["takeaway", "takeout", "take-out"], true)
                                        ? "Take-out"
                                        : ucwords(str_replace("_", " ", $order["dining_option"]))) ?>
                                </span>
                            </div>

                            <div>
                                <span class="detail-label">Scheduled Time</span>
                                <span class="detail-value">
                                    <?php if (!empty($order["pickup_date"])): ?>
                                        <?= escapeOutput(date("M d, Y", strtotime($order["pickup_date"]))) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($order["pickup_time"])): ?>
                                        <?php if (!empty($order["pickup_date"])): ?><br><?php endif; ?>
                                        <?= escapeOutput(date("h:i A", strtotime($order["pickup_time"]))) ?>
                                    <?php endif; ?>
                                    <?php if (empty($order["pickup_date"]) && empty($order["pickup_time"])): ?>
                                        Not scheduled
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <div class="order-bottom">
                            <div>
                                <div class="total-label">Order Total</div>
                                <div class="total-amount">
                                    ₱<?= number_format((float) $order["total_amount"], 2) ?>
                                </div>
                            </div>

                            <a href="index.php#menu" class="browse-button">
                                Browse Menu
                            </a>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h2>No orders yet</h2>
                <p>Your placed orders will appear here.</p>
                <a href="index.php#menu" class="browse-button">
                    Browse Menu
                </a>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
