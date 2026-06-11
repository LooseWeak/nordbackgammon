<?php
$pageTitle = 'Classement ELO — Nord Backgammon';
require_once 'admin/includes/config.php';
require_once 'includes/header.php';

$players = $pdo->query("
    SELECT
        p.*,
        player_stats.matches_played,
        COALESCE(recent_trends.rating_trend, 0) as rating_trend
    FROM players p
    LEFT JOIN (
        SELECT player_id, COUNT(*) as matches_played
        FROM (
            SELECT player1_id as player_id FROM matches
            UNION ALL
            SELECT player2_id FROM matches
        ) all_matches
        GROUP BY player_id
    ) player_stats ON p.id = player_stats.player_id
    LEFT JOIN (
        SELECT player_id, SUM(rating_change) as rating_trend
        FROM (
            SELECT *, (@rn := IF(@prev = player_id, @rn + 1, IF(@prev := player_id, 1, 1))) AS rn
            FROM (
                SELECT player1_id as player_id, rating_change_player1 as rating_change, match_date FROM matches
                UNION ALL
                SELECT player2_id, rating_change_player2, match_date FROM matches
                ORDER BY player_id, match_date DESC
            ) all_participations
            CROSS JOIN (SELECT @prev := NULL, @rn := 0) vars
        ) numbered_matches
        WHERE rn <= 10
        GROUP BY player_id
    ) recent_trends ON p.id = recent_trends.player_id
    ORDER BY p.ranking DESC
")->fetchAll();

$threshold = 10;
?>

<section class="nb-section">
    <div class="container">
        <div class="text-center mb-5">
            <h1 class="section-title">Classement <span>ELO</span></h1>
            <div class="section-divider"></div>
            <p class="text-muted">Mis à jour après chaque recalcul. Tendance sur les 10 derniers matchs.</p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="nb-card">
                    <div class="card-body p-0">
                        <table class="table ranking-table mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">#</th>
                                    <th>Joueur</th>
                                    <th class="text-end">Matchs</th>
                                    <th class="text-end">Expérience</th>
                                    <th class="text-end">Rating</th>
                                    <th class="text-center pe-4">Tendance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $rank = 1;
                                foreach ($players as $player):
                                    $active = $player['matches_played'] > 0;
                                    $trend = abs($player['rating_trend']) >= ($threshold * 100)
                                        ? ($player['rating_trend'] > 0 ? 'up' : 'down')
                                        : 'stable';
                                ?>
                                <tr <?= $active ? '' : 'style="opacity:.4"' ?>>
                                    <td class="ps-4 fw-bold" style="color:<?= $rank === 1 ? '#FFD700' : ($rank === 2 ? '#C0C0C0' : ($rank === 3 ? '#CD7F32' : 'var(--nb-muted)')) ?>">
                                        <?= $rank++ ?>
                                    </td>
                                    <td><?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?></td>
                                    <td class="text-end"><?= $player['matches_played'] ?? 0 ?></td>
                                    <td class="text-end"><?= $player['experience'] ?></td>
                                    <td class="text-end fw-bold" style="color:var(--nb-orange)">
                                        <?= number_format($player['ranking'] / 100, 0, ',', '') ?>
                                    </td>
                                    <td class="text-center pe-4">
                                        <?php if ($active): ?>
                                            <?php if ($trend === 'up'): ?>
                                                <i class="bi bi-arrow-up-circle-fill trend-up" title="En progression"></i>
                                            <?php elseif ($trend === 'down'): ?>
                                                <i class="bi bi-arrow-down-circle-fill trend-down" title="En baisse"></i>
                                            <?php else: ?>
                                                <i class="bi bi-dash-circle text-muted" title="Stable"></i>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <p class="text-muted small mt-3 text-center">
                    <i class="bi bi-arrow-up-circle-fill trend-up"></i> Progression
                    <i class="bi bi-arrow-down-circle-fill trend-down ms-3"></i> Baisse
                    <i class="bi bi-dash-circle ms-3 text-muted"></i> Stable
                    <span class="ms-3">(sur les 10 derniers matchs)</span>
                </p>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
