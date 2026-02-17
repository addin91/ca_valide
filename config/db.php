<?php
define('DB_HOST', 'sql100.infinityfree.com');
define('DB_NAME', 'if0_41116418_ca_valide');
define('DB_USER', 'if0_41116418');
define('DB_PASS', 'QDGdQISTIcbkuO1');
try {
    // Connexion PDO avec charset UTF8MB4
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    );

    // Création de la base si elle n'existe pas
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");

} catch (PDOException $e) {
    die("ERREUR : " . $e->getMessage());
}
?>
