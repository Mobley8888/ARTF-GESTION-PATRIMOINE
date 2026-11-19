# Mise en service — ARTF Gestion du Patrimoine

Application de l’**Agence de Régulation des Transferts de Fonds (ARTF)**, **République du Congo**.

## État vérifié le 19 novembre 2026

- Laravel 13.43.0 et PHP 8.3.36.
- MySQL 8.4.3, schéma `artf_gestion_patrimoine`, 26 tables.
- Les migrations présentes sont marquées comme exécutées.
- Une sauvegarde SQL portable et horodatée est disponible dans `storage/app/backups/`. Son SHA-256 est consigné dans le compte rendu de recette ; conserver une copie chiffrée hors du serveur avant mise en production.
- La sauvegarde a été restaurée dans un schéma MySQL temporaire isolé ; les 26 tables et les tables patrimoniales/inventaire ont été contrôlées. Le schéma temporaire a ensuite été supprimé.

## Paramètres de production

Renseigner ces paramètres dans le gestionnaire de secrets de l’hébergement ou dans son fichier `.env` privé. Ne jamais publier ce fichier, réutiliser un mot de passe local, ni remplacer l’`APP_KEY` d’une installation existante.

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<nom-de-domaine-de-l-ARTF>
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=<hote-mysql>
DB_PORT=3306
DB_DATABASE=artf_gestion_patrimoine
DB_USERNAME=<compte-app-a-privileges-minimaux>
DB_PASSWORD=<secret-fourni-par-le-gestionnaire-de-secrets>

SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

Utiliser HTTPS, un compte applicatif MySQL à privilèges minimaux, des secrets différents de l’environnement local et une journalisation avec rotation. Vérifier les options de file d’attente, de session et de stockage selon l’infrastructure retenue. Les documents et justificatifs doivent rester sur un stockage privé et être servis après contrôle d’autorisation.

## Déploiement applicatif

1. Déployer le code validé et les ressources compilées ; installer les dépendances PHP de production avec `composer install --no-dev --prefer-dist --optimize-autoloader`.
2. Injecter la configuration de production sans afficher les secrets ; vérifier que `APP_DEBUG=false` et que l’URL utilise HTTPS.
3. Créer les liens de stockage nécessaires et accorder au compte du serveur web l’écriture uniquement dans `storage/` et `bootstrap/cache/`.
4. Vérifier la connexion avec `php artisan db:show`, puis `php artisan migrate:status`. Ne lancer aucune migration tant qu’un diff de schéma n’a pas été validé et qu’une sauvegarde récente n’est pas disponible.
5. Exécuter `php artisan optimize`, puis vérifier l’écran de connexion, les permissions par rôle, les pages principales et les téléchargements PDF/Excel.
6. Configurer TLS, les tâches planifiées et les workers uniquement si les services correspondants sont activés dans l’hébergement. Prévoir surveillance des journaux, rotation, sauvegardes et alertes.

Ne pas utiliser `migrate:fresh`, `db:wipe`, ni lancer de seeders sur la base de production.

## Sauvegarde et restauration

Créer chaque sauvegarde sous un nouveau nom horodaté, sans écraser les précédentes. Exemple Laragon :

```powershell
& 'C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe' `
  --host=<hote> --port=3306 --user=<compte-sauvegarde> --password `
  --single-transaction --routines --triggers artf_gestion_patrimoine `
  --result-file='C:\sauvegardes\artf_gestion_patrimoine_<date-heure>.sql'
```

Le dump portable ne contient pas de commande `USE` ni de sélection imposée du schéma. Pour un exercice, créer un schéma vide distinct, restaurer avec `mysql --database=<schema-de-test> < sauvegarde.sql`, comparer tables et volumes, puis retirer uniquement ce schéma d’exercice. Pour une restauration réelle, mettre l’application hors ligne et faire valider le schéma cible, l’heure du point de reprise et la conservation de la base actuelle avant toute importation.

Conserver les sauvegardes chiffrées, avec accès restreint, copie hors machine et durée de rétention définie. La sauvegarde locale existante ne constitue pas à elle seule un plan de reprise après sinistre.

## Limites avant ouverture aux utilisateurs

- L’environnement actuellement vérifié est local (`APP_ENV=local`, `APP_DEBUG=true`) ; aucun serveur de production ni domaine HTTPS n’a été configuré dans cette recette.
- Les profils et parcours métier sont couverts par les tests automatisés SQLite. Un test de distribution de tableau de bord, en lecture seule, a été exécuté sur MySQL réel. Les scénarios MySQL modifiant comptes, référentiels ou biens restent à exécuter sur une copie de recette dédiée, afin de ne pas modifier les données de production pendant cette validation.
- Les documents affichent un emplacement réservé au logo ; le fichier officiel du logo ARTF reste à fournir pour son intégration.
