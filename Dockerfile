# Image de production : FrankenPHP (serveur web prod-grade, un seul conteneur).
FROM dunglas/frankenphp:php8.4-alpine

# Extensions PHP requises par l'app (pdo_pgsql pour Postgres, intl/zip/gd, amqp exigé par
# symfony/amqp-messenger) + perf (opcache/apcu).
RUN install-php-extensions pdo_pgsql intl zip gd opcache apcu amqp

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

ENV APP_ENV=prod \
    APP_DEBUG=0

# 1) Dépendances d'abord (couche cache tant que composer.* ne bouge pas), sans scripts : pas encore de src.
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-progress --no-interaction

COPY . .

# 3) Autoloader optimisé + cache prod + assets compilés (AssetMapper).
#    Valeurs d'env factices au build (cache/assets ne se connectent pas) ; Render fournit les vraies au runtime.
RUN set -e; \
    cp .env.example .env; \
    export APP_SECRET=build DATABASE_URL="postgresql://app:app@localhost:5432/app?serverVersion=16&charset=utf8"; \
    composer dump-autoload --no-dev --optimize --classmap-authoritative; \
    composer run-script auto-scripts; \
    php bin/console asset-map:compile

# Serveur (FrankenPHP) + orchestration au démarrage (migrations, seed, worker Messenger).
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/entrypoint.prod.sh /usr/local/bin/entrypoint.prod.sh
RUN chmod +x /usr/local/bin/entrypoint.prod.sh

# Render lance le conteneur en non-root avec no-new-privileges : la file-capability du binaire
# FrankenPHP déclenche un EPERM à l'exec. On écoute sur $PORT (>1024), la capability est inutile.
RUN setcap -r /usr/local/bin/frankenphp

ENTRYPOINT ["entrypoint.prod.sh"]
