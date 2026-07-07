#!/bin/sh
set -e

cd /app

# Symfony a besoin d'un fichier .env ; les vraies valeurs viennent des variables d'env Render (elles priment).
if [ ! -f .env ]; then
  cp .env.example .env
fi

echo "==> Migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Crée la table du transport Messenger (Doctrine) si absente.
php bin/console messenger:setup-transports >/dev/null 2>&1 || true

# Seed unique : à activer (APP_LOAD_FIXTURES=1) pour le premier déploiement, puis remettre à 0.
# Les fixtures PURGENT la base : ne jamais laisser à 1 (le conteneur redémarre à chaque réveil).
if [ "$APP_LOAD_FIXTURES" = "1" ]; then
  echo "==> Chargement des fixtures (APP_LOAD_FIXTURES=1)..."
  php bin/console doctrine:fixtures:load --no-interaction
fi

# Worker Messenger en tâche de fond : consomme la file async tant que le conteneur est éveillé.
# La boucle le relance après --time-limit (recyclage mémoire) ou en cas de crash.
echo "==> Démarrage du worker Messenger..."
( while true; do
    php bin/console messenger:consume async --time-limit=3600 --memory-limit=128M --no-interaction || true
    sleep 2
  done ) &

echo "==> Démarrage de FrankenPHP..."
exec frankenphp run --config /etc/frankenphp/Caddyfile
