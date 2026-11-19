# État du projet

Dernière mise à jour : 2026-11-19

## Situation actuelle

- Squelette Laravel 13 installé (framework `13.43.0`), compatible avec PHP 8.3 ;
- interface d'administration Blade/Bootstrap 5 responsive avec tableau de bord et
  navigation vers les pages Biens, Inventaires, Affectations, Maintenance et Rapports ;
- modèle initial des catégories, lieux, directions, services, agents, biens et
  historique des localisations implémenté par migrations et relations Eloquent ;
- environnement local PHP `8.3.36`, Composer `2.11.2`, Node `22.23.4` et npm `10.9.9` ;
- base SQLite locale ; migrations initiales et patrimoniales exécutées sans migration
  destructive ; données de démonstration fictives chargées de façon idempotente ;
- MySQL `8.4.3` disponible et vérifié par un scénario d'intégration transactionnel ;
- dépôt Git existant sur `main`, documents initiaux conservés ; aucun push effectué ;
- cahier des charges détaillé non trouvé dans le dossier consulté.

L'authentification, les rôles et l'administration des comptes sont livrés. Le
module des biens permet la consultation, la recherche, la création, la modification,
l'archivage contrôlé et l'historique de localisation. Les catégories et les
localisations hiérarchiques sont administrables et alimentent les formulaires
des biens. Les fournisseurs, acquisitions, dotations, amortissements, inventaires,
maintenance, immobilier, documents et journal d'audit restent à développer.

## Installation et vérifications

- Laravel a été créé temporairement puis fusionné sans écraser `.git`, `.gitignore`
  ni les six documents Markdown d'origine ;
- `.env` local créé à partir de `.env.example`, clé applicative générée ; `.env`
  et SQLite sont exclus du versionnement ;
- Composer utilise l'extension ZIP présente dans PHP, activée uniquement dans une
  configuration temporaire pour cette installation (désactivée dans le `php.ini`
  Laragon) ;
- npm utilise `npm.cmd` sous PowerShell, car `npm.ps1` est bloqué par la stratégie
  locale d'exécution ;
- règles `.gitignore` d'origine conservées, avec ajout de l'exclusion du cache
  PHPUnit généré par les tests.

- `composer install` terminé ; `npm.cmd install` sans vulnérabilité signalée ;
- `npm.cmd run build` réussi (Vite) ;
- `php artisan migrate` appliqué sur SQLite ; `migrate:status` confirme les migrations ;
- `php artisan db:seed` exécuté deux fois sur SQLite : données de démonstration
  patrimoniales fictives chargées de façon idempotente ;
- tests SQLite : 53 tests, 47 réussis, 6 ignorés, 385 assertions ;
- test d'intégration des référentiels MySQL `8.4.3` : 1 test, 28 assertions,
  transaction annulée ;
- Laravel Pint a validé le style des nouveaux fichiers PHP ;
- dépôt `main` aligné avec `origin/main` (0 commit en avance/retard), `.env` et
  SQLite ignorés par Git ; aucun push effectué.

## Prochaines étapes

1. Valider les hiérarchies, codifications, états et règles monétaires avec l'ARTF ;
2. confirmer les exigences de données, rôles, permissions et audit ;
3. valider les migrations et procédures de sauvegarde sur l'environnement cible ;
4. construire acquisitions, affectations, inventaires, amortissements et autres domaines ;
5. vérifier les changements et l'absence de secrets avant toute publication.
