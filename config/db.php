<?php
// Configuration de la base de données
// À adapter selon la configuration locale (XAMPP défaut : root / pas de mdp)
define('DB_HOST', 'localhost');
define('DB_NAME', 'l3_info');
define('DB_USER', 'root');
define('DB_PASS', '');
try {
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    // Définir le mode d'erreur PDO sur Exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Créer la base de données si elle n'existe pas (utile pour le premier lancement)
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE " . DB_NAME);
    
    // Tentative de connexion directe à la DB (post-création)
    // $pdo est déjà connecté au serveur, mais le USE a sélectionné la DB.
    
} catch(PDOException $e) {
    die("ERREUR : Impossible de se connecter. " . $e->getMessage());
}
?>
