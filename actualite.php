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

// Photos de galerie
$imgStmt = $pdo->prepare("SELECT filename FROM news_images WHERE news_id = ? ORDER BY sort_order, id");
$imgStmt->execute([$id]);
$gallery = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

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
                <h1 class="fw-bold mb-3" style="font-size:clamp(1.5rem,4vw,2.5rem)">
                    <?= htmlspecialchars($article['title']) ?>
                </h1>
                <hr style="border-color:var(--nb-orange);opacity:1;width:60px;margin-left:0">

                <?php if ($article['cover_image']): ?>
                <div class="my-4" style="border-radius:10px;overflow:hidden;max-height:460px">
                    <img src="/assets/img/news/<?= htmlspecialchars($article['cover_image']) ?>"
                         alt="<?= htmlspecialchars($article['title']) ?>"
                         style="width:100%;height:100%;object-fit:cover">
                </div>
                <?php endif; ?>

                <div class="mt-4 article-content" style="line-height:1.8;color:#ccc">
                    <?= nl2br(htmlspecialchars($article['content'])) ?>
                </div>

                <?php if (!empty($gallery)): ?>
                <div class="mt-5">
                    <h3 class="h6 fw-bold mb-3" style="color:#E87128">
                        <i class="bi bi-images me-2"></i>Photos
                    </h3>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px">
                        <?php foreach ($gallery as $filename): ?>
                        <a href="/assets/img/news/<?= htmlspecialchars($filename) ?>" target="_blank"
                           style="display:block;height:160px;overflow:hidden;border-radius:8px;border:1px solid #333">
                            <img src="/assets/img/news/<?= htmlspecialchars($filename) ?>"
                                 alt=""
                                 style="width:100%;height:100%;object-fit:cover;transition:transform .3s"
                                 onmouseover="this.style.transform='scale(1.05)'"
                                 onmouseout="this.style.transform='scale(1)'">
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <p class="text-muted small mt-5">
                    Par <?= htmlspecialchars($article['first_name'] . ' ' . $article['last_name']) ?>
                </p>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
