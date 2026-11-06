
<?php
// Load the key store metrics and recent activity for the admin dashboard.
session_start();

require_once __DIR__ . '/../database.php';

// Protect the dashboard from unauthorized access.
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
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

$adminToast = $_SESSION['admin_toast'] ?? null;
unset($_SESSION['admin_toast']);

// Get dashboard statistics.
$stats = [
    'products' => 0,
    'orders' => 0,
    'customers' => 0,
    'sales' => 0
];

$result = $conn->query("SELECT COUNT(*) AS total FROM menu_items");
if ($result) {
    $stats['products'] = (int) $result->fetch_assoc()['total'];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM orders");
if ($result) {
    $stats['orders'] = (int) $result->fetch_assoc()['total'];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM customers");
if ($result) {
    $stats['customers'] = (int) $result->fetch_assoc()['total'];
}

$result = $conn->query(
    "SELECT COALESCE(SUM(total_amount), 0) AS total
     FROM orders
     WHERE order_status = 'completed'"
);

if ($result) {
    $stats['sales'] = (float) $result->fetch_assoc()['total'];
}

// Get recent orders.
$recentOrders = $conn->query(
    "SELECT id, order_code, customer_name, total_amount,
            order_status, created_at
     FROM orders
     ORDER BY created_at DESC
     LIMIT 5"
);

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function statusClass($status) {
    switch ($status) {
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/Purple_Blend/Images/pic1.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Overview | Purple Blend Admin</title>

    <style>
        :root {
            --purple: #6F2B91;
            --dark-purple: #4E176B;
            --light-purple: #F7F0FB;
            --background: #FCFAFD;
            --text: #30243A;
            --muted: #81758A;
            --border: #EEE7F2;
            --white: #FFFFFF;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--background);
            color: var(--text);
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            display: flex;
            flex-direction: column;
            width: 245px;
            padding: 28px 18px;
            background: #441D56;
            color: white;
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
            margin-bottom: 6px;
            padding: 12px 13px;
            border-radius: 8px;
            color: #F2E9F7;
            font-size: 14px;
            text-decoration: none;
        }

        .nav-link:hover,
        .nav-link.active {
            background: var(--purple);
            color: white;
        }

        .nav-label {
            margin: 0 12px 12px;
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        .nav-links {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .nav-links a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 12px;
            border-radius: 9px;
            color: #6E6178;
            font-size: 14px;
            text-decoration: none;
            transition: background 0.2s, color 0.2s;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background: var(--light-purple);
            color: var(--purple);
        }

        .nav-links a.active {
            font-weight: 700;
        }

        .nav-icon {
            width: 22px;
            text-align: center;
            font-size: 17px;
        }

        .sidebar-bottom {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .admin-info {
            margin-bottom: 12px;
            padding: 10px 8px;
            overflow-wrap: anywhere;
        }

        .admin-info strong {
            display: block;
            font-size: 14px;
        }

        .admin-info small {
            display: block;
            margin-top: 5px;
            color: var(--muted);
            font-size: 12px;
            text-transform: capitalize;
        }

        .logout-link {
            display: block;
            padding: 12px;
            border-radius: 8px;
            color: #B42332;
            font-size: 14px;
            text-decoration: none;
        }

        .logout-link:hover {
            background: #FFF0F1;
        }

        /* Main content */
        .main {
            width: calc(100% - 245px);
            min-height: 100vh;
            margin-left: 245px;
            padding: 34px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 32px;
        }

        .page-heading h1 {
            margin: 0;
            font-size: 28px;
            letter-spacing: -0.8px;
        }

        .page-heading p {
            margin: 9px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .date-label {
            padding: 11px 14px;
            border: 1px solid var(--border);
            border-radius: 9px;
            background: var(--white);
            color: #6E6178;
            font-size: 12px;
            white-space: nowrap;
        }

        .welcome-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
            padding: 25px 28px;
            border-radius: 14px;
            background: linear-gradient(
                115deg,
                var(--dark-purple),
                var(--purple)
            );
            color: var(--white);
        }

        .welcome-banner h2 {
            margin: 0 0 10px;
            font-size: 21px;
        }

        .welcome-banner p {
            margin: 0;
            color: #EADCF3;
            font-size: 13px;
            line-height: 1.6;
        }

        .welcome-symbol {
            display: grid;
            flex-shrink: 0;
            width: 64px;
            height: 64px;
            place-items: center;
            border: 1px solid #FFFFFF45;
            border-radius: 18px;
            background: #FFFFFF15;
            font-size: 30px;
        }

        /* Summary cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }

        .stat-card {
            min-width: 0;
            padding: 21px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--white);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 20px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 12px;
        }

        .stat-icon {
            display: grid;
            width: 36px;
            height: 36px;
            place-items: center;
            border-radius: 10px;
            background: var(--light-purple);
            color: var(--purple);
            font-size: 17px;
        }

        .stat-value {
            overflow-wrap: anywhere;
            font-size: clamp(21px, 2vw, 29px);
            font-weight: 800;
            letter-spacing: -0.7px;
        }

        .stat-description {
            margin-top: 9px;
            color: var(--muted);
            font-size: 11px;
        }

        /* Recent orders */
        .section-card {
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--white);
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 22px;
            border-bottom: 1px solid var(--border);
        }

        .section-header h2 {
            margin: 0;
            font-size: 17px;
        }

        .section-header p {
            margin: 7px 0 0;
            color: var(--muted);
            font-size: 12px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            white-space: nowrap;
        }

        th {
            padding: 14px 20px;
            background: #FDFBFE;
            color: var(--muted);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        td {
            padding: 17px 20px;
            border-top: 1px solid #F2EDF5;
            font-size: 13px;
        }

        .order-code {
            color: var(--purple);
            font-weight: 700;
        }

        .status {
            display: inline-block;
            padding: 6px 9px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
            text-transform: capitalize;
        }

        .status.pending {
            background: #FFF4D8;
            color: #936400;
        }

        .status.confirmed {
            background: #E8F2FF;
            color: #235DA8;
        }

        .status.preparing {
            background: #F0E8FF;
            color: #6934A5;
        }

        .status.ready {
            background: #E1F7EA;
            color: #237747;
        }

        .status.completed {
            background: #E6F6F0;
            color: #147456;
        }

        .status.cancelled {
            background: #FFF0F1;
            color: #B42332;
        }

        .empty-state {
            padding: 45px 20px;
            color: var(--muted);
            text-align: center;
            font-size: 13px;
        }

        .empty-state strong {
            display: block;
            margin-bottom: 8px;
            color: var(--text);
            font-size: 15px;
        }

        .mobile-menu {
            display: none;
        }

        @media (max-width: 1100px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .main {
                padding: 25px;
            }
        }

        @media (max-width: 650px) {
            .sidebar {
                position: static;
                width: 100%;
                min-height: auto;
                padding: 20px;
            }

            .layout {
                display: block;
            }

            .brand {
                width: calc(100% + 40px);
                margin: -20px -20px 20px;
            }

            .nav-label,
            .sidebar-bottom {
                display: none;
            }

            .nav-icon {
                display: none;
            }

            .main {
                width: 100%;
                margin-left: 0;
                padding: 22px 16px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
                margin-bottom: 22px;
            }

            .page-heading h1 {
                font-size: 25px;
            }

            .welcome-banner {
                padding: 20px;
            }

            .welcome-symbol {
                display: none;
            }

            .stats-grid {
                gap: 12px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-top {
                align-items: flex-start;
                flex-direction: column-reverse;
                margin-bottom: 15px;
            }

            .section-header {
                padding: 18px;
            }
        }

        .toast-container {
            position: fixed;
            top: 22px;
            right: 22px;
            z-index: 1000;
            width: min(390px, calc(100vw - 32px));
            pointer-events: none;
        }

        .toast {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px;
            border: 1px solid #E9DCEF;
            border-left: 4px solid #188553;
            border-radius: 12px;
            background: #FFFFFF;
            box-shadow: 0 10px 30px rgba(78, 23, 107, 0.16);
            pointer-events: auto;
            animation: toast-slide-in 0.48s cubic-bezier(.2, .9, .25, 1.25) both;
        }

        .toast-icon {
            display: flex;
            flex: 0 0 34px;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #E8F7EF;
            color: #188553;
            font-size: 20px;
            font-weight: 700;
        }

        .toast-content {
            flex: 1;
            min-width: 0;
        }

        .toast-title {
            margin: 1px 0 4px;
            color: #30253A;
            font-size: 14px;
            font-weight: 700;
        }

        .toast-message {
            margin: 0;
            color: #665A70;
            font-size: 13px;
            line-height: 1.5;
        }

        .toast-close {
            padding: 0 2px;
            border: 0;
            background: transparent;
            color: #82778B;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
        }

        .toast-close:hover {
            color: #30253A;
        }

        .toast-progress-track {
            height: 4px;
            margin-top: 11px;
            overflow: hidden;
            border-radius: 99px;
            background: #F0EAF3;
        }

        .toast-progress {
            width: 100%;
            height: 100%;
            border-radius: inherit;
            background: #188553;
        }

        .toast.hide {
            animation: toast-slide-out 0.25s ease-in both;
        }

        @keyframes toast-slide-in {
            from {
                opacity: 0;
                transform: translate(35px, -8px) scale(.96);
            }
            to {
                opacity: 1;
                transform: translate(0) scale(1);
            }
        }

        @keyframes toast-slide-out {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(30px);
            }
        }

        @media (max-width: 480px) {
            .toast-container {
                top: 12px;
                right: 12px;
                width: calc(100vw - 24px);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .toast, .toast.hide {
                animation: none !important;
            }
        }

        [hidden] {
            display: none !important;
        }
    </style>
    <link rel="stylesheet" href="css/mobile-navigation.css?v=1">
    <script src="js/mobile-navigation.js?v=1" defer></script>
</head>

<body>
<?php if ($adminToast): ?>
    <div class="toast-container" aria-live="polite" aria-atomic="true">
        <div class="toast" id="adminLoginToast" role="status">
            <div class="toast-icon" aria-hidden="true">✓</div>
            <div class="toast-content">
                <p class="toast-title"><?= e($adminToast['title']) ?></p>
                <p class="toast-message"><?= e($adminToast['message']) ?></p>
                <div class="toast-progress-track" aria-hidden="true">
                    <div class="toast-progress" id="adminToastProgress"></div>
                </div>
            </div>
            <button type="button" class="toast-close" id="adminToastClose" aria-label="Dismiss notification">&times;</button>
        </div>
    </div>
<?php endif; ?>
<div class="layout">

   <aside class="sidebar">
    <a class="brand" href="dashboard.php" aria-label="Purple Blend admin dashboard">
        <img class="brand-logo" src="../Images/purple-blend-logo.jpg" alt="Purple Blend">
    </a>

    <nav>
        <a class="nav-link active" href="dashboard.php">Home</a>
        <a class="nav-link" href="products.php">Menu</a>
        <a class="nav-link" href="inventory.php">Stock</a>
        <a class="nav-link" href="orders.php">Orders</a>
        <a class="nav-link" href="customers.php">Customers</a>
        <a class="nav-link" href="sales.php">Sales</a>
        <a class="nav-link" href="account.php">Account</a>
        <a class="nav-link" href="logout.php">Log Out</a>
    </nav>
</aside>

    <main class="main">

        <header class="topbar">
            <div class="page-heading">
                <h1>Home</h1>
                <p>A quick look at today's orders and sales.</p>
            </div>

            <div class="date-label">
                <?= date('F j, Y') ?>
            </div>
        </header>

        <section class="welcome-banner">
            <div>
                <h2>
                    Welcome back, <?= e($_SESSION['admin_username'] ?? 'Admin') ?>!
                </h2>
                <p>
                    Use the menu on the left to check orders, update stock,
                    and manage your menu.
                </p>
            </div>

            <div class="welcome-symbol">✦</div>
        </section>

        <section class="stats-grid">

            <article class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Menu Items</span>
                    <span class="stat-icon">▤</span>
                </div>

                <div class="stat-value">
                    <?= number_format($stats['products']) ?>
                </div>

                <div class="stat-description">
                    Products and sizes on your menu
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Orders Placed</span>
                    <span class="stat-icon">☷</span>
                </div>

                <div class="stat-value">
                    <?= number_format($stats['orders']) ?>
                </div>

                <div class="stat-description">
                    Online and in-person orders
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Customers</span>
                    <span class="stat-icon">♙</span>
                </div>

                <div class="stat-value">
                    <?= number_format($stats['customers']) ?>
                </div>

                <div class="stat-description">
                    People with customer accounts
                </div>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <span class="stat-label">Completed Sales</span>
                    <span class="stat-icon">₱</span>
                </div>

                <div class="stat-value">
                    ₱<?= number_format($stats['sales'], 2) ?>
                </div>

                <div class="stat-description">
                    Total from orders marked completed
                </div>
            </article>

        </section>

        <section class="section-card">
            <div class="section-header">
                <div>
                    <h2>Latest Orders</h2>
                    <p>The five most recent customer orders.</p>
                </div>
            </div>

            <?php if ($recentOrders && $recentOrders->num_rows > 0): ?>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Customer</th>
                                <th>Order Time</th>
                                <th>Order Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php while ($order = $recentOrders->fetch_assoc()): ?>
                            <tr>
                                <td class="order-code">
                                    <?= e($order['order_code']) ?>
                                </td>

                                <td>
                                    <?= e($order['customer_name']) ?>
                                </td>

                                <td>
                                    <?= e(date(
                                        'M j, Y g:i A',
                                        strtotime($order['created_at'])
                                    )) ?>
                                </td>

                                <td>
                                    ₱<?= number_format(
                                        (float) $order['total_amount'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    <span class="status <?= e(
                                        statusClass($order['order_status'])
                                    ) ?>">
                                        <?= e($order['order_status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>

                <div class="empty-state">
                    <strong>No orders yet</strong>
                    New customer and walk-in orders will appear here.
                </div>

            <?php endif; ?>
        </section>

    </main>
</div>
<?php if ($adminToast): ?>
<script>
    (() => {
        const toast = document.getElementById('adminLoginToast');
        const progress = document.getElementById('adminToastProgress');
        const closeButton = document.getElementById('adminToastClose');
        const duration = 4500;
        const startedAt = Date.now();
        let progressTimer;
        let hideTimer;

        function dismissToast() {
            clearInterval(progressTimer);
            clearTimeout(hideTimer);
            toast.classList.add('hide');
            window.setTimeout(() => toast.remove(), 260);
        }

        progressTimer = window.setInterval(() => {
            const remaining = Math.max(
                0,
                100 - ((Date.now() - startedAt) / duration) * 100
            );
            progress.style.width = `${remaining}%`;
        }, 40);
        hideTimer = window.setTimeout(dismissToast, duration);
        closeButton.addEventListener('click', dismissToast);
    })();
</script>
<?php endif; ?>
</body>
</html>
