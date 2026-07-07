# TaskFlow

MVP de gestion de projet (Jira/Trello-lite) — Symfony 8, Twig, Doctrine (PostgreSQL), Docker.

## Prérequis

- Docker + Docker Compose

## Démarrage (premier lancement)

```bash
make install
```

Cette commande build les images et démarre tous les services. Au boot, l'entrypoint
(`docker/entrypoint.sh`) exécute automatiquement :

1. copie de `.env.example` → `.env` (avec un `APP_SECRET` généré) si `.env` est absent ;
2. `composer install` + dump des autoloaders ;
3. `importmap:install` — **télécharge les assets front (Stimulus, Turbo, Chart.js)** dans
   `assets/vendor/` ;
4. warm-up du cache + migrations Doctrine.

Une fois démarré :

| Service            | URL                     |
|--------------------|-------------------------|
| Application        | http://localhost:8089   |
| Adminer (BDD)      | http://localhost:8088   |
| Mailpit (e-mails)  | http://localhost:8025   |

**Après le premier boot**, charger les migrations **et** le jeu de données de test
(comptes `admin@` / `manager@` / `member@taskflow.test` mot de passe `password`, ainsi que des
**organisations** et projets de démonstration) :

```bash
make setup
```

> ⚠️ Sans cette étape, la base est vide : aucun compte pour se connecter et **aucune
> organisation**, or un projet doit appartenir à une organisation → la création de projet est
> bloquée. `make setup` règle les deux. (`doctrine:fixtures:load` **purge** la base : ne jamais
> l'automatiser au boot ; c'est pourquoi c'est une étape manuelle.)

Pour recharger uniquement les données de test :

```bash
make fixtures
```

## Rôles & organisation du travail

Modèle de rôles (cf. cahier des charges) : `ROLE_USER ⊂ ROLE_MANAGER ⊂ ROLE_ADMIN`.

- Un **chef de projet** (`ROLE_MANAGER`) crée une **organisation**, y crée des **projets**, et
  **invite des membres** (écran « Membres » d'un projet) en leur attribuant un rôle interne
  (chef / contributeur / observateur).
- Un **membre** (`ROLE_USER`) ne crée pas de projet : il est **invité** à un projet, puis y
  consulte / fait avancer / commente les tâches qui lui sont assignées. Un nouvel inscrit est
  `ROLE_USER` ; un **admin** peut le promouvoir gestionnaire via `/admin/users`.

Un utilisateur ne peut être **assigné** à une tâche que s'il est **membre du projet** — d'où
l'importance d'inviter les membres pour que le Kanban soit pleinement utilisable.

## Commandes utiles

```bash
make up        # démarrer les conteneurs
make down      # arrêter les conteneurs
make restart   # redémarrer
make sh        # shell dans le conteneur PHP
make cache     # vider le cache Symfony
make migrate   # jouer les migrations Doctrine
make setup     # migrations + données de test (à faire au premier boot)
make fixtures  # (re)charger uniquement les données de test
make assets    # (re)télécharger les assets front (importmap:install)
make test      # prépare la base app_test et lance PHPUnit
make logs      # suivre les logs du conteneur PHP
make help      # aide
```

## Tests

```bash
make test
```

Crée si besoin la base **`app_test`** (isolée de la base dev `app`), joue les migrations,
puis lance PHPUnit. L'isolation repose sur `dama/doctrine-test-bundle` : chaque test tourne
dans une transaction annulée à la fin (base toujours propre). Couverture actuelle :

- **Unitaire** — `ProjectProgressCalculator` (calcul d'avancement) et
  `AiTaskGenerator::parseSuggestions` (normalisation du JSON IA), sans base ni réseau.
- **Fonctionnel** (`WebTestCase`) — inscription → connexion → création de tâche ; API JSON
  (`/api/v1/projects/{id}/tasks`) protégée par le firewall de session.

## Dépannage

### « The "@hotwired/stimulus" vendor asset is missing. Try running the "importmap:install" command. »

Le dossier `assets/vendor/` est **gitignoré** : les paquets JS ne sont pas versionnés, ils
sont (re)téléchargés à partir de `importmap.php`. Sur un clone frais, ils sont donc absents
tant que `importmap:install` n'a pas tourné.

L'entrypoint le fait désormais automatiquement au boot. Si l'erreur persiste (conteneur déjà
démarré avant ce correctif, par ex.), lancer manuellement :

```bash
make assets
# équivaut à : docker compose exec php php bin/console importmap:install
```

## Configuration IA (optionnelle)

La génération de tâches par IA utilise l'API [Groq](https://console.groq.com) (gratuite).
Sans clé, la fonctionnalité est simplement désactivée (le reste de l'app fonctionne).

Pour l'activer, renseigner la clé dans `.env.local` (**non versionné**) :

```dotenv
GROQ_API_KEY=gsk_votre_cle_ici
```
