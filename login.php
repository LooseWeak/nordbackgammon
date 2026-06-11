<?php
require_once 'admin/includes/config.php';
require_once 'admin/includes/auth.php';

if (isLoggedIn()) {
    header('Location: /admin/index.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'         => $user['id'],
                'username'   => $user['username'],
                'role'       => $user['role'],
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name'],
            ];
            header('Location: /admin/index.php');
            exit;
        }
    }
    $error = "Identifiant ou mot de passe incorrect.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — Nord Backgammon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #1a1a1a;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background-color: #2a2a2a;
            border: 1px solid #4a4a4a;
            border-radius: 12px;
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
        }
        .login-card img {
            width: 120px;
            display: block;
            margin: 0 auto 1.5rem;
        }
        .form-control {
            background-color: #1a1a1a;
            border-color: #4a4a4a;
            color: #fff;
        }
        .form-control:focus {
            background-color: #1a1a1a;
            border-color: #E87128;
            color: #fff;
            box-shadow: 0 0 0 0.25rem rgba(232,113,40,.25);
        }
        .btn-primary {
            background-color: #E87128;
            border-color: #E87128;
            width: 100%;
        }
        .btn-primary:hover {
            background-color: #cf6122;
            border-color: #cf6122;
        }
        .back-link {
            color: #aaa;
            font-size: .875rem;
            text-align: center;
            display: block;
            margin-top: 1rem;
        }
        .back-link:hover { color: #E87128; }
    </style>
</head>
<body>
    <div class="login-card">
        <img src="/assets/img/logo.jpg" alt="Nord Backgammon">
        <h1 class="h5 text-center mb-4">Espace gérants</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label class="form-label small">Identifiant</label>
                <input type="text" name="username" class="form-control" required autofocus
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="mb-4">
                <label class="form-label small">Mot de passe</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Se connecter</button>
        </form>
        <a href="/" class="back-link">← Retour au site</a>
    </div>
</body>
</html>
