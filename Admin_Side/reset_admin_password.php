<?php
// Route authenticated administrators to the account password controls.
session_start();

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

header('Location: account.php');
exit;
