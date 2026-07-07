# Schéma de la base de données — TaskFlow

Modèle conceptuel de données (MCD) et diagramme de classes du domaine, tenu **à jour avec le
code** (`src/Entity/`) et les migrations Doctrine. Rendu nativement par GitHub (Mermaid).

**Entités : 15 classes** — 12 tables « simples » + `Task` (table unique, héritage STI) déclinée en
`BugTask` / `FeatureTask` / `StoryTask`. Tables physiques : 13 + 2 tables de jonction
(`task_label`, `task_watcher`).

## Diagramme entité-relation (MCD)

```mermaid
erDiagram
    User ||--o{ ProjectMembership : "est membre"
    Project ||--o{ ProjectMembership : "regroupe"
    Organization ||--o{ Project : "possède"
    Project ||--o{ Sprint : "planifie"
    Project ||--o{ Task : "contient"
    Sprint ||--o{ Task : "regroupe"
    Task ||--o{ Task : "sous-tâches"
    Task ||--o{ TaskComment : "reçoit"
    User ||--o{ TaskComment : "écrit"
    Task ||--o{ Document : "pièces jointes"
    User ||--o{ Document : "dépose"
    Task ||--o{ TimeEntry : "temps consigné"
    User ||--o{ TimeEntry : "consigne"
    User ||--o{ Notification : "reçoit"
    Project ||--o{ ActivityLog : "journalise"
    User ||--o{ ActivityLog : "agit"
    User ||--o{ Task : "assigné / auteur"
    Task }o--o{ Label : "étiquetée (task_label)"
    Task }o--o{ User : "observateurs (task_watcher)"

    User {
        int id PK
        string email UK
        json roles
        string password
        string firstName
        string lastName
        string avatar
        text bio
        bool isVerified
        bool isActive
        datetime createdAt
    }
    Organization {
        int id PK
        string name
        string slug UK
        datetime createdAt
    }
    Project {
        int id PK
        int organization_id FK
        string name
        string projectKey
        text description
        date startDate
        date endDate
        string status
        datetime createdAt
    }
    ProjectMembership {
        int id PK
        int user_id FK
        int project_id FK
        string internalRole
        datetime joinedAt
    }
    Sprint {
        int id PK
        int project_id FK
        string name
        text goal
        date startDate
        date endDate
    }
    Task {
        int id PK
        string type "discriminateur STI"
        int project_id FK
        int sprint_id FK
        int assignee_id FK
        int author_id FK
        int parent_id FK
        string title
        text description
        string status
        string priority
        date dueDate
        datetime createdAt
        string severity "BugTask"
        text stepsToReproduce "BugTask"
        text businessValue "FeatureTask"
        int storyPoints "StoryTask"
    }
    Label {
        int id PK
        string name
        string color
    }
    TaskComment {
        int id PK
        int task_id FK
        int author_id FK
        text body
        datetime createdAt
    }
    Document {
        int id PK
        int task_id FK
        int owner_id FK
        string filename
        string mimeType
        int size
        datetime uploadedAt
    }
    TimeEntry {
        int id PK
        int task_id FK
        int user_id FK
        int minutes
        date spentOn
        text description
    }
    Notification {
        int id PK
        int user_id FK
        string type
        string message
        bool isRead
        datetime createdAt
    }
    ActivityLog {
        int id PK
        int project_id FK
        int user_id FK
        string action
        datetime createdAt
    }
```

## Héritage — `Task` (Single Table Inheritance)

Une seule table `task`, colonne discriminante `type` (`bug` / `feature` / `story`). Les champs
spécifiques de chaque sous-type sont des colonnes nullables de cette table.

```mermaid
classDiagram
    class Task {
        <<abstract>>
        +string title
        +string description
        +string status
        +string priority
        +date dueDate
        +User author
        +User assignee
        +Task parent
    }
    class BugTask {
        +string severity
        +string stepsToReproduce
    }
    class FeatureTask {
        +string businessValue
    }
    class StoryTask {
        +int storyPoints
    }
    Task <|-- BugTask
    Task <|-- FeatureTask
    Task <|-- StoryTask
```

## Récapitulatif des relations (exigences du sujet)

**ManyToMany — 3 (exigence : ≥ 2)**
- `User ↔ Project` via l'entité de liaison **ProjectMembership** (avec attributs : rôle interne,
  `joinedAt`).
- `Task ↔ Label` (table de jonction `task_label`).
- `Task ↔ User` — **observateurs** d'une tâche (table de jonction `task_watcher`).

**OneToMany / ManyToOne — 14 (exigence : ≥ 8)**
`Organization→Project` · `Project→Sprint` · `Project→Task` · `Sprint→Task` ·
`Task→Task` (sous-tâches, auto-référence) · `Task→TaskComment` · `User→TaskComment` ·
`Task→Document` · `User→Document` · `Task→TimeEntry` · `User→TimeEntry` · `User→Notification` ·
`Project→ActivityLog` · `User→ActivityLog` · `User→Task` (assigné + auteur).

**Héritage** : `Task` (STI) → `BugTask`, `FeatureTask`, `StoryTask`.

---

> **Régénérer / vérifier le schéma réel** depuis la base :
> ```bash
> docker compose exec php php bin/console doctrine:schema:validate
> ```
> Pour un export image, coller les blocs Mermaid ci-dessus dans <https://mermaid.live> puis
> exporter en PNG/SVG.
