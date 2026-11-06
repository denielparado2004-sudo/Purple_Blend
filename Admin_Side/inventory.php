
<?php
// Update product stock and display the current inventory summary.
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
$lowStockLimit = 10;

if (!isset($_SESSION['inventory_csrf_token']) || !is_string($_SESSION['inventory_csrf_token'])) {
    $_SESSION['inventory_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['inventory_csrf_token'];

function displayInventoryCategory(string $category): string
{
    return in_array(strtolower(trim($category)), ['drinks', 'beverage'], true)
        ? 'Beverage'
        : $category;
}

// Update product stock
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedCsrfToken = $_POST['csrf_token'] ?? '';

    if (!is_string($submittedCsrfToken) || !hash_equals($csrfToken, $submittedCsrfToken)) {
        $message = "Invalid request. Please try again.";
        $messageType = 'error';
    } else {
        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $stockInput = $_POST['stock'] ?? '';

        if (
            !$productId ||
            !is_string($stockInput) ||
            !ctype_digit($stockInput) ||
            (int)$stockInput > 100000
        ) {
            $message = "Please enter a valid stock quantity.";
            $messageType = 'error';
        } else {
            $stock = (int)$stockInput;

            $stmt = $conn->prepare(
                "UPDATE menu_items SET stock = ? WHERE id = ?"
            );
            $stmt->bind_param("ii", $stock, $productId);

            if ($stmt->execute()) {
                $message = "Stock quantity updated successfully.";
            } else {
                $message = "Unable to update stock.";
                $messageType = 'error';
            }

            $stmt->close();
        }
    }
}

// Inventory summary
$summaryResult = $conn->query("
    SELECT
        COALESCE(SUM(CASE WHEN stock BETWEEN 1 AND 10 THEN 1 ELSE 0 END), 0) AS low_stock,
        COALESCE(SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END), 0) AS out_of_stock
    FROM menu_items
");

$summary = $summaryResult->fetch_assoc();

$search = trim($_GET['search'] ?? '');
$products = $conn->query("
    SELECT id, product_code, name, category, size_label, stock, is_available
    FROM menu_items
    ORDER BY stock ASC, name ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/Purple_Blend/Images/pic1.jpg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stock | Purple Blend</title>

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
    border-radius: 8px;
    color: #F2E9F7;
    text-decoration: none;
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

.summary-card small {
    display: block;
    margin-top: 8px;
    color: var(--muted);
    font-size: 11px;
    line-height: 1.4;
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

.search-form {
    display: flex;
    gap: 8px;
}

.search-input {
    width: 220px;
    max-width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
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
    min-width: 720px;
}

th, td {
    padding: 15px 18px;
    text-align: left;
    border-bottom: 1px solid #F0EAF3;
    font-size: 13px;
}

th {
    background: #FBF8FD;
    color: var(--muted);
    font-size: 12px;
}

tbody tr:last-child td {
    border-bottom: 0;
}

.product-name {
    font-weight: 600;
}

.product-code {
    display: block;
    color: var(--muted);
    font-size: 11px;
    margin-top: 5px;
}

.status {
    display: inline-block;
    padding: 6px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
    white-space: nowrap;
}

.status.good {
    background: #E8F7EE;
    color: var(--green);
}

.status.low {
    background: #FFF3DC;
    color: var(--orange);
}

.status.out {
    background: #FDEAEA;
    color: var(--red);
}

.stock-form {
    display: flex;
    align-items: center;
    gap: 7px;
}

.stock-input {
    width: 75px;
    padding: 8px;
    border: 1px solid var(--border);
    border-radius: 7px;
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
        <a class="nav-link active" href="inventory.php">Stock</a>
        <a class="nav-link" href="orders.php">Orders</a>
        <a class="nav-link" href="customers.php">Customers</a>
        <a class="nav-link" href="sales.php">Sales</a>
        <a class="nav-link" href="account.php">Account</a>
        <a class="nav-link" href="logout.php">Log Out</a>
    </nav>
</aside>

<main class="main">

<header class="topbar">
    <div>
        <h1>Stock</h1>
        <p class="subtitle">
            Check how many items are left and update the count when needed.
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
        <p>Running Low</p>
        <h2><?= number_format((int)$summary['low_stock']) ?></h2>
        <small>Menu items with 1-10 pieces left</small>
    </div>

    <div class="summary-card">
        <p>Sold Out</p>
        <h2><?= number_format((int)$summary['out_of_stock']) ?></h2>
        <small>Menu items with no pieces left</small>
    </div>

</section>

<section class="panel">

    <div class="panel-header">
        <h2>Stock by Menu Item</h2>

        <form class="search-form" method="GET" data-admin-live-search>
            <input
                class="search-input"
                type="search"
                name="search"
                maxlength="100"
                data-admin-live-search-input
                placeholder="Find a menu item..."
                value="<?= htmlspecialchars($search) ?>"
            >
            <button class="button" type="submit">Find Item</button>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Menu Item</th>
                <th>Category</th>
                <th>Pieces Left</th>
                <th>Stock Level</th>
                <th>Shown to Customers</th>
                <th>Update Pieces Left</th>
            </tr>
            </thead>

            <tbody>
            <?php if ($products && $products->num_rows > 0): ?>
                <?php while ($product = $products->fetch_assoc()): ?>
                    <?php
                    $stock = (int)$product['stock'];

                    if ($stock === 0) {
                        $statusClass = 'out';
                        $statusText = 'Out of Stock';
                    } elseif ($stock <= $lowStockLimit) {
                        $statusClass = 'low';
                        $statusText = 'Low Stock';
                    } else {
                        $statusClass = 'good';
                        $statusText = 'In Stock';
                    }
                    ?>

                    <tr data-admin-search-row="<?= htmlspecialchars(
                        $product['name'] . ' ' .
                        $product['product_code'] . ' ' .
                        ($product['size_label'] ?? '') . ' ' .
                        displayInventoryCategory($product['category']) . ' ' .
                        $product['category'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>">
                        <td>
                            <span class="product-name">
                                <?= htmlspecialchars($product['name']) ?>
                                <?= !empty($product['size_label']) ? ' · ' . htmlspecialchars($product['size_label'], ENT_QUOTES, 'UTF-8') : '' ?>
                            </span>
                            <span class="product-code">
                                <?= htmlspecialchars($product['product_code']) ?>
                            </span>
                        </td>

                        <td><?= htmlspecialchars(displayInventoryCategory($product['category']), ENT_QUOTES, 'UTF-8') ?></td>

                        <td><?= number_format($stock) ?></td>

                        <td>
                            <span class="status <?= $statusClass ?>">
                                <?= $statusText ?>
                            </span>
                        </td>

                        <td>
                            <?= (int)$product['is_available'] === 1
                                ? 'Yes'
                                : 'No' ?>
                        </td>

                        <td>
                            <form class="stock-form" method="POST">
                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
                                >

                                <input
                                    type="hidden"
                                    name="product_id"
                                    value="<?= (int)$product['id'] ?>"
                                >

                                <input
                                    class="stock-input"
                                    type="number"
                                    name="stock"
                                    min="0"
                                    max="100000"
                                    step="1"
                                    value="<?= $stock ?>"
                                    required
                                    aria-label="Stock quantity"
                                >

                                <button class="button" type="submit">
                                    Update
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td class="empty" colspan="6">
                        No menu items found. Add items on the Menu page first.
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
