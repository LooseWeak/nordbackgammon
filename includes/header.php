<?php
require_once __DIR__ . '/../admin/includes/auth.php';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Nord Backgammon' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark nb-navbar fixed-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/">
            <img src="/assets/img/logo.jpg" alt="Nord Backgammon" height="48" class="rounded">
            <span class="fw-bold">Nord Backgammon</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>" href="/">Le club</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'actualites' ? 'active' : '' ?>" href="/actualites.php">Actualités</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'classement' ? 'active' : '' ?>" href="/classement.php">Classement</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'contact' ? 'active' : '' ?>" href="/contact.php">Contact</a>
                </li>
                <?php if (isLoggedIn()): ?>
                <li class="nav-item">
                    <a class="nav-link text-warning" href="/admin/index.php"><i class="bi bi-gear-fill"></i> Admin</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
                </li>
                <?php else: ?>
                <li class="nav-item ms-lg-2">
                    <a class="btn btn-outline-nb btn-sm" href="/login.php">Connexion</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
