# Cahier des Charges — TaskFlow

**Plateforme collaborative de gestion de projet (MVP)**

> Projet de fin de cycle — Cours Symfony (ESGI 4IW2). Développeur : 1 personne.
> Stack : Symfony 8 · Twig · Doctrine (PostgreSQL) · Docker.

---

## 1. Présentation du projet

### 1.1 Contexte

**TaskFlow** est une application web de gestion de projet collaborative, inspirée des outils
type Jira / Trello / Asana. Elle permet à des équipes d'organiser leur travail autour de
**projets**, découpés en **sprints** et en **tâches** visualisées sur un tableau **Kanban**.

### 1.2 Objectif

Fournir un outil permettant :

* à un **chef de projet** de créer des projets, d'y inviter des membres, de planifier des
  sprints et de répartir le travail ;
* à un **membre d'équipe** de consulter, faire avancer et commenter les tâches qui lui sont
  assignées, et d'y consigner son temps ;
* à un **administrateur** de superviser l'ensemble de la plateforme (utilisateurs, projets,
  statistiques globales).

### 1.3 Périmètre du MVP

| Inclus dans le MVP | Exclu (évolutions futures) |
| --- | --- |
| Authentification & gestion de comptes | Facturation / abonnements |
| Organisations & projets | Applications mobiles natives |
| Sprints & backlog | Diagrammes de Gantt |
| Tâches (Bug / Feature / Story) sur board Kanban | Intégrations tierces (Slack, GitHub…) |
| Commentaires, pièces jointes, labels | Rapports analytiques avancés |
| Suivi du temps (worklog) | |
| Notifications in-app + e-mail | |
| API JSON dédiée | |
| Back-office d'administration | |

---

## 2. Typologie des utilisateurs & rôles

L'application définit **trois rôles** cloisonnant les fonctionnalités (exigence : ≥ 3 rôles).

### 2.1 Membre — `ROLE_USER`

Peut :

* créer un compte et gérer son profil ;
* consulter les projets dont il est membre ;
* voir le tableau Kanban et le backlog de ces projets ;
* faire avancer une tâche qui lui est assignée (changement de statut) ;
* commenter une tâche, y ajouter une pièce jointe ;
* consigner du temps passé (worklog) ;
* recevoir des notifications.

### 2.2 Chef de projet — `ROLE_MANAGER`

Hérite des droits `ROLE_USER`, et peut en plus :

* créer une organisation et des projets ;
* inviter / retirer des membres d'un projet et définir leur rôle interne ;
* créer et planifier des sprints ;
* créer, assigner, éditer et supprimer des tâches ;
* gérer les labels du projet ;
* consulter les statistiques du projet (avancement, charge, tâches en retard).

### 2.3 Administrateur — `ROLE_ADMIN`

Accès au **back-office** sécurisé :

* gérer l'ensemble des utilisateurs (activation, suspension, changement de rôle) ;
* superviser tous les projets et organisations ;
* consulter les statistiques globales de la plateforme ;
* modérer les contenus (commentaires signalés).

---

## 3. Droits d'accès fins (Voters)

Au-delà des rôles, des permissions fines dépendantes du cycle de vie des objets sont gérées
par des **Voters personnalisés** (exigence : ≥ 1 Voter) :

* **`TaskVoter`** — un membre ne peut `EDIT` / `DELETE` une tâche que s'il en est l'auteur,
  l'assigné, ou le chef du projet parent.
* **`ProjectVoter`** — un utilisateur ne peut `VIEW` un projet que s'il en est membre (ou
  administrateur).

---

## 4. Fonctionnalités détaillées

### 4.1 Authentification & comptes

* Inscription e-mail / mot de passe (hachage via le Password Hasher de Symfony).
* Connexion / déconnexion natives (Security Component).
* E-mail de confirmation d'inscription (envoi asynchrone).
* Gestion du profil : nom, avatar, bio, poste.

### 4.2 Organisations & projets

* Création d'une organisation (conteneur de projets).
* Création d'un projet : titre, description, clé courte (ex. `TF`), date de début / fin,
  statut (actif / archivé).
* Invitation de membres via une entité de liaison **ProjectMembership** portant des attributs
  (rôle interne : *lead* / *contributor* / *viewer*, date d'ajout).

### 4.3 Sprints & backlog

* Création de sprints (nom, objectif, dates de début / fin).
* Backlog : tâches non affectées à un sprint.
* Affectation d'une tâche à un sprint.

### 4.4 Tâches (cœur métier)

Une tâche est un **WorkItem** décliné en trois sous-types (héritage Doctrine — *Single Table
Inheritance*) :

| Sous-type | Champs spécifiques |
| --- | --- |
| **Bug** | gravité (bloquant / majeur / mineur), étapes de reproduction |
| **Feature** | valeur métier |
| **Story** | story points (estimation) |

Champs communs : titre, description, statut (`À faire` / `En cours` / `En revue` / `Terminé`),
priorité, assigné, auteur, date d'échéance, projet, sprint, labels, sous-tâches.

Fonctionnalités :

* tableau **Kanban** par statut (glisser-déposer) ;
* **formulaire dynamique** : le choix du *type* de tâche affiche les champs spécifiques, et le
  choix du *projet* recharge la liste des membres assignables (Form Events `PRE_SET_DATA` /
  `PRE_SUBMIT`) ;
* commentaires, pièces jointes (réutilisation de l'entité **Document**), labels (ManyToMany) ;
* suivi du temps : consignation d'entrées de worklog (durée, date, description).

### 4.5 Notifications & e-mails

* Notifications in-app (assignation, commentaire, échéance proche).
* E-mails transactionnels via **Mailer** (confirmation d'inscription, « une tâche vous a été
  assignée »), envoyés en **asynchrone** via Messenger.

### 4.6 API JSON dédiée

Contrôleur API dédié exposant des endpoints REST sous `/api/v1/…` (ex. liste des tâches d'un
projet), sérialisés en JSON via le **Serializer** avec gestion fine des **groupes de
normalisation** (`task:list`, `task:read`).

### 4.7 Consommation d'une API externe

Connexion à un service tiers via **HttpClient** *(choix final à confirmer)* :

* **Option privilégiée — IA** : génération automatique d'une décomposition en tâches à partir
  de la description d'un projet.
* **Option de repli** : API de jours fériés pour signaler les jours non ouvrés lors de la
  planification des sprints.

### 4.8 Back-office d'administration

Espace sécurisé (`ROLE_ADMIN`), développé en Twig sur mesure : CRUD avancé des utilisateurs et
projets, statistiques globales (nombre d'utilisateurs, projets actifs, tâches par statut).

---

## 5. Cas d'utilisation (Use Cases)

**UC-01** — S'inscrire et confirmer son e-mail *(Visiteur)*
**UC-02** — Se connecter / se déconnecter *(Tous)*
**UC-03** — Créer une organisation et un projet *(Manager)*
**UC-04** — Inviter un membre à un projet *(Manager)*
**UC-05** — Créer un sprint et y planifier des tâches *(Manager)*
**UC-06** — Créer une tâche (Bug / Feature / Story) et l'assigner *(Manager)*
**UC-07** — Déplacer une tâche sur le Kanban (changer de statut) *(Membre assigné)*
**UC-08** — Commenter une tâche et y joindre un document *(Membre)*
**UC-09** — Consigner du temps sur une tâche *(Membre)*
**UC-10** — Recevoir une notification / un e-mail d'assignation *(Membre)*
**UC-11** — Consulter les statistiques d'un projet *(Manager)*
**UC-12** — Consulter la liste des tâches via l'API JSON *(Client API)*
**UC-13** — Administrer les utilisateurs et consulter les stats globales *(Admin)*

---

## 6. Modèle de données

### 6.1 Entités (≥ 10 — ici 13)

1. **User** — utilisateur, rôles, profil.
2. **Organization** — conteneur de projets.
3. **Project** — projet appartenant à une organisation.
4. **ProjectMembership** — liaison User ↔ Project *avec attributs* (rôle interne, date).
5. **Task** *(classe abstraite — base d'héritage STI)*.
6. **BugTask / FeatureTask / StoryTask** — sous-types de Task (héritage).
7. **Sprint** — itération temporelle d'un projet.
8. **TaskComment** — commentaire sur une tâche.
9. **Label** — étiquette applicable aux tâches.
10. **Document** — pièce jointe d'une tâche.
11. **TimeEntry** — entrée de suivi du temps.
12. **Notification** — notification utilisateur.
13. **ActivityLog** — journal d'activité par projet.

### 6.2 Relations

**ManyToMany (≥ 2) :**

* `User ↔ Project` via **ProjectMembership** (avec attributs de liaison).
* `Task ↔ Label`.

**OneToMany / ManyToOne (≥ 8) :**

* `Organization → Project`
* `Project → Sprint`
* `Project → Task`
* `Sprint → Task`
* `Task → TaskComment`
* `Task → Document`
* `Task → TimeEntry`
* `Task → Task` (sous-tâches, auto-référence)
* `User → TaskComment` (auteur)
* `User → TimeEntry`
* `User → Notification`
* `Project → ActivityLog`

**Héritage :** `Task` (Single Table Inheritance) → `BugTask`, `FeatureTask`, `StoryTask`.

> Le schéma détaillé (MCD / diagramme de classes UML) est fourni séparément dans
> `Schema BDD.png` et sera tenu à jour.

---

## 7. Architecture technique

| Couche | Choix |
| --- | --- |
| Backend | Symfony 8 (PHP 8.4) |
| ORM | Doctrine |
| Base de données | PostgreSQL |
| Vues | Twig (héritage de templates, blocs, filtres personnalisés) |
| Assets | AssetMapper / Stimulus (Turbo) |
| Asynchrone | Symfony Messenger (transport AMQP) |
| Conteneurisation | Docker (`compose.yaml`) |
| API | Contrôleur dédié + Serializer |
| E-mails | Symfony Mailer / Notifier |
| API externe | Symfony HttpClient |

---

## 8. Sécurité

* Authentification via le **Security Component**, mots de passe hachés.
* Hiérarchie de **3 rôles** (`ROLE_USER` ⊂ `ROLE_MANAGER` ⊂ `ROLE_ADMIN`).
* **Voters** pour les permissions fines (cf. §3).
* Protection CSRF sur les formulaires, échappement Twig (XSS).

---

## 9. Qualité, tests & CI/CD

### 9.1 Tests

* **Test unitaire** (≥ 1) : service `ProjectProgressCalculator` (calcul du % d'avancement) ou
  logique d'un Voter.
* **Test fonctionnel** (≥ 1) : scénario inscription → connexion → création de tâche
  (`WebTestCase`).

### 9.2 Intégration continue (GitHub Actions)

À chaque push :

* linter Symfony (`lint:container`, `lint:twig`, `lint:yaml`) ;
* analyse statique **PHPStan** (niveau 5 minimum) ;
* exécution de la suite de tests.

### 9.3 Déploiement

Application hébergée et accessible en ligne *(cible à confirmer : VPS / Render / Platform.sh)*.

---

## 10. Bonus prévus (développeur solo)

* **Messenger** — envoi asynchrone des e-mails (transport AMQP déjà installé).
* **Commande CLI** — `app:send-deadline-reminders` : notifie les utilisateurs des tâches dont
  l'échéance est proche.
* *(Stretch)* **Mercure** — mise à jour temps réel du tableau Kanban.

---

## 11. Livrables

* Cahier des charges (ce document).
* Schéma de la base de données (`Schema BDD.png`).
* Jeu de fixtures réaliste (DoctrineFixturesBundle + Faker).
* Guide d'installation (`README.md`) avec comptes de test par rôle.
* Code source + pipeline CI + application déployée.

---

## 12. Comptes de test (à générer via les fixtures)

| Rôle | E-mail | Mot de passe |
| --- | --- | --- |
| Administrateur | `admin@taskflow.test` | `password` |
| Chef de projet | `manager@taskflow.test` | `password` |
| Membre | `member@taskflow.test` | `password` |

> Ces identifiants seront finalisés et documentés dans le `README.md`.

---

## 13. Traçabilité — exigences du sujet → couverture

| Exigence du sujet | Couverture TaskFlow |
| --- | --- |
| ≥ 10 entités | 13 entités (§6.1) |
| Héritage d'entités | `Task` STI → Bug / Feature / Story |
| ≥ 2 ManyToMany | User↔Project (attributs), Task↔Label |
| ≥ 8 OneToMany / ManyToOne | 12 relations (§6.2) |
| ≥ 3 rôles | USER / MANAGER / ADMIN |
| ≥ 1 Voter | TaskVoter, ProjectVoter |
| API dédiée + Serializer | `/api/v1/…` + groupes de normalisation |
| Mailer / Notifier | Confirmation d'inscription, assignation |
| API externe (HttpClient) | IA ou jours fériés (§4.7) |
| Interface d'administration | Back-office Twig sécurisé |
| Formulaires dynamiques (Form Events) | Formulaire de tâche (type + membres) |
| Requêtes QueryBuilder | Stats projet, board (anti N+1) |
| ≥ 10 pages Twig | 13+ pages (voir plan de développement) |
| ≥ 1 test unitaire + 1 fonctionnel | §9.1 |
| CI (lint + PHPStan 5 + tests) | GitHub Actions (§9.2) |
| Déploiement en ligne | §9.3 |
| Bonus | Messenger, CLI (+ Mercure stretch) |
