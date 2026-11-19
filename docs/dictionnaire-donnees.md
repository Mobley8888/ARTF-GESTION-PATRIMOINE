# Dictionnaire des données

Les tailles ci-dessous sont des longueurs maximales applicatives Laravel.
`BIGINT` correspond à `$table->id()` / `foreignId()`. Les identifiants sont
techniques ; les codes indiqués sont les identifiants métier uniques.

## Référentiels

### `categories`

| Champ | Type | Null | Règle |
|---|---|---:|---|
| `id` | BIGINT | non | Clé primaire |
| `parent_id` | BIGINT | oui | FK vers `categories.id`, suppression restreinte ; un seul niveau enfant |
| `code` | VARCHAR(40) | non | Unique global, non réutilisé |
| `name` | VARCHAR(120) | non | Libellé |
| `description` | TEXT | oui | Description |
| `is_active` | BOOLEAN | non | Vrai par défaut |
| `created_at`, `updated_at` | DATETIME | oui | Horodatages Laravel |

### `locations`

| Champ | Type | Null | Règle |
|---|---|---:|---|
| `id` | BIGINT | non | Clé primaire |
| `parent_id` | BIGINT | oui | FK auto-référente, suppression restreinte |
| `type` | VARCHAR(20) | non | `site`, `building` ou `room`, cast `LocationType` |
| `code` | VARCHAR(40) | non | Unique global, non réutilisé |
| `name` | VARCHAR(160) | non | Libellé |
| `address` | VARCHAR(255) | oui | Adresse de site/bâtiment |
| `surface_m2` | DECIMAL(12,2) | oui | Surface déclarée, unité m² |
| `description` | TEXT | oui | Description complémentaire |
| `is_active` | BOOLEAN | non | Vrai par défaut |
| `created_at`, `updated_at` | DATETIME | oui | Horodatages Laravel |

La règle parent/type est validée par le modèle : site sans parent, bâtiment sous un
site et local sous un bâtiment. Un site ne peut pas être directement parent d'un
local.

### `directions`

`id BIGINT` PK ; `code VARCHAR(40)` unique ; `name VARCHAR(160)` ; `description
TEXT NULL` ; `is_active BOOLEAN` ; `created_at`, `updated_at DATETIME`.

### `services`

`id BIGINT` PK ; `direction_id BIGINT` FK `directions` (RESTRICT) ; `code
VARCHAR(40)` unique au sein d'une direction ; `name VARCHAR(160)` ; `description
TEXT NULL` ; `is_active BOOLEAN` ; horodatages. Index sur `(direction_id, code)`.

### `agents`

`id BIGINT` PK ; `service_id BIGINT NULL` FK `services` (RESTRICT) ; `user_id
BIGINT NULL` FK `users` (RESTRICT, unique) ; `employee_code VARCHAR(40)` unique ;
`first_name VARCHAR(100)` ; `last_name VARCHAR(100)` ; `is_active BOOLEAN` ;
horodatages. Aucun e-mail ou téléphone personnel n'est collecté à ce stade.

## Biens et historique

### `assets`

| Champ | Type | Null | Règle |
|---|---|---:|---|
| `id` | BIGINT | non | Clé primaire |
| `category_id` | BIGINT | non | FK `categories` (RESTRICT), indexé |
| `location_id` | BIGINT | oui | FK `locations` (RESTRICT), lieu courant |
| `created_by`, `updated_by` | BIGINT | oui | FK `users` (RESTRICT) |
| `asset_code` | VARCHAR(60) | non | Code métier unique, fourni explicitement |
| `designation` | VARCHAR(180) | non | Désignation |
| `description` | TEXT | oui | Description |
| `serial_number` | VARCHAR(120) | oui | Index non unique |
| `characteristics` | JSON | oui | Attributs descriptifs variables, sans données personnelles |
| `acquired_on` | DATE | oui | Date d'acquisition connue |
| `acquisition_value` | DECIMAL(18,2) | oui | Valeur source FCFA ; 0 ou positive, précision/échelle contrôlées dans le modèle |
| `condition` | VARCHAR(24) | oui | Cast `AssetCondition`, valeurs documentées dans le modèle conceptuel |
| `status` | VARCHAR(24) | non | Cast `AssetStatus`, défaut `in_service` |
| `created_at`, `updated_at` | DATETIME | oui | Horodatages Laravel |
| `deleted_at` | DATETIME | oui | Suppression logique ; historique conservé |

Index complémentaires : `(status, category_id)`, `location_id`, `serial_number`.
Le numéro de série n'est pas unique. Les codes métier des biens supprimés
logiquement ne sont pas réutilisables.

### `asset_location_histories`

| Champ | Type | Null | Règle |
|---|---|---:|---|
| `id` | BIGINT | non | Clé primaire |
| `asset_id` | BIGINT | non | FK `assets` (RESTRICT) |
| `from_location_id` | BIGINT | oui | FK `locations` (RESTRICT), null à la première localisation |
| `to_location_id` | BIGINT | oui | FK `locations` (RESTRICT), null si le lieu est retiré |
| `recorded_by` | BIGINT | oui | FK `users` (RESTRICT), null réservé aux imports/sources système |
| `moved_at` | DATETIME | non | Date/heure de l'événement |
| `reason` | VARCHAR(120) | non | Motif obligatoire |
| `notes` | TEXT | oui | Commentaire |
| `created_at`, `updated_at` | DATETIME | oui | Horodatages Laravel |

Index sur `(asset_id, moved_at)` et sur les deux lieux. Une ligne ne peut pas
décrire deux lieux identiques ni être vide. Pas de suppression logique.

## Tables Laravel préexistantes

`users`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`,
`job_batches` et `failed_jobs` sont fournies par le squelette Laravel et ne sont
pas remplacées par les migrations patrimoniales.
