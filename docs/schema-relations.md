# Schéma des relations

## Socle implémenté

```mermaid
erDiagram
    CATEGORIES o|--o{ CATEGORIES : parent_de
    CATEGORIES ||--o{ ASSETS : classe
    LOCATIONS o|--o{ LOCATIONS : parent_de
    LOCATIONS o|--o{ ASSETS : localisation_courante
    DIRECTIONS ||--o{ SERVICES : regroupe
    SERVICES o|--o{ AGENTS : rattache
    USERS o|--o| AGENTS : compte_associe
    USERS o|--o{ ASSETS : cree_ou_modifie
    ASSETS ||--o{ ASSET_LOCATION_HISTORIES : conserve
    LOCATIONS o|--o{ ASSET_LOCATION_HISTORIES : origine
    LOCATIONS o|--o{ ASSET_LOCATION_HISTORIES : destination
    USERS o|--o{ ASSET_LOCATION_HISTORIES : enregistre

    CATEGORIES {
        bigint id PK
        bigint parent_id FK
        varchar code UK
        varchar name
        boolean is_active
    }
    LOCATIONS {
        bigint id PK
        bigint parent_id FK
        varchar type
        varchar code UK
        varchar name
        decimal surface_m2
        boolean is_active
    }
    DIRECTIONS {
        bigint id PK
        varchar code UK
        varchar name
    }
    SERVICES {
        bigint id PK
        bigint direction_id FK
        varchar code
        varchar name
    }
    AGENTS {
        bigint id PK
        bigint service_id FK
        bigint user_id FK
        varchar employee_code UK
        varchar first_name
        varchar last_name
    }
    ASSETS {
        bigint id PK
        bigint category_id FK
        bigint location_id FK
        bigint created_by FK
        bigint updated_by FK
        varchar asset_code UK
        varchar serial_number
        decimal acquisition_value
        varchar status
    }
    ASSET_LOCATION_HISTORIES {
        bigint id PK
        bigint asset_id FK
        bigint from_location_id FK
        bigint to_location_id FK
        bigint recorded_by FK
        datetime moved_at
        varchar reason
    }
    USERS {
        bigint id PK
        varchar email UK
    }
```

`o|` indique une relation facultative côté enfant lorsqu'une FK est nullable.
L'arbre `locations` est limité aux niveaux `site > building > room` par validation
du modèle ; l'arbre `categories` est limité à catégorie > sous-catégorie.

## Relations métier prévues — non migrées

```text
Supplier 1 ── N PurchaseOrder 1 ── N PurchaseOrderLine
PurchaseOrderLine 1 ── N ReceiptLine N ── 1 Receipt
PurchaseOrderLine 1 ── N InvoiceLine N ── 1 Invoice
ReceiptLine 1 ── N Asset (lien à arrêter selon réception/numéros de série)

Asset 1 ── N AssetAssignment ── 0..1 Agent
AssetAssignment ── exactement un Agent ou un Service
Asset 1 ── N DepreciationEntry ; DepreciationPlan 1 ── N DepreciationEntry
InventoryCampaign 1 ── N InventoryObservation N ── 0..1 Asset
InventoryCampaign N ── N User/Agent via InventoryTeamMember
Asset 1 ── N MaintenanceRequest 1 ── N MaintenanceIntervention
MaintenanceIntervention 1 ── N MaintenanceCostLine N ── 0..1 Supplier
Asset 1 ── 0..1 RealEstateDetail
Document N ── N domaine métier via tables de liaison à clés étrangères dédiées
User N ── N Role N ── N Permission ; AuditLog conserve les actions sans suppression
```

Les relations futures sont indicatives. Les clés, règles d'approbation, méthodes
comptables, statuts et contraintes de rétention doivent être validés avec l'ARTF
avant de créer ces tables.
