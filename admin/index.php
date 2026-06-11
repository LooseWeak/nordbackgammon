<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireRole('admin');
require_once 'includes/nav.php';

// Compteurs pour le dashboard
$nbPlayers  = $pdo->query("SELECT COUNT(*) FROM players")->fetchColumn();
$nbMatches  = $pdo->query("SELECT COUNT(*) FROM matches")->fetchColumn();
$nbNews     = $pdo->query("SELECT COUNT(*) FROM news WHERE is_published = 1")->fetchColumn();
$nbMessages = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Admin Nord Backgammon</title>
</head>
<body>

<div class="container-fluid py-4 px-4">
    <h1 class="h4 fw-bold mb-4" style="color:#E87128">Tableau de bord</h1>

    <!-- Compteurs -->
    <div class="row g-3 mb-5">
        <div class="col-sm-6 col-xl-3">
            <div class="card p-3 text-center">
                <i class="bi bi-person-lines-fill mb-2" style="font-size:2rem;color:#E87128"></i>
                <div class="fw-bold fs-3"><?= $nbPlayers ?></div>
                <div class="text-muted small">Joueurs</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card p-3 text-center">
                <i class="bi bi-dice-5-fill mb-2" style="font-size:2rem;color:#E87128"></i>
                <div class="fw-bold fs-3"><?= $nbMatches ?></div>
                <div class="text-muted small">Matchs enregistrés</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card p-3 text-center">
                <i class="bi bi-newspaper mb-2" style="font-size:2rem;color:#E87128"></i>
                <div class="fw-bold fs-3"><?= $nbNews ?></div>
                <div class="text-muted small">Actualités publiées</div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card p-3 text-center">
                <i class="bi bi-envelope-fill mb-2" style="font-size:2rem;color:<?= $nbMessages > 0 ? '#E87128' : '#888' ?>"></i>
                <div class="fw-bold fs-3 <?= $nbMessages > 0 ? 'text-warning' : '' ?>"><?= $nbMessages ?></div>
                <div class="text-muted small">Messages non lus</div>
            </div>
        </div>
    </div>

    <!-- Raccourcis -->
    <h2 class="h6 text-uppercase fw-bold mb-3" style="color:#888;letter-spacing:.08em">Gestion</h2>
    <div class="row g-3">
        <?php
        $tools = [
            ['Joueurs',       'players.php',       'bi-person-lines-fill', 'Ajouter, modifier ou supprimer des joueurs.'],
            ['Matchs',        'matches.php',        'bi-dice-5-fill',       'Enregistrer et gérer les matchs joués.'],
            ['Classement',    'ranking.php',        'bi-trophy-fill',       'Voir le classement ELO complet.'],
            ['Championnat',   'championship.php',   'bi-award-fill',        'Classement et tableau du championnat.'],
            ['Recalcul ELO',  'ratings-update.php', 'bi-arrow-repeat',      'Recalculer tous les ratings depuis zéro.'],
            ['Actualités',    'news.php',           'bi-newspaper',         'Rédiger et publier des actualités.'],
            ['Utilisateurs',  'users.php',          'bi-people-fill',       'Gérer les comptes gérants et membres.'],
        ];
        foreach ($tools as [$label, $url, $icon, $desc]):
        ?>
        <div class="col-sm-6 col-lg-4">
            <a href="<?= $url ?>" class="card p-3 d-flex flex-row align-items-center gap-3 text-decoration-none" style="transition:border-color .2s" onmouseover="this.style.borderColor='#E87128'" onmouseout="this.style.borderColor='#3a3a3a'">
                <i class="bi <?= $icon ?>" style="font-size:1.8rem;color:#E87128;min-width:2rem"></i>
                <div>
                    <div class="fw-bold"><?= $label ?></div>
                    <div class="text-muted small"><?= $desc ?></div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
