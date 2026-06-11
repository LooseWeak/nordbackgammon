<?php
//
// fichier admin/ranking.php
//

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireRole('admin');

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

// Pagination de l'historique des matchs
$matches_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if (!in_array($matches_per_page, [5, 10, 20, 50])) $matches_per_page = 10;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $matches_per_page;
$total_matches = $pdo->query("SELECT COUNT(*) FROM matches")->fetchColumn();
$total_pages = (int)ceil($total_matches / $matches_per_page);
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

$matchesStmt = $pdo->prepare("
    SELECT m.*,
           p1.first_name as p1_first_name, p1.last_name as p1_last_name,
           p2.first_name as p2_first_name, p2.last_name as p2_last_name
    FROM matches m
    JOIN players p1 ON m.player1_id = p1.id
    JOIN players p2 ON m.player2_id = p2.id
    ORDER BY m.match_date DESC
    LIMIT ?, ?
");
$matchesStmt->bindValue(1, $offset, PDO::PARAM_INT);
$matchesStmt->bindValue(2, $matches_per_page, PDO::PARAM_INT);
$matchesStmt->execute();
$matches = $matchesStmt->fetchAll();

$pageTitle = 'Classement ELO — Admin Nord Backgammon';
require_once 'includes/nav.php';
?>

<div class="container-fluid py-4 px-4">
    <div class="nb-page-header"><h1><i class="bi bi-trophy-fill me-2"></i>Classement ELO</h1></div>
        
    <div class="card mb-4">
        <div class="card-header">Classement ELO</div>
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3" style="width:50px">#</th>
                        <th>Joueur</th>
                        <th class="text-end">Matchs</th>
                        <th class="text-end">Expérience</th>
                        <th class="text-end">Rating</th>
                        <th class="text-center pe-3">Tendance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rank = 1;
                    foreach ($players as $player):
                        $isActive = $player['matches_played'] > 0;
                        $trend = abs($player['rating_trend']) >= ($significance_threshold * 100)
                            ? ($player['rating_trend'] > 0 ? 'up' : 'down') : 'stable';
                    ?>
                    <tr <?= $isActive ? '' : 'style="opacity:.4"' ?>>
                        <td class="ps-3 fw-bold" style="color:<?= $rank===1?'#FFD700':($rank===2?'#C0C0C0':($rank===3?'#CD7F32':'#888')) ?>">
                            <?= $rank++ ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?>
                            <a href="player-stats.php?id=<?= $player['id'] ?>" class="ms-1" style="color:#E87128" title="Statistiques">
                                <i class="bi bi-graph-up"></i>
                            </a>
                        </td>
                        <td class="text-end"><?= $player['matches_played'] ?? 0 ?></td>
                        <td class="text-end"><?= $player['experience'] ?></td>
                        <td class="text-end fw-bold" style="color:#E87128">
                            <?= number_format($player['ranking'] / 100, 0, ',', '') ?>
                        </td>
                        <td class="text-center pe-3">
                            <?php if ($isActive): ?>
                                <?php if ($trend === 'up'): ?>
                                    <i class="bi bi-arrow-up-circle-fill text-success" title="En progression"></i>
                                <?php elseif ($trend === 'down'): ?>
                                    <i class="bi bi-arrow-down-circle-fill text-danger" title="En baisse"></i>
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
        <div class="card-header" style="font-size:.8rem;font-weight:400">
            <i class="bi bi-arrow-up-circle-fill text-success"></i> Progression &nbsp;
            <i class="bi bi-arrow-down-circle-fill text-danger"></i> Baisse &nbsp;
            <i class="bi bi-dash-circle text-muted"></i> Stable &nbsp;—&nbsp; sur les 10 derniers matchs · seuil <?= $significance_threshold ?> pts
        </div>
    </div>

    <!-- Historique des matchs -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Historique des matchs</span>
            <form method="get" class="d-inline-flex align-items-center gap-2">
                <label class="text-muted small mb-0">Par page :</label>
                <select name="per_page" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                    <?php foreach ([5, 10, 20, 50] as $pp): ?>
                    <option value="<?= $pp ?>" <?= $matches_per_page == $pp ? 'selected' : '' ?>><?= $pp ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="page" value="1">
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <tbody>
                    <?php foreach ($matches as $match): ?>
                    <tr>
                        <td class="px-3 py-2">
                            <div class="d-flex justify-content-between text-muted small mb-1">
                                <span><?= date('d/m/Y H:i', strtotime($match['match_date'])) ?></span>
                                <span><?= $match['points'] ?> pts<?= $match['is_championship'] ? ' · <span style="color:#E87128">Champ.</span>' : '' ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <?= htmlspecialchars($match['p1_first_name'] . ' ' . $match['p1_last_name']) ?>
                                    <small class="text-muted">(<?= number_format($match['ranking_player1_before'] / 100, 0, ',', '') ?>)
                                    <span class="<?= $match['rating_change_player1'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $match['rating_change_player1'] >= 0 ? '+' : '' ?><?= number_format($match['rating_change_player1'] / 100, 1, ',', '') ?>
                                    </span></small>
                                    <br>
                                    <?= htmlspecialchars($match['p2_first_name'] . ' ' . $match['p2_last_name']) ?>
                                    <small class="text-muted">(<?= number_format($match['ranking_player2_before'] / 100, 0, ',', '') ?>)
                                    <span class="<?= $match['rating_change_player2'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= $match['rating_change_player2'] >= 0 ? '+' : '' ?><?= number_format($match['rating_change_player2'] / 100, 1, ',', '') ?>
                                    </span></small>
                                </div>
                                <div class="fw-bold fs-5 text-nowrap ms-3"><?= $match['score_player1'] ?> – <?= $match['score_player2'] ?></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_pages > 1): ?>
        <div class="card-header">
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $current_page-1 ?>&per_page=<?= $matches_per_page ?>">&laquo;</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == 1 || $i == $total_pages || abs($i - $current_page) <= 2): ?>
                    <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&per_page=<?= $matches_per_page ?>"><?= $i ?></a>
                    </li>
                    <?php elseif (abs($i - $current_page) == 3): ?>
                    <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php endif; ?>
                <?php endfor; ?>
                <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $current_page+1 ?>&per_page=<?= $matches_per_page ?>">&raquo;</a>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
