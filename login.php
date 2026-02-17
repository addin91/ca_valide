<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']);
    $password = $_POST['password'];

    if (empty($nom) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
    } else {
        $result = login_user($pdo, $nom, $password);
        if ($result === true) {
            header("Location: index.php");
            exit;
        } else {
            $error = $result;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - L3 Info Calc</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <header>
        <h1><a href="/">Ca Valide</a></h1>
        <nav>
            <a href="index.php">Retour</a>
            <a href="register.php">Inscription</a>
        </nav>
    </header>

    <div class="auth-container">
        <h2>Connexion</h2>

        <?php if ($error): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="nom">Pseudo</label>
                <input type="text" id="nom" name="nom" required
                    value="<?php echo isset($_POST['nom']) ? htmlspecialchars($_POST['nom']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn-primary">Se connecter</button>
        </form>

        <div class="auth-footer">
            Pas encore de compte ? <a href="register.php">S'inscrire</a>
        </div>
    </div>
</body>

</html>
