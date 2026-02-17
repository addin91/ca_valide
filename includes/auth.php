<?php
session_start();
function is_logged_in()
{
    return isset($_SESSION['user_id']);
}
function require_login()
{
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}
function register_user($pdo, $nom, $password)
{
    $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE nom = ?");
    $stmt->execute([$nom]);
    if ($stmt->fetch())
        return "Ce nom est déjà utilisé.";
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom, mot_de_passe) VALUES (?, ?)");
    if ($stmt->execute([$nom, $hash])) {
        $user_id = $pdo->lastInsertId();
        track_action($pdo, $user_id, "Inscription");
        return true;
    }
    return "Erreur.";
}
function login_user($pdo, $nom, $password)
{
    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE nom = ?");
    $stmt->execute([$nom]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['mot_de_passe'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['nom'];
        track_action($pdo, $user['id'], "Connexion");
        return true;
    }

    return "Nom ou mot de passe incorrect.";
}

function logout_user()
{
    session_destroy();
}
?>