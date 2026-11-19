# ARTF – PLATEFORME INTÉGRÉE DE GESTION DU PATRIMOINE

Plateforme envisagée pour la gestion des inventaires, des immobilisations et du
patrimoine de l'Agence de Régulation des Transferts de Fonds (ARTF). Le projet est
prévu avec Laravel, PHP et MySQL afin d'assurer le suivi des acquisitions,
amortissements, affectations, inventaires physiques, maintenances, patrimoine
immobilier et rapports décisionnels.

> **État initial :** dépôt documentaire en préparation. Aucun module applicatif
> n'est développé et aucun cahier des charges détaillé n'a été trouvé dans le
> dossier de travail au moment de cet audit.

## Contexte et objectifs

Le projet vise à centraliser le cycle de vie des biens, améliorer la traçabilité des
mouvements et fournir des informations fiables pour le pilotage patrimonial. Le
périmètre fonctionnel devra être confirmé à partir du cahier des charges officiel
avant le démarrage du développement.

## Fonctionnalités et modules prévus

- Référentiel et gestion des biens patrimoniaux ;
- acquisitions, fournisseurs et pièces justificatives ;
- amortissements et valeurs nettes comptables ;
- inventaires physiques et rapprochements ;
- affectations, mouvements et historique ;
- maintenance et réparations ;
- patrimoine immobilier ;
- gestion documentaire ;
- tableau de bord décisionnel ;
- rapports PDF et Excel ;
- rôles, permissions et journalisation d'audit ;
- sauvegarde et restauration.

Ces éléments décrivent le périmètre envisagé et ne constituent pas des fonctions
déjà livrées.

## Technologies envisagées

- PHP et Laravel ;
- MySQL ou MariaDB ;
- Composer ;
- Node.js et npm pour les ressources front-end, si requis ;
- Laragon pour le développement local sous Windows.

Les versions seront arrêtées lors de la création du squelette applicatif.

## Prérequis

Installer et vérifier les versions de Git, PHP, Composer, Node.js, npm et Laragon.
Préparer une instance MySQL/MariaDB locale. GitHub CLI (`gh`) est nécessaire pour
les opérations de dépôt prévues en ligne de commande.

## Installation sous Laragon

Le dépôt local contient uniquement la documentation et les règles de protection ;
il n'est pas encore une application Laravel exécutable. Le dépôt GitHub public
`Mobley8888/ARTF-GESTION-PATRIMOINE` existe sur `main`, mais le dossier local n'est
pas initialisé avec Git ni synchronisé. Une fois le squelette Laravel ajouté et le
dépôt local configuré :

1. Placer/cloner le dépôt dans
   `C:\laragon\www\E2M-PROJETS\ARTF-GESTION-PATRIMOINE`.
2. Démarrer Apache/Nginx et MySQL/MariaDB depuis Laragon.
3. Copier `.env.example` vers `.env` localement, si ce fichier existe, puis définir
   des identifiants locaux non réels.
4. Installer les dépendances avec Composer et npm selon les instructions du
   squelette Laravel.
5. Configurer une base de données locale vide et dédiée ; ne jamais importer des
   données institutionnelles dans un dépôt public.
6. Exécuter les migrations uniquement après validation du schéma et des paramètres.

## Configuration de la base de données

Utiliser une base locale dédiée à l'application, avec un compte aux privilèges
minimaux. Les paramètres de connexion appartiennent au fichier `.env` local et ne
doivent jamais être commités. N'utiliser que des données fictives ou explicitement
autorisées. Aucun export réel ne doit être ajouté au dépôt.

## Commandes de lancement

Les commandes ci-dessous ne sont applicables qu'après ajout du squelette Laravel :

```powershell
composer install
npm install
php artisan key:generate
php artisan migrate
npm run dev
php artisan serve
```

Consulter les scripts réellement présents dans `composer.json` et `package.json`
avant de lancer ces commandes.

## Organisation du dépôt

```text
ARTF-GESTION-PATRIMOINE/
├── README.md
├── .gitignore
├── PROJECT_STATUS.md
├── TECHNICAL_DOCUMENTATION.md
├── ROADMAP.md
└── CHANGELOG.md
```

Le code applicatif et les tests seront ajoutés après validation du cahier des
charges. Ce dossier doit avoir son propre dépôt Git ; aucun dépôt global ne doit
être créé dans `E2M-PROJETS`.

## Contribution et versionnement

Examiner `git status`, le diff et les fichiers sensibles avant chaque commit.
Utiliser les préfixes `feat:`, `fix:`, `security:`, `refactor:`, `docs:`, `test:`
ou `chore:` suivis d'un résumé précis. Les commits et pushes ne doivent inclure
aucun secret ni donnée confidentielle. Voir [PROJECT_STATUS.md](PROJECT_STATUS.md)
et [TECHNICAL_DOCUMENTATION.md](TECHNICAL_DOCUMENTATION.md).

## État d'avancement

Initialisation documentaire uniquement. Le dépôt distant public existe sur `main`
avec un commit README initial. L'initialisation Git locale, la vérification des
versions/outils et le squelette Laravel restent à réaliser et à valider.

## Roadmap

Consulter [ROADMAP.md](ROADMAP.md). Le développement des modules est différé jusqu'à
la validation du cahier des charges et la mise en place sûre du versionnement.
