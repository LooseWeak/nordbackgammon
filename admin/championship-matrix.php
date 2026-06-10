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

// Récupérer tous les joueurs qui ont participé au championnat cette année
$playersQuery = $pdo->prepare("
    SELECT DISTINCT p.id, p.first_name, p.last_name
    FROM players p
    JOIN (
        SELECT player1_id as player_id FROM matches 
        WHERE is_championship = TRUE AND YEAR(match_date) = ?
        UNION 
        SELECT player2_id as player_id FROM matches 
        WHERE is_championship = TRUE AND YEAR(match_date) = ?
    ) as championship_players ON p.id = championship_players.player_id
    ORDER BY p.first_name
");
$playersQuery->execute([$year, $year]);
$players = $playersQuery->fetchAll();

// Créer un index des joueurs par ID pour faciliter les comparaisons
$playerIndex = [];
foreach ($players as $index => $player) {
    $playerIndex[$player['id']] = $index;
}

// Récupérer tous les matchs joués dans le championnat pour l'année sélectionnée
$matchesQuery = $pdo->prepare("
    SELECT 
        player1_id, player2_id, 
        score_player1, score_player2,
        match_date, id
    FROM matches
    WHERE is_championship = TRUE 
    AND YEAR(match_date) = ?
    ORDER BY match_date ASC
");
$matchesQuery->execute([$year]);
$matches = $matchesQuery->fetchAll(PDO::FETCH_ASSOC);

// Créer un tableau pour stocker tous les matchs entre paires de joueurs, triés par date
$pairMatches = [];

foreach ($matches as $match) {
    $player1 = $match['player1_id'];
    $player2 = $match['player2_id'];
    
    // Créer une clé unique pour chaque paire de joueurs
    $pairKey = min($player1, $player2) . '_' . max($player1, $player2);
    
    if (!isset($pairMatches[$pairKey])) {
        $pairMatches[$pairKey] = [];
    }
    
    // Ajouter le match à la liste des matchs pour cette paire
    $pairMatches[$pairKey][] = $match;
}

// Initialiser la matrice d'affichage vide
$displayMatrix = [];
foreach ($players as $player) {
    $displayMatrix[$player['id']] = array_fill_keys(array_column($players, 'id'), null);
}

// Remplir la matrice d'affichage
foreach ($pairMatches as $pairKey => $pairMatchList) {
    // Trier les matchs par date
    usort($pairMatchList, function($a, $b) {
        return strtotime($a['match_date']) - strtotime($b['match_date']);
    });
    
    // Traiter chaque match pour cette paire (jusqu'à 2 matchs)
    foreach ($pairMatchList as $index => $match) {
        $player1Id = $match['player1_id'];
        $player2Id = $match['player2_id'];
        
        // Déterminer l'ordre des joueurs (selon l'ordre d'apparition dans la liste des joueurs)
        $firstPlayerId = isset($playerIndex[$player1Id]) && isset($playerIndex[$player2Id]) && 
                         $playerIndex[$player1Id] < $playerIndex[$player2Id] ? $player1Id : $player2Id;
        $secondPlayerId = $firstPlayerId == $player1Id ? $player2Id : $player1Id;
        
        // Déterminer le score à afficher (toujours du point de vue du joueur de la ligne)
        $firstPlayerScore = $firstPlayerId == $player1Id ? $match['score_player1'] : $match['score_player2'];
        $secondPlayerScore = $firstPlayerId == $player1Id ? $match['score_player2'] : $match['score_player1'];
        
        // Premier match: afficher dans le triangle inférieur (ligne du second joueur, colonne du premier joueur)
        if ($index == 0) {
            $displayMatrix[$secondPlayerId][$firstPlayerId] = $secondPlayerScore . '-' . $firstPlayerScore;
        } 
        // Second match: afficher dans le triangle supérieur (ligne du premier joueur, colonne du second joueur)
        else if ($index == 1) {
            $displayMatrix[$firstPlayerId][$secondPlayerId] = $firstPlayerScore . '-' . $secondPlayerScore;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau des Matchs - Championnat BackNord <?= $year ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .match-table th, .match-table td {
            text-align: center;
            vertical-align: middle;
            min-width: 60px;
            padding: 6px;
        }
        .match-table th {
            height: 140px;
            white-space: nowrap;
            position: relative;
            vertical-align: bottom;
        }
        .match-table th .player-name {
            transform: rotate(-45deg);
            position: absolute;
            bottom: 15px;
            left: 15px;
            transform-origin: left bottom;
            width: 120px;
            text-align: left;
        }
        .match-table td.player-cell {
            font-weight: bold;
            text-align: left;
            padding-left: 15px;
        }
        .match-table td.match-played {
            background-color: #f8f9fa;
        }
        .match-table td.no-match {
            background-color: #ffffff;
        }
        .match-table td.diagonal-blocked {
            background-color: #212529;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Tableau des Matchs - Championnat <?= $year ?></h1>
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

        <?php if (empty($players)): ?>
            <div class="alert alert-info">
                Aucun match de championnat enregistré pour l'année <?= $year ?>.
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered match-table">
                            <thead>
                                <tr>
                                    <th></th>
                                    <?php foreach ($players as $player): ?>
                                        <th>
                                            <div class="player-name"><?= htmlspecialchars($player['first_name']) ?> <?= substr(htmlspecialchars($player['last_name']),0,1) ?></div>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($players as $rowPlayer): ?>
                                    <tr>
                                        <td class="player-cell"><?= htmlspecialchars($rowPlayer['first_name']) ?> <?= substr(htmlspecialchars($rowPlayer['last_name']),0,1) ?></td>
                                        <?php foreach ($players as $colPlayer): ?>
                                            <?php 
                                            $rowId = $rowPlayer['id'];
                                            $colId = $colPlayer['id'];
                                            
                                            if ($rowId == $colId): ?>
                                                <td class="diagonal-blocked"></td>
                                            <?php elseif (isset($displayMatrix[$rowId][$colId]) && $displayMatrix[$rowId][$colId] !== null): ?>
                                                <td class="match-played">
                                                    <?= $displayMatrix[$rowId][$colId] ?>
                                                </td>
                                            <?php else: ?>
                                                <td class="no-match"></td>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 small text-muted">
                        <p class="mb-0">
                            Tableau des matchs de championnat <?= $year ?><br>
                            Premier match entre deux joueurs : partie basse du tableau<br>
                            Deuxième match entre deux joueurs : partie haute du tableau<br>
                            Chaque score se lit de la façon suivante : "joueur sur la ligne"-"joueur sur la colonne"
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
