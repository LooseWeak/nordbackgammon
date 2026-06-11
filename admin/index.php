<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireRole('admin');

$pageTitle = 'Dashboard — Admin Nord Backgammon';

$nbPlayers  = $pdo->query("SELECT COUNT(*) FROM players")->fetchColumn();
$nbMatches  = $pdo->query("SELECT COUNT(*) FROM matches")->fetchColumn();
$nbNews     = $pdo->query("SELECT COUNT(*) FROM news WHERE is_published = 1")->fetchColumn();
$nbMessages = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();

require_once 'includes/nav.php';
?>

<div class="container-fluid py-4 px-4">
    <div class="nb-page-header"><h1><i class="bi bi-speedometer2 me-2"></i>Tableau de bord</h1></div>

    <div class="row g-3 mb-5">
        <?php
        $counters = [
            ['bi-person-lines-fill', $nbPlayers,  'Joueurs',              null],
            ['bi-dice-5-fill',       $nbMatches,  'Matchs enregistrés',   null],
            ['bi-newspaper',         $nbNews,     'Actualités publiées',  null],
            ['bi-envelope-fill',     $nbMessages, 'Messages non lus',     $nbMessages > 0 ? '#E87128' : null],
        ];
        foreach ($counters as [$icon, $count, $label, $color]):
        ?>
        <div class="col-sm-6 col-xl-3">
            <div class="card p-3 text-center">
                <i class="bi <?= $icon ?> mb-2" style="font-size:2rem;color:<?= $color ?? '#E87128' ?>"></i>
                <div class="fw-bold fs-3 <?= $color ? 'text-warning' : '' ?>"><?= $count ?></div>
                <div class="text-muted small"><?= $label ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <h2 class="h6 text-uppercase fw-bold mb-3" style="color:#666;letter-spacing:.08em">Outils</h2>
    <div class="row g-3">
        <?php
        $tools = [
            ['Joueurs',         'players.php',         'bi-person-lines-fill', 'Ajouter, modifier ou supprimer des joueurs.'],
            ['Matchs',          'matches.php',          'bi-dice-5-fill',       'Enregistrer et gérer les matchs joués.'],
            ['Classement ELO',  'ranking.php',          'bi-trophy-fill',       'Voir le classement ELO complet.'],
            ['Championnat',     'championship.php',     'bi-award-fill',        'Classement et tableau du championnat annuel.'],
            ['Tableau matchs',  'championship-matrix.php', 'bi-grid-3x3-gap-fill', 'Tableau croisé des matchs de championnat.'],
            ['Recalcul ELO',    'ratings-update.php',   'bi-arrow-repeat',      'Recalculer tous les ratings depuis zéro.'],
            ['Actualités',      'news.php',             'bi-newspaper',         'Rédiger et publier des actualités.'],
            ['Utilisateurs',    'users.php',            'bi-people-fill',       'Gérer les comptes gérants et membres.'],
        ];
        foreach ($tools as [$label, $url, $icon, $desc]):
        ?>
        <div class="col-sm-6 col-lg-4 col-xl-3">
            <a href="<?= $url ?>" class="card p-3 d-flex flex-row align-items-center gap-3 text-decoration-none"
               style="transition:border-color .2s"
               onmouseover="this.style.borderColor='#E87128'" onmouseout="this.style.borderColor='#3a3a3a'">
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

<?php require_once 'includes/admin_footer.php'; ?>
