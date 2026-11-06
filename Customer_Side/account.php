
<?php
// Load the signed-in customer's profile and process account updates.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../database.php';

// Only logged-in customers can access this page.
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$customerId = (int) $_SESSION['customer_id'];

function escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function loadCustomer($conn, $customerId)
{
    $stmt = $conn->prepare(
        'SELECT first_name, last_name, email, phone, created_at
         FROM customers
         WHERE id = ?
         LIMIT 1'
    );

    if (!$stmt) {
        error_log('Unable to prepare customer profile query.');
        die('Unable to load your account. Please try again later.');
    }

    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $customer;
}

$customer = loadCustomer($conn, $customerId);

if (!$customer) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['account_csrf_token'])) {
    $_SESSION['account_csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$successMessage = '';
$formAction = 'profile';

$firstName = $customer['first_name'];
$lastName = $customer['last_name'];
$email = $customer['email'];
$phone = $customer['phone'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['account_csrf_token'], $token)
    ) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } else {
        $formAction = $_POST['form_action'] ?? 'profile';

        if ($formAction === 'profile') {
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');

            if ($firstName === '' || mb_strlen($firstName) > 100) {
                $errors[] = 'Enter a first name (maximum 100 characters).';
            }

            if ($lastName === '' || mb_strlen($lastName) > 100) {
                $errors[] = 'Enter a last name (maximum 100 characters).';
            }

            if (
                $email === '' ||
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                strlen($email) > 150
            ) {
                $errors[] = 'Enter a valid email address (maximum 150 characters).';
            }

            if (!preg_match('/^[0-9]{11}$/', $phone)) {
                $errors[] = 'Enter an 11-digit phone number.';
            }

            // Check whether another customer already uses this email.
            if (!$errors) {
                $emailStmt = $conn->prepare(
                    'SELECT id
                     FROM customers
                     WHERE email = ? AND id != ?
                     LIMIT 1'
                );

                if (!$emailStmt) {
                    $errors[] = 'Unable to validate your email. Please try again.';
                } else {
                    $emailStmt->bind_param('si', $email, $customerId);
                    $emailStmt->execute();

                    if ($emailStmt->get_result()->num_rows > 0) {
                        $errors[] = 'That email address is already in use.';
                    }

                    $emailStmt->close();
                }
            }

            // Save the updated profile.
            if (!$errors) {
                $updateStmt = $conn->prepare(
                    'UPDATE customers
                     SET first_name = ?,
                         last_name = ?,
                         email = ?,
                         phone = ?
                     WHERE id = ?'
                );

                if (!$updateStmt) {
                    $errors[] = 'Unable to save your changes. Please try again.';
                } else {
                    $updateStmt->bind_param(
                        'ssssi',
                        $firstName,
                        $lastName,
                        $email,
                        $phone,
                        $customerId
                    );

                    if ($updateStmt->execute()) {
                        $successMessage = 'Your account information has been updated.';
                        $customer = loadCustomer($conn, $customerId);
                        $_SESSION['account_csrf_token'] =
                            bin2hex(random_bytes(32));
                    } else {
                        error_log('Customer profile update failed.');
                        $errors[] = 'Unable to save your changes. Please try again.';
                    }

                    $updateStmt->close();
                }
            }
        } else {
            $errors[] = 'Invalid account action. Please try again.';
        }
    }
}

$fullName = $customer['first_name'] . ' ' . $customer['last_name'];

$initials = strtoupper(
    mb_substr($customer['first_name'], 0, 1) .
    mb_substr($customer['last_name'], 0, 1)
);

$memberSince = !empty($customer['created_at'])
    ? date('M Y', strtotime($customer['created_at']))
    : 'Customer';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/Purple_Blend/Images/pic1.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account | Purple Blend</title>

    <style>
        :root {
            --purple: #6f2b91;
            --dark-purple: #4e176b;
            --light-purple: #f7f0fb;
            --page-bg: #fcfafd;
            --text: #33263d;
            --muted: #82758b;
            --border: #ece3f1;
            --white: #fff;
            --success: #236b43;
            --error: #a32332;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: var(--text);
            background: var(--page-bg);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button, input {
            font: inherit;
        }

        /* Purple header without a logo */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: #4e176b;
            border-bottom: 1px solid #5e2879;
        }

        .navbar {
            width: 100%;
            min-height: 76px;
            margin: auto;
            padding: 10px 4.5%;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        .nav-link,
        .logout-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0 20px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            white-space: nowrap;
            transition: background .2s, color .2s;
        }

        .nav-link {
            color: #fff;
        }

        .nav-link:hover {
            background: rgb(255 255 255 / 10%);
        }

        .nav-link.active {
            background: #6f3b8c;
            color: #fff;
        }

        .logout-link {
            margin-left: 4px;
            border: 1px solid #8c65a1;
            color: #fff;
        }

        .logout-link:hover {
            background: #5d2878;
        }

        /* Main page */
        .page {
            width: min(1120px, 100%);
            margin: auto;
            padding: 25px 24px 42px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
            color: var(--muted);
            font-size: 12px;
        }

        .breadcrumb a {
            color: var(--purple);
            font-weight: 700;
        }

        /* Welcome banner */
        .welcome {
            margin-bottom: 20px;
            padding: 24px 27px;
            border-radius: 12px;
            background: var(--dark-purple);
            color: white;
        }

        .welcome .eyebrow {
            margin: 0 0 8px;
            color: #e2c9ef;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.3px;
        }

        .welcome h1 {
            margin: 0;
            font-size: clamp(23px, 3vw, 30px);
            line-height: 1.3;
        }

        .welcome h1 span {
            color: #e7c8f6;
        }

        .welcome-copy > p:last-child {
            margin: 8px 0 0;
            color: #eee2f4;
            font-size: 13px;
            line-height: 1.6;
        }

        /* Profile overview */
        .profile-overview {
            display: grid;
            grid-template-columns: 1.2fr .8fr;
            gap: 16px;
            margin-bottom: 20px;
        }

        .profile-card,
        .member-card,
        .panel {
            min-width: 0;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--white);
        }

        .profile-card,
        .member-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 18px;
        }

        .avatar {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: var(--light-purple);
            color: var(--purple);
            font-size: 18px;
            font-weight: 800;
        }

        .profile-info {
            min-width: 0;
        }

        .profile-info h2 {
            overflow-wrap: anywhere;
            margin: 0;
            color: #382443;
            font-size: 17px;
        }

        .profile-info p {
            overflow-wrap: anywhere;
            margin: 6px 0 9px;
            color: var(--muted);
            font-size: 12px;
        }

        .member-tag {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 5px;
            background: var(--light-purple);
            color: var(--purple);
            font-size: 10px;
            font-weight: 700;
        }

        .member-icon {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 42px;
            height: 42px;
            border-radius: 9px;
            background: var(--light-purple);
            color: var(--purple);
            font-size: 20px;
        }

        .member-card small,
        .member-card strong {
            display: block;
        }

        .member-card small {
            margin-bottom: 5px;
            color: var(--muted);
            font-size: 10px;
        }

        .member-card strong {
            color: #432b50;
            font-size: 16px;
        }

        .account-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            align-items: start;
            gap: 18px;
        }

        .panel {
            overflow: hidden;
        }

        .panel-header {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 17px 20px;
            border-bottom: 1px solid var(--border);
        }

        .panel-icon {
            display: grid;
            place-items: center;
            flex-shrink: 0;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: var(--light-purple);
            color: var(--purple);
            font-size: 17px;
        }

        .panel-header h2 {
            margin: 0;
            color: #392443;
            font-size: 15px;
        }

        .panel-header p {
            margin: 5px 0 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.5;
        }

        .panel-body {
            padding: 20px;
        }

        .section-kicker {
            display: block;
            margin-bottom: 16px;
            color: var(--purple);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px 13px;
        }

        .form-group {
            min-width: 0;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: #493851;
            font-size: 12px;
            font-weight: 700;
        }

        input {
            display: block;
            width: 100%;
            min-height: 41px;
            padding: 10px 11px;
            border: 1px solid #e4d9eb;
            border-radius: 7px;
            outline: none;
            background: #fff;
            color: var(--text);
            font-size: 12px;
        }

        input:focus {
            border-color: #a46cbd;
            box-shadow: 0 0 0 2px rgb(111 43 145 / 10%);
        }

        input::placeholder {
            color: #b1a5b8;
        }

        .field-hint {
            margin: 6px 0 0;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.5;
        }

        .button-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 20px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 39px;
            padding: 0 14px;
            border: 1px solid transparent;
            border-radius: 7px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
        }

        .button-primary {
            background: var(--purple);
            color: white;
        }

        .button-primary:hover {
            background: var(--dark-purple);
        }

        .button-secondary {
            border-color: var(--border);
            background: white;
            color: #6c5b75;
        }

        .button-secondary:hover {
            background: #faf5fd;
        }

        /* Feedback messages */
        .message {
            margin-bottom: 16px;
            padding: 11px 12px;
            border: 1px solid;
            border-radius: 7px;
            font-size: 12px;
            line-height: 1.6;
        }

        .message strong {
            display: block;
            margin-bottom: 3px;
        }

        .message-success {
            border-color: #c7e5d1;
            background: #f0faf3;
            color: var(--success);
        }

        .message-error {
            border-color: #f0cbd0;
            background: #fff4f5;
            color: var(--error);
        }

        .message-error ul {
            margin: 5px 0 0;
            padding-left: 18px;
        }

        footer {
            padding: 18px;
            border-top: 1px solid var(--border);
            background: white;
            color: var(--muted);
            text-align: center;
            font-size: 11px;
            line-height: 1.7;
        }

        footer strong {
            color: var(--dark-purple);
        }

        button:focus-visible,
        a:focus-visible {
            outline: 2px solid #cba6de;
            outline-offset: 2px;
        }

        @media (max-width: 760px) {
            .profile-overview,
            .account-layout {
                grid-template-columns: minmax(0, 1fr);
            }
        }

        @media (max-width: 560px) {
            .navbar {
                flex-wrap: wrap;
                gap: 12px;
                padding: 12px;
            }

            .nav-actions {
                width: 100%;
                flex-wrap: wrap;
                gap: 5px;
            }

            .nav-link,
            .logout-link {
                flex: 1;
                min-height: 40px;
                padding: 0 8px;
                font-size: 12px;
            }

            .page {
                padding: 20px 14px 32px;
            }

            .welcome {
                padding: 20px;
            }

            .welcome h1 {
                font-size: 25px;
            }

            .panel-header {
                padding: 15px;
            }

            .panel-body {
                padding: 16px;
            }

            .form-grid {
                grid-template-columns: minmax(0, 1fr);
                gap: 15px;
            }

            .form-group.full-width {
                grid-column: auto;
            }

            .button-row .button {
                flex: 1;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition: none !important;
            }
        }
    </style>
</head>

<body>

<header class="topbar">
    <nav class="navbar" aria-label="Main navigation">
        <div class="nav-actions">
            <a href="index.php" class="nav-link">Menu</a>
            <a href="index.php#myOrders" class="nav-link">My Orders</a>
            <a href="account.php" class="nav-link active" aria-current="page">
                My Account
            </a>
            <a href="logout.php" class="logout-link">Log Out</a>
        </div>
    </nav>
</header>

<main class="page">

    <div class="breadcrumb">
        <a href="index.php">Home</a>
        <span>/</span>
        <span>My Account</span>
    </div>

    <section class="welcome">
        <div class="welcome-copy">
            <p class="eyebrow">YOUR PURPLE BLEND PROFILE</p>
            <h1>Welcome back, <span><?= escape($customer['first_name']) ?>.</span></h1>
            <p>
                Your account, your details, your campus favorites.
                Keep your information up to date right here.
            </p>
        </div>
    </section>

    <section class="profile-overview" aria-label="Profile overview">
        <div class="profile-card">
            <div class="avatar" aria-hidden="true"><?= escape($initials) ?></div>

            <div class="profile-info">
                <h2><?= escape($fullName) ?></h2>
                <p><?= escape($customer['email']) ?></p>
                <span class="member-tag">✓ Purple Blend Customer</span>
            </div>
        </div>

        <div class="member-card">
            <span class="member-icon" aria-hidden="true">✧</span>
            <span>
                <small>MEMBER SINCE</small>
                <strong><?= escape($memberSince) ?></strong>
            </span>
        </div>
    </section>

    <div class="account-layout">

        <!-- Personal information -->
        <section class="panel">
            <div class="panel-header">
                <span class="panel-icon" aria-hidden="true">♙</span>
                <div>
                    <h2>Personal Information</h2>
                    <p>Manage the details connected to your account.</p>
                </div>
            </div>

            <div class="panel-body">
                <span class="section-kicker">PROFILE DETAILS</span>

                <?php if ($successMessage !== '' && $formAction === 'profile'): ?>
                    <div class="message message-success" role="status">
                        <strong>Changes saved</strong>
                        <?= escape($successMessage) ?>
                    </div>
                <?php endif; ?>

                <?php if ($errors && $formAction === 'profile'): ?>
                    <div class="message message-error" role="alert">
                        <strong>Please check your information.</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= escape($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="account.php">
                    <input type="hidden" name="form_action" value="profile">
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= escape($_SESSION['account_csrf_token']) ?>"
                    >

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">First name</label>
                            <input
                                type="text"
                                id="first_name"
                                name="first_name"
                                maxlength="100"
                                autocomplete="given-name"
                                value="<?= escape($firstName) ?>"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="last_name">Last name</label>
                            <input
                                type="text"
                                id="last_name"
                                name="last_name"
                                maxlength="100"
                                autocomplete="family-name"
                                value="<?= escape($lastName) ?>"
                                required
                            >
                        </div>

                        <div class="form-group full-width">
                            <label for="email">Email address</label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                maxlength="150"
                                autocomplete="email"
                                value="<?= escape($email) ?>"
                                required
                            >
                            <p class="field-hint">
                                Use an email address you can access.
                            </p>
                        </div>

                        <div class="form-group full-width">
                            <label for="phone">Phone number *</label>
                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                maxlength="11"
                                minlength="11"
                                pattern="[0-9]{11}"
                                inputmode="numeric"
                                autocomplete="tel"
                                value="<?= escape($phone) ?>"
                                placeholder="Enter your 11-digit phone number"
                                required
                            >
                        </div>
                    </div>

                    <div class="button-row">
                        <button type="submit" class="button button-primary">
                            Save Changes →
                        </button>

                        <a href="account.php" class="button button-secondary">
                            Discard Changes
                        </a>
                    </div>
                </form>
            </div>
        </section>

    </div>
</main>

<footer>
    <strong>Purple Blend</strong> · Your campus food stop
    <br>
    &copy; <?= date('Y') ?> Purple Blend Campus Canteen
</footer>

</body>
</html>
