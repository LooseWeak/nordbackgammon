<?php
//
// Fichier admin/classes/RatingCalculator.php
//


// Définir la constante de debug (mettre à true pour activer les logs)
define('DEBUG_RATING', false);

// Désactiver l'affichage des erreurs dans la sortie
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Configuration d'un log d'erreurs
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/ranking-error.log');

class RatingCalculator {
    /**
     * Calcule la probabilité de victoire basée sur les ratings et la longueur du match
     */
    private function calculateWinProbability($playerRating, $opponentRating, $matchLength) {
        $ratingDiff = ($playerRating - $opponentRating) * sqrt($matchLength) / 2000;
        return 1 - (1 / (pow(10, $ratingDiff) + 1));
    }

    /**
     * Calcule la valeur du match basée sur sa longueur
     */
    private function calculateMatchValue($matchLength) {
        return 4 * sqrt($matchLength);
    }

    /**
     * Calcule le multiplicateur d'expérience pour les nouveaux joueurs
     */
    private function calculateExperienceMultiplier($experienceAfterMatch) {
        if ($experienceAfterMatch >= 400) {
            return 1;
        }
        return 5 - ($experienceAfterMatch / 100);
    }

    /**
     * Calcule les nouveaux ratings pour les deux joueurs
     */
    public function calculateNewRatings($match, $player1, $player2) {
        if (DEBUG_RATING) {
            error_log("Calcul pour match: " . print_r($match, true));
            error_log("Player1: " . print_r($player1, true));
            error_log("Player2: " . print_r($player2, true));
        }
        $matchLength = $match['points'];
        $matchValue = $this->calculateMatchValue($matchLength);

        $p1WinProb = $this->calculateWinProbability($player1['rating'], $player2['rating'], $matchLength);
        
        $player1Won = $match['score_player1'] > $match['score_player2'];

        if ($player1Won) {
            $p1RatingChange = $matchValue * (1 - $p1WinProb);
            $p2RatingChange = -($matchValue * $p1WinProb);
        } else {
            $p1RatingChange = -($matchValue * $p1WinProb);
            $p2RatingChange = $matchValue * (1 - $p1WinProb);
        }

        $p1NewExperience = $player1['experience'] + $matchLength;
        $p2NewExperience = $player2['experience'] + $matchLength;

        $p1Multiplier = $this->calculateExperienceMultiplier($p1NewExperience);
        $p2Multiplier = $this->calculateExperienceMultiplier($p2NewExperience);

        $p1RatingChange *= $p1Multiplier;
        $p2RatingChange *= $p2Multiplier;

        $p1NewRating = round($player1['rating'] + $p1RatingChange,2);
        $p2NewRating = round($player2['rating'] + $p2RatingChange,2);

        $result = [
            'player1' => [
                'newRating' => $p1NewRating,
                'newExperience' => $p1NewExperience,
                'ratingChange' => $p1RatingChange
            ],
            'player2' => [
                'newRating' => $p2NewRating,
                'newExperience' => $p2NewExperience,
                'ratingChange' => $p2RatingChange
            ]
        ];
        if (DEBUG_RATING) {
        error_log("Résultat du calcul: " . print_r($result, true));
        }
        return $result;
    }
}
?>