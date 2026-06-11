<?php
// Script d'installation unique — à supprimer après exécution
require_once 'admin/includes/config.php';

$errors = [];
$success = [];

// Création des tables
$tables = [
    "users" => "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(150) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin', 'member') NOT NULL DEFAULT 'member',
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        is_active TINYINT(1) NOT NULL DEFAULT 1
    )",
    "news" => "CREATE TABLE IF NOT EXISTS news (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL UNIQUE,
        excerpt TEXT,
        content LONGTEXT NOT NULL,
        author_id INT NOT NULL,
        is_published TINYINT(1) NOT NULL DEFAULT 0,
        published_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (author_id) REFERENCES users(id)
    )",
    "contact_messages" => "CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(150) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        is_read TINYINT(1) NOT NULL DEFAULT 0
    )",
];

foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        $success[] = "Table <strong>$name</strong> créée.";
    } catch (PDOException $e) {
        $errors[] = "Table $name : " . $e->getMessage();
    }
}

// Création du compte admin si inexistant
try {
    $existing = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'")->fetchColumn();
    if ($existing == 0) {
        $hash = password_hash('changeme123', PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, first_name, last_name)
                               VALUES (?, ?, ?, 'admin', 'Admin', 'Nord Backgammon')");
        $stmt->execute(['admin', 'admin@nordbackgammon.fr', $hash]);
        $success[] = "Compte admin créé (login: <strong>admin</strong> / mot de passe: <strong>changeme123</strong>).";
    } else {
        $success[] = "Compte admin déjà existant — ignoré.";
    }
} catch (PDOException $e) {
    $errors[] = "Compte admin : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Installation Nord Backgammon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white p-4">
    <div class="container" style="max-width:600px">
        <h1 class="mb-4">Installation Nord Backgammon</h1>
        <?php foreach ($success as $msg): ?>
            <div class="alert alert-success"><?= $msg ?></div>
        <?php endforeach; ?>
        <?php foreach ($errors as $msg): ?>
            <div class="alert alert-danger"><?= $msg ?></div>
        <?php endforeach; ?>
        <?php if (empty($errors)): ?>
            <div class="alert alert-warning mt-4">
                <strong>Installation terminée.</strong><br>
                Supprime ce fichier <code>setup.php</code> avant de mettre en ligne.<br>
                <a href="login.php" class="btn btn-primary mt-2">Aller à la page de connexion</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
