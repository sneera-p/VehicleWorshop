# syntax=docker/dockerfile:1
# worker/Dockerfile — needs named contexts: php, composer, php-extension-installer
# (see .docker/docker-compose.yml)


# ===============================
# Base PHP + Extensions (Shared)
# ===============================
FROM php AS base

COPY --from=php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

RUN install-php-extensions \
   iconv \
   pdo_pgsql \
   redis-6.3.0

WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1



# ===============================
# Development
# ===============================
FROM base AS dev

# Xdebug: dev only. Idle until triggered.
RUN install-php-extensions xdebug
COPY worker/xdebug.ini ${PHP_INI_DIR}/conf.d/99-xdebug.ini

# No web/: nothing here serves HTTP. console/ stays — it runs from this
# image via `docker compose exec worker php console/console <command>`.
COPY --exclude=web . .

# With dev deps (PHPUnit, php-cs-fixer). No --optimize-autoloader:
# the bind mount should pick up new classes live.
RUN composer install \
   --prefer-dist \
   --no-scripts \
   && rm -rf "$(composer config cache-dir)"

CMD ["php", "worker/main.php"]



# ===============================
# Production
# ===============================
FROM base AS prod

# Runtime only: no web, tests, or tooling.
COPY --exclude=web \
   --exclude=**/test \
   --exclude=package.json \
   --exclude=bun.lock* \
   --exclude=tsconfig.json \
   --exclude=.tools \
   . .

RUN composer install \
   --no-dev \
   --no-scripts \
   --prefer-dist \
   --optimize-autoloader \
   && rm -rf "$(composer config cache-dir)"

CMD ["php", "worker/main.php"]
