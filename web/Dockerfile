# syntax=docker/dockerfile:1
# web/Dockerfile — needs named contexts: frankenphp, bun, composer, php-extension-installer
# (see .docker/docker-compose.yml)


# ===============================
# Base PHP + Extensions (Shared)
# ===============================
FROM frankenphp AS base

COPY --from=php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

# pdo_pgsql → Vehicle, Job, Appointment, Invoice, Supplier Bill entities
# redis     → Valkey pub/sub (notifications, SSE streams, session tokens)
# gd        → Vehicle Damage.Picture, User.Picture
# iconv     → required by symfony/polyfill-mbstring (php-cs-fixer dep)
RUN install-php-extensions \
   gd \
   iconv \
   pdo_pgsql \
   redis-6.3.0

WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1
EXPOSE 80



# ===============================
# Development
# ===============================
FROM base AS dev

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

COPY --from=assets /app/web/public/assets ./web/public/assets

CMD ["frankenphp", "run", "--config", "/app/web/config/Caddyfile.prod"]
