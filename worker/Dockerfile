# syntax=docker/dockerfile:1
# worker/Dockerfile — needs named contexts: php, composer, php-extension-installer
# (see .docker/docker-compose.yml)


# ===============================
# Base PHP + Extensions (Shared)
# ===============================
FROM php AS base

# pdo_pgsql → Vehicle, Job, Appointment, Invoice, Supplier Bill entities
# redis     → Valkey pub/sub (notifications, SSE streams, session tokens)
RUN --mount=type=bind,from=php-extension-installer,source=/usr/bin/install-php-extensions,target=/usr/local/bin/install-php-extensions \
   install-php-extensions \
      pdo_pgsql \
      redis-6.3.0

# CBOR extension: not packaged for Alpine, so build it from source.
RUN apk add --no-cache --virtual .cbor-build $PHPIZE_DEPS \
   && mkdir -p /tmp/cbor \
   && curl -fsSL "https://codeload.github.com/ranvis/php-ext-cbor/tar.gz/refs/tags/v0.4.11" \
      | tar -xz -C /tmp/cbor --strip-components=1 \
   && cd /tmp/cbor \
   && phpize && ./configure --enable-cbor && make -j"$(nproc)" && make install \
   && docker-php-ext-enable cbor \
   && cd / && rm -rf /tmp/cbor \
   && apk del .cbor-build

WORKDIR /app



# ===============================
# Development
# ===============================
FROM base AS dev

COPY --from=php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

# Xdebug: dev only. Idle until triggered.
RUN install-php-extensions xdebug
COPY worker/xdebug.ini ${PHP_INI_DIR}/conf.d/99-xdebug.ini

# No web/: nothing here serves HTTP. console/ stays — it runs from this
# image via `docker compose exec worker php console/main.php <command>`.
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
   --exclude=package*.json \
   --exclude=bun.lock* \
   --exclude=tsconfig.json \
   --exclude=**/xdebug.ini \
   --exclude=.tools \
   . .

RUN --mount=type=bind,from=composer,source=/usr/bin/composer,target=/usr/local/bin/composer \
   COMPOSER_ALLOW_SUPERUSER=1 \
   composer install \
      --no-dev \
      --no-scripts \
      --prefer-dist \
      --optimize-autoloader \
   && rm -rf "$(COMPOSER_ALLOW_SUPERUSER=1 composer config cache-dir)"  \
   && rm composer.json composer.lock

CMD ["php", "worker/main.php"]
