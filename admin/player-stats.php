<?php
//
// fichier admin/player-stats.php
//

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireRole('admin');

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
$pageTitle = 'Stats ' . htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) . ' — Admin';
$extraHead = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
require_once 'includes/nav.php';
?>

<div class="container-fluid py-4 px-4">
    <div class="nb-page-header">
        <a href="ranking.php" class="text-muted small d-inline-flex align-items-center gap-1 mb-2">
            <i class="bi bi-arrow-left"></i> Classement
        </a>
        <h1><i class="bi bi-graph-up me-2"></i>Statistiques — <?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?></h1>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card p-3 text-center">
                <div class="text-muted small mb-1">Matchs joués</div>
                <div class="fw-bold" style="font-size:2.5rem;color:#E87128"><?= $global_stats['total_matches'] ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 text-center">
                <div class="text-muted small mb-1">% de victoires</div>
                <div class="fw-bold" style="font-size:2.5rem;color:#E87128">
                    <?= $global_stats['total_matches'] > 0 ? round(($global_stats['wins'] / $global_stats['total_matches']) * 100, 1) : 0 ?>%
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 text-center">
                <div class="text-muted small mb-1">Variation moyenne du rating</div>
                <div class="fw-bold <?= ($global_stats['avg_rating_change'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>" style="font-size:2.5rem">
                    <?= (($global_stats['avg_rating_change'] ?? 0) >= 0 ? '+' : '') . number_format(($global_stats['avg_rating_change'] ?? 0) / 100, 2, ',', ' ') ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header">Stats par durée de match</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th class="ps-3">Points</th><th class="text-end">MJ</th><th class="text-end">V</th><th class="text-end pe-3">%</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($length_stats as $stat): ?>
                            <tr>
                                <td class="ps-3"><?= $stat['points'] ?> pts</td>
                                <td class="text-end"><?= $stat['matches_count'] ?></td>
                                <td class="text-end text-success"><?= $stat['wins'] ?></td>
                                <td class="text-end pe-3"><?= round(($stat['wins'] / $stat['matches_count']) * 100, 1) ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (!empty($frequent_opponents)): ?>
            <div class="card">
                <div class="card-header">Adversaires fréquents <small class="text-muted">(≥3 matchs)</small></div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th class="ps-3">Adversaire</th><th class="text-end">MJ</th><th class="text-end pe-3">%V</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($frequent_opponents as $opp):
                                $pct = round(($opp['wins'] / $opp['matches_count']) * 100, 1);
                            ?>
                            <tr>
                                <td class="ps-3">
                                    <?= htmlspecialchars($opp['last_name'] . ' ' . $opp['first_name']) ?>
                                    <a href="player-stats.php?id=<?= $opp['opponent_id'] ?>" class="ms-1" style="color:#E87128" title="Stats">
                                        <i class="bi bi-graph-up"></i>
                                    </a>
                                </td>
                                <td class="text-end"><?= $opp['matches_count'] ?></td>
                                <td class="text-end pe-3 <?= $pct > 50 ? 'text-success' : ($pct < 50 ? 'text-danger' : '') ?>"><?= $pct ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">Évolution du rating</div>
                <div class="card-body">
                    <canvas id="ratingChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$labels   = json_encode(array_map(fn($d) => date('d/m/Y', strtotime($d['date'])), $rating_data));
$data_pts = json_encode(array_map(fn($d) => $d['rating'], $rating_data));
$extraScript = <<<JS
<script>
new Chart(document.getElementById('ratingChart'), {
    type: 'line',
    data: {
        labels: $labels,
        datasets: [{
            label: 'Rating',
            data: $data_pts,
            borderColor: '#E87128',
            backgroundColor: 'rgba(232,113,40,.1)',
            tension: 0.3,
            fill: true,
            pointRadius: 3
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { labels: { color: '#f0f0f0' } } },
        scales: {
            x: { ticks: { color: '#888' }, grid: { color: '#333' } },
            y: { ticks: { color: '#888' }, grid: { color: '#333' }, beginAtZero: false }
        }
    }
});
</script>
JS;
require_once 'includes/admin_footer.php';
?>