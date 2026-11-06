
<?php
// Calculate sales totals and display completed orders for reporting.
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

// Sales summary
$totalSales = 0;
$monthSales = 0;
$completedOrders = 0;

// Only completed orders count as sales.
$result = $conn->query("
    SELECT
        COALESCE(SUM(CASE
            WHEN order_status = 'completed'
            THEN total_amount ELSE 0
        END), 0) AS total_sales,

        COALESCE(SUM(CASE
            WHEN order_status = 'completed'
            AND YEAR(created_at) = YEAR(CURDATE())
            AND MONTH(created_at) = MONTH(CURDATE())
            THEN total_amount ELSE 0
        END), 0) AS month_sales,

        SUM(CASE
            WHEN order_status = 'completed'
            THEN 1 ELSE 0
        END) AS completed_orders

    FROM orders
");

if ($result) {
    $summary = $result->fetch_assoc();

    $totalSales = (float) $summary['total_sales'];
    $monthSales = (float) $summary['month_sales'];
    $completedOrders = (int) $summary['completed_orders'];
}

// Recent completed orders
$recentSales = $conn->query("
    SELECT
        id,
        order_code,
        customer_name,
        total_amount,
        created_at
    FROM orders
    WHERE order_status = 'completed'
    ORDER BY created_at DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/Purple_Blend/Images/pic1.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales | Purple Blend Admin</title>

    <style>
        :root {
            --purple: #6F2B91;
            --dark-purple: #4E176B;
            --light-purple: #F7F0FB;
            --background: #FCFAFD;
            --text: #30243A;
            --muted: #81758A;
            --border: #EDE5F1;
            --white: #FFFFFF;
            --green: #18865B;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
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
            background: #441D56;
            color: white;
            padding: 28px 18px;
            flex-shrink: 0;
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

        .nav-label {
            font-size: 11px;
            color: #DCC7E9;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 22px 10px 12px;
        }

        .nav-link {
            display: block;
            padding: 12px 13px;
            margin-bottom: 6px;
            border-radius: 8px;
            color: #F2E9F7;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.2s;
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
            gap: 15px;
            margin-bottom: 28px;
            flex-wrap: wrap;
        }

        .topbar h1 {
            font-size: 27px;
            margin-bottom: 7px;
        }

        .subtitle {
            font-size: 14px;
            color: var(--muted);
        }

        .admin-label {
            background: var(--light-purple);
            color: var(--dark-purple);
            padding: 10px 14px;
            border-radius: 9px;
            font-size: 13px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 28px;
        }

        .summary-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 22px;
            box-shadow: 0 3px 12px rgba(78, 23, 107, 0.04);
        }

        .summary-title {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 15px;
        }

        .summary-value {
            font-size: 27px;
            font-weight: bold;
            color: var(--dark-purple);
            overflow-wrap: anywhere;
        }

        .summary-note {
            font-size: 12px;
            color: var(--muted);
            margin-top: 9px;
        }

        .section {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 13px;
            overflow: hidden;
        }

        .section-header {
            padding: 22px;
            border-bottom: 1px solid var(--border);
        }

        .section-header h2 {
            font-size: 18px;
            margin-bottom: 6px;
        }

        .section-header p {
            color: var(--muted);
            font-size: 13px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th,
        td {
            text-align: left;
            padding: 16px 22px;
            border-bottom: 1px solid var(--border);
            font-size: 13px;
        }

        th {
            background: #FCF9FE;
            color: var(--muted);
            font-weight: 600;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .order-code {
            color: var(--dark-purple);
            font-weight: bold;
        }

        .amount {
            font-weight: bold;
            color: var(--green);
            white-space: nowrap;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--muted);
            font-size: 14px;
        }

        @media (max-width: 1100px) {
            .summary-grid {
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
                padding: 20px 14px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .topbar h1 {
                font-size: 24px;
            }
        }
    </style>
    <link rel="stylesheet" href="css/mobile-navigation.css?v=1">
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
        <a class="nav-link" href="orders.php">Orders</a>
        <a class="nav-link" href="customers.php">Customers</a>
        <a class="nav-link active" href="sales.php">Sales</a>
        <a class="nav-link" href="account.php">Account</a>
        <a class="nav-link" href="logout.php">Log Out</a>
    </nav>
</aside>

    <main class="main">
        <div class="topbar">
            <div>
                <h1>Sales</h1>
                <p class="subtitle">
                    Money from orders marked as completed.
                </p>
            </div>

            <div class="admin-label">
                Signed in as <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?>
            </div>
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-title">Sales So Far</div>
                <div class="summary-value">
                    ₱<?= number_format($totalSales, 2) ?>
                </div>
                <div class="summary-note">From all completed orders</div>
            </div>

            <div class="summary-card">
                <div class="summary-title">Sales This Month</div>
                <div class="summary-value">
                    ₱<?= number_format($monthSales, 2) ?>
                </div>
                <div class="summary-note">Completed this month</div>
            </div>

            <div class="summary-card">
                <div class="summary-title">Orders Completed</div>
                <div class="summary-value">
                    <?= number_format($completedOrders) ?>
                </div>
                <div class="summary-note">Marked as completed</div>
            </div>
        </div>

        <section class="section">
            <div class="section-header">
                <h2>Latest Completed Orders</h2>
                <p>The 10 most recent orders marked as completed.</p>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Order Number</th>
                            <th>Customer</th>
                            <th>Order Total</th>
                            <th>Completed On</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if ($recentSales && $recentSales->num_rows > 0): ?>
                        <?php while ($sale = $recentSales->fetch_assoc()): ?>
                            <tr>
                                <td class="order-code">
                                    <?= htmlspecialchars($sale['order_code'] ?? '') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($sale['customer_name'] ?? 'Customer') ?>
                                </td>

                                <td class="amount">
                                    ₱<?= number_format((float) $sale['total_amount'], 2) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(date(
                                        'M d, Y h:i A',
                                        strtotime($sale['created_at'])
                                    )) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="empty-state">
                                No completed sales yet. Completed orders will appear here.
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
