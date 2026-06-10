<?php
//
// fichier include/config.php
//

define('DB_HOST', 'localhost');
define('DB_NAME', 'backnord');
define('DB_USER', 'root');  // à modifier selon votre configuration
define('DB_PASS', '');      // à modifier selon votre configuration

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>
