<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../database.php';

if (isset($_SESSION['customer_id'])) {
    header('Location: account.php');
    exit;
}

$error = '';
$notice = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedEmail = $_POST['email'] ?? '';
    $email = is_string($submittedEmail) ? trim($submittedEmail) : '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrf) ||
        empty($_SESSION['forgot_password_csrf']) ||
        !hash_equals($_SESSION['forgot_password_csrf'], $csrf)
    ) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $error = 'Enter a valid email address.';
    } else {
        // Always show the same success message to avoid revealing whether an email is registered.
        $notice = 'If an account exists for that email, a password reset link will be sent shortly. Check your inbox and spam folder.';

        $stmt = $conn->prepare('SELECT id, first_name, email FROM customers WHERE email = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $email);
            if ($stmt->execute()) {
                $customer = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($customer) {
                    $configPath = __DIR__ . '/../email_config.php';
                    $autoloadPath = __DIR__ . '/../vendor/autoload.php';

                    if (!is_file($configPath) || !is_file($autoloadPath)) {
                        error_log('Purple Blend password reset email is not configured (missing email_config.php or vendor/autoload.php).');
                    } else {
                        $mailConfig = require $configPath;
                        require_once $autoloadPath;

                        if (
                            is_array($mailConfig) &&
                            !empty($mailConfig['smtp_host']) &&
                            !empty($mailConfig['smtp_username']) &&
                            !empty($mailConfig['smtp_password']) &&
                            !empty($mailConfig['from_email']) &&
                            !empty($mailConfig['base_url'])
                        ) {
                            try {
                                $token = bin2hex(random_bytes(32));
                                $tokenHash = hash('sha256', $token);
                              $expiresAt = gmdate('Y-m-d H:i:s', time() + 3600);

                                $conn->begin_transaction();
                                $delete = $conn->prepare('DELETE FROM password_resets WHERE expires_at < UTC_TIMESTAMP()');
                                if (!$delete) {
                                    throw new RuntimeException('Could not prepare reset-token cleanup.');
                                }
                                if (!$delete->execute()) {
                                    throw new RuntimeException('Could not clean up old reset tokens.');
                                }
                                $delete->close();

                                $insert = $conn->prepare('INSERT INTO password_resets (customer_id, token_hash, expires_at) VALUES (?, ?, ?)');
                                if (!$insert) {
                                    throw new RuntimeException('Could not prepare reset token.');
                                }
                                $customerId = (int)$customer['id'];
                                $insert->bind_param('iss', $customerId, $tokenHash, $expiresAt);
                                if (!$insert->execute()) {
                                    throw new RuntimeException('Could not save reset token.');
                                }
                                $insert->close();
                                $conn->commit();

                                $resetUrl = rtrim($mailConfig['base_url'], '/') . '/reset_password.php?token=' . urlencode($token);

                                $transport = new PHPMailer\PHPMailer\PHPMailer(true);
                                $transport->isSMTP();
                                $transport->Host = $mailConfig['smtp_host'];
                                $transport->SMTPAuth = true;
                                $transport->Username = $mailConfig['smtp_username'];
                                $transport->Password = $mailConfig['smtp_password'];
                                $transport->SMTPSecure = $mailConfig['smtp_secure'] ?? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                                $transport->Port = (int)($mailConfig['smtp_port'] ?? 587);
                                $transport->CharSet = 'UTF-8';
                                $transport->setFrom($mailConfig['from_email'], $mailConfig['from_name'] ?? 'The Purple Blend');
                                $transport->addAddress($customer['email'], $customer['first_name']);
                                $transport->isHTML(true);
                                $transport->Subject = 'Reset your Purple Blend password';
                                $safeName = htmlspecialchars((string)$customer['first_name'], ENT_QUOTES, 'UTF-8');
                                $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
                                $transport->Body = '<p>Hi ' . $safeName . ',</p><p>We received a request to reset your Purple Blend account password.</p><p><a href="' . $safeUrl . '">Reset my password</a></p><p>This link expires in one hour and can only be used once. If you requested another reset link, this link remains valid until it expires or a password is reset. If you did not request this, you can ignore this email.</p>';
                                $transport->AltBody = "Hi {$customer['first_name']},\n\nReset your Purple Blend password using this link (expires in one hour):\n{$resetUrl}\n\nIf you requested another reset link, this link remains valid until it expires or a password is reset. If you did not request this, ignore this email.";
                                $transport->send();
                            } catch (Throwable $e) {
                                if ($conn->errno === 0) {
                                    // A transaction may already have been committed; rollback is safe to attempt.
                                    $conn->rollback();
                                }
                                error_log('Purple Blend password reset email failed: ' . $e->getMessage());
                            }
                        } else {
                            error_log('Purple Blend password reset email configuration is incomplete.');
                        }
                    }
                }
            } else {
                $stmt->close();
                error_log('Purple Blend password reset customer lookup failed.');
            }
        }
        $_SESSION['forgot_password_csrf'] = bin2hex(random_bytes(32));
        $email = '';
    }
}

if (empty($_SESSION['forgot_password_csrf'])) {
    $_SESSION['forgot_password_csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/Purple_Blend/Images/pic1.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Purple Blend</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 24px; display: flex; align-items: center; justify-content: center; font-family: Arial, Helvetica, sans-serif; color: #30253a; background: #f7f0fb; }
        main { width: 100%; max-width: 440px; padding: 34px; background: #fff; border: 1px solid #eee4f3; border-radius: 18px; box-shadow: 0 12px 35px rgba(78, 23, 107, .1); }
        h1 { margin: 0 0 12px; font-size: 25px; }
        p { color: #665a70; line-height: 1.6; }
        label { display: block; margin: 20px 0 8px; font-size: 14px; font-weight: 700; }
        input { width: 100%; padding: 13px; border: 1px solid #d8cbdc; border-radius: 8px; font: inherit; }
        button { width: 100%; margin-top: 18px; padding: 13px; border: 0; border-radius: 8px; background: #6f2b91; color: #fff; font-weight: 700; cursor: pointer; }
        .notice, .error { margin: 18px 0; padding: 13px; border-radius: 8px; font-size: 14px; }
        .notice { color: #176b43; background: #e8f7ef; }
        .error { color: #a3211b; background: #fff0ef; }
        a { color: #6f2b91; }
        .back-link { display: block; margin-top: 24px; text-align: center; }
        @media (max-width: 480px) { main { padding: 26px 22px; } }
    </style>
</head>
<body>
<main>
    <h1>Forgot password?</h1>
    <p>Enter the email address linked to your Purple Blend account. We'll email you a secure reset link if the account exists.</p>
    <?php if ($error !== ''): ?><p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if ($notice !== ''): ?><p class="notice" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <form method="post" action="forgot_password.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['forgot_password_csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <label for="email">Registered email address</label>
        <input id="email" name="email" type="email" maxlength="150" autocomplete="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
        <button type="submit">Send Reset Link</button>
    </form>
    <a class="back-link" href="login.php">Back to login</a>
</main>
</body>
</html>
