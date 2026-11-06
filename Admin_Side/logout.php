
<?php
// End the administrator's session and return to the login page.
session_start();

// Clear the admin session.
$_SESSION = [];

// Destroy the session.
session_destroy();

// Return to the admin login page.
header("Location: admin_login.php");
exit;
?>
