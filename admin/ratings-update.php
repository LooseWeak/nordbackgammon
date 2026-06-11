<?php
//
// fichier admin/ratings-update.php
//

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/RatingCalculator.php';
requireRole('admin');

// S'assurer qu'aucune sortie n'a été faite avant
ob_clean();

if (isset($_POST['calculate'])) {
    try {
        // Configuration pour éviter les timeouts
        set_time_limit(0);
        ini_set('memory_limit', '256M');
        
        // S'assurer qu'aucune sortie n'est envoyée avant le JSON
        ob_start();
        
        // Définir le type de contenu comme JSON
        header('Content-Type: application/json');
        
        // 1. Initialisation
        $calculator = new RatingCalculator();
        
        // Structure pour stocker les données des joueurs en mémoire
        $players = [];
        
        // Récupérer tous les joueurs et initialiser leurs données
        $stmt = $pdo->query("SELECT id FROM players");
        while ($player = $stmt->fetch()) {
            $players[$player['id']] = [
                'rating' => 150000,
                'experience' => 0
            ];
        }
        
        // 2. Récupérer tous les matches dans l'ordre chronologique
        $matches = $pdo->query("
            SELECT * FROM matches 
            ORDER BY match_date ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $totalMatches = count($matches);
        $processedMatches = 0;
        
        // 3. Commencer une transaction pour toutes les mises à jour
        $pdo->beginTransaction();
        
        try {
            // Préparer la requête de mise à jour des matches
            $updateMatchStmt = $pdo->prepare("
                UPDATE matches 
                SET ranking_player1_before = :rank1,
                    ranking_player2_before = :rank2,
                    experience_player1_before = :exp1,
                    experience_player2_before = :exp2,
                    rating_change_player1 = :change1,
                    rating_change_player2 = :change2
                WHERE id = :match_id
            ");

            // Traiter chaque match
            foreach ($matches as $match) {
                // Récupérer les valeurs AVANT le calcul du nouveau rating
                $rankingBeforePlayer1 = $players[$match['player1_id']]['rating'];
                $rankingBeforePlayer2 = $players[$match['player2_id']]['rating'];
                $experienceBeforePlayer1 = $players[$match['player1_id']]['experience'];
                $experienceBeforePlayer2 = $players[$match['player2_id']]['experience'];

                $player1 = [
                    'id' => $match['player1_id'],
                    'rating' => $rankingBeforePlayer1 / 100,
                    'experience' => $experienceBeforePlayer1
                ];
                
                $player2 = [
                    'id' => $match['player2_id'],
                    'rating' => $rankingBeforePlayer2 / 100,
                    'experience' => $experienceBeforePlayer2
                ];
                
                // Calculer les nouveaux ratings
                $result = $calculator->calculateNewRatings(
                    [
                        'points' => $match['points'],
                        'score_player1' => $match['score_player1'],
                        'score_player2' => $match['score_player2']
                    ],
                    $player1,
                    $player2
                );

                // Calculer les variations de rating
                $ratingChangePlayer1 = $result['player1']['newRating'] * 100 - $rankingBeforePlayer1;
                $ratingChangePlayer2 = $result['player2']['newRating'] * 100 - $rankingBeforePlayer2;

                // Mettre à jour le match avec les valeurs before et les variations
                $updateMatchStmt->execute([
                    'rank1' => $rankingBeforePlayer1,
                    'rank2' => $rankingBeforePlayer2,
                    'exp1' => $experienceBeforePlayer1,
                    'exp2' => $experienceBeforePlayer2,
                    'change1' => $ratingChangePlayer1,
                    'change2' => $ratingChangePlayer2,
                    'match_id' => $match['id']
                ]);
                
                // Mettre à jour les données en mémoire pour le prochain match
                $players[$player1['id']]['rating'] = $result['player1']['newRating'] * 100;
                $players[$player1['id']]['experience'] = $result['player1']['newExperience'];
                $players[$player2['id']]['rating'] = $result['player2']['newRating'] * 100;
                $players[$player2['id']]['experience'] = $result['player2']['newExperience'];
                
                $processedMatches++;
            }

            // 4. Mise à jour finale des joueurs
            $updatePlayerStmt = $pdo->prepare("
                UPDATE players 
                SET ranking = :rating,
                    experience = :experience
                WHERE id = :id
            ");
            
            foreach ($players as $id => $data) {
                $updatePlayerStmt->execute([
                    'rating' => $data['rating'],
                    'experience' => $data['experience'],
                    'id' => $id
                ]);
            }
            
            // Valider toutes les modifications
            $pdo->commit();
            
            // Nettoyer toute sortie potentielle
            ob_clean();
            
            // Envoyer la réponse JSON
            echo json_encode([
                'success' => true,
                'message' => "Calcul terminé : $processedMatches matches traités",
                'processed' => $processedMatches,
                'total' => $totalMatches
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        // Nettoyer toute sortie potentielle
        ob_clean();
        
        // Envoyer l'erreur en JSON
        echo json_encode([
            'success' => false,
            'message' => "Erreur : " . $e->getMessage()
        ]);
    }
    
    exit;
}

$pageTitle = 'Recalcul ELO — Admin Nord Backgammon';
require_once 'includes/nav.php';
?>

<div class="container-fluid py-4 px-4">
    <div class="nb-page-header"><h1><i class="bi bi-arrow-repeat me-2"></i>Recalcul des ratings ELO</h1></div>

    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="alert alert-warning mb-4">
                <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Attention</h5>
                <p class="mb-2">Cette opération va :</p>
                <ul class="mb-2">
                    <li>Réinitialiser tous les ratings à <strong>1500</strong> et les expériences à <strong>0</strong></li>
                    <li>Rejouer tous les matchs dans l'ordre chronologique</li>
                    <li>Mettre à jour les colonnes <em>rating_before</em> et <em>rating_change</em> de chaque match</li>
                </ul>
                <p class="mb-0 small">Ne fermez pas cette fenêtre pendant le traitement.</p>
            </div>

            <div class="card">
                <div class="card-body text-center py-4">
                    <form method="post" id="ratingForm">
                        <input type="hidden" name="calculate" value="1">
                        <button type="submit" class="btn btn-primary btn-lg px-5" id="calculateBtn">
                            <i class="bi bi-play-fill me-2"></i>Lancer le calcul
                        </button>
                    </form>

                    <div id="progress" class="mt-4" style="display:none">
                        <div class="spinner-border mb-2" style="color:#E87128" role="status"></div>
                        <p class="text-muted">Calcul en cours, veuillez patienter…</p>
                    </div>

                    <div id="result" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extraScript = <<<'JS'
<script>
document.getElementById('ratingForm').onsubmit = function(e) {
    e.preventDefault();
    document.getElementById('calculateBtn').disabled = true;
    document.getElementById('progress').style.display = 'block';
    document.getElementById('result').innerHTML = '';
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'calculate=1'
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('progress').style.display = 'none';
        document.getElementById('result').innerHTML = data.success
            ? `<div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>${data.message} (${data.processed}/${data.total} matchs)</div>`
            : `<div class="alert alert-danger">${data.message}</div>`;
        document.getElementById('calculateBtn').disabled = false;
    })
    .catch(err => {
        document.getElementById('progress').style.display = 'none';
        document.getElementById('result').innerHTML = `<div class="alert alert-danger">Erreur : ${err}</div>`;
        document.getElementById('calculateBtn').disabled = false;
    });
};
</script>
JS;
require_once 'includes/admin_footer.php';
?>