# ARTF – PLATEFORME INTÉGRÉE DE GESTION DU PATRIMOINE

Plateforme de gestion des inventaires, des immobilisations et du patrimoine de
l'Agence de Régulation des Transferts de Fonds (ARTF). Le projet utilise Laravel,
Blade et Bootstrap ; le référentiel des biens patrimoniaux et l'administration
des comptes disposent de parcours fonctionnels.

> **État actuel :** biens patrimoniaux consultables, recherchables, créables et
> modifiables selon les rôles ; référentiels catégories/sous-catégories et
> sites/localisations administrables par les rôles habilités. Les autres domaines
> restent à développer.

## Contexte et objectifs

Le projet vise à centraliser le cycle de vie des biens, améliorer la traçabilité des
mouvements et fournir des informations fiables pour le pilotage patrimonial. Le
périmètre fonctionnel devra être confirmé à partir du cahier des charges officiel
avant le démarrage du développement.

## Fonctionnalités

- **Livrés :** référentiel des biens patrimoniaux (consultation, recherche,
  création, modification, archivage et historique de localisation), référentiels
  catégories et localisations, et administration des comptes selon les rôles ;
- **À développer :**
  - acquisitions, fournisseurs et pièces justificatives ;
  - amortissements et valeurs nettes comptables ;
  - inventaires physiques et rapprochements ;
  - affectations, mouvements et historique ;
  - maintenance et réparations ;
  - patrimoine immobilier ;
  - gestion documentaire ;
  - tableau de bord décisionnel ;
  - rapports PDF et Excel ;
  - journalisation d'audit ;
  - sauvegarde et restauration.

Les éléments « à développer » décrivent le périmètre envisagé et ne constituent
pas des fonctions déjà livrées.

## Modèle de données

Le socle initial couvre les référentiels, les biens et l'historique de leurs
localisations, ainsi que les comptes et leurs rôles. Le modèle conceptuel, le
dictionnaire, le schéma relationnel et les décisions à valider sont décrits dans
[docs/modele-conceptuel.md](docs/modele-conceptuel.md),
[docs/dictionnaire-donnees.md](docs/dictionnaire-donnees.md) et
[docs/schema-relations.md](docs/schema-relations.md). Acquisitions, dotations,
amortissements, inventaires, maintenance, immobilier, documents et audit restent
à construire par étapes.

## Technologies

- PHP 8.3.36 et Laravel 13 (framework résolu en 13.43.0) ;
- Blade pour les vues, Bootstrap 5 et Bootstrap Icons ;
- Composer 2.11.2 ;
- Node.js 22.23.4, npm 10.9.9 et Vite ;
- SQLite pour les tests automatisés ;
- Laragon sous Windows ; l'intégration MySQL 8.4.3 a été vérifiée sans conserver
  de données de test.

React, Vue, Inertia et Tailwind ne sont pas utilisés.

## Prérequis

Installer PHP 8.3+, Composer, Node.js/npm et Git. Le binaire Node de Laragon est
accessible dans `C:\laragon\bin\nodejs\node-v22`. Sous PowerShell, utiliser
`npm.cmd` si la stratégie d'exécution bloque `npm.ps1`. L'extension PHP ZIP
existante est désactivée dans le `php.ini` Laragon ; Composer a été exécuté avec
une configuration temporaire pour l'installation.

## Installation sous Laragon

Depuis la racine du dépôt :

```powershell
composer install
Copy-Item .env.example .env # une seule fois
php artisan key:generate
npm.cmd install
npm.cmd run build
php artisan migrate
php artisan db:seed # données fictives, facultatif et idempotent
php artisan serve
```

Le fichier `.env` et la base SQLite locale sont ignorés par Git. N'ajouter aucun
secret ni donnée institutionnelle au dépôt. Pour le développement front-end avec
rechargement automatique, démarrer également `npm.cmd run dev`.

L'interface se trouve à `/` et comprend le tableau de bord ainsi que les pages
`/biens`, `/inventaires`, `/affectations`, `/maintenance` et `/rapports`.
L'administration des comptes est disponible à `/admin/utilisateurs` pour les
comptes disposant de la capacité `manage-users`.
La gestion des référentiels est accessible aux rôles habilités sous
`/admin/referentiels/categories` et `/admin/referentiels/localisations`.

## Configuration de la base de données

SQLite est configuré par défaut pour le démarrage et les tests. MySQL 8.4.3 a
également été utilisé pour un test d'intégration transactionnel. Les paramètres
de connexion restent locaux dans `.env`; ne jamais versionner ce fichier.

## Commandes de lancement

Commandes courantes :

```powershell
composer install
npm.cmd install
npm.cmd run build
npm.cmd run dev
php artisan migrate
php artisan serve
php artisan test
```

## Organisation du dépôt

```text
ARTF-GESTION-PATRIMOINE/
├── README.md
├── .gitignore
├── app/
├── resources/
├── routes/
├── tests/
├── docs/
├── composer.json
├── package.json
├── PROJECT_STATUS.md
├── TECHNICAL_DOCUMENTATION.md
├── ROADMAP.md
└── CHANGELOG.md
```

Le dépôt existant est conservé. Aucun dépôt global ne doit être créé dans
`E2M-PROJETS`.

## Contribution et versionnement

Examiner `git status`, le diff et les fichiers sensibles avant chaque commit.
Utiliser les préfixes `feat:`, `fix:`, `security:`, `refactor:`, `docs:`, `test:`
ou `chore:` suivis d'un résumé précis. Les commits et pushes ne doivent inclure
aucun secret ni donnée confidentielle. Voir [PROJECT_STATUS.md](PROJECT_STATUS.md)
et [TECHNICAL_DOCUMENTATION.md](TECHNICAL_DOCUMENTATION.md).

## État d'avancement

Socle Laravel, gestion des biens patrimoniaux et administration des comptes
validés par les tests et le build Vite. Aucun commit ni push n'a été effectué.

## Roadmap

Consulter [ROADMAP.md](ROADMAP.md). Le développement des modules est différé jusqu'à
la validation du cahier des charges et la mise en place sûre du versionnement.
