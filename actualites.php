<?php
$pageTitle = 'Actualités — Nord Backgammon';
require_once 'admin/includes/config.php';
require_once 'includes/header.php';

$perPage = 6;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$total = $pdo->query("SELECT COUNT(*) FROM news WHERE is_published = 1")->fetchColumn();
$totalPages = (int)ceil($total / $perPage);
$page = min($page, max(1, $totalPages));

$stmt = $pdo->prepare("
    SELECT n.*, u.first_name, u.last_name
    FROM news n
    JOIN users u ON n.author_id = u.id
    WHERE n.is_published = 1
    ORDER BY n.published_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$articles = $stmt->fetchAll();
?>

<section class="nb-section">
    <div class="container">
        <div class="text-center mb-5">
            <h1 class="section-title">Nos <span>actualités</span></h1>
            <div class="section-divider"></div>
        </div>

        <?php if ($articles): ?>
        <div class="row g-4">
            <?php foreach ($articles as $article): ?>
            <div class="col-md-6 col-lg-4">
                <div class="nb-card">
                    <div class="card-body d-flex flex-column">
                        <p class="news-date mb-1"><?= date('d/m/Y', strtotime($article['published_at'])) ?></p>
                        <h2 class="card-title h6 mb-2"><?= htmlspecialchars($article['title']) ?></h2>
                        <p class="card-text flex-grow-1"><?= htmlspecialchars($article['excerpt'] ?? '') ?></p>
                        <a href="/actualite.php?id=<?= $article['id'] ?>" class="btn btn-nb btn-sm mt-3">Lire la suite</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav class="mt-5">
            <ul class="pagination justify-content-center">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page - 1 ?>" style="background:var(--nb-surface);border-color:var(--nb-border);color:#fff">&laquo;</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"
                       style="background:<?= $i === $page ? 'var(--nb-orange)' : 'var(--nb-surface)' ?>;border-color:<?= $i === $page ? 'var(--nb-orange)' : 'var(--nb-border)' ?>;color:#fff">
                        <?= $i ?>
                    </a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page + 1 ?>" style="background:var(--nb-surface);border-color:var(--nb-border);color:#fff">&raquo;</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

        <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-newspaper text-muted" style="font-size:3rem"></i>
            <p class="text-muted mt-3">Aucune actualité pour le moment. Revenez bientôt !</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
