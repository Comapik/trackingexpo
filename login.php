<?php
require __DIR__ . '/auth.php';

if (isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (hash_equals(APP_PASSWORD, $password)) {
        setAuthCookie();
        header('Location: index.php');
        exit;
    }
    $error = 'Mot de passe incorrect';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>Connexion – Suivi Expo Photo</title>
<link rel="stylesheet" href="style.css">
</head>
<body class="login-body">
<main class="login-main">
    <h1>📸 Suivi de l'expo</h1>
    <form method="post" class="login-form">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" autofocus required>
        <?php if ($error !== ''): ?>
            <p class="login-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
        <?php endif; ?>
        <button type="submit" class="btn-huge">Se connecter</button>
    </form>
</main>
</body>
</html>
