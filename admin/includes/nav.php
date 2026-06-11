<?php
$currentAdmin = basename($_SERVER['PHP_SELF'], '.php');
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Admin — Nord Backgammon' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background:#1a1a1a; color:#f0f0f0; }
        .nav-link { color:#aaa !important; transition:color .2s; }
        .nav-link:hover, .nav-link.active { color:#E87128 !important; }
        .card { background:#2a2a2a; border-color:#3a3a3a; color:#f0f0f0; }
        .card-header { background:#222; border-color:#3a3a3a; font-weight:600; }
        .table { color:#f0f0f0; --bs-table-bg:transparent; --bs-table-striped-bg:rgba(255,255,255,.04); --bs-table-hover-bg:rgba(232,113,40,.08); }
        .table th { color:#E87128; border-color:#3a3a3a; }
        .table td { border-color:#3a3a3a; }
        .table-bordered { border-color:#3a3a3a; }
        .form-control, .form-select {
            background:#2a2a2a; border-color:#3a3a3a; color:#f0f0f0;
        }
        .form-control:focus, .form-select:focus {
            background:#2a2a2a; border-color:#E87128; color:#f0f0f0;
            box-shadow:0 0 0 .25rem rgba(232,113,40,.2);
        }
        .btn-primary { background:#E87128; border-color:#E87128; }
        .btn-primary:hover { background:#cf6122; border-color:#cf6122; }
        .modal-content { background:#2a2a2a; border-color:#3a3a3a; }
        .modal-header, .modal-footer { border-color:#3a3a3a; }
        .alert-info { background:#1a3a4a; border-color:#2a5a6a; color:#9dd; }
        .alert-warning { background:#3a2a00; border-color:#5a4a00; color:#ffc; }
        .alert-success { background:#1a3a1a; border-color:#2a5a2a; color:#9d9; }
        .alert-danger { background:#3a1a1a; border-color:#5a2a2a; color:#f99; }
        .pagination .page-link { background:#2a2a2a; border-color:#3a3a3a; color:#f0f0f0; }
        .pagination .page-item.active .page-link { background:#E87128; border-color:#E87128; }
        .pagination .page-item.disabled .page-link { background:#1a1a1a; color:#555; }
        .nb-page-header { padding: 1.5rem 0 1rem; border-bottom: 1px solid #3a3a3a; margin-bottom: 1.5rem; }
        .nb-page-header h1 { color:#E87128; font-size:1.5rem; font-weight:700; margin:0; }
    </style>
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark sticky-top" style="background:#111;border-bottom:1px solid #3a3a3a">
    <div class="container-fluid px-3">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/admin/index.php">
            <img src="/assets/img/logo.jpg" alt="NB" height="36" class="rounded">
            <span style="color:#E87128;font-weight:700;font-size:.95rem">Admin</span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav me-auto gap-1 small">
                <li class="nav-item">
                    <a class="nav-link <?= $currentAdmin === 'players' ? 'active' : '' ?>" href="/admin/players.php">
                        <i class="bi bi-person-lines-fill"></i> Joueurs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentAdmin === 'matches' ? 'active' : '' ?>" href="/admin/matches.php">
                        <i class="bi bi-dice-5-fill"></i> Matchs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= in_array($currentAdmin, ['ranking','player-stats']) ? 'active' : '' ?>" href="/admin/ranking.php">
                        <i class="bi bi-trophy-fill"></i> Classement ELO
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= in_array($currentAdmin, ['championship','championship-matrix']) ? 'active' : '' ?>" href="/admin/championship.php">
                        <i class="bi bi-award-fill"></i> Championnat
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentAdmin === 'ratings-update' ? 'active' : '' ?>" href="/admin/ratings-update.php">
                        <i class="bi bi-arrow-repeat"></i> Recalcul ELO
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentAdmin === 'news' ? 'active' : '' ?>" href="/admin/news.php">
                        <i class="bi bi-newspaper"></i> Actualités
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentAdmin === 'users' ? 'active' : '' ?>" href="/admin/users.php">
                        <i class="bi bi-people-fill"></i> Utilisateurs
                    </a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-2 small">
                <span class="text-muted"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($user['first_name'] ?? $user['username']) ?></span>
                <a href="/" class="btn btn-sm btn-outline-secondary py-0">
                    <i class="bi bi-globe"></i> Site
                </a>
                <a href="/logout.php" class="btn btn-sm btn-outline-danger py-0">
                    <i class="bi bi-box-arrow-right"></i> Déco
                </a>
            </div>
        </div>
    </div>
</nav>
