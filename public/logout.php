<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    track_action($pdo, $_SESSION['user_id'], "Déconnexion");
}

logout_user();
header("Location: login.php");
exit;
?>