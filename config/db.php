<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'l3_info');
define('DB_USER', 'root');
define('DB_PASS', '');
try {
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE " . DB_NAME);
} catch (PDOException $e) {
    die("ERREUR : " . $e->getMessage());
}
?>