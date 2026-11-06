<?php
// Handle customer login and the sign-up form displayed on this page.
session_start();
require_once __DIR__ . '/../database.php';

function isCompleteEmailAddress(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false &&
        preg_match(
            '/^[^@\s]+@(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\.)+[A-Za-z]{2,}$/D',
            $email
        ) === 1;
}

$allowedRedirects = [
    'checkout.php',
    'checkout.php?mode=buy-now'
];
$requestedRedirect = $_GET['redirect'] ?? '';
$redirect = in_array($requestedRedirect, $allowedRedirects, true)
    ? $requestedRedirect
    : 'index.php';

if (isset($_SESSION['customer_id'])) {
    header('Location: ' . $redirect);
    exit;
}
  


$error = '';
$mode = 'login';
$toast = null;

$firstName = '';
$lastName = '';
$email = '';
$phone = '';

if (isset($_GET['registered'])) {
    $toast = [
        'type' => 'success',
        'title' => 'Account created successfully!',
        'message' => 'Your account is ready. You can now log in.'
    ];
} elseif (isset($_GET['password_reset'])) {
    $toast = [
        'type' => 'success',
        'title' => 'Password updated',
        'message' => 'Your password has been reset. You can now log in.'
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestedRedirect = $_POST['redirect'] ?? '';
    $redirect = in_array($requestedRedirect, $allowedRedirects, true)
        ? $requestedRedirect
        : 'index.php';
    $mode = $_POST['form_mode'] ?? 'login';
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($mode === 'signup') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (
            $firstName === '' ||
            $lastName === '' ||
            $email === '' ||
            $phone === '' ||
            $password === '' ||
            $confirmPassword === ''
        ) {
            $error = 'Please complete all required fields.';
        } elseif (
            !preg_match("/^[\p{L}\s'-]+$/u", $firstName) ||
            !preg_match("/^[\p{L}\s'-]+$/u", $lastName)
        ) {
            $error = 'Please enter a valid first and last name.';
        } elseif (!isCompleteEmailAddress($email)) {
            $error = 'Please enter a complete, valid email address.';
        } elseif (strlen($email) > 150) {
            $error = 'Your email address must be 150 characters or fewer.';
        } elseif (!preg_match('/^[0-9]{11}$/', $phone)) {
            $error = 'Please enter an 11-digit phone number.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Your passwords do not match.';
        } else {
            $stmt = $conn->prepare(
                'SELECT id FROM customers WHERE email = ? LIMIT 1'
            );

            if (!$stmt) {
                $error = 'Unable to process your request. Please try again.';
            } else {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $existing = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($existing) {
                    $error = 'An account with this email already exists.';
                } else {
                    $passwordHash = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $conn->prepare(
                        'INSERT INTO customers
                        (first_name, last_name, email, password_hash, phone)
                        VALUES (?, ?, ?, ?, ?)'
                    );

                    if (!$stmt) {
                        $error = 'Unable to create your account. Please try again.';
                    } else {
                        $stmt->bind_param(
                            'sssss',
                            $firstName,
                            $lastName,
                            $email,
                            $passwordHash,
                            $phone
                        );

                        if ($stmt->execute()) {
                            $stmt->close();
                            $registrationRedirect = '/login.php?registered=1';
                            if ($redirect !== 'index.php') {
                                $registrationRedirect .= '&redirect=' .
                                    rawurlencode($redirect);
                            }
                            header('Location: ' . $registrationRedirect);
                            exit;
                        }

                        if ($stmt->errno === 1062) {
                            $error = 'An account with this email already exists.';
                        } else {
                            $error = 'Unable to create your account. Please try again.';
                        }

                        $stmt->close();
                    }
                }
            }
        }
    } elseif ($mode === 'login') {
        if ($email === '' || $password === '') {
            $error = 'Please enter your email and password.';
        } elseif (strlen($email) > 150) {
            $error = 'Please enter a valid email address.';
        } elseif (!isCompleteEmailAddress($email)) {
            $error = 'Please enter a complete, valid email address.';
        } else {
            $stmt = $conn->prepare(
                'SELECT id, first_name, last_name, email, password_hash
                 FROM customers
                 WHERE email = ?
                 LIMIT 1'
            );

            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $customer = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (
                    $customer &&
                    password_verify($password, $customer['password_hash'])
                ) {
                    session_regenerate_id(true);

                    $_SESSION['customer_id'] = (int) $customer['id'];
                    $_SESSION['customer_name'] =
                        $customer['first_name'] . ' ' . $customer['last_name'];
                    $_SESSION['customer_email'] = $customer['email'];

                    // Show a one-time welcome notification after the redirect.
                    $_SESSION['toast'] = [
                        'type' => 'success',
                        'title' => 'Welcome, ' . $customer['first_name'] . '!',
                        'message' => 'You have successfully logged in.'
                    ];

                    header('Location: ' . $redirect);
                    exit;
                }
            }

            $email = '';
            $error = 'Invalid email or password.';
        }
    } else {
        $mode = 'login';
        $error = 'Invalid request. Please try again.';
    }

    if ($error !== '') {
        $toast = [
            'type' => 'error',
            'title' => $mode === 'signup'
                ? 'Registration failed'
                : 'Login failed',
            'message' => $error
        ];
    }
}
?>
<!-- Shared login/sign-up interface and its client-side form behavior. -->
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/Purple_Blend/Images/pic1.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Purple Blend</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: Arial, Helvetica, sans-serif;
            color: #30253a;
            background: #f7f0fb;
        }

        .auth-card {
            width: 100%;
            max-width: 440px;
            padding: 0 34px 34px;
            background: #fff;
            border: 1px solid #eee4f3;
            border-radius: 18px;
            box-shadow: 0 12px 35px rgba(78, 23, 107, 0.10);
            overflow: hidden;
        }

        .brand {
            margin: 0 -34px 28px;
            text-align: center;
        }

        .brand-logo {
            display: block;
            width: 100%;
            height: auto;
            object-fit: cover;
            object-position: center;
        }

        .brand h1 {
            margin: 0;
            color: #6f2b91;
            font-size: 25px;
        }

        .brand p {
            margin: 0;
            padding: 12px 16px 0;
            color: #82778b;
            font-size: 14px;
        }

        .mode-switch {
            display: flex;
            gap: 4px;
            margin-bottom: 24px;
            padding: 4px;
            border-radius: 10px;
            background: #f0e8f5;
        }

        .mode-button {
            flex: 1;
            padding: 11px 8px;
            border: none;
            border-radius: 7px;
            background: transparent;
            color: #6f2b91;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
        }

        .mode-button.active {
            color: #fff;
            background: #6f2b91;
            box-shadow: 0 2px 6px rgba(78, 23, 107, 0.15);
        }

        h2 {
            margin: 0 0 8px;
            font-size: 23px;
        }

        .intro {
            margin: 0 0 22px;
            color: #82778b;
            font-size: 14px;
            line-height: 1.5;
        }

        .name-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        label {
            display: block;
            margin: 15px 0 7px;
            font-size: 13px;
            font-weight: 700;
        }

        input {
            width: 100%;
            min-width: 0;
            padding: 13px;
            border: 1px solid #ded3e5;
            border-radius: 9px;
            outline: none;
            font-size: 14px;
        }

        input:focus {
            border-color: #6f2b91;
            box-shadow: 0 0 0 3px rgba(111, 43, 145, 0.10);
        }

        input.validation-invalid {
            border-color: #d92d20;
            background: #fff8f7;
        }

        input.validation-invalid:focus {
            border-color: #d92d20;
            box-shadow: 0 0 0 3px rgba(217, 45, 32, 0.12);
        }

        input.validation-valid {
            border-color: #188553;
            background: #f7fcf9;
        }

        input.validation-valid:focus {
            border-color: #188553;
            box-shadow: 0 0 0 3px rgba(24, 133, 83, 0.12);
        }

        .password-wrap {
            position: relative;
        }

        .password-wrap input {
            padding-right: 62px;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            padding: 6px;
            border: none;
            background: transparent;
            color: #82778b;
            cursor: pointer;
        }

        .toggle-password:hover {
            color: #6f2b91;
        }

        .toggle-password svg {
            width: 21px;
            height: 21px;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .password-strength {
            margin-top: 10px;
        }

        .strength-bars {
            display: flex;
            gap: 5px;
            margin-bottom: 7px;
        }

        .strength-bar {
            height: 4px;
            flex: 1;
            border-radius: 99px;
            background: #e9e2ed;
            transition: background 0.2s;
        }

        .strength-bar.weak { background: #d9534f; }
        .strength-bar.medium { background: #e4a23b; }
        .strength-bar.strong { background: #2f9e63; }

        .strength-label {
            margin: 0;
            color: #82778b;
            font-size: 12px;
            line-height: 1.5;
        }

        .strength-label.weak { color: #b52b27; }
        .strength-label.medium { color: #986316; }
        .strength-label.strong { color: #247647; }

        .password-hint {
            display: block;
            margin-top: 5px;
            color: #82778b;
            font-size: 11px;
            line-height: 1.5;
        }

        .submit-button {
            width: 100%;
            margin-top: 24px;
            padding: 14px;
            border: none;
            border-radius: 9px;
            background: #6f2b91;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s;
        }

        .submit-button:hover {
            background: #4e176b;
        }

        .submit-button:disabled {
            background: #b9a9c2;
            cursor: not-allowed;
        }

        .forgot-password {
            margin: 8px 0 0;
            text-align: right;
            font-size: 13px;
        }

        .forgot-password a {
            color: #6f2b91;
            text-decoration: none;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }

        .back-link {
            display: block;
            margin-top: 24px;
            text-align: center;
            color: #82778b;
            font-size: 13px;
            text-decoration: none;
        }

        .back-link:hover {
            color: #6f2b91;
        }

        /* Toast notification */
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
            background: #fff;
            border: 1px solid #e9dcef;
            border-left: 4px solid #6f2b91;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(78, 23, 107, 0.16);
            pointer-events: auto;
            animation: toast-slide-in 0.48s cubic-bezier(.2, .9, .25, 1.25) both;
        }

        .toast.success { border-left-color: #188553; }
        .toast.error { border-left-color: #d92d20; }

        .toast-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 34px;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            color: #6f2b91;
            background: #f7f0fb;
            font-size: 20px;
            font-weight: 700;
        }

        .toast.success .toast-icon {
            color: #188553;
            background: #e8f7ef;
        }

        .toast.error .toast-icon {
            color: #d92d20;
            background: #fff0ef;
        }

        .toast-content {
            flex: 1;
            min-width: 0;
        }

        .toast-title {
            margin: 1px 0 4px;
            color: #30253a;
            font-size: 14px;
            font-weight: 700;
        }

        .toast-message {
            margin: 0;
            color: #665a70;
            font-size: 13px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .toast-close {
            padding: 0 2px;
            border: 0;
            background: transparent;
            color: #82778b;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
        }

        .toast-close:hover {
            color: #30253a;
        }

        .toast-progress-track {
            height: 4px;
            margin-top: 11px;
            overflow: hidden;
            border-radius: 99px;
            background: #f0eaf3;
        }

        .toast-progress {
            width: 100%;
            height: 100%;
            border-radius: inherit;
            background: #188553;
        }

        .toast.error .toast-progress {
            background: #d92d20;
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

        @keyframes toast-pop {
            0% { transform: scale(1); }
            45% { transform: scale(1.025); }
            100% { transform: scale(1); }
        }

        .toast.pop {
            animation: toast-pop 0.32s ease-out;
        }

        [hidden] {
            display: none !important;
        }

        @media (max-width: 480px) {
            .auth-card {
                padding: 0 22px 26px;
            }

            .brand {
                margin: 0 -22px 24px;
            }

            .toast-container {
                top: 12px;
                right: 12px;
                width: calc(100vw - 24px);
            }
        }

        @media (max-width: 360px) {
            .name-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .toast, .toast.hide, .toast.pop {
                animation: none !important;
            }
        }
    </style>
</head>
<body>

<div class="toast-container" aria-live="polite" aria-atomic="true">
    <div
        class="toast <?= htmlspecialchars($toast['type'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        id="feedbackToast"
        role="status"
        <?= $toast ? '' : 'hidden' ?>
    >
        <div class="toast-icon" id="toastIcon">
            <?= ($toast['type'] ?? '') === 'error' ? '!' : '✓' ?>
        </div>

        <div class="toast-content">
            <p class="toast-title" id="toastTitle">
                <?= htmlspecialchars($toast['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </p>

            <p class="toast-message" id="toastMessage">
                <?= htmlspecialchars($toast['message'] ?? '', ENT_QUOTES, 'UTF-8') ?>
            </p>

            <div
                class="toast-progress-track"
                id="toastProgressTrack"
                <?= ($toast['type'] ?? '') === 'error' ? 'hidden' : '' ?>
            >
                <div class="toast-progress" id="toastProgress"></div>
            </div>
        </div>

        <button
            type="button"
            class="toast-close"
            id="toastClose"
            aria-label="Dismiss notification"
        >&times;</button>
    </div>
</div>

<main class="auth-card">
    <header class="brand">
        <img
            class="brand-logo"
           src="/Purple_Blend/Images/purple-blend-logo.jpg"
            alt="The Purple Blend"
        >
        <p>Your campus food stop</p>
    </header>

    <div class="mode-switch" role="group" aria-label="Account options">
        <button
            type="button"
            class="mode-button <?= $mode === 'login' ? 'active' : '' ?>"
            id="loginTab"
            aria-pressed="<?= $mode === 'login' ? 'true' : 'false' ?>"
        >Log In</button>

        <button
            type="button"
            class="mode-button <?= $mode === 'signup' ? 'active' : '' ?>"
            id="signupTab"
            aria-pressed="<?= $mode === 'signup' ? 'true' : 'false' ?>"
        >Sign Up</button>
    </div>

    <h2 id="formTitle">
        <?= $mode === 'login' ? 'Welcome!' : 'Create your account' ?>
    </h2>

    <p class="intro" id="formIntro">
        <?= $mode === 'login'
            ? 'Log in to continue ordering your campus favorites.'
            : 'Create an account to order your campus favorites.' ?>
    </p>

    <form method="POST" action="/login.php" id="authForm">
        <input
            type="hidden"
            name="redirect"
            value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>"
        >

        <input
            type="hidden"
            name="form_mode"
            id="formMode"
            value="<?= htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') ?>"
        >

        <div id="nameFields" <?= $mode === 'signup' ? '' : 'hidden' ?>>
            <div class="name-row">
                <div>
                    <label for="first_name">First name *</label>
                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        maxlength="100"
                        data-validation="name"
                        value="<?= htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="given-name"
                        <?= $mode === 'signup' ? 'required' : 'disabled' ?>
                    >
                </div>

                <div>
                    <label for="last_name">Last name *</label>
                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        maxlength="100"
                        data-validation="name"
                        value="<?= htmlspecialchars($lastName, ENT_QUOTES, 'UTF-8') ?>"
                        autocomplete="family-name"
                        <?= $mode === 'signup' ? 'required' : 'disabled' ?>
                    >
                </div>
            </div>
        </div>

        <label for="email">Email address *</label>
        <input
            type="email"
            id="email"
            name="email"
            maxlength="150"
            placeholder="Enter your email"
            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
            autocomplete="email"
            data-validation="email"
            required
        >

        <div id="phoneField" <?= $mode === 'signup' ? '' : 'hidden' ?>>
            <label for="phone">Phone number *</label>
            <input
                type="tel"
                id="phone"
                name="phone"
                maxlength="11"
                minlength="11"
                pattern="[0-9]{11}"
                inputmode="numeric"
                data-validation="phone"
                placeholder="Enter your 11-digit phone number"
                value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="tel"
                <?= $mode === 'signup' ? 'required' : 'disabled' ?>
            >
        </div>

        <label for="password">Password *</label>

        <div class="password-wrap">
            <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter your password"
                data-validation="password"
                autocomplete="<?= $mode === 'signup' ? 'new-password' : 'current-password' ?>"
                <?= $mode === 'signup' ? 'minlength="8"' : '' ?>
                required
            >

            <button
                type="button"
                class="toggle-password"
                data-target="password"
                aria-label="Show password"
                aria-pressed="false"
            >
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
            </button>
        </div>

        <p class="forgot-password" id="forgotPasswordLink" <?= $mode === 'signup' ? 'hidden' : '' ?>>
            <a href="/forgot_password.php">Forgot password?</a>
        </p>

        <div
            id="passwordStrength"
            class="password-strength"
            <?= $mode === 'signup' ? '' : 'hidden' ?>
            aria-live="polite"
        >
            <div class="strength-bars" aria-hidden="true">
                <span class="strength-bar"></span>
                <span class="strength-bar"></span>
                <span class="strength-bar"></span>
            </div>

            <p class="strength-label" id="strengthLabel">
                Password strength will appear here.
            </p>

            <span class="password-hint">
                Use at least 8 characters. A mix of uppercase and lowercase
                letters, numbers, and symbols can make it stronger.
            </span>
        </div>

        <div id="confirmField" <?= $mode === 'signup' ? '' : 'hidden' ?>>
            <label for="confirm_password">Confirm password *</label>

            <div class="password-wrap">
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Confirm your password"
                    data-validation="confirm-password"
                    autocomplete="new-password"
                    minlength="8"
                    <?= $mode === 'signup' ? 'required' : 'disabled' ?>
                >

                <button
                    type="button"
                    class="toggle-password"
                    data-target="confirm_password"
                    aria-label="Show password"
                    aria-pressed="false"
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
        </div>

        <button type="submit" class="submit-button" id="submitButton" disabled>
            <?= $mode === 'login' ? 'Log In' : 'Create Account' ?>
        </button>
    </form>

   <a class="back-link" href="/">← Back to menu</a>
</main>

<script>
    const loginTab = document.getElementById('loginTab');
    const signupTab = document.getElementById('signupTab');
    const formMode = document.getElementById('formMode');
    const nameFields = document.getElementById('nameFields');
    const phoneField = document.getElementById('phoneField');
    const confirmField = document.getElementById('confirmField');
    const firstName = document.getElementById('first_name');
    const lastName = document.getElementById('last_name');
    const phone = document.getElementById('phone');
    const confirmPassword = document.getElementById('confirm_password');
    const password = document.getElementById('password');
    const forgotPasswordLink = document.getElementById('forgotPasswordLink');
    const formTitle = document.getElementById('formTitle');
    const formIntro = document.getElementById('formIntro');
    const submitButton = document.getElementById('submitButton');
    const passwordStrength = document.getElementById('passwordStrength');
    const strengthLabel = document.getElementById('strengthLabel');
    const strengthBars = document.querySelectorAll('.strength-bar');
    const authForm = document.getElementById('authForm');

    function setMode(mode) {
        const isSignup = mode === 'signup';

        formMode.value = mode;

        loginTab.classList.toggle('active', !isSignup);
        signupTab.classList.toggle('active', isSignup);

        loginTab.setAttribute('aria-pressed', String(!isSignup));
        signupTab.setAttribute('aria-pressed', String(isSignup));

        nameFields.hidden = !isSignup;
        phoneField.hidden = !isSignup;
        confirmField.hidden = !isSignup;
        passwordStrength.hidden = !isSignup;

        firstName.disabled = !isSignup;
        lastName.disabled = !isSignup;
        phone.disabled = !isSignup;
        confirmPassword.disabled = !isSignup;

        firstName.required = isSignup;
        lastName.required = isSignup;
        confirmPassword.required = isSignup;
        phone.required = isSignup;

        password.minLength = isSignup ? 8 : 0;
        password.autocomplete = isSignup ? 'new-password' : 'current-password';
        forgotPasswordLink.hidden = isSignup;

        formTitle.textContent = isSignup
            ? 'Create your account'
            : 'Welcome back!';

        formIntro.textContent = isSignup
            ? 'Create an account to order your campus favorites.'
            : 'Log in to continue ordering your campus favorites.';

        submitButton.textContent = isSignup ? 'Create Account' : 'Log In';
        confirmPassword.setCustomValidity('');
        updatePasswordStrength();
        updateFormValidation();
    }

    loginTab.addEventListener('click', function () {
        setMode('login');
    });

    signupTab.addEventListener('click', function () {
        setMode('signup');
    });

    function updatePasswordStrength() {
        const value = password.value;
        let score = 0;

        if (value.length >= 8) score++;
        if (value.length >= 12) score++;
        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
        if (/\d/.test(value)) score++;
        if (/[^A-Za-z0-9]/.test(value)) score++;

        let level = '';
        let label = 'Enter a password to check its strength.';
        let activeBars = 0;

        if (value.length > 0) {
            if (value.length < 8 || score <= 1) {
                level = 'weak';
                label = 'Weak password';
                activeBars = 1;
            } else if (score <= 3) {
                level = 'medium';
                label = 'Medium password';
                activeBars = 2;
            } else {
                level = 'strong';
                label = 'Strong password';
                activeBars = 3;
            }
        }

        strengthBars.forEach(function (bar, index) {
            bar.classList.remove('weak', 'medium', 'strong');

            if (index < activeBars) {
                bar.classList.add(level);
            }
        });

        strengthLabel.classList.remove('weak', 'medium', 'strong');

        if (level) {
            strengthLabel.classList.add(level);
        }

        strengthLabel.textContent = label;
    }

    function validatePasswordMatch() {
        if (formMode.value !== 'signup' || confirmPassword.disabled) {
            confirmPassword.setCustomValidity('');
            return;
        }

        if (
            confirmPassword.value !== '' &&
            confirmPassword.value !== password.value
        ) {
            confirmPassword.setCustomValidity('Your passwords do not match.');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }

    function validateInput(input, markTouched) {
        if (input.disabled) return true;

        if (markTouched) {
            input.dataset.touched = 'true';
        }

        let customError = '';
        const value = input.value.trim();

        if (
            input.dataset.validation === 'name' &&
            value !== '' &&
            !/^[\p{L}\s'-]+$/u.test(value)
        ) {
            customError = 'Use letters, spaces, apostrophes, or hyphens only.';
        } else if (
            input.dataset.validation === 'email' &&
            value !== '' &&
            !/^[^@\s]+@(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\.)+[A-Za-z]{2,}$/.test(value)
        ) {
            customError = 'Enter a complete email address, such as name@example.com.';
        } else if (
            input.dataset.validation === 'phone' &&
            formMode.value === 'signup' &&
            !/^[0-9]{11}$/.test(value)
        ) {
            customError = 'Enter exactly 11 digits for your phone number.';
        } else if (
            input.dataset.validation === 'confirm-password' &&
            formMode.value === 'signup' &&
            input.value !== '' &&
            input.value !== password.value
        ) {
            customError = 'Your passwords do not match.';
        }

        input.setCustomValidity(customError);
        const isValid = input.checkValidity();
        const showState = input.dataset.touched === 'true';

        input.classList.toggle(
            'validation-invalid',
            showState && !isValid
        );
        input.classList.toggle(
            'validation-valid',
            showState &&
                isValid &&
                (input.value !== '' || input.dataset.validation === 'phone')
        );
        input.setAttribute('aria-invalid', String(showState && !isValid));
        return isValid;
    }

    function updateFormValidation() {
        const activeInputs = Array.from(
            authForm.querySelectorAll('input:not([type="hidden"]):not(:disabled)')
        );
        let isValid = activeInputs.length > 0;

        activeInputs.forEach(function (input) {
            if (!validateInput(input, false)) {
                isValid = false;
            }
        });

        submitButton.disabled = !isValid;
    }

    authForm.querySelectorAll('input:not([type="hidden"])').forEach(function (input) {
        input.addEventListener('input', function () {
            validateInput(input, true);

            if (input === password) {
                updatePasswordStrength();
                validateInput(
                    confirmPassword,
                    confirmPassword.dataset.touched === 'true'
                );
            }

            if (input === confirmPassword) {
                validatePasswordMatch();
            }

            updateFormValidation();
        });

        input.addEventListener('blur', function () {
            validateInput(input, true);
            updateFormValidation();
        });
    });

    authForm.addEventListener('submit', function (event) {
        validatePasswordMatch();
        updateFormValidation();

        if (submitButton.disabled || !authForm.checkValidity() || (
            formMode.value === 'signup' &&
            password.value !== confirmPassword.value
        )) {
            event.preventDefault();
            authForm.querySelectorAll('input:not([type="hidden"]):not(:disabled)').forEach(function (input) {
                validateInput(input, true);
            });
            updateFormValidation();
        }
    });

    document.querySelectorAll('.toggle-password').forEach(function (button) {
        const eyeIcon = `
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
        `;

        const eyeOffIcon = `
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 3l18 18"/>
                <path d="M10.6 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15 15 0 0 1-3.1 3.8"/>
                <path d="M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10 10 0 0 0 4-.8"/>
                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
            </svg>
        `;

        button.innerHTML = eyeIcon;

        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.target);
            const showing = input.type === 'text';

            input.type = showing ? 'password' : 'text';
            button.innerHTML = showing ? eyeIcon : eyeOffIcon;
            button.setAttribute(
                'aria-label',
                showing ? 'Show password' : 'Hide password'
            );
            button.setAttribute('aria-pressed', String(!showing));
        });
    });

    // Toast notification behavior.
    const feedbackToast = document.getElementById('feedbackToast');
    const toastClose = document.getElementById('toastClose');
    const toastProgress = document.getElementById('toastProgress');

    let toastHideTimer = null;
    let toastProgressTimer = null;

    function dismissToast() {
        if (!feedbackToast || feedbackToast.hidden) return;

        clearTimeout(toastHideTimer);
        clearInterval(toastProgressTimer);

        feedbackToast.classList.add('hide');

        window.setTimeout(function () {
            feedbackToast.hidden = true;
            feedbackToast.classList.remove('hide', 'pop');
        }, 260);
    }

    function startToastTimer() {
        if (
            !feedbackToast ||
            feedbackToast.hidden ||
            feedbackToast.classList.contains('error')
        ) {
            return;
        }

        const duration = 4500;
        const startTime = Date.now();

        toastHideTimer = window.setTimeout(dismissToast, duration);

        toastProgressTimer = window.setInterval(function () {
            const elapsed = Date.now() - startTime;
            const remaining = Math.max(
                0,
                100 - (elapsed / duration) * 100
            );

            toastProgress.style.width = remaining + '%';
        }, 40);
    }

    if (feedbackToast && !feedbackToast.hidden) {
        window.requestAnimationFrame(function () {
            feedbackToast.classList.add('pop');
        });

        startToastTimer();
    }

    if (toastClose) {
        toastClose.addEventListener('click', dismissToast);
    }

    updatePasswordStrength();
    updateFormValidation();
</script>

</body>
</html>