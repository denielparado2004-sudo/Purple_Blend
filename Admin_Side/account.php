
<?php
// Load and update the signed-in administrator's account settings.
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

require_once __DIR__ . '/../database.php';

$adminId = (int) $_SESSION['admin_id'];
$message = '';
$messageType = '';

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Get the logged-in admin.
$stmt = $conn->prepare("
    SELECT id, username, role, is_active, created_at, updated_at
    FROM admins
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin || (int) $admin['is_active'] !== 1) {
    session_unset();
    session_destroy();
    header("Location: admin_login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Update username.
    if ($action === 'update_username') {
        $username = trim($_POST['username'] ?? '');

        if ($username === '' || strlen($username) > 50) {
            $message = 'Username is required and must be 50 characters or fewer.';
            $messageType = 'error';
        } else {
            $check = $conn->prepare("
                SELECT id FROM admins
                WHERE username = ? AND id != ?
                LIMIT 1
            ");
            $check->bind_param("si", $username, $adminId);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();

            if ($exists) {
                $message = 'That username is already taken.';
                $messageType = 'error';
            } else {
                $update = $conn->prepare("
                    UPDATE admins
                    SET username = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $update->bind_param("si", $username, $adminId);

                if ($update->execute()) {
                    $_SESSION['admin_username'] = $username;
                    $message = 'Username updated successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Unable to update username.';
                    $messageType = 'error';
                }

                $update->close();
            }
        }
    }

    else {
        $message = 'Invalid account action.';
        $messageType = 'error';
    }

    // Refresh admin details after a submission.
    $stmt = $conn->prepare("
        SELECT id, username, role, is_active, created_at, updated_at
        FROM admins
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/Purple_Blend/Images/pic1.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account | Purple Blend</title>

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
            --red: #B42318;
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
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 28px;
        }

        h1 {
            font-size: 27px;
            margin-bottom: 7px;
        }

        .subtitle {
            color: var(--muted);
            font-size: 14px;
        }

        .admin-label {
            background: var(--light-purple);
            color: var(--dark-purple);
            padding: 10px 14px;
            border-radius: 9px;
            font-size: 13px;
        }

        .message {
            padding: 14px 16px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .message.success {
            color: #126342;
            background: #EAF8F0;
            border: 1px solid #B8E5CC;
        }

        .message.error {
            color: var(--red);
            background: #FEF0EF;
            border: 1px solid #F5C5C1;
        }

        .card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 13px;
            overflow: hidden;
            margin-bottom: 22px;
        }

        .card-header {
            padding: 22px;
            border-bottom: 1px solid var(--border);
        }

        .card-header h2 {
            font-size: 18px;
            margin-bottom: 6px;
        }

        .card-header p {
            font-size: 13px;
            color: var(--muted);
        }

        .card-body {
            padding: 22px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #DCD1E3;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
        }

        input:focus {
            border-color: var(--purple);
            box-shadow: 0 0 0 3px rgba(111, 43, 145, 0.10);
        }

        .btn {
            border: none;
            border-radius: 8px;
            padding: 12px 17px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            color: white;
            background: var(--purple);
        }

        .btn:hover {
            background: var(--dark-purple);
        }

        .account-info {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .info-item span {
            display: block;
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 7px;
        }

        .info-item strong {
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            background: #EAF8F0;
            color: #126342;
            font-size: 12px;
        }

        .security-note {
            font-size: 13px;
            line-height: 1.6;
            color: var(--muted);
            margin-top: 16px;
        }

        .account-columns {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 22px;
            align-items: start;
        }

        @media (max-width: 900px) {
            .account-columns {
                grid-template-columns: 1fr;
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
                padding: 22px 14px;
            }

            .account-info {
                grid-template-columns: 1fr;
            }

            h1 {
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
        <a class="nav-link" href="sales.php">Sales</a>
        <a class="nav-link active" href="account.php">Account</a>
        <a class="nav-link" href="logout.php">Log Out</a>
    </nav>
</aside>

    <main class="main">
        <div class="topbar">
            <div>
                <h1>Account</h1>
                <p class="subtitle">Update your sign-in name and view your account information.</p>
            </div>

            <div class="admin-label">
                Logged in as <?= e($admin['username']) ?>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="message <?= e($messageType) ?>">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <div class="account-columns">
            <section class="card">
                <div class="card-header">
                    <h2>Change Sign-In Name</h2>
                    <p>Choose the name you use to log in.</p>
                </div>

                <div class="card-body">
                    <form method="POST" action="account.php">
                        <input type="hidden" name="action" value="update_username">

                        <div class="form-group">
                            <label for="username">Sign-In Name</label>
                            <input id="username" name="username"
                                   maxlength="50" required
                                   value="<?= e($admin['username']) ?>">
                        </div>

                        <button class="btn" type="submit">Save Name</button>
                    </form>
                </div>
            </section>

        </div>

        <section class="card">
            <div class="card-header">
                <h2>Account Details</h2>
                <p>Details about this account.</p>
            </div>

            <div class="card-body">
                <div class="account-info">
                    <div class="info-item">
                        <span>Account Number</span>
                        <strong>#<?= (int) $admin['id'] ?></strong>
                    </div>

                    <div class="info-item">
                        <span>Account Type</span>
                        <strong><?= e(ucfirst($admin['role'])) ?></strong>
                    </div>

                    <div class="info-item">
                        <span>Sign-In Access</span>
                        <strong>
                            <span class="status">
                                <?= (int) $admin['is_active'] === 1 ? 'Enabled' : 'Disabled' ?>
                            </span>
                        </strong>
                    </div>

                    <div class="info-item">
                        <span>Account Created</span>
                        <strong><?= e(date('M d, Y', strtotime($admin['created_at']))) ?></strong>
                    </div>
                </div>

                <p class="security-note">
                    You can change your sign-in name here. Contact your system
                    manager if your account role or status needs to change.
                </p>
            </div>
        </section>
    </main>
</div>

</body>
</html>
