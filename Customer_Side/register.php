
<?php
// Validate and create a customer account submitted from the registration form.
session_start();

require_once __DIR__ . '/../database.php';

function isCompleteRegistrationEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false &&
        preg_match(
            '/^[^@\s]+@(?:[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?\.)+[A-Za-z]{2,}$/D',
            $email
        ) === 1;
}

if (isset($_SESSION['customer_id'])) {
    header('Location: account.php');
    exit;
}

$firstName = '';
$lastName = '';
$email = '';
$phone = '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
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
    } elseif (!isCompleteRegistrationEmail($email)) {
        $error = 'Please enter a complete, valid email address.';
    } elseif (strlen($email) > 150) {
        $error = 'Your email address must be 150 characters or fewer.';
    } elseif (!preg_match('/^[0-9]{11}$/', $phone)) {
        $error = 'Please enter an 11-digit phone number.';
    } elseif (strlen($password) < 8) {
        $error = 'Your password must be at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Your passwords do not match.';
    } else {
        $stmt = $conn->prepare(
            'SELECT id FROM customers WHERE email = ? LIMIT 1'
        );

        if (!$stmt) {
            $error = 'Something went wrong. Please try again.';
        } else {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $existingCustomer = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existingCustomer) {
                $error = 'An account with this email already exists.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare(
                    'INSERT INTO customers
                    (first_name, last_name, email, password_hash, phone)
                    VALUES (?, ?, ?, ?, ?)'
                );

                if (!$stmt) {
                    $error = 'Something went wrong. Please try again.';
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

                        header('Location: login.php?registered=1');
                        exit;
                    } else {
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
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/Purple_Blend/Images/pic1.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | Purple Blend</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            padding: 30px 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, Helvetica, sans-serif;
            color: #30253a;
            background: #f7f0fb;
        }

        .register-card {
            width: 100%;
            max-width: 460px;
            padding: 0 34px 34px;
            background: #fff;
            border: 1px solid #eee4f3;
            border-radius: 18px;
            box-shadow: 0 12px 35px rgba(78, 23, 107, 0.10);
            overflow: hidden;
        }

        .brand {
            margin: 0 -34px 26px;
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
            gap: 14px;
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
            border: 0;
            background: transparent;
            color: #6f2b91;
            font-weight: 700;
            cursor: pointer;
        }

        .message {
            margin-bottom: 16px;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
        }

        .error {
            color: #a32323;
            background: #fff0f0;
        }

        .register-button {
            width: 100%;
            margin-top: 24px;
            padding: 14px;
            border: 0;
            border-radius: 9px;
            background: #6f2b91;
            color: #fff;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        .register-button:hover {
            background: #4e176b;
        }

        .register-button:disabled {
            background: #b9a9c2;
            cursor: not-allowed;
        }

        .login-link {
            margin: 22px 0 0;
            text-align: center;
            color: #82778b;
            font-size: 14px;
        }

        a {
            color: #6f2b91;
            font-weight: 700;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .back-link {
            display: block;
            margin-top: 22px;
            text-align: center;
            color: #82778b;
            font-size: 13px;
        }

        @media (max-width: 420px) {
            .register-card {
                padding: 0 20px 25px;
            }

            .brand {
                margin: 0 -20px 24px;
            }

            .name-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>

<main class="register-card">
    <header class="brand">
        <img
            class="brand-logo"
            src="../Images/purple-blend-logo.jpg"
            alt="The Purple Blend"
        >
        <p>Your campus food stop</p>
    </header>

    <h2>Create your account</h2>
    <p class="intro">Join Purple Blend and order your campus favorites.</p>

    <?php if ($error !== ''): ?>
        <div class="message error" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php">
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
                    required
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
                    required
                >
            </div>
        </div>

        <label for="email">Email address *</label>
        <input
            type="email"
            id="email"
            name="email"
            maxlength="150"
            data-validation="email"
            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
            autocomplete="email"
            required
        >

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
            required
        >

        <label for="password">Password *</label>
        <div class="password-wrap">
            <input
                type="password"
                id="password"
                name="password"
                minlength="8"
                data-validation="password"
                autocomplete="new-password"
                required
            >
            <button
                type="button"
                class="toggle-password"
                data-target="password"
            >Show</button>
        </div>

        <label for="confirm_password">Confirm password *</label>
        <div class="password-wrap">
            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                minlength="8"
                data-validation="confirm-password"
                autocomplete="new-password"
                required
            >
            <button
                type="button"
                class="toggle-password"
                data-target="confirm_password"
            >Show</button>
        </div>

        <button type="submit" class="register-button" disabled>
            Create Account
        </button>
    </form>

    <p class="login-link">
        Already have an account? <a href="login.php">Log in</a>
    </p>

    <a class="back-link" href="index.php">← Back to menu</a>
</main>

<script>
    const registrationForm = document.querySelector('form[action="register.php"]');
    const registrationButton = registrationForm.querySelector('button[type="submit"]');
    const registrationPassword = document.getElementById('password');
    const registrationConfirmPassword = document.getElementById('confirm_password');

    function validateRegistrationInput(input, markTouched) {
        if (markTouched) {
            input.dataset.touched = 'true';
        }

        const value = input.value.trim();
        let customError = '';

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
            !/^[0-9]{11}$/.test(value)
        ) {
            customError = 'Enter exactly 11 digits for your phone number.';
        } else if (
            input.dataset.validation === 'confirm-password' &&
            input.value !== '' &&
            input.value !== registrationPassword.value
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

    function updateRegistrationValidity() {
        let isValid = true;
        registrationForm.querySelectorAll('input:not([type="hidden"])').forEach(function (input) {
            if (!validateRegistrationInput(input, false)) {
                isValid = false;
            }
        });
        registrationButton.disabled = !isValid;
    }

    registrationForm.querySelectorAll('input:not([type="hidden"])').forEach(function (input) {
        input.addEventListener('input', function () {
            validateRegistrationInput(input, true);
            if (input === registrationPassword) {
                validateRegistrationInput(
                    registrationConfirmPassword,
                    registrationConfirmPassword.dataset.touched === 'true'
                );
            }
            updateRegistrationValidity();
        });

        input.addEventListener('blur', function () {
            validateRegistrationInput(input, true);
            updateRegistrationValidity();
        });
    });

    registrationForm.addEventListener('submit', function (event) {
        updateRegistrationValidity();
        if (registrationButton.disabled || !registrationForm.checkValidity()) {
            event.preventDefault();
            registrationForm.querySelectorAll('input:not([type="hidden"])').forEach(function (input) {
                validateRegistrationInput(input, true);
            });
            updateRegistrationValidity();
        }
    });

    document.querySelectorAll('.toggle-password').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.target);
            const showing = input.type === 'text';

            input.type = showing ? 'password' : 'text';
            button.textContent = showing ? 'Show' : 'Hide';
        });
    });

    updateRegistrationValidity();
</script>

</body>
</html>
