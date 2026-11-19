# Journal des modifications

Les changements notables du projet seront consignés ici.

## [Non publié] — Tableaux de bord dynamiques par rôle — 2026-11-19

### Ajouté

- Tableaux de bord calculés depuis la base pour `super_admin`, `admin`,
  `asset_manager` et `viewer`, avec les indicateurs autorisés à chaque profil ;
- filtres serveur par période, site, catégorie, sous-catégorie et statut, avec
  graphiques ouvrant la liste des biens correspondants ;
- agrégats mensuels des biens, affectations et mouvements pour le super
  administrateur, et listes récentes basées sur les historiques existants ;
- indicateurs de biens disponibles/affectés pour le gestionnaire, fins
  d'affectation traçables et recherche multicritère rapide pour tous les rôles ;
- filtre de liste des biens par site, y compris les biens localisés directement
  sous le site ou dans une hiérarchie bâtiment/local.

## [Non publié] — Corrections fonctionnelles des référentiels — 2026-11-19

### Modifié

- Autorisation des locaux directement rattachés à un site, tout en conservant
  les locaux sous bâtiment et la validation serveur des hiérarchies ;
- ajout des sélecteurs dépendants catégorie principale/sous-catégorie et de la
  validation du parent de sous-catégorie côté serveur ;
- blocage de la désactivation d’une localisation présente dans un historique
  de départ ou d’arrivée.

### Validé

- Tests de régression SQLite pour les deux formes de hiérarchie, les sous-catégories
  incompatibles, la modification des biens et les localisations historiques.

## [Non publié] — Administration des référentiels patrimoniaux — 2026-11-19

### Ajouté

- Interfaces de gestion hiérarchique des catégories/sous-catégories et des sites,
  bâtiments et locaux, avec recherche, consultation, modification et activation ;
- sélecteurs de localisation dépendants dans le formulaire des biens ;
- compatibilité des valeurs MySQL existantes `batiment` et `local` avec les
  anciennes valeurs applicatives `building` et `room`, sans migration ;
- protections serveur contre les hiérarchies invalides et la désactivation de
  référentiels utilisés ; aucune suppression définitive ni donnée de démonstration
  ajoutée à MySQL.

### Validé

- Tests SQLite : 53 tests, 47 réussis, 6 ignorés, 385 assertions ;
- intégration MySQL 8.4.3 : tests des comptes et des référentiels réussis dans des
  transactions annulées (28 assertions pour les référentiels) ;
- build Vite, Pint et contrôle `git diff --check` réussis.

## [Non publié] — Modèle de données patrimoniales — 2026-11-19

### Ajouté

- Migrations additives pour catégories, lieux, directions, services, agents, biens
  et historique immuable de localisation ;
- modèles Eloquent, énumérations de statut/état, relations et service transactionnel
  de déplacement avec traçabilité utilisateur ;
- factories, seeder idempotent avec données fictives et tests des hiérarchies,
  contraintes, relations, transactions et historique ;
- documentation conceptuelle, dictionnaire des données et diagramme relationnel.

### Validé

- Migrations SQLite appliquées ; seeder exécuté deux fois sans doublon ;
- 13 tests, 44 assertions réussis ; Laravel Pint sans erreur ;
- aucune migration destructive exécutée ; MySQL 8.4 non disponible pour test d'intégration.

## [Non publié] — Installation effective Laravel — 2026-11-19

### Ajouté

- Squelette Laravel 13, dépendances Composer et ressources Vite ;
- interface Blade/Bootstrap 5 responsive avec tableau de bord, sidebar, navbar et
  navigation vers les premières sections administratives ;
- configuration locale SQLite, clé Laravel et migrations initiales ;
- tests de navigation pour le tableau de bord et les pages administratives.
- build Vite réussi et suite Laravel validée (4 tests, 16 assertions).

### Modifié

- Documentation mise à jour pour refléter l'installation et les prérequis réels ;
- conservation des documents initiaux et des règles `.gitignore` existantes ;
- exclusion du fichier cache PHPUnit généré par les tests.
- `npm install` a signalé zéro vulnérabilité.

## [Non publié] — Audit de démarrage — 2026-11-19

### Vérifié

- Dossier local documentaire uniquement ; aucun squelette Laravel ni dépôt Git local ;
- dépôt GitHub public `Mobley8888/ARTF-GESTION-PATRIMOINE` présent sur `main`, avec
  un commit initial README ;
- aucun cahier des charges détaillé trouvé dans les fichiers du projet.

### Bloqué

- Audit des outils, initialisation/synchronisation Git, installation Laravel et tests :
  commandes locales indisponibles, l'accès réseau local du sandbox étant désactivé.

## [Non publié] — 2026-11-19

### Ajouté

- Documentation initiale du projet, de son statut, de son architecture cible et de
  sa roadmap ;
- règles `.gitignore` initiales pour Laravel et protection des fichiers
  d'environnement, journaux, bases locales, exports et sauvegardes.

### Notes

- Aucun code applicatif développé ;
- dépôt Git local et dépôt GitHub non initialisés dans cette session.
