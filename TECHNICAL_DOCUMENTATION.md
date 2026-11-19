# Documentation technique

## Périmètre

Ce document établit les premières contraintes techniques. Aucun choix d'architecture
applicative ni schéma de base de données n'est encore implémenté.

## Pile cible

- Serveur : PHP avec Laravel ;
- base relationnelle : MySQL ou MariaDB ;
- dépendances PHP : Composer ;
- ressources front-end : Node.js/npm lorsque nécessaire ;
- environnement de développement local : Laragon sous Windows.

Les versions supportées, extensions PHP et outils de test seront documentés après
inspection des versions disponibles et décision sur le squelette Laravel.

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

La séparation des domaines (biens, acquisitions, amortissements, inventaires,
affectations, maintenance, immobilier, documents et rapports), les règles comptables,
les interfaces d'intégration et les exigences de déploiement restent à déterminer
à partir du cahier des charges validé.

## Développement local

Le dépôt initial est documentaire et ne contient pas encore `artisan`,
`composer.json` ou `package.json`. Ne pas exécuter des commandes Laravel/npm avant
l'ajout et la vérification de ces fichiers. Configurer uniquement une base locale
de test sans données réelles.

## Versionnement

Le projet doit être un dépôt autonome. Avant chaque push, contrôler la branche,
l'URL du remote, le diff indexé et l'absence de secrets, de conflits et de travaux
distants non récupérés. Ne jamais forcer un push.
