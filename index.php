<?php
$pageTitle = 'Nord Backgammon — Les board\'Elites';
require_once 'admin/includes/config.php';
require_once 'includes/header.php';

// 3 dernières actualités publiées
$latestNews = $pdo->query("
    SELECT n.*, u.first_name, u.last_name
    FROM news n
    JOIN users u ON n.author_id = u.id
    WHERE n.is_published = 1
    ORDER BY n.published_at DESC
    LIMIT 3
")->fetchAll();

// Top 5 classement ELO
$topPlayers = $pdo->query("
    SELECT first_name, last_name, ranking, experience
    FROM players
    WHERE experience > 0
    ORDER BY ranking DESC
    LIMIT 5
")->fetchAll();
?>

<!-- HERO -->
<section class="nb-hero">
    <div class="hero-content text-white px-3">
        <img src="/assets/img/logo.jpg" alt="Nord Backgammon" class="rounded mb-4"
             style="width:140px; box-shadow:0 0 40px rgba(232,113,40,.4)">
        <h1 class="text-white">Nord Backgammon</h1>
        <p class="tagline mb-4">Les board'Elites — Club de backgammon à Lille</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="/contact.php" class="btn btn-nb btn-lg">Nous rejoindre</a>
            <a href="/actualites.php" class="btn btn-outline-nb btn-lg">Nos actualités</a>
        </div>
    </div>
</section>

<!-- PILIERS -->
<section class="nb-section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">Le club en <span>3 mots</span></h2>
            <div class="section-divider"></div>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="nb-card text-center p-4">
                    <div class="pilier-icon"><i class="bi bi-dice-5"></i></div>
                    <h3 class="h5 fw-bold mb-2">Découvrir</h3>
                    <p class="text-muted">Le backgammon est l'un des jeux de plateau les plus anciens du monde. Stratégie, calcul, gestion du risque — un jeu accessible à tous, infini pour les passionnés.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="nb-card text-center p-4">
                    <div class="pilier-icon"><i class="bi bi-people-fill"></i></div>
                    <h3 class="h5 fw-bold mb-2">Jouer</h3>
                    <p class="text-muted">Des soirées conviviales régulières à Lille, ouvertes aux débutants comme aux confirmés. Parties amicales, tournois internes, championnat annuel.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="nb-card text-center p-4">
                    <div class="pilier-icon"><i class="bi bi-trophy-fill"></i></div>
                    <h3 class="h5 fw-bold mb-2">Progresser</h3>
                    <p class="text-muted">Un système de classement ELO pour suivre ta progression. Des conseils entre membres, et bientôt des ressources pédagogiques pour tous les niveaux.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CLASSEMENT APERÇU -->
<?php if ($topPlayers): ?>
<section class="nb-section nb-section-dark">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">Top <span>classement ELO</span></h2>
            <div class="section-divider"></div>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <table class="table ranking-table table-borderless">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Joueur</th>
                            <th class="text-end">Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topPlayers as $i => $p): ?>
                        <tr>
                            <td class="fw-bold" style="color:<?= $i === 0 ? '#FFD700' : ($i === 1 ? '#C0C0C0' : ($i === 2 ? '#CD7F32' : 'var(--nb-muted)')) ?>">
                                <?= $i + 1 ?>
                            </td>
                            <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                            <td class="text-end fw-bold" style="color:var(--nb-orange)">
                                <?= number_format($p['ranking'] / 100, 0, ',', '') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="text-center mt-2">
                    <a href="/classement.php" class="btn btn-outline-nb btn-sm">Classement complet</a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ACTUALITÉS -->
<?php if ($latestNews): ?>
<section class="nb-section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">Dernières <span>actualités</span></h2>
            <div class="section-divider"></div>
        </div>
        <div class="row g-4">
            <?php foreach ($latestNews as $article): ?>
            <div class="col-md-4">
                <div class="nb-card">
                    <div class="card-body">
                        <p class="news-date mb-1"><?= date('d/m/Y', strtotime($article['published_at'])) ?></p>
                        <h3 class="card-title h6 mb-2"><?= htmlspecialchars($article['title']) ?></h3>
                        <p class="card-text"><?= htmlspecialchars($article['excerpt'] ?? '') ?></p>
                        <a href="/actualite.php?id=<?= $article['id'] ?>" class="btn btn-nb btn-sm mt-2">Lire</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="/actualites.php" class="btn btn-outline-nb">Toutes les actualités</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- GALERIE -->
<section class="nb-section nb-section-dark">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">La vie du <span>club</span></h2>
            <div class="section-divider"></div>
        </div>
        <div class="gallery-grid">
            <?php for ($i = 1; $i <= 6; $i++): ?>
            <img src="/assets/img/photo<?= sprintf('%02d', $i) ?>.jpg"
                 alt="Photo du club <?= $i ?>"
                 loading="lazy">
            <?php endfor; ?>
        </div>
    </div>
</section>

<!-- OÙ ET QUAND -->
<section class="nb-section">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">Où et <span>quand</span> ?</h2>
            <div class="section-divider"></div>
        </div>
        <div class="row justify-content-center g-4">
            <div class="col-md-5 text-center">
                <div class="nb-card p-4">
                    <i class="bi bi-geo-alt-fill pilier-icon"></i>
                    <h3 class="h5 fw-bold mb-2">Lieu</h3>
                    <p class="text-muted">Retrouvez-nous à Lille et ses environs.<br>Contactez-nous pour connaître le lieu exact des prochaines soirées.</p>
                    <a href="/contact.php" class="btn btn-nb btn-sm mt-2">Nous contacter</a>
                </div>
            </div>
            <div class="col-md-5 text-center">
                <div class="nb-card p-4">
                    <i class="bi bi-calendar-event-fill pilier-icon"></i>
                    <h3 class="h5 fw-bold mb-2">Soirées régulières</h3>
                    <p class="text-muted">Des rencontres régulières pour jouer, progresser et partager un bon moment avec les autres membres du club.</p>
                    <a href="https://www.facebook.com/groups/nordbackgammonlille" target="_blank" rel="noopener" class="btn btn-nb btn-sm mt-2">
                        <i class="bi bi-facebook me-1"></i> Agenda Facebook
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
