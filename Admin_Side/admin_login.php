
<?php
// Authenticate administrators and render the admin login form.
session_start();

require_once __DIR__ . '/../database.php';

$error = '';

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $stmt = $conn->prepare(
            "SELECT id, username, password_hash, role
             FROM admins
             WHERE username = ? AND is_active = 1
             LIMIT 1"
        );

        $stmt->bind_param('s', $username);
        $stmt->execute();

        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);

            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_toast'] = [
                'title' => 'Welcome back!',
                'message' => 'You are now signed in to the admin dashboard.'
            ];

            header('Location: dashboard.php');
            exit;
        }

        $error = 'Invalid username or password.';
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/jpeg" href="/Purple_Blend/Images/pic1.jpg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Purple Blend</title>

    <style>
        * { box-sizing: border-box; }

        body {
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 24px;
            font-family: Arial, Helvetica, sans-serif;
            color: #30253A;
            background: #F7F0FB;
        }

        .auth-card {
            width: 100%;
            max-width: 440px;
            padding: 0 34px 34px;
            overflow: hidden;
            border: 1px solid #EEE4F3;
            border-radius: 18px;
            background: #FFFFFF;
            box-shadow: 0 12px 35px rgba(78, 23, 107, 0.10);
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

        .brand p {
            margin: 0;
            padding: 12px 16px 0;
            color: #82778B;
            font-size: 14px;
        }

        .login-heading h1 {
            margin: 0 0 8px;
            font-size: 23px;
        }

        .login-heading p {
            margin: 0 0 22px;
            color: #82778B;
            font-size: 14px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin: 15px 0 7px;
            font-size: 13px;
            font-weight: 700;
        }

        .form-group input {
            width: 100%;
            min-width: 0;
            height: 47px;
            padding: 0 13px;
            border: 1px solid #DED3E5;
            border-radius: 9px;
            outline: none;
            background: #FFFFFF;
            color: #30253A;
            font: inherit;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus {
            border-color: #6F2B91;
            box-shadow: 0 0 0 3px rgba(111, 43, 145, 0.10);
        }

        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 55px; }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 9px;
            display: flex;
            width: 34px;
            height: 34px;
            align-items: center;
            justify-content: center;
            padding: 6px;
            transform: translateY(-50%);
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: #82778B;
            cursor: pointer;
        }

        .toggle-password:hover {
            background: #F7F0FB;
            color: #6F2B91;
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

        .error-message {
            margin-bottom: 20px;
            padding: 12px 14px;
            border: 1px solid #F0B9C0;
            border-radius: 8px;
            background: #FFF0F1;
            color: #A32032;
            font-size: 13px;
            line-height: 1.5;
        }

        .login-button {
            width: 100%;
            margin-top: 6px;
            padding: 14px;
            border: 0;
            border-radius: 9px;
            background: #6F2B91;
            color: #FFFFFF;
            cursor: pointer;
            font-size: 15px;
            font-weight: 700;
            transition: background 0.2s, transform 0.2s;
        }

        .login-button:hover { background: #4E176B; }
        .login-button:active { transform: scale(0.99); }

        .security-note {
            margin: 24px 0 0;
            color: #82778B;
            font-size: 12px;
            line-height: 1.7;
            text-align: center;
        }

        @media (max-width: 480px) {
            body { padding: 16px; }
            .auth-card { padding: 0 22px 26px; }
            .brand { margin: 0 -22px 24px; }
        }
    </style>
</head>

<body>
    <main class="auth-card">
        <header class="brand">
            <img
                class="brand-logo"
                src="../Images/purple-blend-logo.jpg"
                alt="The Purple Blend"
            >
            <p>Your campus food stop</p>
        </header>

        <div class="login-heading">
            <h1>Admin Sign In</h1>
            <p>Enter your sign-in name and password.</p>
        </div>

        <?php if ($error !== ''): ?>
            <div class="error-message" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="on">
            <div class="form-group">
                <label for="username">Sign-In Name</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your sign-in name"
                    autocomplete="username"
                    maxlength="50"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>

                <div class="password-wrapper">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        id="togglePassword"
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

            <button type="submit" class="login-button">Sign In</button>
        </form>

        <p class="security-note">
            Authorized personnel only. Keep your login credentials private.
        </p>
    </main>

    <script>
        const passwordInput = document.getElementById('password');
        const toggleButton = document.getElementById('togglePassword');
        const eyeIcon = '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>';
        const eyeOffIcon = '<path d="M3 3l18 18"/><path d="M10.6 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a15 15 0 0 1-3.1 3.8"/><path d="M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10 10 0 0 0 4-.8"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>';

        toggleButton.addEventListener('click', function () {
            const isHidden = passwordInput.type === 'password';

            passwordInput.type = isHidden ? 'text' : 'password';
            toggleButton.querySelector('svg').innerHTML =
                isHidden ? eyeOffIcon : eyeIcon;
            toggleButton.setAttribute(
                'aria-label',
                isHidden ? 'Hide password' : 'Show password'
            );
            toggleButton.setAttribute('aria-pressed', String(isHidden));
        });
    </script>
</body>
</html>
