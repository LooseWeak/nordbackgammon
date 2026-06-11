<?php
//
// fichier admin/players.php
//

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireRole('admin');

// Suppression d'un joueur
if (isset($_POST['delete']) && isset($_POST['id'])) {
    try {
        // Vérifier d'abord si le joueur a des matchs
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM matches WHERE player1_id = ? OR player2_id = ?");
        $stmt->execute([$_POST['id'], $_POST['id']]);
        $hasMatches = $stmt->fetchColumn() > 0;

        if ($hasMatches) {
            $error = "Impossible de supprimer ce joueur car il a des matchs enregistrés.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM players WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $success = "Joueur supprimé avec succès.";
        }
    } catch(PDOException $e) {
        $error = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// Ajout d'un nouveau joueur
if (isset($_POST['add'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO players (first_name, last_name, ranking, experience) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            1500, // Valeur par défaut pour le ranking
            0     // Valeur par défaut pour l'experience
        ]);
        $success = "Joueur ajouté avec succès.";
    } catch(PDOException $e) {
        $error = "Erreur lors de l'ajout : " . $e->getMessage();
    }
}

// Modification d'un joueur
if (isset($_POST['edit'])) {
    try {
        $stmt = $pdo->prepare("UPDATE players SET first_name = ?, last_name = ? WHERE id = ?");
        $stmt->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['id']
        ]);
        $success = "Joueur modifié avec succès.";
    } catch(PDOException $e) {
        $error = "Erreur lors de la modification : " . $e->getMessage();
    }
}

// Récupération de tous les joueurs
$players = $pdo->query("SELECT * FROM players ORDER BY last_name, first_name")->fetchAll();

$pageTitle = 'Joueurs — Admin Nord Backgammon';
require_once 'includes/nav.php';
?>

<div class="container-fluid py-4 px-4">
    <div class="nb-page-header"><h1><i class="bi bi-person-lines-fill me-2"></i>Gestion des joueurs</h1></div>

    <?php if (isset($error)):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (isset($success)): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">Ajouter un joueur</div>
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Prénom</label>
                    <input type="text" name="first_name" class="form-control" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Nom</label>
                    <input type="text" name="last_name" class="form-control" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" name="add" class="btn btn-primary w-100">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Liste des joueurs (<?= count($players) ?>)</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">Nom</th>
                            <th>Prénom</th>
                            <th>Création</th>
                            <th class="pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($players as $player): ?>
                        <tr>
                            <td class="ps-3 fw-bold"><?= htmlspecialchars($player['last_name']) ?></td>
                            <td><?= htmlspecialchars($player['first_name']) ?></td>
                            <td class="text-muted small"><?= date('d/m/Y', strtotime($player['creation_date'])) ?></td>
                            <td class="pe-3">
                                <button class="btn btn-sm btn-primary" onclick="editPlayer(<?= htmlspecialchars(json_encode($player)) ?>)">
                                    <i class="bi bi-pencil"></i> Modifier
                                </button>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="id" value="<?= $player['id'] ?>">
                                    <button type="submit" name="delete" class="btn btn-sm btn-danger"
                                            onclick="return confirm('Supprimer ce joueur ?')">
                                        <i class="bi bi-trash"></i> Supprimer
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Modifier un joueur</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label class="form-label">Prénom</label>
                        <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nom</label>
                        <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                    </div>
                    <button type="submit" name="edit" class="btn btn-primary">Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$extraScript = "<script>
function editPlayer(p) {
    document.getElementById('edit_id').value = p.id;
    document.getElementById('edit_first_name').value = p.first_name;
    document.getElementById('edit_last_name').value = p.last_name;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>";
require_once 'includes/admin_footer.php';
?>