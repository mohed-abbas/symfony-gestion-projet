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

Charger les données de test (comptes `admin@` / `manager@` / `member@taskflow.test`,
mot de passe `password`) :

```bash
make fixtures
```

## Commandes utiles

```bash
make up        # démarrer les conteneurs
make down      # arrêter les conteneurs
make restart   # redémarrer
make sh        # shell dans le conteneur PHP
make cache     # vider le cache Symfony
make migrate   # jouer les migrations Doctrine
make fixtures  # (re)charger les données de test
make assets    # (re)télécharger les assets front (importmap:install)
make logs      # suivre les logs du conteneur PHP
make help      # aide
```

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
