<?php
//
// fichier admin/matches.php
//

require_once 'includes/config.php';

// Configuration de la pagination
$matches_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10; // Récupération du nombre de matches par page depuis l'URL
if (!in_array($matches_per_page, [5, 10, 20, 50])) {
    $matches_per_page = 10; // Valeur par défaut si la valeur n'est pas valide
}

$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $matches_per_page;

// Récupération du nombre total de matches pour la pagination
$total_matches = $pdo->query("SELECT COUNT(*) FROM matches")->fetchColumn();
$total_pages = ceil($total_matches / $matches_per_page);

// Validation de la page courante
if ($current_page < 1) {
    $current_page = 1;
} elseif ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
}

// Fonction pour générer les liens de pagination
function generatePaginationLinks($current_page, $total_pages) {
    $links = [];
    
    // Toujours afficher la première page
    $links[] = 1;
    
    // Calculer la plage de pages autour de la page courante
    $start = max(2, $current_page - 2);
    $end = min($total_pages - 1, $current_page + 2);
    
    // Ajouter des points de suspension si nécessaire après la première page
    if ($start > 2) {
        $links[] = '...';
    }
    
    // Ajouter les pages de la plage
    for ($i = $start; $i <= $end; $i++) {
        $links[] = $i;
    }
    
    // Ajouter des points de suspension si nécessaire avant la dernière page
    if ($end < $total_pages - 1) {
        $links[] = '...';
    }
    
    // Toujours afficher la dernière page si elle existe
    if ($total_pages > 1) {
        $links[] = $total_pages;
    }
    
    return $links;
}

// Fonction de vérification pour le championnat
function checkChampionshipRules($pdo, $player1_id, $player2_id, $points, $match_date, $match_id = null) {
    // Vérifier que le match est en 7 points
    if ($points != 7) {
        throw new Exception("Les matches de championnat doivent être en 7 points.");
    }

    // Récupérer l'année du match
    $year = date('Y', strtotime($match_date));

    // Récupérer tous les matches de championnat pour cette paire de joueurs cette année
    $stmt = $pdo->prepare("
        SELECT id 
        FROM matches 
        WHERE is_championship = TRUE 
        AND YEAR(match_date) = ? 
        AND ((player1_id = ? AND player2_id = ?) OR (player1_id = ? AND player2_id = ?))
    ");

    $stmt->execute([$year, $player1_id, $player2_id, $player2_id, $player1_id]);
    $championship_matches = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Si on modifie un match existant
    if ($match_id) {
        // Si ce match est déjà dans les matches de championnat, c'est ok
        if (in_array($match_id, $championship_matches)) {
            return true;
        }
        // Sinon, il faut qu'il y ait moins de 2 matches de championnat
        if (count($championship_matches) >= 2) {
            throw new Exception("MAX_CHAMPIONSHIP_REACHED|Ces joueurs ont déjà joué 2 matches de championnat en $year.");
        }
    } 
    // Pour un nouveau match
    else {
        if (count($championship_matches) >= 2) {
            throw new Exception("MAX_CHAMPIONSHIP_REACHED|Ces joueurs ont déjà joué 2 matches de championnat en $year.");
        }
    }

    return true;
}

// Récupération de tous les joueurs pour les listes déroulantes
$players = $pdo->query("SELECT id, first_name, last_name, ranking, experience FROM players ORDER BY last_name, first_name")->fetchAll();

// Suppression d'un match
if (isset($_POST['delete']) && isset($_POST['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM matches WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $success = "Match supprimé avec succès.";
    } catch(PDOException $e) {
        $error = "Erreur lors de la suppression : " . $e->getMessage();
    }
}

// Modification d'un match
if (isset($_POST['edit'])) {
    try {
        // Vérification que les deux joueurs sont différents
        if ($_POST['player1_id'] == $_POST['player2_id']) {
            throw new Exception("Un joueur ne peut pas jouer contre lui-même.");
        }

        // Vérification que les points sont une valeur impaire entre 1 et 25
        if (!in_array($_POST['points'], [1,3,5,7,9,11,13,15,17,19,21,23,25])) {
            throw new Exception("Le nombre de points doit être impair et compris entre 1 et 25.");
        }

        // Si c'est un match de championnat
        if (isset($_POST['is_championship']) && $_POST['is_championship']) {
            try {
                checkChampionshipRules(
                    $pdo, 
                    $_POST['player1_id'], 
                    $_POST['player2_id'], 
                    $_POST['points'],
                    $_POST['match_date'],
                    $_POST['id']  // ID du match en cours d'édition
                );
            } catch (Exception $e) {
                // Vérifier si c'est l'erreur spécifique des matchs de championnat
                if (strpos($e->getMessage(), 'MAX_CHAMPIONSHIP_REACHED') === 0) {
                    // Extraire le message réel après le code d'erreur
                    $parts = explode('|', $e->getMessage(), 2);
                    $errorMessage = $parts[1];
                    
                    // Définir une variable pour indiquer de proposer comme match normal
                    $proposeAsNormal = true;
                    $formAction = 'edit';
                    $formData = $_POST;
                    throw new Exception($errorMessage . " Voulez-vous enregistrer ce match comme un match hors championnat ?");
                } else {
                    throw $e; // Relancer l'exception si ce n'est pas la bonne erreur
                }
            }
        }

        // Récupération des données des joueurs
        $player1 = null;
        $player2 = null;
        foreach ($players as $player) {
            if ($player['id'] == $_POST['player1_id']) $player1 = $player;
            if ($player['id'] == $_POST['player2_id']) $player2 = $player;
        }

        $stmt = $pdo->prepare("
            UPDATE matches SET 
                match_date = ?,
                player1_id = ?,
                player2_id = ?,
                points = ?,
                score_player1 = ?,
                score_player2 = ?,
                is_championship = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $_POST['match_date'],
            $_POST['player1_id'],
            $_POST['player2_id'],
            $_POST['points'],
            $_POST['score_player1'],
            $_POST['score_player2'],
            isset($_POST['is_championship']) ? 1 : 0,
            $_POST['id']
        ]);

        $success = "Match modifié avec succès.";
    } catch(Exception $e) {
        $error = "Erreur lors de la modification : " . $e->getMessage();
    }
}

// Ajout d'un nouveau match
if (isset($_POST['add'])) {
    try {
        // Vérification que les deux joueurs sont différents
        if ($_POST['player1_id'] == $_POST['player2_id']) {
            throw new Exception("Un joueur ne peut pas jouer contre lui-même.");
        }

        // Vérification que les points sont une valeur impaire entre 1 et 25
        if (!in_array($_POST['points'], [1,3,5,7,9,11,13,15,17,19,21,23,25])) {
            throw new Exception("Le nombre de points doit être impair et compris entre 1 et 25.");
        }

        // Si c'est un match de championnat
        if (isset($_POST['is_championship']) && $_POST['is_championship']) {
            try {
                checkChampionshipRules(
                    $pdo, 
                    $_POST['player1_id'], 
                    $_POST['player2_id'], 
                    $_POST['points'],
                    $_POST['match_date']
                );
            } catch (Exception $e) {
                // Vérifier si c'est l'erreur spécifique des matchs de championnat
                if (strpos($e->getMessage(), 'MAX_CHAMPIONSHIP_REACHED') === 0) {
                    // Extraire le message réel après le code d'erreur
                    $parts = explode('|', $e->getMessage(), 2);
                    $errorMessage = $parts[1];
                    
                    // Définir une variable pour indiquer de proposer comme match normal
                    $proposeAsNormal = true;
                    $formAction = 'add';
                    $formData = $_POST;
                    throw new Exception($errorMessage . " Voulez-vous enregistrer ce match comme un match hors championnat ?");
                } else {
                    throw $e; // Relancer l'exception si ce n'est pas la bonne erreur
                }
            }
        }

        // Récupération des données des joueurs
        $player1 = null;
        $player2 = null;
        foreach ($players as $player) {
            if ($player['id'] == $_POST['player1_id']) $player1 = $player;
            if ($player['id'] == $_POST['player2_id']) $player2 = $player;
        }

        $stmt = $pdo->prepare("
            INSERT INTO matches (
                match_date, 
                player1_id, 
                player2_id, 
                points,
                score_player1,
                score_player2,
                is_championship
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_POST['match_date'],
            $_POST['player1_id'],
            $_POST['player2_id'],
            $_POST['points'],
            $_POST['score_player1'],
            $_POST['score_player2'],
            isset($_POST['is_championship']) ? 1 : 0
        ]);

        $success = "Match ajouté avec succès.";
    } catch(Exception $e) {
        $error = "Erreur lors de l'ajout : " . $e->getMessage();
    }
}

// Récupération des matches avec pagination
$matches = $pdo->prepare("
    SELECT m.*,
           p1.first_name as p1_first_name, p1.last_name as p1_last_name,
           p2.first_name as p2_first_name, p2.last_name as p2_last_name
    FROM matches m
    JOIN players p1 ON m.player1_id = p1.id
    JOIN players p2 ON m.player2_id = p2.id
    ORDER BY m.match_date DESC
    LIMIT ?, ?
");
$matches->bindValue(1, $offset, PDO::PARAM_INT);
$matches->bindValue(2, $matches_per_page, PDO::PARAM_INT);
$matches->execute();
$matches = $matches->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des matches - BackNord</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .pagination .page-link {
            padding: 0.5rem 0.75rem;
            margin: 0 2px;
            border-radius: 4px;
        }
        .pagination .active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .pagination .disabled .page-link {
            color: #6c757d;
            pointer-events: none;
            background-color: #fff;
            border-color: #dee2e6;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Gestion des matchs</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
                <?php if (isset($proposeAsNormal) && $proposeAsNormal): ?>
                    <form method="post" class="mt-2">
                        <?php foreach ($formData as $key => $value): ?>
                            <?php if ($key != 'is_championship'): ?>
                                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <button type="submit" name="<?= $formAction ?>" class="btn btn-primary">
                            Enregistrer comme match hors championnat
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <!-- formulaire d'ajout -->
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">Ajouter un match</h2>
            </div>
            <div class="card-body">
                <form method="post" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Date et heure</label>
                        <input type="text" name="match_date" class="form-control datetimePicker" required
                            value="<?= isset($formData['match_date']) && isset($proposeAsNormal) && $formAction == 'add' ? htmlspecialchars($formData['match_date']) : '' ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Points du match</label>
                        <select name="points" class="form-select" required>
                            <?php for($i=1; $i<=25; $i+=2): ?>
                                <option value="<?= $i ?>" <?= isset($formData['points']) && isset($proposeAsNormal) && $formAction == 'add' && $formData['points'] == $i ? 'selected' : '' ?>>
                                    <?= $i ?> points
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="form-check mt-2">
                            <input type="checkbox" name="is_championship" class="form-check-input" id="is_championship">
                            <label class="form-check-label" for="is_championship">Match de championnat</label>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Joueur 1</label>
                        <select name="player1_id" class="form-select" required>
                            <option value="">Sélectionner un joueur</option>
                            <?php foreach($players as $player): ?>
                                <option value="<?= $player['id'] ?>" <?= isset($formData['player1_id']) && isset($proposeAsNormal) && $formAction == 'add' && $formData['player1_id'] == $player['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Score J1</label>
                        <input type="number" name="score_player1" class="form-control" required min="0"
                            value="<?= isset($formData['score_player1']) && isset($proposeAsNormal) && $formAction == 'add' ? htmlspecialchars($formData['score_player1']) : '' ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">VS</label>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Joueur 2</label>
                        <select name="player2_id" class="form-select" required>
                            <option value="">Sélectionner un joueur</option>
                            <?php foreach($players as $player): ?>
                                <option value="<?= $player['id'] ?>" <?= isset($formData['player2_id']) && isset($proposeAsNormal) && $formAction == 'add' && $formData['player2_id'] == $player['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Score J2</label>
                        <input type="number" name="score_player2" class="form-control" required min="0"
                            value="<?= isset($formData['score_player2']) && isset($proposeAsNormal) && $formAction == 'add' ? htmlspecialchars($formData['score_player2']) : '' ?>">
                    </div>

                    <div class="col-12">
                        <button type="submit" name="add" class="btn btn-primary">Enregistrer le match</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Liste des matches -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Liste des matches</h2>
                <div>
                    <form method="get" class="d-inline-flex align-items-center">
                        <label class="me-2">Matches par page:</label>
                        <select name="per_page" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                            <?php foreach([5, 10, 20, 50] as $per_page): ?>
                                <option value="<?= $per_page ?>" <?= $matches_per_page == $per_page ? 'selected' : '' ?>>
                                    <?= $per_page ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="page" value="1">
                    </form>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Joueur 1</th>
                                <th>Score</th>
                                <th>Joueur 2</th>
                                <th>Points du match</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matches as $match): 
                                $player1IsWinner = $match['score_player1'] > $match['score_player2'];
                                $player2IsWinner = $match['score_player2'] > $match['score_player1'];
                            ?>
                            <tr>
                                <td>
                                    <?= date('d/m/Y H:i', strtotime($match['match_date'])) ?>
                                    <?php if ($match['is_championship']): ?>
                                        <span class="badge bg-primary ms-1" title="Match de championnat">
                                            Championnat <?= date('Y', strtotime($match['match_date'])) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $player1IsWinner ? '<strong>' : '' ?>
                                    <?= htmlspecialchars($match['p1_first_name'] . ' ' . $match['p1_last_name']) ?>
                                    <?= $player1IsWinner ? '</strong>' : '' ?>
                                </td>
                                <td><?= $match['score_player1'] ?> - <?= $match['score_player2'] ?></td>
                                <td><?= $player2IsWinner ? '<strong>' : '' ?>
                                    <?= htmlspecialchars($match['p2_first_name'] . ' ' . $match['p2_last_name']) ?>
                                    <?= $player2IsWinner ? '</strong>' : '' ?>
                                </td>
                                <td><?= $match['points'] ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editMatch(<?= htmlspecialchars(json_encode($match)) ?>)">
                                        Modifier
                                    </button>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="id" value="<?= $match['id'] ?>">
                                        <button type="submit" name="delete" class="btn btn-sm btn-danger" 
                                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce match ?')">
                                            Supprimer
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Navigation des pages" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $current_page - 1 ?>&per_page=<?= $matches_per_page ?>" aria-label="Précédent">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php foreach(generatePaginationLinks($current_page, $total_pages) as $page): ?>
                            <?php if($page === '...'): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php else: ?>
                                <li class="page-item <?= ($page == $current_page) ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page ?>&per_page=<?= $matches_per_page ?>">
                                        <?= $page ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?page=<?= $current_page + 1 ?>&per_page=<?= $matches_per_page ?>" aria-label="Suivant">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal de modification -->
        <div class="modal fade" id="editModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Modifier un match</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" id="editForm" class="row g-3">
                            <input type="hidden" name="id" id="edit_id">
                            
                            <div class="col-md-6">
                                <label class="form-label">Date et heure</label>
                                <input type="text" name="match_date" class="form-control datetimePicker" id="edit_match_date" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Points du match</label>
                                <select name="points" class="form-select" id="edit_points" required>
                                    <?php for($i=1; $i<=25; $i+=2): ?>
                                        <option value="<?= $i ?>"><?= $i ?> points</option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="form-check mt-2">
                                    <input type="checkbox" name="is_championship" class="form-check-input" id="edit_is_championship">
                                    <label class="form-check-label" for="edit_is_championship">Match de championnat</label>
                                </div>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label">Joueur 1</label>
                                <select name="player1_id" class="form-select" id="edit_player1_id" required>
                                    <?php foreach($players as $player): ?>
                                        <option value="<?= $player['id'] ?>">
                                            <?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Score J1</label>
                                <input type="number" name="score_player1" class="form-control" id="edit_score_player1" required min="0">
                            </div>

                            <div class="col-md-5">
                                <label class="form-label">Joueur 2</label>
                                <select name="player2_id" class="form-select" id="edit_player2_id" required>
                                    <?php foreach($players as $player): ?>
                                        <option value="<?= $player['id'] ?>">
                                            <?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Score J2</label>
                                <input type="number" name="score_player2" class="form-control" id="edit_score_player2" required min="0">
                            </div>

                            <div class="col-12">
                                <button type="submit" name="edit" class="btn btn-primary">Enregistrer les modifications</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/fr.js"></script>
    <script>
        // Initialisation de tous les sélecteurs de date
        flatpickr(".datetimePicker", {
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            time_24hr: true,
            locale: "fr",
            defaultDate: new Date()
        });

        function editMatch(match) {
            document.getElementById('edit_id').value = match.id;
            document.getElementById('edit_match_date').value = match.match_date;
            document.getElementById('edit_points').value = match.points;
            document.getElementById('edit_player1_id').value = match.player1_id;
            document.getElementById('edit_player2_id').value = match.player2_id;
            document.getElementById('edit_score_player1').value = match.score_player1;
            document.getElementById('edit_score_player2').value = match.score_player2;
            document.getElementById('edit_is_championship').checked = match.is_championship == 1;
            
            // Réinitialiser Flatpickr pour le champ de date dans le modal
            flatpickr("#edit_match_date", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                time_24hr: true,
                locale: "fr",
                defaultDate: match.match_date
            });

            new bootstrap.Modal(document.getElementById('editModal')).show();
        }
    </script>
</body>
</html>