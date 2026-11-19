# Roadmap

Cette roadmap est indicative et reste soumise à la validation du cahier des charges.

## Phase 1 — Environnement et cadrage

- [x] Vérifier versions de PHP, Composer, Node.js et npm ;
- [x] Confirmer que le dépôt GitHub public `Mobley8888/ARTF-GESTION-PATRIMOINE` existe sur `main` ;
- [ ] vérifier `gh auth status` et la configuration Git locale ;
- [ ] initialiser le dépôt local `main` et synchroniser sans écraser le commit distant existant ;
- [ ] examiner et approuver le cahier des charges fonctionnel ;
- [ ] définir les exigences de confidentialité et de données de test.

## Phase 2 — Socle applicatif

- [x] Choisir Laravel 13 compatible avec PHP 8.3 ;
- [x] créer le squelette Laravel, la configuration SQLite locale et les migrations initiales ;
- [x] ajouter une interface Blade/Bootstrap 5 responsive et des routes administratives initiales ;
- [x] exécuter et valider build front-end et tests automatisés ;
- [x] définir le modèle conceptuel et livrer les migrations Eloquent des référentiels, biens et historique de localisation ;
- [ ] établir les conventions de code, tests, revue et intégration continue ;
- [ ] valider les hypothèses métier et les règles comptables avec les parties prenantes.

## Phase 3 — Modules fonctionnels

- [x] Référentiels initiaux et socle des biens patrimoniaux ;
- [ ] acquisitions/fournisseurs et pièces justificatives ;
- [ ] amortissements et valeurs nettes comptables ;
- [ ] inventaires physiques, affectations et mouvements ;
- [ ] maintenance et patrimoine immobilier ;
- [ ] gestion documentaire, rôles, permissions et audit ;
- [ ] tableaux de bord et rapports PDF/Excel.

## Phase 4 — Validation et exploitation

- [ ] Tests fonctionnels, sécurité et acceptation ;
- [ ] procédures documentées de sauvegarde/restauration ;
- [ ] préparation du déploiement et formation ;
- [ ] validation des contrôles de confidentialité avant chaque publication.
