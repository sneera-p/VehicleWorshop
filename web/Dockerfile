# syntax=docker/dockerfile:1

ARG BUN_IMAGE=oven/bun:1.3-debian@sha256:9dba1a1b43ce28c9d7931bfc4eb00feb63b0114720a0277a8f939ae4dfc9db6f
ARG FRANKENPHP_IMAGE=dunglas/frankenphp:1.12.7-php8.5-trixie@sha256:4b0713ddad6ca7eb21eb82ac6bdb7cb41de5192a930b615d89af6e15d74e82f8
ARG PLAYWRIGHT_IMAGE=mcr.microsoft.com/playwright:v1.62.1-noble



# ===============================
# Bun (shared source)
# ===============================
# This stage exists purely as a bridge for `COPY --from`.
# Docker can be fussy about resolving `ARG` variables directly in copy instructions.
# By anchoring `BUN_IMAGE` to a single named stage here, downstream stages get a clean,
# reliable target—and we keep our pinned digest in exactly one place.
FROM ${BUN_IMAGE} as bun



# ===============================
# Base PHP + Extensions (Shared)
# ===============================
FROM ${FRANKENPHP_IMAGE} AS base

# Extensions mapped directly from ER & Activity diagrams:
# pdo_pgsql    → Vehicle, Job, Appointment, Invoice, Supplier Bill entities
# redis        → Valkey pub/sub (notifications, SSE streams, session tokens)
# gd           → Vehicle Damage.Picture, User.Picture (ER diagram)
# opcache      → Production performance
# iconv        → required by symfony/polyfill-mbstring (php-cs-fixer dep)
RUN install-php-extensions \
   pdo_pgsql \
   redis \
   gd \
   opcache \
   iconv \
   @composer

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1

EXPOSE 80



# ===============================
# Development
# ===============================
FROM base AS dev

# netcat: dev-only debugging aid (port/connectivity checks). Not needed in prod.
RUN apt-get update \
   && apt-get install -y --no-install-recommends netcat-traditional \
   && rm -rf /var/lib/apt/lists/*

# Xdebug is dev-only — significant runtime cost, never ships to prod.
# Idle by default (start_with_request=trigger); only activates on demand.
RUN install-php-extensions xdebug
RUN { \
   echo "xdebug.mode=debug,develop"; \
   echo "xdebug.start_with_request=trigger"; \
   echo "xdebug.client_host=localhost"; \
   echo "xdebug.client_port=9003"; \
   echo "xdebug.log_level=0"; \
   } > "${PHP_INI_DIR}/conf.d/99-xdebug.ini"

# worker/ and console/ are excluded — they're built from their own Dockerfile
# and never run inside this container. The root composer.json resolves
# shared/, domain/, and web/ together into ONE vendor/ here.
COPY --exclude=worker \
   --exclude=console \
   . .

RUN composer install \
   --prefer-dist \
   --no-scripts

# Bun: frontend asset toolchain (TypeScript + SCSS bundling into public/).
# Copied from the official image rather than curl-installed — keeps the
# version pinned and avoids a network fetch during build.
# The root package.json declares "workspaces": ["web"], so this ONE
# `bun install` resolves web/'s dependencies too, into a single root
# node_modules/ — web/node_modules/ never gets created.
COPY --from=bun /usr/local/bin/bun /usr/local/bin/bun
RUN bun install --frozen-lockfile

# Runs both FrankenPHP and Bun's asset watcher concurrently. NOT done as
# CMD ["bun","run","dev","&","frankenphp",...] — exec-form CMD never invokes a shell.
RUN chmod +x ./web/dev.start.sh
CMD ["sh", "./web/dev.start.sh"]



# ===============================
# Asset Build (prod assets only)
# ===============================
# Separate stage so Bun and node_modules never land in the prod image —
# only the compiled output in web/public/assets/ gets copied forward.
FROM bun AS assets
WORKDIR /app

# Only the two package.json files + lockfile are needed to resolve deps —
# copying source separately below means this layer stays cached across
# most source edits.
COPY package.json bun.lock* ./
COPY web/package.json ./web/
RUN bun install --frozen-lockfile

COPY web/tsconfig.json ./web/tsconfig.json
COPY web/resources ./web/resources

RUN bun run build



# ===============================
# E2E Testing (Playwright + Bun) — web's own e2e/, per-deliverable tier
# ===============================
FROM ${PLAYWRIGHT_IMAGE} AS playwright
WORKDIR /app

COPY --from=bun /usr/local/bin/bun /usr/local/bin/bun

COPY package.json bun.lock* ./
COPY web/package.json ./web/
RUN bun install --frozen-lockfile

COPY web/tsconfig.json ./web/tsconfig.json
COPY web/test/e2e ./web/test/e2e

# No CMD that runs tests — this image just sits ready. docker-compose.e2e.yml
# invokes `playwright test` against it explicitly, once web's healthcheck passes.
CMD ["tail", "-f", "/dev/null"]



# ===============================
# Production
# ===============================
FROM base AS prod

ENV FRANKENPHP_WORKER=1

# Everything frontend/test/tooling-related is excluded — none of it should
# ever exist inside the prod runtime image. Bun itself was never installed
# in this stage; the compiled asset output arrives separately below, via
# COPY --from=assets.
COPY --exclude=worker \
   --exclude=console \
   --exclude=web/resources/ts \
   --exclude=web/resources/scss \
   --exclude=web/tests \
   --exclude=package.json \
   --exclude=bun.lock* \
   --exclude=.tools \
   . .

RUN composer install \
   --no-dev \
   --no-scripts \
   --prefer-dist \
   --optimize-autoloader

COPY --from=assets /app/web/public/assets ./web/public/assets

CMD ["frankenphp", "run", "--config", "/app/web/config/Caddyfile.prod"]
