<?php
// Keep the legacy sign-up URL pointed at the combined authentication page.
header('Location: register.php');
exit;