# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Environment

- **Local stack**: Laragon (Windows 11) — Apache + PHP + MySQL
- **Local URL**: http://localhost (root: `C:\laragon\www\`)
- **Database**: MySQL, BDD `backnord` — user `root`, no password (Laragon defaults)
- **Recette**: http://recette.nordbackgammon.fr/ (auth HTTP basique: `backnord` / `backnord`)
- **GitHub**: https://github.com/LooseWeak/nordbackgammon (branche `master`)

## Architecture

Projet PHP procédural sans framework. Chaque page gère son propre cycle requête/réponse (lecture POST → traitement BDD → rendu HTML). Pas de routing, pas de MVC.

```
C:\laragon\www\
├── index.php                        # Page d'accueil / liste des liens
└── admin/
    ├── players.php                  # CRUD joueurs
    ├── matches.php                  # CRUD matchs (avec règles championnat)
    ├── ratings-update.php           # Recalcul complet de tous les ratings (AJAX)
    ├── ranking.php                  # Classement ELO + historique matchs
    ├── championship.php             # Classement championnat de l'année
    ├── championship-matrix.php      # Tableau croisé matchs championnat
    ├── player-stats.php             # Stats individuelles (lié depuis ranking.php)
    ├── assets/css/style.css
    └── includes/
        ├── config.php               # Connexion PDO (à inclure en premier)
        └── RatingCalculator.php     # Classe de calcul ELO
```

## Base de données

Tables principales :
- `players` — `id, first_name, last_name, ranking, experience, creation_date`
  - `ranking` stocké ×100 (entier) pour éviter les flottants ; affiché ÷100
  - `experience` = somme des `points` de tous les matchs joués
- `matches` — `id, match_date, player1_id, player2_id, points, score_player1, score_player2, is_championship, ranking_player1_before, ranking_player2_before, experience_player1_before, experience_player2_before, rating_change_player1, rating_change_player2`
  - Les colonnes `*_before` et `rating_change_*` sont remplies par `ratings-update.php`, pas à la saisie du match

## Calcul ELO (RatingCalculator.php)

- Rating initial : **1500** (stocké 150000 en BDD)
- La valeur du match dépend de sa longueur : `4 * sqrt(points)`
- La probabilité de victoire tient compte de la longueur du match : `ratingDiff = (r1 - r2) * sqrt(points) / 2000`
- Multiplicateur d'expérience pour les nouveaux joueurs : de ×5 (0 exp) à ×1 (≥ 400 exp)
- Le recalcul (`ratings-update.php`) repart toujours de zéro et rejoue tous les matchs dans l'ordre chronologique — **recalcul total obligatoire après toute modification d'un match**

## Règles métier importantes

- Un match de championnat est forcément en **7 points**
- Maximum **2 matchs de championnat** par paire de joueurs par année civile
- Si la limite est atteinte, l'interface propose d'enregistrer le match comme hors championnat
- Un joueur ayant des matchs enregistrés **ne peut pas être supprimé**
- Les points d'un match doivent être un nombre **impair entre 1 et 25**

## Frontend

- Bootstrap 5.3.2 (CDN)
- Bootstrap Icons 1.11.1 (CDN, utilisé dans ranking.php)
- Flatpickr (CDN, sélecteur date/heure dans matches.php) — format `Y-m-d H:i`, locale FR
- Pas de build tool, pas de transpilation — JS vanilla inline dans chaque page

## Versionnement

Toute modification du code doit être committée et pushée sur GitHub :

- Repo : https://github.com/LooseWeak/nordbackgammon (branche `master`)
- Chaque commit doit avoir un message clair décrivant **pourquoi** la modification a été faite (pas seulement ce qui a changé)
- Pousser systématiquement après chaque commit : `git push origin master`

## Débogage

Activer les logs de calcul ELO : passer `DEBUG_RATING` à `true` dans `RatingCalculator.php` (ligne 8). Les logs vont dans `admin/includes/ranking-error.log`.
