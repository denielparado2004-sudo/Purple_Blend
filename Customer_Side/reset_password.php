<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../database.php';

if (isset($_SESSION['customer_id'])) {
    header('Location: account.php');
    exit;
}

$tokenInput = $_GET['token'] ?? ($_POST['token'] ?? '');
$token = is_string($tokenInput) ? trim($tokenInput) : '';
$error = '';
$success = false;
$tokenRow = null;

if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $tokenHash = hash('sha256', $token);
    $stmt = $conn->prepare(
        'SELECT id, customer_id FROM password_resets WHERE token_hash = ? AND expires_at > UTC_TIMESTAMP() LIMIT 1'
    );
    if ($stmt) {
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $tokenRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!is_string($csrf) || empty($_SESSION['reset_password_csrf']) || !hash_equals($_SESSION['reset_password_csrf'], $csrf)) {
        $error = 'Your session expired. Open the reset link from your email again.';
    } elseif (!$tokenRow) {
        $error = 'This reset link is invalid or has expired. Request a new one.';
    } elseif (!is_string($password) || strlen($password) < 8) {
        $error = 'Your new password must be at least 8 characters.';
    } elseif (!is_string($confirm) || $password !== $confirm) {
        $error = 'The passwords do not match.';
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $conn->begin_transaction();

            $update = $conn->prepare('UPDATE customers SET password_hash = ? WHERE id = ?');
            if (!$update) {
                throw new RuntimeException('Could not prepare password update.');
            }
            $customerId = (int)$tokenRow['customer_id'];
            $update->bind_param('si', $passwordHash, $customerId);
            if (!$update->execute() || $update->affected_rows < 0) {
                throw new RuntimeException('Password update failed.');
            }
            $update->close();

            // Remove every reset token for this account so links cannot be reused.
            $delete = $conn->prepare('DELETE FROM password_resets WHERE customer_id = ?');
            if (!$delete) {
                throw new RuntimeException('Could not prepare token cleanup.');
            }
            $delete->bind_param('i', $customerId);
            if (!$delete->execute()) {
                throw new RuntimeException('Token cleanup failed.');
            }
            $delete->close();

            $conn->commit();
            unset($_SESSION['reset_password_csrf']);
            header('Location: login.php?password_reset=1');
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Purple Blend password reset update failed: ' . $e->getMessage());
            $error = 'Unable to reset your password right now. Please request a new link or try again.';
        }
    }
}

if (empty($_SESSION['reset_password_csrf'])) {
    $_SESSION['reset_password_csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/Purple_Blend/Images/pic1.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password | Purple Blend</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 24px; display: flex; align-items: center; justify-content: center; font-family: Arial, Helvetica, sans-serif; color: #30253a; background: #f7f0fb; }
        main { width: 100%; max-width: 440px; padding: 34px; background: #fff; border: 1px solid #eee4f3; border-radius: 18px; box-shadow: 0 12px 35px rgba(78, 23, 107, .1); }
        h1 { margin: 0 0 12px; font-size: 25px; }
        p { color: #665a70; line-height: 1.6; }
        label { display: block; margin: 18px 0 8px; font-size: 14px; font-weight: 700; }
        input { width: 100%; padding: 13px; border: 1px solid #d8cbdc; border-radius: 8px; font: inherit; }
        button { width: 100%; margin-top: 22px; padding: 13px; border: 0; border-radius: 8px; background: #6f2b91; color: #fff; font-weight: 700; cursor: pointer; }
        .password-wrap { position: relative; }
        .password-wrap input { padding-right: 54px; }
        .toggle-password {
            position: absolute;
            top: 50%;
            right: 9px;
            display: grid;
            width: 36px;
            height: 36px;
            margin: 0;
            padding: 7px;
            place-items: center;
            transform: translateY(-50%);
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: #81758A;
        }
        .toggle-password:hover { background: #f7f0fb; color: #6f2b91; }
        .toggle-password svg {
            width: 21px;
            height: 21px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .error { padding: 13px; border-radius: 8px; color: #a3211b; background: #fff0ef; }
        a { color: #6f2b91; }
        @media (max-width: 480px) { main { padding: 26px 22px; } }
    </style>
</head>
<body>
<main>
    <h1>Set a new password</h1>
    <?php if ($error !== ''): ?><p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if (!$tokenRow): ?>
        <p>This reset link is invalid or has expired. Please request a new reset link.</p>
        <p><a href="forgot_password.php">Request another link</a></p>
    <?php else: ?>
        <p>Choose a new password with at least 8 characters.</p>
        <form method="post" action="reset_password.php">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['reset_password_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <label for="password">New password</label>
            <div class="password-wrap">
                <input id="password" type="password" name="password" minlength="8" autocomplete="new-password" required>
                <button class="toggle-password" type="button" data-target="password" aria-label="Show password" aria-pressed="false">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
            <label for="confirm_password">Confirm new password</label>
            <div class="password-wrap">
                <input id="confirm_password" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
                <button class="toggle-password" type="button" data-target="confirm_password" aria-label="Show password" aria-pressed="false">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
            <button type="submit">Reset Password</button>
        </form>
    <?php endif; ?>
    <p><a href="login.php">Back to login</a></p>
</main>
<script>
    const eyeIcon = '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>';
    const eyeOffIcon = '<path d="M3 3l18 18"/><path d="M10.6 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15 15 0 0 1-3.1 3.8"/><path d="M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10 10 0 0 0 4-.8"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>';

    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.target);
            const showing = input.type === 'text';

            input.type = showing ? 'password' : 'text';
            button.querySelector('svg').innerHTML = showing ? eyeIcon : eyeOffIcon;
            button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            button.setAttribute('aria-pressed', String(!showing));
        });
    });
</script>
</body>
</html>
