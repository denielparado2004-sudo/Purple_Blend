
<?php
// Load customer totals and the customer list for admin review.
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

$search = trim($_GET['search'] ?? '');

// Customer summary
$summaryResult = $conn->query("
    SELECT
        COUNT(*) AS total_customers,
        COALESCE(SUM(
            CASE WHEN created_at >= CURDATE()
            AND created_at < CURDATE() + INTERVAL 1 DAY
            THEN 1 ELSE 0 END
        ), 0) AS registered_today
    FROM customers
");

$summary = $summaryResult->fetch_assoc();

$customers = $conn->query("
    SELECT
        id,
        first_name,
        last_name,
        email,
        phone,
        created_at
    FROM customers
    ORDER BY created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/Purple_Blend/Images/pic1.jpg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Customers | Purple Blend</title>

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
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 26px;
    max-width: 650px;
}

.summary-card {
    background: white;
    padding: 22px;
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
    font-size: 28px;
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

.search-form {
    display: flex;
    gap: 8px;
}

.search-input {
    width: 260px;
    max-width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font: inherit;
    font-size: 13px;
    outline-color: var(--purple);
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
    min-width: 780px;
}

th,
td {
    padding: 15px 18px;
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

.customer-name {
    font-weight: 600;
}

.customer-id {
    display: block;
    color: var(--muted);
    font-size: 11px;
    margin-top: 5px;
}

.email {
    overflow-wrap: anywhere;
}

.registered {
    white-space: nowrap;
}

.empty {
    text-align: center;
    color: var(--muted);
    padding: 35px;
}

@media (max-width: 1050px) {
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

    .search-form {
        width: 100%;
    }

    .search-input {
        flex: 1;
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
        <a class="nav-link" href="orders.php">Orders</a>
        <a class="nav-link active" href="customers.php">Customers</a>
        <a class="nav-link" href="sales.php">Sales</a>
        <a class="nav-link" href="account.php">Account</a>
        <a class="nav-link" href="logout.php">Log Out</a>
    </nav>
</aside>

<main class="main">

<header class="topbar">
    <div>
        <h1>Customers</h1>
        <p class="subtitle">
            Find customer contact details and see when they joined.
        </p>
    </div>

    <div class="admin-label">
        Signed in:
        <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?>
    </div>
</header>

<section class="summary">
    <div class="summary-card">
        <p>Customer Accounts</p>
        <h2><?= number_format((int)$summary['total_customers']) ?></h2>
    </div>

    <div class="summary-card">
        <p>Joined Today</p>
        <h2><?= number_format((int)$summary['registered_today']) ?></h2>
    </div>
</section>

<section class="panel">

    <div class="panel-header">
        <h2>Customer List</h2>

        <form class="search-form" method="GET" data-admin-live-search>
            <input
                class="search-input"
                type="search"
                name="search"
                maxlength="100"
                data-admin-live-search-input
                placeholder="Find a customer by name or contact..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <button class="button" type="submit">Find Customer</button>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Customer</th>
                <th>Email Address</th>
                <th>Phone Number</th>
                <th>Joined On</th>
            </tr>
            </thead>

            <tbody>
            <?php if ($customers && $customers->num_rows > 0): ?>
                <?php while ($customer = $customers->fetch_assoc()): ?>
                    <tr data-admin-search-row="<?= htmlspecialchars(
                        $customer['first_name'] . ' ' .
                        $customer['last_name'] . ' ' .
                        $customer['email'] . ' ' .
                        ($customer['phone'] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>">
                        <td>
                            <span class="customer-name">
                                <?= htmlspecialchars(
                                    $customer['first_name'] . ' ' .
                                    $customer['last_name']
                                ) ?>
                            </span>

                            <span class="customer-id">
                                Customer #<?= (int)$customer['id'] ?>
                            </span>
                        </td>

                        <td class="email">
                            <?= htmlspecialchars($customer['email']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $customer['phone'] ?: 'Not provided'
                            ) ?>
                        </td>

                        <td class="registered">
                            <?= date(
                                'M j, Y',
                                strtotime($customer['created_at'])
                            ) ?>
                            <span class="customer-id">
                                <?= date(
                                    'g:i A',
                                    strtotime($customer['created_at'])
                                ) ?>
                            </span>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="empty">
                        No registered customers found.
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
