#syntax=docker/dockerfile:1

# Bazowy obraz FrankenPHP z PHP 8.3
FROM dunglas/frankenphp:1-php8.3 AS frankenphp_upstream

# ===================================================================
# BASE — wspólny fundament dla dev i prod
# ===================================================================
FROM frankenphp_upstream AS frankenphp_base

WORKDIR /app
VOLUME /app/var/

# Systemowe zależności w runtime (w tym dla AMQP/RabbitMQ)
# hadolint ignore=DL3008
RUN apt-get update && apt-get install -y --no-install-recommends \
    acl \
    file \
    gettext \
    git \
    librabbitmq-dev \
 && rm -rf /var/lib/apt/lists/*

# Rozszerzenia PHP i Composer
RUN set -eux; \
    install-php-extensions \
        @composer \
        apcu \
        intl \
        opcache \
        zip \
        amqp \
        pdo_pgsql

# Composer może działać jako root wewnątrz kontenera
ENV COMPOSER_ALLOW_SUPERUSER=1

# Dodatkowy katalog na nasze ini
ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

# Konfiguracje FrankenPHP/Caddy
COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/caddy/Caddyfile

ENTRYPOINT ["docker-entrypoint"]

# Healthcheck Caddy/FrankenPHP (metryki na porcie 2019)
HEALTHCHECK --start-period=60s CMD curl -f http://localhost:2019/metrics || exit 1

# Domyślny CMD — może zostać nadpisany w stage'ach niżej
CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile" ]


# ===================================================================
# DEV — obraz developerski z Xdebug i watch-mode
# ===================================================================
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev \
    APP_DEBUG=1 \
    XDEBUG_MODE=off

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN set -eux; \
    install-php-extensions xdebug

COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/

# watch — reload Caddy/FrankenPHP przy zmianach w bind-mountach
CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--watch" ]


# ===================================================================
# PROD — obraz produkcyjny (lekki, bez dev-deps, z warmup cache)
# ===================================================================
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    FRANKENPHP_CONFIG="import worker.Caddyfile"

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/
COPY --link frankenphp/worker.Caddyfile /etc/caddy/worker.Caddyfile

# 1) Zainstaluj zależności Composer (bez dev) — na bazie samych plików manifestów,
#    to pozwala na maksymalne cache’owanie warstw.
COPY --link composer.* symfony.* ./
RUN set -eux; \
    composer install \
        --no-cache \
        --prefer-dist \
        --no-dev \
        --no-autoloader \
        --no-scripts \
        --no-progress

# 2) Skopiuj resztę źródeł aplikacji
COPY --link . ./
# Usuń katalog z plikami pomocniczymi do obrazu
RUN rm -rf frankenphp/

# 3) Dokończ konfigurację: autoloader, env=prod, post-install-cmd, cache warmup
RUN set -eux; \
    mkdir -p var/cache var/log; \
    composer dump-autoload --optimize --classmap-authoritative --no-dev; \
    composer dump-env prod; \
    composer run-script --no-dev post-install-cmd; \
    php bin/console cache:clear --env=prod --no-debug; \
    php bin/console cache:warmup --env=prod --no-debug; \
    chmod +x bin/console; \
    sync
