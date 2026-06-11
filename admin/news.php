<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireRole('admin');
require_once 'includes/nav.php';

$user = getCurrentUser();
$success = null;
$error = null;

// Suppression
if (isset($_POST['delete'], $_POST['id'])) {
    $pdo->prepare("DELETE FROM news WHERE id = ?")->execute([$_POST['id']]);
    $success = "Article supprimé.";
}

// Toggle publication
if (isset($_POST['toggle'], $_POST['id'])) {
    $article = $pdo->prepare("SELECT is_published FROM news WHERE id = ?")->execute([$_POST['id']]) ?
               $pdo->prepare("SELECT is_published FROM news WHERE id = ?") : null;
    $stmt = $pdo->prepare("SELECT is_published FROM news WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $current = $stmt->fetchColumn();
    $newStatus = $current ? 0 : 1;
    $publishedAt = $newStatus ? date('Y-m-d H:i:s') : null;
    $pdo->prepare("UPDATE news SET is_published = ?, published_at = ? WHERE id = ?")
        ->execute([$newStatus, $publishedAt, $_POST['id']]);
    $success = $newStatus ? "Article publié." : "Article dépublié.";
}

// Création
if (isset($_POST['add'])) {
    try {
        $title   = trim($_POST['title']);
        $excerpt = trim($_POST['excerpt']);
        $content = trim($_POST['content']);
        if (!$title || !$content) throw new Exception("Le titre et le contenu sont obligatoires.");

        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $title)));
        $slug = trim($slug, '-') . '-' . time();

        $published = isset($_POST['is_published']) ? 1 : 0;
        $publishedAt = $published ? date('Y-m-d H:i:s') : null;

        $pdo->prepare("INSERT INTO news (title, slug, excerpt, content, author_id, is_published, published_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$title, $slug, $excerpt, $content, $user['id'], $published, $publishedAt]);
        $success = "Article créé.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Modification
if (isset($_POST['edit'])) {
    try {
        $title   = trim($_POST['title']);
        $excerpt = trim($_POST['excerpt']);
        $content = trim($_POST['content']);
        if (!$title || !$content) throw new Exception("Le titre et le contenu sont obligatoires.");

        $published = isset($_POST['is_published']) ? 1 : 0;
        $stmt = $pdo->prepare("SELECT is_published, published_at FROM news WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $existing = $stmt->fetch();
        $publishedAt = ($published && !$existing['published_at']) ? date('Y-m-d H:i:s') : $existing['published_at'];

        $pdo->prepare("UPDATE news SET title=?, excerpt=?, content=?, is_published=?, published_at=? WHERE id=?")
            ->execute([$title, $excerpt, $content, $published, $publishedAt, $_POST['id']]);
        $success = "Article modifié.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$articles = $pdo->query("
    SELECT n.*, u.first_name, u.last_name
    FROM news n JOIN users u ON n.author_id = u.id
    ORDER BY n.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualités — Admin Nord Backgammon</title>
</head>
<body>
<div class="container-fluid py-4 px-4">
    <h1 class="h4 fw-bold mb-4" style="color:#E87128">Gestion des actualités</h1>

    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Formulaire de création -->
    <div class="card mb-4">
        <div class="card-header fw-bold">Nouvel article</div>
        <div class="card-body">
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Titre *</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Résumé <small class="text-muted">(affiché sur la liste)</small></label>
                    <input type="text" name="excerpt" class="form-control" maxlength="255">
                </div>
                <div class="mb-3">
                    <label class="form-label">Contenu *</label>
                    <textarea name="content" class="form-control" rows="8" required></textarea>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" name="is_published" class="form-check-input" id="pub_new">
                    <label class="form-check-label" for="pub_new">Publier immédiatement</label>
                </div>
                <button type="submit" name="add" class="btn btn-primary">Créer l'article</button>
            </form>
        </div>
    </div>

    <!-- Liste des articles -->
    <div class="card">
        <div class="card-header fw-bold">Articles (<?= count($articles) ?>)</div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Titre</th>
                        <th>Auteur</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th class="pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $a): ?>
                    <tr>
                        <td class="ps-3"><?= htmlspecialchars($a['title']) ?></td>
                        <td><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></td>
                        <td><?= date('d/m/Y', strtotime($a['created_at'])) ?></td>
                        <td>
                            <?php if ($a['is_published']): ?>
                                <span class="badge" style="background:#198754">Publié</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Brouillon</span>
                            <?php endif; ?>
                        </td>
                        <td class="pe-3">
                            <button class="btn btn-sm btn-primary"
                                    onclick="editArticle(<?= htmlspecialchars(json_encode($a)) ?>)">
                                Modifier
                            </button>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                <button type="submit" name="toggle" class="btn btn-sm btn-outline-secondary">
                                    <?= $a['is_published'] ? 'Dépublier' : 'Publier' ?>
                                </button>
                            </form>
                            <form method="post" class="d-inline"
                                  onsubmit="return confirm('Supprimer cet article ?')">
                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                <button type="submit" name="delete" class="btn btn-sm btn-danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$articles): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">Aucun article pour le moment.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal modification -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier l'article</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Titre *</label>
                        <input type="text" name="title" id="edit_title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Résumé</label>
                        <input type="text" name="excerpt" id="edit_excerpt" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contenu *</label>
                        <textarea name="content" id="edit_content" class="form-control" rows="10" required></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" name="is_published" class="form-check-input" id="edit_published">
                        <label class="form-check-label" for="edit_published">Publié</label>
                    </div>
                    <button type="submit" name="edit" class="btn btn-primary">Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editArticle(a) {
    document.getElementById('edit_id').value      = a.id;
    document.getElementById('edit_title').value   = a.title;
    document.getElementById('edit_excerpt').value = a.excerpt || '';
    document.getElementById('edit_content').value = a.content;
    document.getElementById('edit_published').checked = a.is_published == 1;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
</body>
</html>
