<?php
//
// fichier admin/ranking.php
//

require_once 'includes/config.php';

// Récupérer les joueurs avec leurs statistiques
$players = $pdo->query("
    SELECT 
        p.*,
        player_stats.matches_played,
        COALESCE(recent_trends.rating_trend, 0) as rating_trend
    FROM 
        players p
    LEFT JOIN (
        SELECT 
            player_id,
            COUNT(*) as matches_played
        FROM (
            SELECT player1_id as player_id FROM matches
            UNION ALL
            SELECT player2_id FROM matches
        ) all_matches
        GROUP BY player_id
    ) player_stats ON p.id = player_stats.player_id
    LEFT JOIN (
        SELECT 
            player_id,
            SUM(rating_change) as rating_trend
        FROM (
            SELECT 
                *,
                (@rn := IF(@prev = player_id, @rn + 1,
                    IF(@prev := player_id, 1, 1))) AS rn
            FROM (
                SELECT 
                    player1_id as player_id,
                    rating_change_player1 as rating_change,
                    match_date
                FROM matches
                UNION ALL
                SELECT 
                    player2_id,
                    rating_change_player2,
                    match_date
                FROM matches
                ORDER BY player_id, match_date DESC
            ) all_participations
            CROSS JOIN (SELECT @prev := NULL, @rn := 0) vars
        ) numbered_matches
        WHERE rn <= 10
        GROUP BY player_id
    ) recent_trends ON p.id = recent_trends.player_id
    ORDER BY p.ranking DESC
")->fetchAll();

// Définir le seuil pour considérer une variation comme significative
$significance_threshold = 10;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classement - BackNord</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .trend-up {
            color: #198754;  /* Vert Bootstrap */
        }
        .trend-down {
            color: #dc3545;  /* Rouge Bootstrap */
        }
        .ranking-number {
            font-weight: bold;
            width: 40px;
            display: inline-block;
        }
        .inactive {
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1>Classement BackNord</h1>
        
        <div class="card mt-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Joueur</th>
                                <th class="text-end">Matchs</th>
                                <th class="text-end">Expérience</th>
                                <th class="text-end">Rating</th>
                                <th class="text-center">Tendance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            foreach ($players as $player): 
                                // Déterminer si le joueur est actif (a joué au moins un match)
                                $isActive = $player['matches_played'] > 0;
                                // Déterminer la tendance
                                $trend = abs($player['rating_trend']) >= ($significance_threshold * 100) 
                                    ? ($player['rating_trend'] > 0 ? 'up' : 'down') 
                                    : 'stable';
                            ?>
                            <tr class="<?= $isActive ? '' : 'inactive' ?>">
                                <td>
                                    <span class="ranking-number"><?= $rank++ ?></span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?>
                                    <a href="player-stats.php?id=<?= $player['id'] ?>" class="btn btn-sm btn-link p-0 ms-1" title="Statistiques">
                                        <i class="bi bi-graph-up"></i>
                                    </a>
                                </td>
                                <td class="text-end">
                                    <?= $player['matches_played'] ?>
                                </td>
                                <td class="text-end">
                                    <?= $player['experience'] ?>
                                </td>
                                <td class="text-end">
                                    <?= number_format($player['ranking'] / 100, 0, ',', '') ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($isActive): ?>
                                        <?php if ($trend === 'up'): ?>
                                            <i class="bi bi-arrow-up-circle-fill trend-up" title="En progression (<?= $player['rating_trend'] ?>)"></i>
                                        <?php elseif ($trend === 'down'): ?>
                                            <i class="bi bi-arrow-down-circle-fill trend-down" title="En baisse (<?= $player['rating_trend'] ?>)"></i>
                                        <?php else: ?>
                                            <i class="bi bi-dash-circle" title="Stable (<?= $player['rating_trend'] ?>)"></i>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 small text-muted">
                    <p class="mb-1">
                        <i class="bi bi-arrow-up-circle-fill trend-up"></i> En progression significative (>= <?= $significance_threshold ?> points)
                        <i class="bi bi-arrow-down-circle-fill trend-down ms-3"></i> En baisse significative (<= -<?= $significance_threshold ?> points)
                        <i class="bi bi-dash-circle ms-3"></i> Stable
                    </p>
                    <p class="mb-0">
                        Les tendances sont calculées sur les 10 derniers matches de chaque joueur.
                        Les joueurs n'ayant pas encore joué de match apparaissent en grisé.
                    </p>
                </div>
                <?php
// Configuration de la pagination des matches
$matches_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if (!in_array($matches_per_page, [5, 10, 20, 50])) {
    $matches_per_page = 10;
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

<!-- Liste des matches -->
<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0">Historique des matchs</h2>
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
            <table class="table">
                <tbody>
                    <?php foreach ($matches as $match): ?>
                    <tr>
                        <td>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <?= date('d/m/Y H:i', strtotime($match['match_date'])) ?>
                                </div>
                                <div class="text-end">
                                    <?= $match['points'] ?> points
                                </div>
                            </div>
                            <div class="mt-2">
                                <?= htmlspecialchars($match['p1_first_name'] . ' ' . $match['p1_last_name']) ?>
                                <small class="text-muted">
                                    (<?= number_format($match['ranking_player1_before'] / 100, 2, ',', '') ?>/<?= $match['experience_player1_before'] ?>) : 
                                    <span class="<?= $match['rating_change_player1'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $match['rating_change_player1'] >= 0 ? '+' : '' ?><?= number_format($match['rating_change_player1'] / 100, 2, ',', '') ?>
                                    </span>
                                </small>
                                <br>
                                <?= htmlspecialchars($match['p2_first_name'] . ' ' . $match['p2_last_name']) ?>
                                <small class="text-muted">
                                    (<?= number_format($match['ranking_player2_before'] / 100, 2, ',', '') ?>/<?= $match['experience_player2_before'] ?>) : 
                                    <span class="<?= $match['rating_change_player2'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $match['rating_change_player2'] >= 0 ? '+' : '' ?><?= number_format($match['rating_change_player2'] / 100, 2, ',', '') ?>
                                    </span>
                                </small>
                                <div class="float-end">
                                    <?= $match['score_player1'] ?>-<?= $match['score_player2'] ?>
                                </div>
                            </div>
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
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == 1 || $i == $total_pages || abs($i - $current_page) <= 2): ?>
                        <li class="page-item <?= ($i == $current_page) ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&per_page=<?= $matches_per_page ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php elseif (abs($i - $current_page) == 3): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                <?php endfor; ?>
                
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
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
