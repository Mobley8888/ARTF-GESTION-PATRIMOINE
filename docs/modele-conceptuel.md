# Modèle conceptuel des données patrimoniales

## Périmètre et progression

Le dépôt initial contient les tables Laravel `users`, `password_reset_tokens`,
`sessions`, `cache`, `cache_locks`, `jobs`, `job_batches` et `failed_jobs`. Il ne
contenait pas de modèle patrimonial.

Cette première étape implémente les référentiels nécessaires à l'identification
des biens : catégories, lieux hiérarchisés, directions, services et agents. Elle
ajoute les biens, leur localisation courante et un historique append-only des
changements de localisation. Les autres domaines ci-dessous sont le modèle cible,
pas des fonctions déjà livrées.

## Principes de conception

- Identifiants techniques `BIGINT` Laravel ; les codes métier restent des chaînes,
  sont uniques selon la portée documentée et ne sont jamais générés implicitement.
- Référentiels désactivables (`is_active`) plutôt que supprimés ; les clés
  étrangères utilisent `RESTRICT` pour préserver les liens.
- Les biens peuvent être supprimés logiquement. L'historique de localisation n'a
  pas de suppression logique et empêche la suppression physique du bien ou du lieu.
- Valeurs financières en `DECIMAL(18,2)`, jamais en nombres flottants. La valeur
  d'acquisition est une donnée source, pas un calcul comptable.
- Les événements sensibles doivent passer par un service transactionnel qui
  enregistre l'acteur, l'état précédent et l'état suivant.
- Identifiants d'acteurs facultatifs uniquement pour l'initialisation, les imports
  explicitement contrôlés ou les données de démonstration ; une opération humaine
  doit exiger un utilisateur authentifié.
- Les pièces jointes futures seront conservées sur un disque privé ; seules leurs
  métadonnées et leur empreinte seront en base.

## Relations implémentées

- `categories` : arbre limité à deux niveaux, catégorie puis sous-catégorie ;
  un bien appartient à une catégorie.
- `locations` : arbre `site > bâtiment > local` ; un bien a au plus un lieu courant.
- `directions` possèdent plusieurs `services` ; un service peut regrouper plusieurs
  `agents`. Un agent peut être lié à un seul compte `users`, sans créer de compte
  automatiquement.
- `assets` portent un code unique, une désignation, les attributs d'identification,
  une catégorie, un lieu courant facultatif et des états techniques.
- `asset_location_histories` conserve chaque déplacement avec lieu source,
  destination, date, motif et acteur. Le changement du lieu courant et l'écriture
  de l'événement sont transactionnels via `AssetLocationService`.
- `created_by` et `updated_by` relient un bien à `users`; les clés ne sont pas
  supprimées avec l'utilisateur.

## Domaines planifiés

| Domaine | Entités et relations proposées | Décisions à valider avant migration |
|---|---|---|
| Fournisseurs et acquisitions | `suppliers`, `purchase_orders`, `purchase_order_lines`, `invoices`, `invoice_lines`, `receipts`, `receipt_lines`; une ligne réceptionnée peut produire plusieurs biens identifiés | Circuit d'approbation, numérotation, acomptes, multi-factures, réception partielle et traitement des taxes |
| Affectations | `asset_assignments` historisées par période, avec exactement un destinataire agent ou service ; événements de transfert et restitution | Acteur autorisé, chevauchement de périodes, responsabilité en cas d'absence, pièces de transfert |
| Immobilisations | `depreciation_plans` et `depreciation_entries` périodiques, reliés au bien | Durées par catégorie, date de départ, méthodes, prorata, valeur résiduelle, arrondis, clôture et correction |
| Inventaires | `inventory_campaigns`, équipes, `inventory_observations` immuables et écarts rapprochés avec le bien | Périodicité, règles de comptage, tolérances, validation et procédure de régularisation |
| Maintenance | demandes, interventions, lignes de coûts et prestataires reliés aux biens | Workflow, garanties, seuils d'approbation, coût capitalisable et relation avec les achats |
| Immobilier | extension spécialisée du bien pour terrain/bâtiment, titres, surfaces et caractéristiques | Références cadastrales, unités/surfaces, propriété ou location, titres et règles de confidentialité |
| Documents | `documents` (métadonnées/stockage privé) et tables de liaison avec clés étrangères propres à chaque domaine | Types de pièces, taille, rétention, accès, antivirus, chiffrement et durée de conservation |
| Administration | rôles, permissions, liaisons utilisateurs-rôles et journal d'audit append-only | Matrice des habilitations, séparation des tâches, durée de conservation et accès aux journaux |

Ne pas remplacer les historiques métier par un simple champ « courant » : les
tables de périodes ou d'événements restent la source de traçabilité, et les champs
courants sont des projections mises à jour dans la même transaction.

## Hypothèses et validations ARTF requises

1. **Hiérarchies initiales** : les catégories ont au plus deux niveaux ; les lieux
   ont exactement les niveaux site, bâtiment et local. Les règles de nomenclature
   et les éventuelles exceptions sont à confirmer.
2. **Codification** : le code de bien est fourni par un processus validé par l'ARTF
   et unique dans l'application. Aucun préfixe, format ou compteur n'est imposé.
   Le numéro de série est indexé mais pas déclaré unique.
3. **Localisation** : un seul lieu courant peut être associé au bien ; il peut être
   inconnu. La relation historique est enregistrée pour chaque déplacement.
4. **Organisation** : un agent dépend au plus d'un service courant ; l'historique
   des changements d'affectation de personnel reste à concevoir si requis.
5. **États** : les codes techniques proposés sont `in_service`,
   `under_maintenance`, `retired`, `disposed`, `lost`; l'état physique proposé
   est `new`, `good`, `fair`, `poor`, `unserviceable`. Leur libellé et les
   transitions autorisées doivent être validés. Aucune transition comptable ou
   administrative n'est automatisée dans cette étape. Proposition de workflow à
   valider : `in_service` → `under_maintenance`, `retired` ou `lost` ;
   `under_maintenance` → `in_service`, `retired` ou `lost` ; `lost` → `in_service`
   après récupération ou `disposed` après approbation ; `retired` → `disposed`
   après approbation ; `disposed` terminal. Cette matrice n'est pas appliquée par
   le code tant que l'ARTF ne l'a pas approuvée.
6. **Finances** : `acquisition_value` est stockée en FCFA, `DECIMAL(18,2)` pour
   ne pas perdre de précision. La précision réellement autorisée, la convention
   de devise (XAF/XOF selon le périmètre), les taxes et règles d'arrondi doivent
   être décidées. Aucun amortissement n'est calculé.
7. **Dates inconnues** : date et valeur d'acquisition sont nullables pour les biens
   anciens dont les pièces ne sont pas disponibles ; aucun montant n'est reconstitué.
8. **Suppression** : les référentiels et journaux sont conservés ; la mise hors
   service se fait par désactivation ou statut, pas par effacement physique.

## Compatibilité des moteurs

Les migrations utilisent les colonnes et contraintes Laravel portables : clés
`id()`/`foreignId()` (BIGINT auto-incrémenté et FK non signée sous MySQL), chaînes,
`DATE`, `DATETIME`, `DECIMAL(18,2)`, `JSON`, index simples, clés étrangères et
suppression restreinte. Les statuts sont des chaînes avec casts PHP plutôt que
des types `ENUM` propres à un moteur. Le schéma évite les séquences SQLite, les
triggers et les index partiels.

Les tests et migrations sont exécutés sur SQLite. MySQL 8.4 n'étant pas actif dans
Laragon, aucune migration réelle n'y a été exécutée ; la compatibilité MySQL reste
à confirmer dans une instance dédiée avant déploiement.
