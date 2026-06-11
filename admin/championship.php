<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireRole('admin');

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

// Matches du championnat
$matchesStmt = $pdo->prepare("
    SELECT m.*,
           p1.first_name as p1_first_name, p1.last_name as p1_last_name,
           p2.first_name as p2_first_name, p2.last_name as p2_last_name
    FROM matches m
    JOIN players p1 ON m.player1_id = p1.id
    JOIN players p2 ON m.player2_id = p2.id
    WHERE m.is_championship = TRUE AND YEAR(m.match_date) = ?
    ORDER BY m.match_date DESC
");
$matchesStmt->execute([$year]);
$championship_matches = $matchesStmt->fetchAll();

$pageTitle = "Championnat $year — Admin Nord Backgammon";
require_once 'includes/nav.php';
?>

<div class="container-fluid py-4 px-4">
    <div class="nb-page-header d-flex justify-content-between align-items-center">
        <h1><i class="bi bi-award-fill me-2"></i>Championnat <?= $year ?></h1>
        <?php if (!empty($years)): ?>
        <form class="d-flex align-items-center gap-2">
            <label class="text-muted small mb-0">Année :</label>
            <select name="year" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <?php foreach ($years as $y): ?>
                <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
    </div>

    <div class="d-flex gap-2 mb-4">
        <a href="championship-matrix.php?year=<?= $year ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-grid-3x3-gap-fill"></i> Tableau des matchs
        </a>
    </div>

    <?php if (empty($results)): ?>
        <div class="alert alert-info">Aucun match de championnat enregistré pour <?= $year ?>.</div>
    <?php else: ?>
    <div class="card mb-4">
        <div class="card-header">Classement <?= $year ?></div>
        <div class="card-body p-0">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3 text-center" style="width:50px">#</th>
                        <th>Joueur</th>
                        <th class="text-end">MJ</th>
                        <th class="text-end">V</th>
                        <th class="text-end">D</th>
                        <th class="text-end pe-3">Points</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $rank = 1; $prev_points = null; $display_rank = 1;
                    foreach ($results as $player):
                        if ($prev_points !== null && $player['total_points'] < $prev_points) $display_rank = $rank;
                        $prev_points = $player['total_points'];
                    ?>
                    <tr>
                        <td class="text-center ps-3 fw-bold" style="color:<?= $display_rank === 1 ? '#FFD700' : ($display_rank === 2 ? '#C0C0C0' : ($display_rank === 3 ? '#CD7F32' : '#888')) ?>">
                            <?= $display_rank ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?>
                            <span class="text-muted small ms-1">(<?= number_format($player['ranking'] / 100, 0, ',', '') ?>)</span>
                            <a href="player-stats.php?id=<?= $player['id'] ?>" class="btn btn-sm btn-link p-0 ms-1" title="Statistiques">
                                <i class="bi bi-graph-up" style="color:#E87128"></i>
                            </a>
                        </td>
                        <td class="text-end"><?= $player['matches_played'] ?></td>
                        <td class="text-end text-success"><?= $player['victories'] ?></td>
                        <td class="text-end text-danger"><?= $player['defeats'] ?></td>
                        <td class="text-end pe-3 fw-bold" style="color:#E87128"><?= $player['total_points'] ?></td>
                    </tr>
                    <?php $rank++; endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-header" style="font-size:.8rem;font-weight:400">
            MJ = Matchs joués · V = Victoires · D = Défaites · Points : 3 victoire / 1 défaite · Ex-æquo : victoires, puis moins de matchs joués
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($championship_matches)): ?>
    <div class="card">
        <div class="card-header">Matchs du championnat <?= $year ?></div>
        <div class="card-body p-0">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">Date</th>
                        <th>Joueurs</th>
                        <th class="text-end pe-3">Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($championship_matches as $match):
                        $p1win = $match['score_player1'] > $match['score_player2'];
                        $p2win = $match['score_player2'] > $match['score_player1'];
                    ?>
                    <tr>
                        <td class="ps-3 text-muted small text-nowrap"><?= date('d/m/Y H:i', strtotime($match['match_date'])) ?></td>
                        <td>
                            <?= $p1win ? '<strong>' : '' ?><?= htmlspecialchars($match['p1_first_name'] . ' ' . $match['p1_last_name']) ?><?= $p1win ? '</strong>' : '' ?>
                            <span class="text-muted mx-1">vs</span>
                            <?= $p2win ? '<strong>' : '' ?><?= htmlspecialchars($match['p2_first_name'] . ' ' . $match['p2_last_name']) ?><?= $p2win ? '</strong>' : '' ?>
                        </td>
                        <td class="text-end pe-3 fw-bold"><?= $match['score_player1'] ?> – <?= $match['score_player2'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
