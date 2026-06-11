<?php
//
// fichier admin/news.php
//

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireRole('admin');

$user = getCurrentUser();
$success = null;
$error   = null;

$uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'news' . DIRECTORY_SEPARATOR;
$uploadUrl = '/assets/img/news/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

function uploadImage(array $file, string $dir): string {
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed)) {
        throw new Exception("Type non autorisé (jpg, png, gif, webp uniquement).");
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("Fichier trop volumineux (max 5 Mo).");
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('news_') . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        throw new Exception("Erreur lors de l'enregistrement du fichier.");
    }
    return $filename;
}

// AJAX : suppression d'une image de galerie
if (isset($_POST['delete_image'], $_POST['image_id'])) {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->prepare("SELECT filename FROM news_images WHERE id = ?");
        $stmt->execute([(int)$_POST['image_id']]);
        $filename = $stmt->fetchColumn();
        if ($filename) {
            @unlink($uploadDir . $filename);
            $pdo->prepare("DELETE FROM news_images WHERE id = ?")->execute([(int)$_POST['image_id']]);
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Suppression article (+ images)
if (isset($_POST['delete'], $_POST['id'])) {
    $imgs = $pdo->prepare("SELECT filename FROM news_images WHERE news_id = ?");
    $imgs->execute([$_POST['id']]);
    foreach ($imgs->fetchAll(PDO::FETCH_COLUMN) as $f) @unlink($uploadDir . $f);

    $stmt = $pdo->prepare("SELECT cover_image FROM news WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $cover = $stmt->fetchColumn();
    if ($cover) @unlink($uploadDir . $cover);

    $pdo->prepare("DELETE FROM news WHERE id = ?")->execute([$_POST['id']]);
    $success = "Article supprimé.";
}

// Toggle publication
if (isset($_POST['toggle'], $_POST['id'])) {
    $stmt = $pdo->prepare("SELECT is_published FROM news WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $current     = $stmt->fetchColumn();
    $newStatus   = $current ? 0 : 1;
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

        $published   = isset($_POST['is_published']) ? 1 : 0;
        $publishedAt = $published ? date('Y-m-d H:i:s') : null;

        $coverImage = null;
        if (!empty($_FILES['cover_image']['name'])) {
            $coverImage = uploadImage($_FILES['cover_image'], $uploadDir);
        }

        $pdo->prepare("INSERT INTO news (title, slug, excerpt, content, cover_image, author_id, is_published, published_at)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$title, $slug, $excerpt, $content, $coverImage, $user['id'], $published, $publishedAt]);
        $newsId = $pdo->lastInsertId();

        // Galerie
        if (!empty($_FILES['gallery']['name'][0])) {
            $stmtImg = $pdo->prepare("INSERT INTO news_images (news_id, filename) VALUES (?, ?)");
            foreach ($_FILES['gallery']['tmp_name'] as $i => $tmp) {
                if ($_FILES['gallery']['error'][$i] === UPLOAD_ERR_OK) {
                    $f = ['name' => $_FILES['gallery']['name'][$i], 'type' => $_FILES['gallery']['type'][$i],
                          'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => $_FILES['gallery']['size'][$i]];
                    $stmtImg->execute([$newsId, uploadImage($f, $uploadDir)]);
                }
            }
        }

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
        $stmt = $pdo->prepare("SELECT is_published, published_at, cover_image FROM news WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $existing    = $stmt->fetch();
        $publishedAt = ($published && !$existing['published_at']) ? date('Y-m-d H:i:s') : $existing['published_at'];

        $coverImage = $existing['cover_image'];
        if (!empty($_FILES['cover_image']['name'])) {
            $new = uploadImage($_FILES['cover_image'], $uploadDir);
            if ($coverImage) @unlink($uploadDir . $coverImage);
            $coverImage = $new;
        }

        $pdo->prepare("UPDATE news SET title=?, excerpt=?, content=?, cover_image=?, is_published=?, published_at=? WHERE id=?")
            ->execute([$title, $excerpt, $content, $coverImage, $published, $publishedAt, $_POST['id']]);

        // Nouvelles photos de galerie
        if (!empty($_FILES['gallery']['name'][0])) {
            $stmtImg = $pdo->prepare("INSERT INTO news_images (news_id, filename) VALUES (?, ?)");
            foreach ($_FILES['gallery']['tmp_name'] as $i => $tmp) {
                if ($_FILES['gallery']['error'][$i] === UPLOAD_ERR_OK) {
                    $f = ['name' => $_FILES['gallery']['name'][$i], 'type' => $_FILES['gallery']['type'][$i],
                          'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => $_FILES['gallery']['size'][$i]];
                    $stmtImg->execute([$_POST['id'], uploadImage($f, $uploadDir)]);
                }
            }
        }

        $success = "Article modifié.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupération articles + compteur galerie
$articles = $pdo->query("
    SELECT n.*, u.first_name, u.last_name,
           COUNT(ni.id) AS gallery_count
    FROM news n
    JOIN users u ON n.author_id = u.id
    LEFT JOIN news_images ni ON n.id = ni.news_id
    GROUP BY n.id
    ORDER BY n.created_at DESC
")->fetchAll();

// Photos de galerie par article (pour le modal d'édition)
foreach ($articles as &$art) {
    $stmt = $pdo->prepare("SELECT id, filename FROM news_images WHERE news_id = ? ORDER BY sort_order, id");
    $stmt->execute([$art['id']]);
    $art['gallery'] = $stmt->fetchAll();
}
unset($art);

$pageTitle = 'Actualités — Admin Nord Backgammon';
require_once 'includes/nav.php';
?>

<div class="container-fluid py-4 px-4">
    <div class="nb-page-header"><h1><i class="bi bi-newspaper me-2"></i>Gestion des actualités</h1></div>

    <?php if ($success): ?>
    <div class="alert alert-success mb-4"><i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-danger mb-4"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Formulaire de création -->
    <div class="card mb-4">
        <div class="card-header"><i class="bi bi-plus-circle me-2"></i>Nouvel article</div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data">
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
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-image me-1"></i>Photo de couverture</label>
                        <input type="file" name="cover_image" class="form-control" accept="image/*">
                        <div class="form-text">JPG, PNG, WebP · max 5 Mo</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-images me-1"></i>Photos supplémentaires <small class="text-muted">(galerie)</small></label>
                        <input type="file" name="gallery[]" class="form-control" accept="image/*" multiple>
                        <div class="form-text">Sélection multiple possible</div>
                    </div>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" name="is_published" class="form-check-input" id="pub_new">
                    <label class="form-check-label" for="pub_new">Publier immédiatement</label>
                </div>
                <button type="submit" name="add" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Créer l'article
                </button>
            </form>
        </div>
    </div>

    <!-- Liste des articles -->
    <div class="card">
        <div class="card-header"><i class="bi bi-list-ul me-2"></i>Articles (<?= count($articles) ?>)</div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3" style="width:60px"></th>
                        <th>Titre</th>
                        <th>Auteur</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $a): ?>
                    <tr>
                        <td class="ps-3 align-middle">
                            <?php if ($a['cover_image']): ?>
                            <img src="<?= $uploadUrl . htmlspecialchars($a['cover_image']) ?>"
                                 style="width:48px;height:36px;object-fit:cover;border-radius:4px">
                            <?php else: ?>
                            <i class="bi bi-image text-muted" style="font-size:1.4rem"></i>
                            <?php endif; ?>
                        </td>
                        <td class="align-middle">
                            <?= htmlspecialchars($a['title']) ?>
                            <?php if ($a['gallery_count'] > 0): ?>
                            <span class="badge bg-secondary ms-1" title="<?= $a['gallery_count'] ?> photo(s) en galerie">
                                <i class="bi bi-images"></i> <?= $a['gallery_count'] ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small align-middle"><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></td>
                        <td class="text-muted small align-middle"><?= date('d/m/Y', strtotime($a['created_at'])) ?></td>
                        <td class="align-middle">
                            <?php if ($a['is_published']): ?>
                                <span class="badge" style="background:#198754">Publié</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Brouillon</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-3 align-middle">
                            <button class="btn btn-sm btn-outline-secondary me-1"
                                    onclick='editArticle(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)'
                                    title="Modifier">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                <button type="submit" name="toggle"
                                        class="btn btn-sm <?= $a['is_published'] ? 'btn-outline-warning' : 'btn-outline-success' ?> me-1"
                                        title="<?= $a['is_published'] ? 'Dépublier' : 'Publier' ?>">
                                    <i class="bi bi-<?= $a['is_published'] ? 'eye-slash' : 'eye' ?>"></i>
                                </button>
                            </form>
                            <form method="post" class="d-inline"
                                  onsubmit="return confirm('Supprimer cet article et toutes ses photos ?')">
                                <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                <button type="submit" name="delete" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$articles): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Aucun article pour le moment.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal modification -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content" style="background:#2a2a2a;border-color:#444">
            <div class="modal-header" style="border-color:#444">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Modifier l'article</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="editForm" enctype="multipart/form-data">
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

                    <div class="row g-3 mb-3">
                        <!-- Couverture -->
                        <div class="col-md-5">
                            <label class="form-label"><i class="bi bi-image me-1"></i>Photo de couverture</label>
                            <div id="edit_current_cover" class="mb-2"></div>
                            <input type="file" name="cover_image" class="form-control" accept="image/*">
                            <div class="form-text">Laisser vide pour conserver la photo actuelle</div>
                        </div>
                        <!-- Galerie -->
                        <div class="col-md-7">
                            <label class="form-label"><i class="bi bi-images me-1"></i>Galerie photos</label>
                            <div id="edit_gallery" class="d-flex flex-wrap gap-2 mb-2"
                                 style="min-height:70px;padding:8px;background:#1a1a1a;border-radius:6px;border:1px solid #3a3a3a">
                            </div>
                            <input type="file" name="gallery[]" class="form-control" accept="image/*" multiple>
                            <div class="form-text">Ajouter de nouvelles photos · cliquer ✕ pour supprimer</div>
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" name="is_published" class="form-check-input" id="edit_published">
                        <label class="form-check-label" for="edit_published">Publié</label>
                    </div>
                    <button type="submit" name="edit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Enregistrer les modifications
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
const UPLOAD_URL = '/assets/img/news/';

function editArticle(a) {
    document.getElementById('edit_id').value           = a.id;
    document.getElementById('edit_title').value        = a.title;
    document.getElementById('edit_excerpt').value      = a.excerpt || '';
    document.getElementById('edit_content').value      = a.content;
    document.getElementById('edit_published').checked  = a.is_published == 1;

    // Couverture actuelle
    const coverDiv = document.getElementById('edit_current_cover');
    if (a.cover_image) {
        coverDiv.innerHTML = `<img src="${UPLOAD_URL}${a.cover_image}"
            style="height:80px;border-radius:6px;object-fit:cover;border:1px solid #444">`;
    } else {
        coverDiv.innerHTML = '<small class="text-muted"><i class="bi bi-image me-1"></i>Aucune photo de couverture</small>';
    }

    // Galerie
    renderGallery(a.gallery || []);

    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function renderGallery(images) {
    const div = document.getElementById('edit_gallery');
    if (!images.length) {
        div.innerHTML = '<small class="text-muted align-self-center"><i class="bi bi-images me-1"></i>Aucune photo dans la galerie</small>';
        return;
    }
    div.innerHTML = images.map(img => `
        <div class="position-relative" id="gimg_${img.id}" style="flex:0 0 auto">
            <img src="${UPLOAD_URL}${img.filename}"
                 style="height:64px;width:64px;object-fit:cover;border-radius:6px;border:1px solid #444">
            <button type="button"
                    onclick="deleteGalleryImage(${img.id})"
                    title="Supprimer cette photo"
                    style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;
                           border-radius:50%;border:none;background:#dc3545;color:#fff;
                           font-size:.65rem;line-height:1;cursor:pointer;display:flex;
                           align-items:center;justify-content:center">
                <i class="bi bi-x"></i>
            </button>
        </div>
    `).join('');
}

function deleteGalleryImage(id) {
    if (!confirm('Supprimer cette photo de la galerie ?')) return;
    const data = new FormData();
    data.append('delete_image', '1');
    data.append('image_id', id);
    fetch(window.location.href, { method: 'POST', body: data })
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                const el = document.getElementById('gimg_' + id);
                if (el) el.remove();
                if (!document.querySelector('#edit_gallery [id^="gimg_"]')) {
                    document.getElementById('edit_gallery').innerHTML =
                        '<small class="text-muted align-self-center"><i class="bi bi-images me-1"></i>Aucune photo dans la galerie</small>';
                }
            } else {
                alert('Erreur : ' + (result.message || 'Impossible de supprimer'));
            }
        })
        .catch(() => alert('Erreur réseau'));
}
</script>
JS;
require_once 'includes/admin_footer.php';
?>
