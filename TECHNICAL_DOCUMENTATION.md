# Documentation technique

## Périmètre

Le dépôt contient le socle Laravel, une interface administrative et un premier
modèle de données pour les référentiels, les biens et l'historique de localisation.
Les autres domaines sont documentés mais ne sont pas encore migrés.

## Pile cible

- Runtime : PHP `8.3.36`, Laravel `13.43.0` ;
- vues : Blade ;
- front-end : Bootstrap `5.x`, Bootstrap Icons, Vite, Node.js `22.23.4` et npm `10.9.9` ;
- dépendances PHP : Composer `2.11.2` ;
- base de tests : SQLite ; intégration MySQL `8.4.3` de Laragon vérifiée ;
- tests : PHPUnit via `php artisan test`.

React, Vue, Inertia et Tailwind ne sont pas employés. Sous PowerShell, `npm.cmd`
est nécessaire lorsque l'exécution de `npm.ps1` est bloquée. L'extension PHP ZIP
est présente mais désactivée dans la configuration Laragon ; une configuration
temporaire a été utilisée pour télécharger/extraire les dépendances Composer.

## Données et sécurité

- Garder les secrets et paramètres d'environnement dans `.env` local, jamais dans
  Git ; ne publier aucun `.env` réel.
- Utiliser des données fictives en développement et en démonstration.
- Ne publier aucun document administratif, justificatif, export ou sauvegarde de
  données institutionnelles.
- Définir les rôles, permissions, audit et stratégie de conservation des données
  avant la mise en œuvre.
- Définir et tester les procédures de sauvegarde/restauration avant leur usage en
  environnement réel.

## Architecture à préciser

Les tables `categories`, `locations`, `directions`, `services`, `agents`, `assets`,
`asset_location_histories` et `users` constituent le périmètre migré. Les domaines
achats, affectations, dotations, amortissements, inventaires, maintenance,
immobilier et documents sont décrits sans migration dans
[docs/modele-conceptuel.md](docs/modele-conceptuel.md). Le dictionnaire et le
schéma sont dans [docs/dictionnaire-donnees.md](docs/dictionnaire-donnees.md) et
[docs/schema-relations.md](docs/schema-relations.md).

Les transitions de statut, calculs comptables, cycles d'approbation et règles de
conservation ne sont pas codés avant validation métier.

## Développement local

- Les routes administratives et vues Blade sont dans `routes/web.php` et
  `resources/views/admin/` ; les ressources compilées sont définies dans
  `vite.config.js` ;
- SQLite est la configuration par défaut des tests ; la base
  `database/database.sqlite` est ignorée par Git. Une instance MySQL `8.4.3` a
  été vérifiée pour les tests d'intégration ;
- `.env` contient la clé et la configuration locales et ne doit jamais être commité ;
- exécuter `composer install`, `npm.cmd install`, `npm.cmd run build`,
  `php artisan migrate` et `php artisan test` depuis la racine ;
- `php artisan db:seed` charge des données explicitement fictives, de façon idempotente ;
- conserver les paramètres MySQL dans `.env` local et n'exécuter des scénarios
  d'intégration qu'avec des transactions réversibles.

Les migrations et tests automatisés passent sur SQLite. Un scénario d'intégration
des comptes a également été exécuté sur MySQL `8.4.3` dans une transaction annulée
à la fin du test ; aucun compte de test n'a été conservé.

## Référentiels patrimoniaux

- Interfaces : `/admin/referentiels/categories` et
  `/admin/referentiels/localisations`. Elles réutilisent les tables
  `categories`, `locations`, `assets` et `asset_location_histories`, sans
  nouvelle table ni migration.
- Les catégories supportent une racine et un niveau de sous-catégorie. Les lieux
  permettent un bâtiment sous un site et un local sous un site ou sous un bâtiment,
  conformément aux deux niveaux d’implantation utilisés par l’ARTF. Le formulaire
  de bien filtre les sous-catégories par catégorie principale et les localisations
  selon le site et, le cas échéant, le bâtiment sélectionné.
- Les données MySQL existantes utilisent `site`, `batiment` et `local`, tandis que
  les anciens tests applicatifs utilisaient `site`, `building` et `room`. Les deux
  représentations sont prises en charge sans migration; les nouveaux formulaires
  enregistrent les valeurs compatibles avec MySQL.
- Les listes d’actifs sont chargées depuis la base. Les neuf catégories proposées
  dans la demande ne sont pas préchargées ni insérées par un seeder.
- Les rôles capables de gérer les biens peuvent consulter, créer, modifier et
  activer/désactiver les référentiels. Les suppressions définitives ne sont pas
  exposées. La désactivation est refusée pour un élément référencé par un bien,
  un historique de localisation ou des descendants actifs; les clés étrangères
  restent intactes.
- Les tests SQLite couvrent les hiérarchies, validations, permissions,
  désactivation, formulaires dépendants et historique de localisation. Le test
  d’intégration MySQL `8.4.3` utilise une transaction annulée et n’ajoute aucune
  donnée permanente.

## Administration des comptes

- Interface : `/admin/utilisateurs` ; fiche détaillée : `/admin/utilisateurs/{id}`.
- Les capacités sont définies par `UserRole`: seuls `admin` et `super_admin`
  disposent de `manage-users`. Un `viewer` ne peut pas accéder aux routes
  d'administration, y compris par requête directe.
- Les administrateurs peuvent créer, rechercher, filtrer, consulter et modifier
  les comptes, gérer leur statut et réinitialiser leur mot de passe. Ils ne
  peuvent pas gérer un compte `super_admin`, attribuer ce rôle ou modifier leurs
  propres habilitations. Un compte administratif actif ne peut pas être retiré
  lorsqu'il est le dernier compte actif capable de gérer les utilisateurs.
- Pour provisionner le premier compte habilité, exécuter `php artisan
  app:provision-admin` dans le terminal de l'environnement voulu. La commande
  demande le nom et l'adresse e-mail, puis saisit le mot de passe sans l'afficher.
  Elle ne crée que le rôle `admin`, exige une confirmation, refuse les e-mails
  déjà pris et refuse de s'exécuter si un compte `admin` ou `super_admin` existe.
  Le compte est activé et devra changer son mot de passe à la première connexion.
  Ne pas lancer cette commande sur la base réelle sans validation explicite.
- Le test de provisionnement et les scénarios fonctionnels passent sur SQLite.
  Le parcours MySQL a été testé avec un administrateur temporaire créé dans une
  transaction automatiquement annulée ; la commande de provisionnement n'a pas
  été exécutée sur MySQL.

## Tableaux de bord par rôle

- La route d'accueil `/` charge `DashboardController` après authentification et
  choisit les indicateurs à partir du rôle de l'utilisateur connecté. Le rôle
  n'est jamais fourni par le navigateur pour sélectionner les données.
- Les biens archivés (soft deleted) sont exclus des statistiques opérationnelles.
  `En service` compte les biens actifs; les autres statuts enregistrés sont
  présentés comme biens non en service.
- Les filtres serveur portent sur la période d'enregistrement, le site,
  la catégorie principale, sa sous-catégorie et le statut. Une sélection de site
  inclut les biens localisés directement sous le site, sous un bâtiment ou dans
  un local. Le filtre par site est également pris en charge dans `/biens`.
- Tous les profils disposent d'une recherche rapide combinant référence,
  désignation ou numéro de série avec site, localisation, catégorie et statut.
  Le gestionnaire voit aussi les biens disponibles (en service sans affectation
  active) et affectés (avec une affectation non terminée).
- Les graphiques en barres utilisent des agrégats des tables existantes et leurs
  liens ouvrent les listes de biens avec le filtre correspondant. Les tendances
  mensuelles du super administrateur comptent les enregistrements, affectations
  et mouvements réellement stockés; elles utilisent les expressions de date
  SQLite pour les tests et MySQL en production.
- Le super administrateur seul reçoit les totaux d'utilisateurs, de catégories,
  sous-catégories et de niveaux de localisation ainsi que les tendances.
  L'administrateur voit les comptes de localisation actifs/désactivés et le lien
  de gestion des comptes conformément au Gate `manage-users`. Le gestionnaire
  voit les opérations patrimoniales et les affectations terminées; le viewer ne
  reçoit que des raccourcis de consultation. Le schéma distingue les affectations
  actives et terminées, mais ne stocke pas un type d'événement distinct pour
  différencier formellement restitution et fin liée à un transfert. Aucun journal
  d'activité fictif n'est affiché: les listes récentes proviennent des biens,
  affectations, mouvements et champs de suivi existants.
- Tests Feature SQLite : accès authentifié pour les quatre rôles, refus invité,
  isolation des données super administrateur, filtres, liens de graphiques,
  hiérarchies de sites et comptage mensuel des événements enregistrés.

Dernière vérification SQLite : 64 tests exécutés (58 réussis, 6 ignorés,
492 assertions), en excluant explicitement le test préexistant qui appelle un
seeder. Les tests ciblés dashboard, biens et référentiels passent (27 tests,
273 assertions). Aucune écriture de test n'a été exécutée sur MySQL pendant cette
vérification.

## Versionnement

Le projet doit être un dépôt autonome. Avant chaque push, contrôler la branche,
l'URL du remote, le diff indexé et l'absence de secrets, de conflits et de travaux
distants non récupérés. Ne jamais forcer un push.
