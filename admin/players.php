<?php
//
// fichier admin/players.php
//

require_once 'includes/config.php';

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
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des joueurs - BackNord</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <h1>Gestion des joueurs</h1>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- Formulaire d'ajout -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">Ajouter un joueur</h2>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Prénom</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nom</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="add" class="btn btn-primary">Ajouter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des joueurs -->
        <div class="card">
            <div class="card-header">
                <h2 class="h5 mb-0">Liste des joueurs</h2>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Prénom</th>
                                <th>Date création</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($players as $player): ?>
                            <tr id="row-<?= $player['id'] ?>">
                                <td><?= htmlspecialchars($player['last_name']) ?></td>
                                <td><?= htmlspecialchars($player['first_name']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($player['creation_date'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editPlayer(<?= htmlspecialchars(json_encode($player)) ?>)">
                                        Modifier
                                    </button>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="id" value="<?= $player['id'] ?>">
                                        <button type="submit" name="delete" class="btn btn-sm btn-danger" 
                                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce joueur ?')">
                                            Supprimer
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

    <!-- Modal de modification -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier un joueur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" id="editForm">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editPlayer(player) {
            document.getElementById('edit_id').value = player.id;
            document.getElementById('edit_first_name').value = player.first_name;
            document.getElementById('edit_last_name').value = player.last_name;
            
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }
    </script>
</body>
</html>