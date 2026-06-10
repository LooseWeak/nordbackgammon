<?php
//
// fichier admin/player-stats.php
//

require_once 'includes/config.php';

// Récupérer l'ID du joueur depuis l'URL
$player_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Récupérer les informations du joueur
$stmt = $pdo->prepare("
    SELECT * FROM players WHERE id = ?
");
$stmt->execute([$player_id]);
$player = $stmt->fetch();

if (!$player) {
    die("Joueur non trouvé");
}

// Statistiques globales
$stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_matches,
        SUM(CASE 
            WHEN (player1_id = ? AND score_player1 > score_player2) OR 
                 (player2_id = ? AND score_player2 > score_player1) 
            THEN 1 ELSE 0 
        END) as wins,
        AVG(CASE WHEN player1_id = ? THEN rating_change_player1 
                 ELSE rating_change_player2 END) as avg_rating_change
    FROM matches 
    WHERE player1_id = ? OR player2_id = ?
");
$stats->execute([$player_id, $player_id, $player_id, $player_id, $player_id]);
$global_stats = $stats->fetch();

// Statistiques par durée de match
$stats_by_length = $pdo->prepare("
    SELECT 
        points,
        COUNT(*) as matches_count,
        SUM(CASE 
            WHEN (player1_id = ? AND score_player1 > score_player2) OR 
                 (player2_id = ? AND score_player2 > score_player1) 
            THEN 1 ELSE 0 
        END) as wins
    FROM matches 
    WHERE player1_id = ? OR player2_id = ?
    GROUP BY points
    ORDER BY points
");
$stats_by_length->execute([$player_id, $player_id, $player_id, $player_id]);
$length_stats = $stats_by_length->fetchAll();

// Évolution du rating (derniers matches)
$rating_evolution = $pdo->prepare("
    SELECT 
        match_date,
        CASE 
            WHEN player1_id = ? THEN ranking_player1_before
            ELSE ranking_player2_before
        END as rating_before,
        CASE 
            WHEN player1_id = ? THEN rating_change_player1
            ELSE rating_change_player2
        END as rating_change
    FROM matches 
    WHERE player1_id = ? OR player2_id = ?
    ORDER BY match_date ASC
");
$rating_evolution->execute([$player_id, $player_id, $player_id, $player_id]);
$ratings = $rating_evolution->fetchAll();

// Calculer l'évolution cumulative du rating
$rating_data = [];
$current_rating = 150000; // 1500.00
foreach ($ratings as $match) {
    $rating_data[] = [
        'date' => $match['match_date'],
        'rating' => $current_rating / 100,
        'change' => $match['rating_change'] / 100
    ];
    $current_rating += $match['rating_change'];
}
// Ajouter le rating final après le dernier match
if (!empty($ratings)) {
    $last_match = end($ratings);
    $rating_data[] = [
        'date' => $last_match['match_date'],
        'rating' => $current_rating / 100,
        'change' => 0
    ];
}

// Statistiques des adversaires les plus fréquents
$opponents = $pdo->prepare("
    SELECT 
        CASE 
            WHEN player1_id = ? THEN player2_id
            ELSE player1_id
        END as opponent_id,
        p.first_name,
        p.last_name,
        COUNT(*) as matches_count,
        SUM(CASE 
            WHEN (player1_id = ? AND score_player1 > score_player2) OR 
                 (player2_id = ? AND score_player2 > score_player1) 
            THEN 1 
            ELSE 0 
        END) as wins
    FROM matches m
    JOIN players p ON p.id = CASE 
        WHEN player1_id = ? THEN player2_id
        ELSE player1_id
    END
    WHERE player1_id = ? OR player2_id = ?
    GROUP BY opponent_id, p.first_name, p.last_name
    HAVING matches_count >= 3
    ORDER BY matches_count DESC
    LIMIT 10
");
$opponents->execute([$player_id, $player_id, $player_id, $player_id, $player_id, $player_id]);
$frequent_opponents = $opponents->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques de <?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?> - BackNord</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container mt-4">
        <h1>Statistiques de <?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?></h1>

        <!-- Statistiques globales -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Matches joués</h5>
                        <p class="card-text display-4"><?= $global_stats['total_matches'] ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Pourcentage de victoires</h5>
                        <p class="card-text display-4">
                            <?= round(($global_stats['wins'] / $global_stats['total_matches']) * 100, 1) ?>%
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Variation moyenne du rating</h5>
                        <p class="card-text display-4 <?= $global_stats['avg_rating_change'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= ($global_stats['avg_rating_change'] >= 0 ? '+' : '') . 
                                number_format($global_stats['avg_rating_change'] / 100, 2, ',', ' ') ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques par durée de match -->
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">Statistiques par durée de match</h5>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Points</th>
                            <th>Matches joués</th>
                            <th>Victoires</th>
                            <th>Pourcentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($length_stats as $stat): ?>
                        <tr>
                            <td><?= $stat['points'] ?> points</td>
                            <td><?= $stat['matches_count'] ?></td>
                            <td><?= $stat['wins'] ?></td>
                            <td><?= round(($stat['wins'] / $stat['matches_count']) * 100, 1) ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Adversaires fréquents -->
        <?php if (!empty($frequent_opponents)): ?>
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">Adversaires les plus fréquents</h5>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Adversaire</th>
                                <th class="text-end">Matches</th>
                                <th class="text-end">Victoires</th>
                                <th class="text-end">Pourcentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($frequent_opponents as $opponent): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($opponent['last_name'] . ' ' . $opponent['first_name']) ?>
                                    <a href="player-stats.php?id=<?= $opponent['opponent_id'] ?>" class="btn btn-sm btn-link p-0 ms-1" title="Statistiques">
                                        <i class="bi bi-graph-up"></i>
                                    </a>
                                </td>
                                <td class="text-end"><?= $opponent['matches_count'] ?></td>
                                <td class="text-end"><?= $opponent['wins'] ?></td>
                                <td class="text-end">
                                    <span class="<?= ($opponent['wins']/$opponent['matches_count'] > 0.5) ? 'text-success' : 
                                                (($opponent['wins']/$opponent['matches_count'] < 0.5) ? 'text-danger' : '') ?>">
                                        <?= round(($opponent['wins'] / $opponent['matches_count']) * 100, 1) ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!-- Graphique d'évolution du rating -->
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">Évolution du rating</h5>
                <canvas id="ratingChart"></canvas>
            </div>
        </div>
    </div>

    <script>
        // Graphique d'évolution du rating
        const ctx = document.getElementById('ratingChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_map(function($d) { 
                    return date('d/m/Y', strtotime($d['date'])); 
                }, $rating_data)) ?>,
                datasets: [{
                    label: 'Rating',
                    data: <?= json_encode(array_map(function($d) { 
                        return $d['rating']; 
                    }, $rating_data)) ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Évolution du rating au fil des matches'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false
                    }
                }
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>