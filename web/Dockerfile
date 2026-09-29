# syntax=docker/dockerfile:1
# web/Dockerfile — needs named contexts: frankenphp, bun, composer, php-extension-installer
# (see .docker/docker-compose.yml)


# ===============================
# Base PHP + Extensions (Shared)
# ===============================
FROM frankenphp AS base

# pdo_pgsql → Vehicle, Job, Appointment, Invoice, Supplier Bill entities
# redis     → Valkey pub/sub (notifications, SSE streams, session tokens)
# gd        → Vehicle Damage.Picture, User.Picture
RUN --mount=type=bind,from=php-extension-installer,source=/usr/bin/install-php-extensions,target=/usr/local/bin/install-php-extensions \
   IPE_GD_WITHOUTAVIF=1 \
   install-php-extensions \
      gd \
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
EXPOSE 80



# ===============================
# Development
# ===============================
FROM base AS dev

COPY --from=php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

# netcat: port/connectivity checks. libstdc++/libgcc: the bun binary needs them.
RUN apk add --no-cache \
   netcat-openbsd \
   libgcc \
   libstdc++

# Xdebug: dev only. Idle until triggered.
RUN install-php-extensions xdebug
COPY web/xdebug.ini ${PHP_INI_DIR}/conf.d/99-xdebug.ini

# worker/ and console/ have their own image.
COPY --exclude=worker \
   --exclude=console \
   . .

RUN composer install \
   --prefer-dist \
   --no-scripts \
   && rm -rf "$(composer config cache-dir)"

# Bun: builds TS + SCSS into web/public/assets/. One root package.json,
# one root node_modules/.
COPY --from=bun /usr/local/bin/bun /usr/local/bin/bun
RUN bun install --frozen-lockfile

# Runs FrankenPHP and the asset watcher together.
RUN chmod +x ./web/dev.start.sh
CMD ["sh", "./web/dev.start.sh"]



# ===============================
# Asset Build (prod assets only)
# ===============================
# Its own stage, so Bun and node_modules never reach the prod image.
FROM bun AS assets
WORKDIR /app

# Deps first, so this layer survives source edits.
COPY package.json bun.lock* ./
RUN bun install --frozen-lockfile

COPY tsconfig.json ./
COPY web/resources ./web/resources
RUN bun run build:web



# ===============================
# Production
# ===============================
FROM base AS prod

ENV FRANKENPHP_WORKER=1

# Runtime only: no frontend sources, tests, or tooling.
COPY --exclude=worker \
   --exclude=console \
   --exclude=web/resources/ts \
   --exclude=web/resources/scss \
   --exclude=web/resources/build*.ts \
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

COPY --from=assets /app/web/public/assets ./web/public/assets

CMD ["frankenphp", "run", "--config", "/app/web/config/Caddyfile.prod"]
