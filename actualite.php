<?php
require_once 'admin/includes/config.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /actualites.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT n.*, u.first_name, u.last_name
    FROM news n
    JOIN users u ON n.author_id = u.id
    WHERE n.id = ? AND n.is_published = 1
");
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
    header('Location: /actualites.php');
    exit;
}

$pageTitle = htmlspecialchars($article['title']) . ' — Nord Backgammon';
require_once 'includes/header.php';
?>

<section class="nb-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <a href="/actualites.php" class="text-muted small d-inline-flex align-items-center gap-1 mb-4">
                    <i class="bi bi-arrow-left"></i> Toutes les actualités
                </a>
                <p class="news-date mb-2"><?= date('d/m/Y', strtotime($article['published_at'])) ?></p>
                <h1 class="fw-bold mb-4" style="font-size:clamp(1.5rem,4vw,2.5rem)">
                    <?= htmlspecialchars($article['title']) ?>
                </h1>
                <hr style="border-color:var(--nb-orange);opacity:1;width:60px;margin-left:0">
                <div class="mt-4 article-content" style="line-height:1.8;color:#ccc">
                    <?= nl2br(htmlspecialchars($article['content'])) ?>
                </div>
                <p class="text-muted small mt-4">
                    Par <?= htmlspecialchars($article['first_name'] . ' ' . $article['last_name']) ?>
                </p>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
