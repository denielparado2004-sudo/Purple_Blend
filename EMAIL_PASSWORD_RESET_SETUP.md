# Purple Blend customer password reset email setup

The reset flow uses PHPMailer over authenticated SMTP. It sends a one-time link,
stores only a SHA-256 token hash in MySQL, expires links after one hour, and
does not disclose whether an email address has an account.

## 1. Install PHPMailer

Open Command Prompt in the `Purple_Blend` project root (the folder containing
`composer.json`) and run:

```bash
composer install
```

This creates `vendor/autoload.php`, which is intentionally not included in the
project ZIP.

If Composer is not installed, install Composer for Windows, then reopen Command
Prompt and run the command above.

## 2. Configure SMTP

Copy `email_config.php.example` to `email_config.php` in the project root.
Open `email_config.php` and enter your sender email and SMTP credentials.

For Gmail, use a Google App Password (requires 2-Step Verification), not your
normal Gmail password. Never share the app password or put it in screenshots.
Other SMTP providers may use different host, port, and encryption settings.

Keep `email_config.php` private. It is excluded from the ZIP because it contains
credentials.

## 3. Create the token table

In phpMyAdmin, select `purple_blend`, open the SQL tab, and run the contents of
`password_reset_setup.sql`. Run it only once; `IF NOT EXISTS` makes repeat runs
safe.

## 4. Test locally

1. Start Apache and MySQL in XAMPP.
2. Open `http://localhost/Purple_Blend/Customer_Side/forgot_password.php`.
3. Enter the email of a test customer account you control.
4. Check inbox and spam, open the link, set a new password, then log in.
5. Confirm the same link cannot be reused and an expired link is rejected.

The link is generated from `base_url`. Local links work only on the same
computer/network where the project is served; use a publicly reachable HTTPS
base URL for a deployed system.

## If email is not sent

Check the PHP/Apache error log. The page intentionally displays the same
message for existing and non-existing accounts. If SMTP is not configured,
the request will appear accepted but no email will be delivered; configure SMTP
before demonstrating this feature.
