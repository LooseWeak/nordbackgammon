<?php
require_once 'includes/config.php';

// Récupérer l'année demandée ou utiliser l'année courante
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Récupérer les années disponibles pour le sélecteur
$years = $pdo->query("
    SELECT DISTINCT YEAR(match_date) as year 
    FROM matches 
    WHERE is_championship = TRUE 
    ORDER BY year DESC
")->fetchAll(PDO::FETCH_COLUMN);

// Récupérer le classement
$standings = $pdo->prepare("
    WITH championship_matches AS (
        SELECT 
            player1_id as player_id,
            CASE 
                WHEN score_player1 > score_player2 THEN 3
                ELSE 1
            END as match_points,
            CASE 
                WHEN score_player1 > score_player2 THEN 1
                ELSE 0
            END as victory
        FROM matches 
        WHERE is_championship = TRUE 
        AND YEAR(match_date) = ?
        
        UNION ALL
        
        SELECT 
            player2_id as player_id,
            CASE 
                WHEN score_player2 > score_player1 THEN 3
                ELSE 1
            END as match_points,
            CASE 
                WHEN score_player2 > score_player1 THEN 1
                ELSE 0
            END as victory
        FROM matches 
        WHERE is_championship = TRUE 
        AND YEAR(match_date) = ?
    )
    SELECT 
        p.id,
        p.first_name,
        p.last_name,
        p.ranking,           -- Ajout du rating
        COUNT(*) as matches_played,
        SUM(cm.victory) as victories,
        COUNT(*) - SUM(cm.victory) as defeats,
        SUM(cm.match_points) as total_points
    FROM players p
    JOIN championship_matches cm ON cm.player_id = p.id
    GROUP BY p.id, p.first_name, p.last_name, p.ranking
    ORDER BY total_points DESC, victories DESC, matches_played ASC
");

$standings->execute([$year, $year]);
$results = $standings->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Championnat BackNord <?= $year ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Championnat <?= $year ?></h1>
            <?php if (!empty($years)): ?>
                <form class="d-flex align-items-center">
                    <label class="me-2">Année :</label>
                    <select name="year" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                        <?php foreach($years as $y): ?>
                            <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>
        </div>

        <?php if (empty($results)): ?>
            <div class="alert alert-info">
                Aucun match de championnat enregistré pour l'année <?= $year ?>.
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th class="text-center">#</th>
                                    <th>Joueur</th>
                                    <th class="text-end">MJ</th>
                                    <th class="text-end">V</th>
                                    <th class="text-end">D</th>
                                    <th class="text-end">Points</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rank = 1;
                                $previous_points = null;
                                $display_rank = 1;
                                
                                foreach ($results as $player): 
                                    // Gestion des ex-aequo
                                    if ($previous_points !== null && $player['total_points'] < $previous_points) {
                                        $display_rank = $rank;
                                    }
                                    $previous_points = $player['total_points'];
                                ?>
                                <tr>
                                <td class="text-center"><?= $display_rank ?>.</td>
                                <td>
                                    <?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?>
                                    <span class="text-muted">
                                        (<?= number_format($player['ranking'] / 100, 0, ',', '') ?>)
                                    </span>
                                    <a href="player-stats.php?id=<?= $player['id'] ?>" 
                                    class="btn btn-sm btn-link p-0 ms-1" 
                                    title="Statistiques">
                                        <i class="bi bi-graph-up"></i>
                                    </a>
                                </td>
                                    <td class="text-end"><?= $player['matches_played'] ?></td>
                                    <td class="text-end text-success"><?= $player['victories'] ?></td>
                                    <td class="text-end text-danger"><?= $player['defeats'] ?></td>
                                    <td class="text-end"><strong><?= $player['total_points'] ?></strong></td>
                                </tr>
                                <?php 
                                $rank++;
                                endforeach; 
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 small text-muted">
                        <p class="mb-0">
                            MJ = Matches joués, V = Victoires, D = Défaites<br>
                            Points : 3 pour une victoire, 1 pour une défaite<br>
                            En cas d'égalité de points, les joueurs sont départagés dans l'ordre par :
                            <ol class="mb-0 mt-1">
                                <li>Le nombre de victoires</li>
                                <li>Le plus petit nombre de matches joués</li>
                            </ol>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <!-- Liste des matches du championnat -->
        <?php
        // Récupérer tous les matches de championnat de l'année sélectionnée
        $matches = $pdo->prepare("
            SELECT 
                m.*,
                p1.first_name as p1_first_name, p1.last_name as p1_last_name,
                p2.first_name as p2_first_name, p2.last_name as p2_last_name
            FROM matches m
            JOIN players p1 ON m.player1_id = p1.id
            JOIN players p2 ON m.player2_id = p2.id
            WHERE m.is_championship = TRUE 
            AND YEAR(m.match_date) = ?
            ORDER BY m.match_date DESC
        ");
        $matches->execute([$year]);
        $championship_matches = $matches->fetchAll();

        if (!empty($championship_matches)):
        ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h2 class="h5 mb-0">Matches du championnat <?= $year ?></h2>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Joueurs</th>
                                    <th class="text-end">Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($championship_matches as $match): 
                                    $player1IsWinner = $match['score_player1'] > $match['score_player2'];
                                    $player2IsWinner = $match['score_player2'] > $match['score_player1'];
                                ?>
                                <tr>
                                    <td class="text-nowrap">
                                        <?= date('d/m/Y H:i', strtotime($match['match_date'])) ?>
                                    </td>
                                    <td>
                                        <div>
                                            <?= $player1IsWinner ? '<strong>' : '' ?>
                                            <?= htmlspecialchars($match['p1_first_name'] . ' ' . $match['p1_last_name']) ?>
                                            <?= $player1IsWinner ? '</strong>' : '' ?>
                                             / 
                                            <?= $player2IsWinner ? '<strong>' : '' ?>
                                            <?= htmlspecialchars($match['p2_first_name'] . ' ' . $match['p2_last_name']) ?>
                                            <?= $player2IsWinner ? '</strong>' : '' ?>
                                        </div>
                                    </td>
                                    <td class="text-end align-middle">
                                        <?= $match['score_player1'] ?> - <?= $match['score_player2'] ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
