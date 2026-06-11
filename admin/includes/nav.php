<?php
$currentAdmin = basename($_SERVER['PHP_SELF'], '.php');
$user = getCurrentUser();
?>
<nav class="navbar navbar-expand-lg navbar-dark" style="background:#111;border-bottom:1px solid #3a3a3a">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/admin/index.php">
            <img src="/assets/img/logo.jpg" alt="NB" height="36" class="rounded">
            <span style="color:#E87128;font-weight:700">Admin</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav me-auto gap-1">
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
                    <a class="nav-link <?= $currentAdmin === 'ranking' ? 'active' : '' ?>" href="/admin/ranking.php">
                        <i class="bi bi-trophy-fill"></i> Classement
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentAdmin === 'championship' ? 'active' : '' ?>" href="/admin/championship.php">
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
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($user['first_name'] ?? $user['username']) ?>
                </span>
                <a href="/" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-globe"></i> Site
                </a>
                <a href="/logout.php" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i> Déconnexion
                </a>
            </div>
        </div>
    </div>
</nav>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
    body { background:#1a1a1a; color:#f0f0f0; }
    .nav-link { color:#aaa; }
    .nav-link:hover, .nav-link.active { color:#E87128 !important; }
    .card { background:#2a2a2a; border-color:#3a3a3a; color:#f0f0f0; }
    .table { color:#f0f0f0; --bs-table-bg: transparent; --bs-table-striped-bg: rgba(255,255,255,.03); }
    .table th { color:#E87128; border-color:#3a3a3a; }
    .table td { border-color:#3a3a3a; }
    .form-control, .form-select { background:#2a2a2a; border-color:#3a3a3a; color:#f0f0f0; }
    .form-control:focus, .form-select:focus { background:#2a2a2a; border-color:#E87128; color:#f0f0f0; box-shadow:0 0 0 .25rem rgba(232,113,40,.2); }
    .btn-primary { background:#E87128; border-color:#E87128; }
    .btn-primary:hover { background:#cf6122; border-color:#cf6122; }
    .modal-content { background:#2a2a2a; border-color:#3a3a3a; }
    .modal-header { border-color:#3a3a3a; }
</style>
