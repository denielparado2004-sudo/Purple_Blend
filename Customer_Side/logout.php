<?php
// End the customer's session and return to the menu.
session_start();

// Clear all session data.
$_SESSION = [];

// Destroy the customer's session.
session_destroy();

// Return to the main menu.
header('Location: index.php');
exit;
?>