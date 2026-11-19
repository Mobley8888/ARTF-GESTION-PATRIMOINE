# État du projet

Dernière mise à jour : 2026-11-19

## Situation actuelle

- Le dossier local contient uniquement README, .gitignore et documentation ;
- aucun code Laravel ni module fonctionnel ;
- aucun dossier `.git`, fichier `artisan`, `composer.json` ou `package.json` local ;
- dépôt GitHub public `Mobley8888/ARTF-GESTION-PATRIMOINE` visible sur la branche
  `main`, avec un seul commit initial contenant `README.md` ;
- le dépôt local n'est pas initialisé et n'est pas synchronisé avec le remote ;
- aucun cahier des charges détaillé disponible dans le dossier de travail consulté ;
- la page GitHub partagée affiche une session propriétaire `Mobley8888`.

## Blocages et prérequis

Les commandes PowerShell ne peuvent pas démarrer : le sandbox signale que l'accès
réseau local/proxy est désactivé. Les versions installées, extensions PHP, état Git,
configuration Git locale et `gh auth status` restent donc invérifiés. Aucun paquet
ne peut être installé ni aucun test/migration exécuté de façon fiable dans cette
session.

Avant de poursuivre le développement :

1. Rétablir l'exécution des commandes locales dans le sandbox ;
2. vérifier les versions de Git, `gh`, PHP, Composer, Node.js, npm, Laragon et VS Code,
   les extensions PHP, puis `gh auth status` ;
3. vérifier branche, état et remotes avant d'initialiser Git localement ou de
   synchroniser le commit distant existant ;
4. confirmer l'adresse Git locale sans demander ni exposer de secret ;
5. obtenir le cahier des charges officiel et confirmer les choix métier nécessaires ;
6. choisir une installation Laravel compatible avec PHP et ajouter une interface
   Bootstrap 5/Blade sans React, Vue, Inertia ni Tailwind ;
7. créer puis tester l'authentification, les migrations, les autorisations, les
   vues et les tests automatisés avant toute annonce de fonctionnement.
